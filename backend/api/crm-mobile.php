<?php
/**
 * CRM (U-ON) proxy для мобильного приложения: ключ UON_API_KEY на сервере (устаревшее SOTA_API_KEY читается как fallback).
 * Маршруты: /api/crm/submit-booking (POST → U-ON lead/create), user-departure-documents, client-bookings, bonus-balance (GET).
 * Authorization: Bearer <Firebase ID token> — проверяется локально; в U-ON уходит только ключ из .env.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/firebase_id_token_verify.php';
require_once __DIR__ . '/../components/security_helper.php';
require_once __DIR__ . '/../components/tour_bookings_schema.php';
require_once __DIR__ . '/crm_submit_booking_core.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$pathRaw = trim((string) ($_GET['path'] ?? ''));
if ($pathRaw === '' && !empty($_SERVER['PATH_INFO'])) {
    $pathRaw = trim((string) $_SERVER['PATH_INFO'], '/');
}
$pathRaw = ltrim($pathRaw, '/');

if ($pathRaw === '') {
    crm_json_exit(404, ['error' => 'CRM path required']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($pathRaw === 'submit-booking') {
    if ($method !== 'POST') {
        crm_json_exit(405, ['error' => 'Method not allowed']);
    }
    crm_handle_submit_booking();
    exit;
}

if ($method !== 'GET') {
    crm_json_exit(405, ['error' => 'Method not allowed']);
}

if ($pathRaw === 'user-departure-documents') {
    crm_handle_read('departures');
    exit;
}
if ($pathRaw === 'client-bookings') {
    crm_handle_read('bookings');
    exit;
}
if ($pathRaw === 'bonus-balance') {
    crm_handle_read('bonus');
    exit;
}

crm_json_exit(404, ['error' => 'Unknown CRM route']);

// --- helpers ---

function crm_json_exit(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function crm_get_bearer_token(): ?string
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (!preg_match('/^\s*Bearer\s+(.+)\s*$/i', $authHeader, $m)) {
        return null;
    }
    $t = trim($m[1]);

    return $t === '' ? null : $t;
}

function crm_get_uon_api_key(): string
{
    $k = trim((string) (getenv('UON_API_KEY') ?: ($_ENV['UON_API_KEY'] ?? '')));
    if ($k === '') {
        $k = trim((string) (getenv('SOTA_API_KEY') ?: ($_ENV['SOTA_API_KEY'] ?? '')));
    }

    return $k;
}

function crm_uon_base_url(): string
{
    $b = trim((string) (getenv('SOTA_UON_API_BASE') ?: ($_ENV['SOTA_UON_API_BASE'] ?? '')));
    if ($b === '') {
        $b = 'https://api.u-on.ru';
    }

    return rtrim($b, '/');
}

/**
 * @return array{success: bool, data?: mixed, error?: string}
 */
function crm_uon_request(string $endpoint, string $method = 'GET', ?string $jsonBody = null): array
{
    $key = crm_get_uon_api_key();
    if ($key === '') {
        return ['success' => false, 'error' => 'UON_API_KEY is not configured'];
    }
    $path = ltrim($endpoint, '/');
    $url = crm_uon_base_url() . '/' . rawurlencode($key) . '/' . $path;

    $headers = ['Accept: application/json'];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $jsonBody ?? '{}';
    } elseif ($method !== 'GET') {
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        if ($jsonBody !== null) {
            $opts[CURLOPT_POSTFIELDS] = $jsonBody;
        }
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0) {
        return ['success' => false, 'error' => $err ?: 'Network error'];
    }

    $data = null;
    if (is_string($body) && $body !== '') {
        $data = json_decode($body, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $data = $body;
        }
    }

    if ($status < 200 || $status >= 300) {
        $msg = 'HTTP ' . $status;
        if (is_array($data)) {
            $msg = (string) ($data['message'] ?? $data['error'] ?? $msg);
        } elseif (is_string($data) && $data !== '') {
            $msg = $data;
        }

        return ['success' => false, 'error' => $msg];
    }

    return ['success' => true, 'data' => $data];
}

function crm_normalize_phone_digits(string $p): string
{
    return preg_replace('/\D/', '', $p) ?? '';
}

