<?php
/**
 * Управление сотрудниками офисов
 * Добавление, редактирование и удаление сотрудников
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/office_admin_bootstrap.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

if (!$pdo) {
    die('Database connection failed');
}

th_office_admin_bootstrap($pdo);

$message = '';
$messageType = '';



// Обработка загрузки/обновления сотрудников
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_employee') {
        $officeId = intval($_POST['office_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $info = trim($_POST['info'] ?? '');

        // Получаем город офиса для создания правильной папки
        $city = '';
        if ($officeId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT city FROM offices WHERE id = ?");
                $stmt->execute([$officeId]);
                $office = $stmt->fetch();
                $city = $office['city'] ?? '';
            } catch (PDOException $e) {
                $message = 'Ошибка при получении данных офиса: ' . $e->getMessage();
                $messageType = 'error';
            }
        }

        // Обработка загрузки фото
        $photoUrl = '';
        if (!empty($city) && isset($_FILES['employee_photo']) && $_FILES['employee_photo']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileName = basename($_FILES['employee_photo']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExt, $allowedExts)) {
                $uploadDir = th_office_admin_employees_upload_dir($city) . DIRECTORY_SEPARATOR;

                // Создаем уникальное имя файла
                $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\-_.]/', '', $fileName);
                $targetPath = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['employee_photo']['tmp_name'], $targetPath)) {
                    $photoUrl = '/img/employees/' . $city . '/' . $newFileName;
                } else {
                    $message = 'Ошибка при сохранении фото сотрудника';
                    $messageType = 'error';
                }
            } else {
                $message = 'Неподдерживаемый формат фото. Разрешены: JPG, PNG, GIF, WebP';
                $messageType = 'error';
            }
        } elseif (empty($_FILES['employee_photo']['name'])) {
            $message = 'Выберите фото сотрудника';
            $messageType = 'error';
        }

        if (!empty($name) && !empty($photoUrl) && empty($message)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO office_employees (office_id, name, position, phone, email, photo, info)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$officeId, $name, $position, $phone, $email, $photoUrl, $info]);
                $message = 'Сотрудник успешно добавлен!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Ошибка при добавлении сотрудника: ' . $e->getMessage();
                $messageType = 'error';
            }
        } elseif (empty($message)) {
            $message = 'Заполните ФИО и выберите фото сотрудника';
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'update_employee') {
        $employeeId = intval($_POST['employee_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $info = trim($_POST['info'] ?? '');
        $currentPhoto = trim($_POST['current_photo'] ?? '');

        // Обработка загрузки нового фото (если выбрано)
        $photoUrl = $currentPhoto; // По умолчанию оставляем старое фото
        if (isset($_FILES['employee_photo']) && $_FILES['employee_photo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['employee_photo']['name'])) {
            // Получаем город сотрудника
            try {
                $stmt = $pdo->prepare("
                    SELECT o.city
                    FROM office_employees oe
                    JOIN offices o ON oe.office_id = o.id
                    WHERE oe.id = ?
                ");
                $stmt->execute([$employeeId]);
                $employeeData = $stmt->fetch();
                $city = $employeeData['city'] ?? '';

                if (!empty($city)) {
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $fileName = basename($_FILES['employee_photo']['name']);
                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    if (in_array($fileExt, $allowedExts)) {
                        $uploadDir = th_office_admin_employees_upload_dir($city) . DIRECTORY_SEPARATOR;

                        // Создаем уникальное имя файла
                        $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\-_.]/', '', $fileName);
                        $targetPath = $uploadDir . $newFileName;

                        if (move_uploaded_file($_FILES['employee_photo']['tmp_name'], $targetPath)) {
                            $photoUrl = '/img/employees/' . $city . '/' . $newFileName;
                        } else {
                            $message = 'Ошибка при сохранении нового фото сотрудника';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Неподдерживаемый формат фото. Разрешены: JPG, PNG, GIF, WebP';
                        $messageType = 'error';
                    }
                }
            } catch (PDOException $e) {
                $message = 'Ошибка при получении данных сотрудника: ' . $e->getMessage();
                $messageType = 'error';
            }
        }

        if ($employeeId > 0 && empty($message)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE office_employees
                    SET name = ?, position = ?, phone = ?, email = ?, photo = ?, info = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $position, $phone, $email, $photoUrl, $info, $employeeId]);
                $message = 'Сотрудник успешно обновлен!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Ошибка при обновлении сотрудника: ' . $e->getMessage();
                $messageType = 'error';
            }
        } elseif (empty($message)) {
            $message = 'Выберите сотрудника для обновления';
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'delete_employee') {
        $employeeId = intval($_POST['employee_id'] ?? 0);
        if ($employeeId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM office_employees WHERE id = ?");
                $stmt->execute([$employeeId]);
                $message = 'Сотрудник удален!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Ошибка при удалении: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}


// Получаем список офисов
$offices = [];
try {
    $stmt = $pdo->query("SELECT id, city, name, address FROM offices ORDER BY city, name");
    $offices = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[Manage Office Employees] Error loading offices: ' . $e->getMessage());
}

// Получаем сотрудников для выбранного офиса
$officeEmployees = [];
$selectedOfficeId = isset($_GET['office_id']) ? intval($_GET['office_id']) : ($offices[0]['id'] ?? 0);

if ($selectedOfficeId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT oe.*, o.name as office_name, o.city
            FROM office_employees oe
            JOIN offices o ON oe.office_id = o.id
            WHERE oe.office_id = ?
            ORDER BY oe.name
        ");
        $stmt->execute([$selectedOfficeId]);
        $officeEmployees = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[Manage Office Employees] Error loading employees: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Управление сотрудниками офисов - Travel Hub</title>
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
                    <span class="heading-font text-lg sm:text-2xl font-bold text-sky-600">Управление сотрудниками офисов</span>
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
                    <p class="font-semibold mb-1">Не удалось загрузить список офисов</p>
                    <p class="text-sm">Проверьте подключение к БД. Таблицы <code class="bg-white/80 px-1 rounded">offices</code> / <code class="bg-white/80 px-1 rounded">office_employees</code> создаются при открытии этой страницы.</p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-1 gap-6 mb-8">
                <!-- Список сотрудников -->
                <div class="surface-card p-6">
                    <h2 class="heading-font text-2xl font-bold text-slate-900 mb-6">Сотрудники офиса</h2>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Выберите офис</label>
                        <select onchange="window.location.href='?office_id=' + this.value" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500" <?php echo empty($offices) ? 'disabled' : ''; ?>>
                            <?php foreach ($offices as $office): ?>
                                <option value="<?php echo $office['id']; ?>" <?php echo $selectedOfficeId == $office['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($office['city'] . ' - ' . $office['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (empty($officeEmployees)): ?>
                        <p class="text-slate-600 text-center py-8">Нет сотрудников в этом офисе</p>
                    <?php else: ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <?php foreach ($officeEmployees as $employee): ?>
                                <div class="border border-sky-200 rounded-xl p-4">
                                    <div class="flex items-start gap-4">
                                        <div class="w-16 h-16 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center overflow-hidden flex-shrink-0">
                                            <?php if (!empty($employee['photo'])): ?>
                                                <img src="<?php echo htmlspecialchars($employee['photo']); ?>" alt="<?php echo htmlspecialchars($employee['name']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-user text-white text-xl"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-slate-900"><?php echo htmlspecialchars($employee['name']); ?></h3>
                                            <?php if (!empty($employee['position'])): ?>
                                                <p class="text-sm text-slate-600 mb-1"><?php echo htmlspecialchars($employee['position']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($employee['phone'])): ?>
                                                <p class="text-sm text-slate-600 mb-1"><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($employee['phone']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($employee['email'])): ?>
                                                <p class="text-sm text-slate-600"><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($employee['email']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-col gap-2">
                                            <button onclick="editEmployee(<?php echo $employee['id']; ?>, '<?php echo addslashes($employee['name']); ?>', '<?php echo addslashes($employee['position'] ?? ''); ?>', '<?php echo addslashes($employee['phone'] ?? ''); ?>', '<?php echo addslashes($employee['email'] ?? ''); ?>', '<?php echo addslashes($employee['photo'] ?? ''); ?>', '<?php echo addslashes($employee['info'] ?? ''); ?>')" class="px-3 py-1 bg-sky-100 text-sky-600 rounded-lg hover:bg-sky-200 transition text-sm">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Удалить этого сотрудника?');">
                                                <input type="hidden" name="action" value="delete_employee">
                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                <button type="submit" class="px-3 py-1 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition text-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Добавить сотрудника -->
            <div class="surface-card p-6 mb-6">
                <h2 class="heading-font text-2xl font-bold text-slate-900 mb-4">Добавить сотрудника</h2>
                <form method="POST" enctype="multipart/form-data" id="addEmployeeForm">
                    <input type="hidden" name="action" value="add_employee">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Офис</label>
                            <select name="office_id" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500" <?php echo empty($offices) ? 'disabled' : ''; ?>>
                                <option value="">Выберите офис</option>
                                <?php foreach ($offices as $office): ?>
                                    <option value="<?php echo $office['id']; ?>" <?php echo $selectedOfficeId == $office['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($office['city'] . ' - ' . $office['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">ФИО *</label>
                            <input type="text" name="name" required class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Должность</label>
                            <input type="text" name="position" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Телефон</label>
                            <input type="tel" name="phone" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                            <input type="email" name="email" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Дополнительная информация</label>
                            <textarea name="info" rows="3" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500" placeholder="Дополнительная информация о сотруднике (опционально)"></textarea>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Фото сотрудника *</label>
                        <div class="relative border-2 border-dashed border-sky-300 rounded-xl p-6 text-center hover:border-sky-400 transition-colors bg-sky-50/50" id="add-photo-drop-zone">
                            <input type="file" name="employee_photo" accept="image/*" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="handleAddPhotoChange(this)">
                            <div id="add-photo-content" class="text-sky-600">
                                <i class="fas fa-cloud-upload-alt text-3xl mb-2"></i>
                                <p class="font-medium">Выберите фото сотрудника</p>
                                <p class="text-sm text-sky-500 mt-1">Поддерживаются: JPG, PNG, GIF, WebP</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white rounded-xl font-semibold shadow-md hover:shadow-lg transition">
                        <i class="fas fa-plus-circle mr-2"></i>Добавить сотрудника
                    </button>
                </form>
            </div>


        </div>
    </main>

    <!-- Модальное окно редактирования сотрудника -->
    <div id="editEmployeeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <h3 class="heading-font text-xl font-bold text-slate-900 mb-4">Редактировать сотрудника</h3>
            <form method="POST" enctype="multipart/form-data" id="editEmployeeForm">
                <input type="hidden" name="action" value="update_employee">
                <input type="hidden" name="employee_id" id="edit_employee_id">
                <input type="hidden" name="current_photo" id="edit_current_photo">

                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">ФИО</label>
                        <input type="text" name="name" id="edit_name" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Должность</label>
                        <input type="text" name="position" id="edit_position" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Телефон</label>
                        <input type="tel" name="phone" id="edit_phone" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                        <input type="email" name="email" id="edit_email" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Новое фото (опционально)</label>
                        <div class="relative border-2 border-dashed border-sky-300 rounded-xl p-4 text-center hover:border-sky-400 transition-colors bg-sky-50/50">
                            <input type="file" name="employee_photo" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="handleEditPhotoChange(this)">
                            <div class="text-sky-600">
                                <i class="fas fa-cloud-upload-alt text-2xl mb-1"></i>
                                <p class="text-sm font-medium">Выберите новое фото</p>
                                <p class="text-xs text-sky-500">Оставьте пустым, чтобы не менять</p>
                            </div>
                        </div>
                        <div id="current-photo-display" class="mt-2"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Дополнительная информация</label>
                        <textarea name="info" id="edit_info" rows="3" class="w-full px-4 py-2 border border-sky-200 rounded-xl focus:ring-2 focus:ring-sky-500" placeholder="Дополнительная информация о сотруднике (опционально)"></textarea>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white rounded-xl font-semibold shadow-md hover:shadow-lg transition">
                        Сохранить
                    </button>
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-slate-300 text-slate-700 rounded-xl font-semibold shadow-md hover:shadow-lg transition">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editEmployee(id, name, position, phone, email, photo, info) {
            document.getElementById('edit_employee_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_position').value = position || '';
            document.getElementById('edit_phone').value = phone || '';
            document.getElementById('edit_email').value = email || '';
            document.getElementById('edit_current_photo').value = photo || '';
            document.getElementById('edit_info').value = info || '';

            // Показываем текущее фото
            const currentPhotoDisplay = document.getElementById('current-photo-display');
            if (photo) {
                currentPhotoDisplay.innerHTML = `
                    <p class="text-xs text-slate-600 mb-1">Текущее фото:</p>
                    <img src="${photo}" alt="Текущее фото" class="w-16 h-16 object-cover rounded-lg border border-sky-200">
                `;
            } else {
                currentPhotoDisplay.innerHTML = '<p class="text-xs text-slate-500">Фото не установлено</p>';
            }

            document.getElementById('editEmployeeModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editEmployeeModal').classList.add('hidden');
        }

        // Обработка выбора файла при добавлении сотрудника
        function handleAddPhotoChange(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const content = document.getElementById('add-photo-content');
                    content.innerHTML = `
                        <img src="${e.target.result}" class="w-20 h-20 object-cover rounded-lg mx-auto mb-2 border border-sky-300">
                        <p class="font-medium text-sm">${file.name}</p>
                        <p class="text-xs text-sky-500">Размер: ${formatFileSize(file.size)}</p>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                const content = document.getElementById('add-photo-content');
                content.innerHTML = `
                    <i class="fas fa-cloud-upload-alt text-3xl mb-2"></i>
                    <p class="font-medium">Выберите фото сотрудника</p>
                    <p class="text-sm text-sky-500 mt-1">Поддерживаются: JPG, PNG, GIF, WebP</p>
                `;
            }
        }

        // Обработка выбора файла при редактировании сотрудника
        function handleEditPhotoChange(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const content = input.parentElement.querySelector('div');
                    content.innerHTML = `
                        <img src="${e.target.result}" class="w-16 h-16 object-cover rounded-lg mx-auto mb-1 border border-sky-300">
                        <p class="text-xs font-medium">${file.name}</p>
                        <p class="text-xs text-sky-500">Размер: ${formatFileSize(file.size)}</p>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                const content = input.parentElement.querySelector('div');
                content.innerHTML = `
                    <i class="fas fa-cloud-upload-alt text-2xl mb-1"></i>
                    <p class="text-sm font-medium">Выберите новое фото</p>
                    <p class="text-xs text-sky-500">Оставьте пустым, чтобы не менять</p>
                `;
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Закрытие модального окна при клике вне его
        document.getElementById('editEmployeeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>