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
?>
<footer class="footer footer--public footer__public_dif">
    <div class="footer__container">
        <a class="footer__logo" href="<?= e(public_url('index.php')) ?>" aria-label="На главную">
            <img src="<?= e(public_url('images/logo.svg')) ?>" alt="МЭТЧ">
        </a>

        <a href="<?= e(public_url('pages/legal.php')) ?>">Правовая информация</a>
        <a href="<?= e(public_url('pages/help.php')) ?>">Помощь</a>
        <a href="<?= e(public_url('pages/feedback.php')) ?>">Обратная связь</a>

        <p>Сотрудничество: partners@match.ru</p>

        <a class="footer__vk" href="https://vk.com" target="_blank" rel="noopener noreferrer" aria-label="VK">
            <img src="<?= e(public_url('images/ui/vk.svg')) ?>" alt="">
        </a>

        <p class="footer__copyright">© 2026 match</p>
    </div>
</footer>

<button class="back-to-top" type="button" aria-label="Наверх">
    <svg class="back-to-top__icon" aria-hidden="true">
        <use href="#icon-arrow-right"></use>
    </svg>
</button>