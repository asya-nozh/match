<?php

declare(strict_types=1);

require_once __DIR__ . '/chat_helpers.php';

function chat_is_json_request(): bool
{
    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

function chat_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function chat_redirect_with_message(string $url, string $message): never
{
    $_SESSION['chat_error'] = $message;
    header('Location: ' . $url);
    exit();
}

try {
    require_app_page();
    $conn = auth_require_db();
    $currentUserId = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chat_json(['success' => false, 'message' => 'Метод запроса не поддерживается.'], 405);
    }

    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!check_csrf_token('chat_action_csrf', $csrfToken)) {
        chat_json(['success' => false, 'message' => 'Обновите страницу и повторите действие.'], 403);
    }

    $action = trim((string)($_POST['action'] ?? 'send_message'));

    if ($action === 'send_message') {
        $chatId = filter_input(INPUT_POST, 'chat_id', FILTER_VALIDATE_INT) ?: 0;
        $messageText = (string)($_POST['message_text'] ?? '');
        $message = chat_insert_message($conn, $currentUserId, $chatId, $messageText);

        if (chat_is_json_request()) {
            chat_json([
                'success' => true,
                'message' => 'Сообщение отправлено.',
                'item' => $message,
            ]);
        }

        header('Location: ../pages/chats.php?chat_id=' . $chatId);
        exit();
    }


    if ($action === 'get_messages') {
        $chatId = filter_input(INPUT_POST, 'chat_id', FILTER_VALIDATE_INT) ?: 0;
        $afterId = filter_input(INPUT_POST, 'after_id', FILTER_VALIDATE_INT) ?: 0;

        if ($chatId <= 0) {
            chat_json(['success' => false, 'message' => 'Чат не найден.'], 422);
        }

        $items = chat_get_messages($conn, $currentUserId, $chatId, $afterId);
        chat_mark_read($conn, $currentUserId, $chatId);

        chat_json([
            'success' => true,
            'items' => $items,
            'last_id' => $items !== [] ? (int)max(array_map(static fn(array $row): int => (int)$row['id'], $items)) : $afterId,
        ]);
    }

    if ($action === 'report_message') {
        $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT) ?: 0;
        $reasonId = filter_input(INPUT_POST, 'reason_id', FILTER_VALIDATE_INT) ?: 0;
        $details = (string)($_POST['details'] ?? '');
        chat_report_message($conn, $currentUserId, $messageId, $reasonId, $details);
        chat_json(['success' => true, 'message' => 'Жалоба отправлена.']);
    }

    chat_json(['success' => false, 'message' => 'Неизвестное действие.'], 400);
} catch (Throwable $exception) {
    app_log('Ошибка действия в чате: ' . $exception->getMessage());

    if (chat_is_json_request()) {
        chat_json(['success' => false, 'message' => $exception->getMessage()], 400);
    }

    chat_redirect_with_message('../pages/chats.php', $exception->getMessage());
}
