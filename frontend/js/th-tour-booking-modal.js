/**
 * Заявка на тур с карточки (без перехода на tour-detail).
 */
(function () {
  'use strict';

  var modal = null;
  var form = null;
  var summaryEl = null;
  var msgEl = null;
  var currentPayload = null;

  function csrf() {
    return (typeof window.TH_CSRF === 'string' && window.TH_CSRF) ? window.TH_CSRF : '';
  }

  function parsePayload(btn) {
    var raw = btn.getAttribute('data-th-book-tour');
    if (!raw) return null;
    try {
      if (raw.indexOf('%7B') === 0 || raw.indexOf('%') >= 0) {
        try { raw = decodeURIComponent(raw); } catch (eDec) {}
      }
      return JSON.parse(raw);
    } catch (e) { return null; }
  }

  function openModal(payload) {
    if (!modal) return;
    currentPayload = payload || {};
    var title = currentPayload.hotel_name || 'тур';
    var loc = [currentPayload.country, currentPayload.region].filter(Boolean).join(', ');
    if (summaryEl) {
      summaryEl.textContent = (loc ? loc + ' — ' : '') + title +
        (currentPayload.price ? ' · ' + currentPayload.price + ' ₽' : '') +
        (currentPayload.nights ? ' · ' + currentPayload.nights + ' ноч.' : '');
    }
    if (msgEl) {
      msgEl.classList.add('hidden');
      msgEl.textContent = '';
    }
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(true);
    var nameInp = document.getElementById('th-tb-name');
    if (nameInp) try { nameInp.focus(); } catch (e) {}
  }

  function closeModal() {
    if (!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(false);
    currentPayload = null;
  }

  function submitForm(e) {
    e.preventDefault();
    if (!currentPayload) return;
    var nameInp = document.getElementById('th-tb-name');
    var phoneInp = document.getElementById('th-tb-phone');
    var agreeInp = document.getElementById('th-tb-agree');
    var submitBtn = document.getElementById('th-tb-submit');
    var nameVal = (nameInp && nameInp.value || '').trim();
    var phoneVal = (phoneInp && phoneInp.value || '').trim();
    if (window.THLeadCapture && typeof THLeadCapture.validateLeadFields === 'function') {
      var check = THLeadCapture.validateLeadFields({
        name: nameVal,
        phone: phoneVal,
        agree: !!(agreeInp && agreeInp.checked)
      });
      if (!check.ok) {
        if (msgEl) {
          msgEl.textContent = check.error || 'Проверьте данные формы';
          msgEl.className = 'th-tour-booking-modal__msg th-tour-booking-modal__msg--err';
          msgEl.classList.remove('hidden');
        }
        return;
      }
      nameVal = check.name;
      phoneVal = check.phone;
    } else {
      if (!nameVal || nameVal.length < 2 || !/^[\p{L}\s\-'.]+$/u.test(nameVal)) {
        if (msgEl) {
          msgEl.textContent = 'Укажите корректные ФИО';
          msgEl.className = 'th-tour-booking-modal__msg th-tour-booking-modal__msg--err';
          msgEl.classList.remove('hidden');
        }
        return;
      }
      var cyr = nameVal.match(/\p{Script=Cyrillic}/gu);
      if (!cyr || cyr.length < 2) {
        if (msgEl) {
          msgEl.textContent = 'Укажите ФИО русскими буквами';
          msgEl.className = 'th-tour-booking-modal__msg th-tour-booking-modal__msg--err';
          msgEl.classList.remove('hidden');
        }
        return;
      }
      var phoneDigits = phoneVal.replace(/\D/g, '');
      if (phoneDigits.length === 11 && phoneDigits[0] === '8') phoneDigits = '7' + phoneDigits.slice(1);
      var rest = phoneDigits.slice(1);
      if (phoneDigits.length !== 11 || phoneDigits[0] !== '7' || !rest || rest[0] !== '9' || /^(\d)\1{9}$/.test(rest)) {
        if (msgEl) {
          msgEl.textContent = 'Укажите корректный мобильный телефон РФ (+7 9XX…)';
          msgEl.className = 'th-tour-booking-modal__msg th-tour-booking-modal__msg--err';
          msgEl.classList.remove('hidden');
        }
        return;
      }
      if (!agreeInp || !agreeInp.checked) {
        if (msgEl) {
          msgEl.textContent = 'Нужно согласие на обработку данных';
          msgEl.className = 'th-tour-booking-modal__msg th-tour-booking-modal__msg--err';
          msgEl.classList.remove('hidden');
        }
        return;
      }
    }
    var p = currentPayload;
    var body = {
      _csrf_token: csrf(),
      booking_type: 'without_payment',
      tour_link: p.tour_link || (p.tour_id ? 'tourvisor:tour:' + p.tour_id : ''),
      tour_id: p.tour_id || undefined,
      country: p.country || '',
      hotel_name: p.hotel_name || '',
      price: p.price || '',
      nights: p.nights || '',
      meal: p.meal || '',
      room_category: p.room_category || 'Стандарт',
      date_from: p.date_from || undefined,
      date_to: p.date_to || undefined,
      name: nameVal,
      phone: phoneVal,
      agree: true,
      departure_city: p.departure_city || 'Самара',
      search_adults: p.adults ? parseInt(String(p.adults), 10) : 2
    };
    if (p.from_promo === '1') body.applied_promo = 'PROMO_PAGE';

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Отправка…';
    }
    fetch('/backend/api/uon-booking.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
      .then(function (r) { return r.text().then(function (t) {
        try { return { ok: r.ok, data: JSON.parse(t) }; } catch (e) { return { ok: false, data: { success: false, error: t || 'Ошибка' } }; }
      }); })
      .then(function (res) {
        if (res.data && res.data.success) {
          if (window.THLeadCapture && window.THLeadCapture.reachGoal) {
            window.THLeadCapture.reachGoal('lead_ok');
            window.THLeadCapture.reachGoal('card_booking_lead_ok');
          }
          if (msgEl) {
            msgEl.textContent = 'Заявка отправлена! Перезвоним в течение 15 минут.';
            msgEl.className = 'th-tour-booking-modal__msg th-tour-booking-modal__msg--ok';
            msgEl.classList.remove('hidden');
          }
          if (form) form.reset();
          setTimeout(closeModal, 2500);
        } else {
          if (msgEl) {
            msgEl.textContent = (res.data && res.data.error) ? res.data.error : 'Не удалось отправить заявку';
            msgEl.className = 'th-tour-booking-modal__msg th-tour-booking-modal__msg--err';
            msgEl.classList.remove('hidden');
          }
        }
      })
      .catch(function () {
        if (msgEl) {
          msgEl.textContent = 'Ошибка сети. Попробуйте ещё раз.';
          msgEl.className = 'th-tour-booking-modal__msg th-tour-booking-modal__msg--err';
          msgEl.classList.remove('hidden');
        }
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Отправить заявку';
        }
      });
  }

  function init() {
    modal = document.getElementById('th-tour-booking-modal');
    form = document.getElementById('th-tour-booking-form');
    summaryEl = document.getElementById('th-tour-booking-summary');
    msgEl = document.getElementById('th-tour-booking-msg');
    if (!modal) return;

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-th-book-tour]');
      if (btn) {
        e.preventDefault();
        e.stopPropagation();
        var payload = parsePayload(btn);
        if (payload) openModal(payload);
        return;
      }
      if (e.target.closest('[data-th-booking-close]')) {
        closeModal();
      }
    });

    if (form) form.addEventListener('submit', submitForm);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal && modal.style.display !== 'none') closeModal();
    });
  }

  window.THTourBookingModal = { open: openModal, close: closeModal };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
