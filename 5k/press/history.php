<?php
/* ---- what happened on /press, newest first ---------------------------------
 *
 * sanderkleinenberg.com/press/history.php, for the owner and admins; anyone
 * else is sent to admin.php to sign in. It shows the history gate.php keeps in
 * history.log.php on the server: every change made on admin.php and by whom,
 * every sign-in, a promoter's visits to the kit (at most one an hour), their
 * downloads, and passwords that didn't work. A new history starts with what
 * the list already knew ("from the list"): who was added, revoked or made an
 * admin, when, and by whom.
 *
 *   ?who=<id>        one person: what they did and what was done to them
 *   ?kind=changes    changes on admin.php; use = sign-ins, visits and
 *        refused     downloads; refused = passwords that didn't work
 *   ?page=2          older ones, 100 a page
 *   ?csv=1           every row these filters show, as a spreadsheet
 */
require __DIR__ . '/gate.php';

$me = press_who();
if (!press_is_staff($me)) {
    header('Location: ' . press_path() . 'admin.php', true, 302);
    exit;
}
date_default_timezone_set('Europe/Amsterdam');
press_history_ready();

const PER_PAGE = 100;
const KINDS = ['all' => 'Everything', 'changes' => 'Changes', 'use' => 'Sign-ins and downloads', 'refused' => 'Refused passwords'];
const OF_KIND = [
    'changes' => ['add', 'save', 'reopen', 'renew', 'revoke', 'delete', 'admin_add', 'admin_renew', 'admin_remove', 'owner_name'],
    'use'     => ['signin', 'visit', 'file'],
    'refused' => ['ended', 'wrong'],
];

function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/* An event as words: [who, before, whom, after], e.g. ["Tim", "added ", "Paradiso", ""]. */
function says(array $e): array {
    $by   = (string) ($e['by_name'] ?? '');
    $to   = (string) ($e['name'] ?? '');
    $area = ($e['detail'] ?? '') === 'admin' ? 'the admin page' : 'the press kit';
    return match ((string) $e['do']) {
        'add'          => [$by, 'added ', $to, ''],
        'save'         => [$by, 'changed ', $to, ''],
        'reopen'       => [$by, 'let ', $to, ' back in'],
        'renew'        => [$by, 'made a new password for ', $to, ''],
        'revoke'       => [$by, 'revoked ', $to, ''],
        'delete'       => [$by, 'deleted ', $to, ' from the list'],
        'admin_add'    => [$by, 'made ', $to, ' an admin'],
        'admin_renew'  => [$by, 'made a new admin password for ', $to, ''],
        'admin_remove' => [$by, 'removed ', $to, ' as admin'],
        'owner_name'   => [$by, 'set the owner’s name to ', $to, ''],
        'signin'       => [$by, 'signed in to ' . $area, '', ''],
        'visit'        => [$by, 'opened the press kit', '', ''],
        'file'         => [$by, 'downloaded ', basename((string) ($e['detail'] ?? '')), ''],
        'ended'        => [$by, 'tried a password that no longer works, on ' . $area, '', ''],
        'wrong'        => ['Someone', 'typed a wrong password on ' . $area, '', ''],
        default        => [$by, (string) $e['do'], '', ''],
    };
}

/* The line under an event: what a change set. Sign-ins and downloads say it all already. */
function detail_of(array $e): string {
    return in_array($e['do'], OF_KIND['changes'], true) ? (string) ($e['detail'] ?? '') : '';
}

$list = press_list();
$all  = press_history();

/* Everyone the history mentions, with their latest name: the who filter. */
$people = [];
foreach ($all as $e) {
    foreach ([['by', 'by_name'], ['id', 'name']] as [$k, $n]) {
        $pid = (string) ($e[$k] ?? '');
        if ($pid === '' || !preg_match('/^(me|[ap][0-9a-f]{8})$/', $pid)) continue;
        if ($e['do'] === 'owner_name' && $k === 'by') continue;   /* by_name is the old name there */
        $people[$pid] = (string) ($e[$n] ?? ($people[$pid] ?? $pid));
    }
}
if (isset($people[PRESS_ME])) $people[PRESS_ME] = press_name_of($list, PRESS_ME);
$staff = $promoters = [];
foreach ($people as $pid => $n) {
    if ($pid === PRESS_ME || press_is_admin_id($pid)) $staff[$pid] = $n; else $promoters[$pid] = $n;
}
uasort($promoters, 'strcasecmp');
uasort($staff, fn($a, $b) => strcasecmp($a, $b));

