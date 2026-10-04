<?php
/**
 * Страница успешной оплаты (returnUrl для mobile API create-payment).
 */
require_once __DIR__ . '/../../backend/config/config.php';
session_start();

$message = $_SESSION['payment_success_message'] ?? 'Оплата прошла успешно!';
$orderNumber = $_SESSION['payment_order_number'] ?? $_GET['orderId'] ?? '';
unset($_SESSION['payment_success_message'], $_SESSION['payment_order_number']);
$orderId = trim((string) ($_GET['orderId'] ?? ''));
$appDeepLink = $orderId !== '' ? 'travelhub://payment/success?bookingId=' . rawurlencode($orderId) : '';
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Оплата прошла успешно | Travel Hub</title>
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full text-center">
        <div id="payment-status-icon" class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 id="payment-status-title" class="text-xl font-semibold text-slate-800 mb-2">Оплата прошла успешно!</h1>
        <p id="payment-status-message" class="text-slate-600 mb-4"><?php echo htmlspecialchars($message); ?></p>
        <p id="payment-status-pending" class="hidden text-sm text-slate-500 mb-4">Проверяем статус платежа…</p>
        <?php if ($orderNumber): ?>
        <p class="text-sm text-slate-500 mb-6">Номер заказа: <strong><?php echo htmlspecialchars((string) $orderNumber); ?></strong></p>
        <?php elseif ($orderId !== ''): ?>
        <p class="text-sm text-slate-500 mb-6">Номер заказа: <strong><?php echo htmlspecialchars($orderId); ?></strong></p>
        <?php endif; ?>
        <div class="flex flex-col gap-3">
            <?php if ($appDeepLink !== ''): ?>
            <a href="<?php echo htmlspecialchars($appDeepLink); ?>" class="inline-block px-6 py-3 bg-sky-600 text-white rounded-xl hover:bg-sky-700 transition">
                Вернуться в приложение
            </a>
            <?php endif; ?>
            <a href="/" class="inline-block px-6 py-3 <?php echo $appDeepLink ? 'bg-slate-200 text-slate-800 hover:bg-slate-300' : 'bg-sky-600 text-white hover:bg-sky-700'; ?> rounded-xl transition">
                На главную
            </a>
        </div>
    </div>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
    <script src="/frontend/js/th-payment-api.js"></script>
    <?php if ($is_logged_in): ?>
    <script>
    (function () {
        var txId = '';
        try { txId = sessionStorage.getItem('th_payment_tx') || ''; } catch (e) {}
        if (!txId || typeof ThPaymentApi === 'undefined') return;
        var pendingEl = document.getElementById('payment-status-pending');
        var titleEl = document.getElementById('payment-status-title');
        var msgEl = document.getElementById('payment-status-message');
        if (pendingEl) pendingEl.classList.remove('hidden');
        ThPaymentApi.pollPaymentStatus(txId, function (update) {
            if (update.status === 'pending') return;
            if (pendingEl) pendingEl.classList.add('hidden');
            if (update.status === 'success') {
                if (titleEl) titleEl.textContent = 'Оплата подтверждена';
                if (msgEl) msgEl.textContent = 'Платёж успешно зачислен. Менеджер свяжется с вами для подтверждения тура.';
                try { sessionStorage.removeItem('th_payment_tx'); } catch (e2) {}
                return;
            }
            if (update.status === 'failed' || update.status === 'cancelled' || update.status === 'error') {
                if (titleEl) titleEl.textContent = 'Статус оплаты не подтверждён';
                if (msgEl) msgEl.textContent = update.error || 'Если деньги списались, свяжитесь с менеджером — мы проверим платёж вручную.';
            }
        }, 20);
    })();
    </script>
    <?php endif; ?>
</body>
</html>
