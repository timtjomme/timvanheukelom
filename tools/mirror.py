#!/usr/bin/env python3
"""Mirror www.timvanheukelom.nl homepage into a fully offline local copy."""
import os, re, sys, html, hashlib, mimetypes, urllib.request, urllib.error
from urllib.parse import urljoin, urlsplit, unquote, quote

START = "https://www.timvanheukelom.nl/"
OUT   = sys.argv[1] if len(sys.argv) > 1 else "./timvanheukelom-local"
ASSETS = "assets"

UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/125.0 Safari/537.36")

ASSET_EXT = {".css",".js",".mjs",".jpg",".jpeg",".png",".gif",".svg",".webp",".avif",
             ".ico",".woff",".woff2",".ttf",".otf",".eot",".mp4",".webm",".ogg",
             ".mp3",".m4v",".json",".map",".cur"}

url2local = {}     # absolute url -> local path relative to OUT
fetched   = {}     # absolute url -> bytes
failed    = []
used_paths = set()

def fetch(url):
    if url in fetched:
        return fetched[url], None
    req = urllib.request.Request(url, headers={
        "User-Agent": UA,
        "Accept": "*/*",
        "Referer": START,
    })
    try:
        with urllib.request.urlopen(req, timeout=45) as r:
            data = r.read()
            ctype = r.headers.get("Content-Type", "")
    except Exception as e:
        failed.append((url, repr(e)))
        return None, None
    fetched[url] = data
    return data, ctype

def local_path_for(url, ctype=None):
    """Map an absolute URL to a local path under OUT, preserving dir structure."""
    if url in url2local:
        return url2local[url]
    s = urlsplit(url)
    host = s.netloc
    path = unquote(s.path)
    if not path or path.endswith("/"):
        path = path + "index.html"
    # sanitise segments
    segs = [re.sub(r'[<>:"|?*\\]', "_", p) for p in path.split("/") if p not in ("", ".", "..")]
    base = segs[-1] if segs else "index"
    root, ext = os.path.splitext(base)
    if s.query:
        # keep files with differing query strings distinct, but only when needed
        qhash = hashlib.md5(s.query.encode()).hexdigest()[:8]
        if not ext:
            ext = ".css" if (ctype and "css" in ctype) else (".js" if (ctype and "javascript" in ctype) else ".bin")
            base = f"{root}-{qhash}{ext}"
        else:
            candidate = os.path.join(ASSETS, host, *segs[:-1], base)
            if candidate in used_paths:
                base = f"{root}-{qhash}{ext}"
    elif not ext and ctype:
        guess = mimetypes.guess_extension(ctype.split(";")[0].strip()) or ""
        base = root + guess
    segs = segs[:-1] + [base]
    lp = os.path.join(ASSETS, host, *segs)
    n = 1
    while lp in used_paths and url2local.get(url) != lp:
        stem, e = os.path.splitext(lp)
        lp = f"{stem}~{n}{e}"
        n += 1
    used_paths.add(lp)
    url2local[url] = lp
    return lp

def save(local_rel, data):
    full = os.path.join(OUT, local_rel)
    os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, "wb") as f:
        f.write(data)

def is_asset(url):
    s = urlsplit(url)
    if s.scheme not in ("http", "https"):
        return False
    if s.netloc == "fonts.googleapis.com":
        return True
    ext = os.path.splitext(s.path)[1].lower()
    return ext in ASSET_EXT

def relpath_from(local_file, target_local):
    return os.path.relpath(target_local, os.path.dirname(local_file)).replace(os.sep, "/")

CSS_URL_RE = re.compile(r'url\(\s*([\'"]?)([^\'")]+)\1\s*\)', re.I)
CSS_IMPORT_RE = re.compile(r'@import\s+([\'"])([^\'"]+)\1', re.I)

def process_css(css_text, css_url, css_local, depth=0):
    """Download url()/@import targets; rewrite only absolute/root-relative refs."""
    def repl(m, quote_g, raw):
        raw_s = raw.strip()
        if raw_s.startswith("data:") or raw_s.startswith("#") or not raw_s:
            return None
        abs_u = urljoin(css_url, raw_s)
        if urlsplit(abs_u).scheme not in ("http", "https"):
            return None
        got = download_asset(abs_u, depth + 1)
        if not got:
            return None
        # relative refs already resolve thanks to preserved dir structure
        if not (raw_s.startswith("http://") or raw_s.startswith("https://")
                or raw_s.startswith("//") or raw_s.startswith("/")):
            return None
        return relpath_from(css_local, got)

    def url_sub(m):
        new = repl(m, m.group(1), m.group(2))
        if new is None:
            return m.group(0)
        return f'url("{new}")'

    def imp_sub(m):
        new = repl(m, m.group(1), m.group(2))
        if new is None:
            return m.group(0)
        return f'@import "{new}"'

    css_text = CSS_URL_RE.sub(url_sub, css_text)
    css_text = CSS_IMPORT_RE.sub(imp_sub, css_text)
    return css_text

