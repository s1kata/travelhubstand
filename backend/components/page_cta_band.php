<?php
/**
 * Конверсионный блок для контентных страниц.
 *
 * Опции перед include:
 *   $th_cta_source  — funnel_source (default: page_cta)
 *   $th_cta_title   — заголовок
 *   $th_cta_sub     — подзаголовок
 *   $th_cta_id      — id секции / формы
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/contacts.php';
$thc = th_contacts();

$th_cta_source = isset($th_cta_source) ? (string) $th_cta_source : 'page_cta';
$th_cta_title = isset($th_cta_title) ? (string) $th_cta_title : 'Подберём тур под ваш бюджет за 15 минут';
$th_cta_sub = isset($th_cta_sub) ? (string) $th_cta_sub : 'Оставьте телефон — менеджер перезвонит и пришлёт 2–3 варианта без спама.';
$th_cta_id = isset($th_cta_id) ? (string) $th_cta_id : 'th-page-cta';

if (!defined('TH_SITE_LEAD_CSS')) {
    define('TH_SITE_LEAD_CSS', true);
    $_th_sl_css = dirname(__DIR__, 2) . '/frontend/css/th-site-lead.css';
    $_th_sl_v = is_file($_th_sl_css) ? (string) filemtime($_th_sl_css) : '1';
    echo '<link rel="stylesheet" href="/frontend/css/th-site-lead.css?v=' . htmlspecialchars($_th_sl_v, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
?>
<section class="th-page-cta" id="<?php echo htmlspecialchars($th_cta_id, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="th-page-cta__inner">
        <div>
            <h2 class="th-page-cta__title"><?php echo htmlspecialchars($th_cta_title, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="th-page-cta__sub"><?php echo htmlspecialchars($th_cta_sub, ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="th-page-cta__actions">
                <a class="th-page-cta__btn-primary" href="#<?php echo htmlspecialchars($th_cta_id, ENT_QUOTES, 'UTF-8'); ?>-form">
                    Оставить заявку
                </a>
                <a class="th-page-cta__btn-wa th-page-cta__btn-max"
                   href="<?php echo htmlspecialchars($thc['max_url'], ENT_QUOTES, 'UTF-8'); ?>"
                   target="_blank" rel="noopener noreferrer">
                    MAX
                </a>
                <a class="th-page-cta__btn-call"
                   href="tel:<?php echo htmlspecialchars($thc['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-phone" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($thc['phone_display'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
            <div class="th-page-cta__trust">
                <span><i class="fas fa-check-circle" aria-hidden="true"></i> Ответ за 15 минут</span>
                <span><i class="fas fa-shield-alt" aria-hidden="true"></i> Без спама</span>
                <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?php echo htmlspecialchars($thc['address_short'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="th-page-cta__form-wrap" id="<?php echo htmlspecialchars($th_cta_id, ENT_QUOTES, 'UTF-8'); ?>-form">
            <?php
            $th_lead_id = $th_cta_id . '-lead';
            $th_lead_source = $th_cta_source;
            $th_lead_title = 'Быстрая заявка';
            $th_lead_sub = 'Имя и телефон — этого достаточно.';
            $th_lead_submit = 'Жду звонка';
            $th_lead_show_msg = false;
            $th_lead_compact = true;
            include __DIR__ . '/lead_capture.php';
            ?>
        </div>
    </div>
</section>
