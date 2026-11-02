<?php
$basePath = '..';

require_once __DIR__ . '/../php/moderator-check.php';

$conn = auth_require_db();
$currentModeratorId = (int)($_SESSION['user_id'] ?? 0);

$dashboardUserName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Сотрудник';
$dashboardUserRole = 'Модератор';
$logoutUrl = 'php/logout.php';
$pageTitle = 'Жалобы';
$pageDescription = 'Панель модератора. Раздел для просмотра и обработки жалоб пользователей.';
$activePage = 'complaints';
$dashboardHome = 'pages/moderator-complaints.php';

$navItems = [
    'complaints' => ['title' => 'Жалобы', 'href' => 'pages/moderator-complaints.php', 'icon' => 'nav-chats.svg'],
    'appeals' => ['title' => 'Апелляции', 'href' => 'pages/moderator-appeals.php', 'icon' => 'nav-feed.svg'],
];

function mc_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mc_attr(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mc_bind(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || $params === []) {
        return;
    }

    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function mc_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    mc_bind($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function mc_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = mc_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function mc_count(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $row = mc_one($conn, $sql, $types, $params);
    return (int)($row['total'] ?? 0);
}

function mc_execute(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    mc_bind($stmt, $types, $params);
    $stmt->execute();
    return $stmt;
}

function mc_csrf_token(): string
{
    if (empty($_SESSION['moderator_complaints_csrf'])) {
        $_SESSION['moderator_complaints_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['moderator_complaints_csrf'];
}

function mc_check_csrf(): bool
{
    return hash_equals((string)($_SESSION['moderator_complaints_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''));
}

function mc_flash(string $message, bool $isError = false): void
{
    $_SESSION[$isError ? 'moderator_complaints_error' : 'moderator_complaints_success'] = $message;
}

function mc_redirect(array $params = []): never
{
    $url = 'moderator-complaints.php';
    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }
    header('Location: ' . $url);
    exit;
}

function mc_limit(string $key): int
{
    $limit = (int)($_GET[$key] ?? 6);
    if ($limit < 6) {
        return 6;
    }
    return min(60, (int)(ceil($limit / 6) * 6));
}

function mc_keep_limits_from_post(): array
{
    return [
        'new_limit' => max(6, min(60, (int)($_POST['new_limit'] ?? 6))),
        'work_limit' => max(6, min(60, (int)($_POST['work_limit'] ?? 6))),
        'done_limit' => max(6, min(60, (int)($_POST['done_limit'] ?? 6))),
    ];
}

function mc_more_url(string $key, int $currentLimit): string
{
    $params = $_GET;
    unset($params['report_id'], $params['mode']);
    $params[$key] = min(60, $currentLimit + 6);
    return 'moderator-complaints.php?' . http_build_query($params);
}

function mc_open_url(int $reportId, bool $review = false): string
{
    $params = $_GET;
    $params['report_id'] = $reportId;
    if ($review) {
        $params['mode'] = 'review';
    } else {
        unset($params['mode']);
    }
    return 'moderator-complaints.php?' . http_build_query($params);
}

function mc_asset_url(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    $path = ltrim($path, '/');
    if (str_starts_with($path, 'assets/') || str_starts_with($path, 'images/')) {
        return '../' . $path;
    }

    return '../images/' . $path;
}

function mc_initials(?string $name): string
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'М';
    }
    return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
}

function mc_avatar(?string $path, ?string $name, string $class = ''): string
{
    $url = mc_asset_url($path);
    $classAttr = trim('mc-avatar ' . $class);
    if ($url !== '') {
        return '<img class="' . mc_attr($classAttr) . '" src="' . mc_attr($url) . '" alt="' . mc_attr($name ?: 'Пользователь') . '">';
    }
    return '<span class="' . mc_attr($classAttr . ' mc-avatar--empty') . '">' . mc_h(mc_initials($name)) . '</span>';
}

function mc_date(?string $value): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('d.m.Y H:i');
    } catch (Throwable) {
        return $value;
    }
}

function mc_user_display(array $row, string $prefix): array
{
    $name = (string)($row[$prefix . '_name'] ?? '');
    $username = (string)($row[$prefix . '_username'] ?? '');
    $age = $row[$prefix . '_age'] ?? null;

    if ($name === '') {
        $name = $username !== '' ? $username : 'Пользователь';
    }

    return [
        'id' => (int)($row[$prefix . '_id'] ?? 0),
        'name' => $name,
        'username' => $username,
        'age' => $age !== null ? (int)$age : null,
        'avatar' => (string)($row[$prefix . '_avatar'] ?? ''),
        'city' => (string)($row[$prefix . '_city'] ?? ''),
        'about' => (string)($row[$prefix . '_about'] ?? ''),
        'interests' => (string)($row[$prefix . '_interests'] ?? ''),
        'goals' => (string)($row[$prefix . '_goals'] ?? ''),
    ];
}

function mc_user_button(array $row, string $prefix): string
{
    $user = mc_user_display($row, $prefix);
    $title = $user['name'] . ($user['age'] ? ', ' . $user['age'] : '');
    $username = $user['username'] !== '' ? '@' . $user['username'] : 'ID: ' . $user['id'];

    return '<button class="mc-user-link js-profile-open" type="button"'
        . ' data-profile-id="' . mc_attr((string)$user['id']) . '"'
        . ' data-profile-name="' . mc_attr($user['name']) . '"'
        . ' data-profile-age="' . mc_attr((string)($user['age'] ?? '')) . '"'
        . ' data-profile-username="' . mc_attr($user['username']) . '"'
        . ' data-profile-avatar="' . mc_attr(mc_asset_url($user['avatar'])) . '"'
        . ' data-profile-city="' . mc_attr($user['city']) . '"'
        . ' data-profile-about="' . mc_attr($user['about']) . '"'
        . ' data-profile-interests="' . mc_attr($user['interests']) . '"'
        . ' data-profile-goals="' . mc_attr($user['goals']) . '">'
        . mc_avatar($user['avatar'], $user['name'], 'mc-user-link__avatar')
        . '<span><b>' . mc_h($title) . '</b><small>' . mc_h($username) . '</small></span>'
        . '</button>';
}

function mc_interest_items(?string $value): array
{
    $raw = preg_split('/\s*,\s*/u', trim((string)$value)) ?: [];
    $items = [];

    foreach ($raw as $item) {
        $item = trim((string)$item);
        if ($item === '') {
            continue;
        }
        $items[$item] = $item;
    }

    return array_values($items);
}

function mc_interest_chips(?string $value, string $class = 'mc-chip', string $emptyText = 'Интересы не указаны'): string
{
    $items = mc_interest_items($value);
    if ($items === []) {
        return '<span class="mc-muted">' . mc_h($emptyText) . '</span>';
    }

    $html = '<div class="mc-chip-list">';
    foreach ($items as $item) {
        $html .= '<span class="' . mc_attr($class) . '">' . mc_h($item) . '</span>';
    }
    $html .= '</div>';

    return $html;
}

function mc_goal_labels(): array
{
    return [
        'communication' => 'Общение',
        'friendship' => 'Дружба',
        'joint_activities' => 'Совместный досуг',
        'relationship' => 'Отношения',
        'support' => 'Поддержка',
    ];
}

function mc_goal_items(?string $value): array
{
    $labels = mc_goal_labels();
    $raw = preg_split('/\s*,\s*/u', trim((string)$value)) ?: [];
    $items = [];

    foreach ($raw as $item) {
        $item = trim((string)$item);
        if ($item === '') {
            continue;
        }
        $items[] = $labels[$item] ?? $item;
    }

    return array_values(array_unique($items));
}

function mc_goal_chips(?string $value): string
{
    $items = mc_goal_items($value);
    if ($items === []) {
        return '<span class="mc-muted">Формат общения не указан</span>';
    }

    $html = '<div class="mc-chip-list">';
    foreach ($items as $item) {
        $html .= '<span class="mc-chip">' . mc_h($item) . '</span>';
    }
    $html .= '</div>';

    return $html;
}

function mc_object_badge(array $row): string
{
    $target = (string)($row['target_type'] ?? 'profile');
    $label = $target === 'message' || !empty($row['message_id']) ? 'Сообщение' : 'Профиль';
    $class = $label === 'Сообщение' ? 'mc-badge--message' : 'mc-badge--profile';
    return '<span class="mc-badge ' . $class . '">' . mc_h($label) . '</span>';
}

function mc_report_select(): string
{
    return "
        SELECT
            r.id,
            r.author_id,
            au.id AS author_id,
            r.reported_user_id,
            ru.id AS reported_id,
            r.message_id,
            r.reason_id,
            r.details,
            r.status,
            r.assigned_moderator_id,
            r.created_at,
            r.taken_at,
            r.closed_at,
            rr.name AS reason_name,
            rr.target_type,
            rr.description AS reason_description,
            au.username AS author_username,
            ap.display_name AS author_name,
            ap.avatar_path AS author_avatar,
            ap.about AS author_about,
            ap.communication_goal AS author_goals,
            COALESCE(ai_selected.interests_list, '') AS author_interests,
            ac.name AS author_city,
            TIMESTAMPDIFF(YEAR, ap.birth_date, CURDATE()) AS author_age,
            ru.username AS reported_username,
            rp.display_name AS reported_name,
            rp.avatar_path AS reported_avatar,
            rp.about AS reported_about,
            rp.communication_goal AS reported_goals,
            COALESCE(ri_selected.interests_list, '') AS reported_interests,
            rc.name AS reported_city,
            TIMESTAMPDIFF(YEAR, rp.birth_date, CURDATE()) AS reported_age,
            m.message_text,
            m.sent_at AS message_sent_at,
            COALESCE(r.closed_at, r.taken_at, r.created_at) AS list_date,
            md.decision_type,
            md.comment AS decision_comment,
            md.created_at AS decision_created_at
        FROM reports r
        INNER JOIN report_reasons rr ON rr.id = r.reason_id
        INNER JOIN users au ON au.id = r.author_id
        LEFT JOIN user_profiles ap ON ap.user_id = au.id
        LEFT JOIN cities ac ON ac.id = ap.city_id
        INNER JOIN users ru ON ru.id = r.reported_user_id
        LEFT JOIN user_profiles rp ON rp.user_id = ru.id
        LEFT JOIN cities rc ON rc.id = rp.city_id
        LEFT JOIN (
            SELECT ui.user_id, GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR ', ') AS interests_list
            FROM user_interests ui
            INNER JOIN interests i ON i.id = ui.interest_id
            WHERE i.is_active = 1
            GROUP BY ui.user_id
        ) ai_selected ON ai_selected.user_id = au.id
        LEFT JOIN (
            SELECT ui.user_id, GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR ', ') AS interests_list
            FROM user_interests ui
            INNER JOIN interests i ON i.id = ui.interest_id
            WHERE i.is_active = 1
            GROUP BY ui.user_id
        ) ri_selected ON ri_selected.user_id = ru.id
        LEFT JOIN messages m ON m.id = r.message_id
        LEFT JOIN moderation_decisions md ON md.report_id = r.id
    ";
}

