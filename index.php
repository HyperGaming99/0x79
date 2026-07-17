<?php
declare(strict_types=1);

// Let PHP's development server deliver existing assets instead of routing them
// through the application. Production web servers already handle this.
if (PHP_SAPI === 'cli-server') {
    $staticPath = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (str_starts_with(basename($staticPath), '.')) {
        http_response_code(404);
        exit('not found');
    }
    if (is_file($staticPath)) {
        return false;
    }
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/views.php';
require_once __DIR__ . '/qr.php';

if (!function_exists('toolEnabled')) {
function toolEnabled(string $tool): bool {
    $variables = [
        'shortener'    => 'TOOL_SHORTENER_ENABLED',
        'upload'       => 'TOOL_UPLOAD_ENABLED',
        'paste'        => 'TOOL_PASTE_ENABLED',
        'music'        => 'TOOL_MUSIC_ENABLED',
        'metadata'     => 'TOOL_METADATA_ENABLED',
        'secure_share' => 'TOOL_SECURE_SHARE_ENABLED',
        'discord'      => 'TOOL_DISCORD_ENABLED',
        'minecraft'    => 'TOOL_MINECRAFT_ENABLED',
    ];

    if (!isset($variables[$tool])) return false;
    $value = getenv($variables[$tool]);
    if ($value === false || trim((string)$value) === '') return true;

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}
}

$request_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

$toolRoutes = [
    'shorten'          => 'shortener',
    'upload'           => 'upload',
    'paste'            => 'paste',
    'music'            => 'music',
    'metadata'         => 'metadata',
    'secure-share'     => 'secure_share',
    'discord'          => 'discord',
    'discord-card.svg' => 'discord',
    'minecraft'        => 'minecraft',
    'api/discord'      => 'discord',
    'api/minecraft'    => 'minecraft',
    'api/music'        => 'music',
    'api/create-music' => 'music',
    'api/paste'        => 'paste',
    'api/create-paste' => 'paste',
    'api/file'         => 'upload',
    'api/upload-file'  => 'upload',
    'api/image'        => 'upload',
    'api/upload-image' => 'upload',
];
if (isset($toolRoutes[$request_path]) && !toolEnabled($toolRoutes[$request_path])) {
    http_response_code(404);
    if (str_starts_with($request_path, 'api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'tool_disabled']);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'not found';
    }
    exit;
}

// Backwards-compatible logo path used across the older views.
if ($request_path === 'logo.png') {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    readfile(__DIR__ . '/logomark_0x79.jpg');
    exit;
}

// Same-origin QR code as SVG: /qr?d=<data>  (used by success pages & account)
if ($request_path === 'qr') {
    $d = (string)($_GET['d'] ?? '');
    if ($d === '' || strlen($d) > 512) { http_response_code(400); header('Content-Type: text/plain'); exit('bad qr data'); }
    $svg = qrSvg($d, 8, 4);
    if ($svg === '') { http_response_code(413); header('Content-Type: text/plain'); exit('qr data too long'); }
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    echo $svg;
    exit;
}

if ($request_path === 'preview-asset') {
    streamPreviewAsset();
}

if ($request_path === 'discord-asset') {
    streamDiscordAsset();
}

if ($request_path === 'discord-app-icon') {
    streamDiscordApplicationIcon();
}

// Same-origin image proxy for hosted Supabase images (keeps img-src CSP strict).
if ($request_path === 'img') {
    $proxyTarget = (string)($_GET['u'] ?? '');
    if (!isHostedImageStorageUrl($proxyTarget)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'not found';
        exit;
    }
    proxyHostedFile($proxyTarget);
    exit;
}

if ($request_path === 'rss') {
    require_once __DIR__ . '/rss.php';
    exit;
}

if ($request_path === 'tools') {
    renderToolsDashboardPage();
}

if ($request_path === 'status') {
    renderStatusPage();
}

if ($request_path === 'discord') {
    $discordId = trim((string)($_GET['user_id'] ?? ''));
    $discordError = '';
    $discordPresence = null;
    if ($discordId !== '') {
        if (!rateLimit('discord_presence', 30, 60)) {
            $discordError = 'rate_limited';
        } else {
            [$discordOk, $discordError, $discordPresence] = fetchDiscordPresence($discordId);
            if (!$discordOk) $discordPresence = null;
        }
    }
    renderDiscordTrackerPage($discordPresence, $discordError, $discordId, discordCardOptions($_GET));
}

if ($request_path === 'discord-card.svg') {
    $discordId = trim((string)($_GET['user_id'] ?? ''));
    $discordError = '';
    $discordPresence = null;
    if (!rateLimit('discord_readme_card', 120, 60)) {
        $discordError = 'rate_limited';
    } else {
        [$discordOk, $discordError, $discordPresence] = fetchDiscordPresence($discordId);
        if (!$discordOk) $discordPresence = null;
    }
    renderDiscordReadmeCardSvg($discordPresence, $discordError, $discordId, discordCardOptions($_GET));
}

if ($request_path === 'minecraft') {
    $minecraftAddress = trim((string)($_GET['server'] ?? ''));
    $minecraftError = ''; $minecraftStatus = null;
    if ($minecraftAddress !== '') {
        if (!rateLimit('minecraft_status', 20, 60)) $minecraftError = 'rate_limited';
        else {
            [$minecraftOk, $minecraftError, $minecraftStatus] = fetchMinecraftStatus($minecraftAddress);
            if (!$minecraftOk) $minecraftStatus = null;
        }
    }
    renderMinecraftTrackerPage($minecraftStatus, $minecraftError, $minecraftAddress);
}

if ($request_path === 'posts') {
    $posts = fetchRssPosts(100);
    renderAllPostsPage($posts);
    exit;
}

if (preg_match('#^posts?/([0-9]+)$#', $request_path, $m)) {
    $postId = (int)$m[1];
    $post = fetchPostById($postId);
    renderPostPage($post);
    exit;
}




// ---------------------------------------------------------
// NORMAL USER LOGIN / REGISTER / ACCOUNT
// ---------------------------------------------------------
if ($request_path === 'register') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!rateLimit('register', 5, 3600)) {
            renderUserAuthPage('register', 'zu viele registrierungen. bitte später erneut versuchen.');
        }
        [$okUser, $userErr] = createUserAccount($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($okUser) { header('Location: /account'); exit; }
        $msg = $userErr === 'username_taken' ? 'username ist bereits vergeben.' : ($userErr === 'weak_password' ? 'passwort braucht mindestens 8 zeichen.' : ($userErr === 'invalid_username' ? 'username ungültig: 3-32 zeichen, nur a-z, 0-9, . _ -' : 'account konnte nicht erstellt werden. migration ausgeführt?'));
        renderUserAuthPage('register', $msg);
    }
    renderUserAuthPage('register');
}

if ($request_path === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!rateLimit('login', 10, 900)) {
            renderUserAuthPage('login', 'zu viele login-versuche. bitte ein paar minuten warten.');
        }
        [$okUser, $userErr] = loginUserAccount($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($okUser) { header('Location: /account'); exit; }
        renderUserAuthPage('login', 'username oder passwort falsch.');
    }
    renderUserAuthPage('login');
}

if ($request_path === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireUserCsrf();
    }
    unset($_SESSION['user_id'], $_SESSION['last_api_key'], $_SESSION['user_csrf']);
    header('Location: /');
    exit;
}

if ($request_path === 'account') {
    renderUserAccountPage($_GET['notice'] ?? '');
}

if ($request_path === 'account/stats') {
    if (!isUserLoggedIn()) { header('Location: /login'); exit; }
    $code = (string)($_GET['code'] ?? '');
    if (!preg_match('/^[A-Za-z0-9]{1,32}$/', $code) || !linkOwnedByUser($code, currentUserId())) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('not found');
    }
    renderLinkStatsPage($code, fetchRecentClicks($code));
}

if ($request_path === 'account/action') {
    if (!isUserLoggedIn()) { header('Location: /login'); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('method not allowed'); }
    requireUserCsrf();
    $action = (string)($_POST['action'] ?? '');
    $uid = currentUserId();
    if ($action === 'delete_link') {
        deleteOwnedLinkById($_POST['id'] ?? '', $uid);
        header('Location: /account?notice=' . rawurlencode('link gelöscht'));
        exit;
    }
    if ($action === 'edit_link') {
        [$okEdit, $editErr] = updateOwnedLink($_POST['id'] ?? '', $uid, [
            'long_url'       => $_POST['long_url'] ?? '',
            'expires_at'     => $_POST['expires_at'] ?? '',
            'max_clicks'     => $_POST['max_clicks'] ?? '',
            'password'       => $_POST['password'] ?? '',
            'clear_password' => !empty($_POST['clear_password']),
        ]);
        $msg = $okEdit ? 'link aktualisiert' : ($editErr === 'invalid_url' ? 'ziel-url ungültig' : ($editErr === 'invalid_expiry' ? 'ablaufdatum liegt in der vergangenheit' : ($editErr === 'no_changes' ? 'keine änderungen' : 'speichern fehlgeschlagen')));
        header('Location: /account?notice=' . rawurlencode($msg));
        exit;
    }
    if ($action === 'delete_paste') {
        deleteOwnedPasteById($_POST['id'] ?? '', $uid);
        header('Location: /account?notice=' . rawurlencode('paste gelöscht'));
        exit;
    }
    if ($action === 'regen_api_key') {
        regenerateUserApiKey($uid);
        header('Location: /account?notice=' . rawurlencode('api-key neu erstellt'));
        exit;
    }
    header('Location: /account');
    exit;
}


// ---------------------------------------------------------
// PASTE HOST
// ---------------------------------------------------------
if ($request_path === 'paste') {
    $pasteError = '';
    $pasteUrl = '';
    $rawUrl = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!checkCreateRateLimit(10, 3600)) {
            $pasteError = pasteErrorText('rate_limited', $t);
        } else {
            if (isset($_POST['domain']) && in_array($_POST['domain'], $available_domains, true)) {
                $selected_domain = $_POST['domain'];
            }

            $content = (string)($_POST['paste_content'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $expires_at = (string)($_POST['expires_at'] ?? '');
            $max_views = (string)($_POST['max_views'] ?? '');
            $custom_code = (string)($_POST['custom_code'] ?? '');

            [$okPaste, $pasteErr, $result] = createPaste($content, $selected_domain, $password, $expires_at, $max_views, $custom_code, currentUserId());

            if ($okPaste) {
                $pasteUrl = $result['short_url'];
                $rawUrl = $result['raw_url'];
            } else {
                $pasteError = pasteErrorText($pasteErr, $t);
            }
        }
    }

    renderPastePage($pasteError, $pasteUrl, $rawUrl);
}

if (preg_match('#^raw/([A-Za-z0-9]{1,32})$#', $request_path, $m)) {
    $pasteCode = $m[1];
    $pasteRow = fetchPasteByCode($pasteCode);
    if (!$pasteRow) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo $t['err_notfound'] ?? 'not found';
        exit;
    }
    renderRawPaste($pasteRow, $pasteCode);
}

