<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

http_response_code(404);
render_header('Page not found', [
    'body' => 'page-404',
    'description' => 'This page is not at Work is God.',
    'path' => '/404.php',
    'robots' => 'noindex,nofollow',
]);
?>
<main id="main" class="wrap prose">
    <p class="eyebrow">404</p>
    <h1>This room is empty.</h1>
    <p class="lede">The page you asked for is not here. Your place at the table still is.</p>
    <p><a class="button" href="/">Return home</a> <a class="text-link" href="/about.php">About</a></p>
</main>
<?php
render_footer();
