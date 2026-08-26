#!/usr/bin/env python3
"""Self-hosted visit tracker for the site — stdlib only, SQLite storage.

Serves the static site AND collects analytics, so there is no third party
involved and nothing leaves the machine.

Privacy design (this is what keeps it lawful in the EU without a cookie
banner, and it is deliberate — please do not "improve" it by storing more):
  * no cookies, no localStorage, nothing persisted on the visitor's device
  * raw IP addresses are never written to disk. A visitor id is
    sha256(ip + user-agent + site-salt + today) truncated - it rotates at
    midnight, so it cannot be used to follow someone across days
  * location is coarse: the browser's IANA timezone and language, not GPS
    and not an IP-geolocation lookup
  * the Geolocation API is never called

    python3 tracker/server.py [--port 8765] [--site .] [--db tracker/analytics.db]
"""
import argparse, hashlib, json, os, re, sqlite3, secrets, sys, time
from datetime import datetime, timezone, timedelta
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from urllib.parse import urlparse, parse_qs

ROOT = os.path.dirname(os.path.abspath(__file__))
SALT_FILE = os.path.join(ROOT, ".salt")

SCHEMA = """
CREATE TABLE IF NOT EXISTS events (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  ts        INTEGER NOT NULL,
  day       TEXT    NOT NULL,
  hour      INTEGER NOT NULL,
  visitor   TEXT    NOT NULL,
  session   TEXT    NOT NULL,
  type      TEXT    NOT NULL,
  path      TEXT,
  referrer  TEXT,
  tz        TEXT,
  lang      TEXT,
  device    TEXT,
  viewport  TEXT,
  dwell_ms  INTEGER,
  scroll_pc INTEGER,
  target    TEXT
);
CREATE INDEX IF NOT EXISTS idx_day  ON events(day);
CREATE INDEX IF NOT EXISTS idx_path ON events(path);
CREATE INDEX IF NOT EXISTS idx_type ON events(type);
"""

def site_salt():
    """Persistent random salt: without it, visitor hashes would be guessable."""
    if os.path.exists(SALT_FILE):
        return open(SALT_FILE).read().strip()
    s = secrets.token_hex(32)
    with open(SALT_FILE, "w") as f:
        f.write(s)
    os.chmod(SALT_FILE, 0o600)
    return s

SALT = site_salt()

def visitor_id(ip, ua, day):
    return hashlib.sha256(f"{ip}|{ua}|{SALT}|{day}".encode()).hexdigest()[:16]

def db_connect(path):
    con = sqlite3.connect(path, check_same_thread=False)
    con.executescript(SCHEMA)
    con.commit()
    return con

def device_of(viewport):
    try:
        w = int(str(viewport).split("x")[0])
    except Exception:
        return "unknown"
    return "mobile" if w < 768 else "tablet" if w < 1024 else "desktop"

def clean_path(p):
    p = (p or "/").split("?")[0].split("#")[0]
    return p[:200]

def clean_ref(r):
    if not r:
        return "(direct)"
    try:
        h = urlparse(r).netloc.lower()
    except Exception:
        return "(unknown)"
    return "(direct)" if not h else h[:120]


