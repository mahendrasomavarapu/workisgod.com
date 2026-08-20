<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';

require_admin();
$rows = db()->query(
    'SELECT r.slug, r.theme, r.ai_used, r.updated_at, length(r.raw_text) AS chars, u.email
     FROM resumes r
     JOIN users u ON u.id = r.user_id
     ORDER BY r.updated_at DESC
     LIMIT 200'
)->fetchAll();

render_admin_header('Resumes', 'resumes');
?>
<main id="main" class="wrap admin-wrap">
    <p class="eyebrow">Backoffice</p>
    <h1>Resumes</h1>
    <table class="admin-table">
        <thead>
            <tr><th>Owner</th><th>URL</th><th>Theme</th><th>AI</th><th>Chars</th><th>Updated</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6">No resumes yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= h($row['email']) ?></td>
                <td><a href="/resumes/<?= h($row['slug']) ?>" target="_blank" rel="noopener">/resumes/<?= h($row['slug']) ?></a></td>
                <td><?= h($row['theme']) ?></td>
                <td><?= !empty($row['ai_used']) ? 'yes' : 'no' ?></td>
                <td><?= (int) $row['chars'] ?></td>
                <td><?= h((string) $row['updated_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php
render_admin_footer();