// ---------------------------------------------------------
// EXIF METADATA STRIPPER
// ---------------------------------------------------------
if ($request_path === 'metadata') {
    renderMetadataStripperPage();
}

// ---------------------------------------------------------
// ZERO-KNOWLEDGE ENCRYPTED PASTE
// ---------------------------------------------------------
if ($request_path === 'secure-share') {
    $error = '';
    $pasteUrl = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!checkCreateRateLimit(10, 3600)) {
            $error = $t['err_rate_limit'] ?? 'Rate limit reached.';
        } else {
            $content = (string)($_POST['paste_content'] ?? '');
            $expires_at = (string)($_POST['expires_at'] ?? '');
            $max_views = (string)($_POST['max_views'] ?? '');
            $custom_code = (string)($_POST['custom_code'] ?? '');

            if (isset($_POST['domain']) && in_array($_POST['domain'], $available_domains, true)) {
                $selected_domain = $_POST['domain'];
            } else {
                $selected_domain = $available_domains[0];
            }

            [$okPaste, $pasteErr, $result] = createPaste($content, $selected_domain, '', $expires_at, $max_views, $custom_code, currentUserId());

            if ($okPaste) {
                $pasteUrl = $result['short_url'];
            } else {
                if ($pasteErr === 'paste_too_large') {
                    $error = $t['err_file_size'] ?? 'Content too large.';
                } elseif ($pasteErr === 'invalid_alias') {
                    $error = $t['err_alias'];
                } elseif ($pasteErr === 'alias_taken') {
                    $error = $t['err_alias_taken'];
                } else {
                    $error = $t['err_save'];
                }
            }
        }

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isAjax) {
            header('Content-Type: application/json');
            if ($error !== '') {
                echo json_encode(['success' => false, 'error' => $error]);
            } else {
                echo json_encode(['success' => true, 'short_url' => $pasteUrl]);
            }
            exit;
        }
    }

    renderSecureSharePage($error, $pasteUrl);
}


// ---------------------------------------------------------
// MUSIC PROMOTER
// ---------------------------------------------------------
if ($request_path === 'music') {
    $musicError = '';
    $musicShortUrl = '';
    $musicLandingUrl = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['domain']) && in_array($_POST['domain'], $available_domains, true)) {
            $selected_domain = $_POST['domain'];
        }

        if (!checkCreateRateLimit()) {
            $musicError = musicErrorText('rate_limited');
        } else {
            [$okCover, $coverErr, $coverUrl]   = uploadMusicImageFromField('cover_image');
            [$okBanner, $bannerErr, $bannerUrl] = uploadMusicImageFromField('banner_image');

            if (!$okCover) {
                $musicError = musicErrorText($coverErr);
            } elseif (!$okBanner) {
                $musicError = musicErrorText($bannerErr);
            } else {
                $linksInput = $_POST['links'] ?? [];
                if (!is_array($linksInput)) $linksInput = [];

                [$okMusic, $musicErr, $result] = createMusicPromo(
                    $_POST['title']       ?? '',
                    $_POST['artist']      ?? '',
                    $linksInput,
                    $selected_domain,
                    $_POST['password']    ?? '',
                    $_POST['expires_at']  ?? '',
                    $_POST['max_clicks']  ?? '',
                    $_POST['custom_code'] ?? '',
                    currentUserId(),
                    (string)($coverUrl ?? ''),
                    (string)($bannerUrl ?? '')
                );

                if ($okMusic) {
                    $musicShortUrl   = $result['short_url'];
                    $musicLandingUrl = $result['music_url'];
                } else {
                    $musicError = musicErrorText($musicErr);
                }
            }
        }
    }

    renderMusicPromoterPage($musicError, $musicShortUrl, $musicLandingUrl);
}

if (preg_match('#^music/([A-Za-z0-9]{1,32})$#', $request_path, $m)) {
    $musicRow = fetchMusicPromoByCode($m[1]);
    if (!$musicRow || isExpiredRow($musicRow)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'music promo not found or expired';
        exit;
    }
    incrementMusicPromoViews($musicRow);
    renderMusicLandingPage($musicRow);
}

// ---------------------------------------------------------
// API DOCS
// ---------------------------------------------------------
if ($request_path === 'api/docs') {
    renderApiDocs();
}

// ---------------------------------------------------------
// DISCORD PRESENCE API (Lanyard-compatible response shape)
// GET /api/discord?user_id=...
// ---------------------------------------------------------
if ($request_path === 'api/discord') {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Allow: GET, OPTIONS');
        http_response_code(204);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, OPTIONS');
        jsonResponse(['success' => false, 'error' => 'method_not_allowed'], 405);
    }
    if (!rateLimit('discord_api', 30, 60)) {
        jsonResponse(['success' => false, 'error' => 'rate_limited'], 429);
    }
    $userId = trim((string)($_GET['user_id'] ?? $_GET['id'] ?? ''));
    [$ok, $error, $presence] = fetchDiscordPresence($userId);
    if (!$ok) {
        $status = match ($error) {
            'invalid_id' => 400,
            'not_found' => 404,
            'not_configured', 'unavailable' => 503,
            default => 500,
        };
        $payload = ['success' => false, 'error' => $error];
        if ($error === 'not_found') {
            $payload['message'] = 'User is not on the configured Discord server.';
            $payload['discord_url'] = discordInviteUrl();
        }
        jsonResponse($payload, $status);
    }
    jsonResponse(['success' => true, 'data' => $presence]);
}

// GET /api/minecraft?server=play.example.net[:port]
if ($request_path === 'api/minecraft') {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { header('Allow: GET, OPTIONS'); http_response_code(204); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { header('Allow: GET, OPTIONS'); jsonResponse(['success' => false, 'error' => 'method_not_allowed'], 405); }
    if (!rateLimit('minecraft_api', 30, 60)) jsonResponse(['success' => false, 'error' => 'rate_limited'], 429);
    [$ok, $error, $status] = fetchMinecraftStatus(trim((string)($_GET['server'] ?? '')));
    if (!$ok) {
        $http = in_array($error, ['invalid_address', 'invalid_port', 'blocked_host'], true) ? 400 : ($error === 'offline' ? 404 : 502);
        jsonResponse(['success' => false, 'error' => $error], $http);
    }
    jsonResponse(['success' => true, 'data' => $status]);
}

// ---------------------------------------------------------
// FILE UPLOADER
// ---------------------------------------------------------
if ($request_path === 'upload') {
    $uploadError = '';
    $uploadShortUrl = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $isXhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

        if (isset($_POST['domain']) && in_array($_POST['domain'], $available_domains, true)) {
            $selected_domain = $_POST['domain'];
        }

        if (!checkCreateRateLimit()) {
            $uploadError = uploadErrorText('rate_limited', $t);
        } else {
            [$okUpload, $uploadErr, $uploaded] = uploadToSupabaseStorage($_FILES['upload_file'] ?? null);

            if (!$okUpload) {
                $uploadError = uploadErrorText($uploadErr, $t);
            } else {
                $password = $_POST['password'] ?? '';
                $expires_at = $_POST['expires_at'] ?? '';
                $max_clicks = $_POST['max_clicks'] ?? '';
                $custom_code = $_POST['custom_code'] ?? '';

                [$okLink, $linkErr, $result] = createShortLink($uploaded['public_url'], $selected_domain, $password, $expires_at, $max_clicks, $custom_code, currentUserId());

                if ($okLink) {
                    $uploadShortUrl = $result['short_url'];
                } else {
                    $uploadError = uploadErrorText($linkErr, $t);
                }
            }
        }

        if ($isXhr) {
            if ($uploadShortUrl !== '') {
                jsonResponse(['ok' => true, 'short_url' => $uploadShortUrl], 201);
            } else {
                jsonResponse(['ok' => false, 'error_text' => $uploadError], 400);
            }
        }
    }

    renderUploadPage($uploadError, $uploadShortUrl);
}

// ---------------------------------------------------------
// ADMIN LOGIN + DASHBOARD
// ---------------------------------------------------------

if ($request_path === 'admin/edit') {
    requireAdminSession();
    renderAdminEdit($_GET['id'] ?? '');
}

if ($request_path === 'admin/action') {
    requireAdminSession();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('method not allowed');
    }

    requireAdminCsrf();

    $action = (string)($_POST['action'] ?? '');
    $ok = false;
    $status = 400;
    $notice = '';

    if ($action === 'delete_link') {
        [$ok, $status] = deleteLinkById($_POST['link_id'] ?? '');
        $notice = 'link gelöscht';
    } elseif ($action === 'delete_link_by_code') {
        [$ok, $status] = deleteLinkByCode($_POST['code'] ?? '');
        $notice = 'link gelöscht';
    } elseif ($action === 'delete_report') {
        [$ok, $status] = deleteAbuseReportById($_POST['report_id'] ?? '');
        $notice = 'report gelöscht';
    } elseif ($action === 'delete_paste') {
        [$ok, $status] = deletePasteById($_POST['paste_id'] ?? '');
        $notice = 'paste gelöscht';
    } elseif ($action === 'publish_post') {
        $title = (string)($_POST['title'] ?? '');
        $description = (string)($_POST['description'] ?? '');
        $image = (string)($_POST['image_url'] ?? '');
        $uploadFailed = false;

        // Handle file upload
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$okUpload, $uploadErr, $uploadedUrl] = uploadToSupabaseStorage($_FILES['image_file']);
            if ($okUpload && $uploadedUrl) {
                $image = $uploadedUrl;
            } else {
                $uploadFailed = true;
                $status = 400;
                $notice = 'Bild-Upload fehlgeschlagen: ' . ($uploadErr ?? 'unbekannt');
            }
        }

        if (!$uploadFailed) {
            $pubDateStr = (string)($_POST['pub_date'] ?? '');
            $pubDate = ($pubDateStr !== '') ? strtotime($pubDateStr) : time();
            if ($pubDate === false || $pubDate === -1) {
                $pubDate = time();
            }

            [$ok, $errCode] = createPost($title, $description, $image !== '' ? $image : null, $pubDate);
            $status = $ok ? 200 : 500;
            $notice = $ok ? 'Beitrag veröffentlicht' : 'Fehler beim Veröffentlichen: ' . ($errCode ?? 'unbekannt');
        }
    } elseif ($action === 'delete_post') {
        [$ok, $status] = deletePostById($_POST['post_id'] ?? '');
        $notice = 'Beitrag gelöscht';
    } elseif ($action === 'update_link') {

        [$ok, $status] = updateLinkById(
            $_POST['link_id'] ?? '',
            $_POST['long_url'] ?? '',
            $_POST['short_code'] ?? '',
            $_POST['password'] ?? '',
            !empty($_POST['remove_password']),
            $_POST['expires_at'] ?? '',
            $_POST['max_clicks'] ?? ''
        );
        $notice = 'link gespeichert';
    } elseif ($action === 'update_protocols') {
        $custom = normalizeSchemeList($_POST['custom_schemes'] ?? []);
        $newScheme = strtolower(rtrim(trim((string)($_POST['new_scheme'] ?? '')), ':'));
        $removeCustom = normalizeSchemeList($_POST['remove_custom_schemes'] ?? []);

        if ($newScheme !== '') {
            if (!isValidConfigurableScheme($newScheme)) {
                $ok = false;
                $status = 400;
                $notice = 'ungültiges protokoll';
            } else {
                $custom[] = $newScheme;
                $_POST['schemes'][] = $newScheme;
            }
        }

        if ($ok !== false) {
            $custom = array_values(array_filter(normalizeSchemeList($custom), function ($scheme) use ($removeCustom) {
                return !in_array($scheme, $removeCustom, true);
            }));

            $schemes = array_values(array_filter((array)($_POST['schemes'] ?? []), function ($scheme) use ($removeCustom) {
                $scheme = strtolower(rtrim(trim((string)$scheme), ':'));
                return !in_array($scheme, $removeCustom, true);
            }));

            [$saved, $saveErr] = saveProtocolConfig($schemes, $custom);
            $ok = $saved;
            $status = $saved ? 200 : 500;
            $notice = 'protokolle gespeichert';
            if (!$saved && $saveErr) {
                $notice = $saveErr;
            }
        }
    }

    $returnTo = sanitizeAdminReturnTo($_POST['return_to'] ?? '/admin');
    $sep = str_contains($returnTo, '?') ? '&' : '?';

    if ($ok) {
        header('Location: ' . $returnTo . $sep . 'notice=' . urlencode($notice));
        exit;
    }

    header('Location: ' . $returnTo . $sep . 'error=' . urlencode('aktion fehlgeschlagen: status ' . $status));
    exit;
}

