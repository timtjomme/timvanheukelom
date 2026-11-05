<?php
/* ---- each promoter their own password to the press kit --------------------
 *
 * sanderkleinenberg.com/press/admin.php. Opened by the owner's password (the
 * one set with set-press-password.py) or an admin's own password. Here:
 *
 *   add       a promoter: a password is made and shown once, with a message
 *             to send them. It works until a date you choose, or always, and
 *             shows the sections you switch on (all of them to start with).
 *   settings  change a promoter's end date or sections later, no new password
 *             needed. A promoter whose date has passed can be let back in.
 *   see       who has access, until when, who added them (and who revoked
 *             them), and when they last used the kit.
 *   renew     a promoter's password: the old one stops working at once.
 *   revoke    a promoter: out at once, even when signed in.
 *
 * Only the owner sees "Admins": add an admin (a password made and shown once),
 * give one a new password, or remove one (signed out at once), and set the
 * name the list uses for the owner.
 *
 * The list is promoters.php on the server, written from here; see gate.php.
 */
require __DIR__ . '/gate.php';

$self = press_path() . 'admin.php';
$me   = press_who();
if (!press_is_staff($me)) {
    press_login_page(press_try_password($self, true), 'admin');
    exit;
}
$owner  = $me === PRESS_ME;
$myName = press_name_of(press_list(), $me);
press_history_ready();   /* before any change, so a new history's start from the list never repeats it */

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
date_default_timezone_set('Europe/Amsterdam');

const QUICK = ['30' => '30 days', '91' => '3 months', '365' => '1 year'];   /* buttons that fill in the date */
const CANT_SAVE = 'Couldn’t save the list on the server: PHP needs permission to write promoters.php in the press folder. Nothing changed.';

function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function day($t): string { return date('j M Y', (int) $t); }
function count_of(int $n, string $one, string $many): string { return $n . ' ' . ($n === 1 ? $one : $many); }
function valid_id(string $id): bool { return (bool) preg_match('/^p[0-9a-f]{8}$/', $id); }

/* A typed name with its spaces tidied, and what's wrong with it (null: fine). */
function posted_name(): string { return trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? ''))); }
function name_problem(string $name, string $ask): ?string {
    if ($name === '') return $ask;
    if (!preg_match('/^.{1,80}$/u', $name)) return 'That name is longer than 80 characters.';
    return null;
}

/* "2027-02-02" -> the last second of that day here, or null if it isn't a date. */
function end_of_day(string $ymd): ?int {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new DateTimeZone('Europe/Amsterdam'));
    return $d && $d->format('Y-m-d') === $ymd ? $d->setTime(23, 59, 59)->getTimestamp() : null;
}

/* The end date a form sent: a timestamp, null for "no end date", or false
   when it can't be used ($why says why). */
function posted_until(?string &$why) {
    if (!empty($_POST['forever'])) return null;
    $t = end_of_day((string) ($_POST['until'] ?? ''));
    if ($t === null)                   { $why = 'Choose the date the password works until, or “No end date”.'; return false; }
    if ($t < time())                   { $why = 'That date has already passed. Choose today or later.'; return false; }
    if ($t > time() + 5 * 366 * 86400) { $why = 'Choose a date within five years, or “No end date”.'; return false; }
    return $t;
}

/* The sections a form switched OFF (the switches send the ones that are on). */
function posted_hidden(): array {
    $shown = array_map('strval', (array) ($_POST['show'] ?? []));
    return array_values(array_diff(array_keys(PRESS_SECTIONS), $shown));
}

/* "Bio, Photos and Logos" */
function and_list(array $words): string {
    return count($words) < 2 ? implode('', $words) : implode(', ', array_slice($words, 0, -1)) . ' and ' . end($words);
}

/* What the message to a promoter says they'll find. */
function what_they_get(array $hide): string {
    if (!$hide) return 'photos, logos, biography and rider';
    $say = ['bio' => 'biography', 'photos' => 'photos', 'logos' => 'logos', 'music' => 'music',
            'live' => 'live dates', 'rider' => 'rider', 'contact' => 'contacts'];
    $left = array_values(array_intersect_key($say, array_flip(array_diff(array_keys(PRESS_SECTIONS), $hide))));
    return $left ? and_list($left) : 'the introduction';
}

/* For the list: which sections are switched off. */
function hidden_text(array $hide): string {
    return $hide ? 'hidden: ' . strtolower(implode(', ', array_map(fn($k) => PRESS_SECTIONS[$k], $hide))) : 'all sections';
}

