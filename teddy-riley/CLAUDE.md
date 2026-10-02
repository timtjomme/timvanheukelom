# TR Productions — archive site

A static discography site. No framework, no build step for the CSS/JS — plain
HTML, one shared stylesheet, one shared script. The only generated thing is the
release grids, which come from `data/releases.json`.

Content is sourced from the original teddyrileyproductions.com (a WordPress site).
This is a redesign of it, not a copy of its code.

**Where it lives now:** this folder is `teddy-riley/` inside the timvanheukelom.nl repo (the
`timvanheukelom-local` project, one level up) and is served at https://timvanheukelom.nl/teddy-riley/ .
It moved there from teddyrileyproductions.com on 2026-09-25. Every link and asset is
relative, so the folder works at any depth; only the absolute URLs (canonical, Open
Graph, JSON-LD, sitemap) and the footer credit name the new address. Crawl rules live
in the repo-root `../robots.txt`, not in this folder.

## Layout

```
index.html                       front page: video hero, intro, decade panels
1980s.html                       decade page: 1980s releases
1990s.html                       decade page: 1990s releases
njs-productions.html             "NJS Productions": straight hip-hop cuts and other one-offs
new-jack-swing-productions.html  redirect stub: the old address of the page above (keeps ?query and #hash)
sampled.html                     tracks that sample a Teddy Riley record
missing.html                     known mixes/edits not yet in the lossless collection
timeline.html                    life-story chronology, 1967 to now
contact.html                     Formspree contact form
privacy-policy.html              boilerplate privacy policy
404.html                         not-found page, styled as a missing release
style.css                        every page
script.js                        card flip, contact form, footer prompt, nav, back to top
data/releases.json               THE SOURCE OF TRUTH for releases
data/updates.json                source for the homepage's dismissible update banner
tools/build.py                   releases.json -> the grids in the decade pages
tools/extract.py                 one-off, already run: pages -> releases.json
imgs/                            covers (one per release), heroes, decade panels, story/ and 404/ art
fonts/                           Open Sans variable, self-hosted
robots.txt                       (not in this folder: the crawl rules and sitemap pointer live in ../robots.txt)
sitemap.xml                      hand-maintained list of the 10 indexable pages (sampled.html is noindex'd and nav-hidden while its list is still thin)
```

## Adding or changing a release

**Edit `data/releases.json`, then run `python3 tools/build.py`.** Never hand-edit
a `<div class="card">` — the next build overwrites it.

```json
{
  "year": "1988",
  "artist": "Guy",
  "title": "Groove Me",
  "label": "MCA Records",
  "image": "1988-guy-groove-me.jpg",
  "tracks": ["Groove Me (Radio Edit)", "Groove Me (Bonus Beats)"],
  "missing": ["Groove Me (Bonus Beats)"]
}
```

- `missing` is optional; list the exact track strings that are missing from the
  collection. They render red and give that card a legend. It comes from the
  red `<mark>` spans on the source site.
- `format` is optional and usually unnecessary — see below.
- `note` is optional: a sentence or two of pressing-specific context (deleted
  tracks, alternate mixes, promo-only status) that doesn't fit the tracklist
  itself. Renders as a small italic line on the card back, between the
  tracklist and the flip-back button. Used for e.g. distinguishing a promo
  cassette from its retail release.
- `image` is a filename inside `imgs/`. Download the cover, name it
  `<year>-<artist>-<title>` kebab-cased, and drop it there.
- No cover available? Use `"image": "placeholder-sleeve.svg"` — a neutral
  sleeve reading NO COVER YET. Swap it for the real file when one turns up.
- `hidden` is optional: `"hidden": true` pulls a release out of the built
  grid entirely (and out of `build.py`'s counts) without deleting it from
  the JSON — for something pending a rewrite, a correction, or a "coming
  soon" year. If it empties a year, remove that year's `<a href="#yXXXX">`
  from the page's hand-written year-jump nav yourself (build.py won't) —
  turn it into a `<span title="coming soon">` the way the nav dropdown
  marks an unpublished decade.
- Order matters: releases appear in JSON order, grouped by year, and years
  appear in first-seen order. Keep each year's block together.
