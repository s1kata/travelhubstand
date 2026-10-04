<?php
/**
 * Яндекс YML: ротация туров по странам → /feed-samara.yml, /feed-moscow.yml, /feed.yml
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/tourvisor_proxy_http_base.php';
require_once __DIR__ . '/../components/yandex_feed_sync.php';
require_once __DIR__ . '/../components/yandex_yml_rules_runner.php';
require_once __DIR__ . '/../components/yandex_yml_rotation.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

if (!$pdo) {
    die('Ошибка подключения к БД');
}

$message = '';
$messageType = '';
$initError = '';

try {
    yandex_feed_rotation_ensure_tables($pdo);
} catch (Throwable $e) {
    $initError = $e->getMessage();
}

/**
 * Полный справочник стран Tourvisor (+ направления из акций, если их нет в API, напр. Фукуок).
 *
 * @return list<array{id: int, name: string}>
 */
function th_yml_admin_fetch_countries(int $departureId): array
{
    $base = rtrim(get_tourvisor_proxy_http_base_url(), '/');
    $sep = strpos($base, '?') !== false ? '&' : '?';
    $dep = th_departure_normalize_id($departureId > 0 ? $departureId : 7);
    $byId = [];

    foreach (['type=countries&departureId=' . $dep, 'type=countries&departureId=' . $dep . '&departureCountryId=1'] as $q) {
        $raw = yandex_feed_http_get($base . $sep . $q, 45);
        if ($raw === null) {
            continue;
        }
        $j = json_decode($raw, true);
        if (!is_array($j) || empty($j['success']) || !is_array($j['data'] ?? null)) {
            continue;
        }
        foreach ($j['data'] as $c) {
            if (!is_array($c) || !isset($c['id'])) {
                continue;
            }
            $id = (int) $c['id'];
            $name = trim((string) ($c['russianName'] ?? $c['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $byId[$id] = ['id' => $id, 'name' => $name];
            }
        }
    }

    // Направления из акций (popular_countries) — чтобы Фукуок и т.п. были в списке
    foreach (yandex_feed_rotation_default_pool_seed() as $c) {
        $id = (int) ($c['id'] ?? 0);
        $name = trim((string) ($c['name'] ?? ''));
        if ($id > 0 && $name !== '' && !isset($byId[$id])) {
            $byId[$id] = ['id' => $id, 'name' => $name];
        }
    }

    $list = array_values($byId);
    usort($list, static function (array $a, array $b): int {
        return strcmp($a['name'], $b['name']);
    });

    return $list;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_rotation_settings') {
        try {
            $en = ((string) ($_POST['rotation_enabled'] ?? '0') === '1') ? 1 : 0;
            $fr = ((string) ($_POST['rotation_frozen'] ?? '0') === '1') ? 1 : 0;
            $pubSamara = ((string) ($_POST['publish_samara'] ?? '0') === '1') ? 1 : 0;
            $pubMoscow = ((string) ($_POST['publish_moscow'] ?? '0') === '1') ? 1 : 0;
            if ($pubSamara === 0 && $pubMoscow === 0) {
                $pubSamara = 1;
            }
            $cpb = max(1, min(10, (int) ($_POST['countries_per_batch'] ?? 3)));
            $tpc = max(1, min(20, (int) ($_POST['tours_per_country'] ?? 5)));
            $interval = max(1, min(30, (int) ($_POST['rotation_interval_days'] ?? 7)));
            $maxRep = max(0, min(10, (int) ($_POST['max_country_replacements'] ?? 3)));
            $minPub = max(1, min(50, (int) ($_POST['min_offers_publish'] ?? 8)));
            $hist = max(0, min(12, (int) ($_POST['history_exclude_batches'] ?? 3)));
            $nightsFrom = max(1, min(28, (int) ($_POST['nights_from'] ?? 6)));
            $nightsTo = max($nightsFrom, min(28, (int) ($_POST['nights_to'] ?? 14)));
            if (($nightsTo - $nightsFrom) > 10) {
                $nightsTo = $nightsFrom + 10;
            }
            $flightMode = strtolower(trim((string) ($_POST['flight_mode'] ?? 'any')));
            if (!in_array($flightMode, ['any', 'direct'], true)) {
                $flightMode = 'any';
            }
            $primaryCity = $pubSamara ? 'Самара' : 'Москва';
            $primaryDep = $pubSamara ? 7 : 1;
            $stmt = $pdo->prepare('UPDATE yandex_feed_rotation_settings SET enabled=?, frozen=?, source_departure_id=?, source_city=?, publish_samara=?, publish_moscow=?, countries_per_batch=?, tours_per_country=?, rotation_interval_days=?, max_country_replacements=?, min_offers_publish=?, history_exclude_batches=?, nights_from=?, nights_to=?, flight_mode=? WHERE id=1');
            $stmt->execute([$en, $fr, $primaryDep, $primaryCity, $pubSamara, $pubMoscow, $cpb, $tpc, $interval, $maxRep, $minPub, $hist, $nightsFrom, $nightsTo, $flightMode]);
            $message = 'Настройки сохранены.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = 'Не удалось сохранить: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'add_pool_country') {
        $cid = (int) ($_POST['pool_country_id'] ?? 0);
        $cname = trim((string) ($_POST['pool_country_name'] ?? ''));
        if ($cid <= 0 || $cname === '') {
            $message = 'Выберите страну из списка.';
            $messageType = 'error';
        } else {
            try {
                $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM yandex_feed_country_pool')->fetchColumn();
                $sort = $maxSort + 10;
                try {
                    $stmt = $pdo->prepare('INSERT INTO yandex_feed_country_pool (country_id, country_name, sort_order, enabled) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE country_name=VALUES(country_name), enabled=1');
                    $stmt->execute([$cid, $cname, $sort]);
                } catch (Throwable $e) {
                    $chk = $pdo->prepare('SELECT id FROM yandex_feed_country_pool WHERE country_id = ? LIMIT 1');
                    $chk->execute([$cid]);
                    if ($chk->fetchColumn()) {
                        $upd = $pdo->prepare('UPDATE yandex_feed_country_pool SET country_name=?, enabled=1 WHERE country_id=?');
                        $upd->execute([$cname, $cid]);
                    } else {
                        $ins = $pdo->prepare('INSERT INTO yandex_feed_country_pool (country_id, country_name, sort_order, enabled) VALUES (?,?,?,1)');
                        $ins->execute([$cid, $cname, $sort]);
                    }
                }
                $message = 'Страна «' . $cname . '» добавлена.';
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = 'Ошибка: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete_pool_country') {
        $pid = (int) ($_POST['pool_id'] ?? 0);
        if ($pid > 0) {
            $pdo->prepare('DELETE FROM yandex_feed_country_pool WHERE id = ?')->execute([$pid]);
            $message = 'Страна убрана из списка.';
            $messageType = 'success';
        }
    } elseif ($action === 'toggle_pool_country') {
        $pid = (int) ($_POST['pool_id'] ?? 0);
        $en = ((string) ($_POST['enabled'] ?? '0') === '1') ? 1 : 0;
        if ($pid > 0) {
            $pdo->prepare('UPDATE yandex_feed_country_pool SET enabled = ? WHERE id = ?')->execute([$en, $pid]);
            $message = $en ? 'Страна снова участвует в ротации.' : 'Страна временно выключена.';
            $messageType = 'success';
        }
    } elseif ($action === 'seed_rotation_pool') {
        $n = yandex_feed_rotation_seed_default_pool($pdo);
        $message = 'Добавлены страны из акций (' . $n . ').';
        $messageType = 'success';
    } elseif ($action === 'run_rotation') {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        try {
            // Включаем ротацию, если ещё выключена — иначе force бесполезен для нового пользователя
            $settingsNow = yandex_feed_rotation_get_settings($pdo);
            if (empty($settingsNow['enabled'])) {
                $pdo->exec('UPDATE yandex_feed_rotation_settings SET enabled = 1 WHERE id = 1');
            }
            $res = yandex_yml_rotation_run($pdo, false, true);
            if (!empty($res['lock_busy'])) {
                $message = 'Обновление уже идёт — подождите минуту и обновите страницу.';
                $messageType = 'error';
            } elseif (!empty($res['ok']) && !empty($res['rotated'])) {
                $message = 'Фиды обновлены. Туров в общем фиде: ' . (int) ($res['offers_written'] ?? 0) . '.';
                if (!empty($res['city_feeds']) && is_array($res['city_feeds'])) {
                    $message .= ' ' . implode('; ', $res['city_feeds']) . '.';
                }
                $messageType = 'success';
            } elseif (!empty($res['ok']) && !empty($res['skipped'])) {
                $message = 'Пропущено: ' . (string) ($res['message'] ?? '');
                $messageType = 'warning';
            } elseif (!empty($res['stale_kept'])) {
                $message = 'Мало подходящих туров — старые фиды сохранены. ' . (string) ($res['message'] ?? '');
                $messageType = 'warning';
            } else {
                $message = 'Не получилось обновить: ' . (string) ($res['message'] ?? implode('; ', $res['errors'] ?? []));
                $messageType = 'error';
            }
        } catch (Throwable $e) {
            $message = 'Ошибка: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$rotationSettings = yandex_feed_rotation_get_settings($pdo);
$rotationState = yandex_feed_rotation_get_state($pdo);
$rotationPool = yandex_feed_rotation_get_pool($pdo, false);
$rotationActive = yandex_feed_rotation_is_active($pdo);
$rotationEnvOk = yandex_feed_rotation_env_enabled();
$rotationIntervalDays = max(1, (int) ($rotationSettings['rotation_interval_days'] ?? 7));
$rotationDaysSince = yandex_feed_rotation_days_since(isset($rotationState['last_rotated_at']) ? (string) $rotationState['last_rotated_at'] : null);
$rotationDaysUntil = ($rotationDaysSince !== null && $rotationDaysSince < $rotationIntervalDays)
    ? ($rotationIntervalDays - $rotationDaysSince)
    : 0;

$planned = [];
$plannedRaw = (string) ($rotationState['planned_countries_json'] ?? '');
if ($plannedRaw !== '') {
    $decoded = json_decode($plannedRaw, true);
    if (is_array($decoded)) {
        $planned = $decoded;
    }
}

$siteBase = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? '')), '/');
if ($siteBase === '') {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $siteBase = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}
$siteBase = (string) preg_replace('#/frontend/?$#i', '', $siteBase);
$siteBase = rtrim($siteBase, '/');
$feedUrl = $siteBase . '/feed.yml';
$feedSamara = $siteBase . '/feed-samara.yml';
$feedMoscow = $siteBase . '/feed-moscow.yml';

$statusSamara = yandex_yml_rules_city_feed_status('samara');
$statusMoscow = yandex_yml_rules_city_feed_status('moscow');

$countriesForSelect = th_yml_admin_fetch_countries(7);
$promoCountryIds = [];
foreach (yandex_feed_rotation_default_pool_seed() as $pc) {
    $promoCountryIds[(int) $pc['id']] = true;
}
$poolCountryIds = [];
foreach ($rotationPool as $pr) {
    $poolCountryIds[(int) $pr['country_id']] = true;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Фид Яндекс | Travel Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
<header class="border-b bg-white">
    <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
        <a href="admin.php" class="text-sky-600 font-semibold"><i class="fas fa-arrow-left"></i> Админка</a>
        <span class="text-sm text-slate-500">Яндекс.Бизнес · фиды</span>
    </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-8 space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Фиды для Яндекса</h1>
        <p class="text-slate-600 mt-2 text-sm">
            Раз в неделю (или по кнопке) подбираются туры из вашего списка стран и обновляются ссылки для кабинета Яндекса.
            Ничего технического настраивать не нужно — только города, страны и кнопка «Обновить сейчас».
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="rounded-lg px-4 py-3 <?php echo $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : ($messageType === 'warning' ? 'bg-amber-50 text-amber-900 border border-amber-200' : 'bg-red-50 text-red-800 border border-red-200'); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($initError !== ''): ?>
        <div class="rounded-lg px-4 py-3 bg-red-50 text-red-800 border border-red-200 text-sm">
            Не удалось подготовить таблицы: <?php echo htmlspecialchars($initError); ?>
        </div>
    <?php endif; ?>

    <?php if (!$rotationEnvOk): ?>
        <div class="rounded-lg px-4 py-3 bg-amber-50 text-amber-900 border border-amber-200 text-sm">
            На сервере ротация отключена администратором (<code>YML_FEED_ROTATION_ENABLED=0</code>). Обратитесь к разработчику.
        </div>
    <?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-4">
        <h2 class="text-lg font-semibold text-slate-900">Ссылки для Яндекса</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <?php foreach ([$statusSamara, $statusMoscow] as $st):
                $url = $siteBase . (string) $st['public_url_path'];
                $ready = !empty($st['ready']);
                ?>
                <div class="rounded-xl border <?php echo $ready ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50'; ?> p-4">
                    <div class="font-semibold text-slate-900"><?php echo htmlspecialchars((string) $st['label']); ?></div>
                    <a class="mt-2 block text-sm text-sky-700 underline break-all" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($url); ?></a>
                    <ul class="mt-3 text-sm text-slate-600 space-y-1">
                        <li>Туров: <strong><?php echo (int) $st['offers_count']; ?></strong></li>
                        <li>Обновлён: <?php echo ($st['catalog_date'] ?? '') !== '' ? htmlspecialchars((string) $st['catalog_date']) : '—'; ?></li>
                        <li class="<?php echo $ready ? 'text-emerald-700' : 'text-amber-700'; ?>">
                            <?php echo $ready ? 'Готов к отдаче' : 'Пока пусто — нажмите «Обновить сейчас»'; ?>
                        </li>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-slate-500">Общий фид (если нужен): <a class="text-sky-700 underline break-all" href="<?php echo htmlspecialchars($feedUrl); ?>"><?php echo htmlspecialchars($feedUrl); ?></a></p>
    </section>

    <section class="rounded-xl border border-teal-200 bg-white shadow-sm p-5 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Ротация туров</h2>
                <p class="text-sm text-slate-600 mt-1">
                    <?php if ($rotationActive): ?>
                        Автообновление <span class="text-teal-700 font-medium">включено</span>.
                        <?php if ($rotationDaysUntil > 0): ?>
                            Следующая смена через <?php echo (int) $rotationDaysUntil; ?> дн.
                        <?php else: ?>
                            Можно обновить сейчас.
                        <?php endif; ?>
                    <?php else: ?>
                        Автообновление выключено — крон не будет менять фиды, пока вы не включите ниже.
                    <?php endif; ?>
                </p>
            </div>
            <form method="post" onsubmit="return confirm('Обновить фиды Самара и Москва сейчас? Это займёт 1–3 минуты.');">
                <input type="hidden" name="action" value="run_rotation">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-teal-500 to-emerald-500 text-white font-semibold shadow hover:opacity-95">
                    <i class="fas fa-sync-alt"></i> Обновить сейчас
                </button>
            </form>
        </div>

        <?php if (($rotationState['last_rotated_at'] ?? '') !== '' || ($rotationState['last_log'] ?? '') !== ''): ?>
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-sm space-y-1">
                <div>Последнее обновление: <strong><?php echo ($rotationState['last_rotated_at'] ?? '') !== '' ? htmlspecialchars((string) $rotationState['last_rotated_at']) : '—'; ?></strong></div>
                <div>Туров в общем фиде: <strong><?php echo (int) ($rotationState['last_offers_count'] ?? 0); ?></strong></div>
                <?php if ($planned !== []): ?>
                    <div>Текущий набор стран:
                        <?php
                        $names = [];
                        foreach ($planned as $p) {
                            if (is_array($p) && ($p['country_name'] ?? '') !== '') {
                                $names[] = (string) $p['country_name'];
                            }
                        }
                        echo htmlspecialchars(implode(', ', $names) ?: '—');
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4 border-t border-slate-100 pt-4">
            <input type="hidden" name="action" value="save_rotation_settings">

            <div class="flex flex-wrap gap-5">
                <label class="inline-flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="rotation_enabled" value="1" class="rounded" <?php echo !empty($rotationSettings['enabled']) ? 'checked' : ''; ?>>
                    Включить автообновление
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="rotation_frozen" value="1" class="rounded" <?php echo !empty($rotationSettings['frozen']) ? 'checked' : ''; ?>>
                    Пауза (не менять по расписанию)
                </label>
            </div>

            <div>
                <div class="text-sm font-medium text-slate-800 mb-2">Какие фиды обновлять</div>
                <div class="flex flex-wrap gap-5">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="publish_samara" value="1" class="rounded" <?php echo !isset($rotationSettings['publish_samara']) || !empty($rotationSettings['publish_samara']) ? 'checked' : ''; ?>>
                        Самара → <?php echo htmlspecialchars(basename($feedSamara)); ?>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="publish_moscow" value="1" class="rounded" <?php echo !isset($rotationSettings['publish_moscow']) || !empty($rotationSettings['publish_moscow']) ? 'checked' : ''; ?>>
                        Москва → <?php echo htmlspecialchars(basename($feedMoscow)); ?>
                    </label>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Стран в одной смене</label>
                    <input type="number" name="countries_per_batch" min="1" max="10" value="<?php echo (int) ($rotationSettings['countries_per_batch'] ?? 3); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2">
                    <p class="text-xs text-slate-500 mt-1">Например 3: Турция + Египет + Таиланд</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Туров на страну</label>
                    <input type="number" name="tours_per_country" min="1" max="20" value="<?php echo (int) ($rotationSettings['tours_per_country'] ?? 5); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ночей от</label>
                    <input type="number" name="nights_from" min="1" max="28" value="<?php echo (int) ($rotationSettings['nights_from'] ?? 6); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ночей до</label>
                    <input type="number" name="nights_to" min="1" max="28" value="<?php echo (int) ($rotationSettings['nights_to'] ?? 14); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2">
                    <p class="text-xs text-slate-500 mt-1">Диапазон не больше 10 ночей (лимит Tourvisor)</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Перелёт в выдаче фида</label>
                    <?php $fm = strtolower((string) ($rotationSettings['flight_mode'] ?? 'any')); ?>
                    <select name="flight_mode" class="w-full border border-slate-200 rounded-lg px-3 py-2">
                        <option value="any" <?php echo $fm !== 'direct' ? 'selected' : ''; ?>>Любой (прямой и с пересадкой)</option>
                        <option value="direct" <?php echo $fm === 'direct' ? 'selected' : ''; ?>>Только прямой</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Направления — в блоке «Страны в ротации» ниже. Для Вьетнама доп. фильтр прямых по-прежнему применяется.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Менять набор каждые (дней)</label>
                    <input type="number" name="rotation_interval_days" min="1" max="30" value="<?php echo (int) ($rotationSettings['rotation_interval_days'] ?? 7); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Если страна пустая — брать запасных</label>
                    <input type="number" name="max_country_replacements" min="0" max="10" value="<?php echo (int) ($rotationSettings['max_country_replacements'] ?? 3); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Минимум туров, чтобы сохранить фид</label>
                    <input type="number" name="min_offers_publish" min="1" max="50" value="<?php echo (int) ($rotationSettings['min_offers_publish'] ?? 8); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Не повторять те же туры (смен назад)</label>
                    <input type="number" name="history_exclude_batches" min="0" max="12" value="<?php echo (int) ($rotationSettings['history_exclude_batches'] ?? 3); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2">
                </div>
            </div>

            <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white font-medium hover:bg-teal-700">Сохранить настройки</button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Страны в ротации</h2>
                <p class="text-sm text-slate-600">Кнопка ниже подставляет те же направления, что на странице акций. В списке «Добавить» — полный справочник стран.</p>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="seed_rotation_pool">
                <button type="submit" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50">Добавить страны из акций</button>
            </form>
        </div>

        <?php if ($rotationPool === []): ?>
            <p class="text-sm text-slate-500">Список пуст. Нажмите «Добавить страны из акций» или выберите страну ниже.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden">
                <?php foreach ($rotationPool as $pr): ?>
                    <li class="flex items-center justify-between gap-3 px-4 py-3 bg-white <?php echo empty($pr['enabled']) ? 'opacity-50' : ''; ?>">
                        <div>
                            <div class="font-medium text-slate-900"><?php echo htmlspecialchars((string) $pr['country_name']); ?></div>
                            <div class="text-xs text-slate-500"><?php echo !empty($pr['enabled']) ? 'Участвует' : 'Выключена'; ?></div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <form method="post">
                                <input type="hidden" name="action" value="toggle_pool_country">
                                <input type="hidden" name="pool_id" value="<?php echo (int) $pr['id']; ?>">
                                <input type="hidden" name="enabled" value="<?php echo !empty($pr['enabled']) ? '0' : '1'; ?>">
                                <button type="submit" class="text-sm text-sky-700 underline"><?php echo !empty($pr['enabled']) ? 'Выкл.' : 'Вкл.'; ?></button>
                            </form>
                            <form method="post" onsubmit="return confirm('Убрать «<?php echo htmlspecialchars((string) $pr['country_name'], ENT_QUOTES); ?>» из списка?');">
                                <input type="hidden" name="action" value="delete_pool_country">
                                <input type="hidden" name="pool_id" value="<?php echo (int) $pr['id']; ?>">
                                <button type="submit" class="text-sm text-red-600 underline">Удалить</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" class="flex flex-wrap gap-3 items-end border-t border-slate-100 pt-4">
            <input type="hidden" name="action" value="add_pool_country">
            <div class="flex-1 min-w-[220px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">Добавить страну</label>
                <select name="pool_country_id" id="pool-country" required class="w-full border border-slate-200 rounded-lg px-3 py-2">
                    <option value="">— выберите —</option>
                    <?php
                    $promoOpts = [];
                    $otherOpts = [];
                    foreach ($countriesForSelect as $c) {
                        if (isset($poolCountryIds[(int) $c['id']])) {
                            continue;
                        }
                        if (isset($promoCountryIds[(int) $c['id']])) {
                            $promoOpts[] = $c;
                        } else {
                            $otherOpts[] = $c;
                        }
                    }
                    if ($promoOpts !== []): ?>
                        <optgroup label="Из акций">
                            <?php foreach ($promoOpts as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if ($otherOpts !== []): ?>
                        <optgroup label="Все страны">
                            <?php foreach ($otherOpts as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
                <input type="hidden" name="pool_country_name" id="pool-country-name" value="">
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg border border-teal-300 text-teal-800 font-medium hover:bg-teal-50">Добавить</button>
        </form>
    </section>
</main>
<script>
(function () {
    var sel = document.getElementById('pool-country');
    var hid = document.getElementById('pool-country-name');
    if (!sel || !hid) return;
    function sync() {
        var opt = sel.options[sel.selectedIndex];
        hid.value = opt ? (opt.getAttribute('data-name') || opt.textContent || '').trim() : '';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
</body>
</html>
