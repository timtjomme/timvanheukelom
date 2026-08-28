/* ---------------------------------------------------------------------------
   Everything the pages need beyond plain HTML, in one file: counting numbers,
   click-to-play video, and a gallery lightbox. No jQuery, no libraries.

   All three are progressive enhancements — with this file blocked the numbers
   still read correctly, the videos still link out, and photos still open on
   their own.
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
		document.body.appendChild(box);
	}

	function show(i) {
		index = (i + group.length) % group.length;
		var a = group[index];
		pic.src = a.href;
		pic.alt = (a.querySelector("img") || {}).alt || "";
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
