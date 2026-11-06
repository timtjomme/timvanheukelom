<?php
/* The press kit page. Nothing below is sent without a password: gate.php
   checks it (yours or a promoter's), and shows the password page instead. */
require __DIR__ . '/gate.php';
$who = press_who();
if ($who === null) {
    press_login_page(press_try_password(press_path()));
    exit;
}
press_note($who, false);
$see = press_sections($who);   /* sections switched on for this sign-in (admin.php) */
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<style>:root{color-scheme:light;padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}body{margin:0;font:14px/1.4 system-ui,-apple-system,sans-serif}img{max-width:100%}[hidden]{display:none!important}</style>
<title>Sander Kleinenberg Press Kit</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75..112,400..900&family=DM+Mono:wght@400;500&family=Permanent+Marker&display=swap">

<style>
/* Layout: a press folder on the brand's paper wall. Logo masthead and the one
   photo at the decks, then a divider per section, each set as paper slips: two
   or three columns on a desk, one on a phone. Tokens are the live /5k/ values,
   so [data-ground] themes this page with the same switch as the archive and /bio/.
   Single theme on purpose: the brand is ink on cream paper. */
:root{
  color-scheme: light;

  --wall:#f1f1ec;
  --surface:url("img/surface.webp");
  --paper:#fdfcfa;
  --ink:#181817;
  --body:#2c2422;
  --muted:#8d867c;
  --faint:#b4ada1;
  --accent:#de6d96;
  --line:rgba(24,24,23,.15);
  --groove:rgba(24,24,23,.07);
  --shadow:72,64,52;

  --sans:"Archivo",system-ui,-apple-system,"Helvetica Neue",Arial,sans-serif;
  --mono:"DM Mono",ui-monospace,"SF Mono",Menlo,monospace;
  --marker:"Permanent Marker",ui-rounded,cursive;
}

