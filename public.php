<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$isDemo = ($slug === 'demo');
$resume = $isDemo ? null : ($slug !== '' ? resume_by_slug($slug) : null);

if (!$isDemo && !$resume) {
    http_response_code(404);
    render_header('Resume not found', ['body' => 'page-public']);
    echo '<main class="wrap"><section class="auth-card"><h1>No resume here</h1><p class="lede">That public URL does not exist yet.</p><a class="button" href="/">Go home</a></section></main>';
    render_footer();
    exit;
}

$text = $isDemo ? sample_resume_text() : (string) $resume['raw_text'];
$theme = $isDemo ? 'classic' : (string) $resume['theme'];
$doc = parse_resume($text);
$pageTitle = $doc['name'] !== '' ? $doc['name'] : 'Resume';
$owner = current_user();
$isOwner = $owner && $resume && (int) $owner['id'] === (int) $resume['user_id'];

render_header($pageTitle, ['body' => 'page-public theme-body-' . $theme]);
?>
<main class="public-wrap">
    <div class="public-toolbar no-print">
        <?php if ($isDemo): ?>
            <p>Sample resume · <a href="/login.php">Create yours</a></p>
        <?php elseif ($isOwner): ?>
            <p>This is your public page · <a href="/editor.php">Edit</a></p>
        <?php else: ?>
            <p><a href="/"><?= h(SITE_NAME) ?></a></p>
        <?php endif; ?>
        <button type="button" class="secondary" onclick="window.print()">Print / PDF</button>
    </div>
    <?= render_resume_html($doc, $theme) ?>
</main>
<?php
render_footer();
