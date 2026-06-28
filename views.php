<?php
declare(strict_types=1);

function userPageCss() {
    return '<style>
        body{margin:0;background:#0b0b0c;color:#f5f2ea;font:14px/1.5 Inter,ui-sans-serif,system-ui,sans-serif;padding:32px}a{color:#f5f2ea}.wrap{max-width:1060px;margin:0 auto}.nav{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,.12);padding-bottom:16px;margin-bottom:28px}.brand{font-family:monospace;text-decoration:none}.card{border:1px solid rgba(255,255,255,.12);background:#101011;padding:22px;margin:16px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}@media(max-width:760px){.grid{grid-template-columns:1fr}}input,button{font:inherit}input{width:100%;box-sizing:border-box;background:#0b0b0c;color:#fff;border:1px solid rgba(255,255,255,.16);padding:12px;margin:7px 0 14px}button,.btn{display:inline-block;background:#f5f2ea;color:#0b0b0c;border:0;padding:10px 14px;text-decoration:none;cursor:pointer}.ghost{background:transparent;color:#f5f2ea;border:1px solid rgba(255,255,255,.18)}.danger{background:#2b1010;color:#ffb4b4;border:1px solid #613030}.muted{color:rgba(255,255,255,.52)}.err{color:#ff9b9b}.ok{color:#9cffbd}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid rgba(255,255,255,.1);padding:10px;text-align:left;vertical-align:top}code{font-family:JetBrains Mono,monospace;background:#0b0b0c;border:1px solid rgba(255,255,255,.12);padding:2px 5px}.url{max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.inline{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.swatch{display:inline-block;width:10px;height:10px;border-radius:999px;margin-right:7px;vertical-align:middle}.tiny{font-size:12px}.chartbox{background:#f7f7f4;color:#111;border:1px solid rgba(0,0,0,.14);padding:18px;overflow:hidden}.charttitle{text-align:center;font-family:"Comic Sans MS","Segoe Print",cursive;font-size:18px;margin:0 0 8px}.chartwrap{overflow-x:auto}.chart{min-width:760px;width:100%;height:auto;display:block}.chart text{font-family:"Comic Sans MS","Segoe Print",cursive;fill:#222}.chart .gridline{stroke:#d7d7d2;stroke-width:1}.chart .axis{stroke:#555;stroke-width:1.2}.chart .mainline{fill:none;stroke:#111;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}.chart .point{stroke:#fff;stroke-width:2}.chartlegend{display:flex;flex-wrap:wrap;gap:14px 28px;justify-content:center;margin-top:14px}.legenditem{display:flex;align-items:center;gap:8px;font-family:"Comic Sans MS","Segoe Print",cursive;font-size:13px;color:#111}.legendswatch{width:19px;height:19px;border:1px solid rgba(0,0,0,.45);display:inline-block;transform:rotate(-4deg)}
    </style>';
}

function renderUserAuthPage($mode, $error = '') {
    global $lang, $t;
    $isRegister = $mode === 'register';
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isRegister ? 'register' : 'login' ?> — 0x79</title>
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
                <a href="/" class="px-2.5 py-1.5 transition hover:text-white">home</a>
                <a href="/shorten" class="px-2.5 py-1.5 transition hover:text-white">shorten</a>
                <a href="/upload" class="px-2.5 py-1.5 transition hover:text-white">file</a>
                <a href="/paste" class="px-2.5 py-1.5 transition hover:text-white">paste</a>
            </nav>
        </header>

        <section class="flex flex-1 items-center justify-center py-12">
            <div class="w-full max-w-[420px] border border-white/10 bg-[#101011] p-6 sm:p-8">
                <div class="mb-6">
                    <p class="font-mono text-[10px] uppercase tracking-[0.22em] text-white/35">auth</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-white mt-1"><?= $isRegister ? 'account erstellen' : 'login' ?></h1>
                    <p class="mt-2 text-xs leading-5 text-white/55">
                        <?= $isRegister ? 'Mit Account laufen deine Links nicht automatisch nach 14 Tagen ab und du bekommst einen API-Key.' : 'Einloggen, um eigene Links, Dateien und Pastes zu verwalten.' ?>
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="mb-5 border border-red-400/25 bg-red-500/10 p-3.5 font-mono text-xs text-red-200">
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/<?= $isRegister ? 'register' : 'login' ?>" class="grid gap-4">
                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45">username</span>
                        <input type="text" name="username" required autofocus autocomplete="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]{3,32}" placeholder="3-32 · a-z 0-9 . _ -" class="h-11 w-full border border-white/10 bg-[#0b0b0c] px-3.5 font-sans text-sm text-white outline-none transition placeholder:text-white/20 focus:border-white/35">
                    </label>

                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45">passwort</span>
                        <input type="password" name="password" minlength="8" required class="h-11 w-full border border-white/10 bg-[#0b0b0c] px-3.5 text-sm text-white outline-none transition focus:border-white/35">
                    </label>

                    <button type="submit" class="mt-2 flex h-11 items-center justify-between bg-[#f5f2ea] px-4 font-mono text-sm font-semibold text-[#0b0b0c] transition hover:bg-white">
                        <span><?= $isRegister ? 'registrieren' : 'einloggen' ?></span>
                        <span>→</span>
                    </button>
                </form>

                <p class="mt-6 text-center text-xs text-white/45">
                    <?= $isRegister ? 'Schon Account?' : 'Noch kein Account?' ?> 
                    <a href="/<?= $isRegister ? 'login' : 'register' ?>" class="font-semibold text-white underline underline-offset-4 hover:text-white/80"><?= $isRegister ? 'login' : 'register' ?></a>
                </p>
            </div>
        </section>

        <footer class="mt-8 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
            <span>0x79.one</span>
            <span>fftrclo.store · takeitdown.space · mydiscordiscool.store · fckdupfuture.com · <?= date('Y') ?></span>
        </footer>
    </main>
</body>
</html>
    <?php
    exit;
}

function renderUserAccountPage($notice = '') {
    $user = currentUser();
    if (!$user) { header('Location: /login'); exit; }
    $host = cleanHost($_SERVER['HTTP_HOST'] ?? '0x79.one');
    [$linksOk, $links] = fetchUserLinks($user['id'], 100, 0);
    [$pastesOk, $pastes] = fetchUserPastes($user['id'], 100, 0);
    $lastKey = $_SESSION['last_api_key'] ?? '';
    unset($_SESSION['last_api_key']);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html><html lang="de"><head><link rel="icon" href="/logo.png" type="image/jpeg"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>account — 0x79</title><?= userPageCss() ?></head>
<body><main class="wrap"><div class="nav"><a class="brand" href="/" style="display:inline-flex;align-items:center;gap:6px"><img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg object-cover">0x79</a><div class="inline"><a href="/shorten">shorten</a><a href="/upload">upload</a><a href="/paste">paste</a><a href="/music">music</a><a href="/logout">logout</a></div></div>
<h1>account</h1><p class="muted">@<?= h($user['username'] ?? '') ?></p><?php if($notice): ?><p class="ok"><?= h($notice) ?></p><?php endif; ?>
<?php if($lastKey): ?><div class="card"><h2>dein neuer API-Key</h2><p class="muted">Nur jetzt sichtbar. Kopieren und sicher speichern.</p><code style="display:block;white-space:normal;word-break:break-all"><?= h($lastKey) ?></code></div><?php endif; ?>
<div class="grid"><section class="card"><h2>API</h2><p class="muted">Public Create-API braucht jetzt deinen API-Key.</p><p>Prefix: <code><?= h($user['api_key_prefix'] ?? '') ?>…</code></p><form method="POST" action="/account/action"><input type="hidden" name="action" value="regen_api_key"><button type="submit">API-Key neu generieren</button></form><pre class="muted">curl -X POST https://<?= h($host) ?>/api/paste \
  -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"content":"hello"}'</pre></section><section class="card"><h2>guest-regel</h2><p class="muted">Ohne Login bekommen neue Links, Uploads und Pastes automatisch ein Ablaufdatum in 14 Tagen. Mit Account nur, wenn du selbst eins setzt.</p></section></div>
<section class="card"><h2>analytics</h2><?php if(!$linksOk): ?><p class="err">urls migration fehlt evtl. owner_user_id.</p><?php elseif(empty($links)): ?><p class="muted">noch keine daten.</p><?php else: ?><?php
    $chartLinks = array_slice($links, 0, 10);
    $palette = ['#0f7a3d','#09aeea','#f59e0b','#0b70b7','#dc2626','#7c3aed','#0891b2','#16a34a','#db2777','#ca8a04'];
    $values = array_map(function($x){ return max(0, (int)($x['click_count'] ?? 0)); }, $chartLinks);
    $maxClicks = max(1, ...$values);
    $w = 900; $h = 430; $left = 68; $right = 34; $top = 42; $bottom = 72;
    $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
    $count = max(1, count($chartLinks));
    $points = [];
    foreach ($chartLinks as $i => $l) {
        $x = $count === 1 ? $left + ($plotW / 2) : $left + (($plotW / ($count - 1)) * $i);
        $y = $top + $plotH - (((int)($l['click_count'] ?? 0) / $maxClicks) * $plotH);
        $points[] = [round($x, 1), round($y, 1)];
    }
    $poly = implode(' ', array_map(function($p){ return $p[0] . ',' . $p[1]; }, $points));
?><div class="chartbox"><p class="charttitle">Clicks by Link</p><div class="chartwrap"><svg class="chart" viewBox="0 0 <?= h((string)$w) ?> <?= h((string)$h) ?>" role="img" aria-label="Clicks by link chart">
    <?php for($g=0;$g<=5;$g++): $gy=$top+($plotH/5)*$g; $val=(int)round($maxClicks - ($maxClicks/5)*$g); ?>
        <line class="gridline" x1="<?= h((string)$left) ?>" y1="<?= h((string)round($gy,1)) ?>" x2="<?= h((string)($w-$right)) ?>" y2="<?= h((string)round($gy,1)) ?>"></line>
        <text x="<?= h((string)($left-15)) ?>" y="<?= h((string)(round($gy,1)+5)) ?>" text-anchor="end" font-size="13"><?= h((string)$val) ?></text>
    <?php endfor; ?>
    <line class="axis" x1="<?= h((string)$left) ?>" y1="<?= h((string)($top+$plotH)) ?>" x2="<?= h((string)($w-$right)) ?>" y2="<?= h((string)($top+$plotH)) ?>"></line>
    <line class="axis" x1="<?= h((string)$left) ?>" y1="<?= h((string)$top) ?>" x2="<?= h((string)$left) ?>" y2="<?= h((string)($top+$plotH)) ?>"></line>
    <text x="22" y="<?= h((string)($top+$plotH/2)) ?>" transform="rotate(-90 22 <?= h((string)($top+$plotH/2)) ?>)" text-anchor="middle" font-size="15">Clicks</text>
    <text x="<?= h((string)($left+$plotW/2)) ?>" y="<?= h((string)($h-20)) ?>" text-anchor="middle" font-size="15">Links / Files</text>
    <polyline class="mainline" points="<?= h($poly) ?>"></polyline>
    <?php foreach($chartLinks as $i=>$l): $code=$l['short_code']??''; $clicks=(int)($l['click_count']??0); $color=$palette[$i % count($palette)]; $px=$points[$i][0]; $py=$points[$i][1]; ?>
        <circle class="point" cx="<?= h((string)$px) ?>" cy="<?= h((string)$py) ?>" r="6" fill="<?= h($color) ?>"></circle>
        <text x="<?= h((string)$px) ?>" y="<?= h((string)($top+$plotH+27)) ?>" text-anchor="middle" font-size="12"><?= h(mb_strlen($code) > 8 ? mb_substr($code,0,8).'…' : $code) ?></text>
        <text x="<?= h((string)($px+9)) ?>" y="<?= h((string)($py-9)) ?>" font-size="12"><?= h((string)$clicks) ?></text>
    <?php endforeach; ?>
</svg></div><div class="chartlegend"><?php foreach($chartLinks as $i=>$l): $code=$l['short_code']??''; $color=$palette[$i % count($palette)]; ?><span class="legenditem"><span class="legendswatch" style="background:<?= h($color) ?>"></span><?= h($code) ?></span><?php endforeach; ?></div></div><p class="muted tiny">Design wie eine ruhige Line-Chart-Preview. Die Daten sind echte Gesamt-Clicks pro Link/File; historische Quartale gibt es erst, wenn Click-Events separat gespeichert werden.</p><?php endif; ?></section>
<section class="card"><h2>deine links & dateien</h2><?php if(!$linksOk): ?><p class="err">urls migration fehlt evtl. owner_user_id.</p><?php elseif(empty($links)): ?><p class="muted">noch nichts erstellt.</p><?php else: ?><?php $palette = ['#7dd3fc','#86efac','#fde68a','#fca5a5','#c4b5fd','#f9a8d4','#67e8f9','#fdba74','#a7f3d0','#d8b4fe']; ?><table><thead><tr><th>code</th><th>ziel</th><th>clicks</th><th>expires</th><th></th></tr></thead><tbody><?php foreach($links as $i=>$l): $code=$l['short_code']??''; $color=$palette[$i % count($palette)]; ?><tr><td><span class="swatch" style="background:<?= h($color) ?>"></span><a href="/<?= h($code) ?>"><code><?= h($code) ?></code></a><?php if(!empty($l['preview_enabled'])): ?> <span class="muted tiny">preview</span><?php endif; ?></td><td class="url"><?= h($l['long_url']??'') ?></td><td><?= h((string)($l['click_count']??0)) ?></td><td><?= !empty($l['expires_at']) ? h(formatDateTime($l['expires_at'])) : '<span class="muted">never</span>' ?></td><td><div class="inline"><a class="btn ghost tiny" href="/account/stats?code=<?= h(rawurlencode($code)) ?>">stats</a><a class="btn ghost tiny" href="/qr?d=<?= h(rawurlencode('https://' . $host . '/' . $code)) ?>" target="_blank" rel="noopener">QR</a><details><summary class="btn ghost tiny" style="cursor:pointer;list-style:none">edit</summary><form method="POST" action="/account/action" style="margin-top:10px;min-width:240px"><input type="hidden" name="action" value="edit_link"><input type="hidden" name="id" value="<?= h($l['id']??'') ?>"><label class="tiny muted">ziel-url</label><input name="long_url" value="<?= h($l['long_url']??'') ?>"><label class="tiny muted">expires (leer = nie)</label><input type="datetime-local" name="expires_at" value="<?= !empty($l['expires_at']) ? h(date('Y-m-d\TH:i', (int)strtotime((string)$l['expires_at']))) : '' ?>"><label class="tiny muted">max clicks (leer = ∞)</label><input type="number" min="1" name="max_clicks" value="<?= h((string)($l['max_clicks'] ?? '')) ?>"><label class="tiny muted">neues passwort</label><input type="password" name="password" placeholder="leer = unverändert"><label class="tiny" style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="clear_password" value="1" style="width:auto;margin:0"> passwort entfernen</label><button type="submit">speichern</button></form></details><form method="POST" action="/account/action" onsubmit="return confirm('wirklich löschen?')" style="display:inline"><input type="hidden" name="action" value="delete_link"><input type="hidden" name="id" value="<?= h($l['id']??'') ?>"><button class="danger tiny" type="submit">löschen</button></form></div></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<section class="card"><h2>deine pastes</h2><?php if(!$pastesOk): ?><p class="err">pastes migration fehlt evtl. owner_user_id.</p><?php elseif(empty($pastes)): ?><p class="muted">noch keine pastes.</p><?php else: ?><table><thead><tr><th>code</th><th>views</th><th>expires</th><th></th></tr></thead><tbody><?php foreach($pastes as $pa): $code=$pa['paste_code']??''; ?><tr><td><a href="/<?= h($code) ?>"><code><?= h($code) ?></code></a> <a class="muted" href="/raw/<?= h($code) ?>">raw</a></td><td><?= h((string)($pa['view_count']??0)) ?></td><td><?= !empty($pa['expires_at']) ? h(formatDateTime($pa['expires_at'])) : '<span class="muted">never</span>' ?></td><td><form method="POST" action="/account/action" onsubmit="return confirm('paste löschen?')"><input type="hidden" name="action" value="delete_paste"><input type="hidden" name="id" value="<?= h($pa['id']??'') ?>"><button class="danger" type="submit">löschen</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
</main></body></html>
    <?php
    exit;
}

