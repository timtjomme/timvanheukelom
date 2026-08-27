/* ---------------------------------------------------------------------------
   Comment thread polish. Two jobs, both cosmetic — the markup is untouched:

   1. Draw an initial avatar for every commenter without a real Gravatar.
      Of the 32 people who commented, only 2 actually had one — the theme drew
      the same grey "mystery person" silhouette for the other 30. Those two are
      now served from assets/img/avatars/; the rest get a coloured disc with
      their initial, which gives each person a distinct identity, removes 32
      requests to secure.gravatar.com (so no reader's IP leaks to Automattic),
      and keeps the thread looking right with no internet.

   2. Trim "op 13 oktober 2016 om 1:16 pm" down to "13 oktober 2016".
--------------------------------------------------------------------------- */
(function () {
	"use strict";

	// Muted, high-contrast-on-white pairs. Picked per name, so a commenter
	// keeps the same colour on every post they appear on.
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

	function draw(holder, name) {
		var pair = PALETTE[hash(name.toLowerCase()) % PALETTE.length];
		var el = document.createElement("span");
		el.className = "initial-avatar";
		el.style.background = pair[0];
		el.style.color = pair[1];
		el.textContent = initialOf(name);
		el.setAttribute("aria-hidden", "true");
		holder.innerHTML = "";
		holder.appendChild(el);
	}

	document.querySelectorAll(".fl-comments .comment-body").forEach(function (body) {
		var holder = body.querySelector(".comment-avatar");
		var nameEl = body.querySelector(".comment-author-link");
		var name = nameEl ? nameEl.textContent.trim() : "";
		if (holder && name) {
			// Only the two commenters who actually have an avatar still carry an
			// <img> (now served from assets/img/avatars/). Everyone else had the
			// stock silhouette, which the build stripped - draw them an initial.
			var img = holder.querySelector("img");
			if (!img) {
				draw(holder, name);
			} else {
				img.addEventListener("error", function () { draw(holder, name); });
			}
		}

		// "op 13 oktober 2016 om 1:16 pm" -> "13 oktober 2016"
		var date = body.querySelector(".comment-date");
		if (date) {
			var t = date.textContent.trim()
				.replace(/^op\s+/i, "")
				.replace(/\s+om\s+.*$/i, "");
			if (t) date.textContent = t;
		}
	});
})();
