<?php
declare(strict_types=1);

/**
 * Cover-cache поиска Tourvisor: покрытие по датам + safe filter-down по ночам.
 *
 * Identity (жёсткое): departure|country|adults|childs|nightsFrom-nightsTo|currency
 * Даты — в meta coverFrom/coverTo, не в identity.
 *
 * Safe hit: тот же состав туристов, nights cover ⊇ request, dates cover ⊇ request.
 */

if (!function_exists('th_search_cover_cache_dir')) {
    function th_search_cover_cache_dir(): string
    {
        if (function_exists('tvCacheDir')) {
            return tvCacheDir();
        }
        $explicit = trim((string) (getenv('TOURVISOR_CACHE_DIR') ?: ($_ENV['TOURVISOR_CACHE_DIR'] ?? '')));
        if ($explicit !== '') {
            return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $explicit), DIRECTORY_SEPARATOR);
        }
        $root = function_exists('th_project_root') ? th_project_root() : dirname(__DIR__, 2);

        return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tourvisor_cache';
    }
}

if (!function_exists('th_search_cover_index_path')) {
    function th_search_cover_index_path(): string
    {
        return th_search_cover_cache_dir() . DIRECTORY_SEPARATOR . 'search_cover_index.json';
    }
}

if (!function_exists('th_search_cover_lock_path')) {
    function th_search_cover_lock_path(): string
    {
        return th_search_cover_cache_dir() . DIRECTORY_SEPARATOR . 'search_cover_index.lock';
    }
}

if (!function_exists('th_search_cover_with_index_lock')) {
    /**
     * Простая межпроцессная блокировка операций с индексом/cover.
     *
     * @template T
     * @param callable():T $callback
     * @return T|null
     */
    function th_search_cover_with_index_lock(callable $callback): mixed
    {
        $dir = th_search_cover_cache_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $lockPath = th_search_cover_lock_path();
        $h = @fopen($lockPath, 'c+');
        if ($h === false) {
            return null;
        }
        try {
            if (!@flock($h, LOCK_EX)) {
                return null;
            }
            return $callback();
        } finally {
            @flock($h, LOCK_UN);
            @fclose($h);
        }
    }
}

if (!function_exists('th_search_cover_normalize_childs')) {
    /**
     * Нормализация возрастов детей для identity: "7,5" → "5,7".
     */
    function th_search_cover_normalize_childs(mixed $childs): string
    {
        $ages = [];
        if (is_array($childs)) {
            foreach ($childs as $a) {
                if ($a === '' || $a === null) {
                    continue;
                }
                $ages[] = (int) $a;
            }
        } else {
            $raw = trim((string) $childs);
            if ($raw !== '') {
                foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $p) {
                    if ($p === '') {
                        continue;
                    }
                    $ages[] = (int) $p;
                }
            }
        }
        $ages = array_values(array_filter($ages, static fn (int $a): bool => $a >= 0 && $a <= 17));
        sort($ages, SORT_NUMERIC);

        return implode(',', $ages);
    }
}

if (!function_exists('th_search_cover_identity')) {
    /**
     * @param array<string,mixed> $params departureId, countryId, adults, childs, nightsFrom, nightsTo, currency
     */
    function th_search_cover_identity(array $params, ?int $nightsFrom = null, ?int $nightsTo = null): string
    {
        $dep = (int) ($params['departureId'] ?? 0);
        $cnt = (int) ($params['countryId'] ?? 0);
        $adults = max(1, (int) ($params['adults'] ?? 2));
        $childs = th_search_cover_normalize_childs($params['childs'] ?? '');
        $nf = $nightsFrom ?? (int) ($params['nightsFrom'] ?? 6);
        $nt = $nightsTo ?? (int) ($params['nightsTo'] ?? 9);
        if ($nt < $nf) {
            $tmp = $nf;
            $nf = $nt;
            $nt = $tmp;
        }
        $nf = max(1, min(28, $nf));
        $nt = max(1, min(28, $nt));
        $currency = strtoupper(trim((string) ($params['currency'] ?? 'RUB'))) ?: 'RUB';

        return $dep . '|' . $cnt . '|' . $adults . '|' . $childs . '|' . $nf . '-' . $nt . '|' . $currency;
    }
}

