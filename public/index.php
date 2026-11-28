<?php

declare(strict_types=1);

require __DIR__ . '/../app/auth.php';

// Точка входа: вошедшего пользователя — к ошибкам, остальных — на форму входа
redirect(currentUser() !== null ? 'bugs.php' : 'login.php');
