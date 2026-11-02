<?php
$basePath = '..';

require_once __DIR__ . '/../php/profile_helpers.php';
require_app_page();

$conn = auth_require_db();
$userId = (int)$_SESSION['user_id'];
$profile = profile_get_by_user_id($conn, $userId);
$goalLabels = profile_goal_labels();
$isBlocked = is_current_user_blocked();
$successMessage = (string)($_SESSION['profile_success'] ?? '');
$appealSuccessMessage = (string)($_SESSION['profile_appeal_success'] ?? '');
$appealErrorMessage = (string)($_SESSION['profile_appeal_error'] ?? '');
unset($_SESSION['profile_success'], $_SESSION['profile_appeal_success'], $_SESSION['profile_appeal_error']);
$activeBlockNotice = profile_get_active_block_notice($conn, $userId);
$warningNotice = $activeBlockNotice === null ? profile_get_latest_warning_notice($conn, $userId) : null;

$pageTitle = 'Профиль';
$pageDescription = 'Ваша анкета и основные данные для подбора собеседников.';
$activePage = 'profile';
$pageToolbar = '
    <a class="btn btn--secondary profile-hero__action-button" href="' . $basePath . '/pages/profile-edit.php">Редактировать профиль</a>
    <a class="btn btn--secondary profile-hero__action-button" href="' . $basePath . '/php/logout.php">Выйти</a>
';
$pageToolbarInline = true;
$showBlockedAccountNotice = false;

$avatarUrl = public_url(profile_avatar_path((string)($profile['avatar_path'] ?? '')));
$displayName = (string)($profile['display_name'] ?? $_SESSION['display_name'] ?? 'Пользователь');
$username = (string)($profile['username'] ?? $_SESSION['username'] ?? 'user');
$age = $profile['age'] ?? null;
$cityName = (string)($profile['city_name'] ?? 'Город не указан');
$about = trim((string)($profile['about'] ?? ''));
$interests = (array)($profile['interests'] ?? []);
$goalValues = profile_parse_goals($profile['communication_goal'] ?? 'communication');
if ($goalValues === []) {
    $goalValues = ['communication'];
}
$hasAvatar = trim((string)($profile['avatar_path'] ?? '')) !== '';
$appealCsrfToken = get_csrf_token('profile_appeal_csrf');

ob_start();
?>

<?php if ($successMessage !== ''): ?>
    <div class="profile-alert profile-alert--success"><?= e($successMessage) ?></div>
<?php endif; ?>

<?php if ($appealSuccessMessage !== ''): ?>
    <div class="profile-alert profile-alert--success"><?= e($appealSuccessMessage) ?></div>
<?php endif; ?>

<?php if ($appealErrorMessage !== ''): ?>
    <div class="profile-alert profile-alert--error"><?= e($appealErrorMessage) ?></div>
<?php endif; ?>

