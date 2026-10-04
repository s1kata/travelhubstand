<?php
/**
 * Firestore через REST API: чтение/запись кэша поиска туров.
 * Учётные данные: путь к JSON сервисного аккаунта в FIREBASE_SERVICE_ACCOUNT или config/firebase-service-account.json
 */
declare(strict_types=1);

function firestoreCredentialsPath(): ?string {
    $path = getenv('FIREBASE_SERVICE_ACCOUNT') ?: ($_ENV['FIREBASE_SERVICE_ACCOUNT'] ?? '');
    if ($path !== '' && is_file($path)) return $path;
    $root = defined('TV_PROJECT_ROOT') ? TV_PROJECT_ROOT : (function_exists('th_project_root') ? th_project_root() : dirname(__DIR__, 3));
    $candidates = [
        $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'firebase-service-account.json',
        $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'firebase-service-account.json',
    ];
    foreach ($candidates as $p) {
        if (is_file($p)) return $p;
    }
    return null;
}

function firestoreLoadCredentials(): ?array {
    $path = firestoreCredentialsPath();
    if ($path === null) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $d = json_decode($raw, true);
    return is_array($d) && !empty($d['private_key']) && !empty($d['client_email']) ? $d : null;
}

/** JWT для OAuth2 (service account). */
function firestoreJwt(array $creds, int $ttlSec = 3600): string {
    $now = time();
    $payload = [
        'iss' => $creds['client_email'],
        'sub' => $creds['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + $ttlSec,
        'scope' => 'https://www.googleapis.com/auth/datastore',
    ];
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $b64 = static function ($v) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($v));
    };
    $seg1 = $b64(json_encode($header));
    $seg2 = $b64(json_encode($payload));
    $signInput = $seg1 . '.' . $seg2;
    $key = $creds['private_key'];
    $sig = '';
    openssl_sign($signInput, $sig, $key, OPENSSL_ALGO_SHA256);
    return $signInput . '.' . $b64($sig);
}

function firestoreAccessToken(): ?string {
    static $cached = null;
    static $expires = 0;
    if ($cached !== null && time() < $expires) return $cached;
    $creds = firestoreLoadCredentials();
    if ($creds === null) return null;
    $jwt = firestoreJwt($creds);
    $body = 'grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=' . urlencode($jwt);
    $timeout = firestore_http_timeout_sec();
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => $timeout,
        ],
    ]);
    $resp = @file_get_contents('https://oauth2.googleapis.com/token', false, $ctx);
    if ($resp === false) return null;
    $d = json_decode($resp, true);
    $token = $d['access_token'] ?? null;
    if (is_string($token)) {
        $cached = $token;
        $expires = time() + (int)($d['expires_in'] ?? 3600) - 60;
    }
    return $token;
}

/**
 * Короткий таймаут для L1 read-cache: при сбое быстро падаем на L2 (файл/PHP).
 */
function firestore_http_timeout_sec(): float {
    $raw = getenv('TH_CACHE_FIRESTORE_TIMEOUT_SEC') ?: ($_ENV['TH_CACHE_FIRESTORE_TIMEOUT_SEC'] ?? '2.5');
    $t = (float) $raw;
    if ($t < 0.5) {
        $t = 0.5;
    }
    if ($t > 15) {
        $t = 15;
    }

    return $t;
}

/**
 * Читает документ из коллекции. Возвращает массив с ключами data (array) и expiresAt (int) или null.
 * Поддерживает data как stringValue (JSON) или mapValue (массив/объект Firestore); при отсутствии expiresAt не проверяет срок.
 */
function firestoreGet(string $projectId, string $collection, string $documentId): ?array {
    $token = firestoreAccessToken();
    if ($token === null) return null;
    $docId = preg_replace('#[^a-zA-Z0-9_\-=]#', '_', $documentId);
    $url = 'https://firestore.googleapis.com/v1/projects/' . urlencode($projectId) . '/databases/(default)/documents/' . urlencode($collection) . '/' . $docId;
    $timeout = firestore_http_timeout_sec();
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$token}\r\n",
            'timeout' => $timeout,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return null;
    $doc = json_decode($resp, true);
    if (!isset($doc['fields'])) return null;
    $fields = $doc['fields'];
    $expiresAt = isset($fields['expiresAt']['integerValue']) ? (int)$fields['expiresAt']['integerValue'] : 0;
    if ($expiresAt > 0) {
        $now = $expiresAt > 1e12 ? (int)(microtime(true) * 1000) : time();
        if ($now > $expiresAt) return null;
    }
    if (!isset($fields['data'])) return null;
    $dataRaw = firestoreValueToPhp($fields['data']);
    if ($dataRaw === null) return null;
    $data = is_string($dataRaw) ? json_decode($dataRaw, true) : $dataRaw;
    if (!is_array($data)) return null;
    return ['data' => $data, 'expiresAt' => $expiresAt];
}

