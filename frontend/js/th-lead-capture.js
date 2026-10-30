/**
 * TravelHub hard funnel — единый lead capture + аналитика.
 * Цель: одна конверсионная цель `lead_ok` на всех поверхностях.
 */
(function (global) {
  'use strict';

  var YM_FALLBACK = 113554694;
  var ENDPOINT = '/backend/api/uon-lead.php';
  var SUCCESS_MSG = 'Заявка принята. Ждите звонок с +7 (846) 254-16-56 (9:00–21:00 МСК). Не смогли ответить — напишите в MAX.';
  var WORKTIME_HINT = 'Позвоним с +7 (846) 254-16-56 в рабочее время (9:00–21:00 МСК). Сохраните номер в контакты.';
  var PICKUP_HINT = 'Неудобно отвечать на звонок? Напишите в MAX — кнопка внизу экрана.';

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

  var PHONE_DEFAULT_PREFIX = '+7';

  function phoneDigitCount(val) {
    return digitsOnly(val).length;
  }

  /** Только код страны (+7), номер ещё не введён. */
  function isPhonePrefixOnly(val) {
    return phoneDigitCount(val) <= 1;
  }

  function ensurePhoneDefault(el) {
    if (!el) return;
    var raw = String(el.value || '').trim();
    if (!raw || isPhonePrefixOnly(raw)) {
      el.value = PHONE_DEFAULT_PREFIX;
    }
  }

  function formatPhoneInput(el) {
    if (!el || el.__thPhoneBound) return;
    el.__thPhoneBound = true;
    function applyPhoneMask() {
      var v = el.value.replace(/\D/g, '');
      if (v.length > 0 && v[0] === '8') v = '7' + v.slice(1);
      if (v.length > 0 && v[0] !== '7') v = '7' + v;
      if (v.length > 11) v = v.slice(0, 11);
      if (v.length === 0) {
        el.value = '';
        return;
      }
      var formatted = '+7';
      if (v.length > 1) formatted += ' (' + v.slice(1, 4);
      if (v.length > 4) formatted += ') ' + v.slice(4, 7);
      if (v.length > 7) formatted += '-' + v.slice(7, 9);
      if (v.length > 9) formatted += '-' + v.slice(9, 11);
      el.value = formatted;
    }
    el.addEventListener('input', applyPhoneMask);
    el.addEventListener('paste', function () {
      setTimeout(applyPhoneMask, 0);
    });
    el.addEventListener('focus', function () {
      if (isPhonePrefixOnly(el.value)) {
        el.value = PHONE_DEFAULT_PREFIX;
        try {
          var end = el.value.length;
          el.setSelectionRange(end, end);
        } catch (eFocus) {}
      }
    });
    el.addEventListener('blur', function () {
      if (!String(el.value || '').trim() || isPhonePrefixOnly(el.value)) {
        el.value = PHONE_DEFAULT_PREFIX;
        setLeadInputValidity(el, '');
      }
    });
    ensurePhoneDefault(el);
  }

  function normalizeFirstName(name) {
    var n = String(name || '').trim().replace(/\s+/g, ' ');
    if (!n) return '';
    var first = n.split(/[\s\-]+/)[0] || '';
    return first.replace(/[^\p{Script=Cyrillic}\-']/gu, '');
  }

  function formatPersonNameInput(el) {
    if (!el || el.__thNameBound) return;
    el.__thNameBound = true;
    el.addEventListener('input', function () {
      var cleaned = el.value.replace(/[^\p{Script=Cyrillic}\s\-']/gu, '');
      if (cleaned !== el.value) el.value = cleaned;
    });
    el.addEventListener('blur', function () {
      var n = normalizeFirstName(el.value);
      if (n && n !== el.value.trim()) el.value = n;
      validateLeadInputEl(el);
    });
  }

  function leadFieldWrap(el) {
    if (!el || !el.closest) return null;
    return el.closest('.th-qbm-modal__field, .th-lead-capture__row, .th-sf-field') || el.parentElement;
  }

  function ensureLeadFieldErrorEl(input) {
    var wrap = leadFieldWrap(input);
    if (!wrap) return null;
    var err = wrap.querySelector('[data-th-lead-field-error]');
    if (!err) {
      err = document.createElement('p');
      err.className = 'th-lead-field-error';
      err.setAttribute('data-th-lead-field-error', '1');
      err.hidden = true;
      wrap.appendChild(err);
    }
    return err;
  }

  function setLeadInputValidity(input, errorMsg) {
    if (!input) return;
    var bad = !!errorMsg;
    input.classList.toggle('is-invalid', bad);
    input.setAttribute('aria-invalid', bad ? 'true' : 'false');
    var errEl = ensureLeadFieldErrorEl(input);
    if (errEl) {
      if (bad) {
        errEl.textContent = errorMsg;
        errEl.hidden = false;
        errEl.classList.add('is-visible');
      } else {
        errEl.textContent = '';
        errEl.hidden = true;
        errEl.classList.remove('is-visible');
      }
    }
  }

  function validateLeadInputEl(input) {
    if (!input) return '';
    var form = input.closest('form');
    var phoneOnly = form && form.getAttribute('data-th-lead-phone-only') === '1';
    if (input.name === 'name' || input.id && /name$/i.test(input.id)) {
      if (phoneOnly && input.closest('[style*="display: none"]')) return '';
      var nameErr = personNameError(input.value);
      setLeadInputValidity(input, nameErr);
      return nameErr;
    }
    if (input.type === 'tel' || input.name === 'phone') {
      if (isPhonePrefixOnly(input.value)) {
        setLeadInputValidity(input, '');
        return '';
      }
      var phoneErr = ruPhoneError(input.value);
      setLeadInputValidity(input, phoneErr);
      return phoneErr;
    }
    return '';
  }

  function clearLeadFormFieldErrors(form) {
    if (!form || !form.querySelectorAll) return;
    form.querySelectorAll('input[name="name"], input[type="tel"], input[name="phone"]').forEach(function (el) {
      setLeadInputValidity(el, '');
    });
  }

  function markLeadFormErrors(form, check) {
    if (!form || !check) return;
    clearLeadFormFieldErrors(form);
    if (check.ok) return;
    var err = check.error || '';
    var nameEl = form.querySelector('[name="name"]');
    var phoneEl = form.querySelector('input[type="tel"], input[name="phone"]');
    if (/им/i.test(err) && nameEl) setLeadInputValidity(nameEl, err);
    else if (/телефон/i.test(err) && phoneEl) setLeadInputValidity(phoneEl, err);
    else if (/email/i.test(err)) {
      var emailEl = form.querySelector('[name="email"]');
      if (emailEl) setLeadInputValidity(emailEl, err);
    } else if (nameEl && personNameError(nameEl.value)) setLeadInputValidity(nameEl, personNameError(nameEl.value));
    else if (phoneEl && ruPhoneError(phoneEl.value)) setLeadInputValidity(phoneEl, ruPhoneError(phoneEl.value));
  }

  var LEAD_INPUT_FORM_SELECTOR = 'form[data-th-lead], #th-tour-booking-form, #th-site-feedback-form, #booking-form, #main-quick-lead-form, #quick-booking-modal form';

  function bindLeadInputs(form) {
    if (!form || form.__thLeadInputsBound) return;
    form.__thLeadInputsBound = true;
    var nameEl = form.querySelector('[name="name"]:not([type="hidden"])')
      || form.querySelector('#b-manager-name, #th-tb-name, #th-sf-name');
    var phoneEl = form.querySelector('input[type="tel"], input[name="phone"]')
      || form.querySelector('#b-phone, #th-tb-phone, #th-sf-phone');
    formatPersonNameInput(nameEl);
    formatPhoneInput(phoneEl);
    if (phoneEl) {
      phoneEl.addEventListener('blur', function () { validateLeadInputEl(phoneEl); });
      form.addEventListener('reset', function () {
        setTimeout(function () { ensurePhoneDefault(phoneEl); }, 0);
      });
    }
    if (nameEl) {
      nameEl.addEventListener('blur', function () { validateLeadInputEl(nameEl); });
    }
  }

  function autoBindLeadInputs() {
    document.querySelectorAll(LEAD_INPUT_FORM_SELECTOR).forEach(bindLeadInputs);
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
    var n = normalizeFirstName(name);
    if (n.length < 2 || n.length > 40) return false;
    if (!/^[\p{Script=Cyrillic}\-']+$/u.test(n)) return false;
    if (/^(\p{L})\1+$/u.test(n)) return false;
    if (!/[аеёиоуыэюя]/iu.test(n)) return false;
    var lower = n.toLowerCase();
    var bannedExact = ['тест', 'test', 'asdf', 'qwerty', 'admin', 'user', 'имя', 'фамилия', 'фио', 'xxx', 'null', 'none'];
    if (bannedExact.indexOf(lower) >= 0) return false;
    if (/тест|test|qwerty|asdf|admin|xxx/.test(lower)) return false;
    return true;
  }

  function personNameError(name) {
    var raw = String(name || '').trim();
    if (!raw) return 'Укажите имя';
    var n = normalizeFirstName(raw);
    if (isValidPersonName(n)) return '';
    if (/\p{Script=Latin}/u.test(raw) || (raw.length >= 1 && !/\p{Script=Cyrillic}/u.test(raw))) {
      return 'Укажите имя русскими буквами';
    }
    return 'Укажите корректное имя (минимум 2 буквы)';
  }

  function ruPhoneError(phone) {
    var p = String(phone || '').trim();
    if (!p || isPhonePrefixOnly(p)) return 'Укажите телефон';
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
      name = normalizeFirstName(name);
      var nameErr = personNameError(name);
      if (nameErr) return { ok: false, error: nameErr };
    } else if (name !== 'Клиент сайта' && name) {
      name = normalizeFirstName(name);
      var nameErr2 = personNameError(name);
      if (nameErr2) return { ok: false, error: nameErr2 };
    }
    var phoneErr = ruPhoneError(phone);
    if (phoneErr) return { ok: false, error: phoneErr };
    if (!isValidEmailOptional(email)) {
      return { ok: false, error: 'Укажите корректный email или оставьте поле пустым' };
    }
    if (!agree) return { ok: false, error: 'Нужно согласие на обработку персональных данных' };
    if (!phoneOnly && name) name = normalizeFirstName(name);
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
  function defaultSuccessMessage() {
    return SUCCESS_MSG;
  }

  function submitLead(opts) {
    opts = opts || {};
    var source = String(opts.source || 'site');
    reachGoal('lead_submit_attempt', { source: source });
    var check = validateLeadFields(opts);
    if (!check.ok) {
      reachGoal('lead_validation_error', { source: source });
      if (opts.formEl) markLeadFormErrors(opts.formEl, check);
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
            message: data.message || defaultSuccessMessage()
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
    bindLeadInputs(form);
    var phoneEl = form.querySelector('input[type="tel"], input[name="phone"]');

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

      clearLeadFormFieldErrors(form);
      submitLead({
        name: normalizeFirstName(name) || name,
        phone: phone,
        agree: agree,
        agree_ads: agreeAds,
        website: website,
        message: message,
        email: email,
        source: source,
        formEl: form
      }).then(function (res) {
        if (!res.success) markLeadFormErrors(form, { ok: false, error: res.error });
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
          var phoneReset = form.querySelector('input[type="tel"], input[name="phone"]');
          ensurePhoneDefault(phoneReset);
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

  var pageFunnelTracked = false;
  function trackPageFunnelStage() {
    if (pageFunnelTracked || typeof global.ym !== 'function') return;
    var path = String((global.location && global.location.pathname) || '');
    var normalizedPath = path.toLowerCase();
    var pageType = 'other';
    if (normalizedPath === '/' || /\/index\.php$/i.test(normalizedPath)) {
      pageType = 'home';
    } else if (/\/promotions\.php$/i.test(normalizedPath)) {
      pageType = 'promotions';
    } else if (/\/offices\/office\.php$/i.test(normalizedPath)) {
      pageType = 'office_detail';
    } else if (/\/offices(?:\/|\.php)/i.test(normalizedPath)) {
      pageType = 'offices';
    } else if (/\/tour-calendar\.php$/i.test(normalizedPath)) {
      pageType = 'calendar';
    } else if (/\/countries\/(?:country\.php|[^/]+\.php)$/i.test(normalizedPath)) {
      pageType = 'country';
    } else if (/\/countries-list\.php$/i.test(normalizedPath)) {
      pageType = 'country_list';
    } else if (/\/tour-detail\.php$/i.test(normalizedPath)) {
      pageType = 'tour_detail';
    }

    reachGoal('site_page_view', { page_type: pageType, page_path: path });
    try {
      if (!sessionStorage.getItem('th_ym_entry_sent_v1')) {
        sessionStorage.setItem('th_ym_entry_sent_v1', '1');
        reachGoal('site_entry', { entry_page: pageType, entry_path: path });
      }
    } catch (e) {}

    if (/\/tour-detail\.php$/i.test(path)) {
      reachGoal('tour_detail_view');
    } else if (/\/hotels\/hotel-detail\.php$/i.test(path)) {
      reachGoal('vip_hotel_detail_view');
    } else if (/\/turkey-vip-hotels\.php$/i.test(path)) {
      reachGoal('vip_hotels_view');
    } else if (/\/promotions\.php$/i.test(path)) {
      reachGoal('promo_page_view');
    } else if (/\/offices\/office\.php$/i.test(path)) {
      reachGoal('office_detail_view');
    } else if (/\/offices(?:\/|\.php)/i.test(path)) {
      reachGoal('offices_page_view');
    } else if (/\/tour-calendar\.php$/i.test(path)) {
      reachGoal('calendar_page_view');
    } else if (/\/countries\/(?:country\.php|[^/]+\.php)$/i.test(path)) {
      reachGoal('country_page_view');
    } else if (/\/countries-list\.php$/i.test(path)) {
      reachGoal('country_list_view');
    }
    pageFunnelTracked = true;
  }

  // Funnel stages connect search, product selection, booking intent and submitted leads.
  document.addEventListener('click', function (e) {
    var target = e.target;
    if (!target || !target.closest) return;
    var tourLink = target.closest('a[href*="tour-detail.php"]');
    if (tourLink) {
      var currentPath = String((global.location && global.location.pathname) || '').toLowerCase();
      var tourUrl;
      var source = 'site';
      try {
        tourUrl = new URL(tourLink.href, global.location.href);
        if (tourUrl.searchParams.get('from_calendar') === '1' || /\/tour-calendar\.php$/i.test(currentPath)) {
          source = 'calendar';
        } else if (/\/promotions\.php$/i.test(currentPath)) {
          source = 'promotions';
        } else if (/\/countries\//i.test(currentPath)) {
          source = 'country';
        } else if (/\/index\.php$/i.test(currentPath) || currentPath === '/') {
          source = tourLink.closest('.th-tour-offer') ? 'home_hot_tour' : 'home_search';
        }
      } catch (e) {}
      reachGoal('tour_detail_open', { source: source, page_path: currentPath });
      if (source === 'calendar') reachGoal('calendar_tour_open');
      if (source === 'country') reachGoal('country_tour_open');
    }

    var hotelLink = target.closest('a[href*="hotel-detail.php"]');
    if (hotelLink) reachGoal('vip_hotel_open');

    var leadTrigger = target.closest('[data-open-lead-modal]');
    if (leadTrigger) {
      reachGoal('lead_cta_click', {
        source: leadTrigger.getAttribute('data-open-lead-modal') || 'site'
      });
    }

    var bookingTrigger = target.closest('#btn-booking-with, #btn-booking-without, #th-detail-mobile-sticky-btn, [data-th-booking-open], [data-th-book-tour]');
    if (bookingTrigger) {
      var bookingSource = /\/promotions\.php$/i.test(global.location.pathname) ? 'promotions'
        : (/\/countries\//i.test(global.location.pathname) ? 'country'
          : (/\/tour-calendar\.php$/i.test(global.location.pathname) ? 'calendar'
            : (/\/index\.php$/i.test(global.location.pathname) || global.location.pathname === '/' ? 'homepage' : 'tour_detail')));
      reachGoal('booking_form_open', { source: bookingSource });
    }

    var officeLeadTrigger = target.closest('[data-th-office-lead-btn="1"]');
    if (officeLeadTrigger) {
      reachGoal('office_lead_open', {
        office_city: officeLeadTrigger.getAttribute('data-office-city') || ''
      });
    }

    var officeLink = target.closest('a[href*="/offices/"]');
    if (officeLink && !/\/offices\/office\.php/i.test(global.location.pathname)) {
      reachGoal('office_detail_open');
    }
    var countryLink = target.closest('a[href*="/countries/"]');
    if (countryLink) {
      var countrySlug = '';
      try {
        var cu = new URL(countryLink.href, global.location.href);
        countrySlug = (cu.pathname.match(/\/countries\/([^/]+)\.php$/i) || [])[1] || '';
      } catch (eCu) {}
      reachGoal('country_detail_open', {
        source: String((global.location && global.location.pathname) || ''),
        country_slug: countrySlug
      });
      reachGoal('country_page_open', { country_slug: countrySlug });
    }

    var a = target.closest('a[href^="tel:"]');
    if (a) {
      reachGoal('call_click');
      if (/\/offices(?:\/|\.php)/i.test(global.location.pathname) || a.closest('.th-off-card')) {
        reachGoal('office_call_click', {
          office_city: (a.closest('[data-city]') && a.closest('[data-city]').getAttribute('data-city')) || ''
        });
      }
    }
  }, true);

  document.addEventListener('focusin', function (e) {
    var target = e.target;
    var form = target && target.closest
      ? target.closest('form[data-th-lead], #main-quick-lead-form, #quick-booking-modal form, #booking-form, #th-tour-booking-form')
      : null;
    if (!form || form.__thFunnelStarted) return;
    form.__thFunnelStarted = true;
    reachGoal('lead_form_start', {
      source: form.getAttribute('data-th-lead-source') || form.id || 'site'
    });
  }, true);

  global.addEventListener('th:cookie-consent-accepted', trackPageFunnelStage);
  trackPageFunnelStage();

  global.THLeadCapture = {
    submit: submitLead,
    bindForm: bindForm,
    bindLeadInputs: bindLeadInputs,
    markLeadFormErrors: markLeadFormErrors,
    clearLeadFormFieldErrors: clearLeadFormFieldErrors,
    normalizeFirstName: normalizeFirstName,
    reachGoal: reachGoal,
    formatPhoneInput: formatPhoneInput,
    ensurePhoneDefault: ensurePhoneDefault,
    formatPersonNameInput: formatPersonNameInput,
    isValidRuPhone: isValidRuPhone,
    isValidPersonName: isValidPersonName,
    personNameError: personNameError,
    ruPhoneError: ruPhoneError,
    validateLeadFields: validateLeadFields,
    adsAccepted: adsAcceptedFrom,
    SUCCESS_MSG: SUCCESS_MSG,
    WORKTIME_HINT: WORKTIME_HINT,
    PICKUP_HINT: PICKUP_HINT
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      autoBind();
      autoBindLeadInputs();
    });
  } else {
    autoBind();
    autoBindLeadInputs();
  }
})(typeof window !== 'undefined' ? window : this);
