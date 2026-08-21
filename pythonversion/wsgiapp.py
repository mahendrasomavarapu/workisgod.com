"""Work is God — Python WSGI application (stdlib only)."""

from __future__ import annotations

import base64
import hashlib
import hmac
import json
import re
import secrets
from http.cookies import SimpleCookie
from wig import config
from wig.auth import client_ip, parse_form, parse_query, send_otp, verify_admin, verify_otp
from wig.db import (
    admin_by_id,
    connect,
    iso_now,
    logins_on,
    news_on,
    setting,
    setting_set,
    user_by_id,
    videos_on,
)
from wig.html import (
    csrf_field,
    footer,
    h,
    header,
    hp_field,
    parse_resume,
    render_resume,
    sample_resume,
    u,
)

PREFIX = config.PREFIX


def application(environ, start_response):
    try:
        return _dispatch(environ, start_response)
    except Exception:
        import traceback

        traceback.print_exc()
        start_response("500 Internal Server Error", [("Content-Type", "text/html; charset=utf-8")])
        return [b"<h1>Python edition hit a snag.</h1><p>Please try again in a moment.</p>"]


def _dispatch(environ, start_response):
    environ = _normalize(environ)
    path = environ.get("PATH_INFO") or "/"
    method = environ.get("REQUEST_METHOD", "GET").upper()
    sess = _session(environ)
    ctx = {"environ": environ, "sess": sess, "form": {}, "query": parse_query(environ)}
    if method == "POST":
        ctx["form"] = parse_form(environ)
    handler = ROUTES.get((method, path)) or ROUTES.get(("ANY", path))
    if not handler:
        for prefix, fn in PREFIX_ROUTES:
            if path.startswith(prefix):
                handler = fn
                break
    if handler is None:
        status, headers, body = page_404(ctx)
    else:
        status, headers, body = handler(ctx)
    cookie = _save_session(sess, environ)
    if cookie:
        headers = list(headers) + [("Set-Cookie", cookie)]
    start_response(status, headers)
    if isinstance(body, str):
        body = body.encode("utf-8")
    return [body]


def _normalize(environ: dict) -> dict:
    uri = (environ.get("REQUEST_URI") or "").split("?", 1)[0]
    if uri.startswith(PREFIX):
        rest = uri[len(PREFIX) :] or "/"
        environ["PATH_INFO"] = rest if rest.startswith("/") else "/" + rest
        environ["SCRIPT_NAME"] = PREFIX
    path = environ.get("PATH_INFO") or "/"
    if len(path) > 1 and path.endswith("/"):
        environ["PATH_INFO"] = path.rstrip("/") or "/"
    return environ


def _session(environ: dict) -> dict:
    jar = SimpleCookie()
    if environ.get("HTTP_COOKIE"):
        jar.load(environ["HTTP_COOKIE"])
    raw = jar["wig_py"].value if "wig_py" in jar else ""
    data = _unsign(raw)
    return data if isinstance(data, dict) else {}


def _secret() -> bytes:
    return (config.SMTP_PASS or "wig-python-fallback-secret").encode()


def _sign(obj: dict) -> str:
    payload = json.dumps(obj, separators=(",", ":"), sort_keys=True).encode()
    b64 = base64.urlsafe_b64encode(payload).decode().rstrip("=")
    sig = hmac.new(_secret(), payload, hashlib.sha256).hexdigest()[:32]
    return b64 + "." + sig


def _unsign(raw: str):
    if "." not in raw:
        return {}
    b64, sig = raw.rsplit(".", 1)
    pad = "=" * (-len(b64) % 4)
    try:
        payload = base64.urlsafe_b64decode(b64 + pad)
    except Exception:
        return {}
    expect = hmac.new(_secret(), payload, hashlib.sha256).hexdigest()[:32]
    if not hmac.compare_digest(expect, sig):
        return {}
    try:
        return json.loads(payload.decode())
    except Exception:
        return {}


def _save_session(sess: dict, environ: dict) -> str:
    raw = _sign(sess)
    secure = "; Secure" if (environ.get("HTTPS") == "on" or environ.get("wsgi.url_scheme") == "https") else ""
    return f"wig_py={raw}; Path={PREFIX}; HttpOnly; SameSite=Lax; Max-Age={config.SESSION_DAYS * 86400}{secure}"


