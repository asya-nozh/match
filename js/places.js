(() => {
    const page = document.querySelector('.places-page');
    if (!page) return;

    const actionUrl = page.dataset.actionUrl || '../php/place_action.php';
    const csrfToken = page.dataset.csrf || '';
    const cityFilter = page.querySelector('#placesFilterCity');
    const categoryFilter = page.querySelector('#placesFilterCategory');
    const searchInput = page.querySelector('#placesSearch');
    const cards = Array.from(page.querySelectorAll('.place-card'));
    const emptyState = page.querySelector('#placesEmptyState');
    const loadMoreButton = page.querySelector('#placesLoadMore');
    const modal = page.querySelector('#placeInviteModal');
    const inviteName = page.querySelector('#placeInviteName');
    const inviteStatus = page.querySelector('#placeInviteStatus');
    let activePlaceId = '';
    let visibleLimit = getInitialLimit();

    function getInitialLimit() {
        return window.matchMedia('(max-width: 767px)').matches ? 3 : 6;
    }

    function getStep() {
        return window.matchMedia('(max-width: 767px)').matches ? 3 : 6;
    }

    function normalize(value) {
        return String(value || '').trim().toLowerCase();
    }

    function getFilteredCards() {
        const city = cityFilter?.value || '';
        const category = categoryFilter?.value || '';
        const search = normalize(searchInput?.value || '');

        return cards.filter((card) => {
            const okCity = !city || card.dataset.cityId === city;
            const okCategory = !category || card.dataset.categoryId === category;
            const okSearch = !search || normalize(card.dataset.search).includes(search);
            return okCity && okCategory && okSearch;
        });
    }

    function renderPlaces(resetLimit = false) {
        if (resetLimit) visibleLimit = getInitialLimit();

        const filteredCards = getFilteredCards();
        const visibleCards = filteredCards.slice(0, visibleLimit);

        cards.forEach((card) => {
            const shouldHide = !visibleCards.includes(card);
            card.hidden = shouldHide;
            card.classList.toggle('is-place-hidden', shouldHide);
        });

        if (emptyState) {
            const shouldShowEmpty = filteredCards.length === 0;
            emptyState.hidden = !shouldShowEmpty;
            emptyState.classList.toggle('is-place-hidden', !shouldShowEmpty);
        }

        if (loadMoreButton) {
            const shouldHideButton = filteredCards.length <= visibleLimit;
            loadMoreButton.hidden = shouldHideButton;
            loadMoreButton.classList.toggle('is-place-hidden', shouldHideButton);
        }
    }

    function setStatus(text, isError = false) {
        if (!inviteStatus) return;
        inviteStatus.textContent = text || '';
        inviteStatus.classList.toggle('is-error', isError);
    }

    function openInvite(placeId) {
        activePlaceId = String(placeId || '');
        const card = page.querySelector(`.place-card[data-place-id="${CSS.escape(activePlaceId)}"]`);
        const title = card?.querySelector('h2')?.textContent || 'место';
        if (inviteName) inviteName.textContent = title;
        setStatus('');
        if (modal) {
            modal.hidden = false;
            document.body.classList.add('is-modal-open');
        }
    }

    function closeInvite() {
        activePlaceId = '';
        if (modal) modal.hidden = true;
        document.body.classList.remove('is-modal-open');
    }

    async function sendInvite(chatId) {
        if (!activePlaceId || !chatId) return;
        setStatus('Отправляем приглашение...');

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'invite_place');
        formData.append('place_id', activePlaceId);
        formData.append('chat_id', String(chatId));

        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (!data.success) {
                setStatus(data.message || 'Не удалось отправить приглашение.', true);
                return;
            }
            setStatus(data.message || 'Приглашение отправлено.');
            setTimeout(closeInvite, 700);
        } catch (error) {
            setStatus('Не удалось отправить приглашение. Попробуйте позже.', true);
        }
    }

    [cityFilter, categoryFilter, searchInput].forEach((control) => {
        control?.addEventListener('input', () => renderPlaces(true));
        control?.addEventListener('change', () => renderPlaces(true));
    });

    loadMoreButton?.addEventListener('click', () => {
        visibleLimit += getStep();
        renderPlaces(false);
    });

    window.addEventListener('resize', () => {
        const minLimit = getInitialLimit();
        if (visibleLimit < minLimit) visibleLimit = minLimit;
        renderPlaces(false);
    });

    page.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-open-place-invite]');
        if (openButton) {
            openInvite(openButton.dataset.openPlaceInvite || '');
            return;
        }

        const inviteButton = event.target.closest('[data-invite-chat]');
        if (inviteButton) {
            sendInvite(inviteButton.dataset.inviteChat || '');
            return;
        }

        if (event.target.closest('[data-close-place-invite]')) {
            closeInvite();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            closeInvite();
        }
    });

    renderPlaces(true);
})();
