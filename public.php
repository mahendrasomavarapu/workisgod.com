<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$isDemo = ($slug === 'demo');
$resume = $isDemo ? null : ($slug !== '' ? resume_by_slug($slug) : null);

if (!$isDemo && !$resume) {
    http_response_code(404);
    render_header('Resume not found', [
        'body' => 'page-public',
        'robots' => 'noindex,nofollow',
        'description' => 'No resume is published at this address.',
    ]);
    echo '<main id="main" class="wrap prose"><p class="eyebrow">404</p><h1>This page is not yet written.</h1><p class="lede">No resume lives at this address. If you were expected, the slug may have changed.</p><p><a class="button" href="/">Return home</a></p></main>';
    render_footer();
    exit;
}

$text = $isDemo ? sample_resume_text() : (string) $resume['raw_text'];
$theme = $isDemo ? 'classic' : (string) $resume['theme'];
$doc = parse_resume($text);
$pageTitle = $doc['name'] !== '' ? $doc['name'] : 'Resume';
$owner = current_user();
$isOwner = $owner && $resume && (int) $owner['id'] === (int) $resume['user_id'];
$person = [
    '@type' => 'Person',
    'name' => $pageTitle,
    'url' => $isDemo ? rtrim(SITE_URL, '/') . '/r/demo' : public_resume_url((string) $resume['slug']),
];
if (!empty($doc['headline'])) {
    $person['jobTitle'] = $doc['headline'];
}
render_header($pageTitle, [
    'body' => 'page-public theme-body-' . $theme,
    'description' => $pageTitle . ($doc['headline'] !== '' ? ' — ' . $doc['headline'] : '') . ' · a public resume on Work is God.',
    'path' => $isDemo ? '/r/demo' : '/r/' . rawurlencode((string) $resume['slug']),
    'type' => 'ProfilePage',
    'jsonld' => $person,
]);
?>
<main id="main" class="public-wrap">
    <div class="public-toolbar no-print">
        <?php if ($isDemo): ?>
            <p>A sample, with our compliments · <a href="/login.php">Write yours</a></p>
        <?php elseif ($isOwner): ?>
            <p>This is your public hall · <a href="/editor.php">Edit</a></p>
        <?php else: ?>
            <p><a href="/"><?= h(SITE_NAME) ?></a></p>
        <?php endif; ?>
        <button type="button" class="secondary" onclick="window.print()">Print / PDF</button>
    </div>
    <?= render_resume_html($doc, $theme) ?>
</main>
<?php
render_footer();
