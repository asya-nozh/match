<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai_interest_helpers.php';

if (!function_exists('profile_goal_labels')) {
    function profile_goal_labels(): array
    {
        return [
            'friendship' => 'Прогулки',
            'communication' => 'Разговоры',
            'joint_activities' => 'Совместный досуг'
        ];
    }
}


if (!function_exists('profile_public_username')) {
    function profile_public_username(string $username): string
    {
        $map = [
            'demo_liza' => 'liza_laugh',
            'demo_anya' => 'anya_moss',
            'demo_masha' => 'maris_wave07',
            'demo_dasha' => 'dasha_stars42',
            'demo_kirill' => 'kir_neon81',
            'demo_vera' => 'vera_latte36',
            'demo_timur' => 'timur_route59',
            'demo_mikhail' => 'misha_pixel64',
            'demo_sonya' => 'sonya_cinnamon73',
            'demo_polina' => 'polina_pages28',
            'demo_artem' => 'art_orange91',
            'demo_nika' => 'nika_maple55',
            'demo_rita' => 'rita_willow39',
            'lilac_liza24' => 'liza_laugh',
            'anya_morning18' => 'anya_moss',
            'maris_wave07' => 'maris_wave07',
            'dasha_stars42' => 'dasha_stars42',
            'kir_neon81' => 'kir_neon81',
            'vera_latte36' => 'vera_latte36',
            'timur_route59' => 'timur_route59',
            'misha_pixel64' => 'misha_pixel64',
            'sonya_cinnamon73' => 'sonya_cinnamon73',
            'polina_pages28' => 'polina_pages28',
            'art_orange91' => 'art_orange91',
            'nika_maple55' => 'nika_maple55',
            'rita_willow39' => 'rita_willow39',
        ];

        return $map[$username] ?? $username;
    }
}

if (!function_exists('profile_parse_goals')) {
    function profile_parse_goals(mixed $value): array
    {
        $valid = array_keys(profile_goal_labels());
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } else {
            $raw = trim((string)$value);

            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/[,;|]+/u', $raw) ?: [];
            }
        }

        $result = [];
        foreach ($items as $item) {
            $goal = trim((string)$item);
            if (in_array($goal, $valid, true) && !in_array($goal, $result, true)) {
                $result[] = $goal;
            }
        }

        return $result;
    }
}

if (!function_exists('profile_goal_labels_for_value')) {
    function profile_goal_labels_for_value(mixed $value): string
    {
        $labels = profile_goal_labels();
        $goals = profile_parse_goals($value);

        if ($goals === []) {
            return 'Разговоры';
        }

        return implode(', ', array_map(static fn(string $goal): string => $labels[$goal] ?? $goal, $goals));
    }
}