function renderLinkStatsPage($code, $clicks) {
    global $t;
    $host = cleanHost($_SERVER['HTTP_HOST'] ?? '0x79.one');
    $shortUrl = 'https://' . $host . '/' . $code;

    $total = count($clicks);
    $days = 30;
    $byDay = [];
    for ($i = $days - 1; $i >= 0; $i--) $byDay[gmdate('Y-m-d', time() - $i * 86400)] = 0;
    $byRef = []; $byDevice = []; $byCountry = [];
    foreach ($clicks as $c) {
        $ts = strtotime((string)($c['clicked_at'] ?? ''));
        if ($ts) { $d = gmdate('Y-m-d', $ts); if (isset($byDay[$d])) $byDay[$d]++; }
        $r = (string)($c['referrer_host'] ?? ''); $r = $r !== '' ? $r : '(direct)';
        $byRef[$r] = ($byRef[$r] ?? 0) + 1;
        $dev = (string)($c['device'] ?? '') ?: 'other';
        $byDevice[$dev] = ($byDevice[$dev] ?? 0) + 1;
        $co = strtoupper((string)($c['country'] ?? ''));
        if ($co !== '') $byCountry[$co] = ($byCountry[$co] ?? 0) + 1;
    }
    arsort($byRef); arsort($byDevice); arsort($byCountry);
    $last30 = array_sum($byDay);
    $maxDay = max(1, $byDay ? max($byDay) : 1);

    // Bar chart geometry
    $bw = 18; $gap = 4; $chartH = 120; $padL = 4; $padT = 8;
    $chartW = $padL * 2 + count($byDay) * ($bw + $gap);

    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="icon" href="/logo.png" type="image/jpeg"><title>stats /<?= h($code) ?> — 0x79</title><?= userPageCss() ?></head><body><div class="wrap">
    <div class="nav"><a class="brand" href="/">0x79</a><a class="btn ghost" href="/account">← account</a></div>
    <h1 style="margin:0 0 6px">stats · <code><?= h($code) ?></code></h1>
    <p class="muted"><a href="<?= h($shortUrl) ?>" target="_blank" rel="noopener"><?= h($shortUrl) ?></a></p>

    <div class="grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="card"><div class="muted tiny">clicks total</div><div style="font-size:30px"><?= h((string)$total) ?></div></div>
        <div class="card"><div class="muted tiny">last 30 days</div><div style="font-size:30px"><?= h((string)$last30) ?></div></div>
        <div class="card"><div class="muted tiny">unique referrers</div><div style="font-size:30px"><?= h((string)count($byRef)) ?></div></div>
    </div>

    <div class="card"><h2 style="margin:0 0 12px">clicks · last 30 days</h2>
    <?php if ($total === 0): ?>
        <p class="muted">noch keine click-daten. (falls gerade frisch deployed: die <code>link_clicks</code>-tabelle muss existieren — siehe schema.sql.)</p>
    <?php else: ?>
        <div style="overflow-x:auto"><svg width="<?= h((string)$chartW) ?>" height="<?= h((string)($chartH + 26)) ?>" viewBox="0 0 <?= h((string)$chartW) ?> <?= h((string)($chartH + 26)) ?>">
        <?php $x = $padL; foreach ($byDay as $d => $cnt): $bh = (int)round(($cnt / $maxDay) * $chartH); $y = $padT + $chartH - $bh; ?>
            <rect x="<?= h((string)$x) ?>" y="<?= h((string)$y) ?>" width="<?= h((string)$bw) ?>" height="<?= h((string)max(1, $bh)) ?>" fill="#86efac"><title><?= h($d) ?>: <?= h((string)$cnt) ?></title></rect>
            <?php if ((int)substr($d, 8, 2) === 1 || $d === array_key_first($byDay) || $d === array_key_last($byDay)): ?><text x="<?= h((string)($x + $bw / 2)) ?>" y="<?= h((string)($chartH + 22)) ?>" font-size="9" fill="#888" text-anchor="middle"><?= h(substr($d, 5)) ?></text><?php endif; ?>
        <?php $x += $bw + $gap; endforeach; ?>
        </svg></div>
    <?php endif; ?>
    </div>

    <div class="grid">
        <div class="card"><h2 style="margin:0 0 12px">top referrers</h2><?php if (!$byRef): ?><p class="muted">—</p><?php else: ?><table><tbody><?php foreach (array_slice($byRef, 0, 10, true) as $r => $n): ?><tr><td><?= h($r) ?></td><td style="text-align:right;width:60px"><?= h((string)$n) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
        <div class="card"><h2 style="margin:0 0 12px">devices</h2><?php if (!$byDevice): ?><p class="muted">—</p><?php else: ?><table><tbody><?php foreach ($byDevice as $dev => $n): ?><tr><td><?= h($dev) ?></td><td style="text-align:right;width:60px"><?= h((string)$n) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        <?php if ($byCountry): ?><h2 style="margin:18px 0 12px">top countries</h2><table><tbody><?php foreach (array_slice($byCountry, 0, 8, true) as $co => $n): ?><tr><td><?= h($co) ?></td><td style="text-align:right;width:60px"><?= h((string)$n) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
    </div>
    </div></body></html><?php
    exit;
}

function musicPlatformColors() {
    return [
        'spotify'       => '#1DB954',
        'apple_music'   => '#FA2D48',
        'youtube_music' => '#FF0000',
        'soundcloud'    => '#FF5500',
        'deezer'        => '#A238FF',
        'tidal'         => '#00D6D6',
        'amazon_music'  => '#25D1DA',
        'bandcamp'      => '#629AA9',
        'audiomack'     => '#FFA200',
        'beatport'      => '#A8E00F',
    ];
}

function musicPlatformLogos() {
    // Official brand glyphs (single-path SVG, viewBox 0 0 24 24), from Simple Icons.
    return [
        'spotify'        => 'M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z',
        'apple_music'    => 'M23.994 6.124a9.23 9.23 0 00-.24-2.19c-.317-1.31-1.062-2.31-2.18-3.043a5.022 5.022 0 00-1.877-.726 10.496 10.496 0 00-1.564-.15c-.04-.003-.083-.01-.124-.013H5.986c-.152.01-.303.017-.455.026-.747.043-1.49.123-2.193.4-1.336.53-2.3 1.452-2.865 2.78-.192.448-.292.925-.363 1.408-.056.392-.088.785-.1 1.18 0 .032-.007.062-.01.093v12.223c.01.14.017.283.027.424.05.815.154 1.624.497 2.373.65 1.42 1.738 2.353 3.234 2.801.42.127.856.187 1.293.228.555.053 1.11.06 1.667.06h11.03a12.5 12.5 0 001.57-.1c.822-.106 1.596-.35 2.295-.81a5.046 5.046 0 001.88-2.207c.186-.42.293-.87.37-1.324.113-.675.138-1.358.137-2.04-.002-3.8 0-7.595-.003-11.393zm-6.423 3.99v5.712c0 .417-.058.827-.244 1.206-.29.59-.76.962-1.388 1.14-.35.1-.706.157-1.07.173-.95.045-1.773-.6-1.943-1.536a1.88 1.88 0 011.038-2.022c.323-.16.67-.25 1.018-.324.378-.082.758-.153 1.134-.24.274-.063.457-.23.51-.516a.904.904 0 00.02-.193c0-1.815 0-3.63-.002-5.443a.725.725 0 00-.026-.185c-.04-.15-.15-.243-.304-.234-.16.01-.318.035-.475.066-.76.15-1.52.303-2.28.456l-2.325.47-1.374.278c-.016.003-.032.01-.048.013-.277.077-.377.203-.39.49-.002.042 0 .086 0 .13-.002 2.602 0 5.204-.003 7.805 0 .42-.047.836-.215 1.227-.278.64-.77 1.04-1.434 1.233-.35.1-.71.16-1.075.172-.96.036-1.755-.6-1.92-1.544-.14-.812.23-1.685 1.154-2.075.357-.15.73-.232 1.108-.31.287-.06.575-.116.86-.177.383-.083.583-.323.6-.714v-.15c0-2.96 0-5.922.002-8.882 0-.123.013-.25.042-.37.07-.285.273-.448.546-.518.255-.066.515-.112.774-.165.733-.15 1.466-.296 2.2-.444l2.27-.46c.67-.134 1.34-.27 2.01-.403.22-.043.442-.088.663-.106.31-.025.523.17.554.482.008.073.012.148.012.223.002 1.91.002 3.822 0 5.732z',
        'youtube_music'  => 'M12 0C5.376 0 0 5.376 0 12s5.376 12 12 12 12-5.376 12-12S18.624 0 12 0zm0 19.104c-3.924 0-7.104-3.18-7.104-7.104S8.076 4.896 12 4.896s7.104 3.18 7.104 7.104-3.18 7.104-7.104 7.104zm0-13.332c-3.432 0-6.228 2.796-6.228 6.228S8.568 18.228 12 18.228s6.228-2.796 6.228-6.228S15.432 5.772 12 5.772zM9.684 15.54V8.46L15.816 12l-6.132 3.54z',
        'soundcloud'     => 'M23.999 14.165c-.052 1.796-1.612 3.169-3.4 3.169h-8.18a.68.68 0 0 1-.675-.683V7.862a.747.747 0 0 1 .452-.724s.75-.513 2.333-.513a5.364 5.364 0 0 1 2.763.755 5.433 5.433 0 0 1 2.57 3.54c.282-.08.574-.121.868-.12.884 0 1.73.358 2.347.992s.948 1.49.922 2.373ZM10.721 8.421c.247 2.98.427 5.697 0 8.672a.264.264 0 0 1-.53 0c-.395-2.946-.22-5.718 0-8.672a.264.264 0 0 1 .53 0ZM9.072 9.448c.285 2.659.37 4.986-.006 7.655a.277.277 0 0 1-.55 0c-.331-2.63-.256-5.02 0-7.655a.277.277 0 0 1 .556 0Zm-1.663-.257c.27 2.726.39 5.171 0 7.904a.266.266 0 0 1-.532 0c-.38-2.69-.257-5.21 0-7.904a.266.266 0 0 1 .532 0Zm-1.647.77a26.108 26.108 0 0 1-.008 7.147.272.272 0 0 1-.542 0 27.955 27.955 0 0 1 0-7.147.275.275 0 0 1 .55 0Zm-1.67 1.769c.421 1.865.228 3.5-.029 5.388a.257.257 0 0 1-.514 0c-.21-1.858-.398-3.549 0-5.389a.272.272 0 0 1 .543 0Zm-1.655-.273c.388 1.897.26 3.508-.01 5.412-.026.28-.514.283-.54 0-.244-1.878-.347-3.54-.01-5.412a.283.283 0 0 1 .56 0Zm-1.668.911c.4 1.268.257 2.292-.026 3.572a.257.257 0 0 1-.514 0c-.241-1.262-.354-2.312-.023-3.572a.283.283 0 0 1 .563 0Z',
        'deezer'         => 'M.693 10.024c.381 0 .693-1.256.693-2.807 0-1.55-.312-2.807-.693-2.807C.312 4.41 0 5.666 0 7.217s.312 2.808.693 2.808ZM21.038 1.56c-.364 0-.684.805-.91 2.096C19.765 1.446 19.184 0 18.526 0c-.78 0-1.464 2.036-1.784 5-.312-2.158-.788-3.536-1.325-3.536-.745 0-1.386 2.704-1.62 6.472-.442-1.932-1.083-3.145-1.793-3.145s-1.35 1.213-1.793 3.145c-.242-3.76-.874-6.463-1.628-6.463-.537 0-1.013 1.378-1.325 3.535C6.938 2.036 6.262 0 5.474 0c-.658 0-1.247 1.447-1.602 3.665-.217-1.291-.546-2.105-.91-2.105-.675 0-1.221 2.807-1.221 6.272 0 3.466.546 6.273 1.221 6.273.277 0 .537-.476.736-1.273.32 2.928.996 4.938 1.776 4.938.606 0 1.143-1.204 1.507-3.11.251 3.622.875 6.195 1.602 6.195.46 0 .875-1.023 1.187-2.677C10.142 21.6 11 24 12.004 24c1.005 0 1.863-2.4 2.235-5.822.312 1.654.727 2.677 1.186 2.677.728 0 1.352-2.573 1.603-6.195.364 1.906.9 3.11 1.507 3.11.78 0 1.455-2.01 1.775-4.938.208.797.46 1.273.737 1.273.675 0 1.22-2.807 1.22-6.273-.008-3.457-.553-6.272-1.23-6.272ZM23.307 10.024c.381 0 .693-1.256.693-2.807 0-1.55-.312-2.807-.693-2.807-.381 0-.693 1.256-.693 2.807s.312 2.808.693 2.808Z',
        'tidal'          => 'M12.012 3.992L8.008 7.996 4.004 3.992 0 7.996 4.004 12l4.004-4.004L12.012 12l-4.004 4.004 4.004 4.004 4.004-4.004L12.012 12l4.004-4.004-4.004-4.004zM16.042 7.996l3.979-3.979L24 7.996l-3.979 3.979z',
        'amazon_music'   => 'M14.8454 9.4083c-1.3907 1.0194-3.405 1.563-5.1424 1.563a9.333 9.333 0 0 1-6.2768-2.3835c-.1313-.117-.0143-.277.1415-.1846a12.693 12.693 0 0 0 6.285 1.6574c1.5384 0 3.2348-.318 4.7917-.9764.2359-.0985.4328.1538.203.324h-.002zm.5784-.6564c-.1784-.2257-1.1753-.1087-1.6225-.0554-.1374.0164-.158-.1026-.0349-.1867.796-.5558 2.0984-.3958 2.2502-.2092.1539.1867-.041 1.4872-.7856 2.1087-.1149.0964-.2236.0451-.1723-.082.1682-.4165.5436-1.3498.3651-1.5754zm-1.5917-4.1702v-.5394c0-.082.0615-.1375.1374-.1375h2.4348c.078 0 .1395.0554.1395.1354v.4636c0 .078-.0656.1805-.1846.3405L15.0997 6.635c.4677-.0102.9641.0595 1.3887.2974.0964.0534.123.1334.1292.2113v.5744c0 .082-.0882.1723-.1784.123a2.8163 2.8163 0 0 0-2.5723.0062c-.0861.0451-.1743-.0451-.1743-.1251v-.5477c0-.0882.002-.238.0902-.3713l1.4626-2.0881h-1.2718c-.078 0-.1415-.0534-.1436-.1354l.002.002zm4.808-.7466c1.0995 0 1.6944.9395 1.6944 2.1333 0 1.1528-.6564 2.0676-1.6943 2.0676-1.079 0-1.6656-.9395-1.6656-2.1087 0-1.1774.5948-2.0922 1.6656-2.0922zm.0062.7713c-.5456 0-.5805.7384-.5805 1.202 0 .4615-.0061 1.4481.5744 1.4481.5743 0 .601-.7958.601-1.282 0-.318-.0144-.6994-.1108-1.001-.082-.2625-.2482-.3671-.4841-.3671zm-6.008 3.3414c-.0493.041-.1395.0451-.1744.0164-.2543-.1949-.4246-.4923-.4246-.4923-.4061.4123-.6954.5374-1.2225.5374-.6215 0-1.1077-.3835-1.1077-1.1486a1.2512 1.2512 0 0 1 .7897-1.2041c.402-.1764.9641-.2072 1.3928-.2564 0 0 .0349-.4615-.0902-.6297a.521.521 0 0 0-.4164-.1908c-.2728 0-.5395.1477-.5928.4328-.0144.082-.0739.1518-.1395.1436L9.945 5.08a.1292.1292 0 0 1-.1108-.1537c.1641-.8657.9498-1.1282 1.6554-1.1282.361 0 .8307.0964 1.1158.3671.359.3344.3262.7795.3262 1.2677v1.1487c0 .3446.1436.4964.279.681.0471.0677.0574.1477-.002.197-.1519.125-.5703.4881-.5703.4881zm-.7467-1.7969v-.16c-.5353 0-1.1015.115-1.1015.7426 0 .318.1662.5333.4513.5333.2051 0 .3938-.1272.5128-.3344.1436-.2564.1374-.4943.1374-.7815zM2.9278 7.948c-.0472.041-.1375.045-.1723.0163-.2544-.1949-.4246-.4923-.4246-.4923-.4082.4123-.6954.5374-1.2226.5374-.6235 0-1.1076-.3835-1.1076-1.1486a1.2512 1.2512 0 0 1 .7897-1.2041c.402-.1764.964-.2072 1.3928-.2564 0 0 .0348-.4615-.0903-.6297a.521.521 0 0 0-.4164-.1908c-.2748 0-.5395.1477-.5928.4328-.0143.082-.0759.1518-.1395.1436L.2345 5.08a.1292.1292 0 0 1-.1087-.1537c.162-.8657.9497-1.1282 1.6553-1.1282.361 0 .8308.0964 1.1159.3671.359.3344.324.7795.324 1.2677v1.1487c0 .3446.1437.4964.279.681.0472.0677.0575.1477-.002.197-.1518.125-.5702.4881-.5702.4881zm-.7446-1.797v-.16c-.5354 0-1.1015.115-1.1015.7426 0 .318.164.5333.4512.5333.2052 0 .3939-.1272.5128-.3344.1436-.2564.1375-.4943.1375-.7815zm2.9127-.3343v2.002a.1379.1379 0 0 1-.1395.1374H4.218a.1374.1374 0 0 1-.1395-.1374v-3.766a.1379.1379 0 0 1 .1395-.1375h.6913a.1374.1374 0 0 1 .1374.1374v.482h.0143c.1805-.4758.519-.6994.9744-.6994.4636 0 .7528.2236.962.6995a1.0523 1.0523 0 0 1 1.0215-.6995c.3118 0 .6502.1272.8574.4143.236.318.1867.7795.1867 1.1857v2.3855c0 .076-.0636.1354-.1436.1354H8.181a.1374.1374 0 0 1-.1334-.1354v-2.004c0-.16.0144-.558-.0205-.7077-.0554-.2564-.2215-.3282-.4369-.3282a.4923.4923 0 0 0-.441.3118c-.076.1908-.0698.5087-.0698.724v2.0041c0 .076-.0635.1354-.1435.1354h-.7385a.1374.1374 0 0 1-.1333-.1354v-2.004c0-.4226.0677-1.042-.4574-1.042-.5334 0-.5128.603-.5128 1.042h.002zm16.8077 2.002a.1374.1374 0 0 1-.1374.1374h-.7405a.1374.1374 0 0 1-.1374-.1374v-3.766a.1374.1374 0 0 1 .1374-.1375h.683c.0821 0 .1396.0636.1396.1067v.5764h.0143c.2051-.517.4964-.7631 1.0092-.7631.3323 0 .6564.119.8636.4451.1928.3036.1928.8123.1928 1.1774V7.837a.1395.1395 0 0 1-.1415.119h-.7426a.1395.1395 0 0 1-.1313-.119V5.552c0-.763-.2933-.7856-.4635-.7856-.197 0-.357.1538-.4246.2953a1.7025 1.7025 0 0 0-.1231.722l.002 2.0349zM.1914 20.0582c-.1271 0-.1907-.0615-.1907-.1907v-4.4491c0-.1272.0636-.1908.1907-.1908H.616c.0616 0 .1129.0144.1477.039.0349.0246.0595.0738.0718.1436l.0575.3035c.6133-.4184 1.2102-.6276 1.7907-.6276.5948 0 .9969.2256 1.2081.6769.6318-.4513 1.2636-.677 1.8954-.677.441 0 .7794.1231 1.0153.3693.236.2502.3549.603.3549 1.0584v3.3538c0 .1271-.0656.1907-.1928.1907h-.5641c-.1272 0-.1928-.0615-.1928-.1907v-3.085c0-.318-.0616-.5539-.1805-.7057-.1231-.1538-.3139-.2297-.5744-.2297-.4677 0-.9353.1436-1.4092.4307a.997.997 0 0 1 .0103.1416v3.448c0 .1272-.0636.1908-.1908.1908H3.297c-.1272 0-.1908-.0615-.1908-.1907v-3.085c0-.318-.0615-.5539-.1825-.7057-.1231-.1538-.3139-.2297-.5744-.2297-.4861 0-.9517.1395-1.399.4205v3.5999c0 .1271-.0615.1907-.1907.1907H.1914zm9.731.1436c-.4533 0-.8-.1272-1.044-.3815-.242-.2544-.3631-.6133-.3631-1.0769v-3.321c0-.1292.0615-.1927.1908-.1927h.564c.1293 0 .1929.0635.1929.1907v3.0215c0 .3425.0656.5948.201.7569.1333.162.3487.242.642.242.4595 0 .923-.1518 1.3887-.4574v-3.565c0-.1272.0615-.1908.1908-.1908h.564c.1293 0 .1929.0636.1929.1908v4.4511c0 .1252-.0636.1887-.1928.1887h-.4103c-.0636 0-.1149-.0123-.1497-.0369-.0349-.0266-.0575-.0738-.0718-.1436l-.0657-.3323c-.5948.437-1.204.6564-1.8297.6564zm5.4399 0c-.5374 0-1.0195-.0882-1.4461-.2666a.3754.3754 0 0 1-.158-.1047c-.0287-.039-.043-.0984-.043-.1805v-.2687c0-.1148.0369-.1723.1148-.1723.0452 0 .1231.0205.238.0575.4225.1333.8615.199 1.3128.199.3138 0 .5517-.0616.7138-.1806.164-.121.244-.2954.244-.523a.4923.4923 0 0 0-.1476-.3734 1.606 1.606 0 0 0-.5415-.285l-.8144-.3037c-.7097-.2605-1.0625-.7056-1.0625-1.3333 0-.4143.16-.7487.484-1.001.3221-.2543.7447-.3815 1.2677-.3815a3.487 3.487 0 0 1 1.2164.2195c.076.0246.1313.0574.1641.0985.0308.041.0472.1025.0472.1846v.2584c0 .1149-.041.1723-.123.1723a.8615.8615 0 0 1-.2216-.0472 3.5495 3.5495 0 0 0-1.0359-.1538c-.6112 0-.919.2072-.919.6195 0 .164.0514.2953.154.3897.1025.0964.3035.201.603.3159l.7466.2872c.3774.1436.6482.318.8144.519.1661.1989.2482.4574.2482.7753 0 .4513-.1682.8102-.5067 1.0769-.3385.2666-.7877.4-1.3497.4v.002zm3.0645-.1436c-.1272 0-.1928-.0615-.1928-.1907v-4.4491c0-.1272.0656-.1908.1928-.1908h.5641c.1272 0 .1928.0636.1928.1908v4.4511c0 .1251-.0656.1887-.1928.1887h-.564zm.2872-5.688c-.1846 0-.3303-.0513-.437-.1559a.558.558 0 0 1-.1579-.4143c0-.1724.0534-.3098.158-.4144a.5907.5907 0 0 1 .4369-.158c.1846 0 .3282.0534.4349.158.1066.1026.1579.242.1579.4144 0 .1702-.0513.3076-.158.4143-.1046.1026-.2502.1559-.4348.1559zm4.002 5.7926c-.7529 0-1.3293-.2133-1.7272-.642-.4-.4307-.599-1.0502-.599-1.8625 0-.8061.2052-1.4318.6175-1.8728.4102-.441.9948-.6625 1.7476-.6625.3446 0 .683.0615 1.0154.1825.0697.0247.119.0554.1477.0944s.043.1026.043.1908v.2564c0 .1271-.041.1907-.123.1907-.0329 0-.082-.0082-.1539-.0287a2.8307 2.8307 0 0 0-.7959-.1128c-.5353 0-.923.1333-1.1589.404s-.3528.6996-.3528 1.2924v.123c0 .5764.119 1.001.359 1.2718.24.2687.6174.404 1.1343.404.2666 0 .5538-.043.8615-.1332.0718-.0206.119-.0288.1436-.0288.082 0 .1251.0636.1251.1908v.2585c0 .082-.0123.1435-.039.1805-.0246.0369-.0759.0718-.1518.1025-.3138.1354-.6769.201-1.0933.201z',
        'bandcamp'       => 'M0 18.75l7.437-13.5H24l-7.438 13.5H0z',
        'audiomack'      => 'M.331 11.378s.5418-.089.765.1439c.2234.2332.077.7156-.2195.7237-.2965.01-.5705.063-.765-.1439-.1946-.2066-.1424-.6218.2195-.7237m5.881 3.2925c-.0522.01-.1075-.018-.164-.059-.3884-.5413-.5287-2.3923-.707-2.5025-.185-.1144-.8545 1.0255-2.1862.903-.5569-.051-1.1236-.4121-1.4573-.662.031-.4206.0364-1.4027.8659-1.0833.5038.1939 1.3667.7266 2.1245-.23.8378-1.0579 1.2999-.7506 1.577-.5206.2771.23.0925 1.4259.5058 1.0916.4133-.3343 2.082-2.4103 2.082-2.4103s1.292-1.303 1.4898.067c.1979 1.3698 1.0403 2.8877 1.2635 2.8445.2234-.043 2.8223-5.3253 3.1945-5.666.3722-.3409 1.6252-.2961 1.5657.5781-.0596.8742-.1871 6.308-.1871 6.308s-.147 1.5311.0924.7128c.0992-.3392.206-.6453.3392-1.0024.6414-2.0534 1.734-5.5613 2.2784-7.3688.1252-.4325.233-.8037.3166-1.0891l.0001-.0008a3.5925 3.5925 0 0 1 .0973-.3305c.0455-.1532.0763-.2546.0858-.2813.0243-.068.0925-.1192.1884-.157.0962-.061.1995-.064.3165-.067.3021-.027.6907.012 1.0401.1119.1018 0 .2125.037.3172.1118v.0001s.0063 0 .0151.01c.0023 0 .0048 0 .0073.01.0219.015.0573.045.0983.095.0012 0 .0025 0 .004.01.017.021.0341.045.0515.073.1952.2863.315.814.1948 1.7498-.2996 2.3354-.5316 7.1397-.5316 7.1397s-.0461.2298.4353-.782c.0167-.035.0383-.066.058-.098.026-.017.0552-.042.0913-.085.2974-.3546 1.0968-.5629 1.6512-.5586.2336.028.4293.087.5462.1609.2188.333.0897 1.562.0897 1.562-.4612.043-1.3403.2908-1.6519.3366-.3118.046-.7852 2.0699-1.4433 1.8629-.6581-.2069-2.1246-1.1268-2.1246-1.2533 0-.1102.1152-1.4546.1453-1.8016.0022-.024.004-.046.0058-.068a.152.152 0 0 1 .0014-.014l-.0002.0003c.0213-.2733.0023-.3927-.1239-.1199-.1086.2346-.581 1.7359-1.1078 3.3709-.0556.1429-1.0511 3.1558-1.1818 3.5231-.156.4261-.287.7523-.3776.921-.1378.1867-.3234.3036-.5826.2252-.6465-.1954-1.4654-1.0889-1.473-1.3106-.0155-1.2503.0608-7.973-.2423-7.4127-.311.5744-2.73 4.5608-2.73 4.5608-.0405.01-.0705.01-.1062.01-.1712-.019-.4366-.074-.51-.2384-.004-.01-.0094-.018-.0129-.028-.0035-.01-.0075-.022-.0135-.04-.0329-.1097-.0463-.2289-.0753-.3265-.1082-.3652-.2813-.8886-.463-1.421-.2784-.9079-.5654-1.8366-.6127-1.9391-.0923-.2007-.2268-.116-.3475-.0002-.54.458-1.6868 2.4793-2.7225 2.5898',
        'beatport'       => 'M21.429 17.055a7.114 7.114 0 0 1-.794 3.246 6.917 6.917 0 0 1-2.181 2.492 6.698 6.698 0 0 1-3.063 1.163 6.653 6.653 0 0 1-3.239-.434 6.796 6.796 0 0 1-2.668-1.932 7.03 7.03 0 0 1-1.481-2.983 7.124 7.124 0 0 1 .049-3.345 7.015 7.015 0 0 1 1.566-2.937l-4.626 4.73-2.421-2.479 5.201-5.265a3.791 3.791 0 0 0 1.066-2.675V0h3.41v6.613a7.172 7.172 0 0 1-.519 2.794 7.02 7.02 0 0 1-1.559 2.353l-.153.156a6.768 6.768 0 0 1 3.49-1.725 6.687 6.687 0 0 1 3.845.5 6.873 6.873 0 0 1 2.959 2.564 7.118 7.118 0 0 1 1.118 3.8Zm-3.089 0a3.89 3.89 0 0 0-.611-2.133 3.752 3.752 0 0 0-1.666-1.424 3.65 3.65 0 0 0-2.158-.233 3.704 3.704 0 0 0-1.92 1.037 3.852 3.852 0 0 0-1.031 1.955 3.908 3.908 0 0 0 .205 2.213c.282.7.76 1.299 1.374 1.721a3.672 3.672 0 0 0 2.076.647 3.637 3.637 0 0 0 2.635-1.096c.347-.351.622-.77.81-1.231.188-.461.285-.956.286-1.456Z',
    ];
}

