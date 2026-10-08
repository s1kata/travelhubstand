# Деплой на хостинг

Проект: **travelhub-v2** (корень репозитория). Содержимое заливается в **document root** сайта (`public_html` на Spaceweb и аналогах).

## Требования

| Компонент | Версия |
|-----------|--------|
| PHP | 8.1+ (минимум 7.4) |
| MySQL | 5.7 / 8.0 |
| Apache | mod_rewrite, `.htaccess` |
| Composer | для `vendor/` |

Spaceweb: PHP и MySQL настраиваются в панели «Сайты → Конфигурация»; cron — «Задачи по расписанию»; SSL — бесплатный сертификат в панели.

## Шаги

1. Загрузить **весь** репозиторий в корень сайта (FTP, SSH или файловый менеджер).
2. В корне проекта: `composer install --no-dev`
3. Скопировать `.env.example` → `.env`, заполнить доступы к БД, Tourvisor, Firebase, платежи.
4. Импортировать дамп MySQL (если новый сервер).
5. Права на запись: `data/`, при необходимости `frontend/window/img/offices/`.
6. Проверить: главная открывается, редиректы из `.htaccess` работают.
7. Настроить [cron](CRON.md).

## Переменные `.env` (основные)

```env
SITE_URL=https://travelhub63.ru
APP_URL=https://travelhub63.ru
API_URL=https://travelhub63.ru

# MySQL
DB_HOST=...
DB_NAME=...
DB_USER=...
DB_PASS=...

# Tourvisor
TOURVISOR_TOKEN=...
TOURVISOR_API_URL=https://api.tourvisor.ru/search

# Т-Касса (мобильное приложение)
TINKOFF_TERMINAL_KEY=...
TINKOFF_PASSWORD=...
```

В личном кабинете Т-Банка указать **NotificationURL**:

```
https://travelhub63.ru/api/payment-webhook
```

В приложении TravelHubNew: `PAYMENT_PAGE_URL=https://travelhub63.ru`

## Опционально перед выкладкой

```bash
npm install && npm run build:css
```

См. [BUILD.md](BUILD.md) — уменьшает размер CSS вместо Tailwind CDN.

## После деплоя

- Фото офисов: только в `frontend/window/img/offices/` — см. [OFFICES.md](OFFICES.md).
- Юридические страницы: `/frontend/window/consent.php`, `privacy.php`, `terms.php` — см. [LEGAL.md](LEGAL.md).
- Прогрев кэша Tourvisor: `php backend/scripts/tourvisor_background_update.php` или см. [TOURVISOR.md](TOURVISOR.md).
