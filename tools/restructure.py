#!/usr/bin/env python3
"""Rebuild the mirror into a conventional website layout, dropping dead assets."""
import os, re, shutil, sys

SRC = sys.argv[1]
DST = sys.argv[2]
A   = os.path.join(SRC, "assets", "www.timvanheukelom.nl")
G   = os.path.join(SRC, "assets", "fonts.googleapis.com")

CSS_MAP = {
    "wp-content/uploads/bb-plugin/cache/1914-layout.css":                        "css/layout.css",
    "wp-content/uploads/bb-plugin/cache/b6f8a27688e0ca26b6ff9b378e05f814-layout-bundle.css": "css/modules.css",
    "wp-content/plugins/bb-plugin/fonts/fontawesome/5.15.4/css/all.min.css":     "css/fontawesome.css",
    "wp-content/plugins/bb-plugin/css/jquery.magnificpopup.min.css":             "css/magnific-popup.css",
    "wp-content/themes/bb-theme/css/bootstrap.min.css":                          "css/bootstrap.css",
    "wp-content/uploads/bb-theme/skin-5e8f39f56676d.css":                        "css/theme-skin.css",
    "wp-content/themes/bb-theme-child/style.css":                                "css/theme-child.css",
    "wp-content/plugins/bbpowerpack/assets/css/animate.min.css":                 "css/animate.css",
}
CSS_DROP = [
    "wp-includes/css/dist/block-library/style.min.css",   # Gutenberg blocks - page is Beaver Builder
    "wp-includes/css/dashicons.min.css",                  # WP admin icon font
]
JS_MAP = {
    "wp-includes/js/jquery/jquery.min.js":                                   "js/jquery.js",
    "wp-includes/js/jquery/jquery-migrate.min.js":                           "js/jquery-migrate.js",
    "wp-content/plugins/bb-plugin/js/jquery.waypoints.min.js":               "js/waypoints.js",
    "wp-content/plugins/bb-plugin/js/jquery.imagesloaded.min.js":            "js/imagesloaded.js",
    "wp-content/plugins/bb-plugin/js/jquery.ba-throttle-debounce.min.js":    "js/throttle-debounce.js",
    "wp-content/plugins/bb-plugin/js/jquery.magnificpopup.min.js":           "js/magnific-popup.js",
    "wp-content/uploads/bb-plugin/cache/1914-layout.js":                     "js/layout.js",
    "wp-content/uploads/bb-plugin/cache/9e2380fc060c8c6d2a645dfd1b5c6250-layout-bundle.js": "js/modules.js",
    "wp-content/themes/bb-theme/js/bootstrap.min.js":                        "js/bootstrap.js",
    "wp-content/themes/bb-theme/js/theme.min.js":                            "js/theme.js",
}
JS_DROP = [
    "wp-content/plugins/cardboard/three/three.min.js",              # 424 KB VR lib, no VR module here
    "wp-content/plugins/cardboard/three/three-orbit-controls.min.js",
    "wp-content/plugins/cardboard/js/cardboard.js",
    "wp-content/plugins/snazzy-maps/snazzymaps.js",                 # no map on this page
    "wp-includes/js/wp-emoji-release.min.js",                       # emoji polyfill for legacy browsers
]
FA = "wp-content/plugins/bb-plugin/fonts/fontawesome/5.15.4/webfonts"
FA_KEEP = ["fa-solid-900.woff2", "fa-brands-400.woff2"]
OS_KEEP_SUBSETS = ("latin", "latin-ext")

def rd(p):  return open(p, encoding="utf-8", errors="replace").read()
def wr(p,s):
    os.makedirs(os.path.dirname(p), exist_ok=True)
    open(p, "w", encoding="utf-8").write(s)

os.makedirs(DST, exist_ok=True)
for d in ("css","js","img","fonts/fontawesome","fonts/open-sans"):
    os.makedirs(os.path.join(DST,d), exist_ok=True)

# ---------------------------------------------------------------- images
img_names = {}
for root,_,files in os.walk(os.path.join(A,"wp-content/uploads")):
    for f in files:
        if f.lower().endswith((".jpg",".jpeg",".png",".gif")):
            src=os.path.join(root,f)
            name=f
            if name.startswith("cropped-Fav-icon"):
                name = "favicon-" + name.split("-")[-1]
            shutil.copy2(src, os.path.join(DST,"img",name))
            img_names[f]=name
print(f"images: {len(img_names)}")

# ---------------------------------------------------------------- fonts
for f in FA_KEEP:
    shutil.copy2(os.path.join(A,FA,f), os.path.join(DST,"fonts/fontawesome",f))