def csrf(sess: dict) -> str:
    if not sess.get("csrf"):
        sess["csrf"] = secrets.token_hex(16)
    return sess["csrf"]


def csrf_ok(ctx: dict) -> bool:
    a = str(ctx["sess"].get("csrf") or "")
    b = str(ctx["form"].get("csrf") or "")
    return bool(a) and bool(b) and hmac.compare_digest(a, b)


def current_user(sess: dict):
    uid = sess.get("user_id")
    return user_by_id(int(uid)) if uid else None


def current_admin(sess: dict):
    aid = sess.get("admin_id")
    return admin_by_id(int(aid)) if aid else None


def html_response(body: str, status: str = "200 OK", extra=None):
    headers = [
        ("Content-Type", "text/html; charset=utf-8"),
        ("X-Frame-Options", "SAMEORIGIN"),
        ("X-Content-Type-Options", "nosniff"),
        ("Referrer-Policy", "strict-origin-when-cross-origin"),
    ]
    if extra:
        headers.extend(extra)
    return status, headers, body


def redirect(path: str):
    loc = path if path.startswith("http") else u(path)
    return "302 Found", [("Location", loc)], ""


def page(ctx, title, inner, **opts):
    user = current_user(ctx["sess"])
    admin = current_admin(ctx["sess"])
    flash = ctx["sess"].pop("flash", None)
    head = header(title, body=opts.get("body", ""), description=opts.get("description", ""), path=opts.get("path", "/"), user=user, admin=admin)
    scripts = opts.get("scripts")
    return html_response(head + (f'<div class="flash flash-{h(flash["type"])}">{h(flash["message"])}</div>' if flash else "") + inner + footer(scripts))


# --- routes ---

def home(ctx):
    user = current_user(ctx["sess"])
    if user:
        return redirect("/waiting" if (user.get("status") or "active") == "pending" else "/editor")
    tok = csrf(ctx["sess"])
    news = _news_promo() if news_on() else ""
    vids = _video_promo() if videos_on() else ""
    tools = _tools_promo()
    inner = f"""
<main id="main" class="wrap hero-wrap">
<section class="hero">
<p class="eyebrow">Python edition</p>
<h1>Your work is received<br>with full honors.</h1>
<p class="lede">This is the Python WSGI version of Work is God. Sign in with any email. Same rooms, new engine.</p>
<form class="hero-form" method="post" action="{u("/login")}">
{csrf_field(tok)}{hp_field()}
<input type="hidden" name="action" value="send">
<label class="sr-only" for="email">Email</label>
<input id="email" name="email" type="email" required maxlength="180" placeholder="you@gmail.com" autocomplete="email">
<button type="submit">Send my private code</button>
</form>
<p class="fine">A six-digit code arrives in your inbox. No password is kept.</p>
</section>
<ol class="steps">
<li><span>01</span><h2>Sign in</h2><p>Any inbox. Enter the OTP.</p></li>
<li><span>02</span><h2>Paste</h2><p>Drop in plain text. We dress the page.</p></li>
<li><span>03</span><h2>Share</h2><p>A clean link under <code>/pythonversion/resumes/</code>.</p></li>
</ol>
{news}{vids}{tools}
</main>
"""
    return page(ctx, config.SITE_NAME, inner, body="page-home", path="/")


def about(ctx):
    inner = f"""
<main id="main" class="wrap prose">
<p class="eyebrow">About</p>
<h1>Work, received as it deserves.</h1>
<p class="lede">This Python edition is a full WSGI port of Work is God, hosted at <code>{h(PREFIX)}</code>. It shares the same SQLite rooms as the PHP site.</p>
<ul>
<li>Sign in with a one-time email code.</li>
<li>Publish a themed text resume.</li>
<li>News, videos, and twenty browser tools.</li>
</ul>
<p>Built with the Python standard library — no Flask required — so it can run as CGI or Passenger on Namecheap.</p>
<p><a class="button" href="{u("/login")}">Sign in</a> <a class="text-link" href="/">PHP edition →</a></p>
</main>
"""
    return page(ctx, "About", inner, path="/about")


