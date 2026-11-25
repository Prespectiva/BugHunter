<?php

declare(strict_types=1);

require __DIR__ . '/projects_lib.php';

$me = requireLogin();
if (!canCreate($me)) {
    errorPage(403, 'Нет прав на создание проектов.', 'projects.php', 'К списку проектов');
}

$data = ['code' => '', 'name' => '', 'description' => ''];
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
        $errors = validateProject($data['code'], $data['name'], $data['description']);
    }

    if ($errors === []) {
        try {
            $stmt = getPdo()->prepare(
                'INSERT INTO projects (code, name, description, owner_id, lead_id) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$data['code'], $data['name'], $data['description'], $me['user_id'], $me['user_id']]);
            setMessage('success', "Проект {$data['code']} создан.");
            redirect('project_view.php?id=' . getPdo()->lastInsertId());
        } catch (PDOException $e) {
            $errors[] = 'Не удалось сохранить проект. Попробуйте ещё раз.';
        }
    }
}

pageHeader('Новый проект');
?>
<h1>Новый проект</h1>
<p class="links"><a href="projects.php">← К списку проектов</a></p>
<?php showErrors($errors); ?>
<?php projectForm('project_create.php', $data, 'Создать'); ?>
<?php pageFooter();
