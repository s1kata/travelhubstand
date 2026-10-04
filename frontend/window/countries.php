<?php
/** Редirect: старый URL → актуальный список стран. */
declare(strict_types=1);
header('Location: /frontend/window/countries-list.php', true, 301);
exit;
