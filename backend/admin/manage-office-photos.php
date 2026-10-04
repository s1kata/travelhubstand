<?php
/**
 * Управление фотографиями офисов
 * Загрузка фотографий в папки офисов
 */

// Подключаем конфигурационный файл
$configPath = realpath(__DIR__ . '/../config/config.php');
if (!$configPath || !file_exists($configPath)) {
    die('Configuration file not found: ' . __DIR__ . '/../config/config.php');
}
require_once $configPath;
require_once __DIR__ . '/../components/office_admin_bootstrap.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

if (!$pdo) {
    die('<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Нет БД</title></head><body style="font-family:sans-serif;padding:2rem;">'
        . '<p>Нет подключения к базе данных. Проверьте <code>.env</code>: <code>DB_DRIVER</code>, параметры MySQL или <code>SQLITE_PATH</code>.</p>'
        . '</body></html>');
}

th_office_admin_bootstrap($pdo);

$offices = [];
try {
    $stmt = $pdo->query('SELECT id, city, name, address FROM offices ORDER BY city, name');
    $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[Manage Office Photos] Error loading offices: ' . $e->getMessage());
}

$message = '';
$messageType = '';

// Обработка удаления фото
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_photo' && isset($_POST['photo_path'])) {
        $photoPath = (string) ($_POST['photo_path'] ?? '');
        $fullPath = th_office_admin_resolve_disk_file($photoPath);

        if ($fullPath !== null) {
            if (unlink($fullPath)) {
                $message = 'Фото успешно удалено';
                $messageType = 'success';
                error_log('Deleted photo: ' . $fullPath);
            } else {
                $message = 'Ошибка при удалении фото';
                $messageType = 'error';
            }
        } else {
            $message = 'Фото не найдено';
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'delete_office_photos' && isset($_POST['office_id'])) {
        $officeId = intval($_POST['office_id']);

        try {
            $stmt = $pdo->prepare("SELECT city, name FROM offices WHERE id = ?");
            $stmt->execute([$officeId]);
            $office = $stmt->fetch();

            if ($office) {
                $deletedCount = th_office_admin_delete_office_disk_photos((string) $office['city'], (string) $office['name']);
                if ($deletedCount > 0) {
                    $message = "Удалено файлов с диска: {$deletedCount}";
                    $messageType = 'success';
                    error_log("Deleted {$deletedCount} photos from office: {$office['name']}");
                } else {
                    $message = 'Нет файлов для удаления (папки пусты или отсутствуют)';
                    $messageType = 'error';
                }
            } else {
                $message = 'Офис не найден';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Ошибка базы данных: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Обработка добавления фото в галерею офиса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_photos_to_gallery') {
    $officeId = intval($_POST['office_id'] ?? 0);
    $selectedPhotos = $_POST['selected_photos'] ?? [];

    if ($officeId > 0 && !empty($selectedPhotos)) {
        $addedCount = 0;
        $errors = 0;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO office_photos (office_id, image_url, title)
                VALUES (?, ?, ?)
            ");

            foreach ($selectedPhotos as $photoUrl) {
                $title = pathinfo($photoUrl, PATHINFO_FILENAME);
                try {
                    $stmt->execute([$officeId, $photoUrl, $title]);
                    $addedCount++;
                } catch (PDOException $e) {
                    $errors++;
                    error_log('[Add Photos to Gallery] Error adding photo: ' . $e->getMessage());
                }
            }

            if ($addedCount > 0) {
                $message = "Успешно добавлено фото в галерею: {$addedCount}";
                if ($errors > 0) {
                    $message .= " (ошибок: {$errors})";
                }
                $message .= '! Страница обновится через 2 секунды.';
                $messageType = 'success';
                echo '<meta http-equiv="refresh" content="2">';
            } else {
                $message = 'Не удалось добавить ни одного фото в галерею';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Ошибка при работе с базой данных: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = 'Выберите офис и фотографии для добавления';
        $messageType = 'error';
    }
}

// Обработка загрузки фото офисов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_office_photo') {
    error_log('Upload photo request received');
    $officeId = intval($_POST['office_id'] ?? 0);
    error_log('Office ID: ' . $officeId);

    if ($officeId > 0 && isset($_FILES['photo'])) {
        // Получаем информацию об офисе
        try {
            $stmt = $pdo->prepare("SELECT city, name FROM offices WHERE id = ?");
            $stmt->execute([$officeId]);
            $office = $stmt->fetch();

            if ($office) {
                $uploadDir = th_office_admin_office_photos_upload_dir((string) $office['city'], (string) $office['name']);
                if (!is_dir($uploadDir)) {
                    $message = 'Не удалось создать папку для фото офиса: ' . $uploadDir;
                    $messageType = 'error';
                } else {
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $uploadedCount = 0;
                    $errors = 0;

                    // Обрабатываем массив файлов
                    if (is_array($_FILES['photo']['name'])) {
                        $fileCount = count($_FILES['photo']['name']);

                        for ($i = 0; $i < $fileCount; $i++) {
                            if ($_FILES['photo']['error'][$i] === UPLOAD_ERR_OK) {
                                $fileName = basename($_FILES['photo']['name'][$i]);
                                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                                if (in_array($fileExt, $allowedExts)) {
                                    $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\-_.]/', '', $fileName);
                                    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;

                                    if (move_uploaded_file($_FILES['photo']['tmp_name'][$i], $targetPath)) {
                                        $uploadedCount++;
                                    } else {
                                        $errors++;
                                    }
                                } else {
                                    $errors++;
                                }
                            } else {
                                $errors++;
                            }
                        }
                    } else {
                        if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                            $fileName = basename($_FILES['photo']['name']);
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                            if (in_array($fileExt, $allowedExts)) {
                                $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\-_.]/', '', $fileName);
                                $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;

                                if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                                    $uploadedCount++;
                                } else {
                                    $errors++;
                                }
                            } else {
                                $errors++;
                            }
                        } else {
                            $errors++;
                        }
                    }

                    if ($uploadedCount > 0) {
                        $message = "Успешно загружено фото: {$uploadedCount}";
                        if ($errors > 0) {
                            $message .= " (ошибок: {$errors})";
                        }
                        $message .= '! Страница обновится через 2 секунды.';
                        $messageType = 'success';
                        echo '<meta http-equiv="refresh" content="2">';
                    } else {
                        $message = 'Не удалось загрузить ни одного фото. Проверьте формат файлов и права доступа.';
                        $messageType = 'error';
                    }
                }
            } else {
                $message = 'Не удалось найти выбранный офис';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Ошибка при работе с базой данных: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = 'Выберите офис и файлы для загрузки';
        $messageType = 'error';
    }
}

