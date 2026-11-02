<?php
$basePath = '..';

require_once __DIR__ . '/../php/moderator-check.php';

$conn = auth_require_db();
$currentModeratorId = (int)($_SESSION['user_id'] ?? 0);

$dashboardUserName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Сотрудник';
$dashboardUserRole = 'Модератор';
$logoutUrl = 'php/logout.php';

$pageTitle = 'Апелляции';
$pageDescription = 'Панель модератора. Раздел для рассмотрения апелляций пользователей.';
$activePage = 'appeals';
$dashboardHome = 'pages/moderator-complaints.php';

$navItems = [
    'complaints' => ['title' => 'Жалобы', 'href' => 'pages/moderator-complaints.php', 'icon' => 'nav-chats.svg'],
    'appeals' => ['title' => 'Апелляции', 'href' => 'pages/moderator-appeals.php', 'icon' => 'nav-feed.svg'],
];

function ma_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ma_attr(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ma_bind(mysqli_stmt $stmt, string $types, array $params): void
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

function ma_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    ma_bind($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function ma_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = ma_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function ma_count(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $row = ma_one($conn, $sql, $types, $params);
    return (int)($row['total'] ?? 0);
}

function ma_execute(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    ma_bind($stmt, $types, $params);
    $stmt->execute();
    return $stmt;
}

function ma_csrf_token(): string
{
    if (empty($_SESSION['moderator_appeals_csrf'])) {
        $_SESSION['moderator_appeals_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['moderator_appeals_csrf'];
}

function ma_check_csrf(): bool
{
    return hash_equals((string)($_SESSION['moderator_appeals_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''));
}

function ma_flash(string $message, bool $isError = false): void
{
    $_SESSION[$isError ? 'moderator_appeals_error' : 'moderator_appeals_success'] = $message;
}

function ma_redirect(array $params = []): never
{
    $url = 'moderator-appeals.php';
    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }
    header('Location: ' . $url);
    exit;
}

function ma_limit(string $key): int
{
    $limit = (int)($_GET[$key] ?? 6);
    if ($limit < 6) {
        return 6;
    }
    return min(60, (int)(ceil($limit / 6) * 6));
}

function ma_keep_limits_from_post(): array
{
    return [
        'new_limit' => max(6, min(60, (int)($_POST['new_limit'] ?? 6))),
        'work_limit' => max(6, min(60, (int)($_POST['work_limit'] ?? 6))),
        'done_limit' => max(6, min(60, (int)($_POST['done_limit'] ?? 6))),
    ];
}

function ma_more_url(string $key, int $currentLimit): string
{
    $params = $_GET;
    unset($params['appeal_id'], $params['mode']);
    $params[$key] = min(60, $currentLimit + 6);
    return 'moderator-appeals.php?' . http_build_query($params);
}

function ma_open_url(int $appealId, bool $review = false): string
{
    $params = $_GET;
    $params['appeal_id'] = $appealId;
    if ($review) {
        $params['mode'] = 'review';
    } else {
        unset($params['mode']);
    }
    return 'moderator-appeals.php?' . http_build_query($params);
}

function ma_close_url(int $newLimit, int $workLimit, int $doneLimit): string
{
    return 'moderator-appeals.php?' . http_build_query([
        'new_limit' => $newLimit,
        'work_limit' => $workLimit,
        'done_limit' => $doneLimit,
    ]);
}

function ma_asset_url(?string $path): string
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

function ma_initials(?string $name): string
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'М';
    }
    return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
}

function ma_avatar(?string $path, ?string $name, string $class = ''): string
{
    $url = ma_asset_url($path);
    $classAttr = trim('ma-avatar ' . $class);
    if ($url !== '') {
        return '<img class="' . ma_attr($classAttr) . '" src="' . ma_attr($url) . '" alt="' . ma_attr($name ?: 'Пользователь') . '">';
    }
    return '<span class="' . ma_attr($classAttr . ' ma-avatar--empty') . '">' . ma_h(ma_initials($name)) . '</span>';
}

function ma_date(?string $value): string
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

function ma_goal_labels(): array
{
    return [
        'communication' => 'Общение',
        'friendship' => 'Дружба',
        'joint_activities' => 'Совместный досуг',
        'relationship' => 'Отношения',
        'networking' => 'Нетворкинг',
    ];
}

function ma_format_goals(?string $raw): string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return 'Не указан';
    }

    $labels = ma_goal_labels();
    $items = array_filter(array_map('trim', explode(',', $raw)));
    $result = [];
    foreach ($items as $item) {
        $result[] = $labels[$item] ?? $item;
    }

    return $result !== [] ? implode(', ', $result) : 'Не указан';
}

function ma_decision_label(?string $value): string
{
    return match ((string)$value) {
        'no_violation' => 'Нарушение не подтверждено',
        'warning' => 'Предупреждение',
        'temporary_restriction' => 'Временная блокировка',
        'permanent_block' => 'Постоянная блокировка',
        default => 'Не указано',
    };
}

function ma_appeal_status_label(?string $value): string
{
    return match ((string)$value) {
        'new' => 'Новая',
        'in_review' => 'В работе',
        'accepted' => 'Принята',
        'rejected' => 'Отклонена',
        default => 'Не указан',
    };
}

function ma_restriction_label(?string $value): string
{
    return match ((string)$value) {
        'chat_ban' => 'Ограничение чата',
        'profile_hidden' => 'Скрытие профиля',
        'temporary_block' => 'Временная блокировка',
        'permanent_block' => 'Постоянная блокировка',
        default => 'Ограничение',
    };
}

function ma_user_display(array $row, string $prefix): array
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

function ma_user_button(array $row, string $prefix): string
{
    $user = ma_user_display($row, $prefix);
    $title = $user['name'] . ($user['age'] ? ', ' . $user['age'] : '');
    $username = $user['username'] !== '' ? '@' . $user['username'] : 'ID: ' . $user['id'];

    return '<button class="ma-user-link js-appeal-profile-open" type="button"'
        . ' data-profile-id="' . ma_attr((string)$user['id']) . '"'
        . ' data-profile-name="' . ma_attr($user['name']) . '"'
        . ' data-profile-age="' . ma_attr((string)($user['age'] ?? '')) . '"'
        . ' data-profile-username="' . ma_attr($user['username']) . '"'
        . ' data-profile-avatar="' . ma_attr(ma_asset_url($user['avatar'])) . '"'
        . ' data-profile-city="' . ma_attr($user['city']) . '"'
        . ' data-profile-about="' . ma_attr($user['about']) . '"'
        . ' data-profile-interests="' . ma_attr($user['interests']) . '"'
        . ' data-profile-goals="' . ma_attr(ma_format_goals($user['goals'])) . '">'
        . ma_avatar($user['avatar'], $user['name'], 'ma-user-link__avatar')
        . '<span><b>' . ma_h($title) . '</b><small>' . ma_h($username) . '</small></span>'
        . '</button>';
}

function ma_object_badge(array $row): string
{
    $target = (string)($row['target_type'] ?? 'profile');
    $label = $target === 'message' || !empty($row['message_id']) ? 'Сообщение' : 'Профиль';
    $class = $label === 'Сообщение' ? 'ma-badge--message' : 'ma-badge--profile';
    return '<span class="ma-badge ' . $class . '">' . ma_h($label) . '</span>';
}

function ma_appeal_select(): string
{
    return "
        SELECT
            a.id,
            a.decision_id,
            a.appellant_id,
            a.appeal_text,
            a.status,
            a.reviewer_id,
            a.review_comment,
            a.created_at,
            a.reviewed_at,
            COALESCE(a.reviewed_at, a.created_at) AS list_date,

            md.report_id,
            md.moderator_id AS original_moderator_id,
            md.decision_type,
            md.comment AS decision_comment,
            md.created_at AS decision_created_at,

            r.author_id AS report_author_id,
            r.reported_user_id,
            r.message_id,
            r.reason_id,
            r.details AS report_details,
            r.status AS report_status,
            r.created_at AS report_created_at,
            r.closed_at AS report_closed_at,

            rr.name AS reason_name,
            rr.target_type,
            rr.description AS reason_description,

            app.username AS appellant_username,
            app.status AS appellant_account_status,
            app_p.display_name AS appellant_name,
            app_p.avatar_path AS appellant_avatar,
            app_p.about AS appellant_about,
            app_p.communication_goal AS appellant_goals,
            app_c.name AS appellant_city,
            TIMESTAMPDIFF(YEAR, app_p.birth_date, CURDATE()) AS appellant_age,
            COALESCE(app_i.interests_list, '') AS appellant_interests,

            au.username AS author_username,
            au_p.display_name AS author_name,
            au_p.avatar_path AS author_avatar,
            au_p.about AS author_about,
            au_p.communication_goal AS author_goals,
            au_c.name AS author_city,
            TIMESTAMPDIFF(YEAR, au_p.birth_date, CURDATE()) AS author_age,
            COALESCE(au_i.interests_list, '') AS author_interests,

            ru.username AS reported_username,
            ru.status AS reported_account_status,
            ru_p.display_name AS reported_name,
            ru_p.avatar_path AS reported_avatar,
            ru_p.about AS reported_about,
            ru_p.communication_goal AS reported_goals,
            ru_c.name AS reported_city,
            TIMESTAMPDIFF(YEAR, ru_p.birth_date, CURDATE()) AS reported_age,
            COALESCE(ru_i.interests_list, '') AS reported_interests,

            m.message_text,
            m.sent_at AS message_sent_at,

            active_restrictions.active_count AS active_restrictions_count,
            active_restrictions.active_types AS active_restrictions_types,
            active_restrictions.active_until AS active_restrictions_until
        FROM appeals a
        INNER JOIN moderation_decisions md ON md.id = a.decision_id
        INNER JOIN reports r ON r.id = md.report_id
        INNER JOIN report_reasons rr ON rr.id = r.reason_id

        INNER JOIN users app ON app.id = a.appellant_id
        LEFT JOIN user_profiles app_p ON app_p.user_id = app.id
        LEFT JOIN cities app_c ON app_c.id = app_p.city_id
        LEFT JOIN (
            SELECT ui.user_id, GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR ', ') AS interests_list
            FROM user_interests ui
            INNER JOIN interests i ON i.id = ui.interest_id
            WHERE i.is_active = 1
            GROUP BY ui.user_id
        ) app_i ON app_i.user_id = app.id

        INNER JOIN users au ON au.id = r.author_id
        LEFT JOIN user_profiles au_p ON au_p.user_id = au.id
        LEFT JOIN cities au_c ON au_c.id = au_p.city_id
        LEFT JOIN (
            SELECT ui.user_id, GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR ', ') AS interests_list
            FROM user_interests ui
            INNER JOIN interests i ON i.id = ui.interest_id
            WHERE i.is_active = 1
            GROUP BY ui.user_id
        ) au_i ON au_i.user_id = au.id

        INNER JOIN users ru ON ru.id = r.reported_user_id
        LEFT JOIN user_profiles ru_p ON ru_p.user_id = ru.id
        LEFT JOIN cities ru_c ON ru_c.id = ru_p.city_id
        LEFT JOIN (
            SELECT ui.user_id, GROUP_CONCAT(i.name ORDER BY i.name SEPARATOR ', ') AS interests_list
            FROM user_interests ui
            INNER JOIN interests i ON i.id = ui.interest_id
            WHERE i.is_active = 1
            GROUP BY ui.user_id
        ) ru_i ON ru_i.user_id = ru.id

        LEFT JOIN messages m ON m.id = r.message_id
        LEFT JOIN (
            SELECT
                decision_id,
                user_id,
                COUNT(*) AS active_count,
                GROUP_CONCAT(restriction_type ORDER BY id SEPARATOR ', ') AS active_types,
                MAX(ends_at) AS active_until
            FROM user_restrictions
            WHERE status = 'active'
            GROUP BY decision_id, user_id
        ) active_restrictions ON active_restrictions.decision_id = md.id AND active_restrictions.user_id = a.appellant_id
    ";
}