/* For the history: "until 20 Dec 2026, all sections" */
function access_text(?int $until, array $hide): string {
    return ($until ? 'until ' . day($until) : 'no end date') . ', ' . hidden_text($hide);
}

/* Every form here carries this, so another site can't post to it. */
$token = hash_hmac('sha256', 'press-admin', (string) $_COOKIE[PRESS_COOKIE]);
$fresh = null;   /* a password just made: shown this once, kept only as a hash */
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $do = (string) ($_POST['do'] ?? '');
    $id = (string) ($_POST['id'] ?? '');
    if (!hash_equals($token, (string) ($_POST['token'] ?? ''))) {
        $error = 'This page was out of date, so nothing changed. Try again.';
    } elseif ($do === 'signout') {
        press_sign_out();
        header('Location: ' . $self, true, 303);
        exit;
    } elseif (str_starts_with($do, 'admin_') && !$owner) {
        $error = 'Only the owner can change the admins. Nothing changed.';
    } elseif ($do === 'add') {
        $name  = posted_name();
        $hide  = posted_hidden();
        $until = posted_until($why);
        if (($why2 = name_problem($name, 'Who is it for? Fill in a name.')) !== null) {
            $error = $why2;
        } elseif ($until === false) {
            $error = $why;
        } else {
            $password = press_new_password();
            $newId    = 'p' . bin2hex(random_bytes(4));
            $ok = press_change(function (array &$l) use ($name, $password, $until, $hide, $me, $myName, $newId) {
                $l['key'] = $l['key'] ?? bin2hex(random_bytes(32));
                $l['people'][$newId] = [
                    'name' => $name, 'hash' => press_pw_hash($l['key'], $password),
                    'added' => time(), 'by' => $me, 'by_name' => $myName,
                    'until' => $until, 'hide' => $hide, 'revoked' => null,
                    'signins' => 0, 'files' => 0, 'last' => null,
                ];
            });
            if ($ok) {
                $fresh = ['name' => $name, 'password' => $password, 'until' => $until, 'hide' => $hide];
                press_log('add', $me, $myName, $newId, $name, access_text($until, $hide));
            } else {
                $error = CANT_SAVE;
            }
        }
    } elseif ($do === 'save' && valid_id($id)) {
        $hide  = posted_hidden();
        $until = posted_until($why);
        if ($until === false) {
            $error = $why;
        } else {
            $found = false;
            $old   = null;
            $ok = press_change(function (array &$l) use ($id, $until, $hide, &$found, &$old) {
                $p = $l['people'][$id] ?? null;
                if (!is_array($p) || !empty($p['revoked'])) return false;   /* revoked stays revoked */
                $old = $p;
                $l['people'][$id]['until'] = $until;
                $l['people'][$id]['hide']  = $hide;
                $found = true;
            });
            if (!$ok) {
                $error = CANT_SAVE;
            } else {
                if ($found) {
                    $oldUntil = empty($old['until']) ? null : (int) $old['until'];
                    $changed  = [];
                    if ($oldUntil !== $until) {
                        $changed[] = 'end date ' . ($oldUntil ? day($oldUntil) : 'none') . ' → ' . ($until ? day($until) : 'none');
                    }
                    if (press_hidden_of($old) !== $hide) $changed[] = 'now ' . hidden_text($hide);
                    if (!press_active($old)) {
                        press_log('reopen', $me, $myName, $id, (string) $old['name'], access_text($until, $hide));
                    } elseif ($changed) {
                        press_log('save', $me, $myName, $id, (string) $old['name'], implode('; ', $changed));
                    }
                }
                header('Location: ' . $self . ($found ? '?saved=' . $id : ''), true, 303);
                exit;
            }
        }
    } elseif (in_array($do, ['renew', 'revoke', 'delete'], true) && valid_id($id)) {
        $password = $do === 'renew' ? press_new_password() : null;
        $found    = null;
        $ok = press_change(function (array &$l) use ($do, $id, $password, &$found, $me, $myName) {
            $p = $l['people'][$id] ?? null;
            if (!is_array($p) || ($do !== 'delete' && !press_active($p))) return false;
            $found = $p;
            if ($do === 'delete') unset($l['people'][$id]);
            if ($do === 'revoke') {
                $l['people'][$id]['revoked']    = time();
                $l['people'][$id]['revoked_by'] = $me;
                $l['people'][$id]['revoked_by_name'] = $myName;
            }
            if ($do === 'renew')  $l['people'][$id]['hash'] = press_pw_hash($l['key'], $password);
        });
        if ($ok && $found) press_log($do, $me, $myName, $id, (string) $found['name']);
        if (!$ok) {
            $error = CANT_SAVE;
        } elseif ($found && $do === 'renew') {
            $fresh = ['name' => $found['name'], 'password' => $password, 'until' => $found['until'] ?: null,
                      'hide' => press_hidden_of($found)];
        } else {
            /* back to a plain GET, so a refresh doesn't post it again */
            header('Location: ' . $self . ($found && $do === 'revoke' ? '?revoked=' . $id : ''), true, 303);
            exit;
        }
    } elseif ($do === 'admin_add') {
        $name = posted_name();
        if (($why2 = name_problem($name, 'Who is the new admin? Fill in a name.')) !== null) {
            $error = $why2;
        } else {
            $password = press_new_password();
            $newId    = 'a' . bin2hex(random_bytes(4));
            $ok = press_change(function (array &$l) use ($name, $password, $newId) {
                $l['key'] = $l['key'] ?? bin2hex(random_bytes(32));
                $l['admins'][$newId] = [
                    'name' => $name, 'hash' => press_admin_hash($password),
                    'added' => time(), 'signins' => 0, 'last' => null,
                ];
            });
            if ($ok) {
                $fresh = ['kind' => 'admin', 'name' => $name, 'password' => $password];
                press_log('admin_add', $me, $myName, $newId, $name);
            } else {
                $error = CANT_SAVE;
            }
        }
    } elseif (in_array($do, ['admin_renew', 'admin_remove'], true) && press_is_admin_id($id)) {
        $password = $do === 'admin_renew' ? press_new_password() : null;
        $found    = null;
        $ok = press_change(function (array &$l) use ($do, $id, $password, &$found) {
            $a = $l['admins'][$id] ?? null;
            if (!is_array($a)) return false;
            $found = $a;
            if ($do === 'admin_remove') unset($l['admins'][$id]);
            else $l['admins'][$id]['hash'] = press_admin_hash($password);
        });
        if ($ok && $found) press_log($do, $me, $myName, $id, (string) $found['name']);
        if (!$ok) {
            $error = CANT_SAVE;
        } elseif ($found && $do === 'admin_renew') {
            $fresh = ['kind' => 'admin', 'name' => $found['name'], 'password' => $password];
        } else {
            header('Location: ' . $self . ($found ? '?removed=' . rawurlencode((string) $found['name']) : ''), true, 303);
            exit;
        }
    } elseif ($do === 'owner_name') {
        if (!$owner) {
            $error = 'Only the owner can change that. Nothing changed.';
        } elseif (($why2 = name_problem($name = posted_name(), 'Fill in your name.')) !== null) {
            $error = $why2;
        } elseif (!press_change(function (array &$l) use ($name) { $l['owner'] = $name; })) {
            $error = CANT_SAVE;
        } else {
            if ($name !== $myName) press_log('owner_name', $me, $myName, PRESS_ME, $name, 'was ' . $myName);
            header('Location: ' . $self . '?named=1', true, 303);
            exit;
        }
    } else {
        $error = 'That didn’t work. Nothing changed.';
    }
}

