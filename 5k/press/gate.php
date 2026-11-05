<?php
/* ---- who may open the press kit -------------------------------------------
 *
 * Shared by index.php (the page), get.php (the downloads) and admin.php (where
 * promoters get their own password). Three kinds of password open the kit:
 *
 *   the owner's   set with set-press-password.py, next to public_html/. Only
 *                 its bcrypt hash is kept, in config.php. It opens admin.php
 *                 and is the only one that adds or removes admins there. No
 *                 hash: the kit stays shut and says so.
 *   an admin's    made by the owner on admin.php, one per person. Opens the
 *                 kit and admin.php, where they look after promoters (not
 *                 admins). Kept as bcrypt. A new password or removal ends
 *                 that admin's sign-in at once.
 *   a promoter's  made on admin.php by the owner or an admin, one per promoter,
 *                 with an end date or none. A new password or a revoke there
 *                 ends that promoter's sign-in at once, and nobody else's.
 *
 * Signing in sets a cookie for 30 days, never past a promoter's end date. It
 * says who signed in and is checked against the list on every request.
 *
 * config.php is uploaded by hand. promoters.php, the list, is written by
 * admin.php on the server, and history.log.php (what happened, shown on
 * history.php) by this file. None of them is in a repository or synced to
 * /5k, and the two written on the server are never uploaded: that would
 * replace the server's copies.
 */

const PRESS_COOKIE = 'sk_press';
const PRESS_DAYS   = 30;
const PRESS_ME     = 'me';                          /* the owner */
const PRESS_GUARD  = "<?php exit; ?>\n";            /* first line of promoters.php */
const PRESS_CHARS  = 'bcdfghjkmnpqrstvwxz23456789';  /* no vowels, no l, 1 or 0 */

function press_cfg(): array {
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        $cfg  = is_file($file) ? require $file : [];
        if (!is_array($cfg)) $cfg = [];
    }
    return $cfg;
}

function press_hash(): ?string {
    $hash = press_cfg()['hash'] ?? null;
    return is_string($hash) && $hash !== '' ? $hash : null;
}

/* The folder this kit is served from: /press/ live, /5k/press/ on staging. */
function press_path(): string {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/press/index.php'));
    return rtrim($dir, '/') . '/';
}

function press_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/* ---- the list of promoters ------------------------------------------------ */

/* On the server the list sits next to this file. PHP's preview server on the
   Mac keeps it in the temp folder instead, so trying admin.php locally never
   leaves a list in public_html that could be uploaded over the real one. */
function press_list_file(): string {
    return PHP_SAPI === 'cli-server'
        ? sys_get_temp_dir() . '/sk-press-promoters-preview.php'
        : __DIR__ . '/promoters.php';
}

/* The file is "<?php exit; ?>" and then JSON, so asking for it in a browser
   shows nothing. null: there, but damaged. Then no promoter password works
   and nothing is saved over it, rather than starting an empty list. */
function press_parse(string $raw): ?array {
    if (trim($raw) === '') return ['key' => null, 'people' => [], 'admins' => []];
    if (!str_starts_with($raw, PRESS_GUARD)) return null;
    $list = json_decode(substr($raw, strlen(PRESS_GUARD)), true);
    if (!is_array($list) || !is_array($list['people'] ?? null)) return null;
    if (isset($list['admins']) && !is_array($list['admins'])) return null;
    $list['admins'] = $list['admins'] ?? [];
    return $list;
}

