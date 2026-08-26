#!/usr/bin/env python3
"""Full offline copy of timvanheukelom.nl: homepage + 19 story pages.
Keeps ONE image variant per photo (<=1200px) and strips srcset."""
import os, re, sys, html, shutil, urllib.request
from urllib.parse import urljoin, urlsplit, unquote, quote
from concurrent.futures import ThreadPoolExecutor

BASE, OUT = "https://www.timvanheukelom.nl", sys.argv[1]
CACHE = "/tmp/tvh-cache"
TARGET_W = 1200
UA = {"User-Agent":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
                   "(KHTML, like Gecko) Chrome/125.0 Safari/537.36"}
IMG_EXT = (".jpg",".jpeg",".png",".gif",".webp")

PAGES = {"/":"index.html", "/timtjomme/":"timtjomme/index.html",
         "/travelblog/":"travelblog/index.html",
         "/waar-zijn-we-geweest/":"waar-zijn-we-geweest/index.html",
         "/voorbereidingen/micheltim/":"voorbereidingen/micheltim/index.html",
         "/voorbereidingen/laatstedagennederland/":"voorbereidingen/laatstedagennederland/index.html"}
for s in ["3hoofdsteden","3landen","3maanden","8paspoortstempels","953kmlater","bam","enwezijnbruin",
          "hanoiallemaal","naarhelan","nobus","opzoek","vannoordnaarzuid","vanverlaten","vietnamcambodja"]:
    PAGES[f"/landen/{s}/"] = f"landen/{s}/index.html"

def log(*a): print(*a, flush=True)

# ------------------------------------------------------------------ fetch
def cache_path(url):
    s = urlsplit(url); p = (s.netloc + unquote(s.path)).replace("//","/")
    if p.endswith("/"): p += "index"
    if s.query: p += "__" + re.sub(r'[^A-Za-z0-9]+','_',s.query)[:40]
    return os.path.join(CACHE, p.replace(":","_"))

def _ascii_url(u):
    # WordPress serves some uploads under raw UTF-8 paths (e.g. Chùa-Bai-Đính-1.jpg);
    # urllib needs the path percent-encoded.
    sp = urlsplit(u)
    return sp._replace(path=quote(sp.path, safe="/%")).geturl()

def fetch(url):
    url = _ascii_url(url)
    cp = cache_path(url)
    if os.path.exists(cp) and os.path.getsize(cp) > 0: return open(cp,"rb").read()
    for attempt in range(3):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=90) as r:
                d = r.read()
            os.makedirs(os.path.dirname(cp), exist_ok=True)
            open(cp,"wb").write(d)
            return d
        except Exception:
            if attempt == 2: return None

# ------------------------------------------------------------------ pass 1
log("== pages ==")
def getpage(p):
    d = fetch(BASE+p); return p, (d.decode("utf-8","replace") if d else None)
with ThreadPoolExecutor(max_workers=8) as ex: results = list(ex.map(getpage, PAGES))
docs = {p:d for p,d in results if d}
log(f"   {len(docs)}/{len(PAGES)} pages")
for p,d in results:
    if not d: log("   FAIL", p)

ATTR_RE   = re.compile(r'(?:href|src|data-src|data-parallax-image|data-image)\s*=\s*(["\'])(.*?)\1', re.S)
SRCSET_RE = re.compile(r'(?:srcset|data-srcset)\s*=\s*(["\'])(.*?)\1', re.S)
CSSURL_RE = re.compile(r'url\(\s*([\'"]?)([^\'")]+)\1\s*\)', re.I)

css_urls, js_urls, img_urls = set(), set(), set()
for p, doc in docs.items():
    page = BASE+p
    def add(u):
        u = html.unescape(u).strip()
        if not u or u.startswith(("data:","#","javascript:","mailto:")): return
        a = urljoin(page,u).split("#")[0]
        if urlsplit(a).scheme not in ("http","https"): return
        lp = urlsplit(a).path.lower()
        if lp.endswith(".css") or (urlsplit(a).netloc=="fonts.googleapis.com"
                                   and urlsplit(a).path.strip("/")): css_urls.add(a)
        elif lp.endswith(".js"): js_urls.add(a)
        elif lp.endswith(IMG_EXT): img_urls.add(a)
    for m in ATTR_RE.finditer(doc): add(m.group(2))
    for m in SRCSET_RE.finditer(doc):
        for c in m.group(2).split(","):
            b=c.strip().split()
            if b: add(b[0])
    for m in CSSURL_RE.finditer(doc): add(m.group(2))
log(f"   css={len(css_urls)} js={len(js_urls)} imageURLs={len(img_urls)}")

