<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/security_helper.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

if ($pdo) {
    $drv = strtolower(getenv('DB_DRIVER') ?: 'sqlite');
    try {
        if ($drv === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_manager'")->fetchAll();
            if (empty($cols)) $pdo->exec("ALTER TABLE users ADD COLUMN is_manager TINYINT(1) DEFAULT 0 NOT NULL");
        } else {
            $info = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $has = false;
            foreach ($info as $col) { if ($col['name'] === 'is_manager') { $has = true; break; } }
            if (!$has) $pdo->exec("ALTER TABLE users ADD COLUMN is_manager INTEGER DEFAULT 0");
        }
    } catch (PDOException $e) { /* колонка уже есть или другая ошибка */ }
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['action'])) {
    if (!security_csrf_verify()) {
        $message = 'Недействительный запрос (CSRF). Обновите страницу и попробуйте снова.';
        $messageType = 'error';
    } else {
    $userId = (int) $_POST['user_id'];
    $action = $_POST['action'];

    if ($pdo) {
        try {
            if ($action === 'make_admin') {
                $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'Пользователь успешно назначен администратором';
                $messageType = 'success';
            } elseif ($action === 'remove_admin') {
                $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'Права администратора успешно сняты';
                $messageType = 'success';
            } elseif ($action === 'make_manager') {
                $stmt = $pdo->prepare("UPDATE users SET is_manager = 1 WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'Пользователю присвоен статус менеджера (доступ к странице «Для туроператоров»)';
                $messageType = 'success';
            } elseif ($action === 'remove_manager') {
                $stmt = $pdo->prepare("UPDATE users SET is_manager = 0 WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'Статус менеджера снят';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Ошибка при изменении роли пользователя: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = 'Ошибка подключения к базе данных';
        $messageType = 'error';
    }
    }
}

$users = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, email, role, COALESCE(is_manager,0) AS is_manager, reg_date FROM users ORDER BY reg_date DESC");
        $users = $stmt->fetchAll();
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'is_manager') !== false || strpos($e->getMessage(), '42S22') !== false || stripos($e->getMessage(), 'Unknown column') !== false) {
            try {
                $stmt = $pdo->query("SELECT id, name, email, role, reg_date FROM users ORDER BY reg_date DESC");
                $users = $stmt->fetchAll();
                foreach ($users as &$u) { $u['is_manager'] = 0; }
            } catch (PDOException $e2) {
                error_log('[set_admin_role] Error loading users: ' . $e2->getMessage());
            }
        } else {
            error_log('[set_admin_role] Error loading users: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Роли и статусы (админ, менеджер) | Travel Hub Admin</title>
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
            <div class="max-w-6xl mx-auto space-y-8 sm:space-y-12">
                <div class="text-center space-y-3 sm:space-y-4">
                    <span class="eyebrow-badge inline-flex items-center gap-2">
                        <i class="fas fa-user-crown"></i>
                        Управление ролями
                    </span>
                    <h1 class="heading-font text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 px-2">Роли и статусы</h1>
                    <p class="text-slate-600 max-w-2xl mx-auto text-sm sm:text-base px-2">Назначайте администраторов и менеджеров. Менеджеры получают доступ к странице «Для туроператоров».</p>
                </div>

                <?php if ($message): ?>
                <div class="max-w-4xl mx-auto">
                    <div class="rounded-xl p-4 <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
                        <div class="flex items-center gap-3">
                            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> text-lg"></i>
                            <span><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <section class="metric-card p-4 sm:p-6">
                    <h2 class="heading-font text-xl sm:text-2xl font-semibold text-slate-900 mb-4 sm:mb-6 flex items-center gap-2">
                        <i class="fas fa-users text-sky-500 text-base sm:text-lg"></i>
                        <span>Все пользователи</span>
                    </h2>

                    <?php if (!empty($users)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border-collapse">
                                <thead class="bg-sky-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Имя</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Роль</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Менеджер</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Дата регистрации</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="border-t border-sky-100 hover:bg-sky-50 transition">
                                            <td class="px-4 py-2 text-sm text-slate-700"><?php echo htmlspecialchars($user['id']); ?></td>
                                            <td class="px-4 py-2 text-sm text-slate-700"><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td class="px-4 py-2 text-sm text-slate-700"><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td class="px-4 py-2 text-sm text-slate-700">
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                    <i class="fas <?php echo $user['role'] === 'admin' ? 'fa-crown' : 'fa-user'; ?>"></i>
                                                    <?php echo htmlspecialchars($user['role'] === 'admin' ? 'Админ' : 'Пользователь'); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-slate-700">
                                                <?php $isManager = !empty($user['is_manager']); ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium <?php echo $isManager ? 'bg-teal-100 text-teal-800' : 'bg-gray-100 text-gray-600'; ?>">
                                                    <i class="fas fa-briefcase"></i>
                                                    <?php echo $isManager ? 'Да' : 'Нет'; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-slate-700"><?php echo htmlspecialchars($user['reg_date']); ?></td>
                                            <td class="px-4 py-2 text-sm text-slate-700 space-x-1">
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                    <?php if ($user['role'] === 'admin'): ?>
                                                        <button type="submit" name="action" value="remove_admin"
                                                                class="px-2 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600 transition"
                                                                onclick="return confirm('Снять права администратора у пользователя <?php echo htmlspecialchars($user['name']); ?>?')">
                                                            <i class="fas fa-user-times"></i> Снять админа
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="action" value="make_admin"
                                                                class="px-2 py-1 bg-green-500 text-white text-xs rounded hover:bg-green-600 transition"
                                                                onclick="return confirm('Назначить администратором пользователя <?php echo htmlspecialchars($user['name']); ?>?')">
                                                            <i class="fas fa-user-crown"></i> Админ
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                    <?php if ($isManager): ?>
                                                        <button type="submit" name="action" value="remove_manager"
                                                                class="px-2 py-1 bg-amber-500 text-white text-xs rounded hover:bg-amber-600 transition"
                                                                onclick="return confirm('Снять статус менеджера у пользователя <?php echo htmlspecialchars($user['name']); ?>?')">
                                                            <i class="fas fa-user-minus"></i> Снять менеджера
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="action" value="make_manager"
                                                                class="px-2 py-1 bg-teal-500 text-white text-xs rounded hover:bg-teal-600 transition"
                                                                onclick="return confirm('Выдать статус менеджера пользователю <?php echo htmlspecialchars($user['name']); ?>? Доступ к странице «Для туроператоров».')">
                                                            <i class="fas fa-briefcase"></i> Менеджер
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-slate-600 text-sm sm:text-base">Пользователи не найдены.</p>
                    <?php endif; ?>
                </section>

                <section class="text-center">
                    <a href="admin.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-gray-600 to-gray-700 text-white px-6 py-3 rounded-lg font-semibold hover:from-gray-700 hover:to-gray-800 transition-all">
                        <i class="fas fa-arrow-left"></i>
                        Вернуться в админку
                    </a>
                </section>
            </div>
        </div>
    </main>
</body>
</html>