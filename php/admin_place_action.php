<?php

declare(strict_types=1);

require_once __DIR__ . '/places_helpers.php';
require_once __DIR__ . '/admin-check.php';

$conn = auth_require_db();
$currentUserId = (int)$_SESSION['user_id'];

function admin_places_redirect(string $message = '', bool $isError = false): never
{
    if ($message !== '') {
        $_SESSION[$isError ? 'admin_places_error' : 'admin_places_success'] = $message;
    }

    header('Location: ../pages/admin-places.php');
    exit;
}

function admin_places_log(string $message): void
{
    if (function_exists('app_log')) {
        app_log($message);
        return;
    }

    error_log($message);
}

function admin_places_get_old_image(mysqli $conn, int $placeId): string
{
    if ($placeId <= 0) {
        return '';
    }

    $stmt = mysqli_prepare($conn, 'SELECT image_path FROM places WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $placeId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $imagePath);

    $oldImagePath = '';

    if (mysqli_stmt_fetch($stmt)) {
        $oldImagePath = (string)$imagePath;
    }

    mysqli_stmt_close($stmt);

    return $oldImagePath;
}

function admin_places_upload_image(): ?string
{
    if (!isset($_FILES['image_file']) || !is_array($_FILES['image_file'])) {
        return null;
    }

    $file = $_FILES['image_file'];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Не удалось загрузить изображение.');
    }

    $size = (int)($file['size'] ?? 0);

    if ($size <= 0) {
        throw new RuntimeException('Файл изображения пустой.');
    }

    if ($size > 5 * 1024 * 1024) {
        throw new RuntimeException('Размер изображения не должен превышать 5 МБ.');
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Файл загружен некорректно.');
    }

    $mime = '';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }

    if ($mime === '') {
        $imageInfo = getimagesize($tmpPath);
        $mime = is_array($imageInfo) ? (string)($imageInfo['mime'] ?? '') : '';
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException('Можно загружать только изображения JPG, PNG или WEBP.');
    }

    $uploadDir = __DIR__ . '/../assets/images/places';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        throw new RuntimeException('Не удалось создать папку для изображений.');
    }

    $fileName = 'place_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mime];
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Не удалось сохранить изображение.');
    }

    return 'assets/images/places/' . $fileName;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_places_redirect('Некорректный метод запроса.', true);
    }

    if (!check_csrf_token('admin_places_csrf', (string)($_POST['csrf_token'] ?? ''))) {
        admin_places_redirect('Сессия устарела. Обновите страницу.', true);
    }

    $action = (string)($_POST['action'] ?? '');
    $placeId = (int)($_POST['place_id'] ?? 0);

    if ($action === 'delete') {
        if ($placeId <= 0) {
            admin_places_redirect('Место не найдено.', true);
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM places WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $placeId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        admin_places_redirect('Место удалено.');
    }

    if ($action !== 'save') {
        admin_places_redirect('Неизвестное действие.', true);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $cityId = (int)($_POST['city_id'] ?? 0);
    $address = trim((string)($_POST['address'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $websiteUrl = trim((string)($_POST['website_url'] ?? ''));
    $status = (string)($_POST['status'] ?? 'active');

    if ($status !== 'active' && $status !== 'hidden') {
        $status = 'active';
    }

    if ($name === '' || $categoryId <= 0 || $cityId <= 0 || $address === '' || $description === '') {
        admin_places_redirect('Заполните название, город, категорию, адрес и описание.', true);
    }

    if ($websiteUrl !== '' && !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
        admin_places_redirect('Укажите корректную ссылку на официальный сайт.', true);
    }

    $oldImagePath = admin_places_get_old_image($conn, $placeId);
    $uploadedImagePath = admin_places_upload_image();
    $imagePath = $uploadedImagePath ?? $oldImagePath;

    if ($placeId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE places
             SET category_id = ?,
                 city_id = ?,
                 name = ?,
                 description = ?,
                 address = ?,
                 image_path = NULLIF(?, ""),
                 website_url = NULLIF(?, ""),
                 status = ?,
                 updated_at = NOW()
             WHERE id = ?'
        );

        mysqli_stmt_bind_param(
            $stmt,
            'iissssssi',
            $categoryId,
            $cityId,
            $name,
            $description,
            $address,
            $imagePath,
            $websiteUrl,
            $status,
            $placeId
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        admin_places_redirect('Место обновлено.');
    }

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO places (
            category_id,
            city_id,
            name,
            description,
            address,
            image_path,
            website_url,
            status,
            created_by_user_id,
            created_at,
            updated_at
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            NULLIF(?, ""),
            NULLIF(?, ""),
            ?,
            ?,
            NOW(),
            NOW()
        )'
    );

    mysqli_stmt_bind_param(
        $stmt,
        'iissssssi',
        $categoryId,
        $cityId,
        $name,
        $description,
        $address,
        $imagePath,
        $websiteUrl,
        $status,
        $currentUserId
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    admin_places_redirect('Место добавлено.');
} catch (Throwable $exception) {
    admin_places_log('Ошибка управления местом: ' . $exception->getMessage());
    admin_places_redirect($exception->getMessage(), true);
}