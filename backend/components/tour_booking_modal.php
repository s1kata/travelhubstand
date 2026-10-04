<?php
declare(strict_types=1);
if (!function_exists('security_csrf_token')) {
    require_once __DIR__ . '/security_helper.php';
}
$th_booking_csrf = security_csrf_token();
?>
<div id="th-tour-booking-modal" class="th-tour-booking-modal" style="display:none" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="th-tour-booking-title">
    <div class="th-tour-booking-modal__backdrop" data-th-booking-close></div>
    <div class="th-tour-booking-modal__panel">
        <div class="th-tour-booking-modal__head">
            <h2 id="th-tour-booking-title" class="th-tour-booking-modal__title">Заявка на тур</h2>
            <button type="button" class="th-tour-booking-modal__close" data-th-booking-close aria-label="Закрыть">&times;</button>
        </div>
        <p id="th-tour-booking-summary" class="th-tour-booking-modal__summary"></p>
        <form id="th-tour-booking-form" class="th-tour-booking-modal__form">
            <input type="hidden" name="booking_type" value="without_payment">
            <label class="th-tour-booking-modal__lbl">ФИО <span class="th-tour-booking-modal__req">*</span>
                <input type="text" name="name" id="th-tb-name" autocomplete="name" required maxlength="100" class="th-tour-booking-modal__input" placeholder="Иванов Иван Иванович">
            </label>
            <label class="th-tour-booking-modal__lbl">Телефон <span class="th-tour-booking-modal__req">*</span>
                <input type="tel" name="phone" id="th-tb-phone" autocomplete="tel" required class="th-tour-booking-modal__input" placeholder="+7 (999) 123-45-67">
            </label>
            <label class="th-tour-booking-modal__agree">
                <input type="checkbox" id="th-tb-agree" required>
                <span><?php require_once __DIR__ . '/legal_consent_label.php'; echo th_legal_consent_checkbox_html(); ?></span>
            </label>
            <p id="th-tour-booking-msg" class="th-tour-booking-modal__msg hidden"></p>
            <button type="submit" id="th-tb-submit" class="th-tour-card__btn th-tour-card__btn--lead th-tour-booking-modal__submit">Отправить заявку</button>
        </form>
    </div>
</div>