<?php if ($activeBlockNotice !== null || $warningNotice !== null): ?>
    <div class="profile-moderation-stack">
        <?php if ($activeBlockNotice !== null): ?>
            <section class="profile-moderation-card">
                <h2 class="profile-moderation-card__title">Доступ к аккаунту временно ограничен</h2>
                <p class="profile-moderation-card__text">
                    Мы рассмотрели жалобу и временно ограничили доступ к части функций аккаунта. Пожалуйста, соблюдайте правила сообщества, общайтесь вежливо и уважайте других пользователей. Если вы считаете решение ошибочным, вы можете подать апелляцию.
                </p>
                <div class="profile-moderation-card__meta">
                    <span><strong>Причина:</strong> <?= e((string)($activeBlockNotice['reason_name'] ?? 'Не указана')) ?></span>
                    <span><strong>Где:</strong> <?= e((string)($activeBlockNotice['location_label'] ?? 'в профиле')) ?></span>
                </div>
                <?php if (!empty($activeBlockNotice['ends_at'])): ?>
                    <p class="profile-moderation-card__status"><strong>Ограничение действует до:</strong> <?= e(date('d.m.Y H:i', strtotime((string)$activeBlockNotice['ends_at']))) ?></p>
                <?php endif; ?>

                <?php if ((int)($activeBlockNotice['appeal_id'] ?? 0) > 0): ?>
                    <p class="profile-moderation-card__status"><strong>Апелляция:</strong> <?= e(match ((string)($activeBlockNotice['appeal_status'] ?? 'new')) {
                        'new' => 'отправлена и ожидает рассмотрения.',
                        'in_review' => 'находится на рассмотрении.',
                        'accepted' => 'принята.',
                        'rejected' => 'отклонена.',
                        default => 'отправлена.'
                    }) ?></p>
                <?php else: ?>
                    <form class="profile-moderation-form" action="<?= $basePath ?>/php/profile_appeal.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($appealCsrfToken) ?>">
                        <textarea class="form-field" name="appeal_text" maxlength="2000" placeholder="Напишите, почему вы не согласны с блокировкой" required></textarea>
                        <div class="profile-form-actions">
                            <button class="btn btn--primary profile-save-button" type="submit">Подать апелляцию</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($warningNotice !== null): ?>
            <?php $warningDecisionId = (int)($warningNotice['decision_id'] ?? 0); ?>
            <section class="profile-moderation-card profile-moderation-card--warning" data-profile-warning-card data-warning-id="<?= e((string)$warningDecisionId) ?>">
                <button class="profile-moderation-card__close" type="button" data-profile-warning-dismiss aria-label="Скрыть предупреждение">×</button>
                <h2 class="profile-moderation-card__title">Предупреждение</h2>
                <p class="profile-moderation-card__text">
                    Вам выдано предупреждение за нарушение правил сообщества. Пожалуйста, общайтесь вежливо, уважайте других пользователей и не повторяйте это нарушение. При повторных жалобах мы будем вынуждены временно ограничить доступ к аккаунту.
                </p>
                <div class="profile-moderation-card__meta">
                    <span><strong>Причина:</strong> <?= e((string)($warningNotice['reason_name'] ?? 'Не указана')) ?></span>
                    <span><strong>Где:</strong> <?= e((string)($warningNotice['location_label'] ?? 'в профиле')) ?></span>
                </div>
            </section>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="profile-view">
    <div class="profile-hero">
        <div class="profile-hero__photo-wrap<?= $hasAvatar ? '' : ' is-placeholder' ?>">
            <img class="profile-hero__photo" src="<?= e($avatarUrl) ?>" alt="Фото профиля">
        </div>

        <div class="profile-hero__info">
            <h2><?= e($displayName) ?><?= $age !== null ? ', ' . e((string)$age) : '' ?></h2>
            <p class="profile-hero__username">@<?= e($username) ?></p>
            <p class="profile-hero__city">
                <?= profile_svg_icon('location') ?>
                <span><?= e($cityName !== '' ? $cityName : 'Город не указан') ?></span>
            </p>


            <div class="profile-note">
                <?= $about !== '' ? nl2br(e($about)) : 'Добавьте короткое описание о себе, чтобы другим пользователям было проще понять, о чём с вами можно поговорить.' ?>
            </div>
        </div>
    </div>

    <div class="profile-section-line" aria-hidden="true"><span></span></div>

    <div class="profile-info-grid">
        <section class="profile-block">
            <h3>Интересы</h3>
            <?php if ($interests !== []): ?>
                <div class="profile-chip-list">
                    <?php foreach ($interests as $interest): ?>
                        <span class="profile-chip">
                            <?= profile_svg_icon(profile_interest_icon_key((string)$interest), 'profile-chip__icon') ?>
                            <span><?= e((string)$interest) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="profile-empty">Интересы пока не добавлены.</p>
            <?php endif; ?>
        </section>

        <section class="profile-block">
            <h3>Формат общения</h3>
            <div class="profile-chip-list">
                <?php foreach ($goalValues as $goalValue): ?>
                    <span class="profile-chip profile-chip--goal">
                        <span><?= e($goalLabels[$goalValue] ?? $goalValue) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>

<script>
document.querySelectorAll('[data-profile-warning-card]').forEach((card) => {
    const warningId = card.dataset.warningId || 'latest';
    const storageKey = 'match_hidden_profile_warning_' + warningId;

    try {
        if (localStorage.getItem(storageKey) === '1') {
            card.hidden = true;
        }
    } catch (error) {}

    card.querySelector('[data-profile-warning-dismiss]')?.addEventListener('click', () => {
        try {
            localStorage.setItem(storageKey, '1');
        } catch (error) {}
        card.hidden = true;
    });
});
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../modules/app-layout.php';
?>
