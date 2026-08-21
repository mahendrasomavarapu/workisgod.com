# Work is God — Python edition

Hosted at **https://workisgod.com/pythonversion**

Stdlib-only WSGI app (no Flask required). Shares the PHP site’s SQLite (`data/app.sqlite`), CSS, and JS.

## On Namecheap (PHP launcher + CGI)

Files live in `public_html/pythonversion/`. Visit `/pythonversion/`.

`index.php` starts CloudLinux Python 3.12 (`/opt/alt/python312/bin/python3`) because the system `python3` is 3.6. `app.cgi` is executable (`chmod 755`) if CGI is used directly.

## On Namecheap (Setup Python App)

1. cPanel → **Setup Python App**
2. Application URL: `pythonversion`
3. Application startup file: `passenger_wsgi.py`
4. Restart the app

`passenger_wsgi.py` exposes `application`.

## What is ported

- Home, About, Safety, OTP login, editor, public resumes
- Tools, news, videos (same `/assets` JS)
- Admin login, dashboard, users, settings (shared settings table)

AI improve / URL capture stay on the PHP editor for now.