$list = press_list();
$on = $off = [];
foreach ($list['people'] ?? [] as $pid => $p) {
    if (!is_array($p)) continue;
    $p['id'] = (string) $pid;
    $p['hide'] = press_hidden_of($p);
    if (press_active($p)) $on[] = $p; else $off[] = $p;
}
usort($on,  fn($a, $b) => ($b['added'] ?? 0) <=> ($a['added'] ?? 0));
$endedAt = fn($p) => (int) (!empty($p['revoked']) ? $p['revoked'] : ($p['until'] ?? 0));
usort($off, fn($a, $b) => $endedAt($b) <=> $endedAt($a));

$myName = press_name_of($list, $me);   /* again: the owner may just have renamed */
$admins = [];
foreach ($list['admins'] ?? [] as $aid => $a) {
    if (!is_array($a)) continue;
    $a['id'] = (string) $aid;
    $a['count'] = count(array_filter($list['people'] ?? [], fn($p) => is_array($p) && ($p['by'] ?? '') === (string) $aid));
    $admins[] = $a;
}
usort($admins, fn($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));

$note = null;
if ($error === null && $fresh === null) {
    $gone  = (string) ($_GET['revoked'] ?? '');
    $saved = (string) ($_GET['saved'] ?? '');
    if (isset($_GET['removed'])) {
        $note = substr((string) $_GET['removed'], 0, 80) . ' is no longer an admin, and is signed out.';
    } elseif (isset($_GET['named'])) {
        $note = 'Saved. The list calls you ' . $myName . '.';
    } elseif (isset($list['people'][$gone]['name'])) {
        $note = $list['people'][$gone]['name'] . ' can’t open the press kit anymore.';
    } elseif (is_array($sp = $list['people'][$saved] ?? null)) {
        $note = 'Saved. ' . $sp['name'] . ' can open the press kit '
            . (empty($sp['until']) ? 'with no end date' : 'until ' . day($sp['until']))
            . ', ' . hidden_text(press_hidden_of($sp)) . '.';
    }
}

