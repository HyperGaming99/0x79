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
        return [500, json_encode(['message' => 'database connection error']), 'db_connection_error'];
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
        return [pgErrorHttp($e), json_encode(['message' => 'query error']), 'query_error'];
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

// Best-effort click logging. Never throws / blocks the redirect on failure
// (e.g. if the link_clicks table does not exist).
function logLinkClick($code, $referrerHost, $device, $country) {
    global $supabase_url;
    $code = trim((string)$code);
    if ($code === '') return;
    try {
        supabaseRequest('POST', $supabase_url . '/rest/v1/link_clicks', [
            'short_code'    => $code,
            'referrer_host' => $referrerHost !== '' ? $referrerHost : null,
            'device'        => $device !== '' ? $device : null,
            'country'       => $country !== '' ? $country : null,
        ]);
    } catch (Throwable $e) {
        // ignore — analytics is non-critical
    }
}

// Recent click events for a code (newest first), capped. Empty on any error.
// Record one privacy-friendly landing-page visitor per month. Raw IP and
// User-Agent values never leave this process; only a keyed monthly hash is
// stored. Bots are excluded and database failures never block the page.
function logMonthlyLandingVisit() {
    global $supabase_url, $supabase_key, $supabase_db_key, $admin_api_key, $admin_password, $db_driver;

    $month = gmdate('Y-m-01');
    $sessionKey = 'analytics_landing_month_v2';
    if (($_SESSION[$sessionKey] ?? '') === $month) return;

    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '' || detectDeviceType($ua) === 'bot') return;

    $salt = trim((string)(getenv('ANALYTICS_SALT') ?: ''));
    if ($salt === '') {
        // Backwards-compatible secret fallback for existing installations.
        // .env.sample recommends a dedicated random ANALYTICS_SALT.
        $salt = hash('sha256', (string)$admin_api_key . '|' . (string)$admin_password);
    }
    $fingerprint = hash_hmac('sha256', $month . '|' . clientIp() . '|' . substr($ua, 0, 512), $salt);

    try {
        if (($db_driver ?? 'supabase') === 'postgres') {
            $statement = pgConnect()->prepare(
                'INSERT INTO site_visits (visitor_hash, visit_month) VALUES (:hash, :month) '
                . 'ON CONFLICT (visitor_hash, visit_month) DO NOTHING'
            );
            $statement->execute([':hash' => $fingerprint, ':month' => $month]);
            $_SESSION[$sessionKey] = $month;
            @unlink(sys_get_temp_dir() . '/0x79_public_monthly_analytics.json');
            return;
        }

        // PostgREST upsert avoids a read-before-write race and needs only one
        // short, timeout-bounded request on the public landing page.
        $key = $supabase_db_key ?: $supabase_key;
        $url = rtrim((string)$supabase_url, '/')
            . '/rest/v1/site_visits?on_conflict=visitor_hash%2Cvisit_month';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $key,
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
                'Prefer: resolution=ignore-duplicates,return=minimal',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'visitor_hash' => $fingerprint,
                'visit_month' => $month,
            ], JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
        ]);
        curl_exec($ch);
        $error = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error === '' && $http >= 200 && $http < 300) {
            $_SESSION[$sessionKey] = $month;
            @unlink(sys_get_temp_dir() . '/0x79_public_monthly_analytics.json');
        }
    } catch (Throwable $e) {
        // Analytics is non-critical.
    }
}

// Exact PostgREST count without downloading all matching records.
function supabaseExactMonthlyCount($table, $column, $start, $end) {
    global $supabase_url, $supabase_key, $supabase_db_key;
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', (string)$table)
        || !preg_match('/^[a-z_][a-z0-9_]*$/', (string)$column)) return null;

    $key = $supabase_db_key ?: $supabase_key;
    $url = rtrim((string)$supabase_url, '/') . '/rest/v1/' . $table
        . '?select=id&' . $column . '=gte.' . rawurlencode((string)$start)
        . '&' . $column . '=lt.' . rawurlencode((string)$end);
    $contentRange = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'HEAD',
        CURLOPT_NOBODY => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Prefer: count=exact',
            'Range: 0-0',
        ],
        CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$contentRange) {
            if (stripos($line, 'Content-Range:') === 0) $contentRange = trim(substr($line, 14));
            return strlen($line);
        },
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
    ]);
    curl_exec($ch);
    $error = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error !== '' || $http < 200 || $http >= 300) return null;
    return preg_match('#/(\d+)$#', $contentRange, $m) ? (int)$m[1] : null;
}

