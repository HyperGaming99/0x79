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

$request_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

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

if ($request_path === 'api/docs') {
    renderApiDocs();
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
    requireAdminCsrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
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

        [$ok, $err, $result] = createShortLink($long_url, $domain, $password, $expires_at, $max_clicks, $custom_code, $preview_enabled);

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

    $row = fetchLinkByCode($code);

    if (!empty($row) && isset($row['long_url'])) {
        if (isExpiredRow($row)) {
            http_response_code(410);
            header('Content-Type: text/plain; charset=utf-8');
            exit($t['err_expired'] ?? 'expired');
        } elseif (isBurnedRow($row)) {
            http_response_code(410);
            header('Content-Type: text/plain; charset=utf-8');
            exit($t['err_burned'] ?? 'burned');
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

                $password_ok = false;

                if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals((string)($_POST['code'] ?? ''), $code)) {
                    $password_ok = password_verify($post_password, (string)$row['password_hash']);
                }

                if (!$password_ok) {
                    $password_error = $_SERVER['REQUEST_METHOD'] === 'POST' ? $t['err_password'] : '';

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
                getenv('CLOUDFLARE_PROXY') === 'true' ? strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''), 0, 2)) : ''
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
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit($t['err_notfound'] ?? 'not found');
    }
}

// Anything else that isn't the homepage is unknown.
if ($request_path !== '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('not found');
}

// ---------------------------------------------------------
// 2. CREATE LINK
// ---------------------------------------------------------
$short_url = '';
$error = '';
$want_qr = !empty($_POST['qr'] ?? null);
$selected_domain = (isset($_POST['domain']) && in_array($_POST['domain'], $available_domains, true))
    ? $_POST['domain'] : $available_domains[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['long_url'])) {
    requireFormCsrf();

    if (!checkCreateRateLimit()) {
        $ok = false;
        $err = 'rate_limited';
    } else {
        [$ok, $err, $result] = createShortLink($_POST['long_url'], $selected_domain);
    }

    if ($ok) {
        $short_url = $result['short_url'];
    } elseif ($err === 'invalid_url') {
        $error = $t['err_invalid'];
    } elseif ($err === 'rate_limited') {
        $error = $t['err_rate_limit'];
    } else {
        $error = $t['err_save'];
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['title']) ?></title>
    <link rel="icon" href="/logo.png" type="image/jpeg">
    <meta name="description" content="<?= h($t['lead']) ?>">
    <?php renderCardThemeStyles(); ?>
    <style>
        form { margin-top:28px; }
        input[type=url] {
            width:100%; padding:14px 16px; font:inherit; border:1px solid var(--input-border); border-radius:12px;
            background:var(--input-bg); color:var(--ink); text-align:center; transition:border-color .15s, background .15s;
        }
        input[type=url]:focus { outline:none; border-color:var(--accent); background:var(--card-bg); box-shadow:0 0 0 4px rgba(59,130,246,.12); }
        select[name=domain] {
            width:100%; margin-top:10px; padding:12px 16px; font:inherit; font-size:13px; font-weight:600; color:var(--muted);
            border:1px solid var(--input-border); border-radius:12px; background:var(--input-bg); text-align:center;
            text-align-last:center; appearance:none; -webkit-appearance:none; cursor:pointer; transition:border-color .15s, background .15s;
        }
        select[name=domain]:focus { outline:none; border-color:var(--accent); background:var(--card-bg); box-shadow:0 0 0 4px rgba(59,130,246,.12); }
        select[name=domain] option { background:var(--card-bg); color:var(--ink); }
        button[type=submit] {
            margin-top:12px; width:100%; padding:14px 16px; font:inherit; font-weight:700; letter-spacing:-.01em;
            border:0; border-radius:12px; background:var(--accent); color:var(--accent-contrast); cursor:pointer; transition:background .15s, transform .1s;
        }
        button[type=submit]:hover { background:var(--accent-hover); }
        button[type=submit]:active { transform:scale(.98); }
        .result { margin-top:20px; min-height:22px; font-weight:600; word-break:break-all; }
        .result a { color:var(--accent); text-decoration:none; }
        .result a:hover { text-decoration:underline; }
        .result.error { color:var(--error); }
        .opts { margin-top:22px; display:flex; justify-content:center; font-size:13px; font-weight:600; color:var(--muted); }
        .opts label { display:flex; align-items:center; gap:9px; cursor:pointer; }
        .opts input[type=checkbox] {
            appearance:none; -webkit-appearance:none; width:19px; height:19px; margin:0; flex:none;
            border:1.5px solid var(--input-border); border-radius:6px; background:var(--input-bg);
            cursor:pointer; position:relative; transition:background .15s, border-color .15s;
        }
        .opts input[type=checkbox]:hover { border-color:var(--accent); }
        .opts input[type=checkbox]:checked { background:var(--accent); border-color:var(--accent); }
        .opts input[type=checkbox]:checked::after {
            content:''; position:absolute; left:6px; top:2px; width:5px; height:9px;
            border:solid var(--accent-contrast); border-width:0 2px 2px 0; transform:rotate(45deg);
        }
        .opts input[type=checkbox]:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
        .qr { margin-top:16px; width:140px; height:140px; border:1px solid var(--card-border); border-radius:12px; }
    </style>
</head>
<body>
    <main>
        <?php renderCardTopbar($lang); ?>
        <div class="card">
            <div class="brand">
                <img src="/logo.png" alt="">
                <h1>0x79</h1>
            </div>
            <p class="tagline"><?= $lang === 'de' ? 'Lange Links, kurz gemacht.' : 'Long links, made short.' ?></p>
            <form method="POST" action="/">
                <input type="hidden" name="csrf" value="<?= h(formCsrfToken()) ?>">
                <input type="url" name="long_url" required autofocus placeholder="https://some-long.link/" value="<?= h($_POST['long_url'] ?? '') ?>">
                <select name="domain" aria-label="<?= $lang === 'de' ? 'Domain' : 'Domain' ?>">
                    <?php foreach ($available_domains as $d): ?>
                        <option value="<?= h($d) ?>" <?= $d === $selected_domain ? 'selected' : '' ?>><?= h($d) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><?= $lang === 'de' ? 'Link erstellen' : 'Create link' ?> →</button>
                <div class="result<?= $error !== '' ? ' error' : '' ?>">
                    <?php if ($short_url !== ''): ?>
                        <a href="<?= h($short_url) ?>" target="_blank" rel="noopener"><?= h($short_url) ?></a>
                        <?php if ($want_qr): ?><br><img class="qr" src="/qr?d=<?= h(rawurlencode($short_url)) ?>" alt="QR code"><?php endif; ?>
                    <?php elseif ($error !== ''): ?>
                        <?= h($error) ?>
                    <?php else: ?>
                        <?= $lang === 'de' ? 'Gib oben einen Link ein' : 'Enter a link above to compress' ?>
                    <?php endif; ?>
                </div>
                <div class="opts">
                    <label><input type="checkbox" name="qr" value="1" <?= $want_qr ? 'checked' : '' ?>> <?= $lang === 'de' ? 'QR-Code ausgeben' : 'Output QR code' ?></label>
                </div>
            </form>
            <a class="api-link" href="/api/docs">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 20.5L4 12l6-8.5M14 3.5L20 12l-6 8.5"></path></svg>
                <?= $lang === 'de' ? 'API-Dokumentation' : 'API docs' ?>
            </a>
        </div>
        <?php renderCardFooter(); ?>
    </main>
    <?php renderCardThemeScript(); ?>
</body>
</html>
<?php
exit;
