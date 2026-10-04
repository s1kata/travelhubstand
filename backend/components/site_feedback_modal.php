<?php
/**
 * Модальное окно «Обратная связь» — POST в /backend/api/uon-lead.php (U-ON CRM).
 * Открытие: элементы с data-th-site-feedback (клик).
 */
?>
<div id="th-site-feedback-overlay" class="th-sf-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="th-sf-title">
    <div class="th-sf-backdrop" data-th-sf-close="1"></div>
    <div class="th-sf-card">
        <button type="button" class="th-sf-x" data-th-sf-close="1" aria-label="Закрыть">&times;</button>
        <h2 id="th-sf-title" class="th-sf-title">Обратная связь</h2>
        <p class="th-sf-sub">Оставьте телефон — перезвоним за 15 минут. Без спама.</p>
        <form id="th-site-feedback-form" class="th-sf-form">
            <div class="th-sf-field">
                <label for="th-sf-name">ФИО <span class="text-red-500">*</span></label>
                <input type="text" id="th-sf-name" name="name" required maxlength="100" autocomplete="name" class="th-sf-input" placeholder="Иванов Иван Иванович">
            </div>
            <div class="th-sf-field">
                <label for="th-sf-phone">Телефон <span class="text-red-500">*</span></label>
                <input type="tel" id="th-sf-phone" name="phone" required maxlength="20" autocomplete="tel" class="th-sf-input" placeholder="+7">
            </div>
            <div class="th-sf-field">
                <label for="th-sf-email">Email</label>
                <input type="email" id="th-sf-email" name="email" maxlength="120" autocomplete="email" class="th-sf-input">
            </div>
            <div class="th-sf-field">
                <label for="th-sf-comment">Комментарий</label>
                <textarea id="th-sf-comment" name="message" rows="4" maxlength="1000" class="th-sf-input th-sf-textarea"></textarea>
            </div>
            <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
                <label for="th-sf-website">Сайт</label>
                <input type="text" id="th-sf-website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <label class="th-sf-agree flex items-start gap-2 cursor-pointer text-sm text-slate-600">
                <input type="checkbox" id="th-sf-agree" name="agree" required class="mt-1 rounded border-slate-300">
                <span><?php require_once __DIR__ . '/legal_consent_label.php'; echo th_legal_consent_checkbox_html(); ?></span>
            </label>
            <p id="th-sf-msg" class="th-sf-msg hidden text-sm"></p>
            <button type="submit" id="th-sf-submit" class="th-sf-submit">Отправить</button>
        </form>
    </div>
