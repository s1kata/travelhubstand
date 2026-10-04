/**
 * Promo page lead entry — opens site feedback modal with tour context.
 */
(function (global) {
  'use strict';

  function pageCfg() {
    return global.__TH_PROMO_PAGE__ || {};
  }

  function ymGoal(goal) {
    try {
      var c = pageCfg();
      var id = (c.ymId && String(c.ymId).replace(/\D/g, ''))
        ? parseInt(String(c.ymId).replace(/\D/g, ''), 10) : 0;
      if (id && typeof global.ym === 'function') global.ym(id, 'reachGoal', goal);
    } catch (e) {}
  }

  function departureLabel() {
    var c = pageCfg();
    return String(c.departureName || 'Самара').trim();
  }

  function countryLabel() {
    if (global.__promoActiveCountryName) {
      return String(global.__promoActiveCountryName).trim();
    }
    try {
      var u = new URL(global.location.href);
      return String(u.searchParams.get('countryName') || '').trim();
    } catch (e) {
      return '';
    }
  }

  function starsLabel() {
    var lbl = global.document.getElementById('promo-stars-label');
    if (!lbl) return '';
    return String(lbl.textContent || '').trim();
  }

  function buildMessage(note, extras) {
    extras = extras || {};
    var parts = ['Горящие туры / акции'];
    parts.push('Вылет: ' + departureLabel());
    var country = extras.country || countryLabel();
    if (country) parts.push('Страна: ' + country);
    if (extras.hotelName) parts.push('Отель: ' + extras.hotelName);
    if (extras.hotelPrice) parts.push('Цена: ' + extras.hotelPrice + ' ₽');
    var stars = starsLabel();
    if (stars) parts.push('Фильтр отелей: ' + stars);
    if (note) parts.push(String(note));
    return parts.join('\n');
  }

  /**
   * @param {object} [opts]
   * @param {string} [opts.source]
   * @param {string} [opts.title]
   * @param {string} [opts.sub]
   * @param {string} [opts.note]
   * @param {string} [opts.message]
   * @param {boolean} [opts.focusPhone]
   */
  function open(opts) {
    opts = opts || {};
    var source = String(opts.source || 'promo_lead');
    var message = opts.message || buildMessage(opts.note || '', {
      country: opts.country,
      hotelName: opts.hotelName,
      hotelPrice: opts.hotelPrice
    });
    ymGoal('promo_lead_modal_open');

    if (typeof global.openSiteFeedbackModal === 'function') {
      global.openSiteFeedbackModal({
        title: opts.title || 'Подобрать горящий тур',
        sub: opts.sub || 'Оставьте телефон — перезвоним за 15 минут с лучшими акциями.',
        message: message,
        source: source,
        focusPhone: opts.focusPhone !== false,
        phoneOnly: !!opts.phoneOnly
      });
      return true;
    }

    return false;
  }

  global.THPromoLead = {
    open: open,
    buildMessage: buildMessage,
    ymGoal: ymGoal
  };
})(window);
