<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/tour_bookings_schema.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

$bookings = [];
$queryError = null;
$totalShown = 0;
$filterSource = isset($_GET['src']) ? strtolower(trim((string) $_GET['src'])) : 'all';
if (!in_array($filterSource, ['all', 'website', 'app'], true)) {
    $filterSource = 'all';
}
$counts = ['website' => 0, 'app' => 0, 'total' => 0];

if ($pdo) {
    try {
        ensureTourBookingsTable($pdo);
        $counts['website'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM tour_bookings WHERE COALESCE(NULLIF(TRIM(request_source), ''), 'website') = 'website'"
        )->fetchColumn();
        $counts['app'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM tour_bookings WHERE TRIM(COALESCE(request_source, '')) = 'app'"
        )->fetchColumn();
        $counts['total'] = $counts['website'] + $counts['app'];

        $limit = 200;
        $where = '';
        if ($filterSource === 'website') {
            $where = " WHERE COALESCE(NULLIF(TRIM(b.request_source), ''), 'website') = 'website' ";
        } elseif ($filterSource === 'app') {
            $where = " WHERE TRIM(COALESCE(b.request_source, '')) = 'app' ";
        }
        $stmt = $pdo->query(
            'SELECT b.*, u.name AS user_account_name, u.email AS user_account_email
             FROM tour_bookings b
             LEFT JOIN users u ON b.user_id = u.id
             ' . $where . '
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT ' . (int) $limit
        );
        if ($stmt) {
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalShown = count($bookings);
        }
    } catch (Throwable $e) {
        $queryError = $e->getMessage();
        error_log('[tour-bookings] ' . $e->getMessage());
    }
}

/**
 * @param array<string, mixed> $row
 */
function admin_booking_contact_name(array $row): string
{
    $g1 = trim((string) ($row['guest_first_name'] ?? ''));
    $g2 = trim((string) ($row['guest_last_name'] ?? ''));
    $guest = trim($g1 . ' ' . $g2);
    if ($guest !== '') {
        return $guest;
    }
    $acc = trim((string) ($row['user_account_name'] ?? ''));
    if ($acc !== '') {
        return $acc;
    }
    return '—';
}

/**
 * @param array<string, mixed> $row
 */
function admin_booking_email_display(array $row): string
{
    $e = trim((string) ($row['guest_email'] ?? ''));
    if ($e !== '') {
        return $e;
    }
    $e2 = trim((string) ($row['user_account_email'] ?? ''));
    return $e2 !== '' ? $e2 : '—';
}

/**
 * @param array<string, mixed> $row
 */
function admin_booking_phone_display(array $row): string
{
    $p = trim((string) ($row['guest_phone'] ?? ''));
    return $p !== '' ? $p : '—';
}

/**
 * @param array<string, mixed> $row
 */
function admin_booking_source_label(array $row): string
{
    $s = strtolower(trim((string) ($row['request_source'] ?? '')));
    if ($s === 'app') {
        return 'Приложение';
    }

    return 'Сайт';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Заявки на туры | Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --font-sans: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            font-family: var(--font-sans);
            background: linear-gradient(180deg, #f8fbff 0%, #eff5ff 45%, #fdfdff 100%);
            color: #1f2a44;
        }
        .heading-font { font-family: var(--font-sans); font-weight: 600; }
        .metric-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(59, 163, 255, 0.18);
            box-shadow: 0 22px 48px rgba(59, 163, 255, 0.18);
        }
    </style>
