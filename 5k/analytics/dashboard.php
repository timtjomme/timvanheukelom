<?php
/**
 * Who picked up what — the statistics page for the Kleinenberg table.
 *
 * Reads visits.log (written by track.php next door) and nothing else. Behind
 * a username and a password of its own, kept in config.php, which is
 * gitignored and holds the password only as a hash.
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Amsterdam');

$https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
session_name('sk5k_stats');
session_set_cookie_params([
    'path'     => dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $https,
]);
session_start();

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');

/* The two people who may sign in. The addresses live here, in the repo,
   because they are not secret; the passwords live only in config.php on the
   server, one per person, each as a hash. Add or remove somebody here. */
const PEOPLE = ['timvanheukelom@me.com', 'sanne@sanderkleinenberg.com'];

$configFile = __DIR__ . '/config.php';
$config     = is_readable($configFile) ? (array) (require $configFile) : [];

/** Everyone from PEOPLE, with the password hash each one has set, or null. */
function passwords(array $config): array {
    $saved = $config['people'] ?? [];
    // the very first shape this file had held one person as user + hash
    if (!$saved && isset($config['user'])) $saved = [$config['user'] => $config['hash'] ?? null];
    $out = [];
    foreach (PEOPLE as $who) $out[$who] = is_string($saved[$who] ?? null) ? $saved[$who] : null;
    return $out;
}
function savePasswords(array $people): bool {
    $php = "<?php\n// Written by dashboard.php. Gitignored — never touches the repo.\n"
         . "// One password hash per person; the addresses themselves are in dashboard.php.\n"
         . "return [\n    'people' => " . var_export($people, true) . ",\n];\n";
    $ok = file_put_contents(__DIR__ . '/config.php', $php, LOCK_EX) !== false;
    if ($ok) @chmod(__DIR__ . '/config.php', 0600);
    return $ok;
}

$people = passwords($config);
// Nobody has a password yet: the first of the two to arrive sets their own.
$isSetup = !array_filter($people);

$error = $setupError = $notice = null;

/* ---- how many wrong guesses, and when -------------------------------------
   Keyed by a hash of the address, so a locked-out guesser is remembered
   without the address itself being written down.                             */
const TRIES = 8, WINDOW = 900;
function whoIsGuessing(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (str_contains($ip, ',')) $ip = trim(explode(',', $ip)[0]);
    return hash('sha256', $ip);
}
function attempts(): array {
    $f = __DIR__ . '/.attempts.json';
    $a = is_readable($f) ? json_decode((string) file_get_contents($f), true) : [];
    return is_array($a) ? $a : [];
}
function writeAttempts(array $a): void {
    foreach ($a as $k => $v) {                       // forget the old ones
        if (($v['first'] ?? 0) < time() - WINDOW) unset($a[$k]);
    }
    file_put_contents(__DIR__ . '/.attempts.json', json_encode($a), LOCK_EX);
}
function lockedOut(): int {
    $a = attempts()[whoIsGuessing()] ?? null;
    if (!$a || ($a['n'] ?? 0) < TRIES) return 0;
    $left = ($a['first'] + WINDOW) - time();
    return $left > 0 ? $left : 0;
}
function wrongGuess(): void {
    $a = attempts(); $k = whoIsGuessing();
    if (!isset($a[$k]) || $a[$k]['first'] < time() - WINDOW) $a[$k] = ['n' => 0, 'first' => time()];
    $a[$k]['n']++;
    writeAttempts($a);
}
function guessedRight(): void {
    $a = attempts(); unset($a[whoIsGuessing()]); writeAttempts($a);
}

/* First run: config.php is gitignored, so a freshly deployed copy of the site
   never has one, and nobody has a password yet. Whichever of the two arrives
   first sets their own, written straight onto the server. The form does not
   name the two addresses, so a stranger who reaches this page before either
   of them learns nothing from it. */
if ($isSetup && isset($_POST['new_password'])) {
    $u = strtolower(trim((string) ($_POST['new_user'] ?? '')));
    $p = (string) $_POST['new_password'];
    if (!in_array($u, PEOPLE, true)) {
        $setupError = 'That address is not on the list.';
    } elseif (strlen($p) < 8) {
        $setupError = 'Use at least 8 characters for the password.';
    } elseif (savePasswords($people = [$u => password_hash($p, PASSWORD_DEFAULT)] + $people)) {
        $isSetup = false;
        session_regenerate_id(true);
        $_SESSION['sk5k_who'] = $u;
        $notice = 'Password set. Set one for the other address below when you want to let them in.';
    } else {
        $setupError = "Couldn't write config.php — make the analytics/ folder writable, "
                    . "or copy config.example.php to config.php by hand.";
    }
}

