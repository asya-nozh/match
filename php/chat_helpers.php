<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_helpers.php';

if (!function_exists('chat_time_label')) {
    function chat_time_label(?string $datetime): string
    {
        if (!$datetime) {
            return '';
        }

        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return '';
        }

        $today = date('Y-m-d');
        $date = date('Y-m-d', $timestamp);

        if ($date === $today) {
            return date('H:i', $timestamp);
        }

        if ($date === date('Y-m-d', strtotime('-1 day'))) {
            return 'Вчера';
        }

        return date('d.m', $timestamp);
    }
}

if (!function_exists('chat_date_separator')) {
    function chat_date_separator(?string $datetime): string
    {
        if (!$datetime) {
            return '';
        }

        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return '';
        }

        $date = date('Y-m-d', $timestamp);
        if ($date === date('Y-m-d')) {
            return 'Сегодня';
        }

        if ($date === date('Y-m-d', strtotime('-1 day'))) {
            return 'Вчера';
        }

        return date('d.m.Y', $timestamp);
    }
}

if (!function_exists('chat_shorten_text')) {
    function chat_shorten_text(?string $text, int $limit = 54): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text) ?? '');
        if ($text === '') {
            return 'Сообщений пока нет';
        }

        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1, 'UTF-8') . '…';
    }
}



if (!function_exists('chat_is_user_online')) {
    function chat_is_user_online(?string $lastActiveAt): bool
    {
        if (!$lastActiveAt) {
            return false;
        }

        $timestamp = strtotime($lastActiveAt);
        if (!$timestamp) {
            return false;
        }

        return $timestamp >= time() - 90;
    }
}

if (!function_exists('chat_apply_online_status')) {
    function chat_apply_online_status(array $row): array
    {
        $isOnline = chat_is_user_online((string)($row['last_active_at'] ?? ''));
        $row['is_online'] = $isOnline;
        $row['online_label'] = $isOnline ? 'В сети' : 'Оффлайн';
        $row['online_class'] = $isOnline ? 'is-online' : 'is-offline';
        return $row;
    }
}