</head>
<body class="min-h-screen text-slate-900">
    <header class="backdrop-blur-md bg-white/90 border-b border-sky-100 sticky top-0 z-40">
        <div class="th-container mx-auto px-4 sm:px-6 py-3 sm:py-5 flex flex-wrap items-center justify-between gap-3 sm:gap-4">
            <a href="admin.php" class="flex items-center gap-2 sm:gap-3">
                <span class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center shadow-lg">
                    <i class="fas fa-plane text-white text-sm sm:text-base"></i>
                </span>
                <span class="heading-font text-lg sm:text-2xl font-bold text-sky-600">Заявки на туры</span>
            </a>
            <div class="flex items-center gap-2 sm:gap-3 text-xs sm:text-sm text-slate-600">
                <a href="admin.php" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-full border border-sky-100 text-slate-600 hover:bg-sky-50 transition">Панель</a>
                <a href="../scripts/logout.php" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white shadow-md hover:shadow-lg transition">Выход</a>
            </div>
        </div>
    </header>

    <main class="py-8 sm:py-12">
        <div class="th-container mx-auto px-4 sm:px-6">
            <div class="max-w-[100rem] mx-auto">
                <p class="text-slate-600 text-sm mb-4">Заявки, сохранённые в базе сайта (до 200 в списке): с сайта и из приложения. Дубликаты U-ON — по <span class="font-mono">uon_lead_id</span>.</p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                    <div class="rounded-xl border border-sky-100 bg-white px-4 py-3 shadow-sm">
                        <div class="text-xs uppercase tracking-wider text-slate-500 mb-1">Всего в базе</div>
                        <div class="heading-font text-2xl font-semibold text-slate-900"><?php echo (int) $counts['total']; ?></div>
                    </div>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 px-4 py-3 shadow-sm">
                        <div class="text-xs uppercase tracking-wider text-emerald-800 mb-1 flex items-center gap-1"><i class="fas fa-globe"></i> С сайта</div>
                        <div class="heading-font text-2xl font-semibold text-slate-900"><?php echo (int) $counts['website']; ?></div>
                    </div>
                    <div class="rounded-xl border border-violet-100 bg-violet-50/40 px-4 py-3 shadow-sm">
                        <div class="text-xs uppercase tracking-wider text-violet-800 mb-1 flex items-center gap-1"><i class="fas fa-mobile-screen-button"></i> Приложение</div>
                        <div class="heading-font text-2xl font-semibold text-slate-900"><?php echo (int) $counts['app']; ?></div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
                    <span class="text-slate-500 mr-1">Показать:</span>
                    <?php
                    $base = 'tour-bookings.php';
                    foreach (['all' => 'Все', 'website' => 'Только сайт', 'app' => 'Только приложение'] as $k => $lab) {
                        $active = $filterSource === $k;
                        $href = $k === 'all' ? $base : $base . '?src=' . rawurlencode($k);
                        $cls = $active
                            ? 'bg-sky-500 text-white border-sky-500'
                            : 'bg-white text-slate-700 border-sky-100 hover:bg-sky-50';
                        echo '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="inline-flex items-center rounded-full border px-3 py-1.5 font-medium transition ' . $cls . '">' . htmlspecialchars($lab, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
                    }
                    ?>
                </div>
                <?php if ($queryError): ?>
                    <div class="metric-card p-4 text-red-700 text-sm">Ошибка загрузки: <?php echo htmlspecialchars($queryError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                <?php elseif (empty($bookings)): ?>
                    <div class="metric-card p-6 text-slate-600">Заявок пока нет.</div>
                <?php else: ?>
                    <p class="text-xs text-slate-500 mb-2">Показано: <?php echo (int) $totalShown; ?></p>
                    <div class="metric-card p-4 sm:p-6 overflow-x-auto">
                        <table class="min-w-full text-sm border-collapse">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 border-b border-sky-100">
                                    <th class="py-2 pr-3 whitespace-nowrap">Дата</th>
                                    <th class="py-2 pr-3">Источник</th>
                                    <th class="py-2 pr-3">Имя / контакт</th>
                                    <th class="py-2 pr-3">Email</th>
                                    <th class="py-2 pr-3">Телефон</th>
                                    <th class="py-2 pr-3">Отель, страна</th>
                                    <th class="py-2 pr-2 whitespace-nowrap">Цена</th>
                                    <th class="py-2 pr-2">Ночи / питание</th>
                                    <th class="py-2 pr-2">Тип</th>
                                    <th class="py-2 pr-2">Статус</th>
                                    <th class="py-2 pr-2">U-ON / user</th>
                                    <th class="py-2 pr-0">Ссылка на тур</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                    <tr class="border-t border-sky-100 hover:bg-sky-50/80 align-top">
                                        <td class="py-2.5 pr-3 text-slate-600 whitespace-nowrap text-xs">
                                            <?php echo htmlspecialchars((string) ($b['created_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                        </td>
                                        <td class="py-2.5 pr-3 text-xs whitespace-nowrap">
                                            <?php
                                            $srcLab = admin_booking_source_label($b);
                                            $isApp = strtolower(trim((string) ($b['request_source'] ?? ''))) === 'app';
                                            $badgeCls = $isApp ? 'bg-violet-100 text-violet-800' : 'bg-emerald-100 text-emerald-800';
                                            ?>
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.7rem] font-semibold <?php echo $badgeCls; ?>"><?php echo htmlspecialchars($srcLab, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                        </td>
                                        <td class="py-2.5 pr-3 text-slate-800 text-xs max-w-[10rem]">
                                            <?php echo htmlspecialchars(admin_booking_contact_name($b), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                        </td>
                                        <td class="py-2.5 pr-3 text-xs break-all max-w-[9rem]">
                                            <?php
                                            $em = admin_booking_email_display($b);
                                            if ($em !== '—' && strpos($em, '@') !== false) {
                                                echo '<a class="text-sky-600 hover:underline" href="mailto:' . htmlspecialchars($em, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($em, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
                                            } else {
                                                echo htmlspecialchars($em, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                            }
                                            ?>
                                        </td>
                                        <td class="py-2.5 pr-3 text-xs"><?php echo htmlspecialchars(admin_booking_phone_display($b), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                        <td class="py-2.5 pr-3 text-xs max-w-[14rem]">
                                            <span class="font-medium text-slate-900"><?php echo htmlspecialchars((string) ($b['hotel_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                            <?php if (!empty($b['country'])): ?>
                                                <br><span class="text-slate-500"><?php echo htmlspecialchars((string) $b['country'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2.5 pr-2 text-xs whitespace-nowrap"><?php echo htmlspecialchars((string) ($b['price'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?: '—'; ?></td>
                                        <td class="py-2.5 pr-2 text-xs">
                                            <?php echo htmlspecialchars((string) ($b['nights'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?: '—'; ?>
                                            <?php if (!empty($b['meal'])): ?>
                                                <br><span class="text-slate-500"><?php echo htmlspecialchars((string) $b['meal'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2.5 pr-2 text-xs font-mono"><?php echo htmlspecialchars((string) ($b['booking_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?: '—'; ?></td>
                                        <td class="py-2.5 pr-2 text-xs"><?php echo htmlspecialchars((string) ($b['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?: '—'; ?></td>
                                        <td class="py-2.5 pr-2 text-xs">
                                            <?php if (!empty($b['uon_lead_id'])): ?>
                                                <span class="font-mono"><?php echo htmlspecialchars((string) $b['uon_lead_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                            <?php else: ?>
                                                <span class="text-slate-400">—</span>
                                            <?php endif; ?>
                                            <?php if (isset($b['user_id']) && $b['user_id'] !== null && (string) $b['user_id'] !== ''): ?>
                                                <br><span class="text-slate-500">id: <?php echo (int) $b['user_id']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2.5 pl-0 text-xs break-all max-w-[18rem]">
                                            <?php
                                            $tl = trim((string) ($b['tour_link'] ?? ''));
                                            if ($tl === '') {
                                                echo '—';
                                            } elseif (preg_match('#^https?://#i', $tl)) {
                                                $short = $tl;
                                                if (mb_strlen($short) > 72) {
                                                    $short = mb_substr($short, 0, 70, 'UTF-8') . '…';
                                                }
                                                echo '<a class="text-sky-600 hover:underline" href="' . htmlspecialchars($tl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($short, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
                                            } else {
                                                echo htmlspecialchars($tl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
