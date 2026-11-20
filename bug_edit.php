<?php

declare(strict_types=1);

require __DIR__ . '/bugs_lib.php';

$me = requireLogin();
$bug = bugFromRequest();

// Серверная проверка прав: автор, исполнитель или администратор
if (!canEditBug($me, $bug)) {
    errorPage(403, 'Нет прав: изменять ошибку могут только её автор, исполнитель или администратор.');
}

$id = (int) $bug['bug_id'];
$d = [
    'project_id' => (string) $bug['project_id'], 'title' => $bug['title'], 'description' => $bug['description'],
    'steps' => $bug['steps'], 'expected_result' => $bug['expected_result'], 'actual_result' => $bug['actual_result'],
    'priority' => $bug['priority'], 'status' => $bug['status'], 'assignee_id' => (string) ($bug['assignee_id'] ?? ''),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = bugInput();

    if (!checkCsrf()) {
        http_response_code(403);
        $errors[] = 'Неверный CSRF-токен. Обновите страницу и отправьте форму ещё раз.';
    } else {
        $errors = validateBug($d, true);
    }

    if ($errors === []) {
        try {
            $stmt = getPdo()->prepare(
                'UPDATE bugs SET project_id = ?, title = ?, description = ?, steps = ?, expected_result = ?,
                                 actual_result = ?, priority = ?, status = ?, assignee_id = ?
                 WHERE bug_id = ?'
            );
            $stmt->execute([(int) $d['project_id'], $d['title'], $d['description'], $d['steps'],
                $d['expected_result'], $d['actual_result'], $d['priority'], $d['status'],
                $d['assignee_id'] === '' ? null : (int) $d['assignee_id'], $id]);

            setMessage('success', 'Изменения сохранены.');
            redirect('bug_view.php?id=' . $id);
        } catch (PDOException $e) {
            $errors[] = 'Не удалось сохранить изменения. Попробуйте ещё раз.';
        }
    }
}

pageHeader('Редактирование ошибки');
?>
<p class="links"><a href="bug_view.php?id=<?= $id ?>">← К карточке ошибки</a></p>
<div class="page-head"><h1>Редактирование ошибки №<?= $id ?></h1></div>
<div class="card">
    <?php showErrors($errors); ?>
    <?php bugForm('bug_edit.php?id=' . $id, $d, 'Сохранить изменения', true); ?>
</div>
<?php pageFooter();
