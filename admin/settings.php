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
    } elseif ($action === 'add_admin') {
        if (!allow_new_admins()) {
            $error = 'Taking new admins is disabled.';
        } else {
            $email = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
            $pass = (string) ($_POST['admin_pass'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
                $error = 'Give a valid email and a password of at least 10 characters.';
            } else {
                $exists = db()->prepare('SELECT id FROM admins WHERE email = ?');
                $exists->execute([$email]);
                if ($exists->fetch()) {
                    $error = 'That admin email is already seated.';
                } else {
                    db()->prepare('INSERT INTO admins (email, password_hash, created_at) VALUES (?, ?, ?)')
                        ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), iso_now()]);
                    flash_set('ok', 'A new administrator was seated.');
                    redirect('/admin/settings.php');
                }
            }
        }
    } else {
        $mode = (string) ($_POST['signup_mode'] ?? 'open');
        if (!in_array($mode, ['open', 'approval', 'closed'], true)) {
            $mode = 'open';
        }
        setting_set('signup_mode', $mode);
        setting_set('signups_open', $mode === 'closed' ? '0' : '1');
        setting_set('user_logins_enabled', isset($_POST['user_logins_enabled']) ? '1' : '0');
        setting_set('allow_new_admins', isset($_POST['allow_new_admins']) ? '1' : '0');
        setting_set('ai_enabled', isset($_POST['ai_enabled']) ? '1' : '0');
        $note = trim((string) ($_POST['admin_note'] ?? ''));
        setting_set('admin_note', substr($note, 0, 2000));
        flash_set('ok', 'Settings saved.');
        redirect('/admin/settings.php');
    }
}

$mode = signup_mode();
$loginsOn = user_logins_enabled();
$newAdmins = allow_new_admins();
$aiOn = setting('ai_enabled', '1') === '1';
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
        <p class="import-label">New users</p>
        <label class="check"><input type="radio" name="signup_mode" value="open" <?= $mode === 'open' ? 'checked' : '' ?>> <span>Auto-allow new users (admit immediately)</span></label>
        <label class="check"><input type="radio" name="signup_mode" value="approval" <?= $mode === 'approval' ? 'checked' : '' ?>> <span>Keep new users waiting until an admin approves them</span></label>
        <label class="check"><input type="radio" name="signup_mode" value="closed" <?= $mode === 'closed' ? 'checked' : '' ?>> <span>Do not take new users</span></label>
        <p class="import-label">Doors</p>
        <label class="check">
            <input type="checkbox" name="user_logins_enabled" value="1" <?= $loginsOn ? 'checked' : '' ?>>
            <span>Allow user logins (uncheck to close all guest doors)</span>
        </label>
        <label class="check">
            <input type="checkbox" name="allow_new_admins" value="1" <?= $newAdmins ? 'checked' : '' ?>>
            <span>Allow seating new administrators</span>
        </label>
        <label class="check">
            <input type="checkbox" name="ai_enabled" value="1" <?= $aiOn ? 'checked' : '' ?>>
            <span>AI agent enabled for users</span>
        </label>
        <label for="admin_note">Internal note</label>
        <textarea id="admin_note" name="admin_note" rows="4"><?= h($note) ?></textarea>
        <p class="hint">Closing user logins does not lock you out of this admin room.</p>
        <button type="submit">Save settings</button>
    </form>

    <?php if ($newAdmins): ?>
        <h2>Seat a new admin</h2>
        <form method="post" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_admin">
            <label for="admin_email">Email</label>
            <input id="admin_email" name="admin_email" type="email" required>
            <label for="admin_pass">Password</label>
            <input id="admin_pass" name="admin_pass" type="password" required minlength="10">
            <button type="submit">Add administrator</button>
        </form>
    <?php else: ?>
        <p class="hint">Taking new admins is disabled. Enable the flag above to seat another.</p>
    <?php endif; ?>

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