- Write plain text — `&` and `″` are escaped by the build script. Don't put
  `&amp;` or `&Prime;` in the JSON.

`build.py` rewrites only the region between the `RELEASES:START` / `RELEASES:END`
markers. Hero, intro copy, nav and footer are hand-written; edit those directly.

### LP or Single

Each sleeve carries its format top-left, where the year used to sit — the year
is already the section heading above the grid, so the card repeated it.

Nothing in the JSON says LP or Single, so **a numbered tracklist is the tell**:
`"01. Her"`, `"05 — Sleaze"`, `"3. Can We Try Again"` means the entry is an
album; a list of versions of one song (`"Groove Me (Radio Edit)"`) means it's a
single. That is how the source site writes them, so pasted tracklists classify
themselves. `NUMBERED` in `build.py` is the rule, mirrored by `releaseFormat()`
in `script.js` for the card the 404 page builds at runtime — change one, change
both.

Where the tracklist can't tell — one unnumbered track lifted off an album, a
soundtrack — add `"format": "LP"` (or `"Single"`) to that release and it wins.
Two entries need it today: Big Daddy Kane's *It's A Big Daddy Thing* and the
*Do The Right Thing* soundtrack. `build.py` prints the LP/single/EP split per
page, which is the quickest way to spot a new release landing on the wrong side.

`format` isn't actually limited to LP or Single — `fmt()` prints whatever
string is there verbatim, so it doubles as a free-text override for the rare
sleeve that says something else. Teddy Riley's *Nickel Bag Of Swing* EP is the
one case of this today.

The mark itself is the same frosted paper plate as `.flip-hint` in the opposite
corner, minus the border — the two corners read as a pair. A third of these
sleeves are near-white and a third near-black, so no single ink survives both
on its own; the plate is what carries the caps, and on a pale sleeve it all but
disappears.

### A new year

Just add releases with that year. The build wraps it in a `<details>` — the
year heading is the `<summary>`, closed by default, so the page opens short
and a visitor expands the year they want. The **year-jump nav is hand-written**
in the page — add the link yourself; `build.py` prints a warning when it
looks out of date.

The anchor id (`id="y1991"`) lives on the `.grid` inside the `<details>`, not
on the summary or the details element itself. That's deliberate: a browser
only auto-opens a closed `<details>` when the URL fragment's target is one of
its *hidden* descendants. Put the id on the summary instead and a jump-to-year
link would scroll there but leave the section collapsed.

All the years sit inside one `.timeline` wrapper (also from `build.py`), which
draws the connecting rail down the left side — one line for the whole list,
not a segment per year, so it never needs to be re-measured as sections open
and close; it's just the wrapper's own content height. Below 560px it steps
aside entirely (`.timeline{ padding-left: 0 }`, rail hidden) rather than
taking 30px away from `.grid`'s `minmax(300px, 1fr)`, which is already tight
against `.wrap`'s padding on a phone.

### A new decade page

Copy `1990s.html`, swap the hero image and `--hero-pos`, add a `"2000s"` key to
`releases.json`, add the page to `PAGES` in `extract.py` and to the nav on every
page, un-grey its panel in `index.html`, add `"2000s"` to `DECADE_PAGES` in
`build.py`, and give the new page an SEO block (see below) plus an entry in
`sitemap.xml`.

## SEO

Every page carries a hand-written block right after the `apple-touch-icon`
link: a meta description, `<meta name="robots" content="index, follow">`,
a self-referential `<link rel="canonical">`, Open Graph + Twitter Card tags,
and one `<script type="application/ld+json">` holding a `WebSite` object plus
a page-specific `BreadcrumbList` (the homepage also gets a `Person` object for
Teddy Riley). None of this is generated by `build.py` — these pages have no
`<head>` tag to splice against, so it's plain hand-maintained markup like the
hero and nav.

Canonical/OG/Twitter URLs are absolute against `https://timvanheukelom.nl/teddy-riley/` —
the real deploy target (see Deploying), not a placeholder. Before the move they named
`https://teddyrileyproductions.com`.

