<?php
declare(strict_types=1);

// Kleine Helfer: sicheres Escapen für HTML-Kontext
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


function formatDateTime($value) {
    $value = trim((string)$value);
    if ($value === '') return '';

    try {
        $dt = new DateTime($value);
        $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $dt->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $value;
    }
}

// ---------------------------------------------------------
// .ENV LOADER
// ---------------------------------------------------------
function loadEnv($path) {
    if (!file_exists($path)) return false;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;

        list($name, $value) = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value);

        $value = trim($value, "\"'");

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
        }
    }

    return true;
}

// ---------------------------------------------------------
// SPRACH-ERKENNUNG
// ---------------------------------------------------------
function detectLang($supported = ['de', 'en']) {
    if (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        setcookie('lang', $_GET['lang'], time() + 60 * 60 * 24 * 365, '/');
        return $_GET['lang'];
    }

    if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $supported, true)) {
        return $_COOKIE['lang'];
    }

    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $primary = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
        if (in_array($primary, $supported, true)) return $primary;
    }

    return 'en';
}

$supported_langs = ['de', 'en'];
$lang = detectLang($supported_langs);

function loadTranslations($supported) {
    $loaded = [];
    foreach ($supported as $code) {
        $path = __DIR__ . '/lang/' . $code . '.json';
        if (!is_file($path)) continue;
        $data = json_decode((string)file_get_contents($path), true);
        if (is_array($data)) {
            $loaded[$code] = $data;
        }
    }
    return $loaded;
}

$T = loadTranslations($supported_langs);
function renderLangSelect($lang, $supported, $meta) {
    $active = $meta[$lang] ?? ['flag' => '🏳️'];
    ?>
    <div class="relative inline-block text-left" id="custom-lang-select-container">
        <button type="button" id="custom-lang-btn" class="flex h-8 items-center gap-2 border border-white/10 bg-[#0b0b0c] px-3 font-mono text-xs text-white/70 hover:text-white hover:border-white/20 transition-all duration-200 focus:outline-none">
            <span class="flex items-center gap-1.5"><?= h(strtoupper($lang) . ' ' . $active['flag']) ?></span>
            <span class="chevron text-[8px] text-white/40 transition-transform duration-200">▼</span>
        </button>
        <div id="custom-lang-dropdown" class="invisible opacity-0 absolute right-0 mt-1.5 w-28 border border-white/10 bg-[#0b0b0c] shadow-2xl transition-all duration-200 origin-top-right scale-95 z-50">
            <div class="py-1 font-mono text-xs">
                <?php foreach ($supported as $code): ?>
                    <?php 
                    $info = $meta[$code] ?? ['label' => strtoupper($code), 'flag' => '🏳️']; 
                    $activeClass = $lang === $code ? 'bg-white/5 text-white font-medium' : 'text-white/60 hover:text-white hover:bg-white/5';
                    ?>
                    <a href="?lang=<?= h($code) ?>" class="flex items-center justify-between px-3 py-2 transition-colors duration-150 <?= $activeClass ?>">
                        <span><?= h(strtoupper($code) . ' ' . $info['flag']) ?></span>
                        <?php if ($lang === $code): ?>
                            <span class="text-[9px] text-white/40">✓</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script>
    (function() {
        const btn = document.getElementById('custom-lang-btn');
        const dropdown = document.getElementById('custom-lang-dropdown');
        if (!btn || !dropdown) return;
        
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = !dropdown.classList.contains('invisible');
            if (isOpen) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && e.target !== btn) {
                closeDropdown();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDropdown();
            }
        });
        
        function openDropdown() {
            dropdown.classList.remove('invisible', 'opacity-0', 'scale-95');
            dropdown.classList.add('opacity-100', 'scale-100');
            const chevron = btn.querySelector('.chevron');
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        }
        
        function closeDropdown() {
            dropdown.classList.add('invisible', 'opacity-0', 'scale-95');
            dropdown.classList.remove('opacity-100', 'scale-100');
            const chevron = btn.querySelector('.chevron');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    })();
    </script>
    <?php
}

// ---------------------------------------------------------
// API HELPERS + ROUTES
// ---------------------------------------------------------
function cleanHost($host) {
    $host = strtolower(trim((string)$host));
    $host = preg_replace('/[^a-z0-9.-]/', '', $host);
    return $host ?: '0x79.one';
}

