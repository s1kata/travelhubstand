<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/components/security_helper.php';
session_start();
$page_title = "Регистрация";
$current_page = "registration";

// Получаем данные из параметров запроса (если есть ошибки)
$formData = [];
$errors = [];
if (isset($_GET['data'])) {
    $formData = json_decode(urldecode($_GET['data']), true) ?: [];
    if (isset($formData['errors'])) {
        $errors = $formData['errors'];
    }
}

$name = $formData['name'] ?? '';
$email = $formData['email'] ?? '';
$phone = $formData['phone'] ?? '';
$city = $formData['city'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> - Travel Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/registration-desktop.css?v=2">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="gradient-bg min-h-screen th-page-auth">
    <?php require_once __DIR__ . '/../../backend/components/header.php'; ?>

    <!-- Main Content -->
    <main class="login-page-main th-container mx-auto px-4">
        <div class="max-w-md mx-auto">
            <!-- Logo & Title -->
            <div class="text-center mb-10">
                <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-r from-sky-400 to-sky-600 flex items-center justify-center shadow-xl mb-6">
                    <i class="fas fa-user-plus text-2xl text-white"></i>
                </div>
                <h1 class="text-3xl font-bold text-sky-800 mb-3">Создать аккаунт</h1>
                <p class="text-slate-600">Присоединяйтесь к Travel Hub</p>
            </div>

            <!-- Registration Form -->
            <div class="bg-white rounded-2xl card-shadow p-8">
                <?php
                // Показываем общие ошибки
                if (isset($errors['database'])) {
                    echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">';
                    echo '<i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($errors['database']);
                    echo '</div>';
                }
                if (isset($errors['duplicate'])) {
                    echo '<div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl text-yellow-700 text-sm">';
                    echo '<i class="fas fa-exclamation-triangle mr-2"></i>Пользователь с такими данными уже существует.';
                    echo '</div>';
                }
                ?>

                <form id="registrationForm" method="POST" action="/backend/scripts/process_registration.php">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="submit" value="1">

                    <!-- Name -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-user text-sky-500 mr-2"></i>ФИО <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               required
                               minlength="2"
                               maxlength="60"
                               pattern="[А-Яа-яЁё\s\-]+"
                               title="Только русские буквы, пробелы и дефис"
                               value="<?php echo htmlspecialchars($name); ?>"
                               class="w-full px-4 py-3 rounded-xl border <?php echo isset($errors['name']) ? 'border-red-300' : 'border-slate-200'; ?> bg-slate-50 text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-200 transition"
                               placeholder="Иванов Иван Иванович">
                        <?php if (isset($errors['name'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['name']); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Email -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-envelope text-sky-500 mr-2"></i>Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email"
                               id="email"
                               name="email"
                               required
                               value="<?php echo htmlspecialchars($email); ?>"
                               class="w-full px-4 py-3 rounded-xl border <?php echo isset($errors['email']) ? 'border-red-300' : 'border-slate-200'; ?> bg-slate-50 text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-200 transition"
                               placeholder="ваш@email.com">
                        <?php if (isset($errors['email'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['email']); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Password -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-lock text-sky-500 mr-2"></i>Пароль <span class="text-red-500">*</span>
                        </label>
                        <div class="login-password-wrap <?php echo isset($errors['password']) ? 'is-error' : ''; ?>">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   required
                                   minlength="8"
                                   class="px-4 py-3.5 text-slate-700 placeholder-slate-400"
                                   placeholder="Минимум 8 символов"
                                   autocomplete="new-password">
                            <button type="button"
                                    onclick="togglePassword('password')"
                                    class="login-password-toggle th-password-toggle"
                                    aria-label="Показать или скрыть пароль">
                                <i class="fas fa-eye" id="passwordEye"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['password']); ?></p>
                        <?php else: ?>
                            <p class="mt-1 text-xs text-slate-500">Пароль должен содержать не менее 8 символов</p>
                        <?php endif; ?>
                    </div>

                    <!-- Phone (optional) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-phone text-sky-500 mr-2"></i>Телефон
                        </label>
                        <input type="tel"
                               id="phone"
                               name="phone"
                               value="<?php echo htmlspecialchars($phone); ?>"
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-200 transition"
                               placeholder="+7 (999) 123-45-67">
                        <?php if (isset($errors['phone'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['phone']); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- City (optional) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-map-marker-alt text-sky-500 mr-2"></i>Город
                        </label>
                        <input type="text"
                               id="city"
                               name="city"
                               value="<?php echo htmlspecialchars($city); ?>"
                               class="w-full px-4 py-3 rounded-xl border <?php echo isset($errors['city']) ? 'border-red-300' : 'border-slate-200'; ?> bg-slate-50 text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-200 transition"
                               placeholder="Ваш город">
                        <?php if (isset($errors['city'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['city']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="mb-6">
                        <label class="flex items-start gap-2 text-sm text-slate-600 cursor-pointer">
                            <input type="checkbox"
                                   id="agree"
                                   name="agree"
                                   value="1"
                                   required
                                   class="mt-1 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            <span><?php
                                require_once __DIR__ . '/../../backend/components/legal_consent_label.php';
                                echo th_legal_consent_checkbox_html();
                            ?></span>
                        </label>
                        <?php if (isset($errors['agree'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['agree']); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="animated-button w-full font-semibold text-lg mb-6">
                        <i class="fas fa-user-plus mr-2"></i>Зарегистрироваться
                    </button>

                    <!-- Login Link -->
                    <div class="text-center">
                        <p class="text-slate-600">
                            Уже есть аккаунт?
                            <a href="login-desktop.php" class="text-sky-600 font-semibold hover:text-sky-800">
                                Войти
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Password toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(inputId + 'Eye');

            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // Form validation — ошибки по полям, без «временной ошибки сервера»
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const phoneInput = document.getElementById('phone');
            const agreeInput = document.getElementById('agree');

            const name = (nameInput.value || '').trim();
            const email = (emailInput.value || '').trim();
            const password = passwordInput.value || '';
            const phone = (phoneInput.value || '').trim();

            const fieldErrors = {};

            function clearFieldError(id) {
                const el = document.getElementById(id);
                if (!el) return;
                el.classList.remove('border-red-300');
                el.classList.add('border-slate-200');
                const wrap = el.closest('.mb-6') || el.parentElement;
                if (!wrap) return;
                wrap.querySelectorAll('[data-th-reg-err]').forEach(function (n) { n.remove(); });
            }
            function setFieldError(id, text) {
                const el = document.getElementById(id);
                if (!el) return;
                el.classList.add('border-red-300');
                el.classList.remove('border-slate-200');
                const wrap = el.closest('.mb-6') || el.parentElement;
                if (!wrap) return;
                wrap.querySelectorAll('[data-th-reg-err]').forEach(function (n) { n.remove(); });
                const p = document.createElement('p');
                p.className = 'mt-1 text-sm text-red-600';
                p.setAttribute('data-th-reg-err', '1');
                p.textContent = text;
                wrap.appendChild(p);
            }

            ['name', 'email', 'password', 'phone', 'city', 'agree'].forEach(clearFieldError);
            const pwWrap = document.querySelector('.login-password-wrap');
            if (pwWrap) pwWrap.classList.remove('is-error');

            if (name.length < 2) {
                fieldErrors.name = 'ФИО должно содержать минимум 2 символа.';
            } else if (name.length > 60) {
                fieldErrors.name = 'ФИО не должно превышать 60 символов.';
            } else if (!/^[\p{Script=Cyrillic}\s\-]+$/u.test(name)) {
                fieldErrors.name = /\p{Script=Latin}/u.test(name)
                    ? 'Укажите ФИО русскими буквами.'
                    : 'ФИО может содержать только русские буквы, пробелы и дефисы.';
            } else if ((name.match(/\p{Script=Cyrillic}/gu) || []).length < 2) {
                fieldErrors.name = 'Укажите ФИО русскими буквами.';
            } else {
                const compact = name.replace(/[\s\-]+/g, '');
                const parts = name.split(/[\s\-]+/).filter(Boolean);
                const lower = name.toLowerCase();
                const banned = ['тест', 'test', 'asdf', 'qwerty', 'admin', 'user', 'имя', 'фамилия', 'фио', 'xxx'];
                if (compact.length < 2 || /^(.)\1+$/u.test(compact) || banned.indexOf(lower) >= 0) {
                    fieldErrors.name = 'Укажите корректные ФИО.';
                } else if (parts.some(function (p) { return p.length < 2; })) {
                    fieldErrors.name = 'Укажите корректные ФИО.';
                }
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                fieldErrors.email = 'Пожалуйста, введите корректный email.';
            }

            const weakPasswords = ['qwerty', 'qwerty1', 'qwerty12', 'qwerty123', '123456', '1234567', '12345678', 'password', 'password1', '11111111', '00000000', 'abcdef', 'admin', 'admin123', 'йцукен', 'пароль', 'пароль123', 'travelhub'];
            if (password.length < 8) {
                fieldErrors.password = 'Пароль должен содержать не менее 8 символов.';
            } else if (weakPasswords.indexOf(password.toLowerCase()) >= 0 || /^(.)\1+$/u.test(password)) {
                fieldErrors.password = 'Пароль слишком простой. Придумайте более надёжный.';
            }

            if (phone) {
                let d = phone.replace(/\D/g, '');
                if (d.length === 11 && d[0] === '8') d = '7' + d.slice(1);
                const rest = d.slice(1);
                if (d.length !== 11 || d[0] !== '7' || !rest || rest[0] !== '9' || /^(\d)\1{9}$/.test(rest) || /^9(\d)\1{8}$/.test(rest)) {
                    fieldErrors.phone = 'Укажите корректный мобильный телефон РФ (+7 9XX…).';
                }
            }

            const cityInput = document.getElementById('city');
            const city = cityInput ? String(cityInput.value || '').trim() : '';
            if (city) {
                const cityLower = city.toLowerCase();
                const fakeCities = ['луна', 'марс', 'юпитер', 'солнце', 'земля', 'небо', 'море', 'океан', 'тест', 'test', 'asdf', 'qwerty', 'xxx', 'нигде', 'город', 'венера', 'плутон', 'сатурн', 'нептун', 'меркурий'];
                if (!/^[\p{Script=Cyrillic}\s\-'.]+$/u.test(city) || fakeCities.indexOf(cityLower) >= 0 || city.length < 2) {
                    fieldErrors.city = 'Укажите реальный город.';
                }
            }

            if (!agreeInput || !agreeInput.checked) {
                fieldErrors.agree = 'Нужно согласие на обработку персональных данных.';
            }

            if (Object.keys(fieldErrors).length) {
                e.preventDefault();
                Object.keys(fieldErrors).forEach(function (k) {
                    if (k === 'password' && pwWrap) pwWrap.classList.add('is-error');
                    if (k === 'agree') {
                        const agreeWrap = agreeInput && agreeInput.closest('.mb-6');
                        if (agreeWrap) {
                            agreeWrap.querySelectorAll('[data-th-reg-err]').forEach(function (n) { n.remove(); });
                            const p = document.createElement('p');
                            p.className = 'mt-1 text-sm text-red-600';
                            p.setAttribute('data-th-reg-err', '1');
                            p.textContent = fieldErrors.agree;
                            agreeWrap.appendChild(p);
                        }
                        return;
                    }
                    setFieldError(k, fieldErrors[k]);
                });
                const firstKey = Object.keys(fieldErrors)[0];
                const firstEl = firstKey === 'agree' ? agreeInput : document.getElementById(firstKey);
                if (firstEl && firstEl.focus) firstEl.focus();
                return false;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Регистрация...';
            submitBtn.disabled = true;
        });
    </script>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>