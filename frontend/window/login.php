<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/components/security_helper.php';
session_start();

if (!empty($_SESSION['user_id']) && !empty($_GET['redirect'])) {
    $r = trim((string) $_GET['redirect']);
    if ($r !== '' && $r[0] === '/' && strpos($r, '//') === false && stripos($r, '/login.php') === false) {
        header('Location: ' . $r);
        exit;
    }
}

$page_title = "Вход в аккаунт";
$current_page = "login";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Вход - Travel Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/login.css?v=2">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="gradient-bg min-h-screen th-page-auth">
    <?php require_once __DIR__ . '/../../backend/components/header.php'; ?>
    
    <!-- Main Content -->
    <main class="login-page-main th-container mx-auto px-4">
        <div class="max-w-md mx-auto">
            <!-- Logo & Title -->
            <div class="text-center mb-10">
                <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-r from-sky-400 via-sky-500 to-sky-600 flex items-center justify-center shadow-xl shadow-sky-200/50 mb-6">
                    <i class="fas fa-plane text-white text-2xl"></i>
                </div>
                <h1 class="heading-font text-3xl md:text-4xl font-bold text-sky-800 mb-3">Добро пожаловать</h1>
                <p class="text-slate-600">Войдите в ваш аккаунт Travel Hub</p>
            </div>
            
            <!-- Login Form -->
            <div class="bg-white rounded-2xl card-shadow p-8 md:p-10">
                <?php
                // Получаем ошибки из параметров запроса
                $errors = [];
                if (isset($_GET['errors'])) {
                    $errors = json_decode(urldecode($_GET['errors']), true) ?: [];
                }
                
                // Отображаем общие ошибки
                if (isset($errors['login'])) {
                    echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">';
                    echo '<i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($errors['login']);
                    echo '</div>';
                }
                if (isset($errors['database'])) {
                    echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">';
                    echo '<i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($errors['database']);
                    echo '</div>';
                }
                if (isset($errors['account_not_found'])) {
                    echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">';
                    echo '<i class="fas fa-exclamation-circle mr-2"></i>Пользователь с таким email не найден.';
                    echo '</div>';
                }
                ?>
                
                <form id="loginForm" method="POST" action="/backend/scripts/process_login.php">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="login" value="1">
                    <?php if (!empty($_GET['redirect'])): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
                    <?php endif; ?>
                    
                    <!-- Email -->
                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-envelope text-sky-500 mr-2"></i>Email
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>"
                               required
                               class="w-full px-4 py-3.5 rounded-xl border <?php echo isset($errors['email']) ? 'border-red-300' : 'border-slate-200'; ?> bg-slate-50 text-slate-700 input-focus transition placeholder-slate-400"
                               placeholder="ваш@email.com">
                        <?php if (isset($errors['email'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['email']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Password -->
                    <div class="mb-8">
                        <div class="mb-2">
                            <label for="password" class="block text-sm font-medium text-slate-700">
                                <i class="fas fa-lock text-sky-500 mr-2"></i>Пароль
                            </label>
                        </div>
                        <div class="login-password-wrap <?php echo isset($errors['password']) ? 'is-error' : ''; ?>">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   required
                                   class="px-4 py-3.5 text-slate-700 transition placeholder-slate-400"
                                   placeholder="••••••••"
                                   autocomplete="current-password">
                            <button type="button"
                                    onclick="togglePassword('password')"
                                    class="login-password-toggle th-password-toggle"
                                    aria-label="Показать или скрыть пароль">
                                <i class="fas fa-eye" id="passwordEye"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['password']); ?></p>
                        <?php endif; ?>
                        <div class="mt-2 text-right">
                            <a href="forgot-password.php" class="text-sm text-sky-600 hover:text-sky-800">Забыли пароль?</a>
                        </div>
                    </div>
                    
                    <!-- Remember Me -->
                    <div class="mb-8">
                        <label class="flex items-center cursor-pointer">
                            <div class="relative">
                                <input type="checkbox" 
                                       id="remember" 
                                       name="remember"
                                       class="sr-only">
                                <div class="w-5 h-5 rounded border border-slate-300 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-white fill-current hidden" viewBox="0 0 20 20">
                                        <path d="M0 11l2-2 5 5L18 3l2 2L7 18z"/>
                                    </svg>
                                </div>
                            </div>
                            <span class="ml-3 text-slate-600 text-sm">Запомнить меня на 30 дней</span>
                        </label>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full animated-button text-white font-semibold py-4 px-6 rounded-xl text-lg transition duration-300 mb-6">
                        <i class="fas fa-sign-in-alt mr-2"></i>Войти в аккаунт
                    </button>
                    
                    <!-- Register Link -->
                    <div class="text-center">
                        <p class="text-slate-600">
                            Нет аккаунта?
                            <a href="registration-desktop.php"
                               class="text-sky-600 font-semibold hover:text-sky-800 transition ml-1">
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
        
        // Form submission - отправка на сервер
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Вход...';
            submitBtn.disabled = true;
            
            // Форма отправляется на сервер автоматически через method="POST"
            // Обработка происходит на сервере в process_login.php
        });
    </script>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>