# ------------------------------------------------------------------ images
def strip_v(u): return re.sub(r'-\d+x\d+(?=\.[A-Za-z]+$)','',u)
def vw(u):
    m=re.search(r'-(\d+)x\d+(?=\.[A-Za-z]+$)',u); return int(m.group(1)) if m else 10**6
groups={}
for u in img_urls: groups.setdefault(strip_v(u),[]).append(u)
chosen={}
for b,vs in groups.items():
    under=[v for v in vs if vw(v)<=TARGET_W]
    chosen[b]= max(under,key=vw) if under else min(vs,key=vw)
names, used = {}, {}
for b in groups:
    n=re.sub(r'[^A-Za-z0-9._-]+','-', os.path.basename(unquote(urlsplit(b).path)))
    if n in used and used[n]!=b:
        st,ex_=os.path.splitext(n); n=f"{st}-{len(used)}{ex_}"
    used[n]=b; names[b]=n
url2img={v:names[b] for b,vs in groups.items() for v in vs}
log(f"   distinct photos={len(groups)}")

log("== downloading images ==")
os.makedirs(os.path.join(OUT,"img"), exist_ok=True)
done=[0]
def grab(b):
    dst=os.path.join(OUT,"img",names[b])
    if os.path.exists(dst) and os.path.getsize(dst)>0:
        done[0]+=1; return True
    d=fetch(chosen[b])
    if d: open(dst,"wb").write(d)
    done[0]+=1
    if done[0]%50==0: log(f"   {done[0]}/{len(groups)}")
    return bool(d)
with ThreadPoolExecutor(max_workers=10) as ex: ok=list(ex.map(grab, list(groups)))
log(f"   images ok {sum(ok)}/{len(groups)}  ({sum(os.path.getsize(os.path.join(OUT,'img',f)) for f in os.listdir(os.path.join(OUT,'img')))/1048576:.0f} MB)")

# ================================================================== phase 2
log("== assets ==")
FA_KEEP = ["fa-solid-900.woff2","fa-brands-400.woff2","fa-regular-400.woff2"]
OS_SUBSETS = ("latin","latin-ext","vietnamese")

def asset_name(url, kind):
    s=urlsplit(url); p=s.path; stem,_=os.path.splitext(os.path.basename(unquote(p)))
    if s.netloc=="fonts.googleapis.com":
        q=unquote(s.query); m=re.search(r'family=([^&]+)',q)
        ws=re.findall(r'\d{3}', m.group(1)) if m else []
        return "open-sans-"+("-".join(ws) if ws else "default")+".css"
    if kind=="css":
        if "block-library" in p or "dashicons" in p: return None
        if "/fontawesome/" in p and stem.startswith("all"): return "fontawesome.css"
        if stem.startswith("jquery.magnificpopup"): return "magnific-popup.css"
        if stem.startswith("bootstrap"): return "bootstrap.css"
        if stem.startswith("skin-"): return "theme-skin.css"
        if "bb-theme-child" in p: return "theme-child.css"
        if stem.startswith("animate"): return "animate.css"
        m=re.match(r'^(\d+)-layout$',stem)
        if m: return f"layout-{m.group(1)}.css"
        m=re.match(r'^([0-9a-f]{8})[0-9a-f]*-layout-bundle$',stem)
        if m: return f"modules-{m.group(1)}.css"
        return re.sub(r'\.min$','',stem)+".css"
    if any(stem.startswith(d) for d in ("snazzymaps","wp-emoji-release")):
        return None
    # 360 viewer: kept, but only linked from pages that actually embed a panorama
    if stem.startswith("three-orbit-controls"): return "three-orbit-controls.js"
    if stem.startswith("three.min"):            return "three.js"
    if stem.startswith("cardboard"):            return "cardboard.js"
    for pre,out in (("jquery-migrate","jquery-migrate.js"),("jquery.min","jquery.js"),
                    ("jquery.waypoints","waypoints.js"),("jquery.imagesloaded","imagesloaded.js"),
                    ("jquery.ba-throttle-debounce","throttle-debounce.js"),
                    ("jquery.magnificpopup","magnific-popup.js"),
                    ("bootstrap","bootstrap.js"),("theme.min","theme.js")):
        if stem.startswith(pre): return out
    m=re.match(r'^(\d+)-layout$',stem)
    if m: return f"layout-{m.group(1)}.js"
    m=re.match(r'^([0-9a-f]{8})[0-9a-f]*-layout-bundle$',stem)
    if m: return f"modules-{m.group(1)}.js"
    return re.sub(r'\.min$','',stem)+".js"

