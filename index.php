<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/views.php';
require_once __DIR__ . '/qr.php';

$request_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

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
        [$okUser, $userErr] = createUserAccount($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($okUser) { header('Location: /account'); exit; }
        $msg = $userErr === 'email_taken' ? 'email ist bereits registriert.' : ($userErr === 'weak_password' ? 'passwort braucht mindestens 8 zeichen.' : ($userErr === 'invalid_email' ? 'ungültige email.' : 'account konnte nicht erstellt werden. migration ausgeführt?'));
        renderUserAuthPage('register', $msg);
    }
    renderUserAuthPage('register');
}

if ($request_path === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$okUser, $userErr] = loginUserAccount($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($okUser) { header('Location: /account'); exit; }
        renderUserAuthPage('login', 'email oder passwort falsch.');
    }
    renderUserAuthPage('login');
}

if ($request_path === 'logout') {
    unset($_SESSION['user_id'], $_SESSION['last_api_key']);
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
        [$saved, $saveError] = createAbuseReport($reported_link, $reason);
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
    <script>
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
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
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

                <div class="mt-8 grid max-w-md grid-cols-3 border border-white/10 text-center font-mono text-xs text-white/45">
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

            <div class="border border-white/10 bg-[#101011]">
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
                        <input class="h-12 w-full border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/35" type="text" name="long_url" placeholder="https://example.com / mailto:name@example.com / tg://…" required autofocus>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['domain_label']) ?></span>
                            <select name="domain" class="h-12 w-full border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition focus:border-white/35">
                                <?php foreach ($available_domains as $d): ?>
                                    <option class="bg-[#0b0b0c] text-white" value="<?= h($d) ?>" <?= $d === $selected_domain ? 'selected' : '' ?>><?= h($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['alias_label']) ?></span>
                            <input class="h-12 w-full border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/35" type="text" name="custom_code" maxlength="32" pattern="[A-Za-z0-9]{1,32}" placeholder="optional">
                        </label>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['password_label']) ?></span>
                            <input class="h-12 w-full border border-white/10 bg-[#0b0b0c] px-3.5 text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/35" type="password" name="password" placeholder="optional" autocomplete="new-password">
                        </label>

                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['expires_label']) ?></span>
                            <input class="h-12 w-full border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition [color-scheme:dark] focus:border-white/35" type="datetime-local" name="expires_at">
                        </label>

                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45"><?= h($t['burn_label']) ?></span>
                            <input class="h-12 w-full border border-white/10 bg-[#0b0b0c] px-3.5 font-mono text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/35" type="number" name="max_clicks" min="1" max="1000000" step="1" inputmode="numeric" placeholder="<?= h($t['burn_placeholder']) ?>">
                        </label>
                    </div>

                    <label class="flex cursor-pointer items-start gap-3 border border-white/10 bg-[#0b0b0c] p-4 transition hover:border-white/30">
                        <input class="mt-1 h-4 w-4 accent-[#f5f2ea]" type="checkbox" name="preview_enabled" value="1">
                        <span>
                            <span class="block font-mono text-xs text-white"><?= h($t['preview_label']) ?></span>
                            <span class="mt-1 block text-xs leading-5 text-white/40"><?= h($t['preview_hint']) ?></span>
                        </span>
                    </label>

                    <button type="submit" class="mt-2 flex h-12 items-center justify-between bg-[#f5f2ea] px-4 font-mono text-sm font-semibold text-[#0b0b0c] transition hover:bg-white">
                        <span><?= h($t['submit']) ?></span>
                        <span>→</span>
                    </button>
                </form>
            </div>
        </section>

        <?php if (!empty($error)): ?>
            <div class="mb-5 border border-red-400/25 bg-red-500/10 p-4 text-sm text-red-200">
                <div class="mb-1 font-mono text-xs uppercase tracking-[0.22em] text-red-300/60"><?= h($t['err']) ?></div>
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($short_url)): ?>
            <div class="mb-5 border border-emerald-400/25 bg-emerald-500/10 p-4 text-sm text-emerald-100">
                <div class="mb-3 font-mono text-xs uppercase tracking-[0.22em] text-emerald-300/60"><?= h($t['short_link']) ?></div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a class="min-w-0 flex-1 truncate border border-white/10 bg-black/20 px-3.5 py-3 font-mono text-sm text-white underline decoration-white/20 underline-offset-4" href="<?= h($short_url) ?>" target="_blank" rel="noopener"><?= h($short_url) ?></a>
                    <button type="button" class="copy h-11 border border-white/15 px-4 font-mono text-sm text-white transition hover:border-white/35" data-copy="<?= h($t['copy']) ?>" data-copied="<?= h($t['copied']) ?>" onclick="copyLink(this, '<?= h($short_url) ?>')">
                        <?= h($t['copy']) ?>
                    </button>
                </div>
                <div class="mt-4 flex items-center gap-4">
                    <img src="/qr?d=<?= h(rawurlencode($short_url)) ?>" alt="QR code" width="104" height="104" class="border border-white/10 bg-white p-1">
                    <div class="font-mono text-xs text-white/45">
                        <p>scan or <a href="/qr?d=<?= h(rawurlencode($short_url)) ?>" download="qr.svg" class="text-white underline decoration-white/25 underline-offset-2">download QR</a></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <section class="grid gap-3 border-t border-white/10 pt-5 sm:grid-cols-4">
            <a href="/upload" class="border border-white/10 p-4 transition hover:border-white/30">
                <div class="font-mono text-xs uppercase tracking-[0.18em] text-white/35"><?= h($t['upload_title']) ?></div>
                <p class="mt-2 text-sm leading-6 text-white/55"><?= h($t['upload_lead']) ?></p>
            </a>
            <a href="/api/docs" class="border border-white/10 p-4 transition hover:border-white/30">
                <div class="font-mono text-xs uppercase tracking-[0.18em] text-white/35"><?= h($t['api_card_label']) ?></div>
                <p class="mt-2 text-sm leading-6 text-white/55"><?= h($t['api_card_text']) ?></p>
            </a>
            <div class="border border-white/10 p-4">
                <div class="font-mono text-xs uppercase tracking-[0.18em] text-white/35">options</div>
                <p class="mt-2 text-sm leading-6 text-white/55">password, expiry, custom alias, burn-after clicks.</p>
            </div>
            <a href="/abuse" class="border border-white/10 p-4 transition hover:border-white/30">
                <div class="font-mono text-xs uppercase tracking-[0.18em] text-white/35"><?= h($t['abuse']) ?></div>
                <p class="mt-2 text-sm leading-6 text-white/55">report phishing, malware or spam links.</p>
            </a>
        </section>

        <footer class="mt-8 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
            <span>0x79.one</span>
            <span>fftrclo.store · takeitdown.space · mydiscordiscool.store · fckdupfuture.com · <?= date('Y') ?></span>
        </footer>
    </main>

    <script>
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

