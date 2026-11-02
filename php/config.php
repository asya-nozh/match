<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = (int)(getenv('DB_PORT') ?: 3306);
$dbName = getenv('DB_NAME') ?: 'match_project';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPassword = getenv('DB_PASSWORD') ?: '';

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    $message = 'Ошибка подключения к базе данных: ' . $exception->getMessage();

    if (function_exists('app_log')) {
        app_log($message);
    } else {
        error_log($message);
    }

    throw new RuntimeException('Не удалось подключиться к базе данных.', 0, $exception);
}
