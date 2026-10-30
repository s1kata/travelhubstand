# Crontab SpaceWeb — prod `hub63_ru`

Корень сайта:

```text
/home/g/garant77li/hub63_ru/public_html
```

PHP: `/usr/bin/php8.1` (метрики OTLP — `php8.3`, если есть в PATH).

Часовой пояс панели: **Europe/Moscow**.

## Активные задания (оставить)

| Мин | Час | День | Мес | DOW | Команда |
|-----|-----|------|-----|-----|---------|
| 5 | 0,12 | * | * | * | `cd /home/g/garant77li/hub63_ru/public_html && PHP_BIN=/usr/bin/php8.1 flock -n data/promo_warm.lock bash backend/cron/warm_promotions_cache.sh >> data/promo_warm.log 2>&1` |
| 15 | 3 | * | * | 0 | то же (страховка вс) |
| 30 | 0,8,14,20 | * | * | * | `cd ... && flock -n data/search_warm.lock bash backend/cron/warm_home_search_cache.sh >> data/home_search_warm.log 2>&1` |
| 30 | 1 | * | * | * | `bash /home/g/garant77li/hub63_ru/public_html/backend/cron/warm_home_search_nightly.sh>>/home/g/garant77li/hub63_ru/public_html/data/home_search_warm_nightly.log 2>&1` *(фаза 0 по часу)* |
| 30 | 2 | * | * | * | та же команда *(фаза 1)* |
| 30 | 3 | * | * | * | та же команда *(фаза 2)* |
| 20 | 1,9,15,21 | * | * | * | `cd ... && PHP_BIN=/usr/bin/php8.1 flock -n data/calendar_warm.lock bash backend/cron/warm_calendar_cache.sh >> data/calendar_warm.log 2>&1` |
| 20 | 0 | * | * | * | `cd ... && /usr/bin/php8.1 backend/scripts/yml_feed_rules_cron.php >> data/yandex_yml_rules_cron.log 2>&1` |
| 10 | 0 | * | * | 1 | `cd ... && PHP_BIN=/usr/bin/php8.1 flock -n data/yml_rotation_warm.lock bash backend/cron/warm_yml_rotation_countries.sh >> data/yml_rotation_warm.log 2>&1` |
| 0 | 2 | * | * | * | `cd ... && /usr/bin/php8.1 backend/cron/cleanup_search_cover_cache.php >> data/cover_cleanup.log 2>&1` |
| 30 | 4 | * | * | * | `cd ... && /usr/bin/php8.1 clear_image_cache.php 14 --trim-mb=1024 >> data/image_cache_cron.log 2>&1` |
| 0 | 5 | * | * | 0 | `cd ... && /usr/bin/php8.1 clear_cache.php 10 >> data/tourvisor_cache_cleanup.log 2>&1` |
| 30 | 5 | * | * | 1,4 | `cd ... && /usr/bin/php8.1 backend/cron/firestore_cache_cleanup.php >> data/firestore_cache_cleanup.log 2>&1` *(только если Firestore L1 включён)* |
| * | * | * | * | * | `cd ... && /usr/bin/php8.3 backend/cron/prometheus_metrics_export.php >> data/logs/prometheus_metrics_cron.log 2>&1` *(если настроен Grafana OTLP в `.env`)* |

## Удалить (legacy / дубли)

- `warm_promotions_cache.sh` без `flock` и/или на `php7.4`
- второй YML в 05:00 на `php7.4`
- любые `tourvisor_background_update`, `promo_tours_refresh`, `sync_yandex_feed_offers`, `warmup_tourvisor_cache`

Полный список: `docs/CRON.md`.
