<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_helpers.php';

require_role([MATCH_ROLE_PARTICIPANT], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/profile.php');
    exit;
}

$conn = auth_require_db();
$userId = (int)$_SESSION['user_id'];

$displayName = trim((string)($_POST['display_name'] ?? ''));
$birthDate = trim((string)($_POST['birth_date'] ?? ''));
$cityId = filter_input(INPUT_POST, 'city_id', FILTER_VALIDATE_INT) ?: null;
$communicationGoals = profile_parse_goals($_POST['communication_goals'] ?? $_POST['communication_goal'] ?? ['communication']);
if ($communicationGoals === []) {
    $communicationGoals = ['communication'];
}
$communicationGoal = implode(',', $communicationGoals);
$about = trim((string)($_POST['about'] ?? ''));
$interestsText = trim((string)($_POST['interests_text'] ?? ''));
$interestNames = (array)($_POST['interest_names'] ?? []);
$aiPayloadRaw = trim((string)($_POST['interest_payload'] ?? $_POST['ai_interests_payload'] ?? ''));
$csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
$errors = [];
$old = $_POST;
$old['communication_goals'] = $communicationGoals;

if (!check_csrf_token('profile_edit_csrf', $csrfToken)) {
    $errors['csrf'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
}

if ($displayName === '' || mb_strlen($displayName, 'UTF-8') > 80) {
    $errors['display_name'] = 'Укажите имя длиной до 80 символов.';
}

if ($birthDate === '') {
    $errors['birth_date'] = 'Укажите дату рождения.';
} else {
    $birthDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    $birthDateErrors = DateTimeImmutable::getLastErrors();

    if (!$birthDateObject || (is_array($birthDateErrors) && ($birthDateErrors['warning_count'] > 0 || $birthDateErrors['error_count'] > 0))) {
        $errors['birth_date'] = 'Укажите корректную дату рождения.';
    } else {
        $minBirthDate = new DateTimeImmutable('1900-01-01');
        $maxBirthDate = (new DateTimeImmutable('today'))->modify('-18 years');

        if ($birthDateObject < $minBirthDate) {
            $errors['birth_date'] = 'Укажите корректную дату рождения.';
        } elseif ($birthDateObject > $maxBirthDate) {
            $errors['birth_date'] = 'Редактирование доступно пользователям от 18 лет.';
        }
    }
}


if ($about !== '' && (mb_strlen($about, 'UTF-8') > 800 || ai_has_unsafe_content($about))) {
    $errors['about'] = 'Поле «О себе» содержит недопустимый контент или превышает 800 символов.';
}

if ($interestsText !== '' && (mb_strlen($interestsText, 'UTF-8') > 1500 || ai_has_unsafe_content($interestsText))) {
    $errors['interests_text'] = 'Описание интересов содержит недопустимый контент или превышает 1500 символов.';
}

if ($cityId !== null) {
    $cityStmt = mysqli_prepare($conn, 'SELECT id FROM cities WHERE id = ? AND is_active = 1 LIMIT 1');
    mysqli_stmt_bind_param($cityStmt, 'i', $cityId);
    mysqli_stmt_execute($cityStmt);
    mysqli_stmt_store_result($cityStmt);
    if (mysqli_stmt_num_rows($cityStmt) === 0) {
        $errors['city_id'] = 'Выберите город из списка.';
    }
    mysqli_stmt_close($cityStmt);
}

if ($errors !== []) {
    redirect_with_errors('profile', $errors, $old, '../pages/profile-edit.php');
}

$currentProfile = profile_get_by_user_id($conn, $userId);
$currentAvatarPath = (string)($currentProfile['avatar_path'] ?? '');
$avatarPath = $currentAvatarPath !== '' ? $currentAvatarPath : null;

try {
    if (isset($_FILES['avatar']) && is_array($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($_FILES['avatar']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Не удалось загрузить фотографию.');
        }

        if ((int)($_FILES['avatar']['size'] ?? 0) > 3 * 1024 * 1024) {
            throw new RuntimeException('Фотография должна быть не больше 3 МБ.');
        }

        $tmpName = (string)($_FILES['avatar']['tmp_name'] ?? '');
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($tmpName);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($allowed[$mimeType])) {
            throw new RuntimeException('Можно загрузить только JPG, PNG или WEBP.');
        }

        $uploadDir = dirname(__DIR__) . '/images/avatars';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $fileName = 'avatar_' . $userId . '_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mimeType];
        $targetPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Не удалось сохранить фотографию.');
        }

        $avatarPath = 'images/avatars/' . $fileName;
    }

    $decodedPayload = [];
    if ($aiPayloadRaw !== '') {
        $decoded = json_decode($aiPayloadRaw, true);
        if (is_array($decoded)) {
            $decodedPayload = ai_normalize_result($decoded);
        }
    }

    mysqli_begin_transaction($conn);

    if (profile_has_interests_text_column($conn)) {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE user_profiles
             SET display_name = ?, birth_date = ?, city_id = ?, communication_goal = ?, about = ?, interests_text = ?, avatar_path = ?, completed_at = NOW(), updated_at = NOW()
             WHERE user_id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'ssissssi', $displayName, $birthDate, $cityId, $communicationGoal, $about, $interestsText, $avatarPath, $userId);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE user_profiles
             SET display_name = ?, birth_date = ?, city_id = ?, communication_goal = ?, about = ?, avatar_path = ?, completed_at = NOW(), updated_at = NOW()
             WHERE user_id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'ssisssi', $displayName, $birthDate, $cityId, $communicationGoal, $about, $avatarPath, $userId);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    profile_sync_user_interests($conn, $userId, $interestNames, $decodedPayload);

    mysqli_commit($conn);

    unset($_SESSION['profile_errors'], $_SESSION['profile_old']);
    $_SESSION['profile_success'] = 'Профиль обновлён.';

    header('Location: ../pages/profile.php');
    exit;
} catch (Throwable $exception) {
    if (isset($conn)) {
        mysqli_rollback($conn);
    }

    if (function_exists('app_log')) {
        app_log('Ошибка обновления профиля: ' . $exception->getMessage());
    }

    redirect_with_errors('profile', ['profile' => $exception->getMessage()], $old, '../pages/profile-edit.php');
}
