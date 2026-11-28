<?php

declare(strict_types=1);

require __DIR__ . '/../app/search_lib.php';

$me = requireLogin();
$pdo = getPdo();

// Поиск — это чтение, поэтому все параметры передаются методом GET (ссылку можно сохранить)
[$f, $warnings] = parseFilters($_GET);
[$whereSql, $params] = buildWhere($f);

// Сортировка: поле и направление берутся только из белого списка
$sortExpr = SORT_COLUMNS[$f['sort']];
$dir = $f['dir'] === 'asc' ? 'ASC' : 'DESC';
$orderSql = " ORDER BY {$sortExpr} {$dir}, b.bug_id DESC";

$selectSql = "SELECT b.bug_id, b.title, b.priority, b.status, b.created_at, b.author_id, b.assignee_id,
                     (SELECT COUNT(*) FROM comments c WHERE c.bug_id = b.bug_id) AS comments_count,
                     p.code AS project_code, a.full_name AS author_name, s.full_name AS assignee_name "
           . BUGS_FROM_SQL . $whereSql . $orderSql;

// --- Экспорт найденных ошибок в CSV (все страницы выборки) ---
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $pdo->prepare($selectSql);
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bugs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM, чтобы Excel открыл UTF-8
    $safe = static fn (string $v): string => preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;   // защита от формул
    fputcsv($out, ['№', 'Название', 'Проект', 'Приоритет', 'Статус', 'Автор', 'Исполнитель', 'Комментарии', 'Создана'], ';', '"', '\\');
    foreach ($stmt as $r) {
        fputcsv($out, [
            $r['bug_id'], $safe((string) $r['title']), $r['project_code'], $r['priority'], $r['status'],
            $safe((string) $r['author_name']), $r['assignee_name'] ?? '', $r['comments_count'], $r['created_at'],
        ], ';', '"', '\\');
    }
    fclose($out);
    exit;
}

// --- Пагинация: LIMIT / OFFSET ---
$cnt = $pdo->prepare('SELECT COUNT(*) ' . BUGS_FROM_SQL . $whereSql);
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$pages = max(1, (int) ceil($total / PER_PAGE));
$page = min($f['page'], $pages);
$offset = ($page - 1) * PER_PAGE;

$stmt = $pdo->prepare($selectSql . ' LIMIT :lim OFFSET :off');
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$bugs = $stmt->fetchAll();

$hasActions = false;
foreach ($bugs as $b) {
    if (canEditBug($me, $b)) {
        $hasActions = true;
        break;
    }
}

$pageUrl = static fn (int $n): string => 'bugs.php?' . http_build_query(filterQuery($f, $n > 1 ? ['page' => $n] : []));
$sel = static fn (bool $cond): string => $cond ? ' selected' : '';

pageHeader('Ошибки');
?>
<div class="page-head">
    <div>
        <h1>Ошибки</h1>
        <div class="muted">Найдено: <?= $total ?> · страница <?= $page ?> из <?= $pages ?></div>
    </div>
    <div>
        <a class="btn btn-light" href="bugs.php?<?= h(http_build_query(filterQuery($f, ['export' => 'csv']))) ?>">Экспорт в CSV</a>
        <?php if (canCreateBug($me)): ?>
            <a class="btn" href="bug_create.php">+ Зарегистрировать ошибку</a>
        <?php endif; ?>
    </div>
</div>
<?php showMessage(); ?>
<?php if ($warnings !== []): ?>
    <div class="errors"><?php foreach ($warnings as $w): ?><p><?= h($w) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<form class="filters" method="GET" action="bugs.php">
    <input type="text" name="q" value="<?= h($f['q']) ?>" maxlength="100" placeholder="Поиск: название, описание, шаги, автор" class="f-wide">

    <select name="project">
        <option value="">Все проекты</option>
        <?php foreach (allProjects() as $p): ?>
            <option value="<?= (int) $p['project_id'] ?>"<?= $sel($f['project'] === (int) $p['project_id']) ?>><?= h($p['code']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">Любой статус</option>
        <?php foreach (STATUSES as $s): ?>
            <option<?= $sel($f['status'] === $s) ?>><?= h($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="priority">
        <option value="">Любой приоритет</option>
        <?php foreach (array_keys(PRIORITIES) as $p): ?>
            <option<?= $sel($f['priority'] === $p) ?>><?= h($p) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="author">
        <option value="">Любой автор</option>
        <?php foreach (allUsers() as $u): ?>
            <option value="<?= (int) $u['user_id'] ?>"<?= $sel($f['author'] === (int) $u['user_id']) ?>><?= h($u['full_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="assignee">
        <option value="">Любой исполнитель</option>
        <?php foreach (assignableUsers() as $u): ?>
            <option value="<?= (int) $u['user_id'] ?>"<?= $sel($f['assignee'] === (int) $u['user_id']) ?>><?= h($u['full_name']) ?></option>
        <?php endforeach; ?>
    </select>

    <label class="f-label">с <input type="date" name="date_from" value="<?= h($f['date_from']) ?>"></label>
    <label class="f-label">по <input type="date" name="date_to" value="<?= h($f['date_to']) ?>"></label>

    <select name="sort">
        <?php foreach (SORT_TITLES as $key => $title): ?>
            <option value="<?= h($key) ?>"<?= $sel($f['sort'] === $key) ?>><?= h($title) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="dir">
        <option value="desc"<?= $sel($f['dir'] === 'desc') ?>>По убыванию</option>
        <option value="asc"<?= $sel($f['dir'] === 'asc') ?>>По возрастанию</option>
    </select>

    <button type="submit" class="btn-sm">Найти</button>
    <a class="btn btn-light btn-sm" href="bugs.php">Сбросить</a>
</form>

<?php if ($bugs === []): ?>
    <div class="card muted">Ошибок не найдено<?= $f['q'] !== '' ? ' по запросу «' . h($f['q']) . '»' : '' ?>.</div>
<?php else: ?>
<table class="table">
    <tr>
        <th>№</th><th>Название</th><th>Проект</th><th>Приоритет</th>
        <th>Статус</th><th>Автор</th><th>Исполнитель</th><th>Комм.</th><th>Создана</th>
        <?php if ($hasActions): ?><th></th><?php endif; ?>
    </tr>
    <?php foreach ($bugs as $b): ?>
    <tr>
        <td><?= (int) $b['bug_id'] ?></td>
        <td><a href="bug_view.php?id=<?= (int) $b['bug_id'] ?>"><?= h($b['title']) ?></a></td>
        <td><?= h($b['project_code']) ?></td>
        <td class="is-badge"><?= priorityBadge($b['priority']) ?></td>
        <td class="is-badge"><?= statusBadge($b['status']) ?></td>
        <td><?= h($b['author_name']) ?></td>
        <td><?= h($b['assignee_name'] ?? '—') ?></td>
        <td><?= (int) $b['comments_count'] ?></td>
        <td><?= h(substr((string) $b['created_at'], 0, 10)) ?></td>
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

<?php if ($pages > 1): ?>
<nav class="pager">
    <?php if ($page > 1): ?><a href="<?= h($pageUrl($page - 1)) ?>">← Назад</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
        <?php elseif ($i === 1 || $i === $pages || abs($i - $page) <= 2): ?>
            <a href="<?= h($pageUrl($i)) ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?>
            <span class="dots">…</span>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?= h($pageUrl($page + 1)) ?>">Вперёд →</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php pageFooter();