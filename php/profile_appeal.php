<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_helpers.php';
require_role([MATCH_ROLE_PARTICIPANT], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/profile.php');
    exit();
}

if (!check_csrf_token('profile_appeal_csrf', (string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['profile_appeal_error'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    header('Location: ../pages/profile.php');
    exit();
}

$conn = auth_require_db();
$userId = (int)($_SESSION['user_id'] ?? 0);
$text = trim((string)($_POST['appeal_text'] ?? ''));

if ($userId <= 0) {
    header('Location: ../pages/login.php');
    exit();
}

if ($text === '' || mb_strlen($text) > 2000) {
    $_SESSION['profile_appeal_error'] = 'Введите текст апелляции длиной до 2000 символов.';
    header('Location: ../pages/profile.php');
    exit();
}

$blockNotice = profile_get_active_block_notice($conn, $userId);
if ($blockNotice === null) {
    $_SESSION['profile_appeal_error'] = 'Сейчас для вашей учётной записи нет активной блокировки для подачи апелляции.';
    header('Location: ../pages/profile.php');
    exit();
}

$existingAppealId = (int)($blockNotice['appeal_id'] ?? 0);
if ($existingAppealId > 0) {
    $_SESSION['profile_appeal_error'] = 'Апелляция по этой блокировке уже была отправлена.';
    header('Location: ../pages/profile.php');
    exit();
}

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO appeals (decision_id, appellant_id, appeal_text, status, created_at)
     VALUES (?, ?, ?, 'new', NOW())"
);
$decisionId = (int)$blockNotice['decision_id'];
mysqli_stmt_bind_param($stmt, 'iis', $decisionId, $userId, $text);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$_SESSION['profile_appeal_success'] = 'Апелляция отправлена. Ожидайте решения модератора.';
header('Location: ../pages/profile.php');
exit();
