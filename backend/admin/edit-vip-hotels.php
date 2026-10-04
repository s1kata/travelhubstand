<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/security_helper.php';
require_once __DIR__ . '/../components/vip_hotels_schema.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

$message = '';
$messageType = '';

if ($pdo) {
    try {
        vip_hotels_ensure_table($pdo);
    } catch (Throwable $e) {
        error_log('[edit-vip-hotels] ensure_table: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!security_csrf_verify()) {
        $message = 'Недействительный запрос (CSRF). Обновите страницу.';
        $messageType = 'error';
    } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $city = $_POST['city'] ?? '';
        $rating = $_POST['rating'] ?? '5*';
        $description = trim($_POST['description'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $cuisine = trim($_POST['cuisine'] ?? '');
        $mealPlan = trim($_POST['meal_plan'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $beachType = trim($_POST['beach_type'] ?? '');
        $distanceToAirport = trim($_POST['distance_to_airport'] ?? '');
        $checkInTime = trim($_POST['check_in_time'] ?? '');
        $checkOutTime = trim($_POST['check_out_time'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $tourvisorHotelId = (int)($_POST['tourvisor_hotel_id'] ?? 0);
        if ($tourvisorHotelId <= 0) {
            $tourvisorHotelId = null;
        }

        $features = json_decode($_POST['features_json'] ?? '[]', true);
        $features = is_array($features) ? array_filter(array_map('trim', $features)) : [];
        $images = json_decode($_POST['images_json'] ?? '[]', true);
        $images = is_array($images) ? array_filter(array_map('trim', $images)) : [];

        if (empty($name) || empty($slug) || empty($city)) {
            $message = 'Название, slug и город обязательны для заполнения';
            $messageType = 'error';
        } else {
            if ($pdo) {
                try {
                    vip_hotels_ensure_table($pdo);
                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO vip_hotels (name, slug, tourvisor_hotel_id, city, rating, description, bio, cuisine, meal_plan, location, beach_type, distance_to_airport, check_in_time, check_out_time, features, images, display_order, is_active, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $slug, $tourvisorHotelId, $city, $rating, $description, $bio, $cuisine, $mealPlan, $location, $beachType, $distanceToAirport, $checkInTime, $checkOutTime, json_encode($features), json_encode($images), $displayOrder, $isActive, $_SESSION['user_id']]);
                        $message = 'VIP отель успешно добавлен';
                    } else {
                        $id = (int)$_POST['id'];
                        $stmt = $pdo->prepare("UPDATE vip_hotels SET name = ?, slug = ?, tourvisor_hotel_id = ?, city = ?, rating = ?, description = ?, bio = ?, cuisine = ?, meal_plan = ?, location = ?, beach_type = ?, distance_to_airport = ?, check_in_time = ?, check_out_time = ?, features = ?, images = ?, display_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE id = ?");
                        $stmt->execute([$name, $slug, $tourvisorHotelId, $city, $rating, $description, $bio, $cuisine, $mealPlan, $location, $beachType, $distanceToAirport, $checkInTime, $checkOutTime, json_encode($features), json_encode($images), $displayOrder, $isActive, $_SESSION['user_id'], $id]);
                        $message = 'VIP отель успешно обновлен';
                    }
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Ошибка при сохранении отеля: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'Ошибка подключения к базе данных';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM vip_hotels WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'VIP отель успешно удален';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Ошибка при удалении отеля: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    }
}

// Получаем список всех VIP отелей
$hotels = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM vip_hotels ORDER BY display_order, name");
        $hotels = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[edit-vip-hotels] Error loading hotels: ' . $e->getMessage());
    }
}

// Получаем отель для редактирования
$selectedHotel = null;
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    foreach ($hotels as $hotel) {
        if ($hotel['id'] === $id) {
            $selectedHotel = $hotel;
            break;
        }
    }
}

$cities = ['Antalya', 'Belek', 'Kemer'];
$ratings = ['3*', '4*', '5*', '5* Deluxe', '6*'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>VIP Отели Турции | Travel Hub Admin</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --font-sans: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --bg-body: #f4f9ff;
            --bg-surface: #ffffff;
            --bg-muted: #eaf3ff;
            --accent-primary: #3ba3ff;
            --accent-secondary: #7bc4ff;
            --text-primary: #1f2a44;
            --text-secondary: #4f5f78;
            --border-soft: rgba(59, 163, 255, 0.18);
            --shadow-soft: 0 22px 48px rgba(59, 163, 255, 0.18);
        }
        body {
            font-family: var(--font-sans);
            background: linear-gradient(180deg, #f8fbff 0%, #eff5ff 45%, #fdfdff 100%);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }
        .heading-font { font-family: var(--font-sans); font-weight: 600; }
        .metric-card {
            background: var(--bg-surface);
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            box-shadow: var(--shadow-soft);
            padding: 1.25rem;
        }
        .eyebrow-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.32em;
            font-size: 0.65rem;
            background: rgba(59, 163, 255, 0.12);
            border: 1px solid rgba(59, 163, 255, 0.24);
            color: var(--text-primary);
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #3ba3ff;
            box-shadow: 0 0 0 3px rgba(59, 163, 255, 0.1);
        }
        .form-textarea {
            min-height: 100px;
            resize: vertical;
            line-height: 1.5;
        }
        .feature-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            background: #f1f5f9;
            border-radius: 6px;
            font-size: 12px;
            margin: 2px;
        }
        .image-url {
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
            margin: 2px 0;
        }
    </style>
</head>
<body class="bg-transparent text-slate-900 min-h-screen">
    <header class="backdrop-blur-md bg-white/90 border-b border-sky-100 sticky top-0 z-40 shadow-sm">
        <div class="th-container mx-auto px-4 sm:px-6 py-3 sm:py-5">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
                <a href="admin.php" class="flex items-center gap-2 sm:gap-3">
                    <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center shadow-lg flex-shrink-0">
                        <i class="fas fa-arrow-left text-white text-xs sm:text-base"></i>
                    </span>
                    <span class="heading-font text-lg sm:text-2xl font-bold text-sky-600">Назад в админку</span>
                </a>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-xs sm:text-sm text-slate-600 w-full sm:w-auto">
                    <span class="flex items-center gap-1 sm:gap-2"><i class="fas fa-user-shield text-sky-500"></i><span class="hidden sm:inline"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span><span class="sm:hidden"><?php echo htmlspecialchars(mb_substr($_SESSION['user_name'] ?? 'Admin', 0, 10)); ?></span></span>
                    <a href="../scripts/logout.php" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white shadow-md hover:shadow-lg transition text-xs sm:text-sm whitespace-nowrap">Выход</a>
                </div>
            </div>
        </div>
    </header>

    <main class="py-8 sm:py-12 md:py-16">
        <div class="th-container mx-auto px-4 sm:px-6">
            <div class="max-w-7xl mx-auto space-y-8 sm:space-y-12">
                <div class="text-center space-y-3 sm:space-y-4">
                    <span class="eyebrow-badge inline-flex items-center gap-2">
                        <i class="fas fa-hotel"></i>
                        VIP Отели
                    </span>
                    <h1 class="heading-font text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 px-2">Управление VIP отелями Турции</h1>
                    <p class="text-slate-600 max-w-2xl mx-auto text-sm sm:text-base px-2">Добавляйте, редактируйте и управляйте премиум отелями в Анталии, Белеке и Кемере.</p>
                </div>

                <?php if ($message): ?>
                <div class="max-w-6xl mx-auto">
                    <div class="rounded-xl p-4 <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
                        <div class="flex items-center gap-3">
                            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> text-lg"></i>
                            <span><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    <!-- Список отелей -->
                    <div class="xl:col-span-1">
                        <section class="metric-card p-4 sm:p-6 sticky top-24">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="heading-font text-xl font-semibold text-slate-900 flex items-center gap-2">
                                    <i class="fas fa-list text-sky-500 text-base"></i>
                                    <span>VIP Отели</span>
                                </h2>
                                <a href="?action=add" class="bg-gradient-to-r from-green-500 to-green-600 text-white p-2 rounded-lg hover:from-green-600 hover:to-green-700 transition">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>

                            <?php if (!empty($hotels)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($hotels as $hotel): ?>
                                        <div class="p-3 rounded-lg border <?php echo ($selectedHotel && $selectedHotel['id'] === $hotel['id']) ? 'border-sky-300 bg-sky-50' : 'border-sky-100 hover:bg-sky-50'; ?> transition">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-medium text-slate-900 truncate"><?php echo htmlspecialchars($hotel['name']); ?></div>
                                                    <div class="text-xs text-slate-500 flex items-center gap-2 mt-1">
                                                        <span><?php echo htmlspecialchars($hotel['city']); ?></span>
                                                        <span class="bg-yellow-100 text-yellow-800 px-1 rounded"><?php echo htmlspecialchars($hotel['rating']); ?></span>
                                                        <?php if (!$hotel['is_active']): ?>
                                                            <span class="bg-red-100 text-red-800 px-1 rounded">Неактивен</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="flex gap-1 ml-2">
                                                    <a href="?id=<?php echo $hotel['id']; ?>" class="text-sky-500 hover:text-sky-700 p-1">
                                                        <i class="fas fa-edit text-sm"></i>
                                                    </a>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Удалить отель <?php echo htmlspecialchars($hotel['name']); ?>?')">
                                                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $hotel['id']; ?>">
                                                        <button type="submit" class="text-red-500 hover:text-red-700 p-1">
                                                            <i class="fas fa-trash text-sm"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-hotel text-4xl text-slate-300 mb-4"></i>
                                    <p class="text-slate-500 text-sm">Нет VIP отелей для отображения.</p>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <!-- Форма редактирования -->
                    <div class="xl:col-span-2">
                        <section class="metric-card p-4 sm:p-6">
                            <h2 class="heading-font text-xl font-semibold text-slate-900 mb-6 flex items-center gap-2">
                                <i class="fas fa-edit text-sky-500 text-base"></i>
                                <span><?php echo $selectedHotel ? 'Редактировать отель' : 'Добавить новый отель'; ?></span>
                            </h2>

                            <form id="vip-hotel-form" method="POST" class="space-y-6">
                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="<?php echo $selectedHotel ? 'edit' : 'add'; ?>">
                                <?php if ($selectedHotel): ?>
                                    <input type="hidden" name="id" value="<?php echo $selectedHotel['id']; ?>">
                                <?php endif; ?>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Название отеля *</label>
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($selectedHotel['name'] ?? ''); ?>" class="form-input" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Slug *</label>
                                        <input type="text" name="slug" value="<?php echo htmlspecialchars($selectedHotel['slug'] ?? ''); ?>" class="form-input" placeholder="unique-slug" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Tourvisor hotel ID</label>
                                        <input type="number" name="tourvisor_hotel_id" value="<?php echo htmlspecialchars((string) ($selectedHotel['tourvisor_hotel_id'] ?? '')); ?>" class="form-input" placeholder="например 2238 — для точных туров">
                                        <p class="text-xs text-slate-500 mt-1">ID отеля в Tourvisor (hotelIds). Без него фильтр по названию.</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Город *</label>
                                        <select name="city" class="form-select" required>
                                            <option value="">Выберите город</option>
                                            <?php foreach ($cities as $cityOption): ?>
                                                <option value="<?php echo $cityOption; ?>" <?php echo ($selectedHotel && $selectedHotel['city'] === $cityOption) ? 'selected' : ''; ?>>
                                                    <?php echo $cityOption; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Рейтинг</label>
                                        <select name="rating" class="form-select">
                                            <?php foreach ($ratings as $ratingOption): ?>
                                                <option value="<?php echo $ratingOption; ?>" <?php echo ($selectedHotel && $selectedHotel['rating'] === $ratingOption) ? 'selected' : ''; ?>>
                                                    <?php echo $ratingOption; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Порядок отображения</label>
                                        <input type="number" name="display_order" value="<?php echo htmlspecialchars($selectedHotel['display_order'] ?? 0); ?>" class="form-input">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Краткое описание</label>
                                    <textarea name="description" class="form-textarea" placeholder="Краткое описание отеля..."><?php echo htmlspecialchars($selectedHotel['description'] ?? ''); ?></textarea>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Био отеля</label>
                                    <textarea name="bio" class="form-textarea" placeholder="Подробное описание отеля..."><?php echo htmlspecialchars($selectedHotel['bio'] ?? ''); ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Кухня</label>
                                        <input type="text" name="cuisine" value="<?php echo htmlspecialchars($selectedHotel['cuisine'] ?? ''); ?>" class="form-input" placeholder="Типы кухни (европейская, турецкая, etc.)">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Питание</label>
                                        <input type="text" name="meal_plan" value="<?php echo htmlspecialchars($selectedHotel['meal_plan'] ?? ''); ?>" class="form-input" placeholder="AI, UAI, HB, FB, etc.">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Расположение</label>
                                        <input type="text" name="location" value="<?php echo htmlspecialchars($selectedHotel['location'] ?? ''); ?>" class="form-input" placeholder="Адрес или описание расположения">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Тип пляжа</label>
                                        <input type="text" name="beach_type" value="<?php echo htmlspecialchars($selectedHotel['beach_type'] ?? ''); ?>" class="form-input" placeholder="Песчаный, галечный, etc.">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Расстояние до аэропорта</label>
                                        <input type="text" name="distance_to_airport" value="<?php echo htmlspecialchars($selectedHotel['distance_to_airport'] ?? ''); ?>" class="form-input" placeholder="25 км">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Время заезда</label>
                                        <input type="text" name="check_in_time" value="<?php echo htmlspecialchars($selectedHotel['check_in_time'] ?? ''); ?>" class="form-input" placeholder="14:00">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Время выезда</label>
                                        <input type="text" name="check_out_time" value="<?php echo htmlspecialchars($selectedHotel['check_out_time'] ?? ''); ?>" class="form-input" placeholder="12:00">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Особенности (каждая с новой строки)</label>
                                    <textarea name="features_text" id="features_text" class="form-textarea" placeholder="Wi-Fi&#10;Бассейн&#10;SPA-центр&#10;Фитнес-зал"><?php
                                        if ($selectedHotel && $selectedHotel['features']) {
                                            $features = json_decode($selectedHotel['features'], true);
                                            if (is_array($features)) {
                                                echo htmlspecialchars(implode("\n", $features));
                                            }
                                        }
                                    ?></textarea>
                                    <input type="hidden" name="features_json" id="features_hidden" value="[]">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Изображения (URL каждая с новой строки)</label>
                                    <textarea name="images_text" id="images_text" class="form-textarea" placeholder="/img/hotels/hotel1.jpg&#10;/img/hotels/hotel2.jpg"><?php
                                        if ($selectedHotel && $selectedHotel['images']) {
                                            $images = json_decode($selectedHotel['images'], true);
                                            if (is_array($images)) {
                                                echo htmlspecialchars(implode("\n", $images));
                                            }
                                        }
                                    ?></textarea>
                                    <input type="hidden" name="images_json" id="images_hidden" value="[]">
                                </div>

                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="is_active" value="1" <?php echo (!$selectedHotel || $selectedHotel['is_active']) ? 'checked' : ''; ?> class="rounded">
                                        <span class="text-sm text-slate-700">Активен</span>
                                    </label>
                                </div>

                                <div class="flex gap-4">
                                    <button type="submit" class="bg-gradient-to-r from-sky-500 to-sky-600 text-white px-6 py-3 rounded-lg font-medium hover:from-sky-600 hover:to-sky-700 transition flex items-center gap-2">
                                        <i class="fas fa-save"></i>
                                        <?php echo $selectedHotel ? 'Сохранить изменения' : 'Добавить отель'; ?>
                                    </button>
                                    <?php if ($selectedHotel): ?>
                                        <a href="?action=add" class="bg-gray-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-gray-600 transition flex items-center gap-2">
                                            <i class="fas fa-plus"></i>
                                            Новый отель
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </section>
                    </div>
                </div>

                <section class="text-center">
                    <a href="admin.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-gray-600 to-gray-700 text-white px-6 py-3 rounded-lg font-semibold hover:from-gray-700 hover:to-gray-800 transition-all">
                        <i class="fas fa-arrow-left"></i>
                        Вернуться в админку
                    </a>
                </section>
            </div>
        </div>
    </main>

    <script>
        // Преобразование текста в массивы перед отправкой формы (только форма добавления/редактирования)
        (function() {
            var form = document.getElementById('vip-hotel-form');
            var featuresTextEl = document.getElementById('features_text');
            var featuresHiddenEl = document.getElementById('features_hidden');
            var imagesTextEl = document.getElementById('images_text');
            var imagesHiddenEl = document.getElementById('images_hidden');
            if (!form || !featuresTextEl || !featuresHiddenEl || !imagesTextEl || !imagesHiddenEl) return;
            form.addEventListener('submit', function(e) {
                var featuresText = featuresTextEl.value;
                var imagesText = imagesTextEl.value;
                featuresHiddenEl.value = '[]';
                imagesHiddenEl.value = '[]';
                if (featuresText.trim()) {
                    var features = featuresText.split('\n').map(function(f) { return f.trim(); }).filter(function(f) { return f.length > 0; });
                    featuresHiddenEl.value = JSON.stringify(features);
                }
                if (imagesText.trim()) {
                    var images = imagesText.split('\n').map(function(i) { return i.trim(); }).filter(function(i) { return i.length > 0; });
                    imagesHiddenEl.value = JSON.stringify(images);
                }
            });
        })();
    </script>
</body>
</html>