<?php
/* Were you there? -- what the empty Polaroid on the homepage talks to.
 *
 * The pile has one undeveloped Polaroid. Its back is a form for a photo, a
 * story, an idea or a demo link. This file never holds an upload itself.
 * Once a submission passes the checks below, it asks Dropbox for a one-time
 * upload link per file and hands those to the browser, which sends each file
 * straight to Dropbox, byte for byte: nothing is resized or re-encoded on the
 * way. What the visitor wrote and ticked goes into an info.txt next to the
 * files, so each submission is one folder and the Dropbox folder is the pile
 * to review. Nothing here is ever shown on the site by itself.
 *
 * The Dropbox app has "App folder" access only, so the key in config.php
 * reaches Apps/<app name>/ and nothing else in the account.
 *
 *   GET  share/          back to the homepage with the card open (#share)
 *   GET  share/?info=1   what the form needs: ready or not, limits, wording
 *   POST share/          a submission as JSON; answers {ok, uploads:[{name,url}]}
 *
 * config.php (written by set-dropbox.py) and state/ never leave this server:
 * .htaccess denies them over the web, both are PHP that prints nothing, and
 * the deploy and sync scripts skip state/.
 */
declare(strict_types=1);

const SHARE_KINDS = ['photo', 'story', 'idea', 'demo'];
/* Photos only, in what phones and scanners actually make. HEIC is on the list
   so an iPhone sends its original instead of converting it to JPEG first. */
const SHARE_EXT = ['jpg', 'jpeg', 'png', 'heic', 'heif', 'tif', 'tiff', 'webp', 'gif'];

/* The tick boxes, word for word. The form shows these and info.txt records
   these, so what someone agreed to is exactly what they read. */
function share_words(): array {
    return [
        'rights' => [
            'photo' => "I took these photos or I\u{2019}m allowed to share them, and anyone clearly in them is fine with that.",
            'story' => "It\u{2019}s my own story.",
            'idea'  => "It\u{2019}s my own idea.",
            'demo'  => "It\u{2019}s my own music, or I\u{2019}m allowed to send it.",
        ],
        'use' => [
            'photo' => 'Sander may post them, with my name as the credit.',
            'story' => 'Sander may share it, with my name.',
            'idea'  => "Sending an idea doesn\u{2019}t make a deal, and Sander may already have had a similar one.",
            'demo'  => "Sending a demo doesn\u{2019}t make a deal, and similar tracks happen.",
        ],
        'age' => "I\u{2019}m 16 or older.",
    ];
}

function share_cfg(): array {
    $file = getenv('SHARE_CONFIG') ?: __DIR__ . '/config.php';
    $cfg = is_readable($file) ? (array) (require $file) : [];
    return $cfg + [
        'app_key' => '', 'app_secret' => '', 'refresh_token' => '',
        'contact' => '',
        'max_files' => 10, 'max_mb' => 150,          // 150 MB: what Dropbox's one-time links take reliably
        'per_hour' => 5, 'per_day' => 15, 'all_day' => 150,
        'api' => 'https://api.dropboxapi.com', 'content' => 'https://content.dropboxapi.com',
        'state_dir' => __DIR__ . '/state',
    ];
}

function share_ready(array $c): bool {
    return $c['app_key'] !== '' && $c['app_secret'] !== '' && $c['refresh_token'] !== '';
}

