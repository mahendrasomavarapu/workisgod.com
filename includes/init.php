<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/cloudflare.php';
require __DIR__ . '/db.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/parser.php';
require __DIR__ . '/resume.php';
require __DIR__ . '/ai.php';
require __DIR__ . '/moderate.php';
require __DIR__ . '/capture.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/toolset.php';
require __DIR__ . '/news.php';
require __DIR__ . '/videos.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(DATA_DIR)) {
    ini_set('error_log', DATA_DIR . '/php-error.log');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => SESSION_DAYS * 86400,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('wig_session');
    session_start();
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, POST, HEAD');
    exit('Method not allowed');
}

if (is_private_page()) {
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
} else {
    header('Cache-Control: public, max-age=120');
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
$connectSrc = "'self' https://challenges.cloudflare.com";
$frameSrc = "https://challenges.cloudflare.com";
$imgSrc = "'self' data:";
$pageScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if ($pageScript === 'tools.php') {
    // The curl tool issues CORS fetches from the visitor’s browser only.
    $connectSrc = "'self' https: http: https://challenges.cloudflare.com";
}
if ($pageScript === 'videos.php') {
    $frameSrc = videos_csp_frame_src();
    $imgSrc = "'self' data: https://i.ytimg.com https://img.youtube.com https://i.vimeocdn.com https://www.dailymotion.com https://s1.dmcdn.net https://s2.dmcdn.net";
}
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src $imgSrc; connect-src $connectSrc; frame-src $frameSrc; child-src $frameSrc; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests");
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header_remove('X-Powered-By');

try {
    db();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Setup</title>';
    echo '<pre style="font-family:Georgia,serif;padding:32px;white-space:pre-wrap;">';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo "\n\nSee INSTALL-NAMECHEAP.md</pre>";
    exit;
}
