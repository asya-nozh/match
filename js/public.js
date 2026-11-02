const burger = document.querySelector('.header__burger');
const mobileMenu = document.querySelector('.header__mobile-menu');
const header = document.querySelector('.header');
const backToTop = document.querySelector('.back-to-top');

function closePublicMenu() {
    if (!burger || !mobileMenu) return;

    mobileMenu.classList.remove('is-open');
    burger.classList.remove('is-active');
    burger.setAttribute('aria-expanded', 'false');
}

if (burger && mobileMenu) {
    burger.addEventListener('click', () => {
        const isOpen = mobileMenu.classList.toggle('is-open');
        burger.classList.toggle('is-active', isOpen);
        burger.setAttribute('aria-expanded', String(isOpen));
    });

    mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closePublicMenu);
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closePublicMenu();
    });
}

function handlePageScroll() {
    const isScrolled = window.scrollY > 80;

    if (header) {
        header.classList.toggle('header--scrolled', isScrolled);
    }

    if (backToTop) {
        backToTop.classList.toggle('is-visible', window.scrollY > 600);
    }
}

window.addEventListener('scroll', handlePageScroll);
window.addEventListener('load', handlePageScroll);

if (backToTop) {
    backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', event => {
        const targetId = link.getAttribute('href');
        if (!targetId || targetId === '#') return;

        const target = document.querySelector(targetId);
        if (!target) return;

        event.preventDefault();
        closePublicMenu();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

const storiesSlider = document.querySelector('.stories-slider');

if (storiesSlider) {
    const track = storiesSlider.querySelector('.stories-slider__track');
    const viewport = storiesSlider.querySelector('.stories-slider__viewport');
    const prevBtn = storiesSlider.querySelector('.stories-slider__arrow--prev');
    const nextBtn = storiesSlider.querySelector('.stories-slider__arrow--next');
    const dotsWrap = storiesSlider.querySelector('.stories-slider__dots');
    const cards = track ? Array.from(track.querySelectorAll('.story-card')) : [];

    let cardStep = 0;
    let activeIndex = 0;
    let autoplayTimer = null;
    let isAnimating = false;
    let isPointerDown = false;
    let startX = 0;
    let startY = 0;
    let currentX = 0;
    let prependedCount = 0;
    let hasDragged = false;
    let ignoreNextClick = false;

    cards.forEach((card, index) => {
        card.dataset.slideIndex = String(index);
    });

    function getCards() {
        return track ? Array.from(track.querySelectorAll('.story-card')) : [];
    }

    function getGap() {
        if (!track) return 0;
        const styles = window.getComputedStyle(track);
        return parseFloat(styles.columnGap || styles.gap) || 0;
    }

    function updateCardStep() {
        const firstCard = track?.querySelector('.story-card');
        cardStep = firstCard ? firstCard.offsetWidth + getGap() : 0;
    }

    function setTransition(enabled) {
        if (!track) return;
        track.style.transition = enabled ? 'transform .35s ease' : 'none';
    }

    function setTranslate(value) {
        if (!track) return;
        track.style.transform = `translate3d(${value}px, 0, 0)`;
    }

    function forceReflow() {
        track?.offsetHeight;
    }

    function updateDots() {
        if (!dotsWrap) return;

        dotsWrap.querySelectorAll('.stories-slider__dot').forEach((dot, index) => {
            dot.classList.toggle('is-active', index === activeIndex);
        });
    }

    function createDots() {
        if (!dotsWrap || !cards.length) return;

        dotsWrap.innerHTML = '';

        cards.forEach((_, index) => {
            const dot = document.createElement('button');
            dot.className = 'stories-slider__dot';
            dot.type = 'button';
            dot.setAttribute('aria-label', `Показать историю ${index + 1}`);

            dot.addEventListener('click', () => {
                if (isAnimating || index === activeIndex) return;

                const forwardDistance = (index - activeIndex + cards.length) % cards.length;
                const backwardDistance = (activeIndex - index + cards.length) % cards.length;

                if (forwardDistance <= backwardDistance) {
                    moveNext(forwardDistance || cards.length);
                } else {
                    movePrev(backwardDistance);
                }

                restartAutoplay();
            });

            dotsWrap.appendChild(dot);
        });

        updateDots();
    }

    function onTransitionEnd(callback) {
        if (!track) return;

        let isDone = false;

        function done() {
            if (isDone) return;
            isDone = true;
            track.removeEventListener('transitionend', done);
            callback();
        }

        track.addEventListener('transitionend', done);
        window.setTimeout(done, 430);
    }

    function moveNext(count = 1) {
        if (!track || isAnimating || !cards.length || count <= 0) return;

        updateCardStep();
        if (!cardStep) return;

        const slidesToMove = Math.min(count, cards.length);
        isAnimating = true;
        setTransition(true);
        setTranslate(-slidesToMove * cardStep);

        onTransitionEnd(() => {
            setTransition(false);

            for (let i = 0; i < slidesToMove; i += 1) {
                const firstCard = track.firstElementChild;
                if (firstCard) track.appendChild(firstCard);
            }

            setTranslate(0);
            activeIndex = (activeIndex + slidesToMove) % cards.length;
            updateDots();
            forceReflow();
            setTransition(true);
            isAnimating = false;
        });
    }

    function movePrev(count = 1) {
        if (!track || isAnimating || !cards.length || count <= 0) return;

        updateCardStep();
        if (!cardStep) return;

        const slidesToMove = Math.min(count, cards.length);
        isAnimating = true;
        setTransition(false);

        for (let i = 0; i < slidesToMove; i += 1) {
            const lastCard = track.lastElementChild;
            if (lastCard) track.insertBefore(lastCard, track.firstElementChild);
        }

        setTranslate(-slidesToMove * cardStep);
        forceReflow();
        setTransition(true);
        setTranslate(0);

        activeIndex = (activeIndex - slidesToMove + cards.length) % cards.length;
        updateDots();

        onTransitionEnd(() => {
            setTransition(false);
            setTranslate(0);
            forceReflow();
            setTransition(true);
            isAnimating = false;
        });
    }

    function stopAutoplay() {
        if (autoplayTimer) {
            clearInterval(autoplayTimer);
            autoplayTimer = null;
        }
    }

    function startAutoplay() {
        stopAutoplay();
        autoplayTimer = setInterval(() => moveNext(1), 5000);
    }

    function restartAutoplay() {
        stopAutoplay();
        startAutoplay();
    }

    function preparePrevDrag() {
        if (!track || prependedCount > 0 || !cardStep) return;

        prependedCount = 1;
        const lastCard = track.lastElementChild;

        if (lastCard) {
            track.insertBefore(lastCard, track.firstElementChild);
            setTranslate(-cardStep);
        }
    }

    function finishDrag() {
        if (!isPointerDown || !track) return;

        isPointerDown = false;
        viewport?.classList.remove('is-dragging');

        const deltaX = currentX - startX;
        const absDeltaX = Math.abs(deltaX);
        const minDistance = Math.min(75, cardStep * .22);

        setTransition(true);

        if (prependedCount > 0) {
            if (absDeltaX >= minDistance && deltaX > 0) {
                isAnimating = true;
                setTranslate(0);

                onTransitionEnd(() => {
                    activeIndex = (activeIndex - 1 + cards.length) % cards.length;
                    updateDots();
                    setTransition(false);
                    setTranslate(0);
                    forceReflow();
                    setTransition(true);
                    isAnimating = false;
                });
            } else {
                isAnimating = true;
                setTranslate(-prependedCount * cardStep);

                onTransitionEnd(() => {
                    setTransition(false);

                    for (let i = 0; i < prependedCount; i += 1) {
                        const firstCard = track.firstElementChild;
                        if (firstCard) track.appendChild(firstCard);
                    }

                    setTranslate(0);
                    forceReflow();
                    setTransition(true);
                    isAnimating = false;
                });
            }
        } else if (absDeltaX >= minDistance && deltaX < 0) {
            moveNext(1);
        } else {
            setTranslate(0);
        }

        prependedCount = 0;
        restartAutoplay();

        window.setTimeout(() => {
            ignoreNextClick = false;
            hasDragged = false;
        }, 160);
    }

    nextBtn?.addEventListener('click', () => {
        moveNext(1);
        restartAutoplay();
    });

    prevBtn?.addEventListener('click', () => {
        movePrev(1);
        restartAutoplay();
    });

    viewport?.addEventListener('pointerdown', event => {
        if (!event.isPrimary || isAnimating) return;

        updateCardStep();
        if (!cardStep) return;

        isPointerDown = true;
        startX = event.clientX;
        startY = event.clientY;
        currentX = event.clientX;
        prependedCount = 0;
        hasDragged = false;
        ignoreNextClick = false;

        stopAutoplay();
        setTransition(false);
        setTranslate(0);
        viewport.classList.add('is-dragging');
        viewport.setPointerCapture?.(event.pointerId);
    });

    viewport?.addEventListener('pointermove', event => {
        if (!isPointerDown || !event.isPrimary) return;

        currentX = event.clientX;
        const deltaX = currentX - startX;
        const deltaY = event.clientY - startY;

        if (Math.abs(deltaX) > 5) {
            hasDragged = true;
            ignoreNextClick = true;
        }

        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            event.preventDefault();
        }

        if (deltaX > 0) {
            preparePrevDrag();
            setTranslate((-prependedCount * cardStep) + deltaX);
        } else {
            setTranslate(deltaX);
        }
    });

    viewport?.addEventListener('pointerup', finishDrag);
    viewport?.addEventListener('pointercancel', finishDrag);
    viewport?.addEventListener('lostpointercapture', finishDrag);

    viewport?.addEventListener('click', event => {
        if (ignoreNextClick || hasDragged) {
            event.preventDefault();
        }
    });

    storiesSlider.addEventListener('mouseenter', stopAutoplay);
    storiesSlider.addEventListener('mouseleave', startAutoplay);
    storiesSlider.addEventListener('focusin', stopAutoplay);
    storiesSlider.addEventListener('focusout', startAutoplay);

    window.addEventListener('resize', () => {
        updateCardStep();
        setTransition(false);
        setTranslate(0);
        forceReflow();
        setTransition(true);
    });

    updateCardStep();
    createDots();
    startAutoplay();
}

