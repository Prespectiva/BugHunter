<?php

declare(strict_types=1);

require __DIR__ . '/../app/projects_lib.php';

$me = requireLogin();
$project = projectFromRequest();
requireManage($me, $project);   // серверная проверка прав

$id = (int) $project['project_id'];
$data = ['code' => $project['code'], 'name' => $project['name'], 'description' => $project['description']];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'code'        => strtoupper(trim((string) ($_POST['code'] ?? ''))),
        'name'        => trim((string) ($_POST['name'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
    ];

    if (!checkCsrf()) {
        $errors[] = 'Сессия устарела, отправьте форму ещё раз.';
    } else {
        $errors = validateProject($data['code'], $data['name'], $data['description'], $id);
    }

    if ($errors === []) {
        try {
            $stmt = getPdo()->prepare(
                'UPDATE projects SET code = ?, name = ?, description = ? WHERE project_id = ?'
            );
            $stmt->execute([$data['code'], $data['name'], $data['description'], $id]);
            setMessage('success', 'Изменения сохранены.');
            redirect('project_view.php?id=' . $id);
        } catch (PDOException $e) {
            $errors[] = 'Не удалось сохранить изменения. Попробуйте ещё раз.';
        }
    }
}

pageHeader('Редактирование проекта');
?>
<h1>Редактирование проекта</h1>
<p class="links"><a href="project_view.php?id=<?= $id ?>">← К проекту</a></p>
<?php showErrors($errors); ?>
<?php projectForm('project_edit.php?id=' . $id, $data, 'Сохранить', $id); ?>
<?php pageFooter();
