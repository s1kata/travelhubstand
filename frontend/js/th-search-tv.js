/**
 * Tourvisor-like search mode helpers.
 * - Find → #tv-search-btn
 * - Reparent coral popups to <body> so calendar is not clipped by hero overflow
 */
(function () {
  'use strict';

  if (!document.body.classList.contains('th-search-ui-tv')) return;

  var POPUP_IDS = [
    'tv-sc-date-popup',
    'tv-tourists-block',
    'tv-nights-popup',
    'tv-sc-overlay'
  ];

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function reparentPopups() {
    POPUP_IDS.forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.parentNode !== document.body) {
        document.body.appendChild(el);
      }
    });
  }

  function init(root) {
    if (!root || root.__thSearchTvBound) return;
    root.__thSearchTvBound = true;

    reparentPopups();

    var findBtn = qs('[data-th-tv-find]', root);
    if (findBtn) {
      findBtn.addEventListener('click', function () {
        var real = document.getElementById('tv-search-btn');
        if (real) real.click();
      });
    }
  }

  function boot() {
    init(document.getElementById('tour-search-section'));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
