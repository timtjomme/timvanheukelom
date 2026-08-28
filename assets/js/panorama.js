/* ---------------------------------------------------------------------------
   360° photo viewer.

   Replaces three.js + OrbitControls + cardboard.js — 442 KB of library for a
   textured sphere — with the ~120 lines of WebGL that actually draw one. Same
   behaviour as before: the panorama turns slowly on its own, and you can drag
   (or swipe) to look around.

   Each viewer is <div class="panorama" data-image="..."></div>. If WebGL is
   missing the image is shown flat instead, so the photo is never simply lost.
--------------------------------------------------------------------------- */
(function () {
	"use strict";

	var VERT = [
		"attribute vec3 p;",
		"uniform mat4 mvp;",
		"varying vec3 v;",
		"void main(){ v = p; gl_Position = mvp * vec4(p, 1.0); }"
	].join("\n");

	var FRAG = [
		"precision mediump float;",
		"uniform sampler2D tex;",
		"varying vec3 v;",
		"const float PI = 3.1415926535;",
		"void main(){",
		"  vec3 d = normalize(v);",
		"  vec2 uv = vec2(0.5 + atan(d.z, d.x) / (2.0 * PI), 0.5 - asin(d.y) / PI);",
		"  gl_FragColor = texture2D(tex, uv);",
		"}"
	].join("\n");

	function sphere(rings, sectors) {
		var pos = [], idx = [], r, s;
		for (r = 0; r <= rings; r++) {
			for (s = 0; s <= sectors; s++) {
				var phi = Math.PI * r / rings, theta = 2 * Math.PI * s / sectors;
				pos.push(Math.sin(phi) * Math.cos(theta), Math.cos(phi), Math.sin(phi) * Math.sin(theta));
			}
		}
		for (r = 0; r < rings; r++) {
			for (s = 0; s < sectors; s++) {
				var a = r * (sectors + 1) + s, b = a + sectors + 1;
				idx.push(a, b, a + 1, b, b + 1, a + 1);
			}
		}
		return { pos: new Float32Array(pos), idx: new Uint16Array(idx) };
	}

	/* column-major perspective * lookAt, written out rather than pulled in */
	function matrix(yaw, pitch, aspect) {
		var f = 1 / Math.tan(75 * Math.PI / 360), near = 0.1, far = 10;  // 75° vertical
		var cp = Math.cos(pitch), sp = Math.sin(pitch);
		var cy = Math.cos(yaw), sy = Math.sin(yaw);
		// camera basis: forward, right, up
		var fx = cp * cy, fy = sp, fz = cp * sy;
		var rx = -sy, ry = 0, rz = cy;
		var ux = -sp * cy, uy = cp, uz = -sp * sy;
		var view = [rx, ux, -fx, 0, ry, uy, -fy, 0, rz, uz, -fz, 0, 0, 0, 0, 1];
		var proj = [f / aspect, 0, 0, 0, 0, f, 0, 0, 0, 0, (far + near) / (near - far), -1,
			0, 0, 2 * far * near / (near - far), 0];
		var out = new Float32Array(16), i, j, k;
		for (i = 0; i < 4; i++) for (j = 0; j < 4; j++) {
			var sum = 0;
			for (k = 0; k < 4; k++) sum += proj[k * 4 + j] * view[i * 4 + k];
			out[i * 4 + j] = sum;
		}
		return out;
	}

	function fallback(box, src) {
		var img = new Image();
		img.src = src;
		img.alt = "360° panorama";
		img.className = "panorama-fallback";
		box.replaceChildren(img);
	}

	function start(box) {
		var src = box.dataset.image;
		var canvas = document.createElement("canvas");
		var gl = canvas.getContext("webgl", { antialias: false }) ||
			canvas.getContext("experimental-webgl");
		if (!gl) return fallback(box, src);
		box.appendChild(canvas);

		var prog = gl.createProgram();
		[[gl.VERTEX_SHADER, VERT], [gl.FRAGMENT_SHADER, FRAG]].forEach(function (s) {
			var sh = gl.createShader(s[0]);
			gl.shaderSource(sh, s[1]);
			gl.compileShader(sh);
			gl.attachShader(prog, sh);
		});
		gl.linkProgram(prog);
		if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) return fallback(box, src);
		gl.useProgram(prog);

		var mesh = sphere(32, 64);
		var vb = gl.createBuffer();
		gl.bindBuffer(gl.ARRAY_BUFFER, vb);
		gl.bufferData(gl.ARRAY_BUFFER, mesh.pos, gl.STATIC_DRAW);
		var loc = gl.getAttribLocation(prog, "p");
		gl.enableVertexAttribArray(loc);
		gl.vertexAttribPointer(loc, 3, gl.FLOAT, false, 0, 0);
		var ib = gl.createBuffer();
		gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, ib);
		gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, mesh.idx, gl.STATIC_DRAW);

		var tex = gl.createTexture();
		gl.bindTexture(gl.TEXTURE_2D, tex);
		gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, 1, 1, 0, gl.RGB, gl.UNSIGNED_BYTE,
			new Uint8Array([20, 20, 20]));
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);

		var img = new Image();
		img.crossOrigin = "anonymous";
		img.onload = function () {
			gl.bindTexture(gl.TEXTURE_2D, tex);
			gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
			gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, img);
		};
		img.onerror = function () { fallback(box, src); };
		img.src = src;

		var yaw = 0, pitch = 0, spin = true, dragging = false, lx = 0, ly = 0;

		function size() {
			var dpr = Math.min(devicePixelRatio || 1, 2);
			canvas.width = box.clientWidth * dpr;
			canvas.height = box.clientHeight * dpr;
			gl.viewport(0, 0, canvas.width, canvas.height);
		}
		size();
		addEventListener("resize", size);

		box.addEventListener("pointerdown", function (e) {
			dragging = true; spin = false; lx = e.clientX; ly = e.clientY;
			box.classList.add("is-dragging");
			box.setPointerCapture(e.pointerId);
		});
		box.addEventListener("pointermove", function (e) {
			if (!dragging) return;
			yaw -= (e.clientX - lx) * 0.005;
			pitch = Math.max(-1.4, Math.min(1.4, pitch + (e.clientY - ly) * 0.005));
			lx = e.clientX; ly = e.clientY;
		});
		["pointerup", "pointercancel"].forEach(function (t) {
			box.addEventListener(t, function () {
				dragging = false;
				box.classList.remove("is-dragging");
			});
		});

		var visible = true;
		if ("IntersectionObserver" in window) {
			new IntersectionObserver(function (es) { visible = es[0].isIntersecting; })
				.observe(box);
		}

		(function frame() {
			requestAnimationFrame(frame);
			if (!visible || !canvas.width) return;
			if (spin) yaw += 0.0009;
			gl.uniformMatrix4fv(gl.getUniformLocation(prog, "mvp"), false,
				matrix(yaw, pitch, canvas.width / canvas.height));
			gl.drawElements(gl.TRIANGLES, mesh.idx.length, gl.UNSIGNED_SHORT, 0);
		})();
	}

	/* One page carries fifteen of these. Browsers cap the number of live WebGL
	   contexts (and each panorama is a 2560x1280 texture), so a viewer is only
	   built once it comes near the viewport rather than all of them at load. */
	var viewers = document.querySelectorAll(".panorama[data-image]");
	if (!("IntersectionObserver" in window)) {
		viewers.forEach(start);
		return;
	}
	var waking = new IntersectionObserver(function (entries) {
		entries.forEach(function (e) {
			if (!e.isIntersecting) return;
			waking.unobserve(e.target);
			start(e.target);
		});
	}, { rootMargin: "400px" });
	viewers.forEach(function (v) { waking.observe(v); });
})();
