(() => {
    const page = document.querySelector('.chat-page');
    if (!page) return;

    const searchInput = document.querySelector('#chatSearchInput');
    const chatItems = Array.from(document.querySelectorAll('.chat-list-item'));
    const searchEmpty = document.querySelector('#chatSearchEmpty');
    const messagesBox = document.querySelector('#chatMessages');
    const sendForm = document.querySelector('#chatSendForm');
    const messageInput = document.querySelector('#chatMessageInput');
    const actionUrl = page.dataset.chatActionUrl || '../php/chat_action.php';
    const csrfToken = page.dataset.csrf || '';
    const selectedChatId = Number(page.dataset.selectedChatId || '0');
    let lastMessageId = Number(page.dataset.lastMessageId || '0');
    const canReportMessages = page.dataset.canReportMessages === '1';

    const profilesNode = document.querySelector('#chatProfilesData');
    const profileModal = document.querySelector('#chatProfileModal');
    const dialogMenuButton = document.querySelector('#chatDialogMenuButton');
    const dialogMenu = document.querySelector('#chatDialogMenu');
    let chatProfiles = [];

    try {
        chatProfiles = JSON.parse(profilesNode?.textContent || '[]');
    } catch (error) {
        chatProfiles = [];
    }

    const chatProfilesById = new Map(chatProfiles.map((profile) => [String(profile.id), profile]));

    function scrollMessagesDown() {
        if (messagesBox) {
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }
    }

    function escapeHtml(text) {
        return String(text)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }


    function profileChipMarkup(text) {
        return `<span class="feed-modal-chip">${escapeHtml(text || '')}</span>`;
    }

    function openChatProfileModal(userId) {
        if (!profileModal) return;
        const profile = chatProfilesById.get(String(userId));
        if (!profile) return;

        const avatar = profileModal.querySelector('#chatProfileModalAvatar');
        const name = profileModal.querySelector('#chatProfileModalName');
        const username = profileModal.querySelector('#chatProfileModalUsername');
        const city = profileModal.querySelector('#chatProfileModalCity');
        const about = profileModal.querySelector('#chatProfileModalAbout');
        const status = profileModal.querySelector('#chatProfileModalStatus');
        const interests = profileModal.querySelector('#chatProfileModalInterests');
        const goals = profileModal.querySelector('#chatProfileModalGoals');

        if (avatar) avatar.src = profile.avatar_url || '../images/user-icon.png';
        if (name) name.textContent = `${profile.display_name || 'Пользователь'}${profile.age ? `, ${profile.age}` : ''}`;
        if (username) username.textContent = `@${profile.username || 'user'}`;
        if (city) city.textContent = profile.city_name || 'Город не указан';
        if (status) {
            status.classList.toggle('is-online', Boolean(profile.is_online));
            status.classList.toggle('is-offline', !profile.is_online);
            status.innerHTML = `<span></span>${escapeHtml(profile.online_label || 'Оффлайн')}`;
        }
        if (about) about.textContent = profile.about || 'Пользователь пока не добавил описание.';
        if (interests) interests.innerHTML = (Array.isArray(profile.interests) && profile.interests.length ? profile.interests : ['Интересы не указаны']).map(profileChipMarkup).join('');
        if (goals) goals.innerHTML = (Array.isArray(profile.goals) && profile.goals.length ? profile.goals : ['Разговоры']).map(profileChipMarkup).join('');

        if (dialogMenu) dialogMenu.hidden = true;
        if (dialogMenuButton) dialogMenuButton.setAttribute('aria-expanded', 'false');
        profileModal.hidden = false;
        document.body.classList.add('is-modal-open');
    }

    function closeChatProfileModal() {
        if (!profileModal) return;
        profileModal.hidden = true;
        document.body.classList.remove('is-modal-open');
    }

    function ensureTodayDivider() {
        if (!messagesBox) return;
        const dividers = Array.from(messagesBox.querySelectorAll('.chat-date-divider'));
        const hasToday = dividers.some((item) => item.textContent.trim() === 'Сегодня');
        if (!hasToday) {
            const divider = document.createElement('div');
            divider.className = 'chat-date-divider';
            divider.textContent = 'Сегодня';
            messagesBox.appendChild(divider);
        }
    }

    function appendChatMessage(item) {
        if (!messagesBox || !item) return;
        const messageId = Number(item.id || 0);
        if (messageId > 0 && messagesBox.querySelector(`[data-message-id="${messageId}"]`)) return;

        const empty = messagesBox.querySelector('.chat-empty');
        if (empty) empty.remove();

        const dateLabel = item.date_label || 'Сегодня';
        const dividers = Array.from(messagesBox.querySelectorAll('.chat-date-divider'));
        const hasDivider = dividers.some((divider) => divider.textContent.trim() === dateLabel);
        if (!hasDivider) {
            const divider = document.createElement('div');
            divider.className = 'chat-date-divider';
            divider.textContent = dateLabel;
            messagesBox.appendChild(divider);
        }

        const isOwn = Boolean(item.is_own);
        const article = document.createElement('article');
        article.className = `chat-message${isOwn ? ' is-own' : ''}`;
        article.dataset.messageId = item.id || '';

        const reportMarkup = !isOwn && canReportMessages
            ? `<div class="chat-message__more">
                    <button type="button" class="chat-message__more-button" aria-label="Действия с сообщением">⋯</button>
                    <div class="chat-message__menu">
                        <button type="button" data-report-message="${escapeHtml(item.id || '')}">Пожаловаться</button>
                    </div>
               </div>`
            : '';

        article.innerHTML = `
            ${!isOwn ? `<img class="chat-message__avatar" src="${escapeHtml(item.avatar_url || '../images/user-icon.png')}" alt="">` : ''}
            <div class="chat-message__content">
                <div class="chat-message__bubble">
                    <p>${escapeHtml(item.message_text || '').replace(/\n/g, '<br>')}</p>
                    <time>${escapeHtml(item.time_label || '')}</time>
                </div>
                ${reportMarkup}
            </div>
        `;
        messagesBox.appendChild(article);
        if (messageId > lastMessageId) lastMessageId = messageId;
        scrollMessagesDown();
    }

    function appendOwnMessage(item) {
        appendChatMessage(item);
    }

    async function loadNewMessages() {
        if (!messagesBox || !selectedChatId || document.hidden) return;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'get_messages');
        formData.append('chat_id', String(selectedChatId));
        formData.append('after_id', String(lastMessageId));

        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (!data.success || !Array.isArray(data.items)) return;

            data.items.forEach(appendChatMessage);
            if (Number(data.last_id || 0) > lastMessageId) {
                lastMessageId = Number(data.last_id || 0);
            }
        } catch (error) {
            // Не показываем alert при фоновой проверке, чтобы не мешать пользователю.
        }
    }

    if (selectedChatId > 0) {
        setInterval(loadNewMessages, 4000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) loadNewMessages();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            chatItems.forEach((item) => {
                const search = (item.dataset.chatSearch || '').toLowerCase();
                const isVisible = query === '' || search.includes(query);
                item.hidden = !isVisible;
                if (isVisible) visibleCount += 1;
            });

            if (searchEmpty) {
                searchEmpty.hidden = query === '' || visibleCount > 0;
            }
        });
    }

    if (messageInput) {
        messageInput.addEventListener('input', () => {
            messageInput.style.height = 'auto';
            messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
        });

        messageInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendForm?.requestSubmit();
            }
        });
    }

    if (sendForm) {
        sendForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const text = messageInput?.value.trim() || '';
            if (!text) return;

            const button = sendForm.querySelector('button[type="submit"]');
            if (button) button.disabled = true;

            const formData = new FormData(sendForm);

            try {
                const response = await fetch(actionUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await response.json();
                if (!data.success) {
                    alert(data.message || 'Не удалось отправить сообщение.');
                    return;
                }

                appendOwnMessage(data.item);
                if (messageInput) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                }
            } catch (error) {
                alert('Не удалось отправить сообщение. Попробуйте позже.');
            } finally {
                if (button) button.disabled = false;
            }
        });
    }

    page.addEventListener('click', (event) => {
        const profileOpenButton = event.target.closest('[data-open-chat-profile]');
        if (profileOpenButton) {
            event.preventDefault();
            event.stopPropagation();
            openChatProfileModal(profileOpenButton.dataset.openChatProfile || '');
            return;
        }

        if (event.target.closest('[data-close-chat-profile-modal]')) {
            closeChatProfileModal();
            return;
        }

        const chatDialogMoreButton = event.target.closest('#chatDialogMenuButton');
        if (chatDialogMoreButton && dialogMenu) {
            dialogMenu.hidden = !dialogMenu.hidden;
            chatDialogMoreButton.setAttribute('aria-expanded', String(!dialogMenu.hidden));
            return;
        }

        if (!event.target.closest('.chat-dialog__more') && dialogMenu && !dialogMenu.hidden) {
            dialogMenu.hidden = true;
            dialogMenuButton?.setAttribute('aria-expanded', 'false');
        }

        const moreButton = event.target.closest('.chat-message__more-button');
        if (moreButton) {
            const menuWrap = moreButton.closest('.chat-message__more');
            document.querySelectorAll('.chat-message__more.is-open').forEach((item) => {
                if (item !== menuWrap) item.classList.remove('is-open');
            });
            menuWrap?.classList.toggle('is-open');
            return;
        }

        if (!event.target.closest('.chat-message__more')) {
            document.querySelectorAll('.chat-message__more.is-open').forEach((item) => item.classList.remove('is-open'));
        }
    });

    const reportModal = document.querySelector('#chatReportModal');
    const reportForm = document.querySelector('#chatReportForm');
    const reportReason = document.querySelector('#chatReportReason');
    const reportDetails = document.querySelector('#chatReportDetails');
    const reportStatus = document.querySelector('#chatReportStatus');
    let reportMessageId = '';

    function setReportStatus(text, isError = false) {
        if (!reportStatus) return;
        reportStatus.textContent = text || '';
        reportStatus.classList.toggle('is-error', isError);
    }

    function openReportModal(messageId) {
        if (!reportModal || !reportForm) return;
        reportMessageId = String(messageId || '');
        if (reportReason) reportReason.value = '';
        if (reportDetails) reportDetails.value = '';
        setReportStatus('');
        reportModal.hidden = false;
        document.body.classList.add('is-modal-open');
    }

    function closeReportModal() {
        if (!reportModal) return;
        reportModal.hidden = true;
        reportMessageId = '';
        document.body.classList.remove('is-modal-open');
    }

    page.addEventListener('click', (event) => {
        const reportButton = event.target.closest('[data-report-message]');
        if (reportButton) {
            openReportModal(reportButton.dataset.reportMessage || '');
            return;
        }

        if (event.target.closest('[data-close-chat-report]')) {
            closeReportModal();
        }
    });

    if (reportForm) {
        reportForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!reportMessageId) return;
            if (!reportReason?.value) {
                setReportStatus('Выберите причину жалобы.', true);
                return;
            }

            setReportStatus('Отправляем жалобу...');
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'report_message');
            formData.append('message_id', reportMessageId);
            formData.append('reason_id', reportReason.value);
            formData.append('details', reportDetails?.value || '');

            try {
                const response = await fetch(actionUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await response.json();
                if (!data.success) {
                    setReportStatus(data.message || 'Не удалось отправить жалобу.', true);
                    return;
                }
                setReportStatus(data.message || 'Жалоба отправлена.');
                setTimeout(closeReportModal, 700);
            } catch (error) {
                setReportStatus('Не удалось отправить жалобу. Попробуйте позже.', true);
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeReportModal();
            closeChatProfileModal();
            if (dialogMenu) dialogMenu.hidden = true;
            dialogMenuButton?.setAttribute('aria-expanded', 'false');
            document.querySelectorAll('.chat-message__more.is-open').forEach((item) => item.classList.remove('is-open'));
        }
    });

    scrollMessagesDown();
})();
