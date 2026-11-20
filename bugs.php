<?php

declare(strict_types=1);

require __DIR__ . '/bugs_lib.php';

$me = requireLogin();

// Фильтры списка (необязательные)
$fProject  = filter_input(INPUT_GET, 'project', FILTER_VALIDATE_INT) ?: null;
$fPriority = array_key_exists($_GET['priority'] ?? '', PRIORITIES) ? $_GET['priority'] : null;
$fStatus   = in_array($_GET['status'] ?? '', STATUSES, true) ? $_GET['status'] : null;

$where = [];
$params = [];
if ($fProject !== null)  { $where[] = 'b.project_id = ?'; $params[] = $fProject; }
if ($fPriority !== null) { $where[] = 'b.priority = ?';   $params[] = $fPriority; }
if ($fStatus !== null)   { $where[] = 'b.status = ?';     $params[] = $fStatus; }

$sql = "SELECT b.bug_id, b.title, b.priority, b.status, b.created_at, b.author_id, b.assignee_id,
               p.code AS project_code, a.full_name AS author_name, s.full_name AS assignee_name
        FROM bugs b
        JOIN projects p   ON p.project_id = b.project_id
        JOIN users a      ON a.user_id = b.author_id
        LEFT JOIN users s ON s.user_id = b.assignee_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY CASE b.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END,
                  b.bug_id DESC";

$stmt = getPdo()->prepare($sql);
$stmt->execute($params);
$bugs = $stmt->fetchAll();

// Проверяем, есть ли хотя бы одна ошибка, которую текущий пользователь может редактировать
$hasActions = false;
foreach ($bugs as $b) {
    if (canEditBug($me, $b)) {
        $hasActions = true;
        break;
    }
}

pageHeader('Ошибки');
?>
<div class="page-head">
    <div>
        <h1>Ошибки</h1>
        <div class="muted">Найдено: <?= count($bugs) ?></div>
    </div>
    <?php if (canCreateBug($me)): ?>
        <a class="btn" href="bug_create.php">+ Зарегистрировать ошибку</a>
    <?php endif; ?>
</div>
<?php showMessage(); ?>

<form class="filters" method="GET" action="bugs.php">
    <select name="project">
        <option value="">Все проекты</option>
        <?php foreach (allProjects() as $p): ?>
            <option value="<?= (int) $p['project_id'] ?>" <?= $fProject === (int) $p['project_id'] ? 'selected' : '' ?>><?= h($p['code']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="priority">
        <option value="">Любой приоритет</option>
        <?php foreach (array_keys(PRIORITIES) as $p): ?>
            <option <?= $fPriority === $p ? 'selected' : '' ?>><?= h($p) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">Любой статус</option>
        <?php foreach (STATUSES as $s): ?>
            <option <?= $fStatus === $s ? 'selected' : '' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-sm">Показать</button>
    <a class="btn btn-light btn-sm" href="bugs.php">Сбросить</a>
</form>

<?php if ($bugs === []): ?>
    <div class="card muted">Ошибок не найдено.</div>
<?php else: ?>
<table class="table">
    <tr>
        <th>№</th><th>Название</th><th>Проект</th><th>Приоритет</th>
        <th>Статус</th><th>Автор</th><th>Исполнитель</th>
        <?php if ($hasActions): ?><th></th><?php endif; ?>
    </tr>
    <?php foreach ($bugs as $b): ?>
    <tr>
        <td><?= (int) $b['bug_id'] ?></td>
        <td><a href="bug_view.php?id=<?= (int) $b['bug_id'] ?>"><?= h($b['title']) ?></a></td>
        <td><?= h($b['project_code']) ?></td>
        <td><?= priorityBadge($b['priority']) ?></td>
        <td><?= statusBadge($b['status']) ?></td>
        <td><?= h($b['author_name']) ?></td>
        <td><?= h($b['assignee_name'] ?? '—') ?></td>
        <?php if ($hasActions): ?>
            <td class="actions">
                <?php if (canEditBug($me, $b)): ?>
                    <a href="bug_edit.php?id=<?= (int) $b['bug_id'] ?>">Изменить</a>
                <?php endif; ?>
            </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
<?php pageFooter();