/**
 * Преобразует значение Firestore REST API (stringValue, integerValue, mapValue, arrayValue) в PHP-тип.
 */
function firestoreValueToPhp(array $v) {
    if (isset($v['stringValue'])) return (string)$v['stringValue'];
    if (isset($v['integerValue'])) return (int)$v['integerValue'];
    if (isset($v['booleanValue'])) return (bool)$v['booleanValue'];
    if (isset($v['doubleValue'])) return (float)$v['doubleValue'];
    if (isset($v['nullValue'])) return null;
    if (isset($v['mapValue']['fields'])) {
        $out = [];
        $numericKeys = true;
        foreach ($v['mapValue']['fields'] as $k => $fv) {
            $converted = firestoreValueToPhp($fv);
            if (!ctype_digit((string)$k)) $numericKeys = false;
            $out[$k] = $converted;
        }
        if ($numericKeys && !empty($out)) {
            ksort($out, SORT_NUMERIC);
            return array_values($out);
        }
        return $out;
    }
    if (isset($v['arrayValue']['values'])) {
        $out = [];
        foreach ($v['arrayValue']['values'] as $fv) {
            $out[] = firestoreValueToPhp($fv);
        }
        return $out;
    }
    return null;
}

/**
 * Записывает документ (поля data и expiresAt). PATCH создаёт документ, если его нет.
 * expiresAt в секундах (Unix) — при записи конвертируется в мс для совместимости с TravelHubNew.
 */
