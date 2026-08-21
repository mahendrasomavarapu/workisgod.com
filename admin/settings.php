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
    } elseif ($action === 'refresh_news') {
        admin_save_flags_from_post();
        if (rate_limited('news_refresh:' . (string) $admin['id'], 6, 300)) {
            $error = 'Settings saved. News was refreshed too recently — wait a minute and try again.';
        } else {
            $report = news_force_refresh();
            $failN = count($report['failed'] ?? []);
            $msg = 'News refreshed · ' . (int) ($report['telecom'] ?? 0) . ' telecom · '
                . (int) ($report['banking'] ?? 0) . ' banking · '
                . (int) ($report['ok'] ?? 0) . ' sources read';
            if ($failN) {
                $msg .= ' · ' . $failN . ' source(s) did not answer';
            }
            flash_set($failN && (int) ($report['ok'] ?? 0) === 0 ? 'error' : 'ok', $msg);
            redirect('/admin/settings.php');
        }
    } else {
        admin_save_flags_from_post();
        flash_set('ok', 'Settings saved.');
        redirect('/admin/settings.php');
    }
}

function admin_save_flags_from_post(): void
{
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
    setting_set('news_enabled', isset($_POST['news_enabled']) ? '1' : '0');
    foreach (['telecom', 'banking'] as $sector) {
        $raw = (string) ($_POST['news_' . $sector . '_sites'] ?? '');
        $urls = news_parse_site_lines($raw);
        setting_set('news_' . $sector . '_sites', implode("\n", $urls));
    }
}

$mode = signup_mode();
$loginsOn = user_logins_enabled();
$newAdmins = allow_new_admins();
$aiOn = setting('ai_enabled', '1') === '1';
$newsOn = news_enabled();
$note = setting('admin_note', '');
$newsReport = news_refresh_report();

