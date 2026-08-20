<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';

require_admin();
$rows = db()->query(
    'SELECT u.id, u.email, u.created_at, u.last_login_at, r.slug, r.theme, r.ai_used, r.updated_at
     FROM users u
     LEFT JOIN resumes r ON r.user_id = u.id
     ORDER BY u.id DESC
     LIMIT 200'
)->fetchAll();

render_admin_header('Users', 'users');
?>
<main class="wrap admin-wrap">
    <p class="eyebrow">Backoffice</p>
    <h1>Users</h1>
    <table class="admin-table">
        <thead>
            <tr><th>Email</th><th>Joined</th><th>Last login</th><th>Resume</th><th>Theme</th><th>AI</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6">No users yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= h($row['email']) ?></td>
                <td><?= h((string) $row['created_at']) ?></td>
                <td><?= h((string) ($row['last_login_at'] ?? '—')) ?></td>
                <td>
                    <?php if (!empty($row['slug'])): ?>
                        <a href="/r/<?= h($row['slug']) ?>" target="_blank" rel="noopener">/r/<?= h($row['slug']) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?= h((string) ($row['theme'] ?? '—')) ?></td>
                <td><?= !empty($row['ai_used']) ? 'yes' : 'no' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php
render_admin_footer();
