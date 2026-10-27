<?php
/* The download gate. A follow, a like, and then the file.
 *
 * What this does and does not do, stated plainly because the whole design
 * turns on it: clicking a step opens the real link and marks itself done. We
 * never learn whether the follow actually happened -- no platform tells you
 * that without OAuth, and SoundCloud has not handed out keys in years. So the
 * gate is a request made politely, not a lock. Someone who wants the file
 * without following will get it. That is true of every gate of this kind; the
 * difference is that this one does not pretend otherwise.
 *
 * The one thing it does enforce is that the file itself is not a guessable
 * URL: files/ is denied by .htaccess, and get.php only parts with the bytes
 * for a token this page signed a few minutes earlier.
 */
declare(strict_types=1);

$cfgFile = __DIR__ . '/config.php';
$cfg = is_readable($cfgFile) ? (array) (require $cfgFile) : [];
if (!$cfg) { $cfg = (array) (require __DIR__ . '/config.example.php'); $usingExample = true; }

$steps    = $cfg['steps'] ?? [];
$required = array_values(array_filter($steps, fn($s) => !empty($s['required'])));

/* The token endpoint. The page asks for one once the required steps are done.
 * It is a signed expiry, nothing more -- stateless, so no session to keep and
 * nothing to clean up. */
/* The pile on the homepage shows the same gate on the back of its card, and a
 * static .html cannot read this config. So it asks for it here: one source of
 * truth, in config.php, rather than the steps written out twice. */
