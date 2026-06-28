<?php
declare(strict_types=1);


// Dispatches a PostgREST-style request to the active DB driver.
// Returns [$httpStatus, $responseBodyJson, $errorString] like the original.
function supabaseRequest($method, $url, $body = null) {
    global $db_driver;
    if (($db_driver ?? 'supabase') === 'postgres') {
        return pgRestRequest($method, $url, $body);
    }
    return supabaseHttpRequest($method, $url, $body);
}

// ---------------------------------------------------------
// POSTGRES DRIVER
// Translates the small PostgREST URL surface this app uses
// (eq filters, or/ilike search, select/order/limit/offset,
//  insert/update/delete) into parameterized SQL over PDO.
// ---------------------------------------------------------
function pgConnect() {
    global $pg_dsn, $pg_host, $pg_port, $pg_db, $pg_user, $pg_password;
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    if (!extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('DB_DRIVER=postgres requires the pdo_pgsql PHP extension.');
    }
    $dsn = $pg_dsn !== '' ? $pg_dsn : sprintf('pgsql:host=%s;port=%s;dbname=%s', $pg_host, $pg_port, $pg_db);
    $pdo = new PDO($dsn, $pg_user ?: null, $pg_password ?: null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// Convert a PHP value to a PDO-bindable value (arrays -> JSON, bools -> pg literal).
function pgBindValue($v) {
    if (is_array($v))  return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_bool($v))   return $v ? 'true' : 'false';
    if ($v === null)   return null;
    return (string)$v;
}

// Fetch all rows, casting pg native types to JSON-like PHP types (bool/int/float).
function pgFetchAllTyped($st) {
    $types = [];
    $n = $st->columnCount();
    for ($i = 0; $i < $n; $i++) {
        $m = $st->getColumnMeta($i);
        if (is_array($m) && isset($m['name'])) {
            $types[$m['name']] = strtolower((string)($m['native_type'] ?? ''));
        }
    }
    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        foreach ($r as $k => $v) {
            if ($v === null) continue;
            $t = $types[$k] ?? '';
            if ($t === 'bool') {
                $r[$k] = ($v === 't' || $v === true || $v === '1' || $v === 'true');
            } elseif (in_array($t, ['int2', 'int4', 'int8'], true)) {
                $r[$k] = (int)$v;
            } elseif (in_array($t, ['float4', 'float8', 'numeric'], true)) {
                $r[$k] = (float)$v;
            }
            // text/json/jsonb/timestamp/uuid stay as strings (matches PostgREST handling here)
        }
        $out[] = $r;
    }
    return $out;
}

function pgErrorHttp($e) {
    // 23505 = unique_violation -> 409 so create-with-retry loops behave like PostgREST.
    if ($e instanceof PDOException && isset($e->errorInfo[0]) && $e->errorInfo[0] === '23505') {
        return 409;
    }
    return 400;
}

function pgQuoteIdent($name) {
    return '"' . str_replace('"', '', (string)$name) . '"';
}

function pgRestRequest($method, $url, $body = null) {
    try {
        $pdo = pgConnect();
    } catch (Throwable $e) {
        return [500, json_encode(['message' => $e->getMessage()]), $e->getMessage()];
    }

    $parts = parse_url((string)$url);
    $path  = $parts['path'] ?? '';
    if (!preg_match('#/rest/v1/([a-zA-Z_][a-zA-Z0-9_]*)#', $path, $m)) {
        return [400, '[]', 'invalid_table'];
    }
    $table = pgQuoteIdent($m[1]);

    $q = [];
    parse_str($parts['query'] ?? '', $q);

    // WHERE: column=eq.VALUE filters + optional or=(col.ilike.*v*,...)
    $where  = [];
    $params = [];
    $pi = 0;
    $reserved = ['select', 'order', 'limit', 'offset', 'or', 'on_conflict'];
    foreach ($q as $col => $val) {
        if (in_array($col, $reserved, true)) continue;
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$col)) continue;
        if (is_string($val) && strncmp($val, 'eq.', 3) === 0) {
            $ph = ':w' . ($pi++);
            $where[] = pgQuoteIdent($col) . ' = ' . $ph;
            $params[$ph] = substr($val, 3);
        }
    }
    if (!empty($q['or']) && is_string($q['or'])) {
        $orStr = preg_replace('/^\((.*)\)$/s', '$1', trim($q['or']));
        $ors = [];
        foreach (explode(',', $orStr) as $cond) {
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\.ilike\.(.*)$/s', trim($cond), $mm)) {
                $ph = ':o' . ($pi++);
                $ors[] = pgQuoteIdent($mm[1]) . ' ILIKE ' . $ph;
                $params[$ph] = str_replace('*', '%', $mm[2]);
            }
        }
        if ($ors) $where[] = '(' . implode(' OR ', $ors) . ')';
    }
    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    try {
        if ($method === 'GET') {
            $cols = '*';
            if (!empty($q['select'])) {
                $sel = preg_replace('/[^a-zA-Z0-9_,]/', '', (string)$q['select']);
                $names = array_filter(explode(',', $sel));
                if ($names) $cols = implode(', ', array_map('pgQuoteIdent', $names));
            }
            $sql = "SELECT $cols FROM $table" . $whereSql;
            if (!empty($q['order'])) {
                $ords = [];
                foreach (explode(',', (string)$q['order']) as $o) {
                    if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)(?:\.(asc|desc))?$/', trim($o), $om)) {
                        $dir = (isset($om[2]) && strtolower($om[2]) === 'desc') ? 'DESC' : 'ASC';
                        $ords[] = pgQuoteIdent($om[1]) . ' ' . $dir;
                    }
                }
                if ($ords) $sql .= ' ORDER BY ' . implode(', ', $ords);
            }
            if (isset($q['limit'])  && is_numeric($q['limit']))  $sql .= ' LIMIT '  . (int)$q['limit'];
            if (isset($q['offset']) && is_numeric($q['offset'])) $sql .= ' OFFSET ' . (int)$q['offset'];

            $st = $pdo->prepare($sql);
            $st->execute($params);
            return [200, json_encode(pgFetchAllTyped($st), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ''];
        }

        if ($method === 'POST') {
            $row = is_array($body) ? $body : [];
            if ($row !== [] && array_is_list($row)) $row = $row[0] ?? [];
            $cols = [];
            $phs  = [];
            $ins  = [];
            $i = 0;
            foreach ($row as $c => $v) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$c)) continue;
                $ph = ':i' . ($i++);
                $cols[] = pgQuoteIdent($c);
                $phs[]  = $ph;
                $ins[$ph] = pgBindValue($v);
            }
            if (!$cols) return [400, json_encode(['message' => 'empty_insert']), 'empty_insert'];
            $sql = "INSERT INTO $table (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $phs) . ") RETURNING *";
            $st = $pdo->prepare($sql);
            $st->execute($ins);
            return [201, json_encode(pgFetchAllTyped($st), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ''];
        }

        if ($method === 'PATCH') {
            $row = is_array($body) ? $body : [];
            $sets = [];
            $i = 0;
            foreach ($row as $c => $v) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$c)) continue;
                $ph = ':s' . ($i++);
                $sets[] = pgQuoteIdent($c) . ' = ' . $ph;
                $params[$ph] = pgBindValue($v);
            }
            if (!$sets) return [400, json_encode(['message' => 'empty_update']), 'empty_update'];
            $sql = "UPDATE $table SET " . implode(', ', $sets) . $whereSql . " RETURNING *";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return [200, json_encode(pgFetchAllTyped($st), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ''];
        }

        if ($method === 'DELETE') {
            $sql = "DELETE FROM $table" . $whereSql;
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return [204, '', ''];
        }

        return [405, json_encode(['message' => 'method_not_allowed']), 'method_not_allowed'];
    } catch (Throwable $e) {
        return [pgErrorHttp($e), json_encode(['message' => $e->getMessage()]), $e->getMessage()];
    }
}

