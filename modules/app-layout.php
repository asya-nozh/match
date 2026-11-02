<?php
require_once __DIR__ . '/../php/auth.php';
require_app_page();

$accessNotice = (string)($_SESSION['access_notice'] ?? '');
unset($_SESSION['access_notice']);
$showBlockedAccountNotice = $showBlockedAccountNotice ?? true;
$blockedAccountNotice = $showBlockedAccountNotice && is_current_user_blocked()
    ? 'Учётная запись заблокирована. Вы можете просматривать и редактировать свой профиль, но остальные действия временно недоступны.'
    : '';

$pageTitle = $pageTitle ?? 'Заголовок';
$pageDescription = $pageDescription ?? 'Описание страницы';
$pageToolbar = $pageToolbar ?? '';
$pageToolbarInline = $pageToolbarInline ?? false;
$pageContent = $pageContent ?? '<div class="template-placeholder">Контент страницы</div>';
$basePath = $basePath ?? '..';

$currentPage = pathinfo(basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), PATHINFO_FILENAME);

$activePageAliases = [
    'profile-edit' => 'profile',
    'profile-view' => 'profile'
];

$activePage = $activePage ?? ($activePageAliases[$currentPage] ?? $currentPage);

$metaDescription = trim(strip_tags($pageDescription));

$navItems = [
    'profile' => ['title' => 'Профиль', 'href' => 'pages/profile.php', 'icon' => 'nav-profile.svg'],
    'feed' => ['title' => 'Лента', 'href' => 'pages/feed.php', 'icon' => 'nav-feed.svg'],
    'chats' => ['title' => 'Чаты', 'href' => 'pages/chats.php', 'icon' => 'nav-chats.svg'],
    'connections' => ['title' => 'Связи', 'href' => 'pages/connections.php', 'icon' => 'nav-connections.svg'],
    'places' => ['title' => 'Места', 'href' => 'pages/places.php', 'icon' => 'nav-places.svg'],
];

$isBlockedUser = is_current_user_blocked();

function app_nav_counter(mysqli $conn, string $sql, string $types, array $params): int
{
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return (int)$count;
}

$currentUserIdForBadges = (int)($_SESSION['user_id'] ?? 0);
$navBadges = [];

if ($currentUserIdForBadges > 0 && !$isBlockedUser) {
    $navBadges['chats'] = app_nav_counter(
        auth_require_db(),
        'SELECT COUNT(*)
         FROM messages AS msg
         INNER JOIN chats AS ch ON ch.id = msg.chat_id AND ch.is_active = 1
         INNER JOIN matches AS m ON m.id = ch.match_id
         WHERE (m.user1_id = ? OR m.user2_id = ?)
           AND msg.sender_id <> ?
           AND msg.read_at IS NULL
           AND msg.deleted_at IS NULL',
        'iii',
        [$currentUserIdForBadges, $currentUserIdForBadges, $currentUserIdForBadges]
    );

    $badgeConn = auth_require_db();
    $hasLikeViewedAt = function_exists('db_column_exists') && db_column_exists($badgeConn, 'likes', 'viewed_at');
    $connectionsBadgeSql = 'SELECT COUNT(*)
         FROM likes AS l
         LEFT JOIN matches AS m
           ON m.status = "active"
          AND m.user1_id = LEAST(l.sender_id, l.receiver_id)
          AND m.user2_id = GREATEST(l.sender_id, l.receiver_id)
         WHERE l.receiver_id = ?
           AND m.id IS NULL'
        . ($hasLikeViewedAt ? ' AND l.viewed_at IS NULL' : '');

    $navBadges['connections'] = app_nav_counter(
        $badgeConn,
        $connectionsBadgeSql,
        'i',
        [$currentUserIdForBadges]
    );
}

