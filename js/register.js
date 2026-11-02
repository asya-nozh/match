const registerForm = document.getElementById('registerForm');
const displayName = document.getElementById('displayName');
const username = document.getElementById('username');
const email = document.getElementById('email');
const password = document.getElementById('password');
const passwordConfirm = document.getElementById('passwordConfirm');
const birthDateInput = document.getElementById('birthDate');
const privacyInput = document.querySelector('input[name="privacy_accepted"]');

/*
    Старые версии формы могли содержать pattern="[A-Za-z0-9_.-]{6,50}".
    В новых браузерах HTML-pattern проверяется с флагом /v, где дефис в таком
    виде может ломать регулярное выражение. Поэтому клиентская проверка
    выполняется через JS, а HTML-pattern принудительно убирается.
*/
document.querySelectorAll('[pattern]').forEach(input => {
    input.removeAttribute('pattern');
});

function setFieldError(input, message) {
    if (!input) return;

    const group = input.closest('.form-group');
    const field = input.closest('.form-field') || group?.querySelector('.form-field');
    const error = group?.querySelector('[data-error-for="' + (input.id || input.name) + '"]');

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

function getMaxAdultBirthDate() {
    const today = new Date();
    const adultDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    const year = adultDate.getFullYear();
    const month = String(adultDate.getMonth() + 1).padStart(2, '0');
    const day = String(adultDate.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

if (birthDateInput) {
    birthDateInput.max = birthDateInput.max || getMaxAdultBirthDate();
}

function openBirthDatePicker() {
    if (!birthDateInput) return;

    birthDateInput.focus();

    if (typeof birthDateInput.showPicker === 'function') {
        try {
            birthDateInput.showPicker();
        } catch (error) {
            // Некоторые браузеры разрешают showPicker() только при прямом действии пользователя.
            // Если меню не открылось, поле всё равно остаётся доступным для ручного ввода.
        }
    }
}

const birthDateField = birthDateInput?.closest('.form-field--date');

if (birthDateField) {
    birthDateField.addEventListener('click', event => {
        if (event.target === birthDateInput) return;
        openBirthDatePicker();
    });

    birthDateInput.addEventListener('click', openBirthDatePicker);
}

function validateDisplayName() {
    if (!displayName) return true;

    const value = displayName.value.trim();
    displayName.value = value;
    clearFieldError(displayName);

    if (value === '') {
        setFieldError(displayName, 'Введите имя.');
        return false;
    }

    if (value.length < 2 || value.length > 80) {
        setFieldError(displayName, 'Имя должно содержать от 2 до 80 символов.');
        return false;
    }

    if (!/^[\p{L}\s\-']+$/u.test(value)) {
        setFieldError(displayName, 'Имя может содержать буквы, пробелы и дефис.');
        return false;
    }

    return true;
}

function validateUsername() {
    if (!username) return true;

    const value = username.value.trim();
    username.value = value;
    clearFieldError(username);

    if (value === '') {
        setFieldError(username, 'Введите логин.');
        return false;
    }

    if (!/^[A-Za-z0-9_.\-]{6,50}$/.test(value)) {
        setFieldError(username, 'Логин: 6–50 символов, латиница, цифры, _, . или -.');
        return false;
    }

    return true;
}

function validateEmail() {
    if (!email) return true;

    const value = email.value.trim().toLowerCase();
    email.value = value;
    clearFieldError(email);

    if (value === '') {
        setFieldError(email, 'Введите электронную почту.');
        return false;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        setFieldError(email, 'Укажите корректную электронную почту.');
        return false;
    }

    if (value.length > 255) {
        setFieldError(email, 'Адрес электронной почты слишком длинный.');
        return false;
    }

    return true;
}

function validateBirthDate() {
    if (!birthDateInput) return true;

    const maxDate = birthDateInput.max || getMaxAdultBirthDate();
    clearFieldError(birthDateInput);

    if (birthDateInput.value === '') {
        setFieldError(birthDateInput, 'Укажите дату рождения.');
        return false;
    }

    if (birthDateInput.value > maxDate) {
        setFieldError(birthDateInput, 'Регистрация доступна пользователям от 18 лет.');
        return false;
    }

    return true;
}

function validatePassword() {
    if (!password) return true;

    clearFieldError(password);

    if (password.value === '') {
        setFieldError(password, 'Введите пароль.');
        return false;
    }

    if (password.value.length < 8) {
        setFieldError(password, 'Пароль должен содержать не менее 8 символов.');
        return false;
    }

    if (password.value.length > 72) {
        setFieldError(password, 'Пароль должен содержать не более 72 символов.');
        return false;
    }

    return true;
}

function validatePasswordConfirm() {
    if (!password || !passwordConfirm) return true;

    clearFieldError(passwordConfirm);

    if (passwordConfirm.value === '') {
        setFieldError(passwordConfirm, 'Повторите пароль.');
        return false;
    }

    if (password.value !== passwordConfirm.value) {
        setFieldError(passwordConfirm, 'Пароли не совпадают.');
        return false;
    }

    return true;
}

function validatePrivacy() {
    if (!privacyInput) return true;

    clearFieldError(privacyInput);

    if (!privacyInput.checked) {
        setFieldError(privacyInput, 'Необходимо принять правила и политику конфиденциальности.');
        return false;
    }

    return true;
}

function validateRegisterForm() {
    const checks = [
        validateDisplayName(),
        validateUsername(),
        validateEmail(),
        validateBirthDate(),
        validatePassword(),
        validatePasswordConfirm(),
        validatePrivacy()
    ];

    return checks.every(Boolean);
}

if (registerForm) {
    registerForm.addEventListener('submit', event => {
        if (!validateRegisterForm()) {
            event.preventDefault();
        }
    });

    displayName?.addEventListener('input', validateDisplayName);
    username?.addEventListener('input', validateUsername);
    email?.addEventListener('input', validateEmail);
    birthDateInput?.addEventListener('input', validateBirthDate);
    birthDateInput?.addEventListener('change', validateBirthDate);
    password?.addEventListener('input', () => {
        validatePassword();
        if (passwordConfirm?.value) validatePasswordConfirm();
    });
    passwordConfirm?.addEventListener('input', validatePasswordConfirm);
    privacyInput?.addEventListener('change', validatePrivacy);
}
