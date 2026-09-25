// ---- CONTENT PROTECTION -----------------------------------------------
// A deterrent, not real protection — view-source, devtools and a
// screenshot all still work regardless of any of this. It only removes
// the casual, one-click paths: right-click > Save/Copy, and dragging an
// image or the hero video out of the page. Form fields are exempted — a
// visitor needs their browser's normal right-click (paste, spell-check)
// to actually use the contact form.
document.addEventListener('contextmenu', function(e){
  var t = e.target;
  if(t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable) return;
  e.preventDefault();
});
document.addEventListener('dragstart', function(e){
  if(e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO') e.preventDefault();
});

// ---- HERO VIDEO ROTATION ------------------------------------------------
// Six title-reveal cuts. One is picked at random and looped for the
// whole visit, rather than cycling through all of them in one sitting —
// the choice is kept in sessionStorage so it stays the same across
// reloads within a visit, and only re-rolls for a fresh session.
(function(){
  var wrap = document.querySelector('.hero-video');
  var video = wrap && wrap.querySelector('video');
  if(!video) return;
  var playlist = [
    {src: 'imgs/archives-title.mp4',   poster: 'imgs/archives-title-poster.jpg',   dark: false},
    {src: 'imgs/archives-title-2.mp4', poster: 'imgs/archives-title-2-poster.jpg', dark: false},
    {src: 'imgs/archives-title-3.mp4', poster: 'imgs/archives-title-3-poster.jpg', dark: true},
    {src: 'imgs/archives-title-4.mp4', poster: 'imgs/archives-title-4-poster.jpg', dark: true},
    {src: 'imgs/archives-title-5.mp4', poster: 'imgs/archives-title-5-poster.jpg', dark: false},
    {src: 'imgs/archives-title-6.mp4', poster: 'imgs/archives-title-6-poster.jpg', dark: true}
  ];
  var idx = NaN;
  try {
    var stored = sessionStorage.getItem('trp_hero_video');
    if(stored !== null) idx = parseInt(stored, 10);
  } catch(e){}
  if(isNaN(idx) || idx < 0 || idx >= playlist.length){
    idx = Math.floor(Math.random() * playlist.length);
    try { sessionStorage.setItem('trp_hero_video', idx); } catch(e){}
  }
  var pick = playlist[idx];
  wrap.classList.toggle('is-dark', !!pick.dark);
  if(idx === 0) return; // already the video baked into the markup
  video.poster = pick.poster;
  video.querySelector('source').src = pick.src;
  video.load();
  video.play().catch(function(){});
})();

// ---- ROTATING TAB TITLE --------------------------------------------------
// The browser-tab subtitle cycles through a run of Teddy's own ad-libs and
// lyrics instead of sitting on one static line — homepage only (same
// .hero-video gate as the video rotation above). Skipped under
// prefers-reduced-motion: a title quietly changing every few seconds isn't
// motion on the page itself, but it's still something repeatedly changing
// out from under a visitor with no way to pause it, which is exactly what
// that preference is asking sites to avoid.
(function(){
  if(!document.querySelector('.hero-video')) return;
  if(window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var lines = [
    'How can I make you dance some more?',
    'Yes TR is my name',
    'I made the New Jack Swing...',
    'Here we go',
    'FUNKY',
    'Drop the ballistics HOT',
    'Play it a little louder',
    'Check baby check baby 1-2-3-4'
  ];
  var base = 'Teddy Riley Productions';
  var i = 0;
  document.title = base + ' — ' + lines[0];
  setInterval(function(){
    i = (i + 1) % lines.length;
    document.title = base + ' — ' + lines[i];
  }, 4000);
})();

// ---- DECADE HERO/PANEL PHOTO ROTATION ------------------------------------
// Several photos of the same era, picked at random on each load —
// independently on the homepage panel and on the decade page's own hero (a
// visitor could see a different one in each place on the same visit). Each
// photo gets its own crop position, same as every other --hero-pos/
// --panel-pos tuned elsewhere on the site, and its own source image per
// spot (hero vs. panel) since a couple of decades started with two
// separately-cropped originals rather than one shared file.
function initDecadePhotoRotation(panelSelector, heroPage, photos){
  function pick(){ return photos[Math.floor(Math.random() * photos.length)]; }

  var panel = document.querySelector(panelSelector);
  if(panel){
    var p = pick();
    panel.style.backgroundImage = "url('" + p.panelSrc + "')";
    panel.style.setProperty('--panel-pos', p.panelPos);
  }

  // script.js is shared by every page, so the hero half only applies on
  // the one decade page it belongs to — matched by filename since these
  // pages don't all carry a distinguishing class on .hero the way
  // 1980s.html's .is-light-corner does.
  if(location.pathname.replace(/^.*\//, '') === heroPage){
    var hero = document.querySelector('.hero');
    if(hero){
      var h = pick();
      hero.style.setProperty('--hero-img', "url('" + h.heroSrc + "')");
      hero.style.setProperty('--hero-pos', h.heroPos);
    }
  }
}

initDecadePhotoRotation('.panel-1980s', '1980s.html', [
  { heroSrc: 'imgs/hero-1980s.jpg', heroPos: '12%', panelSrc: 'imgs/hero-1980s.jpg', panelPos: '14%' },
  { heroSrc: 'imgs/hero-1980s-2.jpg', heroPos: '10%', panelSrc: 'imgs/hero-1980s-2.jpg', panelPos: '10%' },
  { heroSrc: 'imgs/hero-1980s-3.jpg', heroPos: '10%', panelSrc: 'imgs/hero-1980s-3.jpg', panelPos: '10%' },
  { heroSrc: 'imgs/hero-1980s-4.jpg', heroPos: '10%', panelSrc: 'imgs/hero-1980s-4.jpg', panelPos: '10%' },
  { heroSrc: 'imgs/hero-1980s-5.jpg', heroPos: '15%', panelSrc: 'imgs/hero-1980s-5.jpg', panelPos: '15%' },
  { heroSrc: 'imgs/hero-1980s-6.jpg', heroPos: '12%', panelSrc: 'imgs/hero-1980s-6.jpg', panelPos: '12%' },
  { heroSrc: 'imgs/hero-1980s-7.jpg', heroPos: '18%', panelSrc: 'imgs/hero-1980s-7.jpg', panelPos: '18%' }
]);

initDecadePhotoRotation('.panel-1990s', '1990s.html', [
  { heroSrc: 'imgs/hero-1990s.jpg', heroPos: '14%', panelSrc: 'imgs/panel-1990s.jpg', panelPos: '8%' },
  { heroSrc: 'imgs/hero-1990s-2.jpg', heroPos: '15%', panelSrc: 'imgs/hero-1990s-2.jpg', panelPos: '15%' },
  { heroSrc: 'imgs/hero-1990s-3.jpg', heroPos: '10%', panelSrc: 'imgs/hero-1990s-3.jpg', panelPos: '10%' },
  { heroSrc: 'imgs/hero-1990s-4.jpg', heroPos: '45%', panelSrc: 'imgs/hero-1990s-4.jpg', panelPos: '45%' },
  { heroSrc: 'imgs/hero-1990s-5.jpg', heroPos: '20%', panelSrc: 'imgs/hero-1990s-5.jpg', panelPos: '20%' },
  { heroSrc: 'imgs/hero-1990s-6.jpg', heroPos: '10%', panelSrc: 'imgs/hero-1990s-6.jpg', panelPos: '10%' },
  { heroSrc: 'imgs/hero-1990s-7.jpg', heroPos: '20%', panelSrc: 'imgs/hero-1990s-7.jpg', panelPos: '20%' },
  { heroSrc: 'imgs/hero-1990s-8.jpg', heroPos: '15%', panelSrc: 'imgs/hero-1990s-8.jpg', panelPos: '15%' },
  { heroSrc: 'imgs/hero-1990s-9.jpg', heroPos: '30%', panelSrc: 'imgs/hero-1990s-9.jpg', panelPos: '30%' },
  { heroSrc: 'imgs/hero-1990s-10.jpg', heroPos: '10%', panelSrc: 'imgs/hero-1990s-10.jpg', panelPos: '10%' }
]);

initDecadePhotoRotation('.panel-2020s', '2020s.html', [
  { heroSrc: 'imgs/hero-2020s.jpg', heroPos: '15%', panelSrc: 'imgs/panel-2020s.jpg', panelPos: '28%' },
  { heroSrc: 'imgs/hero-2020s-2.jpg', heroPos: '20%', panelSrc: 'imgs/hero-2020s-2.jpg', panelPos: '20%' },
  { heroSrc: 'imgs/hero-2020s-3.jpg', heroPos: '20%', panelSrc: 'imgs/hero-2020s-3.jpg', panelPos: '20%' },
  { heroSrc: 'imgs/hero-2020s-4.jpg', heroPos: '20%', panelSrc: 'imgs/hero-2020s-4.jpg', panelPos: '20%' },
  { heroSrc: 'imgs/hero-2020s-5.jpg', heroPos: '10%', panelSrc: 'imgs/hero-2020s-5.jpg', panelPos: '10%' }
]);

initDecadePhotoRotation('.panel-2010s', '2010s.html', [
  { heroSrc: 'imgs/hero-2010s.jpg', heroPos: '28%', panelSrc: 'imgs/panel-2010s.jpg', panelPos: '35%' },
  { heroSrc: 'imgs/hero-2010s-2.jpg', heroPos: '15%', panelSrc: 'imgs/hero-2010s-2.jpg', panelPos: '15%' },
  { heroSrc: 'imgs/hero-2010s-3.jpg', heroPos: '10%', panelSrc: 'imgs/hero-2010s-3.jpg', panelPos: '10%' },
  { heroSrc: 'imgs/hero-2010s-4.jpg', heroPos: '15%', panelSrc: 'imgs/hero-2010s-4.jpg', panelPos: '15%' },
  { heroSrc: 'imgs/hero-2010s-5.jpg', heroPos: '10%', panelSrc: 'imgs/hero-2010s-5.jpg', panelPos: '10%' },
  { heroSrc: 'imgs/hero-2010s-6.jpg', heroPos: '5%', panelSrc: 'imgs/hero-2010s-6.jpg', panelPos: '5%' },
  { heroSrc: 'imgs/hero-2010s-7.jpg', heroPos: '10%', panelSrc: 'imgs/hero-2010s-7.jpg', panelPos: '10%' },
  { heroSrc: 'imgs/hero-2010s-8.jpg', heroPos: '15%', panelSrc: 'imgs/hero-2010s-8.jpg', panelPos: '15%' }
]);

initDecadePhotoRotation('.panel-2000s', '2000s.html', [
  { heroSrc: 'imgs/hero-2000s.jpg', heroPos: '18%', panelSrc: 'imgs/panel-2000s.jpg', panelPos: '35%' },
  { heroSrc: 'imgs/hero-2000s-2.jpg', heroPos: '10%', panelSrc: 'imgs/hero-2000s-2.jpg', panelPos: '10%' },
  { heroSrc: 'imgs/hero-2000s-3.jpg', heroPos: '15%', panelSrc: 'imgs/hero-2000s-3.jpg', panelPos: '15%' },
  { heroSrc: 'imgs/hero-2000s-4.jpg', heroPos: '20%', panelSrc: 'imgs/hero-2000s-4.jpg', panelPos: '20%' },
  { heroSrc: 'imgs/hero-2000s-5.jpg', heroPos: '15%', panelSrc: 'imgs/hero-2000s-5.jpg', panelPos: '15%' }
]);

// Named so cards created later (the 404's random pick) can reuse it.
function wireCard(card){
  function toggle(){
    var flipped = card.classList.toggle('flipped');
    card.setAttribute('aria-pressed', flipped ? 'true' : 'false');
  }
  card.addEventListener('click', toggle);
  card.addEventListener('keydown', function(e){
    // e.target guards against a nested control (the video button) bubbling
    // its own Enter/Space up here — only the card itself being the focused
    // target should flip it.
    if(e.target === card && (e.key === 'Enter' || e.key === ' ')){
      e.preventDefault();
      toggle();
    }
  });
}

document.querySelectorAll('.card-inner').forEach(wireCard);

// ---- NAV DROPDOWN ---------------------------------------------------
// The dropdown itself is a plain <details> — opening and closing it
// needs no JS. This only adds what <details> doesn't do on its own:
// closing when you click elsewhere or press Escape, so it doesn't sit
// open over the page after you've moved on.
document.addEventListener('click', function(e){
  document.querySelectorAll('.nav-dropdown[open]').forEach(function(d){
    if(!d.contains(e.target)) d.removeAttribute('open');
  });
});
document.addEventListener('keydown', function(e){
  if(e.key !== 'Escape') return;
  document.querySelectorAll('.nav-dropdown[open]').forEach(function(d){
    d.removeAttribute('open');
  });
});

// LP or Single for a release straight out of releases.json — the decade pages
// get this stamped in by tools/build.py, but the 404 builds its card at runtime.
// Same rule as NUMBERED there: a numbered tracklist is an album. Keep in step.
var NUMBERED = /^\s*(?:\d{1,2}\s*[–—.)]|\d{2}\s)/;
function releaseFormat(r){
  if(r.format) return r.format;
  return r.tracks.some(function(t){ return NUMBERED.test(t); }) ? 'LP' : 'Single';
}

// Mirrors TRACK_NUM_PREFIX in tools/build.py — see the comment there.
var TRACK_NUM_PREFIX = /^\s*\d{1,2}\s*[-–—.)]\s*/;
function displayTracks(r){
  if(releaseFormat(r) !== 'Single') return r.tracks.map(function(t){ return [t, t]; });
  var pairs = r.tracks.map(function(t){ return [t.replace(TRACK_NUM_PREFIX, ''), t]; });
  pairs.sort(function(a, b){ return a[0].toLowerCase().localeCompare(b[0].toLowerCase()); });
  return pairs;
}

var FORM_ENDPOINT = 'https://formspree.io/f/moeawjre';

// Wire a contact form to submit in place. Every form still works as a plain
// POST without JS; this only keeps the visitor on the page.
function wireContactForm(form){
  var status = form.querySelector('.form-status');
  var button = form.querySelector('button[type="submit"]');
  var unconfigured = form.getAttribute('action').indexOf('YOUR_FORM_ID') !== -1;

  function say(msg, kind){
    status.textContent = msg;
    status.className = 'form-status' + (kind ? ' is-' + kind : '');
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    // Don't let a placeholder endpoint swallow someone's message silently.
    if(unconfigured){
      say('This form isn’t connected yet — no endpoint has been set.', 'error');
      return;
    }
    button.disabled = true;
    say('Sending…');

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'Accept': 'application/json' }
    }).then(function(res){
      if(res.ok){
        form.reset();
        say('Thanks — your message is on its way.', 'ok');
      } else {
        return res.json().then(function(d){
          var m = d && d.errors ? d.errors.map(function(x){ return x.message; }).join(', ') : '';
          say(m || 'Something went wrong. Please try again.', 'error');
        });
      }
    }).catch(function(){
      say('Could not send — check your connection and try again.', 'error');
    }).then(function(){
      button.disabled = false;
    });
  });
}

