"""Backend enrichment: cross-check tools/fetch_credits.py's Discogs pull
against Qobuz's own per-track credits, as data only — build.py does not
read this field and nothing on the site renders it.

Run:      python3 tools/fetch_qobuz_credits.py [--decade 2010s] [--limit N]

Resumable — a release already carrying a "qobuz_credits" key is skipped.
Progress is written to releases.json after every release.

Qobuz's storefront is server-rendered (confirmed: a plain, unauthenticated
GET already contains every track's credit text — no login, no API key, no
JS execution needed), so this works entirely over plain HTTP: one search
request per release, one album-page request for the best match. Matching
is the same two-step shape as the Discogs script — a slug-similarity check
against the search results, then a per-track title match against the
matched album's own tracklist — with the same discipline: a release that
doesn't clear the threshold is left unmatched and logged, not guessed at.

Credit lines come out as "Name, Role" (Beat It -> "Steve Lukather, Guitar"),
the reverse of Discogs' "Role: Name" — regrouped here into the same
"Role: Name1, Name2" shape fetch_credits.py already uses, so the two
sources sit side by side in the same format for comparison.
"""
import argparse, json, re, time, urllib.error, urllib.parse, urllib.request
from difflib import SequenceMatcher
from pathlib import Path
from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parent.parent
RELEASES_PATH = ROOT / "data" / "releases.json"
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36"

MATCH_THRESHOLD = 0.35
CONFIRM_THRESHOLD = 0.6
CANDIDATES_TO_TRY = 5
MIN_INTERVAL = 1.5


class RateLimiter:
    def __init__(self):
        self.last = 0.0

    def wait(self):
        elapsed = time.time() - self.last
        if elapsed < MIN_INTERVAL:
            time.sleep(MIN_INTERVAL - elapsed)
        self.last = time.time()


def http_get(url, limiter):
    headers = {"User-Agent": UA}
    for attempt in range(5):
        limiter.wait()
        req = urllib.request.Request(url, headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=20) as r:
                return r.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e:
            if e.code == 404:
                return None
            if attempt == 4:
                raise
            time.sleep(4 * (attempt + 1))
        except (urllib.error.URLError, TimeoutError, ConnectionError, OSError):
            # transient network blip (DNS hiccup, timeout, reset) — after
            # exhausting retries, treat it as a fetch failure for this one
            # URL rather than taking the whole multi-hour run down with it
            if attempt == 4:
                return None
            time.sleep(4 * (attempt + 1))
    return None


def norm(s):
    s = s.lower()
    s = re.sub(r"feat\.?.*$", "", s)
    s = re.sub(r"[^a-z0-9]+", " ", s)
    return re.sub(r"\s+", " ", s).strip()


def similarity(a, b):
    return SequenceMatcher(None, norm(a), norm(b)).ratio()


def search_qobuz(artist, title, limiter):
    # A literal "/" in the query 404s store-router server-side even when
    # percent-encoded (confirmed: "100/Q" alone reproduces it) — titles
    # like "100/Q & C" need it swapped for a space before the request,
    # though the original text is still used for scoring below.
    query = f"{artist} {title}".replace("/", " ")
    q = urllib.parse.quote(query, safe="")
    html = http_get(f"https://www.qobuz.com/store-router/search?q={q}", limiter)
    if not html:
        return [], "no-response"
    soup = BeautifulSoup(html, "html.parser")
    hrefs = []
    seen = set()
    for a in soup.select('a[href*="/album/"]'):
        href = a.get("href", "")
        if href in seen or "/album/" not in href:
            continue
        seen.add(href)
        hrefs.append(href)

    # The slug alone can't be scored as reliable artist-vs-title — a strong
    # title match can as easily belong to a wrong album (self-titled or
    # sequel-style records repeat words across otherwise-unrelated
    # releases). So this is a coarse, generous *pre-filter*: rank by
    # combined-string similarity just to try the more plausible candidates
    # first, but return several of them — confirm_page() below, checking
    # the real page's own JSON-LD, is the actual precision gate.
    target = f"{artist} {title}"
    all_scored = []
    for href in hrefs[:40]:
        slug = href.split("/album/", 1)[1]
        slug_words = slug.rsplit("/", 1)[0]  # drop the trailing id segment
        score = similarity(target, slug_words.replace("-", " "))
        all_scored.append((score, href))
    all_scored.sort(key=lambda x: -x[0])

    candidates = []
    for score, href in all_scored:
        if score < MATCH_THRESHOLD:
            break
        url = href if href.startswith("http") else "https://www.qobuz.com" + href
        candidates.append((url, score))
        if len(candidates) >= CANDIDATES_TO_TRY:
            break
    if candidates:
        return candidates, f"search({candidates[0][1]:.2f})"
    best = all_scored[0][0] if all_scored else 0.0
    return [], f"low-confidence(best={best:.2f})"


TRACK_PREFIX = re.compile(r"^\s*(?:cd\d+\s*)?\d{0,2}[\-.)]?\s*")
FEAT_PREFIX = re.compile(r"^\s*feat(?:uring|\.)?\s+", re.I)