if (!function_exists('th_search_cover_identity_base')) {
    /** Часть identity без ночей — для поиска nights-superset. */
    function th_search_cover_identity_base(array $params): string
    {
        $dep = (int) ($params['departureId'] ?? 0);
        $cnt = (int) ($params['countryId'] ?? 0);
        $adults = max(1, (int) ($params['adults'] ?? 2));
        $childs = th_search_cover_normalize_childs($params['childs'] ?? '');
        $currency = strtoupper(trim((string) ($params['currency'] ?? 'RUB'))) ?: 'RUB';

        return $dep . '|' . $cnt . '|' . $adults . '|' . $childs . '|' . $currency;
    }
}

if (!function_exists('th_search_cover_parse_identity')) {
    /**
     * @return array{departureId:int,countryId:int,adults:int,childs:string,nightsFrom:int,nightsTo:int,currency:string}|null
     */
    function th_search_cover_parse_identity(string $identity): ?array
    {
        $parts = explode('|', $identity);
        if (count($parts) !== 6) {
            return null;
        }
        $nights = explode('-', $parts[4]);
        if (count($nights) !== 2) {
            return null;
        }

        return [
            'departureId' => (int) $parts[0],
            'countryId' => (int) $parts[1],
            'adults' => (int) $parts[2],
            'childs' => (string) $parts[3],
            'nightsFrom' => (int) $nights[0],
            'nightsTo' => (int) $nights[1],
            'currency' => (string) $parts[5],
        ];
    }
}

if (!function_exists('th_search_cover_file_for_identity')) {
    function th_search_cover_file_for_identity(string $identity): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_|-]/', '_', $identity) ?: 'unknown';
        $safe = str_replace('|', '_', $safe);

        return th_search_cover_cache_dir() . DIRECTORY_SEPARATOR . 'cover_' . $safe . '.json';
    }
}

if (!function_exists('th_search_cover_index_load')) {
    /**
     * @return array{entries: array<string, array<string,mixed>>}
     */
    function th_search_cover_index_load(): array
    {
        $path = th_search_cover_index_path();
        if (!is_file($path)) {
            return ['entries' => []];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return ['entries' => []];
        }
        $d = json_decode($raw, true);
        if (!is_array($d)) {
            return ['entries' => []];
        }
        $entries = $d['entries'] ?? $d;
        if (!is_array($entries)) {
            return ['entries' => []];
        }

        return ['entries' => $entries];
    }
}