[data-ground="tide"]  { --wall:#8fcdf7; --surface:none; --accent:#fb1a74 }
[data-ground="olivia"]{ --wall:#e49fb2; --surface:none }
[data-ground="bibi"]  { --wall:#40e0d0; --surface:none }

*,*::before,*::after{ box-sizing:border-box }

html{ scroll-padding-top:calc(env(safe-area-inset-top,0px) + 72px) }

body{
  background:var(--wall);
  background-image:var(--surface);
  background-size:520px auto;
  color:var(--body);
  font-family:var(--sans);
  font-size:15.5px;
  line-height:1.6;
  -webkit-font-smoothing:antialiased;
}
/* paints the band above the sticky index, under a phone's status bar */
body::before{
  content:""; position:fixed; inset:0 0 auto;
  height:env(safe-area-inset-top,0px);
  z-index:10; background:var(--wall);
}

.wrap{
  max-width:1180px;
  margin:0 auto;
  padding-left:max(clamp(16px,4vw,40px),env(safe-area-inset-left,0px));
  padding-right:max(clamp(16px,4vw,40px),env(safe-area-inset-right,0px));
}

/* ---- shared idiom ---------------------------------------------------------- */
.kicker{
  margin:0 0 .8em;
  font-family:var(--mono);
  font-weight:400;
  font-size:10px;
  letter-spacing:.24em;
  text-transform:uppercase;
  color:var(--muted);
}
.cond{
  font-weight:800;
  font-stretch:76%;
  letter-spacing:-.015em;
  line-height:.88;
  text-transform:uppercase;
}
.slip{
  background:var(--paper);
  box-shadow:0 1px 2px rgba(var(--shadow),.15), 0 14px 30px -14px rgba(var(--shadow),.3);
}
/* the poster's torn pink strip under a heading */
.strip{
  background-image:linear-gradient(var(--accent),var(--accent));
  background-repeat:no-repeat;
  background-size:100% .3em;
  background-position:0 84%;
  padding:0 .06em;
}

.btn{
  display:flex; flex-direction:column; justify-content:center; gap:5px;
  padding:14px 16px;
  border:1px solid var(--ink);
  background:var(--paper);
  color:var(--ink);
  text-decoration:none;
  font-weight:800; font-stretch:84%;
  font-size:15px; letter-spacing:.04em;
  text-transform:uppercase; line-height:1.1;
  transition:background .15s ease, color .15s ease, border-color .15s ease;
}
.btn span{
  font-family:var(--mono); font-weight:400;
  font-size:9.5px; letter-spacing:.16em;
  color:var(--muted);
}
.btn.ink{ background:var(--ink); color:var(--paper) }
.btn.ink span{ color:var(--faint) }
.btn:hover,.btn:focus-visible{ background:var(--accent); border-color:var(--accent); color:var(--paper) }
.btn:hover span,.btn:focus-visible span{ color:var(--paper) }

.mini{
  display:inline-flex; align-items:center; gap:8px;
  padding:8px 12px;
  border:1px solid var(--ink);
  background:var(--paper);
  color:var(--ink);
  text-decoration:none;
  font-family:var(--mono);
  font-size:10px; letter-spacing:.16em;
  text-transform:uppercase; white-space:nowrap;
  transition:background .15s ease, color .15s ease, border-color .15s ease;
}
.mini:hover,.mini:focus-visible{ background:var(--accent); border-color:var(--accent); color:var(--paper) }

.copy{
  flex:none;
  padding:6px 10px;
  border:1px solid var(--line);
  background:transparent;
  color:var(--ink);
  font-family:var(--mono);
  font-size:9.5px; letter-spacing:.16em;
  text-transform:uppercase;
  cursor:pointer;
  transition:background .15s ease, color .15s ease, border-color .15s ease;
}
.copy:hover{ border-color:var(--accent); color:var(--accent) }
.copy.ok{ background:var(--accent); border-color:var(--accent); color:var(--paper) }

/* ---- sticky index ---------------------------------------------------------- */
.bar{
  position:sticky;
  top:env(safe-area-inset-top,0px);
  z-index:9;
  background:var(--wall);
  border-bottom:1px solid var(--groove);
}
.bar .wrap{ display:flex; align-items:center; gap:clamp(12px,3vw,28px); padding-block:9px }
.who{
  flex:none;
  color:var(--ink);
  text-decoration:none;
  font-weight:800; font-stretch:78%;
  font-size:15px; letter-spacing:.01em;
  text-transform:uppercase; line-height:1;
}
.who span{
  display:block; margin-top:4px;
  font-family:var(--mono); font-weight:400;
  font-size:8.5px; letter-spacing:.24em;
  color:var(--muted);
}
.bar nav{
  flex:1; min-width:0;
  display:flex; gap:2px;
  overflow-x:auto;
  scrollbar-width:none;
}
.bar nav::-webkit-scrollbar{ display:none }
/* pushes the links right on a desk; resolves to nothing once they overflow */
.bar nav a:first-child{ margin-left:auto }
.bar nav a{
  flex:none;
  padding:8px 9px;
  font-family:var(--mono);
  font-size:10.5px; letter-spacing:.18em;
  text-transform:uppercase;
  color:var(--ink);
  text-decoration:none;
}
.bar nav a:hover{ color:var(--accent) }

/* ---- masthead -------------------------------------------------------------- */
.mast{ padding-top:clamp(28px,6vw,64px); display:flex; flex-direction:column; gap:clamp(22px,3.6vw,36px) }
.mast-grid{
  display:grid;
  grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);
  gap:clamp(24px,5vw,64px);
  align-items:end;
}
.logo{ margin:0; width:min(560px,100%); color:var(--ink); line-height:0 }
.logo svg{ display:block; width:100%; height:auto }
.lede{
  margin:clamp(16px,2.6vw,24px) 0 0;
  max-width:34ch;
  font-size:clamp(17px,2.1vw,21px);
  line-height:1.42;
  color:var(--ink);
  text-wrap:balance;
}

.facts{ margin:0; padding:clamp(16px,2.4vw,22px) clamp(16px,2.6vw,24px); rotate:.4deg }
.facts div{
  display:grid;
  grid-template-columns:72px minmax(0,1fr);
  gap:12px;
  padding:9px 0;
  border-top:1px solid var(--groove);
}
.facts div:first-child{ border-top:0; padding-top:0 }
.facts div:last-child{ padding-bottom:0 }
.facts dt{
  padding-top:3px;
  font-family:var(--mono);
  font-size:9.5px; letter-spacing:.2em;
  text-transform:uppercase;
  color:var(--muted);
}
.facts dd{
  margin:0;
  font-weight:700; font-stretch:84%;
  font-size:15px; letter-spacing:.02em;
  text-transform:uppercase;
  color:var(--ink);
  line-height:1.3;
  overflow-wrap:anywhere;
}
.facts dd.addr{ font-family:var(--mono); font-weight:500; font-size:13px; letter-spacing:0; text-transform:none }

.print{
  margin:0;
  padding:clamp(8px,1.2vw,14px) clamp(8px,1.2vw,14px) 0;
  background:var(--paper);
  box-shadow:0 1px 2px rgba(var(--shadow),.18), 0 22px 44px -22px rgba(var(--shadow),.45);
}
.hero{ rotate:-.35deg }
.hero a{ display:block }
.hero img{ display:block; width:100%; height:auto; aspect-ratio:1800/1130; background:#0d0d0d }
.print figcaption{
  display:flex; justify-content:space-between; flex-wrap:wrap; gap:4px 14px;
  padding:10px 2px 11px;
  font-family:var(--mono);
  font-size:9.5px; letter-spacing:.16em;
  text-transform:uppercase;
  color:var(--muted);
  font-variant-numeric:tabular-nums;
}

.grab{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px }

/* ---- sections -------------------------------------------------------------- */
main{ display:flex; flex-direction:column; gap:clamp(64px,10vw,120px); padding-block:clamp(56px,9vw,110px) 0 }

.sec-head{
  display:flex; align-items:flex-end; justify-content:space-between;
  flex-wrap:wrap; gap:14px 24px;
  margin-bottom:clamp(20px,3vw,32px);
  padding-bottom:14px;
  border-bottom:2px solid var(--ink);
}
.sec-head h2{ margin:0; font-size:clamp(40px,7.2vw,76px); color:var(--ink) }
.sec-head .aside{
  display:flex; align-items:center; flex-wrap:wrap; gap:10px 14px;
  font-family:var(--mono);
  font-size:10px; letter-spacing:.18em;
  text-transform:uppercase;
  color:var(--muted);
}

/* ---- bio ------------------------------------------------------------------- */
.bio{
  display:grid;
  grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);
  grid-template-areas:"full side";
  gap:clamp(18px,3.4vw,44px);
  align-items:start;
}
.bio-full{ grid-area:full; padding:clamp(20px,3.4vw,40px) }
.bio-side{ grid-area:side; display:flex; flex-direction:column; gap:14px }

.len{ padding:16px 18px }
.len-top{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px }
.len h3{
  margin:0;
  font-family:var(--mono); font-weight:500;
  font-size:10px; letter-spacing:.22em;
  text-transform:uppercase;
  color:var(--ink);
}
.len h3 span{ font-weight:400; color:var(--muted) }
.len p{ margin:0; font-size:15px; line-height:1.55 }
.len-tools{ display:flex; gap:6px }

.bio-text{ max-width:64ch }
.bio-text p{ margin:0 0 1em }
.bio-text p:last-child{ margin-bottom:0 }
.bio-text p:first-child{
  font-weight:800; font-stretch:80%;
  font-size:clamp(22px,2.8vw,30px);
  line-height:1.06;
  letter-spacing:.005em;
  text-transform:uppercase;
  color:var(--ink);
  text-wrap:balance;
}

.studio{ margin-top:clamp(30px,4.4vw,48px) }
.studio ul{
  list-style:none; margin:0; padding:0;
  display:flex; flex-wrap:wrap; gap:.18em .4em;
  font-weight:800; font-stretch:76%;
  font-size:clamp(24px,4.2vw,46px);
  line-height:1; letter-spacing:-.005em;
  text-transform:uppercase;
  color:var(--ink);
}
.studio li::after{ content:"/"; margin-left:.4em; font-weight:400; color:var(--accent) }
.studio li:last-child::after{ content:none }

/* ---- photos ---------------------------------------------------------------- */
.ph-grid{ display:grid; gap:clamp(10px,1.4vw,16px) }
.ph-grid.land{ grid-template-columns:repeat(3,minmax(0,1fr)) }
.ph-grid.port{ grid-template-columns:repeat(4,minmax(0,1fr)) }
.ph-grid + .kicker{ margin-top:clamp(26px,3.6vw,40px) }
.ph{ margin:0 }
.ph a{ display:block; color:inherit; text-decoration:none }
.ph img{
  display:block; width:100%; height:auto;
  object-fit:cover;
  background:#0d0d0d;
  box-shadow:0 1px 2px rgba(var(--shadow),.25);
  transition:filter .2s ease;
}
.land .ph img{ aspect-ratio:3/2 }
.port .ph img{ aspect-ratio:3451/4831 }
.ph figcaption{
  display:flex; justify-content:space-between; flex-wrap:wrap; gap:2px 8px;
  margin-top:7px;
  font-family:var(--mono);
  font-size:9px; letter-spacing:.13em;
  text-transform:uppercase;
  color:var(--muted);
  font-variant-numeric:tabular-nums;
}
.ph figcaption b{ font-weight:500; color:var(--ink) }
.ph a:hover img{ filter:contrast(1.06) brightness(1.05) }
.ph a:hover figcaption b{ color:var(--accent) }

/* ---- logos ------------------------------------------------------------------- */
.logos{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:clamp(10px,1.4vw,16px) }
.lg{ display:flex; flex-direction:column; background:var(--paper); box-shadow:0 1px 2px rgba(var(--shadow),.15), 0 14px 30px -14px rgba(var(--shadow),.3) }
.lg .sw{ aspect-ratio:4/3; min-height:0; display:grid; grid-template:minmax(0,1fr)/minmax(0,1fr); place-items:center; padding:12%; background:var(--paper) }
.lg.dark .sw{ background:var(--ink) }
.lg .sw img{ display:block; width:100%; height:100%; object-fit:contain }
.lg .meta{ padding:12px 14px 14px; border-top:1px solid var(--groove) }
.lg h3{
  margin:0 0 9px;
  font-weight:800; font-stretch:84%;
  font-size:14px; letter-spacing:.04em;
  text-transform:uppercase;
  color:var(--ink);
  line-height:1.15;
}
.fmt{ display:flex; flex-wrap:wrap; gap:5px }
.fmt a{
  padding:5px 8px;
  border:1px solid var(--line);
  color:var(--ink);
  text-decoration:none;
  font-family:var(--mono);
  font-size:9.5px; letter-spacing:.14em;
  transition:background .15s ease, color .15s ease, border-color .15s ease;
}
.fmt a:hover,.fmt a:focus-visible{ background:var(--accent); border-color:var(--accent); color:var(--paper) }
.lg .tip{ margin:9px 0 0; font-size:11.5px; line-height:1.4; color:var(--muted) }

/* ---- music ----------------------------------------------------------------- */
.two{
  display:grid;
  grid-template-columns:minmax(0,1fr) minmax(0,1fr);
  gap:clamp(18px,3.4vw,44px);
  align-items:start;
}

.release{ padding:clamp(16px,2.6vw,24px) }
.release .head{ display:flex; gap:16px; align-items:flex-start }
.release img.art{
  width:clamp(96px,14vw,132px); height:auto; aspect-ratio:1;
  flex:none; object-fit:cover; display:block;
  box-shadow:0 1px 2px rgba(var(--shadow),.3);
}
.release .ttl{ flex:1; min-width:0 }
.release h3{ margin:0; font-size:clamp(28px,4vw,40px); color:var(--ink) }
.release .by{
  margin:.6em 0 0;
  font-family:var(--mono);
  font-size:10px; letter-spacing:.14em;
  text-transform:uppercase;
  color:var(--muted);
  line-height:1.7;
}
.dsp{ display:flex; flex-wrap:wrap; gap:6px; margin-top:16px }
.dsp a{
  flex:1 1 calc(33.333% - 4px);
  text-align:center;
  padding:9px 10px;
  border:1px solid var(--line);
  color:var(--ink);
  text-decoration:none;
  font-weight:800; font-stretch:88%;
  font-size:10.5px; letter-spacing:.09em;
  text-transform:uppercase;
  transition:background .15s ease, color .15s ease, border-color .15s ease;
}
.dsp a:hover,.dsp a:focus-visible{ background:var(--accent); border-color:var(--accent); color:var(--paper) }
.dsp a.primary{
  flex:1 0 100%;
  padding:14px 10px;
  background:var(--ink); border-color:var(--ink); color:var(--paper);
  font-size:12.5px; letter-spacing:.12em;
}
.dsp a.primary:hover,.dsp a.primary:focus-visible{ background:var(--accent); border-color:var(--accent) }

.links{ display:flex; flex-direction:column; gap:10px }
.link{
  display:flex; align-items:center; gap:14px;
  padding:12px 15px;
  color:var(--body);
  text-decoration:none;
  transition:transform .15s ease;
}
.link:nth-child(odd){ rotate:-.35deg }
.link:nth-child(even){ rotate:.3deg }
.link:hover,.link:focus-visible{ transform:translateY(-2px) }
.link img{
  width:52px; height:52px;
  flex:none; object-fit:cover; display:block;
  background:#0d0d0d;
  box-shadow:0 1px 2px rgba(var(--shadow),.3);
}
.link .lt{ flex:1; min-width:0 }
.link .lt b{
  display:block;
  font-weight:800; font-stretch:80%;
  font-size:16px; letter-spacing:.005em;
  text-transform:uppercase;
  color:var(--ink);
  line-height:1.1;
}
.link .lt span{
  display:block; margin-top:.4em;
  font-family:var(--mono);
  font-size:9.5px; letter-spacing:.14em;
  text-transform:uppercase;
  color:var(--muted);
}
.link .go{ flex:none; color:var(--accent); font-size:17px; line-height:1 }

.disco{ margin-top:clamp(28px,4vw,44px); padding:clamp(16px,2.6vw,26px) }
.disco-top{ display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:6px 16px; margin-bottom:10px }
.disco-top .kicker{ margin:0 }
.disco-top small{ font-family:var(--mono); font-size:9.5px; letter-spacing:.12em; color:var(--faint) }
.scroll{ overflow-x:auto }
.disco table{ width:100%; min-width:560px; border-collapse:collapse; font-variant-numeric:tabular-nums }
.disco th{
  text-align:left;
  padding:0 14px 10px 0;
  border-bottom:1px solid var(--line);
  font-family:var(--mono); font-weight:400;
  font-size:9.5px; letter-spacing:.2em;
  text-transform:uppercase;
  color:var(--muted);
}
.disco td{ padding:11px 14px 11px 0; border-bottom:1px solid var(--groove); vertical-align:baseline }
.disco tr:last-child td{ border-bottom:0 }
.disco td.y{ width:4.6em; font-family:var(--mono); font-size:12px; color:var(--ink) }
.disco td.t{
  font-weight:800; font-stretch:80%;
  font-size:16px; letter-spacing:.01em;
  text-transform:uppercase;
  color:var(--ink);
}
.disco td.t span{ font-weight:500; font-stretch:92%; font-size:13.5px; letter-spacing:0; text-transform:none; color:var(--muted) }
.disco td.l{ font-size:14px }
.disco td.c{ font-family:var(--mono); font-size:11px; color:var(--muted); white-space:nowrap }

/* ---- live ------------------------------------------------------------------ */
.dates{ padding:clamp(14px,2.4vw,20px) clamp(16px,2.6vw,22px) }
.dates ul{ list-style:none; margin:0; padding:0 }
.dates li + li{ border-top:1px solid var(--groove) }
.date{ display:flex; align-items:center; gap:14px; padding:13px 0; color:var(--body); text-decoration:none }
.date img{ width:56px; height:56px; flex:none; object-fit:cover; display:block }
.date .when{
  flex:none; width:70px;
  font-family:var(--mono);
  font-size:13px; letter-spacing:.04em;
  color:var(--ink);
  line-height:1.25;
  font-variant-numeric:tabular-nums;
}
.date .when small{ display:block; font-size:9.5px; letter-spacing:.16em; color:var(--muted) }
.date .where{ flex:1; min-width:0 }
.date .where b{
  display:block;
  font-weight:700; font-stretch:80%;
  font-size:16px; letter-spacing:.04em;
  text-transform:uppercase;
  color:var(--ink);
  line-height:1.15;
}
.date .where span{
  display:block; margin-top:.3em;
  font-family:var(--mono);
  font-size:9.5px; letter-spacing:.16em;
  text-transform:uppercase;
  color:var(--muted);
}
.date .tix{
  flex:none;
  font-family:var(--mono);
  font-size:9.5px; letter-spacing:.14em;
  text-transform:uppercase;
  color:var(--accent);
  border-bottom:1px solid currentColor;
}
a.date:hover .where b{ color:var(--accent) }

/* where he's played: a festival bill in tiers, then the rooms, then the cities */
.stages{ padding:clamp(18px,2.6vw,26px); rotate:-.4deg }
.bill{ list-style:none; margin:0; padding:0 }
.bill li{ display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:2px 12px; padding:7px 0 6px; border-top:1px solid var(--groove) }
.bill li:first-child{ border-top:0; padding-top:0 }
.bill b{ font-weight:800; font-stretch:76%; font-size:clamp(32px,3.9vw,50px); line-height:.9; letter-spacing:-.015em; text-transform:uppercase; color:var(--ink) }
.bill small{ flex:none; font-family:var(--mono); font-size:9.5px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted); text-align:right }
.bill.t2{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 20px; margin-top:12px; padding-top:12px; border-top:2px solid var(--ink) }
.bill.t2 li{ flex-direction:column; align-items:flex-start; gap:3px; padding:8px 0 7px }
.bill.t2 li:nth-child(-n+2){ border-top:0; padding-top:0 }
.bill.t2 b{ font-stretch:78%; font-size:clamp(20px,2.3vw,26px); line-height:.95 }
.bill.t2 small{ text-align:left }
.also{ margin:14px 0 0; padding-top:12px; border-top:1px solid var(--groove); font-family:var(--mono); font-size:10.5px; letter-spacing:.12em; text-transform:uppercase; line-height:1.9; color:var(--ink) }
.rooms{ margin-top:clamp(18px,3.4vw,44px); padding:clamp(18px,2.6vw,26px); rotate:.25deg }
.rooms ul{ list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:0 clamp(14px,2vw,26px) }
.rooms li{ padding:12px 0 14px; border-top:1px solid var(--groove) }
.rooms li b{ display:block; font-weight:800; font-stretch:78%; font-size:clamp(22px,2.4vw,30px); line-height:.92; letter-spacing:-.01em; text-transform:uppercase; color:var(--ink) }
.rooms li span{ display:block; margin-top:6px; font-family:var(--mono); font-size:10px; letter-spacing:.18em; text-transform:uppercase; color:var(--ink) }
.rooms li small{ display:block; margin-top:3px; font-size:12.5px; line-height:1.4; color:var(--muted) }
.cities{ margin:18px 0 0; padding-top:14px; border-top:2px solid var(--ink); font-weight:800; font-stretch:80%; font-size:clamp(17px,1.9vw,22px); line-height:1.3; text-transform:uppercase; color:var(--ink) }
.cities .kicker{ display:block; margin-bottom:6px }
.cities i{ font-style:normal; color:var(--accent) }
.rooms .quip{ margin:14px 0 0; font-size:14px; max-width:46ch }
.rooms li.lead{ grid-column:span 2 }
/* country after a name, small and raised to the top of the capitals */
.bill sup,.rooms sup,.also sup{ font-family:var(--mono); font-weight:400; font-stretch:100%; font-size:9px; letter-spacing:.1em; line-height:1; vertical-align:top; margin-left:.35em; color:var(--muted) }
.also sup{ font-size:8px; vertical-align:super; margin-left:.25em }
.bill sup{ position:relative; top:clamp(3px,.4vw,5px) }
.bill.t2 sup{ top:2px }

/* ---- rider ----------------------------------------------------------------- */
.rig{ padding:clamp(18px,3vw,32px) }
.spec{
  display:flex; flex-wrap:wrap; gap:10px clamp(20px,3.4vw,40px);
  margin:0 0 clamp(16px,2.4vw,24px);
  padding:0; list-style:none;
}
.spec li{
  font-weight:800; font-stretch:80%;
  font-size:clamp(19px,2.4vw,25px);
  text-transform:uppercase;
  color:var(--ink);
  line-height:1.05;
}
.spec li small{
  display:block; margin-top:5px;
  font-family:var(--mono); font-weight:400;
  font-size:9.5px; letter-spacing:.16em;
  color:var(--muted);
}
/* the booth as the rider shows it, in real photographs. Columns follow each
   unit's width (CDJ-3000 329 mm, DJM-V10 438 mm), so the row stands
   to scale; bottoms align like units on one table. */
.setup{
  display:grid;
  grid-template-columns:329fr 438fr 329fr 329fr;
  gap:clamp(6px,1.4vw,16px);
  align-items:end;
}
.setup figure{ margin:0; min-width:0 }
.setup img{ display:block; width:100%; height:auto }
.setup figcaption{
  margin-top:12px;
  text-align:center;
  font-family:var(--mono);
  font-size:11px; letter-spacing:.2em;
  text-transform:uppercase;
  color:var(--ink);
}
.setup figcaption small{ display:block; margin-top:3px; font-size:9.5px; letter-spacing:.12em; color:var(--muted) }

.rider{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:clamp(12px,2vw,20px);
  align-items:start;
  margin-top:clamp(12px,2vw,20px);
}
.rcol{ display:flex; flex-direction:column; gap:clamp(12px,2vw,20px) }
.rlist{ padding:16px 18px }
.rlist h3{
  margin:0 0 8px;
  font-weight:800; font-stretch:84%;
  font-size:17px; letter-spacing:.02em;
  text-transform:uppercase;
  color:var(--ink);
}
.rlist ul{ list-style:none; margin:0; padding:0 }
.rlist li{ position:relative; padding:8px 0 8px 14px; border-top:1px solid var(--groove); font-size:14px; line-height:1.45 }
.rlist li:first-child{ border-top:0 }
.rlist li::before{ content:"\00B7"; position:absolute; left:2px; color:var(--muted) }
.rlist b{ font-weight:700; color:var(--ink) }
.pill{
  display:inline-block;
  margin:0 0 8px;
  padding:4px 10px;
  border-radius:999px;
  background:rgba(222,109,150,.16);
  background:color-mix(in srgb, var(--accent) 16%, var(--paper));
  color:var(--ink);
  font-family:var(--mono);
  font-size:9px; letter-spacing:.18em;
  text-transform:uppercase;
}
.fn{ margin:10px 0 0; font-family:var(--mono); font-size:9.5px; letter-spacing:.1em; color:var(--muted) }

/* ---- contact --------------------------------------------------------------- */
.contact{
  padding:clamp(22px,4vw,48px);
  display:grid;
  grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);
  gap:clamp(22px,4vw,48px);
  align-items:center;
}
.mail{ display:flex; align-items:center; flex-wrap:wrap; gap:12px 16px }
.mail a{
  font-weight:800; font-stretch:76%;
  font-size:clamp(24px,3.6vw,42px);
  line-height:1.05;
  color:var(--ink);
  text-decoration:none;
  overflow-wrap:anywhere;
}
.mail a:hover{ color:var(--accent) }
.prof{ list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:6px }
.prof a{
  display:flex; flex-direction:column; gap:3px;
  padding:10px 12px;
  border:1px solid var(--line);
  color:var(--ink);
  text-decoration:none;
  font-weight:800; font-stretch:84%;
  font-size:13px; letter-spacing:.06em;
  text-transform:uppercase;
  transition:background .15s ease, color .15s ease, border-color .15s ease;
}
.prof a span{
  font-family:var(--mono); font-weight:400;
  font-size:9.5px; letter-spacing:.06em;
  text-transform:none;
  color:var(--muted);
  overflow-wrap:anywhere;
}
.prof a:hover,.prof a:focus-visible{ background:var(--accent); border-color:var(--accent); color:var(--paper) }
.prof a:hover span,.prof a:focus-visible span{ color:var(--paper) }

