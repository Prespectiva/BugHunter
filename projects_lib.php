<?php

declare(strict_types=1);

/**
 * Общие функции модуля «Проекты».
 */

require_once __DIR__ . '/auth.php';

/** Сообщение для следующей страницы (успех или ошибка). */
function setMessage(string $type, string $text): void
{
    $_SESSION['message'] = ['type' => $type, 'text' => $text];
}

/** Вывод и удаление сообщения из сессии. */
function showMessage(): void
{
    if (empty($_SESSION['message'])) {
        return;
    }
    $m = $_SESSION['message'];
    unset($_SESSION['message']);
    $class = $m['type'] === 'success' ? 'success' : 'errors';
    echo '<div class="' . $class . '"><p>' . h($m['text']) . '</p></div>';
}

/** Проект по id вместе с автором и числом дефектов, или null. */
function findProject(int $id): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT p.project_id, p.code, p.name, p.description, p.owner_id, p.created_at,
                u.full_name AS owner_name,
                (SELECT COUNT(*) FROM bugs b WHERE b.project_id = p.project_id) AS bugs_count
         FROM projects p
         LEFT JOIN users u ON u.user_id = p.owner_id
         WHERE p.project_id = ?'
    );
    $stmt->execute([$id]);
    $project = $stmt->fetch();

    return $project ?: null;
}

/** Проект из параметра ?id= или 404. */
function projectFromRequest(): array
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $project = $id ? findProject((int) $id) : null;
    if ($project === null) {
        http_response_code(404);
        setMessage('error', 'Проект не найден.');
        redirect('projects.php');
    }
    return $project;
}

/** Право изменять и удалять проект: автор проекта или администратор. */
function canManage(array $user, array $project): bool
{
    return $user['role'] === 'admin' || (int) $project['owner_id'] === (int) $user['user_id'];
}

/** Серверная проверка прав: при отказе — 403 и возврат к списку. */
function requireManage(array $user, array $project): void
{
    if (!canManage($user, $project)) {
        http_response_code(403);
        setMessage('error', 'Нет прав: изменять проект может только его автор или администратор.');
        redirect('projects.php');
    }
}

/** Создавать проекты могут все роли, кроме заказчика. */
function canCreate(array $user): bool
{
    return $user['role'] !== 'customer';
}

/** Серверная валидация данных проекта. Возвращает список ошибок. */
function validateProject(string $code, string $name, string $description, ?int $exceptId = null): array
{
    $errors = [];
    if (!preg_match('/^[A-Z0-9]{2,10}$/', $code)) {
        $errors[] = 'Код проекта: от 2 до 10 латинских заглавных букв или цифр.';
    } else {
        $stmt = getPdo()->prepare('SELECT COUNT(*) FROM projects WHERE code = ? AND project_id <> ?');
        $stmt->execute([$code, $exceptId ?? 0]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'Проект с таким кодом уже существует.';
        }
    }
    $len = mb_strlen($name);
    if ($len < 3 || $len > 100) {
        $errors[] = 'Название: от 3 до 100 символов.';
    }
    if (mb_strlen($description) > 1000) {
        $errors[] = 'Описание: не более 1000 символов.';
    }
    return $errors;
}

/** Форма создания и редактирования проекта. */
function projectForm(string $action, array $data, string $button, ?int $id = null): void
{
    ?>
<form class="auth" method="POST" action="<?= h($action) ?>" novalidate>
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <?php if ($id !== null): ?>
        <input type="hidden" name="id" value="<?= $id ?>">
    <?php endif; ?>
    <label for="code">Код проекта</label>
    <input id="code" type="text" name="code" value="<?= h($data['code']) ?>" maxlength="10" required>
    <label for="name">Название</label>
    <input id="name" type="text" name="name" value="<?= h($data['name']) ?>" maxlength="100" required>
    <label for="description">Описание</label>
    <textarea id="description" name="description" rows="5" maxlength="1000"><?= h($data['description']) ?></textarea>
    <button type="submit"><?= h($button) ?></button>
</form>
<?php
}
