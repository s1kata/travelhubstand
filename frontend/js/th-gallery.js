/**
 * Travel Hub — gallery lightbox scroll lock (legacy country pages).
 */
(function () {
  'use strict';

  function syncLock(el) {
    if (!el) return;
    var open = !el.classList.contains('hidden');
    var st = window.getComputedStyle(el);
    if (st.display === 'none' || st.visibility === 'hidden') open = false;
    if (window.THMobile && typeof window.THMobile.lockScroll === 'function') {
      window.THMobile.lockScroll(open);
    } else {
      document.body.classList.toggle('th-modal-open', open);
    }
  }

  function wire(id) {
    var el = document.getElementById(id);
    if (!el || el.__thGalleryWired) return;
    el.__thGalleryWired = true;
    var mo = new MutationObserver(function () { syncLock(el); });
    mo.observe(el, { attributes: true, attributeFilter: ['class', 'style', 'hidden'] });
    syncLock(el);
  }

  function init() {
    ['gallery-lightbox', 'lightbox', 'hotel-image-lightbox'].forEach(wire);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