/* ---- footer ---------------------------------------------------------------- */
.foot{
  display:flex; flex-direction:column; align-items:center; gap:14px;
  padding-block:clamp(56px,8vw,96px) clamp(36px,6vw,64px);
}
.note{ margin:0; font-family:var(--marker); font-size:17px; color:var(--accent); rotate:-2deg; text-align:center }
.swatches{ display:flex; gap:7px; flex-wrap:wrap; justify-content:center }
.gs{
  width:26px; height:26px;
  padding:0;
  border:1px solid var(--line);
  font-size:0;
  cursor:pointer;
  transition:transform .15s ease;
}
.gs:hover{ transform:translateY(-2px) }
.gs[aria-pressed="true"]{ border-color:var(--ink); outline:1px solid var(--ink); outline-offset:1px }
.small{
  margin:0;
  font-family:var(--mono);
  font-size:9px; letter-spacing:.16em;
  text-transform:uppercase;
  color:var(--faint);
  text-align:center;
  line-height:1.8;
}

a:focus-visible,button:focus-visible{ outline:2px solid var(--accent); outline-offset:3px }

/* ---- narrow ---------------------------------------------------------------- */
@media (max-width:980px){
  .rider{ grid-template-columns:repeat(2,minmax(0,1fr)) }
  .rooms ul{ grid-template-columns:repeat(3,minmax(0,1fr)) }
  .rooms li.lead{ grid-column:span 3 }
}
@media (max-width:860px){
  .mast-grid,.two,.contact{ grid-template-columns:minmax(0,1fr) }
  .bio{ grid-template-columns:minmax(0,1fr); grid-template-areas:"side" "full" }
  .logos{ grid-template-columns:repeat(2,minmax(0,1fr)) }
}
@media (max-width:760px){
  .grab{ grid-template-columns:repeat(2,minmax(0,1fr)) }
  .ph-grid.land,.ph-grid.port{ grid-template-columns:repeat(2,minmax(0,1fr)) }
}
@media (max-width:640px){
  .rider{ grid-template-columns:minmax(0,1fr) }
  .rooms ul{ grid-template-columns:repeat(2,minmax(0,1fr)) }
  .rooms li.lead{ grid-column:span 2 }
  .bar .who span{ display:none }
  .setup figcaption{ font-size:8.5px; letter-spacing:.1em }
  .setup figcaption small{ font-size:8px; letter-spacing:.04em }
}

@media (prefers-reduced-motion:reduce){
  *{ transition-duration:.01ms !important; animation-duration:.01ms !important }
  .link:hover,.gs:hover{ transform:none }
}
</style>

<div class="bar">
  <div class="wrap">
    <a class="who" href="#top">Sander Kleinenberg<span>Press kit 2026</span></a>
    <nav aria-label="Sections">
<?php if (in_array('bio', $see, true)): ?>
      <a href="#bio">Bio</a>
<?php endif; ?>
<?php if (in_array('photos', $see, true)): ?>
      <a href="#photos">Photos</a>
<?php endif; ?>
<?php if (in_array('logos', $see, true)): ?>
      <a href="#logos">Logos</a>
<?php endif; ?>
<?php if (in_array('music', $see, true)): ?>
      <a href="#music">Music</a>
<?php endif; ?>
<?php if (in_array('live', $see, true)): ?>
      <a href="#live">Live</a>
