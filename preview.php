<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST only', 405);
}
csrf_verify();

$text = (string) ($_POST['raw_text'] ?? '');
$theme = (string) ($_POST['theme'] ?? 'classic');
if (strlen($text) > RESUME_MAX_CHARS) {
    json_error('Resume is too long.');
}

header('Content-Type: text/html; charset=utf-8');
echo render_resume_html(parse_resume($text), $theme);
exit;