# ----------------------------------------------------------------- queries
def stats(con, days=30):
    since = (datetime.now(timezone.utc) - timedelta(days=days)).strftime("%Y-%m-%d")
    q = lambda s, *a: con.execute(s, a).fetchall()
    row = lambda s, *a: con.execute(s, a).fetchone()

    totals = row("""SELECT COUNT(*) FILTER (WHERE type='pageview'),
                           COUNT(DISTINCT visitor), COUNT(DISTINCT session)
                    FROM events WHERE day >= ?""", since)
    # a session with a single pageview and no meaningful dwell = bounce
    bounce = row("""SELECT
        CAST(SUM(CASE WHEN views=1 THEN 1 ELSE 0 END) AS FLOAT)/NULLIF(COUNT(*),0)*100
        FROM (SELECT session, COUNT(*) views FROM events
              WHERE type='pageview' AND day >= ? GROUP BY session)""", since)
    med = row("""SELECT AVG(dwell_ms) FROM events
                 WHERE type='exit' AND dwell_ms > 0 AND day >= ?""", since)
    return {
        "range_days": days,
        "pageviews":  totals[0] or 0,
        "visitors":   totals[1] or 0,
        "sessions":   totals[2] or 0,
        "bounce_pc":  round(bounce[0] or 0, 1),
        "avg_dwell_s": round((med[0] or 0) / 1000, 1),
        "by_day":     [{"day": d, "views": v, "visitors": u}
                       for d, v, u in q("""SELECT day, COUNT(*), COUNT(DISTINCT visitor)
                                           FROM events WHERE type='pageview' AND day >= ?
                                           GROUP BY day ORDER BY day""", since)],
        "by_hour":    [{"hour": h, "views": v}
                       for h, v in q("""SELECT hour, COUNT(*) FROM events
                                        WHERE type='pageview' AND day >= ?
                                        GROUP BY hour ORDER BY hour""", since)],
        "pages":      [{"path": p, "views": v, "visitors": u,
                        "avg_dwell_s": round((d or 0)/1000, 1), "avg_scroll": round(s or 0)}
                       for p, v, u, d, s in q("""
                           SELECT e.path, COUNT(*), COUNT(DISTINCT e.visitor),
                                  (SELECT AVG(dwell_ms) FROM events x
                                    WHERE x.type='exit' AND x.path=e.path AND x.day>=?),
                                  (SELECT AVG(scroll_pc) FROM events x
                                    WHERE x.type='exit' AND x.path=e.path AND x.day>=?)
                           FROM events e WHERE e.type='pageview' AND e.day >= ?
                           GROUP BY e.path ORDER BY COUNT(*) DESC LIMIT 40""",
                           since, since, since)],
        "referrers":  [{"ref": r, "views": v} for r, v in q("""
                           SELECT referrer, COUNT(*) FROM events
                           WHERE type='pageview' AND day >= ?
                           GROUP BY referrer ORDER BY COUNT(*) DESC LIMIT 20""", since)],
        "timezones":  [{"tz": t, "visitors": u} for t, u in q("""
                           SELECT tz, COUNT(DISTINCT visitor) FROM events
                           WHERE day >= ? AND tz IS NOT NULL AND tz <> ''
                           GROUP BY tz ORDER BY 2 DESC LIMIT 20""", since)],
        "languages":  [{"lang": l, "visitors": u} for l, u in q("""
                           SELECT lang, COUNT(DISTINCT visitor) FROM events
                           WHERE day >= ? AND lang IS NOT NULL AND lang <> ''
                           GROUP BY lang ORDER BY 2 DESC LIMIT 15""", since)],
        "devices":    [{"device": d, "visitors": u} for d, u in q("""
                           SELECT device, COUNT(DISTINCT visitor) FROM events
                           WHERE day >= ? GROUP BY device ORDER BY 2 DESC""", since)],
        "clicks":     [{"target": t, "n": n} for t, n in q("""
                           SELECT target, COUNT(*) FROM events
                           WHERE type='click' AND day >= ? AND target IS NOT NULL
                           GROUP BY target ORDER BY 2 DESC LIMIT 25""", since)],
    }


class Handler(SimpleHTTPRequestHandler):
    con = None
    site = "."

    def log_message(self, fmt, *args):      # keep the console readable
        if "/api/" not in (self.path or ""):
            return
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))

    def translate_path(self, path):
        rel = urlparse(path).path.lstrip("/")
        full = os.path.normpath(os.path.join(self.site, rel))
        if not full.startswith(os.path.abspath(self.site)):
            return self.site                      # refuse traversal
        return full

    # ---------------------------------------------------------------- POST
    def do_POST(self):
        if urlparse(self.path).path != "/api/collect":
            self.send_error(404); return
        try:
            n = int(self.headers.get("Content-Length") or 0)
            if n > 8192:
                self.send_error(413); return
            ev = json.loads(self.rfile.read(n) or b"{}")
        except Exception:
            self.send_error(400); return

        now = datetime.now(timezone.utc)
        day = now.strftime("%Y-%m-%d")
        ip = (self.headers.get("X-Forwarded-For", "").split(",")[0].strip()
              or self.client_address[0])
        ua = self.headers.get("User-Agent", "")[:400]
        vp = str(ev.get("viewport", ""))[:16]

        try:
            self.con.execute("""INSERT INTO events
                (ts,day,hour,visitor,session,type,path,referrer,tz,lang,device,
                 viewport,dwell_ms,scroll_pc,target)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)""", (
                int(now.timestamp()*1000), day, now.hour,
                visitor_id(ip, ua, day), str(ev.get("sid",""))[:32],
                str(ev.get("type","pageview"))[:16],
                clean_path(ev.get("path")), clean_ref(ev.get("ref")),
                str(ev.get("tz",""))[:64], str(ev.get("lang",""))[:16],
                device_of(vp), vp,
                int(ev.get("dwell") or 0), int(ev.get("scroll") or 0),
                (str(ev.get("target"))[:200] if ev.get("target") else None)))
            self.con.commit()
        except Exception as e:
            sys.stderr.write(f"collect error: {e}\n")
        self.send_response(204)
        self.send_header("Content-Length", "0")
        self.end_headers()

    # ----------------------------------------------------------------- GET
    def do_GET(self):
        p = urlparse(self.path)
        if p.path == "/api/stats":
            days = int((parse_qs(p.query).get("days") or [30])[0])
            body = json.dumps(stats(self.con, days)).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers(); self.wfile.write(body); return
        if p.path in ("/dashboard", "/dashboard/"):
            self.path = "/tracker/dashboard.html"
        return super().do_GET()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--port", type=int, default=8765)
    ap.add_argument("--site", default=os.path.dirname(ROOT) or ".")
    ap.add_argument("--db",   default=os.path.join(ROOT, "analytics.db"))
    a = ap.parse_args()
    Handler.con = db_connect(a.db)
    Handler.site = os.path.abspath(a.site)
    srv = ThreadingHTTPServer(("", a.port), Handler)
    print(f"site      http://localhost:{a.port}/")
    print(f"dashboard http://localhost:{a.port}/dashboard")
    print(f"database  {a.db}")
    srv.serve_forever()

if __name__ == "__main__":
    main()
