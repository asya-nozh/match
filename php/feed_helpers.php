<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_helpers.php';


if (!function_exists('plural_ru')) {
    function plural_ru(int $number, string $one, string $few, string $many): string
    {
        $number = abs($number) % 100;
        $last = $number % 10;

        if ($number > 10 && $number < 20) {
            return $many;
        }

        if ($last === 1) {
            return $one;
        }

        if ($last >= 2 && $last <= 4) {
            return $few;
        }

        return $many;
    }
}

if (!function_exists('feed_common_interest_count')) {
    function feed_common_interest_count(mysqli $conn, int $currentUserId, int $targetUserId): int
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*)
             FROM user_interests AS current_ui
             INNER JOIN user_interests AS target_ui
                ON target_ui.interest_id = current_ui.interest_id
             WHERE current_ui.user_id = ? AND target_ui.user_id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'ii', $currentUserId, $targetUserId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return (int)$count;
    }
}

if (!function_exists('feed_has_match')) {
    function feed_has_match(mysqli $conn, int $currentUserId, int $targetUserId): bool
    {
        $user1 = min($currentUserId, $targetUserId);
        $user2 = max($currentUserId, $targetUserId);

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM matches WHERE user1_id = ? AND user2_id = ? AND status = "active" LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'ii', $user1, $user2);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $exists;
    }
}

if (!function_exists('feed_profile_card_data')) {
    function feed_profile_card_data(mysqli $conn, int $currentUserId, array $row): array
    {
        $targetId = (int)$row['id'];
        $interestNames = profile_get_interest_names($conn, $targetId);
        $goals = profile_parse_goals($row['communication_goal'] ?? 'communication');
        if ($goals === []) {
            $goals = ['communication'];
        }

        $goalLabels = profile_goal_labels();
        $goalNames = [];
        foreach ($goals as $goal) {
            $goalNames[] = $goalLabels[$goal] ?? $goal;
        }

        $commonCount = (int)($row['common_interests'] ?? 0);
        $sameCity = (int)($row['same_city'] ?? 0);
        $sameGoal = (int)($row['same_goal'] ?? 0);
        $score = $commonCount * 3 + $sameCity + $sameGoal;
        $age = profile_age((string)($row['birth_date'] ?? ''));

        return [
            'id' => $targetId,
            'username' => profile_public_username((string)($row['username'] ?? '')),
            'display_name' => (string)($row['display_name'] ?? 'Пользователь'),
            'age' => $age,
            'city_name' => (string)($row['city_name'] ?? 'Город не указан'),
            'about' => trim((string)($row['about'] ?? '')),
            'avatar_url' => public_url(profile_avatar_path((string)($row['avatar_path'] ?? ''))),
            'interests' => $interestNames,
            'goals' => $goalNames,
            'goal_values' => $goals,
            'common_interests' => $commonCount,
            'same_city' => $sameCity === 1,
            'same_goal' => $sameGoal === 1,
            'score' => $score,
            'liked_by_me' => (int)($row['liked_by_me'] ?? 0) === 1,
            'liked_me' => (int)($row['liked_me'] ?? 0) === 1,
            'is_matched' => (int)($row['is_matched'] ?? 0) === 1,
        ];
    }
}

