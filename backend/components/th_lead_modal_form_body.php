<?php
/**
 * Единое тело формы заявки в модалке (как «Заявка на акционный тур»).
 *
 * @var string $th_lm_prefix id_prefix для согласий и полей
 * @var string|null $th_lm_name_id
 * @var string|null $th_lm_phone_id
 * @var string|null $th_lm_msg_id
 * @var bool $th_lm_booking_hidden hidden booking_type
 * @var bool $th_lm_callback_note поле «когда звонить»
 */
declare(strict_types=1);

$th_lm_prefix = isset($th_lm_prefix) ? (string) $th_lm_prefix : 'th-lm';
$th_lm_name_id = isset($th_lm_name_id) ? (string) $th_lm_name_id : ($th_lm_prefix . '-name');
$th_lm_phone_id = isset($th_lm_phone_id) ? (string) $th_lm_phone_id : ($th_lm_prefix . '-phone');
$th_lm_msg_id = isset($th_lm_msg_id) ? (string) $th_lm_msg_id : ($th_lm_prefix . '-msg');
$th_lm_booking_hidden = !empty($th_lm_booking_hidden);
$th_lm_callback_note = !empty($th_lm_callback_note);

/** @var array{modal_phone_hint: string} $_th_lm_copy */
$_th_lm_copy = require dirname(__DIR__) . '/config/lead_copy.php';
require_once __DIR__ . '/legal_consent_label.php';
?>
<?php if ($th_lm_booking_hidden): ?>
<input type="hidden" name="booking_type" value="without_payment">
<?php endif; ?>
<div class="th-qbm-modal__field">
    <label class="th-qbm-modal__lbl" for="<?php echo htmlspecialchars($th_lm_name_id, ENT_QUOTES, 'UTF-8'); ?>">Имя <span class="th-qbm-modal__req">*</span></label>
    <input type="text" name="name" id="<?php echo htmlspecialchars($th_lm_name_id, ENT_QUOTES, 'UTF-8'); ?>" required maxlength="40"
           autocomplete="given-name" placeholder="Иван" class="th-qbm-modal__input">
</div>
<div class="th-qbm-modal__field">
    <label class="th-qbm-modal__lbl" for="<?php echo htmlspecialchars($th_lm_phone_id, ENT_QUOTES, 'UTF-8'); ?>">Номер телефона <span class="th-qbm-modal__req">*</span></label>
    <input type="tel" name="phone" id="<?php echo htmlspecialchars($th_lm_phone_id, ENT_QUOTES, 'UTF-8'); ?>" required
           autocomplete="tel" inputmode="tel" value="+7" placeholder="+7 (999) 123-45-67" class="th-qbm-modal__input">
    <p class="th-qbm-modal__hint"><?php echo htmlspecialchars((string) ($_th_lm_copy['modal_phone_hint'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php if ($th_lm_callback_note): ?>
<input type="text" name="callback_note" maxlength="120" placeholder="Когда удобно звонить? (необязательно)" class="th-qbm-modal__input th-qbm-modal__input--note" autocomplete="off">
<?php endif; ?>
<?php echo th_legal_form_consents_html(['id_prefix' => $th_lm_prefix]); ?>
<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
    <input type="text" name="website" class="th-qbm-modal__hp" tabindex="-1" autocomplete="off">
</div>
