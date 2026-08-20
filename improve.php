<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST only', 405);
}
csrf_verify();

$text = (string) ($_POST['raw_text'] ?? '');
$mode = (string) ($_POST['mode'] ?? 'improve');
if ($mode !== 'harder') {
    $mode = 'improve';
}
if (trim($text) === '') {
    json_error('Paste resume notes first.');
}
if (strlen($text) > RESUME_MAX_CHARS) {
    json_error('Resume is too long.');
}

try {
    $improved = ai_improve_resume($text, $mode);
    json_ok([
        'text' => $improved,
        'mode' => $mode,
        'changed' => ai_last_changed(),
        'provider' => ai_last_provider(),
    ]);
} catch (Throwable $e) {
    json_error($e->getMessage());
}