// Pick a readable glyph color (#0b0b0c or #ffffff) for a given brand background.
function musicContrastColor($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
    if (strlen($hex) !== 6) return '#ffffff';
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $lum > 0.6 ? '#0b0b0c' : '#ffffff';
}

function renderMusicPromoterPage($error = '', $short_url = '', $music_url = '') {
    global $lang, $available_domains, $selected_domain, $supported_langs, $LANG_META;
    $platforms = musicPlatforms();
    $colors    = musicPlatformColors();
    $logos     = musicPlatformLogos();
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Music Promoter — 0x79</title>
    <meta name="description" content="Promote one track or release with verified platform links in one short URL.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace','monospace']}}}};</script>
    <style>
        .pf-row{display:none}
        .pf-input:not(:placeholder-shown) ~ .pf-dot,.pf-input:focus ~ .pf-dot{opacity:1}
        input[type=file]::-webkit-file-upload-button{cursor:pointer}
    </style>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 py-5 sm:px-7 lg:px-8">
        <header class="flex items-center justify-between border-b border-white/10 pb-5">
            <a href="/" class="flex items-center gap-2"><img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover"><span class="font-mono text-sm tracking-tight text-white">0x79</span></a>
            <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
                <a href="/shorten" class="px-2.5 py-1.5 transition hover:text-white">shorten</a>
                <a href="/upload" class="px-2.5 py-1.5 transition hover:text-white">file</a>
                <a href="/paste" class="px-2.5 py-1.5 transition hover:text-white">paste</a>
                <a href="/music" class="px-2.5 py-1.5 text-white transition hover:text-white">music</a>
                <span class="mx-1 h-4 w-px bg-white/10"></span>
                <?php renderLangSelect($lang, $supported_langs, $LANG_META); ?>
                <a href="/api/docs" class="px-2.5 py-1.5 transition hover:text-white">api</a>
            </nav>
        </header>

        <section class="py-10 lg:py-12">
            <div class="max-w-2xl">
                <p class="font-mono text-xs uppercase tracking-[0.22em] text-white/35">tool 04</p>
                <h1 class="mt-4 text-4xl font-semibold tracking-[-0.045em] text-white sm:text-5xl">Music Promoter</h1>
                <p class="mt-5 text-base leading-7 text-white/50">Eine smarte Landing-Page mit allen Streaming-Links — Spotify, Apple Music, YouTube Music, SoundCloud, Deezer, TIDAL und mehr — hinter einem kurzen Link. Jeder Link wird gegen die echte Plattform-Domain geprüft.</p>
            </div>

            <?php if ($error): ?><div class="mt-7 max-w-2xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-100"><?= h($error) ?></div><?php endif; ?>

            <?php if ($short_url): ?>
            <div class="mt-7 overflow-hidden border border-emerald-400/30 bg-emerald-400/[0.07]">
                <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-mono text-xs uppercase tracking-[0.18em] text-emerald-200/70">✓ music link ready</p>
                        <a class="mt-2 block break-all text-2xl font-bold tracking-tight text-white" href="<?= h($short_url) ?>" target="_blank" rel="noopener"><?= h($short_url) ?></a>
                        <?php if ($music_url): ?><p class="mt-2 break-all font-mono text-xs text-white/35">landing: <?= h($music_url) ?></p><?php endif; ?>
                    </div>
                    <div class="flex shrink-0 gap-3">
                        <button type="button" onclick="copyLink(this, '<?= h($short_url) ?>')" data-copy="copy link" data-copied="✓ copied" class="border border-white/15 px-4 py-2.5 font-mono text-xs text-white/80 transition hover:border-white/40 hover:text-white">copy link</button>
                        <a href="<?= h($short_url) ?>" target="_blank" rel="noopener" class="bg-[#f5f2ea] px-4 py-2.5 font-mono text-xs font-semibold text-[#0b0b0c] transition hover:bg-white">open →</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-9 grid items-start gap-8 lg:grid-cols-[1.35fr_.95fr]">
                <!-- Form -->
                <form method="POST" action="/music" enctype="multipart/form-data" class="grid gap-6 border border-white/10 bg-[#101011] p-5 sm:p-6">
                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-white/35">1 · track</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-2"><span class="font-mono text-xs text-white/40">title *</span><input id="f-title" name="title" required maxlength="160" value="<?= h($_POST['title'] ?? '') ?>" placeholder="Song / Release" class="w-full border border-white/10 bg-[#0b0b0c] px-3 py-2.5 text-sm text-white outline-none focus:border-white/35"></label>
                            <label class="grid gap-2"><span class="font-mono text-xs text-white/40">artist</span><input id="f-artist" name="artist" maxlength="160" value="<?= h($_POST['artist'] ?? '') ?>" placeholder="Artist Name" class="w-full border border-white/10 bg-[#0b0b0c] px-3 py-2.5 text-sm text-white outline-none focus:border-white/35"></label>
                        </div>
                    </div>

                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-white/35">2 · artwork</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="grid gap-2 border border-white/10 bg-white/[0.03] p-3">
                                <span class="font-mono text-xs text-white/40">cover (square)</span>
                                <input id="f-cover" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" class="block w-full cursor-pointer border border-white/10 bg-[#0b0b0c] p-2 text-xs text-white file:mr-3 file:cursor-pointer file:border-0 file:bg-[#f5f2ea] file:px-3 file:py-2 file:font-mono file:text-xs file:font-semibold file:text-[#0b0b0c]">
                            </label>
                            <label class="grid gap-2 border border-white/10 bg-white/[0.03] p-3">
                                <span class="font-mono text-xs text-white/40">banner (wide)</span>
                                <input id="f-banner" type="file" name="banner_image" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" class="block w-full cursor-pointer border border-white/10 bg-[#0b0b0c] p-2 text-xs text-white file:mr-3 file:cursor-pointer file:border-0 file:bg-[#f5f2ea] file:px-3 file:py-2 file:font-mono file:text-xs file:font-semibold file:text-[#0b0b0c]">
                            </label>
                        </div>
                    </div>

                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-white/35">3 · streaming links</p>
                        <p class="mt-1 font-mono text-[11px] text-white/25">Mindestens einen Link eintragen. Nur echte Plattform-Domains werden akzeptiert.</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <?php foreach ($platforms as $key => $meta): $c = $colors[$key] ?? '#ffffff'; $logo = $logos[$key] ?? ''; $fg = musicContrastColor($c); ?>
                                <label class="relative grid gap-2">
                                    <span class="flex items-center gap-2 font-mono text-xs text-white/45"><span class="inline-block h-2 w-2 rounded-full" style="background:<?= h($c) ?>"></span><?= h($meta['label']) ?></span>
                                    <span class="relative block">
                                        <input type="url" data-platform="<?= h($key) ?>" data-label="<?= h($meta['label']) ?>" data-badge="<?= h($meta['badge']) ?>" data-color="<?= h($c) ?>" data-fg="<?= h($fg) ?>" data-logo="<?= h($logo) ?>" name="links[<?= h($key) ?>]" value="<?= h($_POST['links'][$key] ?? '') ?>" placeholder="<?= h($meta['hint']) ?>" class="pf-input w-full border border-white/10 bg-[#0b0b0c] px-3 py-2.5 pr-8 text-sm text-white outline-none placeholder:text-white/20 focus:border-white/35">
                                        <span class="pf-dot pointer-events-none absolute right-3 top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full opacity-0 transition" style="background:<?= h($c) ?>"></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-white/35">4 · link options</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="grid gap-2"><span class="font-mono text-xs text-white/40">domain</span><select name="domain" class="border border-white/10 bg-[#0b0b0c] px-3 py-2.5 text-sm text-white outline-none focus:border-white/35"><?php foreach ($available_domains as $domain): ?><option value="<?= h($domain) ?>" <?= $selected_domain === $domain ? 'selected' : '' ?>><?= h($domain) ?></option><?php endforeach; ?></select></label>
                            <label class="grid gap-2"><span class="font-mono text-xs text-white/40">custom alias (optional)</span><input name="custom_code" value="<?= h($_POST['custom_code'] ?? '') ?>" placeholder="mytrack" class="border border-white/10 bg-[#0b0b0c] px-3 py-2.5 text-sm text-white outline-none focus:border-white/35"></label>
                            <label class="grid gap-2"><span class="font-mono text-xs text-white/40">password (optional)</span><input type="password" name="password" autocomplete="new-password" class="border border-white/10 bg-[#0b0b0c] px-3 py-2.5 text-sm text-white outline-none focus:border-white/35"></label>
                            <label class="grid gap-2"><span class="font-mono text-xs text-white/40">expires (optional)</span><input type="datetime-local" name="expires_at" value="<?= h($_POST['expires_at'] ?? '') ?>" class="border border-white/10 bg-[#0b0b0c] px-3 py-2.5 text-sm text-white outline-none [color-scheme:dark] focus:border-white/35"></label>
                            <label class="grid gap-2 sm:col-span-2"><span class="font-mono text-xs text-white/40">burn after N clicks (optional)</span><input type="number" min="1" max="1000000" name="max_clicks" value="<?= h($_POST['max_clicks'] ?? '') ?>" placeholder="unlimited" class="border border-white/10 bg-[#0b0b0c] px-3 py-2.5 text-sm text-white outline-none focus:border-white/35"></label>
                        </div>
                    </div>

                    <button type="submit" class="flex items-center justify-between bg-[#f5f2ea] px-5 py-3.5 font-mono text-sm font-semibold text-[#0b0b0c] transition hover:bg-white"><span>create music link</span><span>→</span></button>
                </form>

                <!-- Live preview -->
                <div class="lg:sticky lg:top-6">
                    <p class="mb-3 font-mono text-[11px] uppercase tracking-[0.2em] text-white/35">live preview</p>
                    <div class="overflow-hidden border border-white/10 bg-[#101011] shadow-2xl">
                        <div id="pv-banner" class="hidden aspect-[21/9] w-full bg-cover bg-center"></div>
                        <div class="p-6">
                            <div class="mx-auto grid h-28 w-28 place-items-center overflow-hidden rounded-md border border-white/15 bg-white/5 shadow-xl">
                                <img id="pv-cover" alt="" class="hidden h-full w-full object-cover">
                                <span id="pv-note" class="font-mono text-3xl text-white/70">♪</span>
                            </div>
                            <p class="mt-6 text-center font-mono text-[11px] uppercase tracking-[0.22em] text-white/35">listen now</p>
                            <h2 id="pv-title" class="mt-2 break-words text-center text-2xl font-bold tracking-tight text-white">Dein Titel</h2>
                            <p id="pv-artist" class="mt-1 text-center text-sm text-white/45"></p>
                            <div id="pv-links" class="mt-6 grid gap-2.5">
                                <p id="pv-empty" class="text-center font-mono text-xs text-white/25">Füge Streaming-Links hinzu…</p>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-center font-mono text-[11px] text-white/25">So sieht deine Seite aus.</p>
                </div>
            </div>
        </section>

        <footer class="mt-8 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row"><span>0x79.one</span><span>Music Promoter · <?= date('Y') ?></span></footer>
    </main>
    <script>
    function copyLink(btn,url){navigator.clipboard.writeText(url).then(function(){var o=btn.textContent;btn.textContent=btn.dataset.copied;setTimeout(function(){btn.textContent=btn.dataset.copy},1600);});}

    (function(){
        var title=document.getElementById('f-title'), artist=document.getElementById('f-artist');
        var pvTitle=document.getElementById('pv-title'), pvArtist=document.getElementById('pv-artist');
        var pvCover=document.getElementById('pv-cover'), pvNote=document.getElementById('pv-note');
        var pvBanner=document.getElementById('pv-banner');
        var pvLinks=document.getElementById('pv-links'), pvEmpty=document.getElementById('pv-empty');
        var inputs=Array.prototype.slice.call(document.querySelectorAll('.pf-input'));

        function txt(){ pvTitle.textContent=title.value.trim()||'Dein Titel'; pvArtist.textContent=artist.value.trim(); }
        function img(file,imgEl,noteEl,bgEl){
            if(!file){ return; }
            var r=new FileReader();
            r.onload=function(e){
                if(bgEl){ bgEl.style.backgroundImage='url('+e.target.result+')'; bgEl.classList.remove('hidden'); }
                else { imgEl.src=e.target.result; imgEl.classList.remove('hidden'); if(noteEl) noteEl.classList.add('hidden'); }
            };
            r.readAsDataURL(file);
        }
        function rows(){
            var html='', n=0;
            inputs.forEach(function(i){
                if(i.value.trim()===''){ return; }
                n++;
                var c=i.dataset.color, label=i.dataset.label, badge=i.dataset.badge, fg=i.dataset.fg||'#fff', logo=i.dataset.logo||'';
                var glyph = logo ? '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="'+fg+'"><path d="'+logo+'"/></svg>' : '<span class="font-mono text-[10px] font-bold" style="color:'+fg+'">'+badge+'</span>';
                html+='<div class="flex items-center justify-between gap-3 border border-white/10 bg-[#0b0b0c] p-2.5">'
                    +'<span class="flex items-center gap-2.5"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full" style="background:'+c+'">'+glyph+'</span>'
                    +'<span class="text-sm font-semibold text-white">'+label+'</span></span>'
                    +'<span class="font-mono text-xs text-white/30">→</span></div>';
            });
            if(n===0){ pvLinks.innerHTML=''; pvLinks.appendChild(pvEmpty); pvEmpty.style.display=''; }
            else { pvLinks.innerHTML=html; }
        }
        title.addEventListener('input',txt); artist.addEventListener('input',txt);
        inputs.forEach(function(i){ i.addEventListener('input',rows); });
        document.getElementById('f-cover').addEventListener('change',function(){ img(this.files[0],pvCover,pvNote,null); });
        document.getElementById('f-banner').addEventListener('change',function(){ img(this.files[0],null,null,pvBanner); });
        txt(); rows();
    })();
    </script>
</body>
</html>
    <?php
    exit;
}

function renderMusicLandingPage($row) {
    $links = $row['links'] ?? [];
    if (is_string($links)) {
        $decoded = json_decode($links, true);
        $links = is_array($decoded) ? $decoded : [];
    }
    $colors    = musicPlatformColors();
    $logos     = musicPlatformLogos();
    $coverUrl  = trim((string)($row['cover_url'] ?? ''));
    $bannerUrl = trim((string)($row['banner_url'] ?? ''));
    $title     = (string)($row['title'] ?? 'Music');
    $artist    = trim((string)($row['artist'] ?? ''));
    $ogImage   = $coverUrl !== '' ? $coverUrl : ($bannerUrl !== '' ? $bannerUrl : '');
    $ogDesc    = ($artist !== '' ? $artist . ' — ' : '') . 'Jetzt auf allen Plattformen streamen.';
    $backdrop  = $coverUrl !== '' ? $coverUrl : $bannerUrl;
    // Same-origin proxy URLs for on-page rendering (raw Supabase URLs are blocked by img-src CSP).
    $coverSrc    = proxyImageUrl($coverUrl);
    $bannerSrc   = proxyImageUrl($bannerUrl);
    $backdropSrc = proxyImageUrl($backdrop);

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="de">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?><?= $artist !== '' ? ' · ' . h($artist) : '' ?> — listen now</title>
    <meta name="description" content="<?= h($ogDesc) ?>">
    <meta name="robots" content="index,follow">
    <meta property="og:type" content="music.song">
    <meta property="og:title" content="<?= h($title . ($artist !== '' ? ' · ' . $artist : '')) ?>">
    <meta property="og:description" content="<?= h($ogDesc) ?>">
    <?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= h($ogImage) ?>"><meta name="twitter:image" content="<?= h($ogImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $ogImage !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= h($title . ($artist !== '' ? ' · ' . $artist : '')) ?>">
    <meta name="twitter:description" content="<?= h($ogDesc) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace','monospace']}}}};</script>
    <style>
        @keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
        .rise{animation:rise .5s cubic-bezier(.2,.7,.2,1) both}
        .lk{transition:transform .15s ease, border-color .15s ease, background .15s ease}
        .lk:hover{transform:translateY(-2px)}
    </style>
</head>
<body class="relative min-h-screen overflow-x-hidden bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <?php if ($backdrop !== ''): ?>
    <div class="pointer-events-none fixed inset-0 -z-10 bg-cover bg-center opacity-25 blur-3xl saturate-150" style="background-image:url('<?= h($backdropSrc) ?>')"></div>
    <div class="pointer-events-none fixed inset-0 -z-10 bg-gradient-to-b from-[#0b0b0c]/60 via-[#0b0b0c]/85 to-[#0b0b0c]"></div>
    <?php endif; ?>

    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-5 py-10">
        <section class="rise overflow-hidden border border-white/10 bg-[#101011]/85 shadow-2xl backdrop-blur-xl">
            <?php if ($bannerUrl !== ''): ?>
                <div class="aspect-[21/9] w-full bg-[#0b0b0c]"><img src="<?= h($bannerSrc) ?>" alt="" class="h-full w-full object-cover"></div>
            <?php endif; ?>
            <div class="p-6 sm:p-8">
                <div class="mx-auto grid h-36 w-36 place-items-center overflow-hidden rounded-xl border border-white/15 bg-white/5 shadow-xl ring-1 ring-black/40 sm:h-44 sm:w-44">
                    <?php if ($coverUrl !== ''): ?>
                        <img src="<?= h($coverSrc) ?>" alt="Cover" class="h-full w-full object-cover">
                    <?php else: ?>
                        <span class="font-mono text-5xl text-white/70">♪</span>
                    <?php endif; ?>
                </div>
                <p class="mt-7 text-center font-mono text-[11px] uppercase tracking-[0.28em] text-white/35">listen now</p>
                <h1 class="mt-2 break-words text-center text-3xl font-extrabold tracking-[-0.04em] text-white sm:text-4xl"><?= h($title) ?></h1>
                <?php if ($artist !== ''): ?><p class="mt-2 text-center text-base text-white/55"><?= h($artist) ?></p><?php endif; ?>

                <div class="mt-7 grid gap-2.5">
                <?php $i = 0; foreach ($links as $link): ?>
                    <?php
                        $url = (string)($link['url'] ?? '');
                        if ($url === '') continue;
                        $label = (string)($link['label'] ?? 'Music');
                        $badge = (string)($link['badge'] ?? '♪');
                        $key   = (string)($link['key'] ?? '');
                        $c     = $colors[$key] ?? '#ffffff';
                        $logo  = $logos[$key] ?? '';
                        $fg    = musicContrastColor($c);
                        $i++;
                    ?>
                    <a href="<?= h($url) ?>" target="_blank" rel="noopener nofollow" class="lk group rise flex items-center justify-between gap-4 border border-white/10 bg-[#0b0b0c]/70 p-3.5 hover:border-white/30 hover:bg-[#151517]" style="animation-delay:<?= $i * 60 ?>ms; border-left:3px solid <?= h($c) ?>">
                        <span class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full" style="background:<?= h($c) ?>">
                                <?php if ($logo !== ''): ?><svg viewBox="0 0 24 24" class="h-5 w-5" fill="<?= h($fg) ?>" aria-hidden="true"><path d="<?= $logo ?>"/></svg><?php else: ?><span class="font-mono text-[11px] font-bold" style="color:<?= h($fg) ?>"><?= h($badge) ?></span><?php endif; ?>
                            </span>
                            <span class="font-semibold text-white"><?= h($label) ?></span>
                        </span>
                        <span class="font-mono text-sm font-semibold text-white/40 transition group-hover:translate-x-1 group-hover:text-white">play →</span>
                    </a>
                <?php endforeach; ?>
                </div>

                <div class="mt-6 flex items-center justify-center">
                    <button type="button" id="shareBtn" data-copied="✓ link kopiert" class="flex items-center gap-2 border border-white/10 px-4 py-2 font-mono text-xs text-white/60 transition hover:border-white/30 hover:text-white">
                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M7.2 11.2a2.8 2.8 0 1 0 0 1.6m0-1.6 9.6-5.4m-9.6 7 9.6 5.4m0 0a2.8 2.8 0 1 0 .1-.1m-.1-12.6a2.8 2.8 0 1 0 .1-.1"/></svg>
                        <span>teilen</span>
                    </button>
                </div>

                <p class="mt-7 text-center font-mono text-[11px] text-white/25">powered by <a href="/music" class="text-white/40 underline decoration-white/15 underline-offset-2 hover:text-white">0x79 Music Promoter</a></p>
            </div>
        </section>
    </main>
    <script>
    (function(){
        var b=document.getElementById('shareBtn');
        b.addEventListener('click',function(){
            var data={title:document.title,url:location.href};
            if(navigator.share){ navigator.share(data).catch(function(){}); return; }
            navigator.clipboard.writeText(location.href).then(function(){
                var s=b.querySelector('span'), o=s.textContent;
                s.textContent=b.dataset.copied;
                setTimeout(function(){ s.textContent=o; },1600);
            });
        });
    })();
    </script>
</body>
</html>
    <?php
    exit;
}

function apiMusicCreateResponse() {
    global $available_domains;

    $apiUser = requireUserApiAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST, OPTIONS');
        jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }

    $input = apiReadInput();
    if (!checkCreateRateLimit(10, 3600)) {
        jsonResponse(['ok' => false, 'error' => 'rate_limited'], 429);
    }

    $linksInput = $input['links'] ?? [];
    if (!is_array($linksInput)) $linksInput = [];

    $domain = $input['domain'] ?? ($_SERVER['HTTP_HOST'] ?? $available_domains[0]);
    [$ok, $err, $result] = createMusicPromo(
        $input['title'] ?? '',
        $input['artist'] ?? '',
        $linksInput,
        $domain,
        $input['password'] ?? '',
        $input['expires_at'] ?? $input['valid_until'] ?? '',
        $input['max_clicks'] ?? $input['burn_after'] ?? '',
        $input['custom_code'] ?? $input['alias'] ?? $input['short_code'] ?? '',
        $apiUser['id'] ?? null,
        $input['cover_url'] ?? '',
        $input['banner_url'] ?? ''
    );

    if (!$ok) {
        $status = in_array($err, ['alias_taken'], true) ? 409 : 400;
        jsonResponse(['ok' => false, 'error' => $err, 'message' => musicErrorText($err)], $status);
    }

    jsonResponse(['ok' => true, 'type' => 'music',] + $result, 201);
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
    if (!in_array($tab, ['links', 'pastes', 'abuse', 'protocols', 'posts'], true)) {
        $tab = 'links';
    }

    $linksLimit = isset($_GET['links_limit']) ? (int)$_GET['links_limit'] : 25;
    $linksLimit = max(5, min($linksLimit, 100));
    $linksOffset = isset($_GET['links_offset']) ? (int)$_GET['links_offset'] : 0;
    $linksOffset = max(0, $linksOffset);
    $linksSearch = adminCleanSearch($_GET['q_links'] ?? '');

    $pastesLimit = isset($_GET['pastes_limit']) ? (int)$_GET['pastes_limit'] : 25;
    $pastesLimit = max(5, min($pastesLimit, 100));
    $pastesOffset = isset($_GET['pastes_offset']) ? (int)$_GET['pastes_offset'] : 0;
    $pastesOffset = max(0, $pastesOffset);
    $pastesSearch = adminCleanSearch($_GET['q_pastes'] ?? '');

    $abuseLimit = isset($_GET['abuse_limit']) ? (int)$_GET['abuse_limit'] : 25;
    $abuseLimit = max(5, min($abuseLimit, 100));
    $abuseOffset = isset($_GET['abuse_offset']) ? (int)$_GET['abuse_offset'] : 0;
    $abuseOffset = max(0, $abuseOffset);
    $abuseSearch = adminCleanSearch($_GET['q_abuse'] ?? '');

    $postsLimit = isset($_GET['posts_limit']) ? (int)$_GET['posts_limit'] : 25;
    $postsLimit = max(5, min($postsLimit, 100));
    $postsOffset = isset($_GET['posts_offset']) ? (int)$_GET['posts_offset'] : 0;
    $postsOffset = max(0, $postsOffset);
    $postsSearch = adminCleanSearch($_GET['q_posts'] ?? '');

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
    [$pastesOk, $pastes, $pastesStatus, $pastesHasMore] = fetchAdminPastes($pastesLimit, $pastesOffset, $pastesSearch);
    [$abuseOk, $abuseReports, $abuseStatus, $abuseHasMore] = fetchAdminAbuseReports($abuseLimit, $abuseOffset, $abuseSearch);
    [$postsOk, $postsList, $postsStatus, $postsHasMore] = fetchAdminPosts($postsLimit, $postsOffset, $postsSearch);

    $linksPrev = max(0, $linksOffset - $linksLimit);
    $linksNext = $linksOffset + $linksLimit;
    $pastesPrev = max(0, $pastesOffset - $pastesLimit);
    $pastesNext = $pastesOffset + $pastesLimit;
    $abusePrev = max(0, $abuseOffset - $abuseLimit);
    $abuseNext = $abuseOffset + $abuseLimit;
    $postsPrev = max(0, $postsOffset - $postsLimit);
    $postsNext = $postsOffset + $postsLimit;

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
</head>
<body>
<main>
    <header>
        <div>
            <h1><?= h($t['admin_dashboard']) ?></h1>
            <div class="muted">links · <?= h((string)count($links)) ?> aktuell · pastes · <?= h((string)count($pastes)) ?> aktuell · abuse · <?= h((string)count($abuseReports)) ?> aktuell · posts · <?= h((string)($postsOk ? count($postsList) : 0)) ?> aktuell · protokolle · <?= h((string)count($enabledSchemes)) ?> aktiv</div>
        </div>
        <div class="actions">
            <a class="btn" href="/api/admin/links.csv"><?= h($t['admin_csv']) ?> ↓</a>
            <a class="btn" href="/abuse">abuse form</a>
            <a class="btn" href="/">startseite</a>
            <form method="POST" action="/admin/logout"><button type="submit"><?= h($t['admin_logout']) ?></button></form>
        </div>
    </header>

    <?php if ($notice !== ''): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($adminError !== ''): ?><div class="admin-error"><?= h($adminError) ?></div><?php endif; ?>

    <nav class="tabs" aria-label="admin tabs">
        <a class="tab <?= $tab === 'links' ? 'active' : '' ?>" href="<?= h(adminUrl(['tab' => 'links'])) ?>">links</a>
        <a class="tab <?= $tab === 'pastes' ? 'active' : '' ?>" href="<?= h(adminUrl(['tab' => 'pastes'])) ?>">pastes</a>
        <a class="tab <?= $tab === 'abuse' ? 'active' : '' ?>" href="<?= h(adminUrl(['tab' => 'abuse'])) ?>">abuse meldungen</a>
        <a class="tab <?= $tab === 'protocols' ? 'active' : '' ?>" href="<?= h(adminUrl(['tab' => 'protocols'])) ?>">protokolle</a>
        <a class="tab <?= $tab === 'posts' ? 'active' : '' ?>" href="<?= h(adminUrl(['tab' => 'posts'])) ?>">posts</a>
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
                    <input type="hidden" name="abuse_limit" value="<?= h((string)$abuseLimit) ?>">
                    <input type="hidden" name="abuse_offset" value="<?= h((string)$abuseOffset) ?>">
                    <input type="hidden" name="q_abuse" value="<?= h($abuseSearch) ?>">
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


    <section class="panel <?= $tab === 'pastes' ? 'active' : '' ?>" id="pastes">
        <div class="panel-head">
            <div>
                <h2>pastes</h2>
                <div class="count">zeige <?= h((string)($pastesOffset + 1)) ?>–<?= h((string)($pastesOffset + count($pastes))) ?><?= $pastesSearch !== '' ? ' · suche: ' . h($pastesSearch) : '' ?></div>
            </div>
            <div class="tools">
                <form method="GET" action="/admin">
                    <input type="hidden" name="tab" value="pastes">
                    <input type="search" name="q_pastes" value="<?= h($pastesSearch) ?>" placeholder="code oder inhalt suchen">
                    <select name="pastes_limit" aria-label="pastes pro seite">
                        <?php foreach ([10,25,50,100] as $n): ?>
                            <option value="<?= h((string)$n) ?>" <?= $pastesLimit === $n ? 'selected' : '' ?>><?= h((string)$n) ?>/seite</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="pastes_offset" value="0">
                    <button type="submit">suchen</button>
                    <?php if ($pastesSearch !== ''): ?><a class="btn" href="<?= h(adminUrl(['tab' => 'pastes', 'q_pastes' => '', 'pastes_offset' => 0])) ?>">reset</a><?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (!$pastesOk): ?>
            <p class="err">Supabase error. Status: <?= h((string)$pastesStatus) ?> — Tabelle pastes fehlt evtl.; SQL-Migration ausführen.</p>
        <?php elseif (empty($pastes)): ?>
            <p class="muted">keine pastes gefunden.</p>
        <?php else: ?>
            <div class="card">
                <table>
                    <thead><tr><th>code</th><th>preview</th><th>views</th><th>burn</th><th>expires</th><th>password</th><th>created</th><th>actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($pastes as $paste): ?>
                        <tr>
                            <td><code><?= h($paste['paste_code'] ?? '') ?></code></td>
                            <td class="reason"><?= h(mb_substr(str_replace(["\r", "\n"], ' ', (string)($paste['content'] ?? '')), 0, 140)) ?></td>
                            <td><?= h((string)($paste['view_count'] ?? 0)) ?></td>
                            <td><?= !empty($paste['max_views']) ? h((string)$paste['max_views']) : '<span class="muted">never</span>' ?></td>
                            <td><?= !empty($paste['expires_at']) ? h(formatDateTime($paste['expires_at'])) : '<span class="muted">never</span>' ?></td>
                            <td><?= !empty($paste['password_hash']) ? 'yes' : '<span class="muted">no</span>' ?></td>
                            <td><?= h(formatDateTime($paste['created_at'] ?? '')) ?></td>
                            <td>
                                <div class="inline-actions">
                                    <a class="btn" href="/<?= h($paste['paste_code'] ?? '') ?>" target="_blank" rel="noopener">open</a>
                                    <a class="btn" href="/raw/<?= h($paste['paste_code'] ?? '') ?>" target="_blank" rel="noopener">raw</a>
                                    <form method="POST" action="/admin/action" onsubmit="return confirm('Paste <?= h($paste['paste_code'] ?? '') ?> wirklich löschen?');">
                                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="return_to" value="<?= h($_SERVER['REQUEST_URI'] ?? '/admin?tab=pastes') ?>">
                                        <input type="hidden" name="action" value="delete_paste">
                                        <input type="hidden" name="paste_id" value="<?= h($paste['id'] ?? '') ?>">
                                        <button class="danger" type="submit">löschen</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="pager">
            <a class="btn <?= $pastesOffset <= 0 ? 'disabled' : '' ?>" href="<?= h(adminUrl(['tab' => 'pastes', 'pastes_offset' => $pastesPrev])) ?>">← vorherige</a>
            <span class="muted">offset <?= h((string)$pastesOffset) ?></span>
            <a class="btn <?= !$pastesHasMore ? 'disabled' : '' ?>" href="<?= h(adminUrl(['tab' => 'pastes', 'pastes_offset' => $pastesNext])) ?>">nächste →</a>
        </div>
    </section>

    <section class="panel <?= $tab === 'abuse' ? 'active' : '' ?>" id="abuse">
        <div class="panel-head">
            <div>
                <h2>abuse meldungen</h2>
                <div class="count">zeige <?= h((string)($abuseOffset + 1)) ?>–<?= h((string)($abuseOffset + count($abuseReports))) ?><?= $abuseSearch !== '' ? ' · suche: ' . h($abuseSearch) : '' ?></div>
            </div>
            <div class="tools">
                <form method="GET" action="/admin">
                    <input type="hidden" name="tab" value="abuse">
                    <input type="hidden" name="links_limit" value="<?= h((string)$linksLimit) ?>">
                    <input type="hidden" name="links_offset" value="<?= h((string)$linksOffset) ?>">
                    <input type="hidden" name="q_links" value="<?= h($linksSearch) ?>">
                    <input type="search" name="q_abuse" value="<?= h($abuseSearch) ?>" placeholder="link, grund oder status suchen">
                    <select name="abuse_limit" aria-label="abuse meldungen pro seite">
                        <?php foreach ([10,25,50,100] as $n): ?>
                            <option value="<?= h((string)$n) ?>" <?= $abuseLimit === $n ? 'selected' : '' ?>><?= h((string)$n) ?>/seite</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="abuse_offset" value="0">
                    <button type="submit">suchen</button>
                    <?php if ($abuseSearch !== ''): ?><a class="btn" href="<?= h(adminUrl(['tab' => 'abuse', 'q_abuse' => '', 'abuse_offset' => 0])) ?>">reset</a><?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (!$abuseOk): ?>
            <p class="err">Supabase error für abuse_reports. Status: <?= h((string)$abuseStatus) ?></p>
            <p class="muted">Führe die SQL-Migration aus, falls die Tabelle noch fehlt.</p>
        <?php elseif (empty($abuseReports)): ?>
            <p class="muted">keine abuse meldungen gefunden.</p>
        <?php else: ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>status</th>
                            <th>reported link/code</th>
                            <th>reason</th>
                            <th>created</th>
                            <th>actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($abuseReports as $report): ?>
                            <?php $reportedCode = extractShortCodeFromReportedLink($report['reported_link']); ?>
                            <tr>
                                <td><span class="pill <?= h($report['status'] === 'open' ? 'open' : '') ?>"><?= h($report['status']) ?></span></td>
                                <td class="url" title="<?= h($report['reported_link']) ?>"><?= h($report['reported_link']) ?></td>
                                <td class="reason"><?= h($report['reason']) ?></td>
                                <td><?= h(formatDateTime($report['created_at'] ?? '')) ?></td>
                                <td>
                                    <div class="inline-actions">
                                        <?php if ($reportedCode): ?>
                                            <form method="POST" action="/admin/action" onsubmit="return confirm('Link <?= h($reportedCode) ?> wirklich löschen?');">
                                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                                <input type="hidden" name="return_to" value="<?= h($_SERVER['REQUEST_URI'] ?? '/admin') ?>">
                                                <input type="hidden" name="action" value="delete_link_by_code">
                                                <input type="hidden" name="code" value="<?= h($reportedCode) ?>">
                                                <button class="danger" type="submit">link löschen</button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" disabled>kein code</button>
                                        <?php endif; ?>

                                        <form method="POST" action="/admin/action" onsubmit="return confirm('Report wirklich löschen?');">
                                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="return_to" value="<?= h($_SERVER['REQUEST_URI'] ?? '/admin') ?>">
                                            <input type="hidden" name="action" value="delete_report">
                                            <input type="hidden" name="report_id" value="<?= h($report['id']) ?>">
                                            <button type="submit">report löschen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pager">
                <a class="btn <?= $abuseOffset <= 0 ? 'disabled' : '' ?>" href="<?= h(adminUrl(['tab' => 'abuse', 'abuse_offset' => $abusePrev])) ?>">← vorherige</a>
                <span class="muted">offset <?= h((string)$abuseOffset) ?></span>
                <a class="btn <?= !$abuseHasMore ? 'disabled' : '' ?>" href="<?= h(adminUrl(['tab' => 'abuse', 'abuse_offset' => $abuseNext])) ?>">nächste →</a>
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

    <section class="panel <?= $tab === 'posts' ? 'active' : '' ?>" id="posts">
        <div class="panel-head">
            <div>
                <h2>posts</h2>
                <div class="count">zeige <?= h((string)($postsOffset + 1)) ?>–<?= h((string)($postsOffset + count($postsList))) ?><?= $postsSearch !== '' ? ' · suche: ' . h($postsSearch) : '' ?></div>
            </div>
            <div class="tools">
                <form method="GET" action="/admin">
                    <input type="hidden" name="tab" value="posts">
                    <input type="search" name="q_posts" value="<?= h($postsSearch) ?>" placeholder="titel oder inhalt suchen">
                    <select name="posts_limit" aria-label="posts pro seite">
                        <?php foreach ([10,25,50,100] as $n): ?>
                            <option value="<?= h((string)$n) ?>" <?= $postsLimit === $n ? 'selected' : '' ?>><?= h((string)$n) ?>/seite</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="posts_offset" value="0">
                    <button type="submit">suchen</button>
                    <?php if ($postsSearch !== ''): ?><a class="btn" href="<?= h(adminUrl(['tab' => 'posts', 'q_posts' => '', 'posts_offset' => 0])) ?>">reset</a><?php endif; ?>
                </form>
            </div>
        </div>

        <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: start;">
            <!-- LEFT: POSTS LIST -->
            <div class="card" style="flex: 2; min-width: 320px; padding: 0;">
                <?php if (!$postsOk): ?>
                    <p class="err" style="padding: 16px;">Supabase error. Status: <?= h((string)$postsStatus) ?> — Tabelle public.posts fehlt evtl.; SQL-Migration ausführen.</p>
                <?php elseif (empty($postsList)): ?>
                    <p class="muted" style="padding: 16px;">keine beiträge gefunden.</p>
                <?php else: ?>
                    <table style="min-width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 80px;">bild</th>
                                <th>titel & beschreibung</th>
                                <th style="width: 150px;">datum</th>
                                <th style="width: 100px;">aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($postsList as $postItem): ?>
                                <tr>
                                    <td>
                                        <?php if ($postItem['image']): ?>
                                            <img src="<?= h($postItem['image']) ?>" alt="Post Image" style="width: 60px; height: 40px; object-fit: cover; border: 1px solid var(--rule); border-radius: 4px;">
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= h($postItem['title'] ?? '') ?></strong>
                                        <div class="muted" style="font-size: 11px; margin-top: 4px; max-height: 48px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                            <?= h($postItem['description'] ?? '') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= h(date('d.m.Y H:i', (int)($postItem['pub_date'] ?? 0))) ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="/admin/action" onsubmit="return confirm('Beitrag <?= h($postItem['title'] ?? '') ?> wirklich löschen?');">
                                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="return_to" value="<?= h($_SERVER['REQUEST_URI'] ?? '/admin') ?>">
                                            <input type="hidden" name="action" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?= h($postItem['id'] ?? '') ?>">
                                            <button class="danger" type="submit">löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- RIGHT: NEW POST FORM -->
            <form method="POST" action="/admin/action" enctype="multipart/form-data" class="card" style="flex: 1; min-width: 300px; padding: 16px; display: grid; gap: 14px;">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="return_to" value="<?= h(adminUrl(['tab' => 'posts'])) ?>">
                <input type="hidden" name="action" value="publish_post">

                <h3 style="margin: 0; font-size: 14px;">beitrag veröffentlichen</h3>

                <div style="display: grid; gap: 4px;">
                    <label for="post-title" class="muted">titel</label>
                    <input type="text" id="post-title" name="title" required placeholder="titel eingeben">
                </div>

                <div style="display: grid; gap: 4px;">
                    <label for="post-description" class="muted">beschreibung / inhalt</label>
                    <textarea id="post-description" name="description" rows="6" required placeholder="inhalt hier schreiben…" style="background: var(--bg); color: var(--fg); border: 1px solid var(--rule); padding: 8px; font: inherit; resize: vertical;"></textarea>
                </div>

                <div style="display: grid; gap: 4px;">
                    <label for="post-image-file" class="muted">bild hochladen</label>
                    <input type="file" id="post-image-file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
                    <span class="muted" style="font-size: 11px;">erlaubt: JPG, PNG, WEBP, GIF. max 25 MB.</span>
                </div>

                <div style="display: grid; gap: 4px;">
                    <label for="post-image-url" class="muted">oder bild-url eingeben</label>
                    <input type="text" id="post-image-url" name="image_url" placeholder="https://…">
                </div>

                <div style="display: grid; gap: 4px;">
                    <label for="post-pub-date" class="muted">veröffentlichungsdatum</label>
                    <input type="datetime-local" id="post-pub-date" name="pub_date" value="<?= date('Y-m-d\TH:i') ?>">
                </div>

                <button type="submit" class="btn" style="margin-top: 6px;">beitrag veröffentlichen →</button>
            </form>
        </div>

        <div class="pager" style="margin-top: 14px;">
            <a class="btn <?= $postsOffset <= 0 ? 'disabled' : '' ?>" href="<?= h(adminUrl(['tab' => 'posts', 'posts_offset' => $postsPrev])) ?>">← vorherige</a>
            <span class="muted">offset <?= h((string)$postsOffset) ?></span>
            <a class="btn <?= !$postsHasMore ? 'disabled' : '' ?>" href="<?= h(adminUrl(['tab' => 'posts', 'posts_offset' => $postsNext])) ?>">nächste →</a>
        </div>
    </section>
</main>
</body>
</html>
    <?php
    exit;
}

function renderApiDocs() {
    global $available_domains;

    $host = cleanHost($_SERVER['HTTP_HOST'] ?? '0x79.one');

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="de">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>0x79 API Docs</title>
    <style>
        :root { --bg:#0e0e10; --fg:#ebe9e3; --muted:#a9a59c; --rule:#2a2a2d; --card:#151518; --accent:#ffffff; --bad:#ff6b6b; }
        * { box-sizing:border-box; }
        body { margin:0; padding:40px 22px; font:14px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace; background:var(--bg); color:var(--fg); }
        main { max-width:920px; margin:0 auto; }
        a { color:var(--fg); }
        h1 { font-size:28px; margin:0 0 8px; letter-spacing:-.02em; }
        h2 { font-size:16px; margin:34px 0 12px; border-top:1px solid var(--rule); padding-top:22px; }
        h3 { font-size:13px; margin:22px 0 8px; color:var(--fg); }
        p, li { color:var(--muted); }
        code, pre { background:var(--card); color:var(--fg); border:1px solid var(--rule); }
        code { padding:2px 5px; }
        pre { padding:16px; overflow:auto; }
        .pill { display:inline-block; border:1px solid var(--rule); padding:4px 8px; margin:0 6px 6px 0; color:var(--muted); }
        .note { border:1px solid var(--rule); background:var(--card); padding:14px; color:var(--muted); }
        .bad { color:var(--bad); }
    </style>
</head>
<body>
<main>
    <h1>0x79 API Docs</h1>
    <p>API für URL Shortener, File/Image Host und Paste Host.</p>

    <h2>Base URLs</h2>
    <pre>https://<?= h($host) ?>/api
https://<?= h($host) ?>/api/file
https://<?= h($host) ?>/api/paste</pre>

    <h2>Accounts & API-Key</h2>
    <p>Für Create-API-Endpunkte brauchst du einen normalen User-Account. Erstelle einen Account über <code>/register</code>, logge dich über <code>/login</code> ein und kopiere deinen API-Key auf <code>/account</code>.</p>
    <div class="note">Ohne Login erstellte Inhalte über die Weboberfläche laufen standardmäßig nach 14 Tagen ab. Inhalte, die einem eingeloggten User/API-Key gehören, haben kein automatisches 14-Tage-Limit, außer du setzt selbst <code>expires_at</code>.</div>

    <h3>API-Key senden</h3>
    <pre>X-API-Key: YOUR_KEY</pre>
    <p>Alternativ wird auch <code>Authorization: Bearer YOUR_KEY</code> akzeptiert.</p>

    <h2>Verfügbare Domains</h2>
    <p><?php foreach ($available_domains as $d): ?><span class="pill"><?= h($d) ?></span><?php endforeach; ?></p>

    <h2>URL Shortlink erstellen</h2>
    <p><code>POST /api</code> mit JSON Body. Benötigt <code>X-API-Key</code>.</p>
    <pre>curl -X POST https://<?= h($host) ?>/api \
  -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"long_url":"https://example.com","domain":"0x79.one","custom_code":"github","password":"optional","expires_at":"2026-12-31T23:59","max_clicks":25,"preview_enabled":true}'</pre>

    <p>Antwort:</p>
    <pre>{
  "ok": true,
  "long_url": "https://example.com",
  "short_code": "github",
  "short_url": "https://0x79.one/github",
  "domain": "0x79.one",
  "expires_at": "2026-12-31T23:59:00+00:00",
  "has_password": true,
  "click_count": 0,
  "max_clicks": 25,
  "preview_enabled": true,
  "custom_code": "github"
}</pre>

    <h2>Shortlink abfragen</h2>
    <p><code>GET /api?code=Ab12Cd</code> bleibt öffentlich und braucht keinen API-Key.</p>
    <pre>curl "https://<?= h($host) ?>/api?code=Ab12Cd"</pre>

    <p>Antwort:</p>
    <pre>{
  "ok": true,
  "short_code": "Ab12Cd",
  "long_url": "https://example.com",
  "short_url": "https://<?= h($host) ?>/Ab12Cd",
  "expires_at": null,
  "click_count": 3,
  "has_password": false,
  "max_clicks": null
}</pre>

    <h2>File/Image per API hochladen</h2>
    <p><code>POST /api/file</code>, <code>POST /api/upload-file</code>, <code>POST /api/image</code> oder <code>POST /api/upload-image</code>. Benötigt <code>X-API-Key</code>.</p>
    <p>Erlaubt sind Bilder und ZIP-Dateien. SVG bleibt blockiert.</p>
    <pre>curl -X POST https://<?= h($host) ?>/api/file \
  -H "X-API-Key: YOUR_KEY" \
  -F "file=@./bild.png" \
  -F "domain=0x79.one" \
  -F "custom_code=my-file" \
  -F "max_clicks=10"</pre>

    <p>Antwort:</p>
    <pre>{
  "ok": true,
  "type": "file",
  "short_code": "my-file",
  "short_url": "https://0x79.one/my-file",
  "domain": "0x79.one",
  "expires_at": null,
  "max_clicks": 10,
  "has_password": false,
  "file": {
    "bucket": "files",
    "path": "...",
    "mime": "image/png",
    "size": 12345,
    "original_name": "bild.png"
  },
  "note": "Visitors open the file on your short URL; the Supabase URL is not returned."
}</pre>

    <h2>Paste per API erstellen</h2>
    <p><code>POST /api/paste</code> oder <code>POST /api/create-paste</code>. Benötigt <code>X-API-Key</code>.</p>
    <pre>curl -X POST https://<?= h($host) ?>/api/paste \
  -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"content":"hello paste","custom_code":"hello","max_views":4}'</pre>

    <p>Form-POST geht auch:</p>
    <pre>curl -X POST https://<?= h($host) ?>/api/paste \
  -H "X-API-Key: YOUR_KEY" \
  -F "content=hello paste" \
  -F "custom_code=hello"</pre>

    <p>Antwort:</p>
    <pre>{
  "ok": true,
  "type": "paste",
  "paste_code": "hello",
  "short_code": "hello",
  "short_url": "https://0x79.one/hello",
  "raw_url": "https://0x79.one/raw/hello",
  "domain": "0x79.one",
  "expires_at": null,
  "max_views": 4,
  "has_password": false,
  "view_count": 0
}</pre>

    <h2>Music Promoter per API erstellen</h2>
    <p><code>POST /api/music</code> oder <code>POST /api/create-music</code>. Benötigt <code>X-API-Key</code>. Die Plattform-Links werden gegen echte offizielle Domains geprüft.</p>
    <pre>curl -X POST https://<?= h($host) ?>/api/music \
  -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"title":"My Track","artist":"My Artist","domain":"0x79.one","custom_code":"my-track","links":{"spotify":"https://open.spotify.com/track/...","apple_music":"https://music.apple.com/...","youtube_music":"https://music.youtube.com/watch?v=..."}}'</pre>

    <p>Antwort:</p>
    <pre>{
  "ok": true,
  "type": "music",
  "music_code": "Ab12CdEf",
  "music_url": "https://0x79.one/music/Ab12CdEf",
  "short_code": "my-track",
  "short_url": "https://0x79.one/my-track",
  "domain": "0x79.one",
  "expires_at": null,
  "max_clicks": null,
  "has_password": false
}</pre>

    <h2>Optionale Felder</h2>
    <pre>domain       0x79.one / fftrclo.store / takeitdown.space
custom_code  eigener Alias, alternativ alias oder short_code
password     optionaler Passwortschutz
expires_at   Ablaufdatum, z. B. 2026-12-31T23:59
max_clicks   Burn-after-clicks für URL/File/Music
max_views    Burn-after-views für Paste</pre>

    <h2>Passwort-Link direkt öffnen</h2>
    <p>Geschützte Links können optional per Query geöffnet werden. Der normale Passwort-Screen bleibt weiterhin verfügbar.</p>
    <pre>https://<?= h($host) ?>/Ab12Cd?pw=dein-passwort
https://<?= h($host) ?>/Ab12Cd?password=dein-passwort</pre>

    <h2>Eigene Inhalte löschen</h2>
    <p>Normale User können eigene Links, Files und Pastes im Account-Dashboard unter <code>/account</code> löschen. API-Löschendpunkte sind aktuell nicht öffentlich dokumentiert.</p>

    <h2>Screenshot API</h2>
    <p><code>GET /api/screenshot</code> oder <code>POST /api/screenshot</code>. Bleibt Admin-only und ist geschützt per <code>Authorization: Bearer ADMIN_API_KEY</code> oder Admin-Session. Antwort ist direkt das Bild, nicht JSON.</p>
    <pre>curl -H "Authorization: Bearer YOUR_ADMIN_API_KEY" \
  "https://<?= h($host) ?>/api/screenshot?url=https%3A%2F%2Fexample.com&width=1440&height=900&format=png" \
  --output screenshot.png</pre>

    <p>JSON Body per POST:</p>
    <pre>{
  "url": "https://example.com",
  "width": 1440,
  "height": 900,
  "format": "png",
  "full_page": false
}</pre>

    <h2>Typische Fehler</h2>
    <pre>{ "ok": false, "error": "api_key_required", "hint": "create an account and send X-API-Key: YOUR_KEY" }
{ "ok": false, "error": "invalid_url" }
{ "ok": false, "error": "alias_taken" }
{ "ok": false, "error": "rate_limited" }
{ "ok": false, "error": "not_found" }
{ "ok": false, "error": "expired" }
{ "ok": false, "error": "burned" }
{ "ok": false, "error": "method_not_allowed" }</pre>

    <p><a href="/">← zurück</a></p>
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
function renderUploadPage($error = '', $short_url = '') {
    global $lang, $t, $available_domains, $selected_domain, $file_upload_max_mb;
    header('Content-Type: text/html; charset=utf-8');
    $maxMb    = max(1, $file_upload_max_mb);
    $maxBytes = $maxMb * 1024 * 1024;
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['upload_title']) ?> — 0x79</title>
    <meta name="description" content="<?= h($t['upload_lead']) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace','monospace']}}}};</script>
    <style>
        #dz.over{border-color:rgba(255,255,255,.5);background:rgba(255,255,255,.04)}
        #dz .ico{transition:transform .15s}
        #dz.over .ico{transform:scale(1.1)}
        #pbar{transition:width .2s linear}
    </style>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
<main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-5 py-5 sm:px-7 lg:px-8">

    <header class="flex items-center justify-between border-b border-white/10 pb-5">
        <a href="/" class="flex items-center gap-2">
            <img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover">
            <span class="font-mono text-sm tracking-tight text-white">0x79</span>
        </a>
        <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
            <a href="/" class="px-2.5 py-1.5 transition hover:text-white">home</a>
            <a href="/shorten" class="px-2.5 py-1.5 transition hover:text-white">shorten</a>
            <a href="/upload" class="px-2.5 py-1.5 text-white transition hover:text-white">file</a>
            <a href="/paste" class="px-2.5 py-1.5 transition hover:text-white"><?= h($t['paste']) ?></a>
            <span class="mx-1 h-4 w-px bg-white/10"></span>
            <a href="/abuse" class="px-2.5 py-1.5 transition hover:text-white"><?= h($t['abuse']) ?></a>
            <?php if (isUserLoggedIn()): ?><a href="/account" class="px-2.5 py-1.5 transition hover:text-white">account</a><?php else: ?><a href="/login" class="px-2.5 py-1.5 transition hover:text-white">login</a><?php endif; ?>
        </nav>
    </header>

    <section class="grid flex-1 items-start gap-10 py-10 lg:grid-cols-[1fr_1.25fr] lg:py-14">

        <!-- Left -->
        <div>
            <p class="font-mono text-xs uppercase tracking-[0.22em] text-white/35">tool 02</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.045em] text-white sm:text-5xl"><?= h($t['upload_title']) ?></h1>
            <p class="mt-5 text-base leading-7 text-white/50"><?= h($t['upload_lead']) ?></p>
            <p class="mt-5 border-l border-white/15 pl-4 font-mono text-xs leading-6 text-white/35"><?= h(str_replace('25', (string)$maxMb, $t['file_hint'])) ?></p>

            <?php if ($error !== ''): ?>
            <div class="mt-8 border border-red-400/25 bg-red-500/10 p-4">
                <p class="font-mono text-xs uppercase tracking-[0.22em] text-red-300/60"><?= h($t['err']) ?></p>
                <p class="mt-2 text-sm text-red-200"><?= h($error) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($short_url !== ''): ?>
            <div class="mt-8 border border-emerald-400/25 bg-emerald-500/10 p-5">
                <p class="font-mono text-xs uppercase tracking-[0.22em] text-emerald-300/60"><?= h($t['uploaded_file']) ?></p>
                <a href="<?= h($short_url) ?>" target="_blank" rel="noopener"
                   class="mt-3 block break-all font-mono text-sm text-white underline decoration-white/20 underline-offset-4"><?= h($short_url) ?></a>
                <img src="/qr?d=<?= h(rawurlencode($short_url)) ?>" alt="QR code" width="104" height="104" class="mt-4 border border-white/10 bg-white p-1">
            </div>
            <?php endif; ?>

            <!-- JS result / error (shown after XHR) -->
            <div id="js-result" class="mt-8 hidden border border-emerald-400/25 bg-emerald-500/10 p-5">
                <p class="font-mono text-xs uppercase tracking-[0.22em] text-emerald-300/60"><?= h($t['uploaded_file']) ?></p>
                <a id="js-url" href="#" target="_blank" rel="noopener"
                   class="mt-3 block break-all font-mono text-sm text-white underline decoration-white/20 underline-offset-4"></a>
                <img id="js-qr" src="" alt="QR code" width="104" height="104" class="mt-4 border border-white/10 bg-white p-1">
                <div class="mt-4 flex gap-3">
                    <button onclick="doCopy()" class="border border-white/15 px-4 py-2 font-mono text-xs text-white hover:border-white/40"><?= h($t['copy']) ?></button>
                    <button onclick="doReset()" class="border border-white/15 px-4 py-2 font-mono text-xs text-white/45 hover:border-white/40 hover:text-white">upload another</button>
                </div>
            </div>
            <div id="js-error" class="mt-8 hidden border border-red-400/25 bg-red-500/10 p-4">
                <p class="font-mono text-xs uppercase tracking-[0.22em] text-red-300/60"><?= h($t['err']) ?></p>
                <p id="js-errmsg" class="mt-2 text-sm text-red-200"></p>
            </div>
        </div>

        <!-- Right: upload card -->
        <div id="ucard" class="border border-white/10 bg-[#101011]">
            <div class="border-b border-white/10 px-5 py-4">
                <p class="font-mono text-[11px] uppercase tracking-[0.22em] text-white/35">create</p>
                <h2 class="mt-1 text-lg font-medium tracking-tight text-white"><?= h($t['upload_submit']) ?></h2>
            </div>
            <div class="grid gap-5 p-5 sm:p-6">

                <!-- Drop zone -->
                <div id="dz"
                     class="relative flex min-h-[180px] cursor-pointer flex-col items-center justify-center gap-3 border-2 border-dashed border-white/15 bg-[#0b0b0c] p-8 transition hover:border-white/30"
                     onclick="document.getElementById('fi').click()"
                     ondragover="ev.preventDefault();this.classList.add('over')" ondragleave="this.classList.remove('over')"
                     ondrop="ev.preventDefault();this.classList.remove('over');pick(ev.dataTransfer.files[0])">

                    <div id="dz-default" class="flex flex-col items-center gap-2 text-center">
                        <svg class="ico h-10 w-10 text-white/20" fill="none" stroke="currentColor" stroke-width="1.3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                        <p class="font-mono text-sm text-white/50">drag & drop or <span class="text-white underline underline-offset-2">browse</span></p>
                        <p class="font-mono text-xs text-white/25">JPG · PNG · WEBP · GIF · AVIF · ZIP · max <?= h((string)$maxMb) ?> MB</p>
                    </div>

                    <div id="dz-preview" class="hidden w-full">
                        <img id="img-prev" src="" alt="" class="mx-auto mb-3 hidden max-h-36 max-w-full object-contain">
                        <div class="border border-white/10 bg-[#101011] px-3 py-2.5">
                            <p id="fn" class="truncate font-mono text-xs text-white"></p>
                            <p id="fsz" class="mt-0.5 font-mono text-xs text-white/40"></p>
                        </div>
                    </div>

                    <input id="fi" type="file"
                           accept="image/jpeg,image/png,image/webp,image/gif,image/avif,application/zip,.jpg,.jpeg,.png,.webp,.gif,.avif,.zip"
                           class="sr-only" onchange="pick(this.files[0])">
                </div>

                <!-- Progress -->
                <div id="prog" class="hidden">
                    <div class="mb-1.5 flex items-center justify-between">
                        <span id="prog-lbl" class="font-mono text-xs text-white/50">uploading…</span>
                        <span id="prog-pct" class="font-mono text-xs text-white/50">0%</span>
                    </div>
                    <div class="h-1 w-full bg-white/10"><div id="pbar" class="h-1 bg-white w-0"></div></div>
                    <p id="prog-spd" class="mt-1 font-mono text-xs text-white/30"></p>
                </div>

                <!-- Options -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45"><?= h($t['domain_label']) ?></span>
                        <select id="o-domain" class="h-11 w-full border border-white/10 bg-[#0b0b0c] px-3 font-mono text-sm text-white outline-none focus:border-white/35">
                            <?php foreach ($available_domains as $d): ?>
                            <option value="<?= h($d) ?>" <?= $d === $selected_domain ? 'selected' : '' ?>><?= h($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45"><?= h($t['alias_label']) ?></span>
                        <input id="o-alias" type="text" maxlength="32" placeholder="optional"
                               class="h-11 w-full border border-white/10 bg-[#0b0b0c] px-3 font-mono text-sm text-white outline-none placeholder:text-white/20 focus:border-white/35">
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45"><?= h($t['password_label']) ?></span>
                        <input id="o-pw" type="password" placeholder="optional" autocomplete="new-password"
                               class="h-11 w-full border border-white/10 bg-[#0b0b0c] px-3 text-sm text-white outline-none placeholder:text-white/20 focus:border-white/35">
                    </label>
                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45"><?= h($t['expires_label']) ?></span>
                        <input id="o-exp" type="datetime-local"
                               class="h-11 w-full border border-white/10 bg-[#0b0b0c] px-3 font-mono text-sm text-white outline-none [color-scheme:dark] focus:border-white/35">
                    </label>
                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45"><?= h($t['burn_label']) ?></span>
                        <input id="o-burn" type="number" min="1" max="1000000" inputmode="numeric" placeholder="<?= h($t['burn_placeholder']) ?>"
                               class="h-11 w-full border border-white/10 bg-[#0b0b0c] px-3 font-mono text-sm text-white outline-none placeholder:text-white/20 focus:border-white/35">
                    </label>
                </div>

                <button id="ubtn" onclick="go()"
                        class="flex h-12 items-center justify-between bg-[#f5f2ea] px-4 font-mono text-sm font-semibold text-[#0b0b0c] transition hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                    <span id="ubtn-lbl"><?= h($t['upload_submit']) ?></span><span>→</span>
                </button>
            </div>
        </div>
    </section>

    <footer class="mt-auto flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
        <span>0x79.one</span>
        <span>fftrclo.store · takeitdown.space · mydiscordiscool.store · fckdupfuture.com · <?= date('Y') ?></span>
    </footer>
</main>
<script>
const MAX = <?= $maxBytes ?>;
let file = null, t0 = 0;

function fmt(n){return n<1024?n+' B':n<1048576?(n/1024).toFixed(1)+' KB':(n/1048576).toFixed(2)+' MB'}

function pick(f){
    if(!f) return;
    const ok=['image/jpeg','image/png','image/webp','image/gif','image/avif','application/zip'];
    if(!ok.includes(f.type)&&!/\.(zip|jpe?g|png|webp|gif|avif)$/i.test(f.name)){showErr('<?= addslashes($t["err_file_type"]) ?>'); return;}
    if(f.size>MAX){showErr('<?= addslashes($t["err_file_size"]) ?> (max <?= h((string)$maxMb) ?> MB)'); return;}
    file=f;
    document.getElementById('dz-default').classList.add('hidden');
    document.getElementById('dz-preview').classList.remove('hidden');
    document.getElementById('fn').textContent=f.name;
    document.getElementById('fsz').textContent=fmt(f.size);
    const ip=document.getElementById('img-prev');
    if(f.type.startsWith('image/')){const r=new FileReader();r.onload=e=>{ip.src=e.target.result;ip.classList.remove('hidden')};r.readAsDataURL(f)}
    else{ip.classList.add('hidden')}
    hideMsg();
}

function go(){
    if(!file){document.getElementById('fi').click();return;}
    const btn=document.getElementById('ubtn');
    btn.disabled=true;
    document.getElementById('ubtn-lbl').textContent='uploading…';
    hideMsg();

    const fd=new FormData();
    fd.append('upload_file',file);
    fd.append('domain',document.getElementById('o-domain').value);
    fd.append('custom_code',document.getElementById('o-alias').value);
    fd.append('password',document.getElementById('o-pw').value);
    fd.append('expires_at',document.getElementById('o-exp').value);
    fd.append('max_clicks',document.getElementById('o-burn').value);

    document.getElementById('prog').classList.remove('hidden');
    t0=Date.now();

    const xhr=new XMLHttpRequest();
    xhr.upload.onprogress=e=>{
        if(!e.lengthComputable)return;
        const p=Math.round(e.loaded/e.total*100);
        document.getElementById('pbar').style.width=p+'%';
        document.getElementById('prog-pct').textContent=p+'%';
        const s=(Date.now()-t0)/1000;
        if(s>.5){const spd=e.loaded/s;document.getElementById('prog-spd').textContent=fmt(Math.round(spd))+'/s · ~'+Math.ceil((e.total-e.loaded)/spd)+'s left'}
    };
    xhr.upload.onload=()=>{document.getElementById('prog-lbl').textContent='processing…';document.getElementById('pbar').style.width='100%';document.getElementById('prog-pct').textContent='100%'};
    xhr.onload=()=>{
        document.getElementById('prog').classList.add('hidden');
        try{
            const d=JSON.parse(xhr.responseText);
            if(d.ok&&d.short_url){showOk(d.short_url)}
            else{showErr(d.error_text||d.error||'Upload failed.');resetBtn()}
        }catch(e){location.href='/upload'}
    };
    xhr.onerror=()=>{document.getElementById('prog').classList.add('hidden');showErr('Network error.');resetBtn()};
    xhr.setRequestHeader=xhr.setRequestHeader;
    xhr.open('POST','/upload');
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
    xhr.send(fd);
}

function showOk(url){
    document.getElementById('ucard').classList.add('hidden');
    const b=document.getElementById('js-result');
    b.classList.remove('hidden');
    const a=document.getElementById('js-url');
    a.href=url;a.textContent=url;
    document.getElementById('js-qr').src='/qr?d='+encodeURIComponent(url);
}
function showErr(m){
    document.getElementById('js-error').classList.remove('hidden');
    document.getElementById('js-errmsg').textContent=m;
}
function hideMsg(){
    document.getElementById('js-result').classList.add('hidden');
    document.getElementById('js-error').classList.add('hidden');
}
function doCopy(){
    navigator.clipboard.writeText(document.getElementById('js-url').textContent).then(()=>{
        const b=event.target;const o=b.textContent;b.textContent='<?= addslashes($t["copied"]) ?>';setTimeout(()=>b.textContent=o,1600);
    });
}
function doReset(){
    file=null;
    document.getElementById('js-result').classList.add('hidden');
    document.getElementById('js-error').classList.add('hidden');
    document.getElementById('ucard').classList.remove('hidden');
    document.getElementById('dz-default').classList.remove('hidden');
    document.getElementById('dz-preview').classList.add('hidden');
    document.getElementById('img-prev').src='';
    document.getElementById('fi').value='';
    resetBtn();
}
function resetBtn(){
    const btn=document.getElementById('ubtn');
    btn.disabled=false;
    document.getElementById('ubtn-lbl').textContent='<?= addslashes($t["upload_submit"]) ?>';
}
</script>
</body>
</html>
    <?php
    exit;
}


function renderPastePage($error = '', $paste_url = '', $raw_url = '') {
    global $lang, $t, $available_domains, $selected_domain;

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['paste_title']) ?> — 0x79</title>
    <meta name="description" content="<?= h($t['paste_lead']) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace','monospace']}}}};</script>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 py-5 sm:px-7 lg:px-8">
        <header class="flex items-center justify-between border-b border-white/10 pb-5">
            <a href="/" class="flex items-center gap-2"><img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover"><span class="font-mono text-sm tracking-tight text-white">0x79</span></a>
            <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
                <a href="/shorten" class="px-2.5 py-1.5 transition hover:text-white">shorten</a>
                <a href="/upload" class="px-2.5 py-1.5 transition hover:text-white">file</a>
                <a href="/paste" class="px-2.5 py-1.5 text-white transition hover:text-white"><?= h($t['paste']) ?></a>
                <span class="mx-1 h-4 w-px bg-white/10"></span>
                <?php renderLangSelect($lang, $supported_langs, $LANG_META); ?>
                <span class="mx-1 h-4 w-px bg-white/10"></span>
                <a href="/abuse" class="px-2.5 py-1.5 transition hover:text-white"><?= h($t['abuse']) ?></a>
                <?php if (isUserLoggedIn()): ?><a href="/account" class="px-2.5 py-1.5 transition hover:text-white">account</a><?php else: ?><a href="/login" class="px-2.5 py-1.5 transition hover:text-white">login</a><?php endif; ?>
            </nav>
        </header>

        <section class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[0.85fr_1.15fr] lg:py-16">
            <div>
                <p class="mb-5 font-mono text-xs uppercase tracking-[0.22em] text-white/35">tool 03</p>
                <h1 class="text-4xl font-semibold tracking-[-0.045em] text-white sm:text-5xl lg:text-6xl"><?= h($t['paste_title']) ?></h1>
                <p class="mt-5 max-w-md text-base leading-7 text-white/50 sm:text-lg"><?= h($t['paste_lead']) ?></p>
                <p class="mt-6 max-w-md border-l border-white/15 pl-4 font-mono text-xs leading-6 text-white/35"><?= h($t['paste_hint']) ?></p>
            </div>

            <div class="border border-white/10 bg-[#101011]">
                <div class="border-b border-white/10 p-5 sm:p-6">
                    <p class="font-mono text-xs uppercase tracking-[0.22em] text-white/35">create</p>
                    <h2 class="mt-1 text-lg font-medium tracking-tight text-white"><?= h($t['paste_submit']) ?></h2>
                </div>
                <form method="POST" action="/paste" class="grid gap-4 p-5 sm:p-6">
                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45"><?= h($t['paste_text_label']) ?></span>
                        <textarea name="paste_content" rows="12" maxlength="204800" required autofocus class="w-full resize-y border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm leading-6 text-white outline-none transition placeholder:text-white/25 focus:border-white/35" placeholder="<?= h($t['paste_placeholder']) ?>"></textarea>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2"><span class="font-mono text-xs text-white/45"><?= h($t['domain_label']) ?></span><select name="domain" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none focus:border-white/35"><?php foreach ($available_domains as $d): ?><option value="<?= h($d) ?>" <?= $d === $selected_domain ? 'selected' : '' ?>><?= h($d) ?></option><?php endforeach; ?></select></label>
                        <label class="grid gap-2"><span class="font-mono text-xs text-white/45"><?= h($t['alias_label']) ?></span><input name="custom_code" maxlength="32" pattern="[A-Za-z0-9]{1,32}" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none placeholder:text-white/25 focus:border-white/35" placeholder="optional"></label>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <label class="grid gap-2"><span class="font-mono text-xs text-white/45"><?= h($t['password_label']) ?></span><input type="password" name="password" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none placeholder:text-white/25 focus:border-white/35" placeholder="optional"></label>
                        <label class="grid gap-2.5"><span class="font-mono text-xs text-white/45"><?= h($t['expires_label']) ?></span><input type="datetime-local" name="expires_at" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none focus:border-white/35"></label>
                        <label class="grid gap-2.5"><span class="font-mono text-xs text-white/45"><?= h($t['paste_burn_label']) ?></span><input type="number" name="max_views" min="1" max="1000000" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none placeholder:text-white/25 focus:border-white/35" placeholder="<?= h($t['burn_placeholder']) ?>"></label>
                    </div>

                    <button type="submit" class="mt-1 flex items-center justify-between border border-white bg-[#f5f2ea] px-4 py-3.5 font-mono text-sm font-semibold text-[#0b0b0c] transition hover:bg-transparent hover:text-white"><span><?= h($t['paste_submit']) ?></span><span>→</span></button>
                </form>
            </div>
        </section>

        <?php if (!empty($error)): ?><div class="mb-5 border border-red-400/40 bg-red-400/5 p-4 font-mono text-sm text-red-200"><span class="block text-xs uppercase tracking-[0.22em] text-red-200/50"><?= h($t['err']) ?></span><span class="mt-2 block"><?= h($error) ?></span></div><?php endif; ?>
        <?php if (!empty($paste_url)): ?><div class="mb-5 border border-emerald-300/35 bg-emerald-300/5 p-4 font-mono text-sm text-emerald-100"><span class="block text-xs uppercase tracking-[0.22em] text-emerald-300/60"><?= h($t['paste_link']) ?></span><a class="mt-2 block underline underline-offset-4" href="<?= h($paste_url) ?>"><?= h($paste_url) ?></a><?php if (!empty($raw_url)): ?><a class="mt-2 block text-white/55 underline underline-offset-4" href="<?= h($raw_url) ?>"><?= h($raw_url) ?></a><?php endif; ?></div><?php endif; ?>

        <footer class="mt-8 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row"><span>0x79.one</span><span>url · file/image · paste · <?= date('Y') ?></span></footer>
    </main>
</body>
</html>
    <?php
    exit;
}