/**
 * @param array<string, mixed> $decoded Firebase payload
 */
function crm_assert_email_phone_allowed(array $decoded, ?string $email, ?string $phone): ?string
{
    if ($email !== null && $email !== '') {
        $tokenEmail = isset($decoded['email']) ? strtolower((string) $decoded['email']) : '';
        if ($tokenEmail !== '' && strtolower($email) !== $tokenEmail) {
            return 'Email does not match signed-in user';
        }
    }
    $tokenPhone = isset($decoded['phone_number']) ? crm_normalize_phone_digits((string) $decoded['phone_number']) : '';
    $qPhone = $phone !== null ? crm_normalize_phone_digits($phone) : '';
    if ($qPhone !== '' && $tokenPhone !== '' && $qPhone !== $tokenPhone) {
        return 'Phone does not match signed-in user';
    }

    return null;
}

/**
 * @param array<string, mixed> $r
 * @return array<string, mixed>
 */
function crm_map_uon_request_to_booking(array $r): array
{
    $name = trim(implode(' ', array_filter([
        $r['client_surname'] ?? null,
        $r['client_name'] ?? null,
        $r['client_sname'] ?? null,
    ])));
    if ($name === '') {
        $name = '—';
    }
    $services = $r['services'] ?? [];
    $s0 = is_array($services) && isset($services[0]) && is_array($services[0]) ? $services[0] : [];

    return [
        'id' => (string) ($r['id'] ?? $r['id_system'] ?? ''),
        'bookingNumber' => $r['id_internal'] ?? $r['id_system'] ?? (string) ($r['id'] ?? ''),
        'clientName' => $name,
        'clientPhone' => (string) ($r['client_phone'] ?? $r['client_phone_mobile'] ?? ''),
        'clientEmail' => (string) ($r['client_email'] ?? ''),
        'tourName' => (string) ($s0['hotel'] ?? $s0['description'] ?? '—'),
        'departureDate' => (string) ($r['date_begin'] ?? ''),
        'returnDate' => (string) ($r['date_end'] ?? ''),
        'participants' => 0,
        'status' => (string) ($r['status'] ?? '—'),
        'totalPrice' => $r['calc_price'] ?? 0,
        'currency' => (string) ($s0['currency'] ?? 'RUB'),
        'documents' => [],
        'createdAt' => (string) ($r['dat'] ?? $r['created_at'] ?? ''),
        'updatedAt' => (string) ($r['dat_updated'] ?? ''),
    ];
}

function crm_extract_file_url(array $file): string
{
    foreach (['url', 'link', 'file_url', 'file_link', 'src', 'path'] as $k) {
        if (!empty($file[$k])) {
            return (string) $file[$k];
        }
    }

    return '';
}

function crm_detect_document_type(string $fileName): string
{
    $lower = mb_strtolower($fileName);
    if (str_contains($lower, 'ваучер') || str_contains($lower, 'voucher')) {
        return 'voucher';
    }
    if (
        str_contains($lower, 'билет') || str_contains($lower, 'ticket')
        || str_contains($lower, 'авиа') || str_contains($lower, 'avia')
    ) {
        return 'ticket';
    }
    if (str_contains($lower, 'страхов') || str_contains($lower, 'insurance')) {
        return 'insurance';
    }
    if (str_contains($lower, 'виза') || str_contains($lower, 'visa')) {
        return 'visa';
    }
    if (str_contains($lower, 'паспорт') || str_contains($lower, 'passport')) {
        return 'other';
    }

    return 'other';
}

/**
 * @return array{success: bool, data?: array<int, mixed>, error?: string}
 */