// Historical aggregate values imported from a previous analytics provider.
// Missing tables/rows deliberately resolve to zero for backwards compatibility.
function fetchMonthlyAnalyticsImport($month) {
    global $db_driver, $supabase_url;
    $month = trim((string)$month);
    if (!preg_match('/^\d{4}-\d{2}-01$/', $month)) {
        return ['visitors' => 0, 'clicks' => 0, 'links' => 0];
    }

    try {
        if (($db_driver ?? 'supabase') === 'postgres') {
            $statement = pgConnect()->prepare(
                'SELECT visitors, clicks, links FROM analytics_monthly_imports WHERE month = :month LIMIT 1'
            );
            $statement->execute([':month' => $month]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } else {
            $url = rtrim((string)$supabase_url, '/') . '/rest/v1/analytics_monthly_imports'
                . '?month=eq.' . rawurlencode($month) . '&select=visitors,clicks,links&limit=1';
            [$http, $response, $error] = supabaseRequest('GET', $url);
            if ($error || $http < 200 || $http >= 300) {
                return ['visitors' => 0, 'clicks' => 0, 'links' => 0];
            }
            $rows = json_decode((string)$response, true);
            $row = is_array($rows) && is_array($rows[0] ?? null) ? $rows[0] : null;
        }
    } catch (Throwable $e) {
        $row = null;
    }

    return [
        'visitors' => max(0, (int)($row['visitors'] ?? 0)),
        'clicks' => max(0, (int)($row['clicks'] ?? 0)),
        'links' => max(0, (int)($row['links'] ?? 0)),
    ];
}

// Public aggregate metrics for the current UTC month. Cached briefly to keep
// the landing page cheap under traffic and to avoid exposing individual rows.
function fetchPublicMonthlyAnalytics() {
    global $db_driver;
    $cacheFile = sys_get_temp_dir() . '/0x79_public_monthly_analytics.json';
    if (is_file($cacheFile) && (int)@filemtime($cacheFile) >= time() - 60) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['month'])) return $cached;
    }

    $start = gmdate('Y-m-01\T00:00:00\Z');
    $end = gmdate('Y-m-01\T00:00:00\Z', strtotime('first day of next month 00:00:00 UTC'));
    $stats = [
        'month' => substr($start, 0, 7),
        'visitors' => null,
        'clicks' => null,
        'links' => null,
    ];

    try {
        if (($db_driver ?? 'supabase') === 'postgres') {
            $pdo = pgConnect();
            $queries = [
                'visitors' => 'SELECT COUNT(*) FROM site_visits WHERE first_visited_at >= :start AND first_visited_at < :end',
                'clicks' => 'SELECT COUNT(*) FROM link_clicks WHERE clicked_at >= :start AND clicked_at < :end',
                'links' => 'SELECT COUNT(*) FROM urls WHERE created_at >= :start AND created_at < :end',
            ];
            foreach ($queries as $key => $sql) {
                try {
                    $statement = $pdo->prepare($sql);
                    $statement->execute([':start' => $start, ':end' => $end]);
                    $stats[$key] = (int)$statement->fetchColumn();
                } catch (Throwable $e) {
                    $stats[$key] = null;
                }
            }
        } else {
            $stats['visitors'] = supabaseExactMonthlyCount('site_visits', 'first_visited_at', $start, $end);
            $stats['clicks'] = supabaseExactMonthlyCount('link_clicks', 'clicked_at', $start, $end);
            $stats['links'] = supabaseExactMonthlyCount('urls', 'created_at', $start, $end);
        }
    } catch (Throwable $e) {
        // Keep null values; the UI renders an em dash instead of a false zero.
    }

    $imported = fetchMonthlyAnalyticsImport(substr($start, 0, 7) . '-01');
    foreach (['visitors', 'clicks', 'links'] as $key) {
        if ($stats[$key] !== null) $stats[$key] += $imported[$key];
    }

    @file_put_contents($cacheFile, json_encode($stats, JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $stats;
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

function createShortLink($long_url, $domain, $password = '', $expires_at = '', $max_clicks = '', $custom_code = '', $preview_enabled = false) {
    global $supabase_url, $available_domains;

    $long_url = trim((string)$long_url);
    $domain = in_array($domain, $available_domains, true) ? $domain : $available_domains[0];
    $expires_at = parseOptionalExpiresAt($expires_at);
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