if (!function_exists('chat_get_user_chats')) {
    function chat_get_user_chats(mysqli $conn, int $currentUserId): array
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT ch.id AS chat_id,
                    ch.last_message_at,
                    ch.created_at AS chat_created_at,
                    other_u.id AS user_id,
                    other_u.username,
                    other_u.last_active_at,
                    p.display_name,
                    p.birth_date,
                    p.avatar_path,
                    c.name AS city_name,
                    last_msg.message_text AS last_message_text,
                    last_msg.sent_at AS last_message_time,
                    (
                        SELECT COUNT(*)
                        FROM messages AS unread_msg
                        WHERE unread_msg.chat_id = ch.id
                          AND unread_msg.sender_id <> ?
                          AND unread_msg.read_at IS NULL
                          AND unread_msg.deleted_at IS NULL
                    ) AS unread_count
             FROM chats AS ch
             INNER JOIN matches AS m ON m.id = ch.match_id
             INNER JOIN users AS other_u ON other_u.id = IF(m.user1_id = ?, m.user2_id, m.user1_id)
             INNER JOIN user_profiles AS p ON p.user_id = other_u.id
             LEFT JOIN cities AS c ON c.id = p.city_id
             LEFT JOIN messages AS last_msg ON last_msg.id = (
                SELECT msg.id
                FROM messages AS msg
                WHERE msg.chat_id = ch.id AND msg.deleted_at IS NULL
                ORDER BY msg.sent_at DESC, msg.id DESC
                LIMIT 1
             )
             WHERE (m.user1_id = ? OR m.user2_id = ?)
               AND ch.is_active = 1
               AND other_u.status = "active"
               AND NOT EXISTS (
                   SELECT 1 FROM user_blocks AS b
                   WHERE (b.blocker_id = ? AND b.blocked_id = other_u.id)
                      OR (b.blocker_id = other_u.id AND b.blocked_id = ?)
               )
             ORDER BY COALESCE(ch.last_message_at, ch.created_at) DESC, ch.id DESC'
        );
        mysqli_stmt_bind_param($stmt, 'iiiiii', $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        foreach ($rows as &$row) {
            $row['avatar_url'] = public_url(profile_avatar_path((string)($row['avatar_path'] ?? '')));
            $row['age'] = profile_age((string)($row['birth_date'] ?? ''));
            $row['public_username'] = profile_public_username((string)($row['username'] ?? ''));
            $row['display_name'] = trim((string)($row['display_name'] ?? '')) !== '' ? (string)$row['display_name'] : (string)$row['public_username'];
            $row['city_name'] = trim((string)($row['city_name'] ?? '')) !== '' ? (string)$row['city_name'] : 'Город не указан';
            $row['last_message_preview'] = chat_shorten_text((string)($row['last_message_text'] ?? ''));
            $row['time_label'] = chat_time_label((string)($row['last_message_time'] ?? $row['last_message_at'] ?? $row['chat_created_at'] ?? ''));
            $row['unread_count'] = (int)($row['unread_count'] ?? 0);
            $row = chat_apply_online_status($row);
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('chat_find_or_create_by_user')) {
    function chat_find_or_create_by_user(mysqli $conn, int $currentUserId, int $targetUserId): ?int
    {
        if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
            return null;
        }

        $user1 = min($currentUserId, $targetUserId);
        $user2 = max($currentUserId, $targetUserId);

        // Если чат уже был создан раньше, его можно открыть даже после отмены лайка и закрытия мэтча.
        $existingChatStmt = mysqli_prepare(
            $conn,
            'SELECT ch.id
             FROM chats AS ch
             INNER JOIN matches AS m ON m.id = ch.match_id
             WHERE m.user1_id = ? AND m.user2_id = ? AND ch.is_active = 1
             LIMIT 1'
        );
        mysqli_stmt_bind_param($existingChatStmt, 'ii', $user1, $user2);
        mysqli_stmt_execute($existingChatStmt);
        mysqli_stmt_bind_result($existingChatStmt, $existingChatId);
        $hasExistingChat = mysqli_stmt_fetch($existingChatStmt);
        mysqli_stmt_close($existingChatStmt);

        if ($hasExistingChat && !empty($existingChatId)) {
            return (int)$existingChatId;
        }

        // Для создания первого диалога всё ещё нужен активный мэтч.
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM matches WHERE user1_id = ? AND user2_id = ? AND status = "active" LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'ii', $user1, $user2);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $matchId);
        $hasMatch = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if (!$hasMatch || empty($matchId)) {
            return null;
        }

        $insertStmt = mysqli_prepare(
            $conn,
            'INSERT IGNORE INTO chats (match_id, is_active, created_at, last_message_at) VALUES (?, 1, NOW(), NULL)'
        );
        mysqli_stmt_bind_param($insertStmt, 'i', $matchId);
        mysqli_stmt_execute($insertStmt);
        mysqli_stmt_close($insertStmt);

        $chatStmt = mysqli_prepare($conn, 'SELECT id FROM chats WHERE match_id = ? AND is_active = 1 LIMIT 1');
        mysqli_stmt_bind_param($chatStmt, 'i', $matchId);
        mysqli_stmt_execute($chatStmt);
        mysqli_stmt_bind_result($chatStmt, $chatId);
        $hasChat = mysqli_stmt_fetch($chatStmt);
        mysqli_stmt_close($chatStmt);

        return $hasChat ? (int)$chatId : null;
    }
}

