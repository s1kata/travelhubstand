<?php
declare(strict_types=1);
/** @var array{modal_worktime_sub: string, modal_submit_label: string} $_th_tbm_copy */
$_th_tbm_copy = require dirname(__DIR__) . '/config/lead_copy.php';
$th_lm_prefix = 'th-tb';
$th_lm_name_id = 'th-tb-name';
$th_lm_phone_id = 'th-tb-phone';
$th_lm_msg_id = 'th-tour-booking-msg';
$th_lm_booking_hidden = true;
$th_lm_callback_note = false;
?>
<div id="th-tour-booking-modal" class="th-qbm-modal" style="display:none" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="th-tour-booking-title">
    <div class="th-qbm-modal__backdrop" data-th-booking-close></div>
    <div class="th-qbm-modal__panel">
        <button type="button" class="th-qbm-modal__close" data-th-booking-close aria-label="Закрыть">✕</button>
        <h2 id="th-tour-booking-title" class="th-qbm-modal__title">Заявка на тур</h2>
        <p id="th-tour-booking-summary" class="th-qbm-modal__summary hidden" hidden></p>
        <p class="th-qbm-modal__sub"><?php echo htmlspecialchars((string) ($_th_tbm_copy['modal_worktime_sub'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <form id="th-tour-booking-form" class="th-qbm-modal__form">
            <?php include __DIR__ . '/th_lead_modal_form_body.php'; ?>
            <p id="th-tour-booking-msg" class="th-qbm-modal__msg hidden" hidden></p>
            <button type="submit" id="th-tb-submit" class="th-qbm-modal__submit"><?php echo htmlspecialchars((string) ($_th_tbm_copy['modal_submit_label'] ?? 'Отправить заявку менеджеру'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
    </div>
</div>
