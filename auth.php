<?php

declare(strict_types=1);

/**
 * Сессии и авторизация BugHunter.
 */

require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,   // cookie сессии недоступна из JavaScript
        'cookie_samesite' => 'Lax',
    ]);
}

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
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string) $_POST['csrf']);
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

/** Шапка страницы. */
function pageHeader(string $title): void
{
    ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — BugHunter</title>
    <style>
        body { font-family: sans-serif; margin: 2em; }
        h1 { margin-bottom: .2em; }
        h2 { margin-top: 1.5em; }
        table { margin-top: .5em; }
        th { background: #eee; text-align: left; }
        td, th { font-size: 14px; vertical-align: top; }
        .links a { margin-right: 1em; }
        form.auth { max-width: 360px; }
        form.auth label { display: block; margin: .8em 0 .2em; }
        form.auth input, form.auth select { width: 100%; padding: 6px; box-sizing: border-box; }
        form.auth button { margin-top: 1em; padding: 8px 16px; }
        .errors { color: #a40000; background: #fde8e8; padding: 8px 12px; max-width: 336px; }
        .errors p { margin: 4px 0; }
        .success { color: #135c13; background: #e6f6e6; padding: 8px 12px; max-width: 336px; }
        .userbar { display: flex; gap: 1em; align-items: center; }
        .userbar form { margin: 0; }
    </style>
</head>
<body>
<?php
}

function pageFooter(): void
{
    echo "</body>\n</html>\n";
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
