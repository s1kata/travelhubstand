<?php
require_once __DIR__ . '/../config/config.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

// Создаем таблицу для настроек страницы видео, если её нет
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS video_page_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        button_text TEXT DEFAULT 'Перейти на RuTube',
        page_text TEXT DEFAULT 'Видеообзоры отелей от наших экспертов. Узнайте больше о комфорте, сервисе и атмосфере отелей перед бронированием.',
        rutube_url TEXT DEFAULT '',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Проверяем, есть ли уже настройки
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM video_page_settings");
    $count = $stmt->fetch()['count'];
    if ($count == 0) {
        // Создаем запись по умолчанию
        $pdo->exec("INSERT INTO video_page_settings (button_text, page_text, rutube_url) VALUES ('Перейти на RuTube', 'Видеообзоры отелей от наших экспертов. Узнайте больше о комфорте, сервисе и атмосфере отелей перед бронированием.', '')");
    }
} catch (PDOException $e) {
    error_log('[Video Settings] Error creating table: ' . $e->getMessage());
}

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_page_settings') {
        // Сохранение настроек страницы
        $button_text = $_POST['button_text'] ?? 'Перейти на RuTube';
        $page_text = $_POST['page_text'] ?? 'Видео об отеле';
        $rutube_url = $_POST['rutube_url'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE video_page_settings SET button_text = ?, page_text = ?, rutube_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
            $stmt->execute([$button_text, $page_text, $rutube_url]);
            
            // Если записи нет, создаем её
            if ($stmt->rowCount() == 0) {
                $stmt = $pdo->prepare("INSERT INTO video_page_settings (button_text, page_text, rutube_url) VALUES (?, ?, ?)");
                $stmt->execute([$button_text, $page_text, $rutube_url]);
            }
            
            header('Location: manage-videos.php?success=1&tab=settings');
            exit;
        } catch (PDOException $e) {
            error_log('[Video Settings] Error saving: ' . $e->getMessage());
            header('Location: manage-videos.php?error=1&tab=settings');
            exit;
        }
    } elseif ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $video_url = $_POST['video_url'] ?? '';
        $thumbnail_url = $_POST['thumbnail_url'] ?? '';
        $duration = $_POST['duration'] ?? '';
        $category = $_POST['category'] ?? 'all';
        $is_main = isset($_POST['is_main']) ? 1 : 0;
        $display_days = intval($_POST['display_days'] ?? 30);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Вычисляем дату истечения
        $expires_at = null;
        if ($display_days > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$display_days} days"));
        }
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO videos (title, description, video_url, thumbnail_url, duration, category, is_main, display_days, expires_at, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $description, $video_url, $thumbnail_url, $duration, $category, $is_main, $display_days, $expires_at, $is_active]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE videos 
                SET title = ?, description = ?, video_url = ?, thumbnail_url = ?, duration = ?, category = ?, is_main = ?, display_days = ?, expires_at = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $video_url, $thumbnail_url, $duration, $category, $is_main, $display_days, $expires_at, $is_active, $id]);
        }
        
        header('Location: manage-videos.php?success=1');
        exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: manage-videos.php?success=1');
        exit;
    }
}

// Получаем список видео
$videos = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC");
        $videos = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[Videos] Error: ' . $e->getMessage());
    }
}

// Определяем активную вкладку
$activeTab = $_GET['tab'] ?? 'videos';

// Получаем редактируемое видео
$editVideo = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $editVideo = $stmt->fetch();
    $activeTab = 'videos'; // При редактировании видео всегда показываем вкладку видео
}