function supabaseHttpRequest($method, $url, $body = null) {
    global $supabase_key, $supabase_db_key;

    $key = $supabase_db_key ?: $supabase_key;

    $headers = [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json"
    ];

    if (in_array($method, ['POST', 'PATCH'], true)) {
        $headers[] = 'Prefer: return=representation';
    }

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [$http, $response, $error];
}

function fetchUserById($id) {
    global $supabase_url;
    $id = trim((string)$id);
    if ($id === '') return null;

    $url = $supabase_url . "/rest/v1/app_users?id=eq." . urlencode($id) . "&select=id,email,api_key_prefix,created_at&limit=1";
    [$http, $response, $error] = supabaseRequest('GET', $url);
    if ($error || $http < 200 || $http >= 300) return null;
    $data = json_decode($response, true);
    return (!empty($data) && isset($data[0]['id'])) ? $data[0] : null;
}

function fetchUserByEmail($email) {
    global $supabase_url;
    $email = normalizeEmail($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;

    $url = $supabase_url . "/rest/v1/app_users?email=eq." . urlencode($email) . "&select=id,email,password_hash,api_key_prefix,created_at&limit=1";
    [$http, $response, $error] = supabaseRequest('GET', $url);
    if ($error || $http < 200 || $http >= 300) return null;
    $data = json_decode($response, true);
    return (!empty($data) && isset($data[0]['id'])) ? $data[0] : null;
}
function createUserAccount($email, $password) {
    global $supabase_url;
    $email = normalizeEmail($email);
    $password = (string)$password;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'invalid_email', null, null];
    if (strlen($password) < 8) return [false, 'weak_password', null, null];
    if (fetchUserByEmail($email)) return [false, 'email_taken', null, null];

    $apiKey = generateUserApiKey();
    $payload = [
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'api_key_hash' => userApiKeyHash($apiKey),
        'api_key_prefix' => substr($apiKey, 0, 10),
    ];

    [$http, $response, $error] = supabaseRequest('POST', $supabase_url . '/rest/v1/app_users', $payload);
    if ($error || $http < 200 || $http >= 300) return [false, 'save_failed', null, null];
    $data = json_decode($response, true);
    $user = is_array($data) && isset($data[0]) ? $data[0] : null;
    if (!$user || empty($user['id'])) return [false, 'save_failed', null, null];

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['last_api_key'] = $apiKey;
    return [true, null, $user, $apiKey];
}

function loginUserAccount($email, $password) {
    $user = fetchUserByEmail($email);
    if (!$user || empty($user['password_hash']) || !password_verify((string)$password, (string)$user['password_hash'])) {
        return [false, 'invalid_login', null];
    }

    $_SESSION['user_id'] = $user['id'];
    return [true, null, $user];
}

function regenerateUserApiKey($userId) {
    global $supabase_url;
    $userId = trim((string)$userId);
    if ($userId === '') return [false, null];

    $apiKey = generateUserApiKey();
    $url = $supabase_url . "/rest/v1/app_users?id=eq." . urlencode($userId);
    [$http, $response, $error] = supabaseRequest('PATCH', $url, [
        'api_key_hash' => userApiKeyHash($apiKey),
        'api_key_prefix' => substr($apiKey, 0, 10),
    ]);
    if ($error || $http < 200 || $http >= 300) return [false, null];
    $_SESSION['last_api_key'] = $apiKey;
    return [true, $apiKey];
}

