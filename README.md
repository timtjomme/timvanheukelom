# timvanheukelom.nl — local copy

An offline copy of https://www.timvanheukelom.nl ("Tim van Heukelom – Tim op Reis"),
captured 2026-08-25/26 and rebuilt from the original WordPress + Beaver Builder
output into a conventional static-site layout.

## Run it

```bash
./serve.sh
```

Site at http://localhost:8765, visit stats at http://localhost:8765/dashboard

`serve.sh` runs `tracker/server.py`, which serves the static files *and*
collects the analytics below. Plain `python3 -m http.server` still works if
you only want the site.

## Structure

```
index.html                     homepage
travelblog.html
timtjomme.html
waar-zijn-we-geweest.html
polarsteps.html                added page: Polarsteps journey embed
landen/<slug>.html             14 travel stories
voorbereidingen/<slug>.html    2 pre-trip posts
assets/
  css/   31 files              bootstrap, fontawesome, animate, magnific-popup,
                               theme-skin, theme-child, one <page>.css per page,
                               modules[-n].css, open-sans-<weights>.css
  js/    37 files              jquery, bootstrap, theme, waypoints, imagesloaded,
                               magnific-popup, masonry/mosaicflow/wookmark,
                               three + cardboard (360 viewer), <page>.js, modules.js
  fonts/  6 files              Font Awesome woff2, Open Sans woff2
  img/  759 photos             one size per photo
favicon.ico  serve.sh  README.md  tools/
```

21 pages, 759 photos, ~129 MB. Page URLs are `/landen/nobus.html` style.

Nothing is named after WordPress any more: the per-page stylesheets and
scripts were `layout-<post-id>.css/js` (`layout-2079.css`) and the Beaver
Builder bundles were content hashes (`modules-b6f8a276.css`). They are now
named for the page they belong to (`nobus.css`, `nobus.js`) and numbered by
use (`modules.css` on 16 pages, then `modules-2`, `modules-3`).

Note the page **markup** is still Beaver Builder's — `fl-row`, `fl-module`,
`uabb-*` and the `fl-node-<hash>` ids. That cannot be renamed away: every
stylesheet targets those class names, so the layout depends on them.

## Image policy

The story pages reference 4085 image URLs, but that is only **755 distinct
photos** — WordPress emits ~5.6 `srcset` thumbnails per photo. This copy keeps
**one variant per photo** (the largest at or below 1200px) and strips `srcset`
and `sizes` so the browser uses it directly. Full `srcset` fidelity would have
cost ~308 MB for the same visible result at normal viewport sizes.

360° panoramas are the exception: they are kept at full resolution
(2560x1280), because the viewer maps them onto a sphere and downscaling is
visible.

## What was trimmed

Relative to a raw mirror, with no change to the rendered pages:

- Font Awesome eot/ttf/svg/woff (woff2 only)
- 7 unused Open Sans subsets, keeping latin, latin-ext and vietnamese
  (the last matters — "Hà Nội", "Chùa Bái Đính")
- `block-library.css` (Gutenberg; these pages are Beaver Builder)
- `dashicons` (WP admin icon font)
- `snazzymaps.js` (no map on any page), emoji polyfill
- Dead WordPress `<head>` entries: RSS/comments feeds, oEmbed endpoints,
  REST API links, RSD/xmlrpc, generator meta, shortlink.
  `rel="canonical"` is deliberately kept — it marks the live site as the original.

`three.js` + `cardboard.js` are kept, but linked **only from the 6 pages that
actually embed a panorama**, so the other 15 pages do not pay the 440 KB.

## Fidelity

- **2364 local references across 21 pages resolve — 0 missing. 0 server 404s.**
- The homepage still hashes to `b251d5b7` — the identical rendered fingerprint
  (position, size, font, weight, colour, background) measured before the story
  pages were added, across all 330 rendered elements.
- `landen/nobus/` renders 15 of 15 panorama viewers as live WebGL canvases;
  page width matches the live site exactly (1281px).