os_css = rd(os.path.join(G,"css-c6b5b2fa.css"))
kept_os_files=[]
out_blocks=[]
# blocks look like:  /* subset */\n@font-face {...}
for m in re.finditer(r'/\*\s*([a-z-]+)\s*\*/\s*(@font-face\s*\{[^}]*\})', os_css, re.I):
    subset, block = m.group(1), m.group(2)
    if subset not in OS_KEEP_SUBSETS:
        continue
    u = re.search(r'url\("?([^")]+\.woff2)"?\)', block)
    fn = os.path.basename(u.group(1))
    srcf = os.path.join(SRC,"assets","fonts.gstatic.com","s","opensans","v44",fn)
    if os.path.exists(srcf) and fn not in kept_os_files:
        shutil.copy2(srcf, os.path.join(DST,"fonts/open-sans",fn))
        kept_os_files.append(fn)
    block = re.sub(r'url\("?[^")]+\.woff2"?\)', f'url("../fonts/open-sans/{fn}")', block)
    out_blocks.append(f"/* {subset} */\n{block}")
wr(os.path.join(DST,"css/open-sans.css"), "\n".join(out_blocks)+"\n")
print(f"open-sans: kept {len(kept_os_files)} of 10 subset files, {len(out_blocks)} of 30 @font-face rules")

# ---------------------------------------------------------------- css
FONTFACE = re.compile(r'@font-face\s*\{[^}]*\}', re.I)

def fix_fontawesome(css):
    def repl(m):
        blk = m.group(0)
        w2 = re.findall(r'([\w-]+\.woff2)', blk)
        if not w2:
            return blk
        name = w2[0]
        if name not in FA_KEEP:              # fa-regular-400: unused on this page
            return ""
        # FA5 ships TWO src: declarations per face (eot hack + full stack);
        # drop them all and keep a single woff2 source.
        inner = blk[blk.index("{") + 1: blk.rindex("}")]
        decls = [d for d in inner.split(";")
                 if d.strip() and not d.strip().lower().startswith("src:")]
        decls.append(f'src:url("../fonts/fontawesome/{name}") format("woff2")')
        return "@font-face{" + ";".join(decls) + "}"
    return FONTFACE.sub(repl, css)

def fix_bootstrap(css):
    # glyphicon webfonts 404 upstream and nothing on the page uses them
    return FONTFACE.sub(lambda m: "" if "glyphicons" in m.group(0) else m.group(0), css)

def fix_urls(css):
    def repl(m):
        raw = m.group(1).strip('\'"')
        if raw.startswith("data:"):
            return m.group(0)
        base = os.path.basename(raw.split("?")[0].split("#")[0])
        if base in img_names:
            return f'url("../img/{img_names[base]}")'
        return m.group(0)
    return re.sub(r'url\(([^)]+)\)', repl, css)

for old,new in CSS_MAP.items():
    css = rd(os.path.join(A,old))
    if "fontawesome" in new: css = fix_fontawesome(css)
    if "bootstrap"   in new: css = fix_bootstrap(css)
    css = fix_urls(css)
    wr(os.path.join(DST,new), css)

# ---------------------------------------------------------------- js
for old,new in JS_MAP.items():
    shutil.copy2(os.path.join(A,old), os.path.join(DST,new))

# ---------------------------------------------------------------- html
html = rd(os.path.join(SRC,"index.html"))
P = "assets/www.timvanheukelom.nl/"

# remove tags for dropped assets
for old in CSS_DROP:
    html = re.sub(r"[ \t]*<link[^>]*href='"+re.escape(P+old)+r"'[^>]*/?>\n?", "", html)
for old in JS_DROP:
    html = re.sub(r'[ \t]*<script[^>]*src="'+re.escape(P+old)+r'"[^>]*>\s*</script>\n?', "", html)
# emoji settings JSON + its module loader
html = re.sub(r'<script id="wp-emoji-settings"[^>]*>.*?</script>\s*', "", html, flags=re.S)
html = re.sub(r'<script type="module">\s*/\* <!\[CDATA\[ \*/.*?wp-emoji-loader\.min\.js\s*/\* \]\]> \*/\s*</script>\s*',
              "", html, flags=re.S)
html = re.sub(r"<style id='wp-emoji-styles-inline-css'[^>]*>.*?</style>\s*", "", html, flags=re.S)
# preload hints -> new font paths
html = html.replace(P+FA+"/", "fonts/fontawesome/")
# rewrite mapped assets
for old,new in list(CSS_MAP.items())+list(JS_MAP.items()):
    html = html.replace(P+old, new)
html = html.replace("assets/fonts.googleapis.com/css-c6b5b2fa.css", "css/open-sans.css")
# images
for orig,new in img_names.items():
    html = re.sub(r'assets/www\.timvanheukelom\.nl/wp-content/uploads/[0-9]{4}/[0-9]{2}/'
                  + re.escape(orig), "img/"+new, html)
wr(os.path.join(DST,"index.html"), html)

left = re.findall(r'assets/www\.timvanheukelom\.nl[^"\')\s]*', html)
print("unrewritten refs left in html:", len(left), sorted(set(left))[:5])
