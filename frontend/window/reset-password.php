<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/components/security_helper.php';
session_start();

$page_title = "Сброс пароля";
$current_page = "reset-password";

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;

if (empty($token)) {
    $error = 'Ссылка для восстановления пароля недействительна или устарела.';
} elseif ($pdo) {
    try {
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare('
            SELECT id
            FROM password_reset_tokens
            WHERE token_hash = :token_hash
              AND used_at IS NULL
              AND expires_at > CURRENT_TIMESTAMP
            LIMIT 1
        ');
        $stmt->execute([':token_hash' => $tokenHash]);
        $isValidToken = $stmt->fetch() !== false;
        if (!$isValidToken && !isset($_GET['success'])) {
            $error = 'Ссылка для восстановления недействительна или устарела.';
        }
    } catch (Throwable $e) {
        if (!isset($_GET['success'])) {
            $error = 'Не удалось проверить ссылку восстановления. Попробуйте позже.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Сброс пароля - Travel Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/reset-password.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sky: {
                            50: '#f0f9ff', 100: '#DCEEEC', 200: '#C2E2DF', 300: '#9CCFCB',
                            400: '#79BCB7', 500: '#5DA9A4', 600: '#457F7B', 700: '#366360',
                            800: '#075985', 900: '#0c4a6e',
                        }
                    },
                    fontFamily: { 'sans': ['system-ui', '-apple-system', 'sans-serif'] }
                }
            }
        }
    </script>
    </head>
<body class="gradient-bg min-h-screen">
    <?php require_once __DIR__ . '/../../backend/components/header.php'; ?>

    <main class="th-container mx-auto px-4 py-12 md:py-24">
        <div class="max-w-md mx-auto">
            <div class="text-center mb-10">
                <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-r from-sky-400 via-sky-500 to-sky-600 flex items-center justify-center shadow-xl shadow-sky-200/50 mb-6">
                    <i class="fas fa-lock-open text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-sky-800 mb-3">Новый пароль</h1>
                <p class="text-slate-600">Введите и подтвердите новый пароль</p>
            </div>

            <div class="bg-white rounded-2xl card-shadow p-8 md:p-10">
                <?php
                $formErrors = [];
                if (isset($_GET['errors'])) {
                    $formErrors = json_decode(urldecode($_GET['errors']), true) ?: [];
                }
                if (isset($_GET['success'])) {
                    echo '<div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700">';
                    echo '<i class="fas fa-check-circle mr-2"></i>Пароль успешно изменён. Теперь вы можете войти в аккаунт.';
                    echo '</div>';
                    echo '<div class="text-center"><a href="login-desktop.php" class="text-sky-600 font-semibold hover:text-sky-800">Войти в аккаунт</a></div>';
                } elseif (!empty($error)) {
                    echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">';
                    echo '<i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($error);
                    echo '</div>';
                    echo '<div class="text-center"><a href="forgot-password.php" class="text-sky-600 font-semibold hover:text-sky-800">Запросить новую ссылку</a></div>';
                } else {
                    if (isset($formErrors['password'])) {
                        echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">';
                        echo '<i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($formErrors['password']);
                        echo '</div>';
                    }
                    if (isset($formErrors['token'])) {
                        echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">';
                        echo '<i class="fas fa-exclamation-circle mr-2"></i>' . htmlspecialchars($formErrors['token']);
                        echo '</div>';
                    }
                ?>
                <form id="resetPasswordForm" method="POST" action="/backend/scripts/process_reset_password.php">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="reset_password" value="1">

                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-lock text-sky-500 mr-2"></i>Новый пароль
                        </label>
                        <input type="password" id="password" name="password" required minlength="6"
                               class="w-full px-4 py-3.5 rounded-xl border <?php echo isset($formErrors['password']) ? 'border-red-300' : 'border-slate-200'; ?> bg-slate-50 input-focus transition pr-12"
                               placeholder="Минимум 6 символов">
                    </div>

                    <div class="mb-8">
                        <label for="password_confirm" class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-lock text-sky-500 mr-2"></i>Подтвердите пароль
                        </label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="6"
                               class="w-full px-4 py-3.5 rounded-xl border border-slate-200 bg-slate-50 input-focus transition"
                               placeholder="Повторите пароль">
                    </div>

                    <button type="submit" class="w-full animated-button text-white font-semibold py-4 px-6 rounded-xl text-lg">
                        <i class="fas fa-key mr-2"></i>Сохранить новый пароль
                    </button>
                </form>
                <?php } ?>

                <div class="text-center mt-6">
                    <a href="login-desktop.php" class="text-slate-600 hover:text-sky-600 text-sm">
                        <i class="fas fa-arrow-left mr-1"></i>Вернуться ко входу
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            const p1 = document.getElementById('password').value;
            const p2 = document.getElementById('password_confirm').value;
            if (p1 !== p2) {
                e.preventDefault();
                alert('Пароли не совпадают.');
                return false;
            }
            if (p1.length < 6) {
                e.preventDefault();
                alert('Пароль должен содержать не менее 6 символов.');
                return false;
            }
            this.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Сохранение...';
            this.querySelector('button[type="submit"]').disabled = true;
        });
    </script>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>