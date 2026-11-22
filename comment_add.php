<?php

declare(strict_types=1);

require __DIR__ . '/bugs_lib.php';

$me = requireLogin();   // комментировать могут все авторизованные роли, включая заказчика

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorPage(400, 'Комментарий отправляется только методом POST.');
}
if (!checkCsrf()) {
    errorPage(403, 'Неверный CSRF-токен. Вернитесь в карточку и отправьте комментарий ещё раз.');
}

// Ошибка должна существовать
$bugId = filter_input(INPUT_POST, 'bug_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$bug = $bugId ? findBug((int) $bugId) : null;
if ($bug === null) {
    errorPage(404, 'Ошибка не найдена: комментарий не добавлен.');
}
$back = 'bug_view.php?id=' . (int) $bug['bug_id'] . '#comments';

$text = trim((string) ($_POST['text'] ?? ''));

// Пустые и состоящие только из пробелов (в т.ч. неразрывных) комментарии запрещены
if ($text === '' || preg_match('/^[\s\p{Z}\p{Cf}]*$/u', $text)) {
    setMessage('error', 'Комментарий не может быть пустым.');
    redirect($back);
}
if (mb_strlen($text) > 2000) {
    setMessage('error', 'Комментарий: не более 2000 символов.');
    redirect($back);
}

try {
    // Автор берётся из сессии, а не из формы
    $stmt = getPdo()->prepare(
        "INSERT INTO comments (bug_id, user_id, text, created_at) VALUES (?, ?, ?, datetime('now'))"
    );
    $stmt->execute([(int) $bug['bug_id'], (int) $me['user_id'], $text]);
    setMessage('success', 'Комментарий добавлен.');
} catch (PDOException $e) {
    setMessage('error', 'Не удалось добавить комментарий.');
}

redirect($back);