<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/country_content_helper.php';
require_once __DIR__ . '/../components/security_helper.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!security_csrf_verify()) {
        $message = 'Недействительный запрос (CSRF). Обновите страницу.';
        $messageType = 'error';
    } else {
    $countrySlug = trim($_POST['country_slug'] ?? '');
    $bio = country_content_clean_utf8(trim($_POST['bio'] ?? ''));
    $highlights = trim($_POST['highlights'] ?? '');
    $usefulInfo = trim($_POST['useful_info'] ?? '');
    $detailedInfo = trim($_POST['detailed_info'] ?? '');

    if (empty($countrySlug)) {
        $message = 'Название страны обязательно для заполнения';
        $messageType = 'error';
    } else {
        if ($pdo) {
            try {
                // Проверяем существует ли уже запись для этой страны
                $stmt = $pdo->prepare("SELECT id FROM country_content WHERE country_slug = ?");
                $stmt->execute([$countrySlug]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Обновляем существующую запись
                    $stmt = $pdo->prepare("UPDATE country_content SET bio = ?, highlights = ?, useful_info = ?, detailed_info = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE country_slug = ?");
                    $stmt->execute([$bio, $highlights, $usefulInfo, $detailedInfo, $_SESSION['user_id'], $countrySlug]);
                    $message = 'Контент страны успешно обновлен';
                } else {
                    // Создаем новую запись
                    $stmt = $pdo->prepare("INSERT INTO country_content (country_slug, bio, highlights, useful_info, detailed_info, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$countrySlug, $bio, $highlights, $usefulInfo, $detailedInfo, $_SESSION['user_id']]);
                    $message = 'Контент страны успешно создан';
                }
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Ошибка при сохранении контента: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Ошибка подключения к базе данных';
            $messageType = 'error';
        }
    }
    }
}

// Получаем список всех стран
$countries = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM country_content ORDER BY country_slug");
        $countries = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[edit-country-content] Error loading countries: ' . $e->getMessage());
    }
}

