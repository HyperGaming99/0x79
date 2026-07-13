<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
loadEnv(__DIR__ . '/.env');

$enabled = filter_var((string)(getenv('DISCORD_WS_ENABLED') ?: 'true'), FILTER_VALIDATE_BOOLEAN);
$host = trim((string)(getenv('DISCORD_WS_HOST') ?: '0.0.0.0'));
$port = (int)(getenv('DISCORD_WS_PORT') ?: 8090);
$allowAll = filter_var((string)(getenv('DISCORD_WS_ALLOW_SUBSCRIBE_ALL') ?: 'false'), FILTER_VALIDATE_BOOLEAN);
$heartbeatMs = max(10000, min(120000, (int)(getenv('DISCORD_WS_HEARTBEAT_MS') ?: 30000)));
$maxClients = max(1, min(1000, (int)(getenv('DISCORD_WS_MAX_CLIENTS') ?: 100)));
$cachePath = discordPresenceCachePath();

if (!$enabled || trim((string)getenv('DISCORD_BOT_TOKEN')) === '' || trim((string)getenv('DISCORD_GUILD_ID')) === '') {
    fwrite(STDERR, "discord-socket: disabled\n");
    exit(0);
}
if ($port < 1 || $port > 65535) {
    fwrite(STDERR, "discord-socket: invalid DISCORD_WS_PORT\n");
    exit(1);
}

function dsFrame(string $body, int $opcode = 1): string {
    $length = strlen($body);
    $head = chr(0x80 | ($opcode & 0x0f));
    if ($length < 126) return $head . chr($length) . $body;
    if ($length <= 65535) return $head . chr(126) . pack('n', $length) . $body;
    return $head . chr(127) . pack('NN', 0, $length) . $body;
}

function dsExtractFrame(string &$buffer): ?array {
    if (strlen($buffer) < 2) return null;
    $b1 = ord($buffer[0]); $b2 = ord($buffer[1]);
    $offset = 2; $length = $b2 & 0x7f;
    if ($length === 126) {
        if (strlen($buffer) < 4) return null;
        $length = unpack('nlength', substr($buffer, 2, 2))['length']; $offset = 4;
    } elseif ($length === 127) {
        if (strlen($buffer) < 10) return null;
        $parts = unpack('Nhigh/Nlow', substr($buffer, 2, 8));
        if ($parts['high'] !== 0 || $parts['low'] > 65536) return ['error' => 'payload_too_large'];
        $length = $parts['low']; $offset = 10;
    }
    $masked = (bool)($b2 & 0x80);
    if ($masked) $offset += 4;
    if ($length > 65536) return ['error' => 'payload_too_large'];
    if (strlen($buffer) < $offset + $length) return null;
    $mask = $masked ? substr($buffer, $offset - 4, 4) : '';
    $body = substr($buffer, $offset, $length);
    $buffer = (string)substr($buffer, $offset + $length);
    if ($masked) for ($i = 0; $i < $length; $i++) $body[$i] = $body[$i] ^ $mask[$i % 4];
    return ['fin' => (bool)($b1 & 0x80), 'opcode' => $b1 & 0x0f, 'body' => $body];
}

function dsSnapshot(string $path): array {
    $raw = is_file($path) ? file_get_contents($path) : false;
    $data = $raw !== false ? json_decode($raw, true) : null;
    return is_array($data) && is_array($data['users'] ?? null) ? $data['users'] : [];
}

function dsQueue(array &$client, array $payload): void {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_string($json)) $client['out'] .= dsFrame($json);
}

function dsSubscribed(array $client, string $userId): bool {
    if (($client['mode'] ?? '') === 'all') return true;
    if (($client['mode'] ?? '') === 'single') return (string)($client['ids'][0] ?? '') === $userId;
    return ($client['mode'] ?? '') === 'ids' && in_array($userId, $client['ids'] ?? [], true);
}

function dsInitData(array $client, array $users) {
    if (($client['mode'] ?? '') === 'single') {
        $id = (string)(($client['ids'][0] ?? ''));
        return $users[$id] ?? null;
    }
    if (($client['mode'] ?? '') === 'all') return $users;
    $selected = [];
    foreach (($client['ids'] ?? []) as $id) if (isset($users[$id])) $selected[$id] = $users[$id];
    return $selected;
}

function dsClose(array &$clients, int $id): void {
    if (!isset($clients[$id])) return;
    $stream = $clients[$id]['stream'];
    if (is_resource($stream)) @fclose($stream);
    unset($clients[$id]);
}

$server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $error);
if (!$server) {
    fwrite(STDERR, "discord-socket: listen failed ({$errno}) {$error}\n");
    exit(1);
}
stream_set_blocking($server, false);
fwrite(STDERR, "discord-socket: listening on {$host}:{$port}\n");

$clients = []; $users = dsSnapshot($cachePath); $hashes = [];
foreach ($users as $id => $presence) $hashes[$id] = hash('sha256', json_encode($presence, JSON_UNESCAPED_SLASHES) ?: '');
$lastCacheCheck = microtime(true); $sequence = 0;