if (!$isSetup && isset($_POST['password'])) {
    if ($wait = lockedOut()) {
        $error = 'Too many tries. Try again in ' . ceil($wait / 60) . ' minutes.';
    } else {
        $u = strtolower(trim((string) ($_POST['user'] ?? '')));
        /* Verified against a hash either way, so a made-up address takes the
           same time to turn away as a real one with the wrong password. */
        $hash = $people[$u] ?? null;
        if (password_verify((string) $_POST['password'], (string) $hash) && $hash !== null) {
            guessedRight();
            session_regenerate_id(true);
            $_SESSION['sk5k_who'] = $u;
        } else {
            wrongGuess();
            usleep(400000);                          // slow a guesser down a little
            $error = 'Wrong address or password.';
        }
    }
}

/* Signed in, you can set a password for either address -- your own, to change
   it, or the other one, to let that person in for the first time. */
if (($_SESSION['sk5k_who'] ?? null) !== null && isset($_POST['set_pass'])) {
    if (!hash_equals((string) ($_SESSION['sk5k_token'] ?? ''), (string) ($_POST['token'] ?? ''))) {
        $notice = 'That form had gone stale — try again.';
    } else {
        $for = strtolower(trim((string) ($_POST['set_for'] ?? '')));
        $new = (string) $_POST['set_pass'];
        if (!in_array($for, PEOPLE, true)) {
            $notice = 'Unknown address.';
        } elseif (strlen($new) < 8) {
            $notice = 'Use at least 8 characters.';
        } elseif (savePasswords($people = [$for => password_hash($new, PASSWORD_DEFAULT)] + $people)) {
            $notice = 'Password set for ' . $for . '.';
        } else {
            $notice = "Couldn't write config.php.";
        }
    }
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$who    = $_SESSION['sk5k_who'] ?? null;
$authed = is_string($who) && in_array($who, PEOPLE, true);
if ($authed && empty($_SESSION['sk5k_token'])) {
    $_SESSION['sk5k_token'] = bin2hex(random_bytes(16));
}
function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$days  = max(1, min(730, (int) ($_GET['days'] ?? 30)));
$since = new DateTimeImmutable("-{$days} days");

/* ---- the log --------------------------------------------------------------
   Streamed a line at a time rather than read whole, because presses add up
   much faster than pageviews and the file is turned over at 8 MB.            */
$visits = $presses = $cards = $events = $times = [];
$byDay = [];
for ($i = $days - 1; $i >= 0; $i--) $byDay[(new DateTimeImmutable("-{$i} days"))->format('Y-m-d')] = 0;

if ($authed) {
    // the first line of each is the <?php exit; guard, which json_decode skips
    $logs = glob(__DIR__ . '/visits*.log.php') ?: [];
    sort($logs);
    foreach ($logs as $file) {
        if (!is_readable($file)) continue;
        $fh = fopen($file, 'r');
        if (!$fh) continue;
        while (($line = fgets($fh)) !== false) {
            $e = json_decode($line, true);
            if (!is_array($e) || empty($e['t'])) continue;
            try { $when = new DateTimeImmutable($e['t']); } catch (Exception $ex) { continue; }
            if ($when < $since) continue;
            $e['_when'] = $when;
            switch ($e['type'] ?? '') {
                case 'visit':
                    $visits[] = $e;
                    $k = $when->format('Y-m-d');
                    if (isset($byDay[$k])) $byDay[$k]++;
                    break;
                case 'press': $presses[] = $e; break;
                case 'card':  $cards[]   = $e; break;
                case 'event': $events[]  = $e; break;
                case 'time':  $times[]   = $e; break;
            }
        }
        fclose($fh);
    }
}

/** Where each visitor sat, so a press or a card can be placed without the
    arrival context being repeated on every single line. */
$ctx = [];
foreach ($visits as $v) $ctx[$v['sid'] ?? ''] ??= $v;

/** Count the values of one field, most frequent first. */
function tally(array $rows, string $field, int $limit = 15, ?callable $keep = null): array {
    $out = [];
    foreach ($rows as $r) {
        if ($keep && !$keep($r)) continue;
        $v = $r[$field] ?? null;
        if ($v === null || $v === '') continue;
        $out[(string) $v] = ($out[(string) $v] ?? 0) + 1;
    }
    arsort($out);
    return array_slice($out, 0, $limit, true);
}
function device(?int $vw): string {
    if (!$vw) return 'unknown';
    return $vw < 768 ? 'phone' : ($vw < 1024 ? 'tablet' : 'desktop');
}
function mmss(float $s): string {
    $s = (int) round($s);
    return $s < 60 ? $s . 's' : intdiv($s, 60) . 'm ' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT) . 's';
}

