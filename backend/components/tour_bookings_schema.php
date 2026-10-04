<?php
declare(strict_types=1);

/**
 * Схема tour_bookings: создание таблицы, миграции guest, вспомогательные для INSERT.
 * Используется в backend/api/uon-booking.php и в админке.
 */

/**
 * Создаёт таблицу tour_bookings при отсутствии и доводит схему до поддержки гостевых заявок (user_id NULL).
 */
function ensureTourBookingsTable(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $tableExists = false;
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tour_bookings'");
        $tableExists = $stmt && $stmt->fetch();
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tour_bookings'");
        $tableExists = $stmt && $stmt->rowCount() > 0;
    }
    if (!$tableExists) {
        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE tour_bookings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                tour_link TEXT NOT NULL,
                hotel_name TEXT,
                country TEXT,
                price TEXT,
                nights TEXT,
                meal TEXT,
                uon_lead_id VARCHAR(64),
                booking_type VARCHAR(32),
                status VARCHAR(32) DEFAULT 'booked',
                guest_first_name TEXT,
                guest_last_name TEXT,
                guest_email TEXT,
                guest_phone TEXT,
                request_source TEXT NOT NULL DEFAULT 'website',
                idempotency_key TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE tour_bookings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                tour_link TEXT NOT NULL,
                hotel_name VARCHAR(512),
                country VARCHAR(255),
                price VARCHAR(64),
                nights VARCHAR(32),
                meal VARCHAR(64),
                uon_lead_id VARCHAR(64),
                booking_type VARCHAR(32),
                status VARCHAR(32) DEFAULT 'booked',
                guest_first_name VARCHAR(120) NULL,
                guest_last_name VARCHAR(120) NULL,
                guest_email VARCHAR(255) NULL,
                guest_phone VARCHAR(64) NULL,
                request_source VARCHAR(16) NOT NULL DEFAULT 'website',
                idempotency_key VARCHAR(128) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    }
    tourBookingsMigrateGuestSupport($pdo);
    tourBookingsMigrateRequestSource($pdo);
    tourBookingsMigrateIdempotencyKey($pdo);
}

/**
 * user_id для INSERT: гость — NULL (MySQL) или NULL/0 (SQLite, если колонка ещё NOT NULL).
 */
function tourBookingsResolveInsertUserId(PDO $pdo, bool $isGuestManager, int $userId): ?int
{
    if (!$isGuestManager) {
        return $userId > 0 ? $userId : null;
    }
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver !== 'sqlite') {
        return null;
    }
    try {
        $cols = $pdo->query('PRAGMA table_info(tour_bookings)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if (strtolower((string) ($c['name'] ?? '')) === 'user_id') {
                $nn = isset($c['notnull']) ? (int) $c['notnull'] : 0;

                return $nn === 1 ? 0 : null;
            }
        }
    } catch (Throwable $e) {
    }

    return null;
}

function tourBookingsMigrateGuestSupport(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    try {
        if ($driver === 'sqlite') {
            $cols = $pdo->query('PRAGMA table_info(tour_bookings)')->fetchAll(PDO::FETCH_ASSOC);
            $names = [];
            foreach ($cols as $c) {
                $names[strtolower((string) ($c['name'] ?? ''))] = true;
            }
            if (empty($names['guest_first_name'])) {
                $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN guest_first_name TEXT');
            }
            if (empty($names['guest_last_name'])) {
                $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN guest_last_name TEXT');
            }
            if (empty($names['guest_email'])) {
                $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN guest_email TEXT');
            }
            if (empty($names['guest_phone'])) {
                $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN guest_phone TEXT');
            }

            return;
        }
        $stmt = $pdo->query('SHOW COLUMNS FROM tour_bookings');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $names = [];
        foreach ($rows as $r) {
            $names[strtolower((string) ($r['Field'] ?? ''))] = true;
        }
        if (empty($names['guest_first_name'])) {
            $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN guest_first_name VARCHAR(120) NULL');
        }
        if (empty($names['guest_last_name'])) {
            $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN guest_last_name VARCHAR(120) NULL');
        }
        if (empty($names['guest_email'])) {
            $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN guest_email VARCHAR(255) NULL');
        }
        if (empty($names['guest_phone'])) {
            $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN guest_phone VARCHAR(64) NULL');
        }
        $pdo->exec('ALTER TABLE tour_bookings MODIFY user_id INT NULL');
    } catch (Throwable $e) {
        error_log('[tour_bookings_schema] migrate guest columns: ' . $e->getMessage());
    }
}

/**
 * Источник заявки: website | app (админка, раздельная статистика).
 */