if (isset($_GET['steps'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: same-origin');
    exit(json_encode([
        'title'    => $cfg['title'] ?? '',
        'subtitle' => $cfg['subtitle'] ?? '',
        'key'      => 'sk-gate-' . md5((string) ($cfg['file'] ?? 'x')),
        'ready'    => !empty($cfg['secret']) && !str_starts_with((string) $cfg['secret'], 'CHANGE-ME')
                      && is_file(__DIR__ . '/files/' . basename((string) ($cfg['file'] ?? ''))),
        'steps'    => array_map(fn($x) => [
            'id' => $x['id'], 'label' => $x['label'], 'url' => $x['url'],
            'required' => !empty($x['required']),
        ], $steps),
    ]));
}

if (isset($_GET['issue'])) {
    header('Content-Type: application/json');
    if (empty($cfg['secret']) || str_starts_with((string) $cfg['secret'], 'CHANGE-ME')) {
        http_response_code(503);
        exit(json_encode(['error' => 'not configured']));
    }
    $exp  = time() + (int) ($cfg['ttl'] ?? 900);
    $sig  = hash_hmac('sha256', (string) $exp, (string) $cfg['secret']);
    exit(json_encode(['token' => $exp . '.' . $sig, 'ttl' => (int) ($cfg['ttl'] ?? 900)]));
}

$title = $cfg['title'] ?? 'Free download';
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en" data-ground="tide">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= $h($title) ?> — Sander Kleinenberg</title>
<meta name="description" content="<?= $h($title) ?> — a free download from Sander Kleinenberg.">
<meta name="robots" content="index,follow">
<link rel="icon" href="../p/sk-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="../p/sk-180.png">
<meta property="og:type" content="music.song">
<meta property="og:title" content="<?= $h($title) ?> — free download">
<meta property="og:description" content="Follow, like, and the track is yours.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75..112,400..900&family=DM+Mono:wght@400;500&display=swap">
<style>
/* Same paper on the same wall as the pile next door, so the gate reads as part
   of the site rather than a form bolted to the side of it. */
:root{
  --wall:#8fcdf7; --paper:#faf9f5; --ink:#141413; --accent:#fb1a74;
  --sans:"Archivo",system-ui,-apple-system,"Helvetica Neue",Arial,sans-serif;
  --mono:"DM Mono",ui-monospace,"SF Mono",Menlo,monospace;
  color-scheme:light;
}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;min-height:100svh;background:var(--wall);color:var(--ink);
  font-family:var(--sans);display:flex;align-items:center;justify-content:center;
  padding:clamp(16px,5vw,48px) 16px}
.card{width:min(100%,460px);background:var(--paper);border-radius:3px;
  padding:clamp(22px,6vw,38px);
  box-shadow:0 1px 2px rgba(0,0,0,.09),0 14px 34px rgba(0,0,0,.17)}
/* The giveaway's own label. Not the record's sleeve: that artwork belongs to
   whoever released the record, and a remix credit laid over it would read as a
   pressing that never existed. This is set type on a card, which is honest
   about being a free download and nothing else. */
.art{aspect-ratio:1;display:flex;flex-direction:column;justify-content:center;
  gap:.5em;padding:9%;margin:0 0 clamp(18px,4vw,26px);
  background:var(--ink);color:var(--paper);container-type:inline-size}
.art span{font-family:var(--mono);font-size:3.6cqw;letter-spacing:.24em;
  text-transform:uppercase;opacity:.55}
/* Type is the only thing on this card, so it is allowed to be the size of the
   card. A sleeve with a hole in the middle just looks like a missing image. */
.art strong{font-size:22cqw;font-weight:800;font-stretch:80%;line-height:.86;
  letter-spacing:-.03em;text-wrap:balance}
.art em{font-style:normal;font-family:var(--mono);font-size:3.8cqw;
  letter-spacing:.13em;text-transform:uppercase;color:var(--accent);
  border-top:1px solid rgba(250,249,245,.22);padding-top:.9em;margin-top:.3em}
.kick{font-family:var(--mono);font-size:11px;letter-spacing:.18em;
  text-transform:uppercase;color:#8d867c;margin:0 0 .5em}
h1{font-size:clamp(26px,7vw,38px);font-weight:800;font-stretch:88%;
  letter-spacing:-.02em;line-height:1.04;margin:0 0 .15em}
.by{font-family:var(--mono);font-size:12px;letter-spacing:.1em;color:#8d867c;margin:0}
.rule{height:1px;background:rgba(24,24,23,.14);margin:clamp(18px,4vw,26px) 0}
.ask{font-size:14px;line-height:1.5;margin:0 0 1.1em}
ol{list-style:none;margin:0;padding:0;display:grid;gap:9px}
/* A step is a link, not a button: it really does go somewhere, so it should
   middle-click and long-press like any other link. The tick is the only thing
   that changes when it comes back. */
.step{display:flex;align-items:center;gap:12px;width:100%;text-align:left;
  font:inherit;font-size:14px;font-weight:600;color:var(--ink);text-decoration:none;
  background:#fff;border:1px solid rgba(24,24,23,.18);border-radius:2px;
  padding:13px 15px;cursor:pointer;transition:border-color .18s,transform .18s,box-shadow .18s}
.step:hover,.step:focus-visible{border-color:var(--ink);transform:translateY(-1px);
  box-shadow:0 3px 10px rgba(0,0,0,.09)}
.step:focus-visible{outline:2px solid var(--accent);outline-offset:3px}
.tick{flex:0 0 auto;width:19px;height:19px;border-radius:50%;
  border:1.5px solid rgba(24,24,23,.3);display:grid;place-items:center;
  font-size:11px;color:transparent;transition:background .18s,border-color .18s,color .18s}
.step[data-done="1"] .tick{background:var(--accent);border-color:var(--accent);color:#fff}
.step[data-done="1"]{color:#8d867c}
.opt{margin-left:auto;font-family:var(--mono);font-size:10px;letter-spacing:.12em;
  text-transform:uppercase;color:#b4ada1}
.get{display:block;width:100%;margin-top:clamp(18px,4vw,26px);padding:16px;
  font:inherit;font-size:15px;font-weight:800;font-stretch:88%;letter-spacing:.02em;
  text-transform:uppercase;text-align:center;text-decoration:none;
  color:#fff;background:var(--ink);border:0;border-radius:2px;cursor:pointer;
  transition:background .18s,opacity .18s}
.get[disabled]{opacity:.26;cursor:not-allowed}
.get:not([disabled]):hover{background:var(--accent)}
.note{font-family:var(--mono);font-size:10.5px;line-height:1.6;letter-spacing:.04em;
  color:#b4ada1;margin:1.3em 0 0;text-align:center}
.warn{margin:0 0 1em;padding:11px 13px;border-radius:2px;font-size:12.5px;line-height:1.5;
  background:#fff4d6;border:1px solid #e8cf86;color:#6b5312}
a.back{color:#8d867c}
/* The label is aria-hidden, being decorative type, so the page still needs one
   real heading for anything that reads it rather than looks at it. */
.sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;
  clip-path:inset(50%);white-space:nowrap;border:0}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>
<main class="card">
<?php if (!empty($usingExample)): ?>
  <p class="warn"><strong>Running on the example config.</strong>
     Copy <code>config.example.php</code> to <code>config.php</code>, set a real
     <code>secret</code>, and drop the audio into <code>go/files/</code>.
     Downloads stay switched off until then.</p>
<?php endif; ?>
  <div class="art" aria-hidden="true">
    <span>Free download</span>
    <strong><?= $h($title) ?></strong>
    <em>Sander Kleinenberg 1999 Remix</em>
  </div>
  <h1 class="sr"><?= $h($title) ?> &mdash; Sander Kleinenberg</h1>
  <div class="rule"></div>
  <p class="ask">Give one of these a tap and the track is yours.</p>
  <ol id="steps">
<?php foreach ($steps as $s): ?>
    <li><a class="step" href="<?= $h($s['url']) ?>" target="_blank" rel="noopener noreferrer"
           data-id="<?= $h($s['id']) ?>" data-req="<?= !empty($s['required']) ? 1 : 0 ?>" data-done="0">
      <span class="tick" aria-hidden="true">&#10003;</span>
      <span><?= $h($s['label']) ?></span>
      <?php if (empty($s['required'])): ?><span class="opt">optional</span><?php endif; ?>
    </a></li>
<?php endforeach; ?>
  </ol>
  <button class="get" id="get" disabled>Download</button>
  <p class="note">
    <span id="left"><?= count($required) ?></span> to go<br>
    <a class="back" href="../">Back to the archive</a>
  </p>
</main>
<script>
/* Progress is remembered per browser so a refresh, or coming back from the
   SoundCloud tab, does not start over. It is deliberately not proof of
   anything -- see the note at the top of this file. */
(() => {
  const KEY = 'sk-gate-<?= $h(md5((string) ($cfg['file'] ?? 'x'))) ?>';
  const steps = [...document.querySelectorAll('.step')];
  const need  = steps.filter(s => s.dataset.req === '1');
  const get   = document.getElementById('get');
  const left  = document.getElementById('left');
  let done;
  try { done = new Set(JSON.parse(localStorage.getItem(KEY) || '[]')); }
  catch { done = new Set(); }

  const save = () => { try { localStorage.setItem(KEY, JSON.stringify([...done])); } catch {} };

  async function arm() {
    get.disabled = false;
    get.textContent = 'Download';
    if (get.dataset.armed) return;
    get.dataset.armed = '1';
    try {
      const r = await fetch('?issue=1', { method: 'POST' });
      if (!r.ok) throw new Error('not configured');
      const { token } = await r.json();
      get.dataset.token = token;
    } catch {
      get.disabled = true;
      get.textContent = 'Download not set up yet';
    }
  }

  function paint() {
    steps.forEach(s => s.dataset.done = done.has(s.dataset.id) ? '1' : '0');
    const missing = need.filter(s => !done.has(s.dataset.id)).length;
    left.textContent = missing;
    left.closest('.note').firstChild.textContent = missing ? '' : '';
    if (!missing) arm();
  }

  steps.forEach(s => s.addEventListener('click', () => {
    /* The link opens on its own; all we do is remember it was taken. The beat
       before it counts is there so the new tab has actually surfaced. */
    setTimeout(() => { done.add(s.dataset.id); save(); paint(); }, 700);
  }));

  get.addEventListener('click', () => {
    if (get.disabled || !get.dataset.token) return;
    location.href = 'get.php?t=' + encodeURIComponent(get.dataset.token);
  });

  paint();
})();
</script>
</body>
</html>