function ma_fetch_appeals(mysqli $conn, string $where, string $types, array $params, string $order, int $limit): array
{
    $sql = ma_appeal_select() . " WHERE {$where} ORDER BY {$order} LIMIT ?";
    $types .= 'i';
    $params[] = $limit;
    return ma_all($conn, $sql, $types, $params);
}

function ma_fetch_open_appeal(mysqli $conn, int $appealId, int $moderatorId): ?array
{
    return ma_one(
        $conn,
        ma_appeal_select() . "
            WHERE a.id = ?
              AND a.reviewer_id = ?
              AND a.status IN ('in_review', 'accepted', 'rejected')
            LIMIT 1
        ",
        'ii',
        [$appealId, $moderatorId]
    );
}

function ma_fetch_previous_appeals_total(mysqli $conn, int $appellantId, int $currentAppealId): int
{
    return ma_count(
        $conn,
        "SELECT COUNT(*) AS total FROM appeals WHERE appellant_id = ? AND id <> ?",
        'ii',
        [$appellantId, $currentAppealId]
    );
}

function ma_fetch_restriction_stats(mysqli $conn, int $userId): array
{
    $row = ma_one(
        $conn,
        "
            SELECT
                COUNT(*) AS restrictions_count,
                COALESCE(SUM(CASE WHEN ends_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, starts_at, ends_at) ELSE 0 END), 0) AS restricted_minutes
            FROM user_restrictions
            WHERE user_id = ?
        ",
        'i',
        [$userId]
    );

    return [
        'count' => (int)($row['restrictions_count'] ?? 0),
        'hours' => round(((int)($row['restricted_minutes'] ?? 0)) / 60, 1),
    ];
}