if (!function_exists('chat_get_chat')) {
    function chat_get_chat(mysqli $conn, int $currentUserId, int $chatId): ?array
    {
        if ($chatId <= 0) {
            return null;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT ch.id AS chat_id,
                    other_u.id AS user_id,
                    other_u.username,
                    other_u.last_active_at,
                    p.display_name,
                    p.birth_date,
                    p.avatar_path,
                    c.name AS city_name
             FROM chats AS ch
             INNER JOIN matches AS m ON m.id = ch.match_id
             INNER JOIN users AS other_u ON other_u.id = IF(m.user1_id = ?, m.user2_id, m.user1_id)
             INNER JOIN user_profiles AS p ON p.user_id = other_u.id
             LEFT JOIN cities AS c ON c.id = p.city_id
             WHERE ch.id = ?
               AND ch.is_active = 1
               AND (m.user1_id = ? OR m.user2_id = ?)
               AND other_u.status = "active"
             LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iiii', $currentUserId, $chatId, $currentUserId, $currentUserId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result) ?: null;
        mysqli_stmt_close($stmt);

        if ($row === null) {
            return null;
        }

        $row['avatar_url'] = public_url(profile_avatar_path((string)($row['avatar_path'] ?? '')));
        $row['age'] = profile_age((string)($row['birth_date'] ?? ''));
        $row['public_username'] = profile_public_username((string)($row['username'] ?? ''));
        $row['display_name'] = trim((string)($row['display_name'] ?? '')) !== '' ? (string)$row['display_name'] : (string)$row['public_username'];
        $row['city_name'] = trim((string)($row['city_name'] ?? '')) !== '' ? (string)$row['city_name'] : 'Город не указан';
        $row = chat_apply_online_status($row);

        return $row;
    }
}

if (!function_exists('chat_mark_read')) {
    function chat_mark_read(mysqli $conn, int $currentUserId, int $chatId): void
    {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE messages
             SET read_at = COALESCE(read_at, NOW())
             WHERE chat_id = ?
               AND sender_id <> ?
               AND read_at IS NULL
               AND deleted_at IS NULL'
        );
        mysqli_stmt_bind_param($stmt, 'ii', $chatId, $currentUserId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('chat_get_messages')) {
    function chat_get_messages(mysqli $conn, int $currentUserId, int $chatId, int $afterId = 0): array
    {
        if (chat_get_chat($conn, $currentUserId, $chatId) === null) {
            return [];
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT msg.id,
                    msg.sender_id,
                    msg.message_text,
                    msg.sent_at,
                    u.username,
                    p.display_name,
                    p.avatar_path
             FROM messages AS msg
             INNER JOIN users AS u ON u.id = msg.sender_id
             LEFT JOIN user_profiles AS p ON p.user_id = u.id
             WHERE msg.chat_id = ?
               AND msg.deleted_at IS NULL
               AND (? = 0 OR msg.id > ?)
             ORDER BY msg.sent_at ASC, msg.id ASC'
        );
        mysqli_stmt_bind_param($stmt, 'iii', $chatId, $afterId, $afterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        foreach ($rows as &$row) {
            $row['is_own'] = (int)$row['sender_id'] === $currentUserId;
            $row['avatar_url'] = public_url(profile_avatar_path((string)($row['avatar_path'] ?? '')));
            $row['display_name'] = trim((string)($row['display_name'] ?? '')) !== '' ? (string)$row['display_name'] : profile_public_username((string)($row['username'] ?? ''));
            $row['time_label'] = chat_time_label((string)$row['sent_at']);
            $row['date_label'] = chat_date_separator((string)$row['sent_at']);
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('chat_insert_message')) {
    function chat_insert_message(mysqli $conn, int $currentUserId, int $chatId, string $messageText): array
    {
        $chat = chat_get_chat($conn, $currentUserId, $chatId);
        if ($chat === null) {
            throw new RuntimeException('Чат недоступен.');
        }

        $targetUserId = (int)$chat['user_id'];
        $blockStmt = mysqli_prepare(
            $conn,
            'SELECT id FROM user_blocks
             WHERE (blocker_id = ? AND blocked_id = ?)
                OR (blocker_id = ? AND blocked_id = ?)
             LIMIT 1'
        );
        mysqli_stmt_bind_param($blockStmt, 'iiii', $currentUserId, $targetUserId, $targetUserId, $currentUserId);
        mysqli_stmt_execute($blockStmt);
        mysqli_stmt_store_result($blockStmt);
        $hasBlock = mysqli_stmt_num_rows($blockStmt) > 0;
        mysqli_stmt_close($blockStmt);

        if ($hasBlock) {
            throw new RuntimeException('Отправка сообщения недоступна.');
        }

        $messageText = trim($messageText);
        if ($messageText === '') {
            throw new RuntimeException('Введите текст сообщения.');
        }

        if (mb_strlen($messageText, 'UTF-8') > 1000) {
            throw new RuntimeException('Сообщение слишком длинное.');
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO messages (chat_id, sender_id, message_text, sent_at) VALUES (?, ?, ?, NOW())'
        );
        mysqli_stmt_bind_param($stmt, 'iis', $chatId, $currentUserId, $messageText);
        mysqli_stmt_execute($stmt);
        $messageId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $updateStmt = mysqli_prepare($conn, 'UPDATE chats SET last_message_at = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($updateStmt, 'i', $chatId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        return [
            'id' => $messageId,
            'sender_id' => $currentUserId,
            'message_text' => $messageText,
            'sent_at' => date('Y-m-d H:i:s'),
            'time_label' => date('H:i'),
            'date_label' => 'Сегодня',
            'is_own' => true,
        ];
    }
}

if (!function_exists('chat_report_message')) {
    function chat_report_message(mysqli $conn, int $currentUserId, int $messageId, int $reasonId, string $details): void
    {
        if ($messageId <= 0 || $reasonId <= 0) {
            throw new RuntimeException('Выберите причину жалобы.');
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT msg.sender_id, msg.chat_id
             FROM messages AS msg
             INNER JOIN chats AS ch ON ch.id = msg.chat_id AND ch.is_active = 1
             INNER JOIN matches AS m ON m.id = ch.match_id
             WHERE msg.id = ?
               AND msg.deleted_at IS NULL
               AND (m.user1_id = ? OR m.user2_id = ?)
             LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iii', $messageId, $currentUserId, $currentUserId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $message = mysqli_fetch_assoc($result) ?: null;
        mysqli_stmt_close($stmt);

        if ($message === null) {
            throw new RuntimeException('Сообщение недоступно.');
        }

        $reportedUserId = (int)$message['sender_id'];
        if ($reportedUserId === $currentUserId) {
            throw new RuntimeException('Нельзя пожаловаться на своё сообщение.');
        }

        $reasonStmt = mysqli_prepare($conn, 'SELECT id FROM report_reasons WHERE id = ? AND target_type = "message" AND is_active = 1 LIMIT 1');
        mysqli_stmt_bind_param($reasonStmt, 'i', $reasonId);
        mysqli_stmt_execute($reasonStmt);
        mysqli_stmt_store_result($reasonStmt);
        $reasonExists = mysqli_stmt_num_rows($reasonStmt) > 0;
        mysqli_stmt_close($reasonStmt);

        if (!$reasonExists) {
            throw new RuntimeException('Выберите корректную причину жалобы.');
        }

        $details = trim($details);
        if (mb_strlen($details, 'UTF-8') > 1200) {
            throw new RuntimeException('Комментарий слишком длинный.');
        }

        $insertStmt = mysqli_prepare(
            $conn,
            'INSERT INTO reports (author_id, reported_user_id, message_id, reason_id, details, status, created_at)
             VALUES (?, ?, ?, ?, ?, "new", NOW())'
        );
        mysqli_stmt_bind_param($insertStmt, 'iiiis', $currentUserId, $reportedUserId, $messageId, $reasonId, $details);
        mysqli_stmt_execute($insertStmt);
        mysqli_stmt_close($insertStmt);
    }
}

if (!function_exists('chat_get_message_report_reasons')) {
    function chat_get_message_report_reasons(mysqli $conn): array
    {
        if (!function_exists('db_table_exists') || !db_table_exists($conn, 'report_reasons')) {
            return [];
        }

        $result = mysqli_query($conn, 'SELECT id, name FROM report_reasons WHERE target_type = "message" AND is_active = 1 ORDER BY id');
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}


if (!function_exists('chat_profile_modal_payload')) {
    function chat_profile_modal_payload(mysqli $conn, int $currentUserId, int $targetUserId): ?array
    {
        if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
            return null;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT u.id,
                    u.username,
                    p.display_name,
                    p.birth_date,
                    p.avatar_path,
                    p.about,
                    p.communication_goal,
                    u.last_active_at,
                    c.name AS city_name
             FROM users AS u
             INNER JOIN user_profiles AS p ON p.user_id = u.id
             LEFT JOIN cities AS c ON c.id = p.city_id
             WHERE u.id = ? AND u.status = "active"
             LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'i', $targetUserId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result) ?: null;
        mysqli_stmt_close($stmt);

        if ($row === null) {
            return null;
        }

        $goalLabels = profile_goal_labels();
        $goalValues = profile_parse_goals($row['communication_goal'] ?? 'communication');
        if ($goalValues === []) {
            $goalValues = ['communication'];
        }

        return [
            'id' => (int)$row['id'],
            'display_name' => trim((string)($row['display_name'] ?? '')) !== '' ? (string)$row['display_name'] : profile_public_username((string)($row['username'] ?? '')),
            'username' => profile_public_username((string)($row['username'] ?? '')),
            'age' => profile_age((string)($row['birth_date'] ?? '')),
            'city_name' => trim((string)($row['city_name'] ?? '')) !== '' ? (string)$row['city_name'] : 'Город не указан',
            'avatar_url' => public_url(profile_avatar_path((string)($row['avatar_path'] ?? ''))),
            'about' => trim((string)($row['about'] ?? '')),
            'is_online' => chat_is_user_online((string)($row['last_active_at'] ?? '')),
            'online_label' => chat_is_user_online((string)($row['last_active_at'] ?? '')) ? 'В сети' : 'Оффлайн',
            'interests' => profile_get_interest_names($conn, $targetUserId),
            'goals' => array_values(array_map(static fn(string $goal): string => $goalLabels[$goal] ?? $goal, $goalValues)),
        ];
    }
}

if (!function_exists('chat_profiles_modal_payload')) {
    function chat_profiles_modal_payload(mysqli $conn, int $currentUserId, array $userIds): array
    {
        $uniqueIds = [];
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId > 0 && $userId !== $currentUserId) {
                $uniqueIds[$userId] = $userId;
            }
        }

        $items = [];
        foreach ($uniqueIds as $userId) {
            $profile = chat_profile_modal_payload($conn, $currentUserId, $userId);
            if ($profile !== null) {
                $items[] = $profile;
            }
        }

        return $items;
    }
}
