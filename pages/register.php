<?php

declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
require_guest_page();

$errors = $_SESSION['register_errors'] ?? [];
$old = $_SESSION['register_old'] ?? [];

unset($_SESSION['register_errors'], $_SESSION['register_old']);

$basePath = '..';
$activePublicPage = 'register';
$maxBirthDate = (new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');
$csrfToken = get_csrf_token('csrf_register');
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация | МЭТЧ</title>
    <link rel="icon" href="../images/logo.svg" type="image/png">
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/public.css?v=10">
</head>

<body class="auth-body">
    <?php include __DIR__ . '/../modules/icons.php'; ?>
    <?php include __DIR__ . '/../modules/public-header.php'; ?>

    <main class="auth-page register-page">
        <section class="auth-card register-card" aria-labelledby="registerTitle">
            <div class="auth-intro register-intro">
                <div class="auth-intro__text">
                    <h2 class="auth-intro__title">
                        <span class="decor-letter">С</span>оздай свой<br>
                        МЭТЧ
                    </h2>
                    <p class="auth-intro__description">
                        Найди друзей, с кем легко<br>
                        и интересно.
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
                            <p>Найди близких по духу людей</p>
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
                            <p>Мы бережём твои данные</p>
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
                            <p>Открывай новое вместе с другими</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-form-section register-form-section">
                <div class="auth-form-section__head">
                    <h1 class="auth-form-section__title" id="registerTitle">
                        <span class="decor-letter">Р</span>егистрация
                    </h1>
                    <p>Создай аккаунт в МЭТЧ</p>
                </div>

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

                <form action="../php/register_process.php" method="post" class="auth-form register-form" id="registerForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="form-group">
                        <label class="form-field" for="displayName">
                            <svg class="form-field__icon" aria-hidden="true">
                                <use href="#icon-profile"></use>
                            </svg>
                            <input type="text" id="displayName" name="display_name"
                                value="<?= e((string)($old['display_name'] ?? '')) ?>"
                                placeholder="Имя" maxlength="80" autocomplete="given-name" aria-describedby="displayNameError">
                        </label>
                        <span class="form-error" id="displayNameError" data-error-for="displayName" aria-live="polite"><?= e((string)($errors['display_name'] ?? '')) ?></span>
                    </div>

                    <div class="form-group">
                        <label class="form-field" for="username">
                            <span class="form-field__symbol" aria-hidden="true">@</span>
                            <input type="text" id="username" name="username"
                                value="<?= e((string)($old['username'] ?? '')) ?>"
                                placeholder="Логин (6–50 симв.)" minlength="6" maxlength="50"
                                autocomplete="username" aria-describedby="usernameError">
                        </label>
                        <span class="form-error" id="usernameError" data-error-for="username" aria-live="polite"><?= e((string)($errors['username'] ?? '')) ?></span>
                    </div>

                    <div class="form-group">
                        <label class="form-field" for="email">
                            <svg class="form-field__icon" aria-hidden="true">
                                <use href="#icon-mail"></use>
                            </svg>
                            <input type="email" id="email" name="email"
                                value="<?= e((string)($old['email'] ?? '')) ?>"
                                placeholder="Почта" maxlength="255" autocomplete="email" aria-describedby="emailError">
                        </label>
                        <span class="form-error" id="emailError" data-error-for="email" aria-live="polite"><?= e((string)($errors['email'] ?? '')) ?></span>
                    </div>

                    <div class="form-group">
                        <label class="form-field form-field--date" for="birthDate">
                            <svg class="form-field__icon" aria-hidden="true">
                                <use href="#icon-calendar"></use>
                            </svg>
                            <input type="date" id="birthDate" name="birth_date"
                                min="1900-01-01"
                                value="<?= e((string)($old['birth_date'] ?? '')) ?>"
                                max="<?= e($maxBirthDate) ?>" aria-describedby="birthDateError birthDateHint">
                        </label>
                        <span class="form-error" id="birthDateError" data-error-for="birthDate" aria-live="polite"><?= e((string)($errors['birth_date'] ?? '')) ?></span>
                        <span class="form-hint" id="birthDateHint">Регистрация доступна с 18 лет.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-field" for="password">
                            <svg class="form-field__icon" aria-hidden="true">
                                <use href="#icon-lock"></use>
                            </svg>
                            <input type="password" id="password" name="password"
                                placeholder="Пароль (от 8 симв.)" maxlength="72" autocomplete="new-password" aria-describedby="passwordError">
                            <button type="button" class="password-toggle" data-password-target="password"
                                aria-label="Показать пароль" aria-pressed="false">
                                <svg aria-hidden="true">
                                    <use href="#icon-eye"></use>
                                </svg>
                            </button>
                        </label>
                        <span class="form-error" id="passwordError" data-error-for="password" aria-live="polite"><?= e((string)($errors['password'] ?? '')) ?></span>
                    </div>

                    <div class="form-group">
                        <label class="form-field" for="passwordConfirm">
                            <svg class="form-field__icon" aria-hidden="true">
                                <use href="#icon-lock"></use>
                            </svg>
                            <input type="password" id="passwordConfirm" name="password_confirm"
                                placeholder="Повтор пароля" maxlength="72"
                                autocomplete="new-password" aria-describedby="passwordConfirmError">
                            <button type="button" class="password-toggle" data-password-target="passwordConfirm"
                                aria-label="Показать пароль" aria-pressed="false">
                                <svg aria-hidden="true">
                                    <use href="#icon-eye"></use>
                                </svg>
                            </button>
                        </label>
                        <span class="form-error" id="passwordConfirmError" data-error-for="passwordConfirm" aria-live="polite"><?= e((string)($errors['password_confirm'] ?? '')) ?></span>
                    </div>

                    <div class="form-group">
                        <label class="privacy-check">
                            <input type="checkbox" name="privacy_accepted" value="1"
                                <?= !empty($old['privacy_accepted']) ? 'checked' : '' ?> aria-describedby="privacyAcceptedError">
                            <span class="privacy-check__box" aria-hidden="true"></span>
                            <span>
                                Я принимаю <a href="legal information.html" target="_blank">правила использования</a>
                                и <a href="legal information.html" target="_blank">политику конфиденциальности</a>
                            </span>
                        </label>
                        <span class="form-error" id="privacyAcceptedError" data-error-for="privacy_accepted" aria-live="polite"><?= e((string)($errors['privacy_accepted'] ?? '')) ?></span>
                    </div>

                    <button type="submit" class="auth-submit register-submit">Зарегистрироваться</button>
                </form>

                <div class="auth-form-section__register">
                    <span>Уже есть аккаунт?</span>
                    <a href="login.php">Войти</a>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/../modules/public-footer.php'; ?>

    <script src="../js/public.js?v=9" defer></script>
    <script src="../js/register.js?v=10" defer></script>
</body>

</html>