function share_out(int $code, array $body): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    exit(json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function share_fail(int $code, string $why): void {
    share_out($code, ['ok' => false, 'error' => $why]);
}

/* ---- small private files: PHP that prints nothing, then JSON -------------- */
const SHARE_GUARD = "<?php exit; /* read only by share/index.php */ ?>\n";

function share_state(array $c, string $name): string {
    $dir = $c['state_dir'];
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (!is_file("$dir/.htaccess")) {
        @file_put_contents("$dir/.htaccess", "# Never fetchable: rate limits and the Dropbox access token.\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    }
    return "$dir/$name.php";
}

/* Read, change and write one of them under a lock. $fn gets the data by
   reference; whatever it returns comes back out. */
function share_locked(string $file, callable $fn) {
    $h = @fopen($file, 'c+b');
    if (!$h) { $none = []; return $fn($none); }
    flock($h, LOCK_EX);
    $raw = stream_get_contents($h);
    $data = json_decode(substr((string) $raw, strlen(SHARE_GUARD)), true);
    if (!is_array($data)) $data = [];
    $out = $fn($data);
    $txt = SHARE_GUARD . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n";
    rewind($h); ftruncate($h, 0); fwrite($h, $txt); fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $out;
}

/* ---- talking to Dropbox ---------------------------------------------------- */
function share_http(string $url, array $headers, string $body): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return [$res === false ? 0 : $code, $res === false ? '' : (string) $res];
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers),
        'content' => $body, 'timeout' => 25, 'ignore_errors' => true]]);
    $res = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) $code = (int) $m[1];
    }
    return [$code, $res === false ? '' : $res];
}

/* An access token lasts four hours; the refresh token in config.php does not
   expire. Keep the short one until shortly before it runs out. */
function share_token(array $c, bool $fresh = false): string {
    $file = share_state($c, 'token');
    return share_locked($file, function (&$t) use ($c, $fresh) {
        if (!$fresh && !empty($t['token']) && ($t['until'] ?? 0) > time()) return $t['token'];
        [$code, $res] = share_http($c['api'] . '/oauth2/token',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['grant_type' => 'refresh_token', 'refresh_token' => $c['refresh_token'],
                              'client_id' => $c['app_key'], 'client_secret' => $c['app_secret']]));
        $j = json_decode($res, true);
        if ($code !== 200 || empty($j['access_token'])) {
            error_log("share: Dropbox token refresh failed ($code)");
            throw new RuntimeException('token');
        }
        $t = ['token' => $j['access_token'], 'until' => time() + (int) ($j['expires_in'] ?? 14400) - 300];
        return $t['token'];
    });
}

function share_call(array $c, string $endpoint, array $args): array {
    foreach ([false, true] as $fresh) {           // once more with a new token if the old one was refused
        [$code, $res] = share_http($c['api'] . '/2/' . $endpoint,
            ['Authorization: Bearer ' . share_token($c, $fresh), 'Content-Type: application/json'],
            json_encode($args, JSON_UNESCAPED_SLASHES));
        if ($code === 401 && !$fresh) continue;
        $j = json_decode($res, true);
        if ($code === 200 && is_array($j)) return $j;
        error_log("share: Dropbox $endpoint answered $code: " . substr($res, 0, 300));
        throw new RuntimeException($endpoint);
    }
    throw new RuntimeException($endpoint);
}

/* A small file written by the server itself: info.txt. The Dropbox-API-Arg
   header has to be plain ASCII, which json_encode gives by escaping. */
function share_put(array $c, string $path, string $bytes, bool $mute): void {
    foreach ([false, true] as $fresh) {
        [$code, $res] = share_http($c['content'] . '/2/files/upload', [
            'Authorization: Bearer ' . share_token($c, $fresh),
            'Content-Type: application/octet-stream',
            'Dropbox-API-Arg: ' . json_encode(['path' => $path, 'mode' => 'add', 'autorename' => true,
                                               'mute' => $mute], JSON_UNESCAPED_SLASHES),
        ], $bytes);
        if ($code === 401 && !$fresh) continue;
        if ($code === 200) return;
        error_log("share: Dropbox upload of info.txt answered $code: " . substr($res, 0, 300));
        throw new RuntimeException('upload');
    }
}

/* ---- the visitor's words -------------------------------------------------- */
function share_line($v, int $max): string {
    $s = is_string($v) ? $v : '';
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
    return mb_substr(trim(preg_replace('/\s+/u', ' ', $s) ?? ''), 0, $max);
}

function share_text($v, int $max): string {
    $s = is_string($v) ? str_replace(["\r\n", "\r"], "\n", $v) : '';
    $s = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]+/u', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

