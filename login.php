<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('/editor.php');
}

$stage = 'email';
$email = strtolower(trim((string) ($_POST['email'] ?? $_GET['email'] ?? '')));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? 'send');
    if ($action === 'send') {
        if (honeypot_tripped()) {
            $stage = 'otp';
        } else {
            $error = captcha_verify();
            if ($error === '') {
                $error = send_login_otp($email);
            }
            if ($error === '') {
                $stage = 'otp';
            }
        }
    } elseif ($action === 'verify') {
        $error = verify_login_otp($email, (string) ($_POST['code'] ?? ''));
        if ($error === '') {
            redirect('/editor.php');
        }
        $stage = 'otp';
    }
} elseif (isset($_GET['email']) && $email !== '') {
    $stage = 'otp';
}

render_header('Sign in', ['body' => 'page-auth']);
?>
<a class="admin-corner" href="/admin/login.php">Admin</a>
<main id="main" class="wrap auth-wrap">
    <section class="auth-card">
        <p class="eyebrow">Your table</p>
        <h1><?= $stage === 'otp' ? 'Your code is waiting' : 'Be received' ?></h1>
        <?php if ($error): ?>
            <p class="form-error"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if ($stage === 'otp'): ?>
            <p class="lede">A private code is on its way to <strong><?= h($email) ?></strong>. Check spam if the room is quiet.</p>
            <form method="post">
                <?= csrf_field() ?>
                <?= honeypot_field() ?>
                <input type="hidden" name="action" value="verify">
                <input type="hidden" name="email" value="<?= h($email) ?>">
                <label for="code">One-time code</label>
                <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code" placeholder="123456">
                <button type="submit">Sign in</button>
            </form>
            <form method="post" class="resend">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="email" value="<?= h($email) ?>">
                <?= captcha_widget() ?>
                <button type="submit" class="linkish">Send a new code</button>
            </form>
        <?php else: ?>
            <p class="lede">Any inbox will do. We send a one-time code. Nothing as crude as a stored password.</p>
            <form method="post">
                <?= csrf_field() ?>
                <?= honeypot_field() ?>
                <input type="hidden" name="action" value="send">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="email" placeholder="you@gmail.com" value="<?= h($email) ?>">
                <?= captcha_widget() ?>
                <button type="submit">Send login code</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php
render_footer();
