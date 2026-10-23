<?php
/**
 * Receives one beacon from the tracker at the foot of index.html and appends
 * it to visits.log as a JSON line. Self-hosted: nothing here talks to an
 * analytics company, there are no cookies, and the raw IP address is never
 * written to disk. The one outbound call is a coarse IP -> country/city lookup
 * (ip-api.com) so the dashboard can show where a visitor sat without us
 * keeping a GeoIP database; the result is cached under sha256(ip), so the
 * same visitor is looked up once and the address itself is never stored.
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Amsterdam');

header('Content-Type: application/json');
header('Cache-Control: no-store');

/* The browser's own opt-out. The tracker checks it before it measures
   anything, and it is checked again here in case a beacon arrives by hand. */
if (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') {
    echo json_encode(['ok' => true, 'skipped' => 'opted out']);
    exit;
}

$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);
if ($ua === '' || preg_match('~bot|crawl|spider|slurp|headless|lighthouse|monitor|preview~i', $ua)) {
    echo json_encode(['ok' => true, 'skipped' => 'not a visitor']);
    exit;
}

$data = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

function client_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (str_contains($ip, ',')) $ip = trim(explode(',', $ip)[0]);
    return $ip;
}

/** Coarse place from the IP, cached by hash so the address is never kept. */
function geolocate(string $ip): array {
    $none = ['country' => null, 'region' => null, 'city' => null];
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1'
        || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')
        || str_starts_with($ip, '172.16.')) {
        return $none;
    }

    /* Kept as PHP rather than JSON so that a host which never reads
       .htaccess -- nginx in front of PHP-FPM, say -- still cannot serve it:
       asked for directly it runs, returns, and prints nothing. */
    $cacheFile = __DIR__ . '/geo-cache.php';
    $key = hash('sha256', $ip);
    $cache = is_readable($cacheFile) ? (array) @include $cacheFile : [];
    if (isset($cache[$key])) return $cache[$key];

    $ctx  = stream_context_create(['http' => ['timeout' => 2]]);
    $resp = @file_get_contents(
        "http://ip-api.com/json/{$ip}?fields=status,country,regionName,city", false, $ctx);
    $geo = $none;
    if ($resp) {
        $j = json_decode($resp, true);
        if (is_array($j) && ($j['status'] ?? '') === 'success') {
            $geo = ['country' => $j['country']    ?? null,
                    'region'  => $j['regionName'] ?? null,
                    'city'    => $j['city']       ?? null];
        }
    }

    $cache[$key] = $geo;
    if (count($cache) > 5000) $cache = array_slice($cache, -2500, null, true);
    // written aside and moved into place, so a reader never catches it half-written
    $tmp = $cacheFile . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, '<?php return ' . var_export($cache, true) . ";\n", LOCK_EX) !== false) {
        @rename($tmp, $cacheFile);
    }
    return $geo;
}

/** visit = arrived · press = a finger went down · card = one card, enlarged,
    for this long · event = turned over, film, mixtape, ground · time = left  */
$type = (string) ($data['type'] ?? '');
if (!in_array($type, ['visit', 'press', 'card', 'event', 'time'], true)) $type = 'event';

$str = fn($k, $max) => isset($data[$k]) && $data[$k] !== null && $data[$k] !== ''
    ? substr((string) $data[$k], 0, $max) : null;
$int = fn($k) => isset($data[$k]) ? (int) $data[$k] : null;
$mil = fn($k) => isset($data[$k]) ? max(0, min(1000, (int) $data[$k])) : null;

$entry = ['t' => date('c'), 'type' => $type, 'sid' => substr((string) ($data['sid'] ?? ''), 0, 40)];

// Only the arrival carries the visitor's context — repeating it on every
// press would quadruple the log for nothing. The dashboard joins on sid.
if ($type === 'visit') {
    $geo = geolocate(client_ip());
    $entry += [
        'ref'     => $str('ref', 300),
        'vw'      => $int('vw'),
        'vh'      => $int('vh'),
        'tz'      => $str('tz', 64),
        'lang'    => $str('lang', 16),
        'ground'  => $str('ground', 20),
        'card'    => $str('card', 40),   // arrived straight on one card: ?card=
        'ua'      => $ua,
        'country' => $geo['country'],
        'region'  => $geo['region'],
        'city'    => $geo['city'],
    ];
} else {
    $entry += [
        'kind' => $str('kind', 16),      // polaroid · sleeve · cd · flyer · disc · sheet · clip
        'item' => $str('item', 140),     // what it says on the front
        'file' => $str('file', 60),      // c/44, v/14, 29-small-wheels
        'name' => $str('name', 40),      // for events: turn, film, mixtape, ground, tickets
        'ui'   => $str('ui', 40),        // for presses that missed a card
        'x'    => $mil('x'),             // where on the table, per mille
        'y'    => $mil('y'),
        'vw'   => $int('vw'),
        'dur'  => $int('dur'),           // card: seconds enlarged · time: seconds on the site
        'back' => isset($data['back']) ? (bool) $data['back'] : null,
        'deep' => isset($data['deep']) ? max(0, min(100, (int) $data['deep'])) : null,
        'n'    => $int('n'),             // time: how many cards were picked up
    ];
}

/* One JSON line per beacon, under a first line that makes the whole file a
   PHP script which exits at once. The .htaccess next door already refuses to
   serve it, but a host that never reads .htaccess would hand the lot over;
   this way the worst that can happen is an empty page. */
$log = __DIR__ . '/visits.log.php';
// Presses add up faster than pageviews do, so the log is turned over at 8 MB
// rather than growing until the dashboard has to read it all.
if (is_file($log) && filesize($log) > 8 * 1024 * 1024) {
    @rename($log, __DIR__ . '/visits-' . date('Y-m-d-His') . '.log.php');
}
$head = is_file($log) ? '' : "<?php exit; /* the visit log — read by dashboard.php */ ?>\n";
file_put_contents($log,
    $head . json_encode(array_filter($entry, fn($v) => $v !== null), JSON_UNESCAPED_SLASHES) . "\n",
    FILE_APPEND | LOCK_EX);

echo json_encode(['ok' => true]);