function firestoreSet(string $projectId, string $collection, string $documentId, array $data, int $expiresAtSec): bool {
    $token = firestoreAccessToken();
    if ($token === null) return false;
    $docId = preg_replace('#[^a-zA-Z0-9_\-=]#', '_', $documentId);
    $name = 'projects/' . $projectId . '/databases/(default)/documents/' . $collection . '/' . $docId;
    $url = 'https://firestore.googleapis.com/v1/' . $name . '?updateMask.fieldPaths=data&updateMask.fieldPaths=expiresAt';
    $expiresAtMs = $expiresAtSec > 1e12 ? $expiresAtSec : $expiresAtSec * 1000;
    $payload = [
        'name' => $name,
        'fields' => [
            'data' => ['stringValue' => json_encode($data, JSON_UNESCAPED_UNICODE)],
            'expiresAt' => ['integerValue' => (string)$expiresAtMs],
        ],
    ];
    $ctx = stream_context_create([
        'http' => [
            'method' => 'PATCH',
            'header' => "Authorization: Bearer {$token}\r\nContent-Type: application/json\r\n",
            'content' => json_encode($payload),
            'timeout' => firestore_http_timeout_sec() + 2.0,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    return $resp !== false;
}

/**
 * Список документов коллекции (пагинация). $fieldPaths — только эти поля (например expiresAt).
 *
 * @param list<string> $fieldPaths
 * @return array{documents: list<array{id: string, fields: array<string, mixed>}>, nextPageToken: ?string}
 */
function firestoreListDocuments(
    string $projectId,
    string $collection,
    int $pageSize = 100,
    ?string $pageToken = null,
    array $fieldPaths = []
): array {
    $token = firestoreAccessToken();
    if ($token === null) {
        return ['documents' => [], 'nextPageToken' => null];
    }
    $pageSize = max(1, min(300, $pageSize));
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/' . rawurlencode($collection)
        . '?pageSize=' . $pageSize;
    if ($pageToken !== null && $pageToken !== '') {
        $url .= '&pageToken=' . rawurlencode($pageToken);
    }
    foreach ($fieldPaths as $fp) {
        $fp = trim((string) $fp);
        if ($fp !== '') {
            $url .= '&mask.fieldPaths=' . rawurlencode($fp);
        }
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$token}\r\n",
            'timeout' => firestore_http_timeout_sec() + 5.0,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) {
        return ['documents' => [], 'nextPageToken' => null];
    }
    $decoded = json_decode($resp, true);
    if (!is_array($decoded)) {
        return ['documents' => [], 'nextPageToken' => null];
    }
    $out = [];
    foreach ($decoded['documents'] ?? [] as $doc) {
        if (!is_array($doc) || empty($doc['name'])) {
            continue;
        }
        $name = (string) $doc['name'];
        $id = substr($name, strrpos($name, '/') + 1);
        $out[] = [
            'id' => $id,
            'fields' => is_array($doc['fields'] ?? null) ? $doc['fields'] : [],
        ];
    }

    return [
        'documents' => $out,
        'nextPageToken' => isset($decoded['nextPageToken']) ? (string) $decoded['nextPageToken'] : null,
    ];
}

function firestoreDeleteDocument(string $projectId, string $collection, string $documentId): bool
{
    $token = firestoreAccessToken();
    if ($token === null) {
        return false;
    }
    $docId = preg_replace('#[^a-zA-Z0-9_\-=]#', '_', $documentId);
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
        . '/databases/(default)/documents/' . rawurlencode($collection) . '/' . rawurlencode($docId);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'DELETE',
            'header' => "Authorization: Bearer {$token}\r\n",
            'timeout' => firestore_http_timeout_sec() + 2.0,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', (string) $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }

    return $resp !== false && ($code === 200 || $code === 204);
}

/**
 * Макс. размер JSON payload для одного документа (~лимит Firestore 1 MiB с запасом).
 */
function firestore_max_payload_bytes(): int
{
    $raw = getenv('TH_FIRESTORE_MAX_DOC_BYTES') ?: ($_ENV['TH_FIRESTORE_MAX_DOC_BYTES'] ?? '900000');
    $n = (int) $raw;

    return $n > 100000 ? $n : 900000;
}

/**
 * Project ID для Firestore: FIREBASE_PROJECT_ID или поле project_id в JSON сервисного аккаунта.
 */
function firestore_resolve_project_id(): ?string {
    $pid = getenv('FIREBASE_PROJECT_ID') ?: ($_ENV['FIREBASE_PROJECT_ID'] ?? '');
    if ($pid !== '') {
        return (string) $pid;
    }
    $creds = firestoreLoadCredentials();
    if (is_array($creds) && !empty($creds['project_id'])) {
        return (string) $creds['project_id'];
    }
    return null;
}

/**
 * PATCH верхнеуровневых полей документа (merge по указанным mask). Возвращает HTTP-код и тело ответа.
 *
 * @param array<string, array<string, mixed>> $fields Firestore-формат fields (stringValue, timestampValue, …)
 * @param list<string> $updateMaskPaths пути для updateMask (например paymentStatus, updatedAt)
 * @return array{code:int, body:string}
 */
function firestore_patch_document_fields(
    string $projectId,
    string $collection,
    string $documentId,
    array $fields,
    array $updateMaskPaths
): array {
    $token = firestoreAccessToken();
    if ($token === null) {
        return ['code' => 0, 'body' => 'no_access_token'];
    }
    if ($documentId === '' || strpos($documentId, '/') !== false) {
        return ['code' => 0, 'body' => 'invalid_document_id'];
    }
    $name = 'projects/' . $projectId . '/databases/(default)/documents/'
        . rawurlencode($collection) . '/' . rawurlencode($documentId);
    $url = 'https://firestore.googleapis.com/v1/' . $name . '?';
    $maskParts = [];
    foreach ($updateMaskPaths as $path) {
        $path = (string) $path;
        if ($path === '') {
            continue;
        }
        $maskParts[] = 'updateMask.fieldPaths=' . rawurlencode($path);
    }
    $url .= implode('&', $maskParts);
    $payload = ['fields' => $fields];
    $ctx = stream_context_create([
        'http' => [
            'method' => 'PATCH',
            'header' => "Authorization: Bearer {$token}\r\nContent-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', (string) $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    return ['code' => $code, 'body' => $resp !== false ? (string) $resp : ''];
}

/**
 * После CONFIRMED в Т‑Кассе: bookings/{bookingId} → paymentStatus paid (+ служебные поля).
 * Использует те же FIREBASE_SERVICE_ACCOUNT / JSON, что и кэш Tourvisor (REST, без отдельного Composer SDK).
 *
 * @return bool true при HTTP 200
 */
function firestore_booking_mark_paid(string $orderId, string $paymentId): bool
{
    $bookingId = $orderId;
    if ($bookingId !== '' && strpos($bookingId, '__ts__') !== false) {
        $bookingId = strstr($bookingId, '__ts__', true) ?: $bookingId;
    }
    $bookingId = trim($bookingId);
    if ($bookingId === '' || strlen($bookingId) > 512 || strpos($bookingId, '/') !== false) {
        error_log('[Tinkoff Webhook Firestore] skip: invalid booking id length or slash');
        return false;
    }
    $projectId = firestore_resolve_project_id();
    if ($projectId === null || $projectId === '') {
        error_log('[Tinkoff Webhook Firestore] skip: FIREBASE_PROJECT_ID / service account missing');
        return false;
    }
    $nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    $fields = [
        'paymentStatus' => ['stringValue' => 'paid'],
        'updatedAt' => ['timestampValue' => $nowUtc],
        'paidAt' => ['timestampValue' => $nowUtc],
        'transactionId' => ['stringValue' => $paymentId],
    ];
    $masks = ['paymentStatus', 'updatedAt', 'paidAt', 'transactionId'];
    $r = firestore_patch_document_fields($projectId, 'bookings', $bookingId, $fields, $masks);
    if ($r['code'] === 200) {
        error_log('[Tinkoff Webhook Firestore] OK booking=' . $bookingId . ' paymentId=' . $paymentId);
        return true;
    }
    error_log(
        '[Tinkoff Webhook Firestore] FAIL booking=' . $bookingId . ' paymentId=' . $paymentId
        . ' http=' . $r['code'] . ' body=' . substr($r['body'], 0, 500)
    );
    return false;
}