function tourBookingsMigrateRequestSource(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    try {
        if ($driver === 'sqlite') {
            $cols = $pdo->query('PRAGMA table_info(tour_bookings)')->fetchAll(PDO::FETCH_ASSOC);
            $names = [];
            foreach ($cols as $c) {
                $names[strtolower((string) ($c['name'] ?? ''))] = true;
            }
            if (empty($names['request_source'])) {
                $pdo->exec("ALTER TABLE tour_bookings ADD COLUMN request_source TEXT NOT NULL DEFAULT 'website'");
            }
            $pdo->exec("UPDATE tour_bookings SET request_source = 'website' WHERE request_source IS NULL OR TRIM(request_source) = ''");

            return;
        }
        $stmt = $pdo->query('SHOW COLUMNS FROM tour_bookings');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $names = [];
        foreach ($rows as $r) {
            $names[strtolower((string) ($r['Field'] ?? ''))] = true;
        }
        if (empty($names['request_source'])) {
            $pdo->exec("ALTER TABLE tour_bookings ADD COLUMN request_source VARCHAR(16) NOT NULL DEFAULT 'website'");
        }
        $pdo->exec("UPDATE tour_bookings SET request_source = 'website' WHERE request_source IS NULL OR request_source = ''");
    } catch (Throwable $e) {
        error_log('[tour_bookings_schema] migrate request_source: ' . $e->getMessage());
    }
}

/**
 * Ключ идемпотентности для заявок из приложения (дедуп при повторной отправке).
 */
function tourBookingsMigrateIdempotencyKey(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    try {
        if ($driver === 'sqlite') {
            $cols = $pdo->query('PRAGMA table_info(tour_bookings)')->fetchAll(PDO::FETCH_ASSOC);
            $names = [];
            foreach ($cols as $c) {
                $names[strtolower((string) ($c['name'] ?? ''))] = true;
            }
            if (empty($names['idempotency_key'])) {
                $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN idempotency_key TEXT');
            }

            return;
        }
        $stmt = $pdo->query('SHOW COLUMNS FROM tour_bookings');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $names = [];
        foreach ($rows as $r) {
            $names[strtolower((string) ($r['Field'] ?? ''))] = true;
        }
        if (empty($names['idempotency_key'])) {
            $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN idempotency_key VARCHAR(128) NULL');
        }
    } catch (Throwable $e) {
        error_log('[tour_bookings_schema] migrate idempotency_key: ' . $e->getMessage());
    }
}

/**
 * Лог заявки из мобильного приложения (после успеха U-ON), user_id = NULL, гость в guest_*.
 *
 * @param array<string, mixed> $payload полная нагрузка с idempotencyKey
 */
function tourBookingsInsertAppSubmission(PDO $pdo, array $payload, ?string $uonLeadId): void
{
    $idem = trim((string) ($payload['idempotencyKey'] ?? ''));
    if ($idem !== '') {
        $idemSafe = function_exists('mb_substr') ? mb_substr($idem, 0, 120, 'UTF-8') : substr($idem, 0, 120);
        $chk = $pdo->prepare('SELECT id FROM tour_bookings WHERE request_source = ? AND idempotency_key = ? LIMIT 1');
        $chk->execute(['app', $idemSafe]);
        if ($chk->fetch()) {
            return;
        }
    }

    $ts = is_array($payload['tourSnapshot'] ?? null) ? $payload['tourSnapshot'] : [];
    $ci = is_array($payload['contactInfo'] ?? null) ? $payload['contactInfo'] : [];
    $tourLink = trim((string) ($ts['tourPackageUrl'] ?? ''));
    if ($tourLink === '') {
        $tourLink = 'app://crm/' . ($idem !== '' ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $idem) : 'booking');
    }

    $name = trim((string) ($ci['name'] ?? ''));
    $parts = preg_split('/\s+/u', $name, 2, PREG_SPLIT_NO_EMPTY);
    $gf = $parts[0] ?? '';
    $gl = $parts[1] ?? '';
    $ge = trim((string) ($ci['email'] ?? ''));
    $gp = trim((string) ($ci['phone'] ?? ''));

    $hotel = trim((string) ($ts['hotelName'] ?? ''));
    $country = trim((string) ($ts['countryName'] ?? ''));
    $priceStr = (string) (int) ($payload['totalPrice'] ?? 0);
    $nightsStr = (string) (int) ($payload['nights'] ?? 0);
    $meal = '';
    $bookingType = (($payload['type'] ?? '') === 'hotel') ? 'app_hotel' : 'app_tour';
    $status = 'booked';

    $idemDb = $idem !== '' ? (function_exists('mb_substr') ? mb_substr($idem, 0, 120, 'UTF-8') : substr($idem, 0, 120)) : null;

    $ins = $pdo->prepare(
        'INSERT INTO tour_bookings (user_id, tour_link, hotel_name, country, price, nights, meal, uon_lead_id, booking_type, status, guest_first_name, guest_last_name, guest_email, guest_phone, request_source, idempotency_key) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $tourLink,
        $hotel,
        $country,
        $priceStr,
        $nightsStr,
        $meal,
        $uonLeadId !== null && $uonLeadId !== '' ? $uonLeadId : null,
        $bookingType,
        $status,
        $gf !== '' ? $gf : null,
        $gl !== '' ? $gl : null,
        $ge !== '' ? $ge : null,
        $gp !== '' ? $gp : null,
        'app',
        $idemDb,
    ]);
}