function jsonResponse($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function apiReadInput() {
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    return $_POST;
}

function clampInt($value, $min, $max, $default) {
    if ($value === null || $value === '') return $default;
    $n = (int)$value;
    return max($min, min($max, $n));
}

function boolParam($value, $default = false) {
    if ($value === null || $value === '') return $default;
    if (is_bool($value)) return $value;
    $v = strtolower(trim((string)$value));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}


function builtInLinkSchemes() {
    return [
        'http', 'https',
        'ftp', 'sftp', 'ftps', 'file',
        'mailto', 'tel', 'sms',
        'ssh', 'git', 'magnet',
        'data', 'blob',
        'ws', 'wss',
        'irc', 'xmpp',
        'ipfs', 'ipns',
        'bitcoin', 'ethereum', 'geo',
        'intent', 'market', 'itms-apps',
        'steam', 'discord', 'tg', 'whatsapp',
    ];
}

function isValidConfigurableScheme($scheme) {
    $scheme = strtolower(trim((string)$scheme));

    if ($scheme === '' || $scheme === 'javascript') {
        return false;
    }

    // RFC-style scheme names: start with a letter, then letters/digits/+.-
    // Limit keeps the admin config tidy and avoids weird oversized input.
    return (bool)preg_match('/^[a-z][a-z0-9+.-]{0,63}$/', $scheme);
}

function protocolConfigPath() {
    $envPath = getenv('ALLOWED_PROTOCOLS_FILE');
    if ($envPath) return $envPath;
    return __DIR__ . '/allowed_protocols.json';
}

function readProtocolConfig() {
    $path = protocolConfigPath();

    if (!is_file($path)) {
        return [];
    }

    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function normalizeSchemeList($schemes) {
    $out = [];

    if (!is_array($schemes)) return $out;

    foreach ($schemes as $scheme) {
        $scheme = strtolower(trim((string)$scheme));
        $scheme = rtrim($scheme, ':');

        if (isValidConfigurableScheme($scheme) && !in_array($scheme, $out, true)) {
            $out[] = $scheme;
        }
    }

    sort($out, SORT_NATURAL);
    return $out;
}

function customLinkSchemes() {
    $data = readProtocolConfig();
    return normalizeSchemeList($data['custom_schemes'] ?? []);
}

function allConfigurableLinkSchemes() {
    $all = array_values(array_unique(array_merge(builtInLinkSchemes(), customLinkSchemes())));
    sort($all, SORT_NATURAL);
    return $all;
}

function defaultAllowedLinkSchemes() {
    return builtInLinkSchemes();
}

function normalizeAllowedLinkSchemes($schemes) {
    $known = allConfigurableLinkSchemes();
    $out = [];

    if (!is_array($schemes)) return defaultAllowedLinkSchemes();

    foreach ($schemes as $scheme) {
        $scheme = strtolower(trim((string)$scheme));
        $scheme = rtrim($scheme, ':');

        if (in_array($scheme, $known, true) && isValidConfigurableScheme($scheme) && !in_array($scheme, $out, true)) {
            $out[] = $scheme;
        }
    }

    // Keep at least http/https enabled so the shortener cannot be bricked by accident.
    if (empty($out)) {
        return ['http', 'https'];
    }

    sort($out, SORT_NATURAL);
    return $out;
}

function allowedLinkSchemes() {
    static $cached = null;
    if ($cached !== null) return $cached;

    $data = readProtocolConfig();
    if (isset($data['allowed_schemes'])) {
        $cached = normalizeAllowedLinkSchemes($data['allowed_schemes']);
        return $cached;
    }

    $cached = defaultAllowedLinkSchemes();
    return $cached;
}

function saveProtocolConfig($allowedSchemes, $customSchemes) {
    $custom = normalizeSchemeList($customSchemes);
    $builtIn = builtInLinkSchemes();

    // Custom list only stores non-built-in schemes. Built-ins remain available automatically.
    $custom = array_values(array_filter($custom, function ($scheme) use ($builtIn) {
        return !in_array($scheme, $builtIn, true);
    }));

    $allKnown = array_values(array_unique(array_merge($builtIn, $custom)));
    sort($allKnown, SORT_NATURAL);

    $allowed = [];
    foreach ((array)$allowedSchemes as $scheme) {
        $scheme = strtolower(trim((string)$scheme));
        $scheme = rtrim($scheme, ':');
        if (in_array($scheme, $allKnown, true) && isValidConfigurableScheme($scheme) && !in_array($scheme, $allowed, true)) {
            $allowed[] = $scheme;
        }
    }

    if (empty($allowed)) {
        $allowed = ['http', 'https'];
    }

    sort($allowed, SORT_NATURAL);

    $path = protocolConfigPath();
    $dir = dirname($path);

    if (!is_dir($dir) || !is_writable($dir)) {
        return [false, 'config directory is not writable: ' . $dir];
    }

    $payload = json_encode([
        'allowed_schemes' => $allowed,
        'custom_schemes' => $custom,
        'updated_at' => gmdate('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (@file_put_contents($path, $payload . "\n", LOCK_EX) === false) {
        return [false, 'could not write protocol config'];
    }

    @chmod($path, 0600);
    return [true, null];
}

function saveAllowedLinkSchemes($schemes) {
    return saveProtocolConfig($schemes, customLinkSchemes());
}

function isAllowedShortenerTarget($target) {
    $target = trim((string)$target);

    if ($target === '' || strlen($target) > 4096) {
        return false;
    }

    // Prevent response splitting / header injection.
    if (preg_match('/[\r\n\0]/', $target)) {
        return false;
    }

    // Explicitly keep script URLs blocked.
    if (preg_match('/^\s*javascript\s*:/i', $target)) {
        return false;
    }

    if (!preg_match('/^([a-z][a-z0-9+.-]*):/i', $target, $m)) {
        return false;
    }

    $scheme = strtolower($m[1]);

    if (!in_array($scheme, allowedLinkSchemes(), true)) {
        return false;
    }

    // For web/file-transfer/websocket links, require normal URL validation.
    if (in_array($scheme, ['http', 'https', 'ftp', 'ftps', 'sftp', 'ws', 'wss'], true)) {
        return (bool)filter_var($target, FILTER_VALIDATE_URL);
    }

    // For app/URI schemes like mailto:, tel:, magnet:, tg:// etc. keep it strict but flexible.
    return (bool)preg_match('/^[a-z][a-z0-9+.-]*:[^\s<>"\']+$/i', $target);
}

function isPublicHttpUrl($url) {
    $url = trim((string)$url);

    if ($url === '' || strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return [false, 'invalid_url'];
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return [false, 'invalid_scheme'];
    }

    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
        return [false, 'blocked_host'];
    }

    $checkIp = function ($ip) {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    };

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return $checkIp($host) ? [true, null] : [false, 'blocked_private_ip'];
    }

    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (is_array($records) && count($records) > 0) {
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip && !$checkIp($ip)) {
                return [false, 'blocked_private_ip'];
            }
        }
    }

    return [true, null];
}

function streamScreenshotResponse($input) {
    global $screenshotone_access_key;

    requireAdminAuth();

    if (!$screenshotone_access_key) {
        jsonResponse(['ok' => false, 'error' => 'missing_screenshot_api_key'], 500);
    }

    $target = trim((string)($input['url'] ?? ''));
    [$valid, $validationError] = isPublicHttpUrl($target);

    if (!$valid) {
        jsonResponse(['ok' => false, 'error' => $validationError], 400);
    }

    $format = strtolower(trim((string)($input['format'] ?? 'png')));
    if (!in_array($format, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        jsonResponse(['ok' => false, 'error' => 'invalid_format'], 400);
    }
    if ($format === 'jpeg') $format = 'jpg';

    $width = clampInt($input['width'] ?? $input['viewport_width'] ?? null, 320, 3840, 1440);
    $height = clampInt($input['height'] ?? $input['viewport_height'] ?? null, 240, 2160, 900);
    $fullPage = boolParam($input['full_page'] ?? $input['fullPage'] ?? null, false);
    $blockAds = boolParam($input['block_ads'] ?? null, true);
    $blockCookieBanners = boolParam($input['block_cookie_banners'] ?? null, true);
    $delay = clampInt($input['delay'] ?? null, 0, 10, 0);

    $query = [
        'access_key' => $screenshotone_access_key,
        'url' => $target,
        'format' => $format,
        'viewport_width' => $width,
        'viewport_height' => $height,
        'device_scale_factor' => 1,
        'full_page' => $fullPage ? 'true' : 'false',
        'block_ads' => $blockAds ? 'true' : 'false',
        'block_cookie_banners' => $blockCookieBanners ? 'true' : 'false',
        'block_trackers' => 'true',
        'timeout' => 60,
    ];

    if ($delay > 0) {
        $query['delay'] = $delay;
    }

    $providerUrl = 'https://api.screenshotone.com/take?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($providerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 75);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HEADER, false);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($curlError) {
        jsonResponse(['ok' => false, 'error' => 'screenshot_curl_error', 'detail' => $curlError], 502);
    }

    if ($http < 200 || $http >= 300 || $body === false || $body === '') {
        $detail = is_string($body) ? substr($body, 0, 500) : '';
        jsonResponse(['ok' => false, 'error' => 'screenshot_provider_error', 'status' => $http, 'detail' => $detail], 502);
    }

    $fallbackType = $format === 'jpg' ? 'image/jpeg' : ($format === 'webp' ? 'image/webp' : 'image/png');
    if (!is_string($contentType) || stripos($contentType, 'image/') !== 0) {
        $contentType = $fallbackType;
    }

    header('Content-Type: ' . $contentType);
    header('Cache-Control: no-store');
    header('Content-Disposition: inline; filename="screenshot.' . $format . '"');
    echo $body;
    exit;
}