<?php endif; ?>
<?php if (in_array('rider', $see, true)): ?>
      <a href="#rider">Rider</a>
<?php endif; ?>
<?php if (in_array('contact', $see, true)): ?>
      <a href="#contact">Contact</a>
<?php endif; ?>
    </nav>
  </div>
</div>

<header class="mast wrap" id="top">
  <div class="mast-grid">
    <div>
      <p class="kicker">Press kit &middot; 2026</p>
      <h1 class="logo">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="25.03 9.87 1040.27 546.01" role="img" aria-label="Sander Kleinenberg">
 <defs><filter id="s" x="-3%" y="-4%" width="106%" height="108%" color-interpolation-filters="sRGB">
 <!-- Worn screenprint, fitted to the tour artwork rather than eyeballed. Measured off
      the poster: its ink is almost solid (median L .024) and the wear lives entirely in
      the top few percent of pixels - p97 .169, p99.5 .400, only .8% above L .33. So the
      masks use a SOFT ramp, which thins ink to grey, instead of a hard threshold, which
      punches white holes. Thresholds are stated in standard deviations of each noise's
      real distribution: fractalNoise is centred on .500 with sd .1186, turbulence on
      .254 with sd .1452 - not on 0, as the raw numbers suggest.
      The poster's dust is also clustered in bands, not spread evenly, so both the dust
      and the craquelure are gated by a low-frequency "where the wear happens" field. -->
 
 <!-- where the ink is worn at all: broad, soft-edged bands -->
 <feTurbulence type="fractalNoise" baseFrequency="0.03" numOctaves="2" seed="3" result="fn"/>
 <feColorMatrix in="fn" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 8.0 0 0 0 -3.4307" result="field"/>
 
 <!-- dust: a fine pass and a coarser one, so speck sizes vary the way real ones do -->
 <feTurbulence type="fractalNoise" baseFrequency="0.45" numOctaves="2" seed="7" result="d1"/>
 <feColorMatrix in="d1" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 -3.25 0 0 0 3.3034" result="m1"/>
 <feTurbulence type="fractalNoise" baseFrequency="0.2" numOctaves="2" seed="31" result="d2"/>
 <feColorMatrix in="d2" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 -3.0 0 0 0 3.2152" result="m2"/>
 <feComposite in="m1" in2="m2" operator="arithmetic" k1="1" k2="0" k3="0" k4="0" result="dust"/>
 <feComposite in="dust" in2="field" operator="arithmetic" k1="1" k2="0" k3="-1" k4="1" result="dustg"/>
 <feComposite in="SourceGraphic" in2="dustg" operator="in" result="s0"/>
 
 <!-- craquelure: turbulence branches where fractalNoise only speckles. Gated tightly so
      it shows up in a couple of places, like a real flake, instead of everywhere. -->
 <feTurbulence type="turbulence" baseFrequency="0.35" numOctaves="3" seed="19" result="c0"/>
 <feColorMatrix in="c0" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 -2.6 0 0 0 2.2154" result="cm"/>
 <feTurbulence type="fractalNoise" baseFrequency="0.055" numOctaves="2" seed="9" result="qn"/>
 <feColorMatrix in="qn" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 8.0 0 0 0 -4.5693" result="qfield"/>
 <feComposite in="cm" in2="qfield" operator="arithmetic" k1="1" k2="0" k3="-1" k4="1" result="crack"/>
 <feComposite in="s0" in2="crack" operator="in" result="s1"/>
 
 <!-- hairline squeegee drag along the print direction -->
 <feTurbulence type="fractalNoise" baseFrequency="0.01 0.8" numOctaves="2" seed="23" result="x0"/>
 <feColorMatrix in="x0" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 -7.0 0 0 0 6.8246" result="xm"/>
 <feComposite in="s1" in2="xm" operator="in"/>
 </filter></defs>
 <path id="skmark" fill="currentColor" filter="url(#s)" d="M77.3830 349.5839 87.5233 346.8137C117.5944 338.5989 127.6967 324.1762 127.2019 303.5771L126.1084 266.2951C125.5381 251.1595 117.9903 245.1869 97.7127 234.9165L79.6246 225.8623C74.0078 222.9906 72.3288 220.8575 72.2437 216.2156L71.3613 184.0595C71.3046 180.9648 72.0385 179.4684 75.1855 178.6088C78.6822 177.6535 80.1092 178.8188 80.1029 181.6714L81.1901 221.8061L122.1008 210.6301L120.9569 167.4008C120.5125 152.7491 106.2014 145.2548 76.4800 153.3741L66.6894 156.0487C36.6183 164.2635 27.0262 177.7693 27.5273 195.5157L28.4727 227.9138C28.9517 241.2602 36.0175 249.6970 53.6300 258.3629L71.3055 267.2707C78.1602 270.5816 80.5386 272.5237 80.7151 278.9549L81.6826 315.7530C81.5787 319.6690 81.3204 321.5538 77.4741 322.6045C73.9774 323.5598 72.2007 322.4901 72.0810 319.1535L70.7889 271.0403L28.8292 282.5028L30.1213 330.6160C30.6507 349.9097 47.3119 357.7987 77.3830 349.5839ZM179.8181 256.8063 169.3282 259.6719 171.8412 185.8974ZM186.3868 317.9919 227.6471 306.7204 199.3034 121.6354 144.7558 136.5367 129.3916 333.5619 166.8056 323.3411 168.1535 288.2431 182.8394 284.2312ZM227.3028 113.9866 232.5687 305.3760 268.5841 295.5373 265.0579 179.8708 288.5150 290.0926 328.0270 279.2987 322.4114 88.0048 287.0954 97.6524 290.2970 202.0038 270.6611 102.1420ZM337.4679 276.7196 403.5544 258.6661C423.4853 253.2214 433.0427 241.0209 432.4219 219.9379L428.8356 88.2188C428.2432 68.6832 412.6247 63.3603 390.9456 69.2826L332.2020 85.3302ZM375.6929 99.8854 379.5392 98.8347C384.0849 97.5929 385.0646 99.9170 385.1843 103.2536L388.6571 228.7835C388.8336 235.2147 387.1483 235.9343 383.3020 236.9850L379.4557 238.0358ZM440.9408 55.6181 446.2068 247.0075 520.6852 226.6615 520.0202 196.7785 488.2008 205.4710 486.5432 150.2007 512.4183 143.1321 511.6273 112.7652 485.7522 119.8338 484.4885 73.2679 515.2589 64.8621 514.3703 35.5586ZM564.7001 46.1721 567.8471 45.3125C571.6934 44.2617 572.8337 45.7644 572.8905 48.8591L574.0912 95.1830C574.4976 98.1821 573.4770 100.0160 569.9803 100.9712L566.1340 102.0219ZM569.2885 213.3840 566.8683 129.2941 570.0153 128.4344C573.8616 127.3837 575.2886 128.5489 575.2823 131.4016L577.2866 202.3870C577.3087 206.7870 579.1137 209.4040 580.8274 210.2318L622.7871 198.7692L622.9477 197.9478C621.3947 196.2986 620.6040 194.7004 620.5189 190.0585L618.4579 115.9784C618.1333 104.6633 608.9916 101.1995 602.1306 100.7412C610.6548 96.0800 616.9613 88.6553 616.7974 76.5187L615.5620 31.5001C615.1523 15.5430 601.0869 11.8693 584.6527 16.3588L521.0139 33.7437L526.2798 225.1331ZM124.8938 542.1263 122.3287 466.1105 137.8313 538.5920 182.9380 526.2698 158.2746 430.1139 175.9237 335.3580 133.2647 347.0116 121.0020 419.3027 119.2782 350.8324 76.6192 362.4860 81.8851 553.8755ZM179.4204 334.4027 184.6863 525.7922 254.9688 506.5924 254.1778 476.2255 226.5543 483.7717 222.0794 322.7491ZM249.6983 315.2022 254.9642 506.5916 329.4426 486.2455 328.7776 456.3626 296.9582 465.0551 295.3007 409.7847 321.1758 402.7162 320.3848 372.3493 294.5096 379.4179 293.2459 332.8520 324.0164 324.4461 323.1277 295.1427ZM372.7800 281.5786 329.7713 293.3278 335.0373 484.7172 378.0459 472.9681ZM381.6248 279.1653 386.8907 470.5547 422.9061 460.7160 419.3799 345.0495 442.8370 455.2713 482.3490 444.4774 476.7334 253.1835 441.4173 262.8311 444.6190 367.1825 424.9831 267.3206ZM486.5240 250.5089 491.7899 441.8983 566.2684 421.5522 565.6034 391.6693 533.7839 400.3618 532.1264 345.0914 558.0015 338.0229 557.2105 307.6560 531.3354 314.7246 530.0717 268.1587 560.8421 259.7528 559.9534 230.4494ZM565.8531 228.8389 571.1190 420.2283 607.1344 410.3896 603.6082 294.7231 627.0653 404.9449 666.5773 394.1510 660.9617 202.8571 625.6456 212.5047 628.8473 316.8561 609.2114 216.9942ZM676.0182 391.5719 743.5034 373.1363C765.8819 367.0229 772.6419 355.5866 772.1125 336.2929L770.6093 283.0539C770.2280 268.6441 757.5896 266.1356 750.0923 266.1103C756.4209 263.0855 766.7627 255.3360 766.2207 241.7477L765.3288 199.4862C764.7301 182.8032 751.1750 178.2126 732.6428 183.2752L670.7523 200.1825ZM717.2358 211.8468C721.4318 210.7005 722.7328 211.3818 723.0416 215.4443L724.2139 260.2209C724.3336 263.5575 722.5507 265.3404 719.4037 266.2001L715.5574 267.2509L714.0888 212.7064ZM719.9552 289.8938C724.1512 288.7475 725.8649 289.5753 726.2366 293.8797L727.5162 347.6983C727.6359 351.0349 726.9019 352.5312 722.7060 353.6775L718.1603 354.9193L716.4586 290.8490ZM778.6944 170.6906 783.9603 362.0801 858.4388 341.7340 857.7738 311.8511 825.9543 320.5435 824.2968 265.2732 850.1719 258.2046 849.3809 227.8378 823.5058 234.9064 822.2421 188.3405 853.0125 179.9346 852.1239 150.6312ZM902.4537 161.2447 905.6007 160.3850C909.4470 159.3343 910.5873 160.8370 910.6441 163.9316L911.8448 210.2555C912.2512 213.2546 911.2305 215.0885 907.7339 216.0437L903.8876 217.0945ZM907.0421 328.4565 904.6219 244.3667 907.7688 243.5070C911.6151 242.4563 913.0422 243.6215 913.0359 246.4742L915.0401 317.4596C915.0622 321.8596 916.8673 324.4766 918.5810 325.3043L960.5407 313.8418L960.7013 313.0204C959.1483 311.3712 958.3576 309.7730 958.2725 305.1310L956.2115 231.0509C955.8869 219.7358 946.7452 216.2721 939.8842 215.8138C948.4084 211.1525 954.7149 203.7278 954.5509 191.5913L953.3156 146.5727C952.9059 130.6156 938.8405 126.9419 922.4063 131.4314L858.7675 148.8162L864.0334 340.2057ZM1002.8950 304.0883C1017.2312 300.1719 1025.4813 290.1428 1029.5638 282.8073C1031.1798 284.6984 1033.5803 291.0405 1034.2733 293.7021L1063.2955 285.7738L1060.1756 173.1064L1010.5233 186.6704L1010.9772 211.4275L1020.0685 208.9439L1021.5938 266.5830C1021.4898 270.4990 1019.8329 272.7659 1015.6369 273.9122C1011.4410 275.0584 1009.4753 273.2628 1009.2295 269.4423L1005.6716 139.2705C1005.5519 135.9339 1006.1598 133.9536 1010.0061 132.9028C1013.1531 132.0431 1015.2165 132.7754 1015.3992 136.3539L1016.5148 178.0359L1058.1248 166.6689L1057.0722 125.2288C1056.4105 108.3039 1042.9026 96.7025 1008.2859 106.1592L1002.3416 107.7830C968.0745 117.1441 961.1129 133.5598 961.7053 153.0955L965.1435 279.9307C965.7359 299.4663 978.4185 310.7748 1001.4963 304.4704Z"/>
 </svg>
    </h1>
      <p class="lede">Play the records that excite him. Preferably before someone decides what genre they belong to.</p>
    </div>
    <dl class="facts slip">
      <div><dt>From</dt><dd>The Netherlands</dd></div>
      <div><dt>Since</dt><dd>The 1990s</dd></div>
      <div><dt>Played</dt><dd>Coachella &middot; Glastonbury &middot; Ultra &middot; Tomorrowland</dd></div>
      <div><dt>Latest</dt><dd>Make It Rain, with DARMON &middot; Sept 2026</dd></div>
      <div><dt>Booth</dt><dd>3 &times; CDJ-3000 &middot; DJM-V10</dd></div>
      <div><dt>Bookings</dt><dd class="addr">achaykin@<wbr>independentartistgroup.com</dd></div>
    </dl>
  </div>

  <figure class="print hero">
    <?php if (in_array('photos', $see, true)): ?><a href="get.php?f=photos/Sander-Kleinenberg-press-47.jpg"><img src="img/hero-47.webp" alt="Sander Kleinenberg at the decks, black and white" width="1800" height="1130"></a><?php else: ?><img src="img/hero-47.webp" alt="Sander Kleinenberg at the decks, black and white" width="1800" height="1130"><?php endif; ?>
    <figcaption><span>No. 47 &middot; at the decks</span><span>7447 &times; 4673 &middot; JPG 3.9 MB</span></figcaption>
  </figure>

  <div class="grab">
