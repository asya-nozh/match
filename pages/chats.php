<?php
$basePath = '..';

require_once __DIR__ . '/../php/chat_helpers.php';
require_app_page();

$conn = auth_require_db();
$userId = (int)$_SESSION['user_id'];
$csrfToken = get_csrf_token('chat_action_csrf');

$selectedChatId = filter_input(INPUT_GET, 'chat_id', FILTER_VALIDATE_INT) ?: 0;
$selectedUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT) ?: 0;

if ($selectedUserId > 0) {
    $foundChatId = chat_find_or_create_by_user($conn, $userId, $selectedUserId);
    if ($foundChatId !== null) {
        $selectedChatId = $foundChatId;
    }
}

$chats = chat_get_user_chats($conn, $userId);
if ($selectedChatId <= 0 && $chats !== []) {
    $selectedChatId = (int)$chats[0]['chat_id'];
}

$activeChat = $selectedChatId > 0 ? chat_get_chat($conn, $userId, $selectedChatId) : null;
$messages = [];
$lastMessageId = 0;
if ($activeChat !== null) {
    $messages = chat_get_messages($conn, $userId, $selectedChatId);
    foreach ($messages as $message) {
        $lastMessageId = max($lastMessageId, (int)($message['id'] ?? 0));
    }
    chat_mark_read($conn, $userId, $selectedChatId);
}

$chatProfileUserIds = [];
foreach ($chats as $chat) {
    $chatProfileUserIds[] = (int)($chat['user_id'] ?? 0);
}
if ($activeChat !== null) {
    $chatProfileUserIds[] = (int)($activeChat['user_id'] ?? 0);
}
$chatProfilesForModal = chat_profiles_modal_payload($conn, $userId, $chatProfileUserIds);

$messageReportReasons = chat_get_message_report_reasons($conn);
$error = (string)($_SESSION['chat_error'] ?? '');
unset($_SESSION['chat_error']);

$pageTitle = 'Чаты';
$pageDescription = 'Ваши диалоги и переписка.';
$activePage = 'chats';

ob_start();
?>

