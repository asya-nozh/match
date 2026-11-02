const avatarInput = document.querySelector('#profileAvatarInput');
const avatarPreview = document.querySelector('#profilePhotoPreview');
const interestsText = document.querySelector('#interestsText');
const refreshButton = document.querySelector('#refreshInterests');
const statusText = document.querySelector('#interestStatus');
const chipsContainer = document.querySelector('#interestChips');
const hiddenInputs = document.querySelector('#interestHiddenInputs');
const payloadInput = document.querySelector('#interestsPayload');
const csrfInput = document.querySelector('input[name="csrf_token"]');
const birthDateInput = document.querySelector('#profileBirthDate');
const profileForm = document.querySelector('.profile-edit');

let selectedInterests = [];

function escapeAttr(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function normalizeName(value) {
    return String(value).trim().replace(/\s+/g, ' ');
}

function setStatus(message, type = '') {
    if (!statusText) return;
    statusText.textContent = message;
    statusText.classList.toggle('is-error', type === 'error');
    statusText.classList.toggle('is-success', type === 'success');
}

function getInterestIcon(name) {
    const value = String(name).toLowerCase();

    if (value.includes('книг') || value.includes('чтен') || value.includes('литератур')) return 'book';
    if (value.includes('прогул') || value.includes('путеше') || value.includes('спорт') || value.includes('актив')) return 'walk';
    if (value.includes('разговор') || value.includes('общен') || value.includes('друз') || value.includes('знаком')) return 'chat';
    if (value.includes('кофе') || value.includes('кафе') || value.includes('еда')) return 'cup';
    if (value.includes('музык') || value.includes('концерт') || value.includes('песн')) return 'music';
    if (value.includes('кино') || value.includes('фильм') || value.includes('сериал')) return 'film';

    return 'sparkle';
}

function iconSvg(name) {
    const icons = {
        book: '<path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H20v15.5H7.5A2.5 2.5 0 0 0 5 21V5.5Zm0 0V21m2.5-3.5H20M8 6.5h8M8 9.5h7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        walk: '<path d="M13 4.8a2 2 0 1 0-2-2 2 2 0 0 0 2 2Zm-1.1 2.1 2.5 2.5 2.8.7M11.9 6.9 9 9.8l-.9 3.2m4.1-3.8-1.1 4.1 3.4 2.5m-4.2-2.1-2.1 5.1m7-2.7 2.5 3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        chat: '<path d="M5 6.5h14a2 2 0 0 1 2 2v6.2a2 2 0 0 1-2 2H11l-5 3v-3H5a2 2 0 0 1-2-2V8.5a2 2 0 0 1 2-2Zm3.3 4.8h.1m3.6 0h.1m3.6 0h.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        cup: '<path d="M5.5 7h10v5.5a4.5 4.5 0 0 1-4.5 4.5H10a4.5 4.5 0 0 1-4.5-4.5V7Zm10 2h1.2a2.3 2.3 0 0 1 0 4.6h-1.2M7 20h9M8 4h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        music: '<path d="M10 17.5a2.5 2.5 0 1 1-2.5-2.5H10V5l8-1.5v10a2.5 2.5 0 1 1-2.5-2.5H18V6.5L10 8v9.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        film: '<path d="M5 5h14v14H5V5Zm0 4h14M8 5v14m8-14v14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        sparkle: '<path d="M12 3.5 13.8 9l5.7 1.8-5.7 1.8L12 18l-1.8-5.4-5.7-1.8L10.2 9 12 3.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>'
    };

    return `<svg class="profile-chip__icon" viewBox="0 0 24 24" aria-hidden="true">${icons[name] || icons.sparkle}</svg>`;
}

function syncHiddenInputs() {
    if (!hiddenInputs) return;

    hiddenInputs.innerHTML = selectedInterests
        .map((name) => `<input type="hidden" name="interest_names[]" value="${escapeAttr(name)}">`)
        .join('');
}

function renderChips() {
    if (!chipsContainer) return;

    if (!selectedInterests.length) {
        chipsContainer.innerHTML = '<span class="profile-empty profile-empty--inline">Интересы пока не выбраны</span>';
        syncHiddenInputs();
        return;
    }

    chipsContainer.innerHTML = selectedInterests
        .map((name) => `
            <span class="profile-chip profile-chip--editable">
                ${iconSvg(getInterestIcon(name))}
                <span>${escapeAttr(name)}</span>
                <button type="button" aria-label="Удалить интерес ${escapeAttr(name)}" data-interest-remove="${escapeAttr(name)}">×</button>
            </span>
        `)
        .join('');

    syncHiddenInputs();
}

function setInterests(names) {
    const unique = [];
    const seen = new Set();

    names.forEach((name) => {
        const clean = normalizeName(name);
        const key = clean.toLowerCase();

        if (!clean || seen.has(key)) return;
        seen.add(key);
        unique.push(clean);
    });

    selectedInterests = unique.slice(0, 12);
    renderChips();
}

function validateBirthDate() {
    if (!birthDateInput) return true;

    const value = birthDateInput.value;
    const maxDate = birthDateInput.max;
    const minDate = birthDateInput.min || '1900-01-01';

    if (!value) {
        setStatus('Укажите дату рождения.', 'error');
        return false;
    }

    if (value < minDate) {
        setStatus('Укажите корректную дату рождения.', 'error');
        return false;
    }

    if (maxDate && value > maxDate) {
        setStatus('Редактирование доступно пользователям от 18 лет.', 'error');
        return false;
    }

    if (statusText && statusText.classList.contains('is-error')) {
        setStatus('Напишите текст и обновите список интересов.');
    }

    return true;
}

if (chipsContainer) {
    try {
        const initial = JSON.parse(chipsContainer.dataset.initial || '[]');
        setInterests(Array.isArray(initial) ? initial : []);
    } catch (error) {
        setInterests([]);
    }

    chipsContainer.addEventListener('click', (event) => {
        const button = event.target.closest('[data-interest-remove]');
        if (!button) return;

        const name = button.dataset.interestRemove;
        selectedInterests = selectedInterests.filter((item) => item !== name);
        renderChips();
    });
}

if (avatarInput && avatarPreview) {
    avatarInput.addEventListener('change', () => {
        const file = avatarInput.files && avatarInput.files[0];
        if (!file) return;

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            setStatus('Можно загрузить только JPG, PNG или WEBP.', 'error');
            avatarInput.value = '';
            return;
        }

        avatarPreview.src = URL.createObjectURL(file);
    });
}

