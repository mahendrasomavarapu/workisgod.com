(function () {
  "use strict";

  const deck = document.getElementById("video-deck");
  if (!deck) return;

  let list = [];
  const raw = document.getElementById("video-playlist");
  try {
    list = JSON.parse(raw ? raw.textContent : "[]");
  } catch (e) {
    list = [];
  }
  if (!Array.isArray(list)) list = [];
  list = list.filter(function (item) {
    return item && item.embed && String(item.embed).indexOf("https://") === 0;
  }).slice(0, 1000);

  const frame = document.getElementById("video-frame");
  const titleEl = document.getElementById("video-title");
  const countEl = document.getElementById("video-count");
  const watchEl = document.getElementById("video-watch");
  const toggle = document.getElementById("video-toggle");
  const prevBtn = document.getElementById("video-prev");
  const nextBtn = document.getElementById("video-next");
  const interval = Math.max(6000, parseInt(deck.getAttribute("data-interval") || "12000", 10) || 12000);
  const reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  let index = 0;
  let timer = null;
  let playing = !reduce;

  function embedSrc(item) {
    const url = String(item.embed);
    const join = url.indexOf("?") >= 0 ? "&" : "?";
    if (item.platform === "youtube") {
      return url + join + "rel=0&modestbranding=1&autoplay=1&mute=1";
    }
    if (item.platform === "vimeo") {
      return url + join + "autoplay=1&muted=1&title=0&byline=0";
    }
    if (item.platform === "dailymotion") {
      return url + join + "autoplay=1&mute=1";
    }
    return url;
  }

  function show(i) {
    if (!list.length || !frame) return;
    index = ((i % list.length) + list.length) % list.length;
    const item = list[index];
    frame.removeAttribute("src");
    window.setTimeout(function () {
      frame.src = embedSrc(item);
    }, 40);
    if (titleEl) titleEl.textContent = item.title || (item.platform + " video");
    if (countEl) countEl.textContent = (index + 1) + " / " + list.length;
    if (watchEl) {
      watchEl.href = item.watch || item.embed;
      watchEl.hidden = false;
    }
  }

  function next() {
    show(index + 1);
  }
  function prev() {
    show(index - 1);
  }

  function arm() {
    window.clearInterval(timer);
    timer = null;
    if (playing && list.length > 1) {
      timer = window.setInterval(next, interval);
    }
    if (toggle) toggle.textContent = playing ? "Pause" : "Play";
  }

  if (!list.length) {
    if (titleEl) titleEl.textContent = "The reel is empty. An administrator can refresh video links.";
    if (toggle) toggle.disabled = true;
    return;
  }

  show(0);
  arm();

  if (nextBtn) nextBtn.addEventListener("click", function () { next(); arm(); });
  if (prevBtn) prevBtn.addEventListener("click", function () { prev(); arm(); });
  if (toggle) {
    toggle.addEventListener("click", function () {
      playing = !playing;
      arm();
    });
  }

  deck.addEventListener("mouseenter", function () {
    window.clearInterval(timer);
    timer = null;
  });
  deck.addEventListener("mouseleave", function () { arm(); });
  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
      window.clearInterval(timer);
      timer = null;
    } else {
      arm();
    }
  });
})();