$availablePhotos = th_office_admin_scan_office_photos_on_disk();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Управление фотографиями офисов - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --font-sans: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --bg-body: #f4f9ff;
            --bg-surface: #ffffff;
            --accent-primary: #3ba3ff;
            --text-primary: #1f2a44;
        }
        body {
            font-family: var(--font-sans);
            background: linear-gradient(180deg, #f8fbff 0%, #eff5ff 45%, #fdfdff 100%);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
        }
        .heading-font { font-family: var(--font-sans); font-weight: 600; }
        .surface-card {
            background: var(--bg-surface);
            border-radius: 20px;
            border: 1px solid rgba(59, 163, 255, 0.18);
            box-shadow: 0 22px 48px rgba(59, 163, 255, 0.18);
        }
    </style>
</head>
<body class="min-h-screen">
    <header class="backdrop-blur-md bg-white/90 border-b border-sky-100 sticky top-0 z-40 shadow-sm">
        <div class="th-container mx-auto px-4 sm:px-6 py-3 sm:py-5">
            <div class="flex items-center justify-between">
                <a href="admin.php" class="flex items-center gap-2 sm:gap-3">
                    <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center shadow-lg">
                        <i class="fas fa-plane text-white text-xs sm:text-base"></i>
                    </span>
                    <span class="heading-font text-lg sm:text-2xl font-bold text-sky-600">Управление фотографиями офисов</span>
                </a>
                <a href="admin.php" class="px-4 py-2 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white shadow-md hover:shadow-lg transition">Назад</a>
            </div>
        </div>
    </header>

    <main class="py-8 sm:py-12">
        <div class="th-container mx-auto px-4 sm:px-6">
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($offices)): ?>
                <div class="mb-6 p-4 rounded-xl bg-amber-50 text-amber-900 border border-amber-200">
                    <p class="font-semibold mb-1">В базе нет офисов для выбора</p>
                    <p class="text-sm">Таблицы созданы автоматически; если список пуст — проверьте подключение к БД и права пользователя MySQL/SQLite.</p>
                </div>
            <?php endif; ?>

            <!-- Загрузка фото офисов -->
            <div class="surface-card p-6 mb-6">
                <h2 class="heading-font text-2xl font-bold text-slate-900 mb-4">Загрузить фото офисов</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_office_photo">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Офис *</label>
                            <select name="office_id" required class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500" <?php echo empty($offices) ? 'disabled' : ''; ?>>
                                <option value="">Выберите офис</option>
                                <?php foreach ($offices as $office): ?>
                                    <option value="<?php echo (int) $office['id']; ?>"><?php echo htmlspecialchars($office['city'] . ' — ' . $office['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Фото *</label>
                            <div id="office-drop-zone" class="relative border-2 border-dashed border-sky-300 rounded-xl p-6 text-center hover:border-sky-400 transition-colors cursor-pointer bg-sky-50/50">
                                <input type="file" name="photo[]" accept="image/*" multiple required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" id="office-photo-input">
                                <div class="text-sky-600">
                                    <i class="fas fa-cloud-upload-alt text-3xl mb-2"></i>
                                    <p class="font-medium">Перетащите фото сюда или нажмите для выбора</p>
                                    <p class="text-sm text-sky-500 mt-1">Можно выбрать несколько файлов одновременно</p>
                                </div>
                            </div>
                            <div id="office-file-list" class="mt-2 space-y-1"></div>
                        </div>
                    </div>

                    <button type="submit" id="upload-btn" class="w-full px-6 py-3 bg-gradient-to-r from-purple-300 via-purple-400 to-purple-500 text-white rounded-xl font-semibold shadow-md hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-upload mr-2"></i>
                        <span id="upload-text">Загрузить фото</span>
                        <div id="upload-spinner" class="hidden inline-block ml-2 w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    </button>
                </form>

                <div class="mt-4 text-sm text-slate-600">
                    <p><strong>Поддерживаемые форматы:</strong> JPG, PNG, GIF, WebP</p>
                    <p><strong>Рекомендуемый размер:</strong> Фото офисов для галереи</p>
                    <p><strong>Множественная загрузка:</strong> Можно выбрать и загрузить несколько фото одновременно</p>
                </div>
            </div>

            <!-- Выбор загруженных фотографий для галереи офиса -->
            <div class="surface-card p-6 mb-6">
                <h2 class="heading-font text-2xl font-bold text-slate-900 mb-4">Выбрать загруженные фотографии для галереи офиса</h2>

                <?php if (empty($availablePhotos)): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
                        <p class="text-yellow-800">Нет загруженных фотографий. Используйте форму выше для загрузки фото офисов.</p>
                    </div>
                <?php else: ?>
                    <!-- Выбор офиса -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Выберите офис для добавления фото *</label>
                        <select id="selectedOffice" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500" <?php echo empty($offices) ? 'disabled' : ''; ?>>
                            <option value="">Выберите офис</option>
                            <?php foreach ($offices as $office): ?>
                                <option value="<?php echo (int) $office['id']; ?>"><?php echo htmlspecialchars($office['city'] . ' — ' . $office['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <p class="text-sm text-slate-600">Найдено фотографий: <?php echo count($availablePhotos); ?></p>
                        <p class="text-xs text-slate-500">Выберите фотографии и нажмите "Добавить выбранные фотографии в галерею"</p>
                    </div>

                    <!-- Кнопки действий -->
                    <div class="flex gap-2 mb-4">
                        <button onclick="selectAllPhotos()" class="px-4 py-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition">
                            <i class="fas fa-check-square mr-1"></i>Выбрать все
                        </button>
                        <button onclick="deselectAllPhotos()" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">
                            <i class="fas fa-square mr-1"></i>Снять выбор
                        </button>
                        <button onclick="addSelectedPhotosToGallery()" class="px-6 py-2 bg-gradient-to-r from-green-300 via-green-400 to-green-500 text-white rounded-xl font-semibold shadow-md hover:shadow-lg transition">
                            <i class="fas fa-plus-circle mr-2"></i>Добавить выбранные фотографии в галерею
                        </button>
                    </div>

                    <?php
                    $officeGroups = [];
                    foreach ($availablePhotos as $photo) {
                        $officeKey = $photo['city'] . ' - ' . $photo['office_slug'];
                        $officeGroups[$officeKey][] = $photo;
                    }
                    ?>

                    <?php foreach ($officeGroups as $officeName => $officePhotos): ?>
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold text-slate-900"><?php echo ucfirst($officeName); ?> (<?php echo count($officePhotos); ?> фото)</h3>
                                <?php
                                $officeParts = explode(' - ', $officeName, 2);
                                $officeIdForDelete = null;
                                if (count($officeParts) >= 2) {
                                    $officeCityKey = $officeParts[0];
                                    $folderSlug = $officeParts[1];
                                    foreach ($offices as $o) {
                                        if (($o['city'] ?? '') === $officeCityKey
                                            && th_office_admin_office_disk_slug((string) $officeCityKey, (string) ($o['name'] ?? '')) === $folderSlug) {
                                            $officeIdForDelete = (int) $o['id'];
                                            break;
                                        }
                                    }
                                }
                                if ($officeIdForDelete > 0 && count($officePhotos) > 0): ?>
                                        <button type="button" onclick="deleteAllOfficePhotos(<?php echo $officeIdForDelete; ?>, '<?php echo htmlspecialchars($officeName, ENT_QUOTES, 'UTF-8'); ?>')"
                                                class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600 transition">
                                            <i class="fas fa-trash mr-1"></i>Удалить все
                                        </button>
                                <?php endif; ?>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 p-4 bg-slate-50 rounded-xl">
                                <?php foreach ($officePhotos as $photo): ?>
                                    <div class="relative group">
                                        <label class="block cursor-pointer">
                                            <input type="checkbox" name="selected_photos[]" value="<?php echo htmlspecialchars($photo['path']); ?>" class="photo-checkbox absolute top-2 left-2 z-10 w-4 h-4">
                                            <div class="relative overflow-hidden rounded-lg border-2 border-sky-200 group-hover:border-sky-400 transition">
                                                <img src="<?php echo htmlspecialchars($photo['path']); ?>"
                                                     alt="<?php echo htmlspecialchars($photo['filename']); ?>"
                                                     class="w-full h-32 object-cover"
                                                     onerror="this.src='https://via.placeholder.com/150?text=Error'">
                                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition flex items-center justify-center">
                                                    <i class="fas fa-check-circle text-white text-xl opacity-0 group-hover:opacity-100 transition check-icon"></i>
                                                </div>
                                                <!-- Кнопка удаления отдельного фото -->
                                                <button type="button" onclick="deleteSinglePhoto('<?php echo htmlspecialchars($photo['path']); ?>', '<?php echo htmlspecialchars($photo['filename']); ?>')"
                                                        class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-red-600"
                                                        title="Удалить фото">
                                                    <i class="fas fa-times text-xs"></i>
                                                </button>
                                            </div>
                                            <p class="text-xs text-slate-600 mt-1 truncate" title="<?php echo htmlspecialchars($photo['filename']); ?>">
                                                <?php echo htmlspecialchars($photo['filename']); ?>
                                            </p>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Просмотр загруженных фотографий -->
            <div class="surface-card p-6 mb-6">
                <h2 class="heading-font text-2xl font-bold text-slate-900 mb-4">Все загруженные фотографии по офисам</h2>

                <?php if (empty($availablePhotos)): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
                        <p class="text-yellow-800">Нет загруженных фотографий. Используйте форму выше для загрузки фото офисов.</p>
                    </div>
                <?php else: ?>
                    <div class="mb-4">
                        <p class="text-sm text-slate-600">Найдено фотографий: <?php echo count($availablePhotos); ?></p>
                    </div>

                    <?php
                    $officeGroups = [];
                    foreach ($availablePhotos as $photo) {
                        $officeKey = $photo['city'] . ' - ' . $photo['office_slug'];
                        $officeGroups[$officeKey][] = $photo;
                    }
                    ?>

                    <?php foreach ($officeGroups as $officeName => $officePhotos): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3"><?php echo ucfirst($officeName); ?> (<?php echo count($officePhotos); ?> фото)</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 p-4 bg-slate-50 rounded-xl">
                                <?php foreach ($officePhotos as $photo): ?>
                                    <div class="relative">
                                        <div class="overflow-hidden rounded-lg border-2 border-sky-200">
                                            <img src="<?php echo htmlspecialchars($photo['path']); ?>"
                                                 alt="<?php echo htmlspecialchars($photo['filename']); ?>"
                                                 class="w-full h-32 object-cover"
                                                 onerror="this.src='https://via.placeholder.com/150?text=Error'">
                                        </div>
                                        <p class="text-xs text-slate-600 mt-1 truncate" title="<?php echo htmlspecialchars($photo['filename']); ?>">
                                            <?php echo htmlspecialchars($photo['filename']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Инструкция -->
            <div class="surface-card p-6 mt-6">
                <h3 class="heading-font text-xl font-bold text-slate-900 mb-4">Инструкция</h3>
                <ol class="list-decimal list-inside space-y-2 text-slate-600">
                    <li><strong>Загрузка фото:</strong> Выберите офис из списка и загрузите фото через форму "Загрузить фото офисов"</li>
                    <li><strong>Добавление в галерею офиса:</strong> Выберите офис из списка в разделе "Выбрать загруженные фотографии для галереи офиса"</li>
                    <li><strong>Выбор фото:</strong> Отметьте нужные фотографии (или используйте кнопку "Выбрать все" / "Снять выбор")</li>
                    <li><strong>Добавление:</strong> Нажмите "Добавить выбранные фотографии в галерею"</li>
                    <li><strong>Результат:</strong> Выбранные фотографии будут добавлены в галерею конкретного офиса и отобразятся на странице офиса</li>
                    <li><strong>Хранение:</strong> Фото хранятся в папках <code class="bg-sky-50 px-2 py-1 rounded">frontend/window/img/offices/[город]/[офис-slug]/</code></li>
                </ol>
            </div>
        </div>
    </main>

    <script>
        // Функции для управления галереей фото офисов
        function selectAllPhotos() {
            document.querySelectorAll('.photo-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                updatePhotoSelection(checkbox);
            });
        }

        function deselectAllPhotos() {
            document.querySelectorAll('.photo-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                updatePhotoSelection(checkbox);
            });
        }

        function updatePhotoSelection(checkbox) {
            const label = checkbox.closest('label');
            const checkIcon = label.querySelector('.check-icon');
            if (checkbox.checked) {
                label.querySelector('div').classList.add('border-sky-500', 'ring-2', 'ring-sky-300');
                checkIcon.classList.remove('opacity-0');
            } else {
                label.querySelector('div').classList.remove('border-sky-500', 'ring-2', 'ring-sky-300');
                checkIcon.classList.add('opacity-0');
            }
        }

        function addSelectedPhotosToGallery() {
            const selectedOffice = document.getElementById('selectedOffice').value;
            if (!selectedOffice) {
                alert('Выберите офис для добавления фото');
                return;
            }

            const selectedPhotos = Array.from(document.querySelectorAll('.photo-checkbox:checked')).map(cb => cb.value);
            if (selectedPhotos.length === 0) {
                alert('Выберите хотя бы одно фото');
                return;
            }

            if (confirm(`Добавить ${selectedPhotos.length} фото в галерею выбранного офиса?`)) {
                // Создаем форму и отправляем
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_photos_to_gallery">
                    <input type="hidden" name="office_id" value="${selectedOffice}">
                    ${selectedPhotos.map(url => `<input type="hidden" name="selected_photos[]" value="${url}">`).join('')}
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Обработка изменения чекбоксов
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('photo-checkbox')) {
                updatePhotoSelection(e.target);
            }
        });

        // Drag & Drop для загрузки фото офисов
        const officeDropZone = document.getElementById('office-drop-zone');
        const officeFileInput = document.getElementById('office-photo-input');
        const officeFileList = document.getElementById('office-file-list');

        if (officeDropZone && officeFileInput) {
            // Обработка drag & drop событий
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                officeDropZone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                officeDropZone.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                officeDropZone.addEventListener(eventName, unhighlight, false);
            });

            function highlight(e) {
                officeDropZone.classList.add('border-sky-500', 'bg-sky-100');
            }

            function unhighlight(e) {
                officeDropZone.classList.remove('border-sky-500', 'bg-sky-100');
            }

            officeDropZone.addEventListener('drop', handleDrop, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles(files);
            }

            officeFileInput.addEventListener('change', function(e) {
                handleFiles(e.target.files);
            });

            function handleFiles(files) {
                officeFileList.innerHTML = '';
                [...files].forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'flex items-center gap-2 text-sm text-slate-600 bg-slate-100 px-2 py-1 rounded';
                    fileItem.innerHTML = `
                        <i class="fas fa-file-image text-sky-500"></i>
                        <span>${file.name}</span>
                        <span class="text-xs text-slate-400">(${formatFileSize(file.size)})</span>
                    `;
                    officeFileList.appendChild(fileItem);
                });
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        }

        // Функции удаления фото
        function deleteSinglePhoto(photoPath, fileName) {
            if (confirm(`Удалить фото "${fileName}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_photo">
                    <input type="hidden" name="photo_path" value="${photoPath}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteAllOfficePhotos(officeId, officeName) {
            if (confirm(`Удалить ВСЕ фото офиса "${officeName}"? Это действие нельзя отменить!`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_office_photos">
                    <input type="hidden" name="office_id" value="${officeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Обработка отправки формы загрузки фото
        const uploadForm = document.querySelector('form[action*="upload_office_photo"]');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                const uploadBtn = document.getElementById('upload-btn');
                const uploadText = document.getElementById('upload-text');
                const uploadSpinner = document.getElementById('upload-spinner');

                if (uploadBtn && uploadText && uploadSpinner) {
                    uploadBtn.disabled = true;
                    uploadText.textContent = 'Загружаем...';
                    uploadSpinner.classList.remove('hidden');
                }
            });
        }
    </script>
</body>
</html>