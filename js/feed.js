(() => {
    const page = document.querySelector('.feed-page, .connections-page');
    const dataNode = document.querySelector('#feedProfilesData');
    const modal = document.querySelector('#feedProfileModal');

    if (!page || !dataNode || !modal) return;

    let profiles = [];
    try {
        profiles = JSON.parse(dataNode.textContent || '[]');
    } catch (error) {
        profiles = [];
    }

    const profilesById = new Map(profiles.map((profile) => [String(profile.id), profile]));
    let activeProfile = null;
    let reportProfile = null;
    let isReportSubmitting = false;

    const actionUrl = page.dataset.actionUrl || '../php/feed_action.php';
    const csrfToken = page.dataset.csrf || '';
    const chatsUrl = page.dataset.chatsUrl || '../pages/chats.php';
    const isConnectionsPage = page.classList.contains('connections-page');

    const feedGrid = page.querySelector('#feedGrid');
    const loadMoreButton = page.querySelector('#feedLoadMore');
    let visibleCount = getCardsStep();

    const filterCity = page.querySelector('#feedFilterCity');
    const filterGoal = page.querySelector('#feedFilterGoal');
    const filterAgeRange = page.querySelector('#feedFilterAgeRange');
    const filterInterest = page.querySelector('#feedFilterInterest');
    const filterSearch = page.querySelector('#feedFilterSearch');
    const filterProfileMatch = page.querySelector('#feedFilterProfileMatch');

    const avatar = modal.querySelector('#feedModalAvatar');
    const name = modal.querySelector('#feedModalName');
    const username = modal.querySelector('#feedModalUsername');
    const city = modal.querySelector('#feedModalCity');
    const about = modal.querySelector('#feedModalAbout');
    const interests = modal.querySelector('#feedModalInterests');
    const goals = modal.querySelector('#feedModalGoals');
    const messageButton = modal.querySelector('#feedModalMessage');
    const likeButton = modal.querySelector('#feedModalLike');
    const moreButton = modal.querySelector('#feedModalMore');
    const moreMenu = modal.querySelector('#feedModalMoreMenu');
    const status = modal.querySelector('#feedModalStatus');

    const reportModal = page.querySelector('#feedReportModal');
    const reportForm = page.querySelector('#feedReportForm');
    const reportReason = page.querySelector('#feedReportReason');
    const reportDetails = page.querySelector('#feedReportDetails');
    const reportStatus = page.querySelector('#feedReportStatus');
    const reportUser = page.querySelector('#feedReportUser');

    function getCardsStep() {
        return window.matchMedia('(max-width: 767px)').matches ? 3 : 6;
    }

    function getCards() {
        return Array.from(page.querySelectorAll('.feed-card'));
    }

    function normalize(value) {
        return String(value ?? '').trim().toLowerCase();
    }

    function pipeList(value) {
        return String(value || '').split('|').map((item) => item.trim()).filter(Boolean);
    }

    function parseAgeRange(value) {
        const text = String(value || '').replace(/\s+/g, '').replace(/[–—]/g, '-');
        if (!text) return { min: 0, max: 0 };

        const parts = text.split('-').filter((part) => part !== '');
        const numbers = parts.map((part) => Number(part)).filter((number) => Number.isFinite(number) && number > 0);

        if (numbers.length >= 2) {
            return { min: Math.min(numbers[0], numbers[1]), max: Math.max(numbers[0], numbers[1]) };
        }

        if (numbers.length === 1) {
            return text.startsWith('-') ? { min: 0, max: numbers[0] } : { min: numbers[0], max: 0 };
        }

        return { min: 0, max: 0 };
    }

    function cardMatchesFilters(card) {
        const cityValue = filterCity?.value || '';
        const goalValue = filterGoal?.value || '';
        const interestValue = filterInterest?.value || '';
        const searchValue = normalize(filterSearch?.value || '');
        const ageRange = parseAgeRange(filterAgeRange?.value || '');
        const ageMinValue = ageRange.min;
        const ageMaxValue = ageRange.max;

        if (cityValue && card.dataset.city !== cityValue) return false;
        if (goalValue && !pipeList(card.dataset.goals).includes(goalValue)) return false;
        if (interestValue && !pipeList(card.dataset.interests).includes(interestValue)) return false;

        const age = Number(card.dataset.age || 0);
        if (ageMinValue && (!age || age < ageMinValue)) return false;
        if (ageMaxValue && (!age || age > ageMaxValue)) return false;

        if (searchValue && !normalize(card.dataset.search).includes(searchValue)) return false;

        return true;
    }

    function sortCards() {
        if (!feedGrid) return;
        const cards = getCards();
        const useProfileInfo = Boolean(filterProfileMatch?.checked);

        cards.sort((a, b) => {
            if (useProfileInfo) {
                const scoreDiff = Number(b.dataset.score || 0) - Number(a.dataset.score || 0);
                if (scoreDiff !== 0) return scoreDiff;

                const commonDiff = Number(b.dataset.common || 0) - Number(a.dataset.common || 0);
                if (commonDiff !== 0) return commonDiff;
            }

            return Number(a.dataset.order || 0) - Number(b.dataset.order || 0);
        });

        cards.forEach((card) => feedGrid.appendChild(card));
    }

    function applyCardLimit(reset = false) {
        if (!feedGrid) return;
        const cards = getCards();
        if (cards.length === 0) return;
        if (reset) visibleCount = getCardsStep();

        sortCards();

        let visibleIndex = 0;
        let filteredTotal = 0;

        getCards().forEach((card) => {
            const matches = cardMatchesFilters(card);
            card.classList.toggle('is-filter-hidden', !matches);

            if (!matches) {
                card.classList.add('is-feed-hidden');
                return;
            }

            filteredTotal += 1;
            card.classList.toggle('is-feed-hidden', visibleIndex >= visibleCount);
            visibleIndex += 1;
        });

        if (loadMoreButton) {
            loadMoreButton.hidden = visibleCount >= filteredTotal;
        }
    }

    function escapeText(value) {
        return String(value ?? '').replace(/[&<>'"]/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#039;',
            '"': '&quot;',
        }[char]));
    }

    function chipMarkup(text) {
        return `<span class="feed-modal-chip">${escapeText(text)}</span>`;
    }

    function setStatus(message, isError = false) {
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('is-error', isError);
    }

    function heartIcon(extraClass = '') {
        return `<svg class="feed-action__icon feed-like-icon ${extraClass}" viewBox="0 0 24 24" aria-hidden="true">
            <path class="feed-like-icon__heart" d="M12 20.2 4.7 13.1a4.8 4.8 0 0 1 6.8-6.8l.5.6.5-.6a4.8 4.8 0 0 1 6.8 6.8L12 20.2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path class="feed-like-icon__plus" d="M18.5 13.2v6.2m-3.1-3.1h6.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>`;
    }

    function setLikeButtonContent(label, iconClass = '') {
        if (!likeButton) return;
        likeButton.innerHTML = `${heartIcon(iconClass)}<span>${escapeText(label)}</span>`;
    }

    function updateCardState(profile) {
        const card = document.querySelector(`.feed-card[data-user-id="${String(profile.id)}"]`);
        if (!card) return;
        card.classList.toggle('is-liked', Boolean(profile.liked_by_me) && !profile.is_matched);
        card.classList.toggle('is-incoming', Boolean(profile.liked_me) && !profile.liked_by_me && !profile.is_matched);
        card.classList.toggle('is-matched', Boolean(profile.is_matched));
    }

    function updateConnectionButtons(profile) {
        document.querySelectorAll(`.connection-row[data-user-id="${String(profile.id)}"] .connection-action--like`).forEach((button) => {
            button.classList.toggle('is-liked', Boolean(profile.liked_by_me) && !profile.is_matched);
            button.classList.toggle('is-incoming', Boolean(profile.liked_me) && !profile.liked_by_me && !profile.is_matched);
            button.classList.toggle('is-matched', Boolean(profile.is_matched));
        });
    }

    function updateActions() {
        if (!activeProfile) return;

        if (likeButton) {
            likeButton.classList.remove('is-liked', 'is-incoming', 'is-matched');

            if (activeProfile.is_matched) {
                likeButton.classList.add('is-matched');
                setLikeButtonContent('мэтч', 'is-matched');
            } else if (activeProfile.liked_by_me) {
                likeButton.classList.add('is-liked');
                setLikeButtonContent('лайк', 'is-liked');
            } else if (activeProfile.liked_me) {
                likeButton.classList.add('is-incoming');
                setLikeButtonContent('лайк', 'is-incoming');
            } else {
                setLikeButtonContent('лайк');
            }
        }

        if (messageButton) {
            const canWrite = Boolean(activeProfile.is_matched);
            messageButton.classList.toggle('is-disabled', !canWrite);
            messageButton.setAttribute('aria-disabled', String(!canWrite));
            messageButton.href = canWrite ? `${chatsUrl}?user_id=${encodeURIComponent(activeProfile.id)}` : chatsUrl;
        }

        updateCardState(activeProfile);
        updateConnectionButtons(activeProfile);
    }

    function openModal(profile) {
        activeProfile = profile;
        if (!activeProfile) return;

        if (avatar) avatar.src = profile.avatar_url || '../images/user-icon.png';
        if (name) name.textContent = `${profile.display_name || 'Пользователь'}${profile.age ? `, ${profile.age}` : ''}`;
        if (username) username.textContent = `@${profile.username || 'user'}`;
        if (city) city.textContent = profile.city_name || 'Город не указан';
        if (about) about.textContent = profile.about || 'Пользователь пока не добавил описание.';
        if (interests) interests.innerHTML = (profile.interests && profile.interests.length ? profile.interests : ['Интересы не указаны']).map(chipMarkup).join('');
        if (goals) goals.innerHTML = (profile.goals && profile.goals.length ? profile.goals : ['Разговоры']).map(chipMarkup).join('');

        setStatus('');
        if (moreMenu) moreMenu.hidden = true;
        if (moreButton) moreButton.setAttribute('aria-expanded', 'false');
        updateActions();

        modal.hidden = false;
        document.body.classList.add('is-modal-open');
    }

    function closeModal(options = {}) {
        modal.hidden = true;
        if (!options.keepActiveProfile) {
            activeProfile = null;
        }
        if (!options.keepBodyLock) {
            document.body.classList.remove('is-modal-open');
        }
    }

    function setReportStatus(message, isError = false) {
        if (!reportStatus) return;
        reportStatus.textContent = message || '';
        reportStatus.classList.toggle('is-error', isError);
    }

    function openReportModal() {
        const profile = activeProfile;
        if (!profile || !reportModal || !reportForm) {
            setStatus('Сейчас невозможно открыть форму жалобы.', true);
            return;
        }

        reportProfile = profile;
        isReportSubmitting = false;
        reportForm.querySelector('button[type="submit"]')?.removeAttribute('disabled');

        if (reportReason) reportReason.value = '';
        if (reportDetails) reportDetails.value = '';
        if (reportUser) {
            reportUser.textContent = `Профиль: ${profile.display_name || 'Пользователь'}${profile.age ? `, ${profile.age}` : ''} · @${profile.username || 'user'}`;
        }

        setReportStatus('');
        if (moreMenu) moreMenu.hidden = true;
        if (moreButton) moreButton.setAttribute('aria-expanded', 'false');

        closeModal({ keepActiveProfile: true, keepBodyLock: true });
        reportModal.hidden = false;
        document.body.classList.add('is-modal-open');
    }

    function closeReportModal() {
        if (!reportModal) return;
        reportModal.hidden = true;
        reportProfile = null;
        isReportSubmitting = false;
        reportForm?.querySelector('button[type="submit"]')?.removeAttribute('disabled');
        setReportStatus('');

        if (!modal.hidden) {
            document.body.classList.add('is-modal-open');
        } else {
            document.body.classList.remove('is-modal-open');
        }
    }

    async function sendReport() {
        const targetProfile = reportProfile || activeProfile;
        if (!targetProfile || !reportForm) {
            setReportStatus('Не удалось определить профиль для жалобы. Откройте профиль заново.', true);
            return;
        }

        if (isReportSubmitting) return;

        if (!reportReason?.value) {
            setReportStatus('Выберите причину жалобы.', true);
            reportReason?.focus();
            return;
        }

        const submitButton = reportForm.querySelector('button[type="submit"]');
        isReportSubmitting = true;
        if (submitButton) submitButton.disabled = true;
        setReportStatus('Отправляем жалобу...');

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'report');
        formData.append('target_user_id', String(targetProfile.id));
        formData.append('reason_id', reportReason.value);
        formData.append('details', reportDetails?.value || '');

        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });

            const responseText = await response.text();
            let data = null;
            try {
                data = JSON.parse(responseText);
            } catch (error) {
                data = null;
            }

            if (!response.ok || !data || !data.success) {
                setReportStatus(data?.message || 'Не удалось отправить жалобу.', true);
                isReportSubmitting = false;
                if (submitButton) submitButton.disabled = false;
                return;
            }

            setReportStatus(data.message || 'Жалоба отправлена.');
            setTimeout(closeReportModal, 700);
        } catch (error) {
            setReportStatus('Не удалось отправить жалобу. Попробуйте позже.', true);
            isReportSubmitting = false;
            if (submitButton) submitButton.disabled = false;
        }
    }

    async function sendAction(action, options = {}) {
        if (!activeProfile) return false;

        setStatus('Выполняется...');

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', action);
        formData.append('target_user_id', String(activeProfile.id));

        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (!data.success) {
                setStatus(data.message || 'Не удалось выполнить действие.', true);
                return false;
            }

            if (action === 'like') {
                activeProfile.liked_by_me = Boolean(data.liked_by_me);
                activeProfile.liked_me = Boolean(data.liked_me);
                activeProfile.is_matched = Boolean(data.is_matched);
                updateActions();
            }

            if (action === 'block') {
                document.querySelector(`.feed-card[data-user-id="${String(activeProfile.id)}"]`)?.remove();
                applyCardLimit();
                setStatus(data.message || 'Пользователь заблокирован.');
                if (options.reload) setTimeout(() => window.location.reload(), 500);
                else setTimeout(closeModal, 700);
                return true;
            }

            if (action === 'unblock') {
                setStatus(data.message || 'Пользователь разблокирован.');
                if (options.reload) setTimeout(() => window.location.reload(), 500);
                return true;
            }

            setStatus(data.message || 'Действие выполнено.');
            if (options.reload) setTimeout(() => window.location.reload(), 500);
            return true;
        } catch (error) {
            setStatus('Не удалось выполнить действие. Проверьте подключение и попробуйте ещё раз.', true);
            return false;
        }
    }

    page.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-open-profile]');
        if (openButton) {
            const profile = profilesById.get(String(openButton.dataset.openProfile));
            openModal(profile);
            return;
        }

        const connectionButton = event.target.closest('[data-connection-action]');
        if (connectionButton) {
            const profile = profilesById.get(String(connectionButton.dataset.userId));
            if (!profile) return;
            activeProfile = profile;
            sendAction(connectionButton.dataset.connectionAction || '', { reload: true });
            return;
        }

        if (event.target.closest('[data-close-modal]')) {
            closeModal();
            return;
        }

        const menuAction = event.target.closest('[data-feed-action]');
        if (menuAction) {
            const action = menuAction.dataset.feedAction || '';
            if (moreMenu) moreMenu.hidden = true;
            if (moreButton) moreButton.setAttribute('aria-expanded', 'false');

            if (action === 'report') {
                openReportModal();
                return;
            }

            sendAction(action, { reload: isConnectionsPage });
            return;
        }

        if (event.target.closest('[data-close-feed-report]')) {
            closeReportModal();
            return;
        }
    });

    if (likeButton) {
        likeButton.addEventListener('click', () => sendAction('like', { reload: isConnectionsPage }));
    }

    if (moreButton && moreMenu) {
        moreButton.addEventListener('click', () => {
            moreMenu.hidden = !moreMenu.hidden;
            moreButton.setAttribute('aria-expanded', String(!moreMenu.hidden));
        });
    }

    if (messageButton) {
        messageButton.addEventListener('click', (event) => {
            if (messageButton.classList.contains('is-disabled')) {
                event.preventDefault();
                setStatus('Написать можно после взаимного лайка.');
            }
        });
    }

    if (reportForm) {
        reportForm.addEventListener('submit', (event) => {
            event.preventDefault();
            sendReport();
        });
    }

    if (loadMoreButton) {
        loadMoreButton.addEventListener('click', () => {
            visibleCount += getCardsStep();
            applyCardLimit();
        });
    }

    [filterCity, filterGoal, filterAgeRange, filterInterest, filterSearch, filterProfileMatch].forEach((control) => {
        if (!control) return;
        control.addEventListener('input', () => applyCardLimit(true));
        control.addEventListener('change', () => applyCardLimit(true));
    });

    window.addEventListener('resize', () => {
        visibleCount = Math.max(visibleCount, getCardsStep());
        applyCardLimit();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (reportModal && !reportModal.hidden) {
                closeReportModal();
                return;
            }
            if (!modal.hidden) {
                closeModal();
            }
        }
    });

    applyCardLimit();
})();
