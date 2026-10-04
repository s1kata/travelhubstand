/**
 * promo-timer.js — промокоды TRAVEL10 / TRAVEL5 и обратный отсчёт (ч:мин:с)
 * Фаза 1: 10%, 30 мин (первый визит, попап)
 * После истечения фазы 1: TRAVEL5, 5%, таймер 1 ч
 * Каждый промокод одноразовый на пользователя (localStorage, th-promo-apply.js).
 * Скидка на карточках поиска не применяется автоматически — только вручную на странице тура.
 */
(function () {
    'use strict';

    if (window.__TH_PROMO_TIMER_BOOTED) return;
    window.__TH_PROMO_TIMER_BOOTED = true;

    var CODE_TEN = 'TRAVEL10';
    var CODE_FIVE = 'TRAVEL5';
    var PCT_TEN = 10;
    var PCT_FIVE = 5;
    var TIMER_TEN_SEC = 30 * 60;
    var TIMER_FIVE_SEC = 60 * 60;
    var MAX_DISCOUNT_RUB = 5000;

    var COOKIE_NAME = 'th_first_visit';
    var COOKIE_TTL_MIN = 120;

    var LS_TEN_START = 'th_promo_ten_start';
    var LS_FIVE_START = 'th_promo_five_start';
    var LS_POPUP_SHOWN = 'th_promo_popup_shown';
    var LS_TEN_EXPIRED = 'th_promo_ten_expired';
    var LS_COLLAPSED = 'th_promo_collapsed';

    var promoTickInterval = null;
    var lastSig = '';
    var lastPopupPhase = '';

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.*+?^=!:${}()|[\]/\\])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }
    function setCookie(name, value, minutes) {
        var expires = new Date(Date.now() + minutes * 60 * 1000).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';
    }
    function lsGet(k) {
        try { return localStorage.getItem(k); } catch (e) { return null; }
    }
    function lsSet(k, v) {
        try { localStorage.setItem(k, v); } catch (e) {}
    }

    var isFirstVisit = !getCookie(COOKIE_NAME);
    if (isFirstVisit) {
        setCookie(COOKIE_NAME, '1', COOKIE_TTL_MIN);
    }

    function discountRub(base, pct) {
        var p = parseInt(String(base), 10) || 0;
        if (!p || !pct) return 0;
        var d = Math.round(p * (pct / 100));
        if (d > MAX_DISCOUNT_RUB) d = MAX_DISCOUNT_RUB;
        return d;
    }
    function calcPriceAfterDiscount(price, pct) {
        var p = parseInt(String(price), 10) || 0;
        if (!p) return 0;
        var d = discountRub(p, pct);
        return Math.round((p - d) / 100) * 100;
    }

    function promoCodeUsed(code) {
        if (window.TH_PROMO_APPLY && typeof window.TH_PROMO_APPLY.isCodeUsed === 'function') {
            return window.TH_PROMO_APPLY.isCodeUsed(code);
        }
        return false;
    }

    function readPhase() {
        var now = Date.now();
        if (promoCodeUsed(CODE_TEN) && promoCodeUsed(CODE_FIVE)) {
            return { code: '', pct: 0, rem: 0, phase: 'done' };
        }

        var tenStart = parseInt(lsGet(LS_TEN_START) || '0', 10);
        var fiveStart = parseInt(lsGet(LS_FIVE_START) || '0', 10);
        if (tenStart > 0) {
            var elapsedTen = Math.floor((now - tenStart) / 1000);
            if (elapsedTen >= TIMER_TEN_SEC && lsGet(LS_TEN_EXPIRED) !== '1') {
                lsSet(LS_TEN_EXPIRED, '1');
            }
            if (!promoCodeUsed(CODE_TEN) && elapsedTen < TIMER_TEN_SEC) {
                return {
                    code: CODE_TEN,
                    pct: PCT_TEN,
                    rem: TIMER_TEN_SEC - elapsedTen,
                    phase: 'ten'
                };
            }
            if (promoCodeUsed(CODE_FIVE)) {
                return { code: '', pct: 0, rem: 0, phase: 'done' };
            }
            var fs = fiveStart;
            if (fs <= 0) {
                fs = now;
                lsSet(LS_FIVE_START, String(fs));
            }
            var elapsedFive = Math.floor((now - fs) / 1000);
            if (elapsedFive < TIMER_FIVE_SEC) {
                return {
                    code: CODE_FIVE,
                    pct: PCT_FIVE,
                    rem: TIMER_FIVE_SEC - elapsedFive,
                    phase: 'five'
                };
            }
            return { code: '', pct: 0, rem: 0, phase: 'done' };
        }
        if (isFirstVisit && !promoCodeUsed(CODE_TEN)) {
            return { code: CODE_TEN, pct: PCT_TEN, rem: 0, phase: 'pre' };
        }
        return { code: '', pct: 0, rem: 0, phase: 'done' };
    }

    function pad2(n) {
        n = parseInt(String(n), 10) || 0;
        return (n < 10 ? '0' : '') + n;
    }

    function formatCountdownHMS(totalSeconds) {
        if (totalSeconds <= 0) return '00:00:00';
        var h = Math.floor(totalSeconds / 3600);
        var m = Math.floor((totalSeconds % 3600) / 60);
        var s = totalSeconds % 60;
        return pad2(h) + ':' + pad2(m) + ':' + pad2(s);
    }

    function formatRemainingLong(seconds) {
        if (seconds <= 0) return 'Акция завершена';
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        if (days > 0) {
            return 'Осталось: ' + days + ' д. ' + hours + ' ч. ' + minutes + ' мин.';
        }
        if (hours > 0) {
            return 'Осталось: ' + hours + ' ч. ' + minutes + ' мин.';
        }
        return 'Осталось: ' + minutes + ' мин.';
    }

    /** Парсинг data-expires: ISO или «2026-06-01 23:59:59» (локальное время). */
    function parseExpiresAttr(raw) {
        if (!raw) return NaN;
        var s = String(raw).trim();
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(s)) {
            var p = s.split(/[\s:-]/);
            return new Date(
                parseInt(p[0], 10),
                parseInt(p[1], 10) - 1,
                parseInt(p[2], 10),
                parseInt(p[3], 10),
                parseInt(p[4], 10),
                parseInt(p[5], 10)
            ).getTime();
        }
        return Date.parse(s);
    }

    function getPhaseExpiresIso(ph) {
        ph = ph || readPhase();
        if (!ph || ph.rem <= 0) return '';
        return new Date(Date.now() + ph.rem * 1000).toISOString();
    }

    function getPromoExpiresAttr(ph) {
        ph = ph || readPhase();
        if (ph.rem > 0) {
            return getPhaseExpiresIso(ph);
        }
        if (ph.phase === 'done') {
            return new Date(Date.now() - 1000).toISOString();
        }
        return '';
    }

    function remainingSecondsFromExpires(raw) {
        var end = parseExpiresAttr(raw);
        if (isNaN(end)) return 0;
        return Math.max(0, Math.floor((end - Date.now()) / 1000));
    }

    function escAttr(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function buildPromoTimerHtml(expiresAttr, extraClass) {
        extraClass = extraClass || '';
        if (!expiresAttr) {
            return (
                '<div class="promo-timer promo-timer--unlimited' + extraClass + '" aria-live="polite">' +
                    '<span class="promo-timer__note">Промокод без ограничения по времени</span>' +
                '</div>'
            );
        }
        return (
            '<div class="promo-timer' + extraClass + '" data-expires="' + escAttr(expiresAttr) + '" aria-live="polite">' +
                '<span class="promo-timer__icon" aria-hidden="true"><i class="far fa-clock"></i></span>' +
                '<span class="promo-timer__label">Осталось:</span>' +
                '<span class="promo-timer__countdown" data-expires-display>--:--:--</span>' +
            '</div>'
        );
    }

    function findCopyButtonsForTimer(timerEl) {
        var buttons = [];
        if (!timerEl) return buttons;
        var popup = timerEl.closest('#th-promo-popup');
        if (popup) {
            var copyBtn = popup.querySelector('#th-promo-copy-btn');
            if (copyBtn) buttons.push(copyBtn);
            var useBtn = popup.querySelector('#th-promo-use-btn');
            if (useBtn) buttons.push(useBtn);
        }
        var bar = timerEl.closest('#th-promo-status');
        if (bar) {
            var barCopy = bar.querySelector('.promo-status__copy');
            if (barCopy) buttons.push(barCopy);
        }
        return buttons;
    }

    function setCopyButtonsState(timerEl, expired) {
        findCopyButtonsForTimer(timerEl).forEach(function (btn) {
            btn.disabled = !!expired;
            btn.classList.toggle('is-disabled', !!expired);
            btn.setAttribute('aria-disabled', expired ? 'true' : 'false');
        });
    }

    function markTimerExpired(timerEl) {
        if (!timerEl || timerEl.classList.contains('promo-timer--expired')) return;
        timerEl.classList.add('promo-timer--expired');
        timerEl.removeAttribute('data-expires');
        timerEl.innerHTML = '<span class="promo-timer__expired">Промокод недействителен</span>';
        setCopyButtonsState(timerEl, true);
    }

    function updatePromoTimerElement(el) {
        if (!el || el.classList.contains('promo-timer--expired')) return;
        if (el.classList.contains('promo-timer--unlimited')) return;

        var expiresRaw = el.getAttribute('data-expires');
        if (!expiresRaw) return;

        var rem = remainingSecondsFromExpires(expiresRaw);
        var countdownEl = el.querySelector('.promo-timer__countdown');

        if (rem <= 0) {
            markTimerExpired(el);
            return;
        }

        el.classList.remove('promo-timer--expired');
        setCopyButtonsState(el, false);
        if (countdownEl) {
            countdownEl.textContent = formatCountdownHMS(rem);
        }
    }

    function updateAllPromoTimers(root) {
        root = root || document;
        root.querySelectorAll('.promo-timer[data-expires]').forEach(updatePromoTimerElement);
    }

    function syncExpiresElements(root) {
        updateAllPromoTimers(root);
    }

    window.TH_PROMO = {
        isFirstVisit: isFirstVisit,
        get discount() { return readPhase().pct; },
        get promoCode() { return readPhase().code; },
        calcPrice: function (price) {
            return calcPriceAfterDiscount(price, readPhase().pct);
        },
        fmt: function (price) {
            return price.toLocaleString('ru-RU') + '\u00a0\u20bd';
        },
        reapplyCardPrices: function () {
            clearPromoCardPatches(document.body);
        },
        onCodeUsed: function () {
            clearPromoCardPatches(document.body);
            if (readPhase().phase === 'done') {
                teardownPromoUi();
            } else {
                updatePromoTab(document.getElementById('th-promo-popup'));
                updateStatusBar();
            }
        },
        openModal: function () {
            showPromoModal({ markShown: false });
        },
        getExpiresAt: function () {
            return getPhaseExpiresIso(readPhase());
        },
        updateStatusBar: updateStatusBar,
        updatePromoTimers: updateAllPromoTimers
    };

    function promoUiShouldShow() {
        var ph = readPhase();
        if (ph.phase === 'done' || !ph.pct) return false;
        if (ph.rem > 0) return true;
        if (ph.phase === 'pre' && isFirstVisit) return true;
        if (lsGet(LS_POPUP_SHOWN) === '1' || lsGet(LS_TEN_START)) {
            return ph.phase !== 'done';
        }
        return false;
    }

    function isHomeWizardFlow() {
        var root = document.getElementById('tour-search-section');
        return !!(root && root.classList.contains('th-wizard'));
    }

    function getWizardStep() {
        var root = document.getElementById('tour-search-section');
        if (!root) return 0;
        return parseInt(root.getAttribute('data-step') || '1', 10) || 1;
    }

    function shouldDeferPromoExpand() {
        if (document.body.classList.contains('th-modal-open') || document.body.classList.contains('th-abandon-open')) return true;
        /* На акциях и календаре — только свёрнутый таб, без полноэкранного листа */
        if (document.body.classList.contains('th-promo-page') || document.body.classList.contains('th-deals-cal-page')) return true;
        if (!isHomeWizardFlow()) return false;
        return getWizardStep() >= 1 && getWizardStep() <= 4;
    }

    function collapsePromoForWizard() {
        var popup = document.getElementById('th-promo-popup');
        if (!popup) return;
        popup.classList.remove('th-promo-popup--visible');
        document.body.classList.remove('th-promo-open');
        popup.classList.add('th-promo-popup--collapsed');
        lsSet(LS_COLLAPSED, '1');
    }

    function syncPromoTabPlacement(popup) {
        popup = popup || document.getElementById('th-promo-popup');
        if (!popup) return;
        var tab = popup.querySelector('.th-promo-popup__tab');
        if (!tab) return;
        var slot = document.getElementById('th-promo-header-slot');
        var desktop = typeof window.matchMedia === 'function' && window.matchMedia('(min-width: 768px)').matches;
        popup.classList.remove('th-promo-popup--tab-in-header', 'th-promo-popup--tab-top-fallback');
        document.body.classList.remove('th-promo-tab-bottom');
        if (desktop && slot) {
            slot.setAttribute('aria-hidden', 'false');
            slot.appendChild(tab);
            popup.classList.add('th-promo-popup--tab-in-header');
        } else if (desktop) {
            popup.appendChild(tab);
            popup.classList.add('th-promo-popup--tab-top-fallback');
        } else {
            popup.appendChild(tab);
            document.body.classList.add('th-promo-tab-bottom');
        }
    }

    function teardownPromoUi() {
        var popup = document.getElementById('th-promo-popup');
        if (popup && popup.parentNode) popup.parentNode.removeChild(popup);
        var bar = document.getElementById('th-promo-status');
        if (bar) bar.remove();
        var slot = document.getElementById('th-promo-header-slot');
        if (slot) {
            slot.innerHTML = '';
            slot.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('th-promo-open', 'th-promo-tab-visible', 'th-promo-tab-bottom', 'th-promo-status-visible');
    }

    function copyCurrentCode(onCopied) {
        var ph = readPhase();
        if (ph.phase === 'done') return;
        var code = ph.code;
        function done() {
            if (typeof onCopied === 'function') onCopied();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(done).catch(done);
            return;
        }
        var ta = document.createElement('textarea');
        ta.value = code;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        done();
    }

    function attachPopupHandlers(popup) {
        function collapsePopup() {
            popup.classList.remove('th-promo-popup--visible');
            document.body.classList.remove('th-promo-open');
            popup.classList.add('th-promo-popup--collapsed');
            lsSet(LS_COLLAPSED, '1');
            document.body.classList.add('th-promo-tab-visible');
            updatePromoTab(popup);
            syncPromoTabPlacement(popup);
        }
        function expandPopup() {
            if (readPhase().phase === 'done') return;
            popup.classList.remove('th-promo-popup--collapsed');
            document.body.classList.add('th-promo-open');
            document.body.classList.add('th-promo-tab-visible');
            requestAnimationFrame(function () {
                popup.classList.add('th-promo-popup--visible');
            });
            syncPopupTimerExpires(popup);
            updateAllPromoTimers(popup);
        }
        popup.querySelector('.th-promo-popup__backdrop').addEventListener('click', collapsePopup);
        popup.querySelector('.th-promo-popup__close').addEventListener('click', collapsePopup);
        var tab = popup.querySelector('.th-promo-popup__tab');
        if (tab) {
            tab.addEventListener('click', function () {
                lsSet(LS_COLLAPSED, '0');
                expandPopup();
            });
        }
        var useBtn = popup.querySelector('#th-promo-use-btn');
        if (useBtn) {
            useBtn.addEventListener('click', function () {
                if (useBtn.disabled) return;
                var phUse = readPhase();
                var codeUse = phUse.code;
                copyCurrentCode(function () {
                    var copyBtn = popup.querySelector('#th-promo-copy-btn');
                    if (copyBtn) {
                        copyBtn.textContent = 'Скопировано ✓';
                        setTimeout(function () { copyBtn.textContent = 'Скопировать'; }, 2000);
                    }
                });
                collapsePopup();
                if (window.TH_TOUR_DETAIL_PROMO && typeof window.TH_TOUR_DETAIL_PROMO.fillAndApply === 'function') {
                    window.TH_TOUR_DETAIL_PROMO.fillAndApply(codeUse);
                    return;
                }
                if (window.TH_PROMO_APPLY && typeof window.TH_PROMO_APPLY.setPendingCode === 'function') {
                    window.TH_PROMO_APPLY.setPendingCode(codeUse);
                }
                if (window.THPromoLead && typeof window.THPromoLead.open === 'function') {
                    window.THPromoLead.open({
                        source: 'promo_popup',
                        note: 'Интерес к промокоду со всплывающего блока.'
                    });
                } else if (typeof window.openSiteFeedbackModal === 'function') {
                    window.openSiteFeedbackModal({ source: 'promo_popup' });
                }
            });
        }
        var copyBtn = popup.querySelector('#th-promo-copy-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                if (copyBtn.disabled) return;
                copyCurrentCode(function () {
                    copyBtn.textContent = 'Скопировано ✓';
                    setTimeout(function () { copyBtn.textContent = 'Скопировать'; }, 2000);
                });
            });
        }
        return collapsePopup;
    }

    function updatePromoTab(popup) {
        if (!popup) popup = document.getElementById('th-promo-popup');
        if (!popup) return;
        var tab = popup.querySelector('.th-promo-popup__tab');
        if (!tab) return;
        var ph = readPhase();
        var codeEl = tab.querySelector('.th-promo-popup__tab-code');
        var timerEl = tab.querySelector('.th-promo-popup__tab-timer');
        if (codeEl) codeEl.textContent = ph.code || CODE_TEN;
        if (timerEl) {
            if (ph.rem > 0) {
                timerEl.textContent = formatCountdownHMS(ph.rem);
                timerEl.style.display = '';
            } else if (ph.phase === 'done') {
                timerEl.textContent = 'Истёк';
            } else {
                timerEl.style.display = 'none';
            }
        }
        tab.setAttribute('aria-label', 'Промокод ' + (ph.code || CODE_TEN));
        if (ph.phase === 'done' || (ph.rem <= 0 && ph.phase !== 'pre')) {
            popup.classList.remove('th-promo-popup--collapsed', 'th-promo-popup--visible');
            document.body.classList.remove('th-promo-tab-visible', 'th-promo-tab-bottom', 'th-promo-open');
            var slot = document.getElementById('th-promo-header-slot');
            if (slot) {
                slot.innerHTML = '';
                slot.setAttribute('aria-hidden', 'true');
            }
        }
    }

    function syncPopupTimerExpires(popup) {
        if (!popup) return;
        var ph = readPhase();
        var wrap = popup.querySelector('.th-promo-popup__code-wrap');
        if (!wrap) return;
        var oldTimer = wrap.querySelector('.promo-timer');
        var expires = getPromoExpiresAttr(ph);
        var html = buildPromoTimerHtml(expires, ' th-promo-popup__promo-timer');
        if (oldTimer) {
            oldTimer.outerHTML = html;
        } else {
            var row = wrap.querySelector('.th-promo-popup__code-row');
            if (row && row.nextSibling) {
                row.insertAdjacentHTML('afterend', html);
            } else {
                wrap.insertAdjacentHTML('beforeend', html);
            }
        }
        updateAllPromoTimers(popup);
    }

    function showPromoModal(opts) {
        opts = opts || {};
        var expand = opts.expand !== false && lsGet(LS_COLLAPSED) !== '1';
        if (shouldDeferPromoExpand()) expand = false;
        var existing = document.getElementById('th-promo-popup');
        if (existing) {
            syncPopupTimerExpires(existing);
            updateAllPromoTimers(existing);
            updatePromoTab(existing);
            document.body.classList.add('th-promo-tab-visible');
            if (expand) {
                existing.classList.remove('th-promo-popup--collapsed');
                document.body.classList.add('th-promo-open');
                requestAnimationFrame(function () {
                    existing.classList.add('th-promo-popup--visible');
                });
            } else {
                existing.classList.add('th-promo-popup--collapsed');
                document.body.classList.remove('th-promo-open');
            }
            syncPromoTabPlacement(existing);
            return;
        }
        if (!lsGet(LS_TEN_START)) {
            lsSet(LS_TEN_START, String(Date.now()));
        }
        var popup = buildPopup();
        document.body.appendChild(popup);
        if (expand) {
            document.body.classList.add('th-promo-open');
        }
        if (opts.markShown !== false && lsGet(LS_POPUP_SHOWN) !== '1') {
            lsSet(LS_POPUP_SHOWN, '1');
        }
        lastSig = '';
        lastPopupPhase = readPhase().phase;
        attachPopupHandlers(popup);
        updateAllPromoTimers(popup);
        updatePromoTab(popup);
        document.body.classList.add('th-promo-tab-visible');
        if (!expand || lsGet(LS_COLLAPSED) === '1') {
            popup.classList.add('th-promo-popup--collapsed');
            document.body.classList.remove('th-promo-open');
        } else {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    popup.classList.add('th-promo-popup--visible');
                });
            });
        }
        syncPromoTabPlacement(popup);
    }

    function updatePopupPhase1Done(popup) {
        if (!popup || !popup.parentNode) return;
        var title = popup.querySelector('.th-promo-popup__title');
        var sub = popup.querySelector('.th-promo-popup__sub');
        var codeEl = document.getElementById('th-promo-code-text');
        var line = 'Время вышло! Скидка 5% — промокод TRAVEL5 (макс. 5000 ₽)';
        if (title) title.innerHTML = line;
        if (sub) sub.textContent = '';
        if (codeEl) codeEl.textContent = CODE_FIVE;
        syncPopupTimerExpires(popup);
    }

    function handlePopupPhaseTransition() {
        var popup = document.getElementById('th-promo-popup');
        if (!popup) return;
        var ph = readPhase();
        if (lastPopupPhase === 'ten' && ph.phase === 'five') {
            updatePopupPhase1Done(popup);
        }
        lastPopupPhase = ph.phase;
        var sig = ph.phase + '|' + ph.code + '|' + ph.pct;
        if (sig !== lastSig) {
            lastSig = sig;
            clearPromoCardPatches(document.body);
            syncPopupTimerExpires(popup);
        }
        if (ph.phase === 'done') {
            updateAllPromoTimers(popup);
            teardownPromoUi();
            return;
        }
    }

    function startPromoTicker() {
        if (promoTickInterval) clearInterval(promoTickInterval);
        updateAllPromoTimers(document);
        handlePopupPhaseTransition();
        promoTickInterval = setInterval(function () {
            updateAllPromoTimers(document);
            handlePopupPhaseTransition();
            updatePromoTab(document.getElementById('th-promo-popup'));
            syncPromoTabPlacement(document.getElementById('th-promo-popup'));
            if (!document.getElementById('th-promo-popup')) {
                updateStatusBar();
            }
        }, 1000);
    }

    function buildPopup() {
        var ph0 = readPhase();
        var code0 = ph0.code;
        var isAfterTen = ph0.phase === 'five' || ph0.phase === 'done';
        var titleHtml = isAfterTen
            ? 'Время вышло! Скидка 5% — промокод TRAVEL5 (макс. 5000 ₽)'
            : 'Скидка 10%<br>на первый тур!';
        var sub0 = isAfterTen ? '' : 'Успейте воспользоваться — предложение ограничено';
        var expires0 = getPromoExpiresAttr(ph0);
        var timerHtml = buildPromoTimerHtml(expires0, ' th-promo-popup__promo-timer');
        var el = document.createElement('div');
        el.id = 'th-promo-popup';
        el.className = 'th-promo-popup';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.innerHTML =
            '<div class="th-promo-popup__backdrop"></div>' +
            '<div class="th-promo-popup__sheet">' +
                '<button class="th-promo-popup__close" aria-label="Закрыть">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                '</button>' +
                '<div class="th-promo-popup__badge">Только новым клиентам</div>' +
                '<h2 class="th-promo-popup__title">' + titleHtml + '</h2>' +
                '<p class="th-promo-popup__sub">' + sub0 + '</p>' +
                '<div class="th-promo-popup__code-wrap">' +
                    '<span class="th-promo-popup__code-label">Ваш промокод</span>' +
                    '<div class="th-promo-popup__code-row">' +
                        '<span class="th-promo-popup__code" id="th-promo-code-text">' + code0 + '</span>' +
                        '<button class="th-promo-popup__copy" id="th-promo-copy-btn" aria-label="Скопировать промокод">Скопировать</button>' +
                    '</div>' +
                    timerHtml +
                '</div>' +
                '<button class="th-promo-popup__cta" id="th-promo-use-btn">Использовать промокод</button>' +
                '<p class="th-promo-popup__cap">Скидка по промокоду — не более 5000 ₽</p>' +
                '<p class="th-promo-popup__note">Сообщите менеджеру при бронировании тура</p>' +
            '</div>' +
            '<button type="button" class="th-promo-popup__tab" aria-label="Промокод">' +
                '<span class="th-promo-popup__tab-icon" aria-hidden="true"><i class="fas fa-tag"></i></span>' +
                '<span>Промокод <span class="th-promo-popup__tab-code">' + code0 + '</span></span>' +
                '<span class="th-promo-popup__tab-timer"></span>' +
            '</button>';
        return el;
    }

    function showPopup() {
        if (shouldDeferPromoExpand() && getWizardStep() > 1) return;
        if (document.getElementById('th-promo-popup')) {
            if (lsGet(LS_COLLAPSED) === '1') return;
            showPromoModal({ markShown: false, expand: !shouldDeferPromoExpand() });
            return;
        }
        if (lsGet(LS_POPUP_SHOWN) === '1' && lsGet(LS_COLLAPSED) === '1') {
            showPromoModal({ markShown: false, expand: false });
            return;
        }
        if (lsGet(LS_POPUP_SHOWN) === '1') return;
        showPromoModal({ markShown: true, expand: !shouldDeferPromoExpand() });
    }

    function shouldShowStatusBar() {
        if (document.getElementById('th-promo-popup')) return false;
        if (document.body.classList.contains('th-promo-page')) return false;
        if (!lsGet(LS_TEN_START) && lsGet(LS_POPUP_SHOWN) !== '1') return false;
        var ph = readPhase();
        if (ph.phase === 'pre') return false;
        if (ph.rem <= 0) return false;
        return true;
    }

    function adjustStatusBarPosition() {
        var bar = document.getElementById('th-promo-status');
        if (!bar) return;
        var cta = document.getElementById('promo-sticky-cta');
        var leadBar = document.querySelector('.th-site-lead-bar');
        var leadH = 0;
        if (leadBar && document.body.classList.contains('has-th-lead-bar')) {
            var leadStyle = window.getComputedStyle(leadBar);
            if (leadStyle.display !== 'none' && leadBar.offsetHeight) {
                leadH = leadBar.offsetHeight;
            }
        }
        if (cta && cta.offsetHeight) {
            bar.style.bottom = (cta.offsetHeight + leadH + 8) + 'px';
        } else if (leadH > 0) {
            bar.style.bottom = (leadH + 10) + 'px';
        } else {
            bar.style.bottom = 'max(12px, env(safe-area-inset-bottom))';
        }
    }

    function ensureStatusBar() {
        if (!shouldShowStatusBar()) {
            var old = document.getElementById('th-promo-status');
            if (old) old.remove();
            document.body.classList.remove('th-promo-status-visible');
            return null;
        }
        var bar = document.getElementById('th-promo-status');
        if (bar) return bar;
        bar = document.createElement('div');
        bar.id = 'th-promo-status';
        bar.className = 'promo-status';
        bar.setAttribute('role', 'status');
        bar.innerHTML =
            '<div class="promo-status__inner">' +
                buildPromoTimerHtml(getPromoExpiresAttr(readPhase()), ' promo-timer--compact promo-timer--bar') +
                '<button type="button" class="promo-status__copy">Скопировать промокод</button>' +
            '</div>';
        bar.querySelector('.promo-status__copy').addEventListener('click', function () {
            if (bar.classList.contains('promo-status--expired')) return;
            showPromoModal({ markShown: false });
        });
        document.body.appendChild(bar);
        document.body.classList.add('th-promo-status-visible');
        adjustStatusBarPosition();
        return bar;
    }

    function updateStatusBar() {
        var bar = ensureStatusBar();
        if (!bar) {
            syncExpiresElements(document);
            return;
        }
        var ph = readPhase();
        var copyBtn = bar.querySelector('.promo-status__copy');
        var timerEl = bar.querySelector('.promo-timer');
        var expiresIso = getPhaseExpiresIso(ph);

        if (expiresIso && timerEl) {
            if (timerEl.classList.contains('promo-timer--unlimited')) {
                timerEl.outerHTML = buildPromoTimerHtml(expiresIso, ' promo-timer--compact promo-timer--bar');
                timerEl = bar.querySelector('.promo-timer');
            } else {
                timerEl.setAttribute('data-expires', expiresIso);
                timerEl.classList.remove('promo-timer--expired', 'promo-timer--unlimited');
                if (!timerEl.querySelector('.promo-timer__countdown')) {
                    timerEl.innerHTML =
                        '<span class="promo-timer__icon" aria-hidden="true"><i class="far fa-clock"></i></span>' +
                        '<span class="promo-timer__label">Осталось:</span>' +
                        '<span class="promo-timer__countdown" data-expires-display>--:--:--</span>';
                }
            }
        }

        adjustStatusBarPosition();
        var rem = expiresIso ? remainingSecondsFromExpires(expiresIso) : ph.rem;

        if (rem <= 0) {
            if (ph.phase === 'done') {
                if (timerEl) markTimerExpired(timerEl);
                bar.classList.add('promo-status--expired');
                if (copyBtn) {
                    copyBtn.disabled = true;
                    copyBtn.classList.add('is-disabled');
                }
            } else {
                var stale = document.getElementById('th-promo-status');
                if (stale) stale.remove();
                document.body.classList.remove('th-promo-status-visible');
            }
            return;
        }

        bar.classList.remove('promo-status--expired');
        if (copyBtn) {
            copyBtn.disabled = false;
            copyBtn.classList.remove('is-disabled');
            copyBtn.style.display = '';
        }
        if (timerEl) updatePromoTimerElement(timerEl);
        syncExpiresElements(document);
    }

    function clearPromoCardPatches(root) {
        root = root || document;
        root.querySelectorAll('.th-tour-card[data-promo-patched="1"]').forEach(function (card) {
            var priceBlock = card.querySelector('.th-tour-card__price-block');
            var priceEl = card.querySelector('.th-tour-card__price');
            if (!priceBlock || !priceEl) {
                delete card.dataset.promoPatched;
                return;
            }
            var oldEl = priceBlock.querySelector('.th-promo-old-price');
            if (oldEl) {
                var raw = oldEl.textContent.replace(/[^\d]/g, '');
                var priceNum = parseInt(raw, 10) || 0;
                if (priceNum) {
                    priceEl.textContent = window.TH_PROMO.fmt(priceNum);
                }
                oldEl.remove();
            }
            var badge = priceBlock.querySelector('.th-promo-discount-badge');
            if (badge) badge.remove();
            delete card.dataset.promoPatched;
        });
    }

    function init() {
        var staleBar = document.getElementById('th-promo-status');
        if (staleBar) staleBar.remove();
        document.body.classList.remove('th-promo-status-visible');

        teardownPromoUi();
        lastSig = readPhase().phase + '|' + readPhase().code + '|' + readPhase().pct;
        lastPopupPhase = readPhase().phase;
        clearPromoCardPatches(document.body);
        startPromoTicker();
        /* Автопопап и таб по сессии отключены — промокод выдаём по кнопке на главной. */

        document.addEventListener('th:wizard-step', function (e) {
            if (!e || !e.detail) return;
            if (e.detail.step >= 2) collapsePromoForWizard();
        });
    }

    function ensurePromoSheetOnLoad() {
        if (!promoUiShouldShow()) return;
        if (!document.getElementById('th-promo-popup') && (lsGet(LS_POPUP_SHOWN) === '1' || lsGet(LS_TEN_START))) {
            showPromoModal({ markShown: false, expand: !shouldDeferPromoExpand() && lsGet(LS_COLLAPSED) !== '1' });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
