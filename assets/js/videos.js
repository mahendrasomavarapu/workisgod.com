(function () {
  "use strict";

  const deck = document.getElementById("video-deck");
  if (!deck) return;

  let all = [];
  const raw = document.getElementById("video-playlist");
  try {
    all = JSON.parse(raw ? raw.textContent : "[]");
  } catch (e) {
    all = [];
  }
  if (!Array.isArray(all)) all = [];
  all = all.filter(function (item) {
    return item && item.embed && String(item.embed).indexOf("https://") === 0;
  }).slice(0, 1000);

  const frame = document.getElementById("video-frame");
  const countEl = document.getElementById("video-count");
  const providerEl = document.getElementById("video-provider");
  const modeEl = document.getElementById("video-mode");
  const thumbsEl = document.getElementById("video-thumbs");
  const stage = document.getElementById("video-stage");
  const fullBtn = document.getElementById("video-full");
  const soundBtn = document.getElementById("video-sound");
  const barEl = document.getElementById("video-bar");
  const interval = Math.max(6000, parseInt(deck.getAttribute("data-interval") || "12000", 10) || 12000);
  const reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const order = ["all", "youtube", "vimeo", "dailymotion", "facebook", "instagram", "tiktok", "twitch", "archive"];
  const present = {};
  all.forEach(function (item) { present[item.platform] = true; });
  const providers = order.filter(function (p) {
    return p === "all" || present[p];
  });

  let provider = "all";
  let shorts = false;
  let list = [];
  let index = 0;
  let timer = null;
  let playing = !reduce;
  let sound = false;

  function activeList() {
    let items = all;
    if (provider !== "all") {
      items = items.filter(function (it) { return it.platform === provider; });
    }
    if (shorts) {
      const clipped = items.filter(function (it) {
        return it.kind === "short" || it.platform === "tiktok" || it.platform === "instagram";
      });
      if (clipped.length) items = clipped;
    }
    return items.length ? items : all;
  }

  function embedSrc(item) {
    const url = String(item.embed);
    const join = url.indexOf("?") >= 0 ? "&" : "?";
    const origin = encodeURIComponent(window.location.origin);
    if (item.platform === "youtube") {
      let q = "rel=0&modestbranding=1&playsinline=1&enablejsapi=1&origin=" + origin + "&autoplay=1";
      if (!sound) q += "&mute=1";
      return url + join + q;
    }
    if (item.platform === "vimeo") {
      return url + join + "autoplay=1&title=0&byline=0" + (sound ? "" : "&muted=1");
    }
    if (item.platform === "dailymotion") {
      return url + join + "autoplay=1" + (sound ? "" : "&mute=1");
    }
    return url;
  }

  function ytCommand(func, args) {
    try {
      if (!frame || !frame.contentWindow) return;
      frame.contentWindow.postMessage(JSON.stringify({
        event: "command",
        func: func,
        args: args || []
      }), "*");
    } catch (e) {}
  }

  function reloadCurrent() {
    if (!list[index] || !frame) return;
    frame.src = embedSrc(list[index]);
  }

  function setSound(on) {
    sound = !!on;
    if (soundBtn) soundBtn.textContent = sound ? "Mute" : "Unmute";
    if (sound) {
      playing = false;
      ytCommand("unMute");
      ytCommand("setVolume", [100]);
      ytCommand("playVideo");
    } else {
      ytCommand("mute");
    }
    reloadCurrent();
    arm();
  }

  function letter(item) {
    return (item.platform || "?").charAt(0).toUpperCase();
  }

  function isFull() {
    return !!(document.fullscreenElement || document.webkitFullscreenElement);
  }

  function paintThumbs() {
    if (!thumbsEl) return;
    thumbsEl.textContent = "";
    list.forEach(function (item, i) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "video-thumb" + (i === index ? " is-on" : "");
      btn.setAttribute("role", "option");
      btn.setAttribute("aria-selected", i === index ? "true" : "false");
      if (item.thumb) {
        const img = document.createElement("img");
        img.src = item.thumb;
        img.alt = "";
        img.loading = "lazy";
        img.decoding = "async";
        btn.appendChild(img);
      } else {
        const span = document.createElement("span");
        span.className = "video-thumb-letter";
        span.textContent = letter(item);
        btn.appendChild(span);
      }
      btn.addEventListener("click", function () {
        show(i, true);
        arm();
      });
      thumbsEl.appendChild(btn);
    });
    const on = thumbsEl.querySelector(".is-on");
    if (on && on.scrollIntoView) {
      on.scrollIntoView({ inline: "center", block: "nearest", behavior: reduce ? "auto" : "smooth" });
    }
  }

  function show(i, rebuildThumbs) {
    list = activeList();
    if (!list.length || !frame) return;
    index = ((i % list.length) + list.length) % list.length;
    const item = list[index];
    frame.removeAttribute("src");
    window.setTimeout(function () {
      frame.src = embedSrc(item);
    }, 40);
    if (countEl) countEl.textContent = (index + 1) + " / " + list.length;
    if (providerEl) providerEl.textContent = provider;
    if (modeEl) modeEl.hidden = !shorts;
    if (stage) stage.classList.toggle("is-shorts", shorts);
    if (rebuildThumbs !== false) paintThumbs();
    else if (thumbsEl) {
      const nodes = thumbsEl.children;
      for (let n = 0; n < nodes.length; n++) {
        nodes[n].classList.toggle("is-on", n === index);
        nodes[n].setAttribute("aria-selected", n === index ? "true" : "false");
      }
      const on = thumbsEl.querySelector(".is-on");
      if (on && on.scrollIntoView) {
        on.scrollIntoView({ inline: "center", block: "nearest", behavior: reduce ? "auto" : "smooth" });
      }
    }
  }

  function next() { show(index + 1, false); }
  function prev() { show(index - 1, false); }

  function cycleProvider(dir) {
    if (providers.length < 2) return;
    let i = providers.indexOf(provider);
    if (i < 0) i = 0;
    i = (i + dir + providers.length) % providers.length;
    provider = providers[i];
    show(0, true);
  }

  function toggleShorts() {
    shorts = !shorts;
    show(0, true);
  }

  function arm() {
    window.clearInterval(timer);
    timer = null;
    if (playing && list.length > 1 && !isFull()) {
      timer = window.setInterval(next, interval);
    }
  }

  function toggleFull() {
    const el = stage || deck;
    if (isFull()) {
      const exit = document.exitFullscreen || document.webkitExitFullscreen;
      if (exit) exit.call(document);
      return;
    }
    const req = el.requestFullscreen || el.webkitRequestFullscreen;
    if (req) req.call(el);
  }

  if (!all.length) return;
  list = activeList();
  show(0, true);
  arm();

  if (soundBtn) {
    soundBtn.addEventListener("click", function () { setSound(!sound); });
  }
  if (fullBtn) {
    fullBtn.addEventListener("click", function () { toggleFull(); });
  }
  document.addEventListener("fullscreenchange", function () {
    if (fullBtn) fullBtn.textContent = isFull() ? "Exit" : "Fullscreen";
    arm();
  });
  document.addEventListener("webkitfullscreenchange", function () {
    if (fullBtn) fullBtn.textContent = isFull() ? "Exit" : "Fullscreen";
    arm();
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "ArrowRight") { next(); arm(); }
    else if (e.key === "ArrowLeft") { prev(); arm(); }
    else if (e.key === "ArrowUp") { cycleProvider(1); arm(); }
    else if (e.key === "ArrowDown") { toggleShorts(); arm(); }
    else if (e.key === "f" || e.key === "F") { toggleFull(); }
  });

  function bindSwipe(el, mode) {
    if (!el) return;
    let px = 0, py = 0, tracking = false;
    function pt(e) {
      if (e.changedTouches && e.changedTouches[0]) return e.changedTouches[0];
      if (e.touches && e.touches[0]) return e.touches[0];
      return e;
    }
    function down(e) {
      if (e.target && e.target.closest && e.target.closest("button, a")) return;
      const t = pt(e);
      px = t.clientX;
      py = t.clientY;
      tracking = true;
    }
    function up(e) {
      if (!tracking) return;
      tracking = false;
      const t = pt(e);
      const dx = t.clientX - px;
      const dy = t.clientY - py;
      const ax = Math.abs(dx);
      const ay = Math.abs(dy);
      if (ax < 36 && ay < 36) return;
      if (mode === "bar") {
        if (ay >= ax) {
          if (dy < 0) cycleProvider(1);
          else toggleShorts();
        } else {
          if (dx < 0) next();
          else prev();
        }
      } else if (ax >= ay) {
        if (dx < 0) next();
        else prev();
      }
      arm();
    }
    if (window.PointerEvent) {
      el.addEventListener("pointerdown", down);
      el.addEventListener("pointerup", up);
      el.addEventListener("pointercancel", function () { tracking = false; });
    } else {
      el.addEventListener("touchstart", down, { passive: true });
      el.addEventListener("touchend", up, { passive: true });
    }
  }

  bindSwipe(barEl, "bar");
  bindSwipe(thumbsEl, "thumbs");

  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
      window.clearInterval(timer);
      timer = null;
    } else {
      arm();
    }
  });
})();
