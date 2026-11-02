const burgerButton = document.querySelector('.burger');
const mobileMenu = document.querySelector('#mobileMenu');
const mobileHeader = document.querySelector('#mobileHeader');
const scrollTopButton = document.querySelector('#scrollTopButton');
const sidebar = document.querySelector('.sidebar');
const contentCard = document.querySelector('.content-card');

let lastScrollY = window.scrollY;

function closeMenu() {
    if (!burgerButton || !mobileMenu || !mobileHeader) return;

    burgerButton.classList.remove('is-open');
    mobileMenu.classList.remove('is-open');
    mobileHeader.classList.remove('is-open');
    burgerButton.setAttribute('aria-expanded', 'false');
}

function toggleHeaderState() {
    if (!mobileHeader) return;
    mobileHeader.classList.toggle('is-scrolled', window.scrollY > 10);
}

function updateScrollTopButtonPosition() {
    if (!scrollTopButton || !sidebar) return;

    if (window.innerWidth <= 767) {
        scrollTopButton.style.removeProperty('--scroll-top-left');
        scrollTopButton.style.removeProperty('--scroll-top-width');
        scrollTopButton.style.removeProperty('--scroll-top-bottom');
        return;
    }

    const sidebarRect = sidebar.getBoundingClientRect();

    scrollTopButton.style.setProperty('--scroll-top-left', `${Math.round(sidebarRect.left)}px`);
    scrollTopButton.style.setProperty('--scroll-top-width', `${Math.round(sidebarRect.width)}px`);

    if (contentCard) {
        const contentRect = contentCard.getBoundingClientRect();
        const baseBottom = 18;
        const safeGap = 0;

        let buttonBottom = baseBottom;

        if (contentRect.bottom < window.innerHeight - baseBottom) {
            buttonBottom = window.innerHeight - contentRect.bottom + safeGap;
        }

        scrollTopButton.style.setProperty('--scroll-top-bottom', `${Math.round(buttonBottom)}px`);
    }
}

function toggleScrollTopButton() {
    if (!scrollTopButton) return;

    const pageHeight = document.documentElement.scrollHeight;
    const screenHeight = window.innerHeight;
    const currentScrollY = window.scrollY;
    const pageCanScroll = pageHeight > screenHeight + 40;
    const pageIsScrolled = currentScrollY > 80;

    if (window.innerWidth <= 767) {
        const userScrollsUp = currentScrollY < lastScrollY;

        scrollTopButton.classList.toggle(
            'is-visible',
            pageCanScroll && pageIsScrolled && userScrollsUp
        );

        lastScrollY = currentScrollY;
        return;
    }

    scrollTopButton.classList.toggle('is-visible', pageCanScroll && pageIsScrolled);
    lastScrollY = currentScrollY;
}

function updatePageState() {
    toggleHeaderState();
    updateScrollTopButtonPosition();
    toggleScrollTopButton();
}

if (burgerButton && mobileMenu && mobileHeader) {
    burgerButton.addEventListener('click', () => {
        const isOpen = burgerButton.classList.toggle('is-open');

        mobileMenu.classList.toggle('is-open', isOpen);
        mobileHeader.classList.toggle('is-open', isOpen);
        burgerButton.setAttribute('aria-expanded', String(isOpen));

        updatePageState();
    });

    mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });

    document.addEventListener('click', event => {
        const target = event.target;
        const isClickInsideMenu = mobileMenu.contains(target);
        const isClickOnBurger = burgerButton.contains(target);

        if (!isClickInsideMenu && !isClickOnBurger) {
            closeMenu();
        }
    });
}

if (scrollTopButton) {
    scrollTopButton.addEventListener('click', () => {
        closeMenu();

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

        scrollTopButton.classList.remove('is-visible');
    });
}

window.addEventListener('scroll', updatePageState);
window.addEventListener('resize', updatePageState);
window.addEventListener('load', updatePageState);

updatePageState();

(function initPresence() {
    const config = window.MATCH_PRESENCE || null;
    if (!config || !config.url || !config.csrf) return;

    let lastState = '';

    function sendPresence(action, force = false) {
        if (!force && lastState === action) return;
        lastState = action;

        const formData = new FormData();
        formData.append('csrf_token', config.csrf);
        formData.append('action', action);

        if (action === 'offline' && navigator.sendBeacon) {
            navigator.sendBeacon(config.url, formData);
            return;
        }

        fetch(config.url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => {});
    }

    sendPresence(document.hidden ? 'offline' : 'online');

    setInterval(() => {
        if (!document.hidden) sendPresence('online', true);
    }, 30000);

    document.addEventListener('visibilitychange', () => {
        sendPresence(document.hidden ? 'offline' : 'online');
    });

    window.addEventListener('pagehide', () => sendPresence('offline'));
    window.addEventListener('beforeunload', () => sendPresence('offline'));
})();
