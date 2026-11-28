<?php

declare(strict_types=1);

require __DIR__ . '/../app/bugs_lib.php';

$me = requireLogin();   // доступ только авторизованным
$pdo = getPdo();

try {
    // 1–6. Ключевые показатели (агрегатные функции SQL)
    $total  = (int) $pdo->query('SELECT COUNT(*) FROM bugs')->fetchColumn();
    $open   = (int) $pdo->query("SELECT COUNT(*) FROM bugs WHERE status NOT IN ('Closed', 'Rejected')")->fetchColumn();
    $crit   = (int) $pdo->query("SELECT COUNT(*) FROM bugs WHERE priority = 'Critical' AND status NOT IN ('Closed', 'Rejected')")->fetchColumn();
    $closed = (int) $pdo->query("SELECT COUNT(*) FROM bugs WHERE status = 'Closed'")->fetchColumn();
    $closedPercent = $total > 0 ? round($closed * 100 / $total, 1) : 0.0;
    $avgComments = (float) $pdo->query(
        'SELECT AVG(c_cnt) AS avg_comments FROM (
             SELECT b.bug_id, COUNT(c.id) AS c_cnt
             FROM bugs b LEFT JOIN comments c ON c.bug_id = b.bug_id
             GROUP BY b.bug_id
         )'
    )->fetchColumn();

    // Распределение по статусам и приоритетам (GROUP BY)
    $distribution = static function (PDO $pdo, string $column, array $all): array {
        $rows = $pdo->query("SELECT {$column} AS k, COUNT(*) AS cnt FROM bugs GROUP BY {$column} ORDER BY cnt DESC")->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['k']] = (int) $r['cnt'];
        }
        foreach ($all as $k) {
            $map[$k] = $map[$k] ?? 0;
        }
        return $map;
    };
    $byStatus   = $distribution($pdo, 'status', STATUSES);
    $byPriority = $distribution($pdo, 'priority', array_keys(PRIORITIES));

    // Статистика по проектам: LEFT JOIN + CASE WHEN (проекты без ошибок тоже попадут в таблицу)
    $projects = $pdo->query(
        "SELECT p.project_id, p.code, p.name,
                COUNT(b.bug_id) AS total,
                SUM(CASE WHEN b.status = 'New'         THEN 1 ELSE 0 END) AS new_cnt,
                SUM(CASE WHEN b.status = 'Review'      THEN 1 ELSE 0 END) AS review_cnt,
                SUM(CASE WHEN b.status = 'Confirmed'   THEN 1 ELSE 0 END) AS confirmed_cnt,
                SUM(CASE WHEN b.status = 'In Progress' THEN 1 ELSE 0 END) AS prog_cnt,
                SUM(CASE WHEN b.status = 'Fixed'       THEN 1 ELSE 0 END) AS fixed_cnt,
                SUM(CASE WHEN b.status = 'Closed'      THEN 1 ELSE 0 END) AS closed_cnt,
                SUM(CASE WHEN b.status = 'Rejected'    THEN 1 ELSE 0 END) AS rejected_cnt
         FROM projects p
         LEFT JOIN bugs b ON b.project_id = p.project_id
         GROUP BY p.project_id
         ORDER BY total DESC, p.code"
    )->fetchAll();
} catch (PDOException $e) {
    logError($e);
    errorPage(500, 'База данных не инициализирована. Выполните php scripts/init_db.php и обновите страницу.', 'login.php', 'На главную');
}

$top = ($projects !== [] && (int) $projects[0]['total'] > 0) ? $projects[0] : null;

$cards = [
    ['Всего ошибок',            (string) $total,                                   ''],
    ['Открытые',                (string) $open,                                    'кроме Closed и Rejected'],
    ['Critical (открытые)',     (string) $crit,                                    $crit > 0 ? 'требуют внимания' : ''],
    ['Закрытые',                (string) $closed,                                  'статус Closed'],
    ['Доля закрытых',           number_format($closedPercent, 1, ',', ' ') . ' %', 'Closed от всех ошибок'],
    ['Комментариев на ошибку',  number_format($avgComments, 1, ',', ' '),          'среднее значение'],
];

/** Полоса распределения (чистый CSS, без JavaScript). */
function barRows(array $data): void
{
    $max = max(1, max($data ?: [0]));
    foreach ($data as $name => $cnt) {
        $w = (int) round($cnt * 100 / $max);
        echo '<div class="bar-row"><span class="bar-name">' . h((string) $name) . '</span>'
           . '<span class="bar-track"><span class="bar-fill" style="width:' . $w . '%"></span></span>'
           . '<span class="bar-cnt">' . (int) $cnt . '</span></div>';
    }
}

pageHeader('Dashboard');
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="muted">Сводка по ошибкам и проектам</div>
    </div>
    <a class="btn btn-light" href="bugs.php">К списку ошибок</a>
</div>

<div class="stats">
    <?php foreach ($cards as [$title, $value, $note]): ?>
        <div class="stat">
            <div class="stat-title"><?= h($title) ?></div>
            <div class="stat-value"><?= h($value) ?></div>
            <div class="stat-note"><?= h($note) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="two-col">
    <div class="card">
        <h2 style="margin-top:0">По статусам</h2>
        <?php barRows($byStatus); ?>
    </div>
    <div class="card">
        <h2 style="margin-top:0">По приоритетам</h2>
        <?php barRows($byPriority); ?>
    </div>
</div>

<h2>По проектам</h2>
<?php if ($top !== null): ?>
    <p class="muted">Больше всего ошибок в проекте <b><?= h($top['code'] . ' — ' . $top['name']) ?></b>: <?= (int) $top['total'] ?>.</p>
<?php endif; ?>
<?php if ($projects === []): ?>
    <div class="card muted">Проектов пока нет.</div>
<?php else: ?>
<table class="table">
    <tr>
        <th>Проект</th><th>Всего</th><th>New</th><th>Review</th><th>Confirmed</th>
        <th>In Progress</th><th>Fixed</th><th>Closed</th><th>Rejected</th>
    </tr>
    <?php foreach ($projects as $p): ?>
    <tr>
        <td><a href="bugs.php?project=<?= (int) $p['project_id'] ?>"><?= h($p['code'] . ' — ' . $p['name']) ?></a></td>
        <td><b><?= (int) $p['total'] ?></b></td>
        <td><?= (int) $p['new_cnt'] ?></td>
        <td><?= (int) $p['review_cnt'] ?></td>
        <td><?= (int) $p['confirmed_cnt'] ?></td>
        <td><?= (int) $p['prog_cnt'] ?></td>
        <td><?= (int) $p['fixed_cnt'] ?></td>
        <td><?= (int) $p['closed_cnt'] ?></td>
        <td><?= (int) $p['rejected_cnt'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
<?php pageFooter();