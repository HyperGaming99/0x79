<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
loadEnv(__DIR__ . '/.env');

$token = trim((string)getenv('DISCORD_BOT_TOKEN'));
$guildId = trim((string)getenv('DISCORD_GUILD_ID'));
$cachePath = discordPresenceCachePath();
if ($token === '' || !preg_match('/^[0-9]{15,22}$/', $guildId)) {
    fwrite(STDERR, "discord-worker: disabled (DISCORD_BOT_TOKEN or DISCORD_GUILD_ID missing)\n");
    exit(0);
}

function dgExact($stream, int $length): ?string {
    $data = '';
    while (strlen($data) < $length) {
        $chunk = fread($stream, $length - strlen($data));
        if ($chunk === false || $chunk === '') return null;
        $data .= $chunk;
    }
    return $data;
}

function dgConnect() {
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $stream = @stream_socket_client('tls://gateway.discord.gg:443', $errno, $error, 12, STREAM_CLIENT_CONNECT, $context);
    if (!$stream) return null;
    $key = base64_encode(random_bytes(16));
    $request = "GET /?v=10&encoding=json HTTP/1.1\r\nHost: gateway.discord.gg\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\nUser-Agent: 0x79-discord-presence/1.0\r\n\r\n";
    fwrite($stream, $request);
    $headers = '';
    while (!feof($stream) && !str_contains($headers, "\r\n\r\n")) {
        $headers .= (string)fgets($stream);
        if (strlen($headers) > 16384) break;
    }
    if (!str_starts_with($headers, 'HTTP/1.1 101')) { fclose($stream); return null; }
    stream_set_blocking($stream, false);
    return $stream;
}

function dgSend($stream, array $payload, int $opcode = 1): bool {
    $body = $opcode === 1 ? json_encode($payload, JSON_UNESCAPED_SLASHES) : (string)($payload['raw'] ?? '');
    if (!is_string($body)) return false;
    $length = strlen($body);
    $header = chr(0x80 | $opcode);
    if ($length < 126) $header .= chr(0x80 | $length);
    elseif ($length <= 65535) $header .= chr(0x80 | 126) . pack('n', $length);
    else $header .= chr(0x80 | 127) . pack('NN', 0, $length);
    $mask = random_bytes(4);
    $masked = '';
    for ($i = 0; $i < $length; $i++) $masked .= $body[$i] ^ $mask[$i % 4];
    return fwrite($stream, $header . $mask . $masked) !== false;
}

function dgFrame($stream): ?array {
    stream_set_blocking($stream, true);
    stream_set_timeout($stream, 5);
    $head = dgExact($stream, 2);
    if ($head === null) { stream_set_blocking($stream, false); return null; }
    $b1 = ord($head[0]); $b2 = ord($head[1]);
    $length = $b2 & 0x7f;
    if ($length === 126) { $x = dgExact($stream, 2); if ($x === null) return null; $length = unpack('n', $x)[1]; }
    elseif ($length === 127) { $x = dgExact($stream, 8); if ($x === null) return null; $parts = unpack('Nhigh/Nlow', $x); if ($parts['high'] !== 0) return null; $length = $parts['low']; }
    $mask = ($b2 & 0x80) ? dgExact($stream, 4) : null;
    $body = $length > 0 ? dgExact($stream, $length) : '';
    stream_set_blocking($stream, false);
    if ($body === null) return null;
    if ($mask !== null) for ($i = 0; $i < $length; $i++) $body[$i] = $body[$i] ^ $mask[$i % 4];
    return ['fin' => (bool)($b1 & 0x80), 'opcode' => $b1 & 0x0f, 'body' => $body];
}

