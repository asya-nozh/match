<?php

declare(strict_types=1);

require_once __DIR__ . '/../modules/helpers.php';
ensure_session();


if (!function_exists('auth_require_db')) {
    function auth_require_db(): mysqli
    {
        global $conn;

        if (!isset($conn) || !$conn instanceof mysqli) {
            require_once __DIR__ . '/config.php';
        }

        if (isset($conn) && $conn instanceof mysqli) {
            $GLOBALS['conn'] = $conn;
            return $conn;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            return $GLOBALS['conn'];
        }

        throw new RuntimeException('Не найдено подключение к базе данных. Проверьте файл php/config.php.');
    }
}

if (!defined('MATCH_ROLE_PARTICIPANT')) {
    define('MATCH_ROLE_PARTICIPANT', 'participant');
    define('MATCH_ROLE_MODERATOR', 'moderator');
    define('MATCH_ROLE_ADMIN', 'admin');
    define('MATCH_STATUS_ACTIVE', 'active');
    define('MATCH_STATUS_BLOCKED', 'blocked');
    define('MATCH_STATUS_DELETED', 'deleted');
}

if (!function_exists('auth_pages_url')) {
    function auth_pages_url(string $page): string
    {
        $scriptName = str_replace('\\\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

        if (str_contains($scriptName, '/php/')) {
            return '../pages/' . ltrim($page, '/');
        }

        if (str_contains($scriptName, '/pages/')) {
            return ltrim($page, '/');
        }

        return 'pages/' . ltrim($page, '/');
    }
}

if (!function_exists('auth_root_url')) {
    function auth_root_url(string $path = 'index.php'): string
    {
        $scriptName = str_replace('\\\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

        if (str_contains($scriptName, '/php/') || str_contains($scriptName, '/pages/')) {
            return '../' . ltrim($path, '/');
        }

        return ltrim($path, '/');
    }
}

if (!function_exists('auth_forget_user_session')) {
    function auth_forget_user_session(): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['role_id'],
            $_SESSION['role_slug'],
            $_SESSION['user_status'],
            $_SESSION['username'],
            $_SESSION['display_name']
        );
    }
}

if (!function_exists('auth_redirect')) {
    function auth_redirect(string $location): never
    {
        header('Location: ' . $location);
        exit();
    }
}

if (!function_exists('auth_login_url')) {
    function auth_login_url(): string
    {
        return auth_pages_url('login.php');
    }
}

if (!function_exists('auth_home_url')) {
    function auth_home_url(?string $roleSlug = null, ?string $status = null): string
    {
        $roleSlug = $roleSlug ?? (string)($_SESSION['role_slug'] ?? '');
        $status = $status ?? (string)($_SESSION['user_status'] ?? MATCH_STATUS_ACTIVE);

        if ($roleSlug === MATCH_ROLE_ADMIN && $status === MATCH_STATUS_ACTIVE) {
            return auth_pages_url('admin-statistics.php');
        }

        if ($roleSlug === MATCH_ROLE_MODERATOR && $status === MATCH_STATUS_ACTIVE) {
            return auth_pages_url('moderator-complaints.php');
        }

        if ($roleSlug === MATCH_ROLE_PARTICIPANT && $status === MATCH_STATUS_BLOCKED) {
            return auth_pages_url('profile.php');
        }

        if ($roleSlug === MATCH_ROLE_PARTICIPANT) {
            return auth_pages_url('profile.php');
        }

        return auth_root_url('index.php');
    }
}

if (!function_exists('auth_current_page_key')) {
    function auth_current_page_key(): string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        return pathinfo(basename($path), PATHINFO_FILENAME);
    }
}


if (!function_exists('auth_sync_user_restrictions')) {
    function auth_sync_user_restrictions(mysqli $conn, int $userId): void
    {
        if (!function_exists('db_table_exists') || !db_table_exists($conn, 'user_restrictions')) {
            return;
        }

        $expireStmt = mysqli_prepare(
            $conn,
            "UPDATE user_restrictions
             SET status = 'expired'
             WHERE user_id = ?
               AND status = 'active'
               AND ends_at IS NOT NULL
               AND ends_at <= NOW()"
        );
        mysqli_stmt_bind_param($expireStmt, 'i', $userId);
        mysqli_stmt_execute($expireStmt);
        mysqli_stmt_close($expireStmt);

        $activeBlockStmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*)
             FROM user_restrictions
             WHERE user_id = ?
               AND status = 'active'
               AND restriction_type IN ('temporary_block', 'permanent_block')
               AND (ends_at IS NULL OR ends_at > NOW())"
        );
        mysqli_stmt_bind_param($activeBlockStmt, 'i', $userId);
        mysqli_stmt_execute($activeBlockStmt);
        mysqli_stmt_bind_result($activeBlockStmt, $activeBlocksCount);
        mysqli_stmt_fetch($activeBlockStmt);
        mysqli_stmt_close($activeBlockStmt);

        if ((int)$activeBlocksCount > 0) {
            $updateStatusStmt = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET status = 'blocked'
                 WHERE id = ?
                   AND status <> 'deleted'"
            );
        } else {
            $updateStatusStmt = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET status = 'active'
                 WHERE id = ?
                   AND status = 'blocked'"
            );
        }

        mysqli_stmt_bind_param($updateStatusStmt, 'i', $userId);
        mysqli_stmt_execute($updateStatusStmt);
        mysqli_stmt_close($updateStatusStmt);
    }
}