if (!function_exists('th_search_cover_index_save')) {
    /**
     * @param array{entries: array<string, array<string,mixed>>} $index
     */
    function th_search_cover_index_save(array $index): bool
    {
        $dir = th_search_cover_cache_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = th_search_cover_index_path();
        $payload = json_encode([
            'version' => 1,
            'updatedAt' => time(),
            'entries' => $index['entries'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return false;
        }
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return false;
        }

        return @rename($tmp, $path);
    }
}

if (!function_exists('th_search_cover_nights_supset')) {
    function th_search_cover_nights_supset(int $cFrom, int $cTo, int $qFrom, int $qTo): bool
    {
        return $cFrom <= $qFrom && $cTo >= $qTo;
    }
}

if (!function_exists('th_search_cover_dates_supset')) {
    function th_search_cover_dates_supset(string $cFrom, string $cTo, string $qFrom, string $qTo): bool
    {
        $cf = strtotime($cFrom);
        $ct = strtotime($cTo);
        $qf = strtotime($qFrom);
        $qt = strtotime($qTo);
        if ($cf === false || $ct === false || $qf === false || $qt === false) {
            return false;
        }

        return $cf <= $qf && $ct >= $qt;
    }
}

if (!function_exists('th_search_cover_load_blob')) {
    /**
     * @return array{meta: array<string,mixed>, hotels: list<mixed>}|null
     */
    function th_search_cover_load_blob(string $file): ?array
    {
        if ($file === '' || !is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $d = json_decode($raw, true);
        if (!is_array($d) || !isset($d['hotels']) || !is_array($d['hotels']) || $d['hotels'] === []) {
            return null;
        }

        return [
            'meta' => is_array($d['meta'] ?? null) ? $d['meta'] : [],
            'hotels' => $d['hotels'],
        ];
    }
}

if (!function_exists('th_search_cover_find')) {
    /**
     * Найти свежий cover, совместимый с запросом (туристы + nights ⊇ + dates ⊇).
     *
     * @param array<string,mixed> $params
     * @return array{identity:string,meta:array<string,mixed>,hotels:list<mixed>,read:string}|null
     */
    function th_search_cover_find(array $params, int $ttlSeconds): ?array
    {
        $qFrom = trim((string) ($params['dateFrom'] ?? ''));
        $qTo = trim((string) ($params['dateTo'] ?? ''));
        if ($qFrom === '' || $qTo === '') {
            return null;
        }
        $qNf = (int) ($params['nightsFrom'] ?? 6);
        $qNt = (int) ($params['nightsTo'] ?? 9);
        if ($qNt < $qNf) {
            $tmp = $qNf;
            $qNf = $qNt;
            $qNt = $tmp;
        }
        $base = th_search_cover_identity_base($params);
        $index = th_search_cover_index_load();
        $now = time();
        $candidates = [];

        foreach ($index['entries'] as $identity => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $parsed = th_search_cover_parse_identity((string) $identity);
            if ($parsed === null) {
                continue;
            }
            $entryBase = $parsed['departureId'] . '|' . $parsed['countryId'] . '|' . $parsed['adults'] . '|'
                . $parsed['childs'] . '|' . $parsed['currency'];
            if ($entryBase !== $base) {
                continue;
            }
            if (!th_search_cover_nights_supset($parsed['nightsFrom'], $parsed['nightsTo'], $qNf, $qNt)) {
                continue;
            }
            $cFrom = (string) ($entry['from'] ?? '');
            $cTo = (string) ($entry['to'] ?? '');
            if ($cFrom === '' || $cTo === '' || !th_search_cover_dates_supset($cFrom, $cTo, $qFrom, $qTo)) {
                continue;
            }
            $mtime = (int) ($entry['mtime'] ?? 0);
            if ($ttlSeconds > 0 && $mtime > 0 && ($now - $mtime) >= $ttlSeconds) {
                continue;
            }
            $file = (string) ($entry['file'] ?? '');
            if ($file === '') {
                $file = th_search_cover_file_for_identity((string) $identity);
            } elseif ($file[0] !== '/' && strpos($file, ':') === false) {
                $file = th_search_cover_cache_dir() . DIRECTORY_SEPARATOR . basename($file);
            }
            $blob = th_search_cover_load_blob($file);
            if ($blob === null) {
                continue;
            }
            $nightsSpan = $parsed['nightsTo'] - $parsed['nightsFrom'];
            $exactNights = ($parsed['nightsFrom'] === $qNf && $parsed['nightsTo'] === $qNt) ? 1 : 0;
            $candidates[] = [
                'identity' => (string) $identity,
                'meta' => array_merge($blob['meta'], [
                    'from' => $cFrom,
                    'to' => $cTo,
                    'nightsFrom' => $parsed['nightsFrom'],
                    'nightsTo' => $parsed['nightsTo'],
                    'mtime' => $mtime,
                ]),
                'hotels' => $blob['hotels'],
                'exactNights' => $exactNights,
                'nightsSpan' => $nightsSpan,
                'mtime' => $mtime,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['exactNights'] !== $b['exactNights']) {
                return $b['exactNights'] <=> $a['exactNights'];
            }
            if ($a['nightsSpan'] !== $b['nightsSpan']) {
                return $a['nightsSpan'] <=> $b['nightsSpan'];
            }

            return $b['mtime'] <=> $a['mtime'];
        });

        $best = $candidates[0];
        $read = !empty($best['exactNights']) ? 'cover' : 'cover-filter';

        return [
            'identity' => $best['identity'],
            'meta' => $best['meta'],
            'hotels' => $best['hotels'],
            'read' => $read,
        ];
    }
}

if (!function_exists('th_search_cover_tour_fp')) {
    function th_search_cover_tour_fp(array $tour): string
    {
        $id = (string) ($tour['id'] ?? '');
        if ($id !== '') {
            return 'id:' . $id;
        }
        $date = (string) ($tour['date'] ?? $tour['flydate'] ?? $tour['datefrom'] ?? '');
        $nights = (string) ($tour['nights'] ?? '');
        $price = (string) ($tour['price'] ?? $tour['pricevalue'] ?? '');

        return 'd:' . $date . '|n:' . $nights . '|p:' . $price;
    }
}

if (!function_exists('th_search_cover_merge_hotels')) {
    /**
     * @param list<mixed> $existing
     * @param list<mixed> $incoming
     * @return list<mixed>
     */
    function th_search_cover_merge_hotels(array $existing, array $incoming): array
    {
        $byHotel = [];
        foreach ($existing as $hotel) {
            if (!is_array($hotel)) {
                continue;
            }
            $hid = (string) ($hotel['id'] ?? '');
            if ($hid === '') {
                $byHotel['anon_' . count($byHotel)] = $hotel;
                continue;
            }
            $byHotel[$hid] = $hotel;
        }
        foreach ($incoming as $hotel) {
            if (!is_array($hotel)) {
                continue;
            }
            $hid = (string) ($hotel['id'] ?? '');
            if ($hid === '') {
                $byHotel['anon_' . count($byHotel)] = $hotel;
                continue;
            }
            if (!isset($byHotel[$hid])) {
                $byHotel[$hid] = $hotel;
                continue;
            }
            $oldTours = is_array($byHotel[$hid]['tours'] ?? null) ? $byHotel[$hid]['tours'] : [];
            $newTours = is_array($hotel['tours'] ?? null) ? $hotel['tours'] : [];
            $seen = [];
            $mergedTours = [];
            foreach (array_merge($oldTours, $newTours) as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $fp = th_search_cover_tour_fp($t);
                if (isset($seen[$fp])) {
                    continue;
                }
                $seen[$fp] = true;
                $mergedTours[] = $t;
            }
            $byHotel[$hid] = array_merge($byHotel[$hid], $hotel);
            $byHotel[$hid]['tours'] = $mergedTours;
        }

        return array_values($byHotel);
    }
}

if (!function_exists('th_search_cover_min_date')) {
    function th_search_cover_min_date(string $a, string $b): string
    {
        $ta = strtotime($a);
        $tb = strtotime($b);
        if ($ta === false) {
            return $b;
        }
        if ($tb === false) {
            return $a;
        }

        return $ta <= $tb ? $a : $b;
    }
}

if (!function_exists('th_search_cover_max_date')) {
    function th_search_cover_max_date(string $a, string $b): string
    {
        $ta = strtotime($a);
        $tb = strtotime($b);
        if ($ta === false) {
            return $b;
        }
        if ($tb === false) {
            return $a;
        }

        return $ta >= $tb ? $a : $b;
    }
}

if (!function_exists('th_search_cover_upsert')) {
    /**
     * Записать/расширить cover после live или warm-сегмента.
     *
     * @param array<string,mixed> $params
     * @param list<mixed> $hotels
     * @return array{identity:string,from:string,to:string,hotels:int}|null
     */
    function th_search_cover_upsert(array $params, array $hotels, int $ttlSeconds = 0): ?array
    {
        if ($hotels === []) {
            return null;
        }
        $dateFrom = trim((string) ($params['dateFrom'] ?? ''));
        $dateTo = trim((string) ($params['dateTo'] ?? ''));
        if ($dateFrom === '' || $dateTo === '') {
            return null;
        }

        $nf = (int) ($params['nightsFrom'] ?? 6);
        $nt = (int) ($params['nightsTo'] ?? 9);
        if ($nt < $nf) {
            $tmp = $nf;
            $nf = $nt;
            $nt = $tmp;
        }

        $saved = th_search_cover_with_index_lock(static function () use ($params, $hotels, $ttlSeconds, $dateFrom, $dateTo, $nf, $nt): ?array {
            // Предпочитаем расширять существующий nights-superset с тем же tourist base
            $index = th_search_cover_index_load();
            $base = th_search_cover_identity_base($params);
            $targetIdentity = th_search_cover_identity($params, $nf, $nt);
            $mergeIdentity = $targetIdentity;

            foreach ($index['entries'] as $identity => $entry) {
                $parsed = th_search_cover_parse_identity((string) $identity);
                if ($parsed === null || !is_array($entry)) {
                    continue;
                }
                $entryBase = $parsed['departureId'] . '|' . $parsed['countryId'] . '|' . $parsed['adults'] . '|'
                    . $parsed['childs'] . '|' . $parsed['currency'];
                if ($entryBase !== $base) {
                    continue;
                }
                // Пишем в более широкий nights cover, если он уже есть
                if (th_search_cover_nights_supset($parsed['nightsFrom'], $parsed['nightsTo'], $nf, $nt)) {
                    $mergeIdentity = (string) $identity;
                    break;
                }
            }

            $file = th_search_cover_file_for_identity($mergeIdentity);
            $existingHotels = [];
            $coverFrom = $dateFrom;
            $coverTo = $dateTo;
            $parsedMerge = th_search_cover_parse_identity($mergeIdentity);
            $coverNf = $parsedMerge['nightsFrom'] ?? $nf;
            $coverNt = $parsedMerge['nightsTo'] ?? $nt;

            $blob = th_search_cover_load_blob($file);
            if ($blob !== null) {
                $existingHotels = $blob['hotels'];
                $metaFrom = trim((string) ($blob['meta']['coverFrom'] ?? $index['entries'][$mergeIdentity]['from'] ?? ''));
                $metaTo = trim((string) ($blob['meta']['coverTo'] ?? $index['entries'][$mergeIdentity]['to'] ?? ''));
                if ($metaFrom !== '') {
                    $coverFrom = th_search_cover_min_date($metaFrom, $dateFrom);
                }
                if ($metaTo !== '') {
                    $coverTo = th_search_cover_max_date($metaTo, $dateTo);
                }
            } elseif (isset($index['entries'][$mergeIdentity]) && is_array($index['entries'][$mergeIdentity])) {
                $e = $index['entries'][$mergeIdentity];
                $ef = trim((string) ($e['from'] ?? ''));
                $et = trim((string) ($e['to'] ?? ''));
                if ($ef !== '') {
                    $coverFrom = th_search_cover_min_date($ef, $dateFrom);
                }
                if ($et !== '') {
                    $coverTo = th_search_cover_max_date($et, $dateTo);
                }
            }

            $merged = th_search_cover_merge_hotels($existingHotels, $hotels);
            $now = time();
            $meta = [
                'identity' => $mergeIdentity,
                'coverFrom' => $coverFrom,
                'coverTo' => $coverTo,
                'nightsFrom' => $coverNf,
                'nightsTo' => $coverNt,
                'departureId' => (int) ($params['departureId'] ?? 0),
                'countryId' => (int) ($params['countryId'] ?? 0),
                'adults' => max(1, (int) ($params['adults'] ?? 2)),
                'childs' => th_search_cover_normalize_childs($params['childs'] ?? ''),
                'currency' => strtoupper(trim((string) ($params['currency'] ?? 'RUB'))) ?: 'RUB',
                'warmedAt' => $now,
                'ttl' => $ttlSeconds,
            ];

            $dir = th_search_cover_cache_dir();
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $payload = json_encode([
                'meta' => $meta,
                'hotels' => $merged,
                'cachedAt' => $now,
            ], JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                return null;
            }
            $tmp = $file . '.tmp';
            if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
                return null;
            }
            if (!@rename($tmp, $file)) {
                @unlink($tmp);

                return null;
            }

            $index['entries'][$mergeIdentity] = [
                'file' => basename($file),
                'from' => $coverFrom,
                'to' => $coverTo,
                'nightsFrom' => $coverNf,
                'nightsTo' => $coverNt,
                'mtime' => $now,
                'hotels' => count($merged),
                'departureId' => $meta['departureId'],
                'countryId' => $meta['countryId'],
                'adults' => $meta['adults'],
                'childs' => $meta['childs'],
                'currency' => $meta['currency'],
            ];
            if (!th_search_cover_index_save($index)) {
                return null;
            }

            return [
                'identity' => $mergeIdentity,
                'from' => $coverFrom,
                'to' => $coverTo,
                'hotels' => count($merged),
            ];
        });

        return is_array($saved) ? $saved : null;
    }
}