function ma_fetch_restrictions(mysqli $conn, int $userId, int $decisionId): array
{
    return ma_all(
        $conn,
        "
            SELECT id, restriction_type, starts_at, ends_at, status, lifted_at
            FROM user_restrictions
            WHERE user_id = ? AND decision_id = ?
            ORDER BY FIELD(status, 'active', 'lifted', 'expired'), id DESC
            LIMIT 5
        ",
        'ii',
        [$userId, $decisionId]
    );
}

function ma_render_rows(array $rows, string $type, string $csrfToken, array $limits): string
{
    if ($rows === []) {
        return '<tr><td colspan="6" class="ma-empty">Пока здесь нет апелляций.</td></tr>';
    }

    $html = '';
    foreach ($rows as $row) {
        $date = ma_date((string)($row['list_date'] ?? $row['created_at'] ?? ''));
        $html .= '<tr>';
        $html .= '<td>' . ma_user_button($row, 'appellant') . '</td>';
        $html .= '<td><span class="ma-decision-pill">' . ma_h(ma_decision_label((string)$row['decision_type'])) . '</span></td>';
        $html .= '<td><span class="ma-reason">' . ma_h((string)$row['reason_name']) . '</span></td>';
        $html .= '<td>' . ma_object_badge($row) . '</td>';
        $html .= '<td><span class="ma-date">' . ma_h($date) . '</span></td>';
        $html .= '<td class="ma-actions-cell">';

        if ($type === 'new') {
            $html .= '<form method="post" class="ma-inline-form">'
                . '<input type="hidden" name="csrf_token" value="' . ma_attr($csrfToken) . '">'
                . '<input type="hidden" name="action" value="take_appeal">'
                . '<input type="hidden" name="appeal_id" value="' . ma_attr((string)$row['id']) . '">'
                . '<input type="hidden" name="new_limit" value="' . ma_attr((string)$limits['new_limit']) . '">'
                . '<input type="hidden" name="work_limit" value="' . ma_attr((string)$limits['work_limit']) . '">'
                . '<input type="hidden" name="done_limit" value="' . ma_attr((string)$limits['done_limit']) . '">'
                . '<button class="ma-action ma-action--primary" type="submit">Принять</button>'
                . '</form>';
        } elseif ($type === 'work') {
            $html .= '<a class="ma-action ma-action--soft" href="' . ma_attr(ma_open_url((int)$row['id'])) . '">Открыть</a>';
        } else {
            $html .= '<a class="ma-action ma-action--review" href="' . ma_attr(ma_open_url((int)$row['id'], true)) . '">Пересмотр</a>';
        }

        $html .= '</td></tr>';
    }

    return $html;
}

function ma_take_appeal(mysqli $conn, int $appealId, int $moderatorId): bool
{
    $conn->begin_transaction();

    try {
        $stmt = ma_execute(
            $conn,
            "
                UPDATE appeals
                SET status = 'in_review', reviewer_id = ?
                WHERE id = ? AND status = 'new' AND reviewer_id IS NULL
            ",
            'ii',
            [$moderatorId, $appealId]
        );

        if ($stmt->affected_rows !== 1) {
            $conn->rollback();
            return false;
        }

        ma_execute(
            $conn,
            "
                INSERT INTO action_log (actor_user_id, action_type, entity_type, entity_id, description, ip_address)
                VALUES (?, 'appeal_taken', 'appeal', ?, 'Модератор принял апелляцию в работу.', ?)
            ",
            'iis',
            [$moderatorId, $appealId, $_SERVER['REMOTE_ADDR'] ?? null]
        );

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function ma_sync_appellant_status(mysqli $conn, int $userId): void
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
        ma_execute($conn, "UPDATE users SET status = 'blocked' WHERE id = ? AND status <> 'deleted'", 'i', [$userId]);
        return;
    }

    ma_execute($conn, "UPDATE users SET status = 'active' WHERE id = ? AND status = 'blocked'", 'i', [$userId]);
}

