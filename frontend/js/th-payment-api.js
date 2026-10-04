/**
 * Оплата с сайта через mobile API (серверный мост, вход — PHP-сессия).
 */
(function (global) {
    'use strict';

    function parseJsonResponse(r) {
        return r.text().then(function (text) {
            var data;
            try {
                data = text ? JSON.parse(text) : {};
            } catch (e) {
                var shortErr = 'HTTP ' + r.status;
                if (text && /<!DOCTYPE|<html/i.test(text)) {
                    shortErr = 'Сервис недоступен (HTTP ' + r.status + ')';
                } else if (text) {
                    shortErr = text.length > 200 ? text.slice(0, 200) + '…' : text;
                }
                data = { success: false, error: shortErr };
            }
            return { ok: r.ok, status: r.status, data: data };
        });
    }

    /**
     * @param {object} opts
     * @param {string} [opts.csrfToken]
     * @param {number} opts.amount рубли
     * @param {string} opts.orderId
     * @param {string} [opts.description]
     * @param {string} [opts.returnUrl]
     * @param {string} [opts.failReturnUrl]
     */
    function createPayment(opts) {
        var body = {
            _csrf_token: opts.csrfToken || '',
            amount: opts.amount,
            orderId: opts.orderId,
            description: opts.description || 'Оплата заказа',
        };
        if (opts.returnUrl) body.returnUrl = opts.returnUrl;
        if (opts.failReturnUrl) body.failReturnUrl = opts.failReturnUrl;

        return fetch('/backend/api/site-payment-create.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(body),
        }).then(parseJsonResponse);
    }

    function fetchPaymentStatus(transactionId) {
        return fetch('/backend/api/site-payment-status.php?transactionId=' + encodeURIComponent(transactionId), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        }).then(parseJsonResponse);
    }

    function pollPaymentStatus(transactionId, onUpdate, maxAttempts) {
        var attempts = 0;
        var limit = maxAttempts || 30;

        function tick() {
            attempts += 1;
            fetchPaymentStatus(transactionId).then(function (res) {
                var d = res.data || {};
                if (!d.success) {
                    onUpdate({ status: 'error', error: d.error || 'Ошибка проверки статуса', raw: d });
                    if (attempts < limit && res.status >= 500) {
                        setTimeout(tick, 2000);
                    }
                    return;
                }
                onUpdate({
                    status: d.status,
                    raw: d,
                    done: d.status === 'success' || d.status === 'failed' || d.status === 'cancelled',
                });
                if (d.status === 'pending' && attempts < limit) {
                    setTimeout(tick, 2000);
                }
            }).catch(function () {
                onUpdate({ status: 'error', error: 'Нет связи с сервером' });
                if (attempts < limit) {
                    setTimeout(tick, 2500);
                }
            });
        }

        tick();
    }

    function generateOrderId(prefix) {
        var p = prefix || 'WEB';
        return p + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
    }

    global.ThPaymentApi = {
        createPayment: createPayment,
        fetchPaymentStatus: fetchPaymentStatus,
        pollPaymentStatus: pollPaymentStatus,
        generateOrderId: generateOrderId,
    };
})(typeof window !== 'undefined' ? window : this);