function press_list(): ?array {
    $file = press_list_file();
    if (!is_file($file)) return ['key' => null, 'people' => [], 'admins' => []];
    $h = @fopen($file, 'rb');
    if (!$h) return null;
    flock($h, LOCK_SH);
    $raw = stream_get_contents($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $raw === false ? null : press_parse($raw);
}

/* Change the list under a lock: $fn gets it by reference and changes it, or
   returns false to leave it as it is. False when it could not be read or saved. */
function press_change(callable $fn): bool {
    $h = @fopen(press_list_file(), 'c+b');
    if (!$h) return false;
    $ok = false;
    if (flock($h, LOCK_EX)) {
        $raw  = stream_get_contents($h);
        $list = $raw === false ? null : press_parse($raw);
        if ($list !== null) {
            if ($fn($list) === false) {
                $ok = true;
            } else {
                $out = PRESS_GUARD . json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
                $ok  = rewind($h) && ftruncate($h, 0) && fwrite($h, $out) === strlen($out) && fflush($h);
            }
        }
        flock($h, LOCK_UN);
    }
    fclose($h);
    return $ok;
}

/* 12 random characters in groups of four, like "k7mq-x4tp-9wzr". */
function press_new_password(): string {
    $s = '';
    for ($i = 0; $i < 12; $i++) $s .= PRESS_CHARS[random_int(0, strlen(PRESS_CHARS) - 1)];
    return implode('-', str_split($s, 4));
}

/* Typed with capitals, spaces or without the dashes still counts. */
function press_norm(string $said): string {
    return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $said));
}

/* Promoter passwords are made here and fully random, so a keyed SHA-256 keeps
   them safe enough; yours is chosen by you, so it gets bcrypt instead. */
function press_pw_hash(string $key, string $password): string {
    return hash_hmac('sha256', press_norm($password), $key);
}

function press_active(array $p): bool {
    return empty($p['revoked']) && (empty($p['until']) || (int) $p['until'] > time());
}

/* Ids: promoters are "p" and 8 hex, admins "a" and 8 hex, the owner PRESS_ME. */
function press_is_admin_id(string $who): bool {
    return (bool) preg_match('/^a[0-9a-f]{8}$/', $who);
}

/* The owner or an admin: may open admin.php, sees every section, isn't counted. */
function press_is_staff(?string $who): bool {
    return $who === PRESS_ME || ($who !== null && press_is_admin_id($who));
}

/* Admin passwords are bcrypt, like the owner's: they open admin.php. */
function press_admin_hash(string $password): string {
    return password_hash(press_norm($password), PASSWORD_DEFAULT);
}

/* A name for an id on the list: the owner's chosen name, an admin's current
   name, or the name kept when it was written ($kept) if that admin is gone. */
function press_name_of(?array $list, ?string $id, ?string $kept = null): string {
    $owner = trim((string) ($list['owner'] ?? '')) ?: 'Owner';
    if ($id === null || $id === '' || $id === PRESS_ME) return $owner;
    $a = $list['admins'][$id] ?? null;
    if (is_array($a) && isset($a['name'])) return (string) $a['name'];
    return $kept !== null && $kept !== '' ? $kept : 'a removed admin';
}

/* ---- sections ---------------------------------------------------------------
   The kit's sections, each switched on or off per promoter on admin.php. A
   promoter's entry keeps the ones switched OFF ('hide'), so promoters from
   before this, and any section added later, see everything. */
const PRESS_SECTIONS = [
    'bio' => 'Bio', 'photos' => 'Photos', 'logos' => 'Logos', 'music' => 'Music',
    'live' => 'Live', 'rider' => 'Rider', 'contact' => 'Contact',
];

function press_hidden_of(array $p): array {
    return array_values(array_intersect(array_keys(PRESS_SECTIONS), (array) ($p['hide'] ?? [])));
}

/* The sections this sign-in sees: owner and admins all of them, a promoter what's switched on. */
function press_sections(string $who): array {
    if (press_is_staff($who)) return array_keys(PRESS_SECTIONS);
    $p = press_list()['people'][$who] ?? null;
    if (!is_array($p)) return [];
    return array_values(array_diff(array_keys(PRESS_SECTIONS), press_hidden_of($p)));
}

/* Which section a download belongs to, by its place in the downloads folder. */
function press_file_section(string $rel): ?string {
    $rel  = strtolower(str_replace('\\', '/', $rel));
    $name = basename($rel);
    if (str_starts_with($rel, 'photos/') || str_contains($name, 'press-photos')) return 'photos';
    if (str_starts_with($rel, 'logos/')  || str_contains($name, 'logos'))        return 'logos';
    if (str_contains($name, 'biography')) return 'bio';
    if (str_contains($name, 'rider'))     return 'rider';
    return null;
}

/* ---- signing in ----------------------------------------------------------- */