function fetchUserLinks($userId, $limit = 50, $offset = 0) {
    global $supabase_url;
    $userId = trim((string)$userId);
    $limit = max(1, min((int)$limit, 100));
    $offset = max(0, (int)$offset);
    $select = 'id,long_url,short_code,created_at,expires_at,click_count,max_clicks,password_hash,owner_user_id,preview_enabled';
    $url = $supabase_url . "/rest/v1/urls?owner_user_id=eq." . urlencode($userId)
        . "&select=" . urlencode($select)
        . "&order=created_at.desc&limit=" . urlencode((string)$limit)
        . "&offset=" . urlencode((string)$offset);
    [$http, $response, $error] = supabaseRequest('GET', $url);
    if ($error || $http < 200 || $http >= 300) return [false, []];
    $rows = json_decode($response, true);
    return [true, is_array($rows) ? $rows : []];
}

function fetchUserPastes($userId, $limit = 50, $offset = 0) {
    global $supabase_url;
    $userId = trim((string)$userId);
    $limit = max(1, min((int)$limit, 100));
    $offset = max(0, (int)$offset);
    $select = 'id,paste_code,created_at,expires_at,view_count,max_views,password_hash,owner_user_id';
    $url = $supabase_url . "/rest/v1/pastes?owner_user_id=eq." . urlencode($userId)
        . "&select=" . urlencode($select)
        . "&order=created_at.desc&limit=" . urlencode((string)$limit)
        . "&offset=" . urlencode((string)$offset);
    [$http, $response, $error] = supabaseRequest('GET', $url);
    if ($error || $http < 200 || $http >= 300) return [false, []];
    $rows = json_decode($response, true);
    return [true, is_array($rows) ? $rows : []];
}

function deleteOwnedLinkById($id, $userId) {
    global $supabase_url;
    $id = trim((string)$id);
    $userId = trim((string)$userId);
    if ($id === '' || $userId === '') return false;
    $url = $supabase_url . "/rest/v1/urls?id=eq." . urlencode($id) . "&owner_user_id=eq." . urlencode($userId);
    [$http, $response, $error] = supabaseRequest('DELETE', $url);
    return !$error && $http >= 200 && $http < 300;
}

// Update a link the user owns. Only the provided fields are changed.
function updateOwnedLink($id, $userId, $fields) {
    global $supabase_url;
    $id = trim((string)$id);
    $userId = trim((string)$userId);
    if ($id === '' || $userId === '') return [false, 'invalid'];

    $payload = [];
    if (array_key_exists('long_url', $fields)) {
        $u = trim((string)$fields['long_url']);
        if ($u !== '') {
            if (!isAllowedShortenerTarget($u)) return [false, 'invalid_url'];
            $payload['long_url'] = $u;
        }
    }
    if (array_key_exists('expires_at', $fields)) {
        $exp = parseOptionalExpiresAt($fields['expires_at']); // empty -> null (clears expiry)
        if ($exp !== null && strtotime($exp) <= time()) return [false, 'invalid_expiry'];
        $payload['expires_at'] = $exp;
    }
    if (array_key_exists('max_clicks', $fields)) {
        $payload['max_clicks'] = parseOptionalMaxClicks($fields['max_clicks']);
    }
    if (!empty($fields['clear_password'])) {
        $payload['password_hash'] = null;
    } elseif (array_key_exists('password', $fields) && (string)$fields['password'] !== '') {
        $payload['password_hash'] = password_hash((string)$fields['password'], PASSWORD_DEFAULT);
    }

    if (empty($payload)) return [false, 'no_changes'];

    $url = $supabase_url . "/rest/v1/urls?id=eq." . urlencode($id) . "&owner_user_id=eq." . urlencode($userId);
    [$http, $response, $error] = supabaseRequest('PATCH', $url, $payload);
    return (!$error && $http >= 200 && $http < 300) ? [true, null] : [false, 'save_failed'];
}

function deleteOwnedPasteById($id, $userId) {
    global $supabase_url;
    $id = trim((string)$id);
    $userId = trim((string)$userId);
    if ($id === '' || $userId === '') return false;
    $url = $supabase_url . "/rest/v1/pastes?id=eq." . urlencode($id) . "&owner_user_id=eq." . urlencode($userId);
    [$http, $response, $error] = supabaseRequest('DELETE', $url);
    return !$error && $http >= 200 && $http < 300;
}

// ---------------------------------------------------------
// S3 / MinIO STORAGE DRIVER (AWS Signature V4, streaming PUT)
// ---------------------------------------------------------
function s3PublicUrl($key) {
    global $s3_public_base, $s3_endpoint, $s3_bucket, $s3_use_path_style;
    $encodedKey = str_replace('%2F', '/', rawurlencode((string)$key));
    if ($s3_public_base !== '') {
        return $s3_public_base . '/' . $encodedKey;
    }
    if ($s3_use_path_style) {
        return $s3_endpoint . '/' . rawurlencode($s3_bucket) . '/' . $encodedKey;
    }
    $ep = parse_url($s3_endpoint);
    $scheme = $ep['scheme'] ?? 'http';
    $host = $ep['host'] ?? '';
    if (isset($ep['port'])) $host .= ':' . $ep['port'];
    return $scheme . '://' . rawurlencode($s3_bucket) . '.' . $host . '/' . $encodedKey;
}

