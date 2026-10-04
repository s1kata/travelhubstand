/**
 * Travel Hub — единый API модалок / bottom sheets.
 * Использование: THModal.open(el), THModal.close(el)
 */
(function () {
  'use strict';

  function resolveEl(target) {
    if (!target) return null;
    if (typeof target === 'string') return document.querySelector(target);
    return target;
  }

  function open(target) {
    var el = resolveEl(target);
    if (!el) return;
    el.classList.remove('hidden');
    el.classList.add('th-sheet--open', 'is-open');
    if (el.style && el.style.display === 'none') el.style.display = '';
    if (window.THMobile && typeof window.THMobile.lockScroll === 'function') {
      window.THMobile.lockScroll(true);
    } else {
      document.body.classList.add('th-modal-open');
    }
    el.setAttribute('aria-hidden', 'false');
  }

  function close(target) {
    var el = resolveEl(target);
    if (!el) return;
    el.classList.add('hidden');
    el.classList.remove('th-sheet--open', 'is-open');
    if (window.THMobile && typeof window.THMobile.lockScroll === 'function') {
      window.THMobile.lockScroll(false);
    } else {
      document.body.classList.remove('th-modal-open');
    }
    el.setAttribute('aria-hidden', 'true');
  }

  function toggle(target) {
    var el = resolveEl(target);
    if (!el) return;
    if (el.classList.contains('hidden') || el.classList.contains('th-sheet--open') === false && el.style.display === 'none') {
      open(el);
    } else {
      close(el);
    }
  }

  window.THModal = {
    open: open,
    close: close,
    toggle: toggle
  };
})();