render_admin_header('Settings', 'settings');
$at = (int) ($newsReport['at'] ?? 0);
$failed = $newsReport['failed'] ?? [];
?>
<main id="main" class="wrap admin-wrap admin-settings">
    <header class="settings-head">
        <p class="eyebrow">Configuration</p>
        <h1>Settings</h1>
        <p class="lede">Large taps. Clear on/off. Built for a phone in the hand.</p>
    </header>
    <?php if ($error): ?>
        <p class="form-error"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post" class="settings-form">
        <?= csrf_field() ?>

        <section class="settings-card">
            <h2>New users</h2>
            <p class="settings-lead">How a first-time email is treated.</p>
            <div class="seg" role="radiogroup" aria-label="New users">
                <label class="seg-btn">
                    <input class="sr-only" type="radio" name="signup_mode" value="open" <?= $mode === 'open' ? 'checked' : '' ?>>
                    <span>Admit now<small>Auto-allow</small></span>
                </label>
                <label class="seg-btn">
                    <input class="sr-only" type="radio" name="signup_mode" value="approval" <?= $mode === 'approval' ? 'checked' : '' ?>>
                    <span>Wait<small>Until you approve</small></span>
                </label>
                <label class="seg-btn">
                    <input class="sr-only" type="radio" name="signup_mode" value="closed" <?= $mode === 'closed' ? 'checked' : '' ?>>
                    <span>Closed<small>No new seats</small></span>
                </label>
            </div>
        </section>

        <section class="settings-card">
            <h2>Doors</h2>
            <label class="switch-row">
                <span class="switch-copy">
                    <strong>User logins</strong>
                    <small>Guest OTP doors. Off does not lock this admin room.</small>
                </span>
                <span class="switch">
                    <input class="sr-only" type="checkbox" name="user_logins_enabled" value="1" <?= $loginsOn ? 'checked' : '' ?>>
                    <span class="switch-ui" aria-hidden="true"></span>
                </span>
            </label>
            <label class="switch-row">
                <span class="switch-copy">
                    <strong>New administrators</strong>
                    <small>Allow seating another admin from this page.</small>
                </span>
                <span class="switch">
                    <input class="sr-only" type="checkbox" name="allow_new_admins" value="1" <?= $newAdmins ? 'checked' : '' ?>>
                    <span class="switch-ui" aria-hidden="true"></span>
                </span>
            </label>
            <label class="switch-row">
                <span class="switch-copy">
                    <strong>AI agent</strong>
                    <small>Improve and harder-thinking for signed-in users.</small>
                </span>
                <span class="switch">
                    <input class="sr-only" type="checkbox" name="ai_enabled" value="1" <?= $aiOn ? 'checked' : '' ?>>
                    <span class="switch-ui" aria-hidden="true"></span>
                </span>
            </label>
        </section>

        <section class="settings-card">
            <h2>Technical news</h2>
            <label class="switch-row">
                <span class="switch-copy">
                    <strong>News desk</strong>
                    <small>Homepage, header, and /news.</small>
                </span>
                <span class="switch">
                    <input class="sr-only" type="checkbox" name="news_enabled" value="1" <?= $newsOn ? 'checked' : '' ?>>
                    <span class="switch-ui" aria-hidden="true"></span>
                </span>
            </label>
            <label class="settings-field" for="news_telecom_sites">Telecom sites</label>
            <textarea id="news_telecom_sites" name="news_telecom_sites" rows="6" inputmode="url"><?= h(news_site_text('telecom')) ?></textarea>
            <p class="hint">One https URL per line. A homepage is fine; a <code>/feed</code> or <code>/rss.xml</code> link is better. Max 12.</p>
            <label class="settings-field" for="news_banking_sites">Banking sites</label>
            <textarea id="news_banking_sites" name="news_banking_sites" rows="6" inputmode="url"><?= h(news_site_text('banking')) ?></textarea>
            <p class="hint">Public https only. Localhost and private hosts are ignored.</p>
            <p class="settings-status">
                <?php if ($at > 0): ?>
                    Last refresh <?= h(gmdate('j M Y H:i', $at)) ?> UTC
                    · <?= (int) ($newsReport['telecom'] ?? 0) ?> telecom
                    · <?= (int) ($newsReport['banking'] ?? 0) ?> banking
                    · <?= (int) ($newsReport['ok'] ?? 0) ?> sources read
                    <?php if ($failed): ?>
                        · missed <?= h(implode(', ', array_map(static fn ($u) => news_source_label((string) $u), $failed))) ?>
                    <?php endif; ?>
                <?php else: ?>
                    Not refreshed from these sites yet.
                <?php endif; ?>
            </p>
        </section>

        <section class="settings-card">
            <h2>Internal note</h2>
            <label class="sr-only" for="admin_note">Internal note</label>
            <textarea id="admin_note" name="admin_note" rows="4"><?= h($note) ?></textarea>
        </section>

        <div class="settings-actions">
            <button type="submit" name="action" value="save">Save</button>
            <button type="submit" name="action" value="refresh_news" class="secondary">Refresh news</button>
        </div>
    </form>

    <?php if ($newAdmins): ?>
        <form method="post" class="settings-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_admin">
            <section class="settings-card">
                <h2>Seat a new admin</h2>
                <label class="settings-field" for="admin_email">Email</label>
                <input id="admin_email" name="admin_email" type="email" required autocomplete="off">
                <label class="settings-field" for="admin_pass">Password</label>
                <input id="admin_pass" name="admin_pass" type="password" required minlength="10" autocomplete="new-password">
                <button type="submit">Add administrator</button>
            </section>
        </form>
    <?php else: ?>
        <p class="hint settings-aside">Taking new admins is off. Flip the toggle above, save, then this form appears.</p>
    <?php endif; ?>

    <form method="post" class="settings-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <section class="settings-card">
            <h2>Your password</h2>
            <label class="settings-field" for="current_password">Current</label>
            <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
            <label class="settings-field" for="new_password">New</label>
            <input id="new_password" name="new_password" type="password" required minlength="10" autocomplete="new-password">
            <label class="settings-field" for="new_password2">Confirm</label>
            <input id="new_password2" name="new_password2" type="password" required minlength="10" autocomplete="new-password">
            <button type="submit">Update password</button>
        </section>
    </form>
</main>
<?php
render_admin_footer();
