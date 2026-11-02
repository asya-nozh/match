<?php
$basePath = '..';

require_once __DIR__ . '/../php/places_helpers.php';
require_once __DIR__ . '/../php/admin-check.php';

$conn = auth_require_db();

if (!function_exists('e')) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$dashboardUserName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Сотрудник';
$dashboardUserRole = 'Администратор';
$logoutUrl = 'php/logout.php';
$pageTitle = 'Места';
$pageDescription = 'Управление местами, доступными пользователям.';
$activePage = 'places';
$dashboardHome = 'pages/admin-statistics.php';
$csrfToken = get_csrf_token('admin_places_csrf');

$navItems = [
    'statistics' => ['title' => 'Статистика', 'href' => 'pages/admin-statistics.php', 'icon' => 'nav-feed.svg'],
    'complaints' => ['title' => 'Жалобы', 'href' => 'pages/admin-complaints.php', 'icon' => 'nav-chats.svg'],
    'places' => ['title' => 'Места', 'href' => 'pages/admin-places.php', 'icon' => 'nav-places.svg'],
    'moderators' => ['title' => 'Модераторы', 'href' => 'pages/admin-moderators.php', 'icon' => 'nav-connections.svg'],
];

$places = places_get_all_for_admin($conn);
$cities = places_get_cities($conn);
$categories = places_get_categories($conn);
$success = (string)($_SESSION['admin_places_success'] ?? '');
$error = (string)($_SESSION['admin_places_error'] ?? '');
unset($_SESSION['admin_places_success'], $_SESSION['admin_places_error']);

ob_start();
?>

