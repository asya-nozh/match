<?php
$basePath = '..';

require_once __DIR__ . '/../php/places_helpers.php';
require_app_page();

$conn = auth_require_db();
$userId = (int)$_SESSION['user_id'];
$places = places_get_active($conn);
$cities = places_get_cities($conn);
$categories = places_get_categories($conn);
$dialogs = places_get_dialogs($conn, $userId);
$csrfToken = get_csrf_token('place_action_csrf');

$pageTitle = 'Места';
$pageDescription = 'Выбирайте места для совместных встреч и новых впечатлений.';
$activePage = 'places';

ob_start();
?>

<section class="places-page" data-action-url="<?= e($basePath) ?>/php/place_action.php" data-csrf="<?= e($csrfToken) ?>">
    <div class="places-filter-panel" aria-label="Фильтры мест">
        <div class="places-filter-row">
            <label class="places-filter-pill">
                <?= profile_svg_icon('location', 'places-filter-icon') ?>
                <span class="places-filter-pill__text">
                    <small>Город</small>
                    <select id="placesFilterCity" aria-label="Город">
                        <option value="">Все города</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= e((string)$city['id']) ?>"><?= e((string)$city['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </span>
            </label>

            <label class="places-filter-pill">
                <?= profile_svg_icon('sparkle', 'places-filter-icon') ?>
                <span class="places-filter-pill__text">
                    <small>Категория</small>
                    <select id="placesFilterCategory" aria-label="Категория">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string)$category['id']) ?>"><?= e((string)$category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </span>
            </label>

            <label class="places-search">
                <?= profile_svg_icon('search', 'places-search__icon') ?>
                <input id="placesSearch" type="search" placeholder="Поиск мест" aria-label="Поиск мест">
            </label>
        </div>
    </div>

    <?php if ($places === []): ?>
        <div class="profile-empty-card">Места пока не добавлены.</div>
    <?php else: ?>
        <div class="places-grid" id="placesGrid">
            <?php foreach ($places as $place): ?>
                <?php
                $imagePath = trim((string)($place['image_path'] ?? ''));
                $imageUrl = places_image_url($imagePath);
                $search = mb_strtolower(trim((string)$place['name'] . ' ' . (string)$place['description'] . ' ' . (string)$place['address'] . ' ' . (string)$place['city_name'] . ' ' . (string)$place['category_name']), 'UTF-8');
                ?>
                <article class="place-card"
    data-place-id="<?= e((string)$place['id']) ?>"
    data-city-id="<?= e((string)$place['city_id']) ?>"
    data-category-id="<?= e((string)$place['category_id']) ?>"
    data-search="<?= e($search) ?>">

    <div class="place-card__image-wrap">
        <img
            class="place-card__image"
            src="<?= e($imageUrl) ?>"
            alt="<?= e((string)$place['name']) ?>"
            onerror="this.onerror=null;this.src='<?= e(public_url('images/coffee.png')) ?>';"
        >
        <span class="place-card__category"><?= e((string)$place['category_name']) ?></span>
    </div>

    <div class="place-card__body">
        <?php if (trim((string)($place['website_url'] ?? '')) !== ''): ?>
            <h2>
                <a class="place-card__title-link" href="<?= e((string)$place['website_url']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= e((string)$place['name']) ?>
                </a>
            </h2>
        <?php else: ?>
            <h2><?= e((string)$place['name']) ?></h2>
        <?php endif; ?>

        <p class="place-card__meta">
            <?= profile_svg_icon('location', 'place-card__meta-icon') ?>
            <span><?= e((string)$place['city_name']) ?></span>
        </p>

        <p class="place-card__meta place-card__address">
            <span><?= e((string)$place['address']) ?></span>
        </p>

        <p class="place-card__description"><?= e((string)$place['description']) ?></p>

        <button class="place-card__invite" type="button" data-open-place-invite="<?= e((string)$place['id']) ?>">
            пригласить
        </button>
    </div>
</article>
            <?php endforeach; ?>
        </div>
        <div class="places-empty-state" id="placesEmptyState" hidden>
            <strong>Ой! Пока что таких мест нет.</strong>
            <span>Возможно, они появятся позже.</span>
        </div>
        <button class="places-load-more btn btn--secondary" type="button" id="placesLoadMore" hidden>Смотреть еще</button>
    <?php endif; ?>

    <script type="application/json" id="placesData">
        <?= json_encode($places, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    </script>

    <div class="place-invite-modal" id="placeInviteModal" hidden>
        <div class="place-invite-modal__backdrop" data-close-place-invite></div>
        <article class="place-invite-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="placeInviteTitle">
            <button class="feed-modal__close" type="button" data-close-place-invite aria-label="Закрыть">×</button>
            <h2 id="placeInviteTitle">Пригласить в место</h2>
            <p class="place-invite-modal__place" id="placeInviteName"></p>

            <?php if ($dialogs === []): ?>
                <div class="profile-empty-card profile-empty-card--small">У вас пока нет диалогов. Диалог появится после взаимного лайка.</div>
            <?php else: ?>
                <div class="place-dialog-list" id="placeDialogList">
                    <?php foreach ($dialogs as $dialog): ?>
                        <div class="place-dialog-item" data-dialog-search="<?= e(mb_strtolower((string)$dialog['display_name'] . ' ' . (string)$dialog['username_public'], 'UTF-8')) ?>">
                            <img src="<?= e((string)$dialog['avatar_url']) ?>" alt="Фото пользователя">
                            <div>
                                <strong><?= e((string)$dialog['display_name']) ?><?= $dialog['age'] !== null ? ', ' . e((string)$dialog['age']) : '' ?></strong>
                                <span>@<?= e((string)$dialog['username_public']) ?></span>
                            </div>
                            <button type="button" data-invite-chat="<?= e((string)$dialog['chat_id']) ?>">Отправить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="feed-modal__status" id="placeInviteStatus" aria-live="polite"></div>
        </article>
    </div>
</section>

<script src="<?= $basePath ?>/js/places.js" defer></script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../modules/app-layout.php';
?>