<?php

declare(strict_types=1);

/**
 * Общие функции модуля «Ошибки».
 */

require_once __DIR__ . '/projects_lib.php';

/** 4 уровня приоритета: срочность исправления. */
const PRIORITIES = [
    'Critical' => 'Critical — блокирует работу системы',
    'High'     => 'High — серьёзное нарушение функции',
    'Medium'   => 'Medium — умеренное влияние, есть обход',
    'Low'      => 'Low — незначительный дефект',
];

/** Статус: текущий этап работы над ошибкой. */
const STATUSES = ['Open', 'In Progress', 'Closed'];

function priorityBadge(string $p): string
{
    return '<span class="badge p-' . h($p) . '">' . h($p) . '</span>';
}

function statusBadge(string $s): string
{
    return '<span class="badge s-' . h(str_replace(' ', '-', $s)) . '">' . h($s) . '</span>';
}

/** Страница ошибки 403 / 404 / 400 с кодом ответа HTTP. */
function errorPage(int $code, string $message): never
{
    http_response_code($code);
    pageHeader('Ошибка ' . $code);
    echo '<div class="card error-page"><div class="code">' . $code . '</div>'
        . '<p>' . h($message) . '</p><p><a class="btn btn-light" href="bugs.php">К списку ошибок</a></p></div>';
    pageFooter();
    exit;
}

/** Карточка ошибки с проектом, автором и исполнителем, или null. */
function findBug(int $id): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT b.*, p.code AS project_code, p.name AS project_name,
                a.full_name AS author_name, s.full_name AS assignee_name
         FROM bugs b
         JOIN projects p   ON p.project_id = b.project_id
         JOIN users a      ON a.user_id = b.author_id
         LEFT JOIN users s ON s.user_id = b.assignee_id
         WHERE b.bug_id = ?'
    );
    $stmt->execute([$id]);
    $bug = $stmt->fetch();

    return $bug ?: null;
}

/** Ошибка из параметра ?id=: без id или для несуществующей записи — 404. */
function bugFromRequest(): array
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        errorPage(404, 'Ошибка не найдена: не указан или неверный ID.');
    }
    $bug = findBug((int) $id);
    if ($bug === null) {
        errorPage(404, "Ошибка №{$id} не найдена.");
    }
    return $bug;
}

/** Регистрировать ошибки могут все роли, кроме заказчика. */
function canCreateBug(array $user): bool
{
    return $user['role'] !== 'customer';
}

/** Изменять ошибку могут автор, исполнитель и администратор. */
function canEditBug(array $user, array $bug): bool
{
    $uid = (int) $user['user_id'];
    return $user['role'] === 'admin'
        || (int) $bug['author_id'] === $uid
        || ($bug['assignee_id'] !== null && (int) $bug['assignee_id'] === $uid);
}

/** Список существующих проектов для выбора. */
function allProjects(): array
{
    return getPdo()->query('SELECT project_id, code, name FROM projects ORDER BY code')->fetchAll();
}

/** Пользователи, которых можно назначить исполнителем. */
function assignableUsers(): array
{
    return getPdo()->query(
        "SELECT user_id, full_name FROM users WHERE role IN ('developer', 'admin') ORDER BY full_name"
    )->fetchAll();
}

/** Данные формы ошибки из POST. */
function bugInput(): array
{
    $text = static fn (string $k): string => trim((string) ($_POST[$k] ?? ''));
    return [
        'project_id'      => $text('project_id'),
        'title'           => $text('title'),
        'description'     => $text('description'),
        'steps'           => $text('steps'),
        'expected_result' => $text('expected_result'),
        'actual_result'   => $text('actual_result'),
        'priority'        => $text('priority'),
        'status'          => $text('status'),
        'assignee_id'     => $text('assignee_id'),
    ];
}

