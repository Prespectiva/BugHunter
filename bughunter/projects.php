<?php

declare(strict_types=1);

require __DIR__ . '/projects_lib.php';

$me = requireLogin();

$projects = getPdo()->query(
    'SELECT p.project_id, p.code, p.name, p.owner_id, u.full_name AS owner_name,
            (SELECT COUNT(*) FROM bugs b WHERE b.project_id = p.project_id) AS bugs_count
     FROM projects p
     LEFT JOIN users u ON u.user_id = p.owner_id
     ORDER BY p.project_id'
)->fetchAll();

pageHeader('Проекты');
?>
<h1>Проекты</h1>
<p class="links">
    <?php if (canCreate($me)): ?><a href="project_create.php">+ Новый проект</a><?php endif; ?>
</p>
<?php showMessage(); ?>
<?php if ($projects === []): ?>
    <p><em>Проектов пока нет.</em></p>
<?php else: ?>
<table class="table">
    <tr><th>Код</th><th>Название</th><th>Автор</th><th>Дефектов</th><th>Действия</th></tr>
    <?php foreach ($projects as $p): ?>
    <tr>
        <td><?= h($p['code']) ?></td>
        <td><?= h($p['name']) ?></td>
        <td><?= h($p['owner_name'] ?? '—') ?></td>
        <td><?= (int) $p['bugs_count'] ?></td>
        <td class="actions">
            <a href="project_view.php?id=<?= (int) $p['project_id'] ?>">Открыть</a>
            <?php if (canManage($me, $p)): ?>
                <a href="project_edit.php?id=<?= (int) $p['project_id'] ?>">Изменить</a>
                <form method="POST" action="project_delete.php" onsubmit="return confirm('Удалить проект?')">
                    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $p['project_id'] ?>">
                    <button type="submit" class="btn-danger btn-sm">Удалить</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
<?php pageFooter();