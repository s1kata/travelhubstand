<?php
/** Канонический URL регистрации — registration-desktop.php */
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /frontend/window/registration-desktop.php' . $qs, true, 301);
exit;