$who  = (string) ($_GET['who'] ?? '');
$who  = isset($people[$who]) ? $who : '';
$kind = (string) ($_GET['kind'] ?? 'all');
$kind = isset(KINDS[$kind]) ? $kind : 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));

$rows = array_values(array_filter($all, function (array $e) use ($who, $kind) {
    if ($kind !== 'all' && !in_array($e['do'], OF_KIND[$kind], true)) return false;
    return $who === '' || ($e['by'] ?? null) === $who || ($e['id'] ?? null) === $who;
}));
$rows = array_reverse($rows);                                  /* newest first; the same second keeps file order */
usort($rows, fn($a, $b) => (int) $b['t'] <=> (int) $a['t']);

/* This page's address with the filters kept and $over changed (null: left out). */
function query(array $over): string {
    global $who, $kind;
    $q = array_merge(['who' => $who, 'kind' => $kind === 'all' ? '' : $kind], $over);
    return '?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null));
}

if (isset($_GET['csv'])) {
    /* a spreadsheet cell starting with = + - or @ would be read as a formula */
    $cell = fn(string $s) => preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="press-kit-history-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");                              /* so Excel reads the accents right */
    fputcsv($out, ['Date', 'Time', 'Who', 'What', 'Detail'], ',', '"', '');
    foreach ($rows as $e) {
        [$by, $before, $whom, $after] = says($e);
        $detail = detail_of($e) . (!empty($e['seeded']) ? (detail_of($e) !== '' ? '; ' : '') . 'from the list' : '');
        fputcsv($out, array_map($cell, [date('Y-m-d', (int) $e['t']), date('H:i', (int) $e['t']), $by,
                                        $before . $whom . $after, $detail]), ',', '"', '');
    }
    fclose($out);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$total = count($rows);
$pages = max(1, (int) ceil($total / PER_PAGE));
$page  = min($page, $pages);
$shown = array_slice($rows, ($page - 1) * PER_PAGE, PER_PAGE);
$days  = [];
foreach ($shown as $e) $days[date('D j M Y', (int) $e['t'])][] = $e;
$seeds = (bool) array_filter($shown, fn($e) => !empty($e['seeded']));

function options(array $group, string $who): string {
    $o = '';
    foreach ($group as $pid => $n) {
        $o .= '<option value="' . h($pid) . '"' . ($pid === $who ? ' selected' : '') . '>' . h($n) . '</option>';
    }
    return $o;
}

$css = <<<'CSS'
body.admin{place-items:start center}
main.wide{width:min(640px,100%)}
.top{display:flex; align-items:flex-end; justify-content:space-between; gap:16px}
.top .logo{width:min(190px,52%)}
.links{display:flex; flex-wrap:wrap; justify-content:flex-end; gap:8px 18px}
.kit{font-family:var(--mono); font-size:11px; letter-spacing:.18em; text-transform:uppercase; color:var(--ink); text-decoration:none; border-bottom:1px solid currentColor; padding-bottom:2px; white-space:nowrap}
.kit:hover{color:var(--accent)}
form.filters{display:grid; grid-template-columns:minmax(0,1.3fr) minmax(0,1fr) auto; gap:8px; align-items:end; margin-top:20px}
form.filters label{display:flex; flex-direction:column; gap:6px}
form.filters select{padding:11px 12px; font-size:15px; -webkit-appearance:none; appearance:none;
  background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23181817'/%3E%3C/svg%3E") no-repeat right 12px center; padding-right:30px}
form.filters button{padding:12px 16px}
.count{display:flex; flex-wrap:wrap; justify-content:space-between; gap:6px 16px; margin:16px 0 0; font-family:var(--mono); font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted)}
.count a{color:var(--ink); text-underline-offset:3px}
.count a:hover{color:var(--accent)}
.log{rotate:.2deg}
h2{margin:0; font-family:var(--mono); font-weight:500; font-size:11px; letter-spacing:.2em; text-transform:uppercase; color:var(--ink)}
h2 + ul{margin-top:8px}
ul + h2{margin-top:26px}
ul{list-style:none; margin:0; padding:0}
li{display:grid; grid-template-columns:46px minmax(0,1fr); gap:2px 10px; padding:10px 0; border-top:1px solid rgba(24,24,23,.13); font-size:15px; line-height:1.45; color:var(--body)}
li time{font-family:var(--mono); font-size:12px; line-height:1.9; color:var(--muted)}
li b{font-weight:700; color:var(--ink); overflow-wrap:anywhere}
li .d{grid-column:2; display:block; font-family:var(--mono); font-size:11.5px; line-height:1.55; color:var(--muted); overflow-wrap:anywhere}
li .tag{display:inline-block; margin-left:6px; padding:1px 6px; font-family:var(--mono); font-size:9.5px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); border:1px solid rgba(24,24,23,.2); vertical-align:2px}
li.no b:first-child{color:var(--accent)}
.empty{margin:0; font-size:14px; line-height:1.5; color:var(--muted)}
.seednote{margin:18px 0 0; font-size:13px; line-height:1.5; color:var(--muted)}
.pager{display:flex; justify-content:space-between; gap:16px; margin-top:18px; font-family:var(--mono); font-size:11px; letter-spacing:.16em; text-transform:uppercase}
.pager a{color:var(--ink); text-underline-offset:3px}
.pager a:hover{color:var(--accent)}
@media (max-width:560px){
  form.filters{grid-template-columns:minmax(0,1fr)}
}
CSS;