const loginModeButtons = document.querySelectorAll('[data-login-mode]');
const loginInput = document.querySelector('#login');
const loginModeInput = document.querySelector('#loginMode');
const loginFieldIcon = document.querySelector('#loginFieldIcon');

function setFieldError(input, message) {
    if (!input) return;

    const group = input.closest('.form-group');
    const field = input.closest('.form-field') || group?.querySelector('.form-field');
    const error = group?.querySelector('[data-error-for="' + input.id + '"]');

    if (error) {
        error.textContent = message || '';
        error.classList.toggle('is-visible', Boolean(message));
    }

    if (field) {
        field.classList.toggle('is-invalid', Boolean(message));
    }

    input.setAttribute('aria-invalid', message ? 'true' : 'false');
}

function clearFieldError(input) {
    setFieldError(input, '');
}

function setLoginValidationMode(mode) {
    if (!loginInput) return;

    loginInput.type = mode === 'email' ? 'email' : 'text';
    loginInput.placeholder = mode === 'email' ? 'Почта' : 'Логин';
    loginInput.autocomplete = mode === 'email' ? 'email' : 'username';

    if (loginFieldIcon) {
        loginFieldIcon.setAttribute('href', mode === 'email' ? '#icon-mail' : '#icon-profile');
    }

    clearFieldError(loginInput);
}

