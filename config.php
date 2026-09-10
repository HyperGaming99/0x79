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

header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'nonce-{$csp_nonce}' https://cdn.tailwindcss.com; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' data: blob: https://fonts.gstatic.com; "
    . "img-src 'self' data: blob:; "
    . "media-src 'self' data: blob:; "
    . "connect-src 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'none'; "
    . "base-uri 'self'; "
    . "object-src 'none'");
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
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
$app_version = '2.0.1';
$landscape_backgrounds = [
    [
        'image' => '/backgrounds/hampi.jpg',
        'artist' => 'Vyacheslav Argenberg',
        'source' => 'Wikimedia Commons',
        'url' => 'https://commons.wikimedia.org/wiki/File:Hampi,_India,_Rocky_landscape_of_Hampi,_Granite_rocks_of_Matanga_Hill.jpg',
        'tint' => 'rgba(91, 54, 24, .58)',
    ],
    [
        'image' => '/backgrounds/laugavegur.jpg',
        'artist' => 'Chmee2 / Valtameri',
        'source' => 'Wikimedia Commons',
        'url' => 'https://commons.wikimedia.org/wiki/File:Landscape_during_Laugavegur_hiking_trail_2-CA_reduced.jpg',
        'tint' => 'rgba(19, 67, 79, .55)',
    ],
    [
        'image' => '/backgrounds/tuscany-harvest.jpg',
        'artist' => 'Martin Falbisoner',
        'source' => 'Wikimedia Commons',
        'url' => 'https://commons.wikimedia.org/wiki/File:Tuscan_Landscape_7.JPG',
        'tint' => 'rgba(101, 76, 31, .52)',
    ],
    [
        'image' => '/backgrounds/tuscany-tree.jpg',
        'artist' => 'Eric Kilby',
        'source' => 'Wikimedia Commons',
        'url' => 'https://commons.wikimedia.org/wiki/File:Tuscan_landscape_with_lonely_tree.jpg',
        'tint' => 'rgba(74, 43, 47, .52)',
    ],
    [
        'image' => '/backgrounds/italian-pines.jpg',
        'artist' => 'Hendrik Voogd',
        'source' => 'Wikimedia Commons, public domain',
        'url' => 'https://commons.wikimedia.org/wiki/File:Hendrik_Voogd_-_Italian_landscape_with_Umbrella_Pines.jpg',
        'tint' => 'rgba(31, 55, 47, .56)',
    ],
];
$admin_api_key = getenv('ADMIN_API_KEY');
$admin_password = getenv('ADMIN_PASSWORD') ?: '';
// Prefer the Supabase service role key server-side; fall back to the anon key.
$supabase_db_key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: $supabase_key;
$screenshotone_access_key = getenv('SCREENSHOTONE_ACCESS_KEY') ?: getenv('SCREENSHOT_API_KEY');

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

// Optional per-domain icons for the domain picker. Local files only (no
// external favicon services); domains without an existing file fall back
// to a generated letter mark.
$domain_icons = [
    '0x79.one' => '/logomark_0x79.jpg',
    'fftrclo.store' => '/assets/domains/fftrclo-store.svg',
    'takeitdown.space' => '/assets/domains/takeitdown-space.svg',
    'mydiscordiscool.store' => '/assets/domains/mydiscordiscool-store.svg',
    'fckdupfuture.com' => '/assets/domains/fckdupfuture-com.svg',
];

$domain_icon_map = [];
foreach ($available_domains as $domain) {
    $icon = $domain_icons[$domain] ?? '';
    $domain_icon_map[$domain] = ($icon !== '' && is_file(__DIR__ . $icon)) ? $icon : '';
}

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


