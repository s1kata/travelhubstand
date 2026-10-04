<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/components/security_helper.php';
session_start();
$page_title = "Вход в аккаунт";
$current_page = "login";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> - Travel Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/login-desktop.css?v=2">
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
                    <i class="fas fa-plane text-2xl text-white"></i>
                </div>
                <h1 class="text-3xl font-bold text-sky-800 mb-3">Добро пожаловать</h1>
                <p class="text-slate-600">Войдите в ваш аккаунт Travel Hub</p>
            </div>

            <!-- Login Form -->
            <div class="bg-white rounded-2xl card-shadow p-8">
                <form id="loginForm" method="POST" action="/backend/scripts/process_login.php">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="login" value="1">

                    <!-- Email -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-envelope text-sky-500 mr-2"></i>Email
                        </label>
                        <input type="email"
                               id="email"
                               name="email"
                               required
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-200 transition"
                               placeholder="ваш@email.com">
                    </div>

                    <!-- Password -->
                    <div class="mb-8">
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-slate-700">
                                <i class="fas fa-lock text-sky-500 mr-2"></i>Пароль
                            </label>
                        </div>
                        <div class="login-password-wrap">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   required
                                   class="px-4 py-3.5 text-slate-700 placeholder-slate-400"
                                   placeholder="••••••••"
                                   autocomplete="current-password">
                            <button type="button"
                                    onclick="togglePassword('password')"
                                    class="login-password-toggle th-password-toggle"
                                    aria-label="Показать или скрыть пароль">
                                <i class="fas fa-eye" id="passwordEye"></i>
                            </button>
                        </div>
                        <div class="mt-2 text-right">
                            <a href="forgot-password.php" class="text-sm text-sky-600 hover:text-sky-800">Забыли пароль?</a>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="mb-8">
                        <label class="flex items-center">
                            <input type="checkbox"
                                   id="remember"
                                   name="remember"
                                   class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            <span class="ml-2 text-slate-600 text-sm">Запомнить меня на 30 дней</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="animated-button w-full font-semibold text-lg mb-6">
                        <i class="fas fa-sign-in-alt mr-2"></i>Войти в аккаунт
                    </button>

                    <!-- Register Link -->
                    <div class="text-center">
                        <p class="text-slate-600">
                            Нет аккаунта?
                            <a href="registration-desktop.php" class="text-sky-600 font-semibold hover:text-sky-800">
                                Зарегистрироваться
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

        // Form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Вход...';
            submitBtn.disabled = true;
        });
    </script>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>