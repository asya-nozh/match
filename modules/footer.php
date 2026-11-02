<?php
/* Путь от текущей страницы до корня проекта */
$basePath = $basePath ?? '.';
$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
?>

<footer class="footer">
    <div class="footer__container">
        <a class="footer__logo" href="<?= $safeBasePath ?>/index.php" aria-label="На главную">
            <img src="<?= $safeBasePath ?>/images/logo.svg" alt="МЭТЧ">
        </a>

        <a href="<?= $safeBasePath ?>/pages/legal.php">Правовая информация</a>
        <a href="<?= $safeBasePath ?>/pages/help.php">Помощь</a>
        <a href="<?= $safeBasePath ?>/pages/feedback.php">Обратная связь</a>

        <p>Сотрудничество: partners@match.ru</p>

        <a class="footer__vk" href="https://vk.com" target="_blank" rel="noopener noreferrer" aria-label="VK">
            <img src="<?= $safeBasePath ?>/images/ui/vk.svg" alt="">
        </a>

        <p class="footer__copyright">© 2026 match</p>
    </div>
</footer>