function mc_fetch_reports(mysqli $conn, string $where, string $types, array $params, string $order, int $limit): array
{
    $sql = mc_report_select() . " WHERE {$where} ORDER BY {$order} LIMIT ?";
    $types .= 'i';
    $params[] = $limit;
    return mc_all($conn, $sql, $types, $params);
}

function mc_fetch_open_report(mysqli $conn, int $reportId, int $moderatorId): ?array
{
    return mc_one(
        $conn,
        mc_report_select() . "
            WHERE r.id = ?
              AND r.assigned_moderator_id = ?
              AND r.status IN ('in_review', 'resolved', 'rejected')
            LIMIT 1
        ",
        'ii',
        [$reportId, $moderatorId]
    );
}

function mc_fetch_report_history(mysqli $conn, int $reportedUserId, int $currentReportId): array
{
    return mc_all(
        $conn,
        "
            SELECT r.id, r.status, r.created_at, rr.name AS reason_name, rr.target_type
            FROM reports r
            INNER JOIN report_reasons rr ON rr.id = r.reason_id
            WHERE r.reported_user_id = ? AND r.id <> ?
            ORDER BY r.created_at DESC
            LIMIT 5
        ",
        'ii',
        [$reportedUserId, $currentReportId]
    );
}

function mc_fetch_previous_reports_total(mysqli $conn, int $reportedUserId, int $currentReportId): int
{
    return mc_count(
        $conn,
        "SELECT COUNT(*) AS total FROM reports WHERE reported_user_id = ? AND id <> ?",
        'ii',
        [$reportedUserId, $currentReportId]
    );
}

function mc_fetch_restriction_stats(mysqli $conn, int $reportedUserId): array
{
    $row = mc_one(
        $conn,
        "
            SELECT
                COUNT(*) AS restrictions_count,
                COALESCE(SUM(CASE WHEN ends_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, starts_at, ends_at) ELSE 0 END), 0) AS restricted_minutes
            FROM user_restrictions
            WHERE user_id = ?
        ",
        'i',
        [$reportedUserId]
    );

    return [
        'count' => (int)($row['restrictions_count'] ?? 0),
        'hours' => round(((int)($row['restricted_minutes'] ?? 0)) / 60, 1),
    ];
}

