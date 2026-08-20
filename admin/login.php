<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';

if (current_admin()) {
    redirect('/admin/');
}

$error = '';
$email = strtolower(trim((string) ($_POST['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $error = verify_admin_login($email, (string) ($_POST['password'] ?? ''));
    if ($error === '') {
        redirect('/admin/');
    }
}

render_header('Admin sign in', ['body' => 'page-auth']);
?>
<main class="wrap auth-wrap">
    <section class="auth-card">
        <p class="eyebrow">Backoffice</p>
        <h1>Admin sign in</h1>
        <p class="lede">Staff login for reporting and configuration. Regular users should use email codes.</p>
        <?php if ($error): ?>
            <p class="form-error"><?= h($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="email">Admin email</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username" value="<?= h($email) ?>">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <button type="submit">Sign in to admin</button>
        </form>
        <p class="fine"><a href="/login.php">Back to user sign in</a></p>
    </section>
</main>
<?php
render_footer();