function renderPastePasswordForm($code, $error = '', $raw = false) {
    global $lang, $t;
    $action = $raw ? '/raw/' . rawurlencode($code) : '/' . rawurlencode($code);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html><html lang="<?= h($lang) ?>"><head><link rel="icon" href="/logo.png" type="image/jpeg"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>protected paste — 0x79</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;background:#0b0b0c;color:#f5f2ea;padding:24px}form{width:100%;max-width:420px;border:1px solid rgba(255,255,255,.18);padding:24px;display:grid;gap:14px}input,button{font:inherit;padding:12px;border:1px solid rgba(255,255,255,.25)}input{background:transparent;color:#f5f2ea}button{background:#f5f2ea;color:#0b0b0c;cursor:pointer}.err{color:#ff8a8a}</style></head><body><form method="POST" action="<?= h($action) ?>"><h1>paste passwort</h1><?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?><input type="hidden" name="code" value="<?= h($code) ?>"><input type="password" name="paste_password" placeholder="passwort" required autofocus><button type="submit">öffnen →</button></form></body></html>
    <?php
    exit;
}

function renderPasteView($row, $code) {
    global $t;

    if (isExpiredRow($row)) {
        http_response_code(410);
        header('Content-Type: text/plain; charset=utf-8');
        echo $t['err_expired'] ?? 'expired';
        exit;
    }
    if (isBurnedPaste($row)) {
        http_response_code(410);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'paste ist bereits verbrannt.';
        exit;
    }
    if (!pastePasswordOk($row, $code)) {
        $err = ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['pw']) || isset($_GET['password'])) ? ($t['err_password'] ?? 'wrong password') : '';
        renderPastePasswordForm($code, $err, false);
    }

    incrementPasteView($row);
    $content = (string)($row['content'] ?? '');
    $is_encrypted = strpos(trim($content), '0x79enc:') === 0;
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>paste <?= h($code) ?> — 0x79</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace','monospace']}}}};</script>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <main class="mx-auto flex min-h-screen w-full max-w-4xl flex-col px-5 py-5 sm:px-7 lg:px-8">
        <header class="flex items-center justify-between border-b border-white/10 pb-5 mb-8">
            <a href="/" class="flex items-center gap-2">
                <img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover">
                <span class="font-mono text-sm tracking-tight text-white">0x79</span>
            </a>
            <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
                <?php if (!$is_encrypted): ?>
                    <a href="/raw/<?= h(rawurlencode($code)) ?>" class="px-2.5 py-1.5 transition hover:text-white">raw</a>
                    <span class="mx-1 h-4 w-px bg-white/10"></span>
                <?php endif; ?>
                <a href="/" class="px-2.5 py-1.5 transition hover:text-white">home</a>
            </nav>
        </header>

        <section class="flex-1 py-4 flex flex-col gap-6">
            <?php if ($is_encrypted): ?>
                <div id="decrypt-container" class="w-full">
                    <div id="password-prompt" class="hidden border border-white/10 bg-[#101011] p-6 max-w-md mx-auto flex flex-col gap-6 rounded-lg relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-transparent via-cyan-500 to-transparent"></div>
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 rounded-lg">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold tracking-tight text-white font-sans">Zero-Knowledge Entschlüsselung</h2>
                                <p class="text-xs text-white/40 font-sans mt-0.5">Dieser Paste ist Ende-zu-Ende verschlüsselt.</p>
                            </div>
                        </div>

                        <p class="text-xs leading-relaxed text-white/50 font-sans">
                            Gib den geheimen Schlüssel oder das Passwort ein, um die Daten lokal in deinem Browser zu entschlüsseln. Der Server besitzt keinen Schlüssel.
                        </p>

                        <div id="decrypt-error" class="hidden border border-red-500/20 bg-red-500/5 px-4 py-3 text-xs text-[#ff8a8a] font-mono">
                            Schlüssel oder Passwort ungültig.
                        </div>

                        <div class="flex flex-col gap-3">
                            <input type="password" id="decrypt-key" class="h-12 w-full border border-white/10 bg-[#0b0b0c] px-4 text-sm text-white outline-none focus:border-white/35 transition placeholder:text-white/20 font-mono" placeholder="Passwort oder Key (Hex) eingeben" autofocus>
                            <button id="decrypt-btn" class="h-12 w-full bg-[#f5f2ea] text-[#0b0b0c] font-mono text-sm font-semibold transition hover:bg-white flex items-center justify-center gap-2">
                                <span>Entschlüsseln</span>
                                <span>→</span>
                            </button>
                        </div>
                    </div>
                    
                    <pre id="decrypted-content" class="hidden font-mono text-sm leading-relaxed p-6 bg-[#101011] border border-white/10 overflow-auto whitespace-pre-wrap word-break-all rounded-lg select-text"></pre>
                </div>

                <script>
                    const rawContent = <?= json_encode($content) ?>;

                    async function hexToBytes(hex) {
                        const bytes = new Uint8Array(hex.length / 2);
                        for (let i = 0; i < bytes.length; i++) {
                            bytes[i] = parseInt(hex.substring(i * 2, i * 2 + 2), 16);
                        }
                        return bytes;
                    }

                    async function decryptAesGcm(key, iv, ciphertext) {
                        try {
                            const decrypted = await window.crypto.subtle.decrypt(
                                { name: "AES-GCM", iv: iv },
                                key,
                                ciphertext
                            );
                            return new TextDecoder().decode(decrypted);
                        } catch(e) {
                            return null;
                        }
                    }

                    async function tryDecrypt(inputKeyOrPassword) {
                        if (!inputKeyOrPassword) return null;
                        const parts = rawContent.trim().split(':');
                        if (parts[0] !== '0x79enc') return null;
                        
                        const type = parts[1];
                        
                        if (type === 'key') {
                            const ivHex = parts[2];
                            const ciphertextHex = parts[3];
                            
                            try {
                                const cleanKeyHex = inputKeyOrPassword.trim().replace(/^#key=/, '').replace(/[^0-9a-fA-F]/g, '');
                                if (cleanKeyHex.length !== 64) return null;

                                const rawKey = await hexToBytes(cleanKeyHex);
                                const keyObj = await window.crypto.subtle.importKey(
                                    "raw",
                                    rawKey,
                                    "AES-GCM",
                                    true,
                                    ["decrypt"]
                                );
                                const iv = await hexToBytes(ivHex);
                                const ciphertext = await hexToBytes(ciphertextHex);
                                return await decryptAesGcm(keyObj, iv, ciphertext);
                            } catch(e) {
                                return null;
                            }
                        } else if (type === 'pwd') {
                            const saltHex = parts[2];
                            const ivHex = parts[3];
                            const ciphertextHex = parts[4];
                            
                            try {
                                const passwordKey = await window.crypto.subtle.importKey(
                                    "raw",
                                    new TextEncoder().encode(inputKeyOrPassword),
                                    "PBKDF2",
                                    false,
                                    ["deriveKey"]
                                );
                                const salt = await hexToBytes(saltHex);
                                const iv = await hexToBytes(ivHex);
                                const ciphertext = await hexToBytes(ciphertextHex);
                                
                                const derivedKey = await window.crypto.subtle.deriveKey(
                                    {
                                        name: "PBKDF2",
                                        salt: salt,
                                        iterations: 10000,
                                        hash: "SHA-256"
                                    },
                                    passwordKey,
                                    { name: "AES-GCM", length: 256 },
                                    true,
                                    ["decrypt"]
                                );
                                return await decryptAesGcm(derivedKey, iv, ciphertext);
                            } catch(e) {
                                return null;
                            }
                        }
                        return null;
                    }

                    async function initDecryption() {
                        const hash = window.location.hash;
                        let decrypted = null;
                        
                        if (hash.startsWith('#key=')) {
                            const keyHex = decodeURIComponent(hash.substring(5));
                            decrypted = await tryDecrypt(keyHex);
                        }
                        
                        if (decrypted !== null) {
                            showContent(decrypted);
                        } else {
                            document.getElementById('password-prompt').classList.remove('hidden');
                        }
                    }

                    function showContent(text) {
                        document.getElementById('password-prompt').classList.add('hidden');
                        const pre = document.getElementById('decrypted-content');
                        pre.textContent = text;
                        pre.classList.remove('hidden');
                    }

                    document.getElementById('decrypt-btn').addEventListener('click', async () => {
                        const input = document.getElementById('decrypt-key').value.trim();
                        const decrypted = await tryDecrypt(input);
                        if (decrypted !== null) {
                            showContent(decrypted);
                            document.getElementById('decrypt-error').classList.add('hidden');
                        } else {
                            document.getElementById('decrypt-error').classList.remove('hidden');
                        }
                    });

                    initDecryption();
                </script>
            <?php else: ?>
                <pre class="font-mono text-sm leading-relaxed p-6 bg-[#101011] border border-white/10 overflow-auto whitespace-pre-wrap word-break-all rounded-lg select-text"><?= h($content) ?></pre>
            <?php endif; ?>
        </section>

        <footer class="mt-8 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
            <span>0x79.one</span>
            <a href="/" class="hover:underline">home</a>
        </footer>
    </main>
</body>
</html>
    <?php
    exit;
}