// The footer prompt. Without JS it stays a link to the contact page; here it
// becomes a panel that opens on hover, focus or tap.
function buildAskPanel(ask){
  var uid = 'ask-' + Math.random().toString(36).slice(2, 8);
  var panel = document.createElement('div');
  panel.className = 'ask-panel';
  panel.innerHTML =
    '<div class="ask-panel-inner">' +
      '<form class="contact-form" action="' + FORM_ENDPOINT + '" method="POST">' +
        '<div class="field"><label for="' + uid + '-e">Your email</label>' +
        '<input id="' + uid + '-e" name="email" type="email" required autocomplete="email"></div>' +
        '<div class="field"><label for="' + uid + '-r">Which release is this about? <span class="opt">optional</span></label>' +
        '<input id="' + uid + '-r" name="release" type="text" placeholder="e.g. Guy — Teddy’s Jam (1988, Uptown)"></div>' +
        '<div class="field"><label for="' + uid + '-m">Message</label>' +
        '<textarea id="' + uid + '-m" name="message" rows="4" required></textarea></div>' +
        '<input type="text" name="_gotcha" tabindex="-1" autocomplete="off" aria-hidden="true">' +
        '<div class="form-foot"><button type="submit">Send message</button>' +
        '<p class="form-status" role="status" aria-live="polite"></p></div>' +
      '</form>' +
    '</div>';
  ask.appendChild(panel);
  wireContactForm(panel.querySelector('.contact-form'));

  var line = ask.querySelector('.ask-line');
  line.setAttribute('role', 'button');
  line.setAttribute('aria-expanded', 'false');

  function open(){ ask.classList.add('is-open'); line.setAttribute('aria-expanded','true'); }
  function close(){ ask.classList.remove('is-open'); line.setAttribute('aria-expanded','false'); }

  // Tap or click opens it and pins it open, so a hover-out mid-typing
  // can't collapse the form.
  line.addEventListener('click', function(e){
    e.preventDefault();
    ask.classList.contains('is-open') ? close() : open();
  });
  // Any interaction inside pins it too.
  panel.addEventListener('focusin', open);
  panel.addEventListener('click', open);
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') close(); });
}