if ($request_path === 'admin/logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
    header('Location: /admin');
    exit;
}

if ($request_path === 'admin') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        global $admin_password;
        $provided = trim((string)($_POST['admin_password'] ?? ''));

        if ($admin_password && hash_equals($admin_password, $provided)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_at'] = time();
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            header('Location: /admin');
            exit;
        }

        renderAdminLogin($t['admin_invalid']);
    }

    if (isAdminLoggedIn()) {
        renderAdminDashboard();
    }

    renderAdminLogin();
}

// ---------------------------------------------------------
// HIDDEN ADMIN ENDPOINT
// GET /api/admin/links        JSON
// GET /api/admin/links.csv    CSV Export
// Authorization: Bearer ADMIN_API_KEY oder Admin-Session
// NICHT in den Docs sichtbar
// ---------------------------------------------------------
if ($request_path === 'api/admin/links' || $request_path === 'api/admin/links.csv') {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Allow: GET, OPTIONS');
        http_response_code(204);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, OPTIONS');
        jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }

    requireAdminAuth();

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    [$ok, $links, $status] = fetchAdminLinks($limit, $offset);

    if (!$ok) {
        jsonResponse([
            'ok' => false,
            'error' => 'supabase_error',
            'status' => $status
        ], 500);
    }

    if ($request_path === 'api/admin/links.csv' || (($_GET['format'] ?? '') === 'csv')) {
        outputLinksCsv($links);
    }

    jsonResponse([
        'ok' => true,
        'count' => count($links),
        'limit' => max(1, min((int)$limit, 500)),
        'offset' => max(0, (int)$offset),
        'links' => $links,
    ]);
}



// ---------------------------------------------------------
// MUSIC API
// POST /api/music or /api/create-music
// User API endpoint, requires X-API-Key
// JSON: {"title":"...","artist":"...","links":{"spotify":"https://open.spotify.com/..."}}
// ---------------------------------------------------------
if ($request_path === 'api/music' || $request_path === 'api/create-music') {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Allow: POST, OPTIONS');
        http_response_code(204);
        exit;
    }

    apiMusicCreateResponse();
}

// ---------------------------------------------------------
// PASTE API
// POST /api/paste or /api/create-paste
// User API endpoint, requires X-API-Key
// JSON: {"content":"..."} or form field content / text / paste_content
// ---------------------------------------------------------
if ($request_path === 'api/paste' || $request_path === 'api/create-paste') {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Allow: POST, OPTIONS');
        http_response_code(204);
        exit;
    }

    apiPasteCreateResponse();
}

// ---------------------------------------------------------
// FILE/IMAGE UPLOAD API
// POST /api/file, /api/upload-file, /api/image or /api/upload-image
// User API endpoint, requires X-API-Key
// multipart/form-data field: file / image / upload_file
// ---------------------------------------------------------
if (in_array($request_path, ['api/file', 'api/upload-file', 'api/image', 'api/upload-image'], true)) {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Allow: POST, OPTIONS');
        http_response_code(204);
        exit;
    }

    apiFileUploadResponse();
}

// ---------------------------------------------------------
// SCREENSHOT API
// GET/POST /api/screenshot
// Authorization: Bearer ADMIN_API_KEY oder Admin-Session
// ---------------------------------------------------------
if ($request_path === 'api/screenshot') {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Allow: GET, POST, OPTIONS');
        http_response_code(204);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        streamScreenshotResponse($_GET);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        streamScreenshotResponse(apiReadInput());
    }

    header('Allow: GET, POST, OPTIONS');
    jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