/* Safe as a Dropbox file or folder name on every computer it syncs to. */
function share_name(string $s, int $max): string {
    $s = preg_replace('#[\\\\/:*?"<>|\x00-\x1F\x7F]+#u', ' ', $s) ?? '';
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '', " .");
    return mb_substr($s, 0, $max);
}

function share_mb(int $bytes): string {
    return $bytes >= 1e6 ? number_format($bytes / 1e6, 1) . ' MB' : max(1, (int) round($bytes / 1e3)) . ' KB';
}

/* ---- how often ----------------------------------------------------------- */
function share_allowed(array $c): ?string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $who = substr(hash('sha256', hash('sha256', $c['app_secret']) . $ip), 0, 20);   // no address is kept
    $now = time();
    $day = date('Y-m-d');
    return share_locked(share_state($c, 'limits'), function (&$l) use ($c, $who, $now, $day) {
        $l['ip'] = array_filter(array_map(fn($ts) => array_values(array_filter((array) $ts,
                       fn($t) => $t > $now - 86400)), (array) ($l['ip'] ?? [])));
        $mine = $l['ip'][$who] ?? [];
        $hour = count(array_filter($mine, fn($t) => $t > $now - 3600));
        $all = ($l['day'] ?? '') === $day ? (int) ($l['n'] ?? 0) : 0;
        if ($hour >= $c['per_hour'] || count($mine) >= $c['per_day'])
            return "That\u{2019}s a lot in one go. Please try again later.";
        if ($all >= $c['all_day'])
            return "The box is full for today. Please try again tomorrow.";
        $mine[] = $now;
        $l['ip'][$who] = $mine;
        $l['day'] = $day;
        $l['n'] = $all + 1;
        return null;
    });
}

/* ========================================================================== */
date_default_timezone_set('Europe/Amsterdam');
$cfg = share_cfg();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if (isset($_GET['info'])) {
        share_out(200, [
            'ready'    => share_ready($cfg),
            'contact'  => (string) $cfg['contact'],
            'maxFiles' => (int) $cfg['max_files'],
            'maxMB'    => (int) $cfg['max_mb'],
            'ext'      => SHARE_EXT,
            'words'    => share_words(),
        ]);
    }
    header('Location: ../#share', true, 302);
    exit;
}

/* ---- a submission --------------------------------------------------------- */
if (!share_ready($cfg)) share_fail(503, "The box isn\u{2019}t open yet. Please try again soon.");

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST))
    share_fail(403, 'Not from this site.');

$raw = file_get_contents('php://input', false, null, 0, 65536);
$in = json_decode((string) $raw, true);
if (!is_array($in)) share_fail(400, 'Something went wrong with the form. Please reload the page and try again.');

/* A field no person sees. Something that fills it in is told all went well. */
if (share_line($in['website'] ?? '', 200) !== '') share_out(200, ['ok' => true, 'uploads' => []]);
if ((int) ($in['ms'] ?? 0) < 3000) share_fail(400, 'That was very quick. Please check the form and send it again.');

$kind = (string) ($in['kind'] ?? '');
if (!in_array($kind, SHARE_KINDS, true)) share_fail(400, "Choose what you\u{2019}re sending.");

$name  = share_line($in['name'] ?? '', 80);
$email = share_line($in['email'] ?? '', 200);
$year  = share_line($in['year'] ?? '', 24);
$where = share_line($in['where'] ?? '', 140);
$by    = share_line($in['by'] ?? '', 80);
$link  = share_line($in['link'] ?? '', 500);
$text  = share_text($in['text'] ?? '', 6000);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) share_fail(400, 'Please fill in your email address, so Sander can ask before posting anything.');
if (($kind === 'story' || $kind === 'idea') && mb_strlen($text) < 10) share_fail(400, 'Please write a little more.');
if ($kind === 'demo' && !preg_match('#^https?://[^\s/]+\.[^\s/]+#i', $link)) share_fail(400, 'Please paste a link to your demo (SoundCloud, Dropbox, WeTransfer ...).');

