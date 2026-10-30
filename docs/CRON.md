# Активные cron-задачи TravelHub

**Prod SpaceWeb (hub63_ru):** готовая таблица для панели — [`spaceweb-crontab-hub63_ru.md`](spaceweb-crontab-hub63_ru.md).  
Корень: `/home/g/garant77li/hub63_ru/public_html`.

Ниже — единственный актуальный набор для SpaceWeb. Задачи из раздела
«Не запускать» нужно удалить из панели cron, но сами legacy-скрипты пока
оставлены в репозитории для безопасного отката.

Замените `/path/to/travelhub-v2` на корень сайта и проверьте путь к PHP 8.1.
Расписание указано для Europe/Moscow.

После правок в панели: `php backend/scripts/cron_health_check.php` (SSH).

## Канонический crontab

```cron
# Акции: только promo_cache (страница акций / витрина). Календарь этот кэш не пишет.
# Минимум 2×/сутки — даты «горящих» всегда от сегодня (иначе на главной висят августовские вылеты).
5 0,12 * * * cd /path/to/travelhub-v2 && PHP_BIN=/usr/bin/php8.1 flock -n data/promo_warm.lock bash backend/cron/warm_promotions_cache.sh >> data/promo_warm.log 2>&1

# Доп. страховочный прогрев горящих (вс 03:15) — если дневной cron срывался.
15 3 * * 0 cd /path/to/travelhub-v2 && PHP_BIN=/usr/bin/php8.1 flock -n data/promo_warm.lock bash backend/cron/warm_promotions_cache.sh >> data/promo_warm.log 2>&1

# Обычный поиск: exact/cover cache, Самара+Москва, популярные страны (~42 дня, near-first).
30 0,8,14,20 * * * cd /path/to/travelhub-v2 && flock -n data/search_warm.lock bash backend/cron/warm_home_search_cache.sh >> data/home_search_warm.log 2>&1

# Ночной поэтапный прогрев cover (все страны из popular_countries.php, горизонт ~30 дней).
30 1 * * * cd /path/to/travelhub-v2 && flock -n data/search_warm_nightly.lock env WARM_NIGHTLY_PHASE=0 PHP_BIN=/usr/bin/php8.1 bash backend/cron/warm_home_search_nightly.sh >> data/home_search_warm_nightly.log 2>&1
30 2 * * * cd /path/to/travelhub-v2 && flock -n data/search_warm_nightly.lock env WARM_NIGHTLY_PHASE=1 PHP_BIN=/usr/bin/php8.1 bash backend/cron/warm_home_search_nightly.sh >> data/home_search_warm_nightly.log 2>&1
30 3 * * * cd /path/to/travelhub-v2 && flock -n data/search_warm_nightly.lock env WARM_NIGHTLY_PHASE=2 PHP_BIN=/usr/bin/php8.1 bash backend/cron/warm_home_search_nightly.sh >> data/home_search_warm_nightly.log 2>&1

# Календарь: calendar_cache из cover + seed из promo (копия, без перезаписи promo_cache).
20 1,9,15,21 * * * cd /path/to/travelhub-v2 && PHP_BIN=/usr/bin/php8.1 flock -n data/calendar_warm.lock bash backend/cron/warm_calendar_cache.sh >> data/calendar_warm.log 2>&1

# YML — после ночного прогрева акций (ротация или правила).
20 0 * * * cd /path/to/travelhub-v2 && /usr/bin/php8.1 backend/scripts/yml_feed_rules_cron.php >> data/yandex_yml_rules_cron.log 2>&1

# Ротация YML: прогрев стран текущего batch (пн 00:10), если в админке включена ротация.
10 0 * * 1 cd /path/to/travelhub-v2 && PHP_BIN=/usr/bin/php8.1 flock -n data/yml_rotation_warm.lock bash backend/cron/warm_yml_rotation_countries.sh >> data/yml_rotation_warm.log 2>&1

# Housekeeping.
0 2 * * * cd /path/to/travelhub-v2 && /usr/bin/php8.1 backend/cron/cleanup_search_cover_cache.php >> data/cover_cleanup.log 2>&1
30 4 * * * cd /path/to/travelhub-v2 && /usr/bin/php8.1 clear_image_cache.php 14 --trim-mb=1024 >> data/image_cache_cron.log 2>&1
0 5 * * 0 cd /path/to/travelhub-v2 && /usr/bin/php8.1 clear_cache.php 10 >> data/tourvisor_cache_cleanup.log 2>&1

# Только если реально включён Firestore L1.
30 5 * * 1,4 cd /path/to/travelhub-v2 && /usr/bin/php8.1 backend/cron/firestore_cache_cleanup.php >> data/firestore_cache_cleanup.log 2>&1
```

Если на SpaceWeb нет `flock`, уберите только `flock -n data/*.lock`, остальную
команду оставьте. Важно не ставить search warm и promo warm на одно время.

## Метрики поиска и акций в Grafana Cloud (OTLP push)

На shared-хостинге не запускается постоянно работающий Prometheus/Alloy. Вместо
scrape PHP сохраняет агрегированные гистограммные buckets в MySQL, а этот cron
раз в минуту отправляет накопительные OTLP-метрики напрямую в Grafana Cloud.
Это push через OTLP, а не Prometheus text endpoint и не классический Prometheus
scrape. В Grafana Cloud метрики доступны для запросов и дашбордов после
настройки приёма OTLP и источника метрик.

Перед первым запуском добавьте в корневой `.env` данные OTLP metrics endpoint,
Instance ID и токен Grafana Cloud с правом записи метрик. Endpoint должен быть
полным HTTPS URL, заканчивающимся на `/otlp/v1/metrics`:

```env
GRAFANA_OTLP_METRICS_ENDPOINT=https://<ваш-otlp-host>/otlp/v1/metrics
GRAFANA_CLOUD_INSTANCE_ID=<ваш-instance-id>
GRAFANA_CLOUD_API_TOKEN=<токен-с-правом-записи-метрик>
DEPLOYMENT_ENVIRONMENT=production
```

Не добавляйте реальные значения в Git и не передавайте токен в командной строке.
После загрузки изменений на хостинг один раз создайте таблицу агрегатов:

```sh
cd /path/to/travelhub-v2
php8.3 backend/cron/prometheus_metrics_export.php --init
```

Добавьте в cron SpaceWeb:

```cron
* * * * * cd /path/to/travelhub-v2 && php8.3 backend/cron/prometheus_metrics_export.php >> data/logs/prometheus_metrics_cron.log 2>&1
```

PHP API записывает длительности поиска, опроса статуса, получения результатов,
поиска по кэшу и запросов акций. Также отдельно записываются длительности
вызовов Tourvisor целиком (включая повторы/ограничение частоты) и последней
cURL-попытки. Метрики имеют ограниченные labels `operation`, `source`, `result`;
данные пользователей, ID поиска, URL и параметры запроса не записываются.
Buckets: 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30, 60, 120, 180 секунд и
`+Inf`.

Это серверные задержки: PHP-обработчик и HTTP-запрос от сервера к Tourvisor
(cURL time включает сеть и обработку на стороне API). Они не измеряют RTT до
браузера/телефона, полное время от нажатия до отрисовки, клиентское CPU/память
или загрузку пула PHP-FPM.

Проверяйте результат cron в `data/logs/prometheus_metrics_cron.log`.
Ошибки записи агрегатов из веб-запросов попадают в
`data/logs/prometheus_metrics.log`. Таблица содержит только накопительные
агрегаты и имеет ограниченное число рядов; отправка повторяет их как
cumulative histograms, поэтому временный сбой cron не удаляет уже собранные
наблюдения.

## Что обслуживает сайт

- `warm_home_search_cache.sh` — обычный поиск на главной, страницах стран и VIP.
- `warm_promotions_cache.sh` — страница акций и горячая витрина (`promo_cache_*` только).
- `warm_calendar_cache.sh` — `calendar_cache` из search cover + seed из promo (read-only).
- `yml_feed_rules_cron.php` — `/feed.yml` + `/feed-samara.yml` + `/feed-moscow.yml` (ротация из админки или старые правила).
- `warm_yml_rotation_countries.sh` — прогрев стран текущего batch ротации (пн).
- cleanup-задачи — размер диска и удаление устаревших cover-файлов.

Календарный warm не обращается к live API и **не пишет** promo_cache (seed — только чтение).
Он запускается после search warm: cover + опциональный seed из promo.

## Не запускать как cron

- `backend/scripts/tourvisor_background_update.php` — заменён cover-aware warm.
- `backend/scripts/promo_tours_refresh.php` — legacy onlyPromo/Yandex pipeline.
- `backend/scripts/sync_yandex_feed_offers.php` — дублирует legacy pipeline.
- `backend/scripts/warmup_tourvisor_cache.php` — только разовый bootstrap.
- `backend/promo_tours_sync/fetch_tours.php` — только если отдельно используется
  legacy/mobile таблица `promo_tours`.
- `backend/scripts/firestore_migrate_tourvisor_cache.php` — только разовая миграция.

Если в кабинете Yandex ещё указан старый `/export/services_yml.php`, сначала
переведите его на актуальный `/feed.yml`; только после этого отключайте legacy
Yandex cron.

## Обязательные переменные `.env`

```env
SITE_URL=https://travelhub63.ru
TOURVISOR_PROXY_RELATIVE_PATH=frontend/api/tourvisor-proxy.php

TH_SEARCH_COVER_ENABLED=1
TH_WARM_COVER_ENABLED=1
TH_TV_OUTBOUND_RPM=25
TH_TV_CONTINUE_MAX=0
TH_WARM_LIVE_PAUSE_SEC=2.5
TH_WARM_MAX_LIVE_CHUNKS=80
TH_HOME_COVER_DAYS=42
TH_HOME_COVER_PRIORITY_DAYS=21

TOURVISOR_COUNTRY_PAGE_CACHE_TTL_HOURS=24
PROMO_SPEED_CACHE_TTL_HOURS=18

# Горизонт календаря = текущий месяц + N вперёд (3 → до конца ноября из августа).
# Не влияет на promo_cache и не расширяет home cover warm.
TH_DEALS_CAL_MONTHS_AHEAD=3
TH_CALENDAR_CACHE_TTL_HOURS=30

TOURVISOR_IMAGE_CACHE_TTL_DAYS=14
TOURVISOR_IMAGE_CACHE_MAX_MB=1024
YANDEX_LEGACY_OFFERS_TABLE_SYNC=0
```

## После деплоя

```bash
cd /path/to/travelhub-v2
PHP_BIN=/usr/bin/php8.1 bash backend/cron/warm_promotions_cache.sh
bash backend/cron/warm_home_search_cache.sh
PHP_BIN=/usr/bin/php8.1 bash backend/cron/warm_calendar_cache.sh
```

Проверка результата:

```bash
php -r '$d=json_decode(file_get_contents("data/calendar_cache/calendar_7.json"),true); echo $d["filledDays"]."/".$d["totalDays"].PHP_EOL;'
```
