<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/verify-email.php');
    exit();
}

$email = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
$code = trim((string)($_POST['code'] ?? ''));
$csrfToken = (string)($_POST['csrf_token'] ?? '');
$old = ['email' => $email, 'code' => $code];
$errors = [];

if (!check_csrf_token('csrf_verify_email', $csrfToken)) {
    $errors['account'] = 'Форма устарела. Обновите страницу и попробуйте снова.';
}

if ($email === '') {
    $errors['email'] = 'Введите электронную почту.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Укажите корректную электронную почту.';
}

if ($code === '') {
    $errors['code'] = 'Введите код подтверждения.';
} elseif (!preg_match('/^[0-9]{6}$/', $code)) {
    $errors['code'] = 'Код должен состоять из 6 цифр.';
}

if ($errors !== []) {
    redirect_with_errors('verify', $errors, $old, '../pages/verify-email.php');
}

try {
    require_once __DIR__ . '/config.php';

    foreach (['email_verified_at', 'is_verified', 'email_code_hash', 'email_code_expires_at'] as $columnName) {
        if (!db_column_exists($conn, 'users', $columnName)) {
            app_log('В таблице users отсутствует поле ' . $columnName . '. Импортируйте sql/users_email_code_patch.sql.');
            redirect_with_errors(
                'verify',
                ['account' => 'Подтверждение почты пока не настроено.'],
                $old,
                '../pages/verify-email.php'
            );
        }
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT u.id,
                u.role_id,
                u.username,
                u.email_code_hash,
                u.email_code_expires_at,
                u.is_verified,
                u.email_verified_at,
                u.status,
                r.slug
         FROM users AS u
         INNER JOIN roles AS r ON r.id = u.role_id
         WHERE u.email = ? AND u.status <> "deleted"
         LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result(
        $stmt,
        $userId,
        $roleId,
        $username,
        $codeHash,
        $expiresAt,
        $isVerified,
        $emailVerifiedAt,
        $status,
        $roleSlug
    );

    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        redirect_with_errors(
            'verify',
            ['account' => 'Неверная почта или код подтверждения.'],
            $old,
            '../pages/verify-email.php'
        );
    }
    mysqli_stmt_close($stmt);

    if ((int)$isVerified === 1 || $emailVerifiedAt !== null) {
        unset($_SESSION['pending_verify_user_id'], $_SESSION['pending_verify_email'], $_SESSION['verify_errors'], $_SESSION['verify_old']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$userId;
        $_SESSION['role_id'] = (int)$roleId;
        $_SESSION['role_slug'] = (string)$roleSlug;
        $_SESSION['user_status'] = (string)$status;
        $_SESSION['username'] = (string)$username;
        $_SESSION['display_name'] = (string)$username;
        header('Location: ' . auth_home_url((string)$roleSlug, (string)$status));
        exit();
    }

    if ($codeHash === null || $expiresAt === null) {
        redirect_with_errors(
            'verify',
            ['account' => 'Код не найден. Зарегистрируйтесь повторно или запросите новый код.'],
            $old,
            '../pages/verify-email.php'
        );
    }

    if (strtotime((string)$expiresAt) < time()) {
        redirect_with_errors(
            'verify',
            ['account' => 'Срок действия кода истёк. Зарегистрируйтесь повторно или запросите новый код.'],
            $old,
            '../pages/verify-email.php'
        );
    }

    if (!password_verify($code, (string)$codeHash)) {
        redirect_with_errors(
            'verify',
            ['code' => 'Неверный код подтверждения.'],
            $old,
            '../pages/verify-email.php'
        );
    }

    $verifyUser = mysqli_prepare(
        $conn,
        'UPDATE users
         SET is_verified = 1,
             email_verified_at = NOW(),
             email_code_hash = NULL,
             email_code_expires_at = NULL,
             failed_login_attempts = 0,
             locked_until = NULL,
             last_login_at = NOW(),
             last_active_at = NOW(),
             updated_at = NOW()
         WHERE id = ?'
    );
    mysqli_stmt_bind_param($verifyUser, 'i', $userId);
    mysqli_stmt_execute($verifyUser);
    mysqli_stmt_close($verifyUser);

    unset($_SESSION['pending_verify_user_id'], $_SESSION['pending_verify_email'], $_SESSION['verify_errors'], $_SESSION['verify_old']);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$userId;
    $_SESSION['role_id'] = (int)$roleId;
    $_SESSION['role_slug'] = (string)$roleSlug;
    $_SESSION['user_status'] = (string)$status;
    $_SESSION['username'] = (string)$username;
    $_SESSION['display_name'] = (string)$username;
    $_SESSION['public_login_attempts'] = 0;
    unset($_SESSION['public_login_locked_until'], $_SESSION['login_old'], $_SESSION['login_errors']);

    header('Location: ' . auth_home_url((string)$roleSlug, (string)$status));
    exit();
} catch (Throwable $exception) {
    app_log('Ошибка подтверждения почты: ' . $exception->getMessage() . ' | файл: ' . $exception->getFile() . ' | строка: ' . $exception->getLine());

    redirect_with_errors(
        'verify',
        ['account' => 'Сервис временно недоступен. Попробуйте подтвердить почту позднее.'],
        $old,
        '../pages/verify-email.php'
    );
}
