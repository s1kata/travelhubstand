<?php
require_once __DIR__ . '/../../backend/config/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /frontend/window/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Получаем данные пользователя
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p style='color: red;'>Ошибка загрузки данных: " . $e->getMessage() . "</p>";
    exit;
}

// Обработка успешных сообщений из других страниц
$success_message = null;
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
    // Перезагружаем данные пользователя после успешного обновления
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Игнорируем ошибку перезагрузки
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Профиль - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/profile.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="bg-gradient-to-b from-sky-50/50 to-white min-h-screen">
    <?php 
    $current_page = 'profile';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <!-- Profile Content -->
    <section class="py-8 md:py-16">
        <div class="th-container mx-auto px-4 md:px-6">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-8 md:mb-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 md:w-24 md:h-24 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 mb-4 md:mb-6 avatar-glow">
                        <i class="fas fa-user text-white text-3xl md:text-4xl"></i>
                    </div>
                    <h1 class="heading-font text-3xl md:text-4xl font-bold bg-gradient-to-r from-sky-600 to-blue-600 bg-clip-text text-transparent mb-2 md:mb-3">Мой профиль</h1>
                    <p class="text-slate-600 text-base md:text-lg px-4">Управляйте своей личной информацией и паспортными данными</p>
                </div>
                
                <!-- Back Button -->
                <div class="mb-6 text-left">
                    <a href="/frontend/window/dashboard.php" class="btn-back inline-flex items-center text-sm md:text-base">
                        <i class="fas fa-arrow-left mr-2"></i>Вернуться в личный кабинет
                    </a>
                </div>

                <?php if(isset($success_message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Personal Information Section -->
                <div class="mb-8 md:mb-12">
                    <div class="profile-card rounded-3xl p-6 md:p-10">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 md:mb-8">
                            <h2 class="heading-font text-xl sm:text-2xl md:text-3xl font-bold bg-gradient-to-r from-sky-600 to-blue-600 bg-clip-text text-transparent flex items-center mb-3 sm:mb-0">
                                <div class="icon-gradient w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 rounded-xl sm:rounded-2xl flex items-center justify-center mr-3 sm:mr-4 transition-all duration-300">
                                    <i class="fas fa-user text-white text-base sm:text-lg md:text-xl"></i>
                                </div>
                                <span class="text-lg sm:text-xl md:text-2xl">Личная информация</span>
                            </h2>
                            <a href="/frontend/window/edit-user-data.php" class="btn-gradient inline-flex items-center justify-center text-white px-4 sm:px-6 md:px-8 py-2.5 sm:py-3 md:py-3.5 rounded-lg sm:rounded-xl font-semibold text-xs sm:text-sm md:text-base w-full sm:w-auto mt-3 sm:mt-0">
                                <i class="fas fa-edit mr-2"></i>Редактировать
                            </a>
                        </div>
                        <div class="space-y-3 sm:space-y-4 md:space-y-5">
                            <div class="info-item flex flex-col sm:flex-row sm:justify-between sm:items-center py-3 sm:py-4 md:py-5 px-3 sm:px-4 md:px-6 rounded-lg sm:rounded-xl">
                                <span class="font-semibold text-slate-700 flex items-center mb-2 sm:mb-0 text-sm sm:text-base">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-gradient-to-br from-sky-100 to-blue-100 flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                        <i class="fas fa-user-circle text-sky-500 text-sm sm:text-base"></i>
                                    </div>
                                    Имя:
                                </span>
                                <span class="text-slate-900 font-bold text-base sm:text-lg md:text-xl text-left sm:text-right bg-gradient-to-r from-sky-600 to-blue-600 bg-clip-text text-transparent mt-1 sm:mt-0"><?php echo htmlspecialchars($user['name']); ?></span>
                            </div>
                            <div class="info-item flex flex-col sm:flex-row sm:justify-between sm:items-center py-3 sm:py-4 md:py-5 px-3 sm:px-4 md:px-6 rounded-lg sm:rounded-xl">
                                <span class="font-semibold text-slate-700 flex items-center mb-2 sm:mb-0 text-sm sm:text-base">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-gradient-to-br from-emerald-100 to-teal-100 flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                        <i class="fas fa-envelope text-emerald-500 text-sm sm:text-base"></i>
                                    </div>
                                    Email:
                                </span>
                                <span class="text-slate-900 font-bold text-sm sm:text-base md:text-lg text-left sm:text-right break-all mt-1 sm:mt-0"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <div class="info-item flex flex-col sm:flex-row sm:justify-between sm:items-center py-3 sm:py-4 md:py-5 px-3 sm:px-4 md:px-6 rounded-lg sm:rounded-xl">
                                <span class="font-semibold text-slate-700 flex items-center mb-2 sm:mb-0 text-sm sm:text-base">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-gradient-to-br from-rose-100 to-pink-100 flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                        <i class="fas fa-phone text-rose-500 text-sm sm:text-base"></i>
                                    </div>
                                    Телефон:
                                </span>
                                <span class="text-slate-900 font-bold text-sm sm:text-base md:text-lg text-left sm:text-right mt-1 sm:mt-0"><?php echo htmlspecialchars($user['phone'] ?? 'Не указан'); ?></span>
                            </div>
                            <div class="info-item flex flex-col sm:flex-row sm:justify-between sm:items-center py-3 sm:py-4 md:py-5 px-3 sm:px-4 md:px-6 rounded-lg sm:rounded-xl">
                                <span class="font-semibold text-slate-700 flex items-center mb-2 sm:mb-0 text-sm sm:text-base">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-gradient-to-br from-amber-100 to-orange-100 flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                        <i class="fas fa-map-marker-alt text-amber-500 text-sm sm:text-base"></i>
                                    </div>
                                    Город:
                                </span>
                                <span class="text-slate-900 font-bold text-sm sm:text-base md:text-lg text-left sm:text-right mt-1 sm:mt-0"><?php echo htmlspecialchars($user['city'] ?? 'Не указан'); ?></span>
                            </div>
                            <div class="info-item flex flex-col sm:flex-row sm:justify-between sm:items-center py-3 sm:py-4 md:py-5 px-3 sm:px-4 md:px-6 rounded-lg sm:rounded-xl">
                                <span class="font-semibold text-slate-700 flex items-center mb-2 sm:mb-0 text-sm sm:text-base">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-gradient-to-br from-purple-100 to-indigo-100 flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                        <i class="fas fa-birthday-cake text-purple-500 text-sm sm:text-base"></i>
                                    </div>
                                    Возраст:
                                </span>
                                <span class="text-slate-900 font-bold text-sm sm:text-base md:text-lg text-left sm:text-right relative z-10 mt-1 sm:mt-0" style="color: #0f172a !important;"><?php echo htmlspecialchars($user['age'] ?? 'Не указан'); ?></span>
                            </div>
                            <div class="info-item flex flex-col sm:flex-row sm:justify-between sm:items-center py-3 sm:py-4 md:py-5 px-3 sm:px-4 md:px-6 rounded-lg sm:rounded-xl">
                                <span class="font-semibold text-slate-700 flex items-center mb-2 sm:mb-0 text-sm sm:text-base">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-gradient-to-br from-pink-100 to-rose-100 flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                        <i class="fas fa-venus-mars text-pink-500 text-sm sm:text-base"></i>
                                    </div>
                                    Пол:
                                </span>
                                <span class="text-slate-900 font-bold text-sm sm:text-base md:text-lg text-left sm:text-right mt-1 sm:mt-0"><?php echo htmlspecialchars($user['gender'] ?? 'Не указан'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Passport Information Section -->
                <div class="mb-8 md:mb-12">
                    <a href="/frontend/window/passport-data.php" class="block passport-card rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-10 transition-all duration-300 cursor-pointer group">
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center flex-1 min-w-0">
                                <div class="passport-icon w-12 h-12 sm:w-16 sm:h-16 md:w-20 md:h-20 rounded-xl sm:rounded-2xl flex items-center justify-center mr-3 sm:mr-4 md:mr-6 transition-all duration-300 group-hover:scale-110 group-hover:rotate-3 flex-shrink-0">
                                    <i class="fas fa-passport text-white text-lg sm:text-2xl md:text-3xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h2 class="heading-font text-lg sm:text-xl md:text-2xl lg:text-3xl font-bold bg-gradient-to-r from-sky-600 to-blue-600 bg-clip-text text-transparent mb-1 sm:mb-2">
                                        Паспортные данные
                                    </h2>
                                    <p class="text-slate-600 text-xs sm:text-sm md:text-base lg:text-lg font-medium">
                                        <?php if (!empty($user['passport_series']) && !empty($user['passport_number'])): ?>
                                            <span class="inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg bg-sky-50 text-sky-700 font-semibold text-xs sm:text-sm">
                                                <i class="fas fa-check-circle mr-1.5 sm:mr-2 text-sky-500"></i>
                                                <span class="truncate">Серия <?php echo htmlspecialchars($user['passport_series']); ?> № <?php echo htmlspecialchars($user['passport_number']); ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg bg-amber-50 text-amber-700 font-medium text-xs sm:text-sm">
                                                <i class="fas fa-exclamation-circle mr-1.5 sm:mr-2 text-amber-500"></i>
                                                <span class="hidden sm:inline">Заполните паспортные данные для бронирования туров</span>
                                                <span class="sm:hidden">Заполните данные</span>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center text-sky-500 group-hover:text-sky-600 transition-transform group-hover:translate-x-2 ml-2 sm:ml-4 flex-shrink-0">
                                <i class="fas fa-chevron-right text-lg sm:text-2xl md:text-3xl"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Logout Section -->
                <div class="mt-8 md:mt-12 text-center">
                    <a href="/backend/scripts/logout.php" class="btn-gradient inline-flex items-center justify-center text-white px-8 md:px-10 py-4 rounded-xl font-semibold text-base">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Выход из аккаунта
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>


</body>
</html>