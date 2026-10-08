# Трёхуровневый кэш поиска Tourvisor

Публичный контракт не меняется: `/frontend/api/tourvisor-proxy.php`.  
Заголовок ответа: `X-Tourvisor-Cache-Layer`.

| Уровень | Где | Когда |
|---------|-----|--------|
| **L1** `L1-firestore` | Firestore `searchCache` / `dictionaryCache` | Первый read (remote), SpaceWeb «Взлёт» |
| **L2** `L2-file` | `data/tourvisor_cache/*.json` | Miss/timeout Firestore → локальный файл |
| **L3** `L3-go` | Go `search-cache-reader` | Позже на VPS (сейчас не на shared) |
| `live` | Tourvisor API | Полный miss + не `cacheOnly` |

## Cover-cache (даты + фильтры)

Поверх exact-key и L1/L2: покрытие по диапазону дат для одного **identity**.

| | |
|--|--|
| Модуль | `backend/components/tourvisor_search_cover.php` |
| Индекс | `data/tourvisor_cache/search_cover_index.json` |
| Blob | `data/tourvisor_cache/cover_*.json` |

**Identity (жёсткое):** `departure|country|adults|childs|nightsFrom-nightsTo|currency`  
Даты — в `coverFrom`/`coverTo`, не в identity.

**Read (`search-cached`, без onlyPromo):**
1. Exact key → `X-Tourvisor-Cache-Read: exact`
2. Cover hit (тот же состав туристов, nights cover ⊇ запрос, dates ⊆ cover) → filter → `cover` / `cover-filter`
3. Иначе miss / live

**Нельзя** отдавать cover с другими adults/childs.  
**Можно** узкие ночи из более широкого cover (warm пишет 5–10).

**Warm** (`warm_home_search_cache.php`): горизонт `today+3…+TH_HOME_COVER_DAYS` (по умолчанию 42), сначала ближние `+TH_HOME_COVER_PRIORITY_DAYS` (21), nights `5–10`, skip если cover свежий и закрывает горизонт, иначе live только по дырам (≤14 дней). Туристы: `2+0` всегда, `2+1` (возраст 7) для топ-5 стран. Страны из `popular_countries.php`. Бюджет live: `TH_WARM_MAX_LIVE_CHUNKS` (по умолчанию 80). Календарная лестница месяцев **не** расширяет этот горизонт.

Витрина главной (`home_showcase_shelves`): читает `promo_cache_*` (не search-cover). Прогрев: `warm_promotions_cache.sh` / `update_promotions_cache.php` — cron `0 0,12 * * *` (минимум раз в сутки ночью).

Заголовки: `X-Tourvisor-Cache-Cover`, `X-Tourvisor-Cache-Identity`.

Флаги отката:
- `TH_SEARCH_COVER_ENABLED=0` — отключить read/write cover-слой (останется exact + live).
- `TH_WARM_COVER_ENABLED=0` — вернуть legacy full-live warm.

Housekeeping:
- `php backend/cron/cleanup_search_cover_cache.php` (рекомендуется daily cron).

```
Запрос search-cached / справочники
   │
   ├─ L1 Firestore  (TH_CACHE_FIRESTORE_FIRST=1, timeout ~2.5s)
   │     hit → JSON + прогрев L2 файла
   │
   ├─ L2 файл exact-key
   │     hit → JSON
   │
   ├─ L2-cover (nights ⊇ + dates ⊆ + те же туристы)
   │     hit → filter → JSON
   │
   ├─ L3 Go (когда будет VPS + nginx)
   │
   └─ live Tourvisor → пишем L2 exact + cover upsert (+ L1)
```

SWR на фронте (`cacheOnly` → paint → `live=1`) работает поверх всех слоёв.

## Миграция всего кэша с хостинга → Firestore

Кэш лежит в `data/tourvisor_cache/` на SpaceWeb. Чтобы **перегнать** его в облако:

```bash
# SSH / панель → в корне сайта
php backend/scripts/firestore_migrate_tourvisor_cache.php --dry-run
php backend/scripts/firestore_migrate_tourvisor_cache.php --delay-ms=150
```

