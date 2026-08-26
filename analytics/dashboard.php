<?php
declare(strict_types=1);
session_start();

$configFile = __DIR__ . '/config.php';
$hasConfig  = is_readable($configFile);
$config     = $hasConfig ? require $configFile : ['dashboard_password' => null];

$error = null;
$setupError = null;

// First visit ever: no config.php on the server yet (it's gitignored, so a
// git-deployed copy of this site never has one) — let whoever gets here first
// set the password, written straight to the server, never through git.
if (!$hasConfig && isset($_POST['new_password'])) {
    $new = (string) $_POST['new_password'];
    if (strlen($new) < 8) {
        $setupError = 'Use at least 8 characters.';
    } else {
        $php = "<?php\n// Written by dashboard.php on first setup. Gitignored — never touches the repo.\nreturn [\n    'dashboard_password' => " . var_export($new, true) . ",\n];\n";
        if (file_put_contents($configFile, $php, LOCK_EX) !== false) {
            $hasConfig = true;
            $_SESSION['tvh_analytics_authed'] = true;
        } else {
            $setupError = "Couldn't write config.php — check the analytics/ folder is writable, or create it by hand from config.example.php.";
        }
    }
}

if ($hasConfig && isset($_POST['password'])) {
    if ($config['dashboard_password'] !== null
        && hash_equals((string) $config['dashboard_password'], (string) $_POST['password'])) {
        $_SESSION['tvh_analytics_authed'] = true;
    } else {
        $error = 'Wrong password.';
    }
}
if (isset($_GET['logout'])) unset($_SESSION['tvh_analytics_authed']);

$authed = $_SESSION['tvh_analytics_authed'] ?? false;

function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$days  = max(1, min(365, (int) ($_GET['days'] ?? 30)));
$since = new DateTimeImmutable("-{$days} days");

$rows = [];
if ($authed && is_readable(__DIR__ . '/visits.log')) {
    foreach (file(__DIR__ . '/visits.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $e = json_decode($line, true);
        if (!is_array($e) || empty($e['t'])) continue;
        try { $when = new DateTimeImmutable($e['t']); } catch (Exception $ex) { continue; }
        if ($when < $since) continue;
        $e['_when'] = $when;
        $rows[] = $e;
    }
}

/** Count values of one field, most frequent first. */
function tally(array $rows, string $field, ?string $type = null, int $limit = 15): array {
    $out = [];
    foreach ($rows as $r) {
        if ($type !== null && ($r['type'] ?? '') !== $type) continue;
        $v = $r[$field] ?? null;
        if ($v === null || $v === '') continue;
        $out[(string) $v] = ($out[(string) $v] ?? 0) + 1;
    }
    arsort($out);
    return array_slice($out, 0, $limit, true);
}

$views     = array_values(array_filter($rows, fn($r) => ($r['type'] ?? '') === 'pageview'));
$durations = array_values(array_filter($rows, fn($r) => ($r['type'] ?? '') === 'duration' && ($r['dur'] ?? 0) > 0));
$events    = array_values(array_filter($rows, fn($r) => ($r['type'] ?? '') === 'event'));
$sessions  = array_unique(array_filter(array_column($rows, 'sid')));

$avgDur = $durations ? array_sum(array_column($durations, 'dur')) / count($durations) : 0;
$scrolls = array_filter(array_column($durations, 'scroll'), fn($s) => $s !== null);
$avgScroll = $scrolls ? array_sum($scrolls) / count($scrolls) : 0;

// one bar per day across the window
$byDay = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $byDay[(new DateTimeImmutable("-{$i} days"))->format('Y-m-d')] = 0;
}
foreach ($views as $v) {
    $k = $v['_when']->format('Y-m-d');
    if (isset($byDay[$k])) $byDay[$k]++;
}
$maxDay = max(1, ...array_values($byDay));

// per-session journeys: what one visitor did, in order
$journeys = [];
foreach ($rows as $r) {
    $sid = $r['sid'] ?? '';
    if ($sid === '') continue;
    $journeys[$sid]['sid'] = $sid;
    $journeys[$sid]['first'] ??= $r['_when'];
    $journeys[$sid]['last'] = $r['_when'];
    $journeys[$sid]['country'] ??= $r['country'] ?? null;
    $journeys[$sid]['city'] ??= $r['city'] ?? null;
    $journeys[$sid]['tz'] ??= $r['tz'] ?? null;
    $journeys[$sid]['vw'] ??= $r['vw'] ?? null;
    $journeys[$sid]['ref'] ??= $r['ref'] ?? null;
    if (($r['type'] ?? '') === 'pageview')  $journeys[$sid]['pages'][] = $r['page'];
    if (($r['type'] ?? '') === 'event')     $journeys[$sid]['events'][] = $r['name'];
    if (($r['type'] ?? '') === 'duration')  $journeys[$sid]['secs'] = ($journeys[$sid]['secs'] ?? 0) + (int) $r['dur'];
}
uasort($journeys, fn($a, $b) => $b['last'] <=> $a['last']);
$journeys = array_slice($journeys, 0, 40, true);

