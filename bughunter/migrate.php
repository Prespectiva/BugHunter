<?php

declare(strict_types=1);

/**
 * Миграции базы BugHunter (повторный запуск ничего не ломает).
 *  1. projects: поля owner_id (автор проекта) и description.
 *  2. bugs: структура по ТЗ — author_id, description, expected_result, actual_result,
 *     4 уровня приоритета и 7 статусов ПР №6 (New, Review, Confirmed, In Progress, Fixed, Closed, Rejected).
 *     Старые статусы переносятся: Open -> New, Verified -> Closed.
 *  3. таблицы comments и bug_history (схема из database/schema.sql, CREATE ... IF NOT EXISTS).
 * Запуск: php migrate.php
 */

require __DIR__ . '/config/database.php';
require __DIR__ . '/guard.php';
requireCliOrAdmin();

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

function columns(PDO $pdo, string $table): array
{
    return array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
}

try {
    $pdo = getPdo();

    // --- 1. projects.owner_id и projects.description ---
    $cols = columns($pdo, 'projects');
    if (!in_array('owner_id', $cols, true)) {
        $pdo->exec('ALTER TABLE projects ADD COLUMN owner_id INTEGER REFERENCES users (user_id)');
        $pdo->exec('UPDATE projects SET owner_id = lead_id WHERE owner_id IS NULL');
        echo 'Добавлено поле projects.owner_id.' . PHP_EOL;
    }
    if (!in_array('description', $cols, true)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN description TEXT NOT NULL DEFAULT ''");
        echo 'Добавлено поле projects.description.' . PHP_EOL;
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_owner ON projects (owner_id)');

    // --- 2. структура bugs ---
    // В SQLite нельзя изменить CHECK у существующего столбца, поэтому таблица пересоздаётся
    $bugCols = columns($pdo, 'bugs');
    $bugsSql = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'bugs'")->fetchColumn();
    $needRebuild = !in_array('author_id', $bugCols, true) || !str_contains($bugsSql, "'Review'");

    if ($needRebuild) {
        $has = static fn (string $c): bool => in_array($c, $bugCols, true);
        $text = static fn (string $c): string => $has($c) ? "COALESCE({$c}, '')" : "''";
        $author = $has('author_id') ? 'author_id' : 'reporter_id';
        $prio = $has('severity') ? "CASE WHEN severity = 'Critical' THEN 'Critical' ELSE priority END" : 'priority';

        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->beginTransaction();
        try {
            $pdo->exec("
                CREATE TABLE bugs_new (
                    bug_id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    project_id      INTEGER NOT NULL REFERENCES projects (project_id),
                    author_id       INTEGER NOT NULL REFERENCES users (user_id),
                    assignee_id     INTEGER REFERENCES users (user_id) ON DELETE SET NULL,
                    title           TEXT    NOT NULL CHECK (length(trim(title)) > 0),
                    description     TEXT    NOT NULL DEFAULT '',
                    steps           TEXT    NOT NULL DEFAULT '',
                    expected_result TEXT    NOT NULL DEFAULT '',
                    actual_result   TEXT    NOT NULL DEFAULT '',
                    priority        TEXT    NOT NULL DEFAULT 'Medium' CHECK (priority IN ('Critical', 'High', 'Medium', 'Low')),
                    status          TEXT    NOT NULL DEFAULT 'New' CHECK (status IN ('New', 'Review', 'Confirmed', 'In Progress', 'Fixed', 'Closed', 'Rejected')),
                    created_at      TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
                )");
            $pdo->exec("
                INSERT INTO bugs_new (bug_id, project_id, author_id, assignee_id, title, description, steps,
                                      expected_result, actual_result, priority, status, created_at)
                SELECT bug_id, project_id, {$author}, assignee_id, title,
                       {$text('description')}, {$text('steps')}, {$text('expected_result')}, {$text('actual_result')},
                       CASE WHEN ({$prio}) IN ('Critical', 'High', 'Medium', 'Low') THEN ({$prio}) ELSE 'Medium' END,
                       CASE WHEN status IN ('New', 'Review', 'Confirmed', 'In Progress', 'Fixed', 'Closed', 'Rejected') THEN status
                            WHEN status = 'Verified' THEN 'Closed'
                            ELSE 'New' END,
                       created_at
                FROM bugs");
            $pdo->exec('DROP TABLE bugs');
            $pdo->exec('ALTER TABLE bugs_new RENAME TO bugs');
            $pdo->commit();
            echo 'Таблица bugs переведена на новую структуру (статусы ПР №6).' . PHP_EOL;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    // --- 3. comments, bug_history и индексы ---
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!is_file($schemaFile)) {
        throw new RuntimeException("Не найден файл схемы: {$schemaFile}");
    }
    $pdo->exec((string) file_get_contents($schemaFile));
    echo 'Таблицы comments и bug_history проверены.' . PHP_EOL;

    echo 'Миграция выполнена.' . PHP_EOL;
} catch (Throwable $e) {
    echo 'Ошибка миграции: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
