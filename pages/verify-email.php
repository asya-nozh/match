<?php

declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';

if (auth_load_current_user(false) !== null) {
    auth_redirect(auth_home_url());
}

$errors = $_SESSION['verify_errors'] ?? [];
$old = $_SESSION['verify_old'] ?? [];
$successMessage = (string)($_SESSION['success_message'] ?? '');

unset($_SESSION['verify_errors'], $_SESSION['verify_old'], $_SESSION['success_message']);

$basePath = '..';
$activePublicPage = '';
$pendingEmail = (string)($_SESSION['pending_verify_email'] ?? ($old['email'] ?? ''));
$csrfToken = get_csrf_token('csrf_verify_email');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение почты | МЭТЧ</title>
    
    <link rel="icon" href="../images/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/public.css?v=9">
</head>
<body class="auth-body">
    <?php include __DIR__ . '/../modules/icons.php'; ?>
    <?php include __DIR__ . '/../modules/public-header.php'; ?>

    <main class="auth-page verify-page">
        <section class="auth-card verify-card" aria-labelledby="verifyTitle">
            <div class="auth-intro">
                <div class="auth-intro__text">
                    <h2 class="auth-intro__title">
                        <span class="decor-letter">П</span>одтверди почту
                    </h2>
                    <p class="auth-intro__description">
                        Введи код из письма, чтобы завершить регистрацию.
                    </p>
                </div>

                <div class="auth-illustration" aria-hidden="true">
                    <img class="auth-illustration__image" src="../images/reg-auth-illustration.png" alt="">
                </div>

                <div class="auth-benefits">
                    <div class="auth-benefit">
                        <span class="auth-benefit__icon">
                            <svg aria-hidden="true"><use href="#icon-mail"></use></svg>
                        </span>
                        <div>
                            <h3>Код действует 15 минут</h3>
                            <p>Не передавай его другим людям</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-form-section">
                <div class="auth-form-section__head">
                    <h1 class="auth-form-section__title" id="verifyTitle">
                        <span class="decor-letter">К</span>од подтверждения
                    </h1>
                    <p>Проверь письмо от МЭТЧ</p>
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

                <form action="../php/verify_email_process.php" method="post" class="auth-form" id="verifyEmailForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="form-group">
                        <label class="form-field" for="verifyEmail">
                            <svg class="form-field__icon" aria-hidden="true"><use href="#icon-mail"></use></svg>
                            <input type="email" id="verifyEmail" name="email"
                                value="<?= e($pendingEmail) ?>"
                                placeholder="Почта" maxlength="255" autocomplete="email" required>
                        </label>
                        <?php if (isset($errors['email'])): ?>
                            <span class="form-error"><?= e((string)$errors['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-field" for="verificationCode">
                            <span class="form-field__symbol" aria-hidden="true">#</span>
                            <input type="text" id="verificationCode" name="code"
                                value="<?= e((string)($old['code'] ?? '')) ?>"
                                placeholder="Код из письма" inputmode="numeric" minlength="6" maxlength="6" autocomplete="one-time-code" required>
                        </label>
                        <?php if (isset($errors['code'])): ?>
                            <span class="form-error"><?= e((string)$errors['code']) ?></span>
                        <?php else: ?>
                            <span class="form-hint">Код состоит из 6 цифр.</span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="auth-submit">Подтвердить</button>
                </form>

                <div class="auth-form-section__register">
                    <span>Уже подтвердили почту?</span>
                    <a href="login.php">Войти</a>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/../modules/public-footer.php'; ?>

    <script src="../js/public.js?v=9" defer></script>
</body>
</html>
