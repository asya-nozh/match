<?php

declare(strict_types=1);

require_once __DIR__ . '/places_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function place_json(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_app_page();
    $conn = auth_require_db();
    $currentUserId = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        place_json(['success' => false, 'message' => 'Некорректный метод запроса.'], 405);
    }

    if (!check_csrf_token('place_action_csrf', (string)($_POST['csrf_token'] ?? ''))) {
        place_json(['success' => false, 'message' => 'Сессия устарела. Обновите страницу.'], 403);
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'invite_place') {
        $placeId = (int)($_POST['place_id'] ?? 0);
        $chatId = (int)($_POST['chat_id'] ?? 0);

        if ($placeId <= 0 || $chatId <= 0) {
            place_json(['success' => false, 'message' => 'Не выбрано место или диалог.'], 422);
        }

        $place = places_get_place($conn, $placeId);
        if (!$place || (string)$place['status'] !== 'active') {
            place_json(['success' => false, 'message' => 'Место недоступно.'], 404);
        }

        if (!places_user_can_use_chat($conn, $currentUserId, $chatId)) {
            place_json(['success' => false, 'message' => 'Диалог недоступен.'], 403);
        }

        $message = places_invite_text($place);
        mysqli_begin_transaction($conn);

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO messages (chat_id, sender_id, message_text, sent_at) VALUES (?, ?, ?, NOW())'
        );
        mysqli_stmt_bind_param($stmt, 'iis', $chatId, $currentUserId, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $updateStmt = mysqli_prepare($conn, 'UPDATE chats SET last_message_at = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($updateStmt, 'i', $chatId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        mysqli_commit($conn);

        place_json(['success' => true, 'message' => 'Приглашение отправлено в чат.']);
    }

    place_json(['success' => false, 'message' => 'Неизвестное действие.'], 400);
} catch (Throwable $exception) {
    if (isset($conn) && $conn instanceof mysqli) {
        try { mysqli_rollback($conn); } catch (Throwable) {}
    }
    app_log('Ошибка действия с местом: ' . $exception->getMessage());
    place_json(['success' => false, 'message' => 'Не удалось выполнить действие. Попробуйте позже.'], 500);
}
