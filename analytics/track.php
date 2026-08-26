<?php
/**
 * Receives one visit/behaviour beacon from the snippet in assets/js/tracker.js
 * and appends it as a JSON line to visits.log. Same-origin only — nothing here
 * talks to a third-party analytics service. The one outbound call is a coarse
 * IP -> country/city lookup (ip-api.com) so the dashboard can show location
 * without us maintaining a local GeoIP database; results are cached by a hash
 * of the IP so the same visitor isn't looked up twice and the raw IP is never
 * written to disk.
 */
declare(strict_types=1);

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

function client_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return $ip;
}

function geolocate(string $ip): array {
    $none = ['country' => null, 'city' => null];
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1'
        || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
        return $none;
    }

    $cacheFile = __DIR__ . '/geo-cache.json';
    $key = hash('sha256', $ip);
    $cache = [];
    if (is_readable($cacheFile)) {
        $decoded = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($decoded)) $cache = $decoded;
    }
    if (isset($cache[$key])) return $cache[$key];

    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $resp = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city", false, $ctx);
    $geo = $none;
    if ($resp) {
        $j = json_decode($resp, true);
        if (is_array($j) && ($j['status'] ?? '') === 'success') {
            $geo = ['country' => $j['country'] ?? null, 'city' => $j['city'] ?? null];
        }
    }

    $cache[$key] = $geo;
    if (count($cache) > 5000) {
        $cache = array_slice($cache, -2500, null, true);
    }
    file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
    return $geo;
}

$type = $data['type'] ?? '';
if (!in_array($type, ['pageview', 'duration', 'event'], true)) {
    $type = 'event';
}

$geo = geolocate(client_ip());

$entry = [
    't'       => date('c'),
    'type'    => $type,
    'page'    => substr((string) ($data['page'] ?? ''), 0, 200),
    'name'    => isset($data['name']) ? substr((string) $data['name'], 0, 60) : null,
    'ref'     => isset($data['ref']) ? substr((string) $data['ref'], 0, 300) : null,
    'sid'     => substr((string) ($data['sid'] ?? ''), 0, 40),
    'dur'     => isset($data['dur']) ? (int) $data['dur'] : null,
    // how far down a story someone actually read — the pages here are long
    'scroll'  => isset($data['scroll']) ? max(0, min(100, (int) $data['scroll'])) : null,
    'vw'      => isset($data['vw']) ? (int) $data['vw'] : null,
    'vh'      => isset($data['vh']) ? (int) $data['vh'] : null,
    'tz'      => isset($data['tz']) ? substr((string) $data['tz'], 0, 64) : null,
    'lang'    => isset($data['lang']) ? substr((string) $data['lang'], 0, 16) : null,
    'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
    'country' => $geo['country'],
    'city'    => $geo['city'],
];

file_put_contents(__DIR__ . '/visits.log', json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

echo json_encode(['ok' => true]);
