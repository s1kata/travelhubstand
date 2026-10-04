<?php
/**
 * Единый LeadCapture для жёсткой воронки.
 *
 * Опции (перед include):
 *   $th_lead_id       — id формы (default: th-lead-form)
 *   $th_lead_source   — funnel source для CRM/analytics
 *   $th_lead_title    — заголовок (опц.)
 *   $th_lead_sub      — подзаголовок (опц.)
 *   $th_lead_submit   — текст кнопки
 *   $th_lead_show_msg — показывать textarea сообщения (default false)
 *   $th_lead_compact  — компактный вид
 */
declare(strict_types=1);

$th_lead_id = isset($th_lead_id) ? (string) $th_lead_id : 'th-lead-form';
$th_lead_source = isset($th_lead_source) ? (string) $th_lead_source : 'site';
$th_lead_title = isset($th_lead_title) ? (string) $th_lead_title : '';
$th_lead_sub = isset($th_lead_sub) ? (string) $th_lead_sub : 'Перезвоним в течение 15 минут. Без спама.';
$th_lead_submit = isset($th_lead_submit) ? (string) $th_lead_submit : 'Отправить заявку';
$th_lead_show_msg = !empty($th_lead_show_msg);
$th_lead_compact = !empty($th_lead_compact);
$th_lead_phone_only = !empty($th_lead_phone_only);
$th_lead_social_proof = !isset($th_lead_social_proof) ? true : !empty($th_lead_social_proof);
$th_lead_msg_id = $th_lead_id . '-msg';
$name_id = $th_lead_id . '-name';
$phone_id = $th_lead_id . '-phone';
$agree_id = $th_lead_id . '-agree';
?>
<div class="th-lead-capture<?php echo $th_lead_compact ? ' th-lead-capture--compact' : ''; ?>" data-th-lead-wrap="<?php echo htmlspecialchars($th_lead_source, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($th_lead_title !== ''): ?>
    <h3 class="th-lead-capture__title heading-font"><?php echo htmlspecialchars($th_lead_title, ENT_QUOTES, 'UTF-8'); ?></h3>
    <?php endif; ?>
    <?php if ($th_lead_sub !== ''): ?>
    <p class="th-lead-capture__sub"><?php echo htmlspecialchars($th_lead_sub, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($th_lead_social_proof): ?>
    <ul class="th-lead-capture__proof" aria-label="Гарантии">
        <li><i class="fas fa-clock" aria-hidden="true"></i> Ответ за 15 минут</li>
        <li><i class="fas fa-shield-alt" aria-hidden="true"></i> Без спама</li>
    </ul>
    <?php endif; ?>
    <form id="<?php echo htmlspecialchars($th_lead_id, ENT_QUOTES, 'UTF-8'); ?>"
          class="th-lead-capture__form"
          data-th-lead
          data-th-lead-source="<?php echo htmlspecialchars($th_lead_source, ENT_QUOTES, 'UTF-8'); ?>"
          data-th-lead-msg="<?php echo htmlspecialchars($th_lead_msg_id, ENT_QUOTES, 'UTF-8'); ?>"
          <?php if ($th_lead_phone_only): ?>data-th-lead-phone-only="1"<?php endif; ?>>
        <?php if (!$th_lead_phone_only): ?>
        <div class="th-lead-capture__row">
            <label class="th-lead-capture__lbl" for="<?php echo htmlspecialchars($name_id, ENT_QUOTES, 'UTF-8'); ?>">ФИО</label>
            <input type="text" id="<?php echo htmlspecialchars($name_id, ENT_QUOTES, 'UTF-8'); ?>" name="name" required maxlength="100"
                   autocomplete="name" placeholder="Иванов Иван Иванович" class="th-lead-capture__input">
        </div>
        <?php else: ?>
        <input type="hidden" name="name" value="Клиент сайта">
        <?php endif; ?>
        <div class="th-lead-capture__row">
            <label class="th-lead-capture__lbl" for="<?php echo htmlspecialchars($phone_id, ENT_QUOTES, 'UTF-8'); ?>">Телефон</label>
            <input type="tel" id="<?php echo htmlspecialchars($phone_id, ENT_QUOTES, 'UTF-8'); ?>" name="phone" required
                   autocomplete="tel" inputmode="tel" placeholder="+7 (___) ___-__-__" class="th-lead-capture__input">
        </div>
        <?php if ($th_lead_show_msg): ?>
        <div class="th-lead-capture__row">
            <label class="th-lead-capture__lbl" for="<?php echo htmlspecialchars($th_lead_id, ENT_QUOTES, 'UTF-8'); ?>-message">Пожелания</label>
            <textarea id="<?php echo htmlspecialchars($th_lead_id, ENT_QUOTES, 'UTF-8'); ?>-message" name="message" rows="3" maxlength="1000"
                      placeholder="Необязательно" class="th-lead-capture__input th-lead-capture__textarea"></textarea>
        </div>
        <?php endif; ?>
        <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
            <label for="<?php echo htmlspecialchars($th_lead_id, ENT_QUOTES, 'UTF-8'); ?>-website">Сайт</label>
            <input type="text" id="<?php echo htmlspecialchars($th_lead_id, ENT_QUOTES, 'UTF-8'); ?>-website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <label class="th-lead-capture__agree">
            <input type="checkbox" id="<?php echo htmlspecialchars($agree_id, ENT_QUOTES, 'UTF-8'); ?>" name="agree" required>
            <span><?php require_once __DIR__ . '/legal_consent_label.php'; echo th_legal_consent_checkbox_html(); ?></span>
        </label>
        <div id="<?php echo htmlspecialchars($th_lead_msg_id, ENT_QUOTES, 'UTF-8'); ?>" class="th-lead-capture__msg hidden" data-th-lead-msg hidden></div>
        <button type="submit" class="th-lead-capture__submit"><?php echo htmlspecialchars($th_lead_submit, ENT_QUOTES, 'UTF-8'); ?></button>
    </form>
</div>
<style>
.th-lead-capture { max-width: 28rem; margin-left: auto; margin-right: auto; width: 100%; }
.th-lead-capture__title { font-size: 1.35rem; font-weight: 700; color: #1A1A40; margin: 0 0 0.35rem; }
.th-lead-capture__sub { font-size: 0.9rem; color: #64748b; margin: 0 0 1rem; line-height: 1.4; }
.th-lead-capture__form { display: flex; flex-direction: column; gap: 0.75rem; position: relative; }
.th-lead-capture__lbl { display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em; }
.th-lead-capture__input {
    width: 100%; padding: 0.85rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 12px;
    font-size: 1rem; color: #1e293b; background: #fff; box-sizing: border-box;
}
.th-lead-capture__input:focus { outline: none; border-color: #5DA9A4; box-shadow: 0 0 0 3px rgba(93,169,164,0.18); }
.th-lead-capture__textarea { resize: vertical; min-height: 4.5rem; }
.th-lead-capture__agree { display: flex; align-items: flex-start; gap: 0.55rem; font-size: 0.8125rem; color: #64748b; cursor: pointer; }
.th-lead-capture__agree input { margin-top: 0.15rem; accent-color: #FF6B6B; }
.th-lead-capture__agree a { color: #FF6B6B; text-decoration: underline; }
.th-lead-capture__submit {
    width: 100%; min-height: 52px; border: none; border-radius: 12px; cursor: pointer;
    background: #FF6B6B; color: #fff; font-weight: 700; font-size: 1rem;
    box-shadow: 0 6px 18px rgba(255,107,107,0.32);
}
.th-lead-capture__submit:hover { background: #f65252; }
.th-lead-capture__submit:disabled { opacity: 0.65; cursor: not-allowed; }
.th-lead-capture__msg { font-size: 0.875rem; padding: 0.65rem 0.85rem; border-radius: 10px; }
.th-lead-capture__msg.hidden, .th-lead-capture__msg[hidden] { display: none !important; }
.th-lead-capture__msg.th-lead-msg--ok, .th-lead-capture__msg:not(.hidden):not([hidden]) { display: block; }
</style>
<?php
// Сброс опций, чтобы следующий include не унаследовал значения
unset($th_lead_id, $th_lead_source, $th_lead_title, $th_lead_sub, $th_lead_submit, $th_lead_show_msg, $th_lead_compact, $th_lead_phone_only, $th_lead_social_proof);
?>
