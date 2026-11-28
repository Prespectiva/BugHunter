<?php

declare(strict_types=1);

/**
 * Восстановление базы данных BugHunter из резервной копии (ПР №9).
 * Запуск: php scripts/restore.php backups/bughunter_YYYYMMDD_HHMMSS.db
 * Перед заменой текущая база сохраняется как before_restore_*.db. Остановите сервер перед запуском.
 */

require __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Скрипт запускается только из консоли.');
}

try {
    $source = $argv[1] ?? '';
    if ($source === '' || !is_file($source)) {
        throw new RuntimeException('Укажите существующий файл копии: php scripts/restore.php backups/bughunter_....db');
    }

    // 1. Проверка копии до замены
    $check = new PDO('sqlite:' . $source, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $result = (string) $check->query('PRAGMA integrity_check')->fetchColumn();
    $tables = $check->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    $check = null;
    if ($result !== 'ok') {
        throw new RuntimeException("Копия повреждена: {$result}");
    }
    foreach (['users', 'projects', 'bugs', 'comments', 'bug_history'] as $t) {
        if (!in_array($t, $tables, true)) {
            throw new RuntimeException("В копии нет таблицы {$t}: это не база BugHunter.");
        }
    }

    // 2. Страховочная копия текущей базы
    $dir = dirname(DB_PATH);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Не удалось создать каталог: {$dir}");
    }
    if (is_file(DB_PATH)) {
        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }
        $safety = $backupDir . '/before_restore_' . date('Ymd_His') . '.db';
        if (!copy(DB_PATH, $safety)) {
            throw new RuntimeException('Не удалось сохранить текущую базу перед восстановлением.');
        }
        echo 'Текущая база сохранена: ' . basename($safety) . PHP_EOL;
    }

    // 3. Замена файла (через временный файл)
    $tmp = DB_PATH . '.restore';
    if (!copy($source, $tmp) || !rename($tmp, DB_PATH)) {
        @unlink($tmp);
        throw new RuntimeException('Не удалось заменить файл базы.');
    }
    echo 'База восстановлена из ' . basename($source) . PHP_EOL;
    echo 'Проверьте результат: php scripts/integrity_check.php' . PHP_EOL;
} catch (Throwable $e) {
    echo 'Ошибка восстановления: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
