<?php
include __DIR__ . '/../../backend/config/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /frontend/window/login.php');
    exit;
}

// Проверяем, заполнены ли паспортные данные
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT passport_series, passport_number FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (empty($user['passport_series']) || empty($user['passport_number'])) {
        header('Location: profile.php');
        exit;
    }
} catch (PDOException $e) {
    // Игнорируем ошибку, продолжаем
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Личный кабинет - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/dashboard.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="bg-gradient-to-b from-sky-50/50 to-white min-h-screen">
    <?php 
    $current_page = 'dashboard';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <section class="py-16 bg-gradient-to-b from-sky-50/50 to-white">
        <div class="th-container mx-auto px-4">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 mb-6 shadow-lg shadow-sky-200/60">
                        <i class="fas fa-home text-white text-3xl"></i>
                    </div>
                    <h1 class="heading-font text-4xl font-bold text-slate-900 mb-4">Личный кабинет</h1>
                    <p class="text-xl text-slate-600">Добро пожаловать в ваш личный кабинет Travel Hub!</p>
                </div>

                <div class="bg-white/90 backdrop-blur-lg rounded-2xl shadow-xl border border-sky-100 p-8">
                    <h2 class="heading-font text-2xl font-bold text-slate-900 mb-6">Информация о пользователе</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div class="bg-gradient-to-br from-sky-50 to-blue-50 p-6 rounded-xl border border-sky-100">
                            <h3 class="heading-font text-lg font-semibold text-slate-800 mb-4 flex items-center">
                                <i class="fas fa-info-circle mr-2 text-sky-500"></i>Основная информация
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-2 border-b border-sky-100">
                                    <span class="text-slate-600">Имя:</span>
                                    <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Не указано'); ?></span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-sky-100">
                                    <span class="text-slate-600">Email:</span>
                                    <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Не указано'); ?></span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-sky-100">
                                    <span class="text-slate-600">Статус:</span>
                                    <span class="font-semibold text-green-600">Авторизован</span>
                                </div>
                                <div class="flex justify-between items-center py-2">
                                    <span class="text-slate-600">Роль:</span>
                                    <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Пользователь'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-sky-50 to-blue-50 p-6 rounded-xl border border-sky-100">
                            <h3 class="heading-font text-lg font-semibold text-slate-800 mb-4 flex items-center">
                                <i class="fas fa-bolt mr-2 text-sky-500"></i>Быстрые действия
                            </h3>
                            <div class="space-y-3">
                                <a href="/frontend/window/profile.php" class="block w-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white text-center py-3 px-4 rounded-xl font-semibold hover:shadow-lg shadow-md transition">
                                    <i class="fas fa-user mr-2"></i>Мой профиль
                                </a>
                                <a href="/frontend/window/contacts.php" class="block w-full bg-gradient-to-r from-green-400 to-green-500 text-white text-center py-3 px-4 rounded-xl font-semibold hover:shadow-lg shadow-md transition">
                                    <i class="fas fa-phone mr-2"></i>Связаться с нами
                                </a>
                                <?php if ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['user_is_manager'])): ?>
                                <a href="/frontend/window/for-operators.php" class="block w-full bg-gradient-to-r from-teal-400 to-teal-500 text-white text-center py-3 px-4 rounded-xl font-semibold hover:shadow-lg shadow-md transition">
                                    <i class="fas fa-briefcase mr-2"></i>Для туроператоров
                                </a>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <a href="/backend/admin/admin.php" class="block w-full bg-gradient-to-r from-rose-300 via-rose-400 to-rose-500 text-white text-center py-3 px-4 rounded-xl font-semibold hover:shadow-lg shadow-md transition">
                                    <i class="fas fa-cog mr-2"></i>Админ-панель
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <a href="/backend/scripts/logout.php" class="inline-block bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-3 rounded-xl font-semibold hover:shadow-lg shadow-md transition mr-4">
                            <i class="fas fa-sign-out-alt mr-2"></i>Выйти из аккаунта
                        </a>
                        <a href="/index.php" class="inline-block bg-gradient-to-r from-slate-400 to-slate-500 text-white px-8 py-3 rounded-xl font-semibold hover:shadow-lg shadow-md transition">
                            <i class="fas fa-home mr-2"></i>Вернуться на главную
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>


</body>
</html>