from __future__ import annotations

import html as htmllib
import json
from typing import Any, Optional


from . import config
from .db import news_on, videos_on


def h(value: Any) -> str:
    return htmllib.escape("" if value is None else str(value), quote=True)


def u(path: str) -> str:
    if path.startswith("http://") or path.startswith("https://"):
        return path
    return config.PREFIX + (path if path.startswith("/") else "/" + path)


def asset(path: str) -> str:
    return path


def header(
    title: str,
    *,
    body: str = "",
    description: str = "",
    path: str = "/",
    user: Optional[dict] = None,
    admin: Optional[dict] = None,
    extra_head: str = "",
) -> str:
    desc = description or "Write a text resume, pick a theme, and share a stable public URL on Work is God."
    full = title if title == config.SITE_NAME else f"{title} · {config.SITE_NAME}"
    canonical = config.SITE_URL.rstrip("/") + u(path)
    nav = [
        f'<a href="{u("/about")}">About</a>',
    ]
    if news_on():
        nav.append(f'<a href="{u("/news")}">News</a>')
    if videos_on():
        nav.append(f'<a href="{u("/videos")}">Videos</a>')
    nav.append(f'<a href="{u("/tools")}">Tools</a>')
    nav.append(f'<a href="{u("/safety")}">Safety</a>')
    theme_opts = "".join(
        f'<option value="{h(k)}">{h(lab)}</option>' for k, lab in config.SITE_THEMES.items()
    )
    if user:
        nav.append(f'<span class="who">{h(user.get("email"))}</span>')
        nav.append(f'<a href="{u("/editor")}">My resume</a>')
        nav.append(f'<a href="{u("/account")}">Account</a>')
        nav.append(f'<a href="{u("/logout")}">Sign out</a>')
    elif admin:
        nav.append(f'<span class="who">{h(admin.get("email"))}</span>')
        nav.append(f'<a href="{u("/admin")}">Dashboard</a>')
        nav.append(f'<a href="{u("/admin/logout")}">Sign out</a>')
    else:
        nav.append(f'<a href="{u("/login")}">Sign in</a>')
    return f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{h(full)}</title>
<meta name="description" content="{h(desc)}">
<meta name="theme-color" content="#161410">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="canonical" href="{h(canonical)}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,650&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/resume.css">
<script>try {{ var t = localStorage.getItem('wig_site_theme'); if (t) document.documentElement.setAttribute('data-site', t); }} catch (e) {{}}</script>
{extra_head}
</head>
<body class="{h(body)}">
<a class="skip" href="#main">Skip to content</a>
<p class="py-banner">Python edition · <a href="/">PHP site</a></p>
<header class="site-header">
<a class="wordmark" href="{u("/")}">{h(config.SITE_NAME)}</a>
<nav>
{"".join(nav)}
<label class="sr-only" for="site-theme">Site theme</label>
<select id="site-theme" class="site-theme" aria-label="Site theme">{theme_opts}</select>
</nav>
</header>
"""


def footer(scripts: Optional[list[str]] = None, flash: Optional[dict] = None) -> str:
    extra = ""
    if flash:
        extra = f'<div class="flash flash-{h(flash.get("type"))}" role="status">{h(flash.get("message"))}</div>'
    news = f' · <a href="{u("/news")}">News</a>' if news_on() else ""
    vids = f' · <a href="{u("/videos")}">Videos</a>' if videos_on() else ""
    js = "".join(f'<script src="{h(s)}"></script>\n' for s in (scripts or []))
    return extra + f"""
