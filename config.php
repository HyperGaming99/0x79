<?php
declare(strict_types=1);

// ---------------------------------------------------------
// ADMIN SESSION
// ---------------------------------------------------------
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (session_status() === PHP_SESSION_NONE) {
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
$embed_preview = isset($_GET['embed_preview']) && $_GET['embed_preview'] === '1';
$frame_ancestors = $embed_preview ? "'self'" : "'none'";

header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    // preview-asset läuft über dieselbe Domain. Fonts müssen deshalb 'self' erlauben.
    . "font-src 'self' data: blob: https://fonts.gstatic.com; "
    . "img-src 'self' data: blob:; "
    . "media-src 'self' data: blob:; "
    . "connect-src 'self'; "
    . "frame-src http: https:; "
    . "form-action 'self'; "
    . "frame-ancestors " . $frame_ancestors . "; "
    . "base-uri 'self'; "
    . "object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: ' . ($embed_preview ? 'SAMEORIGIN' : 'DENY'));
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

loadEnv(__DIR__ . '/.env');

// ---------------------------------------------------------
// STORAGE / DATABASE BACKEND SELECTION
// Default is Supabase for both. Switch via .env:
//   DB_DRIVER=supabase|postgres
//   STORAGE_DRIVER=supabase|s3
// ---------------------------------------------------------
$db_driver      = strtolower(trim((string)(getenv('DB_DRIVER') ?: 'supabase')));
$storage_driver = strtolower(trim((string)(getenv('STORAGE_DRIVER') ?: 'supabase')));
if (!in_array($db_driver, ['supabase', 'postgres'], true)) $db_driver = 'supabase';
if (!in_array($storage_driver, ['supabase', 's3'], true)) $storage_driver = 'supabase';

// Postgres (used when DB_DRIVER=postgres). Either a full DSN or discrete parts.
$pg_dsn      = getenv('POSTGRES_DSN') ?: '';
$pg_host     = getenv('POSTGRES_HOST') ?: 'localhost';
$pg_port     = getenv('POSTGRES_PORT') ?: '5432';
$pg_db       = getenv('POSTGRES_DB') ?: 'postgres';
$pg_user     = getenv('POSTGRES_USER') ?: 'postgres';
$pg_password = getenv('POSTGRES_PASSWORD') ?: '';

// S3 / MinIO (used when STORAGE_DRIVER=s3).
$s3_endpoint       = rtrim((string)(getenv('S3_ENDPOINT') ?: ''), '/'); // e.g. http://minio:9000
$s3_region         = getenv('S3_REGION') ?: 'us-east-1';
$s3_bucket         = getenv('S3_BUCKET') ?: 'files';
$s3_access_key     = getenv('S3_ACCESS_KEY') ?: '';
$s3_secret_key     = getenv('S3_SECRET_KEY') ?: '';
$s3_use_path_style = filter_var(getenv('S3_USE_PATH_STYLE') !== false ? getenv('S3_USE_PATH_STYLE') : 'true', FILTER_VALIDATE_BOOLEAN); // MinIO=true
$s3_public_base    = rtrim((string)(getenv('S3_PUBLIC_BASE_URL') ?: ''), '/'); // optional public base for object URLs

$supabase_url = getenv('SUPABASE_URL');
$supabase_key = getenv('SUPABASE_KEY');
$admin_api_key = getenv('ADMIN_API_KEY');
$admin_password = getenv('ADMIN_PASSWORD') ?: $admin_api_key;
$abuse_email = getenv('ABUSE_EMAIL');
// For server-side Storage uploads prefer a Supabase service role key.
// Fallback keeps old setups working, but anon keys often fail without Storage insert policies.
$supabase_db_key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: $supabase_key;
$supabase_storage_key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: (getenv('SUPABASE_STORAGE_KEY') ?: $supabase_key);
$file_upload_bucket = getenv('FILE_UPLOAD_BUCKET') ?: 'files';
$file_upload_max_mb = (int)(getenv('FILE_UPLOAD_MAX_MB') ?: 100);
$screenshotone_access_key = getenv('SCREENSHOTONE_ACCESS_KEY') ?: getenv('SCREENSHOT_API_KEY');
$preview_edge_function_url = getenv('PREVIEW_EDGE_FUNCTION_URL') ?: (rtrim((string)$supabase_url, '/') . '/functions/v1/preview-render');
$preview_edge_secret = getenv('PREVIEW_EDGE_SECRET') ?: '';
$preview_edge_auth_key = getenv('PREVIEW_EDGE_AUTH_KEY') ?: (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: $supabase_key);

// Supabase credentials are only required when a Supabase driver is selected.
$needs_supabase = ($db_driver === 'supabase' || $storage_driver === 'supabase');
if (!$admin_api_key || ($needs_supabase && (!$supabase_url || !$supabase_key))) {
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

$LANG_META = [
    'de' => ['label' => 'Deutsch', 'flag' => '🇩🇪'],
    'en' => ['label' => 'English', 'flag' => '🇬🇧'],
];

$short_url = "";
$error = "";
$selected_domain = $available_domains[0];

// ---------------------------------------------------------
// RSS FEED CONFIGURATION
// ---------------------------------------------------------
define('FEED_TITLE', getenv('FEED_TITLE') ?: '0x79.one Feed');
define('FEED_LINK', getenv('FEED_LINK') ?: 'https://0x79.one');
define('FEED_DESCRIPTION', getenv('FEED_DESCRIPTION') ?: 'Latest updates and files from 0x79.one');
define('FEED_URL', getenv('FEED_URL') ?: 'https://0x79.one/rss');
define('IMAGE_DIR', getenv('IMAGE_DIR') ?: (__DIR__ . '/images'));


