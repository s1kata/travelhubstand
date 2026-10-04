(function (global) {
  'use strict';

  var LOCAL_REPLIES = {
    'горящие туры': 'Горящие туры — в блоке на главной: уже готовые варианты с ценой и датами. Откройте карточку или нажмите «Связаться с менеджером» для подбора.',
    'оплата': 'Оплатить можно онлайн картой (Т-Касса) или в офисе. Нужна ссылка на оплату — оставьте телефон менеджеру.',
    'бронирование': 'Выберите тур → «Забронировать» → имя и телефон. Менеджер подтвердит бронь обычно за 15 минут.',
    'документы': 'Обычно нужны загранпаспорта всех туристов. По визе менеджер пришлёт точный список.',
    'цены': 'Цена на карточке уже за выбранных туристов. Итог зависит от дат и отеля — уточним по телефону.',
    'связаться с менеджером': 'Сейчас передам менеджеру. Оставьте номер — перезвоним за 15 минут.'
  };

  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function localReply(message) {
    var hay = String(message || '').toLowerCase();
    var keys = Object.keys(LOCAL_REPLIES);
    for (var i = 0; i < keys.length; i++) {
      if (hay.indexOf(keys[i]) !== -1) return LOCAL_REPLIES[keys[i]];
    }
    if (/оплат|карт|т-касс/.test(hay)) return LOCAL_REPLIES['оплата'];
    if (/брони|заявк|купить/.test(hay)) return LOCAL_REPLIES['бронирование'];
    if (/документ|паспорт|виза|загран/.test(hay)) return LOCAL_REPLIES['документы'];
    if (/цен|стоит|бюджет/.test(hay)) return LOCAL_REPLIES['цены'];
    if (/горящ|акци|скидк/.test(hay)) return LOCAL_REPLIES['горящие туры'];
    if (/менеджер|перезвон|оператор/.test(hay)) return LOCAL_REPLIES['связаться с менеджером'];
    return 'Могу помочь с горящими турами, оплатой, бронированием и документами. Выберите тему ниже или напишите «Связаться с менеджером».';
  }

  function createWidget() {
    if (document.getElementById('th-support-chat')) return null;
    var root = document.createElement('section');
    root.id = 'th-support-chat';
    root.className = 'th-support-chat';
    root.innerHTML =
      '<div id="th-support-chat-panel" class="th-support-chat__panel th-support-chat__hidden" aria-live="polite">' +
      '  <div class="th-support-chat__head"><h4>Поддержка Travel Hub</h4><button type="button" id="th-support-chat-close" class="th-support-chat__close" aria-label="Закрыть чат">×</button></div>' +
      '  <div id="th-support-chat-body" class="th-support-chat__body"></div>' +
      '  <div id="th-support-chat-quick" class="th-support-chat__quick" aria-label="Быстрые вопросы"></div>' +
      '  <div class="th-support-chat__actions">' +
      '    <button type="button" id="th-support-chat-manager">Связаться с менеджером</button>' +
      '    <a href="tel:+78462541656">Позвонить: +7 (846) 254-16-56</a>' +
      '  </div>' +
      '</div>' +
      '<button type="button" id="th-support-chat-toggle" class="th-support-chat__toggle" aria-label="Открыть чат поддержки"><i class="fas fa-comments" aria-hidden="true"></i></button>';
    document.body.appendChild(root);
    return root;
  }

  function clearBlockingOverlays() {
    try {
      var abandon = document.getElementById('th-abandon-sheet');
      if (abandon && !abandon.classList.contains('hidden')) {
        abandon.classList.add('hidden');
        document.body.classList.remove('th-abandon-open');
        try { sessionStorage.setItem('th_abandon_sheet_done', '1'); } catch (eSs) {}
      }
      var promo = document.getElementById('th-promo-popup');
      if (promo && promo.classList.contains('th-promo-popup--visible')) {
        promo.classList.remove('th-promo-popup--visible');
        promo.classList.add('th-promo-popup--collapsed');
        document.body.classList.remove('th-promo-open');
      }
    } catch (e) {}
  }

  function init() {
    var root = createWidget();
    if (!root) return;

    var panel = document.getElementById('th-support-chat-panel');
    var toggle = document.getElementById('th-support-chat-toggle');
    var closeBtn = document.getElementById('th-support-chat-close');
    var body = document.getElementById('th-support-chat-body');
    var quick = document.getElementById('th-support-chat-quick');
    var managerBtn = document.getElementById('th-support-chat-manager');
    var sessionKey = 'th_support_chat_session';
    var sessionId = '';
    var booted = false;
    var busy = false;
    var defaultQuick = [
      'Подобрать тур',
      'Горящие туры',
      'Бронирование',
      'Оплата',
      'Документы и виза',
      'Связаться с менеджером'
    ];

    function track(goal) {
      try {
        if (global.THLeadCapture && typeof global.THLeadCapture.reachGoal === 'function') {
          global.THLeadCapture.reachGoal(goal);
        }
      } catch (e) {}
    }

    try {
      sessionId = localStorage.getItem(sessionKey) || '';
    } catch (e) {}

    function addMsg(text, who) {
      var div = document.createElement('div');
      div.className = 'th-support-chat__msg th-support-chat__msg--' + (who || 'bot');
      div.innerHTML = esc(text);
      body.appendChild(div);
      body.scrollTop = body.scrollHeight;
    }

    function send(message) {
      return fetch('/backend/api/support-chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: String(message || ''),
          sessionId: sessionId || '',
          channel: 'site'
        })
      }).then(function (r) {
        if (!r.ok) throw new Error('http_' + r.status);
        return r.json();
      });
    }

    function sendFromButton(msg) {
      var text = String(msg || '').trim();
      if (!text || busy) return;
      busy = true;
      track('support_chat_quick_reply');
      addMsg(text, 'user');
      send(text).then(function (res) {
        busy = false;
        if (!res || !res.success) {
          addMsg(localReply(text), 'bot');
          setQuick(defaultQuick);
          return;
        }
        sessionId = res.sessionId || sessionId;
        try { localStorage.setItem(sessionKey, sessionId); } catch (e) {}
        addMsg(res.reply || localReply(text), 'bot');
        setQuick(res.quickReplies || defaultQuick);
        if (res.intent) track('support_chat_intent_' + String(res.intent));
        if (res.handoff || res.managerCta) {
          track('support_chat_handoff_offer');
          managerBtn.classList.add('is-suggested');
          setTimeout(function () { managerBtn.classList.remove('is-suggested'); }, 2200);
        }
      }).catch(function () {
        busy = false;
        addMsg(localReply(text), 'bot');
        setQuick(defaultQuick);
        track('support_chat_error');
      });
    }

    function setQuick(arr) {
      var list = Array.isArray(arr) && arr.length ? arr : defaultQuick;
      quick.innerHTML = '';
      list.slice(0, 14).forEach(function (q) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = String(q);
        b.addEventListener('click', function () {
          sendFromButton(q);
        });
        quick.appendChild(b);
      });
    }

    function open() {
      clearBlockingOverlays();
      panel.classList.remove('th-support-chat__hidden');
      document.body.classList.add('th-support-chat-open');
      track('support_chat_open');
      if (!booted) {
        booted = true;
        addMsg('Здравствуйте! Выберите тему кнопкой — подскажу по турам, оплате и бронированию.', 'bot');
        setQuick(defaultQuick);
        send('').then(function (res) {
          if (!res || !res.success) return;
          sessionId = res.sessionId || sessionId;
          try { localStorage.setItem(sessionKey, sessionId); } catch (e) {}
          if (Array.isArray(res.quickReplies) && res.quickReplies.length) setQuick(res.quickReplies);
          if (res.reply) {
            // приветствие с сервера вместо локального дубля — обновим последним
          }
        }).catch(function () {});
      }
    }

    function close() {
      panel.classList.add('th-support-chat__hidden');
      document.body.classList.remove('th-support-chat-open');
    }

    global.THSupportChat = {
      open: open,
      close: close,
      isOpen: function () {
        return !panel.classList.contains('th-support-chat__hidden');
      }
    };

    toggle.addEventListener('click', function () {
      if (panel.classList.contains('th-support-chat__hidden')) open();
      else close();
    });
    closeBtn.addEventListener('click', close);

    managerBtn.addEventListener('click', function () {
      track('support_chat_handoff');
      if (typeof global.openQuickLeadModal === 'function') {
        global.openQuickLeadModal('support-chat');
      } else {
        global.location.href = 'tel:+78462541656';
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);
