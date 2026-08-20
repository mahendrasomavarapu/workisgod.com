<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['user_id'] ?? 0);
    $act = (string) ($_POST['user_action'] ?? '');
    if ($id > 0 && in_array($act, ['approve', 'disable', 'pending'], true)) {
        $status = $act === 'approve' ? 'active' : ($act === 'disable' ? 'disabled' : 'pending');
        db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $id]);
        flash_set('ok', 'Guest status updated.');
        redirect('/admin/users.php');
    }
}

$rows = db()->query(
    'SELECT u.id, u.email, u.created_at, u.last_login_at, u.status, r.slug, r.theme, r.ai_used, r.updated_at
     FROM users u
     LEFT JOIN resumes r ON r.user_id = u.id
     ORDER BY CASE u.status WHEN \'pending\' THEN 0 WHEN \'active\' THEN 1 ELSE 2 END, u.id DESC
     LIMIT 200'
)->fetchAll();

$pending = 0;
foreach ($rows as $row) {
    if (($row['status'] ?? '') === 'pending') {
        $pending++;
    }
}

render_admin_header('Users', 'users');
?>
<main id="main" class="wrap admin-wrap">
    <p class="eyebrow">Backoffice</p>
    <h1>Users</h1>
    <?php if ($pending): ?>
        <p class="lede"><?= (int) $pending ?> guest<?= $pending === 1 ? '' : 's' ?> waiting for admission.</p>
    <?php endif; ?>
    <table class="admin-table">
        <thead>
            <tr><th>Email</th><th>Status</th><th>Joined</th><th>Resume</th><th>Admit</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="5">No users yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= h($row['email']) ?></td>
                <td><?= h((string) ($row['status'] ?? 'active')) ?></td>
                <td><?= h((string) $row['created_at']) ?></td>
                <td>
                    <?php if (!empty($row['slug'])): ?>
                        <a href="/resumes/<?= h($row['slug']) ?>" target="_blank" rel="noopener">/resumes/<?= h($row['slug']) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" class="inline-actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                        <?php if (($row['status'] ?? '') !== 'active'): ?>
                            <button type="submit" name="user_action" value="approve">Approve</button>
                        <?php endif; ?>
                        <?php if (($row['status'] ?? '') !== 'pending'): ?>
                            <button type="submit" name="user_action" value="pending" class="secondary">Hold</button>
                        <?php endif; ?>
                        <?php if (($row['status'] ?? '') !== 'disabled'): ?>
                            <button type="submit" name="user_action" value="disable" class="danger">Disable</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php
render_admin_footer();