function renderRawPaste($row, $code) {
    global $t;

    if (isExpiredRow($row) || isBurnedPaste($row)) {
        http_response_code(410);
        header('Content-Type: text/plain; charset=utf-8');
        echo isExpiredRow($row) ? ($t['err_expired'] ?? 'expired') : 'paste burned';
        exit;
    }
    if (!pastePasswordOk($row, $code)) {
        $err = ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['pw']) || isset($_GET['password'])) ? ($t['err_password'] ?? 'wrong password') : '';
        renderPastePasswordForm($code, $err, true);
    }

    $content = (string)($row['content'] ?? '');
    if (strpos(trim($content), '0x79enc:') === 0) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Dieser Paste ist clientseitig Ende-zu-Ende verschlüsselt. Raw-Ansicht ist nicht möglich, da der Server den Schlüssel nicht besitzt. Bitte entschlüssele den Paste über das Webinterface.";
        exit;
    }

    incrementPasteView($row);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo (string)($row['content'] ?? '');
    exit;
}

function renderAllPostsPage($posts) {
    global $lang, $t;

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($t['news_all_title']) ?> — 0x79</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace','monospace']}}}};</script>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col px-5 py-5 sm:px-7 lg:px-8">
        <header class="flex items-center justify-between border-b border-white/10 pb-5 mb-8">
            <a href="/" class="flex items-center gap-2"><img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover"><span class="font-mono text-sm tracking-tight text-white">0x79</span></a>
            <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
                <a href="/" class="px-2.5 py-1.5 transition hover:text-white">home</a>
                <a href="/shorten" class="px-2.5 py-1.5 transition hover:text-white">shorten</a>
                <a href="/upload" class="px-2.5 py-1.5 transition hover:text-white">file</a>
                <a href="/paste" class="px-2.5 py-1.5 transition hover:text-white">paste</a>
            </nav>
        </header>

        <section class="flex-1 py-4">
            <h1 class="text-3xl font-semibold tracking-[-0.04em] text-white sm:text-4xl mb-2"><?= h($t['news_all_title']) ?></h1>
            <p class="text-xs text-white/45 mb-8"><?= h($t['news_lead']) ?></p>

            <?php if (empty($posts)): ?>
                <div class="border border-white/5 bg-[#111113] p-12 text-center text-xs text-white/30 font-mono">
                    <?= h($t['news_no_posts']) ?>
                </div>
            <?php else: ?>
                <div class="grid gap-4">
                    <?php foreach ($posts as $post):
                        $postUrl = '/post/' . $post['id'];
                        $pubDate = (int)($post['pub_date'] ?? 0);
                        $dateStr = $pubDate ? date('d.m.Y H:i', $pubDate) : '';
                    ?>
                        <a href="<?= h($postUrl) ?>" class="group flex gap-4 border border-white/5 bg-[#111113] p-4 transition duration-300 hover:border-white/15 hover:bg-[#141417]">
                            <?php if (!empty($post['image'])): ?>
                                <img src="<?= h($post['image']) ?>" alt="Post Thumbnail" class="h-20 w-28 shrink-0 object-cover border border-white/15 rounded bg-black/40">
                            <?php endif; ?>
                            <div class="flex flex-col justify-between min-w-0">
                                <div>
                                    <span class="font-mono text-[9px] uppercase tracking-wider text-white/30"><?= h($dateStr) ?></span>
                                    <h2 class="mt-1 text-base font-semibold text-white transition duration-300 group-hover:text-white/80 truncate"><?= h($post['title'] ?? '') ?></h2>
                                    <p class="mt-1 text-xs leading-relaxed text-white/45 line-clamp-2">
                                        <?= h($post['description'] ?? '') ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <footer class="mt-12 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
            <span>0x79.one</span>
            <a href="/" class="hover:underline"><?= h($t['news_back_home']) ?></a>
        </footer>
    </main>
</body>
</html>
    <?php
    exit;
}

