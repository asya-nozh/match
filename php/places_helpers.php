<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_helpers.php';


if (!function_exists('places_image_url')) {
    function places_image_url(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return public_url('images/coffee.png');
        }

        if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        $path = ltrim($path, '/');
        $root = dirname(__DIR__);

        if (file_exists($root . '/' . $path)) {
            return public_url($path);
        }

        if (str_starts_with($path, 'images/places/') && file_exists($root . '/assets/' . $path)) {
            return public_url('assets/' . $path);
        }

        return public_url($path);
    }
}

if (!function_exists('places_get_categories')) {
    function places_get_categories(mysqli $conn): array
    {
        $result = mysqli_query($conn, 'SELECT id, name FROM place_categories WHERE is_active = 1 ORDER BY name');
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

if (!function_exists('places_get_cities')) {
    function places_get_cities(mysqli $conn): array
    {
        $result = mysqli_query($conn, 'SELECT id, name FROM cities WHERE is_active = 1 ORDER BY name');
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

if (!function_exists('places_get_active')) {
    function places_get_active(mysqli $conn): array
    {
        $sql = 'SELECT p.id, p.name, p.description, p.address, p.image_path, p.website_url,
                       p.status, p.city_id, p.category_id,
                       c.name AS city_name, pc.name AS category_name
                FROM places AS p
                INNER JOIN cities AS c ON c.id = p.city_id
                INNER JOIN place_categories AS pc ON pc.id = p.category_id
                WHERE p.status = "active"
                ORDER BY p.updated_at DESC, p.id DESC';
        $result = mysqli_query($conn, $sql);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

if (!function_exists('places_get_all_for_admin')) {
    function places_get_all_for_admin(mysqli $conn): array
    {
        $sql = 'SELECT p.id, p.name, p.description, p.address, p.image_path, p.website_url,
                       p.status, p.city_id, p.category_id,
                       c.name AS city_name, pc.name AS category_name
                FROM places AS p
                INNER JOIN cities AS c ON c.id = p.city_id
                INNER JOIN place_categories AS pc ON pc.id = p.category_id
                ORDER BY p.updated_at DESC, p.id DESC';
        $result = mysqli_query($conn, $sql);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

if (!function_exists('places_get_place')) {
    function places_get_place(mysqli $conn, int $placeId): ?array
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT p.id, p.name, p.description, p.address, p.image_path, p.website_url,
                    p.status, p.city_id, p.category_id,
                    c.name AS city_name, pc.name AS category_name
             FROM places AS p
             INNER JOIN cities AS c ON c.id = p.city_id
             INNER JOIN place_categories AS pc ON pc.id = p.category_id
             WHERE p.id = ?
             LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'i', $placeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $place = mysqli_fetch_assoc($result) ?: null;
        mysqli_stmt_close($stmt);

        return $place;
    }
}

if (!function_exists('places_get_dialogs')) {
    function places_get_dialogs(mysqli $conn, int $currentUserId): array
    {
        $sql = 'SELECT ch.id AS chat_id,
                       u.id AS user_id,
                       u.username,
                       p.display_name,
                       p.birth_date,
                       p.avatar_path,
                       c.name AS city_name,
                       ch.last_message_at
                FROM chats AS ch
                INNER JOIN matches AS m ON m.id = ch.match_id
                INNER JOIN users AS u ON u.id = IF(m.user1_id = ?, m.user2_id, m.user1_id)
                INNER JOIN user_profiles AS p ON p.user_id = u.id
                LEFT JOIN cities AS c ON c.id = p.city_id
                WHERE ch.is_active = 1
                  AND (m.user1_id = ? OR m.user2_id = ?)
                  AND u.status = "active"
                ORDER BY COALESCE(ch.last_message_at, ch.created_at) DESC, ch.id DESC';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $currentUserId, $currentUserId, $currentUserId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        foreach ($rows as &$row) {
            $row['username_public'] = profile_public_username((string)$row['username']);
            $row['age'] = profile_age((string)$row['birth_date']);
            $row['avatar_url'] = public_url(profile_avatar_path((string)($row['avatar_path'] ?? '')));
            $row['city_name'] = (string)($row['city_name'] ?? 'Город не указан');
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('places_user_can_use_chat')) {
    function places_user_can_use_chat(mysqli $conn, int $currentUserId, int $chatId): bool
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT ch.id
             FROM chats AS ch
             INNER JOIN matches AS m ON m.id = ch.match_id
             WHERE ch.id = ? AND ch.is_active = 1 AND (m.user1_id = ? OR m.user2_id = ?)
             LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'iii', $chatId, $currentUserId, $currentUserId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $exists;
    }
}

if (!function_exists('places_invite_text')) {
    function places_invite_text(array $place): string
    {
        $name = trim((string)($place['name'] ?? 'место'));
        $city = trim((string)($place['city_name'] ?? ''));
        $address = trim((string)($place['address'] ?? ''));
        $placeId = (int)($place['id'] ?? 0);
        $link = 'pages/places.php?place_id=' . $placeId;

        $parts = ['Приглашаю сходить сюда: ' . $name];
        if ($city !== '') {
            $parts[] = $city;
        }
        if ($address !== '') {
            $parts[] = $address;
        }
        $parts[] = $link;

        return implode("\n", $parts);
    }
}
