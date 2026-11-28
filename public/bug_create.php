<?php

declare(strict_types=1);

require __DIR__ . '/../app/bugs_lib.php';

$me = requireLogin();
if (!canCreateBug($me)) {
    errorPage(403, 'Нет прав: заказчик не может регистрировать ошибки.');
}

$d = ['project_id' => (string) (filter_input(INPUT_GET, 'project', FILTER_VALIDATE_INT) ?: ''),
      'title' => '', 'description' => '', 'steps' => '', 'expected_result' => '', 'actual_result' => '',
      'priority' => 'Medium', 'status' => 'New', 'assignee_id' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = bugInput();

    if (!checkCsrf()) {
        http_response_code(403);
        $errors[] = 'Неверный CSRF-токен. Обновите страницу и отправьте форму ещё раз.';
    } else {
        $errors = validateBug($d, false);
    }

    if ($errors === []) {
        try {
            // Автор берётся из сессии, а не из формы
            $stmt = getPdo()->prepare(
                'INSERT INTO bugs (project_id, author_id, title, description, steps,
                                   expected_result, actual_result, priority, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([(int) $d['project_id'], $_SESSION['user_id'], $d['title'], $d['description'],
                $d['steps'], $d['expected_result'], $d['actual_result'], $d['priority'], 'New']);

            $id = (int) getPdo()->lastInsertId();
            setMessage('success', "Ошибка №{$id} зарегистрирована.");
            redirect('bug_view.php?id=' . $id);
        } catch (PDOException $e) {
            $errors[] = 'Не удалось сохранить ошибку. Попробуйте ещё раз.';
        }
    }
}

pageHeader('Регистрация ошибки');
?>
<div class="page-head"><h1>Регистрация ошибки</h1></div>
<div class="card">
    <?php showErrors($errors); ?>
    <p class="muted">Автор: <?= h($me['full_name']) ?> (определяется автоматически). Поля со <span class="req">*</span> обязательны.</p>
    <?php bugForm('bug_create.php', $d, 'Сохранить ошибку', false); ?>
</div>
<?php pageFooter();
