<?php
/**
 * Accepts one new comment and stores it, unapproved, as a JSON line in
 * comments/data/<slug>.jsonl. Nothing is shown on the site until it is
 * approved in comments/admin.php.
 *
 * Deliberately collects name + comment only. No email, no URL, no cookies —
 * so there is no personal data to protect beyond the name someone chooses to
 * type, and no consent banner is needed. The IP is used for rate limiting and
 * is only ever stored as a salted hash.
 */
declare(strict_types=1);

header('Content-Type: application/json');

const MAX_NAME = 60;
const MIN_BODY = 2;
const MAX_BODY = 4000;
const MIN_SECONDS_ON_PAGE = 3;    // a human takes longer than this to write
const MAX_PER_HOUR = 3;

function fail(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('POST only', 405);

$raw = file_get_contents('php://input');
if (strlen($raw) > 20000) fail('Bericht te lang.');
$in = json_decode($raw, true);
if (!is_array($in)) fail('Ongeldige aanvraag.');

// ---- bot checks ---------------------------------------------------------
// Hidden field: a real person never fills this in, most bots fill everything.
if (trim((string) ($in['website'] ?? '')) !== '') fail('Bedankt!', 200);
if ((int) ($in['elapsed'] ?? 0) < MIN_SECONDS_ON_PAGE) fail('Even te snel — probeer het nog eens.');

// ---- fields -------------------------------------------------------------
$slug = preg_replace('/[^a-z0-9._-]/i', '', (string) ($in['post'] ?? ''));
if ($slug === '' || strlen($slug) > 80) fail('Onbekende pagina.');

$name = trim((string) ($in['name'] ?? ''));
$body = trim((string) ($in['body'] ?? ''));
$parent = preg_replace('/[^a-z0-9]/i', '', (string) ($in['parent'] ?? ''));

if ($name === '' || mb_strlen($name) > MAX_NAME)  fail('Vul een naam in (max ' . MAX_NAME . ' tekens).');
if (mb_strlen($body) < MIN_BODY)                  fail('Schrijf even een berichtje.');
if (mb_strlen($body) > MAX_BODY)                  fail('Bericht te lang (max ' . MAX_BODY . ' tekens).');
// A comment that is mostly links is a link-spam comment.
if (preg_match_all('~https?://~i', $body) > 2)    fail('Te veel links.');

// ---- storage ------------------------------------------------------------
$dir = __DIR__ . '/data';
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) fail('Opslag niet beschikbaar.', 500);

$saltFile = __DIR__ . '/.salt';
if (!is_readable($saltFile)) {
    file_put_contents($saltFile, bin2hex(random_bytes(32)), LOCK_EX);
    @chmod($saltFile, 0600);
}
$salt = trim((string) file_get_contents($saltFile));

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (str_contains($ip, ',')) $ip = trim(explode(',', $ip)[0]);
$ipHash = hash('sha256', $ip . '|' . $salt);

$file = $dir . '/' . $slug . '.jsonl';

// ---- rate limit ---------------------------------------------------------
if (is_readable($file)) {
    $recent = 0;
    $cutoff = time() - 3600;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $e = json_decode($line, true);
        if (!is_array($e)) continue;
        if (($e['ip'] ?? '') === $ipHash && strtotime((string) ($e['t'] ?? '')) > $cutoff) $recent++;
    }
    if ($recent >= MAX_PER_HOUR) fail('Je hebt net al gereageerd — probeer het later nog eens.', 429);
}

$entry = [
    'id'       => bin2hex(random_bytes(8)),
    't'        => date('c'),
    'post'     => $slug,
    'name'     => mb_substr($name, 0, MAX_NAME),
    'body'     => mb_substr($body, 0, MAX_BODY),
    'parent'   => $parent !== '' ? $parent : null,
    'approved' => false,
    'ip'       => $ipHash,
];

if (file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
                      FILE_APPEND | LOCK_EX) === false) {
    fail('Opslaan mislukt.', 500);
}

echo json_encode([
    'ok' => true,
    'message' => 'Bedankt! Je reactie is verstuurd en verschijnt zodra Tim hem heeft gelezen.',
], JSON_UNESCAPED_UNICODE);
