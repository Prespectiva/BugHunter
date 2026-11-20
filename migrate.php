<?php

declare(strict_types=1);

/**
 * Миграция: добавление поля owner_id (автор проекта) в таблицу projects.
 * Запуск: php migrate.php (повторный запуск ничего не ломает).
 */

require __DIR__ . '/config/database.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    $pdo = getPdo();

    $columns = array_column($pdo->query('PRAGMA table_info(projects)')->fetchAll(), 'name');

    if (!in_array('owner_id', $columns, true)) {
        $pdo->exec('ALTER TABLE projects ADD COLUMN owner_id INTEGER REFERENCES users (user_id)');
        // Для существующих проектов автором считаем руководителя проекта
        $pdo->exec('UPDATE projects SET owner_id = lead_id WHERE owner_id IS NULL');
        echo 'Добавлено поле projects.owner_id.' . PHP_EOL;
    } else {
        echo 'Поле projects.owner_id уже есть.' . PHP_EOL;
    }

    if (!in_array('description', $columns, true)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN description TEXT NOT NULL DEFAULT ''");
        echo 'Добавлено поле projects.description.' . PHP_EOL;
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_owner ON projects (owner_id)');

    echo 'Миграция выполнена.' . PHP_EOL;
} catch (Throwable $e) {
    echo 'Ошибка миграции: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