// Получаем контент выбранной страны для редактирования
$selectedCountry = null;
if (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    foreach ($countries as $country) {
        if ($country['country_slug'] === $slug) {
            $selectedCountry = $country;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Редактировать контент стран | Travel Hub Admin</title>
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
        .form-textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            line-height: 1.5;
            resize: vertical;
        }
        .form-textarea:focus {
            outline: none;
            border-color: #3ba3ff;
            box-shadow: 0 0 0 3px rgba(59, 163, 255, 0.1);
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
                        <i class="fas fa-globe"></i>
                        Редактор контента
                    </span>
                    <h1 class="heading-font text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 px-2">Редактировать контент стран</h1>
                    <p class="text-slate-600 max-w-2xl mx-auto text-sm sm:text-base px-2">Управляйте описаниями, достопримечательностями и полезной информацией для каждой страны.</p>
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

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Список стран -->
                    <div class="lg:col-span-1">
                        <section class="metric-card p-4 sm:p-6 sticky top-24">
                            <h2 class="heading-font text-xl font-semibold text-slate-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-list text-sky-500 text-base"></i>
                                <span>Страны</span>
                            </h2>

                            <?php if (!empty($countries)): ?>
                                <div class="space-y-2">
                                    <?php foreach ($countries as $country): ?>
                                        <a href="?slug=<?php echo urlencode($country['country_slug']); ?>"
                                           class="block p-3 rounded-lg border border-sky-100 hover:bg-sky-50 transition <?php echo ($selectedCountry && $selectedCountry['country_slug'] === $country['country_slug']) ? 'bg-sky-100 border-sky-300' : ''; ?>">
                                            <div class="font-medium text-slate-900"><?php echo htmlspecialchars(ucfirst($country['country_slug'])); ?></div>
                                            <div class="text-xs text-slate-500 mt-1">
                                                Обновлено: <?php echo htmlspecialchars($country['updated_at'] ?? 'Неизвестно'); ?>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-slate-500 text-sm">Нет доступных стран для редактирования.</p>
                            <?php endif; ?>

                            <div class="mt-6 pt-4 border-t border-sky-100">
                                <a href="?slug=new" class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2 rounded-lg font-medium hover:from-green-600 hover:to-green-700 transition">
                                    <i class="fas fa-plus"></i>
                                    Добавить страну
                                </a>
                            </div>
                        </section>
                    </div>

                    <!-- Форма редактирования -->
                    <div class="lg:col-span-2">
                        <section class="metric-card p-4 sm:p-6">
                            <h2 class="heading-font text-xl font-semibold text-slate-900 mb-6 flex items-center gap-2">
                                <i class="fas fa-edit text-sky-500 text-base"></i>
                                <span><?php echo $selectedCountry ? 'Редактировать страну' : 'Добавить новую страну'; ?></span>
                            </h2>

                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <div>
                                    <label for="country_slug" class="block text-sm font-medium text-slate-700 mb-2">
                                        Название страны (slug) *
                                    </label>
                                    <input type="text"
                                           id="country_slug"
                                           name="country_slug"
                                           value="<?php echo htmlspecialchars($selectedCountry['country_slug'] ?? ($_GET['slug'] === 'new' ? '' : ($_GET['slug'] ?? ''))); ?>"
                                           class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                           placeholder="например: turkey, egypt, thailand"
                                           required>
                                    <p class="text-xs text-slate-500 mt-1">Используйте латиницу, строчные буквы, без пробелов</p>
                                </div>

                                <div>
                                    <label for="bio" class="block text-sm font-medium text-slate-700 mb-2">
                                        Краткое описание (Bio)
                                    </label>
                                    <textarea id="bio"
                                              name="bio"
                                              class="form-textarea"
                                              placeholder="Краткое описание страны..."><?php echo htmlspecialchars($selectedCountry['bio'] ?? ''); ?></textarea>
                                </div>

                                <div>
                                    <label for="highlights" class="block text-sm font-medium text-slate-700 mb-2">
                                        Достопримечательности (Highlights)
                                    </label>
                                    <textarea id="highlights"
                                              name="highlights"
                                              class="form-textarea"
                                              placeholder="Основные достопримечательности страны..."><?php echo htmlspecialchars($selectedCountry['highlights'] ?? ''); ?></textarea>
                                </div>

                                <div>
                                    <label for="useful_info" class="block text-sm font-medium text-slate-700 mb-2">
                                        Полезная информация
                                    </label>
                                    <textarea id="useful_info"
                                              name="useful_info"
                                              class="form-textarea"
                                              placeholder="Виза, валюта, транспорт, советы путешественникам..."><?php echo htmlspecialchars($selectedCountry['useful_info'] ?? ''); ?></textarea>
                                </div>

                                <div>
                                    <label for="detailed_info" class="block text-sm font-medium text-slate-700 mb-2">
                                        Подробная информация
                                    </label>
                                    <textarea id="detailed_info"
                                              name="detailed_info"
                                              class="form-textarea"
                                              placeholder="Подробное описание страны, регионов, особенностей..."><?php echo htmlspecialchars($selectedCountry['detailed_info'] ?? ''); ?></textarea>
                                </div>

                                <div class="flex gap-4">
                                    <button type="submit" class="bg-gradient-to-r from-sky-500 to-sky-600 text-white px-6 py-3 rounded-lg font-medium hover:from-sky-600 hover:to-sky-700 transition flex items-center gap-2">
                                        <i class="fas fa-save"></i>
                                        Сохранить
                                    </button>
                                    <?php if ($selectedCountry): ?>
                                        <a href="?slug=new" class="bg-gray-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-gray-600 transition flex items-center gap-2">
                                            <i class="fas fa-plus"></i>
                                            Новая страна
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
</body>
</html>