function mc_render_rows(array $rows, string $type, string $csrfToken, array $limits): string
{
    if ($rows === []) {
        return '<tr><td colspan="6" class="mc-empty">Пока здесь нет жалоб.</td></tr>';
    }

    $html = '';
    foreach ($rows as $row) {
        $date = mc_date((string)($row['list_date'] ?? $row['created_at'] ?? ''));
        $html .= '<tr>';
        $html .= '<td>' . mc_user_button($row, 'author') . '</td>';
        $html .= '<td>' . mc_user_button($row, 'reported') . '</td>';
        $html .= '<td><span class="mc-reason">' . mc_h((string)$row['reason_name']) . '</span></td>';
        $html .= '<td>' . mc_object_badge($row) . '</td>';
        $html .= '<td><span class="mc-date">' . mc_h($date) . '</span></td>';
        $html .= '<td class="mc-actions-cell">';

        if ($type === 'new') {
            $html .= '<form method="post" class="mc-inline-form">'
                . '<input type="hidden" name="csrf_token" value="' . mc_attr($csrfToken) . '">'
                . '<input type="hidden" name="action" value="take_report">'
                . '<input type="hidden" name="report_id" value="' . mc_attr((string)$row['id']) . '">'
                . '<input type="hidden" name="new_limit" value="' . mc_attr((string)$limits['new_limit']) . '">'
                . '<input type="hidden" name="work_limit" value="' . mc_attr((string)$limits['work_limit']) . '">'
                . '<input type="hidden" name="done_limit" value="' . mc_attr((string)$limits['done_limit']) . '">'
                . '<button class="mc-action mc-action--primary" type="submit">Принять</button>'
                . '</form>';
        } elseif ($type === 'work') {
            $html .= '<a class="mc-action mc-action--soft" href="' . mc_attr(mc_open_url((int)$row['id'])) . '">Открыть</a>';
        } else {
            $html .= '<a class="mc-action mc-action--review" href="' . mc_attr(mc_open_url((int)$row['id'], true)) . '">Пересмотр</a>';
        }

        $html .= '</td></tr>';
    }

    return $html;
}

function mc_take_report(mysqli $conn, int $reportId, int $moderatorId): bool
{
    $conn->begin_transaction();

    try {
        $stmt = mc_execute(
            $conn,
            "
                UPDATE reports
                SET status = 'in_review', assigned_moderator_id = ?, taken_at = NOW()
                WHERE id = ? AND status = 'new' AND assigned_moderator_id IS NULL
            ",
            'ii',
            [$moderatorId, $reportId]
        );

        if ($stmt->affected_rows !== 1) {
            $conn->rollback();
            return false;
        }

        mc_execute(
            $conn,
            "
                INSERT INTO action_log (actor_user_id, action_type, entity_type, entity_id, description, ip_address)
                VALUES (?, 'report_taken', 'report', ?, 'Модератор принял жалобу в работу.', ?)
            ",
            'iis',
            [$moderatorId, $reportId, $_SERVER['REMOTE_ADDR'] ?? null]
        );

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function mc_sync_reported_user_status(mysqli $conn, int $userId): void
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*)
         FROM user_restrictions
         WHERE user_id = ?
           AND status = 'active'
           AND restriction_type IN ('temporary_block', 'permanent_block')
           AND (ends_at IS NULL OR ends_at > NOW())"
    );
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $activeRestrictionsCount);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ((int)$activeRestrictionsCount > 0) {
        mc_execute($conn, "UPDATE users SET status = 'blocked' WHERE id = ? AND status <> 'deleted'", 'i', [$userId]);
        return;
    }

    mc_execute($conn, "UPDATE users SET status = 'active' WHERE id = ? AND status = 'blocked'", 'i', [$userId]);
}