<section class="chat-page" data-chat-action-url="<?= e($basePath) ?>/php/chat_action.php" data-csrf="<?= e($csrfToken) ?>" data-selected-chat-id="<?= e((string)$selectedChatId) ?>" data-last-message-id="<?= e((string)$lastMessageId) ?>" data-can-report-messages="<?= $messageReportReasons !== [] ? '1' : '0' ?>">
    <?php if ($error !== ''): ?>
        <div class="profile-alert profile-alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="chat-shell">
        <aside class="chat-sidebar" aria-label="Список чатов">
            <div class="chat-search">
                <?= profile_svg_icon('search', 'chat-search__icon') ?>
                <input type="search" id="chatSearchInput" placeholder="Поиск по имени">
            </div>

            <div class="chat-list" id="chatList">
                <?php if ($chats === []): ?>
                    <div class="chat-empty chat-empty--small">Пока нет активных диалогов. Чаты появятся после взаимного лайка.</div>
                <?php else: ?>
                    <?php foreach ($chats as $chat): ?>
                        <?php
                            $chatName = (string)$chat['display_name'] . ($chat['age'] !== null ? ', ' . (string)$chat['age'] : '');
                            $searchText = mb_strtolower((string)$chat['display_name'], 'UTF-8');
                        ?>
                        <a class="chat-list-item<?= (int)$chat['chat_id'] === $selectedChatId ? ' is-active' : '' ?>" href="<?= e($basePath) ?>/pages/chats.php?chat_id=<?= e((string)$chat['chat_id']) ?>#chatDialog" data-chat-search="<?= e($searchText) ?>">
                            <img class="chat-list-item__avatar" src="<?= e((string)$chat['avatar_url']) ?>" alt="" data-open-chat-profile="<?= e((string)$chat['user_id']) ?>">
                            <span class="chat-list-item__body">
                                <span class="chat-list-item__top">
                                    <strong><?= e($chatName) ?></strong>
                                    <time><?= e((string)$chat['time_label']) ?></time>
                                </span>
                                <span class="chat-list-item__city">
                                    <?= profile_svg_icon('location', 'chat-list-item__city-icon') ?>
                                    <?= e((string)$chat['city_name']) ?>
                                </span>
                                <span class="chat-status chat-status--list <?= e((string)$chat['online_class']) ?>">
                                    <span></span><?= e((string)$chat['online_label']) ?>
                                </span>
                                <span class="chat-list-item__preview"><?= e((string)$chat['last_message_preview']) ?></span>
                            </span>
                            <?php if ((int)$chat['unread_count'] > 0): ?>
                                <span class="chat-list-item__badge"><?= e((string)$chat['unread_count']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="chat-empty chat-empty--small chat-search-empty" id="chatSearchEmpty" hidden>Чаты с таким именем не найдены.</div>
            </div>
        </aside>

        <section class="chat-dialog" id="chatDialog" aria-label="Диалог">
            <?php if ($activeChat === null): ?>
                <div class="chat-dialog-empty">
                    <?= profile_svg_icon('chat', 'chat-dialog-empty__icon') ?>
                    <h2>Выберите чат</h2>
                    <p>После взаимного лайка здесь появится переписка с пользователем.</p>
                </div>
            <?php else: ?>
                <a class="chat-mobile-back" href="#chatList">← К списку чатов</a>
                <header class="chat-dialog__header">
                    <div class="chat-dialog__person">
                        <button class="chat-dialog__avatar-button" type="button" data-open-chat-profile="<?= e((string)$activeChat['user_id']) ?>" aria-label="Открыть профиль собеседника">
                            <img src="<?= e((string)$activeChat['avatar_url']) ?>" alt="">
                        </button>
                        <div>
                            <h2><?= e((string)$activeChat['display_name']) ?><?= $activeChat['age'] !== null ? ', ' . e((string)$activeChat['age']) : '' ?></h2>
                            <p><?= e((string)$activeChat['city_name']) ?></p>
                            <span class="chat-status chat-status--dialog <?= e((string)$activeChat['online_class']) ?>">
                                <span></span><?= e((string)$activeChat['online_label']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="chat-dialog__more">
                        <button class="chat-dialog__menu-button" type="button" id="chatDialogMenuButton" aria-label="Дополнительные действия" aria-expanded="false">⋮</button>
                        <div class="chat-dialog__menu" id="chatDialogMenu" hidden>
                            <button type="button" data-open-chat-profile="<?= e((string)$activeChat['user_id']) ?>">Открыть профиль</button>
                        </div>
                    </div>
                </header>

                <div class="chat-messages" id="chatMessages">
                    <?php if ($messages === []): ?>
                        <div class="chat-empty chat-empty--dialog">Сообщений пока нет. Напишите первым.</div>
                    <?php else: ?>
                        <?php $lastDateLabel = ''; ?>
                        <?php foreach ($messages as $message): ?>
                            <?php if ((string)$message['date_label'] !== $lastDateLabel): ?>
                                <?php $lastDateLabel = (string)$message['date_label']; ?>
                                <div class="chat-date-divider"><?= e($lastDateLabel) ?></div>
                            <?php endif; ?>

                            <article class="chat-message<?= !empty($message['is_own']) ? ' is-own' : '' ?>" data-message-id="<?= e((string)$message['id']) ?>">
                                <?php if (empty($message['is_own'])): ?>
                                    <img class="chat-message__avatar" src="<?= e((string)$message['avatar_url']) ?>" alt="">
                                <?php endif; ?>
                                <div class="chat-message__content">
                                    <div class="chat-message__bubble">
                                        <p><?= nl2br(e((string)$message['message_text'])) ?></p>
                                        <time><?= e((string)$message['time_label']) ?></time>
                                    </div>
                                    <?php if (empty($message['is_own']) && $messageReportReasons !== []): ?>
                                        <div class="chat-message__more">
                                            <button type="button" class="chat-message__more-button" aria-label="Действия с сообщением">⋯</button>
                                            <div class="chat-message__menu">
                                                <button type="button" data-report-message="<?= e((string)$message['id']) ?>">Пожаловаться</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form class="chat-send" id="chatSendForm" action="<?= e($basePath) ?>/php/chat_action.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="chat_id" value="<?= e((string)$selectedChatId) ?>">
                    <textarea name="message_text" id="chatMessageInput" maxlength="1000" rows="1" placeholder="Напишите сообщение..."></textarea>
                    <button class="btn btn--primary chat-send__button" type="submit">Отправить</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <script type="application/json" id="chatProfilesData"><?= json_encode($chatProfilesForModal, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

    <div class="feed-modal chat-profile-modal" id="chatProfileModal" hidden>
        <div class="feed-modal__backdrop" data-close-chat-profile-modal></div>
        <article class="feed-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="chatProfileModalName">
            <button class="feed-modal__close" type="button" data-close-chat-profile-modal aria-label="Закрыть">×</button>

            <div class="feed-modal__hero">
                <img class="feed-modal__avatar" id="chatProfileModalAvatar" src="" alt="Фото пользователя">
                <div class="feed-modal__main">
                    <h2 id="chatProfileModalName"></h2>
                    <p class="feed-modal__username" id="chatProfileModalUsername"></p>
                    <p class="feed-modal__city" id="chatProfileModalCity"></p>
                    <p class="chat-status chat-status--modal" id="chatProfileModalStatus"><span></span></p>
                    <div class="feed-modal__about" id="chatProfileModalAbout"></div>
                </div>
            </div>

            <div class="feed-modal__divider" aria-hidden="true"><span></span></div>

            <div class="feed-modal__section">
                <h3>Интересы</h3>
                <div class="feed-modal__chips" id="chatProfileModalInterests"></div>
            </div>

            <div class="feed-modal__section">
                <h3>Формат общения</h3>
                <div class="feed-modal__chips" id="chatProfileModalGoals"></div>
            </div>
        </article>
    </div>

    <?php if ($messageReportReasons !== []): ?>
        <div class="chat-report-modal" id="chatReportModal" hidden>
            <div class="chat-report-modal__backdrop" data-close-chat-report></div>
            <form class="chat-report-modal__dialog" id="chatReportForm">
                <button class="feed-modal__close" type="button" data-close-chat-report aria-label="Закрыть">×</button>
                <h2>Пожаловаться на сообщение</h2>
                <label class="profile-form-label">
                    <span>Причина жалобы</span>
                    <select class="form-field" id="chatReportReason" required>
                        <option value="">Выберите причину</option>
                        <?php foreach ($messageReportReasons as $reason): ?>
                            <option value="<?= e((string)$reason['id']) ?>"><?= e((string)$reason['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="profile-form-label">
                    <span>Комментарий</span>
                    <textarea class="form-field" id="chatReportDetails" maxlength="1200" placeholder="Кратко опишите ситуацию"></textarea>
                </label>
                <div class="feed-report-form__actions">
                    <button class="btn btn--secondary" type="button" data-close-chat-report>Отмена</button>
                    <button class="btn btn--primary" type="submit">Отправить</button>
                </div>
                <div class="feed-modal__status" id="chatReportStatus" aria-live="polite"></div>
            </form>
        </div>
    <?php endif; ?>
</section>

<script src="<?= $basePath ?>/js/chats.js" defer></script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../modules/app-layout.php';
?>
