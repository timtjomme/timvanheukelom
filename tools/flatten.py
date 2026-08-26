#!/usr/bin/env python3
"""Rebuild the site into a plain static-site layout:
   flat .html pages, static files under assets/, no WordPress post-IDs."""
import os, re, sys, collections

R = sys.argv[1]
os.chdir(R)

# ---------------------------------------------------------------- page map
pages = [os.path.relpath(os.path.join(r, f), ".")
         for r, _, fs in os.walk(".")
         if not r.startswith(("./.git", "./tools"))
         for f in fs if f == "index.html"]
PAGE = {}
for old in pages:
    d = os.path.dirname(old)
    PAGE[old] = "index.html" if d in ("", ".") else d + ".html"

# ------------------------------------------------------- asset rename map
layout_users = collections.defaultdict(list)
mod_users = collections.defaultdict(list)
for old in pages:
    t = open(old, encoding="utf-8", errors="replace").read()
    for i in set(re.findall(r'layout-(\d+)\.(?:css|js)', t)): layout_users[i].append(old)
    for h in set(re.findall(r'modules-([0-9a-f]+)\.(?:css|js)', t)): mod_users[h].append(old)

LAY = {}
for pid, users in layout_users.items():
    if len(users) > 1:
        LAY[pid] = "home"
    else:
        stem = PAGE[users[0]]
        LAY[pid] = os.path.splitext(os.path.basename(stem))[0]
        if LAY[pid] == "index": LAY[pid] = "home"
MOD = {}
for n, (h, users) in enumerate(sorted(mod_users.items(), key=lambda kv: -len(kv[1])), 1):
    MOD[h] = "modules" if n == 1 else f"modules-{n}"

def new_asset_name(kind, base):
    m = re.match(r'^layout-(\d+)\.(css|js)$', base)
    if m and m.group(1) in LAY: return f"{LAY[m.group(1)]}.{m.group(2)}"
    m = re.match(r'^modules-([0-9a-f]+)\.(css|js)$', base)
    if m and m.group(1) in MOD: return f"{MOD[m.group(1)]}.{m.group(2)}"
    if base.startswith("cropped-Fav-icon"):          # WordPress-generated name
        return "favicon" + os.path.splitext(base)[1]
    return base

ASSET = {}
for kind in ("css", "js", "img"):
    for base in os.listdir(kind):
        ASSET[f"{kind}/{base}"] = f"assets/{kind}/{new_asset_name(kind, base)}"
for r, _, fs in os.walk("fonts"):
    for f in fs:
        p = os.path.join(r, f).replace(os.sep, "/")
        ASSET[p] = "assets/" + p

TARGET = {}
TARGET.update(PAGE); TARGET.update(ASSET)

# ---------------------------------------------------------------- rewrite
ATTR = re.compile(r'((?:href|src|data-src|data-image|data-parallax-image)\s*=\s*)(["\'])([^"\']+)\2')
out_docs = {}
for old, new in PAGE.items():
    olddir = os.path.dirname(old) or "."
    newdir = os.path.dirname(new) or "."
    t = open(old, encoding="utf-8", errors="replace").read()
    def repl(m):
        pre, q, u = m.group(1), m.group(2), m.group(3)
        if u.startswith(("http://","https://","//","#","data:","mailto:","javascript:")) or not u:
            return m.group(0)
        frag = ""
        if "#" in u: u, frag = u.split("#", 1); frag = "#" + frag
        tgt = os.path.normpath(os.path.join(olddir, u)).replace(os.sep, "/")
        if tgt not in TARGET:
            return m.group(0)
        rel = os.path.relpath(TARGET[tgt], newdir).replace(os.sep, "/")
        return f"{pre}{q}{rel}{frag}{q}"
    out_docs[new] = ATTR.sub(repl, t)

# ---------------------------------------------------------------- move
os.makedirs("assets", exist_ok=True)
for kind in ("css", "js", "img", "fonts"):
    os.rename(kind, f"assets/{kind}")
for kind in ("css", "js", "img"):
    d = f"assets/{kind}"
    for base in os.listdir(d):
        nb = new_asset_name(kind, base)
        if nb != base: os.rename(os.path.join(d, base), os.path.join(d, nb))

for old in pages:
    os.remove(old)
for d in sorted({os.path.dirname(p) for p in pages if os.path.dirname(p) not in ("", ".")},
                key=len, reverse=True):
    try: os.rmdir(d)
    except OSError: pass

for new, doc in out_docs.items():
    nd = os.path.dirname(new)
    if nd: os.makedirs(nd, exist_ok=True)
    open(new, "w", encoding="utf-8").write(doc)

print(f"pages: {len(PAGE)}   layout renames: {len(LAY)}   module bundles: {MOD}")
print("sample:", dict(list(sorted(LAY.items()))[:4]))
