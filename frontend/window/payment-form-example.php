<?php
/**
 * ПРИМЕР СТРАНИЦЫ ТОВАРА/УСЛУГИ С КНОПКОЙ ОПЛАТЫ
 *
 * Скопируйте этот блок формы на свою страницу товара (tour-detail.php, services.php и т.д.)
 */
require_once __DIR__ . '/../../backend/config/config.php';
session_start();

$page_title = 'Пример оплаты';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | Travel Hub</title>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-md mx-auto bg-white rounded-2xl shadow-lg p-6">
        <h1 class="text-xl font-semibold text-slate-800 mb-4">Оплата через Альфа-Банк</h1>

        <!--
            ФОРМА ОПЛАТЫ
            action — скрипт create_payment.php
            method — POST
        -->
        <form action="/backend/payment/create_payment.php" method="POST" class="space-y-4">
            <!-- Сумма в рублях (можно заменить на скрытое поле с фиксированной ценой) -->
            <div>
                <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">Сумма (руб.)</label>
                <input type="text" id="amount" name="amount" required
                       placeholder="15000"
                       value="100"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                <p class="text-xs text-slate-500 mt-1">Для теста можно указать 100 рублей</p>
            </div>

            <!-- Описание заказа (отображается в банковской форме) -->
            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Описание заказа</label>
                <input type="text" id="description" name="description"
                       placeholder="Тур в Турцию, отель Hilton"
                       value="Тестовый заказ"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <button type="submit" class="w-full py-3 bg-sky-600 text-white font-semibold rounded-xl hover:bg-sky-700 transition">
                Оплатить
            </button>
        </form>

        <!-- ВАРИАНТ: скрытая форма с фиксированной суммой (например, для конкретного тура) -->
        <!--
        <form action="/backend/payment/create_payment.php" method="POST">
            <input type="hidden" name="amount" value="25000">
            <input type="hidden" name="description" value="Тур Египет, 7 ночей, отель 5*">
            <button type="submit" class="...">Оплатить 25 000 ₽</button>
        </form>
        -->
    </div>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>