if (!function_exists('th_search_cover_get_entry')) {
    /**
     * @return array<string,mixed>|null
     */
    function th_search_cover_get_entry(string $identity): ?array
    {
        $index = th_search_cover_index_load();
        $e = $index['entries'][$identity] ?? null;

        return is_array($e) ? $e : null;
    }
}

if (!function_exists('th_search_cover_date_gaps')) {
    /**
     * Дыры целевого горизонта относительно текущего cover.
     *
     * @return list<array{from:string,to:string}>
     */
    function th_search_cover_date_gaps(string $targetFrom, string $targetTo, ?string $coverFrom, ?string $coverTo): array
    {
        $tf = strtotime($targetFrom);
        $tt = strtotime($targetTo);
        if ($tf === false || $tt === false || $tt < $tf) {
            return [];
        }
        if ($coverFrom === null || $coverTo === null || $coverFrom === '' || $coverTo === '') {
            return [['from' => $targetFrom, 'to' => $targetTo]];
        }
        $cf = strtotime($coverFrom);
        $ct = strtotime($coverTo);
        if ($cf === false || $ct === false) {
            return [['from' => $targetFrom, 'to' => $targetTo]];
        }

        $gaps = [];
        // до cover
        if ($tf < $cf) {
            $gapTo = min($tt, $cf - 86400);
            if ($gapTo >= $tf) {
                $gaps[] = [
                    'from' => date('Y-m-d', $tf),
                    'to' => date('Y-m-d', $gapTo),
                ];
            }
        }
        // после cover
        if ($tt > $ct) {
            $gapFrom = max($tf, $ct + 86400);
            if ($gapFrom <= $tt) {
                $gaps[] = [
                    'from' => date('Y-m-d', $gapFrom),
                    'to' => date('Y-m-d', $tt),
                ];
            }
        }

        return $gaps;
    }
}

