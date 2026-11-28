<?php

declare(strict_types=1);

/**
 * Сессии и авторизация BugHunter.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly'  => true,   // cookie сессии недоступна из JavaScript
        'cookie_samesite'  => 'Lax',
        'use_strict_mode'  => true,   // сервер не принимает чужой, заранее заданный ID сессии
    ]);
}

// Защитные заголовки ответа
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}

// Необработанные исключения: подробности пишем в storage/error.log, пользователю — общий текст
set_exception_handler(static function (Throwable $e): void {
    logError($e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Ошибка 500</title>'
       . '<p>Внутренняя ошибка сервера. Попробуйте позже.</p>';
});

/** Экранирование вывода (защита от XSS). */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Перенаправление и завершение скрипта. */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Текущий пользователь из сессии или null. */
function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = getPdo()->prepare('SELECT user_id, full_name, email, role FROM users WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

/** Защита закрытой страницы: без входа — редирект на login.php. */
function requireLogin(): array
{
    $user = currentUser();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

/** CSRF-токен для форм. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function checkCsrf(): bool
{
    $token = (string) ($_SESSION['csrf'] ?? '');
    // Пустой токен недопустим: иначе '' === '' пропустит запрос без токена в сессии
    return $token !== '' && isset($_POST['csrf']) && hash_equals($token, (string) $_POST['csrf']);
}

/** Валидация данных регистрации. Возвращает список ошибок. */
function validateRegistration(string $name, string $email, string $password, string $confirm, string $role): array
{
    $errors = [];
    if (mb_strlen($name) < 2) {
        $errors[] = 'Укажите имя (не короче 2 символов).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный e-mail.';
    }
    if (strlen($password) < 8 || !preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Пароль: минимум 8 символов, буквы и цифры.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Пароли не совпадают.';
    }
    if (!in_array($role, ['customer', 'tester', 'developer'], true)) {
        $errors[] = 'Выберите роль.';
    }
    return $errors;
}

/** Страница ошибки с настоящим кодом HTTP (403 / 404 / 400) и ссылкой возврата. */
function errorPage(int $code, string $message, string $backUrl = 'bugs.php', string $backTitle = 'К списку ошибок'): never
{
    http_response_code($code);
    pageHeader('Ошибка ' . $code);
    echo '<div class="card error-page"><div class="code">' . $code . '</div>'
        . '<p>' . h($message) . '</p><p><a class="btn btn-light" href="' . h($backUrl) . '">' . h($backTitle) . '</a></p></div>';
    pageFooter();
    exit;
}

/** Шапка страницы: стили и верхнее меню (для вошедшего пользователя). */
function pageHeader(string $title): void
{
    $user = currentUser();
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $active = static fn (string $prefix): string => str_starts_with($page, $prefix) ? ' class="active"' : '';
    ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — BugHunter</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php if ($user !== null): ?>
<header class="topbar">
    <a class="logo" href="bugs.php">Bug<span>Hunter</span></a>
    <nav>
        <a href="bugs.php"<?= $active('bug') ?>>Ошибки</a>
        <a href="projects.php"<?= $active('project') ?>>Проекты</a>
        <a href="dashboard.php"<?= $active('dashboard') ?>>Dashboard</a>
        <!-- Ссылка на "Таблицы" удалена -->
    </nav>
    <div class="me">
        <span><?= h($user['full_name']) ?> · <?= h(roleTitle($user['role'])) ?></span>
        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
            <button type="submit" class="btn btn-light btn-sm">Выйти</button>
        </form>
    </div>
</header>
<?php endif; ?>
<main class="container<?= $user === null ? ' narrow' : '' ?>">
<?php
}

function pageFooter(): void
{
    echo "</main>\n</body>\n</html>\n";
}

function showErrors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    echo '<div class="errors">';
    foreach ($errors as $err) {
        echo '<p>' . h($err) . '</p>';
    }
    echo '</div>';
}

/** Название роли по-русски. */
function roleTitle(string $role): string
{
    return [
        'customer'  => 'Заказчик',
        'tester'    => 'Тестировщик',
        'developer' => 'Разработчик',
        'admin'     => 'Администратор',
    ][$role] ?? $role;
}