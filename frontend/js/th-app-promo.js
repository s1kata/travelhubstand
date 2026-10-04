/**
 * th-app-promo.js — промокод за переход в App Store (без таймера).
 * TRAVELAPP: 10%, только после клика «Скачать», действует на сайте.
 */
(function () {
    'use strict';

    if (window.__TH_APP_PROMO_BOOTED) return;
    window.__TH_APP_PROMO_BOOTED = true;

    var CODE = 'TRAVELAPP';
    var PCT = 10;
    var LS_UNLOCKED = 'th_app_promo_unlocked';
    var APP_STORE_URL = 'https://apps.apple.com/ru/app/travelhub/id6786282632';

    function lsGet(key) {
        try { return localStorage.getItem(key); } catch (e) { return null; }
    }
    function lsSet(key, val) {
        try { localStorage.setItem(key, val); } catch (e) {}
    }

    function isUnlocked() {
        return lsGet(LS_UNLOCKED) === '1';
    }

    function unlock() {
        lsSet(LS_UNLOCKED, '1');
        if (window.TH_PROMO_APPLY && typeof window.TH_PROMO_APPLY.setPendingCode === 'function') {
            window.TH_PROMO_APPLY.setPendingCode(CODE);
        }
    }

    function copyText(text, onDone) {
        function done() {
            if (typeof onDone === 'function') onDone();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                fallbackCopy(text, done);
            });
            return;
        }
        fallbackCopy(text, done);
    }

    function fallbackCopy(text, done) {
        try {
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            done();
        } catch (e) {}
    }

    function wireCopyButton(btn) {
        if (!btn || btn.__thAppPromoCopyWired) return;
        btn.__thAppPromoCopyWired = true;
        btn.addEventListener('click', function () {
            copyText(CODE, function () {
                btn.classList.add('is-copied');
                var prev = btn.textContent;
                btn.textContent = 'Скопировано';
                setTimeout(function () {
                    btn.textContent = prev;
                    btn.classList.remove('is-copied');
                }, 1800);
            });
        });
    }

    function updateBannerState() {
        var teaser = document.getElementById('th-app-promo-teaser');
        var unlocked = document.getElementById('th-app-promo-unlocked');
        if (!teaser || !unlocked) return;
        if (isUnlocked()) {
            teaser.hidden = true;
            unlocked.hidden = false;
        } else {
            teaser.hidden = false;
            unlocked.hidden = true;
        }
    }

    function closeThankYouModal() {
        var modal = document.getElementById('th-app-thanks');
        if (!modal) return;
        modal.classList.remove('is-open');
        document.body.classList.remove('th-app-thanks-open');
        setTimeout(function () {
            if (modal.parentNode) modal.parentNode.removeChild(modal);
        }, 220);
    }

    function showThankYouModal() {
        if (document.getElementById('th-app-thanks')) {
            document.getElementById('th-app-thanks').classList.add('is-open');
            document.body.classList.add('th-app-thanks-open');
            return;
        }

        var modal = document.createElement('div');
        modal.id = 'th-app-thanks';
        modal.className = 'th-app-thanks is-open';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'th-app-thanks-title');
        modal.innerHTML =
            '<div class="th-app-thanks__backdrop" data-th-app-thanks-close></div>' +
            '<div class="th-app-thanks__sheet">' +
                '<button type="button" class="th-app-thanks__close" data-th-app-thanks-close aria-label="Закрыть">' +
                    '<i class="fas fa-times" aria-hidden="true"></i>' +
                '</button>' +
                '<div class="th-app-thanks__icon" aria-hidden="true"><i class="fas fa-heart"></i></div>' +
                '<p class="th-app-thanks__eyebrow">Спасибо, что доверяете нам!</p>' +
                '<h2 class="th-app-thanks__title" id="th-app-thanks-title">Ваш промокод за установку приложения</h2>' +
                '<p class="th-app-thanks__sub">Скидка <strong>' + PCT + '%</strong> при бронировании тура на сайте — без ограничения по времени.</p>' +
                '<div class="th-app-thanks__code-wrap">' +
                    '<span class="th-app-thanks__code-label">Промокод</span>' +
                    '<div class="th-app-thanks__code-row">' +
                        '<span class="th-app-thanks__code">' + CODE + '</span>' +
                        '<button type="button" class="th-app-thanks__copy" id="th-app-thanks-copy">Скопировать</button>' +
                    '</div>' +
                '</div>' +
                '<p class="th-app-thanks__note">' +
                    '<i class="fas fa-info-circle" aria-hidden="true"></i> ' +
                    'Промокоды скоро появятся в приложении. Сейчас скидка действует только на сайте travelhub63.ru.' +
                '</p>' +
                '<p class="th-promo-popup__cap th-app-thanks__cap">Скидка по промокоду — не более 5000 ₽</p>' +
                '<a href="/frontend/window/promotions.php" class="th-app-thanks__cta">Смотреть горящие туры</a>' +
            '</div>';

        document.body.appendChild(modal);
        document.body.classList.add('th-app-thanks-open');

        modal.querySelectorAll('[data-th-app-thanks-close]').forEach(function (el) {
            el.addEventListener('click', closeThankYouModal);
        });
        wireCopyButton(modal.querySelector('#th-app-thanks-copy'));

        document.addEventListener('keydown', function onKey(e) {
            if (e.key === 'Escape') {
                closeThankYouModal();
                document.removeEventListener('keydown', onKey);
            }
        });
    }

    function onInstallClick(e) {
        var link = e.currentTarget;
        e.preventDefault();
        unlock();
        updateBannerState();
        window.open(link.getAttribute('href') || APP_STORE_URL, '_blank', 'noopener,noreferrer');
        showThankYouModal();
        try {
            if (window.__TH_YM_ID && typeof window.ym === 'function') {
                window.ym(window.__TH_YM_ID, 'reachGoal', 'app_install_promo_unlock');
            }
        } catch (ymErr) {}
    }

    function init() {
        var installLink = document.getElementById('th-app-install-link');
        if (installLink) {
            installLink.addEventListener('click', onInstallClick);
        }
        wireCopyButton(document.getElementById('th-app-promo-copy'));
        updateBannerState();
    }

    window.TH_APP_PROMO = {
        code: CODE,
        pct: PCT,
        isUnlocked: isUnlocked,
        unlock: unlock,
        showThankYou: showThankYouModal
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