$tick = (array) ($in['consent'] ?? []);
if (($tick['rights'] ?? false) !== true || ($tick['use'] ?? false) !== true || ($tick['age'] ?? false) !== true)
    share_fail(400, 'Please tick all three boxes.');

$files = [];
if ($kind === 'photo') {
    $list = array_values((array) ($in['files'] ?? []));
    if (!$list) share_fail(400, 'Choose at least one photo.');
    if (count($list) > $cfg['max_files']) share_fail(400, "Up to {$cfg['max_files']} photos at a time, please.");
    foreach ($list as $i => $f) {
        $fn = share_line(is_array($f) ? ($f['name'] ?? '') : '', 200);
        $size = (int) (is_array($f) ? ($f['size'] ?? 0) : 0);
        $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
        if (!in_array($ext, SHARE_EXT, true)) share_fail(400, "$fn isn\u{2019}t a photo this box takes (" . implode(', ', SHARE_EXT) . ').');
        if ($size <= 0 || $size > $cfg['max_mb'] * 1_000_000) share_fail(400, "$fn is bigger than {$cfg['max_mb']} MB.");
        $base = share_name(pathinfo($fn, PATHINFO_FILENAME), 80) ?: 'photo';
        $files[] = ['name' => $fn, 'size' => $size, 'as' => sprintf('%02d %s.%s', $i + 1, $base, $ext)];
    }
}

$busy = share_allowed($cfg);
if ($busy !== null) share_fail(429, $busy);

/* ---- one folder per submission, info.txt first ----------------------------- */
$when = new DateTimeImmutable('now');
$folder = '/' . share_name($when->format('Y-m-d H.i') . ' ' . $kind . ' - ' . ($name !== '' ? $name : 'no name')
                           . ' - ' . bin2hex(random_bytes(2)), 150);
$w = share_words();
$label = ['photo' => 'Photo', 'story' => 'Story', 'idea' => 'Idea', 'demo' => 'Demo'][$kind];
$rows = [
    'Kind'  => $label,
    'Sent'  => $when->format('l j F Y, H:i') . ' (Amsterdam time)',
    'Name'  => $name !== '' ? $name : '(none given)',
    'Email' => $email,
];
if ($year !== '')  $rows['Year'] = $year;
if ($where !== '') $rows['Where'] = $where;
if ($by !== '')    $rows['Photo by'] = $by;
if ($link !== '')  $rows['Link'] = $link;
$info = '';
foreach ($rows as $k => $v) $info .= str_pad($k . ':', 10) . $v . "\n";
if ($text !== '') $info .= "\n" . ($kind === 'photo' ? 'About it' : ($kind === 'demo' ? 'Note' : $label)) . ":\n" . $text . "\n";
$info .= "\nTicked:\n[x] " . $w['rights'][$kind] . "\n[x] " . $w['use'][$kind] . "\n[x] " . $w['age'] . "\n";
if ($files) {
    $info .= "\nPhotos (" . count($files) . ", sent as they are, nothing compressed):\n";
    foreach ($files as $f) $info .= '  ' . $f['as'] . '   ' . share_mb($f['size']) . (substr($f['as'], 3) !== $f['name'] ? '   (was ' . $f['name'] . ')' : '') . "\n";
    $info .= "If a photo is missing from this folder, its upload didn't finish.\n";
}
$info .= "\nPage: " . (string) ($_SERVER['HTTP_REFERER'] ?? '') . "\n";

try {
    share_put($cfg, $folder . '/info.txt', $info, false);
    $uploads = [];
    foreach ($files as $f) {
        $r = share_call($cfg, 'files/get_temporary_upload_link', [
            'commit_info' => ['path' => $folder . '/' . $f['as'], 'mode' => 'add', 'autorename' => true, 'mute' => true],
            'duration' => 14400,
        ]);
        if (empty($r['link'])) throw new RuntimeException('link');
        $uploads[] = ['name' => $f['name'], 'url' => $r['link']];
    }
} catch (Throwable $e) {
    share_fail(502, "The box can\u{2019}t be reached right now. Please try again in a while.");
}

share_out(200, ['ok' => true, 'uploads' => $uploads]);