function apiFileUploadResponse() {
    global $available_domains;

    $apiUser = requireUserApiAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST, OPTIONS');
        jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }

    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (strpos($contentType, 'multipart/form-data') === false) {
        jsonResponse([
            'ok' => false,
            'error' => 'multipart_required',
            'hint' => 'send the file as multipart/form-data field "file", "image" or "upload_file"'
        ], 400);
    }

    $file = $_FILES['file'] ?? $_FILES['image'] ?? $_FILES['upload_file'] ?? null;
    [$okUpload, $uploadErr, $uploaded] = uploadToSupabaseStorage($file);

    if (!$okUpload) {
        jsonResponse([
            'ok' => false,
            'error' => 'upload_failed',
            'detail' => $uploadErr,
        ], 400);
    }

    $domain = $_POST['domain'] ?? ($_SERVER['HTTP_HOST'] ?? $available_domains[0]);
    $password = $_POST['password'] ?? '';
    $expires_at = $_POST['expires_at'] ?? '';
    $max_clicks = $_POST['max_clicks'] ?? '';
    $custom_code = $_POST['custom_code'] ?? ($_POST['alias'] ?? '');

    [$okLink, $linkErr, $result] = createShortLink($uploaded['public_url'], $domain, $password, $expires_at, $max_clicks, $custom_code, $apiUser['id'] ?? null);

    if (!$okLink) {
        jsonResponse([
            'ok' => false,
            'error' => 'link_create_failed',
            'detail' => $linkErr,
        ], 400);
    }

    jsonResponse([
        'ok' => true,
        'type' => 'file',
        'short_code' => $result['short_code'],
        'short_url' => $result['short_url'],
        'domain' => $result['domain'],
        'expires_at' => $result['expires_at'],
        'max_clicks' => $result['max_clicks'],
        'has_password' => $result['has_password'],
        'file' => [
            'bucket' => $uploaded['bucket'],
            'path' => $uploaded['path'],
            'mime' => $uploaded['mime'],
            'size' => $uploaded['size'],
            'original_name' => $uploaded['original_name'],
        ],
        'note' => 'Visitors open the file on your short URL; the Supabase URL is not returned.'
    ], 201);
}


function apiPasteCreateResponse() {
    global $available_domains;

    $apiUser = requireUserApiAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST, OPTIONS');
        jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }

    $input = apiReadInput();

    // JSON: {"content":"..."} or {"text":"..."}; form: content=... / paste_content=...
    $content = (string)($input['content'] ?? $input['text'] ?? $input['paste_content'] ?? '');
    $domain = $input['domain'] ?? ($_SERVER['HTTP_HOST'] ?? $available_domains[0]);
    $password = (string)($input['password'] ?? '');
    $expires_at = (string)($input['expires_at'] ?? '');
    $max_views = (string)($input['max_views'] ?? $input['max_clicks'] ?? '');
    $custom_code = (string)($input['custom_code'] ?? $input['alias'] ?? '');

    if (!checkCreateRateLimit(10, 3600)) {
        jsonResponse([
            'ok' => false,
            'error' => 'rate_limited',
            'detail' => 'max 10 creates per hour'
        ], 429);
    }

    [$okPaste, $pasteErr, $result] = createPaste($content, $domain, $password, $expires_at, $max_views, $custom_code, $apiUser['id'] ?? null);

    if (!$okPaste) {
        $status = in_array($pasteErr, ['empty_paste', 'paste_too_large', 'invalid_alias', 'alias_taken', 'invalid_expiry'], true) ? 400 : 500;
        jsonResponse([
            'ok' => false,
            'error' => 'paste_create_failed',
            'detail' => $pasteErr,
        ], $status);
    }

    jsonResponse([
        'ok' => true,
        'type' => 'paste',
        'paste_code' => $result['paste_code'],
        'short_code' => $result['paste_code'],
        'short_url' => $result['short_url'],
        'raw_url' => $result['raw_url'],
        'domain' => $result['domain'],
        'expires_at' => $result['expires_at'],
        'max_views' => $result['max_views'],
        'has_password' => $result['has_password'],
        'view_count' => $result['view_count'],
    ], 201);
}

function isValidCode($code) {
    return is_string($code) && preg_match('/^[A-Za-z0-9]{1,32}$/', $code);
}

function isReservedCode($code) {
    $reserved = [
        'api', 'admin', 'abuse', 'upload', 'shorten', 'paste', 'raw', 'screenshot', 'preview-asset', 'file', 'files', 'login', 'logout', 'docs', 'assets', 'static',
        'css', 'js', 'img', 'favicon', 'robots.txt', 'sitemap.xml', 'register', 'account', 'music', 'rss'
    ];

    return in_array(strtolower((string)$code), $reserved, true);
}

function isValidCustomCode($code) {
    return isValidCode($code) && !isReservedCode($code);
}

function clientRateLimitKey() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip = trim(explode(',', (string)$ip)[0]);
    return hash('sha256', 'create-link|' . $ip);
}