def download_asset(url, depth=0):
    """Fetch url, store locally, recurse into CSS. Returns local rel path or None."""
    url = url.split("#")[0]
    if url in url2local and url in fetched:
        return url2local[url]
    if depth > 6:
        return None
    data, ctype = fetch(url)
    if data is None:
        return None
    lp = local_path_for(url, ctype)
    is_css = (ctype and "css" in ctype) or urlsplit(url).path.lower().endswith(".css") \
             or urlsplit(url).netloc == "fonts.googleapis.com"
    if is_css:
        try:
            text = data.decode("utf-8")
        except UnicodeDecodeError:
            text = data.decode("latin-1")
        text = process_css(text, url, lp, depth)
        save(lp, text.encode("utf-8"))
    else:
        save(lp, data)
    return lp

# ---------------------------------------------------------------- HTML pass
def mirror_page(page_url, out_html_rel):
    data, ctype = fetch(page_url)
    if data is None:
        print("FATAL: could not fetch", page_url); sys.exit(1)
    doc = data.decode("utf-8", errors="replace")
    url2local[page_url] = out_html_rel
    used_paths.add(out_html_rel)

    replacements = {}   # original attr string -> local relative path

    def handle(raw_url):
        """raw_url as it literally appears in the HTML. Returns local rel path or None."""
        if raw_url in replacements:
            return replacements[raw_url]
        cleaned = html.unescape(raw_url).strip()
        if (not cleaned or cleaned.startswith("data:") or cleaned.startswith("#")
                or cleaned.startswith("javascript:") or cleaned.startswith("mailto:")):
            return None
        abs_u = urljoin(page_url, cleaned)
        if not is_asset(abs_u):
            return None
        lp = download_asset(abs_u)
        if not lp:
            return None
        rel = relpath_from(out_html_rel, lp)
        replacements[raw_url] = rel
        return rel

    # 1. plain href=/src= attributes
    for m in re.finditer(r'(?:href|src|data-src|content)\s*=\s*([\'"])(.*?)\1', doc, re.S):
        handle(m.group(2))

    # 2. srcset / data-srcset (comma separated with descriptors)
    srcset_map = {}
    for m in re.finditer(r'(?:srcset|data-srcset)\s*=\s*([\'"])(.*?)\1', doc, re.S):
        orig = m.group(2)
        parts = []
        changed = False
        for cand in orig.split(","):
            cand = cand.strip()
            if not cand:
                continue
            bits = cand.split()
            u = bits[0]
            rest = " ".join(bits[1:])
            new = handle(u)
            if new:
                changed = True
                parts.append((new + (" " + rest if rest else "")))
            else:
                parts.append(cand)
        if changed:
            srcset_map[orig] = ", ".join(parts)

    # 2b. generic sweep: any quoted string that looks like an asset URL
    #     (catches data-parallax-image, JSON blobs like "concatemoji", etc.)
    ext_alt = "|".join(e.lstrip(".") for e in sorted(ASSET_EXT))
    generic_re = re.compile(
        r'(["\'])((?:https?:)?//[^"\'<>\s]+?\.(?:' + ext_alt + r')(?:\?[^"\'<>\s]*)?)\1', re.I)
    for m in generic_re.finditer(doc):
        handle(m.group(2))

    # 3. url(...) inside inline <style> blocks and style="" attributes
    inline_url_map = {}
    for m in CSS_URL_RE.finditer(doc):
        raw = m.group(2).strip()
        if raw.startswith("data:"):
            continue
        new = handle(raw)
        if new:
            inline_url_map[m.group(0)] = f'url("{new}")'

    # apply replacements (longest first to avoid partial overlaps)
    for orig, new in sorted(srcset_map.items(), key=lambda kv: -len(kv[0])):
        doc = doc.replace(orig, new)
    for orig, new in sorted(replacements.items(), key=lambda kv: -len(kv[0])):
        doc = doc.replace(f'"{orig}"', f'"{new}"').replace(f"'{orig}'", f"'{new}'")
    for orig, new in sorted(inline_url_map.items(), key=lambda kv: -len(kv[0])):
        doc = doc.replace(orig, new)

    save(out_html_rel, doc.encode("utf-8"))
    return doc

if __name__ == "__main__":
    os.makedirs(OUT, exist_ok=True)
    doc = mirror_page(START, "index.html")
    print(f"\nAssets saved : {len(url2local)-1}")
    print(f"Failed       : {len(failed)}")
    for u, e in failed:
        print("  !", u, e)