if (!function_exists('th_search_cover_split_chunks')) {
    /**
     * Нарезать диапазон на куски ≤ $maxDays (включительно: maxDays дней = diff maxDays-1? )
     * Tourvisor: dateTo - dateFrom ≤ 14 календарных дней разницы → diff days <= 14.
     *
     * @return list<array{from:string,to:string}>
     */
    function th_search_cover_split_chunks(string $from, string $to, int $maxSpanDays = 14): array
    {
        $tf = strtotime($from);
        $tt = strtotime($to);
        if ($tf === false || $tt === false || $tt < $tf) {
            return [];
        }
        $maxSpanDays = max(1, min(14, $maxSpanDays));
        $chunks = [];
        $cursor = $tf;
        while ($cursor <= $tt) {
            $chunkEnd = min($tt, $cursor + ($maxSpanDays * 86400));
            $chunks[] = [
                'from' => date('Y-m-d', $cursor),
                'to' => date('Y-m-d', $chunkEnd),
            ];
            $cursor = $chunkEnd + 86400;
        }

        return $chunks;
    }
}

if (!function_exists('th_search_cover_is_fresh')) {
    function th_search_cover_is_fresh(?array $entry, int $ttlSeconds): bool
    {
        if ($entry === null) {
            return false;
        }
        $mtime = (int) ($entry['mtime'] ?? 0);
        if ($mtime <= 0) {
            return false;
        }
        if ($ttlSeconds <= 0) {
            return true;
        }

        return (time() - $mtime) < $ttlSeconds;
    }
}

