<?php
/**
 * Legacy URL — редирект на карточку офиса из каталога (реальные NAP).
 * Старый stub с телефоном +7 (495) 123-45-67 удалён.
 */
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/offices_catalog.php';

$office = function_exists('th_office_by_slug') ? th_office_by_slug('moscow-coral-elite') : null;
$target = ($office && function_exists('th_office_page_url'))
    ? th_office_page_url('moscow-coral-elite')
    : '/frontend/window/offices/moscow-offices.php';

header('Location: ' . $target, true, 301);
exit;