function dgPresence(array $presence, array $knownUser = []): ?array {
    $eventUser = is_array($presence['user'] ?? null) ? $presence['user'] : [];
    $user = array_replace($knownUser, $eventUser);
    $id = (string)($user['id'] ?? '');
    if (!preg_match('/^[0-9]{15,22}$/', $id)) return null;
    $activities = is_array($presence['activities'] ?? null) ? $presence['activities'] : [];
    $spotify = discordSpotifyFromActivities($activities);
    $clients = is_array($presence['client_status'] ?? null) ? $presence['client_status'] : [];
    return [
        'discord_user' => [
            'id' => $id,
            'username' => (string)($user['username'] ?? ''),
            'global_name' => (string)($user['global_name'] ?? ''),
            'discriminator' => (string)($user['discriminator'] ?? '0'),
            'avatar' => $user['avatar'] ?? null,
            'public_flags' => (int)($user['public_flags'] ?? 0),
        ],
        'discord_status' => (string)($presence['status'] ?? 'offline'),
        'active_on_discord_desktop' => isset($clients['desktop']),
        'active_on_discord_mobile' => isset($clients['mobile']),
        'active_on_discord_web' => isset($clients['web']),
        'listening_to_spotify' => $spotify !== null,
        'spotify' => $spotify,
        'activities' => $activities,
        'updated_at' => time(),
    ];
}

function dgOfflinePresence(array $user): ?array {
    return dgPresence(['user' => $user, 'status' => 'offline', 'client_status' => [], 'activities' => []], $user);
}

function dgWriteCache(string $path, array $users): void {
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) return;
    $tmp = $path . '.' . getmypid() . '.tmp';
    $json = json_encode(['updated_at' => time(), 'users' => $users], JSON_UNESCAPED_SLASHES);
    if (is_string($json) && file_put_contents($tmp, $json, LOCK_EX) !== false) @rename($tmp, $path);
}