function press_sign(string $who, int $until, string $key): string {
    $msg = $who . '.' . $until;
    return $msg . '.' . hash_hmac('sha256', $msg, $key);
}

/* What a sign-in is signed with: for the owner, the owner's password hash; for
   an admin or a promoter, the list's key and their own password's hash. So a
   new password, a revoke or a removal ends that one sign-in, nobody else's. */
function press_cookie_key(string $who, ?array $list): ?string {
    if ($who === PRESS_ME) return press_hash();
    if (press_is_admin_id($who)) {
        $a = $list['admins'][$who] ?? null;
        if (!is_array($a) || empty($list['key']) || empty($a['hash'])) return null;
        return $list['key'] . ':admin:' . $a['hash'];
    }
    $p = $list['people'][$who] ?? null;
    if (!is_array($p) || empty($list['key']) || !press_active($p)) return null;
    return $list['key'] . ':' . $p['hash'];
}

/* Who is signed in: PRESS_ME, an admin's or a promoter's id, or null. */
function press_who(): ?string {
    if (press_hash() === null) return null;
    $sent = (string) ($_COOKIE[PRESS_COOKIE] ?? '');
    if (!preg_match('/^(me|[ap][0-9a-f]{8})\.(\d{10})\.[0-9a-f]{64}$/', $sent, $m)) return null;
    if ((int) $m[2] < time()) return null;
    $key = press_cookie_key($m[1], $m[1] === PRESS_ME ? null : press_list());
    return $key !== null && hash_equals(press_sign($m[1], (int) $m[2], $key), $sent) ? $m[1] : null;
}

/* Whose password this is: PRESS_ME, an admin's id, a promoter's id, or null.
   $staffOnly (admin.php): promoters' passwords don't count. $ended says it was
   a promoter's that was revoked or ran out. */
function press_whose(string $said, bool $staffOnly, ?bool &$ended = null, ?string &$endedId = null): ?string {
    $ended = false;
    $endedId = null;
    $hash  = press_hash();
    if ($said === '' || $hash === null) return null;
    $list = press_list();
    $norm = press_norm($said);
    if ($list && !empty($list['key']) && strlen($norm) === 12) {
        if (!$staffOnly) {
            $mine = press_pw_hash($list['key'], $said);
            foreach ($list['people'] as $id => $p) {
                if (hash_equals((string) ($p['hash'] ?? ''), $mine)) {
                    if (press_active($p)) return (string) $id;
                    $ended = true;
                    $endedId = (string) $id;
                    return null;
                }
            }
        }
        foreach ($list['admins'] as $id => $a) {
            if (is_array($a) && password_verify($norm, (string) ($a['hash'] ?? ''))) return (string) $id;
        }
    }
    return password_verify($said, $hash) ? PRESS_ME : null;
}