function checkCreateRateLimit($max = 10, $windowSeconds = 3600) {
    $dir = sys_get_temp_dir() . '/0x79_rate_limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        // Fallback: wenn tmp nicht beschreibbar ist, nicht hart blockieren.
        return true;
    }

    $file = $dir . '/' . clientRateLimitKey() . '.json';
    $now = time();
    $timestamps = [];

    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $data = json_decode($raw ?: '[]', true);
        if (is_array($data)) {
            $timestamps = array_values(array_filter($data, function ($ts) use ($now, $windowSeconds) {
                return is_numeric($ts) && ((int)$ts) > ($now - $windowSeconds);
            }));
        }
    }

    if (count($timestamps) >= $max) {
        return false;
    }

    $timestamps[] = $now;
    @file_put_contents($file, json_encode($timestamps), LOCK_EX);
    return true;
}

function makeShortCode($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $code;
}

function getAuthorizationHeader() {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();

        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                return $value;
            }
        }
    }

    return '';
}

function isAdminLoggedIn() {
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function adminCsrfToken() {
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf'];
}

function requireAdminCsrf() {
    $token = (string)($_POST['csrf'] ?? '');

    if (empty($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $token)) {
        http_response_code(403);
        exit('invalid csrf token');
    }
}

function requireAdminSession() {
    if (!isAdminLoggedIn()) {
        header('Location: /admin');
        exit;
    }
}

function requireAdminAuth() {
    global $admin_api_key;

    if (isAdminLoggedIn()) {
        return;
    }

    $auth = getAuthorizationHeader();

    if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        jsonResponse(['ok' => false, 'error' => 'unauthorized'], 401);
    }

    $provided = trim($m[1]);

    if (!$admin_api_key || !hash_equals($admin_api_key, $provided)) {
        jsonResponse(['ok' => false, 'error' => 'forbidden'], 403);
    }
}

// ---------------------------------------------------------
// NORMAL USER AUTH + API KEYS
// ---------------------------------------------------------
function userApiKeyHash($plain) {
    return hash('sha256', (string)$plain);
}

function generateUserApiKey() {
    return 'ox79_' . bin2hex(random_bytes(24));
}

function normalizeEmail($email) {
    return strtolower(trim((string)$email));
}

function currentUserId() {
    return isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null;
}

function isUserLoggedIn() {
    return currentUserId() !== null;
}


function fetchUserByApiKey($apiKey) {
    global $supabase_url;
    $apiKey = trim((string)$apiKey);
    if ($apiKey === '') return null;

    $hash = userApiKeyHash($apiKey);
    $url = $supabase_url . "/rest/v1/app_users?api_key_hash=eq." . urlencode($hash) . "&select=id,email,api_key_prefix,created_at&limit=1";
    [$http, $response, $error] = supabaseRequest('GET', $url);
    if ($error || $http < 200 || $http >= 300) return null;
    $data = json_decode($response, true);
    return (!empty($data) && isset($data[0]['id'])) ? $data[0] : null;
}

function getUserApiKeyFromRequest() {
    $header = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (trim($header) !== '') return trim($header);

    $auth = getAuthorizationHeader();
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return trim($m[1]);
    }

    return trim((string)($_POST['api_key'] ?? $_GET['api_key'] ?? ''));
}

function requireUserApiAuth() {
    $user = fetchUserByApiKey(getUserApiKeyFromRequest());
    if (!$user) {
        jsonResponse([
            'ok' => false,
            'error' => 'api_key_required',
            'hint' => 'create an account and send X-API-Key: YOUR_KEY'
        ], 401);
    }
    return $user;
}

function currentUser() {
    $id = currentUserId();
    return $id ? fetchUserById($id) : null;
}

function guestDefaultExpiresAt($ownerUserId, $expiresAt) {
    if (!empty($ownerUserId)) return $expiresAt;
    if (!empty($expiresAt)) return $expiresAt;
    return gmdate('c', time() + 14 * 24 * 60 * 60);
}


function safeUploadExtension($name) {
    $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));

    // File/Image host. SVG stays blocked because it can contain scripts/external refs.
    // Executable/scriptable web formats stay blocked.
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'zip'];

    return in_array($ext, $allowed, true) ? $ext : false;
}

function isAllowedUploadMime($mime, $ext = '') {
    $mime = strtolower((string)$mime);
    $ext = strtolower((string)$ext);

    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true)) {
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true);
    }

    if (in_array($mime, ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream'], true)) {
        return $ext === 'zip';
    }

    return false;
}


function compactUploadDebug($value) {
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = str_replace(["\r", "\n", "\0"], ' ', $value);
    return strlen($value) > 260 ? substr($value, 0, 260) . '…' : $value;
}

function storageErrorCodeFromResponse($http, $response, $curlError = '') {
    $detail = '';

    if ($curlError !== '') {
        $detail = 'curl=' . $curlError;
    } else {
        $decoded = json_decode((string)$response, true);
        if (is_array($decoded)) {
            $parts = [];
            foreach (['statusCode', 'error', 'message', 'code'] as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                    $parts[] = $key . '=' . (string)$decoded[$key];
                }
            }
            $detail = implode(' | ', $parts);
        }

        if ($detail === '') {
            $detail = (string)$response;
        }
    }

    $detail = compactUploadDebug($detail);
    return 'storage_failed_http_' . (int)$http . ($detail !== '' ? '__' . $detail : '');
}


// Public URL prefixes we are willing to serve/proxy. Keeps the proxy from
// becoming an open relay: only our own storage backends are allowed.
function storagePublicPrefixes() {
    global $supabase_url, $storage_driver;

    $prefixes = [];
    if (!empty($supabase_url)) {
        $prefixes[] = rtrim((string)$supabase_url, '/') . '/storage/v1/object/public/';
    }
    if (($storage_driver ?? 'supabase') === 's3' && function_exists('s3PublicUrl')) {
        $base = s3PublicUrl('');
        if ($base !== '' && $base !== '/') $prefixes[] = $base;
    }
    return array_values(array_filter($prefixes));
}

// Coarse device classification from a User-Agent string.
function detectDeviceType($ua) {
    $ua = strtolower((string)$ua);
    if ($ua === '') return 'other';
    if (preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|discord|telegram|whatsapp|curl|wget|python-requests/', $ua)) return 'bot';
    if (preg_match('/mobile|android|iphone|ipod|ipad|tablet|windows phone/', $ua)) return 'mobile';
    return 'desktop';
}

function isHostedFileStorageUrl($url) {
    $url = trim((string)$url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return false;

    $matched = false;
    foreach (storagePublicPrefixes() as $prefix) {
        if (strncmp($url, $prefix, strlen($prefix)) === 0) { $matched = true; break; }
    }
    if (!$matched) return false;

    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'zip'], true);
}

