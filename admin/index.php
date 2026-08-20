<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';

require_admin();
$pdo = db();

$users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$resumes = (int) $pdo->query('SELECT COUNT(*) FROM resumes')->fetchColumn();
$aiUsed = (int) $pdo->query('SELECT COUNT(*) FROM resumes WHERE ai_used = 1')->fetchColumn();
$since = gmdate('c', time() - 7 * 86400);
$weekStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE created_at >= ?');
$weekStmt->execute([$since]);
$week = (int) $weekStmt->fetchColumn();

$recent = $pdo->query(
    'SELECT email, created_at, last_login_at FROM users ORDER BY id DESC LIMIT 12'
)->fetchAll();

render_admin_header('Dashboard', 'dashboard');
?>
<main class="wrap admin-wrap">
    <p class="eyebrow">Reporting</p>
    <h1>Overview</h1>
    <ul class="admin-stats">
        <li><strong><?= $users ?></strong><span>Users</span></li>
        <li><strong><?= $resumes ?></strong><span>Resumes</span></li>
        <li><strong><?= $aiUsed ?></strong><span>AI drafts</span></li>
        <li><strong><?= $week ?></strong><span>New this week</span></li>
    </ul>
    <h2>Recent users</h2>
    <table class="admin-table">
        <thead><tr><th>Email</th><th>Joined</th><th>Last login</th></tr></thead>
        <tbody>
        <?php if (!$recent): ?>
            <tr><td colspan="3">No users yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $row): ?>
            <tr>
                <td><?= h($row['email']) ?></td>
                <td><?= h((string) $row['created_at']) ?></td>
                <td><?= h((string) ($row['last_login_at'] ?? '—')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php
render_admin_footer();
