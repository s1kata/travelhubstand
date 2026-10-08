# Ускорение поиска туров (best practice)

Двухслойная схема: **L1 Go** (быстрый cache read) + **L2 PHP** (полный монолит как fallback).  
Публичный URL для фронта не меняется: `/frontend/api/tourvisor-proxy.php`.

```
Браузер
   │
   ▼
Nginx
   │
   ├─ L1  Go :8080  ── cache HIT → JSON (~30–80 ms)
   │         │
   │         └─ miss / live=1 → HTTP на /_internal/tourvisor-proxy.php → PHP
   │
   └─ L2  если Go 502/503/504 / connect timeout → сразу PHP-FPM
            (тот же tourvisor-proxy.php, заголовок X-Cache-Reader: php-nginx-fallback)
```

Конфиг: [`deploy/nginx-travelhub63.conf`](../deploy/nginx-travelhub63.conf).

---

## 1. Go sidecar `search-cache-reader`

Читает файловый кэш `data/tourvisor_cache/` без bootstrap PHP (~3 s → ~50 ms).

```bash
cd search-cache-reader   # репозиторий s1kata/microservice
go build -o search-cache-reader .
export TOURVISOR_CACHE_DIR=/var/www/html/data/tourvisor_cache
# ВАЖНО: loop-safe URL (не публичный /frontend/api/… — иначе Nginx→Go→Nginx цикл)
export PHP_TOURVISOR_PROXY_URL=http://127.0.0.1/_internal/tourvisor-proxy.php
export LISTEN_ADDR=127.0.0.1:8080
./search-cache-reader
```

Проверка слоёв:

```bash
# L1 жив
curl -s http://127.0.0.1:8080/health

# Публичный URL — смотрим X-Cache-Reader (go | php-nginx-fallback | php-go-upstream | php)
curl -sI "https://travelhub63.ru/frontend/api/tourvisor-proxy.php?type=departures" | grep -iE 'x-cache-reader|x-tourvisor'

# Симуляция падения Go: systemctl stop search-cache-reader → снова curl — должен быть php-nginx-fallback
```

---

## 2. SWR на главной (`frontend/index.php`) — PHP-only / SpaceWeb

Без Go/VPS скорость = hit-rate + тонкий JSON + perceived speed.

1. **SessionStorage SWR** — повтор того же поиска (до 15 мин) → paint сразу, без сети.
2. **Скелетоны** карточек, пока идёт `cacheOnly`.
3. **`cacheOnly` + `slim=1`** — файловый кэш / cover, урезанный JSON списка.
4. **Prefetch** при выборе страны / вылета / ночей (фоновый `cacheOnly`).
5. Фоном **`live=1`** — обновление цен без блокировки UI.
6. Cold (нет кэша) — один live-запрос; UI уже показал скелетон/SWR.

Ответ списка: `X-Tourvisor-Slim: 1`. Полный hotel: `?full=1`.

### Cover-cache (даты + ночи + туристы)

Не отдельный URL — слой внутри `search-cached` (`tourvisor_search_cover.php`).

- Exact key → `X-Tourvisor-Cache-Read: exact`
- Cover (даты внутри прогретого окна, nights ⊆ широкого cover, те же adults/childs) → `cover` / `cover-filter`
- Другой состав туристов → другой identity (первый раз live)