def track_keys(t):
    """This site's own track strings are a grab-bag of conventions —
    bare titles, "NN. Title", "NN. Artist - Title", "feat. Artist - Title"
    — while Qobuz always gives a bare title. Rather than one regex trying
    to cover every prefix shape, return every plausible candidate (full
    string, and the tail after the last " - ") and let the caller pick
    whichever scores best against Qobuz's title."""
    t = re.sub(r"\.(flac|mp3)$", "", t, flags=re.I)
    t = re.sub(r"\(feat[^)]*\)", "", t, flags=re.I)
    t = TRACK_PREFIX.sub("", t)
    t = FEAT_PREFIX.sub("", t)
    candidates = {norm(t)}
    if " - " in t:
        candidates.add(norm(t.rsplit(" - ", 1)[-1]))
    return candidates


def group_credits(raw_text):
    """"Name, Role - Name, Role - ..." -> ["Role: Name1, Name2", ...],
    matching fetch_credits.py's Discogs-derived shape for easy comparison.
    raw_text is already just the credits paragraph (the caller selects the
    first .track__info — a separate, later .track__info sibling carries
    the copyright/label line, so no text-splitting is needed here).

    A segment isn't always one "Name, Role" pair — a performer with several
    credited roles comes back as "Name, Role1, Role2, ..." in one segment
    (e.g. "GUY, MainArtist, AssociatedPerformer, Vocals, Producer"), so
    everything after the first comma is a role, not part of the name."""
    segments = [s.strip().rstrip("-").strip() for s in raw_text.split(" - ") if s.strip()]
    by_role = {}
    for seg in segments:
        parts = [p.strip() for p in seg.split(",")]
        if len(parts) < 2 or not parts[0]:
            continue
        name, roles = parts[0], parts[1:]
        for role in roles:
            if not role:
                continue
            by_role.setdefault(role, [])
            if name not in by_role[role]:
                by_role[role].append(name)
    return [f"{role}: {', '.join(names)}" for role, names in by_role.items()]


SOUNDTRACK_BOILERPLATE = re.compile(
    r"\b(music from (and inspired by )?the )?"
    r"(the )?(original )?motion picture( soundtrack| the movie)?\b|\bsoundtrack\b",
    re.I,
)


def strip_boilerplate(s):
    return re.sub(r"\s+", " ", SOUNDTRACK_BOILERPLATE.sub("", s)).strip()


def confirm_page(soup, artist, title, year):
    """The search-result slug match is a coarse first pass — self-titled
    or name-repeating releases (e.g. "Shomari - Shomari") can score well
    against a completely unrelated album by coincidence, and so can a
    right-artist-wrong-release pairing (an artist's own back catalog is
    full of similarly-scoring titles). Re-check against the fetched
    page's own JSON-LD (Product.name = title, Product.brand.name =
    artist, offers[].releaseDate = year), which is ground truth, before
    trusting anything on it.

    Scored artist-vs-artist and title-vs-title *separately*, taking the
    min of the two — not one blended "artist title" string — because a
    blended score lets a strong title match paper over a completely wrong
    artist (tuning against ~190 real search results from this catalog:
    the blended score tops out around 90% precision even at a strict 0.80
    cutoff, while min(artist,title) hits 98% precision at 0.6 with only a
    small recall cost).

    Two failure modes surfaced only once this ran at full scale (~285
    releases): "Various Artists" soundtrack entries share so much title
    boilerplate ("Music From The Motion Picture", "Original Motion
    Picture Soundtrack") that two *completely unrelated* films' soundtrack
    pages were scoring 0.75-0.85 on title alone — the shared boilerplate
    was doing all the "matching", not the actual film title. And a short,
    common-word artist name ("Guy", "Eternal") can substring-match a
    same-named-coincidence act decades removed in catalog terms (a 2025
    release matched against our 1999 entry). Two guards, both needed —
    neither alone covered every case found by hand:
      1. Title score is computed on boilerplate-*stripped* text, so two
         different films' soundtracks can no longer inflate their score
         off shared "motion picture soundtrack" wording.
      2. A release-year sanity check: Qobuz's own releaseDate more than
         5 years from ours is rejected, *unless* both scores are near-
         perfect (>= 0.9) — a genuine reissue (a 2004 compilation
         redistributed digitally in 2013, say) still has an exact title
         and artist, just a later catalog date; only that combination is
         allowed to override the year gate."""
    got_title = got_artist = got_year = None
    for tag in soup.select('script[type="application/ld+json"]'):
        try:
            block = json.loads(tag.get_text())
        except (json.JSONDecodeError, TypeError):
            continue
        if block.get("@type") == "Product":
            got_title = block.get("name")
            brand = block.get("brand")
            if isinstance(brand, dict):
                got_artist = brand.get("name")
            release_date = block.get("releaseDate") or ""
            if len(release_date) >= 4 and release_date[:4].isdigit():
                got_year = int(release_date[:4])
    if not got_title:
        return False, "no-ld-json(unverifiable)"
    artist_score = similarity(artist, got_artist or "")
    title_score = similarity(strip_boilerplate(title), strip_boilerplate(got_title))
    score = min(artist_score, title_score)
    how = f"confirm(a={artist_score:.2f},t={title_score:.2f}) [{got_artist} - {got_title} - {got_year}]"
    if score < CONFIRM_THRESHOLD:
        return False, how
    if got_year:
        gap = abs(got_year - year)
        # "Various" vs "Various Artists" never scores near 1.0 on its own, so
        # a soundtrack reissue can't clear the normal artist>=0.9 reissue
        # bypass — let a nearly-perfect (boilerplate-stripped) title stand in
        # for it here, since artist has no discriminating power on a
        # various-artists comp anyway. Capped at 15 years: sequel titles
        # ("House Party 2" vs "House Party 3") can still score >=0.9 on
        # boilerplate-stripped text alone, and a reissue gap that large is
        # implausible anyway — a physical-to-digital reissue lands within a
        # decade or so in every real reissue seen in this catalog so far.
        both_various = "various" in norm(artist).split() and "various" in norm(got_artist or "").split()
        strong_reissue = gap <= 15 and title_score >= 0.9 and (artist_score >= 0.9 or both_various)
        if gap > 5 and not strong_reissue:
            return False, how + " year-gap"
    return True, how


