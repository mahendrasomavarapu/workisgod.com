from __future__ import annotations

import json
import os
import random
import smtplib
import ssl
import time
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from typing import Any, Optional
from urllib.parse import parse_qs

from . import config
from .db import (
    admin_by_id,
    connect,
    hash_secret,
    iso_now,
    logins_on,
    rate_limited,
    signup_mode,
    user_by_id,
    verify_secret,
)


def parse_form(environ: dict) -> dict[str, str]:
    try:
        n = int(environ.get("CONTENT_LENGTH") or 0)
    except ValueError:
        n = 0
    raw = environ["wsgi.input"].read(n) if n > 0 else b""
    qs = parse_qs(raw.decode("utf-8", "replace"), keep_blank_values=True)
    return {k: (v[-1] if v else "") for k, v in qs.items()}


def parse_query(environ: dict) -> dict[str, str]:
    qs = parse_qs(environ.get("QUERY_STRING") or "", keep_blank_values=True)
    return {k: (v[-1] if v else "") for k, v in qs.items()}


def client_ip(environ: dict) -> str:
    return (environ.get("REMOTE_ADDR") or "0.0.0.0")[:45]


def send_otp(email: str, ip: str) -> str:
    email = email.strip().lower()
    if "@" not in email or "." not in email.split("@")[-1]:
        return "Enter a valid email address."
    if not logins_on():
        return "Guest doors are closed for now."
    con = connect()
    try:
        row = con.execute("SELECT id, status FROM users WHERE email = ?", (email,)).fetchone()
        if row and (row["status"] or "active") == "disabled":
            return "This seat has been withdrawn."
        if not row and signup_mode() == "closed":
            return "New guests are not being received."
    finally:
        con.close()
    if rate_limited("py-otp-ip:" + ip, 10, 3600) or rate_limited("py-otp-email:" + email, 8, 3600):
        return "Too many codes sent. Try again later."
    code = f"{random.randint(100000, 999999):06d}"
    now = int(time.time())
    con = connect()
    try:
        con.execute("DELETE FROM otps WHERE email = ? AND purpose = ?", (email, "pylogin"))
        con.execute(
            "INSERT INTO otps (email, code_hash, ip, expires_at, attempts, created_at, purpose) VALUES (?,?,?,?,0,?,?)",
            (email, hash_secret(code), ip, now + config.OTP_TTL, now, "pylogin"),
        )
        con.commit()
    finally:
        con.close()
    if not send_mail(email, code):
        return "Could not send email. Check SMTP settings."
    return ""


def verify_otp(email: str, code: str, ip: str) -> tuple[str, Optional[dict[str, Any]]]:
    email = email.strip().lower()
    code = code.strip()
    if not code.isdigit() or len(code) != 6:
        return "Enter the 6-digit code from your email.", None
    if rate_limited("py-otp-try:" + ip, 20, 3600):
        return "Too many attempts. Try again later.", None
    con = connect()
    try:
        row = con.execute(
            "SELECT * FROM otps WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1",
            (email, "pylogin"),
        ).fetchone()
        if not row:
            return "No login code found. Request a new one.", None
        if int(row["expires_at"]) < int(time.time()):
            return "That code has expired. Request a new one.", None
        if int(row["attempts"]) >= 5:
            return "Too many incorrect tries. Request a new code.", None
        con.execute("UPDATE otps SET attempts = attempts + 1 WHERE id = ?", (row["id"],))
        con.commit()
        if not verify_secret(code, row["code_hash"]):
            return "That code is incorrect.", None
        con.execute("DELETE FROM otps WHERE email = ? AND purpose = ?", (email, "pylogin"))
        user = con.execute("SELECT * FROM users WHERE email = ?", (email,)).fetchone()
        now = iso_now()
        if user:
            con.execute("UPDATE users SET last_login_at = ? WHERE id = ?", (now, user["id"]))
            uid = int(user["id"])
            status = user["status"] or "active"
        else:
            status = "pending" if signup_mode() == "approval" else "active"
            con.execute(
                "INSERT INTO users (email, created_at, last_login_at, status) VALUES (?,?,?,?)",
                (email, now, now, status),
            )
            uid = int(con.execute("SELECT last_insert_rowid()").fetchone()[0])
        con.commit()
        return "", {"id": uid, "email": email, "status": status}
    finally:
        con.close()


