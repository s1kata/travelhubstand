<?php
/** Legacy alias → VIP отели Турции */
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /frontend/window/turkey-vip-hotels.php' . $qs, true, 301);
exit;