function renderPostPage($post) {
    global $lang, $t;

    if (!$post) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="<?= h($lang) ?>">
        <head>
            <link rel="icon" href="/logo.png" type="image/jpeg">
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 — Not Found</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] flex items-center justify-center font-mono">
            <div class="text-center p-6 border border-white/10 bg-[#101011] max-w-sm w-full">
                <h1 class="text-2xl font-bold mb-2">404</h1>
                <p class="text-white/50 mb-4">Beitrag nicht gefunden.</p>
                <a href="/" class="underline text-white hover:text-white/70">Zur Startseite</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    $title = $post['title'] ?? '';
    $description = $post['description'] ?? '';
    $image = $post['image'] ?? null;
    $pubDate = (int)($post['pub_date'] ?? 0);
    $dateStr = $pubDate ? date('d.m.Y H:i', $pubDate) : '';

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head><link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> — 0x79</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace','monospace']}}}};</script>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col px-5 py-5 sm:px-7 lg:px-8">
        <header class="flex items-center justify-between border-b border-white/10 pb-5 mb-8">
            <a href="/" class="flex items-center gap-2"><img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover"><span class="font-mono text-sm tracking-tight text-white">0x79</span></a>
            <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
                <a href="/" class="px-2.5 py-1.5 transition hover:text-white">home</a>
                <a href="/shorten" class="px-2.5 py-1.5 transition hover:text-white">shorten</a>
                <a href="/upload" class="px-2.5 py-1.5 transition hover:text-white">file</a>
                <a href="/paste" class="px-2.5 py-1.5 transition hover:text-white">paste</a>
            </nav>
        </header>

        <article class="flex-1 py-4">
            <p class="font-mono text-xs text-white/35 mb-2"><?= h($dateStr) ?></p>
            <h1 class="text-3xl font-semibold tracking-[-0.04em] text-white sm:text-4xl mb-6"><?= h($title) ?></h1>

            <?php if ($image): ?>
                <div class="mb-8 overflow-hidden border border-white/10 bg-[#101011] rounded-lg">
                    <img src="<?= h($image) ?>" alt="<?= h($title) ?>" class="w-full h-auto max-h-[400px] object-cover">
                </div>
            <?php endif; ?>

            <div class="text-white/80 leading-7 text-base whitespace-pre-wrap font-sans">
                <?= nl2br(h($description)) ?>
            </div>
        </article>

        <footer class="mt-12 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
            <span>0x79.one</span>
            <a href="/" class="hover:underline">home</a>
        </footer>
    </main>
