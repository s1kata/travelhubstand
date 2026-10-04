(function () {
  if (window.__MAX_WIDGET_BOOTSTRAPPED__) return;
  window.__MAX_WIDGET_BOOTSTRAPPED__ = true;

  var cfg = window.MAX_WIDGET_CONFIG || {};
  var doc = document;
  var type = cfg.type || 'widget';
  var isMobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
  var appearance = cfg.appearance || {};
  var buttonCfg = appearance.button || {};
  var widgetCfg = appearance.widget || {};
  var typography = appearance.typography || {};
  var welcome = appearance.welcome || {};
  var mobile = appearance.mobile || {};
  var popup = cfg.popup || {};
  if (cfg.display) {
    if (isMobile && cfg.display.mobile === false) return;
    if (!isMobile && cfg.display.desktop === false) return;
  }
  if (cfg.hideOnMobile && isMobile) return;

  function pickUrl(channel) {
    if (!channel || channel.on === false) return '';
    if (typeof channel.url === 'string' && channel.url) return channel.url;
    if (typeof channel.link === 'string' && channel.link) return channel.link;
    if (typeof channel.href === 'string' && channel.href) return channel.href;
    return '';
  }

  function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
  }
  function hexToRgba(hex, alpha) {
    if (!hex || typeof hex !== 'string') return 'rgba(108,79,152,' + alpha + ')';
    var h = hex.replace('#', '').trim();
    if (h.length === 3)
      h = h
        .split('')
        .map(function (c) {
          return c + c;
        })
        .join('');
    if (h.length !== 6) return hex;
    var r = parseInt(h.slice(0, 2), 16);
    var g = parseInt(h.slice(2, 4), 16);
    var b = parseInt(h.slice(4, 6), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }
  function esc(text) {
    return String(text || '').replace(/[&<>"]/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch];
    });
  }
  function channelIconClass(kind) {
    return (
      {
        tg: 'fa-brands fa-telegram',
        wa: 'fa-brands fa-whatsapp',
        vk: 'fa-brands fa-vk',
        viber: 'fa-brands fa-viber',
        phone: 'fa-solid fa-phone',
        gmap: 'fa-solid fa-map-location-dot',
        ymap: 'fa-solid fa-location-dot',
        chat: 'fa-solid fa-headset',
        callback: 'fa-solid fa-phone-volume',
      }[kind] || 'fa-solid fa-comment-dots'
    );
  }
  function iconMarkup(kind, size, color, iconClass) {
    var s = size || 18;
    var c = color || 'currentColor';
    if (kind === 'max') {
      var maxLogoUrl =
        widgetCfg.useDefaultMaxLogo === false && widgetCfg.customMaxLogoUrl
          ? widgetCfg.customMaxLogoUrl
          : 'https://max-widget.ru/assets/images/brand/max-messenger-sign-logo.svg';
      return (
        '<img src="' +
        esc(maxLogoUrl) +
        '" alt="MAX" style="width:' +
        s +
        'px;height:' +
        s +
        'px;object-fit:contain;display:block;">'
      );
    }
    var cls = iconClass || channelIconClass(kind);
    return '<i class="' + cls + '" style="font-size:' + s + 'px;color:' + c + ';"></i>';
  }
  function channelColor(kind, fallback) {
    return (
      {
        max: '#F59E0B',
        tg: '#2AABEE',
        wa: '#25D366',
        vk: '#2787F5',
        viber: '#7360F2',
        phone: '#16A34A',
        gmap: '#2563EB',
        ymap: '#DC2626',
        chat: '#F97316',
        callback: '#0EA5E9',
      }[kind] ||
      fallback ||
      '#6C4F98'
    );
  }

  var channels = Array.isArray(cfg.channels) ? cfg.channels : [];
  var isActionChannel = function (ch) {
    return ch && (ch.type === 'callback' || ch.type === 'chat');
  };
  var active = channels.filter(function (ch) {
    return pickUrl(ch) || isActionChannel(ch);
  });
  var activeLinkChannels = active.filter(function (ch) {
    return pickUrl(ch);
  });
  var singleDirectChannel =
    widgetCfg.singleChannelDirect !== false && active.length === 1 && !isActionChannel(active[0]) ? active[0] : null;
  if (type === 'button' && !activeLinkChannels.length) return;
  if (type !== 'button' && !active.length) return;

  var styleName = cfg.style || 'glass';
  var blockLayout = widgetCfg.layout || 'classic';
  var pos = cfg.pos || 'bottom-right';
  var offset = Number(cfg.offset || 20);
  var z = Number(cfg.zIndex || 9999);
  var scale = isMobile ? clamp(Number(mobile.scale || 100) / 100, 0.7, 1.1) : 1;
  var borderAlpha = clamp(Number(popup.borderOpacity || 18) / 100, 0.05, 0.6);
  var panelWidth = Math.round(Number(popup.width || 260) * scale);
  var widgetSize = Math.max(40, Math.min(100, Number(widgetCfg.size || 56))) * scale;
  var widgetBgColor = widgetCfg.bgColor || (styleName === 'modern' ? '#FFFFFF' : cfg.bg || '#6C4F98');
  var widgetTextColor = widgetCfg.textColor || '#F8F5FF';
  var welcomeBgColor = welcome.bgColor || (styleName === 'modern' ? '#FFFFFF' : hexToRgba(cfg.bg || '#6C4F98', 0.32));
  var widgetIconColor = widgetCfg.textColor || (styleName === 'modern' ? '#0F172A' : '#FFFFFF');
  var titleColor = popup.titleColor || '#FFFFFF';
  var panelBg = popup.bg || '#705492';
  var panelCardBg = popup.cardBg || '#8B6BA8';
  var panelHeadBg = popup.headBg || '#7B5E9C';
  var panelTextColor = popup.textColor || '#F1E9FF';
  var panelBorder = hexToRgba(popup.borderColor || '#C5AEDB', borderAlpha);
  var titleSize = Number(popup.titleSize || 14);
  var textSize = Number(popup.textSize || 14);
  var descSize = Number(popup.descSize || 11);
  var welcomeFontSize = Math.max(11, Math.round(Number(welcome.fontSize || 12) * scale));

  var host = doc.createElement('div');
  host.style.position = 'fixed';
  host.style.zIndex = String(z);
  host.style.pointerEvents = 'none';
  if (pos.indexOf('bottom') !== -1) host.style.bottom = offset + 'px';
  if (pos.indexOf('top') !== -1) host.style.top = offset + 'px';
  if (pos.indexOf('left') !== -1) host.style.left = offset + 'px';
  if (pos.indexOf('right') !== -1) host.style.right = offset + 'px';

  var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
  var faLink = doc.createElement('link');
  faLink.rel = 'stylesheet';
  faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
  root.appendChild(faLink);
  var style = doc.createElement('style');
  style.textContent =
    '.mw-root{position:relative;pointer-events:auto;font-family:Inter,system-ui,-apple-system,sans-serif}.mw-widget{position:relative;display:flex;align-items:center;gap:10px}.mw-widget.rev{flex-direction:row-reverse}.mw-widget.top{align-items:flex-start}.mw-bubble{padding:9px 13px;border-radius:11px;font-size:12px;font-weight:800;line-height:1.45;max-width:220px;word-break:break-word}.mw-bubble.chat{max-width:260px;border-radius:18px}.mw-trigger{border:0;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:transform .2s ease}.mw-trigger:hover{transform:scale(1.08)}.mw-widget-icon{width:56px;height:56px;border-radius:50%;overflow:hidden;flex-shrink:0}.mw-widget-icon img{width:100%;height:100%;object-fit:cover;display:block}.mw-button{min-height:42px;padding:0 18px;border:0;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:700;cursor:pointer}.mw-panel{position:absolute;display:none;overflow:hidden;border-style:solid;border-width:1px;max-width:calc(100vw - 24px);animation:mwPopIn .26s cubic-bezier(.22,.68,0,1.2)}.mw-panel.open{display:block}.mw-panel::before{content:"";position:absolute;top:0;left:0;right:0;height:50%;background:linear-gradient(110deg,rgba(255,255,255,0) 0%,rgba(200,180,255,.12) 25%,rgba(130,210,255,.10) 50%,rgba(255,200,180,.08) 75%,rgba(255,255,255,0) 100%);border-radius:20px 20px 0 0;pointer-events:none;z-index:1}.mw-panel::after{content:"";position:absolute;bottom:0;left:0;right:0;height:40%;background:linear-gradient(0deg,rgba(0,0,20,.14) 0%,transparent 100%);pointer-events:none;z-index:1}.mw-head{position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;padding:14px 14px 12px;border-bottom:none}.mw-head-title{font-weight:900;font-size:13px;letter-spacing:-.01em;text-shadow:0 1px 4px rgba(0,0,0,.25)}.mw-close{width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:11px;line-height:1;display:flex;align-items:center;justify-content:center;transition:all .15s;backdrop-filter:blur(8px)}.mw-list{position:relative;z-index:2;display:flex;flex-direction:column;gap:9px;padding:10px}.mw-list.horizontal{flex-direction:row;flex-wrap:wrap}.mw-link{display:flex;align-items:center;gap:10px;padding:10px 11px;border-radius:13px;text-decoration:none;color:inherit;transition:all .2s;backdrop-filter:blur(16px);box-shadow:inset 0 1px 0 rgba(255,255,255,.24),0 2px 8px rgba(0,0,0,.12)}.mw-link:hover{transform:translateY(-1px) scale(1.01);box-shadow:inset 0 1px 0 rgba(255,255,255,.3),0 6px 18px rgba(0,0,0,.15)}.mw-link-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:inset 0 1px 0 rgba(255,255,255,.25);backdrop-filter:blur(8px)}.mw-link-text{display:flex;flex-direction:column;gap:2px}.mw-link-name{font-weight:900;display:block;text-shadow:0 1px 3px rgba(0,0,0,.2)}.mw-link-desc{font-weight:600;display:block;opacity:.72}.mw-trigger-icon{display:inline-flex;align-items:center;justify-content:center;line-height:1}.mw-trigger-icon svg,.mw-link-icon svg{display:block}.mw-style-glass .mw-widget-icon{backdrop-filter:blur(18px) saturate(170%);border:1px solid rgba(220,199,239,.26);box-shadow:inset 0 1px 0 rgba(255,255,255,.44),0 10px 28px rgba(0,0,0,.32)}.mw-style-glass .mw-bubble{backdrop-filter:blur(18px) saturate(165%);border:1px solid rgba(217,191,236,.24);box-shadow:inset 0 1px 0 rgba(255,255,255,.32),0 8px 24px rgba(0,0,0,.26)}.mw-style-dg .mw-widget-icon{background:rgba(10,25,41,.7)!important;backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.1);box-shadow:0 10px 28px rgba(0,0,0,.4)}.mw-style-dg .mw-bubble{background:rgba(10,25,41,.75)!important;backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.08);color:#fff!important}.mw-style-aurora .mw-widget-icon{backdrop-filter:blur(14px) saturate(180%);border:1px solid rgba(255,255,255,.18);box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 10px 28px rgba(87,45,255,.35)}.mw-style-aurora .mw-bubble{backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.16);color:#fff!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.28)}.mw-style-neon .mw-widget-icon{box-shadow:0 0 0 1px rgba(87,45,255,.2),0 10px 26px rgba(87,45,255,.5),0 0 40px rgba(1,197,200,.2)}.mw-style-neon .mw-bubble{background:rgba(5,5,20,.8)!important;border:1px solid rgba(87,45,255,.3);color:#fff!important;box-shadow:0 0 20px rgba(87,45,255,.3),inset 0 1px 0 rgba(87,45,255,.2)}.mw-style-modern .mw-widget-icon{background:rgba(255,255,255,.95)!important;box-shadow:0 12px 34px rgba(0,0,0,.4)}.mw-style-modern .mw-bubble{background:rgba(255,255,255,.92)!important;border:1px solid rgba(255,255,255,.4);box-shadow:0 6px 20px rgba(0,0,0,.25);color:#0A1929!important}.mw-style-minimal .mw-widget-icon{background:rgba(255,255,255,.1)!important;backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.2);box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 7px 20px rgba(0,0,0,.25)}.mw-style-minimal .mw-bubble{background:rgba(255,255,255,.1)!important;backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.18);box-shadow:inset 0 1px 0 rgba(255,255,255,.28),0 4px 10px rgba(0,0,0,.2);color:rgba(255,255,255,.9)!important}@keyframes mwPopIn{from{transform:translateY(10px) scale(.95);opacity:0}to{transform:translateY(0) scale(1);opacity:1}}@media (max-width:768px){.mw-panel{max-width:calc(100vw - 16px)}}';
  root.appendChild(style);

  var overlayStyle = doc.createElement('style');
  overlayStyle.textContent =
    '.mw-overlay{position:fixed;inset:0;z-index:10;display:none;background:rgba(5,10,30,.28);backdrop-filter:blur(2px)}.mw-overlay.open{display:block}.mw-widget,.mw-button-wrap{position:relative;z-index:30}.mw-panel{z-index:20}.mw-trigger-icon i,.mw-link-icon i{line-height:1}.mw-button-wrap{display:inline-flex}.mw-cbf{position:relative;z-index:2;padding:10px;display:none}.mw-cbf-title{font-size:13px;font-weight:800;color:#fff;margin-bottom:8px}.mw-cbf-field{margin-bottom:8px}.mw-cbf-input,.mw-cbf-textarea{width:100%;padding:9px 10px;border-radius:10px;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.14);color:#fff;font-family:Inter,system-ui,-apple-system,sans-serif;font-size:12px}.mw-cbf-input::placeholder,.mw-cbf-textarea::placeholder{color:rgba(255,255,255,.65)}.mw-cbf-textarea{min-height:72px;resize:vertical}.mw-cbf-actions{display:flex;gap:8px;margin-top:6px}.mw-cbf-btn{flex:1;padding:9px 10px;border-radius:10px;border:1px solid rgba(255,255,255,.24);background:rgba(255,255,255,.18);color:#fff;font-weight:700;cursor:pointer}.mw-cbf-btn.primary{background:linear-gradient(145deg,rgba(255,120,90,.95),rgba(255,74,60,.95));border-color:transparent}.mw-cbf-success{display:none;font-size:12px;font-weight:700;color:#fff;background:rgba(16,185,129,.25);border:1px solid rgba(16,185,129,.5);padding:9px 10px;border-radius:10px;margin-top:8px}.mw-pop-toast{position:absolute;left:10px;right:10px;bottom:10px;z-index:3;padding:9px 10px;border-radius:10px;background:rgba(245,158,11,.22);border:1px solid rgba(245,158,11,.5);color:#fff;font-size:12px;font-weight:700;display:none}';
  root.appendChild(overlayStyle);

  var app = doc.createElement('div');
  app.className = 'mw-root mw-style-' + styleName;
  if (typography.fontFamily && typography.fontFamily !== 'inherit') app.style.fontFamily = typography.fontFamily;
  if (typography.fontWeight) app.style.fontWeight = String(typography.fontWeight);

  var overlay = doc.createElement('div');
  overlay.className = 'mw-overlay';
  app.appendChild(overlay);

  var panel = doc.createElement('div');
  panel.className = 'mw-panel';
  panel.style.width = panelWidth + 'px';
  panel.style.borderRadius = Math.round(Number(popup.radius || 16)) + 'px';
  panel.style.background = panelBg;
  panel.style.borderColor = panelBorder;
  if ((popup.shadow || 'soft') === 'soft') panel.style.boxShadow = '0 16px 42px rgba(0,0,0,.22), 0 4px 12px rgba(0,0,0,.14)';
  if (popup.shadow === 'medium') panel.style.boxShadow = '0 22px 56px rgba(0,0,0,.32), 0 7px 20px rgba(0,0,0,.2)';
  if (popup.shadow === 'strong') panel.style.boxShadow = '0 28px 72px rgba(0,0,0,.44), 0 10px 28px rgba(0,0,0,.28)';

  var head = doc.createElement('div');
  head.className = 'mw-head';
  head.style.background = panelHeadBg;
  var headTitle = doc.createElement('div');
  headTitle.className = 'mw-head-title';
  headTitle.textContent = popup.title || 'Свяжитесь с нами';
  headTitle.style.color = titleColor;
  headTitle.style.fontSize = titleSize + 'px';
  headTitle.style.fontWeight = typography.fontWeight || '700';
  head.appendChild(headTitle);
  var closeBtn = doc.createElement('button');
  closeBtn.type = 'button';
  closeBtn.className = 'mw-close';
  closeBtn.textContent = '\u00d7';
  closeBtn.style.color = titleColor;
  closeBtn.style.borderColor = panelBorder;
  closeBtn.style.background = panelCardBg;
  head.appendChild(closeBtn);
  panel.appendChild(head);

  var list = doc.createElement('div');
  list.className = 'mw-list' + (blockLayout === 'horizontal' ? ' horizontal' : '');
  active.forEach(function (ch) {
    var a = doc.createElement('a');
    a.className = 'mw-link';
    var href = pickUrl(ch);
    a.href = href || '#';
    if (href) {
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
    }
    a.style.background = panelCardBg;
    a.style.borderColor = panelBorder;
    a.style.color = panelTextColor;
    var iconBox = doc.createElement('div');
    iconBox.className = 'mw-link-icon';
    var iconSize = Math.max(14, Math.min(28, Number(widgetCfg.iconSize || 20)));
    var iconBoxSize = Math.max(28, Math.min(52, iconSize + 14));
    var kind = ch.type || 'max';
    iconBox.style.width = iconBoxSize + 'px';
    iconBox.style.height = iconBoxSize + 'px';
    iconBox.style.borderColor = panelBorder;
    iconBox.style.color = panelTextColor;
    iconBox.innerHTML = iconMarkup(kind, iconSize, panelTextColor, channelIconClass(kind));
    if (kind === 'max') {
      var maxImg = iconBox.querySelector('img');
      iconBox.style.background = 'transparent';
      iconBox.style.borderColor = 'transparent';
      if (maxImg) maxImg.style.filter = 'none';
    } else if (widgetCfg.iconStyle === 'mono') {
      iconBox.style.background = 'transparent';
      iconBox.style.borderColor = channelColor(kind, buttonCfg.bg || cfg.bg);
      iconBox.innerHTML = iconMarkup(kind, iconSize, channelColor(kind, buttonCfg.bg || cfg.bg), channelIconClass(kind));
    } else if (widgetCfg.iconStyle === 'flat') {
      iconBox.style.background = 'transparent';
      iconBox.style.borderColor = 'transparent';
      iconBox.innerHTML = iconMarkup(kind, iconSize, channelColor(kind, buttonCfg.bg || cfg.bg), channelIconClass(kind));
    } else {
      iconBox.style.background = channelColor(kind, buttonCfg.bg || cfg.bg);
      iconBox.style.borderColor = 'transparent';
      iconBox.innerHTML = iconMarkup(kind, iconSize, '#FFFFFF', channelIconClass(kind));
    }
    var textBox = doc.createElement('div');
    textBox.className = 'mw-link-text';
    var nm = doc.createElement('span');
    nm.className = 'mw-link-name';
    nm.textContent = ch.label || ch.name || ch.type || 'Канал';
    nm.style.color = panelTextColor;
    nm.style.fontWeight = typography.fontWeight || '700';
    nm.style.fontSize = textSize + 'px';
    var ds = doc.createElement('span');
    ds.className = 'mw-link-desc';
    ds.textContent = ch.btnText || 'Открыть ' + (ch.label || ch.type || 'канал');
    ds.style.color = panelTextColor;
    ds.style.fontWeight = String(Math.max(400, Number(typography.fontWeight || 700) - 200));
    ds.style.fontSize = descSize + 'px';
    textBox.appendChild(nm);
    textBox.appendChild(ds);
    a.appendChild(iconBox);
    a.appendChild(textBox);
    if (ch.type === 'callback') {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        list.style.display = 'none';
        toast.style.display = 'none';
        formWrap.style.display = 'block';
      });
    } else if (ch.type === 'chat') {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        toast.textContent =
          ((cfg.personalization || {}).chat || {}).unavailableText || 'Онлайн-чат скоро будет доступен';
        toast.style.display = 'block';
      });
    } else if (!href) {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
      });
    }
    list.appendChild(a);
  });
  panel.appendChild(list);

  var callbackCfg = cfg.callbackLead || {};
  var callbackFields = callbackCfg.fields || {};
  var callbackTexts = callbackCfg.texts || {};
  var callbackDelivery = callbackCfg.delivery || {};
  var formWrap = doc.createElement('div');
  formWrap.className = 'mw-cbf';
  formWrap.innerHTML =
    '<div class="mw-cbf-title"></div><form><div class="mw-cbf-field"><input class="mw-cbf-input" name="name" placeholder="Ваше имя" required></div><div class="mw-cbf-field"><input class="mw-cbf-input" name="phone" placeholder="Телефон" required></div><div class="mw-cbf-field mw-cbf-email"><input class="mw-cbf-input" name="email" placeholder="Email"></div><div class="mw-cbf-field mw-cbf-message"><textarea class="mw-cbf-textarea" name="message" placeholder="Сообщение"></textarea></div><div class="mw-cbf-actions"><button type="button" class="mw-cbf-btn mw-cbf-back">Назад</button><button type="submit" class="mw-cbf-btn primary mw-cbf-submit">Отправить</button></div><div class="mw-cbf-success"></div></form>';
  formWrap.querySelector('.mw-cbf-title').textContent = callbackTexts.title || 'Заказать звонок';
  formWrap.querySelector('.mw-cbf-submit').textContent = callbackTexts.button || 'Перезвоните мне';
  formWrap.querySelector('.mw-cbf-email').style.display = callbackFields.email ? 'block' : 'none';
  formWrap.querySelector('.mw-cbf-message').style.display = callbackFields.message ? 'block' : 'none';
  panel.appendChild(formWrap);

  var toast = doc.createElement('div');
  toast.className = 'mw-pop-toast';
  panel.appendChild(toast);

  function placePanelForWidget() {
    panel.style.top = 'auto';
    panel.style.bottom = 'auto';
    panel.style.left = 'auto';
    panel.style.right = 'auto';
    panel.style.transform = '';
    if (blockLayout === 'horizontal') {
      panel.style.width = 'min(' + Math.round(560 * scale) + 'px, calc(100vw - 20px))';
      panel.style.left = '50%';
      panel.style.bottom = '8px';
      panel.style.transform = 'translateX(-50%)';
      return;
    }
    if (pos.indexOf('bottom') !== -1) panel.style.bottom = widgetSize + 8 + 'px';
    if (pos.indexOf('top') !== -1) panel.style.top = widgetSize + 8 + 'px';
    if (pos.indexOf('right') !== -1) panel.style.right = '0';
    if (pos.indexOf('left') !== -1) panel.style.left = '0';
  }

  function openPanel() {
    panel.classList.add('open');
    overlay.classList.add('open');
  }
  function closePanel() {
    panel.classList.remove('open');
    overlay.classList.remove('open');
    list.style.display = 'flex';
    formWrap.style.display = 'none';
    toast.style.display = 'none';
  }
  function togglePanel() {
    if (panel.classList.contains('open')) closePanel();
    else openPanel();
  }
  closeBtn.addEventListener('click', closePanel);
  overlay.addEventListener('click', closePanel);
  formWrap.querySelector('.mw-cbf-back').addEventListener('click', function () {
    formWrap.style.display = 'none';
    list.style.display = 'flex';
  });
  formWrap.querySelector('form').addEventListener('submit', async function (ev) {
    ev.preventDefault();
    var payload = {
      channel: 'callback',
      name: formWrap.querySelector('[name="name"]').value.trim(),
      phone: formWrap.querySelector('[name="phone"]').value.trim(),
      email: callbackFields.email ? formWrap.querySelector('[name="email"]').value.trim() : '',
      message: callbackFields.message ? formWrap.querySelector('[name="message"]').value.trim() : '',
      createdAt: new Date().toISOString(),
    };
    if (!payload.name || !payload.phone) return;
    var delivered = false;
    if (callbackDelivery.webhook && callbackDelivery.webhook.enabled && callbackDelivery.webhook.url) {
      try {
        var res = await fetch(callbackDelivery.webhook.url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        if (res.ok) delivered = true;
      } catch (_) {}
    }
    if (callbackDelivery.email && callbackDelivery.email.enabled && callbackDelivery.email.to) delivered = true;
    if (
      callbackDelivery.telegram &&
      callbackDelivery.telegram.enabled &&
      callbackDelivery.telegram.botToken &&
      callbackDelivery.telegram.chatId
    )
      delivered = true;
    var success = formWrap.querySelector('.mw-cbf-success');
    success.textContent = delivered
      ? callbackTexts.success || 'Спасибо! Мы перезвоним в ближайшее время.'
      : 'Ошибка отправки. Проверьте настройки доставки.';
    success.style.display = 'block';
    if (delivered) ev.target.reset();
  });

  if (type === 'widget') {
    var widgetWrap = doc.createElement('div');
    widgetWrap.className = 'mw-widget';
    if (pos.indexOf('left') !== -1) widgetWrap.classList.add('rev');
    if (pos.indexOf('top') !== -1) widgetWrap.classList.add('top');

    var showWelcome =
      welcome.enabled !== false &&
      !(isMobile && welcome.hideOnMobile) &&
      !!welcome.text &&
      blockLayout !== 'minimal' &&
      blockLayout !== 'horizontal';
    if (showWelcome) {
      var bubble = doc.createElement('div');
      bubble.className = 'mw-bubble' + (blockLayout === 'chat' ? ' chat' : '');
      bubble.textContent = welcome.text;
      bubble.style.opacity = String(clamp(Number(welcome.opacity || 95) / 100, 0.2, 1));
      bubble.style.fontWeight = typography.fontWeight || '700';
      bubble.style.fontSize = welcomeFontSize + 'px';
      bubble.style.background = welcomeBgColor;
      bubble.style.color = widgetTextColor;
      bubble.style.border = '1px solid ' + panelBorder;
      if (blockLayout === 'glassmorphism') {
        bubble.style.background = welcome.bgColor || 'rgba(255,255,255,.18)';
        bubble.style.backdropFilter = 'blur(16px) saturate(160%)';
      }
      widgetWrap.appendChild(bubble);
    }

    var trigger = doc.createElement('button');
    trigger.type = 'button';
    trigger.className = 'mw-trigger mw-widget-icon';
    trigger.style.width = widgetSize + 'px';
    trigger.style.height = widgetSize + 'px';
    trigger.style.borderRadius = widgetCfg.shape || '50%';
    trigger.style.background = widgetBgColor;
    trigger.style.color = widgetIconColor;
    if (widgetCfg.managerPhotoUrl) {
      trigger.innerHTML = '<img src="' + esc(widgetCfg.managerPhotoUrl) + '" alt="manager">';
    } else if (active.length > 1 && widgetCfg.multiIconMode === 'custom' && widgetCfg.multiIconUrl) {
      trigger.innerHTML =
        '<img src="' +
        esc(widgetCfg.multiIconUrl) +
        '" alt="icon" style="width:' +
        (widgetCfg.iconSize || 20) +
        'px;height:' +
        (widgetCfg.iconSize || 20) +
        'px;object-fit:contain;display:block;">';
    } else {
      var triggerKind =
        active.length > 1
          ? widgetCfg.multiIcon && widgetCfg.multiIcon.indexOf('telegram') !== -1
            ? 'tg'
            : 'chat'
          : active[0].type || 'max';
      if (active.length > 1 && widgetCfg.multiIconMode !== 'custom') {
        trigger.innerHTML =
          '<span class="mw-trigger-icon">' +
          iconMarkup(
            'custom',
            widgetCfg.iconSize || 20,
            widgetCfg.multiIconColor || widgetIconColor,
            widgetCfg.multiIcon || 'fa-solid fa-comments'
          ) +
          '</span>';
      } else {
        trigger.innerHTML =
          '<span class="mw-trigger-icon">' +
          iconMarkup(triggerKind, widgetCfg.iconSize || 20, widgetIconColor, channelIconClass(triggerKind)) +
          '</span>';
      }
    }
    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (singleDirectChannel) {
        window.open(pickUrl(singleDirectChannel), '_blank', 'noopener,noreferrer');
        return;
      }
      togglePanel();
    });
    widgetWrap.appendChild(trigger);
    app.appendChild(widgetWrap);
    app.appendChild(panel);
    placePanelForWidget();
  } else {
    var buttonWrap = doc.createElement('div');
    buttonWrap.className = 'mw-button-wrap';
    var button = doc.createElement('a');
    button.className = 'mw-trigger mw-button';
    button.style.height = Math.max(36, Math.round(Number(buttonCfg.height || 48) * scale)) + 'px';
    button.style.borderRadius = Math.round(Number(buttonCfg.radius || 12)) + 'px';
    button.style.fontSize = Math.max(11, Math.round(Number(buttonCfg.fontSize || 15) * scale)) + 'px';
    button.style.fontWeight = typography.fontWeight || '700';
    button.style.textDecoration = 'none';
    if ((buttonCfg.style || 'solid') === 'outline') {
      button.style.background = 'transparent';
      button.style.border = '1px solid ' + (buttonCfg.bg || '#6C4F98');
      button.style.color = buttonCfg.bg || '#6C4F98';
      button.style.boxShadow = 'none';
    } else if (buttonCfg.style === 'gradient') {
      button.style.background =
        'linear-gradient(135deg,' + (buttonCfg.bg || '#6C4F98') + ',' + (cfg.bg || '#2D085F') + ')';
      button.style.color = buttonCfg.textColor || '#fff';
    } else if (buttonCfg.style === 'glass') {
      button.style.background = 'linear-gradient(145deg,rgba(149,111,180,.56),rgba(96,66,128,.56))';
      button.style.backdropFilter = 'blur(14px) saturate(160%)';
      button.style.border = '1px solid rgba(217,191,236,.24)';
      button.style.color = buttonCfg.textColor || '#fff';
    } else {
      button.style.background = buttonCfg.bg || '#6C4F98';
      button.style.color = buttonCfg.textColor || '#fff';
    }
    if (cfg.theme === 'light' && (buttonCfg.style || 'solid') !== 'outline') button.style.color = '#101827';
    if (cfg.theme === 'dark' && (buttonCfg.style || 'solid') !== 'outline') button.style.color = '#FFFFFF';
    button.href = pickUrl(activeLinkChannels[0]);
    button.target = '_blank';
    button.rel = 'noopener noreferrer';
    if (buttonCfg.showIcon !== false) {
      var bi = doc.createElement('span');
      bi.className = 'mw-trigger-icon';
      bi.innerHTML = iconMarkup(
        'custom',
        buttonCfg.iconSize || 15,
        buttonCfg.iconColor || button.style.color || '#fff',
        buttonCfg.icon || 'fa-solid fa-comment-dots'
      );
      button.appendChild(bi);
    }
    var buttonText = doc.createElement('span');
    buttonText.textContent = buttonCfg.text || 'Связаться';
    button.appendChild(buttonText);
    buttonWrap.appendChild(button);
    app.appendChild(buttonWrap);
  }

  if (blockLayout === 'chat') {
    panel.style.background = '#0B1530';
    head.style.background = '#FF4B3A';
  }
  if (blockLayout === 'business') panel.style.width = Math.round(320 * scale) + 'px';
  if (blockLayout === 'consultant') {
    panel.style.width = Math.round(300 * scale) + 'px';
    head.style.background = '#FF4B3A';
  }

  root.appendChild(app);
  doc.body.appendChild(host);
  doc.addEventListener(
    'click',
    function (e) {
      if (!host.contains(e.target)) closePanel();
    },
    { passive: true }
  );
})();
