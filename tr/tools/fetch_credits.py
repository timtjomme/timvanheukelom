"""Backend enrichment: pull full per-track credits from the Discogs API into
data/releases.json, as data only — build.py does not read this field and
nothing on the site renders it. It's here for later use, not for the cards.

Run:      python3 tools/fetch_credits.py [--decade 2010s] [--limit N] [--token TOKEN]

Resumable — a release already carrying a "credits" key is skipped, so a killed
or interrupted run just picks back up. Progress is written to releases.json
after every release, not batched at the end.

Two ways a release gets matched to a Discogs release id:
  1. KNOWN_IDS — decoded straight out of the i.discogs.com cover URLs used
     when the release was added (see decode_release_id below). Exact, no
     guessing.
  2. Discogs search (/database/search) — for everything else (Apple Music /
     local-file covers). The top result is only accepted if it clears a
     similarity threshold against our own artist+title; otherwise the release
     is left unmatched and logged, rather than risk attaching another
     pressing's credits to the wrong release.

Rate limits: unauthenticated Discogs API is ~25 req/min. Pass --token (a
personal access token from discogs.com/settings/developers) to run at the
authenticated 60/min instead — meaningful at ~400 releases x up to 2 calls
each. The client also backs off on 429s and reads the
X-Discogs-Ratelimit-Remaining header to slow down before it gets throttled.
"""
import argparse, base64, json, re, sys, time, urllib.error, urllib.parse, urllib.request
from difflib import SequenceMatcher
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RELEASES_PATH = ROOT / "data" / "releases.json"
UA = "TeddyRileyProductionsBot/1.0 +https://timvanheukelom.nl/tr/"

MATCH_THRESHOLD = 0.55  # below this, a search result is treated as no-match


def decode_release_id(cover_url):
    """i.discogs.com URLs base64-encode the S3 key, e.g.
    .../czM6Ly9kaXNjb2dz/.../R-9677483-....jpeg -> s3://discogs-database-images/R-9677483-...
    Returns the int release id, or None if this isn't a decodable Discogs cover URL."""
    if "i.discogs.com" not in cover_url:
        return None
    parts = cover_url.split("/")
    try:
        idx = next(i for i, p in enumerate(parts) if p.startswith("w:")) + 1
    except StopIteration:
        return None
    b64 = "".join(parts[idx:])
    b64 = b64.rsplit(".", 1)[0]  # drop the trailing extension
    b64 += "=" * (-len(b64) % 4)
    try:
        decoded = base64.urlsafe_b64decode(b64).decode()
    except Exception:
        return None
    m = re.search(r"/R-(\d+)-", decoded)
    return int(m.group(1)) if m else None


