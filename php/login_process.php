<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit();
}

$loginMode = (string)($_POST['login_mode'] ?? 'username');
$login = trim((string)($_POST['login'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$csrfToken = (string)($_POST['csrf_token'] ?? '');

$old = ['login' => $login];
$errors = [];

if (!check_csrf_token('csrf_login', $csrfToken)) {
    $errors['account'] = 'Форма устарела. Обновите страницу и попробуйте снова.';
}

if ($loginMode === 'email') {
    if ($login === '') {
        $errors['login'] = 'Введите электронную почту.';
    } elseif (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $errors['login'] = 'Укажите корректную электронную почту.';
    }
} else {
    if ($login === '') {
        $errors['login'] = 'Введите логин.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{6,50}$/', $login)) {
        $errors['login'] = 'Логин: 6–50 символов, латиница, цифры, _, . или -.';
    }
}

if ($password === '') {
    $errors['password'] = 'Введите пароль.';
} elseif (mb_strlen($password) < 8 || mb_strlen($password) > 72) {
    $errors['password'] = 'Пароль должен содержать от 8 до 72 символов.';
}

if ($errors !== []) {
    redirect_with_errors('login', $errors, $old, '../pages/login.php');
}

function register_session_login_failure(): void
{
    // Дополнительная браузерная блокировка не используется: попытки считаются в базе данных для конкретной учётной записи.
}

try {
    require_once __DIR__ . '/config.php';

    $hasEmailVerifiedColumn = db_column_exists($conn, 'users', 'email_verified_at');
    $hasIsVerifiedColumn = db_column_exists($conn, 'users', 'is_verified');

    $emailVerifiedSelect = $hasEmailVerifiedColumn ? 'u.email_verified_at' : 'NULL AS email_verified_at';
    $isVerifiedSelect = $hasIsVerifiedColumn ? 'u.is_verified' : '1 AS is_verified';

    $selectSql = 'SELECT u.id, u.role_id, u.username, u.email, '
        . $emailVerifiedSelect . ', '
        . $isVerifiedSelect . ', '
        . 'u.password_hash, u.status, u.failed_login_attempts, u.locked_until, r.slug
           FROM users AS u
           INNER JOIN roles AS r ON r.id = u.role_id
           WHERE u.username = ? OR u.email = ?
           LIMIT 1';

    $stmt = mysqli_prepare($conn, $selectSql);
    mysqli_stmt_bind_param($stmt, 'ss', $login, $login);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result(
        $stmt,
        $userId,
        $roleId,
        $username,
        $email,
        $emailVerifiedAt,
        $isVerified,
        $passwordHash,
        $status,
        $failedLoginAttempts,
        $lockedUntil,
        $roleSlug
    );

    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        register_session_login_failure();
        redirect_with_errors(
            'login',
            ['account' => 'Неверный логин, электронная почта или пароль.'],
            $old,
            '../pages/login.php'
        );
    }
    mysqli_stmt_close($stmt);

    auth_sync_user_restrictions($conn, (int)$userId);
    $statusStmt = mysqli_prepare($conn, 'SELECT status FROM users WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($statusStmt, 'i', $userId);
    mysqli_stmt_execute($statusStmt);
    mysqli_stmt_bind_result($statusStmt, $status);
    mysqli_stmt_fetch($statusStmt);
    mysqli_stmt_close($statusStmt);

    if (!in_array((string)$status, [MATCH_STATUS_ACTIVE, MATCH_STATUS_BLOCKED], true)) {
        redirect_with_errors(
            'login',
            ['account' => 'Доступ к учётной записи ограничен.'],
            $old,
            '../pages/login.php'
        );
    }

    if ((string)$status === MATCH_STATUS_BLOCKED && (string)$roleSlug !== MATCH_ROLE_PARTICIPANT) {
        redirect_with_errors(
            'login',
            ['account' => 'Доступ к служебной учётной записи ограничен.'],
            $old,
            '../pages/login.php'
        );
    }

    if ($lockedUntil !== null && strtotime((string)$lockedUntil) > time()) {
        redirect_with_errors(
            'login',
            ['account' => 'Вход временно заблокирован. Повторите попытку позднее.'],
            $old,
            '../pages/login.php'
        );
    }

    if (!password_verify($password, (string)$passwordHash)) {
        $failedLoginAttempts = (int)$failedLoginAttempts + 1;

        if ($failedLoginAttempts >= 5) {
            $newLockedUntil = date('Y-m-d H:i:s', time() + 86400);
            $updateStmt = mysqli_prepare($conn, 'UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?');
            mysqli_stmt_bind_param($updateStmt, 'isi', $failedLoginAttempts, $newLockedUntil, $userId);
        } else {
            $updateStmt = mysqli_prepare($conn, 'UPDATE users SET failed_login_attempts = ? WHERE id = ?');
            mysqli_stmt_bind_param($updateStmt, 'ii', $failedLoginAttempts, $userId);
        }

        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        register_session_login_failure();
        redirect_with_errors(
            'login',
            ['account' => 'Неверный логин, электронная почта или пароль.'],
            $old,
            '../pages/login.php'
        );
    }

    if (($hasIsVerifiedColumn && (int)$isVerified !== 1) || ($hasEmailVerifiedColumn && $emailVerifiedAt === null)) {
        $_SESSION['pending_verify_user_id'] = (int)$userId;
        $_SESSION['pending_verify_email'] = (string)$email;
        redirect_with_errors(
            'login',
            ['account' => 'Подтвердите электронную почту кодом из письма.'],
            $old,
            '../pages/login.php'
        );
    }

    $updateStmt = mysqli_prepare(
        $conn,
        'UPDATE users
         SET failed_login_attempts = 0,
             locked_until = NULL,
             last_login_at = NOW(),
             last_active_at = NOW()
         WHERE id = ?'
    );
    mysqli_stmt_bind_param($updateStmt, 'i', $userId);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$userId;
    $_SESSION['role_id'] = (int)$roleId;
    $_SESSION['role_slug'] = (string)$roleSlug;
    $_SESSION['user_status'] = (string)$status;
    $_SESSION['username'] = (string)$username;
    $_SESSION['display_name'] = (string)$username;
    $_SESSION['public_login_attempts'] = 0;
    unset($_SESSION['login_old'], $_SESSION['login_errors']);

    header('Location: ' . auth_home_url((string)$roleSlug, (string)$status));
    exit();
} catch (Throwable $exception) {
    if (function_exists('app_log')) {
        app_log('Ошибка авторизации пользователя: ' . $exception->getMessage() . ' | файл: ' . $exception->getFile() . ' | строка: ' . $exception->getLine());
    } else {
        error_log('Ошибка авторизации пользователя: ' . $exception->getMessage());
    }

    redirect_with_errors(
        'login',
        ['account' => 'Сервис временно недоступен. Попробуйте выполнить вход позднее.'],
        $old,
        '../pages/login.php'
    );
}