def fetch_qobuz_credits_for_release(release, limiter, log):
    candidates, how = search_qobuz(release["artist"], release["title"], limiter)
    if not candidates:
        log(f"  UNMATCHED  {release['year']} {release['artist']} - {release['title']}  ({how})")
        return None

    soup = url = None
    rejections = []
    for cand_url, cand_score in candidates:
        html = http_get(cand_url, limiter)
        if not html:
            rejections.append(f"fetch-fail({cand_url})")
            continue
        cand_soup = BeautifulSoup(html, "html.parser")
        ok, confirm_how = confirm_page(cand_soup, release["artist"], release["title"], int(release["year"]))
        if ok:
            soup, url, how = cand_soup, cand_url, f"search({cand_score:.2f}), {confirm_how}"
            break
        rejections.append(confirm_how)
    if soup is None:
        log(f"  UNMATCHED  {release['year']} {release['artist']} - {release['title']}  "
            f"(rejected {len(candidates)}: {'; '.join(rejections)})")
        return None

    rows = soup.select(".track")
    qobuz_tracks = []
    for row in rows:
        name_el = row.select_one(".track__item--name span") or row.select_one('[itemprop="name"]')
        if not name_el:
            continue
        qobuz_tracks.append((norm(name_el.get_text(strip=True)), row))

    credits = []
    hits = 0
    for our_track in release["tracks"]:
        our_keys = track_keys(our_track)
        best_row, best_score = None, 0.0
        for qkey, qrow in qobuz_tracks:
            s = max(SequenceMatcher(None, ok_key, qkey).ratio() for ok_key in our_keys)
            if s > best_score:
                best_row, best_score = qrow, s
        row = best_row if best_score >= 0.75 else None
        track_credits = []
        if row:
            info_el = row.select_one(".track__infos .track__info")
            if info_el:
                track_credits = group_credits(info_el.get_text(" ", strip=True))
        if track_credits:
            hits += 1
        credits.append(track_credits)

    log(f"  ok ({how})  {release['year']} {release['artist']} - {release['title']}  "
        f"[{hits}/{len(release['tracks'])} tracks credited]  {url}")
    return credits


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--decade", help="only this decade key, e.g. 2010s")
    ap.add_argument("--limit", type=int, help="stop after N releases (this run)")
    args = ap.parse_args()

    data = json.loads(RELEASES_PATH.read_text(encoding="utf-8"))
    limiter = RateLimiter()

    def log(msg):
        print(msg, flush=True)

    def save():
        RELEASES_PATH.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    decades = [args.decade] if args.decade else list(data.keys())
    processed = 0
    stats = {"matched": 0, "unmatched": 0, "skipped": 0}

    for decade in decades:
        if decade not in data:
            log(f"unknown decade key: {decade}")
            continue
        log(f"=== {decade} ({len(data[decade])} releases) ===")
        for release in data[decade]:
            if "qobuz_credits" in release:
                stats["skipped"] += 1
                continue
            if args.limit and processed >= args.limit:
                log(f"--limit {args.limit} reached, stopping")
                save()
                print_summary(stats)
                return

            credits = fetch_qobuz_credits_for_release(release, limiter, log)
            processed += 1
            if credits is not None:
                release["qobuz_credits"] = credits
                stats["matched"] += 1
            else:
                release["qobuz_credits"] = []
                stats["unmatched"] += 1
            save()

    print_summary(stats)


def print_summary(stats):
    print(f"\nmatched: {stats['matched']}  unmatched: {stats['unmatched']}  "
          f"already had qobuz_credits (skipped): {stats['skipped']}")


if __name__ == "__main__":
    main()