# Seeded from the original i.discogs.com URLs used when the 2010s releases
# were added this session (already-downloaded local covers no longer carry
# the source URL, so this is the one place that history is preserved).
KNOWN_IDS = {
    ("Mike Posner", "31 Minutes To Takeoff"): 9677483,
    ("B. Howard", "Genesis"): 7027941,
    ("Michael Jackson", "Michael"): 2589933,
    ("Boyz II Men", "Twenty"): 4338098,
    ("Ty$ & Joe Mo$e$ Produced By DJ Mustard", "Whoop!"): 7872764,
    ("Tyga", "Hotel California"): 32527359,
    ("f(x)", "Red Light"): 7720260,
    ("Taemin", "Ace"): 7962108,
    ("Dave Hollister", "Chicago Winds...The Saga Continues"): 6280014,
    ("Red Velvet", "Ice Cream Cake"): 6974296,
    ("Girls' Generation", "Lion Heart"): 8605594,
    ("Mack Wilds", "Love in the 90z"): 32440953,
    ("EXO", "For Life - Winter Special"): 27294513,
    ("Taemin", "Press It"): 8161212,
    ("Tim Bowman Jr.", "Listen"): 15081561,
    ("Charlie Wilson", "In It To Win It"): 9836803,
    ("Lalah Hathaway", "Honestly"): 15354605,
    ("Red Velvet", "Summer Magic"): 13520226,
    ("Keith Sweat", "Playing For Keeps"): 16073045,
    ("Nile Rodgers & Chic", "It's About Time"): 12607788,
    # Manually supplied by the user (search/similarity matching couldn't
    # resolve these on its own — obscure 1980s 12"s not worth guessing at).
    ("Atlantis / Total Climax", "Keep On Movin' And Groovin'"): 1739786,
    ("Kids At Work", "Sugar Baby"): 1037572,
    ("Kids At Work", "Singing Hey Yea"): 4982052,
    ("Doug E. Fresh And The Get Fresh Crew", "The Show / La-Di-Da-Di"): 1139655,
    ("La Va'ba", "That Girl"): 13400863,
    ("The Masters Of Ceremony", "Crime"): 3079937,
    ("Al B. Just Two Mc's", "Wrong"): 401601,
    ("Awesome Foursome", "Monster Beat"): 1203437,
    ("Disco Four", "Get Busy / Stomp, Stomp, Clap"): 96291,
    ("Ray Rock & K.C.", "Minnie / Rayrock Kick It"): 313502,
    ("B-Fats", "B-Fats"): 276655,
    ("D.J. Hollywood", "Love In The Afternoon"): 403878,
    ("D.J. Short & Max Zeke", "My Phone"): 54638,
    ("Delicious", "Stop Playing"): 2187846,
    ("Divine Force", "T.V. Guide / The Jizer / We Came Here"): 825471,
    ("Heavy D. & The Boyz feat. Al B. Sure!", "Money Ernin' Mt. Vernon"): 4989829,
    ("Keith Sweat", "I Want Her"): 366207,
    ("Keith Sweat", "Something Just Ain't Right"): 341365,
    ("Kool Moe Dee", "Dumb Dick (Richard)"): 211688,
    ("Kool Moe Dee", "Kool Moe Dee"): 1287846,
    ("Roof Top Crew", "Caught Out There"): 9586007,
    ("Tony Tee The Composer", "Expressing My Thoughts"): 471449,
    ("Woody Rock", "Bigger's Beat"): 202462,
    ("Al B. Sure! feat. Slick Rick", "If I'm Not Your Lover"): 542616,
    ("Clurel", "Hurtown"): 759812,
    ("Déjà", "Going Crazy"): 879847,
    ("Guy", "'Round And 'Round (Merry Go 'Round Of Love)"): 1415528,
    ("Guy", "Teddy's Jam"): 326117,
    ("Heavy D & The Boyz", "Don't You Know / Moneyearnin' Mount Vernon"): 1050960,
    ("Johnny Kemp", "Just Got Paid"): 15437056,
    ("Stevie Wonder", "My Eyes Don't Cry"): 342817,
    ("The Classical Two", "The Classical Two Is Back"): 1573681,
    ("The Gyrlz", "Wishing You Were Here"): 1620793,
    ("Today", "Him Or Me"): 669059,
    ("Wrecks-N-Effect", "Wrecks-N-Effect"): 319215,
    ("Various", "Do The Right Thing Soundtrack"): 1132606,
    ("Guy", "I Like"): 1056571,
    ("Levert", "Just Coolin'"): 2137061,
    ("Soul II Soul", "Keep On Movin'"): 978567,
    ("Quincy Jones feat. Siedah Garrett", "I Don't Go For That"): 412379,
    ("Keith Sweat", "Keep It Comin'"): 1194129,
    ("Keith Sweat", "Your Love"): 1187932,
    ("Various", "Juice (Original Motion Picture Soundtrack)"): 7092412,
    ("Teddy Riley feat. Tammy Lucas", "Is It Good To You"): 1187490,
    ("The Party", "Free"): 3373823,  # CD album edition — has "All About Love" / "At All Times"
    ("Various", "New Jack City (Music From The Motion Picture)"): 1409200,  # CD edition — has the Guy track
    ("Father", "Sex Is Law"): 1775026,
    ("9*1*1", "The Pressure"): 11859501,
    ("Teddy Riley", "Nickel Bag Of Swing / Nickel Bag Of Tracks"): 948454,
    ("Tony Thompson", "I Wanna Love Like That"): 1460658,
    ("BLACKstreet", "Another Level (Expanded Edition)"): 564278,
    ("Montell Jordan", "Falling"): 2745809,
    ("Whitney Houston", "Step By Step"): 662636,
    ("Shawn", "O.G"): 4849723,
    ("Big Bub", "Timeless"): 1566149,
    ("BLACKstreet feat. Jay-Z", "Call Me"): 1575306,  # Soul Food soundtrack — has "Call Me (Hip Hop Mix)"; the standalone promo (1513343) had no data
    ("Omar Chandler", "Pieces Of My Heart"): 3017861,
    ("Taral feat. LL Cool J", "How Can I Get Over You (Remix)"): 302335,
    ("Men Of Vizion", "Do You Feel Me (...Freak You)"): 3414871,  # resolved from master 515314 — this pressing has data; the other (1233754) had none
    ("Jay-Z feat. BLACKstreet", "The City Is Mine"): 1468734,
    ("Guy", "Diamonds"): 7082238,  # promo-only reference CD; Discogs carries no extraartists data for it
    # "Various" soundtrack entries below store one representative track per
    # release; our track string embeds the performing artist ("X – Song" or
    # "X feat. Y - Song"), which breaks title-matching against the plain
    # Discogs track title, so these are resolved but the per-track credit
    # is assigned by hand in the same pass rather than relying on the
    # automatic matcher.
    ("Various", "CB4"): 184850,
    ("Various", "Blankman (Music From The Motion Picture)"): 3220931,
    ("Various", "Panther (The Original Motion Picture Soundtrack)"): 1165685,
    ("Various", "Nothing To Lose - Music From And Inspired By The Motion Picture"): 2311002,
    ("Various", "Hav Plenty (Music From The Motion Picture)"): 1916042,
    ("Various", "Music From The Motion Picture The Rugrats Movie"): 2681545,
    ("Various", "Music From And Inspired By The Motion Picture The Wood"): 1303572,
    ("Guy", "Don't You Miss Me"): 1673358,  # Guy III album — track is "Don't U Miss Me"
    ("Guy", "Teddy's Jam III"): 1673358,  # Guy III album — local mix names ("Jam 3") won't text-match "Jam III"
    ("Big Bub", "Need Your Love (Remix)"): 1566149,  # standalone promo has no data; reusing the Timeless album cut's credit
    ("Queen Pen", "Party Ain't A Party"): 3396932,  # bundles original + remix mixes; assigned by hand per mix below
    ("Hanson", "Mmm Bop (Remixes)"): 2793401,
    ("Riff", "Judy Had A Boyfriend"): 3087063,
    ("DG", "Hush (Don't Say A Word) (Remix)"): 2185630,
    ("Guy", "Dancin'"): 71255,
    ("Guy", "Why You Wanna Keep Me From My Baby"): 4628976,
    ("Profyle", "Damn"): 2542612,
    ("RPM 2000 feat. Teddy Riley", "Yo Love"): 4622309,
    ("The Product G&B feat. Wyclef Jean", "Freak Freak"): 21289054,
    ("Benzino feat. Mr. Gzus & Teddy Riley", "Boottee (Rumpshaker 2K1)"): 3807535,
    ("Ray J feat. Brandy, Teddy Riley & Shorty Mack", "Formal Invite (The Knockout Remix)"): 3514966,
    ("Benzino", "Figadoh (Remix)"): 10130897,
    ("Chauncey Black", "Everyday Is Your Birthday"): 8177665,
    ("New Kids On The Block", "The Block"): 5389424,
    # single representative-track entries; per-track credit assigned by hand
    # below (embedded artist name/feat. in our track string breaks matching)
    ("Profyle", "Nothin' But Drama"): 2145686,
    ("Various", "The Hurricane (Music From And Inspired By The Motion Picture)"): 6060412,
    ("Full Of Harmony", "W"): 4622263,
    ("John Legend", "Evolver"): 3190248,
    ("Dubb Union", "Snoop Dogg Presents: Dubb Union"): 2150011,
    ("Mihiro", "My Way"): 3432323,
    ("Bishop Lamont", "Pope Mobile"): 1325459,
    ("Red Velvet", "Summer Magic"): 12452121,
    ("Bobby Brown", "Like Bobby"): 12520201,
    ("Jazzy Amra", "Amra"): 12366568,  # Discogs only carries the digital single, and it has no credit data
    ("BLACKstreet feat. Mystikal", "Wizzy Wow"): 2069147,
    ("Benzino feat. Fabolous & G-Dep", "Boottee (Remix)"): 1307062,
    ("BLACKstreet feat. Beverly Crowder, Natural Blend", "Deep"): 13241706,
}