// ---------------------------------------------------------
// PUBLIC API
// ---------------------------------------------------------
if ($request_path === 'api') {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Allow: GET, POST, OPTIONS');
        http_response_code(204);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $code = trim((string)($_GET['code'] ?? $_GET['short_code'] ?? ''));

        if (!isValidCode($code)) {
            jsonResponse(['ok' => false, 'error' => 'invalid_code'], 400);
        }

        $row = fetchLinkByCode($code);

        if (!$row) {
            jsonResponse(['ok' => false, 'error' => 'not_found'], 404);
        }

        if (isExpiredRow($row)) {
            jsonResponse(['ok' => false, 'error' => 'expired'], 410);
        }

        if (isBurnedRow($row)) {
            jsonResponse(['ok' => false, 'error' => 'burned'], 410);
        }

        jsonResponse(['ok' => true] + normalizeLinkRow($row));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!toolEnabled('shortener')) {
            jsonResponse(['ok' => false, 'error' => 'tool_disabled'], 404);
        }
        $apiUser = requireUserApiAuth();
        $input = apiReadInput();

        $long_url = $input['long_url'] ?? $input['url'] ?? '';
        $domain = $input['domain'] ?? ($_SERVER['HTTP_HOST'] ?? $available_domains[0]);
        $password = $input['password'] ?? '';
        $expires_at = $input['expires_at'] ?? $input['valid_until'] ?? '';
        $max_clicks = $input['max_clicks'] ?? $input['burn_after'] ?? '';
        $custom_code = $input['custom_code'] ?? $input['alias'] ?? $input['short_code'] ?? '';
        $preview_enabled = !empty($input['preview_enabled'] ?? $input['preview'] ?? false);

        if (!checkCreateRateLimit()) {
            jsonResponse(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        [$ok, $err, $result] = createShortLink($long_url, $domain, $password, $expires_at, $max_clicks, $custom_code, $apiUser['id'] ?? null, $preview_enabled);

        if (!$ok) {
            $status = in_array($err, ['invalid_url', 'invalid_alias', 'invalid_expiry'], true) ? 400 : ($err === 'alias_taken' ? 409 : 500);
            jsonResponse(['ok' => false, 'error' => $err], $status);
        }

        jsonResponse(['ok' => true] + $result, 201);
    }

    header('Allow: GET, POST, OPTIONS');
    jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

// ---------------------------------------------------------
// ABUSE REPORT PAGE
// ---------------------------------------------------------
if ($request_path === 'abuse') {
    $host = cleanHost($_SERVER['HTTP_HOST'] ?? '0x79.one');
    $to = $abuse_email ?: ('abuse@' . $host);
    $reported_link = trim((string)($_POST['reported_link'] ?? $_GET['link'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $saved = false;
    $saveError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!checkCreateRateLimit(5, 3600)) {
            $saveError = 'zu viele meldungen. bitte später erneut versuchen.';
        } else {
            [$saved, $saveError] = createAbuseReport($reported_link, $reason);
        }
    }

    $subject = 'Abuse report for ' . $host;
    $body = "Reported link/code:\n" . $reported_link . "\n\nReason:\n" . $reason . "\n";
    $mailto = 'mailto:' . rawurlencode($to) . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body);

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['abuse_title']) ?> — 0x79</title>
    <link rel="icon" href="/logo.png" type="image/jpeg">
    <?php renderUiPreferences(); ?>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace; background:#0e0e10; color:#ebe9e3; padding:24px; }
        main { width:100%; max-width:520px; border:1px solid #ebe9e3; padding:24px; display:grid; gap:14px; }
        input, textarea, button, a.btn { font:inherit; padding:12px; border:1px solid #ebe9e3; }
        input, textarea { background:transparent; color:#ebe9e3; width:100%; }
        textarea { min-height:120px; resize:vertical; }
        button, a.btn { background:#ebe9e3; color:#0e0e10; cursor:pointer; text-decoration:none; display:inline-block; }
        a { color:#ebe9e3; }
        p { color:#a9a59c; }
        label { display:grid; gap:8px; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .ok { color:#5dd07a; }
        .err { color:#ff6b6b; }
    </style>
</head>
<body>
    <main>
        <h1><?= h($t['abuse_title']) ?></h1>
        <p><?= h($t['abuse_text']) ?></p>
        <?php if ($saved): ?>
            <p class="ok">meldung gespeichert. danke.</p>
        <?php elseif ($saveError !== ''): ?>
            <p class="err">meldung konnte nicht gespeichert werden.</p>
        <?php endif; ?>
        <form method="POST" action="/abuse">
            <label><?= h($t['abuse_link_label']) ?>
                <input name="reported_link" value="<?= h($reported_link) ?>" placeholder="https://<?= h($host) ?>/Ab12Cd" required>
            </label>
            <label><?= h($t['abuse_reason_label']) ?>
                <textarea name="reason" placeholder="phishing, malware, spam …" required><?= h($reason) ?></textarea>
            </label>
            <div class="actions">
                <button type="submit"><?= h($t['abuse_submit']) ?> →</button>
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <a class="btn" href="<?= h($mailto) ?>"><?= h($t['abuse_mail']) ?> →</a>
                <?php endif; ?>
                <a href="/"><?= h($t['abuse_back']) ?></a>
            </div>
        </form>
    </main>
</body>
</html>
    <?php
    exit;
}

// ---------------------------------------------------------
// 1. REDIRECT — mit Bot-Schutz
// ---------------------------------------------------------
$path_code = '';

if ($request_path !== '' && preg_match('/^[A-Za-z0-9]{1,32}$/', $request_path) && !isReservedCode($request_path)) {
    $path_code = $request_path;
}

if ($path_code !== '' || isset($_GET['c'])) {
    $raw_code = $path_code !== '' ? $path_code : (string)($_GET['c'] ?? '');
    $code = trim($raw_code);

    if (!preg_match('/^[A-Za-z0-9]{1,32}$/', $code)) {
        http_response_code(400);
        exit('invalid code.');
    }

    $pasteRow = fetchPasteByCode($code);
    if ($pasteRow) {
        renderPasteView($pasteRow, $code);
    }

    $row = fetchLinkByCode($code);

    if (!empty($row) && isset($row['long_url'])) {
        if (isExpiredRow($row)) {
            $error = $t['err_expired'];
        } elseif (isBurnedRow($row)) {
            $error = $t['err_burned'];
        } else {
            $target = $row['long_url'];

            if (!isAllowedShortenerTarget($target)) {
                http_response_code(400);
                exit('blocked: target scheme is not allowed.');
            }

            $target = str_replace(["\r", "\n", "\0"], '', $target);
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $bot_patterns = [
                'discordbot', 'twitterbot', 'slackbot', 'facebookexternalhit', 'whatsapp',
                'telegrambot', 'linkedinbot', 'skypeuripreview', 'pinterest', 'redditbot',
                'embedly', 'quora link preview', 'showyoubot', 'outbrain', 'vkshare',
                'w3c_validator', 'bingpreview', 'googlebot', 'bitlybot', 'tumblr',
                'mattermost', 'iframely', 'snapchat',
            ];

            $is_bot = false;
            $ua_lower = strtolower($ua);

            foreach ($bot_patterns as $p) {
                if (strpos($ua_lower, $p) !== false) {
                    $is_bot = true;
                    break;
                }
            }

            $is_hosted_file = isHostedFileStorageUrl($target);

            if ($is_bot && !$is_hosted_file) {
                $host = $_SERVER['HTTP_HOST'] ?? '0x79.one';
                $self = 'https://' . $host . '/' . urlencode($code);

                header('Content-Type: text/html; charset=utf-8');
                header('X-Robots-Tag: noindex, nofollow');

                echo '<!DOCTYPE html><html lang="' . h($lang) . '"><head>'
                    . '<meta charset="UTF-8">'
                    . '<title>' . h($t['title']) . '</title>'
                    . '<meta name="description" content="' . h($t['og_desc']) . '">'
                    . '<meta name="robots" content="noindex,nofollow">'
                    . '<meta property="og:title" content="' . h($t['title']) . '">'
                    . '<meta property="og:description" content="' . h($t['og_desc']) . '">'
                    . '<meta property="og:url" content="' . h($self) . '">'
                    . '<meta property="og:type" content="website">'
                    . '<meta name="twitter:card" content="summary">'
                    . '<meta name="twitter:title" content="' . h($t['title']) . '">'
                    . '<meta name="twitter:description" content="' . h($t['og_desc']) . '">'
                    . '</head><body></body></html>';

                exit;
            }

            if (!empty($row['password_hash'])) {
                $post_password = (string)($_POST['link_password'] ?? '');
                $query_password = (string)($_GET['pw'] ?? $_GET['password'] ?? '');

                $password_ok = false;

                if ($query_password !== '') {
                    $password_ok = password_verify($query_password, (string)$row['password_hash']);
                }

                if (!$password_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals((string)($_POST['code'] ?? ''), $code)) {
                    $password_ok = password_verify($post_password, (string)$row['password_hash']);
                }

                if (!$password_ok) {
                    $password_error = ($_SERVER['REQUEST_METHOD'] === 'POST' || $query_password !== '') ? $t['err_password'] : '';

                    header('Content-Type: text/html; charset=utf-8');
                    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['title']) ?></title>
    <?php renderUiPreferences(); ?>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace; background:#0e0e10; color:#ebe9e3; padding:24px; }
        form { width:100%; max-width:420px; border:1px solid #ebe9e3; padding:24px; display:grid; gap:14px; }
        input, button { font:inherit; padding:12px; border:1px solid #ebe9e3; }
        input { background:transparent; color:#ebe9e3; }
        button { background:#ebe9e3; color:#0e0e10; cursor:pointer; }
        .err { color:#ff6b6b; }
    </style>
</head>
<body>
    <form method="POST" action="/<?= h($code) ?>">
        <h1><?= h($t['password_label']) ?></h1>
        <?php if (!empty($password_error)): ?><p class="err"><?= h($password_error) ?></p><?php endif; ?>
        <input type="hidden" name="code" value="<?= h($code) ?>">
        <input type="password" name="link_password" placeholder="<?= h($t['password_label']) ?>" required autofocus>
        <button type="submit"><?= h($t['open_link']) ?> →</button>
    </form>
</body>
</html>
                    <?php
                    exit;
                }
            }

            incrementClickCount($row);

            $refHost = strtolower((string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
            $refHost = preg_replace('/^www\./', '', $refHost);
            logLinkClick(
                $code,
                $refHost,
                detectDeviceType($_SERVER['HTTP_USER_AGENT'] ?? ''),
                strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''), 0, 2))
            );

            if ($is_hosted_file) {
                proxyHostedFile($target);
            }

            if (!empty($row['preview_enabled']) && empty($_GET['go']) && empty($_GET['no_preview'])) {
                renderUrlPreviewPage($code, $target);
            }

            header("Location: " . $target);
            exit;
        }
    } else {
        $error = $t['err_notfound'];
    }
}

// ---------------------------------------------------------
// 2. CREATE LINK
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request_path === 'shorten' && isset($_POST['long_url'])) {
    if (isset($_POST['domain']) && in_array($_POST['domain'], $available_domains, true)) {
        $selected_domain = $_POST['domain'];
    }

    $password = $_POST['password'] ?? '';
    $expires_at = $_POST['expires_at'] ?? '';
    $max_clicks = $_POST['max_clicks'] ?? '';
    $custom_code = $_POST['custom_code'] ?? '';
    $preview_enabled = !empty($_POST['preview_enabled']);

    if (!checkCreateRateLimit()) {
        $ok = false;
        $err = 'rate_limited';
        $result = null;
    } else {
        [$ok, $err, $result] = createShortLink($_POST['long_url'], $selected_domain, $password, $expires_at, $max_clicks, $custom_code, currentUserId(), $preview_enabled);
    }

    if ($ok) {
        $short_url = $result['short_url'];
    } else {
        if ($err === 'invalid_url') {
            $error = $t['err_invalid'];
        } elseif ($err === 'invalid_expiry') {
            $error = $t['err_expired'];
        } elseif ($err === 'invalid_alias') {
            $error = $t['err_alias'];
        } elseif ($err === 'alias_taken') {
            $error = $t['err_alias_taken'];
        } elseif ($err === 'rate_limited') {
            $error = $t['err_rate_limit'];
        } else {
            $error = $t['err_save'];
        }
    }
}
?>
<?php if ($request_path === 'shorten'): ?><!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['title']) ?></title>
    <link rel="icon" href="/logo.png" type="image/jpeg">
    <meta name="description" content="<?= h($t['lead']) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script nonce="<?= $csp_nonce ?>">
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'ui-monospace', 'monospace']
                    }
                }
            }
        };
    </script>
    <?php renderProductTheme(); ?>
    <style>
        @keyframes rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
        .rise{animation:rise .55s cubic-bezier(.2,.7,.2,1) both}
        .glow-grid{background-image:linear-gradient(rgba(245,242,234,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(245,242,234,.03) 1px,transparent 1px);background-size:64px 64px;mask-image:radial-gradient(ellipse 85% 55% at 50% 0%,#000 30%,transparent 100%)}
    </style>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <div class="pointer-events-none fixed inset-0 -z-10"><div class="glow-grid absolute inset-0"></div></div>
    <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 py-5 sm:px-7 lg:px-8">
        <header class="flex items-center justify-between border-b border-white/10 pb-5">
            <a href="/" class="flex items-center gap-2">
                <img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover">
                <span class="font-mono text-sm tracking-tight text-white">0x79</span>
            </a>

            <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
                <?php renderLangSelect($lang, $supported_langs, $LANG_META); ?>
                <span class="mx-1 h-4 w-px bg-white/10"></span>
                <a href="/shorten" class="px-2.5 py-1.5 text-white">url</a>
                <a href="/upload" class="px-2.5 py-1.5 transition hover:text-white"><?= h($t['upload']) ?></a>
                <a href="/music" class="px-2.5 py-1.5 transition hover:text-white">music</a>
                <a href="/api/docs" class="px-2.5 py-1.5 transition hover:text-white">api</a>
                <a href="/abuse" class="px-2.5 py-1.5 transition hover:text-white"><?= h($t['abuse']) ?></a>
                <?php if (isUserLoggedIn()): ?><a href="/account" class="px-2.5 py-1.5 transition hover:text-white">account</a><?php else: ?><a href="/login" class="px-2.5 py-1.5 transition hover:text-white">login</a><?php endif; ?>
                <span class="mx-1 h-4 w-px bg-white/10"></span>
                <a href="https://github.com/HyperGaming99/0x79" target="_blank" rel="noopener" aria-label="GitHub" title="Source on GitHub" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 transition hover:text-white">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M12 .5A11.5 11.5 0 0 0 .5 12a11.5 11.5 0 0 0 7.86 10.92c.58.1.79-.25.79-.56v-2c-3.2.7-3.88-1.37-3.88-1.37-.53-1.34-1.3-1.7-1.3-1.7-1.06-.72.08-.71.08-.71 1.17.08 1.79 1.2 1.79 1.2 1.04 1.79 2.73 1.27 3.4.97.1-.76.41-1.27.74-1.56-2.55-.29-5.23-1.27-5.23-5.67 0-1.25.45-2.27 1.18-3.07-.12-.29-.51-1.46.11-3.04 0 0 .96-.31 3.15 1.17a10.9 10.9 0 0 1 5.74 0c2.18-1.48 3.14-1.17 3.14-1.17.63 1.58.24 2.75.12 3.04.74.8 1.18 1.82 1.18 3.07 0 4.41-2.69 5.38-5.25 5.66.42.36.8 1.08.8 2.18v3.23c0 .31.21.67.8.56A11.5 11.5 0 0 0 23.5 12 11.5 11.5 0 0 0 12 .5Z"/></svg>
                    <span>github</span>
                </a>
            </nav>
        </header>

        <section class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[.92fr_1.08fr] lg:py-16">
            <div class="max-w-xl">
                <p class="mb-5 font-mono text-xs uppercase tracking-[0.22em] text-white/35">shortener</p>
                <h1 class="text-4xl font-semibold tracking-[-0.045em] text-white sm:text-5xl lg:text-6xl">
                    <?= h($t['h1']) ?>
                </h1>
                <p class="mt-5 max-w-md text-base leading-7 text-white/50 sm:text-lg">
                    <?= h($t['lead']) ?>
                </p>

                <div class="mt-8 grid max-w-md grid-cols-3 overflow-hidden rounded-xl border border-white/10 text-center font-mono text-xs text-white/45">
                    <div class="border-r border-white/10 p-4">
                        <div class="mb-1 text-base font-semibold text-white">alias</div>
                        custom
                    </div>
                    <div class="border-r border-white/10 p-4">
                        <div class="mb-1 text-base font-semibold text-white">burn</div>
                        optional
                    </div>
                    <div class="p-4">
                        <div class="mb-1 text-base font-semibold text-white">api</div>
                        ready
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-white/[0.08] bg-[#101011]">
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-4 sm:px-6">
                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-[0.22em] text-white/35">create</p>
                        <h2 class="mt-1 text-lg font-medium tracking-tight text-white"><?= h($t['submit']) ?></h2>
                    </div>
                    <span class="font-mono text-xs text-white/35">v1</span>
                </div>

                <form method="POST" action="/shorten" class="grid gap-4 p-5 sm:p-6">
                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45"><?= h($t['url_label']) ?></span>
                        <input class="h-12 w-full rounded-lg border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/35" type="text" name="long_url" placeholder="https://example.com / mailto:name@example.com / tg://…" required autofocus>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['domain_label']) ?></span>
                            <select name="domain" class="h-12 w-full rounded-lg border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition focus:border-white/35">
                                <?php foreach ($available_domains as $d): ?>
                                    <option class="bg-[#0b0b0c] text-white" value="<?= h($d) ?>" <?= $d === $selected_domain ? 'selected' : '' ?>><?= h($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['alias_label']) ?></span>
                            <input class="h-12 w-full rounded-lg border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/35" type="text" name="custom_code" maxlength="32" pattern="[A-Za-z0-9]{1,32}" placeholder="optional">
                        </label>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['password_label']) ?></span>
                            <input class="h-12 w-full rounded-lg border border-white/10 bg-[#0b0b0c] px-3.5 text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/35" type="password" name="password" placeholder="optional" autocomplete="new-password">
                        </label>

                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['expires_label']) ?></span>
                            <input class="h-12 w-full rounded-lg border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition [color-scheme:dark] focus:border-white/35" type="datetime-local" name="expires_at">
                        </label>

                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['burn_label']) ?></span>
                            <input class="h-12 w-full rounded-lg border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/35" type="number" name="max_clicks" min="1" max="1000000" step="1" inputmode="numeric" placeholder="<?= h($t['burn_placeholder']) ?>">
                        </label>
                    </div>

                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-white/10 bg-[#0b0b0c] p-4 transition hover:border-white/30">
                        <input class="mt-1 h-4 w-4 accent-[#f5f2ea]" type="checkbox" name="preview_enabled" value="1">
                        <span>
                            <span class="block font-mono text-xs text-white"><?= h($t['preview_label']) ?></span>
                            <span class="mt-1 block text-xs leading-5 text-white/40"><?= h($t['preview_hint']) ?></span>
                        </span>
                    </label>

                    <button type="submit" class="mt-2 flex h-12 items-center justify-between rounded-lg bg-[#f5f2ea] px-4 font-mono text-sm font-semibold text-[#0b0b0c] transition hover:bg-white">
                        <span><?= h($t['submit']) ?></span>
                        <span>→</span>
                    </button>
                </form>
            </div>
        </section>

        <?php if (!empty($error)): ?>
            <div class="mb-5 rounded-xl border border-red-400/25 bg-red-500/10 p-4 text-sm text-red-200">
                <div class="mb-1 font-mono text-xs uppercase tracking-[0.22em] text-red-300/60"><?= h($t['err']) ?></div>
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($short_url)): ?>
            <div class="mb-5 rounded-xl border border-emerald-400/25 bg-emerald-500/10 p-4 text-sm text-emerald-100">
                <div class="mb-3 font-mono text-xs uppercase tracking-[0.22em] text-emerald-300/60"><?= h($t['short_link']) ?></div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a class="min-w-0 flex-1 truncate rounded-lg border border-white/10 bg-black/20 px-3.5 py-3 font-mono text-sm text-white underline decoration-white/20 underline-offset-4" href="<?= h($short_url) ?>" target="_blank" rel="noopener"><?= h($short_url) ?></a>
                    <button type="button" class="copy h-11 rounded-lg border border-white/15 px-4 font-mono text-sm text-white transition hover:border-white/35" data-copy="<?= h($t['copy']) ?>" data-copied="<?= h($t['copied']) ?>" onclick="copyLink(this, '<?= h($short_url) ?>')">
                        <?= h($t['copy']) ?>
                    </button>
                </div>
                <div class="mt-4 flex items-center gap-4">
                    <img src="/qr?d=<?= h(rawurlencode($short_url)) ?>" alt="QR code" width="104" height="104" class="rounded-lg border border-white/10 bg-white p-1">
                    <div class="font-mono text-xs text-white/45">
                        <p>scan or <a href="/qr?d=<?= h(rawurlencode($short_url)) ?>" download="qr.svg" class="text-white underline decoration-white/25 underline-offset-2">download QR</a></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <section class="grid gap-3 border-t border-white/10 pt-5 sm:grid-cols-4">
            <a href="/upload" class="rounded-xl border border-white/10 bg-[#101011]/60 p-4 transition hover:border-white/30">
                <div class="font-mono text-xs uppercase tracking-[0.18em] text-white/35"><?= h($t['upload_title']) ?></div>
                <p class="mt-2 text-sm leading-6 text-white/55"><?= h($t['upload_lead']) ?></p>
            </a>
            <a href="/api/docs" class="rounded-xl border border-white/10 bg-[#101011]/60 p-4 transition hover:border-white/30">
                <div class="font-mono text-xs uppercase tracking-[0.18em] text-white/35"><?= h($t['api_card_label']) ?></div>
                <p class="mt-2 text-sm leading-6 text-white/55"><?= h($t['api_card_text']) ?></p>
            </a>
            <div class="rounded-xl border border-white/10 bg-[#101011]/60 p-4">
                <div class="font-mono text-xs uppercase tracking-[0.18em] text-white/35">options</div>
                <p class="mt-2 text-sm leading-6 text-white/55">password, expiry, custom alias, burn-after clicks.</p>
            </div>
            <a href="/abuse" class="rounded-xl border border-white/10 bg-[#101011]/60 p-4 transition hover:border-white/30">
                <div class="font-mono text-xs uppercase tracking-[0.18em] text-white/35"><?= h($t['abuse']) ?></div>
                <p class="mt-2 text-sm leading-6 text-white/55">report phishing, malware or spam links.</p>
            </a>
        </section>

        <footer class="mt-8 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
            <span>0x79.one</span>
            <span>fftrclo.store · takeitdown.space · mydiscordiscool.store · fckdupfuture.com · <?= date('Y') ?></span>
        </footer>
    </main>

    <script nonce="<?= $csp_nonce ?>">
        function copyLink(btn, url) {
            navigator.clipboard.writeText(url).then(function () {
                btn.textContent = btn.dataset.copied;
                setTimeout(function () {
                    btn.textContent = btn.dataset.copy;
                }, 1600);
            });
        }
    </script>
</body>
</html>

<?php else: ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>0x79</title>
    <link rel="icon" href="/logo.png" type="image/jpeg">
    <meta name="description" content="URL shortener, file/image host and paste host.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script nonce="<?= $csp_nonce ?>">
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Arial','Helvetica','sans-serif'], mono: ['SFMono-Regular','Consolas','Liberation Mono','monospace'] } } } };
    </script>
    <?php renderProductTheme(); ?>
    <style>
        :root{--paper:#e8e6df;--ink:#11110f;--acid:#b8ff31;--rule:rgba(17,17,15,.22)}
        html[data-theme="dark"]{--paper:#11110f;--ink:#e8e6df;--acid:#b8ff31;--rule:rgba(232,230,223,.22)}
        html{scroll-behavior:smooth}
        body{background:var(--paper)!important;color:var(--ink)!important}
        html[data-theme="dark"] body [class*="bg-[#e8e6df]"]{background-color:rgba(17,17,15,.95)!important}
        html[data-theme="dark"] body [class*="text-black"]{color:var(--ink)!important}
        html[data-theme="dark"] body [class*="bg-[#b8ff31]"]{color:#11110f!important}
        html[data-theme="dark"] body [class*="border-black"]{border-color:var(--rule)!important}
        html[data-theme="dark"] .nav-login,html[data-theme="dark"] .rss-button{background:var(--acid)!important;color:#11110f!important;border-color:var(--acid)!important}
        html[data-theme="dark"] .nav-login:hover,html[data-theme="dark"] .rss-button:hover{background:var(--ink)!important;color:var(--paper)!important}
        .wordmark{letter-spacing:-.08em}
        .display{font-size:clamp(3.8rem,9vw,7.5rem);line-height:.8;letter-spacing:-.08em}
        .utility-row{transition:background-color .16s,color .16s}
        .utility-row:hover{background:var(--ink);color:var(--paper)}
        .utility-row:hover .utility-arrow{transform:translate(5px,-2px)}
        .utility-arrow{transition:transform .16s}
        .ticker{animation:ticker 26s linear infinite}
        @keyframes ticker{to{transform:translateX(-50%)}}
        @media(prefers-reduced-motion:reduce){.ticker{animation:none}}
        ::selection{background:var(--ink);color:var(--acid)}
    </style>
</head>
<body class="min-h-screen font-sans antialiased">
    <header class="sticky top-0 z-40 border-b border-black/20 bg-[#e8e6df]/95">
        <div class="mx-auto flex w-full max-w-[1440px] items-stretch justify-between px-4 sm:px-7">
            <a href="/" class="flex items-center gap-3 py-3">
                <img src="/logomark_0x79.jpg" alt="0x79" class="h-8 w-8 object-cover grayscale">
                <span class="wordmark text-xl font-black">0x79</span>
            </a>
            <nav class="flex items-center gap-0 font-mono text-[11px] uppercase tracking-wider">
                <?php renderLangSelect($lang, $supported_langs, $LANG_META); ?>
                <a href="/api/docs" class="hidden border-l border-black/20 px-4 py-4 hover:bg-black hover:text-white sm:block"><?= h($t['home_nav_api']) ?></a>
                <a href="/abuse" class="hidden border-l border-black/20 px-4 py-4 hover:bg-black hover:text-white md:block"><?= h($t['abuse']) ?></a>
                <a href="https://github.com/HyperGaming99/0x79" target="_blank" rel="noopener" aria-label="GitHub" class="hidden items-center gap-2 border-l border-black/20 px-4 py-4 hover:bg-black hover:text-white lg:flex">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M12 .5A11.5 11.5 0 0 0 .5 12a11.5 11.5 0 0 0 7.86 10.92c.58.1.79-.25.79-.56v-2c-3.2.7-3.88-1.37-3.88-1.37-.53-1.34-1.3-1.7-1.3-1.7-1.06-.72.08-.71.08-.71 1.17.08 1.79 1.2 1.79 1.2 1.04 1.79 2.73 1.27 3.4.97.1-.76.41-1.27.74-1.56-2.55-.29-5.23-1.27-5.23-5.67 0-1.25.45-2.27 1.18-3.07-.12-.29-.51-1.46.11-3.04 0 0 .96-.31 3.15 1.17a10.9 10.9 0 0 1 5.74 0c2.18-1.48 3.14-1.17 3.14-1.17.63 1.58.24 2.75.12 3.04.74.8 1.18 1.82 1.18 3.07 0 4.41-2.69 5.38-5.25 5.66.42.36.8 1.08.8 2.18v3.23c0 .31.21.67.8.56A11.5 11.5 0 0 0 23.5 12 11.5 11.5 0 0 0 12 .5Z"/></svg>
                    <span>github</span>
                </a>
                <?php if (isUserLoggedIn()): ?>
                    <a href="/account" class="nav-login border-x border-black/20 bg-black px-4 py-4 text-white hover:bg-[#b8ff31] hover:text-black"><?= h($t['home_nav_account']) ?></a>
                <?php else: ?>
                    <a href="/login" class="nav-login border-x border-black/20 bg-black px-4 py-4 text-white hover:bg-[#b8ff31] hover:text-black"><?= h($t['home_nav_login']) ?></a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-[1440px] px-4 sm:px-7">

        <section class="grid min-h-[460px] border-b border-black/25 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="flex flex-col justify-between border-black/25 py-8 lg:border-r lg:pr-10 lg:py-10">
                <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-[.18em]">
                    <span><?= h($t['home_kicker']) ?></span>
                    <a href="/status" class="flex items-center gap-2 hover:underline"><i class="h-2 w-2 bg-[#37b24d]"></i> <?= h($t['home_online']) ?> ↗</a>
                </div>
                <h1 class="display my-10 max-w-[850px] font-black uppercase">
                <?= h($t['home_h1']) ?>
                </h1>
                <div class="grid gap-6 sm:grid-cols-[minmax(0,560px)_1fr] sm:items-end">
                    <?php if (toolEnabled('shortener')): ?>
                    <form method="POST" action="/shorten" class="flex border-2 border-black bg-white">
                        <label class="sr-only" for="quick-url"><?= h($t['url_label'] ?? 'URL') ?></label>
                        <input id="quick-url" type="url" name="long_url" required placeholder="<?= h($t['home_quick_placeholder']) ?>"
                               class="h-16 min-w-0 flex-1 bg-transparent px-4 font-mono text-sm outline-none placeholder:text-black/35">
                        <button type="submit" class="m-1 bg-[#b8ff31] px-5 font-mono text-xs font-bold uppercase tracking-wider hover:bg-black hover:text-white">
                            <?= h($t['home_quick_submit']) ?> ↗
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="flex h-16 items-center border-2 border-black px-4 font-mono text-xs uppercase tracking-wider text-black/50"><?= h($t['home_tools_disabled']) ?></div>
                    <?php endif; ?>
                    <p class="max-w-xs text-sm leading-5 text-black/60"><?= h($t['home_lead']) ?></p>
                </div>
            </div>
            <aside class="hidden flex-col justify-between px-6 py-10 lg:flex">
                <p class="font-mono text-[10px] uppercase tracking-[.18em]"><?= h($t['home_stack']) ?></p>
                <div class="relative mx-auto h-44 w-44">
                    <img src="/logomark_0x79.jpg" alt="" class="h-full w-full object-cover grayscale contrast-125">
                    <span class="absolute -bottom-4 -left-4 bg-[#b8ff31] px-3 py-2 font-mono text-[10px] font-bold uppercase"><?= h($t['home_private']) ?></span>
                </div>
                <div class="border-t border-black/25 pt-4 font-mono text-[10px] uppercase leading-5 tracking-wider">
                    <?= implode('<br>', array_map('h', explode('|', $t['home_stack_points']))) ?>
                </div>
            </aside>
        </section>

        <div class="-mx-4 overflow-hidden border-b border-black bg-black py-2 text-[#e8e6df] sm:-mx-7">
            <div class="ticker flex w-max font-mono text-[10px] uppercase tracking-[.2em]">
                <span class="px-5"><?= h($t['home_ticker']) ?></span>
                <span class="px-5"><?= h($t['home_ticker']) ?></span>
            </div>
        </div>

        <section class="grid border-b border-black/25 py-10 lg:grid-cols-[220px_1fr] lg:gap-10 lg:py-12">
            <div class="mb-7 lg:mb-0">
                <p class="font-mono text-[10px] uppercase tracking-[.2em]"><?= h($t['home_directory_label']) ?></p>
                <h2 class="mt-3 text-3xl font-black uppercase tracking-[-.06em]"><?= h($t['home_directory_title']) ?></h2>
                <p class="mt-3 max-w-[220px] text-xs leading-5 text-black/55"><?= h($t['home_directory_lead']) ?></p>
                <a href="/tools" class="mt-4 inline-flex border border-black px-3 py-2 font-mono text-[10px] font-bold uppercase tracking-wider hover:bg-black hover:text-white"><?= $lang === 'de' ? 'Tool-Dashboard' : 'Tool dashboard' ?> →</a>
            </div>
            <div class="grid border-t-2 border-black md:grid-cols-2">
                <?php
                $toolCards = [
                    ['tool' => 'shortener', 'href' => '/shorten',      'num' => '01', 'color' => '#60a5fa', 'title' => $t['home_tool1_title'], 'desc' => $t['home_tool1_desc'], 'tags' => ['alias', 'password', 'burn'],
                     'icon' => 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244'],
                    ['tool' => 'upload', 'href' => '/upload',       'num' => '02', 'color' => '#34d399', 'title' => $t['home_tool2_title'], 'desc' => $t['home_tool2_desc'], 'tags' => ['zip', 'images', 'qr'],
                     'icon' => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5'],
                    ['tool' => 'paste', 'href' => '/paste',        'num' => '03', 'color' => '#a78bfa', 'title' => $t['home_tool3_title'], 'desc' => $t['home_tool3_desc'], 'tags' => ['text', 'raw', 'burn'],
                     'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
                    ['tool' => 'music', 'href' => '/music',        'num' => '04', 'color' => '#fb7185', 'title' => $t['home_tool4_title'], 'desc' => $t['home_tool4_desc'], 'tags' => ['spotify', 'apple', 'youtube'],
                     'icon' => 'M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z'],
                    ['tool' => 'metadata', 'href' => '/metadata',     'num' => '05', 'color' => '#fbbf24', 'title' => $t['home_tool5_title'], 'desc' => $t['home_tool5_desc'], 'tags' => ['exif', 'privacy', 'local'],
                     'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
                    ['tool' => 'secure_share', 'href' => '/secure-share', 'num' => '06', 'color' => '#22d3ee', 'title' => $t['home_tool6_title'], 'desc' => $t['home_tool6_desc'], 'tags' => ['aes-gcm', 'zero-knowledge', 'secure'],
                     'icon' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z'],
                    ['tool' => 'discord', 'href' => '/discord', 'num' => '07', 'color' => '#5865F2', 'title' => $t['home_tool7_title'], 'desc' => $t['home_tool7_desc'], 'tags' => ['status', 'spotify', 'gateway'],
                     'icon' => 'M8.25 10.5h.008v.008H8.25V10.5zm7.5 0h.008v.008h-.008V10.5zM7.5 17.25c3 1.5 6 1.5 9 0m1.875-12A15.91 15.91 0 0112 3.75c-2.25 0-4.4.47-6.375 1.5C3.75 8.25 3 11.25 3 15c1.5 1.5 3 2.25 4.5 3l1.125-1.5M18.375 5.25C20.25 8.25 21 11.25 21 15c-1.5 1.5-3 2.25-4.5 3l-1.125-1.5'],
                    ['tool' => 'minecraft', 'href' => '/minecraft', 'num' => '08', 'color' => '#65a30d', 'title' => $t['home_tool8_title'], 'desc' => $t['home_tool8_desc'], 'tags' => ['java', 'players', 'ping'],
                     'icon' => 'M21 16.5V7.5L12 2.25 3 7.5v9l9 5.25 9-5.25zM3.27 6.96L12 12l8.73-5.04M12 22V12'],
                ];
                foreach ($toolCards as $tc): if (!toolEnabled($tc['tool'])) continue; ?>
                <a href="<?= h($tc['href']) ?>" class="utility-row group grid min-h-[88px] grid-cols-[34px_1fr_auto] items-center gap-3 border-b border-black/25 px-2 py-3 md:odd:border-r md:px-4">
                    <span class="font-mono text-[10px]"><?= h($tc['num']) ?></span>
                    <div>
                        <h3 class="text-base font-bold tracking-[-.03em]"><?= h($tc['title']) ?></h3>
                        <p class="mt-1 line-clamp-1 text-xs opacity-55"><?= h($tc['desc']) ?></p>
                    </div>
                    <span class="utility-arrow text-xl">↗</span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (toolEnabled('music')): ?>
        <!-- Music promoter showcase -->
        <section class="grid border-b border-black/25 py-10 lg:grid-cols-[minmax(0,1fr)_460px] lg:items-center lg:gap-16 lg:py-16">
            <div class="max-w-xl pb-9 lg:pb-0">
                <p class="font-mono text-[10px] uppercase tracking-[.2em]"><?= h($t['home_showcase_label']) ?></p>
                <h2 class="mt-3 text-4xl font-black uppercase leading-[.9] tracking-[-.06em] sm:text-5xl"><?= h($t['home_showcase_title']) ?></h2>
                <p class="mt-5 max-w-md text-sm leading-6 text-black/60"><?= h($t['home_showcase_lead']) ?></p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="/music" class="bg-black px-5 py-3 font-mono text-xs font-bold uppercase tracking-wider text-white transition hover:bg-[#fb7185] hover:text-black"><?= h($t['home_showcase_cta']) ?></a>
                    <span class="font-mono text-[10px] uppercase tracking-wider text-black/40"><?= h($t['home_showcase_features']) ?></span>
                </div>
            </div>

            <a href="/music" aria-label="Open the music page creator" class="group block border border-black/25 bg-black p-3 shadow-[10px_10px_0_#fb7185] transition hover:-translate-y-1 hover:shadow-[14px_14px_0_#fb7185] sm:p-5">
                <div class="overflow-hidden border border-white/15 bg-[#101011] text-[#f5f2ea]">
                    <div class="relative h-28 overflow-hidden bg-[#26070d] sm:h-36">
                        <img src="/logomark_0x79.jpg" alt="" class="h-full w-full scale-150 object-cover opacity-65 blur-[2px] saturate-150">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#101011]/50 to-transparent"></div>
                    </div>
                    <div class="px-5 pb-6 sm:px-7 sm:pb-7">
                        <div class="mx-auto -mt-10 h-28 w-28 overflow-hidden rounded-xl border border-white/20 bg-[#1b1b1d] p-1 shadow-xl sm:-mt-12 sm:h-32 sm:w-32">
                            <img src="/logomark_0x79.jpg" alt="Example cover artwork" class="h-full w-full rounded-lg object-cover saturate-150">
                        </div>
                        <p class="mt-5 text-center font-mono text-[9px] uppercase tracking-[.3em] text-white/35"><?= h($t['home_showcase_listen']) ?></p>
                        <h3 class="mt-1 text-center text-2xl font-black tracking-[-.05em] text-white"><?= h($t['home_showcase_release']) ?></h3>
                        <p class="mt-1 text-center text-xs text-white/45"><?= h($t['home_showcase_artist']) ?></p>
                        <div class="mt-5 flex items-center justify-between border border-white/10 bg-[#0b0b0c] p-3" style="border-left:3px solid #1DB954">
                            <span class="flex items-center gap-3">
                                <span class="grid h-9 w-9 place-items-center rounded-full bg-[#1DB954]">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-black" aria-hidden="true"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                                </span>
                                <span class="text-sm font-semibold text-white">Spotify</span>
                            </span>
                            <span class="font-mono text-xs text-white/40 transition group-hover:translate-x-1 group-hover:text-white"><?= h($t['home_showcase_play']) ?></span>
                        </div>
                        <p class="mt-5 text-center font-mono text-[9px] text-white/25"><?= h($t['home_showcase_powered']) ?></p>
                    </div>
                </div>
            </a>
        </section>
        <?php endif; ?>

        <?php if (toolEnabled('discord')): ?>
        <!-- Discord Presence showcase -->
        <section class="grid border-b border-black/25 py-10 lg:grid-cols-[minmax(0,1fr)_500px] lg:items-center lg:gap-16 lg:py-16">
            <div class="max-w-xl pb-9 lg:pb-0">
                <p class="font-mono text-[10px] uppercase tracking-[.2em] text-[#5865F2]"><?= h($t['home_discord_label']) ?></p>
                <h2 class="mt-3 text-4xl font-black uppercase leading-[.9] tracking-[-.06em] sm:text-5xl"><?= h($t['home_discord_title']) ?></h2>
                <p class="mt-5 max-w-md text-sm leading-6 text-black/60"><?= h($t['home_discord_lead']) ?></p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="/discord" class="bg-[#5865F2] px-5 py-3 font-mono text-xs font-bold uppercase tracking-wider text-white transition hover:bg-black"><?= h($t['home_discord_cta']) ?></a>
                    <span class="font-mono text-[10px] uppercase tracking-wider text-black/40"><?= h($t['home_discord_features']) ?></span>
                </div>
            </div>

            <a href="/discord" aria-label="Open Discord Presence" class="group block border border-black bg-[#5865F2] p-3 shadow-[10px_10px_0_#11110f] transition hover:-translate-y-1 hover:shadow-[14px_14px_0_#11110f] sm:p-4">
                <div class="overflow-hidden border border-white/10 bg-[#0d1117] text-white">
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 font-mono text-[9px] uppercase tracking-[.18em] text-white/35">
                        <span><?= h($t['home_discord_profile']) ?></span><span class="flex items-center gap-2 text-[#34d399]"><i class="h-2 w-2 rounded-full bg-[#34d399]"></i> online</span>
                    </div>
                    <div class="p-4 sm:p-5">
                        <div class="flex items-center gap-4">
                            <span class="relative h-16 w-16 shrink-0 overflow-visible rounded-2xl bg-[#5865F2] p-1"><img src="/logomark_0x79.jpg" alt="" class="h-full w-full rounded-xl object-cover"><i class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full border-4 border-[#0d1117] bg-[#34d399]"></i></span>
                            <div class="min-w-0 flex-1"><h3 class="truncate text-xl font-black tracking-[-.05em]">0x79</h3><p class="font-mono text-[10px] text-white/35">@presence · desktop</p><div class="mt-2 flex gap-1"><span class="border border-white/10 px-1.5 py-0.5 font-mono text-[8px] uppercase text-white/40">gateway</span><span class="border border-white/10 px-1.5 py-0.5 font-mono text-[8px] uppercase text-white/40">websocket</span></div></div>
                        </div>

                        <div class="mt-5 border-l-4 border-[#1DB954] bg-white/[.035] p-3">
                            <div class="flex gap-3"><span class="grid h-14 w-14 shrink-0 place-items-center rounded bg-[#1DB954]"><svg viewBox="0 0 24 24" class="h-7 w-7 fill-black" aria-hidden="true"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg></span><div class="min-w-0 flex-1"><p class="font-mono text-[8px] uppercase tracking-[.16em] text-[#1DB954]"><?= h($t['home_discord_listening']) ?></p><p class="mt-1 truncate text-sm font-bold">Midnight Signal</p><p class="truncate text-[10px] text-white/35">0x79 Radio</p><div class="mt-2 h-1 overflow-hidden rounded bg-white/10"><i class="block h-full w-[62%] rounded bg-[#1DB954]"></i></div><div class="mt-1 flex justify-between font-mono text-[8px] text-white/25"><span>1:33</span><span><?= h($t['discord_remaining'] ?? 'remaining') ?> 0:57 · 2:30</span></div></div></div>
                        </div>

                        <div class="mt-3 flex items-center gap-3 border border-white/10 bg-white/[.025] p-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-[#5865F2]/20 text-[#8b9cff]">◆</span>
                            <div class="min-w-0 flex-1"><p class="font-mono text-[8px] uppercase tracking-[.16em] text-[#8b9cff]"><?= h($t['home_discord_playing']) ?></p><p class="mt-1 truncate text-xs font-bold">Discord Presence</p><p class="font-mono text-[8px] text-white/30"><?= h($t['home_discord_elapsed']) ?></p></div><span class="font-mono text-[9px] text-white/20">LIVE</span>
                        </div>
                    </div>
                </div>
            </a>
        </section>
        <?php endif; ?>

        <?php if (toolEnabled('shortener') || toolEnabled('upload') || toolEnabled('paste')): ?>
        <!-- Core tool visual previews -->
        <section class="border-b border-black/25 py-10 lg:py-16">
            <div class="mb-8 grid gap-4 lg:grid-cols-[1fr_420px] lg:items-end">
                <div>
                    <p class="font-mono text-[10px] uppercase tracking-[.2em]"><?= h($t['home_visuals_label']) ?></p>
                    <h2 class="mt-3 max-w-3xl text-4xl font-black uppercase leading-[.92] tracking-[-.06em] sm:text-5xl"><?= h($t['home_visuals_title']) ?></h2>
                </div>
                <p class="max-w-md text-sm leading-6 text-black/60 lg:justify-self-end"><?= h($t['home_visuals_lead']) ?></p>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <?php if (toolEnabled('shortener')): ?>
                <a href="/shorten" class="group border border-black bg-[#60a5fa] p-3 transition hover:-translate-y-1 hover:shadow-[7px_7px_0_#11110f]">
                    <div class="flex min-h-[310px] flex-col bg-[#0e0e10] p-5 text-white">
                        <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-[.18em] text-white/40">
                            <span>01 / URL</span><span class="h-2 w-2 rounded-full bg-[#60a5fa]"></span>
                        </div>
                        <div class="my-auto">
                            <div class="border border-white/15 bg-white/[.04] p-4">
                                <p class="font-mono text-[9px] uppercase tracking-[.2em] text-white/35"><?= h($t['home_visual_short_label']) ?></p>
                                <p class="mt-3 break-all font-mono text-lg font-semibold tracking-[-.04em] text-white"><?= h($t['home_visual_short_value']) ?></p>
                                <div class="mt-5 flex items-end gap-1" aria-hidden="true">
                                    <?php foreach ([25,42,31,58,46,73,54,88,68,100,78,92] as $bar): ?>
                                    <span class="flex-1 bg-[#60a5fa]" style="height:<?= $bar ?>px;opacity:<?= .35 + ($bar / 160) ?>"></span>
                                    <?php endforeach; ?>
                                </div>
                                <p class="mt-3 text-right font-mono text-[10px] text-white/40"><?= h($t['home_visual_short_stat']) ?> ↗</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/10 pt-4">
                            <strong class="text-sm"><?= h($t['home_tool1_title']) ?></strong><span class="font-mono text-[10px] uppercase text-white/45 group-hover:text-white"><?= h($t['home_visual_open']) ?></span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (toolEnabled('upload')): ?>
                <a href="/upload" class="group border border-black bg-[#34d399] p-3 transition hover:-translate-y-1 hover:shadow-[7px_7px_0_#11110f]">
                    <div class="flex min-h-[310px] flex-col bg-[#0e0e10] p-5 text-white">
                        <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-[.18em] text-white/40">
                            <span>02 / FILE</span><span class="h-2 w-2 rounded-full bg-[#34d399]"></span>
                        </div>
                        <div class="my-auto border border-dashed border-white/25 p-5 text-center">
                            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#34d399] text-black">
                                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v4a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
                            </div>
                            <p class="mt-4 font-mono text-[9px] uppercase tracking-[.2em] text-[#34d399]"><?= h($t['home_visual_upload_label']) ?></p>
                            <p class="mt-2 truncate text-sm font-semibold"><?= h($t['home_visual_upload_file']) ?></p>
                            <p class="mt-1 font-mono text-[10px] text-white/35"><?= h($t['home_visual_upload_size']) ?></p>
                            <div class="mt-4 h-1 bg-white/10"><span class="block h-full w-full bg-[#34d399]"></span></div>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/10 pt-4">
                            <strong class="text-sm"><?= h($t['home_tool2_title']) ?></strong><span class="font-mono text-[10px] uppercase text-white/45 group-hover:text-white"><?= h($t['home_visual_open']) ?></span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (toolEnabled('paste')): ?>
                <a href="/paste" class="group border border-black bg-[#a78bfa] p-3 transition hover:-translate-y-1 hover:shadow-[7px_7px_0_#11110f]">
                    <div class="flex min-h-[310px] flex-col bg-[#0e0e10] p-5 text-white">
                        <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-[.18em] text-white/40">
                            <span>03 / PASTE</span><span class="h-2 w-2 rounded-full bg-[#a78bfa]"></span>
                        </div>
                        <div class="my-auto overflow-hidden border border-white/15 bg-white/[.04]">
                            <div class="flex gap-1.5 border-b border-white/10 px-3 py-2.5"><i class="h-1.5 w-1.5 rounded-full bg-[#fb7185]"></i><i class="h-1.5 w-1.5 rounded-full bg-[#fbbf24]"></i><i class="h-1.5 w-1.5 rounded-full bg-[#34d399]"></i></div>
                            <div class="p-4 font-mono text-[10px] leading-6">
                                <p><span class="text-[#a78bfa]">01</span> <span class="text-white/35">// <?= h($t['home_visual_paste_label']) ?></span></p>
                                <p><span class="text-[#a78bfa]">02</span> <span class="text-white">const</span> share = <span class="text-[#34d399]">"0x79"</span>;</p>
                                <p><span class="text-[#a78bfa]">03</span> <span class="text-white/35">••••••••••••••••</span></p>
                            </div>
                            <div class="border-t border-white/10 p-3">
                                <p class="text-xs font-semibold"><?= h($t['home_visual_paste_code']) ?></p>
                                <p class="mt-1 font-mono text-[9px] text-white/35"><?= h($t['home_visual_paste_meta']) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/10 pt-4">
                            <strong class="text-sm"><?= h($t['home_tool3_title']) ?></strong><span class="font-mono text-[10px] uppercase text-white/45 group-hover:text-white"><?= h($t['home_visual_open']) ?></span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- How it works -->
        <section class="grid border-b border-black/25 py-10 lg:grid-cols-[260px_1fr] lg:gap-12 lg:py-14">
            <div class="mb-7 lg:mb-0">
                <p class="font-mono text-[10px] uppercase tracking-[.2em]"><?= h($t['home_how_label']) ?></p>
                <h2 class="mt-3 text-4xl font-black uppercase leading-[.9] tracking-[-.06em]"><?= h($t['home_how_title']) ?></h2>
            </div>
            <ol class="grid border-t-2 border-black md:grid-cols-3">
                <?php foreach ([
                    ['01', 'home_how_step1_title', 'home_how_step1_text', 'Choose a tool', 'Pick links, uploads, pastes or one of the specialized tools.'],
                    ['02', 'home_how_step2_title', 'home_how_step2_text', 'Set your options', 'Add an alias, password, expiry date or access limit.'],
                    ['03', 'home_how_step3_title', 'home_how_step3_text', 'Share securely', 'Copy the short link or share the generated QR code directly.'],
                ] as $step): ?>
                <li class="flex min-h-[190px] flex-col justify-between border-b border-black/25 p-5 md:border-r md:last:border-r-0">
                    <span class="font-mono text-[10px] text-black/40"><?= h($step[0]) ?></span>
                    <div>
                        <h3 class="text-lg font-bold tracking-[-.04em]"><?= h($t[$step[1]] ?? $step[3]) ?></h3>
                        <p class="mt-2 text-xs leading-5 text-black/55"><?= h($t[$step[2]] ?? $step[4]) ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
        </section>

        <!-- Trust strip -->
        <section class="border-b border-black/25 py-8">
            <div class="grid border-y-2 border-black sm:grid-cols-5">
                <?php foreach ([
                    ['home_trust_private', '●'], ['home_trust_source', '↗'], ['home_trust_encrypt', '◆'],
                    ['home_trust_expiry', '◷'], ['home_trust_types', '✓'],
                ] as $trust): ?>
                <div class="flex items-center gap-3 border-b border-black/25 px-4 py-4 last:border-b-0 sm:border-b-0 sm:border-r sm:last:border-r-0">
                    <span class="font-mono text-sm text-[#37b24d]"><?= h($trust[1]) ?></span><span class="font-mono text-[10px] font-bold uppercase tracking-wider"><?= h($t[$trust[0]]) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- API showcase -->
        <section class="grid border-b border-black/25 py-10 lg:grid-cols-[minmax(0,1fr)_560px] lg:items-center lg:gap-14 lg:py-16">
            <div class="max-w-lg pb-8 lg:pb-0">
                <p class="font-mono text-[10px] uppercase tracking-[.2em]"><?= h($t['home_api_label']) ?></p>
                <h2 class="mt-3 text-4xl font-black uppercase leading-[.9] tracking-[-.06em] sm:text-5xl"><?= h($t['home_api_title']) ?></h2>
                <p class="mt-5 text-sm leading-6 text-black/60"><?= h($t['home_api_lead']) ?></p>
                <a href="/api/docs" class="mt-7 inline-block bg-black px-5 py-3 font-mono text-xs font-bold uppercase tracking-wider text-white hover:bg-[#b8ff31] hover:text-black"><?= h($t['home_api_cta']) ?></a>
            </div>
            <div class="border border-black bg-[#11110f] p-2 shadow-[9px_9px_0_#60a5fa] text-[#e8e6df]">
                <div class="flex items-center justify-between border-b border-white/15 px-4 py-3 font-mono text-[9px] uppercase tracking-[.2em] text-white/40"><span>terminal / curl</span><span>POST</span></div>
                <pre class="overflow-x-auto p-4 font-mono text-[11px] leading-6"><span class="text-[#60a5fa]">curl</span> -X POST https://0x79.one/api \
  -H <span class="text-[#34d399]">"X-API-Key: ..."</span> \
  -d <span class="text-[#fbbf24]">'{"url":"https://example.com"}'</span></pre>
                <div class="border-t border-white/15 p-4 font-mono text-[10px]"><p class="mb-2 uppercase tracking-[.18em] text-white/30"><?= h($t['home_api_response']) ?></p><p>{ <span class="text-[#a78bfa]">"ok"</span>: true, <span class="text-[#a78bfa]">"short_url"</span>: <span class="text-[#34d399]">"https://0x79.one/x7Kp"</span> }</p></div>
            </div>
        </section>

        <!-- Use cases -->
        <section class="border-b border-black/25 py-10 lg:py-14">
            <p class="font-mono text-[10px] uppercase tracking-[.2em]"><?= h($t['home_use_label']) ?></p>
            <h2 class="mt-3 max-w-3xl text-4xl font-black uppercase leading-[.9] tracking-[-.06em] sm:text-5xl"><?= h($t['home_use_title']) ?></h2>
            <div class="mt-8 grid border-t-2 border-black md:grid-cols-3">
                <?php foreach ([
                    ['01', 'home_use_dev_title', 'home_use_dev_text', '#60a5fa'],
                    ['02', 'home_use_artist_title', 'home_use_artist_text', '#fb7185'],
                    ['03', 'home_use_team_title', 'home_use_team_text', '#34d399'],
                ] as $use): ?>
                <article class="min-h-[220px] border-b border-black/25 p-5 md:border-r md:last:border-r-0">
                    <div class="flex items-center justify-between"><span class="font-mono text-[10px] opacity-40"><?= h($use[0]) ?></span><i class="h-3 w-3" style="background:<?= h($use[3]) ?>"></i></div>
                    <h3 class="mt-16 text-2xl font-black uppercase tracking-[-.05em]"><?= h($t[$use[1]]) ?></h3><p class="mt-3 max-w-xs text-xs leading-5 opacity-55"><?= h($t[$use[2]]) ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Open source -->
        <section class="grid border-b border-black/25 bg-[#b8ff31] text-[#11110f] lg:grid-cols-[1fr_420px]">
            <div class="p-7 sm:p-10 lg:border-r lg:border-black/25">
                <p class="font-mono text-[10px] uppercase tracking-[.2em]"><?= h($t['home_open_label']) ?></p>
                <h2 class="mt-4 max-w-2xl text-4xl font-black uppercase leading-[.88] tracking-[-.065em] sm:text-6xl"><?= h($t['home_open_title']) ?></h2>
                <p class="mt-5 max-w-xl text-sm leading-6 text-black/60"><?= h($t['home_open_lead']) ?></p>
                <a href="https://github.com/HyperGaming99/0x79" target="_blank" rel="noopener" class="mt-7 inline-block border-2 border-black bg-black px-5 py-3 font-mono text-xs font-bold uppercase text-white hover:bg-transparent hover:text-black"><?= h($t['home_open_github']) ?></a>
            </div>
            <div class="flex flex-col justify-center bg-[#11110f] p-7 text-[#e8e6df] sm:p-10">
                <p class="font-mono text-[10px] uppercase tracking-[.2em] text-white/35"><?= h($t['home_open_docker']) ?></p>
                <code class="mt-5 block border border-white/15 p-4 font-mono text-xs text-[#b8ff31]">docker compose up --build</code>
                <div class="mt-6 grid grid-cols-3 gap-px bg-white/15 font-mono text-center text-[9px] uppercase"><span class="bg-[#11110f] p-3">PHP</span><span class="bg-[#11110f] p-3">Docker</span><span class="bg-[#11110f] p-3">Supabase</span></div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="grid border-b border-black/25 py-10 lg:grid-cols-[260px_1fr] lg:gap-12 lg:py-14">
            <div><p class="font-mono text-[10px] uppercase tracking-[.2em]"><?= h($t['home_faq_label']) ?></p><h2 class="mt-3 text-4xl font-black uppercase leading-[.9] tracking-[-.06em]"><?= h($t['home_faq_title']) ?></h2></div>
            <div class="mt-8 border-t-2 border-black lg:mt-0">
                <?php for ($faq = 1; $faq <= 5; $faq++): ?>
                <details class="group border-b border-black/25">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-5 py-5 font-bold"><span><?= h($t['home_faq_q' . $faq]) ?></span><span class="font-mono text-lg transition group-open:rotate-45">+</span></summary>
                    <p class="max-w-2xl pb-5 pr-10 text-sm leading-6 opacity-55"><?= h($t['home_faq_a' . $faq]) ?></p>
                </details>
                <?php endfor; ?>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="border-b border-black/25 py-12 text-center lg:py-20">
            <p class="font-mono text-[10px] uppercase tracking-[.24em]"><?= h($t['home_final_label']) ?></p>
            <h2 class="mx-auto mt-4 max-w-4xl text-5xl font-black uppercase leading-[.84] tracking-[-.075em] sm:text-7xl"><?= h($t['home_final_title']) ?></h2>
            <div class="mt-9 flex flex-wrap justify-center gap-2 font-mono text-xs font-bold uppercase">
                <?php if (toolEnabled('shortener')): ?><a href="/shorten" class="bg-black px-5 py-3 text-white hover:bg-[#60a5fa] hover:text-black"><?= h($t['home_final_link']) ?></a><?php endif; ?>
                <?php if (toolEnabled('upload')): ?><a href="/upload" class="border-2 border-black px-5 py-3 hover:bg-[#34d399]"><?= h($t['home_final_file']) ?></a><?php endif; ?>
                <?php if (toolEnabled('paste')): ?><a href="/paste" class="border-2 border-black px-5 py-3 hover:bg-[#a78bfa]"><?= h($t['home_final_paste']) ?></a><?php endif; ?>
            </div>
        </section>

        <footer class="flex flex-col items-center justify-between gap-3 border-t border-black/25 py-7 font-mono text-[10px] uppercase tracking-wider text-black/50 sm:flex-row">
            <span class="flex items-center gap-2">
                <img src="/logomark_0x79.jpg" alt="" class="h-5 w-5 object-cover grayscale">
                0x79.one · <?= date('Y') ?>
            </span>
            <span class="text-black/35">fftrclo.store · takeitdown.space · mydiscordiscool.store · fckdupfuture.com</span>
        </footer>
    </main>
</body>
</html>
<?php endif; ?>
