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
    
    // Проверяем, что данные загружены
    if (!$user) {
        $error_message = "Пользователь не найден";
        $user = []; // Инициализируем пустым массивом, чтобы избежать ошибок
    }
} catch (PDOException $e) {
    $error_message = "Ошибка загрузки данных: " . $e->getMessage();
    $user = []; // Инициализируем пустым массивом
}

// Обработка обновления данных пользователя
$success_message = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_data'])) {
    if (!security_csrf_verify()) {
        $error_message = 'Сессия истекла. Обновите страницу.';
    } else {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $age = trim($_POST['age']);
    $gender = trim($_POST['gender']);

    $errors = [];
    if (empty($name)) $errors[] = 'Введите имя';
    if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
        $errors[] = 'Неверный формат телефона';
    }
    if (!empty($age) && (!is_numeric($age) || $age < 1 || $age > 150)) {
        $errors[] = 'Неверный возраст';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, city = ?, age = ?, gender = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $city, $age, $gender, $user_id]);
            
            // Обновляем данные в сессии
            $_SESSION['user_name'] = $name;
            
            // Перезагрузка данных пользователя
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            header('Location: /frontend/window/profile.php?success=' . urlencode('Данные успешно обновлены'));
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
    <title>Редактирование данных - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/edit-user-data.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="bg-gradient-to-b from-sky-50/50 to-white min-h-screen">
    <?php 
    $current_page = 'profile';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <!-- Edit User Data Content -->
    <section class="py-8 md:py-16 bg-gradient-to-b from-sky-50/50 to-white">
        <div class="th-container mx-auto px-4 md:px-6">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 mb-4 md:mb-6 shadow-lg shadow-sky-200/60">
                        <i class="fas fa-user-edit text-white text-2xl md:text-3xl"></i>
                    </div>
                    <h1 class="heading-font text-3xl md:text-4xl font-bold text-slate-900 mb-2 md:mb-3">Редактирование данных</h1>
                    <p class="text-slate-600 text-base md:text-lg">Обновите свою личную информацию</p>
                </div>


                <?php if(isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white/90 backdrop-blur-lg rounded-2xl shadow-xl border border-sky-100 p-6 md:p-8">
                    <form method="POST" class="space-y-4 md:space-y-5">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Имя *</label>
                            <input type="text" name="name" value="<?php echo isset($user['name']) ? htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required
                                   class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base text-slate-900">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                            <input type="email" value="<?php echo isset($user['email']) ? htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') : ''; ?>" disabled
                                   class="w-full px-4 py-3 border border-sky-200 rounded-xl bg-slate-100 text-slate-600 text-base cursor-not-allowed">
                            <p class="text-xs text-slate-500 mt-1">Email нельзя изменить</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Телефон</label>
                            <input type="tel" name="phone" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                                   placeholder="+7 (___) ___-__-__"
                                   class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base text-slate-900">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Город</label>
                            <input type="text" name="city" value="<?php echo isset($user['city']) ? htmlspecialchars($user['city'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                                   placeholder="Москва"
                                   class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base text-slate-900">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Возраст</label>
                                <input type="number" name="age" value="<?php echo isset($user['age']) && $user['age'] !== null && $user['age'] !== '' ? (int)$user['age'] : ''; ?>"
                                       min="1" max="150" placeholder="25"
                                       class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base text-slate-900">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Пол</label>
                                <select name="gender" class="w-full px-4 py-3 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-300 focus:border-sky-400 transition bg-white text-base text-slate-900">
                                    <option value="">Не указан</option>
                                    <option value="Мужской" <?php echo (isset($user['gender']) && $user['gender'] === 'Мужской') ? 'selected' : ''; ?>>Мужской</option>
                                    <option value="Женский" <?php echo (isset($user['gender']) && $user['gender'] === 'Женский') ? 'selected' : ''; ?>>Женский</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-4 pt-4">
                            <button type="submit" name="update_user_data" 
                                    class="flex-1 bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg shadow-md shadow-sky-200/60 transition text-base">
                                <i class="fas fa-save mr-2"></i>Сохранить изменения
                            </button>
                            <a href="/frontend/window/profile.php" 
                               class="flex-1 sm:flex-none text-center bg-slate-100 text-slate-700 px-6 py-3 rounded-xl font-semibold hover:bg-slate-200 transition text-base">
                                <i class="fas fa-arrow-left mr-2"></i>Вернуться
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