<?php
/**
 * Страница неуспешной оплаты (failReturnUrl для mobile API create-payment).
 */
require_once __DIR__ . '/../../backend/config/config.php';
session_start();

$error = $_SESSION['payment_error'] ?? 'Оплата не завершена или была отклонена.';
unset($_SESSION['payment_error']);
$orderId = trim((string) ($_GET['orderId'] ?? ''));
$appDeepLink = $orderId !== '' ? 'travelhub://payment/fail?bookingId=' . rawurlencode($orderId) : '';
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Ошибка оплаты | Travel Hub</title>
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Оплата не прошла</h1>
        <p id="payment-fail-message" class="text-slate-600 mb-6"><?php echo htmlspecialchars($error); ?></p>
        <?php if ($orderId !== ''): ?>
        <p class="text-sm text-slate-500 mb-4">Заказ: <strong><?php echo htmlspecialchars($orderId); ?></strong></p>
        <?php endif; ?>
        <div class="flex flex-col gap-3">
            <?php if ($appDeepLink !== ''): ?>
            <a href="<?php echo htmlspecialchars($appDeepLink); ?>" class="inline-block px-6 py-3 bg-sky-600 text-white rounded-xl hover:bg-sky-700 transition">
                Вернуться в приложение
            </a>
            <?php endif; ?>
            <div class="flex gap-3 justify-center">
                <a href="/" class="inline-block px-6 py-3 bg-slate-200 text-slate-800 rounded-xl hover:bg-slate-300 transition">
                    На главную
                </a>
                <a href="javascript:history.back()" class="inline-block px-6 py-3 bg-sky-600 text-white rounded-xl hover:bg-sky-700 transition">
                    Попробовать снова
                </a>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
    <?php if ($is_logged_in): ?>
    <script src="/frontend/js/th-payment-api.js"></script>
    <script>
    (function () {
        var txId = '';
        try { txId = sessionStorage.getItem('th_payment_tx') || ''; } catch (e) {}
        if (!txId || typeof ThPaymentApi === 'undefined') return;
        ThPaymentApi.fetchPaymentStatus(txId).then(function (res) {
            var d = res.data || {};
            if (d.success && d.status === 'success') {
                window.location.replace('/payment-success?orderId=' + encodeURIComponent(<?php echo json_encode($orderId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>));
            }
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