function mc_apply_decision(mysqli $conn, int $reportId, int $moderatorId, string $decisionAction, float $hours, string $moderatorComment): void
{
    $allowed = ['no_violation', 'warning', 'temporary_restriction'];
    if (!in_array($decisionAction, $allowed, true)) {
        throw new RuntimeException('Выберите корректное решение по жалобе.');
    }

    if ($decisionAction === 'temporary_restriction' && $hours <= 0) {
        throw new RuntimeException('Укажите количество часов блокировки. Можно указать 0.5 для получаса.');
    }

    $conn->begin_transaction();

    try {
        $report = mc_one(
            $conn,
            "
                SELECT id, reported_user_id, status
                FROM reports
                WHERE id = ? AND assigned_moderator_id = ? AND status IN ('in_review', 'resolved', 'rejected')
                FOR UPDATE
            ",
            'ii',
            [$reportId, $moderatorId]
        );

        if (!$report) {
            throw new RuntimeException('Жалоба недоступна или уже закреплена за другим модератором.');
        }

        $reportedUserId = (int)$report['reported_user_id'];
        $defaultComment = match ($decisionAction) {
            'no_violation' => 'Жалоба отклонена: нарушение не подтверждено.',
            'warning' => 'Пользователю выдано предупреждение за нарушение правил сообщества.',
            'temporary_restriction' => 'Пользователь временно заблокирован на ' . $hours . ' ч.',
        };
        $comment = trim($moderatorComment) !== '' ? trim($moderatorComment) : $defaultComment;

        $decision = mc_one(
            $conn,
            "SELECT id FROM moderation_decisions WHERE report_id = ? FOR UPDATE",
            'i',
            [$reportId]
        );

        if ($decision) {
            $decisionId = (int)$decision['id'];
            mc_execute(
                $conn,
                "
                    UPDATE moderation_decisions
                    SET moderator_id = ?, decision_type = ?, comment = ?, created_at = NOW()
                    WHERE id = ?
                ",
                'issi',
                [$moderatorId, $decisionAction, $comment, $decisionId]
            );

            mc_execute(
                $conn,
                "
                    UPDATE user_restrictions
                    SET status = 'lifted', lifted_by_user_id = ?, lifted_at = NOW()
                    WHERE decision_id = ? AND status = 'active'
                ",
                'ii',
                [$moderatorId, $decisionId]
            );
        } else {
            mc_execute(
                $conn,
                "
                    INSERT INTO moderation_decisions (report_id, moderator_id, decision_type, comment)
                    VALUES (?, ?, ?, ?)
                ",
                'iiss',
                [$reportId, $moderatorId, $decisionAction, $comment]
            );
            $decisionId = (int)$conn->insert_id;
        }

        if ($decisionAction === 'temporary_restriction') {
            $minutes = max(1, (int)round($hours * 60));
            mc_execute(
                $conn,
                "
                    INSERT INTO user_restrictions (user_id, decision_id, restriction_type, starts_at, ends_at, status, created_by_user_id)
                    VALUES (?, ?, 'temporary_block', NOW(), DATE_ADD(NOW(), INTERVAL {$minutes} MINUTE), 'active', ?)
                ",
                'iii',
                [$reportedUserId, $decisionId, $moderatorId]
            );
        }

        mc_sync_reported_user_status($conn, $reportedUserId);

        mc_execute(
            $conn,
            "
                UPDATE reports
                SET status = 'resolved', closed_at = NOW()
                WHERE id = ?
            ",
            'i',
            [$reportId]
        );

        mc_execute(
            $conn,
            "
                INSERT INTO action_log (actor_user_id, action_type, entity_type, entity_id, description, ip_address)
                VALUES (?, 'report_decision', 'report', ?, ?, ?)
            ",
            'iiss',
            [$moderatorId, $reportId, $comment, $_SERVER['REMOTE_ADDR'] ?? null]
        );

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!mc_check_csrf()) {
            throw new RuntimeException('Сессия устарела. Обновите страницу и попробуйте снова.');
        }

        $action = (string)($_POST['action'] ?? '');
        $reportId = (int)($_POST['report_id'] ?? 0);
        if ($reportId <= 0) {
            throw new RuntimeException('Жалоба не найдена.');
        }

        $limits = mc_keep_limits_from_post();

        if ($action === 'take_report') {
            if (!mc_take_report($conn, $reportId, $currentModeratorId)) {
                mc_flash('Жалобу уже принял другой модератор или она больше не находится в новых.', true);
                mc_redirect($limits);
            }

            mc_flash('Жалоба принята в работу.');
            mc_redirect($limits + ['report_id' => $reportId]);
        }

        if ($action === 'make_decision') {
            $decisionAction = (string)($_POST['decision_action'] ?? '');
            $hoursRaw = str_replace(',', '.', (string)($_POST['block_hours'] ?? '0'));
            $hours = is_numeric($hoursRaw) ? (float)$hoursRaw : 0.0;
            $comment = (string)($_POST['moderator_comment'] ?? '');

            mc_apply_decision($conn, $reportId, $currentModeratorId, $decisionAction, $hours, $comment);
            mc_flash('Решение по жалобе сохранено.');
            mc_redirect($limits + ['done_limit' => max($limits['done_limit'], 6)]);
        }

        throw new RuntimeException('Некорректное действие.');
    } catch (Throwable $e) {
        mc_flash($e->getMessage(), true);
        mc_redirect(mc_keep_limits_from_post());
    }
}

$csrfToken = mc_csrf_token();
$newLimit = mc_limit('new_limit');
$workLimit = mc_limit('work_limit');
$doneLimit = mc_limit('done_limit');
$limits = ['new_limit' => $newLimit, 'work_limit' => $workLimit, 'done_limit' => $doneLimit];

$newReports = mc_fetch_reports(
    $conn,
    "r.status = 'new' AND r.assigned_moderator_id IS NULL",
    '',
    [],
    'r.created_at ASC',
    $newLimit
);
$workReports = mc_fetch_reports(
    $conn,
    "r.status = 'in_review' AND r.assigned_moderator_id = ?",
    'i',
    [$currentModeratorId],
    'r.taken_at DESC, r.created_at DESC',
    $workLimit
);
$doneReports = mc_fetch_reports(
    $conn,
    "r.status IN ('resolved', 'rejected') AND r.assigned_moderator_id = ?",
    'i',
    [$currentModeratorId],
    'r.closed_at DESC, r.created_at DESC',
    $doneLimit
);

$newTotal = mc_count($conn, "SELECT COUNT(*) AS total FROM reports WHERE status = 'new' AND assigned_moderator_id IS NULL");
$workTotal = mc_count($conn, "SELECT COUNT(*) AS total FROM reports WHERE status = 'in_review' AND assigned_moderator_id = ?", 'i', [$currentModeratorId]);
$doneTotal = mc_count($conn, "SELECT COUNT(*) AS total FROM reports WHERE status IN ('resolved', 'rejected') AND assigned_moderator_id = ?", 'i', [$currentModeratorId]);

$openedReportId = (int)($_GET['report_id'] ?? 0);
$openedReport = $openedReportId > 0 ? mc_fetch_open_report($conn, $openedReportId, $currentModeratorId) : null;
$reportHistory = [];
$restrictionStats = ['count' => 0, 'hours' => 0];
$previousReportsTotal = 0;
if ($openedReport) {
    $reportHistory = mc_fetch_report_history($conn, (int)$openedReport['reported_user_id'], (int)$openedReport['id']);
    $previousReportsTotal = mc_fetch_previous_reports_total($conn, (int)$openedReport['reported_user_id'], (int)$openedReport['id']);
    $restrictionStats = mc_fetch_restriction_stats($conn, (int)$openedReport['reported_user_id']);
}

$successMessage = (string)($_SESSION['moderator_complaints_success'] ?? '');
$errorMessage = (string)($_SESSION['moderator_complaints_error'] ?? '');
unset($_SESSION['moderator_complaints_success'], $_SESSION['moderator_complaints_error']);

ob_start();
?>