press_page_top('Press kit history', $css, 'admin');
?>
<main class="wide">
  <header class="top">
    <img class="logo" src="img/logo-black.svg" alt="Sander Kleinenberg" width="1040" height="546">
    <nav class="links"><a class="kit" href="admin.php">&larr; Access</a><a class="kit" href="<?= h(press_path()) ?>">Press kit &rarr;</a></nav>
  </header>

  <div class="slip">
    <p class="kicker">Press kit &middot; history</p>
    <h1>What happened</h1>
    <p class="lede">Every change on the access page and who made it, every sign-in and download, and passwords that didn&rsquo;t work. A promoter&rsquo;s visits count once an hour.</p>
    <form class="filters" method="get" action="history.php">
      <label>Who
        <select name="who">
          <option value="">Everyone</option>
<?php if ($staff): ?>
          <optgroup label="Owner and admins"><?= options($staff, $who) ?></optgroup>
<?php endif; ?>
<?php if ($promoters): ?>
          <optgroup label="Promoters"><?= options($promoters, $who) ?></optgroup>
<?php endif; ?>
        </select>
      </label>
      <label>What
        <select name="kind">
<?php foreach (KINDS as $k => $label): ?>
          <option value="<?= h($k) ?>"<?= $k === $kind ? ' selected' : '' ?>><?= h($label) ?></option>
<?php endforeach; ?>
        </select>
      </label>
      <button type="submit">Show</button>
    </form>
    <p class="count">
      <span><?= $total === 0 ? 'Nothing' : ($total <= PER_PAGE ? $total . ($total === 1 ? ' event' : ' events')
          : (($page - 1) * PER_PAGE + 1) . '&ndash;' . (($page - 1) * PER_PAGE + count($shown)) . ' of ' . $total . ' events') ?></span>
<?php if ($total > 0): ?>
      <a href="<?= h(query(['csv' => '1'])) ?>">Download as spreadsheet (CSV)</a>
<?php endif; ?>
    </p>
  </div>

  <section class="slip log" aria-label="History">
<?php if (!$shown): ?>
    <p class="empty"><?= $all ? 'Nothing matches these filters.' : 'Nothing has happened yet.' ?></p>
<?php endif; ?>
<?php foreach ($days as $label => $events): ?>
    <h2><?= h($label) ?></h2>
    <ul>
<?php foreach ($events as $e): [$by, $before, $whom, $after] = says($e); $d = detail_of($e); ?>
      <li<?= in_array($e['do'], OF_KIND['refused'], true) ? ' class="no"' : '' ?>>
        <time datetime="<?= h(date('c', (int) $e['t'])) ?>"><?= h(date('H:i', (int) $e['t'])) ?></time>
        <span><b><?= h($by !== '' ? $by : 'Someone') ?></b> <?= h($before) ?><?= $whom !== '' ? '<b>' . h($whom) . '</b>' : '' ?><?= h($after) ?><?= !empty($e['seeded']) ? '<span class="tag">from the list</span>' : '' ?></span>
<?php if ($d !== ''): ?>
        <span class="d"><?= h($d) ?></span>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php endforeach; ?>
<?php if ($seeds): ?>
    <p class="seednote">&ldquo;From the list&rdquo;: from before this history began. For that time only who was added, revoked or made an admin is known, and when.</p>
<?php endif; ?>
<?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
      <span><?= $page > 1 ? '<a href="' . h(query(['page' => $page - 1 > 1 ? $page - 1 : null])) . '">&larr; Newer</a>' : '' ?></span>
      <span>Page <?= $page ?> of <?= $pages ?></span>
      <span><?= $page < $pages ? '<a href="' . h(query(['page' => $page + 1])) . '">Older &rarr;</a>' : '' ?></span>
    </nav>
<?php endif; ?>
  </section>
</main>
</body>
</html>
