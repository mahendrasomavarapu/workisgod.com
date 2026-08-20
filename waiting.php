<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

$user = require_login();
if (user_status($user) === 'active') {
    redirect('/editor.php');
}

render_header('Awaiting admission', [
    'body' => 'page-waiting',
    'robots' => 'noindex,nofollow',
    'description' => 'Your Work is God seat is reserved pending administrator approval.',
    'path' => '/waiting.php',
]);
?>
<main id="main" class="wrap prose">
    <p class="eyebrow">Reserved</p>
    <h1>Your place is held.</h1>
    <p class="lede">Thank you, <?= h($user['email']) ?>. New guests are admitted by the house. An administrator will approve your seat, then your rooms will open.</p>
    <p>You may wait here, or close your account if you no longer wish to be received.</p>
    <p><a class="button" href="/account.php">Account</a> <a class="text-link" href="/logout.php">Sign out</a></p>
</main>
<?php
render_footer();