def safety(ctx):
    inner = f"""
<main id="main" class="wrap prose">
<p class="eyebrow">Safety</p>
<h1>Use the site safely.</h1>
<ul>
<li>Treat the 6-digit code as a key.</li>
<li>Public resume URLs are visible to anyone with the link.</li>
<li>Tools run in your browser. Do not paste production secrets.</li>
</ul>
</main>
"""
    return page(ctx, "Safety", inner, path="/safety")


def login(ctx):
    user = current_user(ctx["sess"])
    if user:
        return redirect("/editor")
    env = ctx["environ"]
    form = ctx["form"]
    q = ctx["query"]
    stage = "email"
    email = (form.get("email") or q.get("email") or "").strip().lower()
    error = ""
    if env["REQUEST_METHOD"] == "POST":
        if not csrf_ok(ctx) or form.get("website_hp"):
            stage = "otp"
        elif form.get("action") == "send":
            error = send_otp(email, client_ip(env))
            if not error:
                stage = "otp"
        elif form.get("action") == "verify":
            error, user = verify_otp(email, form.get("code") or "", client_ip(env))
            if user:
                ctx["sess"]["user_id"] = user["id"]
                ctx["sess"].pop("admin_id", None)
                return redirect("/waiting" if user.get("status") == "pending" else "/editor")
            stage = "otp"
    elif email:
        stage = "otp"
    tok = csrf(ctx["sess"])
    title = "Your code is waiting" if stage == "otp" else "Be received"
    if stage == "otp":
        body_form = f"""
<p class="lede">A private code is on its way to <strong>{h(email)}</strong>.</p>
<form method="post">{csrf_field(tok)}{hp_field()}
<input type="hidden" name="action" value="verify">
<input type="hidden" name="email" value="{h(email)}">
<label for="code">One-time code</label>
<input id="code" name="code" inputmode="numeric" maxlength="6" required autofocus>
<button type="submit">Sign in</button></form>
"""
    else:
        body_form = f"""
<p class="lede">Any inbox will do. We send a one-time code.</p>
<form method="post">{csrf_field(tok)}{hp_field()}
<input type="hidden" name="action" value="send">
<label for="email">Email</label>
<input id="email" name="email" type="email" required autofocus value="{h(email)}">
<button type="submit">Send login code</button></form>
"""
    inner = f"""
<a class="admin-corner" href="{u("/admin/login")}">Admin</a>
<main id="main"><div class="wrap auth-wrap">
<section class="auth-card">
<p class="eyebrow">Python table</p>
<h1>{title}</h1>
{"<p class='form-error'>" + h(error) + "</p>" if error else ""}
{body_form}
</section></div></main>
"""
    return page(ctx, "Sign in", inner, body="page-auth", path="/login")


def logout(ctx):
    ctx["sess"].clear()
    return redirect("/")


