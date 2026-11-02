<?php
$basePath = '..';

require_once __DIR__ . '/../php/admin-check.php';

$conn = auth_require_db();

if (!function_exists('e')) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function admin_stat_count(mysqli $conn, string $sql): int {
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_row($result);

    return (int)($row[0] ?? 0);
}

function admin_stat_rows(mysqli $conn, string $sql): array {
    $result = mysqli_query($conn, $sql);
    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

$dashboardUserName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Сотрудник';
$dashboardUserRole = 'Администратор';
$logoutUrl = 'php/logout.php';

$pageTitle = 'Статистика';
$pageDescription = 'Раздел статистики и общего управления веб-приложением.';
$activePage = 'statistics';
$dashboardHome = 'pages/admin-statistics.php';

$navItems = [
    'statistics' => ['title' => 'Статистика', 'href' => 'pages/admin-statistics.php', 'icon' => 'nav-feed.svg'],
    'complaints' => ['title' => 'Жалобы', 'href' => 'pages/admin-complaints.php', 'icon' => 'nav-chats.svg'],
    'places' => ['title' => 'Места', 'href' => 'pages/admin-places.php', 'icon' => 'nav-places.svg'],
    'moderators' => ['title' => 'Модераторы', 'href' => 'pages/admin-moderators.php', 'icon' => 'nav-connections.svg'],
];

$usersTotal = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM users AS u
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE r.slug = 'participant'
      AND u.deleted_at IS NULL
      AND u.status <> 'deleted'
");

$usersToday = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM users AS u
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE r.slug = 'participant'
      AND u.deleted_at IS NULL
      AND u.status <> 'deleted'
      AND DATE(u.created_at) = CURDATE()
");

$verifiedUsers = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM users AS u
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE r.slug = 'participant'
      AND u.deleted_at IS NULL
      AND u.status <> 'deleted'
      AND u.email_verified_at IS NOT NULL
");

$completedProfiles = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM user_profiles AS up
    INNER JOIN users AS u ON u.id = up.user_id
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE r.slug = 'participant'
      AND u.deleted_at IS NULL
      AND u.status <> 'deleted'
      AND up.completed_at IS NOT NULL
");

$matchesTotal = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM matches
");

$matchesToday = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM matches
    WHERE DATE(created_at) = CURDATE()
");

$activeMatches = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM matches
    WHERE status = 'active'
");

$placesTotal = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM places
");

$placesActive = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM places
    WHERE status = 'active'
");

$invitesTotal = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM likes
");

$invitesToday = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM likes
    WHERE DATE(created_at) = CURDATE()
");

$reportsTotal = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM reports
");

$reportsOpen = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM reports
    WHERE status IN ('new', 'in_review')
");

$moderatorsTotal = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM users AS u
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE r.slug = 'moderator'
      AND u.deleted_at IS NULL
      AND u.status <> 'deleted'
");

$activeInterestsTotal = admin_stat_count($conn, "
    SELECT COUNT(*)
    FROM interests
    WHERE is_active = 1
");

$cityRowsRaw = admin_stat_rows($conn, "
    SELECT c.name AS city_name, COUNT(*) AS users_count
    FROM user_profiles AS up
    INNER JOIN cities AS c ON c.id = up.city_id
    INNER JOIN users AS u ON u.id = up.user_id
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE r.slug = 'participant'
      AND u.deleted_at IS NULL
      AND u.status <> 'deleted'
      AND up.city_id IS NOT NULL
    GROUP BY c.id, c.name
    ORDER BY users_count DESC
");

$totalCityUsers = 0;

foreach ($cityRowsRaw as $row) {
    $totalCityUsers += (int)$row['users_count'];
}

$cityRows = [];
$otherCount = 0;

foreach ($cityRowsRaw as $index => $row) {
    if ($index < 4) {
        $cityRows[] = $row;
    } else {
        $otherCount += (int)$row['users_count'];
    }
}

if ($otherCount > 0) {
    $cityRows[] = [
        'city_name' => 'Другие',
        'users_count' => $otherCount,
    ];
}

$chartColors = ['#9B6CFF', '#FF995E', '#D9B6F4', '#E7A0B8', '#BFA2FF'];
$chartSegments = [];
$chartStart = 0;

foreach ($cityRows as $index => $row) {
    $count = (int)$row['users_count'];
    $angle = $totalCityUsers > 0 ? ($count / $totalCityUsers) * 360 : 0;
    $chartEnd = $chartStart + $angle;

    $chartSegments[] = $chartColors[$index % count($chartColors)] . ' ' . round($chartStart, 2) . 'deg ' . round($chartEnd, 2) . 'deg';
    $chartStart = $chartEnd;
}

$cityChartGradient = count($chartSegments) > 0
    ? 'conic-gradient(' . implode(', ', $chartSegments) . ')'
    : 'conic-gradient(#eee7ff 0deg 360deg)';

$reportStatusLabels = [
    'new' => 'Новые',
    'in_review' => 'На проверке',
    'resolved' => 'Решённые',
    'rejected' => 'Отклонённые',
];

$reportStatusRowsRaw = admin_stat_rows($conn, "
    SELECT status, COUNT(*) AS reports_count
    FROM reports
    GROUP BY status
    ORDER BY FIELD(status, 'new', 'in_review', 'resolved', 'rejected')
");

$totalReportStatuses = 0;
$reportStatusRows = [];

foreach ($reportStatusRowsRaw as $row) {
    $count = (int)$row['reports_count'];
    $status = (string)$row['status'];

    $totalReportStatuses += $count;

    $reportStatusRows[] = [
        'name' => $reportStatusLabels[$status] ?? $status,
        'count' => $count,
    ];
}

$reportChartColors = ['#9B6CFF', '#FF995E', '#D9B6F4', '#E7A0B8'];
$reportChartSegments = [];
$reportChartStart = 0;

foreach ($reportStatusRows as $index => $row) {
    $count = (int)$row['count'];
    $angle = $totalReportStatuses > 0 ? ($count / $totalReportStatuses) * 360 : 0;
    $reportChartEnd = $reportChartStart + $angle;

    $reportChartSegments[] = $reportChartColors[$index % count($reportChartColors)] . ' ' . round($reportChartStart, 2) . 'deg ' . round($reportChartEnd, 2) . 'deg';
    $reportChartStart = $reportChartEnd;
}

$reportChartGradient = count($reportChartSegments) > 0
    ? 'conic-gradient(' . implode(', ', $reportChartSegments) . ')'
    : 'conic-gradient(#eee7ff 0deg 360deg)';

$interestRows = admin_stat_rows($conn, "
    SELECT
        i.name,
        COUNT(DISTINCT ui.user_id) AS users_count
    FROM interests AS i
    INNER JOIN user_interests AS ui ON ui.interest_id = i.id
    INNER JOIN users AS u ON u.id = ui.user_id
    INNER JOIN roles AS r ON r.id = u.role_id
    WHERE i.is_active = 1
      AND r.slug = 'participant'
      AND u.deleted_at IS NULL
      AND u.status <> 'deleted'
    GROUP BY i.id, i.name
    ORDER BY users_count DESC, i.name ASC
    LIMIT 8
");

foreach ($interestRows as &$interest) {
    $count = (int)$interest['users_count'];
    $interest['percent'] = $usersTotal > 0 ? round(($count / $usersTotal) * 100, 1) : 0;
}

unset($interest);

$cards = [
    [
        'icon' => '👥',
        'mod' => 'purple',
        'value' => number_format($usersTotal, 0, '.', ' '),
        'title' => 'Всего пользователей',
        'note' => '+' . $usersToday . ' за сегодня',
    ],
    [
        'icon' => '💜',
        'mod' => 'violet',
        'value' => number_format($matchesTotal, 0, '.', ' '),
        'title' => 'Мэтчей создано',
        'note' => '+' . $matchesToday . ' за сегодня, активных — ' . $activeMatches,
    ],
    [
        'icon' => '📍',
        'mod' => 'orange',
        'value' => number_format($placesTotal, 0, '.', ' '),
        'title' => 'Мест в каталоге',
        'note' => $placesActive . ' активных',
    ],
    [
        'icon' => '✉️',
        'mod' => 'pink',
        'value' => number_format($invitesTotal, 0, '.', ' '),
        'title' => 'Отправлено приглашений',
        'note' => '+' . $invitesToday . ' за сегодня',
    ],
    [
        'icon' => '🚩',
        'mod' => 'orange',
        'value' => number_format($reportsTotal, 0, '.', ' '),
        'title' => 'Жалоб на контент',
        'note' => $reportsOpen . ' требуют проверки',
    ],
    [
        'icon' => '🛡️',
        'mod' => 'purple',
        'value' => number_format($moderatorsTotal, 0, '.', ' '),
        'title' => 'Модераторов',
        'note' => 'Сотрудники с ролью модератора',
    ],
    [
        'icon' => '✅',
        'mod' => 'violet',
        'value' => number_format($verifiedUsers, 0, '.', ' '),
        'title' => 'Подтверждённых аккаунтов',
        'note' => 'Почта подтверждена',
    ],
    [
        'icon' => '📝',
        'mod' => 'pink',
        'value' => number_format($completedProfiles, 0, '.', ' '),
        'title' => 'Заполненных профилей',
        'note' => 'Профили с completed_at',
    ],
    [
        'icon' => '✨',
        'mod' => 'violet',
        'value' => number_format($activeInterestsTotal, 0, '.', ' '),
        'title' => 'Интересов в системе',
        'note' => 'Активные интересы для профилей',
    ],
];

ob_start();
?>

<section class="admin-statistics-page">
    <div class="admin-statistics-grid">
        <?php foreach ($cards as $card): ?>
            <article class="admin-stat-card">
                <div class="admin-stat-card__icon admin-stat-card__icon--<?= e($card['mod']) ?>">
                    <?= e($card['icon']) ?>
                </div>

                <div class="admin-stat-card__content">
                    <strong><?= e($card['value']) ?></strong>
                    <span><?= e($card['title']) ?></span>
                    <small><?= e($card['note']) ?></small>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="admin-statistics-panels">
        <article class="admin-stat-panel admin-stat-panel--cities">
            <div class="admin-stat-panel__header">
                <h2>Распределение по городам</h2>
                <p>На основе городов, указанных пользователями в профиле.</p>
            </div>

            <?php if ($totalCityUsers > 0): ?>
                <div class="admin-city-chart-wrap">
                    <div class="admin-city-chart" style="background: <?= e($cityChartGradient) ?>;"></div>

                    <div class="admin-city-legend">
                        <?php foreach ($cityRows as $index => $row): ?>
                            <?php
                            $count = (int)$row['users_count'];
                            $percent = $totalCityUsers > 0 ? round(($count / $totalCityUsers) * 100) : 0;
                            ?>

                            <div class="admin-city-legend__item">
                                <span style="background: <?= e($chartColors[$index % count($chartColors)]) ?>;"></span>
                                <p><?= e((string)$row['city_name']) ?> — <?= e((string)$percent) ?>%</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="admin-empty-stat">Пока нет данных для построения диаграммы.</div>
            <?php endif; ?>

            <div class="admin-report-status-block">
                <div class="admin-stat-panel__header admin-stat-panel__header--small">
                    <h2>Статусы жалоб</h2>
                    <p>Общее распределение жалоб по текущему состоянию обработки.</p>
                </div>

                <?php if ($totalReportStatuses > 0): ?>
                    <div class="admin-city-chart-wrap admin-city-chart-wrap--compact">
                        <div class="admin-city-chart admin-city-chart--small" style="background: <?= e($reportChartGradient) ?>;"></div>

                        <div class="admin-city-legend">
                            <?php foreach ($reportStatusRows as $index => $row): ?>
                                <?php
                                $count = (int)$row['count'];
                                $percent = $totalReportStatuses > 0 ? round(($count / $totalReportStatuses) * 100) : 0;
                                ?>

                                <div class="admin-city-legend__item">
                                    <span style="background: <?= e($reportChartColors[$index % count($reportChartColors)]) ?>;"></span>
                                    <p><?= e((string)$row['name']) ?> — <?= e((string)$percent) ?>%</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-stat">Жалоб пока нет.</div>
                <?php endif; ?>
            </div>
        </article>

        <article class="admin-stat-panel">
            <div class="admin-stat-panel__header">
                <h2>Популярные интересы</h2>
                <p>Процент показывает долю пользователей, выбравших интерес.</p>
            </div>

            <?php if (count($interestRows) > 0): ?>
                <div class="admin-interest-list">
                    <?php foreach ($interestRows as $interest): ?>
                        <div class="admin-interest-row">
                            <div class="admin-interest-row__top">
                                <span><?= e((string)$interest['name']) ?></span>
                                <strong><?= e((string)$interest['percent']) ?>%</strong>
                            </div>

                            <div class="admin-interest-row__bar">
                                <span style="width: <?= e((string)min((float)$interest['percent'], 100)) ?>%;"></span>
                            </div>

                            <small><?= e((string)$interest['users_count']) ?> пользователей</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-empty-stat">Пользователи пока не выбрали интересы.</div>
            <?php endif; ?>
        </article>
    </div>
</section>

<?php
$pageContent = ob_get_clean();

require_once __DIR__ . '/../modules/dashboard-layout.php';