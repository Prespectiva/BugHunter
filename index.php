<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$me = requireLogin();   // закрытая страница: без входа — редирект на login.php

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = getPdo();

    $users = $pdo->query(
        'SELECT user_id, full_name, email, role, created_at FROM users ORDER BY user_id'
    )->fetchAll();

    $projects = $pdo->query(
        'SELECT p.project_id, p.code, p.name, u.full_name AS lead, p.created_at
         FROM projects p
         LEFT JOIN users u ON u.user_id = p.lead_id
         ORDER BY p.project_id'
    )->fetchAll();

    $bugs = $pdo->query(
        'SELECT b.bug_id, p.code AS project, b.title, b.priority, b.status,
                r.full_name AS author, COALESCE(a.full_name, \'—\') AS assignee, b.created_at
         FROM bugs b
         JOIN projects p ON p.project_id = b.project_id
         JOIN users r    ON r.user_id = b.author_id
         LEFT JOIN users a ON a.user_id = b.assignee_id
         ORDER BY b.bug_id'
    )->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Ошибка</h1><p>' . h($e->getMessage()) . '</p>';
    echo '<p>Запустите <a href="init_db.php">init_db.php</a>.</p>';
    exit;
}

function renderTable(string $title, array $rows): void
{
    echo '<h2>' . h($title) . '</h2>';
    if ($rows === []) {
        echo '<p><em>Нет данных</em></p>';
        return;
    }
    echo '<table class="table">';
    echo '<tr>';
    foreach (array_keys($rows[0]) as $col) {
        echo '<th>' . h($col) . '</th>';
    }
    echo '</tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $val) {
            echo '<td>' . h($val === null ? '' : (string) $val) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
}
?>
<?php pageHeader('Таблицы'); ?>
    <h1>BugHunter</h1>
    <p class="links">
        <a href="index.php">Обновить</a>
        <a href="init_db.php">init_db.php</a>
        <a href="check.php">check.php</a>
    </p>

    <?php
    renderTable('users', $users);
    renderTable('projects', $projects);
    renderTable('bugs', $bugs);
    ?>
<?php pageFooter(); ?>