#!/usr/bin/env python3
"""Generate a standalone page that reuses the site's own shell (head, header,
footer and whatever asset filenames index.html currently points at).

Re-run this after rebuilding the site: it always picks up the current asset
names, so the page never ends up with stale references.

    python3 tools/make_page.py <site-dir> <slug> <title> <body.html> [extra.css]
"""
import os, re, sys

def build(site, slug, title, body_html, extra_css=None):
    src = open(os.path.join(site, "index.html"), encoding="utf-8").read()
    head    = src[:src.index("</head>") + len("</head>")]
    bodyhdr = src[src.index("</head>") + len("</head>"): src.index("</header>") + len("</header>")]
    tail    = src[src.index("\t</div><!-- .fl-page-content -->"):]

    head = re.sub(r"<title>.*?</title>", f"<title>{title}</title>", head, flags=re.S)
    head = re.sub(r'[ \t]*<link rel="canonical"[^>]*/>\n?', "", head)   # no upstream original
    if extra_css:
        head = head.replace("<link rel='stylesheet' id='fl-child-theme-css'",
            f"<link rel='stylesheet' id='page-{slug}-css' href='../css/{extra_css}' "
            f"type='text/css' media='all' />\n<link rel='stylesheet' id='fl-child-theme-css'", 1)

    depth = slug.count("/") + 1
    up = "../" * depth
    def deepen(t):
        t = re.sub(r'(["\'])(css|js|img|fonts)/', r'\1' + up + r'\2/', t)
        # header nav links are root-relative in index.html; lift them too
        t = re.sub(r'(href=["\'])((?:[A-Za-z0-9_-]+/)+index\.html)', r'\1' + up + r'\2', t)
        t = re.sub(r'(href=["\'])(index\.html)(["\'])', r'\1' + up + r'\2\3', t)
        return t.replace('href="favicon.ico"', f'href="{up}favicon.ico"')
    head, bodyhdr, tail = deepen(head), deepen(bodyhdr), deepen(tail)
    bodyhdr = bodyhdr.replace('class="home wp-singular page-template-default page page-id-1914',
                              f'class="wp-singular page-template-default page page-id-{slug}', 1)

    content = ('<div class="uabb-js-breakpoint" style="display: none;"></div>'
               '\t<div class="fl-page-content" itemprop="mainContentOfPage">\n\n'
               '<div class="fl-content-full container">\n\t<div class="row">\n'
               '\t\t<div class="fl-content col-md-12">\n'
               f'\t\t\t<article class="fl-post" id="fl-post-{slug}" itemscope="itemscope" '
               'itemtype="https://schema.org/CreativeWork">\n\t\t\t<div class="fl-post-content">\n\n'
               + body_html +
               '\n\n\t\t\t</div><!-- .fl-post-content -->\n\t\t\t</article><!-- .fl-post -->\n'
               '\t\t</div>\n\t</div>\n</div>\n\n')

    out = os.path.join(site, slug, "index.html")
    os.makedirs(os.path.dirname(out), exist_ok=True)
    open(out, "w", encoding="utf-8").write(head + bodyhdr + content + tail)
    return out

if __name__ == "__main__":
    site, slug, title, bodyfile = sys.argv[1:5]
    extra = sys.argv[5] if len(sys.argv) > 5 else None
    print("wrote", build(site, slug, title, open(bodyfile, encoding="utf-8").read(), extra))
