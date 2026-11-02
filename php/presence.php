<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = auth_load_current_user(true);
    $userId = (int)($user['id'] ?? 0);

    if ($userId <= 0) {
        throw new RuntimeException('Пользователь не найден.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (!check_csrf_token('presence_csrf', (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $action = (string)($_POST['action'] ?? 'online');
    $conn = auth_require_db();

    if ($action === 'offline') {
        $stmt = mysqli_prepare($conn, 'UPDATE users SET last_active_at = NULL WHERE id = ?');
    } else {
        $stmt = mysqli_prepare($conn, 'UPDATE users SET last_active_at = NOW() WHERE id = ?');
    }

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if (function_exists('app_log')) {
        app_log('Ошибка обновления онлайн-статуса: ' . $exception->getMessage());
    }

    http_response_code(400);
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
}
