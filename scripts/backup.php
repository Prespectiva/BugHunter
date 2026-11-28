<?php

declare(strict_types=1);

/**
 * Резервное копирование базы данных BugHunter (ПР №9).
 * Запуск: php scripts/backup.php [каталог_копий]
 * Копия кладётся в backups/ (вне web-root) с датой в имени, проверяется PRAGMA integrity_check,
 * старые копии удаляются (ротация: хранятся последние 10).
 */

require __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Скрипт запускается только из консоли.');
}

const KEEP_BACKUPS = 10;

try {
    if (!is_file(DB_PATH)) {
        throw new RuntimeException('Файл базы не найден: ' . DB_PATH . ' (сначала php scripts/init_db.php)');
    }
    $dir = rtrim($argv[1] ?? (__DIR__ . '/../backups'), '/\\');
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Не удалось создать каталог копий: {$dir}");
    }
    $target = $dir . '/bughunter_' . date('Ymd_His') . '.db';

    // VACUUM INTO делает согласованную копию даже при работающем приложении;
    // если версия SQLite её не поддерживает, копируем файл
    try {
        $pdo = getPdo();
        $pdo->exec('VACUUM INTO ' . $pdo->quote($target));
    } catch (Throwable $e) {
        if (!copy(DB_PATH, $target)) {
            throw new RuntimeException('Не удалось скопировать файл базы.');
        }
    }

    // Проверка целостности копии
    $check = new PDO('sqlite:' . $target, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $result = (string) $check->query('PRAGMA integrity_check')->fetchColumn();
    $users = (int) $check->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $bugs = (int) $check->query('SELECT COUNT(*) FROM bugs')->fetchColumn();
    $check = null;
    if ($result !== 'ok') {
        unlink($target);
        throw new RuntimeException("Копия не прошла проверку целостности: {$result}");
    }

    // Ротация старых копий
    $files = glob($dir . '/bughunter_*.db') ?: [];
    sort($files);
    foreach (array_slice($files, 0, max(0, count($files) - KEEP_BACKUPS)) as $old) {
        unlink($old);
        echo 'Удалена старая копия: ' . basename($old) . PHP_EOL;
    }

    echo 'Копия создана: ' . realpath($target) . PHP_EOL;
    echo "Проверка целостности: ok; пользователей: {$users}, ошибок: {$bugs}" . PHP_EOL;
} catch (Throwable $e) {
    echo 'Ошибка резервного копирования: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