</div>
<style>
.th-sf-overlay { position: fixed; inset: 0; z-index: 100050; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.th-sf-overlay.hidden { display: none !important; }
.th-sf-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,.55); backdrop-filter: blur(6px); }
.th-sf-card { position: relative; z-index: 1; width: 100%; max-width: 26rem; max-height: min(90vh, 640px); overflow-y: auto; background: #fff; border-radius: 1.25rem; padding: 1.5rem 1.35rem 1.25rem; box-shadow: 0 25px 50px -12px rgba(15,23,42,.35); border: 1px solid #e2e8f0; }
.th-sf-x { position: absolute; top: 0.65rem; right: 0.75rem; width: 2.25rem; height: 2.25rem; border: none; background: transparent; font-size: 1.5rem; line-height: 1; color: #64748b; cursor: pointer; border-radius: 0.5rem; }
.th-sf-x:hover { color: #0f172a; background: #f1f5f9; }
.th-sf-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 2rem 0.35rem 0; }
.th-sf-sub { font-size: 0.875rem; color: #64748b; margin: 0 0 1rem; }
.th-sf-field { margin-bottom: 0.85rem; }
.th-sf-field label { display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem; }
.th-sf-input { width: 100%; box-sizing: border-box; padding: 0.65rem 0.85rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; font-size: 0.95rem; }
.th-sf-input:focus { outline: none; border-color: #5DA9A4; box-shadow: 0 0 0 3px rgba(93,169,164,.2); }
.th-sf-textarea { resize: vertical; min-height: 5rem; }
.th-sf-msg.th-success { color: #15803d; }
.th-sf-msg.th-error { color: #b91c1c; }
.th-sf-submit { width: 100%; margin-top: 0.75rem; padding: 0.75rem 1rem; border: none; border-radius: 0.85rem; font-weight: 700; font-size: 0.95rem; color: #fff; cursor: pointer; background: linear-gradient(135deg, #0c4a6e 0%, #366360 45%, #5DA9A4 100%); }
.th-sf-submit:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
<script>
(function () {
    'use strict';
    var overlay = document.getElementById('th-site-feedback-overlay');
    if (!overlay) return;
    var form = document.getElementById('th-site-feedback-form');
    var modalState = { source: 'site_feedback', phoneOnly: false };
    var defaultTitle = 'Обратная связь';
    var defaultSub = 'Оставьте контакты — ответим в ближайшее время.';

    function promoYm(goal) {
        try {
            var c = window.__TH_PROMO_PAGE__;
            var id = (c && c.ymId && String(c.ymId).replace(/\D/g, ''))
                ? parseInt(String(c.ymId).replace(/\D/g, ''), 10) : 0;
            if (id && typeof ym === 'function') ym(id, 'reachGoal', goal);
        } catch (e) {}
    }

    function applyModalCopy(opts) {
        var titleEl = document.getElementById('th-sf-title');
        var subEl = overlay.querySelector('.th-sf-sub');
        if (titleEl) titleEl.textContent = (opts && opts.title) ? opts.title : defaultTitle;
        if (subEl) subEl.textContent = (opts && opts.sub) ? opts.sub : defaultSub;
        var commentEl = document.getElementById('th-sf-comment');
        if (commentEl) commentEl.value = (opts && opts.message) ? String(opts.message) : '';
    }

    function open(opts) {
        opts = opts || {};
        modalState.source = opts.source || 'site_feedback';
        /* focusPhone только ставит фокус — не скрывает поле ФИО */
        modalState.phoneOnly = !!opts.phoneOnly;
        applyModalCopy(opts);
        var nameField = document.getElementById('th-sf-name');
        var nameWrap = nameField && nameField.closest('.th-sf-field');
        if (nameField && nameWrap) {
            if (modalState.phoneOnly) {
                nameField.removeAttribute('required');
                nameWrap.style.display = 'none';
            } else {
                nameField.setAttribute('required', 'required');
                nameWrap.style.display = '';
            }
        }
        overlay.classList.remove('hidden');
        if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(true);
        try {
            var phoneEl = document.getElementById('th-sf-phone');
            var nameEl = document.getElementById('th-sf-name');
            var focusEl = (opts.focusPhone && phoneEl) ? phoneEl : nameEl;
            if (focusEl) setTimeout(function () { focusEl.focus(); }, 80);
        } catch (e) {}
    }
    function close() {
        overlay.classList.add('hidden');
        if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(false);
        modalState.source = 'site_feedback';
        modalState.phoneOnly = false;
        applyModalCopy({});
    }
    document.addEventListener('click', function (e) {
        var trigger = e.target && e.target.closest ? e.target.closest('[data-th-site-feedback]') : null;
        if (!trigger) return;
        e.preventDefault();
        open();
    });
    window.openSiteFeedbackModal = open;
    overlay.querySelectorAll('[data-th-sf-close]').forEach(function (el) {
        el.addEventListener('click', function () { close(); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) close();
    });
    if (form) {
        var phoneEl = document.getElementById('th-sf-phone');
        if (window.THLeadCapture && phoneEl) THLeadCapture.formatPhoneInput(phoneEl);
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = document.getElementById('th-sf-msg');
            var btn = document.getElementById('th-sf-submit');
            var nameEl = document.getElementById('th-sf-name');
            var phoneEl2 = document.getElementById('th-sf-phone');
            var emailEl = document.getElementById('th-sf-email');
            var name = ((nameEl || {}).value || '').trim();
            var phone = ((phoneEl2 || {}).value || '').trim();
            if (!name && modalState.phoneOnly) name = 'Клиент сайта';
            var email = ((emailEl || {}).value || '').trim();
            var message = ((document.getElementById('th-sf-comment') || {}).value || '').trim();
            var agree = !!(document.getElementById('th-sf-agree') || {}).checked;
            var website = ((document.getElementById('th-sf-website') || {}).value || '');
            btn.disabled = true;
            if (msg) msg.classList.add('hidden');

            function markField(el, bad) {
                if (!el) return;
                el.style.borderColor = bad ? '#f87171' : '';
                el.setAttribute('aria-invalid', bad ? 'true' : 'false');
            }
            markField(nameEl, false);
            markField(phoneEl2, false);
            markField(emailEl, false);

            function finish(data) {
                if (data && data.success) {
                    if (msg) {
                        msg.textContent = data.message || 'Заявка отправлена.';
                        msg.className = 'th-sf-msg th-success';
                        msg.classList.remove('hidden');
                    }
                    form.reset();
                    setTimeout(close, 900);
                } else {
                    if (msg) {
                        msg.textContent = (data && data.error) ? data.error : 'Ошибка отправки';
                        msg.className = 'th-sf-msg th-error';
                        msg.classList.remove('hidden');
                    }
                }
                btn.disabled = false;
            }

            if (window.THLeadCapture && typeof THLeadCapture.validateLeadFields === 'function') {
                var check = THLeadCapture.validateLeadFields({
                    name: name,
                    phone: phone,
                    email: email,
                    agree: agree,
                    phoneOnly: modalState.phoneOnly
                });
                if (!check.ok) {
                    var err = check.error || 'Проверьте данные формы';
                    if (/ФИО/i.test(err)) markField(nameEl, true);
                    if (/телефон/i.test(err)) markField(phoneEl2, true);
                    if (/email/i.test(err)) markField(emailEl, true);
                    finish({ success: false, error: err });
                    return;
                }
                name = check.name;
                phone = check.phone;
                email = check.email;
            } else if (window.THLeadCapture && typeof THLeadCapture.submit === 'function') {
                /* validate внутри submit */
            } else {
                finish({ success: false, error: 'Форма временно недоступна. Позвоните нам.' });
                return;
            }

            if (window.THLeadCapture && typeof THLeadCapture.submit === 'function') {
                THLeadCapture.submit({
                    name: name,
                    phone: phone,
                    agree: agree,
                    email: email,
                    message: message,
                    website: website,
                    source: modalState.source || 'site_feedback',
                    phoneOnly: modalState.phoneOnly
                }).then(function (data) {
                    if (data && data.success && String(modalState.source || '').indexOf('promo_') === 0) {
                        promoYm('promo_lead_submit');
                        promoYm('promo_lead_success');
                    }
                    if (!(data && data.success)) {
                        var err2 = (data && data.error) || '';
                        if (/ФИО/i.test(err2)) markField(nameEl, true);
                        if (/телефон/i.test(err2)) markField(phoneEl2, true);
                        if (/email/i.test(err2)) markField(emailEl, true);
                    }
                    finish(data);
                });
                return;
            }

            finish({ success: false, error: 'Форма временно недоступна. Позвоните нам.' });
        });
    }
})();
</script>