css_map={u:asset_name(u,"css") for u in css_urls}
js_map ={u:asset_name(u,"js")  for u in js_urls}
for d in ("css","js","fonts/fontawesome","fonts/open-sans"):
    os.makedirs(os.path.join(OUT,d),exist_ok=True)

def img_for(abs_url):
    if abs_url in url2img: return url2img[abs_url]
    b=strip_v(abs_url)
    return names.get(b)

FONTFACE=re.compile(r'@font-face\s*\{[^}]*\}',re.I)
def proc_css(text, url, outname):
    if outname=="fontawesome.css":
        def f(m):
            blk=m.group(0); w2=re.findall(r'([\w-]+\.woff2)',blk)
            if not w2: return blk
            n=w2[0]
            if n not in FA_KEEP: return ""
            inner=blk[blk.index("{")+1:blk.rindex("}")]
            dec=[d for d in inner.split(";") if d.strip() and not d.strip().lower().startswith("src:")]
            dec.append(f'src:url("../fonts/fontawesome/{n}") format("woff2")')
            return "@font-face{"+";".join(dec)+"}"
        text=FONTFACE.sub(f,text)
        for n in FA_KEEP:
            src=fetch(urljoin(url,"../webfonts/"+n))
            if src: open(os.path.join(OUT,"fonts/fontawesome",n),"wb").write(src)
    if outname=="bootstrap.css":
        text=FONTFACE.sub(lambda m:"" if "glyphicons" in m.group(0) else m.group(0),text)
    if outname.startswith("open-sans"):
        out=[]
        for m in re.finditer(r'/\*\s*([a-z-]+)\s*\*/\s*(@font-face\s*\{[^}]*\})',text,re.I):
            sub,blk=m.group(1),m.group(2)
            if sub not in OS_SUBSETS: continue
            fu=re.search(r'url\("?([^")]+\.woff2)"?\)',blk).group(1)
            fn=os.path.basename(fu)
            d=fetch(urljoin(url,fu))
            if d: open(os.path.join(OUT,"fonts/open-sans",fn),"wb").write(d)
            out.append(f"/* {sub} */\n"+re.sub(r'url\("?[^")]+\.woff2"?\)',
                                               f'url("../fonts/open-sans/{fn}")',blk))
        return "\n".join(out)+"\n"
    def urepl(m):
        raw=m.group(2).strip()
        if raw.startswith("data:"): return m.group(0)
        a=urljoin(url,raw)
        if a.lower().split("?")[0].endswith(IMG_EXT):
            n=img_for(a.split("?")[0])
            if n: return f'url("../img/{n}")'
        return m.group(0)
    return CSSURL_RE.sub(urepl,text)

# warm the cache in parallel first: the host throttles sustained serial requests
_want=[u for u,n in list(css_map.items())+list(js_map.items()) if n]
with ThreadPoolExecutor(max_workers=10) as ex: list(ex.map(fetch,_want))
log(f"   prefetched {len(_want)} css/js")

gf_urls=[u for u in css_urls if urlsplit(u).netloc=="fonts.googleapis.com"]
for u,n in css_map.items():
    if not n or u in gf_urls: continue
    d=fetch(u)
    if not d: log("   CSS FAIL",u); continue
    open(os.path.join(OUT,"css",n),"w",encoding="utf-8").write(
        proc_css(d.decode("utf-8","replace"),u,n))

# Google Fonts: keep one file per requested weight set, so a page gets exactly
# the weights it asked for upstream (one asks 300/400/700, another adds 600).
for u in sorted(gf_urls):
    n=css_map.get(u)
    if not n: continue
    d=fetch(u)
    if not d: log("   GF FAIL",u); continue
    open(os.path.join(OUT,"css",n),"w",encoding="utf-8").write(
        proc_css(d.decode("utf-8","replace"),u,n))
log(f"   google-fonts: {len(gf_urls)} requests -> {len({css_map[u] for u in gf_urls if css_map.get(u)})} files")
for u,n in js_map.items():
    if not n: continue
    d=fetch(u)
    if d: open(os.path.join(OUT,"js",n),"wb").write(d)
log(f"   css={sum(1 for v in css_map.values() if v)} js={sum(1 for v in js_map.values() if v)}")