</body>
</html>
    <?php
    exit;
}

function renderMetadataStripperPage() {
    global $lang, $t;
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXIF Stripper — 0x79</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace','monospace']}}}};</script>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 py-5 sm:px-7 lg:px-8">
        <header class="flex items-center justify-between border-b border-white/10 pb-5">
            <a href="/" class="flex items-center gap-2">
                <img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover">
                <span class="font-mono text-sm tracking-tight text-white">0x79</span>
            </a>
            <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
                <a href="/shorten" class="px-2.5 py-1.5 transition hover:text-white">shorten</a>
                <a href="/upload" class="px-2.5 py-1.5 transition hover:text-white">file</a>
                <a href="/paste" class="px-2.5 py-1.5 transition hover:text-white">paste</a>
                <a href="/metadata" class="px-2.5 py-1.5 text-white transition hover:text-white">exif-stripper</a>
                <span class="mx-1 h-4 w-px bg-white/10"></span>
                <a href="/abuse" class="px-2.5 py-1.5 transition hover:text-white"><?= h($t['abuse']) ?></a>
            </nav>
        </header>

        <section class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[0.85fr_1.15fr] lg:py-16">
            <div>
                <p class="mb-5 font-mono text-xs uppercase tracking-[0.22em] text-white/35">tool 05</p>
                <h1 class="text-4xl font-semibold tracking-[-0.045em] text-white sm:text-5xl lg:text-6xl">EXIF-Entferner</h1>
                <p class="mt-5 max-w-md text-base leading-7 text-white/50 sm:text-lg">
                    Entferne unsichtbare Metadaten wie GPS-Koordinaten, Kameramodell, Zeitstempel und Thumbnail clientseitig direkt im Browser.
                </p>
                <p class="mt-6 max-w-md border-l border-white/15 pl-4 font-mono text-xs leading-6 text-white/35">
                    Deine Bilder werden nicht an den Server übertragen. Die Bereinigung findet zu 100% lokal per HTML5 Canvas statt.
                </p>
            </div>

            <div class="border border-white/10 bg-[#101011] p-6 sm:p-8 flex flex-col gap-6">
                <!-- Dropzone -->
                <div id="dropzone" class="border border-dashed border-white/20 hover:border-white/40 bg-black/20 p-10 text-center cursor-pointer transition flex flex-col items-center justify-center gap-3">
                    <svg class="w-8 h-8 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 00-2 2z"></path></svg>
                    <div>
                        <p class="font-mono text-sm text-white">Bild auswählen oder hierhin ziehen</p>
                        <p class="text-xs text-white/40 mt-1">Unterstützt JPG, PNG, WEBP</p>
                    </div>
                    <input type="file" id="fileInput" accept="image/jpeg,image/png,image/webp" class="hidden">
                </div>

                <!-- Preview & Results Container (Hidden initially) -->
                <div id="results" class="hidden flex flex-col gap-6">
                    <div class="grid grid-cols-2 gap-4 border border-white/5 bg-black/10 p-4 font-mono text-xs">
                        <div>
                            <span class="text-white/40 block">Original-Größe:</span>
                            <span id="originalSize" class="text-white font-medium">-</span>
                        </div>
                        <div>
                            <span class="text-white/40 block">Bereinigte Größe:</span>
                            <span id="cleanedSize" class="text-white font-medium">-</span>
                        </div>
                    </div>

                    <!-- Comparison Image -->
                    <div class="relative max-h-60 overflow-hidden border border-white/15 bg-black/40 rounded flex items-center justify-center">
                        <img id="imagePreview" src="" class="max-h-60 max-w-full object-contain">
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <a id="downloadBtn" href="#" download="cleaned_image.jpg" class="flex h-12 items-center justify-center bg-[#f5f2ea] text-[#0b0b0c] font-mono text-sm font-semibold transition hover:bg-white">
                            Herunterladen
                        </a>
                        <button id="shareBtn" class="flex h-12 items-center justify-center border border-white/20 text-white font-mono text-sm font-semibold transition hover:border-white/50 hover:bg-white/5">
                            Als Link teilen
                        </button>
                    </div>
                </div>

                <!-- Hidden Form for uploading cleaned image -->
                <form id="uploadForm" method="POST" action="/upload" enctype="multipart/form-data" class="hidden">
                    <input type="file" name="file" id="hiddenFileInput">
                </form>
            </div>
        </section>

        <footer class="mt-8 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
            <span>0x79.one</span>
            <span>url · file/image · paste · exif · <?= date('Y') ?></span>
        </footer>
    </main>

    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');
        const results = document.getElementById('results');
        const originalSizeSpan = document.getElementById('originalSize');
        const cleanedSizeSpan = document.getElementById('cleanedSize');
        const imagePreview = document.getElementById('imagePreview');
        const downloadBtn = document.getElementById('downloadBtn');
        const shareBtn = document.getElementById('shareBtn');
        const hiddenFileInput = document.getElementById('hiddenFileInput');
        const uploadForm = document.getElementById('uploadForm');

        let cleanedBlob = null;
        let originalFileName = '';

        dropzone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', handleFileSelect);

        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('border-white/50', 'bg-white/5');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('border-white/50', 'bg-white/5');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-white/50', 'bg-white/5');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (!file) return;

            originalFileName = file.name;
            originalSizeSpan.textContent = formatBytes(file.size);

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    // Create canvas
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);

                    // Export cleaned image (canvas.toBlob automatically strips all EXIF metadata)
                    const mimeType = file.type || 'image/jpeg';
                    canvas.toBlob((blob) => {
                        cleanedBlob = blob;
                        cleanedSizeSpan.textContent = formatBytes(blob.size);
                        
                        const objectURL = URL.createObjectURL(blob);
                        imagePreview.src = objectURL;
                        downloadBtn.href = objectURL;
                        
                        // Set standard cleaned file name
                        const ext = mimeType.split('/')[1] || 'jpg';
                        downloadBtn.download = `cleaned_${originalFileName.replace(/\.[^/.]+$/, "")}.${ext}`;

                        // Show results
                        results.classList.remove('hidden');
                    }, mimeType, 0.92);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        shareBtn.addEventListener('click', () => {
            if (!cleanedBlob) return;
            
            // Re-upload cleaned file using our hidden form
            const mimeType = fileInput.files[0].type || 'image/jpeg';
            const ext = mimeType.split('/')[1] || 'jpg';
            const cleanFile = new File([cleanedBlob], `cleaned_${originalFileName.replace(/\.[^/.]+$/, "")}.${ext}`, { type: mimeType });
            
            const dt = new DataTransfer();
            dt.items.add(cleanFile);
            hiddenFileInput.files = dt.files;
            
            // Submit the form
            uploadForm.submit();
        });
    </script>
