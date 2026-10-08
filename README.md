# Travel Hub — travelhub63.ru

Сайт турагентства: поиск туров (Tourvisor), офисы в Самаре и Москве, акции, бронирование, мобильное приложение с оплатой через Т-Кассу.

Редизайн v2 (2026): палитра navy / teal / coral, тема `frontend/css/v2-theme.css`, сокращённая воронка бронирования.

## Стек

- PHP 8.1+ (мин. 7.4), MySQL 5.7/8.0
- Apache + `.htaccess`
- Tailwind (CDN или собранный CSS) + `design-system.css`, `redesign.css`, `v2-theme.css`
- Vanilla JS, REST API в `api/` для приложения

## Быстрый старт

```bash
composer install
cp .env.example .env   # заполнить на сервере
```

Деплой, cron, офисы, legal — в **[docs/README.md](docs/README.md)**.

| Раздел | Документ |
|--------|----------|
| Деплой | [docs/DEPLOY.md](docs/DEPLOY.md) |
| Cron | [docs/CRON.md](docs/CRON.md) |
| Офисы и фото | [docs/OFFICES.md](docs/OFFICES.md) |
| Юридические страницы | [docs/LEGAL.md](docs/LEGAL.md) |
| Tourvisor | [docs/TOURVISOR.md](docs/TOURVISOR.md) |

## Структура

```
frontend/     Публичные страницы, CSS, JS
backend/      Компоненты, API, админка, cron-скрипты
api/          Мобильный API (оплата, авторизация)
export/       YML-фиды
data/         Кэш и runtime (часть в .gitignore)
docs/         Документация проекта
```

## История изменений

[CHANGELOG.md](CHANGELOG.md) — дизайн v2, перекраска, воронка лидов.

## Контакты оператора

ИП Смахтин Антон Валерьевич · hello@travelhub63.ru · см. [docs/LEGAL.md](docs/LEGAL.md)