| Флаг | Значение |
|------|----------|
| `--dry-run` | Только список, без записи |
| `--only=search` | Только `search_*.json` → `searchCache` |
| `--only=dictionaries` | Справочники → `dictionaryCache` |
| `--purge-local` | Удалить файл после успешной заливки (осторожно) |
| `--delay-ms=150` | Пауза между документами (меньше 429) |

Документы больше ~900 KB Firestore не примет — такие файлы останутся на диске как L2.

После миграции L1 начнёт отдавать прогретые поиски без повторного Tourvisor live.

## Очистка Firestore по cron (2× в неделю)

```bash
php backend/cron/firestore_cache_cleanup.php
# --dry-run  — показать что удалится
```

Удаляет документы с `expiresAt` в прошлом (searchCache + dictionaryCache).

```
# Пн и Чт 03:00 МСК
0 3 * * 1,4 cd /path/to/travelhub-v2 && php backend/cron/firestore_cache_cleanup.php >> data/firestore_cache_cleanup.log 2>&1
```

## Настройка (SpaceWeb / локально)

1. Service account JSON → `backend/config/firebase-service-account.json` (в `.gitignore`).
2. В корневом `.env`:

```bash
FIREBASE_PROJECT_ID=travelhub-mobile-5dade
TH_CACHE_FIRESTORE_FIRST=1
TH_CACHE_FIRESTORE_TIMEOUT_SEC=2.5
```

3. В Firebase Console создайте **Firestore** (Native mode), если ещё нет.
4. После первого live-поиска / warm cron документы появятся в `searchCache` и `dictionaryCache`.

Проверка:

```bash
curl -sI "https://YOUR_HOST/frontend/api/tourvisor-proxy.php?type=departures" \
  | grep -iE 'x-tourvisor-cache-layer|x-tourvisor-firestore'
# ожидаемо: L1-firestore или L2-file; Firestore не off
```

Прогрев (пишет файл + Firestore при live):

```bash
php backend/cron/warm_home_search_cache.php
```

## Фото Tourvisor

Не в Firestore. Остаются в `data/tourvisor_image_cache/` + cron `clear_image_cache.php`.

## Календарь выгодных дат

Календарь имеет отдельный rolling cache, **изолированный от акций**:

- файлы: `data/calendar_cache/calendar_{departureId}.json`;
- горизонт: лестница `TH_DEALS_CAL_MONTHS_AHEAD` (по умолчанию 3 месяца вперёд до конца месяца);
- сборщик: `backend/cron/warm_calendar_cache.php` — search cover + **seed** из promo
  (копия в `calendar_cache`, файлы акций не перезаписываются);
- акции (`data/promo_cache_*`) прогревает только `warm_promotions_cache` / promo-search;
  календарь их **не пишет**;
- live Tourvisor из календарного cron не вызывается.

Порядок прогрева: сначала `warm_promotions_cache.sh` (акции), затем
`warm_home_search_cache.sh` (cover для поиска), после него `warm_calendar_cache.sh`
(сборка calendar_cache из cover).

| API | Основной источник | Fallback |
|-----|-------------------|----------|
| `backend/api/calendar_price_map.php` | `calendar_cache` | read-only `promo_bootstrap` (не пишет promo) |
| `backend/api/calendar_day_tours.php` | `calendar_cache` | read-only `promo_bootstrap` |
| promo-search / страница акций | `promo_cache_*` | live onlyPromo |

Календарь **не пишет** `promo_cache_*`. Warm календаря может *прочитать* promo
и скопировать ближние даты в `calendar_cache` + добрать из search cover.

`deal` = выгодная цена (~40% самых дешёвых дней), `reduced` = пониженная
цена. Все даты и туры реальные; если cover не содержит вылет на день,
фейковая дата не создаётся.

## Безопасность

- JSON service account только на сервере, chmod 600, не в git.
- Если ключ светился — перевыпустите в Firebase Console.
- Client web config (`apiKey`) ≠ server credentials.