class RateLimiter:
    def __init__(self, authenticated):
        self.min_interval = 1.05 if authenticated else 2.5
        self.last = 0.0

    def wait(self):
        elapsed = time.time() - self.last
        if elapsed < self.min_interval:
            time.sleep(self.min_interval - elapsed)
        self.last = time.time()

    def note_headers(self, headers):
        remaining = headers.get("X-Discogs-Ratelimit-Remaining")
        if remaining is not None and int(remaining) <= 2:
            time.sleep(5)


def discogs_get(path, params, token, limiter):
    url = "https://api.discogs.com" + path
    if params:
        url += "?" + urllib.parse.urlencode(params)
    headers = {"User-Agent": UA}
    if token:
        headers["Authorization"] = f"Discogs token={token}"
    for attempt in range(6):
        limiter.wait()
        req = urllib.request.Request(url, headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=20) as r:
                limiter.note_headers(r.headers)
                return json.loads(r.read())
        except urllib.error.HTTPError as e:
            if e.code == 429:
                time.sleep(10 * (attempt + 1))
                continue
            if e.code == 404:
                return None
            if attempt == 5:
                raise
            time.sleep(5 * (attempt + 1))
        except (urllib.error.URLError, TimeoutError, ConnectionError, OSError) as e:
            # transient network blip (timeout, DNS hiccup, reset) — retry
            # with backoff instead of taking the whole run down with it
            if attempt == 5:
                raise
            time.sleep(5 * (attempt + 1))
    return None


