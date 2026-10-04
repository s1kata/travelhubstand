<?php
/**
 * Страница возврата после оплаты в мобильном приложении
 *
 * Банк перенаправляет сюда после оплаты (параметры orderId, result и т.д.).
 * Отображаем результат и кнопку «Вернуться в приложение» для перехода по deep link.
 * Банки требуют https для returnUrl.
 */
header('Content-Type: text/html; charset=utf-8');

$result = trim((string) ($_GET['result'] ?? ''));
$bookingId = trim((string) ($_GET['bookingId'] ?? ''));
$orderId = trim((string) ($_GET['orderId'] ?? ''));

$isSuccess = ($result !== 'fail');
// Приложение ожидает bookingId для обновления статуса в Firestore и CRM
$appSuccessUrl = 'travelhub://booking-success' . ($bookingId !== '' ? '?bookingId=' . rawurlencode($bookingId) : ($orderId !== '' ? '?orderId=' . rawurlencode($orderId) : ''));
$appFailUrl = 'travelhub://booking-fail';
$appUrl = $isSuccess ? $appSuccessUrl : $appFailUrl;

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Возврат в приложение</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: linear-gradient(180deg, #f0f9ff 0%, #DCEEEC 100%);
            color: #1e293b;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            padding: 32px 24px;
            max-width: 360px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .icon { font-size: 64px; margin-bottom: 16px; }
        .success .icon { color: #22c55e; }
        .fail .icon { color: #ef4444; }
        h1 { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
        .desc { font-size: 15px; color: #64748b; margin-bottom: 24px; line-height: 1.4; }
        .btn {
            display: inline-block;
            width: 100%;
            padding: 16px 24px;
            background: #5DA9A4;
            color: #fff;
            font-size: 17px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 14px;
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: #457F7B; }
    </style>
</head>
<body>
    <div class="card <?php echo $isSuccess ? 'success' : 'fail'; ?>">
        <div class="icon"><?php echo $isSuccess ? '✓' : '✕'; ?></div>
        <h1><?php echo $isSuccess ? 'Оплата прошла успешно!' : 'Оплата не выполнена'; ?></h1>
        <p class="desc">
            <?php
            if ($isSuccess) {
                echo 'Нажмите кнопку ниже, чтобы вернуться в приложение TravelHub.';
            } else {
                echo 'Вы можете вернуться в приложение и попробовать снова.';
            }
            ?>
        </p>
        <a href="<?php echo htmlspecialchars($appUrl); ?>" class="btn">Вернуться в приложение</a>
    </div>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>
