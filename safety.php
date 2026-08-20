<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

render_header('Safety', [
    'body' => 'page-safety',
    'description' => 'How to use Work is God safely, what is public, and how the site is protected against common attacks.',
    'path' => '/safety.php',
    'type' => 'WebPage',
]);
?>
<main id="main" class="wrap prose">
    <p class="eyebrow">Safety</p>
    <h1>Use the site safely.</h1>
    <p class="lede">This page is for people publishing a resume and for operators who want to know how the site is defended. Public resumes can be quoted. Secrets never should.</p>

    <h2>Instructions for you</h2>
    <ul>
        <li>Treat the 6-digit email code like a key. Do not forward it. We will never ask for it in chat, SMS, or a second website.</li>
        <li>Your public URL <code>/r/your-slug</code> is visible to anyone with the link. Do not put national IDs, bank details, home addresses, or unpublished phone numbers in the resume text.</li>
        <li>LinkedIn often blocks automated reads. Prefer “Save as PDF” from LinkedIn and upload that file. Do not paste your LinkedIn password anywhere on this site.</li>
        <li>Review AI output before you save. The agent must not invent jobs, dates, or metrics. If it does, delete those lines.</li>
        <li>Change the admin password after first login. Do not reuse that password on other sites.</li>
        <li>Sign out on shared computers.</li>
        <li>Deleting a resume or the whole account requires a fresh OTP sent to your email. Use Account in the header.</li>
    </ul>

    <h2>What is stored</h2>
    <p>We store your email, the resume text you save, theme, and slug. Regular users have no password. One-time codes expire and are stored only as hashes. Admin passwords are hashed. Mailbox and API secrets live in server-only <code>config.local.php</code>, not in the public repository.</p>

    <h2>How the site is protected</h2>
    <ul>
        <li>HTTPS is required. HTTP is redirected.</li>
        <li>Sessions use HTTP-only cookies, Secure when HTTPS is on, and SameSite=Lax.</li>
        <li>Forms use CSRF tokens. JSON endpoints require the same token.</li>
        <li>Login, admin login, AI, and profile-import are rate limited by IP and account.</li>
        <li>Cloudflare Turnstile captcha protects sign-in and delete actions when keys are configured. The site can also sit behind the Cloudflare proxy (see <a href="/CLOUDFLARE.md">CLOUDFLARE.md</a>).</li>
        <li>URL import only allows public http(s) hosts (no localhost or private networks).</li>
        <li>Config files, <code>data/</code>, and <code>includes/</code> are not web-readable. Directory listing is off.</li>
        <li>Security headers: Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS.</li>
        <li>PHP errors are logged, not printed. The admin area is disallowed in robots.txt.</li>
    </ul>

    <h2>Report a problem</h2>
    <p>If you find a security issue, email <a href="mailto:<?= h(MAIL_FROM) ?>"><?= h(MAIL_FROM) ?></a>. Do not post exploits on a public resume. Details also live at <a href="/.well-known/security.txt">/.well-known/security.txt</a>.</p>
</main>
<?php
render_footer();
