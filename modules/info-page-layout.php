<?php
$basePath = $basePath ?? '..';
$pageTitle = $pageTitle ?? 'Страница';
$metaDescription = $metaDescription ?? $pageTitle;
$heroBadge = $heroBadge ?? $pageTitle;
$heroTitle = $heroTitle ?? $pageTitle;
$heroText = $heroText ?? '';
$heroIcon = $heroIcon ?? '✦';
$pageContent = $pageContent ?? '';

$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — Мэтч</title>
<link rel="icon" href="<?= $safeBasePath ?>/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="<?= $safeBasePath ?>/css/template.css">
<link rel="stylesheet" href="<?= $safeBasePath ?>/css/info-pages.css">
</head>
<body>
<div class="info-page">
<header class="info-header">
<a class="info-logo" href="<?= $safeBasePath ?>/index.php" aria-label="На главную страницу Мэтч">
<img src="<?= $safeBasePath ?>/images/logo.svg" alt="Мэтч">
</a>
<button class="info-back" type="button" onclick="if(history.length>1){history.back()}else{location.href='<?= $safeBasePath ?>/index.php'}">← Назад</button>
</header>

<button class="info-scroll-top" type="button" id="infoScrollTopButton" aria-label="Наверх">
    <img src="<?= $safeBasePath ?>/images/ui/nav-up.svg" alt="">
</button>

<main class="info-main">
<section class="info-hero" data-icon="<?= htmlspecialchars($heroIcon, ENT_QUOTES, 'UTF-8') ?>">
<div class="info-hero__inner">
<span class="info-badge"><?= htmlspecialchars($heroBadge, ENT_QUOTES, 'UTF-8') ?></span>
<h1><?= htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8') ?></h1>
<?php if (trim($heroText) !== ''): ?>
<p><?= htmlspecialchars($heroText, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
</div>
</section>

<?= $pageContent ?>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
</div>
<script>
const infoScrollTopButton = document.getElementById('infoScrollTopButton');

if (infoScrollTopButton) {
    const toggleScrollTopButton = () => {
        infoScrollTopButton.classList.toggle('is-visible', window.scrollY > 300);
    };

    infoScrollTopButton.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    window.addEventListener('scroll', toggleScrollTopButton);
    toggleScrollTopButton();
}
</script>
</body>
</html>