function s3PutObject($key, $tmpPath, $mime, $size) {
    global $s3_endpoint, $s3_region, $s3_bucket, $s3_access_key, $s3_secret_key, $s3_use_path_style;

    if ($s3_endpoint === '' || $s3_access_key === '' || $s3_secret_key === '') {
        return [false, 's3_not_configured'];
    }

    $ep = parse_url($s3_endpoint);
    $scheme = $ep['scheme'] ?? 'http';
    $host = $ep['host'] ?? '';
    if (isset($ep['port'])) $host .= ':' . $ep['port'];

    $encodedKey = str_replace('%2F', '/', rawurlencode((string)$key));
    if ($s3_use_path_style) {
        $sigHost      = $host;
        $canonicalUri = '/' . rawurlencode($s3_bucket) . '/' . $encodedKey;
    } else {
        $sigHost      = rawurlencode($s3_bucket) . '.' . $host;
        $canonicalUri = '/' . $encodedKey;
    }
    $url = $scheme . '://' . $sigHost . $canonicalUri;

    $amzDate     = gmdate('Ymd\THis\Z');
    $dateStamp   = gmdate('Ymd');
    $payloadHash = 'UNSIGNED-PAYLOAD';

    $canonicalHeaders = "host:$sigHost\nx-amz-content-sha256:$payloadHash\nx-amz-date:$amzDate\n";
    $signedHeaders    = 'host;x-amz-content-sha256;x-amz-date';
    $canonicalRequest = "PUT\n$canonicalUri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

    $algo  = 'AWS4-HMAC-SHA256';
    $scope = "$dateStamp/$s3_region/s3/aws4_request";
    $stringToSign = "$algo\n$amzDate\n$scope\n" . hash('sha256', $canonicalRequest);

    $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $s3_secret_key, true);
    $kRegion  = hash_hmac('sha256', $s3_region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $authorization = "$algo Credential=$s3_access_key/$scope, SignedHeaders=$signedHeaders, Signature=$signature";

    $fp = fopen($tmpPath, 'rb');
    if (!$fp) return [false, 'tmp_file_read_failed'];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Host: $sigHost",
        "x-amz-content-sha256: $payloadHash",
        "x-amz-date: $amzDate",
        "Authorization: $authorization",
        "Content-Type: $mime",
        "Content-Length: " . (int)$size,
        "Expect:",
    ]);
    curl_setopt($ch, CURLOPT_UPLOAD, true);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, (int)$size);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 131072);
    curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($error || $http < 200 || $http >= 300) {
        return [false, storageErrorCodeFromResponse($http, $response, $error)];
    }
    return [true, null];
}

function uploadToSupabaseStorage($file) {
    global $supabase_url, $supabase_storage_key, $file_upload_bucket, $file_upload_max_mb;
    global $storage_driver, $s3_bucket;

    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [false, 'file_missing', null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadCode = (int)$file['error'];

        if (in_array($uploadCode, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return [false, 'file_size', null];
        }

        if ($uploadCode === UPLOAD_ERR_PARTIAL) {
            return [false, 'upload_partial', null];
        }

        if (in_array($uploadCode, [UPLOAD_ERR_NO_TMP_DIR], true)) {
            return [false, 'upload_tmp', null];
        }

        if (in_array($uploadCode, [UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION], true)) {
            return [false, 'upload_write', null];
        }

        return [false, 'php_upload_error_' . $uploadCode, null];
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return [false, 'tmp_file_invalid', null];
    }

    $maxBytes = max(1, $file_upload_max_mb) * 1024 * 1024;
    if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxBytes) {
        return [false, 'file_size', null];
    }

    $ext = safeUploadExtension($file['name'] ?? '');
    if (!$ext) {
        return [false, 'file_type', null];
    }

    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
    }

    if (!isAllowedUploadMime($mime, $ext)) {
        return [false, 'file_type', null];
    }

    $path = gmdate('Y/m/d') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;

    // --- S3 / MinIO driver ---
    if (($storage_driver ?? 'supabase') === 's3') {
        [$okS3, $s3Err] = s3PutObject($path, $file['tmp_name'], $mime, (int)$file['size']);
        if (!$okS3) {
            return [false, $s3Err ?: 'upload_failed', null];
        }
        return [true, null, [
            'bucket' => $s3_bucket,
            'path' => $path,
            'public_url' => s3PublicUrl($path),
            'mime' => $mime,
            'size' => (int)$file['size'],
            'original_name' => (string)($file['name'] ?? ''),
        ]];
    }

    // --- Supabase Storage driver (default) ---
    $bucket = preg_replace('/[^a-zA-Z0-9._-]/', '', (string)$file_upload_bucket) ?: 'files';
    $uploadUrl = rtrim($supabase_url, '/') . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path));

    $headers = [
        "apikey: $supabase_storage_key",
        "Authorization: Bearer $supabase_storage_key",
        "Content-Type: $mime",
        "x-upsert: false",
        "Cache-Control: public, max-age=31536000"
    ];

    // Stream directly — no full file in RAM
    $fileSize = (int)$file['size'];
    $fp = fopen($file['tmp_name'], 'rb');
    if (!$fp) {
        return [false, 'tmp_file_read_failed', null];
    }
    $headers[] = 'Content-Length: ' . $fileSize;
    $headers[] = 'Expect:';

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_UPLOAD, true);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 131072);
    curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($error || $http < 200 || $http >= 300) {
        // Keep the exact Supabase/cURL reason visible in the UI.
        // This avoids the useless generic "upload fehlgeschlagen" message.
        return [false, storageErrorCodeFromResponse($http, $response, $error), null];
    }

    $publicUrl = rtrim($supabase_url, '/') . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path));

    return [true, null, [
        'bucket' => $bucket,
        'path' => $path,
        'public_url' => $publicUrl,
        'mime' => $mime,
        'size' => (int)$file['size'],
        'original_name' => (string)($file['name'] ?? '')
    ]];
}
function fetchLinkByCode($code) {
    global $supabase_url;

    if (!isValidCode($code)) return null;

    $select = 'id,long_url,short_code,created_at,expires_at,click_count,max_clicks,password_hash,preview_enabled';
    $url = $supabase_url . "/rest/v1/urls?short_code=eq." . urlencode($code) . "&select=" . urlencode($select) . "&limit=1";

    [$http, $response, $error] = supabaseRequest('GET', $url);

    if ($error || $http < 200 || $http >= 300) return null;

    $data = json_decode($response, true);

    return (!empty($data) && isset($data[0]['long_url'])) ? $data[0] : null;
}

