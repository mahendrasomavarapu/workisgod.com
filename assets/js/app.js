(function () {
  const siteTheme = document.getElementById("site-theme");
  if (siteTheme) {
    const saved = localStorage.getItem("wig_site_theme") || "atelier";
    if ([].some.call(siteTheme.options, function (o) { return o.value === saved; })) {
      siteTheme.value = saved;
    }
    document.documentElement.setAttribute("data-site", siteTheme.value);
    siteTheme.addEventListener("change", function () {
      document.documentElement.setAttribute("data-site", siteTheme.value);
      localStorage.setItem("wig_site_theme", siteTheme.value);
    });
  }

  const copyBtn = document.querySelector("[data-copy]");
  if (copyBtn) {
    copyBtn.addEventListener("click", async function () {
      const target = document.querySelector(copyBtn.getAttribute("data-copy"));
      if (!target) return;
      try {
        await navigator.clipboard.writeText(target.value || target.textContent);
        copyBtn.textContent = "Copied";
        setTimeout(function () { copyBtn.textContent = "Copy"; }, 1400);
      } catch (e) {
        target.select();
        document.execCommand("copy");
      }
    });
  }

  const form = document.getElementById("resume-form");
  const textarea = document.getElementById("raw_text");
  const theme = document.getElementById("theme");
  const mount = document.getElementById("preview-mount");
  const file = document.getElementById("resume_file");
  if (!form || !textarea || !mount) return;

  const csrf = form.querySelector('input[name="csrf"]');
  let timer = null;

  function refreshPreview() {
    const body = new URLSearchParams();
    body.set("csrf", csrf ? csrf.value : "");
    body.set("raw_text", textarea.value);
    body.set("theme", theme ? theme.value : "classic");
    fetch("/preview.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString()
    }).then(function (r) { return r.text(); }).then(function (html) {
      mount.innerHTML = html;
    }).catch(function () {});
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(refreshPreview, 280);
  }

  textarea.addEventListener("input", schedule);
  if (theme) theme.addEventListener("change", refreshPreview);

  if (file) {
    file.addEventListener("change", function () {
      const chosen = file.files && file.files[0];
      if (!chosen) return;
      const reader = new FileReader();
      reader.onload = function () {
        textarea.value = String(reader.result || "");
        refreshPreview();
      };
      reader.readAsText(chosen);
    });
  }

  const useAi = document.getElementById("use_ai");
  const saveBtn = document.getElementById("save-btn");
  const restoreBtn = document.getElementById("restore-btn");
  const intent = document.getElementById("intent");
  const improveBtn = document.getElementById("ai-improve-btn");
  const harderBtn = document.getElementById("ai-harder-btn");
  const statusEl = document.getElementById("ai-status");

  function setStatus(message, isError) {
    if (!statusEl) return;
    statusEl.textContent = message || "";
    statusEl.classList.toggle("is-error", !!isError);
  }

  function runAi(mode) {
    const label = mode === "harder" ? "Thinking harder…" : "AI agent writing…";
    setStatus(label, false);
    document.body.classList.add("ai-busy");
    const body = new URLSearchParams();
    body.set("csrf", csrf ? csrf.value : "");
    body.set("raw_text", textarea.value);
    body.set("mode", mode);
    fetch("/improve.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString()
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data || !data.ok) {
        const extra = (data && data.alerts && data.alerts.length) ? " " + data.alerts.join(" · ") : "";
        throw new Error(((data && data.error) || "AI request failed") + extra);
      }
      const before = textarea.value;
      textarea.value = data.text;
      textarea.classList.remove("ai-flash");
      void textarea.offsetWidth;
      textarea.classList.add("ai-flash");
      refreshPreview();
      if (!data.changed || before.trim() === String(data.text || "").trim()) {
        setStatus("AI ran, but the draft barely changed. Try harder thinking or add more detail.", true);
        return;
      }
      setStatus(mode === "harder"
        ? "Harder pass applied — check the new wording, then retry or save."
        : "Rewritten. Read the new draft, then improve again, think harder, or save.");
    }).catch(function (err) {
      setStatus(err.message || "AI request failed", true);
    }).finally(function () {
      document.body.classList.remove("ai-busy");
    });
  }

  const profileBtn = document.getElementById("profile-btn");
  const profileUrl = document.getElementById("profile_url");
  const profilePdf = document.getElementById("profile_pdf");
  if (profileBtn) {
    profileBtn.addEventListener("click", function () {
      const urlVal = profileUrl ? String(profileUrl.value || "").trim() : "";
      const hasPdf = profilePdf && profilePdf.files && profilePdf.files[0];
      if (!urlVal && !hasPdf) {
        setStatus("Add a LinkedIn/profile URL or a PDF first.", true);
        return;
      }
      setStatus("Capturing profile and writing the resume…", false);
      document.body.classList.add("ai-busy");
      const fd = new FormData();
      fd.set("csrf", csrf ? csrf.value : "");
      fd.set("raw_text", textarea.value);
      if (urlVal) fd.set("profile_url", urlVal);
      if (hasPdf) fd.set("profile_pdf", profilePdf.files[0]);
      fetch("/capture.php", { method: "POST", body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.ok) {
            const extra = (data && data.alerts && data.alerts.length) ? " " + data.alerts.join(" · ") : "";
            throw new Error(((data && data.error) || "Could not capture that profile") + extra);
          }
          textarea.value = data.text;
          textarea.classList.remove("ai-flash");
          void textarea.offsetWidth;
          textarea.classList.add("ai-flash");
          refreshPreview();
          const src = (data.sources || []).join(" + ") || "profile";
          setStatus("Built a resume from " + src + ". Review it, then think harder or save.");
        })
        .catch(function (err) {
          setStatus(err.message || "Could not capture that profile", true);
        })
        .finally(function () {
          document.body.classList.remove("ai-busy");
        });
    });
  }

  if (improveBtn) {
    improveBtn.addEventListener("click", function () { runAi("improve"); });
  }
  if (harderBtn) {
    harderBtn.addEventListener("click", function () { runAi("harder"); });
  }
  if (restoreBtn && intent) {
    restoreBtn.addEventListener("click", function () {
      intent.value = "restore";
    });
  }
  form.addEventListener("submit", function () {
    if (intent && intent.value === "restore") return;
    if (useAi && useAi.checked) {
      document.body.classList.add("ai-busy");
      if (saveBtn) saveBtn.textContent = "AI agent writing…";
    }
  });
})();