function isHostedImageStorageUrl($url) {
    $path = parse_url((string)$url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return isHostedFileStorageUrl($url) && in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true);
}

function proxyHostedFile($url) {
    $url = trim((string)$url);

    if (!isHostedFileStorageUrl($url)) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: image/avif,image/webp,image/png,image/jpeg,image/gif,application/zip,*/*;q=0.8'
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
    curl_close($ch);

    if ($error || $response === false || $http < 200 || $http >= 300) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'file proxy failed.';
        exit;
    }

    $body = substr($response, (int)$headerSize);

    $contentType = strtolower(trim(explode(';', $contentType)[0]));
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: 'file', PATHINFO_EXTENSION));

    if (!isAllowedUploadMime($contentType, $ext)) {
        http_response_code(415);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'blocked: not an allowed file type.';
        exit;
    }

    $filename = ($ext === 'zip' ? 'download' : 'image') . ($ext ? '.' . $ext : '');
    $disposition = $ext === 'zip' ? 'attachment' : 'inline';

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"');
    header('Cache-Control: public, max-age=86400');
    header('X-Robots-Tag: noindex, nofollow');
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}


// Rewrite a Supabase storage image URL to a same-origin proxy path so it
// passes the strict img-src CSP. Non-hosted/other URLs are returned unchanged.
function proxyImageUrl($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (!isHostedImageStorageUrl($url)) return $url;
    return '/img?u=' . urlencode($url);
}

function parseOptionalExpiresAt($value) {
    $value = trim((string)$value);
    if ($value === '') return null;

    // HTML datetime-local liefert z.B. 2026-05-25T18:30
    $ts = strtotime($value);
    if ($ts === false) return null;

    return gmdate('c', $ts);
}

function isExpiredRow($row) {
    if (empty($row['expires_at'])) return false;
    $ts = strtotime((string)$row['expires_at']);
    return $ts !== false && $ts <= time();
}

function parseOptionalMaxClicks($value) {
    $value = trim((string)$value);
    if ($value === '' || $value === '0') return null;

    if (!preg_match('/^[0-9]+$/', $value)) return null;

    $n = (int)$value;

    // Leer = kein Burn-Limit. Obergrenze schützt vor Quatschwerten/Overflow.
    return ($n >= 1 && $n <= 1000000) ? $n : null;
}

function isBurnedRow($row) {
    if (empty($row['max_clicks'])) return false;
    $max = (int)$row['max_clicks'];
    $clicks = isset($row['click_count']) ? (int)$row['click_count'] : 0;
    return $max > 0 && $clicks >= $max;
}

function normalizeLinkRow($row, $host = null) {
    $host = $host ?: cleanHost($_SERVER['HTTP_HOST'] ?? '0x79.one');
    $code = $row['short_code'] ?? null;

    return [
        'id' => $row['id'] ?? null,
        'short_code' => $code,
        'short_url' => $code ? 'https://' . $host . '/' . $code : null,
        'long_url' => $row['long_url'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'expires_at' => $row['expires_at'] ?? null,
        'click_count' => isset($row['click_count']) ? (int)$row['click_count'] : 0,
        'max_clicks' => !empty($row['max_clicks']) ? (int)$row['max_clicks'] : null,
        'has_password' => !empty($row['password_hash']),
        'preview_enabled' => !empty($row['preview_enabled']),
    ];
}

// ---------------------------------------------------------
// MUSIC PROMOTER
// ---------------------------------------------------------
function musicPlatforms() {
    return [
        'spotify' => [
            'label' => 'Spotify',
            'hosts' => ['open.spotify.com', 'spotify.link'],
            'badge' => 'SP',
            'hint' => 'https://open.spotify.com/track/...',
        ],
        'apple_music' => [
            'label' => 'Apple Music',
            'hosts' => ['music.apple.com', 'itunes.apple.com'],
            'badge' => 'AM',
            'hint' => 'https://music.apple.com/...',
        ],
        'youtube_music' => [
            'label' => 'YouTube Music',
            'hosts' => ['music.youtube.com', 'youtube.com', 'www.youtube.com', 'youtu.be'],
            'badge' => 'YT',
            'hint' => 'https://music.youtube.com/watch?v=...',
        ],
        'soundcloud' => [
            'label' => 'SoundCloud',
            'hosts' => ['soundcloud.com', 'on.soundcloud.com'],
            'badge' => 'SC',
            'hint' => 'https://soundcloud.com/...',
        ],
        'deezer' => [
            'label' => 'Deezer',
            'hosts' => ['deezer.page.link', 'deezer.com', 'www.deezer.com'],
            'badge' => 'DZ',
            'hint' => 'https://www.deezer.com/track/...',
        ],
        'tidal' => [
            'label' => 'TIDAL',
            'hosts' => ['tidal.com', 'listen.tidal.com'],
            'badge' => 'TD',
            'hint' => 'https://tidal.com/browse/track/...',
        ],
        'amazon_music' => [
            'label' => 'Amazon Music',
            'hosts' => ['music.amazon.com', 'amazon.de', 'amazon.com'],
            'badge' => 'AZ',
            'hint' => 'https://music.amazon.com/albums/...',
        ],
        'bandcamp' => [
            'label' => 'Bandcamp',
            'hosts' => ['bandcamp.com'],
            'badge' => 'BC',
            'hint' => 'https://artist.bandcamp.com/track/...',
            'allow_subdomains' => true,
        ],
        'audiomack' => [
            'label' => 'Audiomack',
            'hosts' => ['audiomack.com'],
            'badge' => 'AU',
            'hint' => 'https://audiomack.com/...',
        ],
        'beatport' => [
            'label' => 'Beatport',
            'hosts' => ['beatport.com', 'www.beatport.com'],
            'badge' => 'BP',
            'hint' => 'https://www.beatport.com/track/...',
        ],
    ];
}

function isAllowedMusicPlatformUrl($platformKey, $url) {
    $platforms = musicPlatforms();
    if (!isset($platforms[$platformKey])) return false;

    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) return false;

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) return false;

    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', 'www.', $host);

    foreach ($platforms[$platformKey]['hosts'] as $allowedHost) {
        $allowedHost = strtolower($allowedHost);
        if ($host === $allowedHost) return true;
        if (!empty($platforms[$platformKey]['allow_subdomains']) && str_ends_with($host, '.' . $allowedHost)) return true;
    }

    return false;
}

function normalizeMusicLinks($input) {
    $platforms = musicPlatforms();
    $links = [];

    foreach ($platforms as $key => $meta) {
        $url = trim((string)($input[$key] ?? ''));
        if ($url === '') continue;

        if (!isAllowedMusicPlatformUrl($key, $url)) {
            return [false, 'invalid_music_url_' . $key, []];
        }

        $links[] = [
            'key' => $key,
            'label' => $meta['label'],
            'url' => $url,
            'badge' => $meta['badge'],
        ];
    }

    if (empty($links)) {
        return [false, 'music_links_missing', []];
    }

    return [true, null, $links];
}


function isValidMusicImageUrl($url) {
    $url = trim((string)$url);
    if ($url === '') return true;
    if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function musicErrorText($err) {
    if ($err === '') return '';
    if ($err === 'music_links_missing') return 'Bitte mindestens einen Musik-Link eintragen.';
    if ($err === 'music_title_invalid') return 'Bitte einen Song-/Release-Titel eintragen.';
    if ($err === 'music_artist_invalid') return 'Artist-Name ist zu lang.';
    if ($err === 'music_image_type') return 'Cover/Banner muss ein Bild sein: JPG, PNG, WEBP, GIF oder AVIF.';
    if ($err === 'music_image_url_invalid') return 'Cover-/Banner-URL ist ungültig.';
    if ($err === 'music_image_upload_failed') return 'Cover oder Banner konnte nicht hochgeladen werden.';
    if ($err === 'invalid_alias') return 'Alias ist ungültig. Nur A-Z, a-z und 0-9, maximal 32 Zeichen.';
    if ($err === 'alias_taken') return 'Dieser Alias ist schon vergeben.';
    if ($err === 'invalid_expiry') return 'Ablaufdatum ist ungültig oder liegt in der Vergangenheit.';
    if ($err === 'rate_limited') return 'Zu viele Creates. Bitte später nochmal versuchen.';
    if (str_starts_with($err, 'invalid_music_url_')) {
        $key = substr($err, strlen('invalid_music_url_'));
        $platforms = musicPlatforms();
        $label = $platforms[$key]['label'] ?? $key;
        return 'Der Link für ' . $label . ' passt nicht zur echten Plattform-Domain.';
    }
    if ($err === 'music_save_failed') return 'Music Promo konnte nicht gespeichert werden. Prüfe, ob die SQL-Migration ausgeführt wurde.';
    return 'Music Promo konnte nicht erstellt werden: ' . $err;
}
function isBurnedPaste($row) {
    if (empty($row['max_views'])) return false;
    $max = (int)$row['max_views'];
    $views = isset($row['view_count']) ? (int)$row['view_count'] : 0;
    return $max > 0 && $views >= $max;
}

function pasteErrorText($err, $t) {
    if ($err === 'empty_paste') return 'paste ist leer.';
    if ($err === 'paste_too_large') return 'paste ist zu groß. maximal 200 KB.';
    if ($err === 'invalid_alias') return 'alias ist ungültig oder reserviert.';
    if ($err === 'alias_taken') return 'alias ist bereits vergeben.';
    if ($err === 'invalid_expiry') return 'ablaufdatum liegt in der vergangenheit.';
    if ($err === 'rate_limited') return 'rate limit erreicht. später nochmal probieren.';
    if ($err === 'save_failed') return 'paste konnte nicht gespeichert werden. prüfe die Supabase-Tabelle pastes.';
    return $t['err_save'] ?? 'fehler beim speichern.';
}


function outputLinksCsv($links) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="0x79-links-' . gmdate('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'short_code', 'short_url', 'long_url', 'created_at', 'expires_at', 'click_count', 'max_clicks', 'has_password']);

    foreach ($links as $link) {
        fputcsv($out, [
            $link['id'] ?? '',
            $link['short_code'] ?? '',
            $link['short_url'] ?? '',
            $link['long_url'] ?? '',
            formatDateTime($link['created_at'] ?? ''),
            !empty($link['expires_at']) ? formatDateTime($link['expires_at']) : '',
            $link['click_count'] ?? 0,
            $link['max_clicks'] ?? '',
            !empty($link['has_password']) ? 'yes' : 'no',
        ]);
    }

    fclose($out);
    exit;
}

function adminCleanSearch($q) {
    $q = trim((string)$q);
    $q = mb_substr($q, 0, 120);
    // PostgREST OR-Filter nutzt Kommas/Klammern als Syntax. Für Suche entfernen wir sie bewusst.
    $q = preg_replace('/[(),*]/u', ' ', $q);
    $q = preg_replace('/\s+/u', ' ', $q);
    return trim($q);
}

function supabaseIlikeValue($q) {
    return '*' . str_replace(['%', '_'], ['\\%', '\\_'], adminCleanSearch($q)) . '*';
}

function extractShortCodeFromReportedLink($reported_link) {
    $reported_link = trim((string)$reported_link);

    if (isValidCode($reported_link)) {
        return $reported_link;
    }

    $path = (string)parse_url($reported_link, PHP_URL_PATH);
    $path = trim($path, '/');

    if ($path !== '') {
        $parts = explode('/', $path);
        $candidate = end($parts);

        if (isValidCode($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function normalizeAbuseReportRow($row) {
    return [
        'id' => $row['id'] ?? null,
        'reported_link' => $row['reported_link'] ?? '',
        'reason' => $row['reason'] ?? '',
        'status' => $row['status'] ?? 'open',
        'created_at' => $row['created_at'] ?? '',
    ];
}

function adminUrl($params = []) {
    $base = [
        'tab' => $_GET['tab'] ?? 'links',
        'q_links' => $_GET['q_links'] ?? '',
        'q_abuse' => $_GET['q_abuse'] ?? '',
        'q_pastes' => $_GET['q_pastes'] ?? '',
        'links_limit' => $_GET['links_limit'] ?? 25,
        'links_offset' => $_GET['links_offset'] ?? 0,
        'pastes_limit' => $_GET['pastes_limit'] ?? 25,
        'pastes_offset' => $_GET['pastes_offset'] ?? 0,
        'abuse_limit' => $_GET['abuse_limit'] ?? 25,
        'abuse_offset' => $_GET['abuse_offset'] ?? 0,
    ];

    foreach ($params as $k => $v) {
        if ($v === null) {
            unset($base[$k]);
        } else {
            $base[$k] = $v;
        }
    }

    return '/admin?' . http_build_query($base);
}


function htmlDatetimeLocalValue($value) {
    if (empty($value)) return '';
    $ts = strtotime((string)$value);
    return $ts !== false ? date('Y-m-d\TH:i', $ts) : '';
}
function sanitizeAdminReturnTo($returnTo, $fallback = '/admin') {
    $returnTo = (string)$returnTo;
    if ($returnTo === '' || str_starts_with($returnTo, '//') || !str_starts_with($returnTo, '/admin')) {
        return $fallback;
    }
    return $returnTo;
}


function isWebPreviewableTarget($url) {
    $scheme = strtolower((string)parse_url((string)$url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL);
}

function previewBase64UrlEncode($value) {
    return rtrim(strtr(base64_encode((string)$value), '+/', '-_'), '=');
}

function previewBase64UrlDecode($value) {
    $value = strtr((string)$value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad) $value .= str_repeat('=', 4 - $pad);
    $decoded = base64_decode($value, true);
    return $decoded === false ? '' : $decoded;
}

function absolutePreviewUrl($url, $base) {
    $url = trim((string)$url);
    if ($url === '' || preg_match('/^(javascript|data|blob|file):/i', $url)) return '';
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) return $url;

    $bp = parse_url((string)$base);
    if (empty($bp['scheme']) || empty($bp['host'])) return '';
    $scheme = $bp['scheme'];
    $host = $bp['host'];
    $port = isset($bp['port']) ? ':' . $bp['port'] : '';
    $root = $scheme . '://' . $host . $port;

    if (strpos($url, '//') === 0) return $scheme . ':' . $url;
    if ($url[0] === '/') return $root . $url;

    $path = $bp['path'] ?? '/';
    $dir = preg_replace('#/[^/]*$#', '/', $path);
    $combined = $dir . $url;
    $parts = [];
    foreach (explode('/', $combined) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') array_pop($parts);
        else $parts[] = $part;
    }
    return $root . '/' . implode('/', $parts);
}

function previewAssetProxyUrl($assetUrl, $baseUrl) {
    $abs = absolutePreviewUrl($assetUrl, $baseUrl);
    if (!isWebPreviewableTarget($abs)) return '';
    return '/preview-asset?u=' . previewBase64UrlEncode($abs);
}

function fetchPreviewHttp($url, $maxRedirects = 3) {
    [$valid, $validationError] = isPublicHttpUrl($url);
    if (!$valid) return [false, $validationError, null, null, null];

    $current = (string)$url;
    for ($i = 0; $i <= $maxRedirects; $i++) {
        $ch = curl_init($current);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_MAXFILESIZE, 2 * 1024 * 1024);
        curl_setopt($ch, CURLOPT_USERAGENT, '0x79-preview/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/html,application/xhtml+xml,text/css,image/*,*/*;q=0.8']);
        if (defined('CURLOPT_PROTOCOLS')) curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $current;
        curl_close($ch);

        if ($err || $raw === false) return [false, 'fetch_failed', null, null, $err];
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        if ($http >= 300 && $http < 400 && preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
            $next = absolutePreviewUrl(trim($m[1]), $effective);
            [$ok, $why] = isPublicHttpUrl($next);
            if (!$ok) return [false, $why, null, null, null];
            $current = $next;
            continue;
        }

        if ($http < 200 || $http >= 300) return [false, 'http_' . $http, null, $contentType, null];
        return [true, null, $body, $contentType, $effective];
    }

    return [false, 'too_many_redirects', null, null, null];
}