// The floating YEP YEP prompt. Injected rather than repeated in each page's
// markup, and skipped on the contact page, which is already the form.
function buildYepWidget(){
  var w = document.createElement('div');
  w.className = 'yep';
  w.innerHTML =
    '<div class="yep-bubble" aria-hidden="true">YEP YEP!</div>' +
    '<button class="yep-btn" type="button" aria-expanded="false" ' +
      'aria-controls="yep-panel">' +
      '<img src="imgs/yep-avatar.png" alt="" height="128">' +
      '<span class="sr-only">Add something to the archive</span>' +
    '</button>' +
    '<div class="yep-panel" id="yep-panel" role="dialog" aria-modal="false" ' +
      'aria-label="Add something to the archive" hidden>' +
      '<div class="yep-head">' +
        '<p class="yep-title">YEP YEP!</p>' +
        '<button class="yep-close" type="button" aria-label="Close">&times;</button>' +
      '</div>' +
      '<p class="yep-sub">Have more info in a release; see something missing; ' +
        'saw a fault; or just want to connect? Hit my inbox!</p>' +
      '<form class="contact-form yep-form" action="' + FORM_ENDPOINT + '" method="POST">' +
        '<div class="field"><label for="yep-name">Your name ' +
          '<span class="opt">optional</span></label>' +
          '<input id="yep-name" name="name" type="text" autocomplete="name"></div>' +
        '<div class="field"><label for="yep-email">Your email</label>' +
          '<input id="yep-email" name="email" type="email" required ' +
          'autocomplete="email"></div>' +
        '<div class="field"><label for="yep-msg">What should we add?</label>' +
          '<textarea id="yep-msg" name="message" rows="3" required></textarea></div>' +
        '<input type="text" name="_gotcha" tabindex="-1" autocomplete="off" ' +
          'aria-hidden="true">' +
        '<div class="form-foot"><button type="submit">Yep, send it</button>' +
        '<a class="ask-upload" href="https://www.dropbox.com/request/grc5mo0e10xu1hvgebyw" target="_blank" rel="noopener noreferrer" ' +
          'title="Upload photos or lossless files">' +
          '<span class="ask-upload-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M12 4l-5 5M12 4l5 5M4 20h16"/></svg></span>' +
          'Upload files</a>' +
        '<p class="form-status" role="status" aria-live="polite"></p></div>' +
      '</form>' +
    '</div>';
  document.body.appendChild(w);

  var btn   = w.querySelector('.yep-btn');
  var panel = w.querySelector('.yep-panel');
  var close = w.querySelector('.yep-close');
  wireContactForm(w.querySelector('.contact-form'));

  function open(){
    panel.hidden = false;
    w.classList.add('is-open');
    btn.setAttribute('aria-expanded', 'true');
    w.querySelector('#yep-name').focus();   // land inside, not on the trigger
  }
  var headerOnScreen = true;   // kept current by the observer below

  function shut(returnFocus){
    panel.hidden = true;
    w.classList.remove('is-open');
    btn.setAttribute('aria-expanded', 'false');
    if(returnFocus) btn.focus();            // don't strand keyboard users
    // the observer only fires on a crossing, so re-check here: closing while
    // the header is on screen should tuck the widget away again
    if(headerOnScreen) w.classList.remove('is-visible');
  }

  btn.addEventListener('click', function(){
    panel.hidden ? open() : shut(false);
  });
  close.addEventListener('click', function(){ shut(true); });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && !panel.hidden) shut(true);
  });

  // Every so often, pop the "YEP YEP!" bubble on its own — so a visitor who
  // never hovers or focuses the button still notices it's there. Skipped
  // under reduced motion; checks is-visible/is-open at fire time rather than
  // pausing and resuming the timer, so it stays in step with the widget's
  // own show/hide and the open panel without extra wiring.
  if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches){
    setInterval(function(){
      if(!w.classList.contains('is-visible') || w.classList.contains('is-open')) return;
      w.classList.add('is-nudging');
      setTimeout(function(){ w.classList.remove('is-nudging'); }, 2400);
    }, 14000);
  }

  // Stay out of the way until the header has scrolled past.
  var header = document.querySelector('.hero-video, .hero');
  if(!header || !('IntersectionObserver' in window)){
    w.classList.add('is-visible');           // no header to wait on
    return;
  }
  new IntersectionObserver(function(entries){
    // entries can coalesce; the last one is the current state
    headerOnScreen = entries[entries.length - 1].isIntersecting;
    // never yank it away mid-typing
    if(headerOnScreen && panel.hidden) w.classList.remove('is-visible');
    if(!headerOnScreen) w.classList.add('is-visible');
  }, { threshold: 0 }).observe(header);
}

