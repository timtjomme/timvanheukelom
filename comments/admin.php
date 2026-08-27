<?php
/**
 * Moderation for the comments. Nothing a visitor writes appears on the site
 * until it is approved here.
 *
 * Same first-run flow as analytics/dashboard.php: config.php is gitignored, so
 * a freshly deployed copy has no password and the first visit sets one.
 */
declare(strict_types=1);
session_start();

$configFile = __DIR__ . '/config.php';
$hasConfig  = is_readable($configFile);
$config     = $hasConfig ? require $configFile : ['admin_password' => null];

$error = $setupError = $notice = null;

if (!$hasConfig && isset($_POST['new_password'])) {
    $new = (string) $_POST['new_password'];
    if (strlen($new) < 8) {
        $setupError = 'Use at least 8 characters.';
    } else {
        $php = "<?php\n// Written by admin.php on first setup. Gitignored — never touches the repo.\nreturn [\n    'admin_password' => " . var_export($new, true) . ",\n];\n";
        if (file_put_contents($configFile, $php, LOCK_EX) !== false) {
            $hasConfig = true;
            $_SESSION['tvh_comments_authed'] = true;
        } else {
            $setupError = "Couldn't write config.php — check the comments/ folder is writable.";
        }
    }
}
if ($hasConfig && isset($_POST['password'])) {
    if ($config['admin_password'] !== null
        && hash_equals((string) $config['admin_password'], (string) $_POST['password'])) {
        $_SESSION['tvh_comments_authed'] = true;
    } else {
        $error = 'Wrong password.';
    }
}
if (isset($_GET['logout'])) unset($_SESSION['tvh_comments_authed']);
$authed = $_SESSION['tvh_comments_authed'] ?? false;

function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$dir = __DIR__ . '/data';

/** Rewrite one post's file with the given comments. */
function save_post(string $dir, string $slug, array $rows): void {
    $lines = array_map(fn($r) => json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $rows);
    file_put_contents($dir . '/' . $slug . '.jsonl', implode("\n", $lines) . ($lines ? "\n" : ''), LOCK_EX);
}

function load_post(string $dir, string $slug): array {
    $f = $dir . '/' . $slug . '.jsonl';
    if (!is_readable($f)) return [];
    $rows = [];
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $e = json_decode($line, true);
        if (is_array($e)) $rows[] = $e;
    }
    return $rows;
}

// ---- actions ------------------------------------------------------------
if ($authed && ($_POST['action'] ?? '') !== '') {
    $slug = preg_replace('/[^a-z0-9._-]/i', '', (string) ($_POST['post'] ?? ''));
    $id   = preg_replace('/[^a-z0-9]/i', '', (string) ($_POST['id'] ?? ''));
    if ($slug !== '' && $id !== '') {
        $rows = load_post($dir, $slug);
        $changed = false;
        foreach ($rows as $i => $r) {
            if (($r['id'] ?? '') !== $id) continue;
            if ($_POST['action'] === 'approve') { $rows[$i]['approved'] = true;  $changed = true; $notice = 'Goedgekeurd.'; }
            if ($_POST['action'] === 'hide')    { $rows[$i]['approved'] = false; $changed = true; $notice = 'Verborgen.'; }
            if ($_POST['action'] === 'delete')  { unset($rows[$i]);              $changed = true; $notice = 'Verwijderd.'; }
            break;
        }
        if ($changed) save_post($dir, $slug, array_values($rows));
    }
}

