# Cloudflare + captcha for Work is God

Two layers:

1. **Cloudflare proxy** in front of Namecheap (DDoS, bot filter, TLS)
2. **Turnstile captcha** on sign-in, admin login, and delete-account/resume

You need a free Cloudflare account: https://dash.cloudflare.com

## A. Put the site behind Cloudflare

The hosting IP stays at Namecheap (`198.54.116.4`). Cloudflare only becomes DNS + proxy.

1. Add `workisgod.com` in Cloudflare.
2. Copy the two Cloudflare nameservers it shows (for example `ada.ns.cloudflare.com` and `bob.ns.cloudflare.com`).
3. In Namecheap: **Domain List → Manage → Nameservers → Custom DNS**. Replace `dns1.namecheaphosting.com` / `dns2.namecheaphosting.com` with the Cloudflare pair.
4. In Cloudflare DNS, keep at least:

   | Type | Name | Content | Proxy |
   | --- | --- | --- | --- |
   | A | `@` | `198.54.116.4` | Proxied (orange cloud) |
   | A | `www` | `198.54.116.4` | Proxied |
   | MX | `@` | `mx1-hosting.jellyfish.systems` (priority 5) | DNS only (grey) |
   | MX | `@` | `mx2-hosting.jellyfish.systems` (priority 10) | DNS only |
   | MX | `@` | `mx3-hosting.jellyfish.systems` (priority 20) | DNS only |
   | TXT | `@` | `v=spf1 include:spf.web-hosting.com ~all` | DNS only |

   **Do not orange-cloud the MX records.** OTP email will break if mail is proxied.

5. SSL/TLS mode: **Full (strict)** because Namecheap already has HTTPS.
6. SSL/TLS → Edge Certificates: **Always Use HTTPS**.
7. Security → **Bot Fight Mode** on (free).
8. Security → Settings: challenge or block countries only if you want.

Wait for DNS (often minutes, up to 48 hours). Admin dashboard shows “Cloudflare proxy: yes” when `CF-Ray` is present.

## B. Turnstile captcha

1. Cloudflare dashboard → **Turnstile** → Add widget.
2. Hostnames: `workisgod.com` and `www.workisgod.com`.
3. Widget mode: **Managed**.
4. Copy site key and secret.
5. In cPanel File Manager, edit `public_html/config.local.php`:

```php
define('TURNSTILE_SITE_KEY', '0x4AAAAAAA...');
define('TURNSTILE_SECRET', '0x4AAAAAAA...');
```

6. Save. Captcha appears on:

   - Home / login “Send login code”
   - Admin sign-in
   - Delete resume / delete account (before the OTP is sent)

Until those two defines are set, captcha stays off so you are not locked out.

## C. If something breaks

- Site timeout: set SSL to Full (strict), not Flexible.
- OTP mail missing: MX must be DNS-only to Namecheap jellyfish hosts.
- Captcha loop: confirm site key matches this domain; allow `challenges.cloudflare.com` (already in CSP).
