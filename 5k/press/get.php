<?php
/* ---- the downloads, behind the same passwords as the page ------------------
 *
 * get.php?f=photos/Sander-Kleinenberg-press-48.jpg
 *
 * The files sit in a folder whose name is only in config.php, so no page and
 * no repository gives it away, and a .htaccess in it refuses direct requests
 * on servers that honour one. Not signed in: back to the password page.
 */
require __DIR__ . '/gate.php';

$who = press_who();
if ($who === null) {
    header('Location: ' . press_path(), true, 302);
    exit;
}

$folder = basename((string) (press_cfg()['files'] ?? ''));
$root   = $folder !== '' ? realpath(__DIR__ . '/' . $folder) : false;
$asked  = (string) ($_GET['f'] ?? '');
$path   = ($root && $asked !== '' && !str_contains($asked, "\0"))
        ? realpath($root . '/' . $asked) : false;

/* realpath() resolves any ../ first, so this also refuses a way out of the folder.
   A file of a section switched off for this promoter (admin.php) isn't there either. */
$section = $path ? press_file_section(substr($path, strlen((string) $root) + 1)) : null;
if ($path === false || !is_file($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)
    || ($section !== null && !in_array($section, press_sections($who), true))) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not in the press kit.\n");
}

$types = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'svg' => 'image/svg+xml', 'pdf' => 'application/pdf',
    'zip' => 'application/zip', 'eps' => 'application/postscript',
];
$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$name = basename($path);
/* photos and PDFs open in the browser; archives, EPS and every logo file are
   saved: a white logo opened in a browser tab looks like an empty page */
$how  = (in_array($ext, ['zip', 'eps'], true) || $section === 'logos') ? 'attachment' : 'inline';

header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . $how . '; filename="' . $name . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');

press_note($who, true, substr($path, strlen((string) $root) + 1));
set_time_limit(0);
while (ob_get_level() > 0) ob_end_clean();
readfile($path);
