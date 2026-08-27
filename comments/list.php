<?php
/**
 * Returns the approved comments for one post, oldest first.
 *
 * The 122 historical comments are still baked into the HTML by the mirror —
 * these are only the new ones, which comments.js appends to the thread. That
 * keeps the pages static and cacheable; nothing needs PHP to render.
 *
 * Never returns the ip hash or the approved flag of pending comments.
 */
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

$slug = preg_replace('/[^a-z0-9._-]/i', '', (string) ($_GET['post'] ?? ''));
if ($slug === '' || strlen($slug) > 80) {
    echo json_encode(['ok' => true, 'comments' => []]);
    exit;
}

$file = __DIR__ . '/data/' . $slug . '.jsonl';
$out = [];

if (is_readable($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $e = json_decode($line, true);
        if (!is_array($e) || empty($e['approved'])) continue;
        $out[] = [
            'id'     => (string) ($e['id'] ?? ''),
            't'      => (string) ($e['t'] ?? ''),
            'name'   => (string) ($e['name'] ?? ''),
            'body'   => (string) ($e['body'] ?? ''),
            'parent' => $e['parent'] ?? null,
        ];
    }
    // a later edit in admin.php rewrites the file, so sort rather than trust order
    usort($out, fn($a, $b) => strcmp($a['t'], $b['t']));
}

echo json_encode(['ok' => true, 'comments' => $out], JSON_UNESCAPED_UNICODE);
