<?php

declare(strict_types=1);

/**
 * Роутер для встроенного сервера PHP: php -S localhost:8000 router.php
 * Закрывает прямой доступ по URL к базе данных, конфигурации, схеме и логам.
 * (На Apache то же самое делают файлы .htaccess в папках data, config и database.)
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

$protectedDir  = (bool) preg_match('#(^|/)(data|config|database)(/|$)#i', $path);
$protectedFile = (bool) preg_match('#\.(db|sqlite|sqlite3|sql|log|md)$#i', $path);

if ($protectedDir || $protectedFile) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden';
    return true;
}

return false;   // остальные запросы обрабатывает встроенный сервер как обычно
