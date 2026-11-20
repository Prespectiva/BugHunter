<?php

declare(strict_types=1);

require __DIR__ . '/config/database.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

function pad(string $text, int $width): string
{
    $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    return $text . str_repeat(' ', max(0, $width - $len));
}

try {
    $pdo = getPdo();

    echo 'foreign_keys = ' . $pdo->query('PRAGMA foreign_keys')->fetchColumn() . PHP_EOL . PHP_EOL;

    $sql = 'SELECT p.code || \'-\' || b.bug_id AS bug,
                   b.title, b.priority, b.status,
                   r.full_name AS author,
                   COALESCE(a.full_name, \'—\') AS assignee
            FROM bugs b
            JOIN projects p ON p.project_id = b.project_id
            JOIN users r    ON r.user_id = b.author_id
            LEFT JOIN users a ON a.user_id = b.assignee_id
            WHERE b.status <> :closed
            ORDER BY b.bug_id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':closed' => 'Closed']);
    $rows = $stmt->fetchAll();

    if ($rows === []) {
        echo 'Открытых багов нет (запустите init_db.php).' . PHP_EOL;
    } else {
        foreach ($rows as $row) {
            echo pad($row['bug'], 7) . ' | ' . pad($row['title'], 38) . ' | '
                . pad($row['priority'], 8) . ' | ' . pad($row['status'], 11) . ' | '
                . $row['assignee'] . PHP_EOL;
        }
    }

    echo PHP_EOL . 'Проверка ограничений:' . PHP_EOL;

    $hasUser2    = (bool) $pdo->query('SELECT 1 FROM users    WHERE user_id    = 2')->fetchColumn();
    $hasProject1 = (bool) $pdo->query('SELECT 1 FROM projects WHERE project_id = 1')->fetchColumn();

    $tests = [];

    $tests['FK: несуществующий проект'] = $hasUser2
        ? "INSERT INTO bugs (project_id, author_id, title, priority) VALUES (99, 2, 'Тест', 'Low')"
        : null;

    $tests['CHECK: неверная роль'] =
        "INSERT INTO users (full_name, email, password_hash, role) VALUES ('Тест', 'x@bh.local', 'h', 'boss')";

    $tests['CHECK: неверный приоритет'] = ($hasProject1 && $hasUser2)
        ? "INSERT INTO bugs (project_id, author_id, title, priority) VALUES (1, 2, 'Тест', 'Urgent')"
        : null;

    $tests['UNIQUE: повторный e-mail'] =
        "INSERT INTO users (full_name, email, password_hash, role) VALUES ('Тест', 'tester@bh.local', 'h', 'tester')";

    $tests['NOT NULL: дефект без заголовка'] = ($hasProject1 && $hasUser2)
        ? "INSERT INTO bugs (project_id, author_id, title, priority) VALUES (1, 2, NULL, 'Low')"
        : null;

    foreach ($tests as $name => $sql) {
        if ($sql === null) {
            echo "[SKIP] {$name}: нет нужных записей в базе (сначала init_db.php)" . PHP_EOL;
            continue;
        }
        try {
            $pdo->exec($sql);
            echo "[ОШИБКА] {$name}: запись добавлена, хотя должна была упасть" . PHP_EOL;
        } catch (PDOException $e) {
            echo "[OK] {$name}: " . $e->getMessage() . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    echo 'Ошибка: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}