function sanitizePreviewHtml($html, $baseUrl) {
    if (!class_exists('DOMDocument')) {
        return '<pre>' . h(substr((string)$html, 0, 50000)) . '</pre>';
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . (string)$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
    libxml_clear_errors();

    $removeTags = ['script', 'iframe', 'object', 'embed', 'applet', 'meta', 'base'];
    foreach ($removeTags as $tag) {
        while (($nodes = $doc->getElementsByTagName($tag))->length > 0) {
            $node = $nodes->item(0);
            $node->parentNode->removeChild($node);
        }
    }

    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//*') as $el) {
        if (!$el->hasAttributes()) continue;
        $attrs = [];
        foreach ($el->attributes as $attr) $attrs[] = $attr->name;
        foreach ($attrs as $name) {
            $value = $el->getAttribute($name);
            $lname = strtolower($name);
            if (strpos($lname, 'on') === 0 || in_array($lname, ['srcdoc', 'integrity', 'nonce'], true)) {
                $el->removeAttribute($name);
                continue;
            }
            if (in_array($lname, ['href', 'src', 'poster'], true)) {
                if ($el->tagName === 'link' && strtolower($el->getAttribute('rel')) === 'stylesheet') {
                    $proxy = previewAssetProxyUrl($value, $baseUrl);
                    if ($proxy !== '') $el->setAttribute($name, $proxy); else $el->parentNode->removeChild($el);
                    continue;
                }
                if (in_array($el->tagName, ['img', 'source', 'video', 'audio'], true)) {
                    $proxy = previewAssetProxyUrl($value, $baseUrl);
                    if ($proxy !== '') $el->setAttribute($name, $proxy); else $el->removeAttribute($name);
                    continue;
                }
                $abs = absolutePreviewUrl($value, $baseUrl);
                if ($abs !== '') {
                    $el->setAttribute($name, $abs);
                    if ($el->tagName === 'a') {
                        $el->setAttribute('target', '_blank');
                        $el->setAttribute('rel', 'noopener noreferrer nofollow');
                    }
                } else {
                    $el->removeAttribute($name);
                }
            }
            if ($lname === 'srcset') {
                $el->removeAttribute($name);
            }
            if ($el->tagName === 'form' && $lname === 'action') {
                $el->removeAttribute($name);
            }
        }
        if ($el->tagName === 'form') {
            $el->setAttribute('data-disabled', 'true');
        }
    }

    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) return '';
    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}


