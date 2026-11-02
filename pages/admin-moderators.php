<?php
$basePath = '..';

require_once __DIR__ . '/../php/admin-check.php';

$conn = auth_require_db();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function admin_moderators_rows(mysqli $conn, string $sql): array
{
    $result = mysqli_query($conn, $sql);
    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

function admin_moderators_count(mysqli $conn, string $sql): int
{
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_row($result);

    return (int)($row[0] ?? 0);
}

function admin_moderators_date(?string $date): string
{
    if (!$date) {
        return '—';
    }

    return date('d.m.Y H:i', strtotime($date));
}

function admin_moderators_status_label(string $status): string
{
    return match ($status) {
        'active' => 'Активен',
        'blocked' => 'Заблокирован',
        'deleted' => 'Удалён',
        default => $status,
    };
}

function admin_moderators_status_class(string $status): string
{
    return match ($status) {
        'active' => 'active',
        'blocked' => 'blocked',
        'deleted' => 'deleted',
        default => 'default',
    };
}

function admin_moderator_redirect(): void
{
    header('Location: admin-moderators.php');
    exit();
}

function admin_moderator_set_success(string $message): void
{
    $_SESSION['admin_moderators_success'] = $message;
}

function admin_moderator_set_error(string $message): void
{
    $_SESSION['admin_moderators_error'] = $message;
}

function admin_moderator_role_id(mysqli $conn): int
{
    $stmt = mysqli_prepare($conn, "SELECT id FROM roles WHERE slug = 'moderator' LIMIT 1");
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $roleId);

    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Роль модератора не найдена.');
    }

    mysqli_stmt_close($stmt);

    return (int)$roleId;
}

function admin_moderator_exists(mysqli $conn, string $username, string $email): bool
{
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    $exists = mysqli_stmt_num_rows($stmt) > 0;

    mysqli_stmt_close($stmt);

    return $exists;
}

