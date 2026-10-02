/* ---------------------------------------------------------------------------
   Everything the pages need beyond plain HTML, in one file: counting numbers,
   click-to-play video, a gallery lightbox, and a hidden way through to the
   Teddy Riley archive. No jQuery, no libraries.

   All four are progressive enhancements — with this file blocked the numbers
   still read correctly, the videos still link out, photos still open on
   their own, and the archive is still there at /tr/.
--------------------------------------------------------------------------- */
(function () {
	"use strict";

	var slow = matchMedia("(prefers-reduced-motion: reduce)").matches;

	/* -- the counters: ring draws itself while the number counts up ------ */
	var numbers = document.querySelectorAll("[data-count]");
	if (numbers.length && !slow) {
		/* arm them first — the ring's resting state is "finished", so a reader
		   without JavaScript sees a complete ring rather than an empty one */
		numbers.forEach(function (el) {
			var ring = el.closest(".counter");
			if (ring) ring.classList.add("is-armed");
		});

		if ("IntersectionObserver" in window) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) {
					if (!e.isIntersecting) return;
					io.unobserve(e.target);
					run(e.target);
				});
			}, { threshold: .4 });
			numbers.forEach(function (el) { io.observe(el); });
		} else {
			numbers.forEach(run);
		}
	}

	function run(el) {
		var ring = el.closest(".counter");
		if (ring) {
			// read the armed value back so the browser has a start point to
			// transition from, even if is-on lands in the same frame
			getComputedStyle(ring.querySelector(".ring-bar")).strokeDashoffset;
			ring.classList.add("is-on");
		}

		// site.css owns the duration; ease-out cubic here is the same curve as
		// the cubic-bezier(.33, 1, .68, 1) on the ring, so the two stay in step
		var ms = ring ? parseFloat(getComputedStyle(ring).getPropertyValue("--count-ms")) : 0;
		if (!ms) ms = 3400;

		var target = +el.dataset.count;
		var t0 = performance.now();
		(function step(now) {
			var k = Math.min((now - t0) / ms, 1);
			el.textContent = Math.round(target * (1 - Math.pow(1 - k, 3))).toLocaleString("nl-NL");
			if (k < 1) requestAnimationFrame(step);
		})(t0);
	}

	/* -- video: swap the poster for the player only when asked ----------- */
	document.querySelectorAll(".video[data-vimeo]").forEach(function (box) {
		box.addEventListener("click", function () {
			var f = document.createElement("iframe");
			f.src = "https://player.vimeo.com/video/" + box.dataset.vimeo +
				"?autoplay=1&title=0&byline=0&portrait=0&dnt=1";
			f.allow = "autoplay; fullscreen; picture-in-picture";
			f.allowFullscreen = true;
			f.title = "Video";
			box.replaceChildren(f);
		}, { once: true });
	});

	/* -- the archive: type "tr" and a menu button for /tr/ appears ---------- */
	/* Nothing on the page hints at it. The button is built here instead of
	   being written into every page's header, and only shows once the two
	   letters are typed within 1.5 seconds of each other. Form fields don't
	   count (a comment about a "trip" would trigger it) and neither do
	   shortcuts like Cmd+T and Cmd+R. */
	var lastKey = "", lastAt = 0, archive;

	document.addEventListener("keydown", function (e) {
		// e.key is missing on the synthetic keydown Chrome fires for autofill
		if (!e.key || e.key.length !== 1 || e.ctrlKey || e.metaKey || e.altKey) return;
		var t = e.target;
		if (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) return;

		var key = e.key.toLowerCase(), now = Date.now();
		if (key === "r" && lastKey === "t" && now - lastAt < 1500) reveal();
		lastKey = key;
		lastAt = now;
	});

	function reveal() {
		var nav = document.querySelector(".topbar-nav");
		if (!nav || archive) return;

		archive = document.createElement("a");
		archive.className = "topbar-extra";
		archive.href = (document.body.dataset.root || "") + "tr/";
		archive.title = "Teddy Riley";
		archive.setAttribute("aria-label", "Teddy Riley");
		archive.rel = "nofollow";   // the one on-site link to it; don't hand crawlers a path in
		archive.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
			'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
			'<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
		nav.prepend(archive);

		// read it back at opacity 0 so the fade has a start point
		getComputedStyle(archive).opacity;
		archive.classList.add("is-on");
	}

	/* -- lightbox -------------------------------------------------------- */
	var groups = [];
	document.querySelectorAll(".gallery").forEach(function (g) {
		var links = [].slice.call(g.querySelectorAll("a[href]"));
		if (links.length) groups.push(links);
	});
	if (!groups.length) return;

	var box, pic, group, index;

	function open(links, i) {
		group = links;
		if (!box) build();
		document.body.classList.add("is-locked");
		box.hidden = false;
		show(i);
	}

	function build() {
		box = document.createElement("div");
		box.className = "lightbox";
		box.hidden = true;
		box.innerHTML =
			'<img alt="">' +
			'<button class="lb-close" aria-label="Sluiten">&times;</button>' +
			'<button class="lb-prev" aria-label="Vorige">&lsaquo;</button>' +
			'<button class="lb-next" aria-label="Volgende">&rsaquo;</button>';
		pic = box.querySelector("img");
		box.querySelector(".lb-close").addEventListener("click", close);
		box.querySelector(".lb-prev").addEventListener("click", function (e) { e.stopPropagation(); show(index - 1); });
		box.querySelector(".lb-next").addEventListener("click", function (e) { e.stopPropagation(); show(index + 1); });
		box.addEventListener("click", function (e) { if (e.target === box || e.target === pic) close(); });

		// swipe sideways to step through the photos (a pinch has two fingers and is ignored)
		var x0 = null, y0 = 0;
		box.addEventListener("touchstart", function (e) {
			if (e.touches.length !== 1) { x0 = null; return; }
			x0 = e.touches[0].clientX;
			y0 = e.touches[0].clientY;
		}, { passive: true });
		box.addEventListener("touchend", function (e) {
			if (x0 === null) return;
			var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
			x0 = null;
			if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) show(index + (dx < 0 ? 1 : -1));
		}, { passive: true });

		document.body.appendChild(box);
	}

	function show(i) {
		index = (i + group.length) % group.length;
		var a = group[index];
		pic.src = a.href;
		pic.alt = (a.querySelector("img") || {}).alt || "";
		// warm the neighbours so stepping through a long gallery doesn't wait on the network
		[1, -1].forEach(function (d) { new Image().src = group[(index + d + group.length) % group.length].href; });
	}

	function close() {
		box.hidden = true;
		pic.removeAttribute("src");
		document.body.classList.remove("is-locked");
	}

	document.addEventListener("keydown", function (e) {
		if (!box || box.hidden) return;
		if (e.key === "Escape") close();
		if (e.key === "ArrowLeft") show(index - 1);
		if (e.key === "ArrowRight") show(index + 1);
	});

	groups.forEach(function (links) {
		links.forEach(function (a, i) {
			a.addEventListener("click", function (e) {
				if (e.metaKey || e.ctrlKey || e.shiftKey || e.button) return;
				e.preventDefault();
				open(links, i);
			});
		});
	});
})();
