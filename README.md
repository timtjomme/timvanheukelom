# timvanheukelom.nl

A plain static site: 20 hand-editable HTML pages, one stylesheet, four small
scripts and the photos. No framework, no build step, no CMS.

It started life as a WordPress + Beaver Builder site; that export was rewritten
into ordinary HTML on 28 Aug 2026 (see "Where this came from" below).

## Run it

```bash
./serve.sh
```

Site at http://localhost:8765, visit stats at
http://localhost:8765/analytics/dashboard.php

`serve.sh` runs `php -S`, so the comment backend and the tracker work locally
exactly as they do on the live host. `python3 -m http.server` also serves the
pages if you only want to look at them.

## Structure

```
index.html                     home: hero with the three counter rings, 16 story cards
travelblog.html                the same page under its own published URL
waar-zijn-we-geweest.html      the route: the travelmap.net map and the
                               Polarsteps one, on the same page
timtjomme.html                 link out to Instagram
landen/<slug>.html             14 travel stories
voorbereidingen/<slug>.html    2 pre-trip posts
assets/
  css/site.css                 the whole stylesheet, ~17 KB
  js/site.js                   counters, click-to-play video, lightbox
  js/panorama.js               the 360° viewer
  js/comments.js               loads and posts comments
  js/tracker.js                the visit beacon
  fonts/open-sans/             three woff2 subsets
  img/                         753 photos
analytics/  comments/          the two small PHP backends
.htaccess  favicon.ico  serve.sh  tools/
```

Every page is one file you can open and edit. The header, footer and comment
form are written out in each page rather than pulled from a template — that is
the trade for having no build step, and it is 20 lines of markup.

## How a page is put together

```html
<header class="topbar">…</header>          the bar: title left, three icons right
                                            (brand text is the home link)

<section class="hero" style="background-image:url('…')">
  <div class="hero-inner"><p class="hero-kicker">…</p><h1>…</h1></div>
</section>                                 full-bleed photo, title over it.
                                            background-attachment:fixed gives
                                            the parallax scroll (the same
                                            technique live uses); the home
                                            page also puts the three counter
                                            rings in .hero-inner

<article class="story">
  <div class="prose"><p>…</p></div>        text, 1020px wide, centred
  <div class="gallery"><a …><img …></a></div>
  <figure class="photo"><img …></figure>
  <div class="video" data-vimeo="199519738">…</div>
  <div class="panorama" data-image="…"></div>
</article>

<nav class="pnav">…</nav>                  previous / all / next story
<section class="comments">…</section>
<footer class="footer">…</footer>
```

Adding a story means copying an existing one in `landen/`, changing the hero
and the blocks, and adding a `<a class="card">` to `index.html` and
`travelblog.html`. Nothing else knows about it.

## The scripts

All four are plain ES5-ish JavaScript with no dependencies, and all four are
enhancements — the pages read correctly with every one of them blocked.

| file | ~size | what it does |
|---|---|---|
| `site.js` | 4 KB | draws the three counter rings while their numbers count up, swaps a video poster for the player on click, opens galleries in a lightbox |
| `panorama.js` | 7 KB | the 360° viewer: an equirectangular sphere in raw WebGL, built only when a viewer scrolls near the viewport |
| `comments.js` | 6 KB | draws the avatars and relative dates, fetches newly approved comments, submits the form |
| `tracker.js` | 4 KB | the visit beacon (see below) |

`panorama.js` replaces three.js + OrbitControls + cardboard.js. One page carries
fifteen viewers, so each one is only given a WebGL context once it comes within
400px of the viewport; without a context it falls back to the flat photo.

## Matching the old site

The rewrite keeps the old site's design, measured off it rather than guessed:
70px hero titles on the single-line pages and 60px (40px on a phone) over a
story, 36px subtitles under a 3px white rule at 85% width, 20px/300 body copy
in a 1020px column, galleries two square columns wide with no gutter, 250px
counter rings in three equal columns, and cards whose photo is untouched until
you point at it and then goes to `rgba(10,10,10,.8)`.

