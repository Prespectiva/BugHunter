<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

// Служебный скрипт: только консоль (в public/ его нет, по URL он недоступен)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Скрипт запускается только из консоли.');
}

try {
    $pdo = getPdo();

    $schemaFile = __DIR__ . '/../database/schema.sql';
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
                'INSERT INTO projects (code, name, description, owner_id, lead_id) VALUES (?, ?, ?, ?, ?)'
            );
            $project->execute(['BH',   'BugHunter',           'Система учёта дефектов', 4, 4]);
            $project->execute(['SITE', 'Сайт «Колледж.Банк»', 'Сайт обмена помощью',    4, 4]);

            $bug = $pdo->prepare(
                'INSERT INTO bugs (project_id, author_id, assignee_id, title, description, steps,
                                   expected_result, actual_result, priority, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $hist = $pdo->prepare(
                "INSERT INTO bug_history (bug_id, user_id, old_status, new_status, created_at)
                 VALUES (?, ?, 'New', ?, datetime('now'))"
            );

            $bug->execute([1, 2, 3,
                'Ошибка 500 при входе с пустым паролем',
                'При пустом пароле возникает ошибка HTTP 500.',
                "1. Открыть login.php\n2. Ввести логин tester@bh.local\n3. Нажать «Войти» с пустым паролем",
                'Сообщение валидации «Введите пароль».',
                'Uncaught PDOException / белый экран (500).',
                'Critical', 'New']);

            $bug->execute([1, 2, null,
                'Поиск тикетов не учитывает регистр',
                'Поиск по слову «Ошибка» не находит «ошибка».',
                "1. Открыть список ошибок\n2. Ввести «ошибка» в поиск",
                'Найдены записи в любом регистре.', 'Записи не найдены.',
                'Medium', 'New']);

            $bug->execute([2, 2, 3,
                'Не открывается карточка публикации',
                'При открытии публикации по ссылке страница пустая.', '', '', '',
                'High', 'Confirmed']);
            // Для статуса ≠ New фиксируем запись в истории, чтобы integrity_check
            // не считал такую ошибку «без истории».
            $hist->execute([(int) $pdo->lastInsertId(), 2, 'Confirmed']);

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