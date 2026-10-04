<?php
/**
 * Ранний детект браузера + подключение финального mobile CSS.
 * Подключать в <head> после mobile-adult / yandex-mobile.
 */
if (!defined('TH_MOBILE_SITE_ASSETS')) {
    define('TH_MOBILE_SITE_ASSETS', true);
    echo '<script src="/frontend/js/th-browser-detect.js?v=2"></script>' . "\n";
    echo '<link rel="stylesheet" href="/frontend/css/mobile-site.css?v=21">' . "\n";
}