Подробнее: [CACHE_LAYERS.md](CACHE_LAYERS.md#cover-cache-даты--фильтры).

---

## 3. Cron прогрева (критично на SpaceWeb)

```bash
php backend/cron/warm_home_search_cache.php
# или
bash backend/cron/warm_home_search_cache.sh
```

**Cover-aware warm:**
- горизонт `today+3 … today+42`;
- nights **5–10** (один широкий коридор);
- Самара + Москва × popular countries;
- `2+0` всегда, `2+1` (ребёнок 7 лет) для топ-5 стран;
- если cover свежий и закрывает горизонт → **skip**;
- иначе live только по **дырам** (куски ≤14 дней) → proxy делает cover upsert.
- аварийный откат: `TH_WARM_COVER_ENABLED=0` (legacy full-live warm).
- лимиты TV: `TH_WARM_LIVE_PAUSE_SEC` (пауза), `TH_WARM_MAX_LIVE_CHUNKS` (потолок live за прогон).

### Соблюдение лимитов Tourvisor API

По [документации шлюза](https://api.tourvisor.ru/search/docs) (~30 req/min; `continue` = отдельный search в суточном лимите):

| Механизм | Поведение |
|----------|-----------|
| Outbound throttle | `TH_TV_OUTBOUND_RPM=25` в `tvRequest()` |
| Status poll | пауза 3с → каждые 2с, soft timeout ~24с |
| `continue` | по умолчанию **выкл** (`TH_TV_CONTINUE_MAX=0`) |
| HTTP 429 | exponential backoff + retry |
| Warm | skip/extend + пауза между сегментами + budget cap |

В логе JSON: `skipped`, `extendedChunks`, `warmed`, `mode: cover-skip-extend`.

Рекомендуется **3–4× в сутки** (не в пик пользовательского трафика):

```
30 0,8,14,20 * * * cd /path/to/travelhub-v2 && bash backend/cron/warm_home_search_cache.sh >> data/home_search_warm.log 2>&1
```

На SpaceWeb часто нужен `php8.1` вместо `php` — поправьте в `.sh` при необходимости.

Акции (speed-cache): см. `backend/cron/update_promotions_cache.php` / `warm_promotions_cache.sh`.

Проверка cover:

```bash
curl -sI "https://YOUR_HOST/frontend/api/tourvisor-proxy.php?type=search-cached&departureId=7&countryId=4&dateFrom=...&dateTo=...&nightsFrom=6&nightsTo=9&adults=2&cacheOnly=1&cacheScope=country_page" \
  | grep -iE 'x-tourvisor-cache-read|x-tourvisor-cache-cover'
# после прогрева: cover | cover-filter | exact
```

Очистка старых cover-артефактов (рекомендуется 1× в сутки):

```bash
php backend/cron/cleanup_search_cover_cache.php
```

---

## 4. systemd unit (пример)

```ini
[Unit]
Description=Travel Hub search-cache-reader (L1)
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/search-cache-reader
Environment=TOURVISOR_CACHE_DIR=/var/www/html/data/tourvisor_cache
Environment=PHP_TOURVISOR_PROXY_URL=http://127.0.0.1/_internal/tourvisor-proxy.php
Environment=LISTEN_ADDR=127.0.0.1:8080
ExecStart=/opt/search-cache-reader/search-cache-reader
Restart=always
RestartSec=2

[Install]
WantedBy=multi-user.target
```

После смены nginx:

```bash
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl enable --now search-cache-reader
```

---

## Заголовки ответа

| Заголовок | Значения |
|-----------|----------|
| `X-Cache-Reader` | `go` (sidecar), `php-nginx-fallback` (Go down), `php-go-upstream` (Go→PHP miss/live), `php` (прямой PHP) |
| `X-Tourvisor-Cache` | `hit` / `miss` / `n/a` |
| `X-Tourvisor-Search-Mode` | `cache` / `live` |

---

## Ожидаемый эффект

| Сценарий | До | После |
|----------|-----|-------|
| Справочники / cache hit, Go up | 3–4 s | 50–150 ms |
| Cache hit, Go down | 502 | PHP L2, обычно &lt;1–3 s |
| Поиск (прогретый кэш) | нестабильно | &lt;1 s + SWR |
| Поиск (cold) | 30–60 s блокировка | preview из кэша + фон (SWR) |

---

## Откат

1. В nginx заменить `location = /frontend/api/tourvisor-proxy.php` на обычный `fastcgi_pass` (или закомментировать `proxy_pass` / `error_page` и оставить только `@tourvisor_php_fallback` логику как основной location).
2. `sudo systemctl stop search-cache-reader`
3. SWR на фронте безопасен без Go.

Полный откат к PHP-only location:

```nginx
location = /frontend/api/tourvisor-proxy.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/frontend/api/tourvisor-proxy.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_read_timeout 130s;
}
```

---

## Roadmap

**Сейчас (SpaceWeb, PHP-only):** session SWR + slim + **cover-cache** (skip/extend warm) + prefetch — без VPS.

Позже:

1. Prefetch tourist-комбо на фронте (2+1 при открытии блока туристов)
2. Partial paint + background delta на пользовательском запросе
3. Go `search-cache-reader` (L1 read без PHP bootstrap) — когда будет VPS
4. Async search jobs (TTFB &lt;200 ms на cold)
5. Cover для promo_speed_cache
6. Redis при нескольких нодах
