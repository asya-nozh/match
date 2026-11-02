<?php

declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
require_guest_page();

$errors = $_SESSION['login_errors'] ?? [];
$oldLogin = (string)($_SESSION['login_old']['login'] ?? '');
$successMessage = (string)($_SESSION['success_message'] ?? '');

unset($_SESSION['login_errors'], $_SESSION['login_old'], $_SESSION['success_message']);

$initialMode = str_contains($oldLogin, '@') ? 'email' : 'username';
$basePath = '..';
$activePublicPage = 'login';
$csrfToken = get_csrf_token('csrf_login');
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация | МЭТЧ</title>
    <link rel="icon" href="../images/logo.svg" type="image/png">
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/public.css?v=9">
</head>

<body class="auth-body">
    <?php include __DIR__ . '/../modules/icons.php'; ?>
    <?php include __DIR__ . '/../modules/public-header.php'; ?>

    <main class="auth-page">
        <section class="auth-card" aria-labelledby="loginTitle">
            <div class="auth-intro">
                <div class="auth-intro__text">
                    <h2 class="auth-intro__title">
                        <span class="decor-letter">Т</span>воё пространство<br>
                        для настоящих связей
                    </h2>

                    <p class="auth-intro__description">
                        Общайся, делись историями<br>
                        и находи своих людей рядом.
                    </p>
                </div>

                <div class="auth-illustration" aria-hidden="true">
                    <img class="auth-illustration__image" src="../images/reg-auth-illustration.png" alt="">
                </div>

                <div class="auth-benefits">
                    <div class="auth-benefit">
                        <span class="auth-benefit__icon">
                            <svg aria-hidden="true">
                                <use href="#icon-heart"></use>
                            </svg>
                        </span>
                        <div>
                            <h3>Для дружбы и общения</h3>
                            <p>Найдите близких по духу людей</p>
                        </div>
                    </div>

                    <div class="auth-benefit">
                        <span class="auth-benefit__icon">
                            <svg aria-hidden="true">
                                <use href="#icon-shield"></use>
                            </svg>
                        </span>
                        <div>
                            <h3>Безопасно и конфиденциально</h3>
                            <p>Мы бережём ваши данные</p>
                        </div>
                    </div>

                    <div class="auth-benefit">
                        <span class="auth-benefit__icon">
                            <svg aria-hidden="true">
                                <use href="#icon-location"></use>
                            </svg>
                        </span>
                        <div>
                            <h3>Интересные места рядом</h3>
                            <p>Открывайте новое вместе</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-form-section">
                <div class="auth-form-section__head">
                    <h1 class="auth-form-section__title" id="loginTitle">
                        <span class="decor-letter">А</span>вторизация
                    </h1>
                    <p>Войди в свой МЭТЧ</p>
                </div>

                <?php if ($successMessage !== ''): ?>
                    <div class="form-message form-message--success">
                        <?= e($successMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['account'])): ?>
                    <div class="form-message form-message--error">
                        <?= e((string)$errors['account']) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['hint'])): ?>
                    <div class="form-message form-message--hint">
                        <?= e((string)$errors['hint']) ?>
                    </div>
                <?php endif; ?>

                <div class="login-mode" data-initial-mode="<?= e($initialMode) ?>">
                    <button type="button"
                        class="login-mode__button <?= $initialMode === 'email' ? 'is-active' : '' ?>"
                        data-login-mode="email">
                        <svg aria-hidden="true">
                            <use href="#icon-mail"></use>
                        </svg>
                        <span>Почта</span>
                    </button>

                    <button type="button"
                        class="login-mode__button <?= $initialMode === 'username' ? 'is-active' : '' ?>"
                        data-login-mode="username">
                        <svg aria-hidden="true">
                            <use href="#icon-profile"></use>
                        </svg>
                        <span>Логин</span>
                    </button>
                </div>

                <form action="../php/login_process.php" method="post" class="auth-form" id="loginForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="login_mode" id="loginMode" value="<?= e($initialMode) ?>">

                    <div class="form-group">
                        <label class="form-field" for="login">
                            <svg class="form-field__icon" aria-hidden="true">
                                <use id="loginFieldIcon" href="<?= $initialMode === 'email' ? '#icon-mail' : '#icon-profile' ?>"></use>
                            </svg>
                            <input type="<?= $initialMode === 'email' ? 'email' : 'text' ?>"
                                id="login" name="login" value="<?= e($oldLogin) ?>"
                                placeholder="<?= $initialMode === 'email' ? 'Почта' : 'Логин' ?>"
                                autocomplete="<?= $initialMode === 'email' ? 'email' : 'username' ?>"
                                maxlength="190" aria-describedby="loginError">
                        </label>
                        <span class="form-error" id="loginError" data-error-for="login" aria-live="polite"><?= e((string)($errors['login'] ?? '')) ?></span>
                    </div>

                    <div class="form-group">
                        <label class="form-field" for="password">
                            <svg class="form-field__icon" aria-hidden="true">
                                <use href="#icon-lock"></use>
                            </svg>
                            <input type="password" id="password" name="password"
                                placeholder="Пароль" autocomplete="current-password"
                                maxlength="72" aria-describedby="passwordError">
                            <button type="button" class="password-toggle" data-password-target="password"
                                aria-label="Показать пароль" aria-pressed="false">
                                <svg aria-hidden="true">
                                    <use href="#icon-eye"></use>
                                </svg>
                            </button>
                        </label>
                        <span class="form-error" id="passwordError" data-error-for="password" aria-live="polite"><?= e((string)($errors['password'] ?? '')) ?></span>
                    </div>

                    <button type="submit" class="auth-submit">Войти</button>
                </form>

                <div class="auth-form-section__register">
                    <span>Нет аккаунта?</span>
                    <a href="register.php">Зарегистрироваться</a>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/../modules/public-footer.php'; ?>

    <script src="../js/public.js?v=9" defer></script>
</body>

</html>