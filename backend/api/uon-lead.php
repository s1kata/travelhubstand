<?php
/**
 * Отправка заявки с формы «Оставьте заявку» в U-ON CRM (обращение lead/create, не заявка request/create).
 * POST: name, phone, message (опц.), agree.
 * Без авторизации. Защита: rate limit, honeypot.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/security_helper.php';
require_once dirname(__DIR__) . '/components/lead_validation.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
// CORS не задаём — форма на том же сайте, cross-origin запросы блокируются


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не разрешён']);
    exit;
}

// Rate limit: 5 заявок за 10 минут с одного IP (анти-спам)
if (security_rate_limit_exceeded('uon_lead', 5, 600)) {
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

// Honeypot: боты заполняют скрытое поле
if (!security_honeypot_check($input, 'website')) {
    echo json_encode(['success' => true, 'message' => 'Заявка принята.']);
    exit;
}

$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 100);
$phone_raw = mb_substr(trim((string) ($input['phone'] ?? '')), 0, 20);
$message = mb_substr(trim((string) ($input['message'] ?? '')), 0, 1000);
$email_raw = mb_substr(trim((string) ($input['email'] ?? '')), 0, 120);
$nameErr = th_lead_validate_person_name($name);
if ($nameErr !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $nameErr]);
    exit;
}
$agreeErr = th_lead_require_agree($input);
if ($agreeErr !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $agreeErr]);
    exit;
}
if ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Укажите корректный email или оставьте поле пустым']);
    exit;
}

$phoneCheck = th_lead_validate_ru_phone($phone_raw);
if (!$phoneCheck['ok']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $phoneCheck['error']]);
    exit;
}
$phone = $phoneCheck['phone'];

$parts = preg_split('/\s+/u', $name, 2);
$u_name = $parts[0] ?? '';
$u_surname = $parts[1] ?? '';

$source = trim((string)(getenv('UON_SOURCE') ?: ($_ENV['UON_SOURCE'] ?? 'Сайт')));
if ($source === '') $source = 'Сайт';
$funnel_source = mb_substr(trim((string) ($input['funnel_source'] ?? $input['source'] ?? $input['page_source'] ?? '')), 0, 80);

$now = date('Y-m-d H:i:s');
$note = $message !== '' ? 'Заявка с сайта. Сообщение: ' . $message : 'Заявка с сайта (форма «Оставьте заявку»)';
if ($funnel_source !== '') {
    $note = '[funnel:' . $funnel_source . '] ' . $note;
}
if ($email_raw !== '') {
    $note .= "\nEmail: " . $email_raw;
}

$body = [
    'r_dat' => $now,
    'r_dat_lead' => $now,
    'source' => $source,
    'u_name' => $u_name,
    'u_surname' => $u_surname,
    'u_phone' => $phone,
    'note' => $note,
];

$api_key = trim((string)(getenv('UON_API_KEY') ?: ($_ENV['UON_API_KEY'] ?? '')));
if ($api_key === '') {
    $api_key = trim((string)(getenv('SOTA_API_KEY') ?: ($_ENV['SOTA_API_KEY'] ?? '')));
}

if ($api_key === '') {
    error_log('[uon-lead] UON_API_KEY (и SOTA_API_KEY) не заданы в .env');
    echo json_encode([
        'success' => true,
        'message' => 'Заявка принята. Мы свяжемся с вами в течение 15 минут.',
        'crm_sent' => false,
    ]);
    exit;
}

$body_json = json_encode($body, JSON_UNESCAPED_UNICODE);
if ($body_json === false) {
    $body_json = '{}';
}
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
$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err !== '') {
    error_log('[uon-lead] cURL error: ' . $curl_err);
}

if ($http_code >= 200 && $http_code < 300) {
    echo json_encode([
        'success' => true,
        'message' => 'Заявка принята. Мы свяжемся с вами в течение 15 минут.',
        'crm_sent' => true,
    ]);
} else {
    error_log('[uon-lead] U-ON HTTP ' . $http_code . ': ' . substr((string)$response, 0, 500));
    echo json_encode([
        'success' => true,
        'message' => 'Заявка принята. Мы свяжемся с вами в течение 15 минут.',
        'crm_sent' => false,
    ]);
}