// ---- gather -------------------------------------------------------------
$all = [];
if ($authed && is_dir($dir)) {
    foreach (glob($dir . '/*.jsonl') as $f) {
        $slug = basename($f, '.jsonl');
        foreach (load_post($dir, $slug) as $r) { $r['post'] = $slug; $all[] = $r; }
    }
    usort($all, fn($a, $b) => strcmp((string) ($b['t'] ?? ''), (string) ($a['t'] ?? '')));
}
$pending  = array_values(array_filter($all, fn($r) => empty($r['approved'])));
$approved = array_values(array_filter($all, fn($r) => !empty($r['approved'])));
?>
<!DOCTYPE html>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Reacties — moderatie</title>
<link rel="icon" href="../favicon.ico">
<style>
  *{box-sizing:border-box}
  body{margin:0;font:15px/1.6 "Open Sans",-apple-system,BlinkMacSystemFont,'Helvetica Neue',Arial,sans-serif;
       color:#2f2f2f;background:#f4f4f6}
  .wrap{max-width:820px;margin:0 auto;padding:40px 20px 80px}
  h1{font-weight:300;font-size:30px;margin:0}
  h2{font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#8a8a8a;margin:34px 0 12px}
  .top{display:flex;justify-content:space-between;align-items:baseline}
  .logout{font-size:13px;color:#8a8a8a;text-decoration:none}
  .login{max-width:320px;margin:120px auto;text-align:center}
  .login input{width:100%;padding:11px 13px;border:1px solid rgba(0,0,0,.16);border-radius:12px;font-size:15px;margin-top:16px}
  .login button{width:100%;margin-top:12px;padding:12px;border:0;border-radius:12px;background:#2f2f2f;color:#fff;
       font:600 13px/1 Arial,sans-serif;letter-spacing:.05em;text-transform:uppercase;cursor:pointer}
  .err{color:#d5443f;font-size:13px;margin-top:10px}
  .notice{background:#eaf4ec;border:1px solid #cfe6d5;color:#2f7d4f;padding:9px 14px;border-radius:10px;
       font-size:13.5px;margin-top:16px}
  .c{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:16px 18px;margin-bottom:12px}
  .c.pending{border-left:3px solid #e0a01c}
  .c .meta{font-size:13px;color:#8a8a8a;margin-bottom:6px}
  .c .meta b{color:#2f2f2f;font-size:14.5px;font-weight:600}
  .c .body{white-space:pre-wrap;overflow-wrap:anywhere}
  .acts{display:flex;gap:8px;margin-top:12px}
  .acts button{padding:6px 14px;border-radius:999px;border:1px solid rgba(0,0,0,.14);background:#fff;
       font:inherit;font-size:12.5px;font-weight:600;cursor:pointer;color:#5a5a5a}
  .acts .ok{background:#2f7d4f;border-color:#2f7d4f;color:#fff}
  .acts .del{color:#c2402f}
  .empty{color:#9a9aa4;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:20px}
  .post{font-size:12px;color:#a0a0a8}
</style>

<?php if (!$authed): ?>
<div class="login">
  <h1 style="font-size:22px">Reacties</h1>
  <?php if (!$hasConfig): ?>
    <p style="color:#8a8a8a;font-size:13px;margin:0">First time here — set a password.</p>
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
    <h1>Reacties</h1>
    <a class="logout" href="?logout=1">Sign out</a>
  </div>
  <?php if ($notice): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>

  <h2><?= count($pending) ?> wachten op goedkeuring</h2>
  <?php if (!$pending): ?><div class="empty">Niets in de wachtrij.</div><?php endif; ?>
  <?php foreach ($pending as $c): ?>
    <div class="c pending">
      <div class="meta"><b><?= h($c['name'] ?? '') ?></b> ·
        <?= h(date('j M Y, H:i', strtotime((string) $c['t']))) ?> ·
        <span class="post"><?= h($c['post']) ?></span></div>
      <div class="body"><?= h($c['body'] ?? '') ?></div>
      <form method="post" class="acts">
        <input type="hidden" name="post" value="<?= h($c['post']) ?>">
        <input type="hidden" name="id" value="<?= h($c['id'] ?? '') ?>">
        <button class="ok"  name="action" value="approve">Goedkeuren</button>
        <button class="del" name="action" value="delete"
                onclick="return confirm('Deze reactie definitief verwijderen?')">Verwijderen</button>
      </form>
    </div>
  <?php endforeach; ?>

  <h2><?= count($approved) ?> zichtbaar op de site</h2>
  <?php if (!$approved): ?><div class="empty">Nog geen goedgekeurde reacties.</div><?php endif; ?>
  <?php foreach ($approved as $c): ?>
    <div class="c">
      <div class="meta"><b><?= h($c['name'] ?? '') ?></b> ·
        <?= h(date('j M Y, H:i', strtotime((string) $c['t']))) ?> ·
        <span class="post"><?= h($c['post']) ?></span></div>
      <div class="body"><?= h($c['body'] ?? '') ?></div>
      <form method="post" class="acts">
        <input type="hidden" name="post" value="<?= h($c['post']) ?>">
        <input type="hidden" name="id" value="<?= h($c['id'] ?? '') ?>">
        <button name="action" value="hide">Verbergen</button>
        <button class="del" name="action" value="delete"
                onclick="return confirm('Deze reactie definitief verwijderen?')">Verwijderen</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
