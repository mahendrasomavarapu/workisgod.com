<?php
/**
 * Site settings. Secrets go in config.local.php on the server.
 */

declare(strict_types=1);

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

if (!defined('SITE_NAME')) define('SITE_NAME', 'Work is God');
if (!defined('SITE_URL')) define('SITE_URL', 'https://workisgod.com');

if (!defined('MAIL_FROM')) define('MAIL_FROM', 'noreply@workisgod.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Work is God');

if (!defined('SMTP_HOST')) define('SMTP_HOST', 'mail.workisgod.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 465);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'ssl');
if (!defined('SMTP_USER')) define('SMTP_USER', 'noreply@workisgod.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');

// Optional free Groq key (console.groq.com, no credit card). Leave empty to use the built-in free agent.
if (!defined('GROQ_API_KEY')) define('GROQ_API_KEY', (string) (getenv('GROQ_API_KEY') ?: ''));
if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', (string) (getenv('GEMINI_API_KEY') ?: ''));

if (!defined('OTP_TTL_SECONDS')) define('OTP_TTL_SECONDS', 600);
if (!defined('OTP_RESEND_SECONDS')) define('OTP_RESEND_SECONDS', 60);
if (!defined('SESSION_DAYS')) define('SESSION_DAYS', 30);
if (!defined('RESUME_MAX_CHARS')) define('RESUME_MAX_CHARS', 50000);
if (!defined('SLUG_MIN')) define('SLUG_MIN', 3);
if (!defined('SLUG_MAX')) define('SLUG_MAX', 40);

if (!defined('TURNSTILE_SITE_KEY')) define('TURNSTILE_SITE_KEY', '');
if (!defined('TURNSTILE_SECRET')) define('TURNSTILE_SECRET', '');

if (!defined('ADMIN_BOOTSTRAP_EMAIL')) define('ADMIN_BOOTSTRAP_EMAIL', 'admin@workisgod.com');
if (!defined('ADMIN_BOOTSTRAP_PASSWORD')) define('ADMIN_BOOTSTRAP_PASSWORD', '');

if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__ . '/data');
if (!defined('DB_PATH')) define('DB_PATH', DATA_DIR . '/app.sqlite');