if (!function_exists('feed_get_profiles')) {
    function feed_get_profiles(mysqli $conn, int $currentUserId, int $limit = 30): array
    {
        $currentProfile = profile_get_by_user_id($conn, $currentUserId);
        $currentCityId = (int)($currentProfile['city_id'] ?? 0);
        $currentGoals = profile_parse_goals($currentProfile['communication_goal'] ?? 'communication');
        if ($currentGoals === []) {
            $currentGoals = ['communication'];
        }
        $currentGoalString = implode(',', $currentGoals);
        $limit = max(1, min($limit, 60));

        $sql = <<<SQL
SELECT u.id,
       u.username,
       p.display_name,
       p.birth_date,
       p.about,
       p.avatar_path,
       p.communication_goal,
       c.name AS city_name,
       COALESCE(SUM(CASE WHEN current_ui.interest_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS common_interests,
       CASE WHEN p.city_id IS NOT NULL AND p.city_id = ? THEN 1 ELSE 0 END AS same_city,
       CASE WHEN (
           (FIND_IN_SET('friendship', REPLACE(p.communication_goal, ' ', '')) > 0 AND FIND_IN_SET('friendship', ?) > 0)
           OR (FIND_IN_SET('communication', REPLACE(p.communication_goal, ' ', '')) > 0 AND FIND_IN_SET('communication', ?) > 0)
           OR (FIND_IN_SET('joint_activities', REPLACE(p.communication_goal, ' ', '')) > 0 AND FIND_IN_SET('joint_activities', ?) > 0)
       ) THEN 1 ELSE 0 END AS same_goal,
       CASE WHEN liked_by_me.id IS NULL THEN 0 ELSE 1 END AS liked_by_me,
       CASE WHEN liked_me.id IS NULL THEN 0 ELSE 1 END AS liked_me,
       CASE WHEN m.id IS NULL THEN 0 ELSE 1 END AS is_matched
FROM users AS u
INNER JOIN roles AS r ON r.id = u.role_id AND r.slug = 'participant'
INNER JOIN user_profiles AS p ON p.user_id = u.id
LEFT JOIN cities AS c ON c.id = p.city_id
LEFT JOIN user_interests AS target_ui ON target_ui.user_id = u.id
LEFT JOIN user_interests AS current_ui ON current_ui.user_id = ? AND current_ui.interest_id = target_ui.interest_id
LEFT JOIN user_blocks AS blocked_by_me ON blocked_by_me.blocker_id = ? AND blocked_by_me.blocked_id = u.id
LEFT JOIN user_blocks AS blocked_me ON blocked_me.blocker_id = u.id AND blocked_me.blocked_id = ?
LEFT JOIN likes AS liked_by_me ON liked_by_me.sender_id = ? AND liked_by_me.receiver_id = u.id
LEFT JOIN likes AS liked_me ON liked_me.sender_id = u.id AND liked_me.receiver_id = ?
LEFT JOIN matches AS m ON m.status = 'active'
    AND m.user1_id = LEAST(?, u.id)
    AND m.user2_id = GREATEST(?, u.id)
WHERE u.id <> ?
  AND u.status = 'active'
  AND p.is_visible = 1
  AND blocked_by_me.id IS NULL
  AND blocked_me.id IS NULL
GROUP BY u.id, u.username, p.display_name, p.birth_date, p.about, p.avatar_path, p.communication_goal, c.name, p.city_id, liked_by_me.id, liked_me.id, m.id
ORDER BY u.last_active_at DESC, u.id DESC
LIMIT ?
SQL;

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'isssiiiiiiiii',
            $currentCityId,
            $currentGoalString,
            $currentGoalString,
            $currentGoalString,
            $currentUserId,
            $currentUserId,
            $currentUserId,
            $currentUserId,
            $currentUserId,
            $currentUserId,
            $currentUserId,
            $currentUserId,
            $limit
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return array_map(static fn(array $row): array => feed_profile_card_data($conn, $currentUserId, $row), $rows);
    }
}

if (!function_exists('feed_get_connection_profiles')) {
    function feed_get_connection_profiles(mysqli $conn, int $currentUserId): array
    {
        $sections = [
            'matches' => [],
            'sent_likes' => [],
            'received_likes' => [],
            'blocked' => [],
            'reports' => [],
        ];

        $profileSelect = "u.id, u.username, p.display_name, p.birth_date, p.about, p.avatar_path, p.communication_goal, c.name AS city_name";
        $profileJoin = "INNER JOIN user_profiles AS p ON p.user_id = u.id LEFT JOIN cities AS c ON c.id = p.city_id";

        $queries = [
            'matches' => "SELECT $profileSelect, m.created_at AS action_date FROM matches AS m INNER JOIN users AS u ON u.id = IF(m.user1_id = ?, m.user2_id, m.user1_id) $profileJoin WHERE (m.user1_id = ? OR m.user2_id = ?) AND m.status = 'active' AND u.status <> 'deleted' ORDER BY m.created_at DESC",
            'sent_likes' => "SELECT $profileSelect, l.created_at AS action_date FROM likes AS l INNER JOIN users AS u ON u.id = l.receiver_id $profileJoin LEFT JOIN matches AS m ON m.status = 'active' AND m.user1_id = LEAST(l.sender_id, l.receiver_id) AND m.user2_id = GREATEST(l.sender_id, l.receiver_id) WHERE l.sender_id = ? AND m.id IS NULL AND u.status <> 'deleted' ORDER BY l.created_at DESC",
            'received_likes' => "SELECT $profileSelect, l.created_at AS action_date FROM likes AS l INNER JOIN users AS u ON u.id = l.sender_id $profileJoin LEFT JOIN likes AS my_like ON my_like.sender_id = ? AND my_like.receiver_id = l.sender_id WHERE l.receiver_id = ? AND my_like.id IS NULL AND u.status <> 'deleted' ORDER BY l.created_at DESC",
            'blocked' => "SELECT $profileSelect, b.created_at AS action_date FROM user_blocks AS b INNER JOIN users AS u ON u.id = b.blocked_id $profileJoin WHERE b.blocker_id = ? AND u.status <> 'deleted' ORDER BY b.created_at DESC",
            'reports' => "SELECT $profileSelect, rp.created_at AS action_date FROM reports AS rp INNER JOIN users AS u ON u.id = rp.reported_user_id $profileJoin WHERE rp.author_id = ? AND u.status <> 'deleted' ORDER BY rp.created_at DESC",
        ];

        foreach ($queries as $section => $sql) {
            $stmt = mysqli_prepare($conn, $sql);
            if ($section === 'matches') {
                mysqli_stmt_bind_param($stmt, 'iii', $currentUserId, $currentUserId, $currentUserId);
            } elseif ($section === 'received_likes') {
                mysqli_stmt_bind_param($stmt, 'ii', $currentUserId, $currentUserId);
            } else {
                mysqli_stmt_bind_param($stmt, 'i', $currentUserId);
            }

            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);

            foreach ($rows as $row) {
                $base = feed_profile_card_data($conn, $currentUserId, $row + [
                    'common_interests' => feed_common_interest_count($conn, $currentUserId, (int)$row['id']),
                    'same_city' => 0,
                    'same_goal' => 0,
                    'liked_by_me' => 0,
                    'liked_me' => 0,
                    'is_matched' => $section === 'matches' ? 1 : 0,
                ]);
                $base['action_date'] = (string)($row['action_date'] ?? '');
                if ($section === 'matches') {
                    $base['liked_by_me'] = true;
                    $base['liked_me'] = true;
                    $base['is_matched'] = true;
                } elseif ($section === 'sent_likes') {
                    $base['liked_by_me'] = true;
                    $base['liked_me'] = false;
                    $base['is_matched'] = false;
                } elseif ($section === 'received_likes') {
                    $base['liked_by_me'] = false;
                    $base['liked_me'] = true;
                    $base['is_matched'] = false;
                }
                $sections[$section][] = $base;
            }
        }

        return $sections;
    }
}
