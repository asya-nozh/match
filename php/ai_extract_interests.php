<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/profile_helpers.php';

try {
    require_role([MATCH_ROLE_PARTICIPANT], true);

    $token = trim((string)($_POST['csrf_token'] ?? ''));
    if (!check_csrf_token('profile_edit_csrf', $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Сессия устарела. Обновите страницу и попробуйте снова.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $text = trim((string)($_POST['text'] ?? ''));

    if ($text === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Сначала опишите свои интересы.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($text, 'UTF-8') < 15) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Описание слишком короткое. Напишите хотя бы одно полноценное предложение.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($text, 'UTF-8') > 1500) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Описание слишком длинное. Оставьте до 1500 символов.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (ai_has_unsafe_content($text)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Текст содержит недопустимый контент и не может быть обработан.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $analysis = ai_extract_interests_from_text($text);

    if (!$analysis['ok']) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Текст содержит недопустимый контент или не может быть обработан.',
            'data' => ['interests' => [], 'anti_interests' => []]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Интересы обновлены.',
        'data' => $analysis['data'],
        'source' => $analysis['source'] ?? 'local'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if (function_exists('app_log')) {
        app_log('Ошибка анализа интересов: ' . $exception->getMessage());
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Не удалось обработать интересы. Попробуйте позже.'], JSON_UNESCAPED_UNICODE);
}