def editor(ctx):
    user = current_user(ctx["sess"])
    if not user:
        ctx["sess"]["flash"] = {"type": "error", "message": "Sign in with your email to continue."}
        return redirect("/login")
    if not logins_on():
        return redirect("/login")
    if (user.get("status") or "active") == "pending":
        return redirect("/waiting")
    con = connect()
    try:
        row = con.execute("SELECT * FROM resumes WHERE user_id = ?", (user["id"],)).fetchone()
        row = dict(row) if row else {}
    finally:
        con.close()
    error = ""
    if ctx["environ"]["REQUEST_METHOD"] == "POST" and csrf_ok(ctx) and not ctx["form"].get("website_hp"):
        text = (ctx["form"].get("raw_text") or "")[: config.RESUME_MAX]
        slug = re.sub(r"[^a-z0-9-]+", "-", (ctx["form"].get("slug") or "resume").lower()).strip("-")[:40]
        theme = ctx["form"].get("theme") or "classic"
        if theme not in config.RESUME_THEMES:
            theme = "classic"
        if not text.strip():
            error = "Paste your resume text first."
        else:
            con = connect()
            try:
                existing = con.execute("SELECT user_id FROM resumes WHERE slug = ?", (slug,)).fetchone()
                if existing and int(existing["user_id"]) != int(user["id"]):
                    slug = f"{slug}-{user['id']}"
                now = iso_now()
                have = con.execute("SELECT id FROM resumes WHERE user_id = ?", (user["id"],)).fetchone()
                if have:
                    con.execute(
                        "UPDATE resumes SET slug=?, theme=?, raw_text=?, updated_at=? WHERE user_id=?",
                        (slug, theme, text, now, user["id"]),
                    )
                else:
                    con.execute(
                        "INSERT INTO resumes (user_id, slug, theme, raw_text, source_text, ai_used, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)",
                        (user["id"], slug, theme, text, text, 0, now, now),
                    )
                con.commit()
                row = dict(con.execute("SELECT * FROM resumes WHERE user_id = ?", (user["id"],)).fetchone())
            finally:
                con.close()
            ctx["sess"]["flash"] = {"type": "ok", "message": "Saved."}
            return redirect("/editor")
    tok = csrf(ctx["sess"])
    text = row.get("raw_text") or ""
    slug = row.get("slug") or ""
    theme = row.get("theme") or "classic"
    opts = "".join(
        f'<option value="{h(k)}"{" selected" if k==theme else ""}>{h(v)}</option>' for k, v in config.RESUME_THEMES.items()
    )
    share = f'{config.SITE_URL.rstrip("/")}{u("/resumes/" + slug)}' if slug else ""
    inner = f"""
<main id="main" class="wrap">
<div class="editor-head">
<div><p class="eyebrow">Python editor</p><h1>My resume</h1></div>
{"<p>Public URL <code>" + h(share) + "</code></p>" if share else ""}
</div>
{"<p class='form-error'>" + h(error) + "</p>" if error else ""}
<form method="post" class="editor-grid" id="resume-form">
{csrf_field(tok)}{hp_field()}
<div class="editor-controls">
<div class="field"><label for="slug">Slug</label>
<div class="slug-row"><span>/pythonversion/resumes/</span>
<input id="slug" name="slug" value="{h(slug)}" required></div></div>
<div class="field"><label for="theme">Theme</label>
<select id="theme" name="theme">{opts}</select></div>
<button type="submit">Save</button>
</div>
<div class="editor-text">
<label for="raw_text">Text</label>
<textarea id="raw_text" name="raw_text" required>{h(text)}</textarea>
</div>
<div class="editor-preview">
<div class="preview-label">Preview</div>
<div class="preview-mount">{render_resume(parse_resume(text or sample_resume()), theme)}</div>
</div>
</form>
</main>
"""
    return page(ctx, "Editor", inner, path="/editor")


def waiting(ctx):
    inner = """<main id="main" class="wrap prose"><p class="eyebrow">Hold</p>
<h1>Your place is reserved.</h1><p class="lede">An administrator will admit you.</p></main>"""
    return page(ctx, "Waiting", inner, path="/waiting")


def account(ctx):
    user = current_user(ctx["sess"])
    if not user:
        return redirect("/login")
    inner = f"""<main id="main" class="wrap prose"><p class="eyebrow">Account</p>
<h1>{h(user.get("email"))}</h1>
<p>Python edition uses the same SQLite rooms. Delete from the PHP account page if you need OTP-protected removal.</p>
<p><a class="button" href="{u("/logout")}">Sign out</a></p></main>"""
    return page(ctx, "Account", inner, path="/account")


def public_resume(ctx):
    slug = (ctx["environ"].get("PATH_INFO") or "").rstrip("/").split("/")[-1]
    con = connect()
    try:
        row = con.execute("SELECT * FROM resumes WHERE slug = ?", (slug,)).fetchone()
    finally:
        con.close()
    if not row:
        return page_404(ctx)
    doc = parse_resume(row["raw_text"] or "")
    inner = f'<main class="public-wrap">{render_resume(doc, row["theme"] or "classic")}</main>'
    return page(ctx, doc.get("name") or slug, inner, path="/resumes/" + slug)