<style>
    .moderator-complaints{display:grid;gap:22px}
    .mc-alert{padding:14px 16px;border-radius:18px;font-weight:700}
    .mc-alert--success{background:#f0fff5;color:#3e8c56;border:1px solid #ccefd8}
    .mc-alert--error{background:#fff1f1;color:#d64545;border:1px solid #ffd1d1}
    .mc-board{background:#fff;border:1px solid rgba(120,100,180,.12);border-radius:28px;box-shadow:0 18px 50px rgba(77,57,118,.08);overflow:hidden}
    .mc-board__head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:24px 28px;background:linear-gradient(90deg,rgba(255,244,233,.9),rgba(247,241,255,.9))}
    .mc-board__head h2{margin:0;color:#18142b;font-size:22px}
    .mc-board__head p{margin:6px 0 0;color:#777197;font-size:14px}
    .mc-counter{display:inline-flex;align-items:center;justify-content:center;min-width:42px;padding:8px 14px;border-radius:999px;background:#f1e8ff;color:#7a5ce6;font-weight:800}
    .mc-table-wrap{overflow-x:auto}
    .mc-table{width:100%;border-collapse:collapse;min-width:940px}
    .mc-table th{padding:16px 22px;text-align:left;color:#8d89ab;font-size:13px;font-weight:800;background:rgba(250,247,255,.75)}
    .mc-table td{padding:16px 22px;border-top:1px solid rgba(120,100,180,.12);vertical-align:middle;color:#363251}
    .mc-user-link{display:inline-flex;align-items:center;gap:12px;padding:0;border:0;background:transparent;text-align:left;cursor:pointer;color:inherit}
    .mc-user-link:hover b{color:#7a5ce6}
    .mc-user-link b{display:block;color:#302b50;font-size:15px;line-height:1.25;transition:.2s}
    .mc-user-link small{display:block;margin-top:3px;color:#8e89ad;font-size:13px}
    .mc-avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;flex:0 0 auto;background:#f2ecff;border:1px solid rgba(122,92,230,.12)}
    .mc-avatar--empty{display:inline-flex;align-items:center;justify-content:center;color:#7a5ce6;font-weight:900}
    .mc-reason{font-weight:700;color:#4a4569}
    .mc-badge{display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border-radius:999px;font-weight:800;font-size:13px}
    .mc-badge--message{background:#f1e8ff;color:#7758e4}
    .mc-badge--profile{background:#fff0e5;color:#f07a3d}
    .mc-date{white-space:nowrap;color:#4f4a70;font-weight:700}
    .mc-actions-cell{white-space:nowrap}
    .mc-inline-form{margin:0}
    .mc-action{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 18px;border-radius:14px;border:1px solid transparent;text-decoration:none;font-weight:800;font-family:inherit;cursor:pointer;transition:.2s}
    .mc-action:hover{transform:translateY(-1px)}
    .mc-action--primary{background:#7a5ce6;color:#fff;box-shadow:0 10px 24px rgba(122,92,230,.2)}
    .mc-action--soft{background:#f4efff;color:#7455df}
    .mc-action--review{background:#fff4e9;color:#f07a3d}
    .mc-more{padding:16px 22px;border-top:1px solid rgba(120,100,180,.12);text-align:center}
    .mc-more a{display:inline-flex;padding:11px 18px;border-radius:16px;background:#faf7ff;border:1px solid rgba(122,92,230,.18);color:#7455df;text-decoration:none;font-weight:800}
    .mc-empty{text-align:center;color:#9c96b9;font-weight:700;padding:28px!important}
    .mc-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:32px 18px;background:rgba(24,20,43,.42);overflow:auto}
    .mc-modal.is-open{display:flex}
    .mc-modal__card{width:min(920px,100%);background:#fff;border-radius:32px;box-shadow:0 24px 70px rgba(40,30,70,.24);padding:28px}
    .mc-modal__top{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:20px}
    .mc-back{display:inline-flex;align-items:center;gap:8px;padding:12px 16px;border-radius:18px;background:#f7f2ff;color:#302b50;text-decoration:none;font-weight:800}
    .mc-close{width:46px;height:46px;border-radius:16px;border:1px solid rgba(120,100,180,.14);background:#fff;color:#6f698d;font-size:28px;line-height:1;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
    .mc-close:hover{background:#faf7ff}
    .mc-modal h2{margin:0 0 24px;color:#18142b;font-size:30px}
    .mc-report-head{display:flex;align-items:center;justify-content:space-between;gap:18px;padding-bottom:22px;border-bottom:1px solid rgba(120,100,180,.14)}
    .mc-report-user{display:flex;align-items:center;gap:18px}
    .mc-report-user .mc-avatar{width:88px;height:88px;font-size:34px}
    .mc-report-user b{font-size:24px}
    .mc-report-user small{font-size:17px;color:#7a5ce6}
    .mc-report-id{display:grid;gap:12px;justify-items:end}
    .mc-report-id span:first-child{padding:12px 20px;border-radius:999px;background:#f1e8ff;color:#7455df;font-size:20px;font-weight:900}
    .mc-report-id span:last-child{font-weight:800;color:#575171}
    .mc-modal-grid{display:grid;gap:18px;margin-top:22px}
    .mc-meta-row{display:flex;justify-content:space-between;gap:16px;align-items:center}
    .mc-label{display:block;color:#8f8aaa;font-weight:800;margin-bottom:8px}
    .mc-value{color:#282342;font-weight:800;font-size:18px}
    .mc-content-box{border:1px solid rgba(120,100,180,.14);border-radius:24px;padding:18px;background:linear-gradient(180deg,#fff,#fffbf8)}
    .mc-content-box p{margin:8px 0 0;color:#4d4869;line-height:1.55}
    .mc-message-card{display:flex;gap:14px;width:min(560px,100%);padding:16px;border:1px solid rgba(120,100,180,.16);border-radius:18px;background:#fff}
    .mc-message-card .mc-avatar{width:54px;height:54px}
    .mc-message-card b{color:#7a5ce6}
    .mc-message-card p{margin:6px 0;color:#383351}
    .mc-message-card time{color:#817b9f;font-weight:700}
    .mc-chip-list,.mc-profile-chips{display:flex;flex-wrap:wrap;gap:10px}
    .mc-chip,.mc-profile-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:#f4efff;color:#7455df;font-weight:800;font-size:14px;line-height:1.2}
    .mc-muted{color:#8d89ab;font-weight:700}
    .mc-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .mc-stat{padding:18px;border-radius:22px;background:#faf7ff;border:1px solid rgba(122,92,230,.14)}
    .mc-stat strong{display:block;font-size:24px;color:#7455df;line-height:1.1}
    .mc-stat span{display:block;margin-top:8px;color:#777197;font-weight:700}
    .mc-history{display:grid;gap:8px}
    .mc-history__item{display:flex;justify-content:space-between;gap:12px;padding:12px 14px;border-radius:16px;background:#fbf9ff;color:#4a4569}
    .mc-decision-form{display:grid;gap:16px}
    .mc-decision-title{margin:10px 0 0;color:#302b50;font-size:20px;font-weight:900}
    .mc-decision-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .mc-decision-option{position:relative;min-width:0}
    .mc-decision-option input{position:absolute;opacity:0;pointer-events:none}
    .mc-decision-option span{box-sizing:border-box;display:flex;min-height:96px;flex-direction:column;align-items:flex-start;justify-content:center;gap:7px;text-align:left;padding:18px 20px;border-radius:22px;border:2px solid rgba(120,100,180,.16);font-weight:900;color:#4e486a;background:#fff;cursor:pointer;line-height:1.25;transition:.2s;box-shadow:0 10px 26px rgba(78,72,106,.04);white-space:normal;overflow-wrap:anywhere}
    .mc-decision-option strong{display:block;width:100%;font-size:18px;line-height:1.25;white-space:normal}
    .mc-decision-option small{display:block;width:100%;font-size:13px;line-height:1.4;font-weight:800;color:#8f8aaa;white-space:normal}
    .mc-decision-option:hover span{border-color:rgba(122,92,230,.32);background:#fbf9ff;box-shadow:0 14px 28px rgba(122,92,230,.08)}
    .mc-decision-option input:checked+span{background:#f6f1ff;border-color:#7a5ce6;color:#7455df;box-shadow:0 14px 30px rgba(122,92,230,.14)}
    .mc-decision-option input:checked+span small{color:#7455df}
    .mc-decision-option--danger{grid-column:1/-1}
    .mc-decision-option--warning input:checked+span{background:#fff8ee;border-color:#f0ae5f;color:#d87f24}
    .mc-decision-option--warning input:checked+span small{color:#d87f24}
    .mc-decision-option--danger input:checked+span{background:#fff3f3;border-color:#ff9b9b;color:#e33d3d}
    .mc-decision-option--danger input:checked+span small{color:#e33d3d}
    .mc-form-row{display:grid;grid-template-columns:220px 1fr;gap:14px}
    .mc-input,.mc-textarea{width:100%;border:1px solid rgba(120,100,180,.18);border-radius:18px;padding:16px 18px;font:inherit;color:#302b50;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s}
    .mc-input:focus,.mc-textarea:focus{border-color:#7a5ce6;box-shadow:0 0 0 4px rgba(122,92,230,.1)}
    .mc-input:disabled{background:#f7f5fb;color:#aaa5bf;cursor:not-allowed}
    .mc-textarea{min-height:96px;resize:vertical}
    .mc-submit{min-height:56px;border:0;border-radius:20px;background:#7a5ce6;color:#fff;font:inherit;font-weight:900;cursor:pointer;box-shadow:0 14px 30px rgba(122,92,230,.24)}
    .mc-submit:hover{transform:translateY(-1px)}
    .mc-note{display:flex;gap:12px;align-items:flex-start;padding:16px 18px;border-radius:20px;border:1px solid rgba(122,92,230,.18);background:#faf7ff;color:#655f86;font-weight:700;line-height:1.45}
    .mc-note b{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#ece3ff;color:#7a5ce6;flex:0 0 auto}
    .mc-profile-card{width:min(760px,100%)}
    .mc-profile-pill{display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border-radius:18px;background:#f7f2ff;color:#4f4a70;font-weight:800}
    .mc-profile-hero{display:flex;align-items:flex-start;gap:18px}
    .mc-profile-hero .mc-avatar{width:92px;height:92px;font-size:34px}
    .mc-profile-main h3{margin:0;color:#18142b;font-size:30px;line-height:1.15}
    .mc-profile-main p{margin:8px 0 0;color:#7a5ce6;font-weight:800;font-size:20px}
    .mc-profile-main .mc-profile-city{margin-top:10px;color:#7d779b;font-size:15px;font-weight:700}
    .mc-profile-divider{height:1px;background:rgba(120,100,180,.12);margin:24px 0}
    .mc-profile-sections{display:grid;gap:18px}
    .mc-profile-block{padding:18px 20px;border-radius:22px;background:#faf7ff}
    .mc-profile-block h4{margin:0 0 12px;color:#302b50;font-size:18px}
    .mc-profile-block p{margin:0;color:#4d4869;line-height:1.55}
    .mc-profile-empty{color:#8d89ab;font-weight:700}
    @media(max-width:920px){.mc-decision-grid{grid-template-columns:1fr}}
    @media(max-width:760px){.mc-board__head,.mc-report-head,.mc-meta-row,.mc-profile-hero{flex-direction:column;align-items:flex-start}.mc-report-id{justify-items:start}.mc-modal__card{padding:20px;border-radius:24px}.mc-stats,.mc-form-row{grid-template-columns:1fr}.mc-modal h2{font-size:24px}.mc-report-user .mc-avatar{width:72px;height:72px}.mc-report-user b{font-size:20px}.mc-profile-main h3{font-size:24px}.mc-profile-main p{font-size:18px}}
</style>

<div class="moderator-complaints">
    <?php if ($successMessage !== ''): ?>
        <div class="mc-alert mc-alert--success"><?= mc_h($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="mc-alert mc-alert--error"><?= mc_h($errorMessage) ?></div>
    <?php endif; ?>

    <section class="mc-board">
        <div class="mc-board__head">
            <div>
                <h2>Новые жалобы</h2>
                <p>Общий список. После принятия жалоба закрепляется за вами.</p>
            </div>
            <span class="mc-counter"><?= $newTotal ?></span>
        </div>
        <div class="mc-table-wrap">
            <table class="mc-table">
                <thead>
                    <tr>
                        <th>Отправитель</th>
                        <th>Жалоба на</th>
                        <th>Причина</th>
                        <th>Объект</th>
                        <th>Дата</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody><?= mc_render_rows($newReports, 'new', $csrfToken, $limits) ?></tbody>
            </table>
        </div>
        <?php if ($newTotal > $newLimit): ?>
            <div class="mc-more"><a href="<?= mc_attr(mc_more_url('new_limit', $newLimit)) ?>">Открыть еще</a></div>
        <?php endif; ?>
    </section>

    <section class="mc-board">
        <div class="mc-board__head">
            <div>
                <h2>Жалобы в работе</h2>
                <p>Показываются только жалобы, которые закреплены за вами.</p>
            </div>
            <span class="mc-counter"><?= $workTotal ?></span>
        </div>
        <div class="mc-table-wrap">
            <table class="mc-table">
                <thead>
                    <tr>
                        <th>Отправитель</th>
                        <th>Жалоба на</th>
                        <th>Причина</th>
                        <th>Объект</th>
                        <th>Дата обновления</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody><?= mc_render_rows($workReports, 'work', $csrfToken, $limits) ?></tbody>
            </table>
        </div>
        <?php if ($workTotal > $workLimit): ?>
            <div class="mc-more"><a href="<?= mc_attr(mc_more_url('work_limit', $workLimit)) ?>">Открыть еще</a></div>
        <?php endif; ?>
    </section>

    <section class="mc-board">
        <div class="mc-board__head">
            <div>
                <h2>Решенные жалобы</h2>
                <p>Ваши закрытые жалобы. Решение можно пересмотреть без апелляции.</p>
            </div>
            <span class="mc-counter"><?= $doneTotal ?></span>
        </div>
        <div class="mc-table-wrap">
            <table class="mc-table">
                <thead>
                    <tr>
                        <th>Отправитель</th>
                        <th>Жалоба на</th>
                        <th>Причина</th>
                        <th>Объект</th>
                        <th>Дата решения</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody><?= mc_render_rows($doneReports, 'done', $csrfToken, $limits) ?></tbody>
            </table>
        </div>
        <?php if ($doneTotal > $doneLimit): ?>
            <div class="mc-more"><a href="<?= mc_attr(mc_more_url('done_limit', $doneLimit)) ?>">Открыть еще</a></div>
        <?php endif; ?>
    </section>
</div>

<?php if ($openedReport): ?>
    <?php
        $reportedUser = mc_user_display($openedReport, 'reported');
        $authorUser = mc_user_display($openedReport, 'author');
        $reportedTitle = $reportedUser['name'] . ($reportedUser['age'] ? ', ' . $reportedUser['age'] : '');
        $reviewMode = (string)($_GET['mode'] ?? '') === 'review' || in_array((string)$openedReport['status'], ['resolved', 'rejected'], true);
        $selectedDecision = (string)($openedReport['decision_type'] ?? '');
    ?>
    <div class="mc-modal is-open" id="report-modal" aria-modal="true" role="dialog">
        <div class="mc-modal__card">
            <div class="mc-modal__top">
                <a class="mc-back" href="moderator-complaints.php?<?= mc_attr(http_build_query(['new_limit' => $newLimit, 'work_limit' => $workLimit, 'done_limit' => $doneLimit])) ?>">‹ Назад к жалобам</a>
                <a class="mc-close" href="moderator-complaints.php?<?= mc_attr(http_build_query(['new_limit' => $newLimit, 'work_limit' => $workLimit, 'done_limit' => $doneLimit])) ?>" aria-label="Закрыть">×</a>
            </div>

            <h2><?= $reviewMode ? 'Пересмотр жалобы' : 'Рассмотрение жалобы' ?></h2>

            <div class="mc-report-head">
                <button class="mc-user-link mc-report-user js-profile-open" type="button"
                    data-profile-id="<?= mc_attr((string)$reportedUser['id']) ?>"
                    data-profile-name="<?= mc_attr($reportedUser['name']) ?>"
                    data-profile-age="<?= mc_attr((string)($reportedUser['age'] ?? '')) ?>"
                    data-profile-username="<?= mc_attr($reportedUser['username']) ?>"
                    data-profile-avatar="<?= mc_attr(mc_asset_url($reportedUser['avatar'])) ?>"
                    data-profile-city="<?= mc_attr($reportedUser['city']) ?>"
                    data-profile-about="<?= mc_attr($reportedUser['about']) ?>"
                    data-profile-interests="<?= mc_attr($reportedUser['interests']) ?>"
                    data-profile-goals="<?= mc_attr($reportedUser['goals']) ?>">
                    <?= mc_avatar($reportedUser['avatar'], $reportedUser['name']) ?>
                    <span>
                        <b><?= mc_h($reportedTitle) ?></b>
                        <small>@<?= mc_h($reportedUser['username']) ?></small>
                        <small>ID: <?= (int)$reportedUser['id'] ?></small>
                    </span>
                </button>
                <div class="mc-report-id">
                    <span>Жалоба #<?= (int)$openedReport['id'] ?></span>
                    <span><?= mc_h(mc_date((string)$openedReport['created_at'])) ?></span>
                </div>
            </div>

            <div class="mc-modal-grid">
                <div class="mc-meta-row">
                    <div>
                        <span class="mc-label">Причина жалобы</span>
                        <span class="mc-value"><?= mc_h((string)$openedReport['reason_name']) ?></span>
                    </div>
                    <?= mc_object_badge($openedReport) ?>
                </div>

                <div class="mc-content-box">
                    <span class="mc-label">Кто отправил жалобу</span>
                    <?= mc_user_button($openedReport, 'author') ?>
                </div>

                <?php if (!empty($openedReport['details'])): ?>
                    <div class="mc-content-box">
                        <span class="mc-label">Описание жалобы</span>
                        <p><?= nl2br(mc_h((string)$openedReport['details'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($openedReport['message_id'])): ?>
                    <div class="mc-content-box">
                        <span class="mc-label">Содержание сообщения</span>
                        <div class="mc-message-card">
                            <?= mc_avatar($reportedUser['avatar'], $reportedUser['name']) ?>
                            <div>
                                <b><?= mc_h($reportedUser['name']) ?></b>
                                <p><?= nl2br(mc_h((string)$openedReport['message_text'])) ?></p>
                                <time><?= mc_h(mc_date((string)$openedReport['message_sent_at'])) ?></time>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mc-content-box">
                        <span class="mc-label">Информация из профиля</span>
                        <p><b>О себе:</b> <?= mc_h($reportedUser['about'] !== '' ? $reportedUser['about'] : 'Не заполнено') ?></p>
                        <div style="margin-top:14px;">
                            <span class="mc-label" style="margin-bottom:10px;">Список интересов</span>
                            <?= mc_interest_chips($reportedUser['interests']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mc-stats">
                    <div class="mc-stat">
                        <strong><?= (int)$previousReportsTotal ?></strong>
                        <span>предыдущих жалоб на пользователя</span>
                    </div>
                    <div class="mc-stat">
                        <strong><?= (int)$restrictionStats['count'] ?> / <?= mc_h((string)$restrictionStats['hours']) ?></strong>
                        <span>ограничений / часов ранее</span>
                    </div>
                </div>

                <?php if ($reportHistory !== []): ?>
                    <div class="mc-content-box">
                        <span class="mc-label">Последние причины жалоб</span>
                        <div class="mc-history">
                            <?php foreach ($reportHistory as $historyItem): ?>
                                <div class="mc-history__item">
                                    <span><?= mc_h((string)$historyItem['reason_name']) ?></span>
                                    <span><?= mc_h(mc_date((string)$historyItem['created_at'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($openedReport['decision_type'])): ?>
                    <div class="mc-content-box">
                        <span class="mc-label">Текущее решение</span>
                        <p><?= mc_h((string)$openedReport['decision_comment']) ?></p>
                    </div>
                <?php endif; ?>

                <form class="mc-decision-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= mc_attr($csrfToken) ?>">
                    <input type="hidden" name="action" value="make_decision">
                    <input type="hidden" name="report_id" value="<?= (int)$openedReport['id'] ?>">
                    <input type="hidden" name="new_limit" value="<?= (int)$newLimit ?>">
                    <input type="hidden" name="work_limit" value="<?= (int)$workLimit ?>">
                    <input type="hidden" name="done_limit" value="<?= (int)$doneLimit ?>">

                    <div class="mc-decision-title">Выберите решение</div>
                    <div class="mc-decision-grid">
                        <label class="mc-decision-option">
                            <input type="radio" name="decision_action" value="no_violation" <?= $selectedDecision === 'no_violation' ? 'checked' : '' ?> required>
                            <span><strong>Отклонить жалобу</strong><small>Нарушение не подтверждено, меры не применяются.</small></span>
                        </label>
                        <label class="mc-decision-option mc-decision-option--warning">
                            <input type="radio" name="decision_action" value="warning" <?= $selectedDecision === 'warning' ? 'checked' : '' ?> required>
                            <span><strong>Предупреждение</strong><small>Зафиксировать нарушение и показать пользователю предупреждение при входе.</small></span>
                        </label>
                        <label class="mc-decision-option mc-decision-option--danger">
                            <input type="radio" name="decision_action" value="temporary_restriction" <?= $selectedDecision === 'temporary_restriction' ? 'checked' : '' ?> required>
                            <span><strong>Заблокировать</strong><small>Ограничить работу с приложением на указанное количество часов.</small></span>
                        </label>
                    </div>

                    <div class="mc-form-row">
                        <input class="mc-input" id="mcBlockHours" type="number" name="block_hours" min="0.5" step="0.5" placeholder="Кол-во часов, например 0.5" value="<?= $selectedDecision === 'temporary_restriction' ? mc_attr((string)($_POST['block_hours'] ?? '')) : '' ?>">
                        <textarea class="mc-textarea" name="moderator_comment" placeholder="Комментарий модератора, необязательно"><?= mc_h((string)($openedReport['decision_comment'] ?? '')) ?></textarea>
                    </div>

                    <button class="mc-submit" type="submit">Сохранить решение</button>
                </form>

                <div class="mc-note">
                    <b>i</b>
                    <span>Пожалуйста, рассмотрите жалобу внимательно и выберите справедливое решение. Ваше решение поможет сделать МЭТЧ безопаснее и комфортнее для всех пользователей.</span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="mc-modal" id="profile-modal" aria-modal="true" role="dialog">
    <div class="mc-modal__card mc-profile-card">
        <div class="mc-modal__top">
            <span class="mc-back">Профиль пользователя</span>
            <button class="mc-close js-profile-close" type="button" aria-label="Закрыть">×</button>
        </div>

        <div class="mc-profile-hero">
            <span id="profile-avatar-wrap"></span>
            <div class="mc-profile-main">
                <h3 id="profile-name">Пользователь</h3>
                <p id="profile-username">@user</p>
                <div class="mc-profile-city" id="profile-city">Город не указан</div>
            </div>
        </div>

        <div class="mc-profile-divider" aria-hidden="true"></div>

        <div class="mc-profile-sections">
            <section class="mc-profile-block">
                <h4>О себе</h4>
                <p id="profile-about">Не заполнено</p>
            </section>

            <section class="mc-profile-block">
                <h4>Интересы</h4>
                <div class="mc-profile-chips" id="profile-interests"></div>
            </section>

            <section class="mc-profile-block">
                <h4>Формат общения</h4>
                <div class="mc-profile-chips" id="profile-goals"></div>
            </section>
        </div>
    </div>
</div>

<script>
    const mcGoalLabels = {
        communication: 'Общение',
        friendship: 'Дружба',
        joint_activities: 'Совместный досуг',
        relationship: 'Отношения',
        support: 'Поддержка'
    };

    function mcEscapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function mcRenderChips(value, emptyText, labels = null) {
        const items = String(value || '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean)
            .map((item) => labels && labels[item] ? labels[item] : item);

        const uniqueItems = [...new Set(items)];
        if (!uniqueItems.length) {
            return '<span class="mc-profile-empty">' + mcEscapeHtml(emptyText) + '</span>';
        }

        return uniqueItems
            .map((item) => '<span class="mc-profile-chip">' + mcEscapeHtml(item) + '</span>')
            .join('');
    }

    function mcToggleBlockHours() {
        const checked = document.querySelector('input[name="decision_action"]:checked');
        const blockHours = document.getElementById('mcBlockHours');
        if (!blockHours) {
            return;
        }

        const isBlock = checked && checked.value === 'temporary_restriction';
        blockHours.disabled = !isBlock;
        blockHours.required = !!isBlock;
        if (!isBlock) {
            blockHours.value = '';
        }
    }

    document.querySelectorAll('input[name="decision_action"]').forEach((radio) => {
        radio.addEventListener('change', mcToggleBlockHours);
    });
    mcToggleBlockHours();

    document.querySelectorAll('.js-profile-open').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById('profile-modal');
            const avatarWrap = document.getElementById('profile-avatar-wrap');
            const name = button.dataset.profileName || 'Пользователь';
            const age = button.dataset.profileAge ? ', ' + button.dataset.profileAge : '';
            const username = button.dataset.profileUsername ? '@' + button.dataset.profileUsername : 'ID: ' + (button.dataset.profileId || '—');
            const avatar = button.dataset.profileAvatar || '';

            document.getElementById('profile-name').textContent = name + age;
            document.getElementById('profile-username').textContent = username;
            document.getElementById('profile-city').textContent = button.dataset.profileCity || 'Город не указан';
            document.getElementById('profile-about').textContent = button.dataset.profileAbout || 'Не заполнено';
            document.getElementById('profile-interests').innerHTML = mcRenderChips(button.dataset.profileInterests, 'Интересы не указаны');
            document.getElementById('profile-goals').innerHTML = mcRenderChips(button.dataset.profileGoals, 'Формат общения не указан', mcGoalLabels);

            if (avatar) {
                avatarWrap.innerHTML = '<img class="mc-avatar" src="' + mcEscapeHtml(avatar) + '" alt="Фото пользователя">';
            } else {
                avatarWrap.innerHTML = '<span class="mc-avatar mc-avatar--empty">' + mcEscapeHtml(name.charAt(0).toUpperCase()) + '</span>';
            }

            modal.classList.add('is-open');
        });
    });

    document.querySelectorAll('.js-profile-close').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('profile-modal').classList.remove('is-open');
        });
    });

    document.getElementById('profile-modal')?.addEventListener('click', (event) => {
        if (event.target.id === 'profile-modal') {
            event.currentTarget.classList.remove('is-open');
        }
    });
</script>

<?php
$pageContent = ob_get_clean();

require_once __DIR__ . '/../modules/dashboard-layout.php';
