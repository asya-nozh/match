<?php
$basePath = '..';

require_once __DIR__ . '/../php/feed_helpers.php';
require_app_page();

$conn = auth_require_db();
$userId = (int)$_SESSION['user_id'];
$profiles = feed_get_profiles($conn, $userId, 30);
$csrfToken = get_csrf_token('feed_action_csrf');
$goalLabels = profile_goal_labels();
$profileReportReasons = [];
$reasonResult = mysqli_query($conn, 'SELECT id, name FROM report_reasons WHERE target_type = "profile" AND is_active = 1 ORDER BY id');
if ($reasonResult) {
    $profileReportReasons = mysqli_fetch_all($reasonResult, MYSQLI_ASSOC);
}

$filterCities = [];
$filterInterests = [];
foreach ($profiles as $profile) {
    $city = trim((string)($profile['city_name'] ?? ''));
    if ($city !== '' && $city !== 'Город не указан') {
        $filterCities[$city] = $city;
    }
    foreach ((array)($profile['interests'] ?? []) as $interest) {
        $interest = trim((string)$interest);
        if ($interest !== '') {
            $filterInterests[$interest] = $interest;
        }
    }
}
ksort($filterCities, SORT_NATURAL | SORT_FLAG_CASE);
ksort($filterInterests, SORT_NATURAL | SORT_FLAG_CASE);

$pageTitle = 'Лента';
$pageDescription = 'Профили пользователей расположены по количеству совпадающих интересов.';
$activePage = 'feed';

ob_start();
?>

