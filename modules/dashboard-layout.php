<?php
$pageTitle = $pageTitle ?? 'Панель управления';
$pageDescription = $pageDescription ?? 'Управление веб-приложением «Мэтч».';
$pageToolbar = $pageToolbar ?? '';
$pageContent = $pageContent ?? '<div class="template-placeholder">Контент страницы</div>';
$basePath = $basePath ?? '..';

$dashboardHome = $dashboardHome ?? 'pages/admin-statistics.php';
$navItems = $navItems ?? [];

$dashboardUserName = $dashboardUserName ?? 'Администратор';
$dashboardUserRole = $dashboardUserRole ?? 'Администратор';
$logoutUrl = $logoutUrl ?? 'php/logout.php';

$currentPage = pathinfo(basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), PATHINFO_FILENAME);

$activePage = $activePage ?? $currentPage;
$metaDescription = trim(strip_tags($pageDescription));
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
    <link rel="stylesheet" href="<?= $safeBasePath ?>/css/dashboard.css">
    <script src="<?= $safeBasePath ?>/js/template.js" defer></script>
</head>
<body>
    <div class="site-page dashboard-page">
        <header class="mobile-header" id="mobileHeader">
            <a class="mobile-header__logo" href="<?= $safeBasePath ?>/<?= htmlspecialchars($dashboardHome, ENT_QUOTES, 'UTF-8') ?>" aria-label="В панель управления">
                <img src="<?= $safeBasePath ?>/images/logo.svg" alt="МЭТЧ">
            </a>
            <button class="burger" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobileMenu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </header>

        <div class="site-frame dashboard-frame">
            <aside class="sidebar dashboard-sidebar" aria-label="Навигация панели управления" id="mobileMenu">
                <a class="sidebar__logo" href="<?= $safeBasePath ?>/<?= htmlspecialchars($dashboardHome, ENT_QUOTES, 'UTF-8') ?>" aria-label="В панель управления">
                    <img src="<?= $safeBasePath ?>/images/logo.svg" alt="МЭТЧ">
                </a>

                <nav class="sidebar__nav" aria-label="Разделы панели управления">
                    <div class="sidebar__links">
                        <?php foreach ($navItems as $key => $item): ?>
                            <a class="sidebar__link<?= $activePage === $key ? ' is-active' : '' ?>"
                               href="<?= $safeBasePath ?>/<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                               <?= $activePage === $key ? 'aria-current="page"' : '' ?>>
                                <img src="<?= $safeBasePath ?>/images/ui/<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                <span><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>
            </aside>

            <button class="scroll-top-button" type="button" id="scrollTopButton" aria-label="Наверх">
                <img src="<?= $safeBasePath ?>/images/ui/nav-up.svg" alt="">
                <span>Наверх</span>
            </button>

            <main class="content-card dashboard-content-card">
                <section class="content-card__inner" aria-labelledby="pageTitle">
                    <header class="content-card__header dashboard-header">
    <div class="dashboard-header__text">
        <h1 id="pageTitle"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="dashboard-user">
        <div class="dashboard-user__info">
            <span class="dashboard-user__name"><?= htmlspecialchars($dashboardUserName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="dashboard-user__role"><?= htmlspecialchars($dashboardUserRole, ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <a class="dashboard-user__logout" href="<?= $safeBasePath ?>/<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>">
            Выйти
        </a>
    </div>

    <?php if (trim($pageToolbar) !== ''): ?>
        <div class="content-card__toolbar dashboard-header__toolbar">
            <?= $pageToolbar ?>
        </div>
    <?php endif; ?>
</header>

                    <div class="content-card__divider" aria-hidden="true"></div>

                    <div class="content-card__body">
                        <?= $pageContent ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>