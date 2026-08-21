from __future__ import annotations

import hashlib
import hmac
import sqlite3
import time
from datetime import datetime, timezone
from typing import Any, Optional

from . import config


def iso_now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def connect() -> sqlite3.Connection:
    config.DATA_DIR.mkdir(parents=True, exist_ok=True)
    con = sqlite3.connect(str(config.DB_PATH), timeout=10)
    con.row_factory = sqlite3.Row
    con.execute("PRAGMA foreign_keys = ON")
    return con


def setting(key: str, default: str = "") -> str:
    con = connect()
    try:
        row = con.execute("SELECT value FROM settings WHERE key = ?", (key,)).fetchone()
        return str(row["value"]) if row else default
    except sqlite3.Error:
        return default
    finally:
        con.close()


def setting_set(key: str, value: str) -> None:
    con = connect()
    try:
        con.execute("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)", (key, value))
        con.commit()
    finally:
        con.close()


def news_on() -> bool:
    return setting("news_enabled", "1") == "1"


def videos_on() -> bool:
    return setting("videos_enabled", "1") == "1"


def logins_on() -> bool:
    return setting("user_logins_enabled", "1") == "1"


def signup_mode() -> str:
    mode = setting("signup_mode", "")
    if mode in ("open", "approval", "closed"):
        return mode
    return "open" if setting("signups_open", "1") == "1" else "closed"


def hash_secret(value: str) -> str:
    salt = hashlib.sha256(os_urandom(16)).hexdigest()[:32]
    digest = hashlib.pbkdf2_hmac("sha256", value.encode(), salt.encode(), 120000).hex()
    return f"pbkdf2${salt}${digest}"


def os_urandom(n: int) -> bytes:
    import os

    return os.urandom(n)


def verify_secret(value: str, stored: str) -> bool:
    stored = stored or ""
    if stored.startswith("pbkdf2$"):
        try:
            _, salt, digest = stored.split("$", 2)
        except ValueError:
            return False
        check = hashlib.pbkdf2_hmac("sha256", value.encode(), salt.encode(), 120000).hex()
        return hmac.compare_digest(check, digest)
    if stored.startswith("$2"):
        try:
            import bcrypt

            blob = stored.replace("$2y$", "$2b$").encode()
            return bcrypt.checkpw(value.encode(), blob)
        except Exception:
            return False
    return False


def rate_limited(key: str, max_n: int, window: int) -> bool:
    now = int(time.time())
    con = connect()
    try:
        con.execute("DELETE FROM rate_limits WHERE window_start < ?", (now - 86400,))
        row = con.execute("SELECT count, window_start FROM rate_limits WHERE key = ?", (key,)).fetchone()
        if not row or (now - int(row["window_start"])) >= window:
            con.execute(
                "INSERT OR REPLACE INTO rate_limits (key, count, window_start) VALUES (?, 1, ?)",
                (key, now),
            )
            con.commit()
            return False
        count = int(row["count"])
        if count >= max_n:
            return True
        con.execute("UPDATE rate_limits SET count = count + 1 WHERE key = ?", (key,))
        con.commit()
        return False
    finally:
        con.close()


def user_by_id(uid: int) -> Optional[dict[str, Any]]:
    con = connect()
    try:
        row = con.execute(
            "SELECT id, email, created_at, last_login_at, status FROM users WHERE id = ?",
            (uid,),
        ).fetchone()
        return dict(row) if row else None
    finally:
        con.close()


def admin_by_id(aid: int) -> Optional[dict[str, Any]]:
    con = connect()
    try:
        row = con.execute(
            "SELECT id, email, created_at, last_login_at FROM admins WHERE id = ?",
            (aid,),
        ).fetchone()
        return dict(row) if row else None
    finally:
        con.close()