function press_set_cookie(string $value, int $until): void {
    setcookie(PRESS_COOKIE, $value, [
        'expires'  => $until,
        'path'     => press_path(),
        'secure'   => press_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function press_sign_in(string $who): bool {
    $until = time() + PRESS_DAYS * 86400;
    $list  = null;
    if ($who !== PRESS_ME) {
        $group = press_is_admin_id($who) ? 'admins' : 'people';
        press_change(function (array &$l) use ($who, $group) {
            if (!isset($l[$group][$who])) return false;
            $l[$group][$who]['signins'] = (int) ($l[$group][$who]['signins'] ?? 0) + 1;
            $l[$group][$who]['last']    = time();
        });
        $list = press_list();
        $end  = $group === 'people' ? (int) ($list['people'][$who]['until'] ?? 0) : 0;
        if ($end > 0) $until = min($until, $end);
    }
    $key = press_cookie_key($who, $list);
    if ($key === null) return false;
    press_set_cookie(press_sign($who, $until, $key), $until);
    return true;
}

function press_sign_out(): void {
    press_set_cookie('', 1);
}

/* A posted password. Right: sign in and load $back again as a plain GET, so a
   refresh never posts it twice. Wrong: answered slowly, and why it failed
   ('wrong' or 'ended') goes to the password page. Nothing posted: null. */
function press_try_password(string $back, bool $staffOnly = false): ?string {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['password'])) return null;
    $who  = press_whose((string) $_POST['password'], $staffOnly, $ended, $endedId);
    $area = $staffOnly ? 'admin' : 'kit';
    if ($who !== null && press_sign_in($who)) {
        press_log('signin', $who, press_label(press_list(), $who), null, null, $area);
        header('Location: ' . $back, true, 303);
        exit;
    }
    usleep(600000);
    if ($ended) press_log('ended', $endedId, press_label(press_list(), (string) $endedId), null, null, $area);
    else        press_log('wrong', null, null, null, null, $area);
    return $ended ? 'ended' : 'wrong';
}

/* For the list on admin.php: when a promoter last used the kit, and how many
   files they took. Visits are noted at most once an hour. The history gets
   each visit noted, and each file ($what, its path in the downloads folder)
   unless it's the same file as their last one, within ten minutes. */
function press_note(string $who, bool $file, ?string $what = null): void {
    if (press_is_staff($who)) return;
    $log = null; $name = null;
    press_change(function (array &$l) use ($who, $file, $what, &$log, &$name) {
        if (!isset($l['people'][$who])) return false;
        if (!$file && (int) ($l['people'][$who]['last'] ?? 0) > time() - 3600) return false;
        $name = (string) ($l['people'][$who]['name'] ?? '');
        if ($file) {
            $l['people'][$who]['files'] = (int) ($l['people'][$who]['files'] ?? 0) + 1;
            $prev = $l['people'][$who]['lastfile'] ?? null;
            if (!(is_array($prev) && ($prev[0] ?? null) === $what && (int) ($prev[1] ?? 0) > time() - 600)) $log = 'file';
            $l['people'][$who]['lastfile'] = [$what, time()];
        } else {
            $log = 'visit';
        }
        $l['people'][$who]['last'] = time();
    });
    if ($log !== null) press_log($log, $who, $name, null, null, $log === 'file' ? $what : null);
}

/* A name for anyone who can sign in: a promoter's, an admin's or the owner's. */
function press_label(?array $list, string $who): string {
    $p = $list['people'][$who] ?? null;
    return is_array($p) && isset($p['name']) ? (string) $p['name'] : press_name_of($list, $who);
}

/* ---- the history ------------------------------------------------------------
   history.log.php: "<?php exit; ?>" and then one JSON line per event, oldest
   first: t (time), do (what), by + by_name (who did it, as named then), id +
   name (whom it was done to), detail. Appended under a lock; when the file
   passes 2 MB only the newest PRESS_HISTORY_KEEP lines stay. A new history
   starts with what the list already knows ("seeded"): who was added and
   revoked, when, by whom, and which admins there are. */
const PRESS_HISTORY_KEEP = 8000;

function press_history_file(): string {
    return PHP_SAPI === 'cli-server'
        ? sys_get_temp_dir() . '/sk-press-history-preview.php'
        : __DIR__ . '/history.log.php';
}

function press_history_seed(): array {
    $list = press_list();
    if (!$list) return [];
    $out = [];
    foreach ($list['people'] as $id => $p) {
        if (!is_array($p)) continue;
        $out[] = ['t' => (int) ($p['added'] ?? 0), 'do' => 'add', 'by' => (string) ($p['by'] ?? PRESS_ME),
                  'by_name' => press_name_of($list, $p['by'] ?? null, $p['by_name'] ?? null),
                  'id' => (string) $id, 'name' => (string) ($p['name'] ?? ''), 'seeded' => true];
        if (!empty($p['revoked'])) {
            $out[] = ['t' => (int) $p['revoked'], 'do' => 'revoke', 'by' => (string) ($p['revoked_by'] ?? PRESS_ME),
                      'by_name' => press_name_of($list, $p['revoked_by'] ?? null, $p['revoked_by_name'] ?? null),
                      'id' => (string) $id, 'name' => (string) ($p['name'] ?? ''), 'seeded' => true];
        }
    }
    foreach ($list['admins'] as $id => $a) {
        if (!is_array($a)) continue;
        $out[] = ['t' => (int) ($a['added'] ?? 0), 'do' => 'admin_add', 'by' => PRESS_ME, 'by_name' => press_name_of($list, PRESS_ME),
                  'id' => (string) $id, 'name' => (string) ($a['name'] ?? ''), 'seeded' => true];
    }
    usort($out, fn($a, $b) => $a['t'] <=> $b['t']);
    return $out;
}

function press_history_line(array $e): string {
    return json_encode($e, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

/* Add events to the history (none: just make sure it exists, seeded). */
function press_history_add(array $events): void {
    $h = @fopen(press_history_file(), 'c+b');
    if (!$h) return;
    if (flock($h, LOCK_EX)) {
        fseek($h, 0, SEEK_END);
        $out = '';
        if (ftell($h) === 0) {
            $out = PRESS_GUARD;
            foreach (press_history_seed() as $e) $out .= press_history_line($e);
        }
        foreach ($events as $e) $out .= press_history_line($e);
        if ($out !== '') fwrite($h, $out);
        if (ftell($h) > 2000000) {
            rewind($h);
            $lines = explode("\n", rtrim((string) stream_get_contents($h), "\n"));
            array_shift($lines);
            $keep = PRESS_GUARD . implode("\n", array_slice($lines, -PRESS_HISTORY_KEEP)) . "\n";
            rewind($h); ftruncate($h, 0); fwrite($h, $keep);
        }
        fflush($h);
        flock($h, LOCK_UN);
    }
    fclose($h);
}

function press_history_ready(): void {
    $file = press_history_file();
    if (!is_file($file) || filesize($file) === 0) press_history_add([]);
}

function press_log(string $do, ?string $by, ?string $byName, ?string $id = null, ?string $name = null, ?string $detail = null): void {
    $e = ['t' => time(), 'do' => $do];
    foreach (['by' => $by, 'by_name' => $byName, 'id' => $id, 'name' => $name, 'detail' => $detail] as $k => $v) {
        if ($v !== null && $v !== '') $e[$k] = $v;
    }
    press_history_add([$e]);
}

/* All of it, oldest first; lines that can't be read are left out. */
function press_history(): array {
    $file = press_history_file();
    if (!is_file($file)) return [];
    $h = @fopen($file, 'rb');
    if (!$h) return [];
    flock($h, LOCK_SH);
    $raw = (string) stream_get_contents($h);
    flock($h, LOCK_UN);
    fclose($h);
    if (!str_starts_with($raw, PRESS_GUARD)) return [];
    $out = [];
    foreach (explode("\n", substr($raw, strlen(PRESS_GUARD))) as $line) {
        $e = $line === '' ? null : json_decode($line, true);
        if (is_array($e) && isset($e['t'], $e['do'])) $out[] = $e;
    }
    return $out;
}

/* ---- pages ---------------------------------------------------------------- */

function press_page_top(string $title, string $css = '', string $bodyClass = ''): void {
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="icon" href="../pics/sk-32.png" sizes="32x32" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75..112,400..900&family=DM+Mono:wght@400;500&display=swap">
<style>
:root{
  color-scheme:light;
  --wall:#f1f1ec; --paper:#fdfcfa; --ink:#181817; --body:#2c2422;
  --muted:#8d867c; --accent:#de6d96; --shadow:72,64,52;
  --sans:"Archivo",system-ui,-apple-system,"Helvetica Neue",Arial,sans-serif;
  --mono:"DM Mono",ui-monospace,"SF Mono",Menlo,monospace;
}
*,*::before,*::after{box-sizing:border-box}
html{height:100%}
body{
  margin:0; min-height:100%;
  display:grid; place-items:center;
  padding-block:max(32px,env(safe-area-inset-top,0px)) max(32px,env(safe-area-inset-bottom,0px));
  padding-left:max(16px,env(safe-area-inset-left,0px));
  padding-right:max(16px,env(safe-area-inset-right,0px));
  background:var(--wall) url("img/surface.webp");
  background-size:520px auto;
  color:var(--body); font-family:var(--sans);
  -webkit-font-smoothing:antialiased;
}
main{width:min(400px,100%); display:flex; flex-direction:column; gap:22px}
.logo{display:block; width:min(250px,72%); height:auto}
.slip{
  background:var(--paper); padding:24px 22px 22px; rotate:-.4deg;
  box-shadow:0 1px 2px rgba(var(--shadow),.15),0 14px 30px -14px rgba(var(--shadow),.3);
}
.kicker{margin:0 0 .9em; font-family:var(--mono); font-size:10px; letter-spacing:.24em; text-transform:uppercase; color:var(--muted)}
h1{margin:0; font-weight:800; font-stretch:76%; font-size:40px; line-height:.9; letter-spacing:-.01em; text-transform:uppercase; color:var(--ink)}
p.lede{margin:12px 0 0; font-size:15px; line-height:1.5}
form{display:flex; flex-direction:column; gap:10px; margin-top:20px}
label{font-family:var(--mono); font-size:10px; letter-spacing:.2em; text-transform:uppercase; color:var(--ink)}
input,select{
  width:100%; padding:13px 14px; font:inherit; font-size:16px;
  color:var(--ink); background:#fff; border:1px solid var(--ink); border-radius:0;
}
input:focus,select:focus{outline:2px solid var(--accent); outline-offset:2px}
button{
  padding:14px 16px; border:1px solid var(--ink); background:var(--ink); color:var(--paper);
  font:inherit; font-weight:800; font-stretch:84%; font-size:15px; letter-spacing:.06em;
  text-transform:uppercase; cursor:pointer; border-radius:0;
}
button:hover,button:focus-visible{background:var(--accent); border-color:var(--accent)}
.wrong{margin:0; padding:10px 12px; background:rgba(222,109,150,.16); color:var(--ink); font-size:14px; line-height:1.45}
.ask{margin:16px 0 0; font-size:13.5px; line-height:1.5; color:var(--muted)}
.ask b{display:block; font-family:var(--mono); font-weight:500; font-size:12.5px; color:var(--ink); overflow-wrap:anywhere}
<?= $css ?>
</style>
</head>
<body<?= $bodyClass !== '' ? ' class="' . $bodyClass . '"' : '' ?>>
<?php
}

/* The password page, for the kit ('kit') or for admin.php ('admin').
   $said: null, 'wrong' or 'ended', from press_try_password(). */
function press_login_page(?string $said, string $for = 'kit'): void {
    $shut  = press_hash() === null;
    $admin = $for === 'admin';
    http_response_code($shut ? 503 : 401);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $book   = 'achaykin@independentartistgroup.com';
    $action = press_path() . ($admin ? 'admin.php' : '');
    press_page_top($admin ? 'Press kit access' : 'Sander Kleinenberg Press Kit');
    ?>
<main>
  <img class="logo" src="img/logo-black.svg" alt="Sander Kleinenberg" width="1040" height="546">
  <div class="slip">
    <p class="kicker"><?= $admin ? 'Press kit &middot; access' : 'Press kit &middot; 2026' ?></p>
<?php if ($shut): ?>
    <h1>Not open yet</h1>
<?php if ($admin): ?>
    <p class="lede">There&rsquo;s no password yet. Set yours with set-press-password.py and upload press/config.php.</p>
<?php else: ?>
    <p class="lede">The press kit isn&rsquo;t open yet. For photos, logos and the rider, ask the booking agent.</p>
<?php endif; ?>
<?php else: ?>
    <h1><?= $admin ? 'Your password' : 'Password, please' ?></h1>
    <p class="lede"><?= $admin
        ? 'This is where promoters get their own password to the press kit, and lose it again. Sign in with your own admin password.'
        : 'Photos, logos, biography and rider for promoters and press.' ?></p>
    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($said === 'ended'): ?>
      <p class="wrong" role="alert">That password doesn&rsquo;t work anymore. For a new one, ask the booking agent.</p>
<?php elseif ($said === 'wrong'): ?>
      <p class="wrong" role="alert">That password isn&rsquo;t right. Check it and try again.</p>
<?php endif; ?>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" autocapitalize="off" spellcheck="false" required autofocus>
      <button type="submit"><?= $admin ? 'Sign in' : 'Open the press kit' ?></button>
    </form>
<?php endif; ?>
<?php if (!$admin): ?>
    <p class="ask">No password? Bookings, Independent Artist Group:<b><?= $book ?></b></p>
<?php endif; ?>
  </div>
</main>
</body>
</html>
<?php
}
