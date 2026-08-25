# timvanheukelom.nl — local copy

An offline copy of the homepage of https://www.timvanheukelom.nl
("Tim van Heukelom – Tim op Reis"), captured 2026-08-25, rebuilt into a
conventional static-site layout.

## Run it

```bash
./serve.sh
```

Then open http://localhost:8765

## Structure

```
index.html
favicon.ico
css/
  bootstrap.css      theme-skin.css    layout.css     open-sans.css
  fontawesome.css    theme-child.css   modules.css    animate.css
  magnific-popup.css
js/
  jquery.js          bootstrap.js      layout.js      waypoints.js
  jquery-migrate.js  theme.js          modules.js     imagesloaded.js
  magnific-popup.js  throttle-debounce.js
fonts/
  fontawesome/       fa-solid-900.woff2, fa-brands-400.woff2
  open-sans/         latin + latin-ext woff2
img/                 19 photos + favicons
tools/               mirror.py, restructure.py (see below)
```

`layout.css` / `layout.js` are this page's generated Beaver Builder layout;
`modules.css` / `modules.js` are the BB module bundle.

## Weight

**8.7 MB → 4.9 MB (44% smaller)**, with the rendered page unchanged.

| removed                              | saved   | why it was safe |
|--------------------------------------|---------|-----------------|
| Font Awesome eot/ttf/svg/woff        | 2.5 MB  | modern browsers load only woff2 |
| `fa-regular-400.*` (all formats)     | (above) | page uses only `.fas` + `.fab` |
| `three.min.js` + orbit controls + cardboard.js | 440 KB | VR plugin, no VR module on this page |
| Open Sans: 8 unused subsets          | 350 KB  | Dutch text needs latin + latin-ext only |
| `block-library/style.min.css`        | 120 KB  | Gutenberg styles; page is Beaver Builder |
| `dashicons.min.css` + `dashicons.ttf`| 120 KB  | WP admin icon font, unused on the front end |
| `snazzymaps.js`                      | 8 KB    | no map on this page |
| `wp-emoji-release.min.js` + loader   | 24 KB   | emoji polyfill for legacy browsers |

Also dropped: the glyphicon `@font-face` in Bootstrap (those files 404 on the
live server too and nothing uses them), and 28 of 30 Open Sans `@font-face`
rules, so no dangling references remain.

**The 19 photos (4.0 MB) were left untouched.** They are already tightly
encoded — re-encoding them at the same dimensions makes them *bigger*
(WebP q82: +12%, JPEG q80: +30%), and at a 1280px viewport the banners render
at 640 CSS px, i.e. 1280 device px on a retina screen, so 1500x1500 is honest
sizing rather than waste. Any further image saving is a visible-quality trade:
WebP q75 would cut about 500 KB.

## Fidelity

Verified against the untrimmed mirror at a matched 1280x800 viewport, both
scrolled through and left to settle:

- 330 rendered elements on each, **identical layout/style fingerprint**
  (hash `b251d5b7`) covering position, size, font, weight, colour and
  background image
- identical `scrollHeight` (5956px) and identical page text
- 16 images, 0 broken; 0 requests leave the machine; 0 404s
- all 3 counter animations fire and land on 87.501 / 250 / 12

## Rebuilding

`tools/mirror.py` re-downloads the live homepage into a raw mirror that
preserves the original WordPress URL paths. `tools/restructure.py` converts
such a mirror into this layout and applies the trimming above:

```bash
python3 tools/mirror.py ./raw-mirror
python3 tools/restructure.py ./raw-mirror ./site
```

## Known, deliberate deviations

- **Navigation links still point at the live site.** This is a copy of the one
  homepage; the ~17 linked posts (`/landen/...`, `/timtjomme/`, `/travelblog/`)
  were not mirrored.
- A `//# sourceURL=` comment in an inline script still names an original URL.
  It is a devtools marker only.
- `dns-prefetch` / `preconnect` hints for fonts.googleapis.com remain in the
  HTML. They fetch nothing offline.