function fetchLongUrlByCode($code) {
    $row = fetchLinkByCode($code);
    return $row && !isExpiredRow($row) && !isBurnedRow($row) ? $row : null;
}

function incrementClickCount($row) {
    global $supabase_url;

    if (empty($row['id'])) return false;

    $next = isset($row['click_count']) ? ((int)$row['click_count'] + 1) : 1;
    $url = $supabase_url . "/rest/v1/urls?id=eq." . urlencode((string)$row['id']);

    [$http, $response, $error] = supabaseRequest('PATCH', $url, ['click_count' => $next]);

    return !$error && $http >= 200 && $http < 300;
}

function createShortLink($long_url, $domain, $password = '', $expires_at = '', $max_clicks = '', $custom_code = '', $owner_user_id = null, $preview_enabled = false) {
    global $supabase_url, $available_domains;

    $long_url = trim((string)$long_url);
    $domain = in_array($domain, $available_domains, true) ? $domain : $available_domains[0];
    $expires_at = guestDefaultExpiresAt($owner_user_id, parseOptionalExpiresAt($expires_at));
    $max_clicks = parseOptionalMaxClicks($max_clicks);
    $password = trim((string)$password);
    $custom_code = trim((string)$custom_code);
    $preview_enabled = !empty($preview_enabled);

    if ($custom_code !== '' && !isValidCustomCode($custom_code)) {
        return [false, 'invalid_alias', null];
    }

    if ($custom_code !== '') {
        $existing = fetchLinkByCode($custom_code);
        if ($existing) {
            return [false, 'alias_taken', null];
        }
    }

    if (!isAllowedShortenerTarget($long_url)) {
        return [false, 'invalid_url', null];
    }

    if ($expires_at !== null && strtotime($expires_at) <= time()) {
        return [false, 'invalid_expiry', null];
    }

    $tries = $custom_code !== '' ? 1 : 5;

    for ($try = 0; $try < $tries; $try++) {
        $code = $custom_code !== '' ? $custom_code : makeShortCode(6);

        $payload = [
            'long_url' => $long_url,
            'short_code' => $code,
            'click_count' => 0,
            'expires_at' => $expires_at,
            'max_clicks' => $max_clicks,
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'owner_user_id' => $owner_user_id ?: null,
            'preview_enabled' => $preview_enabled,
        ];

        $url = $supabase_url . "/rest/v1/urls";

        [$http, $response, $error] = supabaseRequest('POST', $url, $payload);

        if (!$error && ($http === 201 || $http === 200)) {
            return [true, null, [
                'long_url' => $long_url,
                'short_code' => $code,
                'short_url' => 'https://' . $domain . '/' . $code,
                'domain' => $domain,
                'expires_at' => $expires_at,
                'max_clicks' => $max_clicks,
                'has_password' => $password !== '',
                'click_count' => 0,
                'preview_enabled' => $preview_enabled,
            ]];
        }
    }

    return [false, 'save_failed', null];
}



function uploadMusicImageFromField($fieldName) {
    $file = $_FILES[$fieldName] ?? null;

    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [true, null, null];
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)) {
        return [false, 'music_image_type', null];
    }

    [$okUpload, $uploadErr, $uploaded] = uploadToSupabaseStorage($file);
    if (!$okUpload) {
        return [false, $uploadErr ?: 'music_image_upload_failed', null];
    }

    $mime = strtolower((string)($uploaded['mime'] ?? ''));
    if (strpos($mime, 'image/') !== 0) {
        return [false, 'music_image_type', null];
    }

    return [true, null, $uploaded['public_url'] ?? null];
}

function fetchMusicPromoByCode($code) {
    global $supabase_url;

    if (!isValidCode($code)) return null;

    $select = 'id,music_code,title,artist,cover_url,banner_url,links,created_at,expires_at,view_count,owner_user_id';
    $url = $supabase_url . "/rest/v1/music_promos?music_code=eq." . urlencode($code) . "&select=" . urlencode($select) . "&limit=1";
    [$http, $response, $error] = supabaseRequest('GET', $url);
    if ($error || $http < 200 || $http >= 300) return null;
    $rows = json_decode($response, true);
    return (!empty($rows) && isset($rows[0]['id'])) ? $rows[0] : null;
}

function incrementMusicPromoViews($row) {
    global $supabase_url;
    if (empty($row['id'])) return false;
    $next = isset($row['view_count']) ? ((int)$row['view_count'] + 1) : 1;
    $url = $supabase_url . "/rest/v1/music_promos?id=eq." . urlencode((string)$row['id']);
    [$http, $response, $error] = supabaseRequest('PATCH', $url, ['view_count' => $next]);
    return !$error && $http >= 200 && $http < 300;
}