function ma_apply_decision(mysqli $conn, int $appealId, int $moderatorId, string $appealAction, string $comment): void
{
    $allowed = ['reject_appeal', 'accept_appeal'];
    if (!in_array($appealAction, $allowed, true)) {
        throw new RuntimeException('Выберите корректное решение по апелляции.');
    }

    $conn->begin_transaction();

    try {
        $appeal = ma_one(
            $conn,
            "
                SELECT
                    a.id,
                    a.decision_id,
                    a.appellant_id,
                    a.status,
                    md.decision_type
                FROM appeals a
                INNER JOIN moderation_decisions md ON md.id = a.decision_id
                WHERE a.id = ? AND a.reviewer_id = ? AND a.status IN ('in_review', 'accepted', 'rejected')
                FOR UPDATE
            ",
            'ii',
            [$appealId, $moderatorId]
        );

        if (!$appeal) {
            throw new RuntimeException('Апелляция недоступна или уже закреплена за другим модератором.');
        }

        $decisionId = (int)$appeal['decision_id'];
        $appellantId = (int)$appeal['appellant_id'];
        $newStatus = $appealAction === 'accept_appeal' ? 'accepted' : 'rejected';
        $defaultComment = $appealAction === 'accept_appeal'
            ? 'Апелляция принята, активные ограничения пользователя сняты.'
            : 'Апелляция отклонена, первоначальное решение оставлено в силе.';
        $reviewComment = trim($comment) !== '' ? trim($comment) : $defaultComment;

        ma_execute(
            $conn,
            "
                UPDATE appeals
                SET status = ?, reviewer_id = ?, review_comment = ?, reviewed_at = NOW()
                WHERE id = ?
            ",
            'sisi',
            [$newStatus, $moderatorId, $reviewComment, $appealId]
        );

        if ($appealAction === 'accept_appeal') {
            ma_execute(
                $conn,
                "
                    UPDATE user_restrictions
                    SET status = 'lifted', lifted_by_user_id = ?, lifted_at = NOW()
                    WHERE decision_id = ?
                      AND user_id = ?
                      AND status = 'active'
                      AND restriction_type IN ('temporary_block', 'permanent_block')
                ",
                'iii',
                [$moderatorId, $decisionId, $appellantId]
            );
        }

        if ($appealAction === 'reject_appeal') {
            ma_execute(
                $conn,
                "
                    UPDATE user_restrictions
                    SET status = 'active', lifted_by_user_id = NULL, lifted_at = NULL
                    WHERE decision_id = ?
                      AND user_id = ?
                      AND status = 'lifted'
                      AND restriction_type IN ('temporary_block', 'permanent_block')
                      AND (ends_at IS NULL OR ends_at > NOW())
                ",
                'ii',
                [$decisionId, $appellantId]
            );
        }

        ma_sync_appellant_status($conn, $appellantId);

        ma_execute(
            $conn,
            "
                INSERT INTO action_log (actor_user_id, action_type, entity_type, entity_id, description, ip_address)
                VALUES (?, 'appeal_decision', 'appeal', ?, ?, ?)
            ",
            'iiss',
            [$moderatorId, $appealId, $reviewComment, $_SERVER['REMOTE_ADDR'] ?? null]
        );

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!ma_check_csrf()) {
            throw new RuntimeException('Сессия устарела. Обновите страницу и попробуйте снова.');
        }

        $action = (string)($_POST['action'] ?? '');
        $appealId = (int)($_POST['appeal_id'] ?? 0);
        if ($appealId <= 0) {
            throw new RuntimeException('Апелляция не найдена.');
        }

        $limits = ma_keep_limits_from_post();

        if ($action === 'take_appeal') {
            if (!ma_take_appeal($conn, $appealId, $currentModeratorId)) {
                ma_flash('Апелляцию уже принял другой модератор или она больше не находится в новых.', true);
                ma_redirect($limits);
            }

            ma_flash('Апелляция принята в работу.');
            ma_redirect($limits + ['appeal_id' => $appealId]);
        }

        if ($action === 'make_decision') {
            $appealAction = (string)($_POST['appeal_action'] ?? '');
            $comment = (string)($_POST['review_comment'] ?? '');

            ma_apply_decision($conn, $appealId, $currentModeratorId, $appealAction, $comment);
            ma_flash('Решение по апелляции сохранено.');
            ma_redirect($limits + ['done_limit' => max($limits['done_limit'], 6)]);
        }

        throw new RuntimeException('Некорректное действие.');
    } catch (Throwable $e) {
        ma_flash($e->getMessage(), true);
        ma_redirect(ma_keep_limits_from_post());
    }
}

$csrfToken = ma_csrf_token();
$newLimit = ma_limit('new_limit');
$workLimit = ma_limit('work_limit');
$doneLimit = ma_limit('done_limit');
$limits = ['new_limit' => $newLimit, 'work_limit' => $workLimit, 'done_limit' => $doneLimit];

$newAppeals = ma_fetch_appeals(
    $conn,
    "a.status = 'new' AND a.reviewer_id IS NULL",
    '',
    [],
    'a.created_at ASC',
    $newLimit
);
$workAppeals = ma_fetch_appeals(
    $conn,
    "a.status = 'in_review' AND a.reviewer_id = ?",
    'i',
    [$currentModeratorId],
    'a.created_at DESC',
    $workLimit
);
$doneAppeals = ma_fetch_appeals(
    $conn,
    "a.status IN ('accepted', 'rejected') AND a.reviewer_id = ?",
    'i',
    [$currentModeratorId],
    'a.reviewed_at DESC, a.created_at DESC',
    $doneLimit
);

$newTotal = ma_count($conn, "SELECT COUNT(*) AS total FROM appeals WHERE status = 'new' AND reviewer_id IS NULL");
$workTotal = ma_count($conn, "SELECT COUNT(*) AS total FROM appeals WHERE status = 'in_review' AND reviewer_id = ?", 'i', [$currentModeratorId]);
$doneTotal = ma_count($conn, "SELECT COUNT(*) AS total FROM appeals WHERE status IN ('accepted', 'rejected') AND reviewer_id = ?", 'i', [$currentModeratorId]);

$openedAppealId = (int)($_GET['appeal_id'] ?? 0);
$openedAppeal = $openedAppealId > 0 ? ma_fetch_open_appeal($conn, $openedAppealId, $currentModeratorId) : null;
$previousAppealsTotal = 0;
$restrictionStats = ['count' => 0, 'hours' => 0];
$restrictions = [];
if ($openedAppeal) {
    $previousAppealsTotal = ma_fetch_previous_appeals_total($conn, (int)$openedAppeal['appellant_id'], (int)$openedAppeal['id']);
    $restrictionStats = ma_fetch_restriction_stats($conn, (int)$openedAppeal['appellant_id']);
    $restrictions = ma_fetch_restrictions($conn, (int)$openedAppeal['appellant_id'], (int)$openedAppeal['decision_id']);
}

$successMessage = (string)($_SESSION['moderator_appeals_success'] ?? '');
$errorMessage = (string)($_SESSION['moderator_appeals_error'] ?? '');
unset($_SESSION['moderator_appeals_success'], $_SESSION['moderator_appeals_error']);

