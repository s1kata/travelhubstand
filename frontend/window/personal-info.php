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
    <title>Личная информация - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/personal-info.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="bg-gradient-to-b from-sky-50/50 to-white min-h-screen">
    <?php 
    $current_page = 'profile';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <!-- Personal Information Content -->
    <section class="py-8 md:py-16 bg-gradient-to-b from-sky-50/50 to-white">
        <div class="th-container mx-auto px-4 md:px-6">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-8 md:mb-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 mb-4 md:mb-6 shadow-lg shadow-sky-200/60">
                        <i class="fas fa-user text-white text-2xl md:text-3xl"></i>
                    </div>
                    <h1 class="heading-font text-3xl md:text-4xl font-bold text-slate-900 mb-2 md:mb-3">Личная информация</h1>
                    <p class="text-slate-600 text-base md:text-lg px-4">Просмотр и управление вашими персональными данными</p>
                </div>

                <?php if(isset($success_message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Personal Information Section -->
                <div class="mb-8 md:mb-12">
                    <div class="bg-white/90 backdrop-blur-lg rounded-2xl shadow-xl border border-sky-100 p-6 md:p-8 hover:shadow-2xl transition-shadow">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
                            <h2 class="heading-font text-xl md:text-2xl font-bold text-slate-900 flex items-center mb-4 sm:mb-0">
                                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center mr-3 md:mr-4 shadow-md">
                                    <i class="fas fa-user text-white text-sm md:text-base"></i>
                                </div>
                                Мои данные
                            </h2>
                            <a href="/frontend/window/edit-user-data.php" class="inline-flex items-center justify-center bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white px-4 md:px-6 py-2 md:py-3 rounded-xl font-semibold hover:shadow-lg shadow-md shadow-sky-200/60 transition text-sm md:text-base">
                                <i class="fas fa-edit mr-2"></i>Редактировать
                            </a>
                        </div>
                        <div class="space-y-3 md:space-y-4">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-center py-3 border-b border-sky-100 hover:bg-sky-50/50 px-2 rounded-lg transition">
                                <span class="font-medium text-slate-600 flex items-center mb-1 md:mb-0"><i class="fas fa-user-circle mr-2 text-sky-500"></i>Имя:</span>
                                <span class="text-slate-900 font-semibold text-right"><?php echo htmlspecialchars($user['name']); ?></span>
                            </div>
                            <div class="flex flex-col md:flex-row md:justify-between md:items-center py-3 border-b border-sky-100 hover:bg-sky-50/50 px-2 rounded-lg transition">
                                <span class="font-medium text-slate-600 flex items-center mb-1 md:mb-0"><i class="fas fa-envelope mr-2 text-sky-500"></i>Email:</span>
                                <span class="text-slate-900 font-semibold text-right break-all"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <div class="flex flex-col md:flex-row md:justify-between md:items-center py-3 border-b border-sky-100 hover:bg-sky-50/50 px-2 rounded-lg transition">
                                <span class="font-medium text-slate-600 flex items-center mb-1 md:mb-0"><i class="fas fa-phone mr-2 text-sky-500"></i>Телефон:</span>
                                <span class="text-slate-900 font-semibold text-right"><?php echo htmlspecialchars($user['phone'] ?? 'Не указан'); ?></span>
                            </div>
                            <div class="flex flex-col md:flex-row md:justify-between md:items-center py-3 border-b border-sky-100 hover:bg-sky-50/50 px-2 rounded-lg transition">
                                <span class="font-medium text-slate-600 flex items-center mb-1 md:mb-0"><i class="fas fa-map-marker-alt mr-2 text-sky-500"></i>Город:</span>
                                <span class="text-slate-900 font-semibold text-right"><?php echo htmlspecialchars($user['city'] ?? 'Не указан'); ?></span>
                            </div>
                            <div class="flex flex-col md:flex-row md:justify-between md:items-center py-3 border-b border-sky-100 hover:bg-sky-50/50 px-2 rounded-lg transition">
                                <span class="font-medium text-slate-600 flex items-center mb-1 md:mb-0"><i class="fas fa-birthday-cake mr-2 text-sky-500"></i>Возраст:</span>
                                <span class="text-slate-900 font-semibold text-right"><?php echo htmlspecialchars($user['age'] ?? 'Не указан'); ?></span>
                            </div>
                            <div class="flex flex-col md:flex-row md:justify-between md:items-center py-3 hover:bg-sky-50/50 px-2 rounded-lg transition">
                                <span class="font-medium text-slate-600 flex items-center mb-1 md:mb-0"><i class="fas fa-venus-mars mr-2 text-sky-500"></i>Пол:</span>
                                <span class="text-slate-900 font-semibold text-right"><?php echo htmlspecialchars($user['gender'] ?? 'Не указан'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="text-center">
                    <a href="/frontend/window/profile.php" class="inline-flex items-center justify-center bg-slate-100 text-slate-700 px-6 md:px-8 py-3 rounded-xl font-semibold hover:bg-slate-200 transition text-base">
                        <i class="fas fa-arrow-left mr-2"></i>Вернуться к профилю
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>


</body>
</html>