function createMusicPromo($title, $artist, $musicLinksInput, $domain, $password = '', $expires_at = '', $max_clicks = '', $custom_code = '', $owner_user_id = null, $cover_url = '', $banner_url = '') {
    global $supabase_url, $available_domains;

    $title = trim((string)$title);
    $artist = trim((string)$artist);
    $domain = in_array($domain, $available_domains, true) ? $domain : $available_domains[0];
    $cover_url = trim((string)$cover_url);
    $banner_url = trim((string)$banner_url);

    if ($title === '' || mb_strlen($title) > 160) return [false, 'music_title_invalid', null];
    if (mb_strlen($artist) > 160) return [false, 'music_artist_invalid', null];
    if (!isValidMusicImageUrl($cover_url) || !isValidMusicImageUrl($banner_url)) return [false, 'music_image_url_invalid', null];

    [$okLinks, $linksErr, $links] = normalizeMusicLinks($musicLinksInput);
    if (!$okLinks) return [false, $linksErr, null];

    $promoExpiresAt = guestDefaultExpiresAt($owner_user_id, parseOptionalExpiresAt($expires_at));
    if ($promoExpiresAt !== null && strtotime($promoExpiresAt) <= time()) {
        return [false, 'invalid_expiry', null];
    }

    for ($try = 0; $try < 5; $try++) {
        $musicCode = makeShortCode(8);
        $payload = [
            'music_code' => $musicCode,
            'title' => $title,
            'artist' => $artist !== '' ? $artist : null,
            'cover_url' => $cover_url !== '' ? $cover_url : null,
            'banner_url' => $banner_url !== '' ? $banner_url : null,
            'links' => $links,
            'expires_at' => $promoExpiresAt,
            'view_count' => 0,
            'owner_user_id' => $owner_user_id ?: null,
        ];

        [$http, $response, $error] = supabaseRequest('POST', $supabase_url . '/rest/v1/music_promos', $payload);
        if ($error || !in_array($http, [200, 201], true)) continue;

        $promoUrl = 'https://' . $domain . '/music/' . $musicCode;
        [$okShort, $shortErr, $short] = createShortLink($promoUrl, $domain, $password, $expires_at, $max_clicks, $custom_code, $owner_user_id, false);
        if (!$okShort) return [false, $shortErr, null];

        return [true, null, [
            'music_code' => $musicCode,
            'music_url' => $promoUrl,
            'short_code' => $short['short_code'],
            'short_url' => $short['short_url'],
            'domain' => $short['domain'],
            'expires_at' => $short['expires_at'],
            'max_clicks' => $short['max_clicks'],
            'has_password' => $short['has_password'],
            'cover_url' => $cover_url !== '' ? $cover_url : null,
            'banner_url' => $banner_url !== '' ? $banner_url : null,
            'links' => $links,
        ]];
    }

    return [false, 'music_save_failed', null];
}


function fetchPasteByCode($code) {
    global $supabase_url;

    if (!isValidCode($code)) return null;

    $select = 'id,paste_code,content,created_at,expires_at,view_count,max_views,password_hash';
    $url = $supabase_url . "/rest/v1/pastes?paste_code=eq." . urlencode($code) . "&select=" . urlencode($select) . "&limit=1";

    [$http, $response, $error] = supabaseRequest('GET', $url);

    if ($error || $http < 200 || $http >= 300) return null;

    $data = json_decode($response, true);

    return (!empty($data) && isset($data[0]['content'])) ? $data[0] : null;
}

function incrementPasteView($row) {
    global $supabase_url;

    if (empty($row['id'])) return false;

    $next = isset($row['view_count']) ? ((int)$row['view_count'] + 1) : 1;
    $url = $supabase_url . "/rest/v1/pastes?id=eq." . urlencode((string)$row['id']);

    [$http, $response, $error] = supabaseRequest('PATCH', $url, ['view_count' => $next]);

    return !$error && $http >= 200 && $http < 300;
}

function createPaste($content, $domain, $password = '', $expires_at = '', $max_views = '', $custom_code = '', $owner_user_id = null) {
    global $supabase_url, $available_domains;

    $content = (string)$content;
    $domain = in_array($domain, $available_domains, true) ? $domain : $available_domains[0];
    $expires_at = guestDefaultExpiresAt($owner_user_id, parseOptionalExpiresAt($expires_at));
    $max_views = parseOptionalMaxClicks($max_views);
    $password = trim((string)$password);
    $custom_code = trim((string)$custom_code);

    if (trim($content) === '') {
        return [false, 'empty_paste', null];
    }

    // 200 KB keeps pastes fast and prevents huge DB rows.
    if (strlen($content) > 200 * 1024) {
        return [false, 'paste_too_large', null];
    }

    if ($custom_code !== '' && !isValidCustomCode($custom_code)) {
        return [false, 'invalid_alias', null];
    }

    if ($expires_at !== null && strtotime($expires_at) <= time()) {
        return [false, 'invalid_expiry', null];
    }

    if ($custom_code !== '') {
        if (fetchLinkByCode($custom_code) || fetchPasteByCode($custom_code)) {
            return [false, 'alias_taken', null];
        }
    }

    $tries = $custom_code !== '' ? 1 : 8;

    for ($try = 0; $try < $tries; $try++) {
        $code = $custom_code !== '' ? $custom_code : makeShortCode(6);

        if ($custom_code === '' && (fetchLinkByCode($code) || fetchPasteByCode($code))) {
            continue;
        }

        $payload = [
            'paste_code' => $code,
            'content' => $content,
            'view_count' => 0,
            'expires_at' => $expires_at,
            'max_views' => $max_views,
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'owner_user_id' => $owner_user_id ?: null,
        ];

        $url = $supabase_url . "/rest/v1/pastes";
        [$http, $response, $error] = supabaseRequest('POST', $url, $payload);

        if (!$error && ($http === 201 || $http === 200)) {
            return [true, null, [
                'paste_code' => $code,
                'short_url' => 'https://' . $domain . '/' . $code,
                'raw_url' => 'https://' . $domain . '/raw/' . $code,
                'domain' => $domain,
                'expires_at' => $expires_at,
                'max_views' => $max_views,
                'has_password' => $password !== '',
                'view_count' => 0,
            ]];
        }
    }

    return [false, 'save_failed', null];
}