// Получаем настройки страницы
$pageSettings = [
    'button_text' => 'Перейти на RuTube',
    'page_text' => 'Видеообзоры отелей от наших экспертов. Узнайте больше о комфорте, сервисе и атмосфере отелей перед бронированием.',
    'rutube_url' => ''
];
try {
    $stmt = $pdo->query("SELECT * FROM video_page_settings LIMIT 1");
    $settings = $stmt->fetch();
    if ($settings) {
        $pageSettings = [
            'button_text' => $settings['button_text'] ?? 'Перейти на RuTube',
            'page_text' => $settings['page_text'] ?? 'Видео об отеле',
            'rutube_url' => $settings['rutube_url'] ?? ''
        ];
    }
} catch (PDOException $e) {
    error_log('[Video Settings] Error fetching: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Управление видео - Админ-панель</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="th-container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold mb-6">Управление видеоинструкциями</h1>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Изменения сохранены успешно!
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    Ошибка при сохранении настроек!
                </div>
            <?php endif; ?>
            
            <!-- Табы -->
            <div class="mb-6 border-b-2 border-gray-200">
                <div class="flex gap-4">
                    <a href="manage-videos.php?tab=videos" class="px-4 py-3 text-base font-medium transition-colors <?php echo $activeTab === 'videos' ? 'border-b-2 border-blue-500 text-blue-600 font-semibold' : 'text-gray-600 hover:text-blue-600'; ?>">
                        <i class="fas fa-video mr-2"></i>Видео
                    </a>
                    <a href="manage-videos.php?tab=settings" class="px-4 py-3 text-base font-medium transition-colors <?php echo $activeTab === 'settings' ? 'border-b-2 border-blue-500 text-blue-600 font-semibold' : 'text-gray-600 hover:text-blue-600'; ?>">
                        <i class="fas fa-cog mr-2"></i>Настройки страницы
                    </a>
                </div>
            </div>
            
            <?php if ($activeTab === 'settings'): ?>
            <!-- Форма настроек страницы -->
            <div class="mb-8 bg-gray-50 p-6 rounded-lg">
                <h2 class="text-xl font-bold mb-4">Настройки страницы видео</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="save_page_settings">
                    
                    <div>
                        <label class="block text-sm font-medium mb-1">Заголовок кнопки RuTube *</label>
                        <input type="text" name="button_text" value="<?php echo htmlspecialchars($pageSettings['button_text']); ?>" required class="w-full px-4 py-2 border rounded">
                        <p class="text-xs text-gray-500 mt-1">Текст кнопки, которая ведет на страницу RuTube</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1">Текст на странице *</label>
                        <textarea name="page_text" rows="4" required class="w-full px-4 py-2 border rounded"><?php echo htmlspecialchars($pageSettings['page_text']); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Основной текст на странице видео (тематика - обзоры отелей)</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1">URL страницы RuTube</label>
                        <input type="url" name="rutube_url" value="<?php echo htmlspecialchars($pageSettings['rutube_url']); ?>" placeholder="https://rutube.ru/..." class="w-full px-4 py-2 border rounded">
                        <p class="text-xs text-gray-500 mt-1">Ссылка на страницу RuTube (если нужно)</p>
                    </div>
                    
                    <div class="flex gap-4">
                        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                            Сохранить настройки
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <!-- Форма добавления/редактирования -->
            <div class="mb-8 bg-gray-50 p-6 rounded-lg">
                <h2 class="text-xl font-bold mb-4"><?php echo $editVideo ? 'Редактировать видео' : 'Добавить новое видео'; ?></h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="<?php echo $editVideo ? 'edit' : 'add'; ?>">
                    <?php if ($editVideo): ?>
                        <input type="hidden" name="id" value="<?php echo $editVideo['id']; ?>">
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1">Название видео *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($editVideo['title'] ?? ''); ?>" required class="w-full px-4 py-2 border rounded">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1">Описание</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded"><?php echo htmlspecialchars($editVideo['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1">URL видео RuTube *</label>
                        <input type="url" name="video_url" value="<?php echo htmlspecialchars($editVideo['video_url'] ?? ''); ?>" required placeholder="https://rutube.ru/video/..." class="w-full px-4 py-2 border rounded">
                        <p class="text-xs text-gray-500 mt-1">Вставьте полный URL видео с RuTube</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1">URL миниатюры</label>
                        <input type="url" name="thumbnail_url" value="<?php echo htmlspecialchars($editVideo['thumbnail_url'] ?? ''); ?>" placeholder="https://..." class="w-full px-4 py-2 border rounded">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Длительность (например: 5:23)</label>
                            <input type="text" name="duration" value="<?php echo htmlspecialchars($editVideo['duration'] ?? ''); ?>" class="w-full px-4 py-2 border rounded">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Категория</label>
                            <select name="category" class="w-full px-4 py-2 border rounded">
                                <option value="all" <?php echo ($editVideo['category'] ?? 'all') === 'all' ? 'selected' : ''; ?>>Все</option>
                                <option value="booking" <?php echo ($editVideo['category'] ?? '') === 'booking' ? 'selected' : ''; ?>>Бронирование</option>
                                <option value="reporting" <?php echo ($editVideo['category'] ?? '') === 'reporting' ? 'selected' : ''; ?>>Отчетность</option>
                                <option value="mobile" <?php echo ($editVideo['category'] ?? '') === 'mobile' ? 'selected' : ''; ?>>Мобильное приложение</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Сколько дней показывать видео *</label>
                            <input type="number" name="display_days" value="<?php echo $editVideo['display_days'] ?? 30; ?>" min="1" required class="w-full px-4 py-2 border rounded">
                            <p class="text-xs text-gray-500 mt-1">После истечения срока видео автоматически скроется</p>
                        </div>
                        
                        <div class="flex items-end space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_main" <?php echo ($editVideo['is_main'] ?? 0) ? 'checked' : ''; ?> class="mr-2">
                                Главное видео
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" <?php echo ($editVideo['is_active'] ?? 1) ? 'checked' : ''; ?> class="mr-2">
                                Активно
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                            <?php echo $editVideo ? 'Сохранить изменения' : 'Добавить видео'; ?>
                        </button>
                        <?php if ($editVideo): ?>
                            <a href="manage-videos.php" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">Отмена</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Список видео -->
            <div>
                <h2 class="text-xl font-bold mb-4">Список видео</h2>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead>
                            <tr class="bg-gray-200">
                                <th class="border p-2">ID</th>
                                <th class="border p-2">Название</th>
                                <th class="border p-2">Категория</th>
                                <th class="border p-2">Дней до истечения</th>
                                <th class="border p-2">Главное</th>
                                <th class="border p-2">Активно</th>
                                <th class="border p-2">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos as $video): 
                                $daysLeft = '∞';
                                if ($video['expires_at']) {
                                    $expires = new DateTime($video['expires_at']);
                                    $now = new DateTime();
                                    $diff = $now->diff($expires);
                                    $daysLeft = $diff->invert ? 'Истекло' : $diff->days;
                                }
                            ?>
                                <tr>
                                    <td class="border p-2"><?php echo $video['id']; ?></td>
                                    <td class="border p-2"><?php echo htmlspecialchars($video['title']); ?></td>
                                    <td class="border p-2"><?php echo htmlspecialchars($video['category']); ?></td>
                                    <td class="border p-2"><?php echo $daysLeft; ?></td>
                                    <td class="border p-2 text-center">
                                        <?php echo $video['is_main'] ? '<i class="fas fa-star text-yellow-500"></i>' : ''; ?>
                                    </td>
                                    <td class="border p-2 text-center">
                                        <?php echo $video['is_active'] ? '<span class="text-green-600">Да</span>' : '<span class="text-red-600">Нет</span>'; ?>
                                    </td>
                                    <td class="border p-2">
                                        <div class="flex gap-2">
                                            <a href="?edit=<?php echo $video['id']; ?>&tab=videos" class="text-blue-600 hover:underline">Редактировать</a>
                                            <form method="POST" class="inline" onsubmit="return confirm('Удалить видео?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $video['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:underline">Удалить</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php endif; ?>
            
            <div class="mt-6">
                <a href="admin.php" class="text-blue-600 hover:underline">← Вернуться в админ-панель</a>
            </div>
        </div>
    </div>
</body>
</html>