`404.html` is the one page marked `noindex, follow` — everything else is
`index, follow`. A new page needs adding to `sitemap.xml` by hand; there's no
build step that keeps it in sync with the pages on disk.

## Annual: bump the copyright year

The footer year is hardcoded, deliberately — a JS-generated year disappears
when JavaScript is off, and a copyright notice that vanishes is worse than one
a year stale. Every January, update it in all four pages:

```bash
grep -rln '© 2026 timvanheukelom.nl' *.html
sed -i '' 's/© 2026 timvanheukelom.nl/© 2027 timvanheukelom.nl/' *.html
```

The footer is hand-written markup repeated in index.html, 1980s.html,
1990s.html and contact.html — build.py does not touch it.

## Things that are easy to get wrong

- **Anchors are `y`-prefixed** (`id="y1982"`). Ids starting with a digit can't be
  targeted by a plain CSS selector.
- **`--accent-soft` is for the light page only.** On the dark card back use
  `--ink-soft`; `--accent-soft` fails contrast there.
- **`body` needs `background-color`, not just the gradient.** Without it the
  canvas is unpainted below the fold and renders black.
- **`background-attachment: fixed` sizes to the viewport, not the element.** On a
  short header that leaves no overflow, so `--hero-pos` does nothing — the
  compact contact hero uses `scroll` for that reason.
- **Hero images are framed with `--hero-pos`** (set inline per page). These
  portraits sit high, so a centred crop cuts the face off.
- The source page occasionally **merges two tracks onto one line** where a `<br>`
  is missing. Check tracklists against the source when they look short.
- **Public pages don't ask for files.** No upload or drop-off links (the Dropbox file
  request is gone from every page and from the floating widget), and no copy inviting
  lossless files or photos; corrections and missing credits come in through the contact
  form only. A new page's footer panel is just the `.ask-line`.
- **The section is called "NJS Productions".** Its page is `njs-productions.html` and its
  data key in `releases.json` is `njs-productions` (the build and the search both turn the
  key into the file name, so they have to match). Release data is the exception to the
  naming: credits such as "Remix: New Jack Swing Productions" stay exactly as printed on
  the records. The menu link and the update banner say "NJS"; the genre name in running text is unchanged.

## Favicon

Three files at the repo root, all derived from `imgs/logo.png`:

- `favicon.png` — the logo **inverted to dark**, for light browser tabs
- `favicon-dark.png` — the original white, served via
  `media="(prefers-color-scheme: dark)"`
- `apple-touch-icon.png` — white logo on solid `#121214`, since iOS
  composites these opaque and would otherwise flatten the transparency

The source logo is white line art on transparency, so a single favicon would be
invisible on one theme or the other — hence the pair. Regenerate with ffmpeg by
squaring the tall logo first (`force_original_aspect_ratio=decrease` then pad),
then `negate` for the dark variant.

## Contact form

Both forms (contact page, and the footer prompt built by `script.js`) POST to
Formspree `moeawjre`. The endpoint lives in `FORM_ENDPOINT` in `script.js` and in
the `action` on `contact.html`. No email address appears in the markup. Forms work
without JS as a plain POST; JS only upgrades them to submit in place.

## The YEP YEP widget

A floating prompt bottom-left, built by `script.js` (`buildYepWidget`) rather
than repeated in each page's markup. It posts to the same Formspree endpoint
through the shared `wireContactForm`, so there is one submit path for all three
forms on the site.

It is skipped on contact.html, which is already the form — the guard looks for a
`.contact-form` that is not inside `.ask` or `.yep`.

The avatar is `imgs/yep-avatar.jpg`, cropped from `imgs/story/ins08.jpg`, the
photo where he is holding the YEP YUP licence plate frame.

## Back to top

`initToTop()` in `script.js` injects one control on every page — not markup
repeated five times. Fixed bottom-right, opacity 0.5 at rest, full on
hover/focus, hidden entirely until you've scrolled past 80% of a viewport
height. A page short enough to never cross that threshold just never shows it,
which is why it needs no per-page opt-out.

Sits bottom-right specifically so it never collides with the YEP YEP widget
(bottom-left) or the footer prompt.

