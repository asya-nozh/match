<?php
$basePath = '..';

require_once __DIR__ . '/../php/admin-check.php';

$conn = auth_require_db();

if (!function_exists('e')) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function admin_complaints_rows(mysqli $conn, string $sql): array {
    $result = mysqli_query($conn, $sql);
    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

function admin_complaints_date(?string $date): string {
    if (!$date) {
        return '—';
    }

    return date('d.m.Y H:i', strtotime($date));
}

function admin_complaints_user_name(?string $displayName, string $username, string $fallback): string {
    $name = trim((string)$displayName);

    if ($name !== '') {
        return $name;
    }

    if ($username !== '') {
        return $username;
    }

    return $fallback;
}

function admin_complaints_user_card(array $user, string $basePath): string {
    $id = (string)($user['id'] ?? '');
    $username = (string)($user['username'] ?? '');
    $displayName = admin_complaints_user_name($user['display_name'] ?? '', $username, 'Пользователь');
    $avatarPath = trim((string)($user['avatar_path'] ?? ''));
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8');

    $avatarHtml = '<div class="admin-complaints-avatar admin-complaints-avatar--empty">' . e($initial) . '</div>';

    if ($avatarPath !== '') {
        if (str_starts_with($avatarPath, 'http://') || str_starts_with($avatarPath, 'https://') || str_starts_with($avatarPath, '/')) {
            $avatarUrl = $avatarPath;
        } elseif (str_starts_with($avatarPath, 'assets/') || str_starts_with($avatarPath, 'images/')) {
            $avatarUrl = $basePath . '/' . $avatarPath;
        } else {
            $avatarUrl = $basePath . '/images/' . $avatarPath;
        }

        $avatarHtml = '<img class="admin-complaints-avatar" src="' . e($avatarUrl) . '" alt="">';
    }

    return '
        <div class="admin-complaints-user">
            ' . $avatarHtml . '
            <div>
                <strong>' . e($displayName) . '</strong>
                <span>@' . e($username !== '' ? $username : ('user' . $id)) . '</span>
            </div>
        </div>
    ';
}

function admin_complaints_moderator_link(?string $moderatorId, ?string $displayName, ?string $username, string $basePath): string {
    $moderatorId = (string)$moderatorId;

    if ($moderatorId === '') {
        return '<span class="admin-complaints-muted">—</span>';
    }

    $name = admin_complaints_user_name($displayName, (string)$username, 'Модератор');

    return '<a class="admin-complaints-moderator-link" href="' . e($basePath) . '/pages/admin-moderators.php?moderator_id=' . e($moderatorId) . '#moderator-' . e($moderatorId) . '">' . e($name) . '</a>';
}

function admin_complaints_status_label(string $status, string $type): string {
    $reportLabels = [
        'new' => 'Новая',
        'in_review' => 'В работе',
        'resolved' => 'Решена',
        'rejected' => 'Отклонена',
    ];

    $appealLabels = [
        'new' => 'Новая',
        'in_review' => 'В работе',
        'accepted' => 'Принята',
        'rejected' => 'Отклонена',
    ];

    $labels = $type === 'appeal' ? $appealLabels : $reportLabels;

    return $labels[$status] ?? $status;
}

function admin_complaints_status_class(string $status): string {
    return match ($status) {
        'new' => 'new',
        'in_review' => 'review',
        'resolved', 'accepted' => 'resolved',
        'rejected' => 'rejected',
        default => 'default',
    };
}

$dashboardUserName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Сотрудник';
$dashboardUserRole = 'Администратор';
$logoutUrl = 'php/logout.php';
$pageTitle = 'Жалобы';
$pageDescription = 'Раздел для просмотра жалоб и апелляций пользователей.';
$activePage = 'complaints';
$dashboardHome = 'pages/admin-statistics.php';

$navItems = [
    'statistics' => ['title' => 'Статистика', 'href' => 'pages/admin-statistics.php', 'icon' => 'nav-feed.svg'],
    'complaints' => ['title' => 'Жалобы', 'href' => 'pages/admin-complaints.php', 'icon' => 'nav-chats.svg'],
    'places' => ['title' => 'Места', 'href' => 'pages/admin-places.php', 'icon' => 'nav-places.svg'],
    'moderators' => ['title' => 'Модераторы', 'href' => 'pages/admin-moderators.php', 'icon' => 'nav-connections.svg'],
];

$reports = admin_complaints_rows($conn, "
    SELECT
        r.id,
        r.status,
        r.created_at,
        COALESCE(r.closed_at, r.taken_at, r.created_at) AS updated_at,
        rr.name AS reason_name,

        author.id AS author_id,
        author.username AS author_username,
        author_profile.display_name AS author_display_name,
        author_profile.avatar_path AS author_avatar_path,

        reported.id AS reported_id,
        reported.username AS reported_username,
        reported_profile.display_name AS reported_display_name,
        reported_profile.avatar_path AS reported_avatar_path,

        moderator.id AS moderator_id,
        moderator.username AS moderator_username,
        moderator_profile.display_name AS moderator_display_name
    FROM reports AS r
    INNER JOIN users AS author ON author.id = r.author_id
    INNER JOIN users AS reported ON reported.id = r.reported_user_id
    INNER JOIN report_reasons AS rr ON rr.id = r.reason_id
    LEFT JOIN user_profiles AS author_profile ON author_profile.user_id = author.id
    LEFT JOIN user_profiles AS reported_profile ON reported_profile.user_id = reported.id
    LEFT JOIN users AS moderator ON moderator.id = r.assigned_moderator_id
    LEFT JOIN user_profiles AS moderator_profile ON moderator_profile.user_id = moderator.id
    ORDER BY updated_at DESC, r.id DESC
");

$appeals = admin_complaints_rows($conn, "
    SELECT
        a.id,
        a.status,
        a.appeal_text,
        a.created_at,
        COALESCE(a.reviewed_at, a.created_at) AS updated_at,

        appellant.id AS appellant_id,
        appellant.username AS appellant_username,
        appellant_profile.display_name AS appellant_display_name,
        appellant_profile.avatar_path AS appellant_avatar_path,

        reviewer.id AS reviewer_id,
        reviewer.username AS reviewer_username,
        reviewer_profile.display_name AS reviewer_display_name,

        md.report_id,
        md.decision_type
    FROM appeals AS a
    INNER JOIN users AS appellant ON appellant.id = a.appellant_id
    INNER JOIN moderation_decisions AS md ON md.id = a.decision_id
    LEFT JOIN user_profiles AS appellant_profile ON appellant_profile.user_id = appellant.id
    LEFT JOIN users AS reviewer ON reviewer.id = a.reviewer_id
    LEFT JOIN user_profiles AS reviewer_profile ON reviewer_profile.user_id = reviewer.id
    ORDER BY updated_at DESC, a.id DESC
");

ob_start();
?>

<section class="admin-complaints-page">
    <article class="admin-complaints-panel">
        <div class="admin-complaints-panel__header">
            <div>
                <h2>Жалобы пользователей</h2>
                <p>Список последних жалоб с указанием заявителя, пользователя, причины и статуса обработки.</p>
            </div>
            <span><?= e((string)count($reports)) ?> всего</span>
        </div>

        <?php if (count($reports) > 0): ?>
            <div class="admin-complaints-table-wrap">
                <table class="admin-complaints-table">
                    <thead>
                        <tr>
                            <th>Кто подал</th>
                            <th>На кого подали</th>
                            <th>Причина</th>
                            <th>Дата</th>
                            <th>Обновлено</th>
                            <th>Статус</th>
                            <th>Модератор</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $index => $report): ?>
                            <?php
                            $author = [
                                'id' => $report['author_id'],
                                'username' => $report['author_username'],
                                'display_name' => $report['author_display_name'],
                                'avatar_path' => $report['author_avatar_path'],
                            ];

                            $reported = [
                                'id' => $report['reported_id'],
                                'username' => $report['reported_username'],
                                'display_name' => $report['reported_display_name'],
                                'avatar_path' => $report['reported_avatar_path'],
                            ];

                            $status = (string)$report['status'];
                            $rowClass = $index >= 10 ? ' admin-complaints-row is-hidden' : ' admin-complaints-row';
                            ?>

                            <tr class="<?= e($rowClass) ?>" data-complaints-row="reports">
                                <td><?= admin_complaints_user_card($author, $basePath) ?></td>
                                <td><?= admin_complaints_user_card($reported, $basePath) ?></td>
                                <td><?= e((string)$report['reason_name']) ?></td>
                                <td><?= e(admin_complaints_date((string)$report['created_at'])) ?></td>
                                <td><?= e(admin_complaints_date((string)$report['updated_at'])) ?></td>
                                <td>
                                    <span class="admin-complaints-status admin-complaints-status--<?= e(admin_complaints_status_class($status)) ?>">
                                        <?= e(admin_complaints_status_label($status, 'report')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= admin_complaints_moderator_link(
                                        $report['moderator_id'],
                                        $report['moderator_display_name'],
                                        $report['moderator_username'],
                                        $basePath
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($reports) > 10): ?>
                <div class="admin-complaints-more">
                    <button class="admin-complaints-more__button" type="button" data-show-more="reports">Показать ещё</button>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="admin-complaints-empty">Жалоб пока нет.</div>
        <?php endif; ?>
    </article>

    <article class="admin-complaints-panel">
        <div class="admin-complaints-panel__header">
            <div>
                <h2>Апелляции</h2>
                <p>Обращения пользователей с просьбой пересмотреть решение по жалобе или ограничению.</p>
            </div>
            <span><?= e((string)count($appeals)) ?> всего</span>
        </div>

        <?php if (count($appeals) > 0): ?>
            <div class="admin-complaints-table-wrap">
                <table class="admin-complaints-table admin-complaints-table--appeals">
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Основание</th>
                            <th>Дата</th>
                            <th>Обновлено</th>
                            <th>Статус</th>
                            <th>Модератор</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appeals as $index => $appeal): ?>
                            <?php
                            $appellant = [
                                'id' => $appeal['appellant_id'],
                                'username' => $appeal['appellant_username'],
                                'display_name' => $appeal['appellant_display_name'],
                                'avatar_path' => $appeal['appellant_avatar_path'],
                            ];

                            $status = (string)$appeal['status'];
                            $appealText = trim((string)$appeal['appeal_text']);
                            $rowClass = $index >= 10 ? ' admin-complaints-row is-hidden' : ' admin-complaints-row';
                            ?>

                            <tr class="<?= e($rowClass) ?>" data-complaints-row="appeals">
                                <td><?= admin_complaints_user_card($appellant, $basePath) ?></td>
                                <td>
                                    <span class="admin-complaints-reason">
                                        <?= e($appealText !== '' ? $appealText : 'Апелляция по решению #' . $appeal['decision_type']) ?>
                                    </span>
                                </td>
                                <td><?= e(admin_complaints_date((string)$appeal['created_at'])) ?></td>
                                <td><?= e(admin_complaints_date((string)$appeal['updated_at'])) ?></td>
                                <td>
                                    <span class="admin-complaints-status admin-complaints-status--<?= e(admin_complaints_status_class($status)) ?>">
                                        <?= e(admin_complaints_status_label($status, 'appeal')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= admin_complaints_moderator_link(
                                        $appeal['reviewer_id'],
                                        $appeal['reviewer_display_name'],
                                        $appeal['reviewer_username'],
                                        $basePath
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($appeals) > 10): ?>
                <div class="admin-complaints-more">
                    <button class="admin-complaints-more__button" type="button" data-show-more="appeals">Показать ещё</button>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="admin-complaints-empty">Апелляций пока нет.</div>
        <?php endif; ?>
    </article>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const step = 10;

    document.querySelectorAll('[data-show-more]').forEach((button) => {
        button.addEventListener('click', () => {
            const type = button.dataset.showMore;
            const hiddenRows = Array.from(document.querySelectorAll(`[data-complaints-row="${type}"].is-hidden`));

            hiddenRows.slice(0, step).forEach((row) => {
                row.classList.remove('is-hidden');
            });

            if (hiddenRows.length <= step) {
                button.closest('.admin-complaints-more')?.remove();
            }
        });
    });
});
</script>

<?php
$pageContent = ob_get_clean();

require_once __DIR__ . '/../modules/dashboard-layout.php';