function app_nav_badge_markup(int $count): string
{
    if ($count <= 0) {
        return '';
    }

    $label = $count > 99 ? '99+' : (string)$count;
    return '<span class="sidebar__badge" aria-label="Новых событий: ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

$presenceToken = get_csrf_token('presence_csrf');

$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — МЭТЧ</title>
    <link rel="icon" href="<?= $safeBasePath ?>/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= $safeBasePath ?>/css/template.css">
    <script>window.MATCH_PRESENCE = {url: "<?= $safeBasePath ?>/php/presence.php", csrf: "<?= htmlspecialchars($presenceToken, ENT_QUOTES, 'UTF-8') ?>"};</script>
    <script src="<?= $safeBasePath ?>/js/template.js" defer></script>
</head>
<body>
    <div class="site-page">
        <header class="mobile-header" id="mobileHeader">
            <a class="mobile-header__logo" href="<?= $safeBasePath ?>/index.php" aria-label="На главную">
                <img src="<?= $safeBasePath ?>/images/logo.svg" alt="МЭТЧ">
            </a>
            <button class="burger" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobileMenu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </header>

        <div class="site-frame">
            <aside class="sidebar" aria-label="Основная навигация" id="mobileMenu">
                <a class="sidebar__logo" href="<?= $safeBasePath ?>/index.php" aria-label="На главную">
                    <img src="<?= $safeBasePath ?>/images/logo.svg" alt="МЭТЧ">
                </a>

                <nav class="sidebar__nav" aria-label="Разделы приложения">
                    <div class="sidebar__links">
                        <?php foreach ($navItems as $key => $item): ?>
                            <?php
                                $isActiveLink = $activePage === $key;
                                $isDisabledLink = $isBlockedUser && $key !== 'profile';
                                $linkClass = 'sidebar__link'
                                    . ($isActiveLink ? ' is-active' : '')
                                    . ($isDisabledLink ? ' is-disabled' : '');
                            ?>

                            <?php if ($isDisabledLink): ?>
                                <span class="<?= htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8') ?>" aria-disabled="true" title="Недоступно для заблокированной учётной записи">
                                    <img src="<?= $safeBasePath ?>/images/ui/<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                    <span><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?= app_nav_badge_markup((int)($navBadges[$key] ?? 0)) ?>
                                </span>
                            <?php else: ?>
                                <a class="<?= htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8') ?>"
                                   href="<?= $safeBasePath ?>/<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                                   <?= $isActiveLink ? 'aria-current="page"' : '' ?>>
                                    <img src="<?= $safeBasePath ?>/images/ui/<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                    <span><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?= app_nav_badge_markup((int)($navBadges[$key] ?? 0)) ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </nav>
            </aside>

            <button class="scroll-top-button" type="button" id="scrollTopButton" aria-label="Наверх">
                <img src="<?= $safeBasePath ?>/images/ui/nav-up.svg" alt="">
                <span>Наверх</span>
            </button>

            <main class="content-card">
                <section class="content-card__inner" aria-labelledby="pageTitle">
                    <header class="content-card__header">
                        <?php if ($pageToolbarInline && trim($pageToolbar) !== ''): ?>
                            <div class="content-card__title-row">
                                <h1 id="pageTitle"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                                <div class="content-card__toolbar content-card__toolbar--inline">
                                    <?= $pageToolbar ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <h1 id="pageTitle"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                        <?php endif; ?>

                        <p><?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?></p>

                        <?php if (!$pageToolbarInline && trim($pageToolbar) !== ''): ?>
                            <div class="content-card__toolbar">
                                <?= $pageToolbar ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <div class="content-card__divider" aria-hidden="true"></div>

                    <div class="content-card__body">
                        <?php if ($accessNotice !== ''): ?>
                            <div class="access-notice access-notice--warning">
                                <?= htmlspecialchars($accessNotice, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($blockedAccountNotice !== ''): ?>
                            <div class="access-notice access-notice--blocked">
                                <?= htmlspecialchars($blockedAccountNotice, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <?= $pageContent ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>