/* ---- time on the site ---------------------------------------------------- */
$secs = [];
foreach ($times as $t) if (($t['dur'] ?? 0) > 0) $secs[] = (int) $t['dur'];
sort($secs);
$avgTime = $secs ? array_sum($secs) / count($secs) : 0;
$medTime = $secs ? $secs[intdiv(count($secs), 2)] : 0;
$buckets = ['under 10s' => 0, '10–30s' => 0, '30s–1m' => 0, '1–3m' => 0, '3–10m' => 0, 'over 10m' => 0];
foreach ($secs as $s) {
    if ($s < 10)       $buckets['under 10s']++;
    elseif ($s < 30)   $buckets['10–30s']++;
    elseif ($s < 60)   $buckets['30s–1m']++;
    elseif ($s < 180)  $buckets['1–3m']++;
    elseif ($s < 600)  $buckets['3–10m']++;
    else               $buckets['over 10m']++;
}
$deep = array_filter(array_map(fn($t) => $t['deep'] ?? null, $times), fn($d) => $d !== null);
$avgDeep = $deep ? array_sum($deep) / count($deep) : 0;

/* ---- what was picked up -------------------------------------------------- */
$opened  = count($cards);
$turned  = count(array_filter($cards, fn($c) => !empty($c['back'])));
$films   = count(array_filter($events, fn($e) => ($e['name'] ?? '') === 'film'));
$tickets = count(array_filter($events, fn($e) => ($e['name'] ?? '') === 'tickets'));
$tapes   = count(array_filter($events, fn($e) => ($e['name'] ?? '') === 'mixtape'));

/** Average time a card stayed enlarged — only for the ones picked up twice or
    more, so a single long look doesn't win the table on its own. */
$dwell = [];
foreach ($cards as $c) {
    $key = ($c['item'] ?? '') ?: ($c['file'] ?? '');
    if ($key === '' || ($c['dur'] ?? 0) <= 0) continue;
    $dwell[$key]['n'] = ($dwell[$key]['n'] ?? 0) + 1;
    $dwell[$key]['s'] = ($dwell[$key]['s'] ?? 0) + (int) $c['dur'];
    $dwell[$key]['kind'] ??= $c['kind'] ?? '';
}
$lingered = [];
foreach ($dwell as $k => $d) {
    if ($d['n'] < 2) continue;
    $lingered[$k] = ['avg' => $d['s'] / $d['n'], 'n' => $d['n'], 'kind' => $d['kind']];
}
uasort($lingered, fn($a, $b) => $b['avg'] <=> $a['avg']);
$lingered = array_slice($lingered, 0, 12, true);

/* ---- where the fingers land ----------------------------------------------
   x and y arrive as per mille of the whole table, not of the screen, because
   the pile is longer than one screen and what matters is which part of the
   table gets reached. The pile is re-dealt on every visit, so this says where
   a hand goes, not which card it found — the tables below say that.          */
function heat(array $presses, callable $keep, int $cols, int $rows): array {
    $grid = array_fill(0, $cols * $rows, 0);
    $n = 0;
    foreach ($presses as $p) {
        if (!isset($p['x'], $p['y']) || !$keep($p)) continue;
        $cx = min($cols - 1, max(0, (int) floor($p['x'] / 1000 * $cols)));
        $cy = min($rows - 1, max(0, (int) floor($p['y'] / 1000 * $rows)));
        $grid[$cy * $cols + $cx]++;
        $n++;
    }
    return ['grid' => $grid, 'n' => $n, 'max' => $grid ? max($grid) : 0,
            'cols' => $cols, 'rows' => $rows];
}
$onDesk  = fn($p) => device($p['vw'] ?? ($ctx[$p['sid'] ?? '']['vw'] ?? null)) !== 'phone';
$onPhone = fn($p) => device($p['vw'] ?? ($ctx[$p['sid'] ?? '']['vw'] ?? null)) === 'phone';
$heatDesk  = heat($presses, $onDesk, 18, 12);
$heatPhone = heat($presses, $onPhone, 8, 16);

