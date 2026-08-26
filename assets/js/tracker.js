/*  Self-hosted visit tracker — client half.
 *
 *  Sends: page view, time on page, scroll depth, link clicks, coarse locale.
 *  Never sends: anything identifying. No cookies, no localStorage, no
 *  Geolocation API. The session id lives in sessionStorage only, so it dies
 *  with the tab and cannot follow anyone between visits.
 */
(function () {
	"use strict";

	var ENDPOINT = "/api/collect";

	// Honour the browser's own opt-out.
	if (navigator.doNotTrack === "1" || window.doNotTrack === "1" ||
	    navigator.globalPrivacyControl === true) return;

	function sessionId() {
		try {
			var k = "_vt_sid", v = sessionStorage.getItem(k);
			if (!v) {
				v = (Date.now().toString(36) + Math.random().toString(36).slice(2, 10));
				sessionStorage.setItem(k, v);
			}
			return v;
		} catch (e) { return "nosession"; }   // private mode: still count the view
	}

	var sid    = sessionId(),
	    start  = Date.now(),
	    maxPc  = 0,
	    sent   = false,
	    active = 0,          // ms the tab was actually visible
	    lastOn = Date.now();

	function base(type) {
		return {
			type: type,
			sid: sid,
			path: location.pathname,
			ref: document.referrer || "",
			// coarse location: an IANA zone like "Europe/Amsterdam", never GPS
			tz: (function () {
				try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ""; }
				catch (e) { return ""; }
			})(),
			lang: (navigator.language || "").slice(0, 16),
			viewport: window.innerWidth + "x" + window.innerHeight
		};
	}

	function send(payload, useBeacon) {
		var body = JSON.stringify(payload);
		if (useBeacon && navigator.sendBeacon) {
			navigator.sendBeacon(ENDPOINT, new Blob([body], { type: "application/json" }));
		} else {
			fetch(ENDPOINT, {
				method: "POST", body: body, keepalive: true,
				headers: { "Content-Type": "application/json" }
			}).catch(function () {});
		}
	}

	/* ---------------------------------------------------------- page view */
	send(base("pageview"), false);

	/* ------------------------------------------------------- scroll depth */
	function onScroll() {
		var h = document.documentElement.scrollHeight - window.innerHeight;
		var pc = h > 0 ? Math.round((window.scrollY / h) * 100) : 100;
		if (pc > maxPc) maxPc = Math.min(100, Math.max(0, pc));
	}
	addEventListener("scroll", onScroll, { passive: true });
	onScroll();

	/* ------------------------------------------- visible time, not wall time */
	function visChange() {
		if (document.visibilityState === "hidden") {
			active += Date.now() - lastOn;
			flush();                       // tab hidden may mean tab closed
		} else {
			lastOn = Date.now();
		}
	}
	addEventListener("visibilitychange", visChange);

	/* -------------------------------------------------------- link clicks */
	addEventListener("click", function (e) {
		var a = e.target && e.target.closest && e.target.closest("a[href]");
		if (!a) return;
		var href = a.getAttribute("href") || "";
		if (!href || href.charAt(0) === "#" || /^(javascript|mailto):/i.test(href)) return;
		var ev = base("click");
		ev.target = href.slice(0, 200);
		send(ev, true);
	}, true);

	/* ---------------------------------------------------- dwell on the way out */
	function flush() {
		if (sent) return;
		sent = true;
		var visible = active + (document.visibilityState === "visible"
			? Date.now() - lastOn : 0);
		var ev = base("exit");
		ev.dwell = Math.min(visible, Date.now() - start);
		ev.scroll = maxPc;
		send(ev, true);
	}
	addEventListener("pagehide", flush);
	addEventListener("beforeunload", flush);
})();