def norm(s):
    s = s.lower()
    s = re.sub(r"feat\.?.*$", "", s)
    s = re.sub(r"[^a-z0-9]+", " ", s)
    return re.sub(r"\s+", " ", s).strip()


def similarity(a, b):
    return SequenceMatcher(None, norm(a), norm(b)).ratio()


def resolve_release_id(release, token, limiter):
    key = (release["artist"], release["title"])
    if key in KNOWN_IDS:
        return KNOWN_IDS[key], "known"

    query = f"{release['artist']} {release['title']}"
    data = discogs_get("/database/search", {"q": query, "type": "release"}, token, limiter)
    if not data or not data.get("results"):
        return None, "no-results"

    target = f"{release['artist']} {release['title']}"
    best, best_score = None, 0.0
    for r in data["results"][:10]:
        score = similarity(target, r.get("title", ""))
        if score > best_score:
            best, best_score = r, score
    if best and best_score >= MATCH_THRESHOLD:
        return best["id"], f"search({best_score:.2f})"
    return None, f"low-confidence(best={best_score:.2f})"


TRACK_PREFIX = re.compile(r"^\s*(?:cd\d+\s*)?\d{0,2}[\-.)]?\s*")


def track_key(t):
    t = re.sub(r"\.(flac|mp3)$", "", t, flags=re.I)
    t = re.sub(r"\(feat[^)]*\)", "", t, flags=re.I)
    t = TRACK_PREFIX.sub("", t)
    return norm(t)


