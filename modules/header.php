<?php
/* Путь до корня проекта: "." на главной и ".." на страницах из папки pages */
$basePath = $basePath ?? '.';
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<header class="header">
    <div class="header__container">
        <!-- Логотип -->
        <a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php"
            class="header__logo"
            aria-label="Перейти на главную страницу">
            <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/images/logo.svg"
                alt="МЭТЧ"
                class="header__logo-img">
        </a>

        <!-- Основная навигация -->
        <nav class="header__nav" aria-label="Основная навигация">
            <ul class="header__nav-list">
                <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#about">О сервисе</a></li>
                <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#features">Возможности</a></li>
                <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#how">Как это работает</a></li>
                <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#places">Места</a></li>
                <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#safety">Безопасность</a></li>
                <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#stories">Истории</a></li>
            </ul>
        </nav>

        <!-- Кнопки остаются отдельными на всех устройствах -->
        <div class="header__auth">
            <a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/pages/register.php"
                class="header__auth-button header__auth-button--register <?= $currentPage === 'register.php' ? 'is-active' : '' ?>"
                aria-label="Перейти к регистрации">
                <span class="header__auth-label header__auth-label--full">Регистрация</span>
            </a>

            <a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/pages/login.php"
                class="header__auth-button header__auth-button--login <?= $currentPage === 'login.php' ? 'is-active' : '' ?>">
                Войти
            </a>
        </div>

        <!-- Бургер открывает только навигационные ссылки -->
        <button class="header__burger"
            type="button"
            aria-label="Открыть меню"
            aria-expanded="false"
            aria-controls="headerMobileMenu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <nav class="header__mobile-menu"
        id="headerMobileMenu"
        aria-label="Мобильная навигация">
        <ul class="header__mobile-list">
            <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#about">О сервисе</a></li>
            <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#features">Возможности</a></li>
            <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#how">Как это работает</a></li>
            <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#places">Места</a></li>
            <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#safety">Безопасность</a></li>
            <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/index.php#stories">Истории</a></li>
        </ul>
    </nav>
</header>