# ------------------------------------------------------------------ html
log("== pages out ==")
CARDBOARD_JS = {"three.js","three-orbit-controls.js","cardboard.js"}
WP_CRUFT=[r'[ \t]*<link rel="alternate" type="application/rss\+xml"[^>]*/>\n?',
          r'[ \t]*<link rel="alternate" title="oEmbed \([^)]*\)"[^>]*/>\n?',
          r'[ \t]*<link rel="https://api\.w\.org/"[^>]*/>(?:<link rel="alternate" title="JSON"[^>]*/>)?(?:<link rel="EditURI"[^>]*/>)?\n?',
          r'[ \t]*<meta name="generator" content="WordPress[^"]*"[^>]*/>\n?',
          r"[ \t]*<link rel='shortlink'[^>]*/>\n?"]

def build_page(path, doc, outrel):
    outdir=os.path.dirname(outrel) or "."
    rel=lambda t: os.path.relpath(t, outdir).replace(os.sep,"/") if outdir!="." else t
    page=BASE+path
    has_cb = 'class="cardboard"' in doc
    drop_urls, repl = [], {}
    def handle(raw):
        u=html.unescape(raw).strip()
        if not u or u.startswith(("data:","#","javascript:","mailto:")): return
        a=urljoin(page,u).split("#")[0]
        if urlsplit(a).scheme not in ("http","https"): return
        lp=urlsplit(a).path.lower()
        if a in css_map or (urlsplit(a).netloc=="fonts.googleapis.com" and a in css_map):
            n=css_map[a]; (repl.__setitem__(raw,rel("css/"+n)) if n else drop_urls.append(raw))
        elif a in js_map:
            n=js_map[a]
            if n and n in CARDBOARD_JS and not has_cb: drop_urls.append(raw)
            elif n: repl[raw]=rel("js/"+n)
            else: drop_urls.append(raw)
        elif lp.endswith(IMG_EXT):
            n=img_for(a)
            if n: repl[raw]=rel("img/"+n)
        else:
            p2=urlsplit(a).path
            if urlsplit(a).netloc.endswith("timvanheukelom.nl") and p2 in PAGES:
                repl[raw]=rel(PAGES[p2])
    for m in ATTR_RE.finditer(doc): handle(m.group(2))
    for m in CSSURL_RE.finditer(doc): handle(m.group(2))
    for m in SRCSET_RE.finditer(doc):
        for c in m.group(2).split(","):
            b=c.strip().split()
            if b: handle(b[0])
    # remove tags for dropped assets
    for raw in drop_urls:
        e=re.escape(raw)
        doc=re.sub(r'[ \t]*<script[^>]*src=["\']'+e+r'["\'][^>]*>\s*</script>\n?','',doc)
        doc=re.sub(r'[ \t]*<link[^>]*href=["\']'+e+r'["\'][^>]*/?>\n?','',doc)
    # emoji + wp cruft
    doc=re.sub(r'<script id="wp-emoji-settings"[^>]*>.*?</script>\s*','',doc,flags=re.S)
    doc=re.sub(r'<script\b[^>]*>(?:(?!</script>).)*?wp-emoji-loader(?:(?!</script>).)*?</script>\s*','',doc,flags=re.S)
    doc=re.sub(r"<style id='wp-emoji-styles-inline-css'[^>]*>.*?</style>\s*",'',doc,flags=re.S)
    for pat in WP_CRUFT: doc=re.sub(pat,'',doc)
    # single image size: drop srcset/sizes
    doc=re.sub(r'\s+(?:data-)?srcset=(["\']).*?\1','',doc,flags=re.S)
    doc=re.sub(r'\s+sizes=(["\'])[^"\']*\1','',doc)
    doc=re.sub(r'https?://www\.timvanheukelom\.nl/wp-content/plugins/bb-plugin/fonts/'
               r'fontawesome/[\d.]+/webfonts/([\w-]+\.woff2)',
               lambda m: rel("fonts/fontawesome/"+m.group(1)), doc)
    for raw,new in sorted(repl.items(), key=lambda kv:-len(kv[0])):
        doc=doc.replace('"'+raw+'"','"'+new+'"').replace("'"+raw+"'","'"+new+"'")
    dst=os.path.join(OUT,outrel); os.makedirs(os.path.dirname(dst),exist_ok=True)
    open(dst,"w",encoding="utf-8").write(doc)
    left=len(re.findall(r'https?://www\.timvanheukelom\.nl/wp-(content|includes)',doc))
    return left

tot=0
for p,outrel in PAGES.items():
    if p not in docs: continue
    n=build_page(p,docs[p],outrel); tot+=n
    log(f"   {outrel:46} leftover-asset-refs={n}")
log(f"\nTOTAL leftover asset refs: {tot}")
sz=sum(os.path.getsize(os.path.join(r,f)) for r,_,fs in os.walk(OUT) for f in fs)
log(f"site size: {sz/1048576:.0f} MB")
