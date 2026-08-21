"""Load site config. Secrets come from ../config.local.php when present."""

from __future__ import annotations

import os
import re
from pathlib import Path

PREFIX = "/pythonversion"
SITE_NAME = "Work is God"
SITE_URL = "https://workisgod.com"
MAIL_FROM = "noreply@workisgod.com"
MAIL_FROM_NAME = "Work is God"
SMTP_HOST = "mail.workisgod.com"
SMTP_PORT = 465
SMTP_SECURE = "ssl"
SMTP_USER = "noreply@workisgod.com"
SMTP_PASS = ""
OTP_TTL = 600
SESSION_DAYS = 30
RESUME_MAX = 50000
SLUG_MIN = 3
SLUG_MAX = 40

HERE = Path(__file__).resolve().parent
APP_ROOT = HERE.parent
PHP_ROOT = APP_ROOT.parent
DATA_DIR = PHP_ROOT / "data"
DB_PATH = DATA_DIR / "app.sqlite"


def _parse_php_defines(text: str) -> dict[str, str]:
    out: dict[str, str] = {}
    for m in re.finditer(
        r"define\(\s*'([A-Z0-9_]+)'\s*,\s*'((?:\\'|[^'])*)'\s*\)",
        text,
    ):
        out[m.group(1)] = m.group(2).replace("\\'", "'")
    return out


def load() -> None:
    global SITE_URL, MAIL_FROM, SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS
    local = PHP_ROOT / "config.local.php"
    if local.is_file():
        defs = _parse_php_defines(local.read_text(encoding="utf-8", errors="replace"))
        SMTP_PASS = defs.get("SMTP_PASS", SMTP_PASS)
        MAIL_FROM = defs.get("MAIL_FROM", MAIL_FROM)
        SMTP_HOST = defs.get("SMTP_HOST", SMTP_HOST)
        SMTP_USER = defs.get("SMTP_USER", SMTP_USER)
        if defs.get("SMTP_PORT"):
            try:
                SMTP_PORT = int(defs["SMTP_PORT"])
            except ValueError:
                pass
        SITE_URL = defs.get("SITE_URL", SITE_URL)
    env_pass = os.environ.get("SMTP_PASS")
    if env_pass:
        SMTP_PASS = env_pass


load()

SITE_THEMES = {
    "atelier": "Atelier · millennial",
    "acid": "Acid · Gen Z",
    "brutal": "Brutal · Gen Z",
    "midnight": "Midnight · millennial",
    "peach": "Peach · millennial",
    "vapor": "Vapor · Gen Z",
}

RESUME_THEMES = {
    "classic": "Classic · navy",
    "modern": "Modern · teal",
    "minimal": "Minimal · ink",
    "acid": "Acid · Gen Z",
    "brutal": "Brutal · Gen Z",
    "midnight": "Midnight · millennial",
    "peach": "Peach · millennial",
    "vapor": "Vapor · Gen Z",
}

TOOLS = [
    {"id": "json", "name": "JSON", "cli": "jq", "blurb": "Format, validate, and minify JSON."},
    {"id": "base64", "name": "Base64", "cli": "base64", "blurb": "Encode or decode Base64, including URL-safe."},
    {"id": "url", "name": "URL encode", "cli": "printf %s | jq -sRr @uri", "blurb": "Percent-encode and decode URLs and form values."},
    {"id": "hash", "name": "Hash / HMAC", "cli": "shasum / openssl dgst", "blurb": "SHA-1, SHA-256, SHA-512, MD5, and HMAC."},
    {"id": "uuid", "name": "UUID", "cli": "uuidgen", "blurb": "Mint UUID v4 identifiers, one or a handful."},
    {"id": "time", "name": "Timestamp", "cli": "date +%s", "blurb": "Unix seconds, milliseconds, ISO-8601, and relative time."},
    {"id": "jwt", "name": "JWT decode", "cli": "jq after cut -d. -f2", "blurb": "Inspect header and payload. Signature is not verified."},
    {"id": "regex", "name": "Regex", "cli": "grep -E / perl -pe", "blurb": "Test a pattern, list groups, and run a replace."},
    {"id": "diff", "name": "Diff", "cli": "diff -u", "blurb": "Line-level unified diff of two texts."},
    {"id": "query", "name": "URL / query", "cli": "python -m urllib.parse", "blurb": "Split a URL or query string into parts and params."},
    {"id": "cron", "name": "Cron", "cli": "crontab", "blurb": "Read a 5-field expression and the next fire times."},
    {"id": "http", "name": "HTTP codes", "cli": "man http", "blurb": "Look up status codes used in APIs and curl output."},
    {"id": "cidr", "name": "CIDR", "cli": "ipcalc / sipcalc", "blurb": "Network, mask, hosts, and wildcard for IPv4 prefixes."},
    {"id": "password", "name": "Secrets", "cli": "openssl rand -base64", "blurb": "Generate passwords and random tokens locally."},
    {"id": "html", "name": "HTML entities", "cli": "recode / python html", "blurb": "Escape and unescape HTML, XML, and numeric entities."},
    {"id": "case", "name": "Case / slug", "cli": "tr / sed", "blurb": "Upper, lower, title, snake, kebab, camel, and URL slug."},
    {"id": "hex", "name": "Hex", "cli": "xxd / hexdump", "blurb": "UTF-8 text to hex and back, with a byte dump."},
    {"id": "bases", "name": "Number bases", "cli": "printf / bc", "blurb": "Convert among binary, octal, decimal, and hex."},
    {"id": "pem", "name": "PEM decoder", "cli": "openssl x509 -text", "blurb": "Read certificates and keys: type, fingerprint, subject."},
    {"id": "curl", "name": "HTTP / curl", "cli": "curl -i", "blurb": "Build a curl command and send it from this browser."},
]