function crm_get_bookings_by_client(?string $clientEmail, ?string $clientPhone): array
{
    if (($clientEmail === null || $clientEmail === '') && ($clientPhone === null || $clientPhone === '')) {
        return ['success' => false, 'error' => 'Укажите email или телефон клиента', 'data' => []];
    }

    $clientId = null;
    if ($clientEmail !== null && $clientEmail !== '') {
        $emailRes = crm_uon_request('user/email.json', 'POST', json_encode(['email' => $clientEmail], JSON_UNESCAPED_UNICODE));
        if ($emailRes['success'] && is_array($emailRes['data'] ?? null) && isset($emailRes['data']['id'])) {
            $clientId = $emailRes['data']['id'];
        }
    }
    if ($clientId === null && $clientPhone !== null && $clientPhone !== '') {
        $digits = crm_normalize_phone_digits($clientPhone);
        $phoneRes = crm_uon_request('user/phone/' . rawurlencode($digits) . '.json', 'GET');
        if ($phoneRes['success'] && is_array($phoneRes['data'] ?? null) && isset($phoneRes['data']['id'])) {
            $clientId = $phoneRes['data']['id'];
        }
    }
    if ($clientId === null) {
        return ['success' => true, 'data' => []];
    }

    $response = crm_uon_request('request-by-client/' . rawurlencode((string) $clientId) . '/1.json', 'GET');
    if (!$response['success']) {
        return ['success' => false, 'error' => $response['error'] ?? 'CRM error', 'data' => []];
    }
    $list = is_array($response['data'] ?? null) ? $response['data'] : [];
    $out = [];
    foreach ($list as $item) {
        if (is_array($item)) {
            $out[] = crm_map_uon_request_to_booking($item);
        }
    }

    return ['success' => true, 'data' => $out];
}

/**
 * @return array{success: bool, data?: array<int, mixed>, error?: string}
 */
function crm_get_departure_documents(string $bookingId): array
{
    $requestResponse = crm_uon_request('request/' . rawurlencode($bookingId) . '.json', 'GET');
    if (!$requestResponse['success'] || !is_array($requestResponse['data'] ?? null)) {
        return ['success' => false, 'error' => $requestResponse['error'] ?? 'Failed to fetch request data', 'data' => []];
    }
    $reqData = $requestResponse['data'];
    $files = is_array($reqData['files'] ?? null) ? $reqData['files'] : [];
    $documents = [];
    foreach ($files as $index => $file) {
        if (!is_array($file)) {
            continue;
        }
        $fn = (string) ($file['name'] ?? $file['file_name'] ?? $file['filename'] ?? 'document_' . $index);
        $documents[] = [
            'id' => (string) ($file['id'] ?? $file['file_id'] ?? 'file_' . $index),
            'bookingId' => $bookingId,
            'documentType' => crm_detect_document_type($fn),
            'fileName' => $fn,
            'fileUrl' => crm_extract_file_url($file),
            'mimeType' => (string) ($file['mime_type'] ?? $file['type'] ?? $file['mime'] ?? 'application/pdf'),
            'fileSize' => (int) ($file['size'] ?? $file['file_size'] ?? 0),
            'uploadedAt' => (string) ($file['date'] ?? $file['created_at'] ?? $file['uploaded_at'] ?? gmdate('c')),
            'description' => (string) ($file['description'] ?? $file['file_note'] ?? $file['note'] ?? ''),
        ];
    }

    return ['success' => true, 'data' => $documents];
}

/**
 * @return array{success: bool, data?: array<int, mixed>, error?: string}
 */
function crm_get_user_departure_documents(?string $email, ?string $phone): array
{
    $bookingsResponse = crm_get_bookings_by_client($email, $phone);
    if (!$bookingsResponse['success'] || !isset($bookingsResponse['data'])) {
        return $bookingsResponse;
    }
    $result = [];
    foreach ($bookingsResponse['data'] as $booking) {
        if (!is_array($booking)) {
            continue;
        }
        $bid = (string) ($booking['id'] ?? '');
        if ($bid === '') {
            continue;
        }
        $documentsResponse = crm_get_departure_documents($bid);
        if ($documentsResponse['success'] && !empty($documentsResponse['data'])) {
            $result[] = ['booking' => $booking, 'documents' => $documentsResponse['data']];
        }
    }

    return ['success' => true, 'data' => $result];
}

/**
 * @return array{success: bool, data?: mixed, error?: string}
 */
