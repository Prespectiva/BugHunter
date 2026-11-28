<?php

declare(strict_types=1);

/**
 * Демо-данные для проверки поиска, пагинации и Dashboard (ПР №7).
 * Добавляет 25 ошибок и несколько комментариев, только если в базе меньше 15 ошибок.
 * Запуск: php seed_demo.php
 */

require __DIR__ . '/../config/database.php';

// Служебный скрипт: только консоль (в public/ его нет, по URL он недоступен)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Скрипт запускается только из консоли.');
}

try {
    $pdo = getPdo();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM bugs')->fetchColumn();
    if ($count >= 15) {
        echo "В базе уже {$count} ошибок — демо-данные не добавлены." . PHP_EOL;
        exit(0);
    }

    $projectIds = array_map('intval', $pdo->query('SELECT project_id FROM projects ORDER BY project_id')->fetchAll(PDO::FETCH_COLUMN));
    $authorIds  = array_map('intval', $pdo->query("SELECT user_id FROM users WHERE role IN ('tester', 'admin') ORDER BY user_id")->fetchAll(PDO::FETCH_COLUMN));
    $devIds     = array_map('intval', $pdo->query("SELECT user_id FROM users WHERE role IN ('developer', 'admin') ORDER BY user_id")->fetchAll(PDO::FETCH_COLUMN));
    if (!$projectIds || !$authorIds || !$devIds) {
        throw new RuntimeException('Сначала запустите init_db.php.');
    }

    $titles = [
        'Не сохраняется форма регистрации', 'Ошибка 404 на странице профиля', 'Кнопка «Войти» не реагирует на Enter',
        'Неверно считается итоговая сумма', 'Поиск не учитывает регистр', 'Белый экран при загрузке аватара',
        'Долгая загрузка списка проектов', 'Сбой при экспорте отчёта', 'Некорректная дата в карточке ошибки',
        'Не приходит письмо подтверждения', 'Пагинация показывает лишнюю страницу', 'Ошибка валидации e-mail с плюсом',
        'Выпадающий список не закрывается', 'Падает приложение при пустом пароле', 'Кириллица в имени файла ломает загрузку',
        'Неправильный порядок сортировки', 'Не обновляется счётчик комментариев', 'Дублируются записи в истории',
        'Двойная отправка формы создаёт копии', 'Текст обрезается на узком экране', 'Нет сообщения об успешном сохранении',
        'Фильтр по статусу сбрасывается', 'Ошибка подключения к базе данных', 'Тёмная тема: нечитаемый текст', 'Опечатка в заголовке страницы',
    ];
    $priorities = ['Critical', 'High', 'Medium', 'Low'];
    $statuses   = ['New', 'Review', 'Confirmed', 'In Progress', 'Fixed', 'Closed', 'Rejected'];

    $pdo->beginTransaction();
    $ins = $pdo->prepare(
        "INSERT INTO bugs (project_id, author_id, assignee_id, title, description, steps, expected_result, actual_result,
                           priority, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', ?))"
    );
    $hist = $pdo->prepare(
        "INSERT INTO bug_history (bug_id, user_id, old_status, new_status, created_at)
         VALUES (?, ?, 'New', ?, datetime('now'))"
    );

    foreach ($titles as $i => $title) {
        $status = $statuses[($i * 3) % 7];
        $ins->execute([
            $projectIds[$i % count($projectIds)],
            $authorIds[$i % count($authorIds)],
            $i % 4 === 0 ? null : $devIds[$i % count($devIds)],
            $title,
            'Демо-описание: ' . mb_strtolower($title) . '.',
            "1. Открыть страницу\n2. Выполнить действие\n3. Проверить результат",
            'Система работает штатно.',
            'Наблюдается дефект.',
            $priorities[$i % 4],
            $status,
            '-' . ($i * 2 + 1) . ' days',
        ]);

        // Для статусов, отличных от New, создаём запись в истории,
        // чтобы integrity_check.php не считал такие ошибки «без истории».
        if ($status !== 'New') {
            $hist->execute([(int) $pdo->lastInsertId(), $authorIds[0], $status]);
        }
    }

    $bugIds = array_map('intval', $pdo->query('SELECT bug_id FROM bugs ORDER BY bug_id DESC LIMIT 10')->fetchAll(PDO::FETCH_COLUMN));
    $com = $pdo->prepare("INSERT INTO comments (bug_id, user_id, text, created_at) VALUES (?, ?, ?, datetime('now'))");
    foreach ($bugIds as $k => $bid) {
        for ($j = 0; $j <= $k % 3; $j++) {
            $com->execute([$bid, $authorIds[0], 'Демо-комментарий №' . ($j + 1)]);
        }
    }
    $pdo->commit();

    echo 'Добавлено 25 демо-ошибок и комментарии.' . PHP_EOL;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'Ошибка: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}