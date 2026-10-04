<?php
/**
 * Компонент для подключения скриптов оптимизации производительности
 * Подключается перед закрывающим тегом </body>
 */
?>
<!-- Firebase: App + Analytics (проект qqqwe12-d2a5d) -->
<?php include __DIR__ . '/firebase_scripts.php'; ?>

<!-- Performance Optimization Scripts -->
<script src="/frontend/js/performance.js" defer></script>

<!-- Preconnect только для реально используемых доменов -->
<link rel="dns-prefetch" href="https://api.tourvisor.ru">
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="dns-prefetch" href="https://uon.u-on.ru">

<!-- Resource Hints для критических ресурсов -->
<?php if (isset($critical_images) && is_array($critical_images)): ?>
    <?php foreach ($critical_images as $img): ?>
        <link rel="preload" as="image" href="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" fetchpriority="high">
    <?php endforeach; ?>
<?php endif; ?>
