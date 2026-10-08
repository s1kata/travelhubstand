/**
 * TravelHub hard funnel — единый lead capture + аналитика.
 * Цель: одна конверсионная цель `lead_ok` на всех поверхностях.
 */
(function (global) {
  'use strict';

  var YM_FALLBACK = 109291068;
  var ENDPOINT = '/backend/api/uon-lead.php';

  function ymId() {
    try {
      if (global.__TH_YM_ID) {
        var n = parseInt(String(global.__TH_YM_ID).replace(/\D/g, ''), 10);
        if (n) return n;
      }
      if (global.__TH_PROMO_PAGE__ && global.__TH_PROMO_PAGE__.ymId) {
        var p = parseInt(String(global.__TH_PROMO_PAGE__.ymId).replace(/\D/g, ''), 10);
        if (p) return p;
      }
    } catch (e) {}
    return YM_FALLBACK;
  }

  function reachGoal(goal, params) {
    try {
      var id = ymId();
      if (id && typeof global.ym === 'function') {
        if (params && typeof params === 'object') global.ym(id, 'reachGoal', goal, params);
        else global.ym(id, 'reachGoal', goal);
      }
    } catch (e) {}
  }

  function pageMeta() {
    var path = '';
    try { path = location.pathname + location.search; } catch (e) {}
    return path;
  }

  function buildMessage(opts) {
    var parts = [];
    if (opts && opts.source) parts.push('Источник: ' + opts.source);
    if (opts && opts.message) parts.push(String(opts.message));
    var url = pageMeta();
    if (url) parts.push('URL: ' + url);
    try {
      var params = new URLSearchParams(location.search);
      var utm = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']
        .map(function (k) { return params.get(k) ? (k + '=' + params.get(k)) : ''; })
        .filter(Boolean);
      if (utm.length) parts.push('UTM: ' + utm.join('&'));
    } catch (e2) {}
    return parts.join('\n');
  }

  function formatPhoneInput(el) {
    if (!el || el.__thPhoneBound) return;
    el.__thPhoneBound = true;
    el.addEventListener('input', function () {
      var v = el.value.replace(/\D/g, '');
      if (v.length > 0 && v[0] === '8') v = '7' + v.slice(1);
      if (v.length > 0 && v[0] !== '7') v = '7' + v;
      if (v.length > 11) v = v.slice(0, 11);
      var formatted = '';
      if (v.length > 0) formatted += '+7';
      if (v.length > 1) formatted += ' (' + v.slice(1, 4);
      if (v.length > 4) formatted += ') ' + v.slice(4, 7);
      if (v.length > 7) formatted += '-' + v.slice(7, 9);
      if (v.length > 9) formatted += '-' + v.slice(9, 11);
      el.value = formatted;
    });
  }

  function digitsOnly(s) {
    return String(s || '').replace(/\D/g, '');
  }

  function isValidRuPhone(phone) {
    var d = digitsOnly(phone);
    if (d.length === 11 && d[0] === '8') d = '7' + d.slice(1);
    if (d.length !== 11 || d[0] !== '7') return false;
    var rest = d.slice(1);
    // Мобильный РФ: +7 9XX… (как lead_validation.php)
    if (!rest || rest[0] !== '9') return false;
    if (/^(\d)\1{9}$/.test(rest)) return false;
    if (/^9(\d)\1{8}$/.test(rest)) return false;
    if (rest === '9012345678' || rest === '9876543210' || rest === '9123456789') return false;
    return true;
  }

  function isValidPersonName(name) {
    var n = String(name || '').trim().replace(/\s+/g, ' ');
    if (n.length < 2 || n.length > 100) return false;
    // Только кириллица — латиница вперемешку («Lion Паша…») отклоняется
    if (!/^[\p{Script=Cyrillic}\s\-'.]+$/u.test(n)) return false;
    var compact = n.replace(/[\s\-'.]/g, '');
    if (compact.length < 2) return false;
    if (/^(\p{L})\1+$/u.test(compact)) return false;
    var cyr = n.match(/\p{Script=Cyrillic}/gu);
    if (!cyr || cyr.length < 2) return false;
    var parts = n.split(/[\s\-]+/).filter(Boolean);
    if (parts.length >= 2) {
      for (var i = 0; i < parts.length; i++) {
        if (parts[i].length < 2) return false;
      }
    }
    var lower = n.toLowerCase();
    var bannedExact = ['тест', 'test', 'asdf', 'qwerty', 'admin', 'user', 'имя', 'фамилия', 'фио', 'xxx', 'null', 'none'];
    if (bannedExact.indexOf(lower) >= 0) return false;
    if (/тест | test|qwerty|asdf|admin|xxx/.test(lower)) return false;
    return true;
  }

  function personNameError(name) {
    var n = String(name || '').trim();
    if (!n) return 'Укажите ФИО';
    if (isValidPersonName(n)) return '';
    if (/\p{Script=Latin}/u.test(n) || (n.length >= 2 && !/\p{Script=Cyrillic}/u.test(n))) {
      return 'Укажите ФИО русскими буквами';
    }
    return 'Укажите корректные ФИО (минимум 2 буквы)';
  }

  function ruPhoneError(phone) {
    if (!String(phone || '').trim()) return 'Укажите телефон';
    if (!isValidRuPhone(phone)) {
      return 'Укажите корректный мобильный телефон РФ (+7 9XX…)';
    }
    return '';
  }

  function isValidEmailOptional(email) {
    var e = String(email || '').trim();
    if (!e) return true;
    if (e.length > 120) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(e);
  }

  function validateLeadFields(opts) {
    opts = opts || {};
    var phoneOnly = !!opts.phoneOnly;
    var name = String(opts.name || '').trim();
    if (!name && phoneOnly) name = 'Клиент сайта';
    var phone = String(opts.phone || '').trim();
    var email = String(opts.email || '').trim();
    var agree = !!opts.agree;
    if (!phoneOnly) {
      var nameErr = personNameError(name);
      if (nameErr) return { ok: false, error: nameErr };
    } else if (name !== 'Клиент сайта' && name) {
      var nameErr2 = personNameError(name);
      if (nameErr2) return { ok: false, error: nameErr2 };
    }
    var phoneErr = ruPhoneError(phone);
    if (phoneErr) return { ok: false, error: phoneErr };
    if (!isValidEmailOptional(email)) {
      return { ok: false, error: 'Укажите корректный email или оставьте поле пустым' };
    }
    if (!agree) return { ok: false, error: 'Нужно согласие на обработку персональных данных' };
    return { ok: true, name: name, phone: phone, email: email };
  }

  function adsAcceptedFrom(root) {
    var el = null;
    if (root && root.querySelector) el = root.querySelector('[name="agree_ads"]');
    if (!el && typeof document !== 'undefined') el = document.querySelector('[name="agree_ads"]');
    return !!(el && el.checked);
  }

  /**
   * @param {object} opts
   * @param {string} opts.name
   * @param {string} opts.phone
   * @param {boolean} opts.agree
   * @param {string} [opts.message]
   * @param {string} [opts.source] — funnel surface id
   * @param {string} [opts.website] — honeypot
   * @param {string} [opts.email]
   * @returns {Promise<{success:boolean, message?:string, error?:string}>}
   */
  function defaultSuccessMessage(source) {
    var base = 'Заявка принята. Перезвоним в течение 15 минут.';
    try {
      var maxA = document.querySelector('.th-site-lead-bar__btn--max');
      var maxHref = maxA && maxA.getAttribute('href') ? maxA.getAttribute('href') : '';
      if (maxHref && (source === 'slow_search_lead' || source === 'abandon_sheet')) {
        return base + ' Или напишите нам в MAX.';
      }
    } catch (e0) {}
    return base;
  }

  function submitLead(opts) {
    opts = opts || {};
    var check = validateLeadFields(opts);
    if (!check.ok) {
      return Promise.resolve({ success: false, error: check.error });
    }
    var name = check.name;
    var phone = check.phone;
    var email = check.email;

    var payload = {
      name: name,
      phone: phone,
      agree: true,
      agree_ads: !!opts.agree_ads,
      website: String(opts.website || ''),
      message: buildMessage(opts),
      email: email,
      funnel_source: String(opts.source || 'site')
    };

    var source = String(opts.source || 'site');
    reachGoal('lead_submit_attempt', { source: source });
    return fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (r) {
        return r.json().catch(function () {
          return { success: false, error: 'Ошибка ответа сервера' };
        });
      })
      .then(function (data) {
        if (data && data.success) {
          var src = String(opts.source || 'site');
          reachGoal('lead_ok', { source: src });
          if (src === 'slow_search_lead') reachGoal('slow_search_lead');
          if (src === 'abandon_sheet') reachGoal('abandon_sheet_lead');
          if (src.indexOf('empty') >= 0 || src === 'empty-state') reachGoal('empty_state_lead');
          try { global.document.dispatchEvent(new CustomEvent('th:lead_ok', { detail: { source: src } })); } catch (e3) {}
          return {
            success: true,
            message: data.message || defaultSuccessMessage(src)
          };
        }
        reachGoal('lead_err', { source: source });
        return { success: false, error: (data && data.error) ? data.error : 'Не удалось отправить заявку' };
      })
      .catch(function () {
        reachGoal('lead_err', { source: source });
        return { success: false, error: 'Нет связи с сервером. Попробуйте позже или позвоните.' };
      });
  }

  /**
   * Bind a standard form: [name], [phone]/tel, [agree], optional [website] honeypot, [message]
   */
  function bindForm(form, options) {
    if (!form || form.__thLeadBound) return;
    form.__thLeadBound = true;
    options = options || {};
    var source = options.source || form.getAttribute('data-th-lead-source') || 'site';
    var msgEl = options.msgEl || (options.msgSelector ? form.querySelector(options.msgSelector) : null)
      || document.getElementById(form.getAttribute('data-th-lead-msg') || '')
      || form.querySelector('[data-th-lead-msg]');
    var submitBtn = options.submitBtn || form.querySelector('[type="submit"]');
    var phoneEl = form.querySelector('input[type="tel"], input[name="phone"]');
    formatPhoneInput(phoneEl);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (submitBtn && submitBtn.disabled) return;
      var fd = new FormData(form);
      var name = String(fd.get('name') || (form.querySelector('[name="name"]') || {}).value || '').trim();
      var phone = String(fd.get('phone') || (phoneEl && phoneEl.value) || '').trim();
      var phoneOnly = form.getAttribute('data-th-lead-phone-only') === '1';
      if (!name && phoneOnly) name = 'Клиент сайта';
      var agreeEl = form.querySelector('[name="agree"]');
      var agree = agreeEl ? !!agreeEl.checked : !!fd.get('agree');
      var adsEl = form.querySelector('[name="agree_ads"]');
      var agreeAds = adsEl ? !!adsEl.checked : !!fd.get('agree_ads');
      var website = String(fd.get('website') || '');
      var message = String(fd.get('message') || options.message || '');
      var email = String(fd.get('email') || '');

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.dataset.thPrevLabel = submitBtn.textContent || '';
        submitBtn.textContent = 'Отправка…';
      }
      if (msgEl) {
        msgEl.classList.add('hidden');
        msgEl.textContent = '';
      }

      submitLead({
        name: name,
        phone: phone,
        agree: agree,
        agree_ads: agreeAds,
        website: website,
        message: message,
        email: email,
        source: source
      }).then(function (res) {
        if (msgEl) {
          msgEl.textContent = res.success ? (res.message || '') : (res.error || '');
          msgEl.classList.remove('hidden');
          msgEl.removeAttribute('hidden');
          msgEl.style.display = 'block';
          if (res.success) {
            msgEl.classList.add('th-lead-msg--ok');
            msgEl.classList.remove('th-lead-msg--err');
            msgEl.style.background = '#ecfdf5';
            msgEl.style.color = '#065f46';
            msgEl.style.padding = '10px 12px';
            msgEl.style.borderRadius = '10px';
          } else {
            msgEl.classList.add('th-lead-msg--err');
            msgEl.classList.remove('th-lead-msg--ok');
            msgEl.style.background = '#fef2f2';
            msgEl.style.color = '#991b1b';
            msgEl.style.padding = '10px 12px';
            msgEl.style.borderRadius = '10px';
          }
        }
        if (res.success) {
          form.reset();
          if (typeof options.onSuccess === 'function') options.onSuccess(res);
        } else if (typeof options.onError === 'function') {
          options.onError(res);
        }
      }).finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = submitBtn.dataset.thPrevLabel || options.submitLabel || 'Отправить заявку';
        }
      });
    });
  }

  function autoBind() {
    document.querySelectorAll('form[data-th-lead]').forEach(function (form) {
      bindForm(form, { source: form.getAttribute('data-th-lead-source') || 'site' });
    });
  }

  function trackPageFunnelStage() {
    var path = String((global.location && global.location.pathname) || '');
    if (/\/tour-detail\.php$/i.test(path)) {
      reachGoal('tour_detail_view');
    } else if (/\/hotels\/hotel-detail\.php$/i.test(path)) {
      reachGoal('vip_hotel_detail_view');
    } else if (/\/turkey-vip-hotels\.php$/i.test(path)) {
      reachGoal('vip_hotels_view');
    }
  }

  // Funnel stages connect search, product selection, booking intent and submitted leads.
  document.addEventListener('click', function (e) {
    var target = e.target;
    if (!target || !target.closest) return;
    var tourLink = target.closest('a[href*="tour-detail.php"]');
    if (tourLink) reachGoal('tour_detail_open');

    var hotelLink = target.closest('a[href*="hotel-detail.php"]');
    if (hotelLink) reachGoal('vip_hotel_open');

    var leadTrigger = target.closest('[data-open-lead-modal]');
    if (leadTrigger) {
      reachGoal('lead_cta_click', {
        source: leadTrigger.getAttribute('data-open-lead-modal') || 'site'
      });
    }

    var bookingTrigger = target.closest('#btn-booking-with, #btn-booking-without, [data-th-booking-open]');
    if (bookingTrigger) reachGoal('booking_form_open');

    var a = target.closest('a[href^="tel:"]');
    if (a) reachGoal('call_click');
  }, true);

  document.addEventListener('focusin', function (e) {
    var target = e.target;
    var form = target && target.closest
      ? target.closest('form[data-th-lead], #main-quick-lead-form, #quick-booking-modal form, #booking-form')
      : null;
    if (!form || form.__thFunnelStarted) return;
    form.__thFunnelStarted = true;
    reachGoal('lead_form_start', {
      source: form.getAttribute('data-th-lead-source') || form.id || 'site'
    });
  }, true);

  trackPageFunnelStage();

  global.THLeadCapture = {
    submit: submitLead,
    bindForm: bindForm,
    reachGoal: reachGoal,
    formatPhoneInput: formatPhoneInput,
    isValidRuPhone: isValidRuPhone,
    isValidPersonName: isValidPersonName,
    personNameError: personNameError,
    ruPhoneError: ruPhoneError,
    validateLeadFields: validateLeadFields,
    adsAccepted: adsAcceptedFrom,
    SUCCESS_MSG: 'Заявка принята. Перезвоним в течение 15 минут.'
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoBind);
  } else {
    autoBind();
  }
})(typeof window !== 'undefined' ? window : this);
