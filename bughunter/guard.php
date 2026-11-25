<?php

declare(strict_types=1);

/**
 * Служебные скрипты (init_db, migrate, check, seed_demo, integrity_check) нельзя открывать
 * анонимно через браузер: разрешены консоль (CLI) или вошедший администратор.
 */
function requireCliOrAdmin(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    require_once __DIR__ . '/auth.php';

    $user = null;
    try {
        $user = currentUser();
    } catch (Throwable $e) {
        // базы или таблицы ещё нет — считаем, что пользователь не вошёл
    }
    if ($user === null || $user['role'] !== 'admin') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('403: служебный скрипт доступен только из консоли или администратору.');
    }
}
