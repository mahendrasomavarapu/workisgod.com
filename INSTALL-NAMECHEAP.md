# Install Work is God on Namecheap cPanel

This app is plain PHP + SQLite. No Node, Composer, or extra services.

You need:

- A Namecheap hosting package (Stellar is enough)
- Domain pointed at the hosting (nameservers `dns1.namecheaphosting.com` and `dns2.namecheaphosting.com`)
- PHP 8.1 or newer with `pdo_sqlite` enabled

## 1. Upload the files

1. Zip the project folder on your computer, **or** keep the files unzipped.
2. Log in to cPanel. From Namecheap: **Account → Hosting List → Go to cPanel**.
3. Open **File Manager**.
4. Go to `public_html`.
5. Optional: rename the current `index.htm` parking page to `index.htm.bak`.
6. Upload all project files into `public_html` so you have:

```
public_html/
  index.php
  login.php
  editor.php
  public.php
  preview.php
  logout.php
  config.php
  .htaccess
  assets/
  includes/
  data/
  INSTALL-NAMECHEAP.md
```

If you uploaded a zip, right-click it → **Extract**.

The site must live in `public_html` (the web root), not a subfolder, because CSS/JS paths start with `/assets/`.

A Python edition of the same app lives in `public_html/pythonversion/` and is served at `https://workisgod.com/pythonversion/`. It shares `data/app.sqlite` and `/assets`. If that URL 500s, in cPanel open **Setup Python App**, set Application URL to `pythonversion` and startup file to `passenger_wsgi.py`.

## 2. Set PHP 8 and SQLite

1. In cPanel open **Select PHP Version** (sometimes called **MultiPHP Manager** + **Select PHP Version**).
2. Choose **PHP 8.1**, **8.2**, or **8.3** for `workisgod.com`.
3. Open **Extensions** and enable:

   - `pdo`
   - `pdo_sqlite`
   - `sqlite3`
   - `openssl` (needed for SMTP and AI)
   - `curl` (needed for the AI agent)

4. Save.

## 3. Make the data folder writable

1. In File Manager, select the `data` folder.
2. **Permissions** → set to `755`.
3. If saving a resume later fails, set `data` to `775`.

SQLite will create `data/app.sqlite` on the first visit. Do not delete `data/.htaccess`.

## 4. Create the mailbox used to send OTP codes

OTP login emails must come from your domain or they often land in spam.

1. cPanel → **Email Accounts** → **Create**.
2. Address: `noreply@workisgod.com` (or another address on this domain).
3. Set a strong password and save it.

## 5. Edit `config.php`

In File Manager, right-click `config.php` → **Edit**.

Set:

```php
define('SITE_URL', 'https://workisgod.com');
define('MAIL_FROM', 'noreply@workisgod.com');
define('SMTP_HOST', 'mail.workisgod.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USER', 'noreply@workisgod.com');
define('SMTP_PASS', 'paste-the-mailbox-password-here');
```

Save.

If SMTP is empty (`SMTP_PASS` left blank), the app falls back to PHP `mail()`. SMTP is the reliable option on Namecheap.

The **Use AI agent to improve content** checkbox is free. It first tries a public text model, then a built-in rewriter if that model is busy. No paid API is required.

Optional: for a stronger rewrite, create a free Groq key (no credit card) at https://console.groq.com and add it to `config.local.php`:

```php
define('GROQ_API_KEY', 'gsk_...');
```

## 6. Check the site

Visit:

- `https://workisgod.com/` — home / sign-in
- `https://workisgod.com/resumes/demo` — sample themed resume

Then:

1. Enter your Gmail (or any email).
2. Open the 6-digit code.
3. Paste or upload a `.txt` resume.
4. Optionally check **Use AI agent to improve content**.
5. Pick a theme and a URL slug.
6. Save. Share `https://workisgod.com/resumes/your-slug`.

## 7. If OTP email does not arrive

- Confirm `noreply@workisgod.com` exists.
- Confirm `SMTP_PASS` is the mailbox password.
- Check **Spam**.
- In cPanel → **Track Delivery** (if present) to see whether the message left the server.
- Try port `587` with `SMTP_SECURE` set to `tls` instead of `465` / `ssl`.
- Add an SPF record in **Zone Editor**:

```
v=spf1 include:spf.web-hosting.com ~all
```

Namecheap often adds this when email is hosted there. Don’t duplicate conflicting SPF records.

## 8. Cloudflare and captcha

Follow [CLOUDFLARE.md](CLOUDFLARE.md):

1. Point the domain nameservers to Cloudflare and orange-cloud the A records.
2. Keep MX records DNS-only so OTP email still hits Namecheap.
3. Create a Turnstile widget and put `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET` in `config.local.php`.

Admin → Dashboard shows whether the proxy and captcha are detected.

## 9. Common problems

| Problem | Fix |
| --- | --- |
| Blank page | Select PHP 8.x. Enable `pdo_sqlite`. |
| `Cannot create data/` | Permissions on `public_html/data` = 755 or 775. |
| `/resumes/name` 404 | `.htaccess` is in `public_html`. In **Zone Editor** / hosting, AllowOverride is already on for Namecheap. |
| CSS missing | Files must be in `public_html`, not `public_html/workisgod`. |
| 403 on the site | Restore `.htaccess` from the project. Don’t deny `public_html` itself. |
| Want HTTPS | Namecheap AutoSSL is usually already on. The app’s `.htaccess` redirects HTTP → HTTPS. |

## Resume text format

Plain text is enough. This shape looks best:

```
Your Name
Your title
you@gmail.com | city | website

SUMMARY
Two or three sentences.

EXPERIENCE
Company | Role | Dates
- Achievement
- Achievement

EDUCATION
School | Degree | Year

SKILLS
PHP, writing, systems
```

`## Heading` also works. Change the theme anytime; the public URL stays the same unless you change the slug.
