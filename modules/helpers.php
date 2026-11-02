<?php

declare(strict_types=1);

if (!function_exists('app_log')) {
    function app_log(string $message): void
    {
        $logDir = dirname(__DIR__) . '/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logDir . '/app.log', $line, FILE_APPEND | LOCK_EX);
        error_log($message);
    }
}

if (!function_exists('ensure_session')) {
    function ensure_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('public_url')) {
    function public_url(string $path): string
    {
        $basePath = $GLOBALS['basePath'] ?? '.';
        return rtrim((string)$basePath, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('get_csrf_token')) {
    function get_csrf_token(string $key): string
    {
        ensure_session();

        if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION[$key];
    }
}

if (!function_exists('check_csrf_token')) {
    function check_csrf_token(string $key, string $token): bool
    {
        ensure_session();
        return isset($_SESSION[$key])
            && is_string($_SESSION[$key])
            && hash_equals((string)$_SESSION[$key], $token);
    }
}

if (!function_exists('redirect_with_errors')) {
    function redirect_with_errors(string $scope, array $errors, array $old, string $location): never
    {
        ensure_session();
        $_SESSION[$scope . '_errors'] = $errors;
        $_SESSION[$scope . '_old'] = $old;
        header('Location: ' . $location);
        exit();
    }
}

if (!function_exists('db_table_exists')) {
    function db_table_exists(mysqli $conn, string $tableName): bool
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        mysqli_stmt_bind_param($stmt, 's', $tableName);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return (int)$count > 0;
    }
}

if (!function_exists('db_column_exists')) {
    function db_column_exists(mysqli $conn, string $tableName, string $columnName): bool
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return (int)$count > 0;
    }
}
