<?php

declare(strict_types=1);

require_once __DIR__ . '/feed_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function feed_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function feed_check_target(mysqli $conn, int $currentUserId, int $targetUserId): void
{
    if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
        feed_json(['success' => false, 'message' => 'Некорректный профиль.'], 400);
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT u.id
         FROM users AS u
         INNER JOIN roles AS r ON r.id = u.role_id AND r.slug = "participant"
         INNER JOIN user_profiles AS p ON p.user_id = u.id
         WHERE u.id = ? AND u.status = "active" AND p.is_visible = 1
         LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'i', $targetUserId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    if (!$exists) {
        feed_json(['success' => false, 'message' => 'Профиль недоступен.'], 404);
    }
}

function feed_ensure_match_chat(mysqli $conn, int $matchId): void
{
    $stmt = mysqli_prepare($conn, 'INSERT IGNORE INTO chats (match_id, is_active, created_at, last_message_at) VALUES (?, 1, NOW(), NULL)');
    mysqli_stmt_bind_param($stmt, 'i', $matchId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

try {
    require_app_page();
    $conn = auth_require_db();
    $currentUserId = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        feed_json(['success' => false, 'message' => 'Метод запроса не поддерживается.'], 405);
    }

    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!check_csrf_token('feed_action_csrf', $csrfToken)) {
        feed_json(['success' => false, 'message' => 'Обновите страницу и повторите действие.'], 403);
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $targetUserId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT) ?: 0;
    feed_check_target($conn, $currentUserId, $targetUserId);

    $blockedStmt = mysqli_prepare(
        $conn,
        'SELECT id FROM user_blocks
         WHERE (blocker_id = ? AND blocked_id = ?)
            OR (blocker_id = ? AND blocked_id = ?)
         LIMIT 1'
    );
    mysqli_stmt_bind_param($blockedStmt, 'iiii', $currentUserId, $targetUserId, $targetUserId, $currentUserId);
    mysqli_stmt_execute($blockedStmt);
    mysqli_stmt_store_result($blockedStmt);
    $hasBlock = mysqli_stmt_num_rows($blockedStmt) > 0;
    mysqli_stmt_close($blockedStmt);

    if ($hasBlock && !in_array($action, ['block', 'unblock'], true)) {
        feed_json(['success' => false, 'message' => 'Взаимодействие с этим профилем недоступно.'], 403);
    }

    if ($action === 'like') {
        mysqli_begin_transaction($conn);

        $currentLikeStmt = mysqli_prepare($conn, 'SELECT id FROM likes WHERE sender_id = ? AND receiver_id = ? LIMIT 1');
        mysqli_stmt_bind_param($currentLikeStmt, 'ii', $currentUserId, $targetUserId);
        mysqli_stmt_execute($currentLikeStmt);
        mysqli_stmt_store_result($currentLikeStmt);
        $alreadyLiked = mysqli_stmt_num_rows($currentLikeStmt) > 0;
        mysqli_stmt_close($currentLikeStmt);

        $user1 = min($currentUserId, $targetUserId);
        $user2 = max($currentUserId, $targetUserId);

        if ($alreadyLiked) {
            $deleteLikeStmt = mysqli_prepare($conn, 'DELETE FROM likes WHERE sender_id = ? AND receiver_id = ?');
            mysqli_stmt_bind_param($deleteLikeStmt, 'ii', $currentUserId, $targetUserId);
            mysqli_stmt_execute($deleteLikeStmt);
            mysqli_stmt_close($deleteLikeStmt);

            // При отмене лайка сам тип связи меняется: активный мэтч закрывается.
            // Уже созданный чат не удаляется и остаётся доступным, пока пользователи не заблокировали друг друга.
            $closeMatchStmt = mysqli_prepare(
                $conn,
                'UPDATE matches
                 SET status = "closed", closed_at = NOW()
                 WHERE user1_id = ? AND user2_id = ? AND status = "active"'
            );
            mysqli_stmt_bind_param($closeMatchStmt, 'ii', $user1, $user2);
            mysqli_stmt_execute($closeMatchStmt);
            mysqli_stmt_close($closeMatchStmt);

            $reverseStmt = mysqli_prepare($conn, 'SELECT id FROM likes WHERE sender_id = ? AND receiver_id = ? LIMIT 1');
            mysqli_stmt_bind_param($reverseStmt, 'ii', $targetUserId, $currentUserId);
            mysqli_stmt_execute($reverseStmt);
            mysqli_stmt_store_result($reverseStmt);
            $likedMe = mysqli_stmt_num_rows($reverseStmt) > 0;
            mysqli_stmt_close($reverseStmt);

            mysqli_commit($conn);

            feed_json([
                'success' => true,
                'message' => 'Лайк убран. Если диалог уже был создан, переписка останется доступной.',
                'liked_by_me' => false,
                'liked_me' => $likedMe,
                'is_matched' => false,
                'target_user_id' => $targetUserId,
            ]);
        }

        $likeStmt = mysqli_prepare($conn, 'INSERT IGNORE INTO likes (sender_id, receiver_id, created_at) VALUES (?, ?, NOW())');
        mysqli_stmt_bind_param($likeStmt, 'ii', $currentUserId, $targetUserId);
        mysqli_stmt_execute($likeStmt);
        mysqli_stmt_close($likeStmt);

        // Тестовая логика для неживых профилей: часть профилей автоматически отвечает взаимным лайком.
        $autoMatchStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM users
             WHERE id = ?
               AND username IN (
                    "demo_masha", "demo_dasha", "demo_kirill", "demo_mikhail", "demo_sonya",
                    "maris_wave07", "dasha_stars42", "kir_neon81", "misha_pixel64", "sonya_cinnamon73"
               )
             LIMIT 1'
        );
        mysqli_stmt_bind_param($autoMatchStmt, 'i', $targetUserId);
        mysqli_stmt_execute($autoMatchStmt);
        mysqli_stmt_store_result($autoMatchStmt);
        $shouldAutoMatch = mysqli_stmt_num_rows($autoMatchStmt) > 0;
        mysqli_stmt_close($autoMatchStmt);

        if ($shouldAutoMatch) {
            $demoLikeStmt = mysqli_prepare($conn, 'INSERT IGNORE INTO likes (sender_id, receiver_id, created_at) VALUES (?, ?, NOW())');
            mysqli_stmt_bind_param($demoLikeStmt, 'ii', $targetUserId, $currentUserId);
            mysqli_stmt_execute($demoLikeStmt);
            mysqli_stmt_close($demoLikeStmt);
        }

        $reverseStmt = mysqli_prepare($conn, 'SELECT id FROM likes WHERE sender_id = ? AND receiver_id = ? LIMIT 1');
        mysqli_stmt_bind_param($reverseStmt, 'ii', $targetUserId, $currentUserId);
        mysqli_stmt_execute($reverseStmt);
        mysqli_stmt_store_result($reverseStmt);
        $isMutual = mysqli_stmt_num_rows($reverseStmt) > 0;
        mysqli_stmt_close($reverseStmt);

        $isMatched = false;
        if ($isMutual) {
            $matchStmt = mysqli_prepare(
                $conn,
                'INSERT INTO matches (user1_id, user2_id, status, created_at)
                 VALUES (?, ?, "active", NOW())
                 ON DUPLICATE KEY UPDATE status = "active", closed_at = NULL'
            );
            mysqli_stmt_bind_param($matchStmt, 'ii', $user1, $user2);
            mysqli_stmt_execute($matchStmt);
            mysqli_stmt_close($matchStmt);

            $selectMatchStmt = mysqli_prepare($conn, 'SELECT id FROM matches WHERE user1_id = ? AND user2_id = ? LIMIT 1');
            mysqli_stmt_bind_param($selectMatchStmt, 'ii', $user1, $user2);
            mysqli_stmt_execute($selectMatchStmt);
            mysqli_stmt_bind_result($selectMatchStmt, $matchId);
            mysqli_stmt_fetch($selectMatchStmt);
            mysqli_stmt_close($selectMatchStmt);

            if (!empty($matchId)) {
                feed_ensure_match_chat($conn, (int)$matchId);
                $isMatched = true;
            }
        }

        mysqli_commit($conn);

        feed_json([
            'success' => true,
            'message' => $isMatched ? 'Мэтч создан. Теперь можно написать пользователю.' : 'Лайк отправлен.',
            'liked_by_me' => true,
            'liked_me' => $isMutual,
            'is_matched' => $isMatched,
            'target_user_id' => $targetUserId,
        ]);
    }

    if ($action === 'block') {
        $stmt = mysqli_prepare($conn, 'INSERT IGNORE INTO user_blocks (blocker_id, blocked_id, created_at) VALUES (?, ?, NOW())');
        mysqli_stmt_bind_param($stmt, 'ii', $currentUserId, $targetUserId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        feed_json([
            'success' => true,
            'message' => 'Пользователь добавлен в блокировку.',
            'target_user_id' => $targetUserId,
        ]);
    }


    if ($action === 'unblock') {
        $stmt = mysqli_prepare($conn, 'DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $currentUserId, $targetUserId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        feed_json([
            'success' => true,
            'message' => 'Пользователь разблокирован.',
            'target_user_id' => $targetUserId,
        ]);
    }

    if ($action === 'report') {
        $reasonId = (int)($_POST['reason_id'] ?? 0);
        $details = trim((string)($_POST['details'] ?? ''));

        if ($reasonId <= 0) {
            feed_json(['success' => false, 'message' => 'Выберите причину жалобы.'], 422);
        }

        if (mb_strlen($details, 'UTF-8') > 1200) {
            feed_json(['success' => false, 'message' => 'Комментарий слишком длинный.'], 422);
        }

        $reasonStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM report_reasons WHERE id = ? AND target_type = "profile" AND is_active = 1 LIMIT 1'
        );
        mysqli_stmt_bind_param($reasonStmt, 'i', $reasonId);
        mysqli_stmt_execute($reasonStmt);
        mysqli_stmt_store_result($reasonStmt);
        $reasonExists = mysqli_stmt_num_rows($reasonStmt) > 0;
        mysqli_stmt_close($reasonStmt);

        if (!$reasonExists) {
            feed_json(['success' => false, 'message' => 'Причина жалобы не найдена.'], 422);
        }

        if ($details === '') {
            $details = 'Пользователь отправил жалобу на профиль из ленты.';
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO reports (author_id, reported_user_id, message_id, reason_id, details, status, created_at)
             VALUES (?, ?, NULL, ?, ?, "new", NOW())'
        );
        mysqli_stmt_bind_param($stmt, 'iiis', $currentUserId, $targetUserId, $reasonId, $details);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        feed_json([
            'success' => true,
            'message' => 'Жалоба отправлена на рассмотрение.',
            'target_user_id' => $targetUserId,
        ]);
    }

    feed_json(['success' => false, 'message' => 'Неизвестное действие.'], 400);
} catch (Throwable $exception) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            mysqli_rollback($conn);
        } catch (Throwable) {
        }
    }

    app_log('Ошибка действия в ленте: ' . $exception->getMessage());
    feed_json(['success' => false, 'message' => 'Не удалось выполнить действие. Попробуйте позже.'], 500);
}
