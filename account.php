<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

$user = require_login();
$resume = user_resume((int) $user['id']);
$error = '';
$stage = 'menu';
$purpose = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $purpose = (string) ($_POST['purpose'] ?? '');
    if ($action === 'send' && in_array($purpose, ['delete_resume', 'delete_account'], true)) {
        if ($purpose === 'delete_resume' && !$resume) {
            $error = 'There is no published resume to delete.';
        } else {
            $error = captcha_verify();
            if ($error === '') {
                $error = send_action_otp($user['email'], $purpose);
            }
            if ($error === '') {
                $stage = 'code';
            }
        }
    } elseif ($action === 'confirm' && in_array($purpose, ['delete_resume', 'delete_account'], true)) {
        $error = verify_action_otp($user['email'], (string) ($_POST['code'] ?? ''), $purpose);
        if ($error === '') {
            if ($purpose === 'delete_resume') {
                delete_user_resume((int) $user['id']);
                flash_set('ok', 'Your public resume was deleted. The old URL no longer works.');
                redirect('/editor.php');
            }
            delete_user_account((int) $user['id'], $user['email']);
            logout_user();
            // flash after logout would be lost; send query
            header('Location: /?deleted=1');
            exit;
        }
        $stage = 'code';
    } elseif ($action === 'cancel') {
        $stage = 'menu';
        $purpose = '';
    }
}

$labels = [
    'delete_resume' => 'delete your public resume',
    'delete_account' => 'delete your account and resume',
];

render_header('Account', [
    'body' => 'page-account',
    'description' => 'Manage your Work is God account. Delete your public resume or delete your account after an email confirmation code.',
    'path' => '/account.php',
]);
?>
<main id="main" class="wrap prose">
    <p class="eyebrow">Account</p>
    <h1>Your data</h1>
    <p class="lede">Signed in as <strong><?= h($user['email']) ?></strong>. Destructive actions send a new 6-digit code to this inbox.</p>
    <?php if ($error): ?>
        <p class="form-error"><?= h($error) ?></p>
    <?php endif; ?>

    <?php if ($stage === 'code'): ?>
        <section class="danger-box">
            <h2>Enter the code</h2>
            <p>We emailed a code to confirm you want to <?= h($labels[$purpose] ?? 'continue') ?>.</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="purpose" value="<?= h($purpose) ?>">
                <label for="code">One-time code</label>
                <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code" placeholder="123456">
                <p>
                    <button type="submit">Confirm</button>
                </p>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="purpose" value="<?= h($purpose) ?>">
                <?= captcha_widget() ?>
                <button type="submit" class="linkish">Send a new code</button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="linkish">Cancel</button>
            </form>
        </section>
    <?php else: ?>
        <section class="danger-box">
            <h2>Delete public resume</h2>
            <p>Removes the page at your <code>/resumes/</code> URL. Your account stays so you can write a new one. This needs an OTP.</p>
            <?php if ($resume): ?>
                <p>Current URL: <a href="<?= h(public_resume_url($resume['slug'])) ?>"><?= h(public_resume_url($resume['slug'])) ?></a></p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send">
                    <input type="hidden" name="purpose" value="delete_resume">
                    <?= captcha_widget() ?>
                    <button type="submit" class="danger">Email a code to delete resume</button>
                </form>
            <?php else: ?>
                <p>No public resume is published yet. <a href="/editor.php">Write one</a>.</p>
            <?php endif; ?>
        </section>

        <section class="danger-box">
            <h2>Delete account</h2>
            <p>Permanently removes your email login, resume, and public URL. This cannot be undone. This needs an OTP.</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="purpose" value="delete_account">
                <?= captcha_widget() ?>
                <button type="submit" class="danger">Email a code to delete account</button>
            </form>
        </section>
        <p><a href="/editor.php">Back to my resume</a></p>
    <?php endif; ?>
</main>
<?php
render_footer();