def verify_admin(email: str, password: str, ip: str) -> tuple[str, Optional[dict[str, Any]]]:
    email = email.strip().lower()
    if rate_limited("py-admin-ip:" + ip, 12, 3600):
        return "Too many admin attempts. Try again later.", None
    con = connect()
    try:
        row = con.execute("SELECT * FROM admins WHERE email = ?", (email,)).fetchone()
        if not row or not verify_secret(password, row["password_hash"]):
            return "Those admin credentials are not correct.", None
        con.execute("UPDATE admins SET last_login_at = ? WHERE id = ?", (iso_now(), row["id"]))
        con.commit()
        return "", dict(row)
    finally:
        con.close()


def send_mail(to: str, code: str) -> bool:
    subject = f"Your {config.SITE_NAME} login code"
    text = f"Your {config.SITE_NAME} login code is {code}. It expires in {config.OTP_TTL // 60} minutes."
    html = (
        "<p>Your private login code</p>"
        f'<p style="font-size:32px;letter-spacing:.2em"><b>{code}</b></p>'
        f"<p>It expires in {config.OTP_TTL // 60} minutes.</p>"
    )
    msg = MIMEMultipart("alternative")
    msg["Subject"] = subject
    msg["From"] = f"{config.MAIL_FROM_NAME} <{config.MAIL_FROM}>"
    msg["To"] = to
    msg.attach(MIMEText(text, "plain", "utf-8"))
    msg.attach(MIMEText(html, "html", "utf-8"))
    raw = msg.as_string()
    errors = []
    if config.SMTP_HOST and config.SMTP_PASS:
        if _smtp_send(raw, to, errors):
            return True
    if _sendmail(raw, errors):
        return True
    import sys

    sys.stderr.write("[pythonversion] mail failed: " + " | ".join(errors) + "\n")
    return False


def _ssl_ctx(check_hostname: bool):
    ctx = ssl.create_default_context()
    if not check_hostname:
        # Namecheap presents the server hostname cert, not mail.domain.com.
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
    return ctx


def _smtp_send(raw: str, to: str, errors) -> bool:
    host = config.SMTP_HOST
    port = int(config.SMTP_PORT or 465)
    secure = (config.SMTP_SECURE or "ssl").lower()
    attempts = [
        (host, port, secure, True),
        (host, port, secure, False),
        ("localhost", port, secure, False),
        ("127.0.0.1", 25, "plain", False),
    ]
    if secure == "ssl" and port == 465:
        attempts.append((host, 587, "tls", False))
        attempts.append(("localhost", 587, "tls", False))
    seen = set()
    for h, p, mode, verify in attempts:
        key = (h, p, mode, verify)
        if key in seen:
            continue
        seen.add(key)
        try:
            ctx = _ssl_ctx(verify)
            if mode == "ssl":
                with smtplib.SMTP_SSL(h, p, timeout=20, context=ctx) as smtp:
                    smtp.login(config.SMTP_USER, config.SMTP_PASS)
                    smtp.sendmail(config.MAIL_FROM, [to], raw)
            elif mode == "tls":
                with smtplib.SMTP(h, p, timeout=20) as smtp:
                    smtp.ehlo()
                    smtp.starttls(context=ctx)
                    smtp.ehlo()
                    smtp.login(config.SMTP_USER, config.SMTP_PASS)
                    smtp.sendmail(config.MAIL_FROM, [to], raw)
            else:
                with smtplib.SMTP(h, p, timeout=15) as smtp:
                    smtp.ehlo()
                    smtp.sendmail(config.MAIL_FROM, [to], raw)
            return True
        except Exception as e:
            errors.append(f"{mode} {h}:{p} verify={verify} {type(e).__name__}: {e}")
    return False


def _sendmail(raw: str, errors) -> bool:
    import subprocess

    sendmail = "/usr/sbin/sendmail"
    if not os.path.exists(sendmail):
        errors.append("sendmail missing")
        return False
    try:
        proc = subprocess.run(
            [sendmail, "-t", "-oi", "-f", config.MAIL_FROM],
            input=raw.encode("utf-8"),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            timeout=20,
            check=False,
        )
        if proc.returncode == 0:
            return True
        err = (proc.stderr or b"").decode("utf-8", "replace").strip()
        errors.append(f"sendmail exit {proc.returncode} {err}")
    except Exception as e:
        errors.append(f"sendmail {type(e).__name__}: {e}")
    return False


def load_json(path, default):
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return default
