<?php
/* Hands over the file, and only against a token this site signed.
 *
 * The token is a signed expiry and nothing else -- no database, no session, no
 * cleanup. Copy the link to a friend and it works until it expires, then it
 * does not; change the secret in config.php and every token dies at once.
 *
 * This is not what stops someone skipping the gate -- nothing does, and the
 * gate page says so. It is what stops the mp3 becoming a bare URL that gets
 * pasted into a forum and hotlinked off the hosting bill.
 */
declare(strict_types=1);

$cfgFile = __DIR__ . '/config.php';
$cfg = is_readable($cfgFile) ? (array) (require $cfgFile) : [];

function refuse(int $code, string $why): never {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit($why . "\n");
}

if (!$cfg || empty($cfg['secret']) || str_starts_with((string) $cfg['secret'], 'CHANGE-ME')) {
    refuse(503, 'The download is not set up yet.');
}

$tok = (string) ($_GET['t'] ?? '');
if (!str_contains($tok, '.')) refuse(403, 'No.');
[$exp, $sig] = explode('.', $tok, 2);

/* hash_equals, not ==, so a wrong signature takes the same time as a right one
 * and cannot be felt out a byte at a time. */
$want = hash_hmac('sha256', $exp, (string) $cfg['secret']);
if (!hash_equals($want, $sig)) refuse(403, 'That link is not valid.');
if (!ctype_digit($exp) || (int) $exp < time()) refuse(410, 'That link has expired. Go back and tap again.');

/* basename() so a file name in the config can never climb out of files/. */
$name = basename((string) ($cfg['file'] ?? ''));
$path = __DIR__ . '/files/' . $name;
if ($name === '' || !is_file($path) || !is_readable($path)) {
    refuse(404, 'The file is not on the server yet.');
}

/* Logged next to the visit log the rest of the site keeps, in the same shape,
 * so downloads show up in the dashboard rather than in a second place nobody
 * remembers to look at. Failure to log must never cost someone their file. */
$log = __DIR__ . '/../analytics/visits.log.php';
if (is_writable($log)) {
    @file_put_contents($log, json_encode([
        't'    => date('c'),
        'type' => 'download',
        'file' => $name,
        'ref'  => $_SERVER['HTTP_REFERER'] ?? '',
    ]) . "\n", FILE_APPEND | LOCK_EX);
}

$size = filesize($path);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $name . '"');
if ($size !== false) header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

/* Straight off disk in chunks: a 60MB wav must not have to fit in memory. */
while (ob_get_level() > 0) ob_end_clean();
readfile($path);