/* ---- where they are ------------------------------------------------------ */
$devices = [];
foreach ($visits as $v) { $d = device($v['vw'] ?? null); $devices[$d] = ($devices[$d] ?? 0) + 1; }
arsort($devices);

$grounds = [];
foreach ($visits as $v) if (!empty($v['ground'])) $grounds[$v['ground']] = ($grounds[$v['ground']] ?? 0) + 1;
foreach ($events as $e) if (($e['name'] ?? '') === 'ground' && !empty($e['item']))
    $grounds[$e['item']] = ($grounds[$e['item']] ?? 0) + 1;
arsort($grounds);

$maxDay = max(1, ...array_values($byDay));

/* ---- one visit at a time ------------------------------------------------- */
$trail = [];
foreach ($cards as $c) {
    $sid = $c['sid'] ?? ''; if ($sid === '') continue;
    $trail[$sid][] = $c;
}
$seen = [];
foreach ($visits as $v) {
    $sid = $v['sid'] ?? ''; if ($sid === '') continue;
    $seen[$sid] = ['when' => $v['_when'], 'v' => $v, 'cards' => $trail[$sid] ?? []];
}
foreach ($times as $t) {
    $sid = $t['sid'] ?? '';
    if (isset($seen[$sid])) {
        $seen[$sid]['secs'] = max($seen[$sid]['secs'] ?? 0, (int) ($t['dur'] ?? 0));
        $seen[$sid]['deep'] = max($seen[$sid]['deep'] ?? 0, (int) ($t['deep'] ?? 0));
    }
}
uasort($seen, fn($a, $b) => $b['when'] <=> $a['when']);
$seen = array_slice($seen, 0, 40, true);
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Who picked up what — Sander Kleinenberg</title>
<link rel="icon" href="../p/sk-32.png" sizes="32x32" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75..112,400..900&family=DM+Mono:wght@400;500&display=swap">
<style>
  :root{--paper:#faf9f5;--ink:#141413;--dim:#6f6d66;--line:rgba(20,20,19,.1);
        --card:#fffefb;--accent:#de6d96;--accent-dim:rgba(222,109,150,.14)}
  *{box-sizing:border-box}
  body{margin:0;background:var(--paper);color:var(--ink);
       font:15px/1.55 Archivo,-apple-system,BlinkMacSystemFont,sans-serif;
       font-variation-settings:"wdth" 100}
  .wrap{max-width:1080px;margin:0 auto;padding:46px 22px 90px}
  h1{font-size:clamp(28px,5vw,42px);font-weight:800;letter-spacing:-.02em;margin:0;
     font-variation-settings:"wdth" 86}
  h2{font:600 11px/1 "DM Mono",ui-monospace,monospace;letter-spacing:.14em;text-transform:uppercase;
     color:var(--dim);margin:0 0 12px}
  a{color:var(--ink)}
  .top{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;
       border-bottom:1px solid var(--line);padding-bottom:16px;margin-bottom:22px}
  .out{font:500 12px/1 "DM Mono",monospace;color:var(--dim);text-decoration:none;white-space:nowrap}
  .out:hover{color:var(--accent)}
  .sub{color:var(--dim);font-size:13.5px;margin:6px 0 0}
  .range{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 26px}
  .range a{font:500 12px/1 "DM Mono",monospace;padding:7px 14px;border-radius:999px;
           border:1px solid var(--line);text-decoration:none;color:var(--dim);background:var(--card)}
  .range a.on{background:var(--ink);color:var(--paper);border-color:var(--ink)}
  .tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:12px;margin-bottom:34px}
  .tile{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 17px}
  .tile .n{font-size:27px;font-weight:700;letter-spacing:-.02em;line-height:1.1}
  .tile .l{font:500 10.5px/1.3 "DM Mono",monospace;letter-spacing:.1em;text-transform:uppercase;
           color:var(--dim);margin-top:5px}
  section{margin-bottom:38px}
  .two{display:grid;grid-template-columns:1fr 1fr;gap:26px}
  .three{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
  @media (max-width:760px){.two,.three{grid-template-columns:1fr}}
  table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);
        border-radius:14px;overflow:hidden}
  th,td{text-align:left;padding:8px 13px;font-size:13.5px;border-bottom:1px solid var(--line);
        vertical-align:top}
  th{font:600 10px/1 "DM Mono",monospace;letter-spacing:.1em;text-transform:uppercase;color:var(--dim)}
  tr:last-child td{border-bottom:none}
  td.n,th.n{text-align:right;color:var(--dim);white-space:nowrap;font-family:"DM Mono",monospace;font-size:12.5px}
  .k{display:inline-block;font:500 9.5px/1 "DM Mono",monospace;letter-spacing:.08em;
     text-transform:uppercase;color:var(--dim);border:1px solid var(--line);border-radius:4px;
     padding:3px 5px;margin-right:7px;vertical-align:1px}
  .bars{display:flex;align-items:flex-end;gap:3px;height:120px;background:var(--card);
        border:1px solid var(--line);border-radius:14px;padding:14px}
  .bw{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%}
  .b{width:100%;background:var(--accent);border-radius:2px 2px 0 0;min-height:2px}
  .bl{font:500 8.5px/1 "DM Mono",monospace;color:var(--dim);margin-top:5px}
  .rows{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:15px 17px}
  .row{display:grid;grid-template-columns:86px 1fr 46px;align-items:center;gap:10px;margin:7px 0}
  .row .lab{font:500 11px/1 "DM Mono",monospace;color:var(--dim);text-transform:uppercase;letter-spacing:.06em}
  .row .track{height:8px;background:var(--accent-dim);border-radius:99px;overflow:hidden}
  .row .fill{height:100%;background:var(--accent);border-radius:99px}
  .row .val{font:500 11.5px/1 "DM Mono",monospace;color:var(--dim);text-align:right}
  .hmwrap{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:15px}
  /* One cell is the same size on both maps, so a square of pink means the
     same amount of pressing whichever screen it came from. That leaves the
     phone map narrow and tall, which is what a phone is. */
  .hm{display:grid;gap:2px;margin:0 auto}
  .hm i{aspect-ratio:1;border-radius:3px;background:var(--accent);
        box-shadow:inset 0 0 0 1px rgba(20,20,19,.045)}
  .hmfoot{display:flex;justify-content:space-between;align-items:center;margin-top:11px;
          font:500 10px/1 "DM Mono",monospace;color:var(--dim);letter-spacing:.07em;text-transform:uppercase}
  .scale{display:flex;gap:2px}
  .scale i{width:13px;height:9px;border-radius:2px;background:var(--accent)}
  .empty{color:var(--dim);font-size:13.5px;padding:17px;background:var(--card);
         border:1px solid var(--line);border-radius:14px}
  .note{color:var(--dim);font-size:12.5px;line-height:1.75;margin-top:30px;
        border-top:1px solid var(--line);padding-top:18px;max-width:72ch}
  code{font:12px "DM Mono",monospace;background:rgba(20,20,19,.055);padding:1px 5px;border-radius:3px}
  .trail{color:var(--dim);font-size:12.5px;line-height:1.6}
  .who{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media (max-width:760px){.who{grid-template-columns:1fr}}
  .person{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 17px}
  .pm{font-size:14.5px;font-weight:600;word-break:break-all}
  .ps{font:500 10.5px/1.4 "DM Mono",monospace;letter-spacing:.07em;text-transform:uppercase;
      color:var(--dim);margin:4px 0 12px}
  .pf{display:flex;gap:7px}
  .pf input{flex:1;min-width:0;padding:9px 11px;border:1px solid var(--line);border-radius:10px;
      font:13.5px Archivo,sans-serif;background:var(--paper);color:var(--ink)}
  .pf input:focus{outline:2px solid var(--accent);outline-offset:1px;border-color:transparent}
  .pf button{padding:9px 15px;border:none;border-radius:10px;background:var(--ink);color:var(--paper);
      cursor:pointer;font:600 10.5px/1 "DM Mono",monospace;letter-spacing:.12em;text-transform:uppercase}
  .login{max-width:330px;margin:16vh auto;padding:0 22px;text-align:center}
  .login h1{font-size:26px;margin-bottom:4px}
  .login p{color:var(--dim);font-size:13px;margin:0 0 18px}
  .login input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:12px;
       font:15px Archivo,sans-serif;margin-top:9px;background:var(--card);color:var(--ink)}
  .login input:focus{outline:2px solid var(--accent);outline-offset:1px;border-color:transparent}
  .login button{width:100%;margin-top:12px;padding:13px;border:none;border-radius:12px;
       background:var(--ink);color:var(--paper);cursor:pointer;
       font:600 11.5px/1 "DM Mono",monospace;letter-spacing:.14em;text-transform:uppercase}
  .err{color:#c0392b;font-size:13px;margin-top:12px}
  .ok{color:var(--dim);font-size:13px;margin-top:12px}
</style>
</head><body>

<?php if (!$authed): ?>
<div class="login">
  <h1>Who picked up what</h1>
  <?php if ($isSetup): ?>
    <p>Nobody has set a password yet. Put in your own address and choose one — it is
       written straight onto the server, never into the repo.</p>
    <form method="post" autocomplete="off">
      <input type="email" name="new_user" placeholder="Your email address" autofocus required>
      <input type="password" name="new_password" placeholder="Password (8 or more)" minlength="8" required>
      <button type="submit">Set it</button>
    </form>
    <?php if ($setupError): ?><p class="err"><?= h($setupError) ?></p><?php endif; ?>
  <?php else: ?>
    <p>The Kleinenberg table, counted.</p>
    <form method="post">
      <input type="email" name="user" placeholder="Email address" autofocus autocomplete="username">
      <input type="password" name="password" placeholder="Password" autocomplete="current-password">
      <button type="submit">Sign in</button>
    </form>
    <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
  <?php endif; ?>
</div>

<?php else: ?>
<div class="wrap">
  <div class="top">
    <div>
      <h1>Who picked up what</h1>
      <p class="sub"><?= count($visits) ?> visits · <?= $opened ?> cards picked up ·
         <?= count($presses) ?> presses · last <?= $days ?> day<?= $days === 1 ? '' : 's' ?></p>
    </div>
    <a class="out" href="?logout=1"><?= h($who) ?> · Sign out</a>
  </div>

  <?php if ($notice): ?><p class="ok"><?= h($notice) ?></p><?php endif; ?>

  <div class="range">
    <?php foreach ([1 => '24 hours', 7 => '7 days', 30 => '30 days', 90 => '3 months', 365 => '1 year'] as $d => $label): ?>
      <a href="?days=<?= $d ?>" class="<?= $d === $days ? 'on' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="tiles">
    <div class="tile"><div class="n"><?= count($visits) ?></div><div class="l">Visits</div></div>
    <div class="tile"><div class="n"><?= $avgTime ? mmss($avgTime) : '—' ?></div><div class="l">Average stay</div></div>
    <div class="tile"><div class="n"><?= $medTime ? mmss((float) $medTime) : '—' ?></div><div class="l">Median stay</div></div>
    <div class="tile"><div class="n"><?= $opened ?></div><div class="l">Cards picked up</div></div>
    <div class="tile"><div class="n"><?= $turned ?></div><div class="l">Backs read</div></div>
    <div class="tile"><div class="n"><?= $films ?></div><div class="l">Films played</div></div>
    <div class="tile"><div class="n"><?= $avgDeep ? round($avgDeep) . '%' : '—' ?></div><div class="l">Down the table</div></div>
  </div>

  <section>
    <h2>Visits per day</h2>
    <div class="bars">
      <?php foreach ($byDay as $day => $n): ?>
        <div class="bw" title="<?= h($day) ?>: <?= $n ?>">
          <div class="b" style="height:<?= $n / $maxDay * 100 ?>%"></div>
          <?php if (count($byDay) <= 31): ?><div class="bl"><?= h(substr($day, 8, 2)) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section>
    <h2>Where the hands go</h2>
    <div class="two">
      <?php foreach ([['On a big screen', $heatDesk], ['On a phone', $heatPhone]] as [$label, $H]): ?>
        <div>
          <div class="hmwrap">
            <?php if (!$H['n']): ?>
              <p class="trail" style="margin:4px 2px">No presses from this kind of screen yet.</p>
            <?php else: ?>
              <div class="hm" style="grid-template-columns:repeat(<?= $H['cols'] ?>,1fr);
                          max-width:<?= $H['cols'] * 27 ?>px">
                <?php foreach ($H['grid'] as $cell):
                        $a = $H['max'] ? $cell / $H['max'] : 0;
                        // a touch of curve, so one hot cell doesn't black out the rest
                        $a = $a > 0 ? 0.1 + 0.9 * sqrt($a) : 0; ?>
                  <i style="opacity:<?= round($a, 3) ?>" title="<?= $cell ?> press<?= $cell === 1 ? '' : 'es' ?>"></i>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div class="hmfoot">
              <span><?= h($label) ?> · <?= $H['n'] ?> presses</span>
              <span class="scale">
                <?php foreach ([.12, .32, .55, .78, 1] as $s): ?><i style="opacity:<?= $s ?>"></i><?php endforeach; ?>
              </span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="note" style="margin-top:14px;border:0;padding:0">
      Top left is the top left of the table, the bottom row is the far end of it. The pile is
      dealt out again on every visit, so this is where a hand reaches, not which card it found —
      that is the next two tables.
    </p>
  </section>

  <section>
    <h2>What they pick up</h2>
    <div class="two">
      <div>
        <?php $t = tally($cards, 'item', 18); if (!$t): ?><div class="empty">Nothing picked up yet</div><?php else: ?>
        <table><tr><th>Card</th><th class="n">Times</th></tr>
        <?php foreach ($t as $k => $n):
                $kind = '';
                foreach ($cards as $c) if (($c['item'] ?? '') === $k) { $kind = $c['kind'] ?? ''; break; } ?>
          <tr><td><?php if ($kind): ?><span class="k"><?= h($kind) ?></span><?php endif; ?><?= h($k) ?></td>
              <td class="n"><?= $n ?></td></tr>
        <?php endforeach; ?>
        </table><?php endif; ?>
      </div>
      <div>
        <?php if (!$lingered): ?>
          <div class="empty">Not enough repeat looks yet to rank by how long a card is held.</div>
        <?php else: ?>
        <table><tr><th>Held the longest</th><th class="n">Average</th></tr>
        <?php foreach ($lingered as $k => $d): ?>
          <tr><td><?php if ($d['kind']): ?><span class="k"><?= h($d['kind']) ?></span><?php endif; ?><?= h($k) ?></td>
              <td class="n"><?= mmss($d['avg']) ?></td></tr>
        <?php endforeach; ?>
        </table>
        <p class="trail" style="margin:9px 2px 0">Cards picked up twice or more, so one long stare
           can't win it on its own.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section>
    <h2>What they are after</h2>
    <div class="three">
      <div>
        <?php $t = tally($cards, 'kind', 10); if (!$t): ?><div class="empty">No data yet</div><?php else: ?>
        <table><tr><th>Kind of card</th><th class="n">Opened</th></tr>
        <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
        </table><?php endif; ?>
      </div>
      <div>
        <?php $t = tally($events, 'name', 10); if (!$t): ?><div class="empty">No data yet</div><?php else: ?>
        <table><tr><th>What they did</th><th class="n">Times</th></tr>
        <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
        </table><?php endif; ?>
      </div>
      <div>
        <?php $t = tally($presses, 'ui', 10); if (!$t): ?><div class="empty">Every press landed on a card</div><?php else: ?>
        <table><tr><th>Presses off the cards</th><th class="n">Times</th></tr>
        <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
        </table><?php endif; ?>
      </div>
    </div>
  </section>

  <section>
    <h2>How long they stay</h2>
    <div class="rows">
      <?php $maxB = max(1, ...array_values($buckets)); foreach ($buckets as $label => $n): ?>
        <div class="row">
          <span class="lab"><?= h($label) ?></span>
          <span class="track"><span class="fill" style="width:<?= $n / $maxB * 100 ?>%"></span></span>
          <span class="val"><?= $n ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section>
    <h2>Where they are</h2>
    <div class="three">
      <div>
        <?php $t = tally($visits, 'country', 14); if (!$t): ?>
          <div class="empty">Nothing placed yet — visits from this house aren't looked up</div><?php else: ?>
        <table><tr><th>Country</th><th class="n">Visits</th></tr>
        <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
        </table><?php endif; ?>
      </div>
      <div>
        <?php $t = tally($visits, 'city', 14); if (!$t): ?><div class="empty">No cities yet</div><?php else: ?>
        <table><tr><th>City</th><th class="n">Visits</th></tr>
        <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
        </table><?php endif; ?>
      </div>
      <div>
        <?php $t = tally($visits, 'tz', 14); if (!$t): ?><div class="empty">No clocks yet</div><?php else: ?>
        <table><tr><th>Their clock</th><th class="n">Visits</th></tr>
        <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
        </table><?php endif; ?>
      </div>
    </div>
  </section>

  <section>
    <h2>How they arrive</h2>
    <div class="three">
      <div>
        <?php $t = tally($visits, 'ref', 10); if (!$t): ?><div class="empty">All straight in, no referrer</div><?php else: ?>
        <table><tr><th>From</th><th class="n">Visits</th></tr>
        <?php foreach ($t as $k => $n): ?>
          <tr><td><?= h(parse_url($k, PHP_URL_HOST) ?: $k) ?></td><td class="n"><?= $n ?></td></tr>
        <?php endforeach; ?>
        </table><?php endif; ?>
      </div>
      <div>
        <?php if (!$devices): ?><div class="empty">No data yet</div><?php else: ?>
        <table><tr><th>On what</th><th class="n">Visits</th></tr>
        <?php foreach ($devices as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
        </table><?php endif; ?>
      </div>
      <div>
        <?php if (!$grounds): ?><div class="empty">No data yet</div><?php else: ?>
        <table><tr><th>Ground</th><th class="n">Chosen</th></tr>
        <?php foreach (array_slice($grounds, 0, 10, true) as $k => $n): ?>
          <tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
        </table><?php endif; ?>
      </div>
    </div>
  </section>

  <section>
    <h2>One visit at a time</h2>
    <?php if (!$seen): ?><div class="empty">Nobody has been by yet</div><?php else: ?>
    <table>
      <tr><th>When</th><th>Where from</th><th>What they picked up</th><th class="n">Cards</th><th class="n">Stayed</th></tr>
      <?php foreach ($seen as $s):
        $v = $s['v'];
        // a visit with no place at all keeps neither name nor the comma between them
        $place = implode(', ', array_filter([$v['city'] ?? null, $v['country'] ?? null]));
        $items = [];
        foreach ($s['cards'] as $c) { $i = ($c['item'] ?? '') ?: ($c['kind'] ?? '?'); if ($i !== '') $items[] = $i; } ?>
        <tr>
          <td><?= h($s['when']->format('d M · H:i')) ?></td>
          <td><?= h($place ?: ($v['tz'] ?? '—')) ?><br>
              <span class="trail"><?= h(device($v['vw'] ?? null)) ?><?= !empty($v['lang']) ? ' · ' . h($v['lang']) : '' ?></span></td>
          <td class="trail"><?= $items ? h(implode(' → ', array_slice($items, 0, 7)))
                                       . (count($items) > 7 ? ' → …' : '') : 'nothing — just looked' ?></td>
          <td class="n"><?= count($items) ?></td>
          <td class="n"><?= !empty($s['secs']) ? mmss((float) $s['secs']) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>

  <section>
    <h2>Who can get in</h2>
    <div class="who">
      <?php foreach ($people as $mail => $hash): ?>
        <form class="person" method="post" autocomplete="off">
          <input type="hidden" name="token" value="<?= h($_SESSION['sk5k_token']) ?>">
          <input type="hidden" name="set_for" value="<?= h($mail) ?>">
          <div class="pm"><?= h($mail) ?><?php if ($mail === $who): ?> <span class="k">you</span><?php endif; ?></div>
          <div class="ps"><?= $hash ? 'has a password' : 'no password yet, so cannot sign in' ?></div>
          <div class="pf">
            <input type="password" name="set_pass" minlength="8" autocomplete="new-password"
                   placeholder="<?= $hash ? 'Change the password' : 'Give them a password' ?>">
            <button type="submit">Set</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
    <p class="trail" style="margin:10px 2px 0">
      Passwords are kept as hashes, so nobody — not even this page — can read one back out.
      Setting one for somebody else is how they get in the first time; tell them what it is,
      and they can change it here themselves afterwards.
    </p>
  </section>

  <p class="note">
    Counted here, by us. No cookies and no third party: the visit id is a random string in
    <code>sessionStorage</code> that dies with the tab, so nobody is followed from one day to the
    next and two visits by the same person are two strangers. Raw IP addresses are never written
    down — the country and city come from a lookup keyed by <code>sha256(ip)</code>, and only the
    answer is kept. Anyone whose browser sends <code>Do Not Track</code> or
    <code>Global Privacy Control</code> is not recorded at all.
  </p>
</div>
<?php endif; ?>
</body></html>
