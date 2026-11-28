<?php

declare(strict_types=1);

const DB_PATH = __DIR__ . '/../storage/bughunter.db';

function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Не удалось создать папку: {$dir}");
        }

        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');

        // LIKE в SQLite нечувствителен к регистру только для латиницы, поэтому для поиска
        // по кириллице регистрируем функцию mb_lower() (приведение к нижнему регистру в UTF-8).
        // NULL пробрасываем как NULL, иначе LIKE по NULL-колонке вернёт ложное совпадение с ''.
        $pdo->sqliteCreateFunction(
            'mb_lower',
            static fn ($s) => $s === null ? null : mb_strtolower((string) $s, 'UTF-8'),
            1
        );
    }

    return $pdo;
}