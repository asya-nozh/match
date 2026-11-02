<?php
$basePath = '..';

require_once __DIR__ . '/../php/profile_helpers.php';
require_app_page();

$conn = auth_require_db();
$userId = (int)$_SESSION['user_id'];
$profile = profile_get_by_user_id($conn, $userId);
$cities = profile_get_cities($conn);
$goalLabels = profile_goal_labels();
$errors = (array)($_SESSION['profile_errors'] ?? []);
$old = (array)($_SESSION['profile_old'] ?? []);
unset($_SESSION['profile_errors'], $_SESSION['profile_old']);

$pageTitle = 'Редактирование профиля';
$pageDescription = 'Обновите анкету, описание и интересы для более точного подбора.';
$activePage = 'profile';
$showBlockedAccountNotice = false;

$avatarUrl = public_url(profile_avatar_path((string)($profile['avatar_path'] ?? '')));
$displayName = (string)($old['display_name'] ?? $profile['display_name'] ?? '');
$birthDate = (string)($old['birth_date'] ?? $profile['birth_date'] ?? '');
$cityId = (string)($old['city_id'] ?? $profile['city_id'] ?? '');
$goalValues = profile_parse_goals($old['communication_goals'] ?? $old['communication_goal'] ?? $profile['communication_goal'] ?? 'communication');
if ($goalValues === []) {
    $goalValues = ['communication'];
}
$hasAvatar = trim((string)($profile['avatar_path'] ?? '')) !== '';
$about = (string)($old['about'] ?? $profile['about'] ?? '');
$interestsText = (string)($old['interests_text'] ?? $profile['interests_text'] ?? $profile['about'] ?? '');
$interestNames = array_values((array)($old['interest_names'] ?? $profile['interests'] ?? []));
$analysisPayload = $profile['analysis_topics'] ?? ['interests' => [], 'anti_interests' => []];
$maxBirthDate = (new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');
$csrfToken = get_csrf_token('profile_edit_csrf');

ob_start();
?>

<?php if ($errors !== []): ?>
    <div class="profile-alert profile-alert--error">
        <?php foreach ($errors as $error): ?>
            <div><?= e((string)$error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form class="profile-edit" action="<?= $basePath ?>/php/profile_update.php" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" id="interestsPayload" name="interest_payload" value="<?= e(json_encode($analysisPayload, JSON_UNESCAPED_UNICODE)) ?>">

    <section class="profile-edit-card profile-edit-card--top">
        <div class="profile-edit__photo-col">
            <div class="profile-edit__photo-frame<?= $hasAvatar ? '' : ' is-placeholder' ?>">
                <img src="<?= e($avatarUrl) ?>" alt="Фото профиля" id="profilePhotoPreview">
            </div>
            <label class="profile-edit__photo-button btn btn--secondary">
                Изменить фотографию
                <input type="file" name="avatar" id="profileAvatarInput" accept="image/jpeg,image/png,image/webp">
            </label>
        </div>

        <div class="profile-edit__fields">
            <label class="profile-form-label">
                <span>Имя</span>
                <input class="form-field" type="text" name="display_name" value="<?= e($displayName) ?>" maxlength="80" required>
            </label>

            <label class="profile-form-label">
                <span>Никнейм</span>
                <input class="form-field" type="text" value="<?= e((string)($profile['username'] ?? '')) ?>" disabled>
            </label>

            <label class="profile-form-label">
                <span>Город</span>
                <select class="form-field" name="city_id">
                    <option value="">Не указан</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?= e((string)$city['id']) ?>" <?= (string)$city['id'] === $cityId ? 'selected' : '' ?>><?= e((string)$city['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="profile-form-label">
                <span>О себе</span>
                <textarea class="form-field" name="about" maxlength="800" placeholder="Например: ищу собеседников для прогулок и обсуждения книг"><?= e($about) ?></textarea>
            </label>
        </div>
    </section>

    <section class="profile-edit-card">
        <h2>Личная информация</h2>

        <label class="profile-form-label">
            <span>Дата рождения</span>
            <input class="form-field" id="profileBirthDate" type="date" name="birth_date" min="1900-01-01" max="<?= e($maxBirthDate) ?>" value="<?= e($birthDate) ?>" required>
            <small class="profile-field-hint">
                <?= profile_svg_icon('lock', 'profile-hint-icon') ?>
                <span>Дата рождения видна только вам, но возраст отображается в Ленте.</span>
            </small>
        </label>

        <label class="profile-form-label">
            <span>Опишите свои интересы</span>
            <textarea class="form-field profile-edit__interests-text" id="interestsText" name="interests_text" maxlength="1500" placeholder="Например: люблю кофе, выставки, длинные прогулки, книги и спокойные разговоры"><?= e($interestsText) ?></textarea>
        </label>

        <div class="profile-interest-row">
            <small class="profile-field-hint">
                <?= profile_svg_icon('lock', 'profile-hint-icon') ?>
                <span>Этот текст виден только вам.</span>
            </small>
            <button class="profile-interest-button btn btn--secondary" type="button" id="refreshInterests">Обновить интересы</button>
        </div>
        <span class="profile-interest-status" id="interestStatus" aria-live="polite"></span>

        <div class="profile-form-label">
            <span>Интересы</span>
            <div class="profile-chip-list profile-chip-list--editable" id="interestChips" data-initial='<?= e(json_encode($interestNames, JSON_UNESCAPED_UNICODE)) ?>'></div>
            <div id="interestHiddenInputs">
                <?php foreach ($interestNames as $interest): ?>
                    <input type="hidden" name="interest_names[]" value="<?= e((string)$interest) ?>">
                <?php endforeach; ?>
            </div>
        </div>

        <div class="profile-form-label">
            <span>Цель общения</span>
            <div class="profile-goal-list">
                <?php foreach ($goalLabels as $value => $label): ?>
                    <label class="profile-goal-option">
                        <input type="checkbox" name="communication_goals[]" value="<?= e($value) ?>" <?= in_array((string)$value, $goalValues, true) ? 'checked' : '' ?>>
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="profile-form-actions">
            <button class="profile-save-button btn btn--primary" type="submit">Сохранить изменения</button>
            <a class="profile-cancel-button btn btn--secondary" href="<?= $basePath ?>/pages/profile.php">Отменить</a>
        </div>
    </section>
</form>

<script src="<?= $basePath ?>/js/profile-edit.js" defer></script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../modules/app-layout.php';
?>
