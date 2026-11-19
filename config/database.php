<?php

declare(strict_types=1);

const DB_PATH = __DIR__ . '/../data/bughunter.db';

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
    }

    return $pdo;
}