while (true) {
    $read = [$server]; $write = [];
    foreach ($clients as $client) {
        $read[] = $client['stream'];
        if ($client['out'] !== '') $write[] = $client['stream'];
    }
    $except = [];
    $ready = @stream_select($read, $write, $except, 0, 250000);
    if ($ready === false) { usleep(100000); continue; }

    if (in_array($server, $read, true)) {
        $connection = @stream_socket_accept($server, 0);
        if ($connection) {
            if (count($clients) >= $maxClients) { fclose($connection); }
            else {
                stream_set_blocking($connection, false);
                $id = (int)$connection;
                $clients[$id] = ['stream' => $connection, 'handshake' => false, 'buffer' => '', 'out' => '', 'mode' => 'none', 'ids' => [], 'last_heartbeat' => microtime(true)];
            }
        }
    }

    foreach ($write as $stream) {
        $id = (int)$stream;
        if (!isset($clients[$id]) || $clients[$id]['out'] === '') continue;
        $written = @fwrite($stream, $clients[$id]['out']);
        if ($written === false) { dsClose($clients, $id); continue; }
        $clients[$id]['out'] = (string)substr($clients[$id]['out'], $written);
    }

    foreach ($read as $stream) {
        if ($stream === $server) continue;
        $id = (int)$stream;
        if (!isset($clients[$id])) continue;
        $chunk = @fread($stream, 65536);
        if ($chunk === false || ($chunk === '' && feof($stream))) { dsClose($clients, $id); continue; }
        $clients[$id]['buffer'] .= $chunk;
        if (strlen($clients[$id]['buffer']) > 131072) { dsClose($clients, $id); continue; }

        if (!$clients[$id]['handshake']) {
            $end = strpos($clients[$id]['buffer'], "\r\n\r\n");
            if ($end === false) continue;
            $headers = substr($clients[$id]['buffer'], 0, $end + 4);
            $clients[$id]['buffer'] = (string)substr($clients[$id]['buffer'], $end + 4);
            if (!preg_match('/^GET\s+\S+\s+HTTP\/1\.[01]/i', $headers) || !preg_match('/Sec-WebSocket-Key:\s*([^\r\n]+)/i', $headers, $match)) {
                dsClose($clients, $id); continue;
            }
            $accept = base64_encode(sha1(trim($match[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            $clients[$id]['out'] .= "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\n\r\n";
            $clients[$id]['handshake'] = true;
            dsQueue($clients[$id], ['op' => 1, 'd' => ['heartbeat_interval' => $heartbeatMs]]);
        }

        while ($clients[$id]['handshake'] && $clients[$id]['buffer'] !== '') {
            $frame = dsExtractFrame($clients[$id]['buffer']);
            if ($frame === null) break;
            if (isset($frame['error']) || !$frame['fin']) { dsClose($clients, $id); break; }
            if ($frame['opcode'] === 8) { dsClose($clients, $id); break; }
            if ($frame['opcode'] === 9) { $clients[$id]['out'] .= dsFrame($frame['body'], 10); continue; }
            if ($frame['opcode'] !== 1) continue;
            $message = json_decode($frame['body'], true);
            if (!is_array($message)) continue;
            $op = (int)($message['op'] ?? -1); $data = is_array($message['d'] ?? null) ? $message['d'] : [];
            if ($op === 2) {
                $mode = 'none'; $ids = [];
                if (isset($data['subscribe_to_id']) && preg_match('/^[0-9]{15,22}$/', (string)$data['subscribe_to_id'])) {
                    $mode = 'single'; $ids = [(string)$data['subscribe_to_id']];
                } elseif (is_array($data['subscribe_to_ids'] ?? null)) {
                    foreach (array_slice($data['subscribe_to_ids'], 0, 100) as $userId) if (preg_match('/^[0-9]{15,22}$/', (string)$userId)) $ids[] = (string)$userId;
                    $ids = array_values(array_unique($ids)); $mode = 'ids';
                } elseif (($data['subscribe_to_all'] ?? false) === true && $allowAll) {
                    $mode = 'all';
                }
                if ($mode === 'none') { dsQueue($clients[$id], ['op' => 0, 'seq' => ++$sequence, 't' => 'ERROR', 'd' => ['error' => 'invalid_subscription']]); continue; }
                $clients[$id]['mode'] = $mode; $clients[$id]['ids'] = $ids;
                dsQueue($clients[$id], ['op' => 0, 'seq' => ++$sequence, 't' => 'INIT_STATE', 'd' => dsInitData($clients[$id], $users)]);
            } elseif ($op === 3) {
                $clients[$id]['last_heartbeat'] = microtime(true);
                dsQueue($clients[$id], ['op' => 3, 'd' => ['heartbeat_at' => (int)round(microtime(true) * 1000)]]);
            } elseif ($op === 4) {
                $remove = [];
                if (isset($data['unsubscribe_from_id'])) $remove[] = (string)$data['unsubscribe_from_id'];
                if (is_array($data['unsubscribe_from_ids'] ?? null)) $remove = array_merge($remove, array_map('strval', $data['unsubscribe_from_ids']));
                if (($data['unsubscribe_from_all'] ?? false) === true) { $clients[$id]['mode'] = 'none'; $clients[$id]['ids'] = []; }
                elseif ($remove) { $clients[$id]['ids'] = array_values(array_diff($clients[$id]['ids'], $remove)); if (!$clients[$id]['ids']) $clients[$id]['mode'] = 'none'; }
            }
        }
    }

    $now = microtime(true);
    if ($now - $lastCacheCheck >= 0.5) {
        $nextUsers = dsSnapshot($cachePath); $nextHashes = [];
        foreach ($nextUsers as $userId => $presence) {
            $nextHashes[$userId] = hash('sha256', json_encode($presence, JSON_UNESCAPED_SLASHES) ?: '');
            if (($hashes[$userId] ?? '') === $nextHashes[$userId]) continue;
            foreach ($clients as &$client) if ($client['handshake'] && dsSubscribed($client, (string)$userId)) dsQueue($client, ['op' => 0, 'seq' => ++$sequence, 't' => 'PRESENCE_UPDATE', 'd' => $presence]);
            unset($client);
        }
        $users = $nextUsers; $hashes = $nextHashes;
        $lastCacheCheck = $now;
    }
    foreach (array_keys($clients) as $id) if ($now - $clients[$id]['last_heartbeat'] > ($heartbeatMs / 1000) * 3) dsClose($clients, $id);
}
