<?php

declare(strict_types=1);

require __DIR__ . '/../app/bugs_lib.php';

$me = requireLogin();

// Только POST + CSRF-токен
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorPage(400, 'Смена статуса выполняется только методом POST.');
}
if (!checkCsrf()) {
    errorPage(403, 'Неверный CSRF-токен. Вернитесь в карточку и повторите действие.');
}

// 1–3. ID ошибки, новый статус и поиск записи в БД
$id = filter_input(INPUT_POST, 'bug_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$newStatus = trim((string) ($_POST['new_status'] ?? ''));

$bug = $id ? findBug((int) $id) : null;
if ($bug === null) {
    errorPage(404, 'Ошибка не найдена: не указан или неверный ID.');
}
$oldStatus = $bug['status'];

// Карта допустимых переходов: любой другой переход запрещён
if (!in_array($newStatus, TRANSITIONS[$oldStatus] ?? [], true)) {
    errorPage(400, "Недопустимый переход статуса: {$oldStatus} → " . ($newStatus !== '' ? $newStatus : '—') . '.');
}

// 4. Проверка прав роли на сервере (роль берётся из БД по сессии, а не из формы)
if (!in_array($newStatus, allowedNextStatuses($me, $oldStatus), true)) {
    errorPage(403, 'Нет прав: роль «' . roleTitle($me['role']) . "» не может переводить ошибку {$oldStatus} → {$newStatus}.");
}

// 5–7. UPDATE bugs + INSERT bug_history в одной транзакции
try {
    $done = changeBugStatus((int) $bug['bug_id'], $oldStatus, $newStatus, (int) $me['user_id']);
} catch (Throwable $e) {
    setMessage('error', 'Не удалось изменить статус. Попробуйте ещё раз.');
    redirect('bug_view.php?id=' . (int) $bug['bug_id']);
}

setMessage(
    $done ? 'success' : 'error',
    $done ? "Статус изменён: {$oldStatus} → {$newStatus}." : 'Статус уже был изменён другим пользователем. Обновите страницу.'
);
redirect('bug_view.php?id=' . (int) $bug['bug_id']);