$kitUrl = (press_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'sanderkleinenberg.com') . press_path();
if ($fresh && ($fresh['kind'] ?? '') === 'admin') {
    $fresh['message'] = 'Here’s your own admin access to the Sander Kleinenberg press kit. '
        . "That’s where promoters get their password to the kit:\n\n"
        . $kitUrl . "admin.php\nPassword: " . $fresh['password'] . "\n\n"
        . 'It opens the press kit itself too. The password is just for you.';
} elseif ($fresh) {
    $fresh['message'] = 'Here’s the press kit for Sander Kleinenberg: ' . what_they_get($fresh['hide']) . ".\n\n"
        . $kitUrl . "\nPassword: " . $fresh['password'] . "\n\n"
        . ($fresh['until'] ? 'It works until ' . date('j F Y', $fresh['until']) . '. ' : '')
        . 'The password is just for you.';
}

$today   = date('Y-m-d');
$default = date('Y-m-d', strtotime('+3 months'));

function act(string $do, array $p, string $label, string $confirm, string $token, string $class = 'ghost'): string {
    return '<form method="post" data-confirm="' . h($confirm) . '">'
        . '<input type="hidden" name="token" value="' . h($token) . '">'
        . '<input type="hidden" name="do" value="' . h($do) . '">'
        . '<input type="hidden" name="id" value="' . h($p['id']) . '">'
        . '<button type="submit" class="' . $class . '">' . h($label) . '</button></form>';
}

/* The end date and section switches, for adding a promoter and in each one's settings. */
function access_fields(string $prefix, ?int $until, array $hide, string $today, string $default): string {
    $date    = $until ? date('Y-m-d', $until) : $default;
    $forever = $until === null;
    $switches = '';
    foreach (PRESS_SECTIONS as $key => $label) {
        $switches .= '<label class="switch"><span>' . $label . '</span><input type="checkbox" role="switch" name="show[]" value="' . $key . '"'
            . (in_array($key, $hide, true) ? '' : ' checked') . '></label>';
    }
    $quick   = '';
    foreach (QUICK as $days => $label) {
        $quick .= '<button type="button" class="ghost" data-days="' . $days . '">' . $label . '</button>';
    }
    return '<label for="' . $prefix . '-until">Works until</label>'
        . '<div class="when">'
        . '<input id="' . $prefix . '-until" name="until" type="date" min="' . $today . '" value="' . h($date) . '"'
        . ($forever ? ' disabled' : '') . ' required>'
        . '<div class="quick" aria-label="Quick choices">' . $quick . '</div>'
        . '</div>'
        . '<label class="switch wide"><span>No end date</span><input type="checkbox" role="switch" name="forever" value="1"' . ($forever ? ' checked' : '') . '></label>'
        . '<fieldset class="sections"><legend>Sections they see</legend><div class="switches">' . $switches . '</div></fieldset>';
}

function settings(array $p, string $token, string $today, string $default, bool $ended): string {
    return '<details class="settings"><summary>' . ($ended ? 'Let them back in' : 'Settings') . '</summary>'
        . '<form method="post">'
        . '<input type="hidden" name="token" value="' . h($token) . '">'
        . '<input type="hidden" name="do" value="save">'
        . '<input type="hidden" name="id" value="' . h($p['id']) . '">'
        . access_fields($p['id'], $ended ? end_of_day($default) : ($p['until'] ? (int) $p['until'] : null), $p['hide'], $today, $default)
        . '<button type="submit">Save</button>'
        . '</form></details>';
}

