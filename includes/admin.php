<?php

declare(strict_types=1);

function render_admin_header(string $title, string $active = 'dashboard'): void
{
    $admin = current_admin();
    $fullTitle = $title . ' · Admin · ' . SITE_NAME;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($fullTitle) ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= h(asset_url('/assets/css/app.css')) ?>">
    <script>
    try { var t = localStorage.getItem('wig_site_theme'); if (t) document.documentElement.setAttribute('data-site', t); } catch (e) {}
    </script>
</head>
<body class="page-admin">
<header class="site-header">
    <a class="wordmark" href="/admin/"><?= h(SITE_NAME) ?> admin</a>
    <nav>
        <a href="/admin/" class="<?= $active === 'dashboard' ? 'is-on' : '' ?>">Dashboard</a>
        <a href="/admin/users.php" class="<?= $active === 'users' ? 'is-on' : '' ?>">Users</a>
        <a href="/admin/resumes.php" class="<?= $active === 'resumes' ? 'is-on' : '' ?>">Resumes</a>
        <a href="/admin/settings.php" class="<?= $active === 'settings' ? 'is-on' : '' ?>">Settings</a>
        <?php if ($admin): ?>
            <span class="who"><?= h($admin['email']) ?></span>
            <a href="/admin/logout.php">Sign out</a>
        <?php endif; ?>
    </nav>
</header>
    <?php
    $flash = flash_get();
    if ($flash):
        echo '<div class="flash flash-' . h($flash['type']) . '">' . h($flash['message']) . '</div>';
    endif;
}

function render_admin_footer(): void
{
    echo '<footer class="site-footer"><p>Backoffice · ' . h(SITE_NAME) . '</p></footer></body></html>';
}
