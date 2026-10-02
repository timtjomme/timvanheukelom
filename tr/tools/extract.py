"""One-off: lift the release data out of the built pages into data/releases.json.

Kept in the repo for reference. Day to day you edit the JSON and run build.py;
you should not need to run this again.
"""
import json, re, html as H
from pathlib import Path
from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parent.parent
PAGES = ["1980s", "1990s"]


def text_of(el):
    """Decoded text, entities resolved. build.py re-escapes on the way out."""
    return H.unescape(el.decode_contents()).strip()


def extract(page):
    soup = BeautifulSoup((ROOT / f"{page}.html").read_text(encoding="utf-8"), "html.parser")
    releases = []
    # walk year headings and the grid that follows each one
    for rule in soup.select(".year-rule"):
        year = rule.get_text(strip=True)
        grid = rule.find_next_sibling("div", class_="grid")
        for card in grid.select(".card"):
            tracks, missing = [], []
            for li in card.select("ul.tracks li"):
                t = text_of(li)
                tracks.append(t)
                if "missing" in (li.get("class") or []):
                    missing.append(t)
            rec = {
                "year": year,
                "artist": text_of(card.select_one(".front .artist")),
                "title": text_of(card.select_one(".front .title")),
                "label": text_of(card.select_one(".back .label-name")),
                "image": card.select_one(".front img")["src"].replace("imgs/", ""),
                "tracks": tracks,
            }
            if missing:
                rec["missing"] = missing
            releases.append(rec)
    return releases


def main():
    data = {p: extract(p) for p in PAGES}
    out = ROOT / "data" / "releases.json"
    out.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    for p, rs in data.items():
        flagged = sum(len(r.get("missing", [])) for r in rs)
        print(f"  {p}: {len(rs)} releases, "
              f"{sum(len(r['tracks']) for r in rs)} tracks, {flagged} flagged")
    print(f"  -> {out.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