def tools(ctx):
    tools = config.TOOLS
    nav = "".join(
        f'<a href="#{h(t["id"])}" data-tool="{h(t["id"])}"><strong>{h(t["name"])}</strong><span>{h(t["cli"])}</span></a>'
        for t in tools
    )
    panels = []
    for i, t in enumerate(tools):
        hid = "" if i == 0 else " hidden"
        on = " is-on" if i == 0 else ""
        panels.append(
            f'<section id="{h(t["id"])}" class="tool-panel{on}" data-tool="{h(t["id"])}"{hid}>'
            f'<p class="eyebrow">{h(t["cli"])}</p><h2>{h(t["name"])}</h2>'
            f'<p class="hint">{h(t["blurb"])}</p>'
            f'<div class="tool-body" data-mount="{h(t["id"])}"></div></section>'
        )
    inner = f"""
<main id="main" class="wrap tools-page">
<header class="tools-hero">
<p class="eyebrow">Operations</p>
<h1>Twenty tools, in the browser.</h1>
<input id="tool-search" type="search" placeholder="Filter tools">
</header>
<div class="tools-layout">
<nav class="tools-nav" id="tools-nav">{nav}</nav>
<div class="tools-workspace">{"".join(panels)}</div>
</div>
</main>
"""
    return page(ctx, "Tools", inner, path="/tools", scripts=["/assets/js/tools.js"])


def news(ctx):
    if not news_on():
        return page_404(ctx)
    cache = _json_file(config.DATA_DIR / "news-cache.json")
    items_t = (cache.get("sectors") or {}).get("telecom") or []
    items_b = (cache.get("sectors") or {}).get("banking") or []
    path = ctx["environ"].get("PATH_INFO") or "/news"

    def lis(items):
        out = ['<ol class="news-list">']
        for it in items[:12]:
            out.append(
                f'<li class="news-item"><p class="news-meta">{h(it.get("source"))}</p>'
                f'<h3><a href="{h(it.get("url"))}" rel="noopener noreferrer" target="_blank">{h(it.get("title"))}</a></h3>'
                f'<p class="news-summary">{h(it.get("summary"))}</p></li>'
            )
        out.append("</ol>")
        return "".join(out)

    if path.endswith("/telecom"):
        body = f'<main class="wrap news-page"><h1>Telecom</h1>{lis(items_t)}</main>'
        return page(ctx, "Telecom news", body, path="/news/telecom")
    if path.endswith("/banking"):
        body = f'<main class="wrap news-page"><h1>Banking</h1>{lis(items_b)}</main>'
        return page(ctx, "Banking news", body, path="/news/banking")
    inner = f"""
<main class="wrap news-page">
<p class="eyebrow">Desk</p><h1>Technical news.</h1>
<div class="news-home-cols">
<section><h2>Telecom</h2>{lis(items_t[:6])}<p><a class="text-link" href="{u("/news/telecom")}">Full desk →</a></p></section>
<section><h2>Banking</h2>{lis(items_b[:6])}<p><a class="text-link" href="{u("/news/banking")}">Full desk →</a></p></section>
</div></main>
"""
    return page(ctx, "News", inner, path="/news")


def videos(ctx):
    if not videos_on():
        return page_404(ctx)
    cache = _json_file(config.DATA_DIR / "videos-cache.json")
    items = cache.get("items") or []
    public = [
        {
            "platform": it.get("platform"),
            "id": it.get("id"),
            "kind": it.get("kind") or "video",
            "thumb": it.get("thumb") or "",
            "embed": it.get("embed"),
            "watch": it.get("watch"),
        }
        for it in items[:1000]
        if it.get("embed")
    ]
    payload = json.dumps(public, ensure_ascii=False).replace("</", "<\\/")
    inner = f"""
<main id="main" class="videos-page">
<script type="application/json" id="video-playlist">{payload}</script>
<div id="video-deck" class="video-deck" data-interval="12000">
<div id="video-stage" class="video-stage">
<div class="video-glass">
<iframe id="video-frame" title="Embedded video" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>
</div>
<div class="video-bar" id="video-bar">
<span id="video-provider">all</span>
<span id="video-mode" class="video-mode" hidden>shorts</span>
<span id="video-count">0 / 0</span>
<button type="button" id="video-sound">Unmute</button>
<button type="button" id="video-full" class="secondary">Fullscreen</button>
</div>
<div id="video-thumbs" class="video-thumbs"></div>
</div></div></main>
"""
    return page(ctx, "Videos", inner, path="/videos", scripts=["/assets/js/videos.js"], body="page-videos")