function crm_get_client_id_from_email_phone(?string $email, ?string $phone): array
{
    if ($email !== null && trim($email) !== '') {
        $res = crm_uon_request('user/email.json', 'POST', json_encode(['email' => trim($email)], JSON_UNESCAPED_UNICODE));
        if ($res['success'] && is_array($res['data'] ?? null) && isset($res['data']['id'])) {
            return ['success' => true, 'id' => $res['data']['id']];
        }
    }
    if ($phone !== null && $phone !== '') {
        $digits = crm_normalize_phone_digits($phone);
        if ($digits !== '') {
            $res = crm_uon_request('user/phone/' . rawurlencode($digits) . '.json', 'GET');
            if ($res['success'] && is_array($res['data'] ?? null) && isset($res['data']['id'])) {
                return ['success' => true, 'id' => $res['data']['id']];
            }
        }
    }

    return ['success' => true, 'id' => null];
}

function crm_handle_submit_booking(): void
{
    if (security_rate_limit_exceeded('crm_submit_booking', 40, 60)) {
        crm_json_exit(429, ['error' => 'Too many requests']);
    }

    $token = crm_get_bearer_token();
    if ($token === null) {
        crm_json_exit(401, ['error' => 'Unauthorized: Bearer token required']);
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw !== false ? $raw : '', true);
    if (!is_array($body)) {
        crm_json_exit(400, ['error' => 'Invalid JSON body']);
    }

    $idempotencyKey = isset($body['idempotencyKey']) ? trim((string) $body['idempotencyKey']) : '';
    $payload = $body['payload'] ?? null;
    if ($idempotencyKey === '' || !is_array($payload)) {
        crm_json_exit(400, ['error' => 'Required: idempotencyKey, payload']);
    }

    $userId = isset($payload['userId']) ? trim((string) $payload['userId']) : '';
    if ($userId === '') {
        crm_json_exit(400, ['error' => 'payload.userId required']);
    }
    $decoded = firebase_id_token_parse_and_verify($token);
    if ($decoded === null) {
        crm_json_exit(401, ['error' => 'Invalid or expired auth token']);
    }
    if ((string) ($decoded['sub'] ?? '') !== $userId) {
        crm_json_exit(403, ['error' => 'Forbidden: userId mismatch']);
    }

    if (crm_get_uon_api_key() === '') {
        crm_json_exit(503, ['error' => 'CRM backend is not configured (UON_API_KEY)']);
    }

    $merged = array_merge($payload, ['idempotencyKey' => $idempotencyKey]);
    try {
        $requestBody = crm_build_request_create_body($merged);
    } catch (InvalidArgumentException $e) {
        crm_json_exit(400, ['error' => $e->getMessage()]);
    }

    $jsonOut = json_encode($requestBody, JSON_UNESCAPED_UNICODE);
    $response = crm_uon_request('lead/create.json', 'POST', $jsonOut);

    if (!$response['success'] || !isset($response['data'])) {
        crm_json_exit(502, [
            'success' => false,
            'error' => $response['error'] ?? 'CRM request failed',
        ]);
    }

    $data = is_array($response['data']) ? $response['data'] : [];
    $id = $data['id'] ?? $data['id_system'] ?? null;
    $idStr = $id !== null ? (string) $id : null;
    $bookingNumber = null;
    if (array_key_exists('id_internal', $data) && $data['id_internal'] !== null) {
        $bookingNumber = (string) $data['id_internal'];
    } elseif ($idStr !== null) {
        $bookingNumber = $idStr;
    }
    $out = [
        'success' => true,
        'data' => array_filter(
            [
                'id' => $idStr,
                'requestId' => $idStr,
                'bookingNumber' => $bookingNumber,
            ],
            static fn ($v) => $v !== null
        ),
    ];

    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            ensureTourBookingsTable($pdo);
            tourBookingsInsertAppSubmission($pdo, $merged, $idStr);
        } catch (Throwable $e) {
            error_log('[crm-mobile] tour_bookings app log: ' . $e->getMessage());
        }
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
}

