# Производительность

## Кэш HTML-страниц

Компонент `backend/components/page_cache.php`. На главной уже подключён.

Для других тяжёлых страниц — в начале:

```php
<?php
require_once __DIR__ . '/../backend/components/page_cache.php';
if (PageCache::get()) {
    exit;
}
PageCache::start();
```

Перед `</body>`:

```php
<?php PageCache::end(); ?>
```

После первого запроса TTFB снижается с секунд до сотен миллисекунд. Очистка старых файлов: `php clear_cache.php 10` (см. [CRON.md](CRON.md)).

## Изображения

```bash
php scripts/optimize_images.php
```

Сжимает крупные файлы в `frontend/window/img/` и смежных каталогах. На проде уже включены lazy loading, alt, preload критичных hero.

## Apache

В `.htaccess`: gzip, Cache-Control для статики (CSS/JS ~1 мес, изображения ~1 год).

## Чеклист перед аудитом PageSpeed

1. Кэш страниц на горячих URL.
2. `php scripts/optimize_images.php` после загрузки новых фото.
3. `npm run build:css` — локальный Tailwind вместо CDN ([BUILD.md](BUILD.md)).
4. Cron Tourvisor и промо — чтобы не блокировать первый пользовательский запрос.