function fetchAdminLinks($limit = 25, $offset = 0, $search = '') {
    global $supabase_url;

    $limit = max(1, min((int)$limit, 100));
    $offset = max(0, (int)$offset);
    $search = adminCleanSearch($search);
    $fetchLimit = $limit + 1;

    $select = 'id,long_url,short_code,created_at,expires_at,click_count,max_clicks,password_hash,preview_enabled';

    $url = $supabase_url
        . "/rest/v1/urls?"
        . "select=" . urlencode($select)
        . "&order=created_at.desc"
        . "&limit=" . urlencode((string)$fetchLimit)
        . "&offset=" . urlencode((string)$offset);

    if ($search !== '') {
        $needle = supabaseIlikeValue($search);
        $url .= '&or=' . rawurlencode('(short_code.ilike.' . $needle . ',long_url.ilike.' . $needle . ')');
    }

    [$http, $response, $curlError] = supabaseRequest('GET', $url);

    if ($curlError || $http < 200 || $http >= 300) {
        return [false, [], $http ?: 500, false];
    }

    $rows = json_decode($response, true);

    if (!is_array($rows)) {
        return [false, [], 500, false];
    }

    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $limit);
    }

    $host = cleanHost($_SERVER['HTTP_HOST'] ?? '0x79.one');

    return [true, array_map(function ($row) use ($host) {
        return normalizeLinkRow($row, $host);
    }, $rows), 200, $hasMore];
}


function fetchAdminPastes($limit = 25, $offset = 0, $search = '') {
    global $supabase_url;

    $limit = max(1, min((int)$limit, 100));
    $offset = max(0, (int)$offset);
    $search = adminCleanSearch($search);
    $fetchLimit = $limit + 1;

    $select = 'id,paste_code,content,created_at,expires_at,view_count,max_views,password_hash';
    $url = $supabase_url
        . "/rest/v1/pastes?"
        . "select=" . urlencode($select)
        . "&order=created_at.desc"
        . "&limit=" . urlencode((string)$fetchLimit)
        . "&offset=" . urlencode((string)$offset);

    if ($search !== '') {
        $needle = supabaseIlikeValue($search);
        $url .= '&or=' . rawurlencode('(paste_code.ilike.' . $needle . ',content.ilike.' . $needle . ')');
    }

    [$http, $response, $curlError] = supabaseRequest('GET', $url);

    if ($curlError || $http < 200 || $http >= 300) {
        return [false, [], $http ?: 500, false];
    }

    $rows = json_decode($response, true);
    if (!is_array($rows)) {
        return [false, [], 500, false];
    }

    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $limit);
    }

    return [true, $rows, 200, $hasMore];
}


function fetchLinkById($id) {
    global $supabase_url;

    $id = trim((string)$id);
    if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
        return null;
    }

    $select = 'id,long_url,short_code,created_at,expires_at,click_count,max_clicks,password_hash,preview_enabled';
    $url = $supabase_url . "/rest/v1/urls?id=eq." . urlencode($id) . "&select=" . urlencode($select) . "&limit=1";

    [$http, $response, $error] = supabaseRequest('GET', $url);
    if ($error || $http < 200 || $http >= 300) return null;

    $data = json_decode($response, true);
    return (!empty($data) && isset($data[0]['id'])) ? $data[0] : null;
}

function updateLinkById($id, $long_url, $short_code, $password, $remove_password, $expires_at, $max_clicks) {
    global $supabase_url;

    $row = fetchLinkById($id);
    if (!$row) return [false, 404];

    $long_url = trim((string)$long_url);
    $short_code = trim((string)$short_code);
    if (!isAllowedShortenerTarget($long_url)) {
        return [false, 400];
    }

    if (!isValidCustomCode($short_code)) {
        return [false, 400];
    }

    if ($short_code !== ($row['short_code'] ?? '')) {
        $existing = fetchLinkByCode($short_code);
        if ($existing && (string)($existing['id'] ?? '') !== (string)$id) {
            return [false, 409];
        }
    }

    $parsedExpires = parseOptionalExpiresAt($expires_at);
    $parsedMaxClicks = parseOptionalMaxClicks($max_clicks);

    $payload = [
        'long_url' => $long_url,
        'short_code' => $short_code,
        'expires_at' => $parsedExpires,
        'max_clicks' => $parsedMaxClicks,
    ];

    $password = trim((string)$password);
    if ($remove_password) {
        $payload['password_hash'] = null;
    } elseif ($password !== '') {
        $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $url = $supabase_url . "/rest/v1/urls?id=eq." . urlencode((string)$id);
    [$http, $response, $error] = supabaseRequest('PATCH', $url, $payload);

    return [!$error && $http >= 200 && $http < 300, $http ?: 500];
}

function deleteLinkByCode($code) {
    global $supabase_url;

    if (!isValidCode($code)) {
        return [false, 400];
    }

    $url = $supabase_url . "/rest/v1/urls?short_code=eq." . urlencode($code);
    [$http, $response, $error] = supabaseRequest('DELETE', $url);

    return [!$error && $http >= 200 && $http < 300, $http ?: 500];
}

function deleteLinkById($id) {
    global $supabase_url;

    $id = trim((string)$id);
    if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
        return [false, 400];
    }

    $url = $supabase_url . "/rest/v1/urls?id=eq." . urlencode($id);
    [$http, $response, $error] = supabaseRequest('DELETE', $url);

    return [!$error && $http >= 200 && $http < 300, $http ?: 500];
}

function deletePasteById($id) {
    global $supabase_url;

    $id = trim((string)$id);
    if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
        return [false, 400];
    }

    $url = $supabase_url . "/rest/v1/pastes?id=eq." . urlencode($id);
    [$http, $response, $error] = supabaseRequest('DELETE', $url);

    return [!$error && $http >= 200 && $http < 300, $http ?: 500];
}


