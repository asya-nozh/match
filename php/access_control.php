<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Глобальная карта доступа по ролям
 * + учет blocked статуса
 */
function canAccess(string $page, string $role, string $status): bool
{
    // 🔴 заблокированный пользователь
    if ($status === 'blocked') {
        return in_array($page, ['profile', 'profile_edit'], true);
    }

    // 🟢 гость (нет сессии)
    if ($role === '') {
        return in_array($page, [
            'public',
            'login',
            'register',
            'legal'
        ], true);
    }

    // 🟡 зарегистрированный пользователь
    if ($role === 'participant') {
        return !in_array($page, [
            'login',
            'register',
            'admin',
            'moderator'
        ], true);
    }

    // 🟠 модератор
    if ($role === 'moderator') {
        return in_array($page, [
            'public',
            'moderator_complaints',
            'moderator_appeals'
        ], true);
    }

    // 🔵 админ
    if ($role === 'admin') {
        return in_array($page, [
            'public',
            'admin_complaints',
            'admin_statistics',
            'admin_places',
            'admin_moderators'
        ], true);
    }

    return false;
}

/**
 * Проверка доступа
 */
function requirePageAccess(string $page): void
{
    $role = $_SESSION['role_slug'] ?? '';
    $status = $_SESSION['user_status'] ?? 'active';

    if (!canAccess($page, $role, $status)) {
        http_response_code(403);
        exit('Доступ запрещён');
    }
}