if (!function_exists('profile_age')) {
    function profile_age(?string $birthDate): ?int
    {
        if (!$birthDate) {
            return null;
        }

        try {
            $birth = new DateTimeImmutable($birthDate);
            $today = new DateTimeImmutable('today');
            return (int)$birth->diff($today)->y;
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('profile_avatar_path')) {
    function profile_avatar_path(?string $path): string
    {
        $path = trim((string)$path);

        if ($path === '' || preg_match('/^(https?:)?\/\//i', $path) === 1) {
            return 'images/user-icon.png';
        }

        return ltrim($path, '/');
    }
}

if (!function_exists('profile_get_cities')) {
    function profile_get_cities(mysqli $conn): array
    {
        $result = mysqli_query($conn, 'SELECT id, name FROM cities WHERE is_active = 1 ORDER BY name');
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

if (!function_exists('profile_has_interests_text_column')) {
    function profile_has_interests_text_column(mysqli $conn): bool
    {
        return function_exists('db_column_exists') && db_column_exists($conn, 'user_profiles', 'interests_text');
    }
}

if (!function_exists('profile_get_interest_names')) {
    function profile_get_interest_names(mysqli $conn, int $userId): array
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT i.name
             FROM user_interests AS ui
             INNER JOIN interests AS i ON i.id = ui.interest_id
             WHERE ui.user_id = ? AND i.is_active = 1
             ORDER BY i.name'
        );
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $items = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return array_values(array_map(static fn(array $row): string => (string)$row['name'], $items));
    }
}

if (!function_exists('profile_get_ai_topics')) {
    function profile_get_ai_topics(mysqli $conn, int $userId): array
    {
        if (!function_exists('db_table_exists') || !db_table_exists($conn, 'user_ai_interests')) {
            return ['interests' => [], 'anti_interests' => []];
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT i.name, uai.topic, uai.type
             FROM user_ai_interests AS uai
             LEFT JOIN interests AS i ON i.id = uai.interest_id
             WHERE uai.user_id = ?
             ORDER BY uai.type, i.name, uai.topic'
        );
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = ['interests' => [], 'anti_interests' => []];

        while ($row = mysqli_fetch_assoc($result)) {
            $block = (string)$row['type'] === 'anti_interest' ? 'anti_interests' : 'interests';
            $name = (string)($row['name'] ?? '');
            $topic = (string)($row['topic'] ?? '');

            if ($name === '') {
                continue;
            }

            if (!isset($data[$block][$name])) {
                $data[$block][$name] = ['name' => $name, 'topics' => []];
            }

            if ($topic !== '') {
                $data[$block][$name]['topics'][] = $topic;
            }
        }

        mysqli_stmt_close($stmt);

        foreach (['interests', 'anti_interests'] as $block) {
            $data[$block] = array_values($data[$block]);
        }

        return $data;
    }
}

if (!function_exists('profile_get_by_user_id')) {
    function profile_get_by_user_id(mysqli $conn, int $userId): array
    {
        $selectInterestsText = profile_has_interests_text_column($conn)
            ? 'p.interests_text'
            : 'NULL AS interests_text';

        $stmt = mysqli_prepare(
            $conn,
            'SELECT u.id,
                    u.username,
                    u.email,
                    u.status,
                    p.display_name,
                    p.birth_date,
                    p.city_id,
                    p.communication_goal,
                    p.about,
                    p.avatar_path,
                    p.is_visible,
                    ' . $selectInterestsText . ',
                    c.name AS city_name
             FROM users AS u
             INNER JOIN user_profiles AS p ON p.user_id = u.id
             LEFT JOIN cities AS c ON c.id = p.city_id
             WHERE u.id = ?
             LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $profile = mysqli_fetch_assoc($result) ?: [];
        mysqli_stmt_close($stmt);

        if ($profile === []) {
            return [];
        }

        $profile['age'] = profile_age((string)$profile['birth_date']);
        $profile['interests'] = profile_get_interest_names($conn, $userId);
        $profile['ai_topics'] = profile_get_ai_topics($conn, $userId);
        $profile['analysis_topics'] = $profile['ai_topics'];

        return $profile;
    }
}

if (!function_exists('profile_find_or_create_interest')) {
    function profile_find_or_create_interest(mysqli $conn, string $name): ?int
    {
        $name = ai_normalize_category($name) ?: trim($name);
        $name = mb_substr($name, 0, 100, 'UTF-8');

        if ($name === '' || ai_has_unsafe_content($name)) {
            return null;
        }

        $stmt = mysqli_prepare($conn, 'SELECT id FROM interests WHERE LOWER(name) = LOWER(?) LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $name);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $id);

        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            return (int)$id;
        }

        mysqli_stmt_close($stmt);

        $category = 'Профиль';
        $insertStmt = mysqli_prepare($conn, 'INSERT INTO interests (name, category, is_active) VALUES (?, ?, 1)');
        mysqli_stmt_bind_param($insertStmt, 'ss', $name, $category);
        mysqli_stmt_execute($insertStmt);
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($insertStmt);

        return $newId > 0 ? $newId : null;
    }
}

if (!function_exists('profile_sync_user_interests')) {
    function profile_sync_user_interests(mysqli $conn, int $userId, array $interestNames, array $aiPayload = []): void
    {
        $cleanNames = [];

        foreach ($interestNames as $name) {
            $normalized = ai_normalize_category((string)$name) ?: trim((string)$name);

            if ($normalized === '' || ai_has_unsafe_content($normalized)) {
                continue;
            }

            $cleanNames[$normalized] = $normalized;
        }

        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM user_interests WHERE user_id = ?');
        mysqli_stmt_bind_param($deleteStmt, 'i', $userId);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        $insertStmt = mysqli_prepare($conn, 'INSERT IGNORE INTO user_interests (user_id, interest_id, created_at) VALUES (?, ?, NOW())');

        $interestIdsByName = [];

        foreach ($cleanNames as $name) {
            $interestId = profile_find_or_create_interest($conn, $name);
            if ($interestId === null) {
                continue;
            }

            $interestIdsByName[$name] = $interestId;
            mysqli_stmt_bind_param($insertStmt, 'ii', $userId, $interestId);
            mysqli_stmt_execute($insertStmt);
        }

        mysqli_stmt_close($insertStmt);

        if (!function_exists('db_table_exists') || !db_table_exists($conn, 'user_ai_interests')) {
            return;
        }

        $deleteAiStmt = mysqli_prepare($conn, 'DELETE FROM user_ai_interests WHERE user_id = ?');
        mysqli_stmt_bind_param($deleteAiStmt, 'i', $userId);
        mysqli_stmt_execute($deleteAiStmt);
        mysqli_stmt_close($deleteAiStmt);

        if ($aiPayload === []) {
            return;
        }

        $insertAiStmt = mysqli_prepare(
            $conn,
            'INSERT INTO user_ai_interests (user_id, interest_id, type, topic, created_at) VALUES (?, ?, ?, ?, NOW())'
        );

        foreach (['interests' => 'interest', 'anti_interests' => 'anti_interest'] as $block => $type) {
            if (empty($aiPayload[$block]) || !is_array($aiPayload[$block])) {
                continue;
            }

            foreach ($aiPayload[$block] as $item) {
                $name = ai_normalize_category((string)($item['name'] ?? $item['category'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $interestId = $interestIdsByName[$name] ?? profile_find_or_create_interest($conn, $name);
                if ($interestId === null) {
                    continue;
                }

                $topics = $item['topics'] ?? [$name];
                if (!is_array($topics) || $topics === []) {
                    $topics = [$name];
                }

                foreach ($topics as $topic) {
                    $topic = trim((string)$topic);
                    $topic = mb_substr($topic, 0, 100, 'UTF-8');

                    if ($topic === '' || ai_has_unsafe_content($topic)) {
                        continue;
                    }

                    mysqli_stmt_bind_param($insertAiStmt, 'iiss', $userId, $interestId, $type, $topic);
                    mysqli_stmt_execute($insertAiStmt);
                }
            }
        }

        mysqli_stmt_close($insertAiStmt);
    }
}

if (!function_exists('profile_interest_icon_key')) {
    function profile_interest_icon_key(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');

        if (str_contains($name, 'книг') || str_contains($name, 'чтен') || str_contains($name, 'литератур')) {
            return 'book';
        }

        if (str_contains($name, 'прогул') || str_contains($name, 'путеше') || str_contains($name, 'спорт') || str_contains($name, 'актив')) {
            return 'walk';
        }

        if (str_contains($name, 'разговор') || str_contains($name, 'общен') || str_contains($name, 'друз') || str_contains($name, 'знаком')) {
            return 'chat';
        }

        if (str_contains($name, 'кофе') || str_contains($name, 'кафе') || str_contains($name, 'еда')) {
            return 'cup';
        }

        if (str_contains($name, 'музык') || str_contains($name, 'концерт') || str_contains($name, 'песн')) {
            return 'music';
        }

        if (str_contains($name, 'кино') || str_contains($name, 'фильм') || str_contains($name, 'сериал')) {
            return 'film';
        }

        return 'sparkle';
    }
}

if (!function_exists('profile_goal_icon_key')) {
    function profile_goal_icon_key(string $goal): string
    {
        return match ($goal) {
            'friendship' => 'heart',
            'joint_activities' => 'walk',
            default => 'chat',
        };
    }
}

if (!function_exists('profile_svg_icon')) {
    function profile_svg_icon(string $icon, string $class = 'profile-inline-icon'): string
    {
        $paths = [
            'book' => '<path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H20v15.5H7.5A2.5 2.5 0 0 0 5 21V5.5Zm0 0V21m2.5-3.5H20M8 6.5h8M8 9.5h7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'walk' => '<path d="M13 4.8a2 2 0 1 0-2-2 2 2 0 0 0 2 2Zm-1.1 2.1 2.5 2.5 2.8.7M11.9 6.9 9 9.8l-.9 3.2m4.1-3.8-1.1 4.1 3.4 2.5m-4.2-2.1-2.1 5.1m7-2.7 2.5 3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'chat' => '<path d="M5 6.5h14a2 2 0 0 1 2 2v6.2a2 2 0 0 1-2 2H11l-5 3v-3H5a2 2 0 0 1-2-2V8.5a2 2 0 0 1 2-2Zm3.3 4.8h.1m3.6 0h.1m3.6 0h.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'heart' => '<path d="M12 20.2 4.7 13.1a4.8 4.8 0 0 1 6.8-6.8l.5.6.5-.6a4.8 4.8 0 0 1 6.8 6.8L12 20.2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
            'cup' => '<path d="M5.5 7h10v5.5a4.5 4.5 0 0 1-4.5 4.5H10a4.5 4.5 0 0 1-4.5-4.5V7Zm10 2h1.2a2.3 2.3 0 0 1 0 4.6h-1.2M7 20h9M8 4h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'music' => '<path d="M10 17.5a2.5 2.5 0 1 1-2.5-2.5H10V5l8-1.5v10a2.5 2.5 0 1 1-2.5-2.5H18V6.5L10 8v9.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'film' => '<path d="M5 5h14v14H5V5Zm0 4h14M8 5v14m8-14v14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'calendar' => '<path d="M7 3v3m10-3v3M5 8.5h14M6.5 5h11A2.5 2.5 0 0 1 20 7.5v10A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-10A2.5 2.5 0 0 1 6.5 5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'lock' => '<path d="M7 10V8a5 5 0 0 1 10 0v2m-9.5 0h9A1.5 1.5 0 0 1 18 11.5v6A1.5 1.5 0 0 1 16.5 19h-9A1.5 1.5 0 0 1 6 17.5v-6A1.5 1.5 0 0 1 7.5 10Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'location' => '<path d="M12 21s6.5-5.3 6.5-11A6.5 6.5 0 1 0 5.5 10C5.5 15.7 12 21 12 21Zm0-8.7a2.3 2.3 0 1 0 0-4.6 2.3 2.3 0 0 0 0 4.6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'sparkle' => '<path d="M12 3.5 13.8 9l5.7 1.8-5.7 1.8L12 18l-1.8-5.4-5.7-1.8L10.2 9 12 3.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
            'confetti' => '<path d="M5 19 9.5 7.5 16.5 14.5 5 19Zm7.5-11.5 2-2m2.5 5.5 3-1m-9-5.5V2.5m7.5 3.5 1.8-1.8M15.7 18.2l2.1 2.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'search' => '<path d="m20 20-4.2-4.2m1.7-5.3a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        ];

        $path = $paths[$icon] ?? $paths['sparkle'];
        $safeClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

        return '<svg class="' . $safeClass . '" viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
    }
}


if (!function_exists('profile_notice_location_label')) {
    function profile_notice_location_label(?int $messageId): string
    {
        return $messageId !== null && $messageId > 0 ? 'в сообщениях' : 'в профиле';
    }
}

if (!function_exists('profile_get_active_block_notice')) {
    function profile_get_active_block_notice(mysqli $conn, int $userId): ?array
    {
        if (!function_exists('db_table_exists') || !db_table_exists($conn, 'user_restrictions')) {
            return null;
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                ur.id AS restriction_id,
                ur.decision_id,
                ur.restriction_type,
                ur.starts_at,
                ur.ends_at,
                md.decision_type,
                md.comment AS moderator_comment,
                r.id AS report_id,
                r.message_id,
                r.details,
                rr.name AS reason_name,
                a.id AS appeal_id,
                a.status AS appeal_status,
                a.appeal_text,
                a.review_comment,
                a.created_at AS appeal_created_at,
                a.reviewed_at AS appeal_reviewed_at
             FROM user_restrictions AS ur
             INNER JOIN moderation_decisions AS md ON md.id = ur.decision_id
             INNER JOIN reports AS r ON r.id = md.report_id
             LEFT JOIN report_reasons AS rr ON rr.id = r.reason_id
             LEFT JOIN appeals AS a ON a.decision_id = ur.decision_id AND a.appellant_id = ur.user_id
             WHERE ur.user_id = ?
               AND ur.status = 'active'
               AND ur.restriction_type IN ('temporary_block', 'permanent_block')
               AND (ur.ends_at IS NULL OR ur.ends_at > NOW())
             ORDER BY ur.starts_at DESC
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $notice = mysqli_fetch_assoc($result) ?: null;
        mysqli_stmt_close($stmt);

        if ($notice === null) {
            return null;
        }

        $notice['location_label'] = profile_notice_location_label(isset($notice['message_id']) ? (int)$notice['message_id'] : null);
        return $notice;
    }
}

if (!function_exists('profile_get_latest_warning_notice')) {
    function profile_get_latest_warning_notice(mysqli $conn, int $userId): ?array
    {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                md.id AS decision_id,
                md.comment AS moderator_comment,
                md.created_at,
                r.id AS report_id,
                r.message_id,
                r.details,
                rr.name AS reason_name
             FROM moderation_decisions AS md
             INNER JOIN reports AS r ON r.id = md.report_id
             LEFT JOIN report_reasons AS rr ON rr.id = r.reason_id
             WHERE r.reported_user_id = ?
               AND md.decision_type = 'warning'
             ORDER BY md.created_at DESC
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $notice = mysqli_fetch_assoc($result) ?: null;
        mysqli_stmt_close($stmt);

        if ($notice === null) {
            return null;
        }

        $notice['location_label'] = profile_notice_location_label(isset($notice['message_id']) ? (int)$notice['message_id'] : null);
        return $notice;
    }
}

