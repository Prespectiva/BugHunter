<?php

declare(strict_types=1);

/**
 * Проверка целостности базы данных BugHunter (ПР №8).
 * Запуск: php integrity_check.php (или в браузере под администратором).
 */

require __DIR__ . '/config/database.php';
require __DIR__ . '/guard.php';
requireCliOrAdmin();

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    $pdo = getPdo();
    $failed = 0;

    $report = static function (string $name, array $problems) use (&$failed): void {
        if ($problems === []) {
            echo "[OK]   {$name}" . PHP_EOL;
        } else {
            $failed++;
            echo "[FAIL] {$name}: " . implode('; ', $problems) . PHP_EOL;
        }
    };

    echo 'Проверка целостности BugHunter' . PHP_EOL . str_repeat('-', 40) . PHP_EOL;

    // 1. Режим SQLite
    $report('PRAGMA foreign_keys = ON', (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1 ? [] : ['внешние ключи выключены']);
    $ic = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
    $report('PRAGMA integrity_check', $ic === 'ok' ? [] : [$ic]);
    $fk = $pdo->query('PRAGMA foreign_key_check')->fetchAll();
    $report('PRAGMA foreign_key_check', $fk === [] ? [] : [count($fk) . ' нарушений внешних ключей']);

    // 2. Прикладные проверки: каждый запрос должен вернуть 0 строк
    $checks = [
        'Ошибки без существующего проекта' =>
            'SELECT b.bug_id FROM bugs b LEFT JOIN projects p ON p.project_id = b.project_id WHERE p.project_id IS NULL',
        'Ошибки без существующего автора' =>
            'SELECT b.bug_id FROM bugs b LEFT JOIN users u ON u.user_id = b.author_id WHERE u.user_id IS NULL',
        'Исполнитель не существует' =>
            'SELECT b.bug_id FROM bugs b LEFT JOIN users u ON u.user_id = b.assignee_id WHERE b.assignee_id IS NOT NULL AND u.user_id IS NULL',
        'Недопустимые статусы и приоритеты' =>
            "SELECT bug_id FROM bugs WHERE status NOT IN ('New','Review','Confirmed','In Progress','Fixed','Closed','Rejected')
                OR priority NOT IN ('Critical','High','Medium','Low')",
        'Пустые названия ошибок' =>
            'SELECT bug_id FROM bugs WHERE length(trim(title)) = 0',
        'Дубликаты e-mail пользователей' =>
            'SELECT email FROM users GROUP BY email HAVING COUNT(*) > 1',
        'Недопустимые роли пользователей' =>
            "SELECT user_id FROM users WHERE role NOT IN ('customer','tester','developer','admin')",
        'Проекты с несуществующим владельцем' =>
            'SELECT p.project_id FROM projects p LEFT JOIN users u ON u.user_id = p.owner_id WHERE p.owner_id IS NOT NULL AND u.user_id IS NULL',
        'Комментарии без ошибки или автора' =>
            'SELECT c.id FROM comments c LEFT JOIN bugs b ON b.bug_id = c.bug_id LEFT JOIN users u ON u.user_id = c.user_id
             WHERE b.bug_id IS NULL OR u.user_id IS NULL',
        'Записи истории без ошибки или автора' =>
            'SELECT h.id FROM bug_history h LEFT JOIN bugs b ON b.bug_id = h.bug_id LEFT JOIN users u ON u.user_id = h.user_id
             WHERE b.bug_id IS NULL OR u.user_id IS NULL',
        'Статус ошибки не совпадает с последней записью истории' =>
            'SELECT b.bug_id FROM bugs b
             WHERE EXISTS (SELECT 1 FROM bug_history h WHERE h.bug_id = b.bug_id)
               AND (SELECT h2.new_status FROM bug_history h2 WHERE h2.bug_id = b.bug_id ORDER BY h2.id DESC LIMIT 1) <> b.status',
    ];
    foreach ($checks as $name => $sql) {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $report($name, $rows === [] ? [] : ['найдено ' . count($rows) . ' (' . implode(', ', array_slice($rows, 0, 5)) . ')']);
    }

    echo str_repeat('-', 40) . PHP_EOL;
    echo $failed === 0 ? 'Итог: нарушений не найдено.' . PHP_EOL : "Итог: проверок с нарушениями — {$failed}." . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
} catch (Throwable $e) {
    echo 'Ошибка: ' . $e->getMessage() . PHP_EOL;
    exit(2);
}
