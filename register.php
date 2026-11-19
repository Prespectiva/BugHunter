<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

if (currentUser() !== null) {
    redirect('index.php');
}

$errors = [];
$name  = trim((string) ($_POST['full_name'] ?? ''));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$role  = (string) ($_POST['role'] ?? 'tester');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if (!checkCsrf()) {
        $errors[] = 'Сессия устарела, отправьте форму ещё раз.';
    } else {
        $errors = validateRegistration($name, $email, $password, $confirm, $role);
    }

    if ($errors === []) {
        try {
            $stmt = getPdo()->prepare(
                'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
            );
            // В базу записывается только хеш, сам пароль не хранится
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);

            $_SESSION['flash'] = 'Регистрация прошла успешно. Войдите в систему.';
            redirect('login.php');
        } catch (PDOException $e) {
            $errors[] = str_contains($e->getMessage(), 'UNIQUE')
                ? 'Пользователь с таким e-mail уже зарегистрирован.'
                : 'Ошибка базы данных. Попробуйте позже.';
        }
    }
}

$roles = ['tester' => 'Тестировщик', 'developer' => 'Разработчик', 'customer' => 'Заказчик'];

pageHeader('Регистрация');
?>
<h1>Регистрация</h1>
<?php showErrors($errors); ?>
<form class="auth" method="POST" action="register.php" novalidate>
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <label for="full_name">Имя</label>
    <input id="full_name" type="text" name="full_name" value="<?= h($name) ?>" required>
    <label for="email">E-mail</label>
    <input id="email" type="email" name="email" value="<?= h($email) ?>" required>
    <label for="role">Роль</label>
    <select id="role" name="role">
        <?php foreach ($roles as $value => $title): ?>
            <option value="<?= h($value) ?>" <?= $role === $value ? 'selected' : '' ?>><?= h($title) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="password">Пароль</label>
    <input id="password" type="password" name="password" required minlength="8">
    <label for="password_confirm">Повторите пароль</label>
    <input id="password_confirm" type="password" name="password_confirm" required minlength="8">
    <button type="submit">Зарегистрироваться</button>
</form>
<p>Уже есть аккаунт? <a href="login.php">Войти</a></p>
<?php pageFooter();