function deleteAbuseReportById($id) {
    global $supabase_url;

    $id = trim((string)$id);
    if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
        return [false, 400];
    }

    $url = $supabase_url . "/rest/v1/abuse_reports?id=eq." . urlencode($id);
    [$http, $response, $error] = supabaseRequest('DELETE', $url);

    return [!$error && $http >= 200 && $http < 300, $http ?: 500];
}

function createAbuseReport($reported_link, $reason) {
    global $supabase_url;

    $reported_link = trim((string)$reported_link);
    $reason = trim((string)$reason);

    if ($reported_link === '' || $reason === '') {
        return [false, 'missing_fields'];
    }

    // Abuse-Meldungen speichern bewusst keine IP-Adresse.
    $payload = [
        'reported_link' => mb_substr($reported_link, 0, 2000),
        'reason' => mb_substr($reason, 0, 5000),
        'status' => 'open',
    ];

    $url = $supabase_url . "/rest/v1/abuse_reports";
    [$http, $response, $error] = supabaseRequest('POST', $url, $payload);

    if ($error || $http < 200 || $http >= 300) {
        return [false, 'save_failed'];
    }

    return [true, null];
}

function fetchAdminAbuseReports($limit = 25, $offset = 0, $search = '') {
    global $supabase_url;

    $limit = max(1, min((int)$limit, 100));
    $offset = max(0, (int)$offset);
    $search = adminCleanSearch($search);
    $fetchLimit = $limit + 1;

    $select = 'id,reported_link,reason,status,created_at';
    $url = $supabase_url
        . "/rest/v1/abuse_reports?"
        . "select=" . urlencode($select)
        . "&order=created_at.desc"
        . "&limit=" . urlencode((string)$fetchLimit)
        . "&offset=" . urlencode((string)$offset);

    if ($search !== '') {
        $needle = supabaseIlikeValue($search);
        $url .= '&or=' . rawurlencode('(reported_link.ilike.' . $needle . ',reason.ilike.' . $needle . ',status.ilike.' . $needle . ')');
    }

    [$http, $response, $curlError] = supabaseRequest('GET', $url);

    if ($curlError || $http < 200 || $http >= 300) {
        return [false, [], $http ?: 500, false];
    }

    $rows = json_decode($response, true);

    if (!is_array($rows)) {
        return [false, [], 500, false];
    }

    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $limit);
    }

    return [true, array_map('normalizeAbuseReportRow', $rows), 200, $hasMore];
}

function fetchRssPosts($limit = 50) {
    global $supabase_url;
    $limit = max(1, min((int)$limit, 100));
    $url = $supabase_url . "/rest/v1/posts?select=id,title,description,image,pub_date&order=pub_date.desc&limit=" . $limit;
    [$http, $response, $error] = supabaseRequest('GET', $url);
    if ($error || $http < 200 || $http >= 300) {
        return [];
    }
    $rows = json_decode($response, true);
    return is_array($rows) ? $rows : [];
}

function fetchAdminPosts($limit = 25, $offset = 0, $search = '') {
    global $supabase_url;

    $limit = max(1, min((int)$limit, 100));
    $offset = max(0, (int)$offset);
    $search = adminCleanSearch($search);
    $fetchLimit = $limit + 1;

    $select = 'id,title,description,image,pub_date';
    $url = $supabase_url
        . "/rest/v1/posts?"
        . "select=" . urlencode($select)
        . "&order=pub_date.desc"
        . "&limit=" . urlencode((string)$fetchLimit)
        . "&offset=" . urlencode((string)$offset);

    if ($search !== '') {
        $needle = supabaseIlikeValue($search);
        $url .= '&or=' . rawurlencode('(title.ilike.' . $needle . ',description.ilike.' . $needle . ')');
    }

    [$http, $response, $curlError] = supabaseRequest('GET', $url);

    if ($curlError || $http < 200 || $http >= 300) {
        return [false, [], $http ?: 500, false];
    }

    $rows = json_decode($response, true);
    if (!is_array($rows)) {
        return [false, [], 500, false];
    }

    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $limit);
    }

    return [true, $rows, 200, $hasMore];
}

function createPost($title, $description, $image = null, $pub_date = null) {
    global $supabase_url;

    $title = trim((string)$title);
    $description = trim((string)$description);
    if ($title === '') {
        return [false, 'title_required'];
    }

    if ($pub_date === null) {
        $pub_date = time();
    } else {
        $pub_date = (int)$pub_date;
    }

    $payload = [
        'title' => $title,
        'description' => $description,
        'image' => $image ? trim((string)$image) : null,
        'pub_date' => $pub_date,
    ];

    $url = $supabase_url . "/rest/v1/posts";
    [$http, $response, $error] = supabaseRequest('POST', $url, $payload);

    if ($error || $http < 200 || $http >= 300) {
        return [false, 'save_failed'];
    }

    return [true, null];
}

function deletePostById($id) {
    global $supabase_url;
    $id = trim((string)$id);
    if ($id === '') return [false, 400];

    $url = $supabase_url . "/rest/v1/posts?id=eq." . urlencode($id);
    [$http, $response, $error] = supabaseRequest('DELETE', $url);

    if ($error || $http < 200 || $http >= 300) {
        return [false, $http ?: 500];
    }
    return [true, 200];
}

function fetchPostById($id) {
    global $supabase_url;
    $id = (int)$id;
    $url = $supabase_url . "/rest/v1/posts?id=eq." . $id . "&select=id,title,description,image,pub_date&limit=1";
    [$http, $response, $error] = supabaseRequest('GET', $url);
    if ($error || $http < 200 || $http >= 300) return null;
    $data = json_decode($response, true);
    return (!empty($data) && isset($data[0]['id'])) ? $data[0] : null;
}