birthDateInput?.addEventListener('change', validateBirthDate);
birthDateInput?.addEventListener('input', validateBirthDate);

profileForm?.addEventListener('submit', (event) => {
    if (!validateBirthDate()) {
        event.preventDefault();
    }
});

if (refreshButton && interestsText) {
    refreshButton.addEventListener('click', async () => {
        const text = interestsText.value.trim();

        if (text.length < 15) {
            setStatus('Напишите хотя бы одно полноценное предложение об интересах.', 'error');
            return;
        }

        refreshButton.disabled = true;
        refreshButton.textContent = 'Обработка...';
        setStatus('Идёт обработка интересов...');

        try {
            const formData = new FormData();
            formData.append('text', text);
            formData.append('csrf_token', csrfInput ? csrfInput.value : '');

            const response = await fetch('../php/extract_interests.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.ok) {
                setStatus(data.message || 'Не удалось обработать интересы.', 'error');
                return;
            }

            const payload = data.data || { interests: [], anti_interests: [] };
            const names = (payload.interests || []).map((item) => item.name).filter(Boolean);

            if (!names.length) {
                setStatus('Интересы не найдены. Попробуйте описать их подробнее.', 'error');
                setInterests([]);
                if (payloadInput) payloadInput.value = JSON.stringify(payload);
                return;
            }

            setInterests(names);
            if (payloadInput) payloadInput.value = JSON.stringify(payload);

            setStatus('Интересы обновлены.', 'success');
        } catch (error) {
            setStatus('Не удалось обработать интересы. Попробуйте позже.', 'error');
        } finally {
            refreshButton.disabled = false;
            refreshButton.textContent = 'Обновить интересы';
        }
    });
}