def admin_login(ctx):
    if current_admin(ctx["sess"]):
        return redirect("/admin")
    error = ""
    if ctx["environ"]["REQUEST_METHOD"] == "POST":
        if csrf_ok(ctx) and not ctx["form"].get("website_hp"):
            error, admin = verify_admin(
                ctx["form"].get("email") or "",
                ctx["form"].get("password") or "",
                client_ip(ctx["environ"]),
            )
            if admin:
                ctx["sess"]["admin_id"] = admin["id"]
                ctx["sess"].pop("user_id", None)
                return redirect("/admin")
        else:
            error = "Those admin credentials are not correct."
    tok = csrf(ctx["sess"])
    inner = f"""
<main class="wrap auth-wrap"><section class="auth-card">
<p class="eyebrow">Backoffice</p><h1>Admin sign in</h1>
<p class="lede">Python edition. Regular users should use email codes.</p>
{"<p class='form-error'>" + h(error) + "</p>" if error else ""}
<form method="post">{csrf_field(tok)}{hp_field()}
<label>Email</label><input name="email" type="email" required>
<label>Password</label><input name="password" type="password" required>
<button type="submit">Sign in</button></form>
</section></main>
"""
    return page(ctx, "Admin sign in", inner, body="page-auth", path="/admin/login")


def admin_home(ctx):
    admin = current_admin(ctx["sess"])
    if not admin:
        return redirect("/admin/login")
    con = connect()
    try:
        users = con.execute("SELECT COUNT(*) c FROM users").fetchone()["c"]
        resumes = con.execute("SELECT COUNT(*) c FROM resumes").fetchone()["c"]
    finally:
        con.close()
    inner = f"""
<main class="wrap admin-wrap">
<p class="eyebrow">Python admin</p><h1>Overview</h1>
<ul class="admin-stats">
<li><strong>{users}</strong><span>Users</span></li>
<li><strong>{resumes}</strong><span>Resumes</span></li>
</ul>
<p><a href="{u("/admin/settings")}">Settings</a> · <a href="{u("/admin/users")}">Users</a></p>
</main>
"""
    return page(ctx, "Admin", inner, path="/admin")


def admin_users(ctx):
    if not current_admin(ctx["sess"]):
        return redirect("/admin/login")
    con = connect()
    try:
        rows = con.execute("SELECT id, email, status, created_at FROM users ORDER BY id DESC LIMIT 100").fetchall()
    finally:
        con.close()
    tr = "".join(
        f"<tr><td>{h(r['email'])}</td><td>{h(r['status'] or 'active')}</td><td>{h(r['created_at'])}</td></tr>"
        for r in rows
    ) or '<tr><td colspan="3">No users yet.</td></tr>'
    inner = f"""<main class="wrap admin-wrap"><h1>Users</h1>
<table class="admin-table"><thead><tr><th>Email</th><th>Status</th><th>Joined</th></tr></thead>
<tbody>{tr}</tbody></table></main>"""
    return page(ctx, "Users", inner, path="/admin/users")


