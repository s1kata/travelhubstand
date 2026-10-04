<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/components/security_helper.php';
session_start();
$page_title = "Восстановление пароля";
$current_page = "forgot-password";

// Определяем базовый путь
$base_path = dirname(dirname(dirname(__FILE__))); // Поднимаемся на 3 уровня вверх
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Восстановление пароля - Travel Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/forgot-password.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sky: {
                            50: '#f0f9ff',
                            100: '#DCEEEC',
                            200: '#C2E2DF',
                            300: '#9CCFCB',
                            400: '#79BCB7',
                            500: '#5DA9A4',
                            600: '#457F7B',
                            700: '#366360',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    fontFamily: {
                        'sans': ['system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                        'heading': ['system-ui', '-apple-system', 'sans-serif']
                    }
                }
            }
        }
    </script>
    </head>
<body class="gradient-bg min-h-screen">
    <?php require_once __DIR__ . '/../../backend/components/header.php'; ?>

    <!-- Main Content -->
    <main class="th-container mx-auto px-4 py-12 md:py-24">
        <div class="max-w-md mx-auto">
            <!-- Logo & Title -->
            <div class="text-center mb-10">
                <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-r from-sky-400 via-sky-500 to-sky-600 flex items-center justify-center shadow-xl shadow-sky-200/50 mb-6">
                    <i class="fas fa-key text-white text-2xl"></i>
                </div>
                <h1 class="heading-font text-3xl md:text-4xl font-bold text-sky-800 mb-3">Восстановление пароля</h1>
                <p class="text-slate-600">Введите ваш email для восстановления пароля</p>
            </div>

            <!-- Forgot Password Form -->
            <div class="bg-white rounded-2xl card-shadow p-8 md:p-10">
                <?php
                // Получаем ошибки из параметров запроса
                $errors = [];
                if (isset($_GET['errors'])) {
                    $errors = json_decode(urldecode($_GET['errors']), true) ?: [];
                }

                // Отображаем общие ошибки
                if (isset($errors['email'])) {
                    echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">';
                    echo '<i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($errors['email']);
                    echo '</div>';
                }
                if (isset($errors['general'])) {
                    echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">';
                    echo '<i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($errors['general']);
                    echo '</div>';
                }

                // Success message
                if (isset($_GET['success'])) {
                    echo '<div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700">';
                    if (isset($_GET['dev_link'])) {
                        $devLink = trim((string)($_GET['dev_link'] ?? ''));
                        echo '<i class="fas fa-check-circle mr-2"></i><strong>Локальная разработка:</strong> письмо не отправлено, ссылка ниже.<br>';
                        if ($devLink !== '') {
                            echo '<a href="' . htmlspecialchars($devLink) . '" class="mt-2 inline-block text-sky-600 underline break-all">' . htmlspecialchars($devLink) . '</a>';
                        }
                    } else {
                        echo '<i class="fas fa-check-circle mr-2"></i>Инструкции по восстановлению пароля отправлены на ваш email.';
                    }
                    echo '</div>';
                }
                ?>

                <form id="forgotPasswordForm" method="POST" action="/backend/scripts/process_forgot_password.php">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="forgot_password" value="1">

                    <!-- Email -->
                    <div class="mb-8">
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

                    <!-- Submit Button -->
                    <button type="submit"
                            class="w-full animated-button text-white font-semibold py-4 px-6 rounded-xl text-lg transition duration-300 mb-6">
                        <i class="fas fa-paper-plane mr-2"></i>Отправить инструкции
                    </button>

                    <!-- Back to Login Link -->
                    <div class="text-center">
                        <p class="text-slate-600">
                            Вспомнили пароль?
                            <a href="login-desktop.php"
                               class="text-sky-600 font-semibold hover:text-sky-800 transition ml-1">
                                Войти
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Form submission
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Отправка...';
            submitBtn.disabled = true;
        });
    </script>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>