<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';

/**
 * Fallback data in case the SQL database is unavailable (keeps the UI functional).
 */
function fallbackTours(): array
{
    return [
        [
            'id' => 1,
            'slug' => 'seychelles-hilton-labriz',
            'title' => 'Hilton Seychelles Labriz Resort & Spa',
            'subtitle' => 'Вилла с личным батлером и приватным пляжем',
            'description' => 'Единственный отель на вулканическом острове Силуэт. Батлер 24/7 и гастрономические сеты.',
            'image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1600&q=80',
            'price' => 365000,
            'rating' => 4.9,
            'reviews' => 186,
            'destination' => 'seychelles',
            'duration' => '7-14 ночей',
            'badge' => 'Limited',
            'tags' => ['Seychelles', 'Private Island'],
            'spotlight_headline' => 'Private Island. Батлер & Chef',
            'spotlight_text' => 'Перелёт бизнес-классом, батлер, welcome-ритуал и закрытая лагуна только для гостей Travel Hub.',
            'spotlight_price_label' => 'от 365 000 ₽',
            'spotlight_price_old' => 415000,
        ],
        [
            'id' => 2,
            'slug' => 'seychelles-cheval-blanc',
            'title' => 'Cheval Blanc Seychelles',
            'subtitle' => 'Signature Maison от Louis Vuitton',
            'description' => '50 вилл с бассейнами, мастер-классы от шефов и приватные экскурсии.',
            'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80',
            'price' => 540000,
            'rating' => 5.0,
            'reviews' => 94,
            'destination' => 'seychelles',
            'duration' => '5-12 ночей',
            'badge' => 'New',
            'tags' => ['Seychelles'],
            'spotlight_headline' => 'Cheval Blanc Collection',
            'spotlight_text' => 'Персональные консьержи, shopping-сессии и тревел-фотограф в комплекте.',
            'spotlight_price_label' => 'от 540 000 ₽',
            'spotlight_price_old' => 610000,
        ],
    ];
}

/**
 * Normalize numeric values for JSON output.
 */
function normalizePrice(?string $value): ?float
{
    if ($value === null) {
        return null;
    }
    return (float) $value;
}

// Request parameters
$page = max((int) ($_GET['page'] ?? 1), 1);
$perPage = (int) ($_GET['per_page'] ?? 12);
$perPage = max(1, min($perPage, 48));
$filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
$context = strtolower(trim((string) ($_GET['context'] ?? 'list')));

// Destination whitelist to prevent SQL injection in filters
$allowedDestinations = ['all', 'seychelles', 'maldives', 'turkey', 'uae', 'egypt', 'thailand', 'spain', 'italy', 'greece', 'cyprus', 'tunisia', 'morocco', 'bali', 'russia', 'china', 'vietnam', 'india', 'indonesia', 'philippines', 'sri-lanka', 'mauritius', 'oman', 'tanzania', 'montenegro', 'abkhazia', 'armenia', 'bahrain', 'jordan', 'qatar', 'cuba', 'venezuela'];
if (!in_array($filter, $allowedDestinations, true)) {
    $filter = 'all';
}

// Default response in case of DB outage
$response = [
    'tours' => fallbackTours(),
    'hasMore' => false,
    'total' => count(fallbackTours()),
    'page' => 1,
    'context' => $context,
    'source' => 'fallback',
];

if (!$pdo) {
    echo json_encode($response);
    exit;
}

try {
    $conditions = [];
    $params = [];

    if ($filter !== 'all') {
        $conditions[] = 'destination = :destination';
        $params[':destination'] = $filter;
    }

    // Context-specific ordering
    $orderBy = 'created_at DESC';
    if ($context === 'featured') {
        $conditions[] = 'feature_rank IS NOT NULL';
        $orderBy = 'feature_rank ASC';
        $perPage = min($perPage, 8);
        $page = 1;
    } elseif ($context === 'spotlight') {
        $conditions[] = 'spotlight_rank IS NOT NULL';
        $orderBy = 'spotlight_rank ASC';
        $perPage = min($perPage, 4);
        $page = 1;
    } else {
        $orderBy = 'feature_rank IS NULL, feature_rank ASC, created_at DESC';
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Count total items for pagination
    $countSql = "SELECT COUNT(*) AS total FROM tours $whereClause";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT
            id,
            slug,
            title,
            subtitle,
            destination,
            description,
            image_url,
            price_from,
            nights_min,
            nights_max,
            rating,
            reviews_count,
            badge,
            tag_line,
            spotlight_headline,
            spotlight_text,
            spotlight_price_label,
            spotlight_price_old
        FROM tours
        $whereClause
        ORDER BY $orderBy
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $tourIds = array_column($rows, 'id');

    // Attach tags (if present)
    $tagsByTour = [];
    if (!empty($tourIds)) {
        $placeholders = implode(',', array_fill(0, count($tourIds), '?'));
        $tagStmt = $pdo->prepare("SELECT tour_id, label FROM tour_tags WHERE tour_id IN ($placeholders)");
        foreach ($tourIds as $index => $tourId) {
            $tagStmt->bindValue($index + 1, $tourId, PDO::PARAM_INT);
        }
        $tagStmt->execute();
        foreach ($tagStmt->fetchAll() as $tagRow) {
            $tagsByTour[$tagRow['tour_id']][] = $tagRow['label'];
        }
    }

    $tours = array_map(static function (array $tour) use ($tagsByTour) {
        $nightsMin = $tour['nights_min'] ? (int) $tour['nights_min'] : null;
        $nightsMax = $tour['nights_max'] ? (int) $tour['nights_max'] : null;
        $duration = null;
        if ($nightsMin && $nightsMax) {
            $duration = $nightsMin === $nightsMax ? sprintf('%d ночей', $nightsMin) : sprintf('%d-%d ночей', $nightsMin, $nightsMax);
        } elseif ($nightsMin) {
            $duration = sprintf('от %d ночей', $nightsMin);
        }

        return [
            'id' => (int) $tour['id'],
            'slug' => $tour['slug'],
            'title' => $tour['title'],
            'subtitle' => $tour['subtitle'],
            'description' => $tour['description'],
            'image' => $tour['image_url'],
            'price' => normalizePrice($tour['price_from']),
            'rating' => $tour['rating'] !== null ? (float) $tour['rating'] : null,
            'reviews' => $tour['reviews_count'] !== null ? (int) $tour['reviews_count'] : null,
            'destination' => $tour['destination'],
            'duration' => $duration,
            'badge' => $tour['badge'],
            'tagLine' => $tour['tag_line'],
            'tags' => $tagsByTour[$tour['id']] ?? [],
            'spotlight' => [
                'headline' => $tour['spotlight_headline'],
                'text' => $tour['spotlight_text'],
                'priceLabel' => $tour['spotlight_price_label'],
                'priceOld' => normalizePrice($tour['spotlight_price_old']),
            ],
        ];
    }, $rows);

    $hasMore = ($offset + $perPage) < $total;

    $response = [
        'tours' => $tours,
        'hasMore' => $hasMore,
        'total' => $total,
        'page' => $page,
        'context' => $context,
        'source' => 'database',
    ];
} catch (Throwable $e) {
    error_log('[API][tours] ' . $e->getMessage());
    $response['error'] = 'Database error';
    $response['source'] = 'fallback';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>