function crm_handle_read(string $kind): void
{
    if (security_rate_limit_exceeded('crm_read_' . $kind, 60, 60)) {
        crm_json_exit(429, ['error' => 'Too many requests']);
    }

    $token = crm_get_bearer_token();
    if ($token === null) {
        crm_json_exit(401, ['error' => 'Unauthorized: Bearer token required']);
    }

    $decoded = firebase_id_token_parse_and_verify($token);
    if ($decoded === null) {
        crm_json_exit(401, ['error' => 'Invalid or expired auth token']);
    }

    if (crm_get_uon_api_key() === '') {
        crm_json_exit(503, ['error' => 'CRM backend is not configured (UON_API_KEY)']);
    }

    $email = isset($_GET['email']) ? trim((string) $_GET['email']) : '';
    $phone = isset($_GET['phone']) ? trim((string) $_GET['phone']) : '';
    $email = $email === '' ? null : $email;
    $phone = $phone === '' ? null : $phone;

    $err = crm_assert_email_phone_allowed($decoded, $email, $phone);
    if ($err !== null) {
        crm_json_exit(403, ['error' => $err]);
    }

    try {
        if ($kind === 'departures') {
            $r = crm_get_user_departure_documents($email, $phone);
            if (!$r['success']) {
                crm_json_exit(502, ['success' => false, 'error' => $r['error'] ?? 'CRM error']);
            }
            echo json_encode(['success' => true, 'data' => $r['data']], JSON_UNESCAPED_UNICODE);

            return;
        }
        if ($kind === 'bookings') {
            $r = crm_get_bookings_by_client($email, $phone);
            if (!$r['success']) {
                crm_json_exit(502, ['success' => false, 'error' => $r['error'] ?? 'CRM error']);
            }
            echo json_encode(['success' => true, 'data' => $r['data']], JSON_UNESCAPED_UNICODE);

            return;
        }
        if ($kind === 'bonus') {
            $cid = crm_get_client_id_from_email_phone($email, $phone);
            if (($cid['id'] ?? null) === null) {
                echo json_encode(['success' => true, 'data' => ['balance' => 0, 'transactions' => []]], JSON_UNESCAPED_UNICODE);

                return;
            }
            $clientId = $cid['id'];
            $r = crm_uon_request('bcard-bonus-by-user/' . rawurlencode((string) $clientId) . '.json', 'GET');
            if (!$r['success']) {
                crm_json_exit(502, ['success' => false, 'error' => $r['error'] ?? 'CRM error']);
            }
            $raw = $r['data'];
            $list = [];
            if (is_array($raw)) {
                if (array_is_list($raw)) {
                    $list = $raw;
                } elseif (isset($raw['rows']) && is_array($raw['rows'])) {
                    $list = $raw['rows'];
                } elseif (isset($raw['row']) && is_array($raw['row'])) {
                    $list = $raw['row'];
                } elseif (isset($raw['data']) && is_array($raw['data'])) {
                    $list = $raw['data'];
                } elseif (isset($raw['items']) && is_array($raw['items'])) {
                    $list = $raw['items'];
                }
            }
            $transactions = [];
            foreach ($list as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $transactions[] = [
                    'id' => $t['id'] ?? null,
                    'bcard_id' => $t['bcard_id'] ?? null,
                    'datetime' => (string) ($t['datetime'] ?? ''),
                    'increase' => $t['increase'] ?? 0,
                    'decrease' => $t['decrease'] ?? 0,
                    'amount' => $t['amount'] ?? 0,
                    'amount_till_date' => $t['amount_till_date'] ?? null,
                    'reason' => $t['reason'] ?? null,
                    'manager_id' => $t['manager_id'] ?? null,
                    'request_id' => $t['request_id'] ?? null,
                ];
            }
            $balance = 0;
            foreach ($transactions as $t) {
                if ((int) ($t['increase'] ?? 0) === 1) {
                    $balance += (int) ($t['amount'] ?? 0);
                }
                if ((int) ($t['decrease'] ?? 0) === 1) {
                    $balance -= (int) ($t['amount'] ?? 0);
                }
            }
            echo json_encode(['success' => true, 'data' => ['balance' => $balance, 'transactions' => $transactions]], JSON_UNESCAPED_UNICODE);

            return;
        }
        crm_json_exit(404, ['error' => 'Unknown CRM route']);
    } catch (Throwable $e) {
        crm_json_exit(500, ['error' => $e->getMessage() ?: 'CRM read failed']);
    }
}
