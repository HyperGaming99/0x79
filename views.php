<?php
declare(strict_types=1);

/** Floating language + theme controls shared by the remaining utility pages. */
function renderUiPreferences(bool $withTheme = false): void {
    static $rendered = false;
    global $lang;

    if ($rendered) return;
    $rendered = true;
    $currentLang = in_array((string)$lang, ['de', 'en'], true) ? (string)$lang : 'en';
    ?>
    <style>
        :root{--ui-bg:#0b0b0c;--ui-panel:#101011;--ui-ink:#f5f2ea;--ui-muted:rgba(255,255,255,.48);--ui-rule:rgba(255,255,255,.12);--ui-accent:#b8ff31}
        .ui-preferences{position:fixed;right:18px;bottom:18px;z-index:90;display:flex;align-items:stretch;border:1px solid var(--ui-rule);background:var(--ui-panel);color:var(--ui-ink);box-shadow:4px 4px 0 var(--ui-accent);font:700 10px/1 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.1em;text-transform:uppercase}
        .ui-pref-languages{display:flex;align-items:stretch}
        .ui-pref-language,.ui-theme-switch{display:flex;min-width:38px;height:40px;align-items:center;justify-content:center;border:0;border-right:1px solid var(--ui-rule);background:transparent;color:var(--ui-muted);font:inherit;letter-spacing:inherit;text-decoration:none;cursor:pointer}
        .ui-pref-language:hover,.ui-theme-switch:hover{background:var(--ui-ink);color:var(--ui-bg)}
        .ui-pref-language[aria-current="true"]{background:var(--ui-accent);color:#11110f}
        .ui-theme-switch{min-width:42px;border-right:0}
        .ui-theme-switch svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.7}
        @media(max-width:760px){.ui-pref-language,.ui-theme-switch{height:44px;min-width:38px}}
    </style>
    <script nonce="<?= $GLOBALS['csp_nonce'] ?>">
        document.addEventListener('DOMContentLoaded', function () {
            if (document.querySelector('.ui-preferences')) return;
            var currentLang = <?= json_encode($currentLang, JSON_UNESCAPED_SLASHES) ?>;

            var controls = document.createElement('div');
            controls.className = 'ui-preferences';
            controls.setAttribute('aria-label', currentLang === 'de' ? 'Anzeige und Sprache' : 'Display and language');

            var languages = document.createElement('div');
            languages.className = 'ui-pref-languages';
            ['de', 'en'].forEach(function (code) {
                var link = document.createElement('a');
                var url = new URL(window.location.href);
                url.searchParams.set('lang', code);
                link.href = url.pathname + url.search + url.hash;
                link.className = 'ui-pref-language';
                link.textContent = code;
                link.lang = code;
                link.setAttribute('aria-label', code === 'de' ? 'Deutsch' : 'English');
                if (code === currentLang) link.setAttribute('aria-current', 'true');
                languages.appendChild(link);
            });

            var theme = document.createElement('button');
            theme.type = 'button';
            theme.className = 'ui-theme-switch';
            theme.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3.5"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path></svg>';
            function syncTheme() {
                var light = document.documentElement.dataset.theme === 'light';
                theme.setAttribute('aria-label', light ? (currentLang === 'de' ? 'Dunkles Design' : 'Dark theme') : (currentLang === 'de' ? 'Helles Design' : 'Light theme'));
                theme.setAttribute('aria-pressed', light ? 'true' : 'false');
            }
            theme.addEventListener('click', function () {
                var next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
                document.documentElement.dataset.theme = next;
                localStorage.setItem('0x79-theme', next);
                syncTheme();
            });
            syncTheme();
            controls.appendChild(languages);
            if (<?= $withTheme ? 'true' : 'false' ?>) controls.appendChild(theme);
            document.body.appendChild(controls);
        });
    </script>
    <?php
}

/** Shared design system (gradient background, card, topbar) for the public-facing pages. */
function renderCardThemeStyles(): void {
    global $csp_nonce;
    ?>
    <script nonce="<?= $csp_nonce ?>">
        (function () {
            var saved = localStorage.getItem('0x79-theme');
            var preferred = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = saved || preferred;
        })();
    </script>
    <style>
        * { box-sizing: border-box; }
        :root {
            --bg-a:#eef4ff; --bg-b:#eef9f1; --page-bg:#fff; --card-bg:#fff; --card-border:#eceef2;
            --ink:#111; --muted:#667; --input-bg:#f7f8fa; --input-border:#dde1e8;
            --accent:#3b82f6; --accent-hover:#2f6fe0; --accent-contrast:#fff; --error:#dc2626;
            --shadow-card:0 20px 45px -20px rgba(20,30,60,.18); --shadow-brand:0 6px 16px -6px rgba(20,30,60,.35);
        }
        html[data-theme="dark"] {
            --bg-a:#1a1a1a; --bg-b:#1a1a1a; --page-bg:#1a1a1a; --card-bg:#262626; --card-border:#3d3d3d;
            --ink:#f2f2f2; --muted:#a3a3a3; --input-bg:#2e2e2e; --input-border:#454545;
            --accent:#5b9bff; --accent-hover:#75aaff; --accent-contrast:#0a0e15; --error:#f87171;
            --shadow-card:0 20px 45px -20px rgba(0,0,0,.6); --shadow-brand:0 6px 16px -6px rgba(0,0,0,.6);
        }
        body {
            margin:0; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;
            font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif; color:var(--ink);
            background:radial-gradient(circle at 20% 15%, var(--bg-a) 0%, var(--page-bg) 45%), radial-gradient(circle at 85% 85%, var(--bg-b) 0%, var(--page-bg) 50%);
            padding:24px; transition:background-color .2s, color .2s;
        }
        main { width:100%; max-width:440px; }
        .topbar { display:flex; justify-content:flex-end; gap:8px; margin-bottom:14px; }
        .topbar a, .topbar button {
            display:inline-flex; align-items:center; justify-content:center; height:32px; padding:0 11px;
            font:700 12px/1 inherit; letter-spacing:.02em; color:var(--muted); text-decoration:none;
            border:1px solid var(--card-border); border-radius:8px; background:var(--card-bg); cursor:pointer;
        }
        .topbar a[aria-current="true"] { color:var(--accent-contrast); background:var(--accent); border-color:var(--accent); }
        .topbar button { width:32px; padding:0; }
        .topbar button svg { width:16px; height:16px; stroke:currentColor; fill:none; stroke-width:1.8; }
        .card { background:var(--card-bg); border:1px solid var(--card-border); border-radius:20px; padding:40px 32px; text-align:center; box-shadow:var(--shadow-card); transition:background-color .2s, border-color .2s; }
        .brand { display:flex; align-items:center; justify-content:center; gap:12px; }
        .brand img { width:44px; height:44px; border-radius:12px; object-fit:cover; box-shadow:var(--shadow-brand); }
        .brand h1 { margin:0; font-size:34px; font-weight:800; letter-spacing:-.03em; }
        .tagline { margin:10px 0 0; color:var(--muted); font-size:14px; }
        .api-link {
            margin-top:22px; padding-top:20px; border-top:1px solid var(--card-border);
            display:flex; align-items:center; justify-content:center; gap:8px;
            font-size:13px; font-weight:700; letter-spacing:-.01em; color:var(--muted); text-decoration:none;
            transition:color .15s;
        }
        .api-link svg { width:15px; height:15px; stroke:currentColor; fill:none; stroke-width:2; transition:transform .15s; }
        .api-link:hover { color:var(--accent); }
        .api-link:hover svg { transform:translateX(2px); }
        .page-footer { margin-top:18px; display:flex; align-items:center; justify-content:center; gap:10px; font-size:12px; color:var(--muted); }
        .page-footer a {
            display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px;
            border:1px solid var(--card-border); border-radius:8px; color:var(--muted); transition:color .15s, border-color .15s;
        }
        .page-footer a:hover { color:var(--accent); border-color:var(--accent); }
        .page-footer a svg { width:15px; height:15px; }
    </style>
    <?php
}

function renderCardTopbar($lang): void {
    ?>
    <div class="topbar">
        <a href="?lang=de" aria-current="<?= $lang === 'de' ? 'true' : 'false' ?>">DE</a>
        <a href="?lang=en" aria-current="<?= $lang === 'en' ? 'true' : 'false' ?>">EN</a>
        <button type="button" id="theme-toggle" aria-label="<?= $lang === 'de' ? 'Design wechseln' : 'Toggle theme' ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3.5"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path></svg>
        </button>
    </div>
    <?php
}

function renderCardFooter(): void {
    ?>
    <div class="page-footer">
        <span>© 2026 0x79.one</span>
        <a href="https://github.com/HyperGaming99/0x79" target="_blank" rel="noopener" aria-label="GitHub">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 .5A11.5 11.5 0 0 0 .5 12a11.5 11.5 0 0 0 7.86 10.92c.58.1.79-.25.79-.56v-2c-3.2.7-3.88-1.37-3.88-1.37-.53-1.34-1.3-1.7-1.3-1.7-1.06-.72.08-.71.08-.71 1.17.08 1.79 1.2 1.79 1.2 1.04 1.79 2.73 1.27 3.4.97.1-.76.41-1.27.74-1.56-2.55-.29-5.23-1.27-5.23-5.67 0-1.25.45-2.27 1.18-3.07-.12-.29-.51-1.46.11-3.04 0 0 .96-.31 3.15 1.17a10.9 10.9 0 0 1 5.74 0c2.18-1.48 3.14-1.17 3.14-1.17.63 1.58.24 2.75.12 3.04.74.8 1.18 1.82 1.18 3.07 0 4.41-2.69 5.38-5.25 5.66.42.36.8 1.08.8 2.18v3.23c0 .31.21.67.8.56A11.5 11.5 0 0 0 23.5 12 11.5 11.5 0 0 0 12 .5Z"/></svg>
        </a>
    </div>
    <?php
}

function renderCardThemeScript(): void {
    global $csp_nonce;
    ?>
    <script nonce="<?= $csp_nonce ?>">
        document.getElementById('theme-toggle').addEventListener('click', function () {
            var next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            localStorage.setItem('0x79-theme', next);
        });
    </script>
    <?php
}

function renderAdminLogin($error = '') {
    global $t, $lang;

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['admin_login']) ?> — 0x79</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace; background:#0e0e10; color:#ebe9e3; padding:24px; }
        form { width:100%; max-width:420px; border:1px solid #ebe9e3; padding:24px; display:grid; gap:14px; }
        input, button { font:inherit; padding:12px; border:1px solid #ebe9e3; }
        input { background:transparent; color:#ebe9e3; width:100%; }
        button { background:#ebe9e3; color:#0e0e10; cursor:pointer; }
        a { color:#ebe9e3; }
        .err { color:#ff6b6b; }
        .muted { color:#a9a59c; }
    </style>
    <?php renderUiPreferences(); ?>
</head>
<body>
    <form method="POST" action="/admin" autocomplete="off">
        <h1><?= h($t['admin_login']) ?></h1>
        <p class="muted">/admin</p>
        <?php if ($error !== ''): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
        <input type="password" name="admin_password" placeholder="<?= h($t['admin_password']) ?>" required autofocus>
        <button type="submit"><?= h($t['admin_submit']) ?> →</button>
        <a href="/">← 0x79</a>
    </form>
</body>
</html>
    <?php
    exit;
}

function renderApiDocs() {
    global $lang, $csp_nonce, $available_domains;

    $host = cleanHost($_SERVER['HTTP_HOST'] ?? '0x79.one');
    $de = $lang === 'de';

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API docs — 0x79</title>
    <?php renderCardThemeStyles(); ?>
    <style>
        main { max-width:620px; }
        .card { text-align:left; }
        .card > .brand { justify-content:flex-start; }
        .lead { margin:10px 0 0; color:var(--muted); font-size:14px; }
        h2 {
            display:flex; align-items:center; gap:10px; margin:34px 0 12px; font-size:15px; font-weight:700;
            letter-spacing:-.01em; padding-top:24px; border-top:1px solid var(--card-border);
        }
        h2:first-of-type { padding-top:0; border-top:0; margin-top:28px; }
        .method {
            display:inline-flex; align-items:center; justify-content:center; min-width:48px; height:22px;
            border-radius:6px; font:700 11px/1 ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.02em;
            color:var(--accent-contrast); background:var(--accent);
        }
        .method.get { background:#10b981; }
        p { margin:10px 0; font-size:14px; line-height:1.6; color:var(--ink); }
        p.muted { color:var(--muted); font-size:13px; }
        code {
            font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; background:var(--input-bg);
            border:1px solid var(--input-border); border-radius:5px; padding:1px 6px;
        }
        pre {
            margin:10px 0; padding:14px 16px; overflow-x:auto; border-radius:12px;
            background:var(--input-bg); border:1px solid var(--input-border);
            font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; line-height:1.6; color:var(--ink);
        }
    </style>
</head>
<body>
    <main>
        <?php renderCardTopbar($lang); ?>
        <div class="card">
            <div class="brand">
                <img src="/logo.png" alt="">
                <h1>API</h1>
            </div>
            <p class="lead"><?= $de ? 'Kein Account, kein API-Key nötig.' : 'No account, no API key needed.' ?></p>

            <h2><span class="method">POST</span>/api</h2>
            <p><?= $de ? 'Legt einen Kurzlink an.' : 'Creates a short link.' ?></p>
            <pre>curl -X POST https://<?= h($host) ?>/api \
  -H "Content-Type: application/json" \
  -d '{"long_url":"https://example.com"}'</pre>
            <p class="muted"><?= $de ? 'Optionale Felder:' : 'Optional fields:' ?> <code>domain</code>, <code>password</code>, <code>expires_at</code>, <code>max_clicks</code>, <code>custom_code</code>, <code>preview_enabled</code></p>
            <p class="muted"><?= $de ? 'Verfügbare Domains:' : 'Available domains:' ?> <?php foreach ($available_domains as $i => $d): ?><?= $i > 0 ? ', ' : '' ?><code><?= h($d) ?></code><?php endforeach; ?></p>
            <pre>{
  "ok": true,
  "long_url": "https://example.com",
  "short_code": "Ab12Cd",
  "short_url": "https://<?= h($host) ?>/Ab12Cd",
  "domain": "<?= h($host) ?>",
  "expires_at": null,
  "has_password": false,
  "click_count": 0,
  "max_clicks": null
}</pre>

            <h2><span class="method get">GET</span>/api?code=…</h2>
            <p><?= $de ? 'Fragt einen Kurzlink ab.' : 'Looks up a short link.' ?></p>
            <pre>curl "https://<?= h($host) ?>/api?code=Ab12Cd"</pre>

            <h2><?= $de ? 'Fehler' : 'Errors' ?></h2>
            <pre>{ "ok": false, "error": "invalid_url" }
{ "ok": false, "error": "alias_taken" }
{ "ok": false, "error": "rate_limited" }
{ "ok": false, "error": "not_found" }
{ "ok": false, "error": "expired" }
{ "ok": false, "error": "burned" }</pre>

            <a class="api-link" href="/">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 20.5L8 12l6-8.5"></path></svg>
                0x79
            </a>
        </div>
        <?php renderCardFooter(); ?>
    </main>
    <?php renderCardThemeScript(); ?>
</body>
</html>
    <?php
    exit;
}

function renderAdminEdit($id) {
    global $t, $lang;

    $row = fetchLinkById($id);
    if (!$row) {
        http_response_code(404);
        exit('link not found');
    }

    $csrf = adminCsrfToken();
    $returnTo = sanitizeAdminReturnTo($_GET['return_to'] ?? '/admin');

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>link bearbeiten — 0x79</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace; background:#0e0e10; color:#ebe9e3; padding:24px; }
        main { width:100%; max-width:640px; border:1px solid #2a2a2d; background:#151518; padding:24px; display:grid; gap:16px; }
        h1 { margin:0; font-size:22px; }
        form { display:grid; gap:12px; }
        label { display:grid; gap:6px; color:#a9a59c; }
        input, button, a { font:inherit; }
        input { width:100%; border:1px solid #2a2a2d; background:#0e0e10; color:#ebe9e3; padding:11px 12px; }
        input[type="checkbox"] { width:auto; }
        .check { display:flex; gap:10px; align-items:center; color:#ebe9e3; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; }
        button, .btn { border:1px solid #ebe9e3; background:transparent; color:#ebe9e3; padding:10px 12px; text-decoration:none; cursor:pointer; }
        button:hover, .btn:hover { background:#ebe9e3; color:#0e0e10; }
        .muted { color:#a9a59c; }
    </style>
    <?php renderUiPreferences(); ?>
</head>
<body>
<main>
    <div>
        <h1>link bearbeiten</h1>
        <p class="muted"><code><?= h($row['short_code'] ?? '') ?></code></p>
    </div>

    <form method="POST" action="/admin/action">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
        <input type="hidden" name="action" value="update_link">
        <input type="hidden" name="link_id" value="<?= h($row['id'] ?? '') ?>">

        <label>ziel-url
            <input type="text" name="long_url" value="<?= h($row['long_url'] ?? '') ?>" required>
        </label>

        <label>custom alias / code
            <input type="text" name="short_code" value="<?= h($row['short_code'] ?? '') ?>" maxlength="32" pattern="[A-Za-z0-9]{1,32}" required>
        </label>

        <label>gültig bis
            <input type="datetime-local" name="expires_at" value="<?= h(htmlDatetimeLocalValue($row['expires_at'] ?? '')) ?>">
        </label>

        <label>burn after clicks
            <input type="number" name="max_clicks" min="1" max="1000000" step="1" value="<?= !empty($row['max_clicks']) ? h((string)$row['max_clicks']) : '' ?>" placeholder="<?= h($t['burn_placeholder']) ?>">
        </label>

        <label>neues passwort
            <input type="password" name="password" placeholder="leer lassen = unverändert" autocomplete="new-password">
        </label>

        <label class="check">
            <input type="checkbox" name="remove_password" value="1">
            passwort entfernen<?= !empty($row['password_hash']) ? ' (aktuell gesetzt)' : ' (aktuell keins)' ?>
        </label>

        <div class="actions">
            <button type="submit">speichern →</button>
            <a class="btn" href="<?= h($returnTo) ?>">abbrechen</a>
        </div>
    </form>
</main>
</body>
</html>
    <?php
    exit;
}

function renderAdminDashboard() {
    global $t, $lang;

    $csrf = adminCsrfToken();
    $notice = (string)($_GET['notice'] ?? '');
    $adminError = (string)($_GET['error'] ?? '');

    $tab = (string)($_GET['tab'] ?? 'links');
    if (!in_array($tab, ['links', 'protocols'], true)) {
        $tab = 'links';
    }

    $linksLimit = isset($_GET['links_limit']) ? (int)$_GET['links_limit'] : 25;
    $linksLimit = max(5, min($linksLimit, 100));
    $linksOffset = isset($_GET['links_offset']) ? (int)$_GET['links_offset'] : 0;
    $linksOffset = max(0, $linksOffset);
    $linksSearch = adminCleanSearch($_GET['q_links'] ?? '');

    $allSchemes = allConfigurableLinkSchemes();
    $enabledSchemes = allowedLinkSchemes();
    $customSchemes = customLinkSchemes();
    $schemeLabels = [
        'http' => 'Web', 'https' => 'Web TLS', 'ftp' => 'FTP', 'sftp' => 'SSH File Transfer', 'ftps' => 'FTP TLS', 'file' => 'lokale Datei',
        'mailto' => 'E-Mail', 'tel' => 'Telefon', 'sms' => 'SMS', 'ssh' => 'SSH', 'git' => 'Git', 'magnet' => 'Magnet/Torrent',
        'data' => 'Data URI', 'blob' => 'Blob URI', 'ws' => 'WebSocket', 'wss' => 'WebSocket TLS', 'irc' => 'IRC', 'xmpp' => 'XMPP',
        'ipfs' => 'IPFS', 'ipns' => 'IPNS', 'bitcoin' => 'Bitcoin', 'ethereum' => 'Ethereum', 'geo' => 'Geo', 'intent' => 'Android Intent',
        'market' => 'Android Market', 'itms-apps' => 'Apple App Store', 'steam' => 'Steam', 'discord' => 'Discord', 'tg' => 'Telegram', 'whatsapp' => 'WhatsApp',
    ];

    [$ok, $links, $status, $linksHasMore] = fetchAdminLinks($linksLimit, $linksOffset, $linksSearch);

    $linksPrev = max(0, $linksOffset - $linksLimit);
    $linksNext = $linksOffset + $linksLimit;

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['admin_dashboard']) ?> — 0x79</title>
    <style>
        :root { --bg:#0e0e10; --fg:#ebe9e3; --muted:#a9a59c; --rule:#2a2a2d; --card:#151518; --err:#ff6b6b; --ok:#5dd07a; }
        * { box-sizing:border-box; }
        body { margin:0; padding:32px 20px; font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace; background:var(--bg); color:var(--fg); }
        main { max-width:1180px; margin:0 auto; display:grid; gap:22px; }
        header { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; }
        h1 { margin:0; font-size:24px; }
        h2 { margin:0; font-size:16px; }
        a, button { color:var(--fg); }
        .actions, .tabs, .tools, .pager { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .btn, button, input, select { border:1px solid var(--fg); background:transparent; color:var(--fg); padding:8px 10px; font:inherit; text-decoration:none; }
        input, select { border-color:var(--rule); background:var(--bg); min-height:36px; }
        input::placeholder { color:var(--muted); }
        select option { background:var(--bg); color:var(--fg); }
        .btn, button { cursor:pointer; }
        .btn:hover, button:hover { background:var(--fg); color:var(--bg); }
        .tabs { border-bottom:1px solid var(--rule); padding-bottom:10px; }
        .tab { border:1px solid var(--rule); color:var(--muted); padding:9px 12px; text-decoration:none; }
        .tab.active { border-color:var(--fg); color:var(--fg); background:var(--card); }
        .panel { display:none; gap:14px; }
        .panel.active { display:grid; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-end; gap:14px; flex-wrap:wrap; }
        .tools form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .card { border:1px solid var(--rule); background:var(--card); overflow:auto; }
        table { width:100%; border-collapse:collapse; min-width:980px; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--rule); vertical-align:top; }
        th { color:var(--muted); font-weight:500; white-space:nowrap; }
        td code { color:var(--fg); }
        .url { max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .reason { max-width:460px; white-space:pre-wrap; overflow-wrap:anywhere; }
        .muted { color:var(--muted); }
        .err { color:var(--err); }
        .pill { border:1px solid var(--rule); padding:2px 6px; color:var(--muted); white-space:nowrap; }
        .pill.open { color:var(--ok); border-color:var(--ok); }
        form { margin:0; }
        .inline-actions { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
        .danger { border-color:var(--err); color:var(--err); }
        .danger:hover { background:var(--err); color:var(--bg); }
        .notice { border:1px solid var(--ok); color:var(--ok); padding:10px 12px; background:rgba(93,208,122,.06); }
        .admin-error { border:1px solid var(--err); color:var(--err); padding:10px 12px; background:rgba(255,107,107,.06); }
        button[disabled], .btn.disabled { opacity:.35; cursor:not-allowed; pointer-events:none; }
        button[disabled]:hover { background:transparent; color:var(--fg); }
        .count { color:var(--muted); font-size:12px; }
        .protocol-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:10px; }
        .protocol-option { border:1px solid var(--rule); background:var(--bg); padding:12px; display:flex; gap:10px; align-items:flex-start; }
        .protocol-option input { min-height:auto; margin-top:3px; }
        .protocol-option strong { display:block; }
        .protocol-option small { color:var(--muted); display:block; margin-top:2px; }
        @media (max-width: 720px) { body { padding:20px 12px; } .panel-head { align-items:stretch; } .tools, .tools form { width:100%; } input[type="search"] { width:100%; } }
    </style>
    <?php renderUiPreferences(); ?>
</head>
<body>
<main>
    <header>
        <div>
            <h1><?= h($t['admin_dashboard']) ?></h1>
            <div class="muted">links · <?= h((string)count($links)) ?> aktuell · protokolle · <?= h((string)count($enabledSchemes)) ?> aktiv</div>
        </div>
        <div class="actions">
            <a class="btn" href="/api/admin/links.csv"><?= h($t['admin_csv']) ?> ↓</a>
            <a class="btn" href="/">startseite</a>
            <form method="POST" action="/admin/logout"><input type="hidden" name="csrf" value="<?= h(adminCsrfToken()) ?>"><button type="submit"><?= h($t['admin_logout']) ?></button></form>
        </div>
    </header>

    <?php if ($notice !== ''): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($adminError !== ''): ?><div class="admin-error"><?= h($adminError) ?></div><?php endif; ?>

    <nav class="tabs" aria-label="admin tabs">
        <a class="tab <?= $tab === 'links' ? 'active' : '' ?>" href="<?= h(adminUrl(['tab' => 'links'])) ?>">links</a>
        <a class="tab <?= $tab === 'protocols' ? 'active' : '' ?>" href="<?= h(adminUrl(['tab' => 'protocols'])) ?>">protokolle</a>
    </nav>

    <section class="panel <?= $tab === 'links' ? 'active' : '' ?>" id="links">
        <div class="panel-head">
            <div>
                <h2>links</h2>
                <div class="count">zeige <?= h((string)($linksOffset + 1)) ?>–<?= h((string)($linksOffset + count($links))) ?><?= $linksSearch !== '' ? ' · suche: ' . h($linksSearch) : '' ?></div>
            </div>
            <div class="tools">
                <form method="GET" action="/admin">
                    <input type="hidden" name="tab" value="links">
                    <input type="search" name="q_links" value="<?= h($linksSearch) ?>" placeholder="code oder ziel-url suchen">
                    <select name="links_limit" aria-label="links pro seite">
                        <?php foreach ([10,25,50,100] as $n): ?>
                            <option value="<?= h((string)$n) ?>" <?= $linksLimit === $n ? 'selected' : '' ?>><?= h((string)$n) ?>/seite</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="links_offset" value="0">
                    <button type="submit">suchen</button>
                    <?php if ($linksSearch !== ''): ?><a class="btn" href="<?= h(adminUrl(['tab' => 'links', 'q_links' => '', 'links_offset' => 0])) ?>">reset</a><?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (!$ok): ?>
            <p class="err">Supabase error. Status: <?= h((string)$status) ?></p>
        <?php elseif (empty($links)): ?>
            <p class="muted">keine links gefunden.</p>
        <?php else: ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>code</th>
                            <th>short url</th>
                            <th>long url</th>
                            <th>clicks</th>
                            <th>burn</th>
                            <th>expires</th>
                            <th>password</th>
                            <th>created</th>
                            <th>actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                            <tr>
                                <td><code><?= h($link['short_code'] ?? '') ?></code></td>
                                <td><a href="<?= h($link['short_url'] ?? '#') ?>" target="_blank" rel="noopener"><?= h($link['short_url'] ?? '') ?></a></td>
                                <td class="url" title="<?= h($link['long_url'] ?? '') ?>"><?= h($link['long_url'] ?? '') ?></td>
                                <td><?= h((string)($link['click_count'] ?? 0)) ?></td>
                                <td><?= !empty($link['max_clicks']) ? h((string)$link['max_clicks']) : '<span class="muted">never</span>' ?></td>
                                <td><?= !empty($link['expires_at']) ? h(formatDateTime($link['expires_at'])) : '<span class="muted">never</span>' ?></td>
                                <td><?= !empty($link['has_password']) ? '<span class="pill">yes</span>' : '<span class="muted">no</span>' ?></td>
                                <td><?= h(formatDateTime($link['created_at'] ?? '')) ?></td>
                                <td>
                                    <div class="inline-actions">
                                        <a class="btn" href="/admin/edit?id=<?= h($link['id'] ?? '') ?>&return_to=<?= h(urlencode($_SERVER['REQUEST_URI'] ?? '/admin')) ?>">bearbeiten</a>
                                        <form method="POST" action="/admin/action" onsubmit="return confirm('Link <?= h($link['short_code'] ?? '') ?> wirklich löschen?');">
                                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="return_to" value="<?= h($_SERVER['REQUEST_URI'] ?? '/admin') ?>">
                                            <input type="hidden" name="action" value="delete_link">
                                            <input type="hidden" name="link_id" value="<?= h($link['id'] ?? '') ?>">
                                            <button class="danger" type="submit">löschen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pager">
                <a class="btn <?= $linksOffset <= 0 ? 'disabled' : '' ?>" href="<?= h(adminUrl(['tab' => 'links', 'links_offset' => $linksPrev])) ?>">← vorherige</a>
                <span class="muted">offset <?= h((string)$linksOffset) ?></span>
                <a class="btn <?= !$linksHasMore ? 'disabled' : '' ?>" href="<?= h(adminUrl(['tab' => 'links', 'links_offset' => $linksNext])) ?>">nächste →</a>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel <?= $tab === 'protocols' ? 'active' : '' ?>" id="protocols">
        <div class="panel-head">
            <div>
                <h2>erlaubte protokolle</h2>
                <div class="count">Diese Auswahl gilt sofort für neue Shortlinks und Admin-Bearbeitung.</div>
            </div>
        </div>

        <form method="POST" action="/admin/action" class="card" style="padding:16px; display:grid; gap:16px;">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="return_to" value="<?= h(adminUrl(['tab' => 'protocols'])) ?>">
            <input type="hidden" name="action" value="update_protocols">

            <p class="muted" style="margin:0;">
                Deaktiviere riskante Schemes wie <code>file</code>, <code>data</code>, <code>blob</code> oder <code>intent</code>, wenn du weniger Abuse-Risiko willst.
                <code>javascript:</code> bleibt immer verboten. Eigene Schemes müssen mit einem Buchstaben starten und dürfen nur <code>a-z</code>, <code>0-9</code>, <code>+</code>, <code>.</code> und <code>-</code> enthalten.
            </p>

            <?php foreach ($customSchemes as $scheme): ?>
                <input type="hidden" name="custom_schemes[]" value="<?= h($scheme) ?>">
            <?php endforeach; ?>

            <div class="card" style="padding:14px; display:grid; gap:10px;">
                <label>eigenes protokoll hinzufügen</label>
                <div class="tools">
                    <input type="text" name="new_scheme" placeholder="z. b. matrix oder myapp" autocomplete="off" style="max-width:360px;">
                    <span class="muted">ohne <code>:</code> eingeben</span>
                </div>
            </div>

            <div class="protocol-grid">
                <?php foreach ($allSchemes as $scheme): ?>
                    <?php $isCustomScheme = in_array($scheme, $customSchemes, true); ?>
                    <label class="protocol-option">
                        <input type="checkbox" name="schemes[]" value="<?= h($scheme) ?>" <?= in_array($scheme, $enabledSchemes, true) ? 'checked' : '' ?>>
                        <span>
                            <strong><?= h($scheme) ?>:</strong>
                            <small><?= h($schemeLabels[$scheme] ?? ($isCustomScheme ? 'custom' : '')) ?></small>
                            <?php if ($isCustomScheme): ?>
                                <label class="muted" style="display:flex; gap:6px; align-items:center; margin-top:8px;">
                                    <input type="checkbox" name="remove_custom_schemes[]" value="<?= h($scheme) ?>" style="width:auto; min-height:auto;"> entfernen
                                </label>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="inline-actions">
                <button type="submit">speichern →</button>
                <button type="button" onclick="document.querySelectorAll('#protocols input[name=&quot;schemes[]&quot;]').forEach(function(x){x.checked=true})">alle an</button>
                <button type="button" onclick="document.querySelectorAll('#protocols input[name=&quot;schemes[]&quot;]').forEach(function(x){x.checked=['http','https'].includes(x.value)})">nur http/https</button>
            </div>
        </form>
    </section>

</main>
</body>
</html>
    <?php
    exit;
}

function renderUrlPreviewPage($code, $target) {
    global $lang;

    $host = cleanHost($_SERVER['HTTP_HOST'] ?? '0x79.one');
    $targetHost = strtolower((string)(parse_url((string)$target, PHP_URL_HOST) ?: ''));
    $targetScheme = strtolower((string)(parse_url((string)$target, PHP_URL_SCHEME) ?: ''));
    $canPreview = in_array($targetScheme, ['http', 'https'], true) && $targetHost !== '';
    $goUrl = '/' . rawurlencode((string)$code) . '?go=1';
    $frameUrl = (string)$target;

    // Wenn die eigene Domain im iframe angezeigt wird, darf diese konkrete iframe-Antwort sich selbst einbetten lassen.
    if ($canPreview && $targetHost === strtolower($host)) {
        $frameUrl = addQueryParamToUrl($frameUrl, 'embed_preview', '1');
    }

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>preview — <?= h($host) ?></title>
    <?php renderUiPreferences(); ?>
    <style>
        *{box-sizing:border-box}html,body{height:100%}body{margin:0;background:#0b0b0c;color:#f5f2ea;font:14px/1.5 Inter,ui-sans-serif,system-ui,sans-serif}.top{height:64px;border-bottom:1px solid rgba(255,255,255,.12);display:flex;align-items:center;justify-content:space-between;padding:0 18px;gap:14px;background:#0b0b0c;position:sticky;top:0;z-index:20}.brand{font-family:monospace;text-decoration:none;color:#f5f2ea}.meta{min-width:0;color:rgba(255,255,255,.55);font-family:monospace;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.actions{display:flex;gap:10px;align-items:center}.btn{display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 13px;text-decoration:none;border:1px solid rgba(255,255,255,.16);font-family:monospace;font-size:12px;color:#f5f2ea}.primary{background:#f5f2ea;color:#0b0b0c;border-color:#f5f2ea}.frame-wrap{background:#fff;min-height:calc(100vh - 64px);position:relative}.frame{display:block;width:100%;height:calc(100vh - 64px);border:0;background:#fff}.hint{position:absolute;left:18px;right:18px;bottom:18px;padding:12px 14px;background:rgba(11,11,12,.88);border:1px solid rgba(255,255,255,.14);color:rgba(255,255,255,.72);font-family:monospace;font-size:12px;pointer-events:none}.notice{max-width:760px;margin:80px auto;padding:0 24px}.card{border:1px solid rgba(255,255,255,.12);background:#101011;padding:24px}.muted{color:rgba(255,255,255,.55)}code{word-break:break-all;background:#0b0b0c;border:1px solid rgba(255,255,255,.12);padding:8px;display:block;margin:14px 0;font-family:monospace;color:#f5f2ea}@media(max-width:720px){.top{height:auto;min-height:64px;align-items:flex-start;flex-direction:column;padding:14px}.actions{width:100%}.btn{flex:1}.frame{height:calc(100vh - 122px)}.frame-wrap{min-height:calc(100vh - 122px)}}
    </style>
</head>
<body>
    <div class="top">
        <a class="brand" href="/" style="display:inline-flex;align-items:center;gap:6px"><img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg object-cover">0x79</a>
        <div class="meta">iframe preview: <?= h($targetHost ?: $target) ?> · <?= h($target) ?></div>
        <div class="actions">
            <a class="btn" href="/<?= h($code) ?>?no_preview=1">reload normal</a>
            <a class="btn primary" href="<?= h($goUrl) ?>" rel="noopener">open target →</a>
        </div>
    </div>

    <?php if ($canPreview): ?>
        <main class="frame-wrap">
            <iframe class="frame" src="<?= h($frameUrl) ?>" referrerpolicy="no-referrer" sandbox="allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads"></iframe>
            <div class="hint">Wenn die Vorschau leer bleibt, blockiert die Zielseite iframe-Embedding. Dann bitte „open target“ nutzen.</div>
        </main>
    <?php else: ?>
        <main class="notice"><section class="card"><h1>preview not available</h1><p class="muted">Iframe preview funktioniert nur für http/https Ziele.</p><code><?= h($target) ?></code><a class="btn primary" href="<?= h($goUrl) ?>">open target →</a></section></main>
    <?php endif; ?>
</body>
</html>
    <?php
    exit;
}

function streamPreviewAsset() {
    $encoded = (string)($_GET['u'] ?? '');
    $url = previewBase64UrlDecode($encoded);
    [$valid, $validationError] = isPublicHttpUrl($url);
    if (!$valid) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'blocked: ' . $validationError;
        exit;
    }

    [$ok, $err, $body, $contentType, $status] = callPreviewEdgeAsset($url);
    if (!$ok || !is_string($body)) {
        http_response_code($status ?: 502);
        header('Content-Type: text/plain; charset=utf-8');
        echo $err ?: 'asset fetch failed';
        exit;
    }

    header('Content-Type: ' . ($contentType ?: 'application/octet-stream'));
    header('Cache-Control: public, max-age=3600');
    echo $body;
    exit;
}
