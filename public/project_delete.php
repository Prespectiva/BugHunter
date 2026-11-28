<?php

declare(strict_types=1);

require __DIR__ . '/../app/projects_lib.php';

$me = requireLogin();

// Удаление только через POST с CSRF-токеном
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorPage(400, 'Удаление выполняется только методом POST.', 'projects.php', 'К списку проектов');
}
if (!checkCsrf()) {
    errorPage(403, 'Неверный CSRF-токен. Вернитесь к списку проектов и повторите действие.', 'projects.php', 'К списку проектов');
}

$project = projectFromRequest();
requireManage($me, $project);   // серверная проверка прав

if ((int) $project['bugs_count'] > 0) {
    setMessage('error', "Нельзя удалить проект {$project['code']}: в нём {$project['bugs_count']} дефект(ов).");
    redirect('projects.php');
}

try {
    $stmt = getPdo()->prepare('DELETE FROM projects WHERE project_id = ?');
    $stmt->execute([$project['project_id']]);
    setMessage('success', "Проект {$project['code']} удалён.");
} catch (PDOException $e) {
    setMessage('error', 'Не удалось удалить проект.');
}

redirect('projects.php');
