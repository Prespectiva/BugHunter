<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

if (currentUser() !== null) {
    redirect('index.php');
}

$errors = [];
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');

    if (!checkCsrf()) {
        $errors[] = 'Сессия устарела, отправьте форму ещё раз.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Введите e-mail и пароль.';
    } else {
        try {
            $stmt = getPdo()->prepare('SELECT user_id, password_hash FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);               // новый ID сессии после входа
                $_SESSION['user_id'] = (int) $user['user_id'];
                redirect('index.php');
            }
            // Одно сообщение на оба случая, чтобы не подсказывать, какой e-mail есть в базе
            $errors[] = 'Неверный e-mail или пароль.';
        } catch (PDOException $e) {
            $errors[] = 'Ошибка базы данных. Запустите init_db.php.';
        }
    }
}

pageHeader('Вход');
?>
<h1>Вход</h1>
<?php if ($flash): ?>
    <div class="success"><?= h($flash) ?></div>
<?php endif; ?>
<?php showErrors($errors); ?>
<form class="auth" method="POST" action="login.php" novalidate>
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <label for="email">E-mail</label>
    <input id="email" type="email" name="email" value="<?= h($email) ?>" required>
    <label for="password">Пароль</label>
    <input id="password" type="password" name="password" required>
    <button type="submit">Войти</button>
</form>
<p>Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
<?php pageFooter();
