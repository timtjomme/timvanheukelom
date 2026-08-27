/* ---------------------------------------------------------------------------
   Comment thread: presentation + the live form.

   The 122 historical comments are baked into the HTML by the mirror. This
   script polishes those, then loads any newly approved comments from
   comments/list.php and appends them, and replaces the old WordPress form
   with one that posts to comments/post.php.

   Replacing the form matters: the original still pointed at
   https://www.timvanheukelom.nl/wp-comments-post.php, so submitting it from
   this copy would file a comment on the live WordPress site.

   Avatars: of the 32 people who commented, only 2 had a real Gravatar. Those
   two are served from assets/img/avatars/; everyone else gets a coloured disc
   with their initial. No requests to gravatar.com, so the thread works
   offline and no reader's IP is handed to Automattic.
--------------------------------------------------------------------------- */
(function () {
	"use strict";

	var root = document.querySelector(".fl-comments");
	if (!root) return;

	// -------------------------------------------------------------- helpers
	var OWNER = /^tim( van heukelom)?$/i;   // gets an "auteur" badge

	var PALETTE = [
		["#e8eefb", "#2f6ac0"], ["#e9f4ec", "#2f7d4f"], ["#fdeeea", "#c2542f"],
		["#f3ecfa", "#6b46a8"], ["#fdf4e3", "#a37211"], ["#e6f4f6", "#1f7a86"],
		["#fbecf3", "#b0417a"], ["#eef0f3", "#4a5568"]
	];

	function hash(s) {
		var h = 0;
		for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
		return h;
	}

	function initialOf(name) {
		var m = (name || "").trim().match(/[\p{L}\p{N}]/u);
		return m ? m[0].toUpperCase() : "?";
	}

	function avatarFor(name) {
		var pair = PALETTE[hash(name.toLowerCase()) % PALETTE.length];
		var el = document.createElement("span");
		el.className = "initial-avatar";
		el.style.background = pair[0];
		el.style.color = pair[1];
		el.textContent = initialOf(name);
		el.setAttribute("aria-hidden", "true");
		return el;
	}

	var MONTHS = ["januari","februari","maart","april","mei","juni",
	              "juli","augustus","september","oktober","november","december"];

	// "8 jaar geleden" reads better than a 2016 date; the exact date stays in
	// the tooltip so nothing is actually lost.
	function relative(d) {
		var s = (Date.now() - d.getTime()) / 1000;
		if (s < 60)      return "zojuist";
		if (s < 3600)    return Math.floor(s / 60) + " min geleden";
		if (s < 86400)   return Math.floor(s / 3600) + " uur geleden";
		if (s < 2592000) return Math.floor(s / 86400) + " dagen geleden";
		if (s < 31536000) {
			var m = Math.floor(s / 2592000);
			return m + (m === 1 ? " maand geleden" : " maanden geleden");
		}
		var y = Math.floor(s / 31536000);
		return y + (y === 1 ? " jaar geleden" : " jaar geleden");
	}

	function exact(d) {
		return d.getDate() + " " + MONTHS[d.getMonth()] + " " + d.getFullYear();
	}

	// The theme prints "op oktober 13, 2016 om 1:16 pm" — parse it back out.
	function parseDutch(text) {
		var t = text.replace(/^op\s+/i, "").replace(/\s+om\s+.*$/i, "").trim();
		var m = t.match(/^([a-zé]+)\s+(\d{1,2}),\s*(\d{4})$/i);
		if (m) {
			var mi = MONTHS.indexOf(m[1].toLowerCase());
			if (mi >= 0) return new Date(+m[3], mi, +m[2]);
		}
		m = t.match(/^(\d{1,2})\s+([a-zé]+)\s+(\d{4})$/i);
		if (m) {
			var mi2 = MONTHS.indexOf(m[2].toLowerCase());
			if (mi2 >= 0) return new Date(+m[3], mi2, +m[1]);
		}
		return null;
	}

	function stamp(el, date) {
		if (!date || isNaN(date)) return;
		el.textContent = relative(date);
		el.title = exact(date);
		el.setAttribute("datetime", date.toISOString());
	}

	// --------------------------------------------------- polish what's there
	function decorate(body) {
		var holder = body.querySelector(".comment-avatar");
		var nameEl = body.querySelector(".comment-author-link");
		var name = nameEl ? nameEl.textContent.trim() : "";

		if (holder && name && !holder.querySelector("img") && !holder.querySelector(".initial-avatar")) {
			holder.innerHTML = "";
			holder.appendChild(avatarFor(name));
		}
		if (holder) {
			var img = holder.querySelector("img");
			if (img) img.addEventListener("error", function () {
				holder.innerHTML = "";
				holder.appendChild(avatarFor(name));
			});
		}
		if (name && OWNER.test(name) && !body.querySelector(".author-badge")) {
			var b = document.createElement("span");
			b.className = "author-badge";
			b.textContent = "auteur";
			nameEl.insertAdjacentElement("afterend", b);
		}
		var date = body.querySelector(".comment-date");
		if (date && !date.dataset.done) {
			date.dataset.done = "1";
			stamp(date, parseDutch(date.textContent));
		}
	}

	root.querySelectorAll(".comment-body").forEach(decorate);

	// put the count in the heading as a chip
	var title = root.querySelector(".fl-comments-list-title");
	if (title) {
		var n = root.querySelectorAll("li.comment").length;
		title.innerHTML = '<span class="c-count">' + n + "</span> " +
			(n === 1 ? "reactie" : "reacties");
	}

	// ------------------------------------------------------------ post slug
	var slug = location.pathname.replace(/^\/+/, "").replace(/\.html?$/, "")
		.replace(/[^a-z0-9._-]+/gi, "-").replace(/^-+|-+$/g, "") || "index";

	var base = (function () {
		var s = document.querySelector('script[src$="assets/js/comments.js"]');
		return s ? s.src.replace(/assets\/js\/comments\.js.*$/, "") : "/";
	})();

	// --------------------------------------------- newly approved comments
	var list = root.querySelector("ol#comments");
	function renderNew(c) {
		var li = document.createElement("li");
		li.className = "comment depth-1 is-new";
		var d = new Date(c.t);
		li.innerHTML =
			'<div class="comment-body">' +
				'<span class="comment-avatar"></span>' +
				'<div class="comment-meta">' +
					'<span class="comment-author-link"></span> ' +
					'<time class="comment-date"></time>' +
				'</div>' +
				'<div class="comment-content"><p></p></div>' +
			'</div>';
		li.querySelector(".comment-author-link").textContent = c.name;
		li.querySelector(".comment-content p").textContent = c.body;
		stamp(li.querySelector(".comment-date"), d);
		li.querySelector(".comment-avatar").appendChild(avatarFor(c.name));
		if (OWNER.test(c.name)) {
			var b = document.createElement("span");
			b.className = "author-badge";
			b.textContent = "auteur";
			li.querySelector(".comment-author-link").insertAdjacentElement("afterend", b);
		}
		return li;
	}

	if (list) {
		fetch(base + "comments/list.php?post=" + encodeURIComponent(slug))
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (j) {
				if (!j || !j.comments || !j.comments.length) return;
				j.comments.forEach(function (c) { list.appendChild(renderNew(c)); });
				if (title) {
					var n = root.querySelectorAll("li.comment").length;
					title.innerHTML = '<span class="c-count">' + n + "</span> " +
						(n === 1 ? "reactie" : "reacties");
				}
			})
			.catch(function () { /* static host, no PHP — the baked-in thread still shows */ });
	}

	// -------------------------------------------------------------- the form
	var respond = root.querySelector("#respond") ||
	              document.querySelector(".fl-comments ~ #respond") ||
	              document.querySelector("#respond");
	if (!respond) return;

	var opened = Date.now();
	respond.className = "comment-respond";
	respond.innerHTML =
		'<h3 class="comment-reply-title">Laat een reactie achter</h3>' +
		'<form class="c-form" novalidate>' +
			'<div class="c-field">' +
				'<textarea name="body" id="c-body" rows="4" maxlength="4000" ' +
					'placeholder=" " required></textarea>' +
				'<label for="c-body">Je reactie</label>' +
				'<span class="c-count-chars">0 / 4000</span>' +
			'</div>' +
			'<div class="c-row">' +
				'<div class="c-field c-field-name">' +
					'<input type="text" name="name" id="c-name" maxlength="60" ' +
						'placeholder=" " autocomplete="name" required>' +
					'<label for="c-name">Je naam</label>' +
				'</div>' +
				'<button type="submit" class="c-submit">Plaatsen</button>' +
			'</div>' +
			// honeypot: hidden from people, irresistible to bots
			'<div class="c-hp" aria-hidden="true">' +
				'<label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>' +
			'</div>' +
			'<p class="c-status" role="status"></p>' +
			'<p class="c-privacy">Alleen je naam en je bericht worden opgeslagen — ' +
				'geen e-mailadres, geen cookies. Reacties verschijnen na goedkeuring.</p>' +
		'</form>';

	var form   = respond.querySelector("form");
	var bodyEl = respond.querySelector("#c-body");
	var nameEl = respond.querySelector("#c-name");
	var status = respond.querySelector(".c-status");
	var button = respond.querySelector(".c-submit");
	var counter = respond.querySelector(".c-count-chars");

	bodyEl.addEventListener("input", function () {
		counter.textContent = bodyEl.value.length + " / 4000";
	});

	function say(msg, kind) {
		status.textContent = msg;
		status.className = "c-status" + (kind ? " is-" + kind : "");
	}

	form.addEventListener("submit", function (e) {
		e.preventDefault();
		if (!nameEl.value.trim())  { say("Vul even je naam in.", "error"); nameEl.focus(); return; }
		if (bodyEl.value.trim().length < 2) { say("Schrijf even een berichtje.", "error"); bodyEl.focus(); return; }

		button.disabled = true;
		button.classList.add("is-busy");
		say("Versturen…");

		fetch(base + "comments/post.php", {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({
				post: slug,
				name: nameEl.value.trim(),
				body: bodyEl.value.trim(),
				website: form.website.value,
				elapsed: Math.round((Date.now() - opened) / 1000)
			})
		})
		.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
		.then(function (res) {
			if (res.ok && res.j.ok) {
				form.reset();
				counter.textContent = "0 / 4000";
				say(res.j.message || "Bedankt! Je reactie is verstuurd.", "ok");
			} else {
				say(res.j.error || "Er ging iets mis. Probeer het nog eens.", "error");
			}
		})
		.catch(function () {
			say("Reageren werkt alleen op de live site (dit is een offline kopie).", "error");
		})
		.then(function () {
			button.disabled = false;
			button.classList.remove("is-busy");
		});
	});
})();
