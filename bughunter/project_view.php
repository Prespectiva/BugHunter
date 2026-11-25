<?php

declare(strict_types=1);

require __DIR__ . '/projects_lib.php';

$me = requireLogin();
$project = projectFromRequest();

$stmt = getPdo()->prepare(
    'SELECT bug_id, title, priority, status FROM bugs WHERE project_id = ? ORDER BY bug_id'
);
$stmt->execute([$project['project_id']]);
$bugs = $stmt->fetchAll();

pageHeader($project['name']);
?>
<h1><?= h($project['code']) ?> — <?= h($project['name']) ?></h1>
<p class="links">
    <a href="projects.php">← К списку проектов</a>
    <?php if (canManage($me, $project)): ?><a href="project_edit.php?id=<?= (int) $project['project_id'] ?>">Изменить</a><?php endif; ?>
</p>
<?php showMessage(); ?>
<table class="table">
    <tr><th>Автор</th><td><?= h($project['owner_name'] ?? '—') ?></td></tr>
    <tr><th>Создан</th><td><?= h($project['created_at']) ?></td></tr>
    <tr><th>Описание</th><td><?= nl2br(h($project['description'] !== '' ? $project['description'] : '—')) ?></td></tr>
    <tr><th>Дефектов</th><td><?= (int) $project['bugs_count'] ?></td></tr>
</table>

<h2>Дефекты проекта</h2>
<?php if ($bugs === []): ?>
    <p><em>Дефектов нет.</em></p>
<?php else: ?>
<table class="table">
    <tr><th>№</th><th>Заголовок</th><th>Приоритет</th><th>Статус</th></tr>
    <?php foreach ($bugs as $b): ?>
    <tr>
        <td><?= h($project['code'] . '-' . $b['bug_id']) ?></td>
        <td><?= h($b['title']) ?></td>
        <td><?= h($b['priority']) ?></td>
        <td><?= h($b['status']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
<?php pageFooter();