<footer class="site-footer">
<p>{h(config.SITE_NAME)} · Python edition at {h(config.PREFIX)}</p>
<p class="fine"><a href="{u("/about")}">About</a>{news}{vids} · <a href="{u("/tools")}">Tools</a> · <a href="{u("/safety")}">Safety</a> · <a href="/">PHP</a></p>
</footer>
<script src="/assets/js/app.js"></script>
{js}
<style>
.py-banner {{ width:min(1120px,calc(100% - 32px)); margin:8px auto 0; font-size:.78rem; letter-spacing:.12em; text-transform:uppercase; color:var(--gold); }}
.py-banner a {{ color:inherit; }}
.hp, .hp label, .hp input {{
  position:absolute !important; left:-10000px !important; top:auto !important;
  width:1px !important; height:1px !important; min-width:0 !important; max-width:1px !important;
  overflow:hidden !important; clip:rect(0,0,0,0) !important; clip-path:inset(50%) !important;
  opacity:0 !important; pointer-events:none !important; flex:none !important;
  margin:0 !important; padding:0 !important; border:0 !important;
}}
</style>
</body></html>
"""


def csrf_field(token: str) -> str:
    return f'<input type="hidden" name="csrf" value="{h(token)}">'


def hp_field() -> str:
    return (
        '<div class="hp" aria-hidden="true">'
        '<label for="website_hp">Company</label>'
        '<input id="website_hp" type="text" name="website_hp" value="" tabindex="-1" autocomplete="off">'
        "</div>"
    )


def parse_resume(text: str) -> dict:
    lines = text.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    i = 0
    n = len(lines)
    while i < n and not lines[i].strip():
        i += 1
    name = lines[i].strip() if i < n else ""
    i += 1
    headline = ""
    contacts: list[str] = []
    while i < n and lines[i].strip() and not _is_header(lines[i]):
        line = lines[i].strip()
        if not headline and "@" not in line and "http" not in line.lower():
            headline = line
        else:
            contacts.extend([p.strip() for p in line.replace("|", "·").split("·") if p.strip()])
        i += 1
    sections = []
    current: Optional[dict] = None
    while i < n:
        raw = lines[i]
        trim = raw.strip()
        i += 1
        if _is_header(trim):
            if current:
                sections.append(current)
            current = {"title": trim.strip("# ").strip(), "blocks": []}
            continue
        if current is None:
            current = {"title": "Profile", "blocks": []}
        if trim == "":
            current["blocks"].append({"type": "spacer"})
            continue
        if trim[:1] in "-*•":
            item = trim.lstrip("-*• ").strip()
            if current["blocks"] and current["blocks"][-1]["type"] == "list":
                current["blocks"][-1]["items"].append(item)
            else:
                current["blocks"].append({"type": "list", "items": [item]})
            continue
        if "|" in trim and len(trim) < 180:
            current["blocks"].append({"type": "meta", "parts": [p.strip() for p in trim.split("|")]})
            continue
        current["blocks"].append({"type": "p", "text": trim})
    if current:
        sections.append(current)
    return {"name": name, "headline": headline, "contacts": contacts, "sections": sections}


def _is_header(line: str) -> bool:
    t = line.strip()
    if not t or len(t) > 48:
        return False
    letters = [c for c in t if c.isalpha()]
    if len(letters) < 3:
        return False
    return t == t.upper() or t.startswith("#")


def render_resume(doc: dict, theme: str = "classic") -> str:
    parts = [f'<article class="resume theme-{h(theme)}">']
    parts.append(f'<header><h1>{h(doc.get("name") or "Resume")}</h1>')
    if doc.get("headline"):
        parts.append(f'<p class="headline">{h(doc["headline"])}</p>')
    if doc.get("contacts"):
        parts.append("<p>" + " · ".join(h(c) for c in doc["contacts"]) + "</p>")
    parts.append("</header>")
    for sec in doc.get("sections") or []:
        parts.append(f'<section><h2>{h(sec.get("title"))}</h2>')
        for b in sec.get("blocks") or []:
            if b["type"] == "p":
                parts.append(f'<p>{h(b["text"])}</p>')
            elif b["type"] == "meta":
                parts.append("<p>" + " · ".join(h(p) for p in b.get("parts") or []) + "</p>")
            elif b["type"] == "list":
                parts.append("<ul>" + "".join(f'<li>{h(it)}</li>' for it in b.get("items") or []) + "</ul>")
        parts.append("</section>")
    parts.append("</article>")
    return "".join(parts)


def sample_resume() -> str:
    return """Alex Rivera
Product engineer
alex@example.com | example.com

SUMMARY
Builds quiet, reliable software for people who have better things to do.

EXPERIENCE
Northwind | Staff engineer | 2021–present
- Shipped the public resume URL scheme used here.
- Cut incident noise by writing down the boring path.

SKILLS
PHP, Python, SQLite, plain HTML
"""