$backoff = 2;
while (true) {
    $socket = dgConnect();
    if (!$socket) { fwrite(STDERR, "discord-worker: gateway connection failed\n"); sleep($backoff); $backoff = min(30, $backoff * 2); continue; }
    fwrite(STDERR, "discord-worker: connected\n");
    $backoff = 2; $heartbeatMs = 0; $nextHeartbeat = PHP_FLOAT_MAX; $sequence = null; $identified = false; $users = []; $memberProfiles = [];
    $fragment = '';
    while (is_resource($socket) && !feof($socket)) {
        $now = microtime(true);
        if ($identified && $now >= $nextHeartbeat) {
            dgSend($socket, ['op' => 1, 'd' => $sequence]);
            $nextHeartbeat = $now + ($heartbeatMs / 1000);
        }
        $read = [$socket]; $write = $except = [];
        $waitUs = $identified ? max(10000, min(500000, (int)(($nextHeartbeat - $now) * 1000000))) : 500000;
        $ready = @stream_select($read, $write, $except, 0, $waitUs);
        if ($ready === false || $ready === 0) continue;
        $frame = dgFrame($socket);
        if ($frame === null) break;
        if ($frame['opcode'] === 8) break;
        if ($frame['opcode'] === 9) { dgSend($socket, ['raw' => $frame['body']], 10); continue; }
        if ($frame['opcode'] === 1) $fragment = $frame['body'];
        elseif ($frame['opcode'] === 0) $fragment .= $frame['body'];
        else continue;
        if (!$frame['fin']) continue;
        $payload = json_decode($fragment, true); $fragment = '';
        if (!is_array($payload)) continue;
        if (isset($payload['s'])) $sequence = $payload['s'];
        $op = (int)($payload['op'] ?? -1); $event = (string)($payload['t'] ?? ''); $data = $payload['d'] ?? null;
        if ($op === 10 && is_array($data)) {
            $heartbeatMs = (int)($data['heartbeat_interval'] ?? 45000);
            $nextHeartbeat = microtime(true) + ($heartbeatMs / 1000);
            // GUILDS | GUILD_MEMBERS | GUILD_PRESENCES. Lanyard also requests
            // both privileged intents before fetching a fresh presence chunk.
            dgSend($socket, ['op' => 2, 'd' => ['token' => $token, 'intents' => 259, 'properties' => ['os' => 'linux', 'browser' => '0x79', 'device' => '0x79']]]);
            $identified = true;
        } elseif ($op === 1) {
            dgSend($socket, ['op' => 1, 'd' => $sequence]);
        } elseif ($op === 7 || $op === 9) break;
        elseif ($op === 0 && $event === 'GUILD_CREATE' && is_array($data) && (string)($data['id'] ?? '') === $guildId) {
            $users = []; $memberProfiles = [];
            foreach (($data['members'] ?? []) as $member) {
                $profile = is_array($member) && is_array($member['user'] ?? null) ? $member['user'] : [];
                $id = (string)($profile['id'] ?? '');
                if (preg_match('/^[0-9]{15,22}$/', $id)) {
                    $memberProfiles[$id] = $profile;
                    $offline = dgOfflinePresence($profile);
                    if ($offline) $users[$id] = $offline;
                }
            }
            foreach (($data['presences'] ?? []) as $item) {
                $id = is_array($item) ? (string)($item['user']['id'] ?? '') : '';
                $normalized = is_array($item) ? dgPresence($item, $memberProfiles[$id] ?? []) : null;
                if ($normalized) $users[$normalized['discord_user']['id']] = $normalized;
            }
            dgWriteCache($cachePath, $users);
            dgSend($socket, ['op' => 8, 'd' => ['guild_id' => $guildId, 'query' => '', 'limit' => 0, 'presences' => true, 'nonce' => bin2hex(random_bytes(8))]]);
        } elseif ($op === 0 && $event === 'GUILD_MEMBERS_CHUNK' && is_array($data) && (string)($data['guild_id'] ?? '') === $guildId) {
            foreach (($data['members'] ?? []) as $member) {
                $profile = is_array($member) && is_array($member['user'] ?? null) ? $member['user'] : [];
                $id = (string)($profile['id'] ?? '');
                if (!preg_match('/^[0-9]{15,22}$/', $id)) continue;
                $memberProfiles[$id] = $profile;
                if (isset($users[$id])) $users[$id]['discord_user'] = array_replace($users[$id]['discord_user'], $profile);
                else { $offline = dgOfflinePresence($profile); if ($offline) $users[$id] = $offline; }
            }
            foreach (($data['presences'] ?? []) as $item) {
                $id = is_array($item) ? (string)($item['user']['id'] ?? '') : '';
                $normalized = is_array($item) ? dgPresence($item, $memberProfiles[$id] ?? []) : null;
                if ($normalized) $users[$normalized['discord_user']['id']] = $normalized;
            }
            dgWriteCache($cachePath, $users);
        } elseif ($op === 0 && $event === 'PRESENCE_UPDATE' && is_array($data) && (string)($data['guild_id'] ?? '') === $guildId) {
            $id = (string)($data['user']['id'] ?? '');
            $normalized = dgPresence($data, $memberProfiles[$id] ?? []);
            if ($normalized) { $users[$normalized['discord_user']['id']] = $normalized; dgWriteCache($cachePath, $users); }
        } elseif ($op === 0 && in_array($event, ['GUILD_MEMBER_ADD', 'GUILD_MEMBER_UPDATE'], true) && is_array($data) && (string)($data['guild_id'] ?? '') === $guildId) {
            $profile = is_array($data['user'] ?? null) ? $data['user'] : [];
            $id = (string)($profile['id'] ?? '');
            if (preg_match('/^[0-9]{15,22}$/', $id)) {
                $memberProfiles[$id] = array_replace($memberProfiles[$id] ?? [], $profile);
                if (isset($users[$id])) $users[$id]['discord_user'] = array_replace($users[$id]['discord_user'], $profile);
                else { $offline = dgOfflinePresence($profile); if ($offline) $users[$id] = $offline; }
                dgWriteCache($cachePath, $users);
            }
        } elseif ($op === 0 && $event === 'GUILD_MEMBER_REMOVE' && is_array($data) && (string)($data['guild_id'] ?? '') === $guildId) {
            $id = (string)($data['user']['id'] ?? '');
            if (preg_match('/^[0-9]{15,22}$/', $id)) { unset($users[$id], $memberProfiles[$id]); dgWriteCache($cachePath, $users); }
        }
    }
    if (is_resource($socket)) fclose($socket);
    fwrite(STDERR, "discord-worker: disconnected, reconnecting\n");
    sleep($backoff); $backoff = min(30, $backoff * 2);
}
