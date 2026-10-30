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

  var SUBMIT_LABEL = '✈ Отправить заявку менеджеру';

  function setMsg(text, kind) {
    if (!msgEl) return;
    msgEl.textContent = text || '';
    msgEl.classList.remove('hidden', 'is-visible', 'is-ok', 'is-err');
    if (!text) {
      msgEl.classList.add('hidden');
      msgEl.hidden = true;
      return;
    }
    msgEl.hidden = false;
    msgEl.classList.add('is-visible', kind === 'ok' ? 'is-ok' : 'is-err');
  }

  function openModal(payload) {
    if (!modal) return;
    currentPayload = payload || {};
    if (window.THLeadCapture && window.THLeadCapture.reachGoal) {
      window.THLeadCapture.reachGoal('booking_form_open', { source: 'card_booking' });
    }
    var titleEl = document.getElementById('th-tour-booking-title');
    if (titleEl) {
      var promo = currentPayload.from_promo === '1' || currentPayload.from_promo === 1 || currentPayload.from_promo === true;
      titleEl.textContent = promo ? 'Заявка на акционный тур' : 'Заявка на тур';
    }
    var title = currentPayload.hotel_name || 'тур';
    var loc = [currentPayload.country, currentPayload.region].filter(Boolean).join(', ');
    var summaryText = (loc ? loc + ' — ' : '') + title +
      (currentPayload.price ? ' · ' + currentPayload.price + ' ₽' : '') +
      (currentPayload.nights ? ' · ' + currentPayload.nights + ' ноч.' : '');
    if (summaryEl) {
      if (summaryText && summaryText !== 'тур') {
        summaryEl.textContent = summaryText;
        summaryEl.classList.remove('hidden');
        summaryEl.hidden = false;
      } else {
        summaryEl.textContent = '';
        summaryEl.classList.add('hidden');
        summaryEl.hidden = true;
      }
    }
    setMsg('', '');
    if (form && window.THLeadCapture && window.THLeadCapture.bindLeadInputs) {
      window.THLeadCapture.bindLeadInputs(form);
    }
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(true);
    var nameInp = document.getElementById('th-tb-name');
    var phoneInpOpen = document.getElementById('th-tb-phone');
    if (window.THLeadCapture && window.THLeadCapture.ensurePhoneDefault && phoneInpOpen) {
      window.THLeadCapture.ensurePhoneDefault(phoneInpOpen);
    }
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
    function trackBookingGoal(goal) {
      if (window.THLeadCapture && window.THLeadCapture.reachGoal) {
        window.THLeadCapture.reachGoal(goal, { source: 'card_booking' });
      }
    }
    trackBookingGoal('lead_submit_attempt');
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
        trackBookingGoal('lead_validation_error');
        if (window.THLeadCapture.markLeadFormErrors && form) {
          window.THLeadCapture.markLeadFormErrors(form, check);
        }
        setMsg(check.error || 'Проверьте данные формы', 'err');
        return;
      }
      nameVal = check.name;
      phoneVal = check.phone;
    } else {
      if (!nameVal || nameVal.length < 2 || !/^[\p{L}\s\-'.]+$/u.test(nameVal)) {
        trackBookingGoal('lead_validation_error');
        setMsg('Укажите корректное имя', 'err');
        return;
      }
      var cyr = nameVal.match(/\p{Script=Cyrillic}/gu);
      if (!cyr || cyr.length < 2) {
        trackBookingGoal('lead_validation_error');
        setMsg('Укажите имя русскими буквами', 'err');
        return;
      }
      var phoneDigits = phoneVal.replace(/\D/g, '');
      if (phoneDigits.length === 11 && phoneDigits[0] === '8') phoneDigits = '7' + phoneDigits.slice(1);
      var rest = phoneDigits.slice(1);
      if (phoneDigits.length !== 11 || phoneDigits[0] !== '7' || !rest || rest[0] !== '9' || /^(\d)\1{9}$/.test(rest)) {
        trackBookingGoal('lead_validation_error');
        setMsg('Укажите корректный мобильный телефон РФ (+7 9XX…)', 'err');
        return;
      }
      if (!agreeInp || !agreeInp.checked) {
        trackBookingGoal('lead_validation_error');
        setMsg('Нужно согласие на обработку данных', 'err');
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
      agree_ads: !!(document.getElementById('th-tb-agree-ads') || {}).checked,
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
            window.THLeadCapture.reachGoal('lead_ok', { source: 'card_booking' });
            window.THLeadCapture.reachGoal('card_booking_lead_ok');
          }
          setMsg((window.THLeadCapture && window.THLeadCapture.SUCCESS_MSG)
              || 'Заявка отправлена. Ждите звонок с +7 (846) 254-16-56.', 'ok');
          if (form) {
            form.reset();
            var phoneAfterReset = document.getElementById('th-tb-phone');
            if (window.THLeadCapture && window.THLeadCapture.ensurePhoneDefault && phoneAfterReset) {
              window.THLeadCapture.ensurePhoneDefault(phoneAfterReset);
            }
          }
          setTimeout(closeModal, 2500);
        } else {
          trackBookingGoal('lead_err');
          setMsg((res.data && res.data.error) ? res.data.error : 'Не удалось отправить заявку', 'err');
        }
      })
      .catch(function () {
        trackBookingGoal('lead_err');
        setMsg('Ошибка сети. Попробуйте ещё раз.', 'err');
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = SUBMIT_LABEL;
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

    if (form) {
      form.addEventListener('submit', submitForm);
      if (window.THLeadCapture && window.THLeadCapture.bindLeadInputs) {
        window.THLeadCapture.bindLeadInputs(form);
      }
    }

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