function device(?int $vw): string {
    if (!$vw) return 'unknown';
    return $vw < 768 ? 'mobile' : ($vw < 1024 ? 'tablet' : 'desktop');
}
$devices = [];
foreach ($views as $v) { $d = device($v['vw'] ?? null); $devices[$d] = ($devices[$d] ?? 0) + 1; }
arsort($devices);
?>
<!DOCTYPE html>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Visits — Tim van Heukelom</title>
<link rel="icon" href="../favicon.ico">
<style>
  *{box-sizing:border-box}
  body{margin:0;font:15px/1.5 "Open Sans",-apple-system,BlinkMacSystemFont,'Helvetica Neue',Arial,sans-serif;
       color:#2f2f2f;background:#f4f4f6}
  .wrap{max-width:1040px;margin:0 auto;padding:40px 20px 80px}
  h1{font-weight:300;font-size:32px;margin:0 0 6px;letter-spacing:-.01em}
  h2{font-weight:600;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#63636b;margin:0 0 14px}
  a{color:#2f2f2f}
  .top{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
  .logout{font-size:13px;color:#63636b;text-decoration:none}
  .login{max-width:320px;margin:120px auto;text-align:center}
  .login input{width:100%;padding:11px 13px;border:1px solid rgba(0,0,0,.16);border-radius:12px;font-size:15px;margin-top:16px}
  .login button{width:100%;margin-top:12px;padding:12px;border:none;border-radius:12px;background:#2f2f2f;color:#fff;
       font:600 13px/1 'Helvetica Neue',Arial,sans-serif;letter-spacing:.05em;text-transform:uppercase;cursor:pointer}
  .err{color:#d5443f;font-size:13px;margin-top:10px}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:30px}
  .stat{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:18px}
  .stat .n{font-size:28px;font-weight:300}
  .stat .l{font-size:12px;color:#63636b;text-transform:uppercase;letter-spacing:.06em;margin-top:2px}
  .range{display:flex;gap:8px;margin-bottom:22px}
  .range a{font-size:13px;padding:6px 14px;border-radius:999px;border:1px solid rgba(0,0,0,.16);
       text-decoration:none;color:#63636b}
  .range a.is-current{background:#2f2f2f;color:#fff;border-color:#2f2f2f}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-bottom:32px}
  @media (max-width:700px){.grid2{grid-template-columns:1fr}}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:16px;overflow:hidden;
        border:1px solid rgba(0,0,0,.08)}
  th,td{text-align:left;padding:9px 14px;font-size:14px;border-bottom:1px solid rgba(0,0,0,.06)}
  th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#63636b;font-weight:600}
  tr:last-child td{border-bottom:none}
  td.n{text-align:right;color:#63636b;white-space:nowrap}
  .bars{display:flex;align-items:flex-end;gap:3px;height:130px;background:#fff;border:1px solid rgba(0,0,0,.08);
        border-radius:16px;padding:16px;margin-bottom:32px}
  .bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%}
  .bar{width:100%;background:#3b7dd8;border-radius:3px 3px 0 0;min-height:2px}
  .bar-label{font-size:9px;color:#9a9aa4;margin-top:4px}
  .empty{color:#9a9aa4;font-size:14px;padding:20px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px}
  .j-pages{color:#63636b;font-size:13px}
  .note{color:#63636b;font-size:12.5px;line-height:1.7;margin-top:34px}
  code{background:rgba(0,0,0,.06);padding:1px 5px;border-radius:3px;font-size:11.5px}
</style>

<?php if (!$authed): ?>
<div class="login">
  <h1 style="font-size:22px">Visits</h1>
  <?php if (!$hasConfig): ?>
    <p style="color:#63636b;font-size:13px;margin:0">First time here — set a password for this dashboard.</p>
    <form method="post">
      <input type="password" name="new_password" placeholder="Choose a password" autofocus minlength="8">
      <button type="submit">Set password</button>
    </form>
    <?php if ($setupError): ?><p class="err"><?= h($setupError) ?></p><?php endif; ?>
  <?php else: ?>
    <form method="post">
      <input type="password" name="password" placeholder="Password" autofocus>
      <button type="submit">Sign in</button>
    </form>
    <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="wrap">
  <div class="top">
    <h1>Visits</h1>
    <a class="logout" href="?logout=1">Sign out</a>
  </div>
  <p style="color:#63636b;margin:0 0 20px;font-size:13px">
    <?= count($views) ?> pageviews · <?= count($sessions) ?> sessions · last <?= $days ?> days
  </p>

  <div class="range">
    <?php foreach ([1 => '24h', 7 => '7 days', 30 => '30 days', 365 => '1 year'] as $d => $label): ?>
      <a href="?days=<?= $d ?>" class="<?= $d === $days ? 'is-current' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="stats">
    <div class="stat"><div class="n"><?= count($views) ?></div><div class="l">Pageviews</div></div>
    <div class="stat"><div class="n"><?= count($sessions) ?></div><div class="l">Sessions</div></div>
    <div class="stat"><div class="n"><?= $avgDur ? round($avgDur) . 's' : '—' ?></div><div class="l">Avg. time on page</div></div>
    <div class="stat"><div class="n"><?= $avgScroll ? round($avgScroll) . '%' : '—' ?></div><div class="l">Avg. scroll depth</div></div>
    <div class="stat"><div class="n"><?= count($events) ?></div><div class="l">Interactions</div></div>
  </div>

  <h2>Pageviews per day</h2>
  <div class="bars">
    <?php foreach ($byDay as $day => $n): ?>
      <div class="bar-wrap" title="<?= h($day) ?>: <?= $n ?>">
        <div class="bar" style="height:<?= $n / $maxDay * 100 ?>%"></div>
        <?php if (count($byDay) <= 31): ?>
          <div class="bar-label"><?= h(substr($day, 8, 2)) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="grid2">
    <div>
      <h2>Pages</h2>
      <?php $t = tally($rows, 'page', 'pageview', 20); if (!$t): ?><div class="empty">No data yet</div><?php else: ?>
      <table><tr><th>Page</th><th class="n">Views</th></tr>
      <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
      </table><?php endif; ?>
    </div>
    <div>
      <h2>Country</h2>
      <?php $t = tally($rows, 'country', 'pageview'); if (!$t): ?><div class="empty">No data yet — local visits aren't geolocated</div><?php else: ?>
      <table><tr><th>Country</th><th class="n">Views</th></tr>
      <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
      </table><?php endif; ?>
    </div>
    <div>
      <h2>City</h2>
      <?php $t = tally($rows, 'city', 'pageview'); if (!$t): ?><div class="empty">No data yet</div><?php else: ?>
      <table><tr><th>City</th><th class="n">Views</th></tr>
      <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
      </table><?php endif; ?>
    </div>
    <div>
      <h2>Referrers</h2>
      <?php $t = tally($rows, 'ref', 'pageview'); if (!$t): ?><div class="empty">All direct</div><?php else: ?>
      <table><tr><th>From</th><th class="n">Views</th></tr>
      <?php foreach ($t as $k => $n): ?><tr><td><?= h(parse_url($k, PHP_URL_HOST) ?: $k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
      </table><?php endif; ?>
    </div>
    <div>
      <h2>Interactions</h2>
      <?php $t = tally($rows, 'name', 'event'); if (!$t): ?><div class="empty">No data yet</div><?php else: ?>
      <table><tr><th>What</th><th class="n">Times</th></tr>
      <?php foreach ($t as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
      </table><?php endif; ?>
    </div>
    <div>
      <h2>Device</h2>
      <?php if (!$devices): ?><div class="empty">No data yet</div><?php else: ?>
      <table><tr><th>Type</th><th class="n">Views</th></tr>
      <?php foreach ($devices as $k => $n): ?><tr><td><?= h($k) ?></td><td class="n"><?= $n ?></td></tr><?php endforeach; ?>
      </table><?php endif; ?>
    </div>
  </div>

  <h2>Recent visitors</h2>
  <?php if (!$journeys): ?><div class="empty">No visits yet</div><?php else: ?>
  <table>
    <tr><th>When</th><th>Where</th><th>Journey</th><th class="n">Pages</th><th class="n">Time</th></tr>
    <?php foreach ($journeys as $j): ?>
      <tr>
        <td><?= h($j['last']->format('d M H:i')) ?></td>
        <td><?= h(trim(($j['city'] ?? '') . ' ' . ($j['country'] ?? '')) ?: ($j['tz'] ?? '—')) ?><br>
            <span class="j-pages"><?= h(device($j['vw'] ?? null)) ?></span></td>
        <td class="j-pages"><?= h(implode(' → ', array_slice($j['pages'] ?? [], 0, 6)) ?: '—') ?>
            <?= !empty($j['events']) ? '<br>' . h(implode(', ', array_unique($j['events']))) : '' ?></td>
        <td class="n"><?= count($j['pages'] ?? []) ?></td>
        <td class="n"><?= isset($j['secs']) ? $j['secs'] . 's' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <p class="note">
    No cookies and nothing stored on the visitor's device beyond a
    <code>sessionStorage</code> id that dies with the tab, so a visitor is never
    followed across days. Raw IP addresses are never written to disk — the
    country/city lookup is keyed by <code>sha256(ip)</code> and only the result
    is cached. Visitors sending <code>Do Not Track</code> or
    <code>Global Privacy Control</code> are not recorded at all.
  </p>
</div>
<?php endif; ?>