def group_credits(extraartists):
    by_role = {}
    for a in extraartists:
        role = a.get("role", "").strip()
        name = re.sub(r"\s*\(\d+\)\s*$", "", a.get("name", "")).strip()  # "Luis Navarro (2)" -> "Luis Navarro"
        if not role or not name:
            continue
        by_role.setdefault(role, [])
        if name not in by_role[role]:
            by_role[role].append(name)
    return [f"{role}: {', '.join(names)}" for role, names in by_role.items()]


def fetch_credits_for_release(release, token, limiter, log):
    rid, how = resolve_release_id(release, token, limiter)
    if rid is None:
        log(f"  UNMATCHED  {release['year']} {release['artist']} - {release['title']}  ({how})")
        return None

    data = discogs_get(f"/releases/{rid}", None, token, limiter)
    if not data:
        log(f"  FETCH-FAIL {release['year']} {release['artist']} - {release['title']}  (id {rid})")
        return None

    tracklist = data.get("tracklist", [])
    by_key = {track_key(t.get("title", "")): t for t in tracklist if t.get("title")}
    release_level = data.get("extraartists", [])

    credits = []
    hits = 0
    for our_track in release["tracks"]:
        tk = track_key(our_track)
        entry = by_key.get(tk)
        if not entry:
            # fall back to best fuzzy match within this release's tracklist
            best_t, best_score = None, 0.0
            for cand_key, cand in by_key.items():
                s = SequenceMatcher(None, tk, cand_key).ratio()
                if s > best_score:
                    best_t, best_score = cand, s
            entry = best_t if best_score >= 0.75 else None
        # Older/vinyl-era Discogs entries often carry no per-track
        # extraartists at all — everything printed on the sleeve is
        # credited at the release level instead. That still genuinely
        # applies to every track on a single/EP, so use it rather than
        # report empty when real data exists just one level up — but
        # only once a specific track has actually been identified on
        # this release; a track we couldn't even locate on the
        # tracklist shouldn't inherit credits it can't be confirmed to
        # share.
        if entry:
            track_credits = group_credits(entry.get("extraartists") or release_level)
        else:
            track_credits = []
        if track_credits:
            hits += 1
        credits.append(track_credits)

    log(f"  ok ({how:>14})  {release['year']} {release['artist']} - {release['title']}  "
        f"[{hits}/{len(release['tracks'])} tracks credited]  discogs.com/release/{rid}")
    return credits


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--decade", help="only this decade key, e.g. 2010s")
    ap.add_argument("--limit", type=int, help="stop after N releases (this run)")
    ap.add_argument("--token", help="Discogs personal access token (optional; speeds up + raises rate limit)")
    args = ap.parse_args()

    data = json.loads(RELEASES_PATH.read_text(encoding="utf-8"))
    limiter = RateLimiter(authenticated=bool(args.token))

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
            if "credits" in release:
                stats["skipped"] += 1
                continue
            if args.limit and processed >= args.limit:
                log(f"--limit {args.limit} reached, stopping")
                save()
                print_summary(stats)
                return

            credits = fetch_credits_for_release(release, args.token, limiter, log)
            processed += 1
            if credits is not None:
                release["credits"] = credits
                stats["matched"] += 1
            else:
                release["credits"] = []  # mark as attempted so we don't retry every run
                stats["unmatched"] += 1
            save()

    print_summary(stats)


def print_summary(stats):
    print(f"\nmatched: {stats['matched']}  unmatched: {stats['unmatched']}  "
          f"already had credits (skipped): {stats['skipped']}")


if __name__ == "__main__":
    main()