<section class="feed-page" data-action-url="<?= e($basePath) ?>/php/feed_action.php" data-csrf="<?= e($csrfToken) ?>" data-chats-url="<?= e($basePath) ?>/pages/chats.php">
    <div class="feed-filter-panel" aria-label="Фильтры ленты">
        <div class="feed-filter-line">
            <label class="feed-filter-pill">
                <?= profile_svg_icon('location', 'feed-filter-icon') ?>
                <select id="feedFilterCity" aria-label="Город">
                    <option value="">Город</option>
                    <?php foreach ($filterCities as $city): ?>
                        <option value="<?= e($city) ?>"><?= e($city) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="feed-filter-pill">
                <?= profile_svg_icon('confetti', 'feed-filter-icon') ?>
                <select id="feedFilterGoal" aria-label="Формат общения">
                    <option value="">Формат общения</option>
                    <?php foreach ($goalLabels as $goalValue => $goalLabel): ?>
                        <option value="<?= e((string)$goalValue) ?>"><?= e((string)$goalLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="feed-filter-pill feed-filter-pill--age">
                <?= profile_svg_icon('calendar', 'feed-filter-icon') ?>
                <input id="feedFilterAgeRange" type="text" inputmode="text" placeholder="20-26" aria-label="Возраст, например 20-26">
            </label>

            <label class="feed-filter-pill">
                <?= profile_svg_icon('sparkle', 'feed-filter-icon') ?>
                <select id="feedFilterInterest" aria-label="Интересы">
                    <option value="">Интересы</option>
                    <?php foreach ($filterInterests as $interest): ?>
                        <option value="<?= e($interest) ?>"><?= e($interest) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="feed-filter-search">
                <span>@</span>
                <input id="feedFilterSearch" type="search" placeholder="Никнейм" aria-label="Поиск по никнейму">
                <?= profile_svg_icon('search', 'feed-filter-search-icon') ?>
            </label>
        </div>

        <label class="feed-filter-profile-match">
            <input id="feedFilterProfileMatch" type="checkbox" checked>
            <span>подборка по информации из профиля</span>
        </label>
    </div>

    <div class="feed-info-box">
        <?= profile_svg_icon('sparkle', 'feed-info-icon') ?>
        <span>Выбирайте интересы, формат общения и находите единомышленников.</span>
    </div>

    <?php if ($profiles === []): ?>
        <div class="profile-empty-card">Пока нет подходящих профилей. Добавьте интересы в своём профиле или выполните SQL-файл с тестовыми пользователями.</div>
    <?php else: ?>
        <div class="feed-grid" id="feedGrid">
            <?php foreach ($profiles as $index => $item): ?>
                <?php
                    $cardStateClass = '';
                    if (!empty($item['is_matched'])) {
                        $cardStateClass = ' is-matched';
                    } elseif (!empty($item['liked_by_me'])) {
                        $cardStateClass = ' is-liked';
                    } elseif (!empty($item['liked_me'])) {
                        $cardStateClass = ' is-incoming';
                    }
                    $goalsRaw = implode('|', array_map('strval', (array)($item['goal_values'] ?? [])));
                    $interestsRaw = implode('|', array_map('strval', (array)($item['interests'] ?? [])));
                    $searchRaw = trim((string)$item['username'] . ' ' . (string)$item['display_name']);
                ?>
                <article class="feed-card<?= $cardStateClass ?>" data-user-id="<?= e((string)$item['id']) ?>" data-city="<?= e((string)$item['city_name']) ?>" data-age="<?= e((string)($item['age'] ?? '')) ?>" data-goals="<?= e($goalsRaw) ?>" data-interests="<?= e($interestsRaw) ?>" data-search="<?= e(mb_strtolower($searchRaw, 'UTF-8')) ?>" data-score="<?= e((string)($item['score'] ?? 0)) ?>" data-common="<?= e((string)($item['common_interests'] ?? 0)) ?>" data-order="<?= e((string)$index) ?>">
                    <div class="feed-card__top">
                        <img class="feed-card__avatar" src="<?= e((string)$item['avatar_url']) ?>" alt="Фото пользователя">
                        <div class="feed-card__info">
                            <h2><?= e((string)$item['display_name']) ?><?= $item['age'] !== null ? ', ' . e((string)$item['age']) : '' ?></h2>
                            <p class="feed-card__username">@<?= e((string)$item['username']) ?></p>
                            <p class="feed-card__city">
                                <?= profile_svg_icon('location', 'feed-inline-icon') ?>
                                <span><?= e($item['city_name'] !== '' ? (string)$item['city_name'] : 'Город не указан') ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="feed-card__divider" aria-hidden="true"></div>

                    <div class="feed-card__chips">
                        <?php foreach (array_slice((array)$item['interests'], 0, 3) as $interest): ?>
                            <span class="feed-chip">
                                <?= profile_svg_icon(profile_interest_icon_key((string)$interest), 'feed-chip__icon') ?>
                                <span><?= e((string)$interest) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <div class="feed-common-count">
                        <?= (int)($item['common_interests'] ?? 0) ?> <?= plural_ru((int)($item['common_interests'] ?? 0), 'общий интерес', 'общих интереса', 'общих интересов') ?>
                    </div>

                    <button class="feed-open-profile" type="button" data-open-profile="<?= e((string)$item['id']) ?>">Открыть профиль</button>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (count($profiles) > 3): ?>
            <button class="feed-load-more" type="button" id="feedLoadMore">Смотреть еще</button>
        <?php endif; ?>
    <?php endif; ?>

    <script type="application/json" id="feedProfilesData"><?= json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

    <div class="feed-modal" id="feedProfileModal" hidden>
        <div class="feed-modal__backdrop" data-close-modal></div>
        <article class="feed-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="feedModalName">
            <button class="feed-modal__close" type="button" data-close-modal aria-label="Закрыть">×</button>

            <div class="feed-modal__hero">
                <img class="feed-modal__avatar" id="feedModalAvatar" src="" alt="Фото пользователя">
                <div class="feed-modal__main">
                    <h2 id="feedModalName"></h2>
                    <p class="feed-modal__username" id="feedModalUsername"></p>
                    <p class="feed-modal__city" id="feedModalCity"></p>
                    <div class="feed-modal__about" id="feedModalAbout"></div>
                </div>
            </div>

            <div class="feed-modal__divider" aria-hidden="true"><span></span></div>

            <div class="feed-modal__section">
                <h3>Интересы</h3>
                <div class="feed-modal__chips" id="feedModalInterests"></div>
            </div>

            <div class="feed-modal__section">
                <h3>Формат общения</h3>
                <div class="feed-modal__chips" id="feedModalGoals"></div>
            </div>

            <div class="feed-modal__actions feed-modal__actions--top">
                <a class="feed-action feed-action--message is-disabled" id="feedModalMessage" href="<?= e($basePath) ?>/pages/chats.php" aria-disabled="true">
                    <?= profile_svg_icon('chat', 'feed-action__icon') ?>
                    <span>Написать</span>
                </a>

                <button class="feed-action feed-action--like" type="button" id="feedModalLike">
                    <?= profile_svg_icon('heart', 'feed-action__icon') ?>
                    <span>лайк</span>
                </button>

                <div class="feed-more">
                    <button class="feed-action feed-action--more" type="button" id="feedModalMore" aria-label="Дополнительные действия" aria-expanded="false">•••</button>
                    <div class="feed-more__menu" id="feedModalMoreMenu" hidden>
                        <button type="button" data-feed-action="report">Пожаловаться</button>
                        <button type="button" data-feed-action="block">Заблокировать</button>
                    </div>
                </div>
            </div>

            <div class="feed-modal__status" id="feedModalStatus" aria-live="polite"></div>
        </article>
    </div>

    <?php if ($profileReportReasons !== []): ?>
        <div class="chat-report-modal feed-report-modal" id="feedReportModal" hidden>
            <div class="chat-report-modal__backdrop" data-close-feed-report></div>
            <form class="chat-report-modal__dialog" id="feedReportForm">
                <button class="feed-modal__close" type="button" data-close-feed-report aria-label="Закрыть">×</button>
                <h2>Пожаловаться на профиль</h2>
                <p class="feed-report-modal__user" id="feedReportUser"></p>
                <label class="profile-form-label">
                    <span>Причина жалобы</span>
                    <select class="form-field" id="feedReportReason" required>
                        <option value="">Выберите причину</option>
                        <?php foreach ($profileReportReasons as $reason): ?>
                            <option value="<?= e((string)$reason['id']) ?>"><?= e((string)$reason['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="profile-form-label">
                    <span>Комментарий</span>
                    <textarea class="form-field" id="feedReportDetails" maxlength="1200" placeholder="Кратко опишите ситуацию"></textarea>
                </label>
                <div class="feed-report-form__actions">
                    <button class="btn btn--secondary" type="button" data-close-feed-report>Отмена</button>
                    <button class="btn btn--primary" type="submit">Отправить</button>
                </div>
                <div class="feed-modal__status" id="feedReportStatus" aria-live="polite"></div>
            </form>
        </div>
    <?php endif; ?>
</section>

<script src="<?= $basePath ?>/js/feed.js" defer></script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../modules/app-layout.php';
?>