- Counters, parallax hero, hover banners and scroll animations all work.

## Needs an internet connection

Everything is local **except** third-party embeds, which cannot be mirrored:

- **Vimeo videos** on 6 story pages — click-to-play; the poster thumbnails also
  come from `i.vimeocdn.com`
- **Polarsteps map** on `/polarsteps/`
- Outbound links in post text (Instagram, Facebook, etc.)

Navigation between the 21 mirrored pages is fully local. Links to pages that
were not mirrored — notably the 34 `/cardboard/<id>` full-screen panorama pages
— still point at the live site; the inline 360° viewers work offline regardless.

## Rebuilding

```bash
python3 tools/build_site.py <output-dir>   # crawl the live site + build
python3 tools/flatten.py   <output-dir>    # flat pages, assets/, drop post-id names
python3 tools/make_page.py <output-dir> polarsteps "Title" \
        tools/pages/polarsteps.html polarsteps.css
```

`build_site.py` mirrors WordPress's own shape (`landen/<slug>/index.html`,
`layout-<id>.css`); `flatten.py` converts that into the layout above.

`make_page.py` derives its shell from the current `index.html`, so generated
pages always pick up the current asset filenames instead of going stale.

Note: the host throttles sustained crawling to roughly 8 requests/minute, so a
full image fetch takes about 90 minutes. `build_site.py` caches to
`/tmp/tvh-cache` and skips files already downloaded, so it is resumable.

## Local-copy fix

`css/theme-child.css` carries one added rule (`.cardboard { overflow:hidden }`).
Served locally the panorama images decode far faster than over the network, so a
viewer inside a collapsed container could be initialised at full width and spill
out of the page. The rule restores the live site's layout and is a no-op for
correctly sized viewers.


## Self-hosted visit tracker

No third party, no account, no external requests: the same process that serves
the pages records the visits, into a local SQLite file.

```
tracker/server.py       serves the site + POST /api/collect + GET /api/stats
tracker/dashboard.html  the dashboard at /dashboard
assets/js/tracker.js    3.5 KB client, loaded with defer on all 21 pages
tracker/analytics.db    SQLite (gitignored)
tracker/.salt           per-install secret (gitignored, chmod 600)
```

### What it records

| | |
|---|---|
| behaviour | pageviews, time on page, scroll depth, link clicks, entry/exit, sessions |
| location  | the browser's IANA timezone (`Europe/Amsterdam`) and language |
| time      | timestamp, day, hour-of-day |
| device    | viewport size, bucketed to mobile / tablet / desktop |

Time on page counts only the time the tab was actually *visible* — switching
tabs pauses it, so a forgotten tab does not inflate the numbers. Exit events
go out via `sendBeacon`, which survives the page closing.

### Privacy design

These choices are deliberate. They are what makes the tracker lawful in the EU
without a cookie banner, so please do not "improve" it by storing more:

- **no cookies, no localStorage** — nothing is persisted on the visitor's
  device. The session id lives in `sessionStorage`, so it dies with the tab.
- **no raw IP addresses are ever written to disk.** A visitor is
  `sha256(ip + user-agent + salt + date)`, truncated. It rotates at midnight,
  so the same person is a new id tomorrow and cannot be followed across days.
- **coarse location only** — timezone and language. The Geolocation API is
  never called and there is no IP-geolocation lookup.
- **`Do Not Track` and `Global Privacy Control` are honoured** — those
  visitors are not recorded at all.

Because no personal data is stored and nothing is read from the visitor's
device, this needs no consent banner under GDPR/ePrivacy. That stops being
true if you add IP logging, cross-day identifiers, or third-party scripts.

### Hosting

The tracker needs a process running — **it cannot work on GitHub Pages**,
which only serves static files. On a real host (Plesk, a VPS) run
`tracker/server.py` behind nginx/Apache, or port the two endpoints to
whatever backend the host offers; the client script and schema stay the same.
Put the dashboard behind auth before exposing it publicly — it is
`noindex,nofollow` but it is not access-controlled.