<?php if (in_array('photos', $see, true)): ?>
    <a class="btn ink" href="get.php?f=Sander-Kleinenberg-press-photos-2026.zip">All 15 photos<span>ZIP &middot; 53 MB</span></a>
<?php endif; ?>
<?php if (in_array('logos', $see, true)): ?>
    <a class="btn" href="get.php?f=Sander-Kleinenberg-logos-2026.zip">Logo pack<span>ZIP &middot; SVG PNG EPS PDF</span></a>
<?php endif; ?>
<?php if (in_array('bio', $see, true)): ?>
    <a class="btn" href="get.php?f=Sander-Kleinenberg-biography-2026.pdf">Biography<span>PDF &middot; 2026</span></a>
<?php endif; ?>
<?php if (in_array('rider', $see, true)): ?>
    <a class="btn" href="get.php?f=Sander-Kleinenberg-rider-2026.pdf">Rider<span>PDF &middot; tech &amp; hospitality</span></a>
<?php endif; ?>
  </div>
</header>

<main class="wrap">

<?php if (in_array('bio', $see, true)): ?>
  <!-- =================================================================== BIO -->
  <section id="bio" aria-labelledby="h-bio">
    <div class="sec-head">
      <h2 class="cond" id="h-bio"><span class="strip">Biography</span></h2>
      <div class="aside"><span>Three lengths, ready to paste</span><a class="mini" href="get.php?f=Sander-Kleinenberg-biography-2026.pdf">PDF &darr;</a></div>
    </div>

    <div class="bio">
      <div class="bio-side">
        <div class="len slip">
          <div class="len-top">
            <h3>One line <span data-words="b-one">&middot; 29 words</span></h3>
            <button class="copy" type="button" data-copy="b-one" id="c-one">Copy</button>
          </div>
          <div id="b-one"><p>Dutch DJ and producer Sander Kleinenberg has spent more than three decades playing the records that excite him, from My Lexicon and This Is Miami to Make It Rain.</p></div>
        </div>
        <div class="len slip">
          <div class="len-top">
            <h3>Short <span data-words="b-short">&middot; 41 words</span></h3>
            <button class="copy" type="button" data-copy="b-short" id="c-short">Copy</button>
          </div>
          <div id="b-short"><p>Sander Kleinenberg has never been particularly good at standing still. For more than three decades, the Dutch DJ and producer has followed one fairly simple instinct: play the records that excite him. Preferably before someone decides what genre they belong to.</p></div>
        </div>
      </div>

      <article class="bio-full slip">
        <div class="len-top">
          <h3 class="kicker" style="margin:0">Full <span data-words="b-full">&middot; 363 words</span></h3>
          <button class="copy" type="button" data-copy="b-full" id="c-full">Copy</button>
        </div>
        <div class="bio-text" id="b-full">
          <p>Sander Kleinenberg has never been particularly good at standing still.</p>
          <p>For more than three decades, the Dutch DJ and producer has followed one fairly simple instinct: play the records that excite him. Preferably before someone decides what genre they belong to.</p>
          <p>That instinct has taken him from small, dark rooms to some very large ones, through progressive house, techno, disco, psychedelia, electronic pop and a fair amount of music that was probably a bad idea on paper. The common thread was never a genre. It was curiosity.</p>
          <p>Emerging from the Netherlands in the 1990s, Sander became part of a generation of DJs who somehow found themselves taking underground electronic music around the world. Records like My Lexicon, The Fruit, This Is Miami and Can You Feel It became markers along the way, while his DJ sets built a reputation for joining dots between records that didn&rsquo;t always appear to have much business being in the same room.</p>
          <p>It took him around the planet more times than he can reasonably remember, from sweaty basements to Coachella, Glastonbury and Ultra, and through a long list of clubs, afterparties, airports and questionable hotel breakfasts.</p>
          <p>The studio has been equally difficult to keep in one lane. Over the years Sander has produced, remixed and collaborated with Madonna, Daft Punk, Janet Jackson, Justin Timberlake, Halsey, LANY, Eurythmics, Jamie Cullum, Audio Bullys, Gwen McCrae and James Vincent McMorrow &mdash; which probably says more about his musical attention span than any genre description ever could.</p>
          <p>And, inconveniently, he&rsquo;s still curious.</p>
          <p>A new generation of artists and a more adventurous corner of electronic music have pulled him back towards the reason he started doing this in the first place: finding strange, beautiful records and figuring out what happens when you play them very loud in the right order.</p>
          <p>The sets are getting deeper again. The edges are getting stranger. New music is being made, new collaborations are taking shape, old friends are resurfacing and younger artists are introducing him to records he wishes he&rsquo;d found first.</p>
          <p>This isn&rsquo;t about recreating a particular period of Sander Kleinenberg. That would be far too easy. It&rsquo;s about continuing what was there from the beginning.</p>
        </div>
      </article>
    </div>

    <div class="studio">
      <p class="kicker">Produced, remixed and collaborated with</p>
      <ul>
        <li>Madonna</li><li>Daft Punk</li><li>Janet Jackson</li><li>Justin Timberlake</li><li>Halsey</li><li>LANY</li><li>Eurythmics</li><li>Jamie Cullum</li><li>Audio Bullys</li><li>Gwen McCrae</li><li>James Vincent McMorrow</li>
      </ul>
    </div>
  </section>
<?php endif; ?>

