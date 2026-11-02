<?php

declare(strict_types=1);

require_once __DIR__ . '/../modules/helpers.php';
ensure_session();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    try {
        require_once __DIR__ . '/config.php';
        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET last_active_at = NULL WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } catch (Throwable $exception) {
        error_log('Ошибка выхода пользователя: ' . $exception->getMessage());
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool)$params['secure'],
        (bool)$params['httponly']
    );
}

session_destroy();

header('Location: ../pages/login.php');
exit();
