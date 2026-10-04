/**
 * Travel Hub v2 — микровзаимодействия темы.
 * 1) Back-to-top кнопка (создаётся на лету, если её нет в разметке).
 * 2) Scroll-reveal: секции и карточки плавно появляются при прокрутке.
 */
(function () {
  'use strict';

  function initBackToTop() {
    var btn = document.querySelector('.v2-back-to-top');
    if (!btn) {
      btn = document.createElement('button');
      btn.className = 'v2-back-to-top';
      btn.setAttribute('aria-label', 'Наверх');
      btn.innerHTML =
        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
      document.body.appendChild(btn);
    }
    var ticking = false;
    window.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        btn.classList.toggle('visible', window.scrollY > 400);
        ticking = false;
      });
    }, { passive: true });
    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  function initReveal() {
    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var targets = document.querySelectorAll('section, .th-tour-card, .surface-card');
    if (!targets.length) return;

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('v2-reveal--visible');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    targets.forEach(function (el) {
      // Не анимируем hero и элементы в зоне видимости при загрузке
      var rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight * 0.9) return;
      el.classList.add('v2-reveal');
      io.observe(el);
    });

    /* UX-страховка: через 3с показываем всё, что не открылось само */
    setTimeout(function () {
      document.querySelectorAll('.v2-reveal:not(.v2-reveal--visible)').forEach(function (el) {
        el.classList.add('v2-reveal--visible');
      });
    }, 3000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initBackToTop();
      initReveal();
    });
  } else {
    initBackToTop();
    initReveal();
  }
})();
