<?php

declare(strict_types=1);

function render_header(string $title, array $opts = []): void
{
    $user = current_user();
    $bodyClass = $opts['body'] ?? '';
    $path = (string) ($opts['path'] ?? current_path());
    $canonical = rtrim(SITE_URL, '/') . ($path === '' ? '/' : $path);
    $description = (string) ($opts['description'] ?? 'Write a text resume, optionally improve it with AI, pick a theme, and share a stable public URL on Work is God.');
    $pageType = (string) ($opts['type'] ?? 'WebPage');
    $robots = (string) ($opts['robots'] ?? (is_private_page() ? 'noindex,nofollow' : 'index,follow,max-image-preview:large'));
    $fullTitle = $title === SITE_NAME ? SITE_NAME : $title . ' · ' . SITE_NAME;
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebSite',
                '@id' => rtrim(SITE_URL, '/') . '/#website',
                'name' => SITE_NAME,
                'url' => rtrim(SITE_URL, '/') . '/',
                'description' => 'A small site for text resumes with public share URLs.',
                'inLanguage' => 'en',
            ],
            [
                '@type' => $pageType,
                'name' => $fullTitle,
                'url' => $canonical,
                'description' => $description,
                'isPartOf' => ['@id' => rtrim(SITE_URL, '/') . '/#website'],
            ],
        ],
    ];
    if (!empty($opts['jsonld']) && is_array($opts['jsonld'])) {
        $jsonLd['@graph'][] = $opts['jsonld'];
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($fullTitle) ?></title>
    <meta name="description" content="<?= h($description) ?>">
    <meta name="robots" content="<?= h($robots) ?>">
    <meta name="theme-color" content="#161410">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="describedby" href="<?= h(rtrim(SITE_URL, '/') . '/llms.txt') ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($fullTitle) ?>">
    <meta property="og:description" content="<?= h($description) ?>">
    <meta property="og:url" content="<?= h($canonical) ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= h($fullTitle) ?>">
    <meta name="twitter:description" content="<?= h($description) ?>">
    <meta name="ai-content-declaration" content="public-pages-may-be-cited">
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,650&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(asset_url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= h(asset_url('/assets/css/resume.css')) ?>">
    <script>
    try { var t = localStorage.getItem('wig_site_theme'); if (t) document.documentElement.setAttribute('data-site', t); } catch (e) {}
    </script>
</head>
<body class="<?= h($bodyClass) ?>">
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
    <a class="wordmark" href="/"><?= h(SITE_NAME) ?></a>
    <nav>
        <a href="/about.php">About</a>
        <a href="/news">News</a>
        <a href="/tools.php">Tools</a>
        <a href="/safety.php">Safety</a>
        <label class="sr-only" for="site-theme">Site theme</label>
        <select id="site-theme" class="site-theme" aria-label="Site theme">
            <?php foreach (site_themes() as $key => $label): ?>
                <option value="<?= h($key) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($user): ?>
            <span class="who"><?= h($user['email']) ?></span>
            <a href="/editor.php">My resume</a>
            <a href="/account.php">Account</a>
            <a href="/logout.php">Sign out</a>
        <?php else: ?>
            <a href="/login.php">Sign in</a>
        <?php endif; ?>
    </nav>
</header>
    <?php
    $flash = flash_get();
    if ($flash):
        ?>
        <div class="flash flash-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div>
        <?php
    endif;
}

function render_footer(array $opts = []): void
{
    ?>
<footer class="site-footer">
    <p><?= h(SITE_NAME) ?> · your work is received with care</p>
    <p class="fine"><a href="/about.php">About</a> · <a href="/news">News</a> · <a href="/tools.php">Tools</a> · <a href="/safety.php">Safety</a> · <a href="/llms.txt">llms.txt</a></p>
</footer>
<script src="<?= h(asset_url('/assets/js/app.js')) ?>"></script>
<?php
    if (!empty($opts['scripts']) && is_array($opts['scripts'])) {
        foreach ($opts['scripts'] as $src) {
            echo '<script src="' . h(asset_url((string) $src)) . '"></script>' . "\n";
        }
    }
    if (captcha_enabled()): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</body>
</html>
    <?php
}