function previewEdgeConfigured() {
    global $preview_edge_function_url, $preview_edge_secret;
    return !empty($preview_edge_function_url) && !empty($preview_edge_secret);
}

function callPreviewEdgeJson($payload) {
    global $preview_edge_function_url, $preview_edge_secret, $preview_edge_auth_key;

    if (!previewEdgeConfigured()) {
        return [false, 'preview_edge_not_configured', null];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Preview-Secret: ' . $preview_edge_secret,
    ];

    if (!empty($preview_edge_auth_key)) {
        $headers[] = 'Authorization: Bearer ' . $preview_edge_auth_key;
    }

    $ch = curl_init($preview_edge_function_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error || $response === false) {
        return [false, 'edge_request_failed' . ($error ? ': ' . $error : ''), null];
    }

    $data = json_decode((string)$response, true);
    if ($http < 200 || $http >= 300) {
        $msg = is_array($data) && isset($data['error']) ? (string)$data['error'] : ('edge_http_' . $http);
        return [false, $msg, $data];
    }

    if (!is_array($data)) {
        return [false, 'edge_invalid_json', null];
    }

    return [true, null, $data];
}

function callPreviewEdgeAsset($url) {
    global $preview_edge_function_url, $preview_edge_secret, $preview_edge_auth_key;

    if (!previewEdgeConfigured()) {
        return [false, 'preview_edge_not_configured', null, null, 500];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: */*',
        'X-Preview-Secret: ' . $preview_edge_secret,
    ];

    if (!empty($preview_edge_auth_key)) {
        $headers[] = 'Authorization: Bearer ' . $preview_edge_auth_key;
    }

    $payload = ['mode' => 'asset', 'url' => $url];

    $ch = curl_init($preview_edge_function_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($error || $raw === false) {
        return [false, 'edge_asset_request_failed', null, null, 502];
    }

    $body = substr((string)$raw, $headerSize);
    if ($http < 200 || $http >= 300) {
        return [false, 'edge_asset_http_' . $http, $body, $contentType, $http];
    }

    return [true, null, $body, $contentType, $http];
}

function addQueryParamToUrl($url, $key, $value) {
    $parts = parse_url((string)$url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return (string)$url;

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query[$key] = $value;

    $rebuilt = $parts['scheme'] . '://';
    if (!empty($parts['user'])) {
        $rebuilt .= $parts['user'];
        if (!empty($parts['pass'])) $rebuilt .= ':' . $parts['pass'];
        $rebuilt .= '@';
    }
    $rebuilt .= $parts['host'];
    if (!empty($parts['port'])) $rebuilt .= ':' . $parts['port'];
    $rebuilt .= $parts['path'] ?? '/';
    $rebuilt .= '?' . http_build_query($query);
    if (!empty($parts['fragment'])) $rebuilt .= '#' . $parts['fragment'];
    return $rebuilt;
}


function uploadErrorText($err, $t) {
    $err = (string)$err;

    if (strpos($err, 'storage_failed_http_') === 0) {
        $detail = substr($err, strlen('storage_failed_http_'));
        $detail = str_replace('__', ' — ', $detail);
        return 'upload fehlgeschlagen: storage http ' . $detail;
    }

    if (strpos($err, 'php_upload_error_') === 0) {
        return 'upload fehlgeschlagen: php upload error code ' . substr($err, strlen('php_upload_error_')) . '. Prüfe upload_max_filesize, post_max_size und Server-Logs.';
    }

    if ($err === 'tmp_file_invalid') return 'upload fehlgeschlagen: temporäre Upload-Datei ist ungültig. Prüfe PHP upload_tmp_dir und Server-Rechte.';
    if ($err === 'tmp_file_read_failed') return 'upload fehlgeschlagen: temporäre Datei konnte nicht gelesen werden.';

    if ($err === 'file_missing') return $t['err_file_missing'] ?? 'no file selected.';
    if ($err === 'file_size') return $t['err_file_size'] ?? 'file is too large.';
    if ($err === 'file_type') return $t['err_file_type'] ?? 'file type is not allowed.';
    if ($err === 'upload_partial') return $t['err_upload_partial'] ?? 'upload was only partially transferred.';
    if ($err === 'upload_tmp') return $t['err_upload_tmp'] ?? 'temporary upload folder is missing.';
    if ($err === 'upload_write') return $t['err_upload_write'] ?? 'file could not be written.';
    if ($err === 'storage_forbidden') return $t['err_storage_forbidden'] ?? 'storage access denied.';
    if ($err === 'storage_bucket') return $t['err_storage_bucket'] ?? 'storage bucket not found.';
    if ($err === 'invalid_expiry') return $t['err_expired'] ?? 'expired.';
    if ($err === 'invalid_alias') return $t['err_alias'] ?? 'invalid alias.';
    if ($err === 'alias_taken') return $t['err_alias_taken'] ?? 'alias taken.';
    if ($err === 'rate_limited') return $t['err_rate_limit'] ?? 'rate limit reached.';

    return 'upload fehlgeschlagen: ' . ($err !== '' ? $err : 'unbekannter fehler');
}

function pastePasswordOk($row, $code) {
    if (empty($row['password_hash'])) return true;

    $query_password = (string)($_GET['pw'] ?? $_GET['password'] ?? '');
    if ($query_password !== '' && password_verify($query_password, (string)$row['password_hash'])) return true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals((string)($_POST['code'] ?? ''), $code)) {
        return password_verify((string)($_POST['paste_password'] ?? ''), (string)$row['password_hash']);
    }

    return false;
}


// ---------------------------------------------------------
// RSS FEED HELPERS & MOCK
// ---------------------------------------------------------
class SupabaseRssDbMock {
    public function query($sql) {
        $limit = 50;
        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $matches)) {
            $limit = (int)$matches[1];
        }
        $this->rows = fetchRssPosts($limit);
        return $this;
    }
    
    public function fetchAll() {
        return $this->rows;
    }
    
    private $rows = [];
}

if (!function_exists('db')) {
    function db() {
        return new SupabaseRssDbMock();
    }
}

if (!function_exists('xml')) {
    function xml(?string $str): string {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cdata')) {
    function cdata(?string $str): string {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string)$str) . ']]>';
    }
}

if (!function_exists('postUrl')) {
    function postUrl(int $id): string {
        $host = $_SERVER['HTTP_HOST'] ?? '0x79.one';
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $proto . '://' . $host . '/posts/' . $id;
    }
}

if (!function_exists('imageUrl')) {
    function imageUrl(?string $image): ?string {
        if (!$image) return null;
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }
        $host = $_SERVER['HTTP_HOST'] ?? '0x79.one';
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $proto . '://' . $host . '/images/' . $image;
    }
}