## Content protection

`user-select:none` on `body` (style.css) plus a `contextmenu` blocker and an
`img`/`video` `dragstart` blocker (script.js, top of the file) — site-wide,
every page.

This is a deterrent, not real protection. View-source, devtools, and a
screenshot all still get past every part of it; nothing client-side can stop
someone who actually wants the file. It only removes the casual, one-click
paths (drag-save an image, right-click > Save/Copy, select-and-copy text),
which is what most casual lifting actually is.

Deliberately not here: blocking keyboard shortcuts (Ctrl+C, Ctrl+U) or trying
to detect/block devtools. Both are trivially bypassed — the browser's own
menu still has View Page Source — and mainly succeed at breaking things for
the site owner too the next time something needs debugging.

Form fields are exempted from both rules (`input, textarea` in the CSS; an
`e.target` check in the JS) — a visitor has to be able to select, copy, and
right-click-paste into the contact form for it to work at all.

## 404

`404.html` at the repo root. Netlify, Cloudflare Pages and GitHub Pages all
serve that filename automatically for unmatched routes — no config needed. On
Apache you would need `ErrorDocument 404 /404.html` in `.htaccess`.

The joke reuses the site's own machinery: the page presents itself as a release
that is missing from the collection, with every version rendered in the red
`li.missing` style, pressed on Lil' Man Records. If the missing-marker colour or
convention changes, this page follows it automatically.

`initLostPage()` in `script.js` makes it react to the visitor:

- echoes the URL they actually asked for
- runs a two-phase sequence: it first digs through the nine labels in large
  type — Lil' Man Records, Funky Mamma, G.R. Productions, New Jack Swing,
  Future Records, LOR Records, Sound Of New York, Rooftop Records, QDT — and
  only when that finishes does the "Never pressed." block fade in. Under
  `prefers-reduced-motion` it skips straight to the answer. Add a label by
  editing `LABELS` in `initLostPage`.
- reads `data/releases.json`, so a year in the URL — `/1987-anything` — offers a
  link straight to `1980s.html#y1987` with the real release count
- renders one genuine release picked at random from the archive, using
  `wireCard()` so it flips like any other

All release text is set with `textContent`, never `innerHTML` — the data must not
be parsed as markup.

Testing it needs a server that actually serves 404.html on a miss;
`python3 -m http.server` returns its own error page instead.

## Deploying

This folder ships as part of the timvanheukelom.nl repo (its parent), so a deploy is
that repo's deploy: commit and push it, then in Plesk
(`https://shared195.cloud86-host.io:8443/`) → Websites & domeinen → timvanheukelom.nl →
Git → **Nu pull uitvoeren**, then **Nu publiceren**. Publication is Manual, so a push
alone does not deploy, and publishing adds files without deleting what is already there.

- The repo root is the web root, so `data/`, `tools/` and this file are served too.
  `data/releases.json` has to stay public: the search and the 404 page fetch it.
- Both GitHub repos are **public**, this one's history included (this file used to say
  private, which was wrong), so everything committed is world-readable.
- `analytics/config.php` is gitignored. On a fresh deploy, copy `analytics/config.example.php`
  to `analytics/config.php` on the server and set the dashboard password. The visit log
  starts empty here; the old one stays on the teddyrileyproductions.com host.
- teddyrileyproductions.com is a separate Plesk site on the same server. What it does
  after the move (redirect, park, take down) is decided separately; nothing in this
  folder redirects it.

## Checking your work

```bash
python3 tools/build.py                  # regenerate the grids
python3 -m http.server 8934             # then open http://localhost:8934/
```

Serve over HTTP, not `file://` — the fonts and the hero video break otherwise.

## Local preview

From the repo root run `./serve.sh` (PHP's built-in server) and open
http://localhost:8765/teddy-riley/ — or, for just this folder, run `python3 -m http.server 8934`
here and open http://localhost:8934/. The analytics beacon needs PHP; without it the pages
work and the beacon just fails quietly.

(The always-on mirror on localhost:1000 serves the *original* folder in
`~/Documents/Teddy Riley Productions new website`, not this copy.)