if (!function_exists('auth_load_current_user')) {
    function auth_load_current_user(bool $required = true): ?array
    {
        if (empty($_SESSION['user_id'])) {
            if ($required) {
                auth_redirect(auth_login_url());
            }

            return null;
        }

        $userId = (int)$_SESSION['user_id'];
        $conn = auth_require_db();
        auth_sync_user_restrictions($conn, $userId);

        $stmt = mysqli_prepare(
            $conn,
            'SELECT u.id,
                    u.role_id,
                    u.username,
                    u.email,
                    u.status,
                    r.slug AS role_slug,
                    COALESCE(NULLIF(p.display_name, ""), u.username) AS display_name
             FROM users AS u
             INNER JOIN roles AS r ON r.id = u.role_id
             LEFT JOIN user_profiles AS p ON p.user_id = u.id
             WHERE u.id = ?
             LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result) ?: null;
        mysqli_stmt_close($stmt);

        if ($user === null || (string)$user['status'] === MATCH_STATUS_DELETED) {
            auth_forget_user_session();

            if ($required) {
                auth_redirect(auth_login_url());
            }

            return null;
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role_id'] = (int)$user['role_id'];
        $_SESSION['role_slug'] = (string)$user['role_slug'];
        $_SESSION['user_status'] = (string)$user['status'];
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['display_name'] = (string)$user['display_name'];

        if (in_array((string)$user['status'], [MATCH_STATUS_ACTIVE, MATCH_STATUS_BLOCKED], true)) {
            $updateStmt = mysqli_prepare($conn, 'UPDATE users SET last_active_at = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($updateStmt, 'i', $userId);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }

        return $user;
    }
}

if (!function_exists('require_guest_page')) {
    function require_guest_page(): void
    {
        $user = auth_load_current_user(false);

        if ($user !== null) {
            auth_redirect(auth_home_url((string)$user['role_slug'], (string)$user['status']));
        }
    }
}

if (!function_exists('require_app_page')) {
    function require_app_page(): void
    {
        $user = auth_load_current_user(true);
        $roleSlug = (string)$user['role_slug'];
        $status = (string)$user['status'];

        if ($roleSlug !== MATCH_ROLE_PARTICIPANT) {
            auth_redirect(auth_home_url($roleSlug, $status));
        }

        if (!in_array($status, [MATCH_STATUS_ACTIVE, MATCH_STATUS_BLOCKED], true)) {
            auth_forget_user_session();
            auth_redirect(auth_login_url());
        }

        if ($status === MATCH_STATUS_BLOCKED) {
            $allowedPages = ['profile', 'profile-edit', 'edit-profile'];
            $currentPage = auth_current_page_key();

            if (!in_array($currentPage, $allowedPages, true)) {
                $_SESSION['access_notice'] = 'Учётная запись заблокирована. Доступны только просмотр и редактирование личного профиля.';
                auth_redirect(auth_pages_url('profile.php'));
            }
        }
    }
}

if (!function_exists('require_admin_page')) {
    function require_admin_page(): void
    {
        $user = auth_load_current_user(true);

        if ((string)$user['role_slug'] !== MATCH_ROLE_ADMIN || (string)$user['status'] !== MATCH_STATUS_ACTIVE) {
            auth_redirect(auth_home_url((string)$user['role_slug'], (string)$user['status']));
        }
    }
}

if (!function_exists('require_moderator_page')) {
    function require_moderator_page(): void
    {
        $user = auth_load_current_user(true);

        if ((string)$user['role_slug'] !== MATCH_ROLE_MODERATOR || (string)$user['status'] !== MATCH_STATUS_ACTIVE) {
            auth_redirect(auth_home_url((string)$user['role_slug'], (string)$user['status']));
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(array $allowedRoles, bool $allowBlockedParticipant = false): void
    {
        $user = auth_load_current_user(true);
        $roleSlug = (string)$user['role_slug'];
        $status = (string)$user['status'];

        if (!in_array($roleSlug, $allowedRoles, true)) {
            auth_redirect(auth_home_url($roleSlug, $status));
        }

        if ($status !== MATCH_STATUS_ACTIVE && !($allowBlockedParticipant && $roleSlug === MATCH_ROLE_PARTICIPANT && $status === MATCH_STATUS_BLOCKED)) {
            auth_redirect(auth_home_url($roleSlug, $status));
        }
    }
}


if (!function_exists('requireRole')) {
    function requireRole(array $allowedRoles, bool $allowBlockedParticipant = false): void
    {
        require_role($allowedRoles, $allowBlockedParticipant);
    }
}

if (!function_exists('is_current_user_blocked')) {
    function is_current_user_blocked(): bool
    {
        return (string)($_SESSION['user_status'] ?? '') === MATCH_STATUS_BLOCKED;
    }
}
