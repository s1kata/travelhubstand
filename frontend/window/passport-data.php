<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/components/security_helper.php';
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
    echo "<p style='color: red;'>Ошибка загрузки данных: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>";
    exit;
}

// Обработка обновления паспортных данных
$success_message = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_passport'])) {
    if (!security_csrf_verify()) {
        $error_message = 'Сессия истекла. Обновите страницу.';
    } else {
    $passport_series = trim($_POST['passport_series']);
    $passport_number = trim($_POST['passport_number']);
    $passport_issued_by = trim($_POST['passport_issued_by']);
    $passport_issue_date = trim($_POST['passport_issue_date']);
    $passport_expiry_date = trim($_POST['passport_expiry_date']);

    $errors = [];
    if (empty($passport_series)) $errors[] = 'Введите серию паспорта';
    if (empty($passport_number)) $errors[] = 'Введите номер паспорта';
    if (empty($passport_issued_by)) $errors[] = 'Введите кем выдан паспорт';
    if (empty($passport_issue_date)) $errors[] = 'Введите дату выдачи';
    if (empty($passport_expiry_date)) $errors[] = 'Введите дату окончания';
    
    // Проверка дат
    if (!empty($passport_issue_date) && !empty($passport_expiry_date)) {
        if (strtotime($passport_expiry_date) <= strtotime($passport_issue_date)) {
            $errors[] = 'Дата окончания должна быть позже даты выдачи';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET passport_series = ?, passport_number = ?, passport_issued_by = ?, passport_issue_date = ?, passport_expiry_date = ? WHERE id = ?");
            $stmt->execute([$passport_series, $passport_number, $passport_issued_by, $passport_issue_date, $passport_expiry_date, $user_id]);
            
            // Перезагрузка данных пользователя
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            header('Location: /frontend/window/profile.php?success=' . urlencode('Паспортные данные успешно обновлены'));
            exit;
        } catch (PDOException $e) {
            $error_message = "Ошибка обновления: " . $e->getMessage();
        }
    } else {
        $error_message = implode(', ', $errors);
    }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Паспортные данные - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/passport-data.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="bg-gradient-to-b from-sky-50/50 to-white min-h-screen">
    <?php 
    $current_page = 'profile';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <!-- Passport Data Content -->
    <section class="py-8 md:py-16 bg-gradient-to-b from-sky-50/50 to-white">
        <div class="th-container mx-auto px-4 md:px-6">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 mb-4 md:mb-6 shadow-lg shadow-sky-200/60">
                        <i class="fas fa-passport text-white text-2xl md:text-3xl"></i>
                    </div>
                    <h1 class="heading-font text-3xl md:text-4xl font-bold text-slate-900 mb-2 md:mb-3">Паспортные данные</h1>
                    <p class="text-slate-600 text-base md:text-lg">Введите данные вашего паспорта для бронирования туров</p>
                </div>


                <?php if(isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white/90 backdrop-blur-lg rounded-2xl shadow-xl border border-sky-100 p-6 md:p-8">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
                        <p class="text-sm text-blue-700">
                            <i class="fas fa-info-circle mr-2"></i>
                            Эти данные необходимы для оформления туров и виз. Информация хранится в зашифрованном виде и используется только для бронирования.
                        </p>
                    </div>
                    
                    <form method="POST" class="space-y-4 md:space-y-5">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Серия паспорта *</label>
                                <input type="text" name="passport_series" value="<?php echo htmlspecialchars($user['passport_series'] ?? ''); ?>" 
                                       placeholder="1234" maxlength="4" required
                                       class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base">
                                <p class="text-xs text-slate-500 mt-1">4 цифры</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Номер паспорта *</label>
                                <input type="text" name="passport_number" value="<?php echo htmlspecialchars($user['passport_number'] ?? ''); ?>" 
                                       placeholder="567890" maxlength="6" required
                                       class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base">
                                <p class="text-xs text-slate-500 mt-1">6 цифр</p>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Кем выдан *</label>
                            <input type="text" name="passport_issued_by" value="<?php echo htmlspecialchars($user['passport_issued_by'] ?? ''); ?>" 
                                   placeholder="Отделением УФМС России по г. Москве" required
                                   class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Дата выдачи *</label>
                                <input type="date" name="passport_issue_date" value="<?php echo htmlspecialchars($user['passport_issue_date'] ?? ''); ?>" 
                                       max="<?php echo date('Y-m-d'); ?>" required
                                       class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Дата окончания *</label>
                                <input type="date" name="passport_expiry_date" value="<?php echo htmlspecialchars($user['passport_expiry_date'] ?? ''); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required
                                       class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base">
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-4 pt-4">
                            <button type="submit" name="update_passport" 
                                    class="flex-1 bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg shadow-md shadow-sky-200/60 transition text-base">
                                <i class="fas fa-save mr-2"></i>Сохранить паспортные данные
                            </button>
                            <a href="/frontend/window/profile.php" 
                               class="flex-1 sm:flex-none text-center bg-slate-100 text-slate-700 px-6 py-3 rounded-xl font-semibold hover:bg-slate-200 transition text-base">
                                <i class="fas fa-arrow-left mr-2"></i>Вернуться к профилю
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>

</body>
</html>