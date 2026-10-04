# Интеграция оплаты через Альфа-Банк

Подробная инструкция по подключению эквайринга Альфа-Банка к сайту travelhub63.ru.

---

## 1. Выбор библиотеки

Используется **voronkovich/sberbank-acquiring-client** — она поддерживает API Альфа-Банка, активно поддерживается (330k+ установок) и имеет встроенные фабрики `ClientFactory::alfabank()` и `ClientFactory::alfabankTest()`.

Альтернативы: `kostikpenzin/alfabank-api-acquiring`, `davidnadejdin/alfabank-php-client` — менее популярны.

---

## 2. Установка Composer (если ещё нет)

### Windows

1. Скачайте [Composer-Setup.exe](https://getcomposer.org/Composer-Setup.exe)
2. Запустите установщик, укажите путь к `php.exe` (обычно в папке XAMPP, OpenServer или отдельной установки PHP)
3. Перезапустите терминал и проверьте: `composer --version`

### Linux / macOS

```bash
# Скачать и установить глобально
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
composer --version
```

---

## 3. Установка библиотеки

В корне проекта выполните:

```bash
composer install
```

Или только для добавления библиотеки Альфа-Банка:

```bash
composer require voronkovich/sberbank-acquiring-client
```

После установки появится папка `vendor/` и файл `composer.lock`.

> **Важно:** Добавьте `vendor/` в `.gitignore`, чтобы не коммитить зависимости. Папка `vendor/` обычно уже в `.gitignore`.

---

## 4. Настройка .env

В файле `.env` укажите:

```env
# Реквизиты от Альфа-Банка (логин и пароль магазина)
PAYMENT_ALFA_MERCHANT=ваш_логин
PAYMENT_ALFA_SECRET=ваш_пароль

# 1 = тестовый режим, 0 = боевой
PAYMENT_ALFA_TEST=1

# URL сайта (обязателен для редиректа после оплаты)
SITE_URL=https://travelhub63.ru
```

**Где взять логин и пароль:**
- Обратитесь в Альфа-Банк для подключения интернет-эквайринга
- После заключения договора банк выдаёт тестовые и боевые реквизиты
- Тестовые: для проверки без реальных списаний
- Боевые: для приёма реальных платежей

---

## 5. Структура файлов

| Файл | Назначение |
|------|------------|
| `alfabank_config.php` | Конфигурация (логин, пароль, URL, тест/бой) |
| `create_payment.php` | Создание заказа, регистрация в банке, редирект на оплату |
| `payment_callback.php` | Приём редиректа от банка, проверка статуса, обновление БД |
| `payment-success.php` | Страница успешной оплаты |
| `payment-fail.php` | Страница ошибки оплаты |

Таблица `alfabank_orders` создаётся автоматически при первом создании платежа.

---

## 6. Пример формы на странице товара

Скопируйте блок формы на страницу товара/услуги:

```html
<form action="/backend/payment/create_payment.php" method="POST">
    <!-- Сумма в рублях -->
    <input type="text" name="amount" required placeholder="15000" value="25000">
    <!-- Описание заказа -->
    <input type="hidden" name="description" value="Тур в Турцию, отель Hilton, 7 ночей">
    <button type="submit">Оплатить</button>
</form>
```

Или с фиксированной суммой:

```html
<form action="/backend/payment/create_payment.php" method="POST">
    <input type="hidden" name="amount" value="25000">
    <input type="hidden" name="description" value="Тур Египет, 7 ночей">
    <button type="submit">Оплатить 25 000 ₽</button>
</form>
```

---

## 7. Тестирование в тестовом режиме

1. Убедитесь, что `PAYMENT_ALFA_TEST=1` в `.env`
2. Используйте тестовые логин и пароль, выданные банком
3. Откройте страницу с формой оплаты (например `/frontend/window/payment-form-example.php`)
4. Введите сумму (можно 100 рублей для теста)
5. Нажмите «Оплатить» — произойдёт редирект на платёжную страницу Альфа-Банка (тестовый стенд)
6. Используйте **тестовую карту** из документации банка, например:
   - Номер: `4111 1111 1111 1111`
   - Срок: любой будущий (например 12/25)
   - CVC: любой (например 123)
7. После оплаты вы будете перенаправлены на страницу успеха

**Тестовые карты Альфа-Банка:**
- Успешная оплата: см. документацию банка (обычно указывают при выдаче тестовых реквизитов)
- Документация: https://alfa.rbsuat.com/sandbox/

---

## 8. Переход в боевой режим

1. Убедитесь, что сайт соответствует требованиям банка (информация о компании, условия доставки/оказания услуг)
2. Получите боевые реквизиты от Альфа-Банка
3. В `.env` измените:
   ```
   PAYMENT_ALFA_MERCHANT=боевой_логин
   PAYMENT_ALFA_SECRET=боевой_пароль
   PAYMENT_ALFA_TEST=0
   ```
4. Проверьте, что `SITE_URL` указывает на рабочий домен по HTTPS

---

## 9. Обработка ошибок

- Если реквизиты не указаны — пользователь перенаправляется на страницу ошибки с сообщением
- Ошибки банка логируются в `error_log` (настройте логирование в PHP)
- При проблемах проверьте:
  1. Доступность `SITE_URL` по HTTPS
  2. Корректность логина и пароля
  3. Режим (тест/бой) и соответствующие реквизиты

---

## 10. Безопасность

- **Никогда** не храните пароли в коде — только в `.env`
- Файл `.env` не должен быть доступен из браузера (положите его выше корня сайта или настройте `.htaccess`/nginx)
- Используйте HTTPS на боевом сайте

---

## 11. Мобильное приложение (TravelHubNew)

Для Expo/React Native приложения используется отдельный API:

- **Эндпоинт:** `POST /backend/api/payments/create`
- **URL для приложения:** `PAYMENT_API_URL=https://travelhub63.ru/backend/api/payments`
- Приложение отправляет JSON: `{ provider: "alpha", bookingId, amount, description, returnUrl }`
- Ответ: `{ success, paymentId, paymentUrl }` — приложение открывает `paymentUrl` в WebView

Страница `app-payment-redirect.php` перенаправляет пользователя после оплаты в deep link `travelhub://booking-success`, чтобы приложение закрыло WebView и открыло «Мои бронирования».

Подробнее: `D:\projects\TravelHubNew\docs\PAYMENT_ALFABANK_MOBILE.md`
