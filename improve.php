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

$incoming = moderate_profile_text($text);
if (!$incoming['allow']) {
    json_error(moderation_message($incoming), 422, ['alerts' => $incoming['alerts']]);
}

try {
    $improved = ai_improve_resume($text, $mode);
} catch (Throwable $e) {
    json_error($e->getMessage());
}

$outgoing = moderate_profile_text($improved);
if (!$outgoing['allow']) {
    json_error('The AI draft was not safe to use. ' . moderation_message($outgoing), 422, ['alerts' => $outgoing['alerts']]);
}

json_ok([
    'text' => $improved,
    'mode' => $mode,
    'changed' => ai_last_changed(),
    'provider' => ai_last_provider(),
    'alerts' => $outgoing['alerts'],
]);
