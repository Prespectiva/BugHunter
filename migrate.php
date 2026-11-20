<?php

declare(strict_types=1);

/**
 * Миграции базы BugHunter (повторный запуск ничего не ломает).
 *  1. projects: поля owner_id (автор проекта) и description.
 *  2. bugs: структура по ТЗ — author_id, description, expected_result, actual_result,
 *     4 уровня приоритета (Critical–Low) и статусы Open / In Progress / Closed.
 * Запуск: php migrate.php
 */

require __DIR__ . '/config/database.php';

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

    // --- 2. новая структура bugs ---
    // В SQLite нельзя изменить CHECK у существующего столбца, поэтому таблица пересоздаётся
    if (!in_array('author_id', columns($pdo, 'bugs'), true)) {
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
                    priority        TEXT    NOT NULL DEFAULT 'Medium'
                                    CHECK (priority IN ('Critical', 'High', 'Medium', 'Low')),
                    status          TEXT    NOT NULL DEFAULT 'Open'
                                    CHECK (status IN ('Open', 'In Progress', 'Closed')),
                    created_at      TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
                )");
            // Перенос данных: reporter_id -> author_id, severity Critical -> приоритет Critical,
            // старые статусы сводятся к трём новым
            $pdo->exec("
                INSERT INTO bugs_new (bug_id, project_id, author_id, assignee_id, title, steps,
                                      priority, status, created_at)
                SELECT bug_id, project_id, reporter_id, assignee_id, title, COALESCE(steps, ''),
                       CASE WHEN severity = 'Critical' THEN 'Critical' ELSE priority END,
                       CASE WHEN status = 'Closed' THEN 'Closed'
                            WHEN status IN ('In Progress', 'Fixed', 'Verified') THEN 'In Progress'
                            ELSE 'Open' END,
                       created_at
                FROM bugs");
            $pdo->exec('DROP TABLE bugs');
            $pdo->exec('ALTER TABLE bugs_new RENAME TO bugs');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_bugs_project ON bugs (project_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_bugs_author  ON bugs (author_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_bugs_status  ON bugs (status)');
            $pdo->commit();
            echo 'Таблица bugs переведена на новую структуру.' . PHP_EOL;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    echo 'Миграция выполнена.' . PHP_EOL;
} catch (Throwable $e) {
    echo 'Ошибка миграции: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