<section class="admin-places-page">
    <?php if ($success !== ''): ?>
        <div class="profile-alert profile-alert--success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="profile-alert profile-alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="admin-place-form" action="<?= e($basePath) ?>/php/admin_place_action.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="place_id" id="adminPlaceId" value="">
        <input type="hidden" name="current_image_path" id="adminPlaceCurrentImage" value="">

        <h2>Добавить или изменить место</h2>

        <div class="admin-place-form__grid">
            <label class="profile-form-label">
                <span>Название</span>
                <input class="form-field" type="text" name="name" id="adminPlaceName" required>
            </label>

            <label class="profile-form-label">
                <span>Город</span>
                <select class="form-field" name="city_id" id="adminPlaceCity" required>
                    <option value="">Выберите город</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?= e((string)$city['id']) ?>"><?= e((string)$city['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="profile-form-label">
                <span>Категория</span>
                <select class="form-field" name="category_id" id="adminPlaceCategory" required>
                    <option value="">Выберите категорию</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string)$category['id']) ?>"><?= e((string)$category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="profile-form-label">
                <span>Статус</span>
                <select class="form-field" name="status" id="adminPlaceStatus">
                    <option value="active">Активно</option>
                    <option value="hidden">Скрыто</option>
                </select>
            </label>

            <label class="profile-form-label admin-place-form__wide">
                <span>Адрес</span>
                <input class="form-field" type="text" name="address" id="adminPlaceAddress" required>
            </label>

            <label class="profile-form-label admin-place-form__wide">
                <span>Изображение</span>
                <label class="admin-place-file">
                    <input type="file" name="image_file" id="adminPlaceImageFile" accept="image/jpeg,image/png,image/webp">
                    <span>Выбрать изображение</span>
                    <small id="adminPlaceImageName">Файл не выбран</small>
                </label>
                <small class="admin-place-current-image" id="adminPlaceCurrentImageText"></small>
            </label>

            <label class="profile-form-label admin-place-form__wide">
                <span>Ссылка на официальный сайт</span>
                <input class="form-field" type="url" name="website_url" id="adminPlaceWebsite" placeholder="https://...">
            </label>

            <label class="profile-form-label admin-place-form__wide">
                <span>Описание</span>
                <textarea class="form-field" name="description" id="adminPlaceDescription" rows="3" required></textarea>
            </label>
        </div>

        <div class="admin-place-form__actions">
            <button class="btn btn--primary" type="submit">Сохранить место</button>
            <button class="btn btn--secondary" type="button" id="adminPlaceReset">Очистить форму</button>
        </div>
    </form>

    <div class="admin-places-table-wrap">
        <table class="admin-places-table">
            <thead>
                <tr>
                    <th>Место</th>
                    <th>Город</th>
                    <th>Категория</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($places as $place): ?>
                    <tr data-place-row
                        data-id="<?= e((string)$place['id']) ?>"
                        data-name="<?= e((string)$place['name']) ?>"
                        data-city-id="<?= e((string)$place['city_id']) ?>"
                        data-category-id="<?= e((string)$place['category_id']) ?>"
                        data-address="<?= e((string)$place['address']) ?>"
                        data-description="<?= e((string)$place['description']) ?>"
                        data-image-path="<?= e((string)($place['image_path'] ?? '')) ?>"
                        data-website-url="<?= e((string)($place['website_url'] ?? '')) ?>"
                        data-status="<?= e((string)$place['status']) ?>">

                        <td>
                            <strong><?= e((string)$place['name']) ?></strong>
                            <span><?= e((string)$place['address']) ?></span>
                        </td>

                        <td><?= e((string)$place['city_name']) ?></td>
                        <td><?= e((string)$place['category_name']) ?></td>

                        <td>
                            <span class="admin-place-status admin-place-status--<?= e((string)$place['status']) ?>">
                                <?= (string)$place['status'] === 'active' ? 'Активно' : 'Скрыто' ?>
                            </span>
                        </td>

                        <td>
                            <button class="admin-place-inline-button" type="button" data-edit-place>Изменить</button>

                            <form class="admin-place-delete-form" action="<?= e($basePath) ?>/php/admin_place_action.php" method="post" onsubmit="return confirm('Удалить это место?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="place_id" value="<?= e((string)$place['id']) ?>">
                                <button class="admin-place-inline-button admin-place-inline-button--danger" type="submit">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.admin-place-form');
    const resetButton = document.querySelector('#adminPlaceReset');

    const fields = {
        id: document.querySelector('#adminPlaceId'),
        name: document.querySelector('#adminPlaceName'),
        city: document.querySelector('#adminPlaceCity'),
        category: document.querySelector('#adminPlaceCategory'),
        address: document.querySelector('#adminPlaceAddress'),
        description: document.querySelector('#adminPlaceDescription'),
        currentImage: document.querySelector('#adminPlaceCurrentImage'),
        currentImageText: document.querySelector('#adminPlaceCurrentImageText'),
        imageFile: document.querySelector('#adminPlaceImageFile'),
        imageName: document.querySelector('#adminPlaceImageName'),
        website: document.querySelector('#adminPlaceWebsite'),
        status: document.querySelector('#adminPlaceStatus'),
    };

    document.querySelectorAll('[data-edit-place]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-place-row]');

            if (!row) return;

            fields.id.value = row.dataset.id || '';
            fields.name.value = row.dataset.name || '';
            fields.city.value = row.dataset.cityId || '';
            fields.category.value = row.dataset.categoryId || '';
            fields.address.value = row.dataset.address || '';
            fields.description.value = row.dataset.description || '';
            fields.currentImage.value = row.dataset.imagePath || '';
            fields.website.value = row.dataset.websiteUrl || '';
            fields.status.value = row.dataset.status || 'active';

            fields.imageFile.value = '';
            fields.imageName.textContent = 'Файл не выбран';

            if (row.dataset.imagePath) {
                fields.currentImageText.textContent = 'Текущее изображение: ' + row.dataset.imagePath;
            } else {
                fields.currentImageText.textContent = 'Изображение не загружено.';
            }

            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    fields.imageFile?.addEventListener('change', () => {
        const file = fields.imageFile.files[0];

        if (file) {
            fields.imageName.textContent = file.name;
        } else {
            fields.imageName.textContent = 'Файл не выбран';
        }
    });

    resetButton?.addEventListener('click', () => {
        form.reset();
        fields.id.value = '';
        fields.currentImage.value = '';
        fields.currentImageText.textContent = '';
        fields.imageName.textContent = 'Файл не выбран';
    });
});
</script>

<?php
$pageContent = ob_get_clean();

require_once __DIR__ . '/../modules/dashboard-layout.php';