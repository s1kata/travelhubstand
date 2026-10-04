<?php
declare(strict_types=1);

// Legacy entrypoint shim: route all sync traffic to the hardened endpoint.
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'sync.php';
