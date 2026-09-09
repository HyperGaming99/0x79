<?php
declare(strict_types=1);

// ---------------------------------------------------------
// ADMIN SESSION
// ---------------------------------------------------------
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (session_status() === PHP_SESSION_NONE) {
    // sessions/ liegt nicht im Repo (nur das Docker-Image legt es an).
    // Ohne Verzeichnis schlägt session_start() still fehl und CSRF-Tokens gehen verloren.
    $session_dir = __DIR__ . '/sessions';
    if (!is_dir($session_dir) && !mkdir($session_dir, 0700, true) && !is_dir($session_dir)) {
        http_response_code(500);
        die('Configuration error: cannot create sessions directory.');
    }
    if (!is_writable($session_dir)) {
        http_response_code(500);
        die('Configuration error: sessions directory is not writable.');
    }
    session_save_path($session_dir);
    session_name('ox79_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ---------------------------------------------------------
// SECURITY HEADERS (gegen XSS / Script-Injection / Clickjacking)
// ---------------------------------------------------------
// Per-request nonce for inline scripts (replaces unsafe-inline).
$csp_nonce = base64_encode(random_bytes(16));

// embed_preview only permitted on short-link paths (alphanumeric codes).
$_csp_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$embed_preview = isset($_GET['embed_preview']) && $_GET['embed_preview'] === '1'
    && preg_match('/^[A-Za-z0-9]{1,32}$/', $_csp_path);
unset($_csp_path);
$frame_ancestors = $embed_preview ? "'self'" : "'none'";

header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'nonce-{$csp_nonce}' https://cdn.tailwindcss.com; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    // preview-asset läuft über dieselbe Domain. Fonts müssen deshalb 'self' erlauben.
    . "font-src 'self' data: blob: https://fonts.gstatic.com; "
    . "img-src 'self' data: blob:; "
    . "media-src 'self' data: blob:; "
    . "connect-src 'self'; "
    . "frame-src https:; "
    . "form-action 'self'; "
    . "frame-ancestors " . $frame_ancestors . "; "
    . "base-uri 'self'; "
    . "object-src 'none'");
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: ' . ($embed_preview ? 'SAMEORIGIN' : 'DENY'));
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

loadEnv(__DIR__ . '/.env');

// ---------------------------------------------------------
// DATABASE BACKEND SELECTION
// Default is Supabase. Switch via .env:
//   DB_DRIVER=supabase|postgres
// ---------------------------------------------------------
$db_driver = strtolower(trim((string)(getenv('DB_DRIVER') ?: 'supabase')));
if (!in_array($db_driver, ['supabase', 'postgres'], true)) $db_driver = 'supabase';

// Postgres (used when DB_DRIVER=postgres). Either a full DSN or discrete parts.
$pg_dsn      = getenv('POSTGRES_DSN') ?: '';
$pg_host     = getenv('POSTGRES_HOST') ?: 'localhost';
$pg_port     = getenv('POSTGRES_PORT') ?: '5432';
$pg_db       = getenv('POSTGRES_DB') ?: 'postgres';
$pg_user     = getenv('POSTGRES_USER') ?: 'postgres';
$pg_password = getenv('POSTGRES_PASSWORD') ?: '';

$supabase_url = getenv('SUPABASE_URL');
$supabase_key = getenv('SUPABASE_KEY');
$admin_api_key = getenv('ADMIN_API_KEY');
$admin_password = getenv('ADMIN_PASSWORD') ?: '';
// Prefer the Supabase service role key server-side; fall back to the anon key.
$supabase_db_key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: $supabase_key;
$screenshotone_access_key = getenv('SCREENSHOTONE_ACCESS_KEY') ?: getenv('SCREENSHOT_API_KEY');
$preview_edge_function_url = getenv('PREVIEW_EDGE_FUNCTION_URL') ?: (rtrim((string)$supabase_url, '/') . '/functions/v1/preview-render');
$preview_edge_secret = getenv('PREVIEW_EDGE_SECRET') ?: '';
$preview_edge_auth_key = getenv('PREVIEW_EDGE_AUTH_KEY') ?: (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: $supabase_key);

// Supabase credentials are only required when the Supabase DB driver is selected.
$needs_supabase = ($db_driver === 'supabase');
if (!$admin_api_key || !$admin_password || ($needs_supabase && (!$supabase_url || !$supabase_key))) {
    http_response_code(500);
    die("Configuration error.");
}

// ---------------------------------------------------------
// VERFÜGBARE DOMAINS
// ---------------------------------------------------------
$available_domains = [
    '0x79.one',
    'fftrclo.store',
    'takeitdown.space',
    'mydiscordiscool.store',
    'fckdupfuture.com'
];

if (!isset($T['en'])) {
    $T['en'] = [];
}
if (!isset($T[$lang])) {
    $lang = 'en';
}
$t = array_replace($T['en'], $T[$lang] ?? []);

$LANG_META = $LANG_DATA;

$short_url = "";
$error = "";