<?php if (in_array('photos', $see, true)): ?>
  <!-- ================================================================ PHOTOS -->
  <section id="photos" aria-labelledby="h-photos">
    <div class="sec-head">
      <h2 class="cond" id="h-photos"><span class="strip">Press photos</span></h2>
      <div class="aside"><span>15 photos &middot; for press and event promotion &middot; click for full size</span><a class="mini" href="get.php?f=Sander-Kleinenberg-press-photos-2026.zip">All &middot; ZIP 53 MB &darr;</a></div>
    </div>

    <p class="kicker">Landscape &middot; No. 47 is the one above</p>
    <div class="ph-grid land">
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-48.jpg"><img src="img/p-48.webp" alt="Sander Kleinenberg against a blue door. Colour." width="780" height="491" loading="lazy"><figcaption><b>No. 48 &middot; Colour</b><span>7681 &times; 4831 &middot; 4.5 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-46.jpg"><img src="img/p-46.webp" alt="Sander Kleinenberg against a door. Black and white." width="780" height="505" loading="lazy"><figcaption><b>No. 46 &middot; B&amp;W</b><span>7465 &times; 4831 &middot; 3.2 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-50.jpg"><img src="img/p-50.webp" alt="Sander Kleinenberg from behind, walking down a corridor. Black and white." width="780" height="547" loading="lazy"><figcaption><b>No. 50 &middot; B&amp;W</b><span>6638 &times; 4655 &middot; 3.6 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-44.jpg"><img src="img/p-44.webp" alt="Sander Kleinenberg in front of a bank of stage lights. Black and white." width="780" height="520" loading="lazy"><figcaption><b>No. 44 &middot; B&amp;W</b><span>6240 &times; 4160 &middot; 4.4 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-49.jpg"><img src="img/p-49.webp" alt="Sander Kleinenberg close up among stage lights. Black and white." width="780" height="543" loading="lazy"><figcaption><b>No. 49 &middot; B&amp;W</b><span>6946 &times; 4831 &middot; 4.1 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-45.jpg"><img src="img/p-45.webp" alt="Sander Kleinenberg seated in a barber chair, wide. Black and white." width="780" height="442" loading="lazy"><figcaption><b>No. 45 &middot; B&amp;W</b><span>8537 &times; 4831 &middot; 5.2 MB</span></figcaption></a></figure>
    </div>

    <p class="kicker">Portrait &middot; all 3451 &times; 4831</p>
    <div class="ph-grid port">
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-14.jpg"><img src="img/p-14.webp" alt="Sander Kleinenberg in a leather jacket and cap, hand at his chin. Colour." width="560" height="784" loading="lazy"><figcaption><b>No. 14 &middot; Colour</b><span>2.0 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-22.jpg"><img src="img/p-22.webp" alt="Sander Kleinenberg in a leather jacket and cap, hand at his chin. Black and white." width="560" height="784" loading="lazy"><figcaption><b>No. 22 &middot; B&amp;W</b><span>2.3 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-10.jpg"><img src="img/p-10.webp" alt="Sander Kleinenberg looking back over his shoulder by a door. Colour." width="560" height="784" loading="lazy"><figcaption><b>No. 10 &middot; Colour</b><span>3.6 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-11.jpg"><img src="img/p-11.webp" alt="Sander Kleinenberg looking back over his shoulder by a door. Black and white." width="560" height="784" loading="lazy"><figcaption><b>No. 11 &middot; B&amp;W</b><span>4.0 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-35.jpg"><img src="img/p-35.webp" alt="Sander Kleinenberg standing under a lamp in a white T-shirt. Colour." width="560" height="784" loading="lazy"><figcaption><b>No. 35 &middot; Colour</b><span>2.5 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-40.jpg"><img src="img/p-40.webp" alt="Sander Kleinenberg seated in a leather jacket. Black and white." width="560" height="784" loading="lazy"><figcaption><b>No. 40 &middot; B&amp;W</b><span>2.4 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-17.jpg"><img src="img/p-17.webp" alt="Sander Kleinenberg seated in a cap, looking down. Black and white." width="560" height="784" loading="lazy"><figcaption><b>No. 17 &middot; B&amp;W</b><span>3.3 MB</span></figcaption></a></figure>
      <figure class="ph"><a href="get.php?f=photos/Sander-Kleinenberg-press-43.jpg"><img src="img/p-43.webp" alt="Sander Kleinenberg seated in a barber chair. Black and white." width="560" height="784" loading="lazy"><figcaption><b>No. 43 &middot; B&amp;W</b><span>4.1 MB</span></figcaption></a></figure>
    </div>
  </section>
<?php endif; ?>

