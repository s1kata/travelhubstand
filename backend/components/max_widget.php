<?php
/**
 * Плавающий MAX-виджет (канал MAX + обратный звонок). Подключается в конце body (например из footer.php).
 */
if (!defined('TRAVELHUB_MAX_WIDGET')) {
    define('TRAVELHUB_MAX_WIDGET', true);
}
if (!defined('TRAVELHUB_MAX_BOT_URL')) {
    require_once __DIR__ . '/../config/config.php';
}
$mwConfigPath = __DIR__ . '/max_widget_config.json';
if (!is_readable($mwConfigPath)) {
    return;
}
$mwCfg = json_decode((string) file_get_contents($mwConfigPath), true);
if (!is_array($mwCfg)) {
    return;
}
if (!empty($mwCfg['channels']) && is_array($mwCfg['channels'])) {
    foreach ($mwCfg['channels'] as $i => $ch) {
        if (is_array($ch) && ($ch['type'] ?? '') === 'max') {
            $mwCfg['channels'][$i]['url'] = TRAVELHUB_MAX_BOT_URL;
            break;
        }
    }
}
?>
<script>
window.MAX_WIDGET_CONFIG = <?php echo json_encode($mwCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php
$_mw_bs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'max-widget-bootstrap.js';
$_mw_bs_ver = is_readable($_mw_bs) ? (string) filemtime($_mw_bs) : '1';
?>
<script src="/frontend/js/max-widget-bootstrap.js?v=<?php echo htmlspecialchars($_mw_bs_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
