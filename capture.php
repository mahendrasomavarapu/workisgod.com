<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST only', 405);
}
csrf_verify();

@ini_set('max_execution_time', '120');

if (rate_limited('cap-ip:' . client_ip(), 30, 3600) || rate_limited('cap-user:' . (string) ($_SESSION['user_id'] ?? '0'), 20, 3600)) {
    json_error('Too many profile imports. Try again later.');
}

$notes = trim((string) ($_POST['raw_text'] ?? ''));
$url = trim((string) ($_POST['profile_url'] ?? ''));
$chunks = [];
$sources = [];

try {
    if ($url !== '') {
        $got = capture_from_url($url);
        $chunks[] = "SOURCE: {$got['label']}\n" . $got['text'];
        $sources[] = $got['label'];
    }

    if (isset($_FILES['profile_pdf']) && is_uploaded_file($_FILES['profile_pdf']['tmp_name'])) {
        $name = (string) ($_FILES['profile_pdf']['name'] ?? 'profile.pdf');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            json_error('Upload a PDF file.');
        }
        $got = capture_from_pdf($_FILES['profile_pdf']['tmp_name'], $name);
        $chunks[] = "SOURCE: PDF {$got['label']}\n" . $got['text'];
        $sources[] = 'PDF';
    }
} catch (Throwable $e) {
    json_error($e->getMessage());
}

if (!$chunks) {
    json_error('Add a LinkedIn/profile URL or upload a PDF first.');
}

$imported = implode("\n\n----\n\n", $chunks);
if ($notes !== '' && $notes !== sample_resume_text()) {
    $imported .= "\n\n----\n\nEXISTING NOTES\n" . $notes;
}

$incoming = moderate_profile_text($imported);
if (!$incoming['allow']) {
    json_error(moderation_message($incoming), 422, ['alerts' => $incoming['alerts']]);
}

try {
    $resume = ai_improve_resume($imported, 'from_profile');
} catch (Throwable $e) {
    json_error($e->getMessage());
}

$outgoing = moderate_profile_text($resume);
if (!$outgoing['allow']) {
    json_error('The generated résumé was not safe to use. ' . moderation_message($outgoing), 422, ['alerts' => $outgoing['alerts']]);
}

json_ok([
    'text' => $resume,
    'changed' => ai_last_changed(),
    'provider' => ai_last_provider(),
    'sources' => $sources,
    'captured_chars' => strlen($imported),
]);
