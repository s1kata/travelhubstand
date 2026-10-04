/**
 * Главная: блок «Хотите дешевле?» — промокод один раз на человека.
 */
(function (global) {
  'use strict';

  var LS_KEY = 'th_promo_claim_done';

  function lsGet() {
    try {
      var raw = localStorage.getItem(LS_KEY);
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      return parsed && parsed.code ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  function lsSet(data) {
    try { localStorage.setItem(LS_KEY, JSON.stringify(data)); } catch (e) {}
  }

  function currentOffer() {
    var code = 'TRAVEL10';
    var pct = 10;
    try {
      if (global.TH_PROMO && global.TH_PROMO.promoCode) {
        code = String(global.TH_PROMO.promoCode).toUpperCase();
        pct = parseInt(global.TH_PROMO.discount, 10) || pct;
      }
    } catch (e1) {}
    if (global.TH_PROMO_APPLY && typeof global.TH_PROMO_APPLY.getPromoPct === 'function') {
      var p10 = global.TH_PROMO_APPLY.getPromoPct('TRAVEL10') || 0;
      var p5 = global.TH_PROMO_APPLY.getPromoPct('TRAVEL5') || 0;
      var livePct = global.TH_PROMO_APPLY.getPromoPct(code) || 0;
      if (livePct > 0) {
        pct = livePct;
      } else if (p5 > 0 && (!p10 || p5 <= p10)) {
        code = 'TRAVEL5';
        pct = p5;
      } else if (p10 > 0) {
        code = 'TRAVEL10';
        pct = p10;
      }
    }
    if (!pct) {
      code = 'TRAVEL10';
      pct = 10;
    }
    return { code: code, pct: pct };
  }

  function paintDone(saved) {
    var btn = document.getElementById('th-want-cheaper-btn');
    var done = document.getElementById('th-want-cheaper-done');
    var codeEl = document.getElementById('th-want-cheaper-code');
    if (!saved) return;
    if (btn) btn.classList.add('hidden');
    if (done) {
      done.hidden = false;
      done.classList.remove('hidden');
    }
    if (codeEl) {
      codeEl.textContent = saved.code + ' (−' + String(saved.pct) + '%)';
    }
  }

  function ensureModal() {
    var el = document.getElementById('th-promo-claim-modal');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'th-promo-claim-modal';
    el.className = 'th-promo-claim-modal hidden';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.innerHTML =
      '<div class="th-promo-claim-modal__backdrop" data-th-promo-claim-close></div>' +
      '<div class="th-promo-claim-modal__panel">' +
        '<button type="button" class="th-promo-claim-modal__close" data-th-promo-claim-close aria-label="Закрыть">&times;</button>' +
        '<h3 class="th-promo-claim-modal__title">Получить промокод</h3>' +
        '<p class="th-promo-claim-modal__sub">Оставьте имя и телефон — покажем действующую скидку.</p>' +
        '<form id="th-promo-claim-form" class="th-promo-claim-modal__form">' +
          '<label class="th-promo-claim-modal__field">Имя' +
            '<input type="text" name="name" required autocomplete="name" placeholder="Анна">' +
          '</label>' +
          '<label class="th-promo-claim-modal__field">Телефон' +
            '<input type="tel" name="phone" required autocomplete="tel" placeholder="+7 (___) ___-__-__">' +
          '</label>' +
          '<label class="th-promo-claim-modal__agree">' +
            '<input type="checkbox" name="agree" required>' +
            '<span>Согласен на <a href="/frontend/window/consent.php" target="_blank" rel="noopener">обработку персональных данных</a></span>' +
          '</label>' +
          '<input type="text" name="website" tabindex="-1" autocomplete="off" class="th-promo-claim-modal__hp">' +
          '<p class="th-promo-claim-modal__msg hidden" id="th-promo-claim-msg"></p>' +
          '<button type="submit" class="th-promo-claim-modal__submit">Получить промокод</button>' +
        '</form>' +
      '</div>';
    document.body.appendChild(el);
    el.addEventListener('click', function (e) {
      if (e.target && e.target.closest && e.target.closest('[data-th-promo-claim-close]')) {
        closeModal();
      }
    });
    var form = document.getElementById('th-promo-claim-form');
    var phone = form && form.querySelector('[name="phone"]');
    if (phone && global.THLeadCapture && global.THLeadCapture.formatPhoneInput) {
      global.THLeadCapture.formatPhoneInput(phone);
    }
    if (form) form.addEventListener('submit', onSubmit);
    return el;
  }

  function openModal() {
    if (lsGet()) return;
    var el = ensureModal();
    el.classList.remove('hidden');
    document.body.classList.add('th-promo-claim-open');
    var name = el.querySelector('[name="name"]');
    if (name) window.setTimeout(function () { name.focus(); }, 50);
  }

  function closeModal() {
    var el = document.getElementById('th-promo-claim-modal');
    if (el) el.classList.add('hidden');
    document.body.classList.remove('th-promo-claim-open');
  }

  function showMsg(text, ok) {
    var msg = document.getElementById('th-promo-claim-msg');
    if (!msg) return;
    msg.textContent = text || '';
    msg.classList.remove('hidden');
    msg.classList.toggle('is-ok', !!ok);
  }

  function onSubmit(e) {
    e.preventDefault();
    if (lsGet()) {
      showMsg('Промокод уже получен', true);
      return;
    }
    var form = e.target;
    var fd = new FormData(form);
    var name = String(fd.get('name') || '').trim();
    var phone = String(fd.get('phone') || '').trim();
    var agree = !!fd.get('agree');
    var website = String(fd.get('website') || '');
    var offer = currentOffer();
    var submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    var send = (global.THLeadCapture && global.THLeadCapture.submit)
      ? global.THLeadCapture.submit({
          name: name,
          phone: phone,
          agree: agree,
          website: website,
          source: 'promo_claim',
          phoneOnly: false,
          message: 'Заявка на промокод ' + offer.code + ' (−' + offer.pct + '%)'
        })
      : Promise.resolve({ success: false, error: 'Модуль заявки не загружен' });
    send.then(function (data) {
      if (data && data.success) {
        var saved = { code: offer.code, pct: offer.pct, ts: Date.now() };
        lsSet(saved);
        if (global.TH_PROMO_APPLY && typeof global.TH_PROMO_APPLY.setPendingCode === 'function') {
          global.TH_PROMO_APPLY.setPendingCode(offer.code);
        }
        paintDone(saved);
        showMsg('Ваш промокод: ' + offer.code + ' (−' + offer.pct + '%)', true);
        window.setTimeout(closeModal, 1600);
      } else {
        showMsg((data && data.error) || 'Не удалось отправить. Проверьте имя и телефон.', false);
      }
    }).finally(function () {
      if (submit) submit.disabled = false;
    });
  }

  function init() {
    var root = document.getElementById('th-want-cheaper-btn');
    if (!root && !document.querySelector('.th-want-cheaper')) return;
    var saved = lsGet();
    if (saved) paintDone(saved);
    var btn = document.getElementById('th-want-cheaper-btn');
    if (btn) {
      btn.addEventListener('click', function () {
        if (lsGet()) {
          paintDone(lsGet());
          return;
        }
        openModal();
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : this);
