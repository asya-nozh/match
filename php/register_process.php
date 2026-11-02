<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_guest_page();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/register.php');
    exit();
}

$displayName = trim((string)($_POST['display_name'] ?? ''));
$username = trim((string)($_POST['username'] ?? ''));
$email = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
$birthDate = trim((string)($_POST['birth_date'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$passwordConfirm = (string)($_POST['password_confirm'] ?? '');
$privacyAccepted = isset($_POST['privacy_accepted']);
$csrfToken = (string)($_POST['csrf_token'] ?? '');

$old = [
    'display_name' => $displayName,
    'username' => $username,
    'email' => $email,
    'birth_date' => $birthDate,
    'privacy_accepted' => $privacyAccepted ? '1' : ''
];

$errors = [];

if (!check_csrf_token('csrf_register', $csrfToken)) {
    $errors['account'] = 'Форма устарела. Обновите страницу и попробуйте снова.';
}

if ($displayName === '') {
    $errors['display_name'] = 'Введите имя.';
} elseif (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 80) {
    $errors['display_name'] = 'Имя должно содержать от 2 до 80 символов.';
} elseif (!preg_match('/^[\p{L}\s\-\']+$/u', $displayName)) {
    $errors['display_name'] = 'Имя может содержать буквы, пробелы и дефис.';
}

if ($username === '') {
    $errors['username'] = 'Введите логин.';
} elseif (!preg_match('/^[A-Za-z0-9_.-]{6,50}$/', $username)) {
    $errors['username'] = 'Логин: 6–50 символов, латиница, цифры, _, . или -.';
}

if ($email === '') {
    $errors['email'] = 'Введите электронную почту.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Укажите корректную электронную почту.';
} elseif (mb_strlen($email) > 255) {
    $errors['email'] = 'Адрес электронной почты слишком длинный.';
}

$birthDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
$birthDateErrors = DateTimeImmutable::getLastErrors();

if (!$birthDateObject || (is_array($birthDateErrors) && ($birthDateErrors['warning_count'] > 0 || $birthDateErrors['error_count'] > 0))) {
    $errors['birth_date'] = 'Укажите корректную дату рождения.';
} else {
    $maxBirthDate = (new DateTimeImmutable('today'))->modify('-18 years');

    if ($birthDateObject > $maxBirthDate) {
        $errors['birth_date'] = 'Регистрация доступна пользователям от 18 лет.';
    }
}

if ($password === '') {
    $errors['password'] = 'Введите пароль.';
} elseif (mb_strlen($password) < 8) {
    $errors['password'] = 'Пароль должен содержать не менее 8 символов.';
} elseif (mb_strlen($password) > 72) {
    $errors['password'] = 'Пароль должен содержать не более 72 символов.';
}

if ($passwordConfirm === '') {
    $errors['password_confirm'] = 'Повторите пароль.';
} elseif ($password !== $passwordConfirm) {
    $errors['password_confirm'] = 'Пароли не совпадают.';
}

if (!$privacyAccepted) {
    $errors['privacy_accepted'] = 'Необходимо принять правила и политику конфиденциальности.';
}

if ($errors !== []) {
    redirect_with_errors('register', $errors, $old, '../pages/register.php');
}

$transactionStarted = false;

try {
    require_once __DIR__ . '/config.php';

    foreach (['email_verified_at', 'is_verified', 'email_code_hash', 'email_code_expires_at'] as $columnName) {
        if (!db_column_exists($conn, 'users', $columnName)) {
            if (function_exists('app_log')) {
                app_log('В таблице users отсутствует поле ' . $columnName . '. Импортируйте sql/users_email_code_patch.sql.');
            }

            redirect_with_errors(
                'register',
                ['account' => 'Сервис временно недоступен. Попробуйте повторить регистрацию позднее.'],
                $old,
                '../pages/register.php'
            );
        }
    }

    $checkLoginStmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? LIMIT 1');
    mysqli_stmt_bind_param($checkLoginStmt, 's', $username);
    mysqli_stmt_execute($checkLoginStmt);
    mysqli_stmt_store_result($checkLoginStmt);

    if (mysqli_stmt_num_rows($checkLoginStmt) > 0) {
        $errors['username'] = 'Логин уже занят. Попробуйте другой.';
    }
    mysqli_stmt_close($checkLoginStmt);

    $checkEmailStmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($checkEmailStmt, 's', $email);
    mysqli_stmt_execute($checkEmailStmt);
    mysqli_stmt_store_result($checkEmailStmt);

    if (mysqli_stmt_num_rows($checkEmailStmt) > 0) {
        $errors['email'] = 'Не удалось использовать этот адрес почты. Проверьте данные или укажите другой адрес.';
    }
    mysqli_stmt_close($checkEmailStmt);

    if ($errors !== []) {
        redirect_with_errors('register', $errors, $old, '../pages/register.php');
    }

    $participantSlug = 'participant';
    $roleStmt = mysqli_prepare($conn, 'SELECT id FROM roles WHERE slug = ? LIMIT 1');
    mysqli_stmt_bind_param($roleStmt, 's', $participantSlug);
    mysqli_stmt_execute($roleStmt);
    mysqli_stmt_bind_result($roleStmt, $roleId);

    if (!mysqli_stmt_fetch($roleStmt)) {
        throw new RuntimeException('Роль участника не найдена.');
    }
    mysqli_stmt_close($roleStmt);

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $verificationCode = (string)random_int(100000, 999999);
    $codeHash = password_hash($verificationCode, PASSWORD_DEFAULT);
    $expiresAt = (new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');

    mysqli_begin_transaction($conn);
    $transactionStarted = true;

    $userStmt = mysqli_prepare(
        $conn,
        'INSERT INTO users
            (role_id, username, email, email_verified_at, email_code_hash, email_code_expires_at, password_hash, status, privacy_accepted_at, created_at, updated_at, is_verified)
         VALUES
            (?, ?, ?, NULL, ?, ?, ?, "active", NOW(), NOW(), NOW(), 0)'
    );
    mysqli_stmt_bind_param($userStmt, 'isssss', $roleId, $username, $email, $codeHash, $expiresAt, $passwordHash);
    mysqli_stmt_execute($userStmt);
    $userId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($userStmt);

    $profileStmt = mysqli_prepare(
        $conn,
        'INSERT INTO user_profiles (user_id, display_name, birth_date, completed_at, updated_at)
         VALUES (?, ?, ?, NULL, NOW())'
    );
    mysqli_stmt_bind_param($profileStmt, 'iss', $userId, $displayName, $birthDate);
    mysqli_stmt_execute($profileStmt);
    mysqli_stmt_close($profileStmt);

    mysqli_commit($conn);
    $transactionStarted = false;

    unset($_SESSION['register_old'], $_SESSION['register_errors']);
    $_SESSION['pending_verify_user_id'] = $userId;
    $_SESSION['pending_verify_email'] = $email;

    require_once __DIR__ . '/mailer.php';
    $isSent = send_email_confirmation_code($email, $verificationCode);

    if (!$isSent) {
        if (function_exists('app_log')) {
            app_log('Код подтверждения для ' . $email . ': ' . $verificationCode);
        } else {
            error_log('Код подтверждения для ' . $email . ': ' . $verificationCode);
        }

        $_SESSION['success_message'] = 'Аккаунт создан. Код подтверждения отправить не удалось, проверьте настройки почты.';
    } else {
        $_SESSION['success_message'] = 'Аккаунт создан. Код подтверждения отправлен на указанную почту.';
    }

    header('Location: ../pages/verify-email.php');
    exit();
} catch (mysqli_sql_exception $exception) {
    if ($transactionStarted && isset($conn)) {
        mysqli_rollback($conn);
    }

    if (function_exists('app_log')) {
        app_log('Ошибка регистрации пользователя: ' . $exception->getMessage());
    } else {
        error_log('Ошибка регистрации пользователя: ' . $exception->getMessage());
    }

    if ((int)$exception->getCode() === 1062) {
        redirect_with_errors(
            'register',
            ['account' => 'Не удалось создать аккаунт с указанными данными. Проверьте корректность заполнения или попробуйте войти.'],
            $old,
            '../pages/register.php'
        );
    }

    redirect_with_errors(
        'register',
        ['account' => 'Сервис временно недоступен. Попробуйте повторить регистрацию позднее.'],
        $old,
        '../pages/register.php'
    );
} catch (Throwable $exception) {
    if ($transactionStarted && isset($conn)) {
        mysqli_rollback($conn);
    }

    if (function_exists('app_log')) {
        app_log('Ошибка регистрации пользователя: ' . $exception->getMessage());
    } else {
        error_log('Ошибка регистрации пользователя: ' . $exception->getMessage());
    }

    redirect_with_errors(
        'register',
        ['account' => 'Сервис временно недоступен. Попробуйте повторить регистрацию позднее.'],
        $old,
        '../pages/register.php'
    );
}
