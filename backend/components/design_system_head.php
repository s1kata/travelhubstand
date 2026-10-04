<?php
/**
 * Полный CSS/JS стек Travel Hub v2.
 * Подключать в <head> — заменяет mobile_stack_head и дубли responsive.css.
 * Если responsive уже подключён вручную — не дублируем.
 */
if (!defined('TRAVELHUB_DS_HEAD')) {
    define('TRAVELHUB_DS_HEAD', true);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Work+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/frontend/css/tokens.css?v=3">
<?php include __DIR__ . '/tailwind_css.php'; ?>
<?php if (!defined('TH_RESPONSIVE_LOADED')): ?>
<link rel="stylesheet" href="/frontend/css/responsive.css?v=17">
<?php endif; ?>
<link rel="stylesheet" href="/frontend/css/design-system.css?v=16">
<link rel="stylesheet" href="/frontend/css/redesign.css?v=41">
<link rel="stylesheet" href="/frontend/css/v2-theme.css?v=5">
<link rel="stylesheet" href="/frontend/css/tour-search-wizard.css?v=2">
<link rel="stylesheet" href="/frontend/css/th-hard-funnel.css?v=8">
<link rel="stylesheet" href="/frontend/css/mobile-adult.css?v=16">
<link rel="stylesheet" href="/frontend/css/th-site-lead.css?v=11">
<link rel="stylesheet" href="/frontend/css/yandex-mobile.css?v=11">
<link rel="stylesheet" href="/frontend/css/th-sheet.css?v=2">
<?php include __DIR__ . '/mobile_site_head.php'; ?>
<link rel="stylesheet" href="/frontend/css/th-unified-ui.css?v=12">
<link rel="stylesheet" href="/frontend/css/support-chat.css?v=9">
<script src="/frontend/js/v2-theme.js?v=1" defer></script>
<?php
    if (!defined('TH_LEAD_CAPTURE_JS')) {
        define('TH_LEAD_CAPTURE_JS', true);
        echo '<script src="/frontend/js/th-lead-capture.js?v=5" defer></script>' . "\n";
    }
    if (!defined('TH_SITE_LEAD_CSS')) {
        define('TH_SITE_LEAD_CSS', true);
    }
?>
<script src="/frontend/js/th-mobile.js?v=16" defer></script>
<script src="/frontend/js/th-modal.js?v=2" defer></script>
<script src="/frontend/js/th-gallery.js?v=1" defer></script>
<script src="/frontend/js/support-chat-widget.js?v=4" defer></script>
<?php
}

/* Обратная совместимость: mobile_stack_head = design_system_head */
if (!defined('TH_MOBILE_STACK')) {
    define('TH_MOBILE_STACK', true);
}