if (loginModeButtons.length && loginInput && loginModeInput) {
    loginModeButtons.forEach(button => {
        button.addEventListener('click', () => {
            const mode = button.dataset.loginMode || 'username';

            loginModeButtons.forEach(item => {
                item.classList.toggle('is-active', item === button);
            });

            loginModeInput.value = mode;
            setLoginValidationMode(mode);
            loginInput.focus();
        });
    });

    setLoginValidationMode(loginModeInput.value || 'username');
}

document.querySelectorAll('[data-password-target]').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordTarget);
        if (!input) return;

        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(showPassword));
        button.setAttribute('aria-label', showPassword ? 'Скрыть пароль' : 'Показать пароль');
    });
});

const loginForm = document.getElementById('loginForm');

function validateLoginForm() {
    if (!loginForm || !loginInput) return true;

    let isValid = true;
    const mode = loginModeInput?.value || 'username';
    const value = loginInput.value.trim();
    const passwordField = loginForm.querySelector('#password');

    loginInput.value = value;
    clearFieldError(loginInput);
    clearFieldError(passwordField);

    if (mode === 'email') {
        if (value === '') {
            setFieldError(loginInput, 'Введите электронную почту.');
            isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            setFieldError(loginInput, 'Укажите корректную электронную почту.');
            isValid = false;
        }
    } else if (value === '') {
        setFieldError(loginInput, 'Введите логин.');
        isValid = false;
    } else if (!/^[A-Za-z0-9_.-]{6,50}$/.test(value)) {
        setFieldError(loginInput, 'Логин: 6–50 символов, латиница, цифры, _, . или -.');
        isValid = false;
    }

    if (passwordField) {
        if (passwordField.value === '') {
            setFieldError(passwordField, 'Введите пароль.');
            isValid = false;
        } else if (passwordField.value.length < 8 || passwordField.value.length > 72) {
            setFieldError(passwordField, 'Пароль должен содержать от 8 до 72 символов.');
            isValid = false;
        }
    }

    return isValid;
}

if (loginForm) {
    loginForm.addEventListener('submit', event => {
        if (!validateLoginForm()) {
            event.preventDefault();
        }
    });

    loginForm.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', () => clearFieldError(input));
    });
}