if (!function_exists('th_search_cover_wide_nights')) {
    /**
     * Широкий коридор ночей для warm (лимит TV = 10).
     *
     * @return array{from:int,to:int}
     */
    function th_search_cover_wide_nights(): array
    {
        return ['from' => 5, 'to' => 10];
    }
}

if (!function_exists('th_search_cover_cleanup')) {
    /**
     * Очистка индекса/файлов cover от устаревших и осиротевших записей.
     *
     * @return array{removedIndex:int,removedFiles:int,kept:int}
     */
    function th_search_cover_cleanup(int $maxAgeSeconds = 0): array
    {
        $ttl = $maxAgeSeconds > 0 ? $maxAgeSeconds : (int) (
            min(
                720,
                max(24, (float) (getenv('TOURVISOR_SEARCH_CACHE_TTL_HOURS') ?: ($_ENV['TOURVISOR_SEARCH_CACHE_TTL_HOURS'] ?? 336)))
            ) * 3600
        );
        $now = time();
        $removedIndex = 0;
        $removedFiles = 0;
        $kept = 0;

        $res = th_search_cover_with_index_lock(static function () use ($ttl, $now, &$removedIndex, &$removedFiles, &$kept): array {
            $index = th_search_cover_index_load();
            $entries = is_array($index['entries'] ?? null) ? $index['entries'] : [];
            $activeFiles = [];

            foreach ($entries as $identity => $entry) {
                if (!is_array($entry)) {
                    unset($entries[$identity]);
                    $removedIndex++;
                    continue;
                }
                $mtime = (int) ($entry['mtime'] ?? 0);
                $fileName = trim((string) ($entry['file'] ?? ''));
                $file = $fileName !== '' ? (th_search_cover_cache_dir() . DIRECTORY_SEPARATOR . basename($fileName)) : th_search_cover_file_for_identity((string) $identity);
                $isFresh = $mtime > 0 && ($now - $mtime) < ($ttl * 2);
                if (!$isFresh || !is_file($file)) {
                    unset($entries[$identity]);
                    $removedIndex++;
                    if (is_file($file) && @unlink($file)) {
                        $removedFiles++;
                    }
                    continue;
                }
                $activeFiles[basename($file)] = true;
                $kept++;
            }

            $dir = th_search_cover_cache_dir();
            $files = glob($dir . DIRECTORY_SEPARATOR . 'cover_*.json') ?: [];
            foreach ($files as $path) {
                $bn = basename($path);
                if (isset($activeFiles[$bn])) {
                    continue;
                }
                $mtime = (int) (@filemtime($path) ?: 0);
                if ($mtime > 0 && ($now - $mtime) < 86400) {
                    continue;
                }
                if (@unlink($path)) {
                    $removedFiles++;
                }
            }

            $index['entries'] = $entries;
            th_search_cover_index_save($index);

            return ['removedIndex' => $removedIndex, 'removedFiles' => $removedFiles, 'kept' => $kept];
        });

        return is_array($res) ? $res : ['removedIndex' => 0, 'removedFiles' => 0, 'kept' => 0];
    }
}
