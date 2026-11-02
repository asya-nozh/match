<?php

declare(strict_types=1);

$helpersPath = __DIR__ . '/helpers.php';
if (is_file($helpersPath)) {
    require_once $helpersPath;
}

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('public_url')) {
    function public_url(string $path): string
    {
        $basePath = $GLOBALS['basePath'] ?? '.';
        return rtrim((string)$basePath, '/') . '/' . ltrim($path, '/');
    }
}

$activePublicPage = $activePublicPage ?? '';
$session = $_SESSION ?? [];
$isAuth = isset($session['user_id']);
$roleSlug = (string)($session['role_slug'] ?? '');

$profileLink = match ($roleSlug) {
    'admin' => public_url('pages/admin-statistics.php'),
    'moderator' => public_url('pages/moderator-complaints.php'),
    default => public_url('pages/profile.php')
};
$profileLabel = in_array($roleSlug, ['admin', 'moderator'], true) ? 'Панель' : 'Профиль';
?>
<header class="header" id="siteHeader">
    <div class="header__container">
        <a class="header__logo" href="<?= e(public_url('index.php')) ?>" aria-label="На главную страницу">
            <img class="header__logo-img" src="<?= e(public_url('images/logo.svg')) ?>" alt="МЭТЧ">
        </a>

        <nav class="header__nav" aria-label="Основная навигация">
            <ul class="header__nav-list">
                <li><a href="<?= e(public_url('index.php#promo')) ?>">Главная</a></li>
                <li><a href="<?= e(public_url('index.php#about')) ?>">О приложении</a></li>
                <li><a href="<?= e(public_url('index.php#stories')) ?>">Истории</a></li>
            </ul>
        </nav>

        <div class="header__auth">
            <?php if ($isAuth): ?>
                <a class="header__auth-button header__auth-button--register" href="<?= e($profileLink) ?>"><?= e($profileLabel) ?></a>
                <a class="header__auth-button header__auth-button--login" href="<?= e(public_url('php/logout.php')) ?>">Выйти</a>
            <?php else: ?>
                <a class="header__auth-button header__auth-button--register" href="<?= e(public_url('pages/register.php')) ?>">
                    <span class="header__auth-label--full">Регистрация</span>
                    <span class="header__auth-label--short">Рег.</span>
                </a>
                <a class="header__auth-button header__auth-button--login" href="<?= e(public_url('pages/login.php')) ?>">Войти</a>
            <?php endif; ?>
        </div>

        <button class="header__burger" type="button" aria-label="Открыть меню" aria-controls="publicMobileMenu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div class="header__mobile-menu" id="publicMobileMenu">
        <ul class="header__mobile-list">
            <li><a href="<?= e(public_url('index.php#promo')) ?>">Главная</a></li>
            <li><a href="<?= e(public_url('index.php#about')) ?>">О приложении</a></li>
            <li><a href="<?= e(public_url('index.php#stories')) ?>">Истории</a></li>

            <?php if ($isAuth): ?>
                <li class="header__mobile-auth"><a class="header__auth-button header__auth-button--register" href="<?= e($profileLink) ?>"><?= e($profileLabel) ?></a></li>
                <li class="header__mobile-auth"><a class="header__auth-button header__auth-button--login" href="<?= e(public_url('php/logout.php')) ?>">Выйти</a></li>
            <?php else: ?>
                <li class="header__mobile-auth"><a class="header__auth-button header__auth-button--register" href="<?= e(public_url('pages/register.php')) ?>">Регистрация</a></li>
                <li class="header__mobile-auth"><a class="header__auth-button header__auth-button--login" href="<?= e(public_url('pages/login.php')) ?>">Войти</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>
