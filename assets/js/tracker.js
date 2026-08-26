// ---- VISIT TRACKING -------------------------------------------------
// Self-hosted: every beacon goes to our own analytics/track.php, never to a
// third party. No cookies — the session id is a random string kept in
// sessionStorage, so it resets once the tab/browser session ends rather
// than following a visitor across days.
(function () {
  // Honour the browser's own opt-out before anything else happens.
  if (navigator.doNotTrack === '1' || window.doNotTrack === '1' ||
      navigator.globalPrivacyControl === true) return;

  // Pages live at two depths (index.html, landen/nobus.html), so the endpoint
  // is derived from this script's own URL instead of being a fixed relative
  // path. That also survives the site being served from a subdirectory.
  var self = document.currentScript ||
             document.querySelector('script[src$="assets/js/tracker.js"]');
  var ENDPOINT = self
    ? self.src.replace(/assets\/js\/tracker\.js.*$/, 'analytics/track.php')
    : '/analytics/track.php';

  function sid() {
    try {
      var v = sessionStorage.getItem('tvh_sid');
      if (!v) {
        v = Math.random().toString(36).slice(2) + Date.now().toString(36);
        sessionStorage.setItem('tvh_sid', v);
      }
      return v;
    } catch (e) { return ''; }
  }

  function send(payload) {
    var body = JSON.stringify(payload);
    if (navigator.sendBeacon) {
      navigator.sendBeacon(ENDPOINT, new Blob([body], { type: 'application/json' }));
    } else {
      fetch(ENDPOINT, { method: 'POST', body: body, keepalive: true }).catch(function () {});
    }
  }

  // Keep the folder so landen/nobus.html and index.html stay distinguishable.
  var page = location.pathname.replace(/^\//, '') || 'index.html';
  var started = Date.now();
  var visitorId = sid();
  var maxScroll = 0;
  var sentDuration = false;

  send({
    type: 'pageview', page: page, ref: document.referrer || null, sid: visitorId,
    vw: window.innerWidth, vh: window.innerHeight,
    // coarse location fallback for when the IP lookup can't resolve
    tz: (function () {
      try { return Intl.DateTimeFormat().resolvedOptions().timeZone || null; }
      catch (e) { return null; }
    })(),
    lang: (navigator.language || '').slice(0, 16) || null
  });

  // How far down a story someone actually read — these pages are very long.
  function onScroll() {
    var h = document.documentElement.scrollHeight - window.innerHeight;
    var pc = h > 0 ? Math.round((window.scrollY / h) * 100) : 100;
    if (pc > maxScroll) maxScroll = Math.max(0, Math.min(100, pc));
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  function sendDuration() {
    if (sentDuration) return;
    var dur = Math.round((Date.now() - started) / 1000);
    if (dur < 1) return;
    sentDuration = true;
    send({ type: 'duration', page: page, sid: visitorId, dur: dur, scroll: maxScroll });
  }
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') sendDuration();
  });
  window.addEventListener('pagehide', sendDuration);

  // A light touch on behaviour, not full click-tracking: the interactions
  // that actually say something on a travel blog.
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    if (t.closest('.uabb-new-ib-link')) {
      send({ type: 'event', name: 'story_open', page: page, sid: visitorId });
    } else if (t.closest('.cardboard')) {
      send({ type: 'event', name: 'panorama_view', page: page, sid: visitorId });
    } else if (t.closest('.uabb-video__play')) {
      send({ type: 'event', name: 'video_play', page: page, sid: visitorId });
    } else if (t.closest('.fl-icon a')) {
      send({ type: 'event', name: 'nav_icon', page: page, sid: visitorId });
    }
  });
})();