document.querySelectorAll('.ask').forEach(buildAskPanel);

// ---- BACK TO TOP ------------------------------------------------------------
// One control, injected once, rather than markup repeated across five pages.
// Threshold-based: a page short enough to never cross it just never shows it.
function initToTop(){
  var a = document.createElement('a');
  a.href = '#';
  a.className = 'to-top';
  a.setAttribute('aria-label', 'Back to top');
  a.innerHTML =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" ' +
    'stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
  document.body.appendChild(a);

  a.addEventListener('click', function(e){
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? 'auto' : 'smooth'
    });
  });

  var onScroll = function(){
    a.classList.toggle('is-visible', window.scrollY > window.innerHeight * 0.8);
  };
  document.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

// ---- VIDEO MODAL -------------------------------------------------------
// A release can carry one or more YouTube clips: a .video-btn's data-videos
// is a JSON array of {id, label}. Delegated at the document level so it
// also catches the 404 page's card, built after this runs.
function videoModal(){
  var modal, frame, tabs, lastFocus;

  function ensure(){
    if(modal) return;
    modal = document.createElement('div');
    modal.className = 'video-modal';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="video-modal-backdrop"></div>' +
      '<div class="video-modal-box" role="dialog" aria-modal="true" aria-label="Video">' +
        '<button type="button" class="video-modal-close" aria-label="Close">&times;</button>' +
        '<div class="video-modal-tabs"></div>' +
        '<div class="video-modal-frame">' +
          '<iframe src="" title="" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);
    frame = modal.querySelector('iframe');
    tabs  = modal.querySelector('.video-modal-tabs');
    modal.querySelector('.video-modal-backdrop').addEventListener('click', close);
    modal.querySelector('.video-modal-close').addEventListener('click', close);
  }

  function show(id){
    frame.title = 'YouTube video player';
    frame.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
  }

  function open(videos){
    ensure();
    lastFocus = document.activeElement;
    tabs.innerHTML = '';
    tabs.hidden = videos.length < 2;
    videos.forEach(function(v, i){
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = v.label;
      if(i === 0) b.setAttribute('aria-current', 'true');
      b.addEventListener('click', function(){
        show(v.id);
        Array.prototype.forEach.call(tabs.querySelectorAll('button'), function(x){
          x.removeAttribute('aria-current');
        });
        b.setAttribute('aria-current', 'true');
      });
      tabs.appendChild(b);
    });
    show(videos[0].id);
    modal.hidden = false;
    requestAnimationFrame(function(){ modal.classList.add('is-open'); });
    modal.querySelector('.video-modal-close').focus();
  }

  function close(){
    if(!modal || modal.hidden) return;
    modal.classList.remove('is-open');
    frame.src = '';                 // stop playback, not just hide it
    modal.hidden = true;
    if(lastFocus) lastFocus.focus();
  }

  // Capture phase, not bubble: .card-inner's own click-to-flip listener
  // sits between this button and document, so by the time a bubbling
  // listener up here saw the click, the flip had already fired. Capture
  // runs root-down, ahead of that listener, so stopPropagation here
  // actually pre-empts it instead of reacting a step too late.
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.video-btn');
    if(!btn) return;
    e.preventDefault();
    e.stopPropagation();
    open(JSON.parse(btn.getAttribute('data-videos')));
  }, true);
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') close();
  });
}

videoModal();

// ---- PHOTO MODAL ---------------------------------------------------
// Same shape as videoModal above: one lazily-created overlay, reused for
// every node photo on the timeline. A node's photo sits inside its
// <summary> — the whole point of that element is that a click toggles
// the parent <details> — so the trigger listener runs in the capture
// phase and calls preventDefault/stopPropagation before that native
// toggle ever fires, the same trick videoModal uses against the card's
// own click-to-flip.
function photoModal(){
  var modal, img, lastFocus;

  function ensure(){
    if(modal) return;
    modal = document.createElement('div');
    modal.className = 'photo-modal';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="photo-modal-backdrop"></div>' +
      '<div class="photo-modal-box" role="dialog" aria-modal="true" aria-label="Photo">' +
        '<button type="button" class="photo-modal-close" aria-label="Close">&times;</button>' +
        '<img src="" alt="">' +
      '</div>';
    document.body.appendChild(modal);
    img = modal.querySelector('img');
    modal.querySelector('.photo-modal-backdrop').addEventListener('click', close);
    modal.querySelector('.photo-modal-close').addEventListener('click', close);
  }

  function open(src, alt){
    ensure();
    lastFocus = document.activeElement;
    img.src = src;
    img.alt = alt || '';
    modal.hidden = false;
    requestAnimationFrame(function(){ modal.classList.add('is-open'); });
    modal.querySelector('.photo-modal-close').focus();
  }

  function close(){
    if(!modal || modal.hidden) return;
    modal.classList.remove('is-open');
    modal.hidden = true;
    if(lastFocus) lastFocus.focus();
  }

  document.addEventListener('click', function(e){
    var dot = e.target.closest('.node.has-photo .node-dot');
    if(!dot) return;
    e.preventDefault();
    e.stopPropagation();
    var thumb = dot.querySelector('img');
    // A tightly-cropped thumbnail can point at the real, uncropped photo
    // via data-full — the circle shows the crop, the zoom shows everything.
    // No data-full just means the thumbnail already is the full photo.
    open(thumb.getAttribute('data-full') || thumb.src, thumb.alt);
  }, true);
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') close();
  });
}
photoModal();

