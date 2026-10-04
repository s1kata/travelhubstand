<?php
/**
 * CRUD-интерфейс для управления услугами
 * Добавление, редактирование, удаление услуг для вывода на сайте и экспорта в Яндекс.Бизнес
 */
require_once __DIR__ . '/../config/config.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

if (!$pdo) {
    die('Ошибка подключения к БД');
}

$message = '';
$messageType = '';

// Проверка существования таблицы services
try {
    $driver = strtolower(getenv('DB_DRIVER') ?: 'sqlite');
    if ($driver === 'mysql') {
        $exists = $pdo->query("SHOW TABLES LIKE 'services'")->rowCount() > 0;
    } else {
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='services'")->fetchColumn() !== false;
    }
    if (!$exists) {
        $migrationPath = __DIR__ . '/../../backend/scripts/run_services_migration.php';
        $message = 'Таблица services не найдена. Запустите миграцию: php backend/scripts/run_services_migration.php';
        $messageType = 'error';
    }
} catch (PDOException $e) {
    $message = 'Ошибка проверки таблицы: ' . $e->getMessage();
    $messageType = 'error';
}

// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $price = floatval(str_replace(',', '.', $_POST['price'] ?? 0));
        $description = trim($_POST['description'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $available = isset($_POST['available']) ? 1 : 0;
        $displayOrder = intval($_POST['display_order'] ?? 0);

        if (empty($name)) {
            $message = 'Введите название услуги';
            $messageType = 'error';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO services (name, price, description, url, available, display_order) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $price, $description, $url, $available, $displayOrder]);
                    $message = 'Услуга успешно добавлена';
                    $messageType = 'success';
                } else {
                    $id = intval($_POST['id'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE services SET name=?, price=?, description=?, url=?, available=?, display_order=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                    $stmt->execute([$name, $price, $description, $url, $available, $displayOrder, $id]);
                    $message = 'Услуга обновлена';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Ошибка сохранения: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Услуга удалена';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Ошибка удаления: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Загрузка списка услуг
$services = [];
if (empty($message) || $messageType === 'success') {
    try {
        $stmt = $pdo->query("SELECT * FROM services ORDER BY display_order ASC, name ASC");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Таблица может не существовать
    }
}

$editId = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editService = null;
if ($editId > 0) {
    foreach ($services as $s) {
        if ((int)$s['id'] === $editId) {
            $editService = $s;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Управление услугами | Travel Hub Admin</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: var(--font-sans); }
        .heading-font { font-family: var(--font-sans); font-weight: 600; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <header class="bg-white border-b border-slate-200 py-4 px-6">
        <div class="th-container mx-auto flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="admin.php" class="text-sky-600 hover:text-sky-700"><i class="fas fa-arrow-left"></i></a>
                <h1 class="heading-font text-xl font-bold">Управление услугами</h1>
            </div>
        </div>
    </header>

    <main class="th-container mx-auto px-4 py-8 max-w-4xl">
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Форма добавления/редактирования -->
        <section class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h2 class="heading-font text-lg font-semibold mb-4">
                <?php echo $editService ? 'Редактировать услугу' : 'Добавить услугу'; ?>
            </h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="<?php echo $editService ? 'edit' : 'add'; ?>">
                <?php if ($editService): ?><input type="hidden" name="id" value="<?php echo (int)$editService['id']; ?>"><?php endif; ?>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Название *</label>
                    <input type="text" name="name" required
                           value="<?php echo htmlspecialchars($editService['name'] ?? ''); ?>"
                           class="w-full border border-slate-300 rounded-lg px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Цена (руб.)</label>
                    <input type="text" name="price" placeholder="1000"
                           value="<?php echo htmlspecialchars($editService['price'] ?? ''); ?>"
                           class="w-full border border-slate-300 rounded-lg px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Описание</label>
                    <textarea name="description" rows="3" class="w-full border border-slate-300 rounded-lg px-4 py-2"><?php echo htmlspecialchars($editService['description'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">URL (ссылка на страницу услуги)</label>
                    <input type="text" name="url" placeholder="/usluga/test или /frontend/window/services.php"
                           value="<?php echo htmlspecialchars($editService['url'] ?? ''); ?>"
                           class="w-full border border-slate-300 rounded-lg px-4 py-2">
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="available" <?php echo ($editService['available'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="text-sm">Доступна</span>
                    </label>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Порядок отображения</label>
                        <input type="number" name="display_order" value="<?php echo (int)($editService['display_order'] ?? 0); ?>" class="w-24 border border-slate-300 rounded-lg px-3 py-1">
                    </div>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="bg-sky-500 text-white px-6 py-2 rounded-lg hover:bg-sky-600">
                        <?php echo $editService ? 'Сохранить' : 'Добавить'; ?>
                    </button>
                    <?php if ($editService): ?>
                    <a href="manage-services.php" class="text-slate-600 px-6 py-2">Отмена</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Список услуг -->
        <section class="bg-white rounded-xl shadow-md overflow-hidden">
            <h2 class="heading-font text-lg font-semibold p-6 border-b">Список услуг</h2>
            <?php if (empty($services)): ?>
            <p class="p-6 text-slate-600">Нет услуг. Добавьте первую услугу выше.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left p-4 font-medium">Название</th>
                            <th class="text-left p-4 font-medium">Цена</th>
                            <th class="text-left p-4 font-medium">Доступна</th>
                            <th class="text-left p-4 font-medium">URL</th>
                            <th class="p-4">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $s): ?>
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="p-4 font-medium"><?php echo htmlspecialchars($s['name']); ?></td>
                            <td class="p-4"><?php echo number_format((float)$s['price'], 0, '.', ' '); ?> ₽</td>
                            <td class="p-4"><?php echo ($s['available'] ?? 1) ? 'Да' : 'Нет'; ?></td>
                            <td class="p-4 text-sm text-slate-600"><?php echo htmlspecialchars($s['url'] ?? '-'); ?></td>
                            <td class="p-4 flex gap-2">
                                <a href="?edit=<?php echo (int)$s['id']; ?>" class="text-sky-600 hover:text-sky-700"><i class="fas fa-edit"></i></a>
                                <form method="post" class="inline" onsubmit="return confirm('Удалить услугу?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-700"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <p class="mt-6 text-sm text-slate-500">
            <strong>YML для Яндекс.Бизнеса:</strong>
            <a href="/export/services_yml.php" target="_blank" class="text-sky-600 hover:underline">/export/services_yml.php</a>
            — укажите эту ссылку в Яндекс.Бизнес → Товары и услуги → YML-фид.
        </p>
    </main>
</body>
</html>
