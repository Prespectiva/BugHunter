<?php

declare(strict_types=1);

require __DIR__ . '/config/database.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    $pdo = getPdo();

    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!is_file($schemaFile)) {
        throw new RuntimeException("Не найден файл схемы: {$schemaFile}");
    }
    $pdo->exec(file_get_contents($schemaFile));

    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    if ($count === 0) {
        $pdo->beginTransaction();
        try {
            $user = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
            );
            $hash = password_hash('Password123', PASSWORD_DEFAULT);
            $users = [
                ['Петров Олег',    'customer@bh.local', 'customer'],
                ['Смирнова Анна',  'tester@bh.local',   'tester'],
                ['Иванов Алексей', 'dev@bh.local',      'developer'],
                ['Кузнецов Игорь', 'admin@bh.local',    'admin'],
            ];
            foreach ($users as [$name, $email, $role]) {
                $user->execute([$name, $email, $hash, $role]);
            }

            $project = $pdo->prepare(
                'INSERT INTO projects (code, name, lead_id) VALUES (?, ?, ?)'
            );
            $project->execute(['BH',   'BugHunter',            4]);
            $project->execute(['SITE', 'Сайт «Колледж.Банк»',  4]);

            $bug = $pdo->prepare(
                'INSERT INTO bugs (project_id, reporter_id, assignee_id, title, steps, severity, priority, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $bug->execute([1, 2, 3,
                'Ошибка 500 при входе с пустым паролем',
                '1. Открыть страницу входа; 2. Ввести логин tester@bh.local; 3. Нажать «Войти» с пустым паролем',
                'Critical', 'High', 'Assigned']);
            $bug->execute([1, 2, null,
                'Поиск тикетов не учитывает регистр',
                null, 'Minor', 'Medium', 'New']);
            $bug->execute([2, 2, 3,
                'Не открывается карточка публикации',
                null, 'Major', 'High', 'In Progress']);

            $pdo->commit();
            echo 'Добавлены тестовые данные.' . PHP_EOL;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } else {
        echo "База уже заполнена ({$count} пользователей) — сиды пропущены." . PHP_EOL;
    }

    echo 'База данных создана: ' . realpath(DB_PATH) . PHP_EOL;
} catch (Throwable $e) {
    echo 'Ошибка: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}