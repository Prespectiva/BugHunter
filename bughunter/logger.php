<?php

declare(strict_types=1);

/**
 * Лог критических ошибок: data/error.log (папка закрыта от прямого доступа по URL).
 */
function logError(Throwable|string $e): void
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $msg = $e instanceof Throwable
        ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : $e;
    $uri = $_SERVER['REQUEST_URI'] ?? 'cli';
    @file_put_contents($dir . '/error.log', '[' . date('Y-m-d H:i:s') . '] ' . $uri . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}
