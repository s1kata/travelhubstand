<?php
/**
 * Заявка «Написать в офис» — POST в U-ON как обращение (lead/create).
 * POST JSON: first_name, last_name, phone, email, comment (опц.), office_city, office_name, agree, website (honeypot)
 * Без авторизации. Защита: rate limit, honeypot.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/security_helper.php';
require_once dirname(__DIR__) . '/components/lead_validation.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не разрешён']);
    exit;
}

// Rate limit: 6 заявок за 10 минут с одного IP
if (security_rate_limit_exceeded('office_lead', 6, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Слишком много запросов. Попробуйте через 10 минут.']);
    exit;
}

$input = [];
$raw = file_get_contents('php://input');
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input)) {
    $input = $_POST;
}

// Honeypot
if (!security_honeypot_check($input, 'website')) {
    echo json_encode(['success' => true, 'message' => 'Заявка принята.']);
    exit;
}

$fullName = mb_substr(trim((string)($input['name'] ?? '')), 0, 100);
$first = mb_substr(trim((string)($input['first_name'] ?? '')), 0, 80);
$last = mb_substr(trim((string)($input['last_name'] ?? '')), 0, 80);
$phone_raw = mb_substr(trim((string)($input['phone'] ?? '')), 0, 30);
$email = mb_substr(trim((string)($input['email'] ?? '')), 0, 120);
$comment = mb_substr(trim((string)($input['comment'] ?? '')), 0, 1500);
$office_city = mb_substr(trim((string)($input['office_city'] ?? '')), 0, 40);
$office_name = mb_substr(trim((string)($input['office_name'] ?? '')), 0, 120);

if ($fullName !== '') {
    $nameErr = th_lead_validate_person_name($fullName);
    if ($nameErr !== null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $nameErr]);
        exit;
    }
    $parts = preg_split('/\s+/u', $fullName, 2);
    $first = $parts[0] ?? '';
    $last = $parts[1] ?? '';
} else {
    $firstErr = th_lead_validate_person_name($first, 2, 80);
    if ($firstErr !== null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Укажите корректные ФИО']);
        exit;
    }
    if ($last === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Укажите фамилию (или полное ФИО в одном поле)']);
        exit;
    }
    $lastErr = th_lead_validate_person_name($last, 2, 80);
    if ($lastErr !== null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Укажите корректную фамилию']);
        exit;
    }
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Укажите корректный email или оставьте поле пустым']);
    exit;
}
$agreeErr = th_lead_require_agree($input);
if ($agreeErr !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $agreeErr]);
    exit;
}

$phoneCheck = th_lead_validate_ru_phone($phone_raw);
if (!$phoneCheck['ok']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $phoneCheck['error']]);
    exit;
}
$phone = $phoneCheck['phone'];

$source = trim((string)(getenv('UON_SOURCE') ?: ($_ENV['UON_SOURCE'] ?? 'Сайт')));
if ($source === '') $source = 'Сайт';

$now = date('Y-m-d H:i:s');
$noteParts = [];
$noteParts[] = 'Заявка «Написать в офис» с сайта.';
if ($office_city !== '' || $office_name !== '') {
    $noteParts[] = 'Офис: ' . trim($office_name . ($office_city !== '' ? ' (' . $office_city . ')' : ''));
}
if ($comment !== '') {
    $noteParts[] = 'Комментарий: ' . $comment;
}
$note = implode("\n", $noteParts);

$body = [
    'r_dat' => $now,
    'r_dat_lead' => $now,
    'source' => $source,
    'u_name' => $first,
    'u_surname' => $last,
    'u_phone' => $phone,
    'u_email' => $email,
    'note' => $note,
];

$api_key = trim((string)(getenv('UON_API_KEY') ?: ($_ENV['UON_API_KEY'] ?? '')));
if ($api_key === '') {
    $api_key = trim((string)(getenv('SOTA_API_KEY') ?: ($_ENV['SOTA_API_KEY'] ?? '')));
}
if ($api_key === '') {
    error_log('[office-lead] UON_API_KEY (и SOTA_API_KEY) не заданы в .env');
    echo json_encode([
        'success' => true,
        'message' => 'Заявка принята. Мы свяжемся с вами в ближайшее время.',
        'crm_sent' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$body_json = json_encode($body, JSON_UNESCAPED_UNICODE);
if ($body_json === false) $body_json = '{}';

$url = 'https://api.u-on.ru/' . $api_key . '/lead/create.json';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body_json,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err !== '') {
    error_log('[office-lead] cURL error: ' . $curl_err);
}

if ($http_code >= 200 && $http_code < 300) {
    echo json_encode([
        'success' => true,
        'message' => 'Заявка принята. Мы свяжемся с вами в ближайшее время.',
        'crm_sent' => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

error_log('[office-lead] U-ON HTTP ' . $http_code . ': ' . substr((string)$response, 0, 500));
echo json_encode([
    'success' => true,
    'message' => 'Заявка принята. Мы свяжемся с вами в ближайшее время.',
    'crm_sent' => false,
], JSON_UNESCAPED_UNICODE);

