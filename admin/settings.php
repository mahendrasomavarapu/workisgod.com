<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';

$admin = require_admin();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $next = (string) ($_POST['new_password'] ?? '');
        $again = (string) ($_POST['new_password2'] ?? '');
        $row = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $row->execute([(int) $admin['id']]);
        $hash = (string) ($row->fetch()['password_hash'] ?? '');
        if (!password_verify($current, $hash)) {
            $error = 'Current password is not correct.';
        } elseif (strlen($next) < 10) {
            $error = 'New password must be at least 10 characters.';
        } elseif ($next !== $again) {
            $error = 'New passwords do not match.';
        } else {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($next, PASSWORD_DEFAULT), $admin['id']]);
            flash_set('ok', 'Admin password updated.');
            redirect('/admin/settings.php');
        }
    } else {
        setting_set('ai_enabled', isset($_POST['ai_enabled']) ? '1' : '0');
        setting_set('signups_open', isset($_POST['signups_open']) ? '1' : '0');
        $note = trim((string) ($_POST['admin_note'] ?? ''));
        setting_set('admin_note', substr($note, 0, 2000));
        flash_set('ok', 'Settings saved.');
        redirect('/admin/settings.php');
    }
}

$aiOn = setting('ai_enabled', '1') === '1';
$signups = setting('signups_open', '1') === '1';
$note = setting('admin_note', '');

render_admin_header('Settings', 'settings');
?>
<main id="main" class="wrap admin-wrap">
    <p class="eyebrow">Configuration</p>
    <h1>Settings</h1>
    <?php if ($error): ?>
        <p class="form-error"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <label class="check">
            <input type="checkbox" name="ai_enabled" value="1" <?= $aiOn ? 'checked' : '' ?>>
            <span>AI agent enabled for users</span>
        </label>
        <label class="check">
            <input type="checkbox" name="signups_open" value="1" <?= $signups ? 'checked' : '' ?>>
            <span>Allow new user email sign-in</span>
        </label>
        <label for="admin_note">Internal note</label>
        <textarea id="admin_note" name="admin_note" rows="4"><?= h($note) ?></textarea>
        <p class="hint">SMTP, Turnstile, and API keys stay in <code>config.local.php</code>. Cloudflare DNS is set in the Cloudflare dashboard (see <code>CLOUDFLARE.md</code>).</p>
        <p class="hint">Captcha is <?= captcha_enabled() ? 'enabled' : 'not configured' ?>. Cloudflare proxy is <?= behind_cloudflare() ? 'detected' : 'not detected' ?>.</p>
        <button type="submit">Save settings</button>
    </form>

    <h2>Change admin password</h2>
    <form method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" required>
        <label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" required minlength="10">
        <label for="new_password2">Confirm new password</label>
        <input id="new_password2" name="new_password2" type="password" required minlength="10">
        <button type="submit">Update password</button>
    </form>
</main>
<?php
render_admin_footer();
