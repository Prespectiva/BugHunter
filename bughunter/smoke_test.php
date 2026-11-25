<?php

declare(strict_types=1);

/**
 * Автопроверка страниц BugHunter по HTTP (ПР №8).
 * Запуск: сначала php -S localhost:8000 router.php, затем в другом окне:
 *         php smoke_test.php http://localhost:8000
 */

if (PHP_SAPI !== 'cli') {
    exit('Скрипт запускается только из консоли.');
}

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');

/** @return array{0:int,1:string} код ответа и заголовок Location (без перехода по редиректу) */
function request(string $url): array
{
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 5]]);
    @file_get_contents($url, false, $ctx);
    $code = 0;
    $loc = '';
    foreach ($http_response_header ?? [] as $i => $line) {
        if ($i === 0 && preg_match('#\s(\d{3})\s#', $line, $m)) {
            $code = (int) $m[1];
        }
        if (stripos($line, 'Location:') === 0) {
            $loc = trim(substr($line, 9));
        }
    }
    return [$code, $loc];
}

// [путь, ожидаемый код, подстрока в Location или '']
$cases = [
    ['/login.php',            200, ''],
    ['/register.php',         200, ''],
    ['/bugs.php',             302, 'login.php'],
    ['/bug_view.php?id=1',    302, 'login.php'],
    ['/bug_create.php',       302, 'login.php'],
    ['/projects.php',         302, 'login.php'],
    ['/project_view.php?id=1', 302, 'login.php'],
    ['/dashboard.php',        302, 'login.php'],
    ['/index.php',            302, 'login.php'],
    ['/init_db.php',          403, ''],
    ['/migrate.php',          403, ''],
    ['/check.php',            403, ''],
    ['/integrity_check.php',  403, ''],
    ['/data/bughunter.db',    403, ''],
    ['/config/database.php',  403, ''],
    ['/database/schema.sql',  403, ''],
];

$failed = 0;
foreach ($cases as [$path, $expCode, $expLoc]) {
    [$code, $loc] = request($base . $path);
    $ok = $code === $expCode && ($expLoc === '' || str_contains($loc, $expLoc));
    if (!$ok) {
        $failed++;
    }
    printf("[%s] %-26s ожидалось %d%s, получено %d%s\n",
        $ok ? 'OK  ' : 'FAIL', $path, $expCode, $expLoc !== '' ? " → {$expLoc}" : '', $code, $loc !== '' ? " → {$loc}" : '');
}

echo PHP_EOL . ($failed === 0 ? 'Все страницы ведут себя как ожидается.' : "Проверок не пройдено: {$failed}. Сервер запущен с router.php?") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