/** Серверная валидация данных ошибки. Возвращает список ошибок. */
function validateBug(array $d, bool $isEdit): array
{
    $errors = [];
    $pdo = getPdo();

    // Проект обязателен и должен существовать
    if ($d['project_id'] === '') {
        $errors[] = 'Выберите проект.';
    } elseif (!ctype_digit($d['project_id'])) {
        $errors[] = 'Некорректный проект.';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE project_id = ?');
        $stmt->execute([(int) $d['project_id']]);
        if ((int) $stmt->fetchColumn() === 0) {
            $errors[] = 'Выбранный проект не существует.';
        }
    }

    $len = mb_strlen($d['title']);
    if ($len === 0) {
        $errors[] = 'Укажите название ошибки.';
    } elseif ($len < 5 || $len > 200) {
        $errors[] = 'Название: от 5 до 200 символов.';
    }

    foreach (['description' => 'Описание', 'steps' => 'Шаги воспроизведения',
              'expected_result' => 'Ожидаемый результат', 'actual_result' => 'Фактический результат'] as $k => $label) {
        if (mb_strlen($d[$k]) > 2000) {
            $errors[] = "{$label}: не более 2000 символов.";
        }
    }

    if (!array_key_exists($d['priority'], PRIORITIES)) {
        $errors[] = 'Выберите приоритет: Critical, High, Medium или Low.';
    }

    if ($isEdit) {
        if (!in_array($d['status'], STATUSES, true)) {
            $errors[] = 'Некорректный статус.';
        }
        if ($d['assignee_id'] !== '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ? AND role IN ('developer', 'admin')");
            $stmt->execute([ctype_digit($d['assignee_id']) ? (int) $d['assignee_id'] : 0]);
            if ((int) $stmt->fetchColumn() === 0) {
                $errors[] = 'Исполнитель не найден.';
            }
        }
    }

    return $errors;
}

/** Форма ошибки (регистрация и редактирование). */
function bugForm(string $action, array $d, string $button, bool $isEdit): void
{
    $projects = allProjects();
    ?>
<form class="form" method="POST" action="<?= h($action) ?>">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">

    <label for="project_id">Проект <span class="req">*</span></label>
    <select id="project_id" name="project_id" required>
        <option value="">— выберите проект —</option>
        <?php foreach ($projects as $p): ?>
            <option value="<?= (int) $p['project_id'] ?>" <?= (string) $p['project_id'] === (string) $d['project_id'] ? 'selected' : '' ?>>
                <?= h($p['code'] . ' — ' . $p['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="title">Название <span class="req">*</span></label>
    <input id="title" type="text" name="title" value="<?= h($d['title']) ?>" maxlength="200" required
           placeholder="Например: Ошибка входа без пароля">

    <label for="description">Описание</label>
    <textarea id="description" name="description" rows="3" maxlength="2000"><?= h($d['description']) ?></textarea>

    <label for="steps">Шаги воспроизведения</label>
    <textarea id="steps" name="steps" rows="3" maxlength="2000" placeholder="1. Открыть форму&#10;2. Нажать…"><?= h($d['steps']) ?></textarea>

    <div class="row">
        <div>
            <label for="expected_result">Ожидаемый результат</label>
            <textarea id="expected_result" name="expected_result" rows="2" maxlength="2000"><?= h($d['expected_result']) ?></textarea>
        </div>
        <div>
            <label for="actual_result">Фактический результат</label>
            <textarea id="actual_result" name="actual_result" rows="2" maxlength="2000"><?= h($d['actual_result']) ?></textarea>
        </div>
    </div>

    <div class="row">
        <div>
            <label for="priority">Приоритет <span class="req">*</span></label>
            <select id="priority" name="priority" required>
                <?php foreach (PRIORITIES as $value => $title): ?>
                    <option value="<?= h($value) ?>" <?= $d['priority'] === $value ? 'selected' : '' ?>><?= h($title) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($isEdit): ?>
        <div>
            <label for="status">Статус</label>
            <select id="status" name="status">
                <?php foreach (STATUSES as $s): ?>
                    <option value="<?= h($s) ?>" <?= $d['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isEdit): ?>
    <label for="assignee_id">Исполнитель</label>
    <select id="assignee_id" name="assignee_id">
        <option value="">— не назначен —</option>
        <?php foreach (assignableUsers() as $u): ?>
            <option value="<?= (int) $u['user_id'] ?>" <?= (string) $u['user_id'] === (string) $d['assignee_id'] ? 'selected' : '' ?>><?= h($u['full_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <button type="submit"><?= h($button) ?></button>
    <a class="btn btn-light" href="bugs.php" style="margin-top:18px">Отмена</a>
</form>
<?php
}