/* "added 5 Nov 2026 by Tim" */
function added_by(array $p, ?array $list): string {
    return 'added ' . day($p['added'] ?? 0) . ' by ' . press_name_of($list, $p['by'] ?? null, $p['by_name'] ?? null);
}

function signed_in(array $a): string {
    if (empty($a['last'])) return 'Not signed in yet';
    return 'Last signed in ' . day($a['last']) . ' · ' . count_of((int) ($a['signins'] ?? 0), 'sign-in', 'sign-ins');
}

function used(array $p): string {
    if (empty($p['last'])) return 'Not used yet';
    return 'Last used ' . day($p['last']) . ' · ' . count_of((int) ($p['signins'] ?? 0), 'sign-in', 'sign-ins')
        . ' · ' . count_of((int) ($p['files'] ?? 0), 'file', 'files');
}

$css = <<<'CSS'
body.admin{place-items:start center}
main.wide{width:min(580px,100%)}
.top{display:flex; align-items:flex-end; justify-content:space-between; gap:16px}
.top .logo{width:min(190px,52%)}
.kit{font-family:var(--mono); font-size:11px; letter-spacing:.18em; text-transform:uppercase; color:var(--ink); text-decoration:none; border-bottom:1px solid currentColor; padding-bottom:2px; white-space:nowrap}
.kit:hover{color:var(--accent)}
.links{display:flex; flex-wrap:wrap; justify-content:flex-end; gap:8px 18px}
.meta a.hist{color:var(--ink); text-underline-offset:2px}
.meta a.hist:hover{color:var(--accent)}
.note{margin:16px 0 0; padding:10px 12px; background:rgba(143,205,247,.3); color:var(--ink); font-size:14px; line-height:1.45}
.wrong{margin-top:16px}
form.add{margin-top:22px}
input[type=date]{-webkit-appearance:none; appearance:none; min-height:50px; text-align:left; background:#fff}
input[type=date]::-webkit-date-and-time-value{text-align:left}
input[type=date]:disabled{color:var(--muted); background:var(--wall); border-color:rgba(24,24,23,.25)}
.when{display:flex; flex-direction:column; gap:8px}
.quick{display:flex; flex-wrap:wrap; gap:6px}
.quick button{padding:7px 10px; font-size:11.5px; letter-spacing:.07em}
fieldset.sections{margin:6px 0 2px; padding:0; border:0; min-width:0}
fieldset.sections legend{padding:0; margin-bottom:8px; font-family:var(--mono); font-size:10px; letter-spacing:.2em; text-transform:uppercase; color:var(--ink)}
.switches{display:grid; grid-template-columns:repeat(auto-fill,minmax(128px,1fr)); gap:6px}
label.switch{display:flex; align-items:center; justify-content:space-between; gap:10px; padding:9px 10px 9px 12px; background:#fff; border:1px solid rgba(24,24,23,.18);
  font-family:var(--sans); font-size:15px; letter-spacing:0; text-transform:none; color:var(--ink); cursor:pointer; user-select:none; -webkit-user-select:none}
label.switch.wide{margin-top:2px}
label.switch input{-webkit-appearance:none; appearance:none; position:relative; flex:none; width:40px; height:24px; margin:0; padding:0;
  border:0; border-radius:12px; background:#d3cec6; cursor:pointer; transition:background .15s}
label.switch input::after{content:""; position:absolute; top:3px; left:3px; width:18px; height:18px; border-radius:50%; background:#fff;
  box-shadow:0 1px 2px rgba(0,0,0,.25); transition:transform .15s}
label.switch input:checked{background:var(--ink)}
label.switch input:checked::after{transform:translateX(16px)}
label.switch input:focus-visible{outline:2px solid var(--accent); outline-offset:2px}
.switches label.switch:has(input:not(:checked)) span{color:var(--muted)}
.fresh{margin-top:20px; padding:18px 18px 16px; background:#fff; border:1px dashed var(--ink)}
.fresh .kicker{margin-bottom:.5em; color:var(--ink)}
.pw{margin:0; font-family:var(--mono); font-weight:500; font-size:clamp(22px,7vw,30px); letter-spacing:.05em; color:var(--ink); user-select:all; -webkit-user-select:all}
.fresh .small{margin:8px 0 14px; font-size:13.5px; line-height:1.5; color:var(--body)}
.fresh textarea{display:block; width:100%; min-height:9.5em; padding:11px 12px; font-family:var(--mono); font-size:12.5px; line-height:1.55; color:var(--ink); background:var(--wall); border:0; border-radius:0; resize:vertical}
.row{display:flex; flex-wrap:wrap; gap:8px; margin-top:12px}
.row button{flex:1 1 auto; padding:11px 12px; font-size:13px}
.ghost{background:transparent; color:var(--ink)}
.ghost:hover,.ghost:focus-visible{color:var(--paper)}
.people{rotate:.25deg}
h2{display:flex; gap:10px; margin:0; font-family:var(--mono); font-weight:500; font-size:11px; letter-spacing:.2em; text-transform:uppercase; color:var(--ink)}
h2 span{color:var(--muted)}
h2.later{margin-top:28px}
ul{list-style:none; margin:10px 0 0; padding:0}
li{display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px 14px; padding:14px 0; border-top:1px solid rgba(24,24,23,.13)}
li .who{flex:1 1 220px; min-width:0}
li b{display:block; font-weight:700; font-size:16.5px; line-height:1.3; color:var(--ink); overflow-wrap:anywhere}
.meta{display:block; margin-top:3px; font-family:var(--mono); font-size:11.5px; line-height:1.55; color:var(--muted)}
.meta .no{color:var(--accent)}
.acts{display:flex; gap:8px}
.acts form{margin:0}
.acts button{padding:8px 11px; font-size:12px; letter-spacing:.07em}
li.off b{font-weight:600; color:var(--muted)}
details.settings{flex:1 1 100%; margin-top:-2px}
details.settings summary{display:inline-block; cursor:pointer; font-family:var(--mono); font-size:11px; letter-spacing:.16em; text-transform:uppercase; color:var(--ink); text-decoration:underline; text-underline-offset:3px; list-style:none}
details.settings summary::-webkit-details-marker{display:none}
details.settings summary::after{content:" +"}
details.settings[open] summary::after{content:" \2212"}
details.settings summary:hover{color:var(--accent)}
details.settings form{margin-top:12px; padding:14px; background:var(--wall)}
.empty{margin:12px 0 0; font-size:14px; line-height:1.5; color:var(--muted)}
.out{margin:0; align-self:center; display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center; font-family:var(--mono); font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted)}
.team{rotate:-.2deg}
.team .lede.small{margin:8px 0 0; font-size:13.5px; color:var(--muted)}
.team form.add{margin-top:18px}
details.settings form label{font-family:var(--mono); font-size:10px; letter-spacing:.2em; text-transform:uppercase; color:var(--ink)}
.out button{padding:4px; background:none; border:0; color:var(--muted); font-family:var(--mono); font-weight:400; font-size:11px; letter-spacing:.16em; text-decoration:underline; text-underline-offset:3px}
.out button:hover{background:none; color:var(--accent)}
CSS;

press_page_top('Press kit access', $css, 'admin');
?>
<main class="wide">
  <header class="top">
    <img class="logo" src="img/logo-black.svg" alt="Sander Kleinenberg" width="1040" height="546">
    <nav class="links"><a class="kit" href="history.php">History &rarr;</a><a class="kit" href="<?= h(press_path()) ?>">Press kit &rarr;</a></nav>
  </header>

  <div class="slip">
    <p class="kicker">Press kit &middot; access</p>
    <h1>Who gets in</h1>
    <p class="lede">Every promoter gets their own password. Revoke it, and they&rsquo;re out at once, even when signed in.</p>
<?php if ($list === null): ?>
    <p class="wrong" role="alert">promoters.php on the server is damaged, so no promoter password works right now and nothing is saved over it. Fix or delete that file.</p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="wrong" role="alert"><?= h($error) ?></p>
<?php endif; ?>
<?php if ($note): ?>
    <p class="note" role="status"><?= h($note) ?></p>
<?php endif; ?>
<?php if ($fresh): ?>
    <div class="fresh" id="fresh">
<?php if (($fresh['kind'] ?? '') === 'admin'): ?>
      <p class="kicker">Admin password for <?= h($fresh['name']) ?></p>
      <p class="pw" id="pw"><?= h($fresh['password']) ?></p>
      <p class="small">Opens this page and the press kit. It&rsquo;s shown only now. Lost it? Make a new one under Admins.</p>
<?php else: ?>
      <p class="kicker">Password for <?= h($fresh['name']) ?></p>
      <p class="pw" id="pw"><?= h($fresh['password']) ?></p>
      <p class="small"><?= $fresh['until'] ? 'Works until ' . h(day($fresh['until'])) : 'No end date' ?>,
        <?= h(hidden_text($fresh['hide'])) ?>.
        It&rsquo;s shown only now. Lost it? Make a new one with &ldquo;New password&rdquo;.</p>
<?php endif; ?>
      <textarea id="msg" readonly aria-label="Message to send"><?= h($fresh['message']) ?></textarea>
      <div class="row">
        <button type="button" data-copy="msg">Copy message</button>
        <button type="button" class="ghost" data-copy="pw">Copy password</button>
        <button type="button" class="ghost" data-share hidden>Share&hellip;</button>
      </div>
    </div>
<?php endif; ?>
<?php
    $again = $error && ($_POST['do'] ?? '') === 'add';   /* refill the form after a mistake */
    $addUntil = $again ? (empty($_POST['forever']) ? end_of_day((string) ($_POST['until'] ?? '')) : null) : end_of_day($default);
    $addHide  = $again ? posted_hidden() : [];
?>
    <form class="add" method="post" action="<?= h($self) ?>">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <input type="hidden" name="do" value="add">
      <label for="name">Promoter or event</label>
      <input id="name" name="name" maxlength="80" placeholder="Paradiso, Amsterdam" autocomplete="off" required<?= $fresh ? '' : ' autofocus' ?><?= $again ? ' value="' . h($_POST['name'] ?? '') . '"' : '' ?>>
      <?= access_fields('add', $addUntil ?: ($again && !empty($_POST['forever']) ? null : end_of_day($default)), $addHide, $today, $default) ?>
      <button type="submit">Make a password</button>
    </form>
  </div>

  <section class="slip people" aria-label="Promoters">
    <h2>Has access <span><?= count($on) ?></span></h2>
<?php if (!$on): ?>
    <p class="empty">Nobody yet. Make the first password above.</p>
<?php else: ?>
    <ul>
<?php foreach ($on as $p): ?>
      <li>
        <div class="who">
          <b><?= h($p['name']) ?></b>
          <span class="meta"><?= empty($p['until']) ? 'No end date' : 'Until ' . h(day($p['until'])) ?> &middot; <?= $p['hide'] ? '<span class="no">' . h(hidden_text($p['hide'])) . '</span>' : 'all sections' ?> &middot; <?= h(added_by($p, $list)) ?></span>
          <span class="meta"><?= h(used($p)) ?> &middot; <a class="hist" href="history.php?who=<?= h($p['id']) ?>">history</a></span>
        </div>
        <div class="acts">
          <?= act('renew', $p, 'New password', 'New password for ' . $p['name'] . '? The old one stops working right away.', $token) ?>
          <?= act('revoke', $p, 'Revoke', 'Revoke ' . $p['name'] . '? Their password stops working right away, and they are signed out.', $token, '') ?>
        </div>
        <?= settings($p, $token, $today, $default, false) ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($off): ?>
    <h2 class="later">No access anymore <span><?= count($off) ?></span></h2>
    <ul>
<?php foreach ($off as $p): ?>
      <li class="off">
        <div class="who">
          <b><?= h($p['name']) ?></b>
          <span class="meta"><?= !empty($p['revoked'])
              ? 'Revoked ' . h(day($p['revoked'])) . (isset($p['revoked_by']) ? ' by ' . h(press_name_of($list, $p['revoked_by'], $p['revoked_by_name'] ?? null)) : '')
              : 'Ended ' . h(day($p['until'])) ?> &middot; <?= h(added_by($p, $list)) ?></span>
          <span class="meta"><?= h(used($p)) ?> &middot; <a class="hist" href="history.php?who=<?= h($p['id']) ?>">history</a></span>
        </div>
        <div class="acts">
          <?= act('delete', $p, 'Delete', 'Delete ' . $p['name'] . ' from the list?', $token) ?>
        </div>
<?php if (empty($p['revoked'])): ?>
        <?= settings($p, $token, $today, $default, true) ?>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>
<?php if ($owner): ?>

  <section class="slip team" aria-label="Admins">
    <h2>Admins <span><?= count($admins) ?></span></h2>
    <p class="lede small">Admins sign in here with their own password and look after promoters. Only you add or remove admins.</p>
    <ul>
      <li>
        <div class="who">
          <b><?= h($myName) ?></b>
          <span class="meta">Owner &middot; that&rsquo;s you<?= trim((string) ($list['owner'] ?? '')) === '' ? ' &middot; <span class="no">set your name, so the list shows who added whom</span>' : '' ?> &middot; <a class="hist" href="history.php?who=me">history</a></span>
        </div>
        <details class="settings"><summary>Your name</summary>
          <form method="post">
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <input type="hidden" name="do" value="owner_name">
            <label for="owner-name">Your name on the list</label>
            <input id="owner-name" name="name" maxlength="80" autocomplete="off" required value="<?= h(trim((string) ($list['owner'] ?? ''))) ?>">
            <button type="submit">Save</button>
          </form>
        </details>
      </li>
<?php foreach ($admins as $a): ?>
      <li>
        <div class="who">
          <b><?= h($a['name']) ?></b>
          <span class="meta">Admin &middot; added <?= h(day($a['added'] ?? 0)) ?> &middot; <?= h(count_of($a['count'], 'promoter', 'promoters')) ?> on the list</span>
          <span class="meta"><?= h(signed_in($a)) ?> &middot; <a class="hist" href="history.php?who=<?= h($a['id']) ?>">history</a></span>
        </div>
        <div class="acts">
          <?= act('admin_renew', $a, 'New password', 'New password for ' . $a['name'] . '? The old one stops working right away.', $token) ?>
          <?= act('admin_remove', $a, 'Remove', 'Remove ' . $a['name'] . ' as admin? They are signed out right away. The promoters they added keep their access.', $token, '') ?>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
    <form class="add" method="post" action="<?= h($self) ?>">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <input type="hidden" name="do" value="admin_add">
      <label for="admin-name">New admin</label>
      <input id="admin-name" name="name" maxlength="80" placeholder="Name, e.g. the booking agent" autocomplete="off" required>
      <button type="submit">Make an admin password</button>
    </form>
  </section>
<?php endif; ?>

  <form class="out" method="post" action="<?= h($self) ?>">
    <input type="hidden" name="token" value="<?= h($token) ?>">
    <input type="hidden" name="do" value="signout">
    <span>Signed in as <?= h($myName) ?><?= $owner ? ' (owner)' : '' ?></span>
    <button type="submit">Sign out</button>
  </form>
</main>
<script>
document.addEventListener('submit', function (e) {
  var ask = e.target.getAttribute('data-confirm');
  if (ask && !confirm(ask)) e.preventDefault();
});
/* the end date: the quick buttons fill it in, "No end date" switches it off */
function ymd(d) {
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
document.querySelectorAll('form').forEach(function (f) {
  var date = f.querySelector('input[name=until]'), never = f.querySelector('input[name=forever]');
  if (!date || !never) return;
  never.addEventListener('change', function () { date.disabled = never.checked; });
  f.querySelectorAll('[data-days]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = new Date();
      d.setDate(d.getDate() + Number(b.getAttribute('data-days')));
      date.value = ymd(d);
      never.checked = false;
      date.disabled = false;
    });
  });
});
function textOf(id) {
  var el = document.getElementById(id);
  return el.value !== undefined ? el.value : el.textContent;
}
function copy(text) {
  if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
  var t = document.createElement('textarea');
  t.value = text; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
  document.body.appendChild(t); t.select();
  try { document.execCommand('copy'); } finally { t.remove(); }
  return Promise.resolve();
}
document.querySelectorAll('[data-copy]').forEach(function (b) {
  var label = b.textContent;
  b.addEventListener('click', function () {
    copy(textOf(b.getAttribute('data-copy'))).then(function () {
      b.textContent = 'Copied';
      setTimeout(function () { b.textContent = label; }, 1600);
    });
  });
});
var msg = document.getElementById('msg');
function fit() { msg.style.height = 'auto'; msg.style.height = (msg.scrollHeight + 2) + 'px'; }
if (msg) {
  fit();
  if (document.fonts) document.fonts.ready.then(fit);
  addEventListener('resize', fit);
}
var share = document.querySelector('[data-share]');
if (share && navigator.share) {
  share.hidden = false;
  share.addEventListener('click', function () {
    navigator.share({ text: textOf('msg') }).catch(function () {});
  });
}
<?php if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'): ?>
/* this page answered a post: make a refresh load it plainly instead of
   posting again (and making yet another password) */
if (history.replaceState) history.replaceState(null, '', location.pathname);
<?php endif; ?>
</script>
</body>
</html>
