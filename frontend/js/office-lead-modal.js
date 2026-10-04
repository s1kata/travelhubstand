/**
 * Мини-попап «Заявка в офис» — короткий lead (имя + телефон) через THLeadCapture.
 * Триггер: [data-th-office-lead-btn="1"] + data-office-city / data-office-name.
 */
(function () {
  'use strict';

  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function ensureStyles() {
    if (document.getElementById('th-office-lead-styles')) return;
    var css = document.createElement('style');
    css.id = 'th-office-lead-styles';
    css.textContent = [
      '#th-office-lead-ov{position:fixed;inset:0;z-index:100000;background:rgba(16,16,46,.55);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);display:flex;align-items:flex-end;justify-content:center;padding:0;opacity:0;pointer-events:none;transition:opacity .22s ease}',
      '@media (min-width:640px){#th-office-lead-ov{align-items:center;padding:1rem}}',
      '#th-office-lead-ov.th-open{opacity:1;pointer-events:auto}',
      '#th-office-lead-card{max-width:28rem;width:100%;border-radius:1.25rem 1.25rem 0 0;overflow:hidden;background:#fff;box-shadow:0 25px 60px rgba(2,6,23,.35);transform:translateY(16px);transition:transform .28s cubic-bezier(.22,1,.36,1)}',
      '@media (min-width:640px){#th-office-lead-card{border-radius:1.25rem;transform:translateY(10px) scale(.98)}}',
      '#th-office-lead-ov.th-open #th-office-lead-card{transform:translateY(0) scale(1)}',
      '.th-ol-head{padding:1.1rem 1.15rem;color:#fff !important;background:linear-gradient(135deg,#1A1A40 0%,#2a4a6b 55%,#5DA9A4 100%)}',
      '.th-ol-title{font-size:1.15rem;font-weight:800;line-height:1.2;margin:0;font-family:Outfit,sans-serif;color:#fff !important}',
      '.th-ol-sub{margin-top:.35rem;font-size:.86rem;opacity:.92;line-height:1.4;color:#fff !important}',
      '.th-ol-body{padding:1rem 1.15rem calc(1.15rem + env(safe-area-inset-bottom,0px))}',
      '.th-ol-label{display:block;font-size:.78rem;color:#475569;font-weight:700;margin-bottom:.25rem}',
      '.th-ol-inp{width:100%;border:1.5px solid #e2e8f0;border-radius:.9rem;padding:.85rem .9rem;font-size:1rem;outline:none;margin-bottom:.75rem;box-sizing:border-box;color:#0f172a;background:#fff}',
      '.th-ol-inp:focus{border-color:#5DA9A4;box-shadow:0 0 0 3px rgba(93,169,164,.18)}',
      '.th-ol-row{display:flex;align-items:flex-start;gap:.6rem;margin:.25rem 0 .85rem}',
      '.th-ol-row input{margin-top:.2rem}',
      '.th-ol-actions{display:flex;gap:.6rem}',
      '.th-ol-btn{flex:1;border:none;border-radius:.95rem;padding:.9rem;font-weight:800;font-size:.95rem;cursor:pointer;min-height:48px}',
      '.th-ol-btn-primary{background:#FF6B6B;color:#fff !important}',
      '.th-ol-btn-ghost{background:#f1f5f9;color:#0f172a}',
      '.th-ol-msg{margin-top:.75rem;font-size:.9rem;padding:.65rem .75rem;border-radius:.85rem;display:none}',
      '.th-ol-msg.ok{display:block;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}',
      '.th-ol-msg.bad{display:block;background:#fef2f2;color:#991b1b;border:1px solid #fecaca}'
    ].join('');
    document.head.appendChild(css);
  }

  function close() {
    var ov = document.getElementById('th-office-lead-ov');
    if (!ov) return;
    ov.classList.remove('th-open');
    setTimeout(function () {
      if (ov && ov.parentNode) ov.parentNode.removeChild(ov);
    }, 240);
  }

  function open(opts) {
    opts = opts || {};
    ensureStyles();
    close();
    var officeCity = opts.officeCity || '';
    var officeName = opts.officeName || '';
    var title = officeName ? ('Заявка: ' + officeName) : 'Заявка в офис';
    var sub = 'Перезвоним за 15 минут' + (officeCity ? (' · ' + officeCity) : '') + '. Без спама.';

    var ov = document.createElement('div');
    ov.id = 'th-office-lead-ov';
    ov.innerHTML =
      '<div id="th-office-lead-card" role="dialog" aria-modal="true" aria-label="' + esc(title) + '">' +
      '<div class="th-ol-head">' +
      '<p class="th-ol-title">' + esc(title) + '</p>' +
      '<div class="th-ol-sub">' + esc(sub) + '</div>' +
      '</div>' +
      '<div class="th-ol-body">' +
      '<label class="th-ol-label" for="th-ol-name">ФИО</label>' +
      '<input class="th-ol-inp" id="th-ol-name" autocomplete="name" maxlength="100" placeholder="Иванов Иван Иванович">' +
      '<label class="th-ol-label" for="th-ol-phone">Телефон</label>' +
      '<input class="th-ol-inp" id="th-ol-phone" inputmode="tel" autocomplete="tel" maxlength="24" placeholder="+7 (___) ___-__-__">' +
      '<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">' +
      '<label>Сайт</label><input type="text" id="th-ol-website" tabindex="-1" autocomplete="off">' +
      '</div>' +
      '<div class="th-ol-row">' +
      '<input type="checkbox" id="th-ol-agree" required>' +
      '<label for="th-ol-agree" style="font-size:.82rem;color:#475569;line-height:1.35">Согласен на <a href="/frontend/window/consent.php" target="_blank" rel="noopener" style="color:#5DA9A4;font-weight:800;text-decoration:underline">обработку персональных данных</a>, с <a href="/frontend/window/privacy.php" target="_blank" rel="noopener" style="color:#5DA9A4;font-weight:800;text-decoration:underline">Политикой конфиденциальности</a> и <a href="/frontend/window/terms.php" target="_blank" rel="noopener" style="color:#5DA9A4;font-weight:800;text-decoration:underline">Пользовательским соглашением</a></label>' +
      '</div>' +
      '<div class="th-ol-actions">' +
      '<button type="button" class="th-ol-btn th-ol-btn-ghost" id="th-ol-cancel">Отмена</button>' +
      '<button type="button" class="th-ol-btn th-ol-btn-primary" id="th-ol-send">Жду звонка</button>' +
      '</div>' +
      '<div class="th-ol-msg" id="th-ol-msg"></div>' +
      '</div></div>';

    document.body.appendChild(ov);
    requestAnimationFrame(function () { ov.classList.add('th-open'); });

    var nameEl = document.getElementById('th-ol-name');
    var phone = document.getElementById('th-ol-phone');
    var agree = document.getElementById('th-ol-agree');
    var website = document.getElementById('th-ol-website');
    var msg = document.getElementById('th-ol-msg');
    var btnSend = document.getElementById('th-ol-send');

    if (window.THLeadCapture && THLeadCapture.formatPhoneInput) {
      THLeadCapture.formatPhoneInput(phone);
    }

    setTimeout(function () { try { nameEl && nameEl.focus(); } catch (e) {} }, 50);

    function show(text, ok) {
      if (!msg) return;
      msg.className = 'th-ol-msg ' + (ok ? 'ok' : 'bad');
      msg.textContent = text;
    }

    document.getElementById('th-ol-cancel').addEventListener('click', close);
    ov.addEventListener('click', function (e) { if (e.target === ov) close(); });
    document.addEventListener('keydown', function onKey(e) {
      if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); }
    });

    btnSend.addEventListener('click', function () {
      if (btnSend.disabled) return;
      var name = (nameEl && nameEl.value || '').trim();
      var phoneVal = (phone && phone.value || '').trim();
      var okAgree = !!(agree && agree.checked);
      var websiteVal = (website && website.value) || '';

      // Единая клиентская валидация (как у uon-lead / THLeadCapture)
      if (window.THLeadCapture && typeof THLeadCapture.validateLeadFields === 'function') {
        var check = THLeadCapture.validateLeadFields({
          name: name,
          phone: phoneVal,
          agree: okAgree
        });
        if (!check.ok) {
          show(check.error || 'Проверьте данные формы', false);
          return;
        }
        name = check.name;
        phoneVal = check.phone;
      } else {
        // Fallback, если th-lead-capture.js не загрузился — те же правила
        if (!name || name.length < 2 || !/^[\p{L}\s\-'.]+$/u.test(name)) {
          show('Укажите корректные ФИО', false);
          return;
        }
        var compact = name.replace(/[\s\-'.]/g, '');
        if (compact.length < 2 || /^(\p{L})\1+$/u.test(compact)) {
          show('Укажите корректные ФИО', false);
          return;
        }
        var cyr = name.match(/\p{Script=Cyrillic}/gu);
        if (!cyr || cyr.length < 2) {
          show('Укажите ФИО русскими буквами', false);
          return;
        }
        var pd = phoneVal.replace(/\D/g, '');
        if (pd.length === 11 && pd[0] === '8') pd = '7' + pd.slice(1);
        var rest = pd.slice(1);
        if (pd.length !== 11 || pd[0] !== '7' || !rest || rest[0] !== '9' || /^(\d)\1{9}$/.test(rest)) {
          show('Укажите корректный мобильный телефон РФ (+7 9XX…)', false);
          return;
        }
        if (!okAgree) {
          show('Нужно согласие на обработку данных', false);
          return;
        }
      }

      var note = [];
      if (officeName) note.push('Офис: ' + officeName);
      if (officeCity) note.push('Город: ' + officeCity);

      function done(res) {
        if (res && res.success) {
          show(res.message || 'Заявка принята.', true);
          setTimeout(close, 900);
        } else {
          show((res && res.error) || 'Ошибка отправки', false);
        }
        btnSend.disabled = false;
        btnSend.textContent = 'Жду звонка';
      }

      btnSend.disabled = true;
      btnSend.textContent = 'Отправка…';

      // office-lead.php — серверная валидация + привязка к офису
      fetch('/backend/api/office-lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: name,
          phone: phoneVal,
          agree: okAgree,
          website: websiteVal,
          office_city: officeCity,
          office_name: officeName,
          comment: note.join(', ')
        })
      }).then(function (r) {
        return r.json().catch(function () {
          return { success: false, error: 'Ошибка ответа сервера' };
        });
      }).then(function (j) {
        if (j && j.success && window.THLeadCapture) THLeadCapture.reachGoal('lead_ok');
        done(j && j.success
          ? { success: true, message: j.message || 'Заявка принята.' }
          : { success: false, error: (j && j.error) || 'Ошибка' });
      }).catch(function () {
        done({ success: false, error: 'Ошибка сети' });
      });
    });
  }

  function bind() {
    document.querySelectorAll('[data-th-office-lead-btn="1"]').forEach(function (el) {
      if (el.__thOfficeLeadBound) return;
      el.__thOfficeLeadBound = true;
      el.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        open({
          officeCity: el.getAttribute('data-office-city') || '',
          officeName: el.getAttribute('data-office-name') || ''
        });
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  window.THOfficeLeadModal = { open: open, close: close, bind: bind };
})();