ob_start();
?>

<style>
    .moderator-appeals{display:grid;gap:22px}.ma-alert{padding:14px 16px;border-radius:18px;font-weight:700}.ma-alert--success{background:#f0fff5;color:#3e8c56;border:1px solid #ccefd8}.ma-alert--error{background:#fff1f1;color:#d64545;border:1px solid #ffd1d1}.ma-board{background:#fff;border:1px solid rgba(120,100,180,.12);border-radius:28px;box-shadow:0 18px 50px rgba(77,57,118,.08);overflow:hidden}.ma-board__head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:24px 28px;background:linear-gradient(90deg,rgba(255,244,233,.9),rgba(247,241,255,.9))}.ma-board__head h2{margin:0;color:#18142b;font-size:22px}.ma-board__head p{margin:6px 0 0;color:#777197;font-size:14px}.ma-counter{display:inline-flex;align-items:center;justify-content:center;min-width:42px;padding:8px 14px;border-radius:999px;background:#f1e8ff;color:#7a5ce6;font-weight:800}.ma-table-wrap{overflow-x:auto}.ma-table{width:100%;border-collapse:collapse;min-width:940px}.ma-table th{padding:16px 22px;text-align:left;color:#8d89ab;font-size:13px;font-weight:800;background:rgba(250,247,255,.75)}.ma-table td{padding:16px 22px;border-top:1px solid rgba(120,100,180,.12);vertical-align:middle;color:#363251}.ma-user-link{display:inline-flex;align-items:center;gap:12px;padding:0;border:0;background:transparent;text-align:left;cursor:pointer;color:inherit}.ma-user-link:hover b{color:#7a5ce6}.ma-user-link b{display:block;color:#302b50;font-size:15px;line-height:1.25;transition:.2s}.ma-user-link small{display:block;margin-top:3px;color:#8e89ad;font-size:13px}.ma-avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;flex:0 0 auto;background:#f2ecff;border:1px solid rgba(122,92,230,.12)}.ma-avatar--empty{display:inline-flex;align-items:center;justify-content:center;color:#7a5ce6;font-weight:900}.ma-reason{font-weight:700;color:#4a4569}.ma-badge,.ma-decision-pill{display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border-radius:999px;font-weight:800;font-size:13px}.ma-badge--message{background:#f1e8ff;color:#7758e4}.ma-badge--profile{background:#fff0e5;color:#f07a3d}.ma-decision-pill{background:#eefcf2;color:#3d9b5e}.ma-date{white-space:nowrap;color:#4f4a70;font-weight:700}.ma-actions-cell{white-space:nowrap}.ma-inline-form{margin:0}.ma-action{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:9px 16px;border-radius:14px;border:1px solid transparent;text-decoration:none;font-weight:800;font-family:inherit;cursor:pointer}.ma-action--primary{background:#7a5ce6;color:#fff}.ma-action--soft{background:#f4efff;color:#7455df}.ma-action--review{background:#fff4e9;color:#f07a3d}.ma-more{padding:16px 22px;border-top:1px solid rgba(120,100,180,.12);text-align:center}.ma-more a{display:inline-flex;padding:11px 18px;border-radius:16px;background:#faf7ff;border:1px solid rgba(122,92,230,.18);color:#7455df;text-decoration:none;font-weight:800}.ma-empty{text-align:center;color:#9c96b9;font-weight:700;padding:28px!important}.ma-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:32px 18px;background:rgba(24,20,43,.42);overflow:auto}.ma-modal.is-open{display:flex}.ma-modal__card{width:min(920px,100%);background:#fff;border-radius:32px;box-shadow:0 24px 70px rgba(40,30,70,.24);padding:28px}.ma-modal__top{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:20px}.ma-back{display:inline-flex;align-items:center;gap:8px;padding:12px 16px;border-radius:18px;background:#f7f2ff;color:#302b50;text-decoration:none;font-weight:800}.ma-close{width:42px;height:42px;border-radius:14px;border:1px solid rgba(120,100,180,.14);background:#fff;color:#6f698d;font-size:28px;line-height:1;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.ma-modal h2{margin:0 0 24px;color:#18142b;font-size:30px}.ma-appeal-head{display:flex;align-items:center;justify-content:space-between;gap:18px;padding-bottom:22px;border-bottom:1px solid rgba(120,100,180,.14)}.ma-appeal-user{display:flex;align-items:center;gap:18px}.ma-appeal-user .ma-avatar{width:88px;height:88px;font-size:34px}.ma-appeal-user b{font-size:24px}.ma-appeal-user small{font-size:17px;color:#7a5ce6}.ma-appeal-id{display:grid;gap:12px;justify-items:end}.ma-appeal-id span:first-child{padding:12px 20px;border-radius:999px;background:#f1e8ff;color:#7455df;font-size:20px;font-weight:900}.ma-appeal-id span:last-child{font-weight:800;color:#575171}.ma-modal-grid{display:grid;gap:18px;margin-top:22px}.ma-meta-row{display:flex;justify-content:space-between;gap:16px;align-items:center}.ma-label{display:block;color:#8f8aaa;font-weight:800;margin-bottom:8px}.ma-value{color:#282342;font-weight:800;font-size:18px}.ma-content-box{border:1px solid rgba(120,100,180,.14);border-radius:22px;padding:18px;background:linear-gradient(180deg,#fff,#fffbf8)}.ma-content-box p{margin:8px 0 0;color:#4d4869;line-height:1.55}.ma-content-box b{color:#302b50}.ma-message-card{display:flex;gap:14px;width:min(560px,100%);padding:16px;border:1px solid rgba(120,100,180,.16);border-radius:18px;background:#fff}.ma-message-card .ma-avatar{width:54px;height:54px}.ma-message-card b{color:#7a5ce6}.ma-message-card p{margin:6px 0;color:#383351}.ma-message-card time{color:#817b9f;font-weight:700}.ma-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.ma-stat{padding:16px;border-radius:20px;background:#faf7ff;border:1px solid rgba(122,92,230,.14)}.ma-stat strong{display:block;font-size:24px;color:#7455df}.ma-stat span{color:#777197;font-weight:700}.ma-restrictions{display:grid;gap:8px}.ma-restriction-item{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:12px;padding:12px 14px;border-radius:16px;background:#fbf9ff;color:#4a4569}.ma-status-badge{display:inline-flex;width:max-content;padding:6px 10px;border-radius:999px;background:#f1e8ff;color:#7455df;font-weight:900}.ma-decision-form{display:grid;gap:16px}.ma-decision-title{margin:10px 0 0;color:#302b50;font-size:20px;font-weight:900}.ma-decision-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.ma-decision-option{position:relative;min-width:0}.ma-decision-option input{position:absolute;opacity:0;pointer-events:none}.ma-decision-option span{box-sizing:border-box;display:flex;min-height:96px;flex-direction:column;align-items:flex-start;justify-content:center;gap:7px;text-align:left;padding:18px 20px;border-radius:22px;border:2px solid rgba(120,100,180,.16);font-weight:900;color:#4e486a;background:#fff;cursor:pointer;line-height:1.25;transition:.2s;box-shadow:0 10px 26px rgba(78,72,106,.04);white-space:normal;overflow-wrap:anywhere}.ma-decision-option strong{display:block;width:100%;font-size:18px;line-height:1.25;white-space:normal}.ma-decision-option small{display:block;width:100%;font-size:13px;line-height:1.4;font-weight:800;color:#8f8aaa;white-space:normal}.ma-decision-option:hover span{border-color:rgba(122,92,230,.32);background:#fbf9ff;box-shadow:0 14px 28px rgba(122,92,230,.08)}.ma-decision-option input:checked+span{background:#f6f1ff;border-color:#7a5ce6;color:#7455df;box-shadow:0 14px 30px rgba(122,92,230,.14)}.ma-decision-option input:checked+span small{color:#7455df}.ma-decision-option--accept input:checked+span{background:#f1fff6;border-color:#78d69a;color:#2f9854}.ma-decision-option--accept input:checked+span small{color:#2f9854}.ma-textarea{width:100%;border:1px solid rgba(120,100,180,.18);border-radius:18px;padding:14px 16px;font:inherit;color:#302b50;background:#fff;min-height:96px;resize:vertical}.ma-submit{min-height:54px;border:0;border-radius:20px;background:#7a5ce6;color:#fff;font:inherit;font-weight:900;cursor:pointer}.ma-note{display:flex;gap:12px;align-items:flex-start;padding:16px 18px;border-radius:20px;border:1px solid rgba(122,92,230,.18);background:#faf7ff;color:#655f86;font-weight:700;line-height:1.45}.ma-note b{color:#7a5ce6}.ma-profile-card{width:min(560px,100%)}.ma-profile-main{display:flex;gap:16px;align-items:center}.ma-profile-main .ma-avatar{width:86px;height:86px;font-size:34px}.ma-profile-main h3{margin:0;color:#18142b;font-size:26px}.ma-profile-main p{margin:6px 0 0;color:#7a5ce6;font-weight:800}.ma-profile-info{display:grid;gap:12px;margin-top:20px;color:#4d4869;line-height:1.5}.ma-profile-info div{padding:14px 16px;border-radius:18px;background:#faf7ff}.ma-profile-info b{display:block;margin-bottom:4px;color:#302b50}@media(max-width:760px){.ma-board__head,.ma-appeal-head,.ma-meta-row{flex-direction:column;align-items:flex-start}.ma-appeal-id{justify-items:start}.ma-modal__card{padding:20px;border-radius:24px}.ma-decision-grid,.ma-stats,.ma-restriction-item{grid-template-columns:1fr}.ma-modal h2{font-size:24px}.ma-appeal-user .ma-avatar{width:72px;height:72px}.ma-appeal-user b{font-size:20px}}
</style>

<div class="moderator-appeals">
    <?php if ($successMessage !== ''): ?>
        <div class="ma-alert ma-alert--success"><?= ma_h($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="ma-alert ma-alert--error"><?= ma_h($errorMessage) ?></div>
    <?php endif; ?>

    <section class="ma-board">
        <div class="ma-board__head">
            <div>
                <h2>Новые апелляции</h2>
                <p>Общий список. После принятия апелляция закрепляется за вами.</p>
            </div>
            <span class="ma-counter"><?= $newTotal ?></span>
        </div>
        <div class="ma-table-wrap">
            <table class="ma-table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Исходное решение</th>
                        <th>Причина жалобы</th>
                        <th>Объект</th>
                        <th>Дата апелляции</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody><?= ma_render_rows($newAppeals, 'new', $csrfToken, $limits) ?></tbody>
            </table>
        </div>
        <?php if ($newTotal > $newLimit): ?>
            <div class="ma-more"><a href="<?= ma_attr(ma_more_url('new_limit', $newLimit)) ?>">Открыть еще</a></div>
        <?php endif; ?>
    </section>

    <section class="ma-board">
        <div class="ma-board__head">
            <div>
                <h2>Апелляции в работе</h2>
                <p>Показываются только апелляции, которые закреплены за вами.</p>
            </div>
            <span class="ma-counter"><?= $workTotal ?></span>
        </div>
        <div class="ma-table-wrap">
            <table class="ma-table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Исходное решение</th>
                        <th>Причина жалобы</th>
                        <th>Объект</th>
                        <th>Дата обновления</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody><?= ma_render_rows($workAppeals, 'work', $csrfToken, $limits) ?></tbody>
            </table>
        </div>
        <?php if ($workTotal > $workLimit): ?>
            <div class="ma-more"><a href="<?= ma_attr(ma_more_url('work_limit', $workLimit)) ?>">Открыть еще</a></div>
        <?php endif; ?>
    </section>

    <section class="ma-board">
        <div class="ma-board__head">
            <div>
                <h2>Рассмотренные апелляции</h2>
                <p>Ваши закрытые апелляции. При необходимости решение можно пересмотреть.</p>
            </div>
            <span class="ma-counter"><?= $doneTotal ?></span>
        </div>
        <div class="ma-table-wrap">
            <table class="ma-table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Исходное решение</th>
                        <th>Причина жалобы</th>
                        <th>Объект</th>
                        <th>Дата решения</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody><?= ma_render_rows($doneAppeals, 'done', $csrfToken, $limits) ?></tbody>
            </table>
        </div>
        <?php if ($doneTotal > $doneLimit): ?>
            <div class="ma-more"><a href="<?= ma_attr(ma_more_url('done_limit', $doneLimit)) ?>">Открыть еще</a></div>
        <?php endif; ?>
    </section>
</div>

<?php if ($openedAppeal): ?>
    <?php
        $appellantUser = ma_user_display($openedAppeal, 'appellant');
        $reportedUser = ma_user_display($openedAppeal, 'reported');
        $authorUser = ma_user_display($openedAppeal, 'author');
        $appellantTitle = $appellantUser['name'] . ($appellantUser['age'] ? ', ' . $appellantUser['age'] : '');
        $reviewMode = (string)($_GET['mode'] ?? '') === 'review' || in_array((string)$openedAppeal['status'], ['accepted', 'rejected'], true);
        $selectedAction = (string)$openedAppeal['status'] === 'accepted' ? 'accept_appeal' : ((string)$openedAppeal['status'] === 'rejected' ? 'reject_appeal' : '');
        $closeUrl = ma_close_url($newLimit, $workLimit, $doneLimit);
    ?>
    <div class="ma-modal is-open" id="appeal-modal" aria-modal="true" role="dialog">
        <div class="ma-modal__card">
            <div class="ma-modal__top">
                <a class="ma-back" href="<?= ma_attr($closeUrl) ?>">‹ Назад к апелляциям</a>
                <a class="ma-close" href="<?= ma_attr($closeUrl) ?>" aria-label="Закрыть">×</a>
            </div>

            <h2><?= $reviewMode ? 'Пересмотр апелляции' : 'Рассмотрение апелляции' ?></h2>

            <div class="ma-appeal-head">
                <button class="ma-user-link ma-appeal-user js-appeal-profile-open" type="button"
                    data-profile-id="<?= ma_attr((string)$appellantUser['id']) ?>"
                    data-profile-name="<?= ma_attr($appellantUser['name']) ?>"
                    data-profile-age="<?= ma_attr((string)($appellantUser['age'] ?? '')) ?>"
                    data-profile-username="<?= ma_attr($appellantUser['username']) ?>"
                    data-profile-avatar="<?= ma_attr(ma_asset_url($appellantUser['avatar'])) ?>"
                    data-profile-city="<?= ma_attr($appellantUser['city']) ?>"
                    data-profile-about="<?= ma_attr($appellantUser['about']) ?>"
                    data-profile-interests="<?= ma_attr($appellantUser['interests']) ?>"
                    data-profile-goals="<?= ma_attr(ma_format_goals($appellantUser['goals'])) ?>">
                    <?= ma_avatar($appellantUser['avatar'], $appellantUser['name']) ?>
                    <span>
                        <b><?= ma_h($appellantTitle) ?></b>
                        <small>@<?= ma_h($appellantUser['username']) ?></small>
                        <small>ID: <?= (int)$appellantUser['id'] ?></small>
                    </span>
                </button>
                <div class="ma-appeal-id">
                    <span>Апелляция #<?= (int)$openedAppeal['id'] ?></span>
                    <span><?= ma_h(ma_date((string)$openedAppeal['created_at'])) ?></span>
                </div>
            </div>

            <div class="ma-modal-grid">
                <div class="ma-meta-row">
                    <div>
                        <span class="ma-label">Статус апелляции</span>
                        <span class="ma-value"><?= ma_h(ma_appeal_status_label((string)$openedAppeal['status'])) ?></span>
                    </div>
                    <div>
                        <span class="ma-label">Исходное решение</span>
                        <span class="ma-value"><?= ma_h(ma_decision_label((string)$openedAppeal['decision_type'])) ?></span>
                    </div>
                    <?= ma_object_badge($openedAppeal) ?>
                </div>

                <div class="ma-content-box">
                    <span class="ma-label">Текст апелляции пользователя</span>
                    <p><?= nl2br(ma_h((string)$openedAppeal['appeal_text'])) ?></p>
                </div>

                <div class="ma-content-box">
                    <span class="ma-label">Первоначальное решение модератора</span>
                    <p><b><?= ma_h(ma_decision_label((string)$openedAppeal['decision_type'])) ?>:</b> <?= nl2br(ma_h((string)$openedAppeal['decision_comment'])) ?></p>
                    <p><b>Дата решения:</b> <?= ma_h(ma_date((string)$openedAppeal['decision_created_at'])) ?></p>
                </div>

                <div class="ma-content-box">
                    <span class="ma-label">Исходная жалоба</span>
                    <p><b>Причина:</b> <?= ma_h((string)$openedAppeal['reason_name']) ?></p>
                    <p><b>Описание:</b> <?= nl2br(ma_h((string)($openedAppeal['report_details'] ?: 'Комментарий к жалобе не указан.'))) ?></p>
                    <p><b>Отправитель жалобы:</b></p>
                    <?= ma_user_button($openedAppeal, 'author') ?>
                </div>

                <?php if (!empty($openedAppeal['message_id'])): ?>
                    <div class="ma-content-box">
                        <span class="ma-label">Сообщение, на которое была жалоба</span>
                        <div class="ma-message-card">
                            <?= ma_avatar($reportedUser['avatar'], $reportedUser['name']) ?>
                            <div>
                                <b><?= ma_h($reportedUser['name']) ?></b>
                                <p><?= nl2br(ma_h((string)$openedAppeal['message_text'])) ?></p>
                                <time><?= ma_h(ma_date((string)$openedAppeal['message_sent_at'])) ?></time>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ma-content-box">
                        <span class="ma-label">Информация из профиля пользователя</span>
                        <p><b>О себе:</b> <?= ma_h($reportedUser['about'] !== '' ? $reportedUser['about'] : 'Не заполнено') ?></p>
                        <p><b>Интересы:</b> <?= ma_h($reportedUser['interests'] !== '' ? $reportedUser['interests'] : 'Не заполнено') ?></p>
                    </div>
                <?php endif; ?>

                <div class="ma-stats">
                    <div class="ma-stat">
                        <strong><?= (int)$previousAppealsTotal ?></strong>
                        <span>предыдущих апелляций пользователя</span>
                    </div>
                    <div class="ma-stat">
                        <strong><?= (int)$restrictionStats['count'] ?> / <?= ma_h((string)$restrictionStats['hours']) ?></strong>
                        <span>ограничений / часов ранее</span>
                    </div>
                </div>

                <div class="ma-content-box">
                    <span class="ma-label">Ограничения по этому решению</span>
                    <?php if ($restrictions === []): ?>
                        <p>Активных или сохраненных ограничений по этому решению не найдено.</p>
                    <?php else: ?>
                        <div class="ma-restrictions">
                            <?php foreach ($restrictions as $restriction): ?>
                                <div class="ma-restriction-item">
                                    <span><b><?= ma_h(ma_restriction_label((string)$restriction['restriction_type'])) ?></b></span>
                                    <span><?= ma_h(ma_date((string)$restriction['starts_at'])) ?> — <?= ma_h($restriction['ends_at'] ? ma_date((string)$restriction['ends_at']) : 'без срока') ?></span>
                                    <span class="ma-status-badge"><?= ma_h((string)$restriction['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($openedAppeal['review_comment'])): ?>
                    <div class="ma-content-box">
                        <span class="ma-label">Текущее решение по апелляции</span>
                        <p><?= nl2br(ma_h((string)$openedAppeal['review_comment'])) ?></p>
                        <p><b>Дата:</b> <?= ma_h(ma_date((string)$openedAppeal['reviewed_at'])) ?></p>
                    </div>
                <?php endif; ?>

                <form class="ma-decision-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= ma_attr($csrfToken) ?>">
                    <input type="hidden" name="action" value="make_decision">
                    <input type="hidden" name="appeal_id" value="<?= (int)$openedAppeal['id'] ?>">
                    <input type="hidden" name="new_limit" value="<?= (int)$newLimit ?>">
                    <input type="hidden" name="work_limit" value="<?= (int)$workLimit ?>">
                    <input type="hidden" name="done_limit" value="<?= (int)$doneLimit ?>">

                    <div class="ma-decision-title">Выберите решение</div>
                    <div class="ma-decision-grid">
                        <label class="ma-decision-option">
                            <input type="radio" name="appeal_action" value="reject_appeal" <?= $selectedAction === 'reject_appeal' ? 'checked' : '' ?> required>
                            <span>
                                <strong>Отклонить апелляцию</strong>
                                <small>Оставить первоначальное решение в силе</small>
                            </span>
                        </label>
                        <label class="ma-decision-option ma-decision-option--accept">
                            <input type="radio" name="appeal_action" value="accept_appeal" <?= $selectedAction === 'accept_appeal' ? 'checked' : '' ?> required>
                            <span>
                                <strong>Разблокировать пользователя</strong>
                                <small>Принять апелляцию и снять активные ограничения</small>
                            </span>
                        </label>
                    </div>

                    <textarea class="ma-textarea" name="review_comment" placeholder="Комментарий модератора, необязательно"><?= ma_h((string)($openedAppeal['review_comment'] ?? '')) ?></textarea>
                    <button class="ma-submit" type="submit">Сохранить решение</button>
                </form>

                <div class="ma-note">
                    <b>i</b>
                    <span>Пожалуйста, рассмотрите апелляцию внимательно. Если апелляция обоснована, активные ограничения пользователя будут сняты автоматически.</span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="ma-modal" id="appeal-profile-modal" aria-modal="true" role="dialog">
    <div class="ma-modal__card ma-profile-card">
        <div class="ma-modal__top">
            <span class="ma-back">Профиль пользователя</span>
            <button class="ma-close js-appeal-profile-close" type="button" aria-label="Закрыть">×</button>
        </div>
        <div class="ma-profile-main">
            <span id="appeal-profile-avatar-wrap"></span>
            <div>
                <h3 id="appeal-profile-name">Пользователь</h3>
                <p id="appeal-profile-username">@user</p>
            </div>
        </div>
        <div class="ma-profile-info">
            <div><b>ID</b><span id="appeal-profile-id">—</span></div>
            <div><b>Город</b><span id="appeal-profile-city">—</span></div>
            <div><b>О себе</b><span id="appeal-profile-about">—</span></div>
            <div><b>Интересы</b><span id="appeal-profile-interests">—</span></div>
            <div><b>Формат общения</b><span id="appeal-profile-goals">—</span></div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.js-appeal-profile-open').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById('appeal-profile-modal');
            const avatarWrap = document.getElementById('appeal-profile-avatar-wrap');
            const name = button.dataset.profileName || 'Пользователь';
            const age = button.dataset.profileAge ? ', ' + button.dataset.profileAge : '';
            const username = button.dataset.profileUsername ? '@' + button.dataset.profileUsername : 'ID: ' + (button.dataset.profileId || '—');
            const avatar = button.dataset.profileAvatar || '';

            document.getElementById('appeal-profile-name').textContent = name + age;
            document.getElementById('appeal-profile-username').textContent = username;
            document.getElementById('appeal-profile-id').textContent = button.dataset.profileId || '—';
            document.getElementById('appeal-profile-city').textContent = button.dataset.profileCity || 'Не указан';
            document.getElementById('appeal-profile-about').textContent = button.dataset.profileAbout || 'Не заполнено';
            document.getElementById('appeal-profile-interests').textContent = button.dataset.profileInterests || 'Не заполнено';
            document.getElementById('appeal-profile-goals').textContent = button.dataset.profileGoals || 'Не указан';

            if (avatar) {
                avatarWrap.innerHTML = '<img class="ma-avatar" src="' + avatar.replace(/"/g, '&quot;') + '" alt="">';
            } else {
                avatarWrap.innerHTML = '<span class="ma-avatar ma-avatar--empty">' + name.charAt(0).toUpperCase() + '</span>';
            }

            modal.classList.add('is-open');
        });
    });

    document.querySelectorAll('.js-appeal-profile-close').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('appeal-profile-modal').classList.remove('is-open');
        });
    });

    document.getElementById('appeal-profile-modal')?.addEventListener('click', (event) => {
        if (event.target.id === 'appeal-profile-modal') {
            event.currentTarget.classList.remove('is-open');
        }
    });
</script>

<?php
$pageContent = ob_get_clean();

require_once __DIR__ . '/../modules/dashboard-layout.php';