// ---- SEARCH ----------------------------------------------------------
// Unlike videoModal()/photoModal(), which wire up triggers already sitting
// in page-specific markup, no page has a search box of its own — this
// function injects both the floating trigger and the modal shell into
// every page's <body>, so search works everywhere with zero per-page HTML.
// data/releases.json is fetched once, lazily, on first open (not at page
// load), and cached in RESULTS_CACHE for the rest of the session — a
// visitor who never opens search never pays for it.
function searchModal(){
  var modal, input, status, results, lastFocus;
  var RESULTS_CACHE = null;   // flat [{page, rel}], one entry per release
  var MAX_RESULTS = 40;

  function ensure(){
    if(modal) return;

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'search-trigger';
    trigger.setAttribute('aria-label', 'Search the archive');
    trigger.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>';
    document.body.appendChild(trigger);
    trigger.addEventListener('click', function(){ open(); });

    modal = document.createElement('div');
    modal.className = 'search-modal';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="search-modal-backdrop"></div>' +
      '<div class="search-modal-box" role="dialog" aria-modal="true" aria-label="Search">' +
        '<button type="button" class="search-modal-close" aria-label="Close">&times;</button>' +
        '<div class="search-modal-head">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>' +
          '<input class="search-modal-input" type="search" placeholder="Search artist, title, or track…" aria-label="Search">' +
        '</div>' +
        '<p class="search-modal-status"></p>' +
        '<div class="search-modal-results"></div>' +
      '</div>';
    document.body.appendChild(modal);
    input   = modal.querySelector('.search-modal-input');
    status  = modal.querySelector('.search-modal-status');
    results = modal.querySelector('.search-modal-results');

    modal.querySelector('.search-modal-backdrop').addEventListener('click', close);
    modal.querySelector('.search-modal-close').addEventListener('click', close);
    input.addEventListener('input', function(){ render(input.value); });
    input.addEventListener('keydown', function(e){
      if(e.key !== 'Enter') return;
      var first = results.querySelector('.search-result');
      if(first) location.href = first.href;
    });
  }

  function loadData(){
    if(RESULTS_CACHE) return Promise.resolve(RESULTS_CACHE);
    return fetch('data/releases.json').then(function(r){ return r.json(); }).then(function(data){
      var all = [];
      Object.keys(data).forEach(function(page){
        data[page].forEach(function(rel){
          if(!rel.hidden) all.push({ page: page, rel: rel });
        });
      });
      RESULTS_CACHE = all;
      return all;
    });
  }

  function render(query){
    var words = query.toLowerCase().trim().split(/\s+/).filter(Boolean);
    results.innerHTML = '';

    if(!words.length){
      status.textContent = RESULTS_CACHE ? 'Type to search ' + RESULTS_CACHE.length + ' releases' : 'Loading…';
      return;
    }

    var matches = (RESULTS_CACHE || []).filter(function(x){
      var r = x.rel;
      // credits/qobuz_credits are one array of "Role: Name, Name" lines per
      // track — flatten both down to plain lines so a producer, engineer, or
      // writer's name is searchable even when it never appears in the
      // artist/title/track text at all.
      var creditLines = [];
      (r.credits || []).concat(r.qobuz_credits || []).forEach(function(trackCredits){
        (trackCredits || []).forEach(function(line){ creditLines.push(line); });
      });
      var haystack = [r.artist, r.title, r.label].concat(r.tracks, creditLines).join(' ').toLowerCase();
      return words.every(function(w){ return haystack.indexOf(w) !== -1; });
    });

    if(!matches.length){
      status.textContent = 'No matches for "' + query + '"';
      return;
    }
    status.textContent = matches.length > MAX_RESULTS ?
      'Showing ' + MAX_RESULTS + ' of ' + matches.length + ' matches — try a more specific search' :
      matches.length + (matches.length === 1 ? ' match' : ' matches');

    matches.slice(0, MAX_RESULTS).forEach(function(x){
      var r = x.rel;
      var a = document.createElement('a');
      a.className = 'search-result';
      a.href = x.page + '.html#y' + r.year;
      a.innerHTML =
        '<img src="imgs/' + r.image + '" alt="" loading="lazy">' +
        '<span class="search-result-text">' +
          '<span class="search-result-title"></span>' +
          '<span class="search-result-meta"></span>' +
        '</span>';
      a.querySelector('.search-result-title').textContent = r.artist + ' — ' + r.title;
      a.querySelector('.search-result-meta').textContent =
        r.year + ' · ' + r.label + ' · ' + r.tracks.length + (r.tracks.length === 1 ? ' track' : ' tracks');
      results.appendChild(a);
    });
  }

  function open(){
    ensure();
    lastFocus = document.activeElement;
    modal.hidden = false;
    requestAnimationFrame(function(){ modal.classList.add('is-open'); });
    input.value = '';
    status.textContent = 'Loading…';
    results.innerHTML = '';
    input.focus();
    loadData().then(function(){ render(input.value); });
  }

  function close(){
    if(!modal || modal.hidden) return;
    modal.classList.remove('is-open');
    modal.hidden = true;
    if(lastFocus) lastFocus.focus();
  }

  ensure();

  document.addEventListener('keydown', function(e){
    if(!modal.hidden && e.key === 'Escape'){ close(); return; }
    // "/" opens search from anywhere, unless the visitor is already typing
    // into some other field — a bare "/" would otherwise just land in it.
    var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) ||
                 document.activeElement.isContentEditable;
    if(modal.hidden && e.key === '/' && !typing){
      e.preventDefault();
      open();
    }
  });
}
searchModal();

initToTop();

