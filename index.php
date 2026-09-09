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
    header('Cache-Control: public, max-age=604800');
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
    header('Cache-Control: public, max-age=604800');
    header('X-Content-Type-Options: nosniff');
    echo $svg;
    exit;
}

if ($request_path === 'api/docs') {
    renderApiDocs();
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
        if (!checkCreateRateLimit()) {
            jsonResponse(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        [$ok, $err, $result] = createShortLink($long_url, $domain, $password, $expires_at, $max_clicks, $custom_code);

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
<html lang="<?= h($lang) ?>" dir="<?= h($LANG_DATA[$lang]['dir'] ?? 'ltr') ?>">
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

            $refHost = strtolower((string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
            $refHost = preg_replace('/^www\./', '', $refHost);

            recordClickAnalytics(
                $row,
                $code,
                $refHost,
                detectDeviceType($_SERVER['HTTP_USER_AGENT'] ?? ''),
                getenv('CLOUDFLARE_PROXY') === 'true' ? strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''), 0, 2)) : ''
            );

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

// Optional fields ("more options" section) — same set the API accepts.
$opt_password     = (string)($_POST['password'] ?? '');
$opt_expires_at   = (string)($_POST['expires_at'] ?? '');
$opt_max_clicks   = (string)($_POST['max_clicks'] ?? '');
$opt_custom_code  = (string)($_POST['custom_code'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['long_url'])) {
    requireFormCsrf();

    if (!checkCreateRateLimit()) {
        $ok = false;
        $err = 'rate_limited';
    } else {
        [$ok, $err, $result] = createShortLink(
            $_POST['long_url'],
            $selected_domain,
            $opt_password,
            $opt_expires_at,
            $opt_max_clicks,
            $opt_custom_code
        );
    }

    if ($ok) {
        $short_url = $result['short_url'];
    } elseif ($err === 'invalid_url' || $err === 'invalid_expiry') {
        $error = $t['err_invalid'];
    } elseif ($err === 'invalid_alias' || $err === 'alias_taken') {
        $error = $t['err_alias'] ?? $t['err_save'];
    } elseif ($err === 'rate_limited') {
        $error = $t['err_rate_limit'];
    } else {
        $error = $t['err_save'];
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" dir="<?= h($LANG_DATA[$lang]['dir'] ?? 'ltr') ?>">
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
        .domain-picker { position:relative; margin-top:10px; text-align:left; }
        .domain-trigger { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; min-height:56px; padding:8px 14px 8px 12px; border:1px solid var(--input-border); border-radius:12px; background:var(--input-bg); color:var(--ink); font:600 13px/1 inherit; cursor:pointer; transition:border-color .15s, background .15s, box-shadow .15s; }
        .domain-trigger:hover, .domain-trigger[aria-expanded="true"] { border-color:var(--accent); background:var(--card-bg); }
        .domain-trigger[aria-expanded="true"] { box-shadow:0 0 0 4px rgba(59,130,246,.12); }
        .domain-trigger-copy { display:flex; align-items:center; gap:10px; min-width:0; }
        .domain-mark { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; flex:none; border:1px solid var(--input-border); border-radius:8px; background:var(--card-bg); color:var(--accent); font:800 11px/1 ui-monospace,SFMono-Regular,Menlo,monospace; }
        .domain-trigger-text { display:grid; gap:4px; min-width:0; text-align:left; }
        .domain-kicker { color:var(--muted); font-size:9px; font-weight:800; letter-spacing:.1em; line-height:1; text-transform:uppercase; }
        #domain-current { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .domain-chevron { width:16px; height:16px; flex:none; stroke:var(--muted); fill:none; stroke-width:2; transition:transform .15s; }
        .domain-trigger[aria-expanded="true"] .domain-chevron { transform:rotate(180deg); stroke:var(--accent); }
        .domain-menu { position:absolute; left:0; right:0; top:calc(100% + 7px); z-index:20; display:grid; gap:4px; max-height:240px; overflow:auto; padding:8px; border:1px solid var(--card-border); border-radius:14px; background:var(--card-bg); box-shadow:0 18px 40px -24px rgba(20,30,60,.55); }
        .domain-menu[hidden] { display:none; }
        .domain-option { display:flex; align-items:center; justify-content:space-between; gap:12px; min-height:44px; padding:5px 9px; border:1px solid transparent; border-radius:9px; background:transparent; color:var(--ink); font:600 13px/1 inherit; text-align:left; cursor:pointer; }
        .domain-option:hover, .domain-option[aria-selected="true"] { border-color:var(--input-border); background:var(--input-bg); color:var(--accent); }
        .domain-option-content { display:flex; align-items:center; gap:10px; min-width:0; }
        .domain-option-mark { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; flex:none; border:1px solid var(--input-border); border-radius:7px; background:var(--card-bg); color:var(--muted); font:800 9px/1 ui-monospace,SFMono-Regular,Menlo,monospace; }
        .domain-option[aria-selected="true"] .domain-option-mark { border-color:var(--accent); color:var(--accent); }
        .domain-option-check { width:15px; height:15px; flex:none; opacity:0; stroke:var(--accent); fill:none; stroke-width:2.2; }
        .domain-option[aria-selected="true"] .domain-option-check { opacity:1; }
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
        .result-actions { display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-top:12px; }
        .result-action { display:inline-flex; align-items:center; justify-content:center; min-height:32px; padding:0 10px; border:1px solid var(--card-border); border-radius:8px; background:var(--input-bg); color:var(--muted); font:700 12px/1 inherit; text-decoration:none !important; cursor:pointer; }
        .result-action:hover { border-color:var(--accent); color:var(--accent) !important; background:var(--card-bg); }
        .result-action svg { width:14px; height:14px; margin-right:6px; stroke:currentColor; fill:none; stroke-width:1.8; }
        .qr-result { display:grid; justify-items:center; gap:10px; margin-top:16px; }
        .qr-result[hidden] { display:none; }
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
        .more-opts { margin-top:10px; text-align:left; }
        .more-opts summary {
            cursor:pointer; list-style:none; padding:10px 0; text-align:center;
            font-size:13px; font-weight:600; color:var(--muted); transition:color .15s;
        }
        .more-opts summary::-webkit-details-marker { display:none; }
        .more-opts summary::before { content:'+ '; color:var(--accent); }
        .more-opts[open] summary::before { content:'– '; }
        .more-opts summary:hover { color:var(--ink); }
        .more-opts-body { display:grid; gap:8px; padding-bottom:4px; }
        .more-opts input[type=text], .more-opts input[type=number], .more-opts input[type=datetime-local] {
            width:100%; padding:10px 14px; font:inherit; font-size:13px; border:1px solid var(--input-border); border-radius:10px;
            background:var(--input-bg); color:var(--ink); transition:border-color .15s, background .15s;
        }
        .more-opts input:focus { outline:none; border-color:var(--accent); background:var(--card-bg); }
        .preview-opt { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--muted); cursor:pointer; padding:2px 0; }
        .preview-opt input { accent-color:var(--accent); width:16px; height:16px; margin:0; }
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
            <p class="tagline"><?= h($t['tagline']) ?></p>
            <form method="POST" action="/">
                <input type="hidden" name="csrf" value="<?= h(formCsrfToken()) ?>">
                <input type="url" name="long_url" required autofocus placeholder="<?= h($t['url_placeholder']) ?>" value="<?= h($_POST['long_url'] ?? '') ?>">
                <div class="domain-picker">
                    <input type="hidden" name="domain" id="domain-value" value="<?= h($selected_domain) ?>">
                    <button type="button" class="domain-trigger" id="domain-trigger" aria-haspopup="listbox" aria-expanded="false" aria-label="<?= h($t['domain_label']) ?>">
                        <span class="domain-trigger-copy">
                            <span class="domain-mark" aria-hidden="true">0x</span>
                            <span class="domain-trigger-text"><span class="domain-kicker"><?= h($t['domain_label']) ?></span><span id="domain-current"><?= h($selected_domain) ?></span></span>
                        </span>
                        <svg class="domain-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                    </button>
                    <div class="domain-menu" id="domain-menu" role="listbox" aria-label="<?= h($t['domain_label']) ?>" hidden>
                        <?php foreach ($available_domains as $d): ?>
                            <button type="button" class="domain-option" role="option" data-domain-value="<?= h($d) ?>" aria-selected="<?= $d === $selected_domain ? 'true' : 'false' ?>">
                                <span class="domain-option-content"><span class="domain-option-mark" aria-hidden="true">0x</span><span><?= h($d) ?></span></span>
                                <svg class="domain-option-check" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <details class="more-opts"<?= ($opt_password !== '' || $opt_expires_at !== '' || $opt_max_clicks !== '' || $opt_custom_code !== '' || $error !== '') ? ' open' : '' ?>>
                    <summary><?= h($t['options_label']) ?></summary>
                    <div class="more-opts-body">
                        <input type="text" name="custom_code" value="<?= h($opt_custom_code) ?>" placeholder="<?= h($t['custom_code_label']) ?>" autocomplete="off" spellcheck="false">
                        <input type="text" name="password" value="<?= h($opt_password) ?>" placeholder="<?= h($t['password_label']) ?> (<?= h($t['burn_placeholder']) ?>)" autocomplete="off">
                        <input type="datetime-local" name="expires_at" value="<?= h($opt_expires_at) ?>" aria-label="<?= h($t['expires_label']) ?>">
                        <input type="number" name="max_clicks" value="<?= h($opt_max_clicks) ?>" min="1" max="1000000" placeholder="<?= h($t['max_clicks_label']) ?>">
                    </div>
                </details>
                <button type="submit"><?= h($t['create_link']) ?> →</button>
                <div class="result<?= $error !== '' ? ' error' : '' ?>">
                    <?php if ($short_url !== ''): ?>
                        <a href="<?= h($short_url) ?>" target="_blank" rel="noopener"><?= h($short_url) ?></a>
                        <div class="result-actions">
                            <button type="button" class="result-action" id="copy-link" data-url="<?= h($short_url) ?>" data-copied-label="<?= h($t['copied_label']) ?>" data-copy-label="<?= h($t['copy_label']) ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                <?= h($t['copy_label']) ?>
                            </button>
                        </div>
                        <div class="qr-result" id="qr-result"<?= $want_qr ? '' : ' hidden' ?>>
                            <a class="result-action" id="qr-download" href="/qr?d=<?= h(rawurlencode($short_url)) ?>" download="0x79-<?= h($result['short_code']) ?>.svg">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 15h3v3h-3zM21 15v6h-3M15 12h2M21 12v2"></path></svg>
                                <?= h($t['download_qr_label']) ?>
                            </a>
                            <img class="qr" id="qr-image" src="/qr?d=<?= h(rawurlencode($short_url)) ?>" alt="QR code">
                        </div>
                    <?php elseif ($error !== ''): ?>
                        <?= h($error) ?>
                    <?php else: ?>
                        <?= h($t['enter_link_hint']) ?>
                    <?php endif; ?>
                </div>
                <div class="opts">
                    <label><input type="checkbox" id="qr-toggle" name="qr" value="1" <?= $want_qr ? 'checked' : '' ?>> <?= h($t['qr_label']) ?></label>
                </div>
            </form>
            <a class="api-link" href="/api/docs">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 20.5L4 12l6-8.5M14 3.5L20 12l-6 8.5"></path></svg>
                <?= h($t['api_docs']) ?>
            </a>
        </div>
        <?php renderCardFooter(); ?>
    </main>
        <?php if ($short_url !== ''): ?>
        <script nonce="<?= $csp_nonce ?>">
            (function () {
                var copyButton = document.getElementById('copy-link');
                if (!copyButton) return;
                copyButton.addEventListener('click', function () {
                    var value = copyButton.getAttribute('data-url') || '';
                    var done = function () {
                        copyButton.textContent = copyButton.getAttribute('data-copied-label');
                        window.setTimeout(function () {
                            copyButton.textContent = copyButton.getAttribute('data-copy-label');
                        }, 1800);
                    };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(value).then(done).catch(function () {});
                        return;
                    }
                    var field = document.createElement('textarea');
                    field.value = value;
                    field.style.position = 'fixed';
                    field.style.opacity = '0';
                    document.body.appendChild(field);
                    field.select();
                    if (document.execCommand('copy')) done();
                    field.remove();
                });
            }());
        </script>
        <?php endif; ?>
        <?php if ($short_url !== ''): ?>
        <script nonce="<?= $csp_nonce ?>">
            (function () {
                var toggle = document.getElementById('qr-toggle');
                var result = document.getElementById('qr-result');
                if (!toggle || !result) return;
                toggle.addEventListener('change', function () {
                    result.hidden = !toggle.checked;
                });
            }());
        </script>
        <?php endif; ?>
        <script nonce="<?= $csp_nonce ?>">
            (function () {
                var trigger = document.getElementById('domain-trigger');
                var menu = document.getElementById('domain-menu');
                var value = document.getElementById('domain-value');
                var current = document.getElementById('domain-current');
                if (!trigger || !menu || !value || !current) return;
                function closeMenu() {
                    menu.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                }
                trigger.addEventListener('click', function () {
                    var open = menu.hidden;
                    menu.hidden = !open;
                    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                menu.querySelectorAll('.domain-option').forEach(function (option) {
                    option.addEventListener('click', function () {
                        value.value = option.dataset.domainValue;
                        current.textContent = option.dataset.domainValue;
                        menu.querySelectorAll('.domain-option').forEach(function (item) {
                            item.setAttribute('aria-selected', item === option ? 'true' : 'false');
                        });
                        closeMenu();
                    });
                });
                document.addEventListener('click', function (event) {
                    if (!event.target.closest('.domain-picker')) closeMenu();
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') closeMenu();
                });
            }());
        </script>
    <?php renderCardThemeScript(); ?>
</body>
</html>
<?php
exit;