Two things are deliberately *not* copied, because they were faults rather than
choices, and both were reported before this rewrite: the bar was missing on
phones entirely, and on the story pages it rendered 110px tall with cramped
left-aligned icons because those pages' CSS bundle never included the header
module's own rules. Every page now carries the same 36px bar.

## Image policy

The stories reference 753 photos, one size each (the largest at or below
1200px). There is no `srcset`: full WordPress srcset fidelity would have cost
~308 MB for the same visible result at normal viewport sizes.

360° panoramas are the exception — they stay at 2560x1280, because the viewer
maps them onto a sphere and downscaling is visible.

## Needs an internet connection

Everything is local **except** third-party embeds, which cannot be mirrored:

- **Vimeo videos** on 6 story pages — click-to-play; the poster thumbnails also
  come from `i.vimeocdn.com`
- **both route maps** on `/waar-zijn-we-geweest.html` — travelmap.net and
  Polarsteps. Note travelmap.net serves a bot-block page to unusual user
  agents; in a normal browser it frames fine
- outbound links in post text (Instagram, Facebook, etc.)

Nothing else leaves the server. There is no Google Fonts, no CDN, no analytics
service, no gravatar.com.

## Where this came from

The site was WordPress with the Beaver Builder page builder. It was mirrored to
static files on 25/26 Aug 2026 and then rewritten on 28 Aug 2026, because the
mirror carried the whole builder with it:

| | before | after |
|---|---|---|
| stylesheets | 31 files, 2.7 MB | 1 file, 17 KB |
| scripts | 37 files, 2.0 MB | 4 files, 21 KB |
| fonts | Open Sans + Font Awesome, 264 KB | Open Sans, 92 KB |
| home page | 45 KB of markup | 7 KB |
| markup | `fl-row`, `fl-col`, `uabb-infobox`, `fl-node-5b50940b8247c` | `header`, `article`, `figure`, `.gallery` |

Dropped along the way: jQuery and jQuery Migrate, Bootstrap, Font Awesome,
animate.css, magnific-popup, masonry / mosaicflow / wookmark, waypoints,
imagesloaded, three.js, the Beaver Builder theme and module CSS, one 100–200 KB
stylesheet and one 40–100 KB script per page, and the WordPress `<head>`
(Akismet, speculation rules, emoji polyfill, oEmbed, Gutenberg block styles).

Kept: every word, every photo, all 122 comments, both PHP backends, and the
`/landen/<slug>.html` URLs, so nothing that was ever linked has moved.

The one page that did move is `/polarsteps`, which was folded into
`/waar-zijn-we-geweest.html` (both were a map of the same trip). It was a
published URL, so `.htaccess` 301s `/polarsteps`, `/polarsteps/` and
`/polarsteps.html` to the combined page rather than letting them 404.

`tools/` holds the scripts that crawled the WordPress site (`build_site.py`)
and flattened the mirror (`flatten.py`, `make_page.py`). They cannot rebuild
the site in its current shape — they are kept only as the record of where the
content came from while the WordPress install still exists.

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
assets/js/tracker.js         the beacon snippet, on all 20 pages
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

The 122 historical comments are plain HTML in the pages. New ones are handled
by a small PHP backend, in the same style as the tracker — no database, JSON
lines on disk, nothing third-party.

```
comments/post.php            accepts a new comment, stores it unapproved
comments/list.php            returns approved comments for one post
comments/admin.php           moderation, password-protected
comments/.htaccess           denies data/, config.php and the salt
comments/data/<slug>.jsonl   the comments        (gitignored)
comments/config.php          the password        (gitignored)
assets/js/comments.js        avatars, dates, loads new comments, drives the form
```

**Nothing appears on the site until it is approved** in `comments/admin.php`,
which uses the same first-run password flow as the analytics dashboard.

The pages stay static: `comments.js` fetches newly approved comments from
`list.php` and appends them to the thread, so no page needs to be regenerated
or rendered through PHP. Each page names its own thread with
`<body data-post="landen-nobus">`.

The form is ordinary markup in each story page, so it is visible with
JavaScript off — it simply cannot submit. (The mirrored WordPress form used to
point at `wp-comments-post.php` on the live site; that is gone.)

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
