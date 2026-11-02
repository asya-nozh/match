<?php
$basePath = '..';

require_once __DIR__ . '/../php/feed_helpers.php';
require_app_page();

$conn = auth_require_db();
$userId = (int)$_SESSION['user_id'];
if (function_exists('db_column_exists') && db_column_exists($conn, 'likes', 'viewed_at')) {
    $viewLikesStmt = mysqli_prepare($conn, 'UPDATE likes SET viewed_at = COALESCE(viewed_at, NOW()) WHERE receiver_id = ? AND viewed_at IS NULL');
    mysqli_stmt_bind_param($viewLikesStmt, 'i', $userId);
    mysqli_stmt_execute($viewLikesStmt);
    mysqli_stmt_close($viewLikesStmt);
}
$sections = feed_get_connection_profiles($conn, $userId);
$csrfToken = get_csrf_token('feed_action_csrf');

$pageTitle = 'Связи';
$pageDescription = 'Пользователи, с которыми вы взаимодействовали: лайки, мэтчи, жалобы и блокировки.';
$activePage = 'connections';

$sectionTitles = [
    'matches' => 'Мэтчи',
    'sent_likes' => 'Вы лайкнули',
    'received_likes' => 'Вас лайкнули',
    'blocked' => 'Заблокированные',
    'reports' => 'Отправленные жалобы',
];

$sectionEmpty = [
    'matches' => 'Пока нет взаимных лайков.',
    'sent_likes' => 'Вы пока никого не лайкнули.',
    'received_likes' => 'Пока никто не лайкнул ваш профиль.',
    'blocked' => 'Заблокированных пользователей нет.',
    'reports' => 'Вы пока не отправляли жалобы.',
];

$profilesForModal = [];
foreach ($sections as $items) {
    foreach ($items as $profile) {
        $profilesForModal[(string)$profile['id']] = $profile;
    }
}

function connection_like_class(array $profile): string
{
    if (!empty($profile['is_matched'])) {
        return ' is-matched';
    }
    if (!empty($profile['liked_by_me'])) {
        return ' is-liked';
    }
    if (!empty($profile['liked_me'])) {
        return ' is-incoming';
    }
    return '';
}

ob_start();
?>

<section class="connections-page" data-action-url="<?= e($basePath) ?>/php/feed_action.php" data-csrf="<?= e($csrfToken) ?>" data-chats-url="<?= e($basePath) ?>/pages/chats.php">
    <div class="connections-tabs" role="tablist" aria-label="Разделы связей">
        <?php $first = true; foreach ($sectionTitles as $key => $title): ?>
            <button class="connections-tab<?= $first ? ' is-active' : '' ?>" type="button" data-tab-target="<?= e($key) ?>">
                <?= e($title) ?>
                <span><?= e((string)count($sections[$key] ?? [])) ?></span>
            </button>
        <?php $first = false; endforeach; ?>
    </div>

    <?php $first = true; foreach ($sectionTitles as $key => $title): ?>
        <section class="connections-section<?= $first ? ' is-active' : '' ?>" data-tab-panel="<?= e($key) ?>">
            <h2><?= e($title) ?></h2>

            <?php if (empty($sections[$key])): ?>
                <div class="connections-empty"><?= e($sectionEmpty[$key]) ?></div>
            <?php else: ?>
                <div class="connections-list">
                    <?php foreach ($sections[$key] as $profile): ?>
                        <article class="connection-row" data-user-id="<?= e((string)$profile['id']) ?>">
                            <button class="connection-row__avatar-button" type="button" data-open-profile="<?= e((string)$profile['id']) ?>" aria-label="Открыть профиль">
                                <img class="connection-row__avatar" src="<?= e((string)$profile['avatar_url']) ?>" alt="Фото пользователя">
                            </button>

                            <div class="connection-row__body">
                                <h3><?= e((string)$profile['display_name']) ?><?= $profile['age'] !== null ? ', ' . e((string)$profile['age']) : '' ?></h3>
                                <p class="connection-row__username">@<?= e((string)$profile['username']) ?></p>
                                <p class="connection-row__city">
                                    <?= profile_svg_icon('location', 'connection-row__icon') ?>
                                    <span><?= e((string)$profile['city_name']) ?></span>
                                </p>
                                <?php if (!empty($profile['interests'])): ?>
                                    <div class="connection-row__chips">
                                        <?php foreach (array_slice((array)$profile['interests'], 0, 4) as $interest): ?>
                                            <span><?= e((string)$interest) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="connection-row__actions">
                                <?php if ($key === 'matches'): ?>
                                    <a class="connection-action connection-action--message" href="<?= e($basePath) ?>/pages/chats.php?user_id=<?= e((string)$profile['id']) ?>">
                                        <?= profile_svg_icon('chat', 'connection-action__icon') ?>
                                        <span>Написать</span>
                                    </a>
                                    <button class="connection-action connection-action--like<?= connection_like_class($profile) ?>" type="button" data-connection-action="like" data-user-id="<?= e((string)$profile['id']) ?>" aria-label="Убрать мэтч">
                                        <?= profile_svg_icon('heart', 'connection-action__icon') ?>
                                    </button>
                                <?php elseif ($key === 'sent_likes' || $key === 'received_likes'): ?>
                                    <button class="connection-action connection-action--like<?= connection_like_class($profile) ?>" type="button" data-connection-action="like" data-user-id="<?= e((string)$profile['id']) ?>" aria-label="Изменить лайк">
                                        <?= profile_svg_icon('heart', 'connection-action__icon') ?>
                                    </button>
                                <?php elseif ($key === 'blocked'): ?>
                                    <button class="connection-action connection-action--plain" type="button" data-connection-action="unblock" data-user-id="<?= e((string)$profile['id']) ?>">Разблокировать</button>
                                <?php else: ?>
                                    <span class="connection-row__note">Жалоба отправлена</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php $first = false; endforeach; ?>

    <script type="application/json" id="feedProfilesData"><?= json_encode(array_values($profilesForModal), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

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
</section>

<script src="<?= $basePath ?>/js/feed.js" defer></script>
<script>
document.querySelectorAll('.connections-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.tabTarget;
        document.querySelectorAll('.connections-tab').forEach((item) => item.classList.toggle('is-active', item === tab));
        document.querySelectorAll('.connections-section').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.tabPanel === target));
    });
});
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../modules/app-layout.php';
?>