</body>
</html>
    <?php
    exit;
}

function renderSecureSharePage($error = '', $pasteUrl = '') {
    global $lang, $t, $available_domains, $selected_domain;
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <link rel="icon" href="/logo.png" type="image/jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zero-Knowledge Paste — 0x79</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace','monospace']}}}};</script>
</head>
<body class="min-h-screen bg-[#0b0b0c] text-[#f5f2ea] antialiased selection:bg-[#f5f2ea] selection:text-[#0b0b0c]">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 py-5 sm:px-7 lg:px-8">
        <header class="flex items-center justify-between border-b border-white/10 pb-5">
            <a href="/" class="flex items-center gap-2"><img src="/logo.png" alt="Logo" class="h-10 w-10 rounded-lg border border-white/10 object-cover"><span class="font-mono text-sm tracking-tight text-white">0x79</span></a>
            <nav class="flex items-center gap-1 font-mono text-xs text-white/45">
                <a href="/shorten" class="px-2.5 py-1.5 transition hover:text-white">shorten</a>
                <a href="/upload" class="px-2.5 py-1.5 transition hover:text-white">file</a>
                <a href="/paste" class="px-2.5 py-1.5 transition hover:text-white">paste</a>
                <a href="/secure-share" class="px-2.5 py-1.5 text-white transition hover:text-white">secure-share</a>
                <span class="mx-1 h-4 w-px bg-white/10"></span>
                <a href="/abuse" class="px-2.5 py-1.5 transition hover:text-white"><?= h($t['abuse']) ?></a>
            </nav>
        </header>

        <section class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[0.85fr_1.15fr] lg:py-16">
            <div>
                <p class="mb-5 font-mono text-xs uppercase tracking-[0.22em] text-white/35">tool 06</p>
                <h1 class="text-4xl font-semibold tracking-[-0.045em] text-white sm:text-5xl lg:text-6xl">Zero-Knowledge Paste</h1>
                <p class="mt-5 max-w-md text-base leading-7 text-white/50 sm:text-lg">
                    Verschlüssele deine Snippets und Texte clientseitig mit AES-256-GCM. Der Server sieht deine Daten nur in verschlüsselter Form.
                </p>
                <p class="mt-6 max-w-md border-l border-white/15 pl-4 font-mono text-xs leading-6 text-white/35">
                    Entweder wird ein zufälliger Schlüssel im URL-Hash generiert (den der Server nie erhält) oder du wählst ein eigenes Passwort zur Ableitung des Schlüssels.
                </p>
            </div>

            <div class="border border-white/10 bg-[#101011]">
                <div class="border-b border-white/10 p-5 sm:p-6">
                    <p class="font-mono text-xs uppercase tracking-[0.22em] text-white/35">create secure</p>
                    <h2 class="mt-1 text-lg font-medium tracking-tight text-white">Verschlüsselten Paste erstellen</h2>
                </div>
                <form id="securePasteForm" class="grid gap-4 p-5 sm:p-6">
                    <label class="grid gap-2">
                        <span class="font-mono text-xs text-white/45">Inhalt</span>
                        <textarea id="paste_content" rows="10" required autofocus class="w-full resize-y border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm leading-6 text-white outline-none transition placeholder:text-white/25 focus:border-white/35" placeholder="Vertraulichen Text hier einfügen..."></textarea>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2">
                            <span class="font-mono text-xs text-white/45">Verschlüsselungsart</span>
                            <select id="enc_type" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none focus:border-white/35">
                                <option value="link">Zufälliger Link-Schlüssel (Empfohlen)</option>
                                <option value="password">Eigenes Passwort festlegen</option>
                            </select>
                        </label>
                        <label id="pwd_label" class="grid gap-2 hidden">
                            <span class="font-mono text-xs text-white/45">Passwort</span>
                            <input type="password" id="custom_password" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none placeholder:text-white/25 focus:border-white/35" placeholder="Eigenes Passwort eingeben">
                        </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2"><span class="font-mono text-xs text-white/45"><?= h($t['domain_label']) ?></span><select id="domain" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none focus:border-white/35"><?php foreach ($available_domains as $d): ?><option value="<?= h($d) ?>" <?= $d === $selected_domain ? 'selected' : '' ?>><?= h($d) ?></option><?php endforeach; ?></select></label>
                        <label class="grid gap-2"><span class="font-mono text-xs text-white/45"><?= h($t['alias_label']) ?></span><input id="custom_code" maxlength="32" pattern="[A-Za-z0-9]{1,32}" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none placeholder:text-white/25 focus:border-white/35" placeholder="optional"></label>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="grid gap-2.5"><span class="font-mono text-xs text-white/45"><?= h($t['expires_label']) ?></span><input type="datetime-local" id="expires_at" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none focus:border-white/35"></label>
                        <label class="grid gap-2.5"><span class="font-mono text-xs text-white/45"><?= h($t['paste_burn_label']) ?></span><input type="number" id="max_views" min="1" max="1000000" class="w-full border border-white/10 bg-[#0b0b0c] px-3.5 py-3 font-mono text-sm text-white outline-none placeholder:text-white/25 focus:border-white/35" placeholder="<?= h($t['burn_placeholder']) ?>"></label>
                    </div>

                    <button type="submit" id="submitBtn" class="mt-1 flex items-center justify-between border border-white bg-[#f5f2ea] px-4 py-3.5 font-mono text-sm font-semibold text-[#0b0b0c] transition hover:bg-transparent hover:text-white">
                        <span>Sicher erstellen</span>
                        <span id="btnArrow">→</span>
                    </button>
                </form>
            </div>
        </section>

        <!-- Status Alerts -->
        <div id="errorAlert" class="hidden mb-5 border border-red-400/40 bg-red-400/5 p-4 font-mono text-sm text-red-200">
            <span class="block text-xs uppercase tracking-[0.22em] text-red-200/50"><?= h($t['err']) ?></span>
            <span id="errorMsg" class="mt-2 block"></span>
        </div>

        <div id="successAlert" class="hidden mb-5 border border-emerald-300/35 bg-emerald-300/5 p-4 font-mono text-sm text-emerald-100">
            <span class="block text-xs uppercase tracking-[0.22em] text-emerald-300/60">Sicherer Paste-Link bereit</span>
            <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center">
                <a id="secureLink" class="min-w-0 flex-1 truncate border border-white/10 bg-black/20 px-3.5 py-3 font-mono text-sm text-white underline decoration-white/20 underline-offset-4" href="" target="_blank" rel="noopener"></a>
                <button type="button" class="copy h-11 border border-white/15 px-4 font-mono text-sm text-white transition hover:border-white/35" onclick="copyResultLink()">
                    Kopieren
                </button>
            </div>
        </div>

        <footer class="mt-8 flex flex-col justify-between gap-2 border-t border-white/10 py-5 font-mono text-xs text-white/30 sm:flex-row">
            <span>0x79.one</span>
            <span>url · file/image · paste · secure-share · <?= date('Y') ?></span>
        </footer>
    </main>

    <script>
        const encTypeSelect = document.getElementById('enc_type');
        const pwdLabel = document.getElementById('pwd_label');
        const customPassword = document.getElementById('custom_password');
        const form = document.getElementById('securePasteForm');
        const submitBtn = document.getElementById('submitBtn');
        
        const errorAlert = document.getElementById('errorAlert');
        const errorMsg = document.getElementById('errorMsg');
        const successAlert = document.getElementById('successAlert');
        const secureLink = document.getElementById('secureLink');

        encTypeSelect.addEventListener('change', () => {
            if (encTypeSelect.value === 'password') {
                pwdLabel.classList.remove('hidden');
                customPassword.required = true;
            } else {
                pwdLabel.classList.add('hidden');
                customPassword.required = false;
            }
        });

        function bytesToHex(bytes) {
            return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
        }

        async function encryptAesGcm(key, plaintext) {
            const iv = window.crypto.getRandomValues(new Uint8Array(12));
            const encoder = new TextEncoder();
            const ciphertext = await window.crypto.subtle.encrypt(
                { name: "AES-GCM", iv: iv },
                key,
                encoder.encode(plaintext)
            );
            return {
                ivHex: bytesToHex(iv),
                ciphertextHex: bytesToHex(new Uint8Array(ciphertext))
            };
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Hide previous status
            errorAlert.classList.add('hidden');
            successAlert.classList.add('hidden');
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Snippet wird verschlüsselt...';

            const plaintext = document.getElementById('paste_content').value;
            const encType = encTypeSelect.value;
            
            let payload = '';
            let keyHex = '';

            try {
                if (encType === 'link') {
                    // Generate random AES key
                    const key = await window.crypto.subtle.generateKey(
                        { name: "AES-GCM", length: 256 },
                        true,
                        ["encrypt", "decrypt"]
                    );
                    const exportedKey = await window.crypto.subtle.exportKey("raw", key);
                    keyHex = bytesToHex(new Uint8Array(exportedKey));
                    
                    const { ivHex, ciphertextHex } = await encryptAesGcm(key, plaintext);
                    payload = `0x79enc:key:${ivHex}:${ciphertextHex}`;
                } else {
                    // Derive key from custom password
                    const pwd = customPassword.value;
                    const passwordKey = await window.crypto.subtle.importKey(
                        "raw",
                        new TextEncoder().encode(pwd),
                        "PBKDF2",
                        false,
                        ["deriveKey"]
                    );
                    
                    const salt = window.crypto.getRandomValues(new Uint8Array(16));
                    const saltHex = bytesToHex(salt);
                    
                    const derivedKey = await window.crypto.subtle.deriveKey(
                        {
                            name: "PBKDF2",
                            salt: salt,
                            iterations: 10000,
                            hash: "SHA-256"
                        },
                        passwordKey,
                        { name: "AES-GCM", length: 256 },
                        true,
                        ["encrypt", "decrypt"]
                    );
                    
                    const { ivHex, ciphertextHex } = await encryptAesGcm(derivedKey, plaintext);
                    payload = `0x79enc:pwd:${saltHex}:${ivHex}:${ciphertextHex}`;
                }

                // Prepare AJAX form data
                const formData = new FormData();
                formData.append('paste_content', payload);
                formData.append('domain', document.getElementById('domain').value);
                formData.append('custom_code', document.getElementById('custom_code').value);
                formData.append('expires_at', document.getElementById('expires_at').value);
                formData.append('max_views', document.getElementById('max_views').value);

                const response = await fetch('/secure-share', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    let finalUrl = data.short_url;
                    if (encType === 'link') {
                        finalUrl += `#key=${keyHex}`;
                    }
                    
                    secureLink.href = finalUrl;
                    secureLink.textContent = finalUrl;
                    successAlert.classList.remove('hidden');
                } else {
                    errorMsg.textContent = data.error || 'Fehler beim Speichern.';
                    errorAlert.classList.remove('hidden');
                }
            } catch(err) {
                console.error(err);
                errorMsg.textContent = 'Verschlüsselungsfehler: ' + err.message;
                errorAlert.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sicher erstellen';
            }
        });

        function copyResultLink() {
            navigator.clipboard.writeText(secureLink.href).then(() => {
                alert('Link kopiert!');
            });
        }
    </script>
</body>
</html>
    <?php
    exit;
}