def admin_settings(ctx):
    if not current_admin(ctx["sess"]):
        return redirect("/admin/login")
    if ctx["environ"]["REQUEST_METHOD"] == "POST" and csrf_ok(ctx):
        setting_set("news_enabled", "1" if ctx["form"].get("news_enabled") else "0")
        setting_set("videos_enabled", "1" if ctx["form"].get("videos_enabled") else "0")
        setting_set("user_logins_enabled", "1" if ctx["form"].get("user_logins_enabled") else "0")
        if ctx["form"].get("video_tags"):
            setting_set("video_tags", ctx["form"]["video_tags"][:2000])
        ctx["sess"]["flash"] = {"type": "ok", "message": "Settings saved (Python edition)."}
        return redirect("/admin/settings")
    tok = csrf(ctx["sess"])
    tags_default = "funny\nloving\ndance\nmusic\ntrending\nvibing\nenjoy"
    tags = h(setting("video_tags", tags_default))
    inner = f"""
<main class="wrap admin-settings">
<h1>Settings</h1>
<form method="post" class="settings-form">{csrf_field(tok)}
<section class="settings-card"><h2>Doors</h2>
<label class="switch-row"><span class="switch-copy"><strong>User logins</strong></span>
<span class="switch"><input class="sr-only" type="checkbox" name="user_logins_enabled" value="1" {"checked" if logins_on() else ""}>
<span class="switch-ui"></span></span></label>
<label class="switch-row"><span class="switch-copy"><strong>News</strong></span>
<span class="switch"><input class="sr-only" type="checkbox" name="news_enabled" value="1" {"checked" if news_on() else ""}>
<span class="switch-ui"></span></span></label>
<label class="switch-row"><span class="switch-copy"><strong>Videos</strong></span>
<span class="switch"><input class="sr-only" type="checkbox" name="videos_enabled" value="1" {"checked" if videos_on() else ""}>
<span class="switch-ui"></span></span></label>
<label class="settings-field" for="video_tags">Video tags</label>
<textarea id="video_tags" name="video_tags" rows="6">{tags}</textarea>
<button type="submit">Save</button>
</section></form>
<p class="hint">OTP limits, news sources, and video refresh still live on the PHP admin. This page shares the same settings table.</p>
</main>
"""
    return page(ctx, "Settings", inner, path="/admin/settings")


def admin_logout(ctx):
    ctx["sess"].pop("admin_id", None)
    return redirect("/admin/login")


def page_404(ctx):
    inner = f"""<main class="wrap prose"><p class="eyebrow">404</p>
<h1>This room is empty.</h1>
<p><a class="button" href="{u("/")}">Return</a></p></main>"""
    status, headers, body = page(ctx, "Not found", inner, path="/404")
    return "404 Not Found", headers, body


def _json_file(path):
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return {}


def _news_promo() -> str:
    return f"""<section class="news-promo"><div class="news-promo-copy">
<p class="eyebrow">News</p><h2>Telecom and banking.</h2>
<p><a class="text-link" href="{u("/news")}">Open the desk →</a></p></div></section>"""


def _video_promo() -> str:
    return f"""<section class="videos-promo"><div class="videos-promo-copy">
<p class="eyebrow">Videos</p><h2>Open-web embeds.</h2>
<p><a class="text-link" href="{u("/videos")}">Open the door →</a></p></div></section>"""


def _tools_promo() -> str:
    cards = "".join(
        f'<a class="tool-card" href="{u("/tools")}#{h(t["id"])}"><span class="cli"><code>{h(t["cli"])}</code></span>'
        f'<strong>{h(t["name"])}</strong><small>{h(t["blurb"])}</small></a>'
        for t in config.TOOLS
    )
    return f"""<section class="tools-promo"><div class="tools-promo-copy">
<p class="eyebrow">Tools</p><h2>Twenty ops tools.</h2>
<p><a class="text-link" href="{u("/tools")}">Open the bench →</a></p></div>
<div class="tools-grid">{cards}</div></section>"""


ROUTES = {
    ("GET", "/"): home,
    ("HEAD", "/"): home,
    ("GET", "/about"): about,
    ("GET", "/safety"): safety,
    ("GET", "/login"): login,
    ("POST", "/login"): login,
    ("GET", "/logout"): logout,
    ("GET", "/editor"): editor,
    ("POST", "/editor"): editor,
    ("GET", "/waiting"): waiting,
    ("GET", "/account"): account,
    ("GET", "/tools"): tools,
    ("GET", "/news"): news,
    ("GET", "/news/telecom"): news,
    ("GET", "/news/banking"): news,
    ("GET", "/videos"): videos,
    ("GET", "/admin"): admin_home,
    ("GET", "/admin/login"): admin_login,
    ("POST", "/admin/login"): admin_login,
    ("GET", "/admin/logout"): admin_logout,
    ("GET", "/admin/users"): admin_users,
    ("GET", "/admin/settings"): admin_settings,
    ("POST", "/admin/settings"): admin_settings,
}

PREFIX_ROUTES = [
    ("/resumes/", public_resume),
]
