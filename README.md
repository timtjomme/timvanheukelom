# timvanheukelom.nl — local copy

An offline copy of https://www.timvanheukelom.nl ("Tim van Heukelom – Tim op Reis"),
captured 2026-08-25/26 and rebuilt from the original WordPress + Beaver Builder
output into a conventional static-site layout.

## Run it

```bash
./serve.sh
```

Site at http://localhost:8765, visit stats at
http://localhost:8765/analytics/dashboard.php

`serve.sh` runs `php -S`, so the tracker below works locally exactly as it
does on the live host. `python3 -m http.server` still serves the site if you
only want the pages.

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

Same design as the teddyrileyproductions site: plain PHP, no database, no
third-party analytics service. Beacons go to our own `analytics/track.php`,
which appends one JSON line per event to `visits.log`.

```
analytics/track.php          receives beacons, appends to visits.log
analytics/dashboard.php      password-protected dashboard
analytics/.htaccess          denies direct access to *.log, *.json, config.php
analytics/config.example.php password template
analytics/visits.log         the data          (gitignored)
analytics/geo-cache.json     ip-hash -> country/city cache (gitignored)
analytics/config.php         the password      (gitignored)
assets/js/tracker.js         the beacon snippet, on all 21 pages
```

### What it records

| | |
|---|---|
| behaviour | pageviews, time on page, scroll depth, and named interactions: `story_open`, `panorama_view`, `video_play`, `nav_icon` |
| location  | country and city, from a coarse IP lookup; browser timezone and language as fallback |
| time      | ISO timestamp per event, plus per-day and per-session totals |
| device    | viewport, bucketed to mobile / tablet / desktop |

The dashboard also shows **per-session journeys** — the pages one visitor moved
through in order, with what they clicked and how long they stayed.

Unlike the TRP site, pages here live at two depths (`index.html`,
`landen/nobus.html`), so the snippet derives its endpoint from its own script
URL rather than a fixed relative path. That also survives being served from a
subdirectory.

### Privacy

- no cookies; the session id lives in `sessionStorage`, so it dies with the tab
  and never follows a visitor across days
- **raw IP addresses are never written to disk.** The geo lookup is keyed by
  `sha256(ip)` and only the country/city result is cached
- `Do Not Track` and `Global Privacy Control` are honoured — those visitors are
  not recorded at all
- the one outbound call is the coarse IP -> country/city lookup at
  `ip-api.com`. That is a third party seeing visitor IPs, so if you would
  rather keep everything in-house, delete the `geolocate()` call in
  `track.php` and the dashboard falls back to browser timezone.

### First run

`config.php` is gitignored, so a freshly deployed copy has no password. The
first visit to `analytics/dashboard.php` lets you set one in the browser; it is
written on the server and never passes through the repo. Alternatively copy
`config.example.php` to `config.php` by hand.

**This needs PHP**, so it works on the Plesk host but not on GitHub Pages.

## Comments

The 122 historical comments stay baked into the pages by the mirror. New ones
are handled by a small PHP backend, in the same style as the tracker — no
database, JSON lines on disk, nothing third-party.

```
comments/post.php            accepts a new comment, stores it unapproved
comments/list.php            returns approved comments for one post
comments/admin.php           moderation, password-protected
comments/.htaccess           denies data/, config.php and the salt
comments/data/<slug>.jsonl   the comments        (gitignored)
comments/config.php          the password        (gitignored)
assets/css/comments.css      thread + form styling
assets/js/comments.js        polish, loads new comments, drives the form
```

**Nothing appears on the site until it is approved** in `comments/admin.php`,
which uses the same first-run password flow as the analytics dashboard.

The pages stay static: `comments.js` fetches newly approved comments from
`list.php` and appends them to the thread, so no page needs to be regenerated
or rendered through PHP.

### Why the form had to be replaced

The mirrored form still pointed at
`https://www.timvanheukelom.nl/wp-comments-post.php`. Submitting it from this
copy would have filed a comment on the live WordPress site. It now posts to
`comments/post.php`.

### Spam handling

No captcha, no third-party service:

- a honeypot field that is off-screen rather than `display:none`
- a minimum time-on-page before a submission is accepted
- max 3 comments per hour per IP hash
- comments with more than two links are rejected
- length limits, and everything is escaped on output

### Data collected

Name and comment only — no email, no URL, no cookies. The IP is used for rate
limiting and stored only as a salted hash, so there is no personal data to
protect beyond the name someone chooses to type.

## Deploying (replacing the WordPress site)

The static build takes over `timvanheukelom.nl` from the WordPress install.
Plesk at `https://shared195.cloud86-host.io:8443/` → Websites & domeinen →
timvanheukelom.nl → Git, then **Nu pull uitvoeren** followed by **Nu
publiceren** (publication is Manual, so a `git push` alone does not deploy).

Three things have to be right or the site breaks on switchover:

**1. Old URLs.** Every published link points at a directory
(`/landen/nobus/`); the static build is files (`/landen/nobus.html`). The
root `.htaccess` 301-redirects the old shapes to the new ones. Without it
every existing link, bookmark and search result 404s.

**2. WordPress has to actually go.** Publishing does not delete what is
already in the document root. If WordPress's `index.php` and its `.htaccess`
survive, they win — WP's rewrite sends every request to its front controller
and the static pages never render. Remove or move aside `index.php`,
`wp-*.php`, `wp-admin/`, `wp-includes/`, `wp-content/` and the old
`.htaccess` before or immediately after the first publish.

Take a full backup of the WordPress install and its database first. This
copy is a snapshot from 25 Aug 2026, and once WordPress is gone the posts,
comments and media only exist here.

**3. Apache vs nginx.** All the protection for `comments/data/`,
`analytics/visits.log` and both `config.php` files is `.htaccess`, which
nginx ignores. On nginx, move those directories outside the web root and
serve the equivalents in the vhost config instead.

After the first publish:

- visit `analytics/dashboard.php` once to set the stats password
- visit `comments/admin.php` once to set the moderation password
- make `comments/` and `analytics/` writable so they can create `data/`,
  `visits.log`, and their salts