function admin_moderator_is_valid(mysqli $conn, int $moderatorId): bool
{
    $stmt = mysqli_prepare($conn, "
        SELECT u.id
        FROM users AS u
        INNER JOIN roles AS r ON r.id = u.role_id
        WHERE u.id = ?
          AND r.slug = 'moderator'
          AND u.status <> 'deleted'
          AND u.deleted_at IS NULL
        LIMIT 1
    ");

    mysqli_stmt_bind_param($stmt, 'i', $moderatorId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    $exists = mysqli_stmt_num_rows($stmt) > 0;

    mysqli_stmt_close($stmt);

    return $exists;
}

function admin_moderator_can_restore(mysqli $conn, int $moderatorId): bool
{
    $stmt = mysqli_prepare($conn, "
        SELECT u.id
        FROM users AS u
        INNER JOIN roles AS r ON r.id = u.role_id
        WHERE u.id = ?
          AND r.slug = 'moderator'
          AND (u.status = 'deleted' OR u.deleted_at IS NOT NULL)
        LIMIT 1
    ");

    mysqli_stmt_bind_param($stmt, 'i', $moderatorId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    $exists = mysqli_stmt_num_rows($stmt) > 0;

    mysqli_stmt_close($stmt);

    return $exists;
}

if (empty($_SESSION['admin_moderators_csrf'])) {
    $_SESSION['admin_moderators_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_moderators_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');

    if ($token === '' || !hash_equals($csrfToken, $token)) {
        admin_moderator_set_error('Ошибка безопасности. Обновите страницу и повторите действие.');
        admin_moderator_redirect();
    }

    $action = (string)($_POST['action'] ?? '');
    $transactionStarted = false;

    try {
        if ($action === 'create') {
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if (mb_strlen($displayName, 'UTF-8') < 2 || mb_strlen($displayName, 'UTF-8') > 80) {
                admin_moderator_set_error('Имя модератора должно содержать от 2 до 80 символов.');
                admin_moderator_redirect();
            }

            if (!preg_match('/^[A-Za-z0-9_]{6,50}$/', $username)) {
                admin_moderator_set_error('Логин должен содержать от 6 до 50 символов: латинские буквы, цифры или нижнее подчёркивание.');
                admin_moderator_redirect();
            }

            if (mb_strlen($password, 'UTF-8') < 8) {
                admin_moderator_set_error('Пароль должен содержать минимум 8 символов.');
                admin_moderator_redirect();
            }

            $roleId = admin_moderator_role_id($conn);
            $email = mb_strtolower($username, 'UTF-8') . '@moderator.local';

            if (admin_moderator_exists($conn, $username, $email)) {
                admin_moderator_set_error('Пользователь с таким логином уже существует.');
                admin_moderator_redirect();
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            mysqli_begin_transaction($conn);
            $transactionStarted = true;

            $stmt = mysqli_prepare($conn, "
                INSERT INTO users (
                    role_id,
                    username,
                    email,
                    email_verified_at,
                    password_hash,
                    status,
                    failed_login_attempts,
                    locked_until,
                    privacy_accepted_at,
                    created_at,
                    updated_at,
                    is_verified
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    NOW(),
                    ?,
                    'active',
                    0,
                    NULL,
                    NOW(),
                    NOW(),
                    NOW(),
                    1
                )
            ");

            mysqli_stmt_bind_param($stmt, 'isss', $roleId, $username, $email, $passwordHash);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $moderatorId = mysqli_insert_id($conn);
            $birthDate = '2000-01-01';
            $gender = 'not_specified';
            $communicationGoal = 'moderation';
            $isVisible = 0;

            $stmt = mysqli_prepare($conn, "
                INSERT INTO user_profiles (
                    user_id,
                    display_name,
                    birth_date,
                    gender,
                    communication_goal,
                    is_visible,
                    updated_at
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW()
                )
            ");

            mysqli_stmt_bind_param($stmt, 'issssi', $moderatorId, $displayName, $birthDate, $gender, $communicationGoal, $isVisible);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            $transactionStarted = false;

            admin_moderator_set_success('Модератор успешно создан.');
            admin_moderator_redirect();
        }

        if ($action === 'change_status') {
            $moderatorId = (int)($_POST['moderator_id'] ?? 0);
            $newStatus = (string)($_POST['status'] ?? '');

            if (!in_array($newStatus, ['active', 'blocked'], true)) {
                admin_moderator_set_error('Выбран некорректный статус.');
                admin_moderator_redirect();
            }

            if ($moderatorId <= 0 || !admin_moderator_is_valid($conn, $moderatorId)) {
                admin_moderator_set_error('Модератор не найден или уже удалён.');
                admin_moderator_redirect();
            }

            $stmt = mysqli_prepare($conn, "
                UPDATE users AS u
                INNER JOIN roles AS r ON r.id = u.role_id
                SET
                    u.status = ?,
                    u.updated_at = NOW()
                WHERE u.id = ?
                  AND r.slug = 'moderator'
                  AND u.status <> 'deleted'
                  AND u.deleted_at IS NULL
            ");

            mysqli_stmt_bind_param($stmt, 'si', $newStatus, $moderatorId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_moderator_set_success('Статус модератора изменён.');
            admin_moderator_redirect();
        }

        if ($action === 'change_password') {
            $moderatorId = (int)($_POST['moderator_id'] ?? 0);
            $password = (string)($_POST['password'] ?? '');

            if ($moderatorId <= 0 || !admin_moderator_is_valid($conn, $moderatorId)) {
                admin_moderator_set_error('Модератор не найден или уже удалён.');
                admin_moderator_redirect();
            }

            if (mb_strlen($password, 'UTF-8') < 8) {
                admin_moderator_set_error('Новый пароль должен содержать минимум 8 символов.');
                admin_moderator_redirect();
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn, "
                UPDATE users AS u
                INNER JOIN roles AS r ON r.id = u.role_id
                SET
                    u.password_hash = ?,
                    u.failed_login_attempts = 0,
                    u.locked_until = NULL,
                    u.updated_at = NOW()
                WHERE u.id = ?
                  AND r.slug = 'moderator'
                  AND u.status <> 'deleted'
                  AND u.deleted_at IS NULL
            ");

            mysqli_stmt_bind_param($stmt, 'si', $passwordHash, $moderatorId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_moderator_set_success('Пароль модератора изменён.');
            admin_moderator_redirect();
        }

        if ($action === 'delete') {
            $moderatorId = (int)($_POST['moderator_id'] ?? 0);

            if ($moderatorId <= 0 || !admin_moderator_is_valid($conn, $moderatorId)) {
                admin_moderator_set_error('Модератор не найден или уже удалён.');
                admin_moderator_redirect();
            }

            $stmt = mysqli_prepare($conn, "
                UPDATE users AS u
                INNER JOIN roles AS r ON r.id = u.role_id
                SET
                    u.status = 'deleted',
                    u.deleted_at = NOW(),
                    u.updated_at = NOW()
                WHERE u.id = ?
                  AND r.slug = 'moderator'
                  AND u.status <> 'deleted'
                  AND u.deleted_at IS NULL
            ");

            mysqli_stmt_bind_param($stmt, 'i', $moderatorId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_moderator_set_success('Модератор помечен как удалённый.');
            admin_moderator_redirect();
        }

        if ($action === 'restore') {
            $moderatorId = (int)($_POST['moderator_id'] ?? 0);

            if ($moderatorId <= 0 || !admin_moderator_can_restore($conn, $moderatorId)) {
                admin_moderator_set_error('Модератор не найден или не требует восстановления.');
                admin_moderator_redirect();
            }

            $stmt = mysqli_prepare($conn, "
                UPDATE users AS u
                INNER JOIN roles AS r ON r.id = u.role_id
                SET
                    u.status = 'active',
                    u.deleted_at = NULL,
                    u.updated_at = NOW()
                WHERE u.id = ?
                  AND r.slug = 'moderator'
                  AND (u.status = 'deleted' OR u.deleted_at IS NOT NULL)
            ");

            mysqli_stmt_bind_param($stmt, 'i', $moderatorId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_moderator_set_success('Модератор восстановлен и снова активен.');
            admin_moderator_redirect();
        }

        admin_moderator_set_error('Неизвестное действие.');
        admin_moderator_redirect();
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            mysqli_rollback($conn);
        }

        admin_moderator_set_error('Не удалось выполнить действие. Проверьте данные и повторите попытку.');
        admin_moderator_redirect();
    }
}

$dashboardUserName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Сотрудник';
$dashboardUserRole = 'Администратор';
$logoutUrl = 'php/logout.php';
$pageTitle = 'Модераторы';
$pageDescription = 'Раздел для управления учетными записями модераторов.';
$activePage = 'moderators';
$dashboardHome = 'pages/admin-statistics.php';

$navItems = [
    'statistics' => ['title' => 'Статистика', 'href' => 'pages/admin-statistics.php', 'icon' => 'nav-feed.svg'],
    'complaints' => ['title' => 'Жалобы', 'href' => 'pages/admin-complaints.php', 'icon' => 'nav-chats.svg'],
    'places' => ['title' => 'Места', 'href' => 'pages/admin-places.php', 'icon' => 'nav-places.svg'],
    'moderators' => ['title' => 'Модераторы', 'href' => 'pages/admin-moderators.php', 'icon' => 'nav-connections.svg'],
];

$success = (string)($_SESSION['admin_moderators_success'] ?? '');
$error = (string)($_SESSION['admin_moderators_error'] ?? '');
unset($_SESSION['admin_moderators_success'], $_SESSION['admin_moderators_error']);

$highlightModeratorId = (int)($_GET['moderator_id'] ?? 0);

$moderators = admin_moderators_rows($conn, "
    SELECT
        u.id,
        u.username,
        u.email,
        u.status,
        u.created_at,
        u.last_login_at,
        u.last_active_at,
        u.deleted_at,
        up.display_name,

        (
            SELECT COUNT(*)
            FROM reports AS r
            WHERE r.assigned_moderator_id = u.id
              AND r.status = 'in_review'
        ) AS reports_in_review,

        (
            SELECT COUNT(*)
            FROM reports AS r
            WHERE r.assigned_moderator_id = u.id
              AND r.status IN ('resolved', 'rejected')
        ) AS reports_done,

        (
            SELECT COUNT(*)
            FROM appeals AS a
            WHERE a.reviewer_id = u.id
              AND a.status = 'in_review'
        ) AS appeals_in_review,

        (
            SELECT COUNT(*)
            FROM appeals AS a
            WHERE a.reviewer_id = u.id
              AND a.status IN ('accepted', 'rejected')
        ) AS appeals_done,

        (
            SELECT COUNT(*)
            FROM moderation_decisions AS md
            WHERE md.moderator_id = u.id
        ) AS decisions_total,

        (
            SELECT COUNT(*)
            FROM user_restrictions AS ur
            WHERE ur.created_by_user_id = u.id
              AND ur.status = 'active'
        ) AS active_restrictions

    FROM users AS u
    INNER JOIN roles AS r ON r.id = u.role_id
    LEFT JOIN user_profiles AS up ON up.user_id = u.id
    WHERE r.slug = 'moderator'
    ORDER BY
        CASE WHEN u.status = 'deleted' THEN 1 ELSE 0 END,
        u.created_at DESC,
        u.id DESC
");

$moderatorsTotal = admin_moderators_count($conn, "
    SELECT COUNT(*)
    FROM users AS u
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE r.slug = 'moderator'
");

$activeModeratorsTotal = admin_moderators_count($conn, "
    SELECT COUNT(*)
    FROM users AS u
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE r.slug = 'moderator'
      AND u.status = 'active'
      AND u.deleted_at IS NULL
");

$blockedModeratorsTotal = admin_moderators_count($conn, "
    SELECT COUNT(*)
    FROM users AS u
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE r.slug = 'moderator'
      AND u.status = 'blocked'
      AND u.deleted_at IS NULL
");

$assignedReportsTotal = admin_moderators_count($conn, "
    SELECT COUNT(*)
    FROM reports
    WHERE assigned_moderator_id IS NOT NULL
      AND status = 'in_review'
");

$moderationDecisionsTotal = admin_moderators_count($conn, "
    SELECT COUNT(*)
    FROM moderation_decisions
");

ob_start();
?>

<section class="admin-moderators-page">
    <?php if ($success !== ''): ?>
        <div class="profile-alert profile-alert--success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="profile-alert profile-alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="admin-moderators-top">
        <form class="admin-moderator-create" action="admin-moderators.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create">

            <div class="admin-moderator-create__header">
                <h2>Добавить модератора</h2>
                <p>Учетная запись создаётся по имени, логину и паролю.</p>
            </div>

            <label class="profile-form-label">
                <span>Имя</span>
                <input class="form-field" type="text" name="display_name" minlength="2" maxlength="80" placeholder="Например, Дарья" required>
            </label>

            <label class="profile-form-label">
                <span>Логин</span>
                <input class="form-field" type="text" name="username" minlength="6" maxlength="50" pattern="[A-Za-z0-9_]+" placeholder="moderator_1" required>
            </label>

            <label class="profile-form-label">
                <span>Пароль</span>
                <input class="form-field" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Минимум 8 символов" required>
            </label>

            <button class="btn btn--primary" type="submit">Создать модератора</button>
        </form>

        <div class="admin-moderators-summary">
            <article>
                <strong><?= e((string)$moderatorsTotal) ?></strong>
                <span>Всего модераторов</span>
            </article>

            <article>
                <strong><?= e((string)$activeModeratorsTotal) ?></strong>
                <span>Активных</span>
            </article>

            <article>
                <strong><?= e((string)$blockedModeratorsTotal) ?></strong>
                <span>Заблокированных</span>
            </article>

            <article>
                <strong><?= e((string)$assignedReportsTotal) ?></strong>
                <span>Жалоб в работе</span>
            </article>

            <article>
                <strong><?= e((string)$moderationDecisionsTotal) ?></strong>
                <span>Решений принято</span>
            </article>
        </div>
    </div>

    <article class="admin-moderators-panel">
        <div class="admin-moderators-panel__header">
            <div>
                <h2>Список модераторов</h2>
                <p>В таблице отображается активность модераторов по жалобам, апелляциям и ограничениям пользователей.</p>
            </div>
        </div>

        <?php if (count($moderators) > 0): ?>
            <div class="admin-moderators-table-wrap">
                <table class="admin-moderators-table">
                    <thead>
                        <tr>
                            <th>Модератор</th>
                            <th>Статус</th>
                            <th>Жалобы</th>
                            <th>Апелляции</th>
                            <th>Решения</th>
                            <th>Активность</th>
                            <th>Действия</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($moderators as $moderator): ?>
                            <?php
                            $moderatorId = (int)$moderator['id'];
                            $displayName = trim((string)($moderator['display_name'] ?? ''));
                            $username = (string)$moderator['username'];
                            $name = $displayName !== '' ? $displayName : $username;
                            $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
                            $status = (string)$moderator['status'];
                            $isDeleted = $status === 'deleted' || !empty($moderator['deleted_at']);
                            $isHighlighted = $highlightModeratorId === $moderatorId;
                            ?>

                            <tr id="moderator-<?= e((string)$moderatorId) ?>" class="<?= $isHighlighted ? 'is-highlighted' : '' ?>">
                                <td>
                                    <div class="admin-moderators-user">
                                        <div class="admin-moderators-avatar"><?= e($initial) ?></div>
                                        <div>
                                            <strong><?= e($name) ?></strong>
                                            <span>@<?= e($username) ?></span>
                                            <small><?= e((string)$moderator['email']) ?></small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="admin-moderators-status admin-moderators-status--<?= e(admin_moderators_status_class($status)) ?>">
                                        <?= e(admin_moderators_status_label($status)) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="admin-moderators-metrics">
                                        <span>В работе: <?= e((string)$moderator['reports_in_review']) ?></span>
                                        <span>Решено: <?= e((string)$moderator['reports_done']) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <div class="admin-moderators-metrics">
                                        <span>В работе: <?= e((string)$moderator['appeals_in_review']) ?></span>
                                        <span>Решено: <?= e((string)$moderator['appeals_done']) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <div class="admin-moderators-metrics">
                                        <span>Всего: <?= e((string)$moderator['decisions_total']) ?></span>
                                        <span>Ограничений: <?= e((string)$moderator['active_restrictions']) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <div class="admin-moderators-metrics">
                                        <span>Создан: <?= e(admin_moderators_date((string)$moderator['created_at'])) ?></span>
                                        <span>Вход: <?= e(admin_moderators_date($moderator['last_login_at'])) ?></span>
                                        <span>Активность: <?= e(admin_moderators_date($moderator['last_active_at'])) ?></span>
                                    </div>
                                </td>

                                <td class="admin-moderators-actions-cell">
                                    <?php if (!$isDeleted): ?>
                                        <div class="admin-moderators-actions">
                                            <form class="admin-moderators-action-form admin-moderators-status-form" action="admin-moderators.php" method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="change_status">
                                                <input type="hidden" name="moderator_id" value="<?= e((string)$moderatorId) ?>">

                                                <select class="form-field" name="status">
                                                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Активен</option>
                                                    <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
                                                </select>

                                                <button class="admin-moderators-inline-button" type="submit">Изменить статус</button>
                                            </form>

                                            <form class="admin-moderators-action-form admin-moderators-password-form" action="admin-moderators.php" method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="change_password">
                                                <input type="hidden" name="moderator_id" value="<?= e((string)$moderatorId) ?>">

                                                <input class="form-field" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Новый пароль" required>

                                                <button class="admin-moderators-inline-button" type="submit">Изменить пароль</button>
                                            </form>

                                            <form class="admin-moderators-delete-form" action="admin-moderators.php" method="post" onsubmit="return confirm('Удалить этого модератора? Запись будет помечена как удалённая.');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="moderator_id" value="<?= e((string)$moderatorId) ?>">

                                                <button class="admin-moderators-inline-button admin-moderators-inline-button--danger" type="submit">Удалить</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="admin-moderators-deleted-actions">
                                            <span class="admin-moderators-muted">Доступ ограничен</span>

                                            <form action="admin-moderators.php" method="post" onsubmit="return confirm('Восстановить этого модератора? После восстановления статус станет активным.');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="moderator_id" value="<?= e((string)$moderatorId) ?>">

                                                <button class="admin-moderators-inline-button" type="submit">Восстановить</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-moderators-empty">Модераторы пока не добавлены.</div>
        <?php endif; ?>
    </article>
</section>

<?php
$pageContent = ob_get_clean();

require_once __DIR__ . '/../modules/dashboard-layout.php';