// ---- SITE STATS COUNT-UP ----------------------------------------------
// The homepage's site-wide numbers, and each decade page's own scoped
// ones, count up from zero once scrolled into view — a little flourish,
// not information the page depends on (the real totals from
// tools/build.py are already sitting in the markup), so it's skipped
// outright under reduced motion rather than jumping straight to the
// final numbers.
function initSiteStats(){
  var nums = document.querySelectorAll('.stat-num');
  if(!nums.length || !('IntersectionObserver' in window)) return;
  if(window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var targets = Array.prototype.map.call(nums, function(el){
    return parseInt(el.textContent.replace(/,/g, ''), 10) || 0;
  });
  var DURATION = 1200;
  function animate(el, target){
    var start = null;
    function step(ts){
      if(start === null) start = ts;
      var progress = Math.min((ts - start) / DURATION, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(eased * target).toLocaleString('en-US');
      if(progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  var obs = new IntersectionObserver(function(entries){
    if(!entries[entries.length - 1].isIntersecting) return;
    nums.forEach(function(el, i){ animate(el, targets[i]); });
    obs.disconnect();
  }, { threshold: 0 });
  obs.observe(document.querySelector('.site-stats'));
}
initSiteStats();

// ---- YEAR-GROUP HINT --------------------------------------------------------
// The first collapsed year gets a one-time "click me" (style.css) — timed to
// when it actually scrolls into view rather than page load. A reader who
// lingers on the hero and intro first would otherwise find the hint already
// finished fading before they ever reach it.
function initYearHint(){
  var summary = document.querySelector(
    '.year-group[open] + .year-group:not([open]) > summary.year-rule'
  );
  if(!summary || !('IntersectionObserver' in window)) return;
  var obs = new IntersectionObserver(function(entries){
    if(!entries[entries.length - 1].isIntersecting) return;
    summary.classList.add('show-hint');
    obs.disconnect();
  }, { threshold: 0.5 });
  obs.observe(summary);
}
initYearHint();

document.querySelectorAll('.contact-form').forEach(function(f){
  if(!f.closest('.ask') && !f.closest('.yep')) wireContactForm(f);
});

// contact.html ships a real form in its markup, and the 404 already offers
// plenty of ways out — the widget would just sit on its call to action
if(!document.querySelector('.contact-form:not(.ask .contact-form):not(.yep-form)') &&
   !document.querySelector('.lost')){
  buildYepWidget();
}

// ---- 404 -------------------------------------------------------------------
// Reads data/releases.json so the page knows which years actually exist, can
// point a mistyped year at the right anchor, and can offer a real release.
function initLostPage(){
  if(!document.querySelector('.lost')) return;

  var pathEl   = document.querySelector('[data-lost-path]');
  var hintEl   = document.querySelector('[data-lost-hint]');
  var realWrap = document.querySelector('[data-lost-real]');
  var realSub  = document.querySelector('[data-lost-real-sub]');
  var realGrid = document.querySelector('[data-lost-real-grid]');
  var calm     = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // --- 1. name what they actually asked for -------------------------------
  // filter(Boolean): a directory-style miss (/1988-guy-groove-me/) ends in a
  // slash, so a plain pop() would hand back an empty string.
  var asked = decodeURIComponent(location.pathname.split('/').filter(Boolean).pop() || '');
  if(asked && asked !== '404.html'){
    pathEl.innerHTML = 'No pressing of <span class="lost-slug"></span> exists.';
    pathEl.querySelector('.lost-slug').textContent = asked;
  }

  // --- 2. dig through the crates, then reveal ------------------------------
  var LABELS = ["Lil' Man Records", "Funky Mamma", "G.R. Productions",
                "New Jack Swing", "Future Records", "LOR Records",
                "Sound Of New York", "Rooftop Records", "QDT", "Donril Music"];

  var dig     = document.querySelector('[data-lost-dig]');
  var digName = document.querySelector('[data-lost-dig-name]');
  var reveal  = document.querySelector('[data-lost-reveal]');

  function showReveal(){
    if(dig) dig.hidden = true;
    if(reveal){
      reveal.hidden = false;
      requestAnimationFrame(function(){ reveal.classList.add('is-in'); });
    }
  }

  if(calm || !dig || !digName || !reveal){
    showReveal();                       // no animation: go straight to the answer
  } else {
    var i = 0;
    (function nextLabel(){
      if(i < LABELS.length){
        digName.textContent = LABELS[i];
        digName.classList.remove('is-flip');
        void digName.offsetWidth;       // restart the flip animation
        digName.classList.add('is-flip');
        i++;
        setTimeout(nextLabel, 700);
      } else {
        digName.textContent = 'Nothing.';
        setTimeout(showReveal, 1000);
      }
    })();
  }

  // --- 3. the data knows which years exist --------------------------------
  fetch('data/releases.json').then(function(r){ return r.json(); }).then(function(data){
    var all = [];
    Object.keys(data).forEach(function(page){
      data[page].forEach(function(rel){ all.push({ page: page, rel: rel }); });
    });
    if(!all.length) return;

    // a year in the URL they typed -> jump straight to that section
    var m = asked.match(/(19|20)\d{2}/);
    if(m){
      var year = m[0];
      var hit = all.filter(function(x){ return x.rel.year === year; });
      if(hit.length){
        hintEl.innerHTML = '';
        var a = document.createElement('a');
        a.href = hit[0].page + '.html#y' + year;
        a.textContent = 'Looking for ' + year + '? ' + hit.length +
                        (hit.length === 1 ? ' release' : ' releases') + ' →';
        hintEl.appendChild(a);
        hintEl.hidden = false;
      }
    }

    // --- 4. hand them something that does exist ---------------------------
    var pick = all[Math.floor(Math.random() * all.length)];
    var r = pick.rel;
    realSub.textContent = 'This one is real. Pulled at random from ' +
                          all.length + ' releases in the archive.';
    var card = document.createElement('div');
    card.className = 'card';
    card.innerHTML =
      '<div class="card-inner" role="button" tabindex="0" aria-pressed="false" style="--cover-img:url(\'imgs/' + r.image + '\')">' +
        '<div class="face front">' +
          '<img src="imgs/' + r.image + '" alt="">' +
          '<span class="format"></span>' +
        '</div>' +
        '<div class="face back">' +
          '<div class="back-head"><div class="back-head-text"><p class="artist"></p>' +
          '<p class="label-name"></p></div></div>' +
          '<ul class="tracks"></ul>' +
          '<div class="back-foot"><button type="button">Flip back</button></div>' +
        '</div>' +
      '</div>';
    // textContent throughout: release data must never be parsed as markup
    card.querySelector('.format').textContent = releaseFormat(r);
    card.querySelector('.back .artist').textContent  = r.artist + ' — ' + r.title;
    card.querySelector('.label-name').textContent    = r.label;
    card.querySelector('img').alt = r.artist + ' – ' + r.title + ' sleeve';
    if(r.videos && r.videos.length){
      var videoBtn = document.createElement('button');
      videoBtn.type = 'button';
      videoBtn.className = 'video-btn';
      videoBtn.setAttribute('aria-label', 'Watch video');
      videoBtn.setAttribute('data-videos', JSON.stringify(r.videos));
      videoBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
      card.querySelector('.back-head').appendChild(videoBtn);
    }
    var ul = card.querySelector('.tracks');
    var missing = r.missing || [];
    displayTracks(r).forEach(function(pair){
      var li = document.createElement('li');
      li.textContent = pair[0];
      if(missing.indexOf(pair[1]) !== -1) li.className = 'missing';
      ul.appendChild(li);
    });
    if(missing.length){
      var legend = document.createElement('span');
      legend.className = 'legend';
      legend.innerHTML = '<span></span>missing from collection';
      card.querySelector('.back-foot').insertBefore(
        legend, card.querySelector('.back-foot button'));
    }
    realGrid.appendChild(card);
    realWrap.hidden = false;
    wireCard(card.querySelector('.card-inner'));

    var link = document.createElement('a');
    link.className = 'lost-real-link';
    link.href = pick.page + '.html#y' + r.year;
    link.textContent = 'See all of ' + r.year + ' →';
    realWrap.appendChild(link);
  }).catch(function(){ /* no data, no bonus card — the page still works */ });
}

initLostPage();

// ---- UPDATE BANNER -----------------------------------------------------
// Content and the data-update marker are baked in by tools/build.py; this
// wires the dismiss button (remembered in localStorage, keyed to the
// update's own date — a later entry in data/updates.json carries a new
// date, so it reappears even if an older one was dismissed) and reveals
// the banner once the visitor has scrolled a little way down, rather than
// showing it the instant the page loads. Runs last so a thrown
// localStorage access (old Safari private browsing) can't stop anything
// earlier in the file from wiring up.
function initUpdateBanner(){
  var banner = document.querySelector('.update-banner');
  if(!banner) return;
  var KEY = 'trp-dismissed-update';
  var id = banner.getAttribute('data-update');
  if(localStorage.getItem(KEY) === id){
    banner.remove();
    return;
  }
  banner.querySelector('.dismiss').addEventListener('click', function(){
    localStorage.setItem(KEY, id);
    banner.remove();
  });
  // Hidden until scrolled a little way down — appearing the instant the
  // page loads competed with the hero title for attention.
  var onScroll = function(){
    banner.classList.toggle('is-visible', window.scrollY > 40);
  };
  document.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}
initUpdateBanner();

// ---- TIMELINE SNAKE LINES -----------------------------------------------
// The connector on the biography timeline (timeline.html) isn't a fixed
// image or a hand-authored path — it's measured from the real, rendered
// centre of each .node-dot and redrawn whenever that can move: on load,
// on resize, and when a node opens and pushes the rows below it down.
// Column count is entirely .snake's own call in CSS; this just finds
// wherever the dots actually landed and connects them in order.
function debounce(fn, ms){
  var t;
  return function(){
    clearTimeout(t);
    t = setTimeout(fn, ms);
  };
}

// Straight segments with the sharp corners rounded off: pull back `r`
// along each side of a corner and swap the corner itself for a
// quadratic curve through it. A segment shorter than 2r just gets all
// the room it has, so tight turns still round rather than overshoot.
// Shared by initSnakeLines (within one era) and initEraLinks (between
// eras), so both draw the exact same kind of line.
function roundedPath(points, r){
  if(points.length < 2) return '';
  var d = 'M' + points[0].x.toFixed(1) + ',' + points[0].y.toFixed(1);
  for(var i = 1; i < points.length - 1; i++){
    var p0 = points[i - 1], p1 = points[i], p2 = points[i + 1];
    var v1x = p1.x - p0.x, v1y = p1.y - p0.y, len1 = Math.hypot(v1x, v1y) || 1;
    var v2x = p2.x - p1.x, v2y = p2.y - p1.y, len2 = Math.hypot(v2x, v2y) || 1;
    var rr = Math.min(r, len1 / 2, len2 / 2);
    var ax = p1.x - (v1x / len1) * rr, ay = p1.y - (v1y / len1) * rr;
    var bx = p1.x + (v2x / len2) * rr, by = p1.y + (v2y / len2) * rr;
    d += ' L' + ax.toFixed(1) + ',' + ay.toFixed(1);
    d += ' Q' + p1.x.toFixed(1) + ',' + p1.y.toFixed(1) + ' ' + bx.toFixed(1) + ',' + by.toFixed(1);
  }
  var last = points[points.length - 1];
  d += ' L' + last.x.toFixed(1) + ',' + last.y.toFixed(1);
  return d;
}

function initSnakeLines(){
  var snakes = document.querySelectorAll('.snake');
  if(!snakes.length) return;

  snakes.forEach(function(snake){
    var svg = snake.querySelector('.snake-line');
    var path = svg && svg.querySelector('path');
    var nodes = Array.prototype.slice.call(snake.querySelectorAll('.node'));
    if(!svg || !path || nodes.length < 2) return;

    function draw(){
      var box = snake.getBoundingClientRect();
      svg.setAttribute('viewBox', '0 0 ' + box.width + ' ' + box.height);

      // below 640px .snake is a single column (style.css) — every node is
      // its own "row", so the row-to-row jog further down would zig out
      // past the edge and back for each one. A straight line through the
      // dots reads as the same rail without that. Anchored to each dot's
      // left edge, not its center: .node summary is a left-aligned flex
      // column (dot, then year, then title, all starting at that same left
      // edge), so a line through the dot's center sits well inside the
      // text's own horizontal span, not beside it. style.css indents
      // .node-year/.node-head a little further right of this same edge so
      // the rail runs clear of the text while still meeting the dot itself.
      var singleColumn = getComputedStyle(snake).gridTemplateColumns.trim().split(' ').length === 1;
      if(singleColumn){
        var linePts = nodes.map(function(n){
          var d = n.querySelector('.node-dot').getBoundingClientRect();
          return { x: d.left - box.left, y: d.top + d.height / 2 - box.top };
        });
        path.setAttribute('d', roundedPath(linePts, 24));
        return;
      }

      // group nodes into visual rows by comparing each one's top edge —
      // simpler and more robust than trusting a hardcoded column count
      var rows = [];
      nodes.forEach(function(n){
        var r = n.getBoundingClientRect();
        var row = rows[rows.length - 1];
        if(row && Math.abs(r.top - row.top) < 4){
          row.nodes.push(n);
          row.bottom = Math.max(row.bottom, r.bottom);
        } else {
          rows.push({ top: r.top, bottom: r.bottom, nodes: [n] });
        }
      });

      var pts = [];
      rows.forEach(function(row, ri){
        row.nodes.forEach(function(n){
          var d = n.querySelector('.node-dot').getBoundingClientRect();
          pts.push({ x: d.left + d.width / 2 - box.left, y: d.top + d.height / 2 - box.top });
        });
        var next = rows[ri + 1];
        if(next){
          // The turn: past the end of the row first, THEN down — never
          // straight down from the last dot itself, because that column
          // keeps going for another 100-odd px of this same node's own
          // year and title underneath it. Sideways at the dot's own
          // height is safe regardless (text starts below the dot, not
          // beside it), and grid auto-flow guarantees a row only gets a
          // "next" row when it's full — a partial row is always the last
          // one — so the departing dot is always in the final column and
          // "just past the grid's right edge" is always clear of every
          // column's text, not just this row's.
          var last = pts[pts.length - 1];
          var channelX = box.width + 16;
          var midY = (row.bottom + next.top) / 2 - box.top;
          var nd = next.nodes[0].querySelector('.node-dot').getBoundingClientRect();
          var nextX = nd.left + nd.width / 2 - box.left;
          pts.push({ x: channelX, y: last.y });
          pts.push({ x: channelX, y: midY });
          pts.push({ x: nextX, y: midY });
        }
      });

      path.setAttribute('d', roundedPath(pts, 24));
    }

    draw();
    // a node opening pushes every row below it down, which is exactly
    // the kind of position change this needs to follow
    snake.addEventListener('toggle', function(){ requestAnimationFrame(draw); }, true);
    window.addEventListener('resize', debounce(draw, 150));
  });
}
initSnakeLines();

// ---- TIMELINE ERA LINKS --------------------------------------------------
// One more connector, same recipe as the snake lines above, joining the
// last dot of one era to the first dot of the next now that .story lays
// eras out side by side instead of stacking them — so the seam between
// eras reads as the same line, not a different, one-off treatment. Drawn
// into a single overlay SVG (.era-link-line) sized in JS to .story's full
// scrollable area: inset:0 alone would only ever cover the visible,
// clipped slice of a scroll container, not the content scrolled to.
function initEraLinks(){
  var story = document.querySelector('.story');
  var svg = story && story.querySelector('.era-link-line');
  var path = svg && svg.querySelector('path');
  var eras = story ? Array.prototype.slice.call(story.querySelectorAll('.era')) : [];
  if(!story || !svg || !path || eras.length < 2) return;

  function dotCenter(era, which, edge){
    var nodes = era.querySelectorAll('.node');
    var node = nodes[which === 'first' ? 0 : nodes.length - 1];
    var d = node.querySelector('.node-dot').getBoundingClientRect();
    return { x: edge ? d.left : d.left + d.width / 2, y: d.top + d.height / 2 };
  }
  function isSingleColumn(era){
    return getComputedStyle(era.querySelector('.snake')).gridTemplateColumns.trim().split(' ').length === 1;
  }

  function draw(){
    var w = story.scrollWidth, h = story.scrollHeight;
    svg.style.width = w + 'px';
    svg.style.height = h + 'px';
    svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
    var origin = svg.getBoundingClientRect();

    var segments = [];
    for(var i = 0; i < eras.length - 1; i++){
      var eraA = eras[i], eraB = eras[i + 1];
      // single column (mobile): anchored to each dot's left edge, same
      // reason as the singleColumn branch in initSnakeLines — the era
      // heading and node text below both start at that same left edge,
      // so a join through the center sits inside that text instead of
      // beside it. A plain drop straight down is all it needs.
      var bothSingle = isSingleColumn(eraA) && isSingleColumn(eraB);
      var a = dotCenter(eraA, 'last', bothSingle);
      var b = dotCenter(eraB, 'first', bothSingle);
      a = { x: a.x - origin.left, y: a.y - origin.top };
      b = { x: b.x - origin.left, y: b.y - origin.top };
      if(bothSingle){
        segments.push(roundedPath([a, b], 24));
        continue;
      }
      // multi-column: the last node of era A can land in any column, not
      // just the last one — same channel-out/drop/channel-in shape
      // initSnakeLines draws between rows within one era, just spanning
      // two separate .snake grids instead of two rows of the same one.
      // Channel past .story's own right edge (every node's text starts
      // below its dot, never beside it, so this stays clear of every
      // column in both eras) then down to the midpoint between them.
      var channelX = w - origin.left + 16;
      var midY = (eraA.getBoundingClientRect().bottom + eraB.getBoundingClientRect().top) / 2 - origin.top;
      segments.push(roundedPath([a, { x: channelX, y: a.y }, { x: channelX, y: midY }, { x: b.x, y: midY }, b], 24));
    }
    path.setAttribute('d', segments.join(' '));
  }

  draw();
  story.addEventListener('toggle', function(){ requestAnimationFrame(draw); }, true);
  window.addEventListener('resize', debounce(draw, 150));
}
initEraLinks();

// ---- VISIT TRACKING -------------------------------------------------
// Self-hosted: every beacon goes to our own analytics/track.php, never to a
// third party. No cookies — the session id is a random string kept in
// sessionStorage, so it resets once the tab/browser session ends rather
// than following a visitor across days.
(function(){
  // Honour the browser's own opt-out before anything else happens.
  if(navigator.doNotTrack === '1' || window.doNotTrack === '1' ||
     navigator.globalPrivacyControl === true) return;

  function sid(){
    try {
      var v = sessionStorage.getItem('trp_sid');
      if(!v){
        v = Math.random().toString(36).slice(2) + Date.now().toString(36);
        sessionStorage.setItem('trp_sid', v);
      }
      return v;
    } catch(e){ return ''; }
  }

  function send(payload){
    var body = JSON.stringify(payload);
    if(navigator.sendBeacon){
      navigator.sendBeacon('analytics/track.php', new Blob([body], {type: 'application/json'}));
    } else {
      fetch('analytics/track.php', {method: 'POST', body: body, keepalive: true}).catch(function(){});
    }
  }

  var page = location.pathname.replace(/^.*\//, '') || 'index.html';
  var started = Date.now();
  var visitorId = sid();
  var maxScroll = 0;
  var sentDuration = false;

  send({
    type: 'pageview', page: page, ref: document.referrer || null, sid: visitorId,
    vw: window.innerWidth, vh: window.innerHeight,
    // coarse location fallback for when the IP lookup can't resolve
    tz: (function(){
      try { return Intl.DateTimeFormat().resolvedOptions().timeZone || null; }
      catch(e){ return null; }
    })(),
    lang: (navigator.language || '').slice(0, 16) || null
  });

  // Most of this site's content lives in long flip-open sections — how far
  // someone actually scrolls says more than a raw time-on-page number.
  function onScroll(){
    var h = document.documentElement.scrollHeight - window.innerHeight;
    var pc = h > 0 ? Math.round((window.scrollY / h) * 100) : 100;
    if(pc > maxScroll) maxScroll = Math.max(0, Math.min(100, pc));
  }
  window.addEventListener('scroll', onScroll, {passive: true});
  onScroll();

  function sendDuration(){
    if(sentDuration) return;
    var dur = Math.round((Date.now() - started) / 1000);
    if(dur < 1) return;
    sentDuration = true;
    send({ type: 'duration', page: page, sid: visitorId, dur: dur, scroll: maxScroll });
  }
  document.addEventListener('visibilitychange', function(){
    if(document.visibilityState === 'hidden') sendDuration();
  });
  window.addEventListener('pagehide', sendDuration);

  // A light touch on behaviour, not full click-tracking: the interactions
  // that actually say something (browsing sleeves, using the two CTAs).
  document.addEventListener('click', function(e){
    if(e.target.closest('.card-inner')){
      send({ type: 'event', name: 'card_flip', page: page, sid: visitorId });
    } else if(e.target.closest('.ask-upload')){
      send({ type: 'event', name: 'upload_click', page: page, sid: visitorId });
    } else if(e.target.closest('.yep-btn')){
      send({ type: 'event', name: 'yep_open', page: page, sid: visitorId });
    }
  });
})();
