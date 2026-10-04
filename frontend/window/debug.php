<?php
/**
 * Страница отладки — кэш, API Tourvisor, состояние системы.
 * Открыть: /frontend/window/debug.php
 *
 * На продакшене: в .env задайте DEBUG_PAGE_ENABLED=true только временно,
 * либо удалите этот файл.
 */
require_once __DIR__ . '/../../backend/config/config.php';

if (!filter_var(getenv('DEBUG_PAGE_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

session_start();

// Защита: только администратор
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: /frontend/window/login.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Отладка — Travel Hub</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 1.5rem; max-width: 800px; margin: 0 auto; background: #f8fafc; }
        h1 { color: #1e293b; }
        h2 { color: #334155; margin-top: 1.5rem; font-size: 1rem; }
        .block { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .ok { color: #16a34a; }
        .warn { color: #ca8a04; }
        .err { color: #dc2626; }
        pre { background: #f1f5f9; padding: 0.75rem; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; }
        .meta { color: #64748b; font-size: 0.875rem; }
    </style>
</head>
<body>
    <h1>🔧 Отладка Travel Hub</h1>
    <p class="meta"><?= date('Y-m-d H:i:s') ?> · <?= $_SERVER['HTTP_HOST'] ?? '—' ?></p>

    <h2>1. Page Cache (HTML)</h2>
    <div class="block">
        <?php
        $cacheDir = __DIR__ . '/../../data/page_cache';
        $cacheFiles = is_dir($cacheDir) ? glob($cacheDir . '/*.html') : [];
        $count = is_array($cacheFiles) ? count($cacheFiles) : 0;
        $readable = is_dir($cacheDir) && is_readable($cacheDir);
        ?>
        <p><strong>Директория:</strong> <?= $readable ? 'OK' : '<span class="err">нет доступа</span>' ?></p>
        <p><strong>Файлов в кэше:</strong> <?= $count ?></p>
        <p><strong>Проверка:</strong> откройте любую страницу и в DevTools → Network → Response Headers найдите <code>X-Cache: HIT</code> или <code>X-Cache: MISS</code></p>
    </div>

    <h2>2. Tourvisor API</h2>
    <div class="block">
        <?php
        $token = trim((string)(getenv('TOURVISOR_TOKEN') ?: ($_ENV['TOURVISOR_TOKEN'] ?? '')));
        if ($token === '') $token = trim((string)(getenv('TOURVISOR_JWT_TOKEN') ?: ($_ENV['TOURVISOR_JWT_TOKEN'] ?? '')));
        $tokenOk = strlen($token) > 10;
        $tvCacheDir = __DIR__ . '/../../data/tourvisor_cache';
        $tvFiles = is_dir($tvCacheDir) ? glob($tvCacheDir . '/*.json') : [];
        $tvCount = is_array($tvFiles) ? count($tvFiles) : 0;
        ?>
        <p><strong>Токен:</strong> <?= $tokenOk ? '<span class="ok">задан</span>' : '<span class="err">отсутствует</span>' ?></p>
        <p><strong>Кэш Tourvisor (файлов):</strong> <?= $tvCount ?></p>
        <p><strong>URL прокси:</strong>
            <?php
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $apiUrl = $proto . '://' . $host . '/frontend/api/tourvisor-proxy.php';
            ?>
            <a href="<?= htmlspecialchars($apiUrl) ?>?type=departures" target="_blank"><?= htmlspecialchars($apiUrl) ?>?type=departures</a>
        </p>
        <p><strong>Проверка:</strong> DevTools → Network → выберите запрос к <code>tourvisor-proxy.php</code> → Headers → <code>X-Tourvisor-Cache</code>, <code>X-Tourvisor-Token</code>, <code>X-Tourvisor-Success</code></p>
    </div>

    <h2>3. Почему API не отвечает (ERR_CONNECTION_REFUSED)</h2>
    <div class="block">
        <ul>
            <li><strong>Сервер не запущен</strong> — убедитесь, что PHP/веб-сервер работает на порту <?= $_SERVER['SERVER_PORT'] ?? '—' ?>.</li>
            <li><strong>Другой порт</strong> — если сайт открывается, например, на <code>localhost:8000</code>, API вызывается с тем же хостом. Проверьте, что запросы идут на тот же адрес.</li>
            <li><strong>CORS</strong> — прокси уже возвращает <code>Access-Control-Allow-Origin: *</code>.</li>
        </ul>
        <p>Проверьте лог API: <code>data/tourvisor_api.log</code></p>
    </div>

    <h2>4. Быстрая проверка API</h2>
    <div class="block">
        <p>Откройте в браузере:</p>
        <pre><?= htmlspecialchars($apiUrl) ?>?type=departures</pre>
        <p>Ожидается JSON: <code>{"success":true,"data":[...]}</code>. Если видите «подключение отклонено» — сервер не запущен или порт другой.</p>
    </div>

    <p class="meta" style="margin-top: 2rem;">На продакшене держите DEBUG_PAGE_ENABLED=0 в .env или удалите этот файл.</p>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>