<?php if (in_array('logos', $see, true)): ?>
  <!-- ================================================================= LOGOS -->
  <section id="logos" aria-labelledby="h-logos">
    <div class="sec-head">
      <h2 class="cond" id="h-logos"><span class="strip">Logos</span></h2>
      <div class="aside"><span>Black on light, white on dark &middot; outlined, no font needed</span><a class="mini" href="get.php?f=Sander-Kleinenberg-logos-2026.zip">All &middot; ZIP &darr;</a></div>
    </div>

    <div class="logos">
      <div class="lg">
        <div class="sw"><img src="img/logo-black.svg" alt="Sander Kleinenberg logo in black" width="1040" height="546" loading="lazy"></div>
        <div class="meta"><h3>Logo &middot; black</h3>
          <div class="fmt"><a href="get.php?f=logos/SK-logo-full-black.svg">SVG</a><a href="get.php?f=logos/SK-logo-full-black.png">PNG</a><a href="get.php?f=logos/SK-logo-full-black.eps">EPS</a><a href="get.php?f=logos/SK-logo-full-black.pdf">PDF</a></div>
        </div>
      </div>
      <div class="lg dark">
        <div class="sw"><img src="img/logo-white.svg" alt="Sander Kleinenberg logo in white" width="1040" height="546" loading="lazy"></div>
        <div class="meta"><h3>Logo &middot; white</h3>
          <div class="fmt"><a href="get.php?f=logos/SK-logo-full-white.svg">SVG</a><a href="get.php?f=logos/SK-logo-full-white.png">PNG</a><a href="get.php?f=logos/SK-logo-full-white.eps">EPS</a><a href="get.php?f=logos/SK-logo-full-white.pdf">PDF</a></div>
          <p class="tip">White on transparent: it looks empty on a white screen and shows on a dark background.</p>
        </div>
      </div>
      <div class="lg">
        <div class="sw"><img src="img/logo-worn-black.webp" alt="Sander Kleinenberg logo, worn screenprint, in black" width="1040" height="541" loading="lazy"></div>
        <div class="meta"><h3>Logo worn &middot; black</h3>
          <div class="fmt"><a href="get.php?f=logos/SK-logo-full-worn-black.svg">SVG</a><a href="get.php?f=logos/SK-logo-full-worn-black.png">PNG</a><a href="get.php?f=logos/SK-logo-full-worn-black.eps">EPS</a><a href="get.php?f=logos/SK-logo-full-worn-black.pdf">PDF</a></div>
        </div>
      </div>
      <div class="lg dark">
        <div class="sw"><img src="img/logo-worn-white.webp" alt="Sander Kleinenberg logo, worn screenprint, in white" width="1040" height="541" loading="lazy"></div>
        <div class="meta"><h3>Logo worn &middot; white</h3>
          <div class="fmt"><a href="get.php?f=logos/SK-logo-full-worn-white.svg">SVG</a><a href="get.php?f=logos/SK-logo-full-worn-white.png">PNG</a><a href="get.php?f=logos/SK-logo-full-worn-white.eps">EPS</a><a href="get.php?f=logos/SK-logo-full-worn-white.pdf">PDF</a></div>
          <p class="tip">White on transparent: it looks empty on a white screen and shows on a dark background.</p>
        </div>
      </div>
      <div class="lg">
        <div class="sw"><img src="img/mark-black.svg" alt="SK mark in black" width="564" height="600" loading="lazy"></div>
        <div class="meta"><h3>SK mark &middot; black</h3>
          <div class="fmt"><a href="get.php?f=logos/SK-logo-head-black.svg">SVG</a><a href="get.php?f=logos/SK-logo-head-black.png">PNG</a><a href="get.php?f=logos/SK-logo-head-black.eps">EPS</a><a href="get.php?f=logos/SK-logo-head-black.pdf">PDF</a></div>
        </div>
      </div>
      <div class="lg dark">
        <div class="sw"><img src="img/mark-white.svg" alt="SK mark in white" width="564" height="600" loading="lazy"></div>
        <div class="meta"><h3>SK mark &middot; white</h3>
          <div class="fmt"><a href="get.php?f=logos/SK-logo-head-white.svg">SVG</a><a href="get.php?f=logos/SK-logo-head-white.png">PNG</a><a href="get.php?f=logos/SK-logo-head-white.eps">EPS</a><a href="get.php?f=logos/SK-logo-head-white.pdf">PDF</a></div>
          <p class="tip">White on transparent: it looks empty on a white screen and shows on a dark background.</p>
        </div>
      </div>
      <div class="lg">
        <div class="sw"><img src="img/mark-worn-black.webp" alt="SK mark, worn screenprint, in black" width="564" height="600" loading="lazy"></div>
        <div class="meta"><h3>SK mark worn &middot; black</h3>
          <div class="fmt"><a href="get.php?f=logos/SK-logo-head-worn-black.svg">SVG</a><a href="get.php?f=logos/SK-logo-head-worn-black.png">PNG</a><a href="get.php?f=logos/SK-logo-head-worn-black.eps">EPS</a><a href="get.php?f=logos/SK-logo-head-worn-black.pdf">PDF</a></div>
        </div>
      </div>
      <div class="lg dark">
        <div class="sw"><img src="img/mark-worn-white.webp" alt="SK mark, worn screenprint, in white" width="564" height="600" loading="lazy"></div>
        <div class="meta"><h3>SK mark worn &middot; white</h3>
          <div class="fmt"><a href="get.php?f=logos/SK-logo-head-worn-white.svg">SVG</a><a href="get.php?f=logos/SK-logo-head-worn-white.png">PNG</a><a href="get.php?f=logos/SK-logo-head-worn-white.eps">EPS</a><a href="get.php?f=logos/SK-logo-head-worn-white.pdf">PDF</a></div>
          <p class="tip">White on transparent: it looks empty on a white screen and shows on a dark background.</p>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (in_array('music', $see, true)): ?>
  <!-- ================================================================= MUSIC -->
  <section id="music" aria-labelledby="h-music">
    <div class="sec-head">
      <h2 class="cond" id="h-music"><span class="strip">Music</span></h2>
      <div class="aside"><span>Latest release, DJ sets, selected records</span></div>
    </div>

    <div class="two">
      <div>
        <p class="kicker">Out now</p>
        <div class="release slip">
          <div class="head">
            <img class="art" src="img/make-it-rain.webp" alt="Make It Rain cover" width="300" height="300">
            <div class="ttl">
              <h3 class="cond">Make It Rain</h3>
              <p class="by">Sander Kleinenberg &amp; DARMON<br>Make The Girls Dance Records<br>Released 4 September 2026</p>
            </div>
          </div>
          <div class="dsp">
            <a class="primary" href="https://music.apple.com/us/album/make-it-rain-single/6800771167">Apple Music</a>
            <a href="https://open.spotify.com/track/5xrKQ7HwqAcsPaXkU4x336">Spotify</a>
            <a href="https://www.beatport.com/release/make-it-rain-extended-mix/7345060">Beatport</a>
            <a href="https://listen.tidal.com/track/551763853">Tidal</a>
            <a href="https://www.deezer.com/album/1052542742">Deezer</a>
            <a href="https://music.amazon.com/albums/B0HF14444K">Amazon</a>
          </div>
        </div>
      </div>

      <div>
        <p class="kicker">DJ sets</p>
        <div class="links">
          <a class="link slip" href="https://www.youtube.com/watch?v=Y9PXiVSKWNI">
            <img src="img/set-panorama.webp" alt="" width="427" height="240" loading="lazy">
            <span class="lt"><b>Panorama Sessions 003</b><span>SXM Festival &middot; video &middot; YouTube</span></span>
            <span class="go" aria-hidden="true">&rarr;</span>
          </a>
          <a class="link slip" href="https://soundcloud.com/balance-series/balance-selections-300-sander-kleinenberg">
            <img src="img/set-balance.webp" alt="" width="240" height="240" loading="lazy">
            <span class="lt"><b>Balance Selections 300</b><span>Balance Music &middot; SoundCloud</span></span>
            <span class="go" aria-hidden="true">&rarr;</span>
          </a>
          <a class="link slip" href="https://soundcloud.com/sanderkleinenberg/sander-kleinenberg-the-deepest">
            <img src="img/mixtape-deepest-68.webp" alt="" width="200" height="200" loading="lazy">
            <span class="lt"><b>The Deepest Mixtape In The Universe #68</b><span>SoundCloud</span></span>
            <span class="go" aria-hidden="true">&rarr;</span>
          </a>
          <a class="link slip" href="https://soundcloud.com/sanderkleinenberg/the-deepest-mixtape-in-the-6">
            <img src="img/set-deep67.webp" alt="" width="240" height="240" loading="lazy">
            <span class="lt"><b>The Deepest Mixtape In The Universe #67</b><span>SoundCloud</span></span>
            <span class="go" aria-hidden="true">&rarr;</span>
          </a>
        </div>
      </div>
    </div>

    <div class="disco slip">
      <div class="disco-top">
        <p class="kicker">Selected discography</p>
        <small>Years and catalogue numbers from Discogs</small>
      </div>
      <div class="scroll">
        <table>
          <thead><tr><th scope="col">Year</th><th scope="col">Record</th><th scope="col">Label</th><th scope="col">Cat. no.</th></tr></thead>
          <tbody>
            <tr><td class="y">2026</td><td class="t">Make It Rain <span>with DARMON</span></td><td class="l">Make The Girls Dance Records</td><td class="c"></td></tr>
            <tr><td class="y">2025</td><td class="t">My Lexicon <span>with Helsloot</span></td><td class="l">Armada Music</td><td class="c">ARMNWSV007</td></tr>
            <tr><td class="y">2014</td><td class="t">Can You Feel It <span>feat. Gwen McCrae</span></td><td class="l">Spinnin&rsquo; Deep</td><td class="c">SPDEEP150</td></tr>
            <tr><td class="y">2009</td><td class="t">This Is Sander Kleinenberg 2</td><td class="l">Little Mountain Recordings</td><td class="c">LMRCD0901</td></tr>
            <tr><td class="y">2007</td><td class="t">This Is Sander Kleinenberg</td><td class="l">Renaissance</td><td class="c">REN36CD</td></tr>
            <tr><td class="y">2006</td><td class="t">This Is Miami</td><td class="l">Little Mountain Recordings</td><td class="c">LMR025</td></tr>
            <tr><td class="y">2004</td><td class="t">The Fruit</td><td class="l">Little Mountain Recordings</td><td class="c">LMR011</td></tr>
            <tr><td class="y">2004</td><td class="t">This Is Everybody Too</td><td class="l">Renaissance</td><td class="c">REN16CD</td></tr>
            <tr><td class="y">2003</td><td class="t">Everybody</td><td class="l">Renaissance</td><td class="c">REN10CD</td></tr>
            <tr><td class="y">2001</td><td class="t">Global Underground Nubreed 004</td><td class="l">Boxed</td><td class="c">NU:004CD</td></tr>
            <tr><td class="y">1999</td><td class="t">My Lexicon</td><td class="l">Combined Forces</td><td class="c">CF007</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (in_array('live', $see, true)): ?>
  <!-- ================================================================== LIVE -->
  <section id="live" aria-labelledby="h-live">
    <div class="sec-head">
      <h2 class="cond" id="h-live"><span class="strip">Live</span></h2>
      <div class="aside"><span>Upcoming dates &middot; and where he&rsquo;s been</span></div>
    </div>

    <div class="two">
      <div class="dates slip">
        <ul>
          <li><a class="date" href="https://dice.fm/event/eonmqd-sander-kleinenberg-armada-music-27th-nov-do-not-sit-on-the-furniture-miami-beach-tickets">
            <img src="img/flyer-do-not-sit-miami.webp" alt="" width="200" height="200" loading="lazy">
            <span class="when">27.11<small>2026</small></span>
            <span class="where"><b>Do Not Sit On The Furniture</b><span>Miami Beach &middot; US</span></span>
            <span class="tix">Tickets</span>
          </a></li>
          <li><a class="date" href="https://events.musicofourdesire.com/nl/event/nwmk-winter-of-love-festival-2026-festival-amsterdam-netherlands">
            <img src="img/flyer-winter-of-love.webp" alt="" width="200" height="200" loading="lazy">
            <span class="when">12.12<small>2026</small></span>
            <span class="where"><b>Winter of Love &middot; Thuishaven</b><span>Amsterdam &middot; NL</span></span>
            <span class="tix">Tickets</span>
          </a></li>
          <li><div class="date">
            <img src="img/tide.webp" alt="" width="200" height="200" loading="lazy">
            <span class="when">18.12<small>2026</small></span>
            <span class="where"><b>TIDE &middot; Club Onder Hans</b><span>Amsterdam &middot; NL</span></span>
          </div></li>
          <li><div class="date">
            <img src="img/tide.webp" alt="" width="200" height="200" loading="lazy">
            <span class="when">26.02<small>2027</small></span>
            <span class="where"><b>TIDE with Konvex &middot; Club Onder Hans</b><span>Amsterdam &middot; NL</span></span>
          </div></li>
          <li><div class="date">
            <img src="img/tide.webp" alt="" width="200" height="200" loading="lazy">
            <span class="when">14.05<small>2027</small></span>
            <span class="where"><b>TIDE &middot; Paal 69</b><span>Zandvoort &middot; NL</span></span>
          </div></li>
          <li><div class="date">
            <img src="img/tide.webp" alt="" width="200" height="200" loading="lazy">
            <span class="when">16.07<small>2027</small></span>
            <span class="where"><b>TIDE with Sentin &middot; Paal 69</b><span>Zandvoort &middot; NL</span></span>
          </div></li>
          <li><a class="date" href="https://www.balancecroatia.com">
            <img src="img/flyer-balance-croatia.webp" alt="" width="200" height="200" loading="lazy">
            <span class="when">05&ndash;09.08<small>2027</small></span>
            <span class="where"><b>Balance Croatia &middot; The Garden Resort</b><span>Tisno &middot; HR</span></span>
            <span class="tix">Tickets</span>
          </a></li>
          <li><div class="date">
            <img src="img/tide.webp" alt="" width="200" height="200" loading="lazy">
            <span class="when">17.09<small>2027</small></span>
            <span class="where"><b>TIDE &middot; Paal 69</b><span>Zandvoort &middot; NL</span></span>
          </div></li>
        </ul>
      </div>

      <!-- Where he's played. Every name here is documented; check before adding more.
           Coachella, Glastonbury, Ultra: Sander's own 2026 biography.
           Sets with dates: mixesdb.com/w/Category:Sander_Kleinenberg (Ultra Miami 2004 + 2011,
           Ultra Buenos Aires 2012, Tomorrowland 2007, Sensation White 2004 + 2006, Mysteryland 2003
           (Floriade grounds, Haarlemmermeer), Exit 2004, Dance Valley 2010,
           SW4 2009, Loveland 2008 + 2009, Extrema Outdoor 2001 + 2011 (Netherlands), ADE 2006-2012,
           Zouk 2003, Cafe Mambo 2009 + 2010, Pacha NYC 2011, Renaissance at Pacha Ibiza with Sasha 2004,
           and the cities).
           Residencies: en.wikipedia.org/wiki/Sander_Kleinenberg (Crobar monthly from 2005; the This Is
           nights at Avalon and Pacha Ibiza; Lahore 2012);
           khaleejtimes.com "Spin wizard", 2008 (Ministry of Sound, Avalon; Dubai);
           electronicgroove.com 2024 (Lima). Pacha Ibiza closing party 2005: the archive's own flyer.
           Creamfields 2007 (DVJ set) + 2008, Daresbury: gigwise.com line-up pages 34688 and 40050.
           Countries everywhere on the site: ISO 3166-1 two-letter codes (UK, ISO's reserved code, not GB); under each name: the town only. -->
      <div class="stages slip">
        <p class="kicker">Festival stages</p>
        <ul class="bill">
          <li><b>Coachella<sup>(US)</sup></b><small>Indio</small></li>
          <li><b>Glastonbury<sup>(UK)</sup></b><small>Pilton</small></li>
          <li><b>Ultra<sup>(US/AR)</sup></b><small>Miami &middot; Buenos Aires</small></li>
          <li><b>Tomorrowland<sup>(BE)</sup></b><small>Boom</small></li>
        </ul>
        <ul class="bill t2">
          <li><b>Sensation White<sup>(NL)</sup></b><small>Amsterdam</small></li>
          <li><b>Creamfields<sup>(UK)</sup></b><small>Daresbury</small></li>
          <li><b>Mysteryland<sup>(NL)</sup></b><small>Haarlemmermeer</small></li>
          <li><b>Exit Festival<sup>(RS)</sup></b><small>Novi Sad</small></li>
        </ul>
        <p class="also">Dance Valley<sup>(NL)</sup>&nbsp;&middot; SW4<sup>(UK)</sup>&nbsp;&middot; Loveland<sup>(NL)</sup>&nbsp;&middot; Extrema&nbsp;Outdoor<sup>(NL)</sup>&nbsp;&middot; ADE<sup>(NL)</sup></p>
      </div>
    </div>

    <div class="rooms slip">
      <p class="kicker">Rooms he&rsquo;s played</p>
      <ul>
      <li class="lead"><b>Pacha<sup>(ES)</sup></b><span>Ibiza</span><small>Renaissance with Sasha, and his own Everybody and This Is nights</small></li>
      <li><b>Ministry of Sound<sup>(UK)</sup></b><span>London</span><small>Residency</small></li>
      <li><b>Crobar<sup>(US)</sup></b><span>New York</span><small>Monthly residency from 2005</small></li>
      <li><b>Avalon<sup>(US)</sup></b><span>Los Angeles</span><small>This Is residency</small></li>
      <li><b>Zouk<sup>(SG)</sup></b><span>Singapore</span><small>2003</small></li>
      <li><b>Pacha<sup>(US)</sup></b><span>New York</span><small>This Is, 2011</small></li>
      <li><b>Caf&eacute; Mambo<sup>(ES)</sup></b><span>Ibiza</span><small>2009 and 2010</small></li>
      </ul>
      <p class="cities"><span class="kicker">And on the way</span>Reykjav&iacute;k&nbsp;<i>/</i> Istanbul&nbsp;<i>/</i> Beirut&nbsp;<i>/</i> Dubai&nbsp;<i>/</i> Lahore&nbsp;<i>/</i> Lima&nbsp;<i>/</i> Buenos&nbsp;Aires&nbsp;<i>/</i> Sydney&nbsp;<i>/</i> Bucharest&nbsp;<i>/</i> Riga&nbsp;<i>/</i> Toronto&nbsp;<i>/</i> Montreal</p>
      <p class="quip">&hellip;and a long list of clubs, afterparties, airports and questionable hotel breakfasts.</p>
    </div>
  </section>
<?php endif; ?>

<?php if (in_array('rider', $see, true)): ?>
  <!-- ================================================================= RIDER -->
  <section id="rider" aria-labelledby="h-rider">
    <div class="sec-head">
      <h2 class="cond" id="h-rider"><span class="strip">Rider</span></h2>
      <div class="aside"><span>Technical &amp; hospitality &middot; 2026</span><a class="mini" href="get.php?f=Sander-Kleinenberg-rider-2026.pdf">PDF &darr;</a></div>
    </div>

    <div class="rig slip">
      <ul class="spec">
        <li>3 &times; CDJ-3000<small>Pioneer &middot; linked, hubbed</small></li>
        <li>1 &times; DJM-V10<small>Pioneer &middot; V9 also accepted</small></li>
        <li>2 &times; monitors<small>Ear level &middot; within 2 m &middot; 100 dB+</small></li>
        <li>Table &ge; 1.00 m<small>Mounted apart from the dance floor</small></li>
      </ul>
      <div class="setup">
        <figure><img src="img/gear-cdj3000.webp" alt="" width="520" height="716" loading="lazy"><figcaption>Player 1<small>CDJ-3000</small></figcaption></figure>
        <figure><img src="img/gear-djm-v10.webp" alt="" width="680" height="727" loading="lazy"><figcaption>Mixer<small>DJM-V10</small></figcaption></figure>
        <figure><img src="img/gear-cdj3000.webp" alt="" width="520" height="716" loading="lazy"><figcaption>Player 2<small>CDJ-3000</small></figcaption></figure>
        <figure><img src="img/gear-cdj3000.webp" alt="" width="520" height="716" loading="lazy"><figcaption>Player 3<small>CDJ-3000</small></figcaption></figure>
      </div>
    </div>

    <div class="rider">
      <div class="rcol">
        <div class="rlist slip">
          <h3>Technical</h3>
          <ul>
            <li>3 &times; Pioneer CDJ-3000 players, linked</li>
            <li>1 &times; Pioneer DJM-V10 mixer (V9 also accepted)</li>
            <li>The CDJs placed next to the mixer and hubbed together</li>
            <li>2 &times; high-power monitor speakers pointing at the DJ <b>at ear level</b>, within two metres, with their own volume control and preferably powered separately. They must reach <b>100 dB or more, EQ flat</b>.</li>
            <li>The monitors must be controlled from the <b>booth output</b> on the mixer</li>
            <li>1 &times; Shure microphone connected to the DJ mixer</li>
            <li>1 &times; fully qualified sound engineer on the premises at all times</li>
            <li>2 &times; free EU power sockets next to the mixer</li>
            <li>1 &times; free rec out on the mixer</li>
            <li>The DJ deck mounted independently from the dance floor</li>
            <li>DJ table at least 1.00 m high</li>
            <li>A cooling fan next to the booth</li>
          </ul>
        </div>
      </div>

      <div class="rcol">
        <div class="rlist slip">
          <h3>DJ booth</h3>
          <ul>
            <li>The booth must be in a prime location and not pushed to one side of the stage</li>
            <li>Decks draped at the front and sides to hide the wiring</li>
            <li>Good lighting on the booth</li>
            <li>The stage area stable and structurally safe</li>
            <li>Nobody is admitted to the booth or the stage without the tour manager&rsquo;s approval</li>
            <li>All cables to, from and around the booth taped down securely, so that nothing can be tripped over and nothing can interrupt power or audio</li>
          </ul>
        </div>
        <div class="rlist slip">
          <h3>Catering on the night</h3>
          <ul>
            <li>1 bottle of vodka &mdash; Absolut, Grey Goose or Ketel One *</li>
            <li>4 bottles of still water</li>
            <li>Sodas and juices</li>
            <li>Extra beverage tokens if no further drinks are available on stage</li>
            <li>2 clean towels</li>
            <li>A dressing room or relax area if the set is long and has breaks for other acts</li>
            <li>A good meal, when the performance or the sound check falls around dinner time</li>
            <li>6 guestlist spots for the event *</li>
            <li>2 extra backstage / all-area passes</li>
            <li>1 parking spot near the venue</li>
          </ul>
          <p class="fn">* Does not apply to business events.</p>
        </div>
      </div>

      <div class="rcol">
        <div class="rlist slip">
          <h3>Hotel and meals</h3>
          <p class="pill">Only for bookings outside the Netherlands</p>
          <ul>
            <li>All flights booked and paid by the organiser: <b>2 tickets with priority boarding</b>, for the artist and the tour manager. A direct flight is required.</li>
            <li>The organiser pays for and provides a <b>4-star hotel, one single room</b></li>
            <li>Free WiFi at the hotel</li>
            <li>At least one breakfast and one dinner on each day of the stay, or a per diem of <b>&euro;50 per person per day</b></li>
            <li>Transport from the airport to the hotel, hotel to venue, venue to hotel and hotel to airport</li>
          </ul>
        </div>
        <div class="rlist slip">
          <h3>Transport</h3>
          <p class="pill">Only for bookings outside the Netherlands</p>
          <ul>
            <li>All ground transport by car is carried out by a professional driver company, approved and booked by the purchaser</li>
            <li>The driver is fully licensed and under no condition under the influence of drugs or alcohol</li>
            <li>The driver does not use a phone unless hands-free, and never texts while driving</li>
            <li>On long journeys pillows are provided, and the car model is reviewed and confirmed beforehand</li>
            <li>Nobody other than the artists, the purchaser and the driver travels in the car</li>
            <li>The driver is available to the artists at all times</li>
            <li>Pick-up times and the driver&rsquo;s telephone number for every journey are included in the full itinerary and call sheet</li>
          </ul>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (in_array('contact', $see, true)): ?>
  <!-- =============================================================== CONTACT -->
  <section id="contact" aria-labelledby="h-contact">
    <div class="sec-head">
      <h2 class="cond" id="h-contact"><span class="strip">Contact</span></h2>
    </div>

    <div class="contact slip">
      <div>
        <p class="kicker">Bookings &middot; Independent Artist Group</p>
        <div class="mail">
          <a href="mailto:achaykin@independentartistgroup.com" id="addr-book">achaykin@<wbr>independentartistgroup.com</a>
          <button class="copy" type="button" data-copy="addr-book" id="c-book">Copy</button>
        </div>
      </div>
      <ul class="prof">
        <li><a href="https://www.instagram.com/sanderkleinenberg">Instagram<span>@sanderkleinenberg</span></a></li>
        <li><a href="https://soundcloud.com/sanderkleinenberg">SoundCloud<span>/sanderkleinenberg</span></a></li>
        <li><a href="https://open.spotify.com/artist/3CfH3WZPzbk5mNDWXpGIy6">Spotify<span>Artist profile</span></a></li>
        <li><a href="https://music.apple.com/us/artist/sander-kleinenberg/4091314">Apple Music<span>Artist profile</span></a></li>
        <li><a href="https://www.youtube.com/@sanderkleinenbergtv">YouTube<span>@sanderkleinenbergtv</span></a></li>
        <li><a href="https://www.sanderkleinenberg.com">Website<span>sanderkleinenberg.com</span></a></li>
      </ul>
    </div>
  </section>
<?php endif; ?>

</main>

<footer class="foot wrap">
  <p class="note">very loud, in the right order</p>
  <div class="swatches" id="swatches" role="group" aria-label="Ground"></div>
  <p class="small">Sander Kleinenberg &middot; Press kit 2026 &middot; updated November 2026<br>sanderkleinenberg.com</p>
</footer>

<script>
(function(){
  "use strict";

  /* the grounds the archive already ships */
  var GROUNDS = [
    { id:"",       name:"Paper",  sw:"#f1f1ec" },
    { id:"tide",   name:"Tide",   sw:"#8fcdf7" },
    { id:"olivia", name:"Olivia", sw:"#e49fb2" },
    { id:"bibi",   name:"Bibi",   sw:"#40e0d0" }
  ];

  var box = document.getElementById("swatches");
  GROUNDS.forEach(function(g){
    var b = document.createElement("button");
    b.type = "button";
    b.className = "gs";
    b.style.background = g.sw;
    b.title = g.name;
    b.setAttribute("aria-label", g.name);
    b.setAttribute("aria-pressed", g.id === "" ? "true" : "false");
    b.addEventListener("click", function(){
      if(g.id) document.documentElement.setAttribute("data-ground", g.id);
      else document.documentElement.removeAttribute("data-ground");
      var all = box.querySelectorAll(".gs");
      for(var i=0;i<all.length;i++) all[i].setAttribute("aria-pressed","false");
      b.setAttribute("aria-pressed","true");
    });
    box.appendChild(b);
  });

  /* Plain text of a block: paragraphs from textContent, never innerText, which
     would hand back the first bio paragraph in the capitals it is styled in. */
  function plain(el){
    var ps = el.querySelectorAll("p");
    if(!ps.length) return el.textContent.trim();
    var out = [];
    for(var i=0;i<ps.length;i++) out.push(ps[i].textContent.replace(/\s+/g," ").trim());
    return out.join("\n\n");
  }

  /* word counts are counted, not typed, so they stay right when the bio changes */
  var counts = document.querySelectorAll("[data-words]");
  for(var i=0;i<counts.length;i++){
    var src = document.getElementById(counts[i].getAttribute("data-words"));
    if(src) counts[i].textContent = "· " + plain(src).split(/\s+/).length + " words";
  }

  var buttons = document.querySelectorAll("[data-copy]");
  for(var j=0;j<buttons.length;j++){
    buttons[j].addEventListener("click", function(){
      var btn = this;
      var el = document.getElementById(btn.getAttribute("data-copy"));
      var text = plain(el);
      function done(){
        btn.textContent = "Copied";
        btn.classList.add("ok");
        setTimeout(function(){ btn.textContent = "Copy"; btn.classList.remove("ok"); }, 1600);
      }
      /* where the clipboard is refused, select the text so a keyboard copy works */
      function fallback(){
        var r = document.createRange();
        r.selectNodeContents(el);
        var s = window.getSelection();
        s.removeAllRanges();
        s.addRange(r);
        btn.textContent = "Selected";
        setTimeout(function(){ btn.textContent = "Copy"; }, 2400);
      }
      try{ navigator.clipboard.writeText(text).then(done, fallback); }
      catch(e){ fallback(); }
    });
  }
})();
</script>
