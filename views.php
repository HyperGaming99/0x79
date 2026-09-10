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
    global $csp_nonce, $landscape_backgrounds;
    ?>
    <script nonce="<?= $csp_nonce ?>">
        (function () {
            var saved = localStorage.getItem('0x79-theme');
            var preferred = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = saved || preferred;
        })();
        document.addEventListener('DOMContentLoaded', function () {
            var scenes = <?= json_encode($landscape_backgrounds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            var credit = document.getElementById('landscape-credit');
            if (!scenes.length) return;
            var layers = [document.createElement('div'), document.createElement('div')];
            layers.forEach(function (layer) {
                layer.className = 'landscape-layer';
                document.body.prepend(layer);
            });
            var index = 0;
            var activeLayer = 0;
            function showScene(initial) {
                var scene = scenes[index];
                var root = document.documentElement;
                var image = new Image();
                var applied = false;
                function applyScene() {
                    if (applied) return;
                    applied = true;
                    var nextLayer = initial ? layers[activeLayer] : layers[1 - activeLayer];
                    nextLayer.style.backgroundImage = 'url("' + scene.image + '")';
                    nextLayer.classList.add('is-visible');
                    if (!initial) layers[activeLayer].classList.remove('is-visible');
                    activeLayer = initial ? activeLayer : 1 - activeLayer;
                    root.style.setProperty('--landscape-tint', scene.tint);
                }
                image.onload = function () {
                    applyScene();
                };
                image.onerror = applyScene;
                image.src = scene.image;
                if (credit) {
                    credit.innerHTML = '<span>Foto von © </span><a href="' + scene.url + '" target="_blank" rel="noopener">' + scene.artist + '</a>';
                }
                index = (index + 1) % scenes.length;
            }
            showScene(true);
            window.setInterval(showScene, 12000);
        });
    </script>
    <style>
        /* Registered color properties let the page background gradient fade
           between themes instead of snapping (browsers without @property
           support just switch instantly). */
        @property --bg-a { syntax: '<color>'; inherits: true; initial-value: #eef4ff; }
        @property --bg-b { syntax: '<color>'; inherits: true; initial-value: #eef9f1; }
        * { box-sizing: border-box; }
        html { color-scheme: light; }
        html[data-theme="dark"] { color-scheme: dark; }
        :root {
            --bg-a:#eef4ff; --bg-b:#eef9f1; --page-bg:#fff; --card-bg:rgba(255,255,255,.86); --card-border:#eceef2;
            --ink:#111; --muted:#667; --input-bg:rgba(247,248,250,.82); --input-border:#dde1e8;
            --accent:#3b82f6; --accent-hover:#2f6fe0; --accent-contrast:#fff; --error:#dc2626;
            --shadow-card:0 20px 45px -20px rgba(20,30,60,.18); --shadow-brand:0 6px 16px -6px rgba(20,30,60,.35);
        }
        html[data-theme="dark"] {
            --bg-a:#1a1a1a; --bg-b:#1a1a1a; --page-bg:#1a1a1a; --card-bg:rgba(38,38,38,.88); --card-border:#3d3d3d;
            --ink:#f2f2f2; --muted:#a3a3a3; --input-bg:rgba(46,46,46,.84); --input-border:#454545;
            --accent:#5b9bff; --accent-hover:#75aaff; --accent-contrast:#0a0e15; --error:#f87171;
            --shadow-card:0 20px 45px -20px rgba(0,0,0,.6); --shadow-brand:0 6px 16px -6px rgba(0,0,0,.6);
        }
        html[data-theme="light"] { --muted:#334155; --landscape-tint:rgba(255,255,255,.22); }
        body {
            margin:0; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; isolation:isolate;
            font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif; color:var(--ink);
            background:radial-gradient(circle at 20% 15%, var(--bg-a) 0%, var(--page-bg) 45%), radial-gradient(circle at 85% 85%, var(--bg-b) 0%, var(--page-bg) 50%);
            padding:24px; transition:background-color .2s, color .2s;
        }
        .landscape-layer { position:fixed; inset:0; z-index:-2; background-position:center; background-size:cover; background-repeat:no-repeat; opacity:0; filter:saturate(1.12) contrast(1.03); transform:scale(1.03); transition:opacity .7s ease; pointer-events:none; }
        .landscape-layer.is-visible { opacity:.34; }
        body::after { content:""; position:fixed; inset:0; z-index:-1; background:var(--landscape-tint, rgba(255,255,255,.24)); mix-blend-mode:multiply; pointer-events:none; transition:background 1s ease; }
        html[data-theme="light"] body::after { background:rgba(255,255,255,.2); mix-blend-mode:screen; }
        body > * { position:relative; z-index:0; }
        body.home-page { justify-content:center; padding-top:24px; padding-bottom:24px; }
        body.home-page .landscape-layer.is-visible { opacity:.52; }
        body.home-page::after { background:rgba(22,17,30,.34); mix-blend-mode:multiply; }
        html[data-theme="light"] body.home-page::after { background:rgba(255,255,255,.08); mix-blend-mode:multiply; }
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
        .page-footer { margin-top:18px; display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap; font-size:12px; font-weight:700; letter-spacing:.01em; color:var(--muted); text-shadow:0 1px 2px rgba(255,255,255,.18); }
        .footer-version { color:var(--ink); font-weight:800; }
        .page-footer a {
            display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px;
            border:1px solid var(--card-border); border-radius:8px; color:var(--muted); transition:color .15s, border-color .15s;
        }
        .page-footer a:hover { color:var(--accent); border-color:var(--accent); }
        .page-footer a svg { width:15px; height:15px; }
        a.footer-status {
            width:auto; gap:6px; padding:0 11px; font-size:11px; font-weight:800; letter-spacing:.04em; text-decoration:none;
        }
        a.footer-status svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
        .landscape-credit { position:fixed; left:16px; bottom:14px; z-index:10; max-width:calc(100vw - 32px); color:#fff; font-size:11px; font-weight:800; letter-spacing:.01em; line-height:1.3; text-shadow:0 1px 4px rgba(0,0,0,.9); }
        .landscape-credit a { color:#fff; text-decoration:none; }
        .landscape-credit a:hover { text-decoration:underline; text-underline-offset:2px; }
        html[data-theme="light"] .page-footer { color:#334155; text-shadow:0 1px 2px rgba(255,255,255,.7); }
        html[data-theme="light"] .footer-version { color:#0f172a; }
        html[data-theme="light"] .page-footer a { color:#334155; border-color:rgba(30,41,59,.28); }
        /* Theme-switch animation: only applied while .theme-anim is set (see
           renderCardThemeScript), so page loads and hover effects stay snappy. */
        .theme-anim body {
            transition: --bg-a .35s ease, --bg-b .35s ease, background-color .35s ease, color .35s ease !important;
        }
        .theme-anim body *, .theme-anim body *::before, .theme-anim body *::after {
            transition: background-color .35s ease, border-color .35s ease, color .35s ease,
                        box-shadow .35s ease, fill .35s ease, stroke .35s ease !important;
        }
        /* While a view transition runs, element transitions must stay off -
           the animated clip-path on ::view-transition-new(root) is what shows. */
        html.vt-active, html.vt-active * { transition: none !important; }
            ::view-transition-old(root), ::view-transition-new(root) { animation: none; }
            ::view-transition-new(root) { mix-blend-mode: normal; }
        /* Right-side settings panel keeps the full language list visible. */
        .sidebar-backdrop { position: fixed; inset: 0; z-index: 50; background: rgba(10,10,14,.42); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); opacity: 0; transition: opacity .2s ease; }
        .sidebar-backdrop[hidden] { display: none; }
        .sidebar-backdrop.is-open { opacity: 1; }
        .sidebar-panel {
            position: absolute; top: 10px; right: 10px; bottom: auto; display: flex; flex-direction: column;
            width: min(340px, calc(100% - 20px)); max-height: calc(100dvh - 20px); padding: 22px 20px;
            background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 18px;
            box-shadow: -18px 0 45px -28px rgba(20,30,60,.4); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            transform: translateX(calc(100% + 12px)); transition: transform .3s cubic-bezier(.16,1,.3,1);
        }
        .sidebar-backdrop.is-open .sidebar-panel { transform: none; }
        .sidebar-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
        .sidebar-title { font-size: 16px; font-weight: 800; letter-spacing: -.01em; }
        .sidebar-close { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border: 1px solid var(--card-border); border-radius: 10px; background: transparent; color: var(--muted); cursor: pointer; transition: color .15s, border-color .15s, background-color .15s; }
        .sidebar-close:hover { color: var(--accent); border-color: var(--accent); }
        .sidebar-close svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
        .sidebar-label { margin: 0 0 9px; color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .language-list { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; flex: 1 1 auto; min-height: 0; overflow-y: auto; padding-right: 4px; scrollbar-width: thin; scrollbar-color: var(--input-border) transparent; }
        .language-list::-webkit-scrollbar { width: 6px; }
        .language-list::-webkit-scrollbar-thumb { background: var(--input-border); border-radius: 3px; }
        .language-list a { position: relative; display: flex; align-items: center; gap: 8px; min-height: 40px; padding: 8px 9px; border: 1px solid transparent; border-radius: 10px; color: var(--ink); font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; }
        .language-list a > span { overflow: hidden; text-overflow: ellipsis; }
        .language-list a:hover { background: var(--input-bg); border-color: var(--input-border); }
        .language-list a.active { color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, transparent); border-color: color-mix(in srgb, var(--accent) 30%, transparent); }
        .language-list img { flex: none; border-radius: 2px; object-fit: cover; }
        .lang-check { flex: none; width: 13px; height: 13px; margin-left: auto; stroke: currentColor; fill: none; stroke-width: 2.6; stroke-linecap: round; stroke-linejoin: round; visibility: hidden; }
        .language-list a.active .lang-check { visibility: visible; }
        .sidebar-divider { height: 1px; margin: 22px 0; background: var(--card-border); }
        .sidebar-theme { display: grid; gap: 10px; }
        .sidebar-theme .sidebar-label { margin: 0; }
        .theme-choices { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .theme-choice { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; min-height: 48px; padding: 7px 4px; border: 1px solid var(--card-border); border-radius: 10px; background: transparent; color: var(--muted); font: 700 11px/1 inherit; letter-spacing: .03em; cursor: pointer; transition: color .15s, border-color .15s, background-color .15s; }
        .theme-choice svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .theme-choice:hover { color: var(--ink); background: var(--input-bg); }
        .theme-choice[aria-pressed="true"] { border-color: var(--accent); color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, transparent); }
        @media (prefers-reduced-motion: reduce) {
            .landscape-layer, .sidebar-backdrop, .sidebar-panel { transition: none !important; }
        }
        @media (max-width: 420px) { .sidebar-panel { top: 6px; right: 6px; max-height: calc(100dvh - 12px); width: calc(100% - 12px); padding: 20px 16px; } }
        @media (max-width: 520px) { body.home-page { padding:18px 12px 64px; } body.home-page .card { border-width:6px; } body.home-page .brand, body.home-page form { padding-left:18px; padding-right:18px; } body.home-page .tagline { padding-left:18px; padding-right:18px; } }
    </style>
    <?php
}

function renderCardTopbar($lang): void {
    global $supported_langs, $LANG_DATA, $t;
    ?>
    <div class="topbar">
        <button type="button" id="settings-toggle" aria-haspopup="dialog" aria-label="<?= h($t['settings']) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1,1-1.73l.43-.25a2 2 0 0 1,2 0l.15.08a2 2 0 0 0,2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1,1-1.74l.15-.09a2 2 0 0 0,.73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        </button>
    </div>
    <div class="sidebar-backdrop" id="settings-modal" hidden>
        <aside class="sidebar-panel" role="dialog" aria-modal="true" aria-label="<?= h($t['settings']) ?>">
            <div class="sidebar-heading">
                <div class="sidebar-title"><?= h($t['settings']) ?></div>
                <button type="button" class="sidebar-close" id="settings-close" aria-label="<?= h($t['close']) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
                </button>
            </div>
            <p class="sidebar-label"><?= h($t['language']) ?></p>
            <nav class="language-list" aria-label="<?= h($t['language']) ?>">
                <?php foreach ($supported_langs as $code):
                    $meta = $LANG_DATA[$code]; ?>
                <a href="?lang=<?= h($code) ?>" lang="<?= h($code) ?>"<?= $code === $lang ? ' class="active" aria-current="true"' : '' ?>>
                    <img src="/assets/flags/<?= h($meta['flag']) ?>.svg" alt="" width="18" height="12">
                    <span><?= h($meta['label']) ?></span>
                    <svg class="lang-check" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12.5l5 5L20 6.5"></path></svg>
                </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-divider"></div>
            <div class="sidebar-theme">
                <span class="sidebar-label"><?= h($t['theme']) ?></span>
                <div class="theme-choices" role="group" aria-label="<?= h($t['theme']) ?>">
                    <button type="button" class="theme-choice" data-theme-choice="system" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="4" width="19" height="12.5" rx="2"></rect><path d="M8.5 20h7M12 16.5V20"></path></svg>
                        <span><?= h($t['theme_system']) ?></span>
                    </button>
                    <button type="button" class="theme-choice" data-theme-choice="dark" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.6 13.2A8.4 8.4 0 0 1 10.8 3.4a8.4 8.4 0 1 0 9.8 9.8Z"></path></svg>
                        <span><?= h($t['theme_dark']) ?></span>
                    </button>
                    <button type="button" class="theme-choice" data-theme-choice="light" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3.6"></circle><path d="M12 2.5v2.2M12 19.3v2.2M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6"></path></svg>
                        <span><?= h($t['theme_light']) ?></span>
                    </button>
                </div>
            </div>
        </aside>
    </div>
    <?php
}

function renderCardFooter(): void {
    global $app_version, $landscape_backgrounds, $lang;
    $first_landscape = $landscape_backgrounds[0] ?? null;
    $status_locale = $lang === 'de' ? 'de' : 'en';
    ?>
    <?php if ($first_landscape): ?>
    <div class="landscape-credit" id="landscape-credit">
        <span>Foto von © </span><a href="<?= h($first_landscape['url']) ?>" target="_blank" rel="noopener"><?= h($first_landscape['artist']) ?></a>
    </div>
    <?php endif; ?>
    <div class="page-footer">
        <span>© 2026 0x79.one</span>
        <span class="footer-version">Current version v<?= h($app_version) ?></span>
        <a class="footer-status" href="https://status.arolg.dev/<?= h($status_locale) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12h4l2.5 7 5-14 2.5 7h5"></path></svg>
            <span>Status</span>
        </a>
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
        var settingsToggle = document.getElementById('settings-toggle');
        var modal = document.getElementById('settings-modal');
        var lastFocus = null;

        function openSettings() {
            lastFocus = document.activeElement;
            modal.hidden = false;
            void modal.offsetWidth; // register transitions before toggling .is-open
            modal.classList.add('is-open');
            var first = modal.querySelector('.language-list a, .theme-button');
            if (first) first.focus();
        }

        function closeSettings() {
            modal.classList.remove('is-open');
            setTimeout(function () { modal.hidden = true; }, 150); // match leave transition
            if (lastFocus && lastFocus.focus) lastFocus.focus();
        }

        settingsToggle.addEventListener('click', openSettings);
        document.getElementById('settings-close').addEventListener('click', closeSettings);
        modal.addEventListener('click', function (e) { if (e.target === modal) closeSettings(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) closeSettings();
        });

        var themeChoices = Array.from(document.querySelectorAll('[data-theme-choice]'));
        var systemTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

        function syncThemeChoices() {
            var savedTheme = localStorage.getItem('0x79-theme');
            var selected = savedTheme || 'system';
            themeChoices.forEach(function (choice) {
                choice.setAttribute('aria-pressed', choice.dataset.themeChoice === selected ? 'true' : 'false');
            });
        }

        themeChoices.forEach(function (choice) {
            choice.addEventListener('click', function () {
                var selected = choice.dataset.themeChoice;
                var next = selected === 'system' ? systemTheme : selected;
                var root = document.documentElement;
                var rect = choice.getBoundingClientRect();
                var x = rect.left + rect.width / 2;
                var y = rect.top + rect.height / 2;

                if (selected === 'system') localStorage.removeItem('0x79-theme');
                else localStorage.setItem('0x79-theme', selected);

                function applyTheme() {
                    root.dataset.theme = next;
                }

                if (typeof document.startViewTransition !== 'function') {
                    root.classList.add('theme-anim');
                    void root.offsetWidth;
                    applyTheme();
                    syncThemeChoices();
                    setTimeout(function () { root.classList.remove('theme-anim'); }, 450);
                    return;
                }

                var radius = Math.hypot(
                    Math.max(x, window.innerWidth - x),
                    Math.max(y, window.innerHeight - y)
                );
                root.classList.add('vt-active');
                var transition = document.startViewTransition(applyTheme);
                transition.ready.then(function () {
                    var reveal = document.documentElement.animate(
                        { clipPath: ['circle(0px at ' + x + 'px ' + y + 'px)', 'circle(' + radius + 'px at ' + x + 'px ' + y + 'px)'] },
                        { duration: 600, easing: 'cubic-bezier(.16,1,.3,1)', fill: 'both', pseudoElement: '::view-transition-new(root)' }
                    );
                    syncThemeChoices();
                    reveal.finished.then(function () { root.classList.remove('vt-active'); }).catch(function () { root.classList.remove('vt-active'); });
                }).catch(function () { root.classList.remove('vt-active'); });
                transition.finished.catch(function () { root.classList.remove('vt-active'); });
            });
        });
        syncThemeChoices();
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
    global $lang, $csp_nonce, $available_domains, $LANG_DATA, $t;

    $host = cleanHost($_SERVER['HTTP_HOST'] ?? '0x79.one');
    $dir = $LANG_DATA[$lang]['dir'] ?? 'ltr';

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" dir="<?= h($dir) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API — 0x79</title>
    <?php renderCardThemeStyles(); ?>
    <style>
        main { max-width:920px; }
        .card { text-align:left; padding:32px 30px; }
        .card > .brand { justify-content:flex-start; }
        .lead { margin:10px 0 0; color:var(--muted); font-size:14px; }
        h2 {
            display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin:36px 0 14px; font-size:16px; font-weight:800;
            letter-spacing:-.01em; padding-top:26px; border-top:1px solid var(--card-border);
        }
        h2:first-of-type { padding-top:0; border-top:0; margin-top:26px; }
        .h2-desc { font-size:12px; font-weight:600; color:var(--muted); letter-spacing:0; }
        .method {
            display:inline-flex; align-items:center; justify-content:center; min-width:52px; height:24px;
            border-radius:7px; font:800 11px/1 ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.04em;
            color:var(--accent-contrast); background:var(--accent);
        }
        .method.get { background:#10b981; }
        .method.opt { background:var(--muted); }
        .api-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; align-items:start; margin:14px 0 4px; }
        .api-col-label { margin:0 0 8px; font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); }
        p { margin:10px 0; font-size:14px; line-height:1.6; color:var(--ink); }
        p.muted { color:var(--muted); font-size:13px; }
        code { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; background:var(--input-bg); border:1px solid var(--input-border); border-radius:5px; padding:1px 6px; }
        pre {
            margin:0 0 12px; padding:14px 16px; overflow-x:auto; border-radius:12px;
            background:var(--input-bg); border:1px solid var(--input-border);
            font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; line-height:1.6; color:var(--ink);
        }
        .param { padding:11px 13px; border:1px solid var(--input-border); border-radius:10px; background:var(--input-bg); }
        .param + .param { margin-top:8px; }
        .param-head { display:flex; align-items:center; gap:9px; }
        .param-name { font:700 12.5px/1 ui-monospace,SFMono-Regular,Menlo,monospace; color:var(--ink); }
        .param-badge { font:800 9px/1 inherit; letter-spacing:.07em; text-transform:uppercase; padding:4px 7px; border-radius:5px; }
        .param-badge.req { color:var(--accent-contrast); background:var(--accent); }
        .param-badge.opt { color:var(--muted); background:var(--input-border); }
        .param-type { margin-left:auto; font:600 10px/1 ui-monospace,SFMono-Regular,Menlo,monospace; color:var(--muted); letter-spacing:.04em; }
        .param-desc { margin:7px 0 0; font-size:13px; line-height:1.55; color:var(--muted); }
        .error-table { display:grid; gap:0; border:1px solid var(--input-border); border-radius:10px; overflow:hidden; }
        .error-row { display:flex; align-items:center; gap:12px; padding:9px 13px; background:var(--input-bg); }
        .error-row + .error-row { border-top:1px solid var(--input-border); }
        .error-name { font:700 12.5px/1 ui-monospace,SFMono-Regular,Menlo,monospace; color:var(--error); }
        .error-status { margin-left:auto; font:800 10px/1 ui-monospace,SFMono-Regular,Menlo,monospace; color:var(--muted); letter-spacing:.05em; }
        .try-form { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:12px; }
        .try-form .full { grid-column:1 / -1; }
        .try-form input, .try-form select {
            width:100%; box-sizing:border-box; padding:12px 14px; font:inherit; font-size:13px;
            border:1px solid var(--input-border); border-radius:10px; background:var(--card-bg); color:var(--ink);
        }
        .try-form input:focus, .try-form select:focus { outline:2px solid var(--accent); outline-offset:1px; }
        .try-send {
            grid-column:1 / -1; min-height:46px; border:0; border-radius:10px; background:var(--accent);
            color:var(--accent-contrast); font:700 13px/1 inherit; letter-spacing:.02em; cursor:pointer;
        }
        .try-send:hover { background:var(--accent-hover); }
        .try-send:disabled { opacity:.6; cursor:wait; }
        .try-result { margin-top:14px; }
        .try-result[hidden] { display:none; }
        .try-status { display:inline-flex; align-items:center; margin-bottom:8px; padding:5px 9px; border-radius:6px; font:800 11px/1 ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.04em; }
        .try-status.ok { color:#059669; background:color-mix(in srgb, #10b981 14%, transparent); }
        .try-status.err { color:var(--error); background:color-mix(in srgb, var(--error) 12%, transparent); }
        @media (max-width:760px) { .api-grid { grid-template-columns:1fr; } }
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
            <p class="lead"><?= h($t['api_intro']) ?></p>

            <h2><span class="method">POST</span>/api <span class="h2-desc"><?= h($t['api_create_desc']) ?></span></h2>
            <div class="api-grid">
                <div>
                    <p class="api-col-label"><?= h($t['api_params']) ?></p>
                    <div class="param">
                        <div class="param-head"><span class="param-name">long_url</span><span class="param-badge req"><?= h($t['api_required']) ?></span><span class="param-type">string</span></div>
                        <p class="param-desc"><?= h($t['api_p_long_url']) ?></p>
                    </div>
                    <div class="param">
                        <div class="param-head"><span class="param-name">domain</span><span class="param-badge opt"><?= h($t['api_optional']) ?></span><span class="param-type">string</span></div>
                        <p class="param-desc"><?= h($t['api_p_domain']) ?> <?php foreach ($available_domains as $i => $d): ?><?= $i > 0 ? ', ' : '' ?><code><?= h($d) ?></code><?php endforeach; ?></p>
                    </div>
                    <div class="param">
                        <div class="param-head"><span class="param-name">custom_code</span><span class="param-badge opt"><?= h($t['api_optional']) ?></span><span class="param-type">string</span></div>
                        <p class="param-desc"><?= h($t['api_p_custom_code']) ?></p>
                    </div>
                    <div class="param">
                        <div class="param-head"><span class="param-name">password</span><span class="param-badge opt"><?= h($t['api_optional']) ?></span><span class="param-type">string</span></div>
                        <p class="param-desc"><?= h($t['api_p_password']) ?></p>
                    </div>
                    <div class="param">
                        <div class="param-head"><span class="param-name">expires_at</span><span class="param-badge opt"><?= h($t['api_optional']) ?></span><span class="param-type">ISO 8601</span></div>
                        <p class="param-desc"><?= h($t['api_p_expires_at']) ?></p>
                    </div>
                    <div class="param">
                        <div class="param-head"><span class="param-name">max_clicks</span><span class="param-badge opt"><?= h($t['api_optional']) ?></span><span class="param-type">integer</span></div>
                        <p class="param-desc"><?= h($t['api_p_max_clicks']) ?></p>
                    </div>
                </div>
                <div>
                    <p class="api-col-label"><?= h($t['api_request']) ?></p>
                    <pre>curl -X POST https://<?= h($host) ?>/api \
  -H "Content-Type: application/json" \
  -d '{
    "long_url": "https://example.com",
    "domain": "<?= h($available_domains[0]) ?>",
    "custom_code": "my-link",
    "password": "secret",
    "expires_at": "2026-12-31T23:59:00Z",
    "max_clicks": 100
  }'</pre>
                    <p class="api-col-label"><?= h($t['api_response']) ?> · 201</p>
                    <pre>{
  "ok": true,
  "long_url": "https://example.com",
  "short_code": "Ab12Cd",
  "short_url": "https://<?= h($host) ?>/Ab12Cd",
  "domain": "<?= h($available_domains[0]) ?>",
  "expires_at": null,
  "has_password": false,
  "click_count": 0,
  "max_clicks": null
}</pre>
                </div>
            </div>

            <h2><span class="method get">GET</span>/api?code=… <span class="h2-desc"><?= h($t['api_lookup_desc']) ?></span></h2>
            <div class="api-grid">
                <div>
                    <p class="api-col-label"><?= h($t['api_params']) ?></p>
                    <div class="param">
                        <div class="param-head"><span class="param-name">code</span><span class="param-badge req"><?= h($t['api_required']) ?></span><span class="param-type">string</span></div>
                        <p class="param-desc"><?= h($t['api_p_code']) ?></p>
                    </div>
                </div>
                <div>
                    <p class="api-col-label"><?= h($t['api_request']) ?></p>
                    <pre>curl "https://<?= h($host) ?>/api?code=Ab12Cd"</pre>
                    <p class="api-col-label"><?= h($t['api_response']) ?> · 200</p>
                    <pre>{
  "ok": true,
  "long_url": "https://example.com",
  "short_code": "Ab12Cd",
  "short_url": "https://<?= h($host) ?>/Ab12Cd",
  "expires_at": null,
  "has_password": false,
  "click_count": 12,
  "max_clicks": null
}</pre>
                </div>
            </div>

            <h2><?= h($t['api_errors']) ?></h2>
            <div class="error-table">
                <div class="error-row"><code class="error-name">invalid_url</code><span class="error-status">400</span></div>
                <div class="error-row"><code class="error-name">invalid_alias</code><span class="error-status">400</span></div>
                <div class="error-row"><code class="error-name">invalid_expiry</code><span class="error-status">400</span></div>
                <div class="error-row"><code class="error-name">invalid_code</code><span class="error-status">400</span></div>
                <div class="error-row"><code class="error-name">alias_taken</code><span class="error-status">409</span></div>
                <div class="error-row"><code class="error-name">rate_limited</code><span class="error-status">429</span></div>
                <div class="error-row"><code class="error-name">not_found</code><span class="error-status">404</span></div>
                <div class="error-row"><code class="error-name">expired</code><span class="error-status">410</span></div>
                <div class="error-row"><code class="error-name">burned</code><span class="error-status">410</span></div>
            </div>

            <h2><?= h($t['api_try_title']) ?></h2>
            <p class="muted"><?= h($t['api_try_desc']) ?></p>
            <form class="try-form" id="try-form">
                <input class="full" id="try-long-url" type="url" required placeholder="https://example.com" autocomplete="off">
                <select id="try-domain"><?php foreach ($available_domains as $d): ?><option value="<?= h($d) ?>"><?= h($d) ?></option><?php endforeach; ?></select>
                <input id="try-custom-code" type="text" placeholder="custom_code" autocomplete="off" spellcheck="false">
                <input id="try-password" type="text" placeholder="password" autocomplete="off">
                <input id="try-expires" type="datetime-local" aria-label="expires_at">
                <input id="try-max-clicks" type="number" min="1" max="1000000" placeholder="max_clicks">
                <button class="try-send" id="try-send" type="submit"><?= h($t['api_try_send']) ?></button>
            </form>
            <div class="try-result" id="try-result" hidden>
                <span class="try-status" id="try-status"></span>
                <pre id="try-json"></pre>
            </div>

            <a class="api-link" href="/">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 20.5L8 12l6-8.5"></path></svg>
                0x79
            </a>
        </div>
        <?php renderCardFooter(); ?>
    </main>
    <?php renderCardThemeScript(); ?>
    <script nonce="<?= $csp_nonce ?>">
        (function () {
            var form = document.getElementById('try-form');
            var button = document.getElementById('try-send');
            var result = document.getElementById('try-result');
            var statusEl = document.getElementById('try-status');
            var jsonEl = document.getElementById('try-json');
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var payload = {
                    long_url: document.getElementById('try-long-url').value,
                    domain: document.getElementById('try-domain').value
                };
                var code = document.getElementById('try-custom-code').value.trim();
                var password = document.getElementById('try-password').value;
                var expires = document.getElementById('try-expires').value;
                var maxClicks = document.getElementById('try-max-clicks').value;
                if (code) payload.custom_code = code;
                if (password) payload.password = password;
                if (expires) payload.expires_at = expires;
                if (maxClicks) payload.max_clicks = parseInt(maxClicks, 10);

                button.disabled = true;
                result.hidden = false;
                statusEl.className = 'try-status';
                statusEl.textContent = '…';
                jsonEl.textContent = '';

                fetch('/api', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(function (res) {
                    return res.text().then(function (text) {
                        try { text = JSON.stringify(JSON.parse(text), null, 2); } catch (err) { /* keep raw */ }
                        statusEl.textContent = res.status + ' ' + res.statusText;
                        statusEl.className = 'try-status ' + (res.ok ? 'ok' : 'err');
                        jsonEl.textContent = text;
                    });
                }).catch(function () {
                    statusEl.textContent = 'network error';
                    statusEl.className = 'try-status err';
                }).finally(function () {
                    button.disabled = false;
                });
            });
        })();
    </script>
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

