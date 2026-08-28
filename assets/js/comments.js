/* ---------------------------------------------------------------------------
   Comment thread.

   The 122 historical comments are plain HTML in the page. This adds what
   HTML cannot do on its own: pull in comments approved since the page was
   built (comments/list.php), post a new one (comments/post.php), and wire up
   the "Reageer" pill so it fills the form's hidden parent field rather than
   just being decorative. The form itself is in the markup and floats its
   labels with pure CSS (an empty placeholder plus :not(:placeholder-shown)),
   so it is fully visible and usable with JavaScript off — it simply cannot
   submit.

   Avatars are a coloured disc with an initial, drawn locally. Nothing is
   fetched from gravatar.com, so no reader's IP goes to a third party.
--------------------------------------------------------------------------- */
(function () {
	"use strict";

	var root = document.querySelector(".comments");
	if (!root) return;

	var OWNER = /^tim( van heukelom)?$/i;
	var PALETTE = [
		["#e8eefb", "#2f6ac0"], ["#e9f4ec", "#2f7d4f"], ["#fdeeea", "#c2542f"],
		["#f3ecfa", "#6b46a8"], ["#fdf4e3", "#a37211"], ["#e6f4f6", "#1f7a86"],
		["#fbecf3", "#b0417a"], ["#eef0f3", "#4a5568"]
	];
	var MONTHS = ["januari", "februari", "maart", "april", "mei", "juni",
		"juli", "augustus", "september", "oktober", "november", "december"];

	function avatar(name) {
		var h = 0, i;
		for (i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
		var pair = PALETTE[h % PALETTE.length];
		var m = name.trim().match(/[\p{L}\p{N}]/u);
		var el = document.createElement("span");
		el.className = "c-avatar";
		el.style.background = pair[0];
		el.style.color = pair[1];
		el.textContent = m ? m[0].toUpperCase() : "?";
		el.setAttribute("aria-hidden", "true");
		return el;
	}

	function badge() {
		var b = document.createElement("span");
		b.className = "c-badge";
		b.textContent = "auteur";
		return b;
	}

	/* "8 jaar geleden" reads better than a bare 2016 date; the exact date
	   stays in the tooltip, so nothing is lost. */
	function ago(d) {
		var s = (Date.now() - d.getTime()) / 1000;
		if (s < 3600) return Math.max(1, Math.floor(s / 60)) + " min geleden";
		if (s < 86400) return Math.floor(s / 3600) + " uur geleden";
		if (s < 2592000) return Math.floor(s / 86400) + " dagen geleden";
		if (s < 31536000) {
			var m = Math.floor(s / 2592000);
			return m + (m === 1 ? " maand geleden" : " maanden geleden");
		}
		return Math.floor(s / 31536000) + " jaar geleden";
	}

	function stamp(el, d) {
		if (!d || isNaN(d)) return;
		el.textContent = ago(d);
		el.title = d.getDate() + " " + MONTHS[d.getMonth()] + " " + d.getFullYear();
	}

	function decorate(li) {
		var name = li.dataset.author || "";
		li.querySelector(".c-avatar-slot").appendChild(avatar(name));
		if (OWNER.test(name)) li.querySelector(".c-name").after(badge());
		var t = li.querySelector(".c-date[datetime]");
		if (t) stamp(t, new Date(t.dateTime));
	}

	root.querySelectorAll(".c-item").forEach(decorate);

	function retally() {
		var n = root.querySelectorAll(".c-item").length;
		var chip = root.querySelector(".c-count");
		var title = root.querySelector(".c-title");
		if (chip) chip.textContent = n;
		if (title) {
			var tail = title.lastChild;
			if (tail && tail.nodeType === 3) tail.textContent = " " + (n === 1 ? "reactie" : "reacties");
		}
	}

	/* ------------------------------------------------ newly approved ones */
	var slug = (document.body.dataset.post || "").trim();
	var base = document.body.dataset.root || "";
	var list = root.querySelector(".c-list");

	function render(c) {
		var li = document.createElement("li");
		li.className = "c-item is-new";
		li.id = "c" + (c.id || "");
		li.dataset.author = c.name;
		li.innerHTML =
			'<span class="c-avatar-slot"></span>' +
			'<p class="c-meta"><span class="c-name"></span> <time class="c-date"></time></p>' +
			'<div class="c-body"><p></p></div>' +
			'<button type="button" class="c-reply" data-parent="' + (c.id || "") + '" ' +
				'data-name="' + c.name.replace(/"/g, "&quot;") + '">Reageer</button>';
		li.querySelector(".c-name").textContent = c.name;
		li.querySelector(".c-body p").textContent = c.body;
		stamp(li.querySelector(".c-date"), new Date(c.t));
		decorate(li);
		return li;
	}

	if (list && slug) {
		fetch(base + "comments/list.php?post=" + encodeURIComponent(slug))
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (j) {
				if (!j || !j.comments || !j.comments.length) return;
				j.comments.forEach(function (c) { list.appendChild(render(c)); });
				retally();
			})
			.catch(function () { /* no PHP here — the thread in the page still shows */ });
	}

	/* ---------------------------------------------------------- the form */
	var respond = root.querySelector(".c-respond");
	var form = root.querySelector(".c-form");
	if (!form) return;

	var opened = Date.now();
	var status = form.querySelector(".c-status");
	var submit = form.querySelector(".c-submit");
	var body = form.elements.body;
	var name = form.elements.name;
	var parentField = form.elements.parent;
	var counter = form.querySelector(".c-count-chars");
	var replying = root.querySelector(".c-replying");
	var replyingName = replying ? replying.querySelector("b") : null;

	body.addEventListener("input", function () {
		counter.textContent = body.value.length + " / 4000";
	});

	/* clicking "Reageer" on a comment fills the parent field the backend
	   already accepts and stores; it does not render nested replies — none
	   of the 122 historical comments are threaded, so there was nothing to
	   match there. */
	root.addEventListener("click", function (e) {
		var pill = e.target.closest(".c-reply");
		if (!pill) return;
		parentField.value = pill.dataset.parent || "";
		if (replying && replyingName) {
			replyingName.textContent = pill.dataset.name || "";
			replying.hidden = false;
		}
		respond.scrollIntoView({ behavior: "smooth", block: "start" });
		body.focus();
	});

	var cancelReply = root.querySelector(".c-cancel-reply");
	if (cancelReply) {
		cancelReply.addEventListener("click", function () {
			parentField.value = "";
			replying.hidden = true;
		});
	}

	function say(msg, kind) {
		status.textContent = msg;
		status.className = "c-status" + (kind ? " is-" + kind : "");
	}

	form.addEventListener("submit", function (e) {
		e.preventDefault();
		if (!name.value.trim()) { say("Vul even je naam in.", "error"); return name.focus(); }
		if (body.value.trim().length < 2) { say("Schrijf even een berichtje.", "error"); return body.focus(); }

		submit.disabled = true;
		submit.classList.add("is-busy");
		say("Versturen…");

		fetch(base + "comments/post.php", {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({
				post: slug,
				name: name.value.trim(),
				body: body.value.trim(),
				parent: parentField.value || null,
				website: form.elements.website.value,
				elapsed: Math.round((Date.now() - opened) / 1000)
			})
		})
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (res) {
				if (res.ok && res.j.ok) {
					form.reset();
					counter.textContent = "0 / 4000";
					if (replying) replying.hidden = true;
					say(res.j.message || "Bedankt! Je reactie is verstuurd.", "ok");
				} else {
					say(res.j.error || "Er ging iets mis. Probeer het nog eens.", "error");
				}
			})
			.catch(function () { say("Reageren lukt nu even niet.", "error"); })
			.then(function () {
				submit.disabled = false;
				submit.classList.remove("is-busy");
			});
	});
})();
