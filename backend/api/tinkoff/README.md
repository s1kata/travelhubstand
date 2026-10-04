# Tinkoff T-Kassa: проверка и подключение

## 1. Переменные окружения (.env на сервере)

Добавь в `.env` (или настройки хостинга):

```env
TINKOFF_TERMINAL_KEY=ключ_терминала_из_Т_Бизнес
TINKOFF_PASSWORD=пароль_терминала
APP_URL=https://travelhub63.ru
API_URL=https://travelhub63.ru
```

**Где взять:** [Т-Бизнес](https://business.tbank.ru/) → Оплата (интернет-эквайринг) → Магазины → Терминалы → нужный терминал → TerminalKey и Пароль.

---

## 2. Проверка оплаты из приложения

### Шаг 1: Убедиться, что API доступны

- `POST https://travelhub63.ru/api/create-payment` — создание платежа (нужен Bearer токен от Firebase).
- `GET https://travelhub63.ru/api/payment-status/:transactionId` — статус по PaymentId.
- Вебхук: `POST https://travelhub63.ru/api/payment-webhook` — его вызывает Тинькофф, не приложение.

### Шаг 2: Настроить NotificationURL в Т-Бизнес

В личном кабинете Т-Банка в настройках терминала укажи:

**NotificationURL:** `https://travelhub63.ru/api/payment-webhook`

(Либо оставь пустым — мы передаём этот URL в каждом запросе Init.)

### Шаг 3: Тест из приложения

1. В приложении: войти (Firebase Auth), создать/выбрать бронирование, нажать «Оплатить».
2. Приложение должно отправить `POST /api/create-payment` с телом:
   - `amount` (рубли), `orderId`, `description`, `userId`
   - заголовок `Authorization: Bearer <Firebase ID token>`.
3. В ответе придут `paymentUrl` и `transactionId`. Приложение открывает `paymentUrl` (браузер/WebView), пользователь платит.
4. После оплаты Тинькофф вызовет наш вебхук; мы обновим статус в БД.
5. Приложение может опрашивать `GET /api/payment-status/{transactionId}` до `status: "success"` или `"failed"`.

### Шаг 4: Тест через Postman / curl (без приложения)

Получить Firebase ID token можно в приложении (логировать или экран «Скопировать токен») или через Firebase Auth REST API.

```bash
# Создать платёж (подставь свой токен и данные)
curl -X POST https://travelhub63.ru/api/create-payment \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ВАШ_FIREBASE_ID_TOKEN" \
  -d '{"amount":1,"orderId":"test-order-1","description":"Тест","userId":"FIREBASE_UID"}'
```

В ответ будет `paymentUrl` — открой в браузере и заверши тестовую оплату. Потом проверь статус:

```bash
curl "https://travelhub63.ru/api/payment-status/PAYMENT_ID_ИЗ_ОТВЕТА"
```

---

## 3. Подключение Т-кассы на сайте

Оплата с сайта идёт через тот же терминал Tinkoff. Достаточно добавить ссылку «Оплатить» с нужными параметрами.

**URL оплаты с сайта:**

```
https://travelhub63.ru/payment-tinkoff?orderId=ЗАКАЗ&amount=СУММА_РУБ&description=ОПИСАНИЕ
```

| Параметр     | Обязательный | Пример   |
|-------------|--------------|----------|
| `orderId`   | да           | `123` или `TH-тур-456` |
| `amount`    | да           | `1500` (рубли) |
| `description` | нет        | `Бронирование тура` (до 140 символов) |

**Пример ссылки с кнопки «Оплатить заказ»:**

```html
<a href="https://travelhub63.ru/payment-tinkoff?orderId=<?php echo urlencode($orderId); ?>&amount=<?php echo (int)$amount; ?>&description=<?php echo urlencode('Оплата заказа ' . $orderId); ?>">
  Оплатить картой (Т-касса)
</a>
```

После перехода по ссылке пользователь попадает на страницу Тинькоффа, после оплаты — на `/payment-success` или `/payment-fail`. Вебхук обновляет статус в БД так же, как для оплаты из приложения.

---

## 4. Частые ошибки

| Симптом | Что проверить |
|--------|----------------|
| 401 от /api/create-payment | Передан ли заголовок `Authorization: Bearer <token>`, совпадает ли `userId` в теле с `uid` в токене. |
| 500 / "Payment is not configured" | В .env заданы `TINKOFF_TERMINAL_KEY` и `TINKOFF_PASSWORD`. |
| Вебхук не приходит / платёж не обновляется | NotificationURL доступен с интернета по HTTPS; в ответ вебхука мы возвращаем 200 и тело `OK`. |
| Статус всегда pending | Дождаться завершения оплаты; проверить, что вебхук отработал и в БД запись обновилась. |