<?php else:
$homePosts = fetchRssPosts(10);
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>0x79</title>
    <link rel="icon" href="/logo.png" type="image/jpeg">
    <meta name="description" content="URL shortener, file/image host and paste host.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif'], mono: ['JetBrains Mono','ui-monospace','monospace'] } } } };
    </script>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-5 py-5 sm:px-7 lg:px-8">
        <header class="flex items-center justify-between border-b border-white/10 pb-5">
            <a href="/" class="flex items-center gap-2">
                <img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover">
                <span class="font-mono text-sm tracking-tight text-white">0x79</span>
            </a>
            <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
                <?php renderLangSelect($lang, $supported_langs, $LANG_META); ?>
                <span class="mx-1 h-4 w-px bg-white/10"></span>
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

        <section class="flex flex-1 items-center py-14 sm:py-20">
            <div class="w-full">
                <p class="mb-5 font-mono text-xs uppercase tracking-[0.22em] text-white/35">choose tool</p>
                <h1 class="max-w-2xl text-4xl font-semibold tracking-[-0.045em] text-white sm:text-5xl lg:text-6xl">
                    <?= h($t['home_h1']) ?>
                </h1>
                <p class="mt-5 max-w-xl text-base leading-7 text-white/50 sm:text-lg">
                    <?= h($t['home_lead']) ?>
                </p>

                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Tool 01 -->
                    <a href="/shorten" class="group relative flex flex-col justify-between border border-white/5 bg-[#111113] p-4 transition-all duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-[#141417] hover:shadow-[0_8px_24px_rgba(0,0,0,0.4)]">
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_#3b82f6]"></span>
                                    <span class="font-mono text-[10px] uppercase tracking-widest text-white/30">Tool 01</span>
                                </div>
                                <span class="font-mono text-sm text-white/25 transition-all duration-300 group-hover:translate-x-1 group-hover:text-blue-400">→</span>
                            </div>
                            <h2 class="mt-3 text-lg font-medium tracking-tight text-white transition-colors duration-300 group-hover:text-blue-400">
                                <?= h($t['home_tool1_title']) ?>
                            </h2>
                            <p class="mt-2 text-xs leading-relaxed text-white/45">
                                <?= h($t['home_tool1_desc']) ?>
                            </p>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-1.5">
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">alias</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">password</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">burn</span>
                        </div>
                    </a>

                    <!-- Tool 02 -->
                    <a href="/upload" class="group relative flex flex-col justify-between border border-white/5 bg-[#111113] p-4 transition-all duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-[#141417] hover:shadow-[0_8px_24px_rgba(0,0,0,0.4)]">
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]"></span>
                                    <span class="font-mono text-[10px] uppercase tracking-widest text-white/30">Tool 02</span>
                                </div>
                                <span class="font-mono text-sm text-white/25 transition-all duration-300 group-hover:translate-x-1 group-hover:text-emerald-400">→</span>
                            </div>
                            <h2 class="mt-3 text-lg font-medium tracking-tight text-white transition-colors duration-300 group-hover:text-emerald-400">
                                <?= h($t['home_tool2_title']) ?>
                            </h2>
                            <p class="mt-2 text-xs leading-relaxed text-white/45">
                                <?= h($t['home_tool2_desc']) ?>
                            </p>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-1.5">
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">zip</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">images</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">no svg</span>
                        </div>
                    </a>

                    <!-- Tool 03 -->
                    <a href="/paste" class="group relative flex flex-col justify-between border border-white/5 bg-[#111113] p-4 transition-all duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-[#141417] hover:shadow-[0_8px_24px_rgba(0,0,0,0.4)]">
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-violet-500 shadow-[0_0_8px_#8b5cf6]"></span>
                                    <span class="font-mono text-[10px] uppercase tracking-widest text-white/30">Tool 03</span>
                                </div>
                                <span class="font-mono text-sm text-white/25 transition-all duration-300 group-hover:translate-x-1 group-hover:text-violet-400">→</span>
                            </div>
                            <h2 class="mt-3 text-lg font-medium tracking-tight text-white transition-colors duration-300 group-hover:text-violet-400">
                                <?= h($t['home_tool3_title']) ?>
                            </h2>
                            <p class="mt-2 text-xs leading-relaxed text-white/45">
                                <?= h($t['home_tool3_desc']) ?>
                            </p>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-1.5">
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">text</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">raw</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">burn</span>
                        </div>
                    </a>

                    <!-- Tool 04 -->
                    <a href="/music" class="group relative flex flex-col justify-between border border-white/5 bg-[#111113] p-4 transition-all duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-[#141417] hover:shadow-[0_8px_24px_rgba(0,0,0,0.4)]">
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500 shadow-[0_0_8px_#f43f5e]"></span>
                                    <span class="font-mono text-[10px] uppercase tracking-widest text-white/30">Tool 04</span>
                                </div>
                                <span class="font-mono text-sm text-white/25 transition-all duration-300 group-hover:translate-x-1 group-hover:text-rose-400">→</span>
                            </div>
                            <h2 class="mt-3 text-lg font-medium tracking-tight text-white transition-colors duration-300 group-hover:text-rose-400">
                                <?= h($t['home_tool4_title']) ?>
                            </h2>
                            <p class="mt-2 text-xs leading-relaxed text-white/45">
                                <?= h($t['home_tool4_desc']) ?>
                            </p>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-1.5">
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">spotify</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">apple</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">youtube</span>
                        </div>
                    </a>

                    <!-- Tool 05 -->
                    <a href="/metadata" class="group relative flex flex-col justify-between border border-white/5 bg-[#111113] p-4 transition-all duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-[#141417] hover:shadow-[0_8px_24px_rgba(0,0,0,0.4)]">
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 shadow-[0_0_8px_#f59e0b]"></span>
                                    <span class="font-mono text-[10px] uppercase tracking-widest text-white/30">Tool 05</span>
                                </div>
                                <span class="font-mono text-sm text-white/25 transition-all duration-300 group-hover:translate-x-1 group-hover:text-amber-400">→</span>
                            </div>
                            <h2 class="mt-3 text-lg font-medium tracking-tight text-white transition-colors duration-300 group-hover:text-amber-400">
                                <?= h($t['home_tool5_title']) ?>
                            </h2>
                            <p class="mt-2 text-xs leading-relaxed text-white/45">
                                <?= h($t['home_tool5_desc']) ?>
                            </p>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-1.5">
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">exif</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">privacy</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">local</span>
                        </div>
                    </a>

                    <!-- Tool 06 -->
                    <a href="/secure-share" class="group relative flex flex-col justify-between border border-white/5 bg-[#111113] p-4 transition-all duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-[#141417] hover:shadow-[0_8px_24px_rgba(0,0,0,0.4)]">
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-500 shadow-[0_0_8px_#06b6d4]"></span>
                                    <span class="font-mono text-[10px] uppercase tracking-widest text-white/30">Tool 06</span>
                                </div>
                                <span class="font-mono text-sm text-white/25 transition-all duration-300 group-hover:translate-x-1 group-hover:text-cyan-400">→</span>
                            </div>
                            <h2 class="mt-3 text-lg font-medium tracking-tight text-white transition-colors duration-300 group-hover:text-cyan-400">
                                <?= h($t['home_tool6_title']) ?>
                            </h2>
                            <p class="mt-2 text-xs leading-relaxed text-white/45">
                                <?= h($t['home_tool6_desc']) ?>
                            </p>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-1.5">
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">aes-gcm</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">zero-knowledge</span>
                            <span class="rounded bg-white/[0.03] border border-white/5 px-1.5 py-0.5 font-mono text-[9px] text-white/40">secure</span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- News Section -->
        <section class="border-t border-white/10 py-10 sm:py-14">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-xl font-semibold text-white tracking-tight"><?= h($t['news_title']) ?></h2>
                    <p class="mt-1 text-xs text-white/45"><?= h($t['news_lead']) ?></p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="/posts" class="border border-white/10 px-3.5 py-1.5 font-mono text-xs text-white/60 transition hover:border-white/30 hover:text-white bg-black/20">
                        <?= h($t['news_all_posts']) ?>
                    </a>
                    <a href="/rss" target="_blank" class="flex items-center gap-2 border border-white/10 px-3.5 py-1.5 font-mono text-xs text-white/60 transition hover:border-orange-500/40 hover:text-orange-400 bg-black/20">
                        <svg class="h-3 w-3 fill-current" viewBox="0 0 24 24">
                            <path d="M6.18 15.64a2.18 2.18 0 11-2.18 2.18 2.18 2.18 0 012.18-2.18zM3 3a18 18 0 0118 18h-2.91A15.09 15.09 0 003 5.91zm0 6.06a11.94 11.94 0 0111.94 11.94H12A9 9 0 003 12z"/>
                        </svg>
                        <span><?= h($t['news_rss']) ?></span>
                    </a>
                </div>
            </div>

            <?php if (empty($homePosts)): ?>
                <div class="border border-white/5 bg-[#111113] p-8 text-center text-xs text-white/30 font-mono">
                    <?= h($t['news_no_posts']) ?>
                </div>
            <?php else: ?>
                <div class="grid gap-4 md:grid-cols-2">
                    <?php foreach ($homePosts as $post):
                        $postUrl = '/post/' . $post['id'];
                        $pubDate = (int)($post['pub_date'] ?? 0);
                        $dateStr = $pubDate ? date('d.m.Y', $pubDate) : '';
                    ?>
                        <a href="<?= h($postUrl) ?>" class="group flex gap-4 border border-white/5 bg-[#111113] p-4 transition duration-300 hover:border-white/15 hover:bg-[#141417]">
                            <?php if (!empty($post['image'])): ?>
                                <img src="<?= h($post['image']) ?>" alt="Post Thumbnail" class="h-20 w-28 shrink-0 object-cover border border-white/15 rounded bg-black/40">
                            <?php endif; ?>
                            <div class="flex flex-col justify-between min-w-0">
                                <div>
                                    <span class="font-mono text-[9px] uppercase tracking-wider text-white/30"><?= h($dateStr) ?></span>
                                    <h3 class="mt-1 text-sm font-medium text-white transition duration-300 group-hover:text-white/80 truncate"><?= h($post['title'] ?? '') ?></h3>
                                    <p class="mt-1 text-xs text-white/45 line-clamp-2 leading-relaxed">
                                        <?= h($post['description'] ?? '') ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <footer class="mt-8 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
            <span>0x79.one</span>
            <span>fftrclo.store · takeitdown.space · mydiscordiscool.store · fckdupfuture.com · <?= date('Y') ?></span>
        </footer>
    </main>
</body>
</html>
<?php endif; ?>