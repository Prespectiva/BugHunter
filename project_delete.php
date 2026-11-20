<?php

declare(strict_types=1);

require __DIR__ . '/projects_lib.php';

$me = requireLogin();

// Удаление только через POST с CSRF-токеном
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !checkCsrf()) {
    http_response_code(400);
    setMessage('error', 'Некорректный запрос на удаление.');
    redirect('projects.php');
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
