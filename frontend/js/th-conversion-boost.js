/**
 * Travel Hub — conversion boost: wizard sticky, abandon sheet (1× per session @ 60s), funnel analytics.
 */
(function (global) {
  'use strict';

  // Один boot на вкладку — защита от двойного подключения скрипта / гонок таймеров.
  if (global.__TH_CONVERSION_BOOST_BOOTED) return;
  global.__TH_CONVERSION_BOOST_BOOTED = true;

  var ABANDON_DONE_KEY = 'th_abandon_sheet_done';
  var ABANDON_START_KEY = 'th_abandon_session_start';
  /** Ровно 1 минута от первого захода во вкладку. Ни раньше. */
  var ABANDON_DELAY_MS = 60 * 1000;
  /**
   * Если дедлайн уже прошёл (пользователь был на сайте >1 мин и перешёл
   * на другую страницу), не вспыхиваем в момент загрузки — короткая пауза.
   * Никогда не укорачивает 60с ожидание.
   */
  var PAGE_LOAD_GRACE_MS = 2500;
  var abandonTimersBound = false;
  var sheetEl = null;
  var pendingRetryTimer = null;
  var scheduleTimer = null;
  var openArmed = false;
  var closeWasSubmit = false;

  function reach(goal) {
    if (global.THLeadCapture && global.THLeadCapture.reachGoal) {
      global.THLeadCapture.reachGoal(goal);
    }
  }

  function maxUrl() {
    try {
      var a = document.querySelector('.th-site-lead-bar__btn--max');
      return a && a.getAttribute('href') ? a.getAttribute('href') : '';
    } catch (e) { return ''; }
  }

  function isAbandonDone() {
    try { return sessionStorage.getItem(ABANDON_DONE_KEY) === '1'; } catch (e) { return false; }
  }
  function markAbandonDone() {
    try { sessionStorage.setItem(ABANDON_DONE_KEY, '1'); } catch (e) {}
  }

  /** Старт визита на сайт (не сбрасывается при переходах по страницам). */
  function getSessionStart() {
    try {
      var raw = sessionStorage.getItem(ABANDON_START_KEY);
      var ts = raw ? parseInt(raw, 10) : 0;
      // Только валидный unix-ms в прошлом/настоящем. Битые значения → новый старт.
      if (!ts || isNaN(ts) || ts < 1e12 || ts > Date.now() + 1000) {
        ts = Date.now();
        sessionStorage.setItem(ABANDON_START_KEY, String(ts));
      }
      return ts;
    } catch (e) {
      return Date.now();
    }
  }

  function getAbandonDeadline() {
    return getSessionStart() + ABANDON_DELAY_MS;
  }

  function msUntilAbandonDeadline() {
    return getAbandonDeadline() - Date.now();
  }

  function clearAbandonTimers() {
    if (scheduleTimer) {
      clearTimeout(scheduleTimer);
      scheduleTimer = null;
    }
    if (pendingRetryTimer) {
      clearTimeout(pendingRetryTimer);
      pendingRetryTimer = null;
    }
  }

  function isHomeWizard() {
    return !!(document.getElementById('tour-search-section') && document.body.classList.contains('th-home-funnel-top'));
  }

  function syncWizardLeadBar(step) {
    var body = document.body;
    if (!body) return;
    body.classList.remove('th-wizard-step-1', 'th-wizard-step-2', 'th-wizard-step-3', 'th-wizard-step-4');
    body.classList.add('th-wizard-step-' + step);
    if (isHomeWizard() && step >= 2) {
      body.classList.add('th-wizard-lead-visible');
      reach('wizard_step_' + step);
    } else {
      body.classList.remove('th-wizard-lead-visible');
    }
  }

  function bindWizardSteps() {
    document.addEventListener('th:wizard-step', function (e) {
      var step = e.detail && e.detail.step ? parseInt(e.detail.step, 10) : 1;
      if (!isNaN(step)) syncWizardLeadBar(step);
    });
    var root = document.getElementById('tour-search-section');
    if (root) {
      var s = parseInt(root.getAttribute('data-step') || '1', 10);
      syncWizardLeadBar(isNaN(s) ? 1 : s);
    }
  }

  function ensureAbandonSheet() {
    if (sheetEl) return sheetEl;
    sheetEl = document.createElement('div');
    sheetEl.id = 'th-abandon-sheet';
    sheetEl.className = 'th-abandon-sheet hidden';
    sheetEl.setAttribute('role', 'dialog');
    sheetEl.setAttribute('aria-modal', 'true');
    sheetEl.innerHTML =
      '<div class="th-abandon-sheet__backdrop" data-th-abandon-close></div>' +
      '<div class="th-abandon-sheet__panel">' +
        '<button type="button" class="th-abandon-sheet__x" data-th-abandon-close aria-label="Закрыть">&times;</button>' +
        '<p class="th-abandon-sheet__badge">Ответ за 15 минут</p>' +
        '<h3 class="th-abandon-sheet__title">Не нашли тур?</h3>' +
        '<p class="th-abandon-sheet__sub">Оставьте телефон — менеджер подберёт лучшие варианты бесплатно.</p>' +
        '<form class="th-abandon-sheet__form" id="th-abandon-sheet-form">' +
          '<input type="tel" name="phone" required autocomplete="tel" placeholder="+7 (___) ___-__-__" class="th-abandon-sheet__input">' +
          '<label class="th-abandon-sheet__agree"><input type="checkbox" name="agree" required> Согласен на <a href="/frontend/window/consent.php" target="_blank" rel="noopener">обработку персональных данных</a>, с <a href="/frontend/window/privacy.php" target="_blank" rel="noopener">Политикой конфиденциальности</a> и <a href="/frontend/window/terms.php" target="_blank" rel="noopener">Пользовательским соглашением</a></label>' +
          '<input type="text" name="website" tabindex="-1" autocomplete="off" class="th-abandon-sheet__hp">' +
          '<p class="th-abandon-sheet__msg hidden" id="th-abandon-sheet-msg"></p>' +
          '<button type="submit" class="th-abandon-sheet__submit">Подобрать тур за меня</button>' +
        '</form>' +
        '<p class="th-abandon-sheet__proof"><i class="fas fa-shield-alt"></i> Без спама · только по вашему запросу</p>' +
      '</div>';
    document.body.appendChild(sheetEl);

    sheetEl.querySelectorAll('[data-th-abandon-close]').forEach(function (el) {
      el.addEventListener('click', closeAbandonSheet);
    });

    var form = document.getElementById('th-abandon-sheet-form');
    var phone = form && form.querySelector('[name="phone"]');
    if (global.THLeadCapture && phone) global.THLeadCapture.formatPhoneInput(phone);

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = document.getElementById('th-abandon-sheet-msg');
        var btn = form.querySelector('[type="submit"]');
        var fd = new FormData(form);
        if (btn) { btn.disabled = true; btn.textContent = 'Отправка…'; }
        reach('abandon_sheet_submit');
        var submit = (global.THLeadCapture && global.THLeadCapture.submit)
          ? global.THLeadCapture.submit({
            name: 'Клиент сайта',
            phone: String(fd.get('phone') || '').trim(),
            agree: !!fd.get('agree'),
            website: String(fd.get('website') || ''),
            source: 'abandon_sheet',
            message: 'Abandon sheet — timed popup'
          })
          : Promise.resolve({ success: false, error: 'Форма недоступна' });
        submit.then(function (res) {
          if (msg) {
            msg.classList.remove('hidden');
            if (res.success) {
              msg.textContent = res.message || 'Заявка принята! Перезвоним за 15 минут.';
              msg.className = 'th-abandon-sheet__msg th-abandon-sheet__msg--ok';
              markAbandonDone();
              closeWasSubmit = true;
              form.reset();
              var mu = maxUrl();
              if (mu) {
                msg.innerHTML = (res.message || 'Заявка принята!') +
                  ' <a href="' + mu.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener">Написать в MAX</a>';
              }
              setTimeout(closeAbandonSheet, 3500);
            } else {
              msg.textContent = res.error || 'Ошибка отправки';
              msg.className = 'th-abandon-sheet__msg th-abandon-sheet__msg--err';
            }
          }
        }).finally(function () {
          if (btn) { btn.disabled = false; btn.textContent = 'Подобрать тур за меня'; }
        });
      });
    }
    return sheetEl;
  }

  function canShowAbandonNow() {
    if (isAbandonDone()) return false;
    if (openArmed) return false;
    if (document.body.classList.contains('th-modal-open')) return false;
    if (document.body.classList.contains('th-abandon-open')) return false;
    if (document.body.classList.contains('th-support-chat-open')) return false;
    if (document.body.classList.contains('th-promo-open')) return false;
    if (document.querySelector('#tv-search-loader.active')) return false;
    if (sheetEl && !sheetEl.classList.contains('hidden')) return false;
    return true;
  }

  /**
   * Жёсткий гейт: никогда раньше дедлайна (sessionStart + 60s).
   * Done помечается ДО открытия — второй показ в этой вкладке невозможен.
   */
  function tryOpenAbandonSheet(reason) {
    if (isAbandonDone() || openArmed) return false;

    var left = msUntilAbandonDeadline();
    if (left > 0) {
      armAbandonSchedule();
      return false;
    }

    if (!canShowAbandonNow()) {
      scheduleBlockedRetry();
      return false;
    }

    // Анти-спам: сначала done, потом UI.
    markAbandonDone();
    openArmed = true;
    closeWasSubmit = false;
    clearAbandonTimers();
    reach(reason || 'abandon_sheet_timer_1');
    ensureAbandonSheet();
    sheetEl.classList.remove('hidden');
    document.body.classList.add('th-abandon-open');
    if (global.THMobile && global.THMobile.lockScroll) global.THMobile.lockScroll(true);
    if (global.THMobile && typeof global.THMobile.sync === 'function') global.THMobile.sync();
    if (global.THMobile && typeof global.THMobile.pinFixedBottoms === 'function') {
      global.THMobile.pinFixedBottoms();
    }
    return true;
  }

  function closeAbandonSheet() {
    if (!sheetEl) return;
    sheetEl.classList.add('hidden');
    document.body.classList.remove('th-abandon-open');
    var panel = sheetEl.querySelector('.th-abandon-sheet__panel');
    if (panel) {
      panel.style.removeProperty('position');
      panel.style.removeProperty('top');
      panel.style.removeProperty('bottom');
      panel.style.removeProperty('left');
      panel.style.removeProperty('right');
      panel.style.removeProperty('width');
      panel.style.removeProperty('max-width');
      panel.style.removeProperty('margin');
    }
    if (global.THMobile && global.THMobile.lockScroll) global.THMobile.lockScroll(false);

    if (openArmed && !closeWasSubmit) {
      reach('abandon_sheet_skip_1');
    }
    openArmed = false;
    closeWasSubmit = false;
    markAbandonDone();
    clearAbandonTimers();
  }

  function scheduleBlockedRetry() {
    if (pendingRetryTimer || isAbandonDone()) return;
    var attempts = 0;
    function retry() {
      pendingRetryTimer = null;
      attempts++;
      if (isAbandonDone() || attempts > 90) return;
      if (msUntilAbandonDeadline() > 0) {
        armAbandonSchedule();
        return;
      }
      if (tryOpenAbandonSheet('abandon_sheet_timer_1')) return;
      pendingRetryTimer = setTimeout(retry, 2000);
    }
    pendingRetryTimer = setTimeout(retry, 2000);
  }

  /**
   * Планирует показ ровно по дедлайну.
   * При троттлинге фоновых вкладок перепроверяем Date.now() и перепланируем остаток.
   */
  function armAbandonSchedule() {
    if (isAbandonDone()) return;
    clearAbandonTimers();

    var left = msUntilAbandonDeadline();
    var wait;
    if (left > 0) {
      // Ровно остаток до 60с. Кап 30с — чтобы после сна вкладки пересчитать.
      wait = Math.min(left, 30000);
    } else {
      // Дедлайн уже был — только grace после загрузки страницы, не раньше 60с сессии.
      wait = PAGE_LOAD_GRACE_MS;
    }

    scheduleTimer = setTimeout(function onDue() {
      scheduleTimer = null;
      if (isAbandonDone()) return;
      var stillLeft = msUntilAbandonDeadline();
      if (stillLeft > 16) {
        // Ещё рано (троттлинг/сон) — дожимаем остаток, никогда не открываем раньше.
        armAbandonSchedule();
        return;
      }
      tryOpenAbandonSheet('abandon_sheet_timer_1');
    }, wait);
  }

    function bindAbandonTriggers() {
        /* Таймерная модалка через 60с отключена — промокод выдаём по кнопке на главной. */
        return;
    }

  /** Intent copy for quick lead modal */
  var INTENT = {
    'slow-search': { title: 'Подберём тур за вас', sub: 'Поиск идёт — пока ждёте, оставьте телефон. Перезвоним за 15 минут.', submit: 'Подобрать за меня', phoneOnly: true },
    'empty-state': { title: 'Не нашли подходящий тур?', sub: 'Менеджер подберёт варианты вручную — бесплатно.', submit: 'Получить подбор', phoneOnly: true },
    'search-error': { title: 'Не удалось загрузить туры', sub: 'Оставьте телефон — подберём варианты и перезвоним за 15 минут.', submit: 'Помочь с подбором', phoneOnly: true },
    'results-toolbar': { title: 'Помочь с выбором?', sub: 'Перезвоним за 15 минут с лучшими вариантами.', submit: 'Перезвоните мне', phoneOnly: true },
    'results-sticky': { title: 'Нужна помощь с туром?', sub: 'Без спама · ответ за 15 минут.', submit: 'Оставить телефон', phoneOnly: true },
    'home_quick_modal': { title: 'Подберём тур для вас', sub: 'Перезвоним за 15 минут. Без спама.', submit: 'Отправить заявку', phoneOnly: false },
    'promo_country_callback': { title: 'Перезвонить с акциями', sub: 'Подберём горящие туры по выбранной стране.', submit: 'Жду звонка', phoneOnly: true }
  };

  function applyIntent(source) {
    var cfg = INTENT[source] || INTENT.home_quick_modal;
    var modal = document.getElementById('quick-booking-modal');
    if (!modal) return cfg;
    var title = modal.querySelector('.th-qbm-modal__title');
    var sub = modal.querySelector('.th-qbm-modal__sub');
    var submit = document.getElementById('qbm-submit');
    var nameRow = modal.querySelector('[name="name"]');
    if (title) title.textContent = cfg.title;
    if (sub) sub.textContent = cfg.sub;
    if (submit) submit.textContent = cfg.submit;
    if (nameRow) {
      var wrap = nameRow.closest('.th-qbm-modal__field') || nameRow.parentElement;
      if (cfg.phoneOnly) {
        nameRow.removeAttribute('required');
        if (wrap) wrap.style.display = 'none';
      } else {
        nameRow.setAttribute('required', 'required');
        if (wrap) wrap.style.display = '';
      }
    }
    modal.dataset.thLeadSource = source || 'home_quick_modal';
    modal.dataset.thPhoneOnly = cfg.phoneOnly ? '1' : '0';
    return cfg;
  }

  function bindQuickModalIntent() {
    global.openQuickLeadModalWithIntent = function (source) {
      applyIntent(source || 'home_quick_modal');
      if (typeof global.openQuickLeadModal === 'function') {
        global.openQuickLeadModal(source);
      }
    };
    document.addEventListener('click', function (e) {
      var t = e.target && e.target.closest ? e.target.closest('[data-open-lead-modal]') : null;
      if (!t) return;
      applyIntent(t.getAttribute('data-open-lead-modal') || 'home_quick_modal');
    }, true);
  }

  function bindPromoCountryCallbacks() {
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-promo-country-callback]') : null;
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      var country = btn.getAttribute('data-country-name') || '';
      var msg = country ? ('Страна: ' + country + '. Перезвонить с горящими акциями.') : 'Перезвонить с горящими акциями.';
      if (typeof global.openSiteFeedbackModal === 'function') {
        global.openSiteFeedbackModal({
          source: 'promo_country_callback',
          title: 'Перезвонить с акциями',
          sub: 'Перезвоним за 15 минут · ' + (country || 'выбранное направление'),
          message: msg,
          focusPhone: true,
          phoneOnly: true
        });
      } else if (global.openQuickLeadModalWithIntent) {
        global.openQuickLeadModalWithIntent('promo_country_callback');
      }
      reach('promo_country_callback_click');
    });
  }

  var boostInitialized = false;
  function init() {
    if (boostInitialized) return;
    boostInitialized = true;
    bindWizardSteps();
    bindAbandonTriggers();
    bindQuickModalIntent();
    bindPromoCountryCallbacks();
  }

  global.THConversionBoost = {
    applyIntent: applyIntent,
    reach: reach,
    INTENT: INTENT
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : this);
