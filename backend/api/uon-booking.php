<?php
/**
 * Отправка бронирования в U-ON CRM (логика как в TravelHubNew SotaCrmService).
 * 1. Сначала сохраняем бронирование в локальную БД (tour_bookings).
 * 2. Отправляем в CRM POST lead/create.json — обращение (не заявка); см. https://api.u-on.ru/doc
 * 3. При ошибке CRM всё равно возвращаем success (заявка уже в БД).
 *
 * POST: booking_type, tour_link, country, … name/email/phone (заявка менеджеру) или first_name, last_name для полной формы.
 * with_payment — только авторизованный пользователь, полная форма.
 * without_payment (менеджер) — без входа: имя (можно «Имя Фамилия»), email, телефон, опционально note; search_adults, search_childs — в заметку CRM; CSRF + лимит по IP.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/security_helper.php';
require_once dirname(__DIR__) . '/components/lead_validation.php';
require_once dirname(__DIR__) . '/components/tour_link_sanitize.php';
require_once dirname(__DIR__) . '/components/tour_bookings_schema.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не разрешён']);
    exit;
}

session_start();

$input = [];
$raw = file_get_contents('php://input');
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if ($input === []) {
    $input = $_POST;
}

$csrfToken = trim((string) ($input['_csrf_token'] ?? $input['csrf_token'] ?? $_POST['_csrf_token'] ?? ''));
if ($csrfToken === '' || !security_csrf_verify_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Недействительный запрос (CSRF). Обновите страницу и попробуйте снова.']);
    exit;
}

$booking_type = trim((string) ($input['booking_type'] ?? ''));
if ($booking_type !== 'without_payment' && $booking_type !== 'with_payment') {
    echo json_encode(['success' => false, 'error' => 'Укажите тип бронирования: without_payment или with_payment']);
    exit;
}
$user_id = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$is_guest_manager = ($booking_type === 'without_payment' && $user_id <= 0);

if ($booking_type === 'with_payment' && $user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Для бронирования с оплатой войдите в аккаунт.']);
    exit;
}

if (!$is_guest_manager && $user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Необходима авторизация']);
    exit;
}

if ($is_guest_manager) {
    if (security_rate_limit_exceeded('uon_booking_guest_ip', 15, 3600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Слишком много заявок с вашего адреса. Попробуйте позже.']);
        exit;
    }
    $agreeErr = th_lead_require_agree($input);
    if ($agreeErr !== null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $agreeErr]);
        exit;
    }
} elseif (security_rate_limit_exceeded('uon_booking_' . (string) $user_id, 20, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Слишком много запросов. Попробуйте позже.']);
    exit;
}
$tour_link = tour_link_sanitize_for_app(trim((string) ($input['tour_link'] ?? '')));
if ($tour_link !== '' && isset($tour_link[0]) && $tour_link[0] === '/' && strpos($tour_link, '//') !== 0) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $h = isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : '';
    if ($h !== '') {
        $tour_link = $scheme . '://' . $h . $tour_link;
    }
}
$country = trim((string) ($input['country'] ?? ''));
$departure_city = trim((string) ($input['departure_city'] ?? ''));
$flight_info = trim((string) ($input['flight_info'] ?? ''));
if (function_exists('mb_substr')) {
    $flight_info = mb_substr($flight_info, 0, 500, 'UTF-8');
} elseif (strlen($flight_info) > 500) {
    $flight_info = substr($flight_info, 0, 500);
}
$hotel_name = trim((string) ($input['hotel_name'] ?? ''));
$price_str = trim((string) ($input['price'] ?? ''));
$nights_str = trim((string) ($input['nights'] ?? ''));
$meal = trim((string) ($input['meal'] ?? ''));
$room_category = trim((string) ($input['room_category'] ?? ''));
if (function_exists('mb_substr')) {
    $room_category = mb_substr($room_category, 0, 200, 'UTF-8');
} elseif (strlen($room_category) > 200) {
    $room_category = substr($room_category, 0, 200);
}
$note = trim((string) ($input['note'] ?? ''));
$form_name = trim((string) ($input['name'] ?? ''));
$form_email = trim((string) ($input['email'] ?? ''));
$form_phone = trim((string) ($input['phone'] ?? ''));
$date_from = trim((string) ($input['date_from'] ?? $input['startDate'] ?? ''));
$date_to = trim((string) ($input['date_to'] ?? $input['endDate'] ?? ''));
$form_first = trim((string) ($input['first_name'] ?? ''));
$form_last = trim((string) ($input['last_name'] ?? ''));
$search_adults_param = isset($input['search_adults']) && $input['search_adults'] !== '' && $input['search_adults'] !== null
    ? max(1, min(9, (int) $input['search_adults']))
    : null;
$search_childs_param = trim((string) ($input['search_childs'] ?? ''));
$tour_id_crm = preg_replace('/\D+/', '', trim((string) ($input['tour_id'] ?? $input['tourId'] ?? '')));
if (strlen($tour_id_crm) > 24) {
    $tour_id_crm = substr($tour_id_crm, 0, 24);
}
$tour_operator = trim((string) ($input['tour_operator'] ?? ''));
if (function_exists('mb_substr')) {
    $tour_operator = mb_substr($tour_operator, 0, 200, 'UTF-8');
} elseif (strlen($tour_operator) > 200) {
    $tour_operator = substr($tour_operator, 0, 200);
}
$placement_text = trim((string) ($input['placement'] ?? ''));
if (function_exists('mb_substr')) {
    $placement_text = mb_substr($placement_text, 0, 300, 'UTF-8');
} elseif (strlen($placement_text) > 300) {
    $placement_text = substr($placement_text, 0, 300);
}
$operator_tour_link = trim((string) ($input['operator_tour_link'] ?? ''));
if ($operator_tour_link !== '') {
    if (!preg_match('#^https?://#i', $operator_tour_link)) {
        $operator_tour_link = '';
    } elseif (strlen($operator_tour_link) > 2000) {
        $operator_tour_link = substr($operator_tour_link, 0, 2000);
    }
    if ($operator_tour_link !== '' && strcasecmp($operator_tour_link, $tour_link) === 0) {
        $operator_tour_link = '';
    }
}
$applied_promo = trim((string) ($input['applied_promo'] ?? ''));
if ($applied_promo !== '') {
    $applied_promo = strtoupper($applied_promo);
    if (!preg_match('/^[A-Z0-9_-]{1,64}$/', $applied_promo)) {
        $applied_promo = '';
    }
}

if ($tour_link === '') {
    echo json_encode(['success' => false, 'error' => 'Некорректная или пустая ссылка на тур. Откройте страницу тура на сайте и отправьте заявку снова.']);
    exit;
}
$link_ok = preg_match('#^https?://#i', $tour_link) === 1
    || preg_match('#^tourvisor:tour:\d+#i', $tour_link) === 1
    || (strpos($tour_link, '/') === 0 && strpos($tour_link, '//') !== 0);
if (!$link_ok) {
    echo json_encode(['success' => false, 'error' => 'Некорректная ссылка на тур. Откройте тур из поиска или акций снова.']);
    exit;
}
if ($country === '') {
    echo json_encode(['success' => false, 'error' => 'Укажите страну']);
    exit;
}

$user = null;
if (!$is_guest_manager) {
    try {
        $stmt = $pdo->prepare('SELECT name, email, phone, city FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[uon-booking] DB error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка загрузки данных пользователя']);
        exit;
    }
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
        exit;
    }
}

if ($is_guest_manager) {
    $nameErr = th_lead_validate_person_name($form_name);
    if ($nameErr !== null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $nameErr]);
        exit;
    }
    $email = $form_email;
    $phone_raw = $form_phone;
    $phoneCheck = th_lead_validate_ru_phone($phone_raw);
    if (!$phoneCheck['ok']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $phoneCheck['error']]);
        exit;
    }
    $gnParts = preg_split('/\s+/u', $form_name, 2);
    $u_name = $gnParts[0] ?? '';
    $u_surname = $gnParts[1] ?? '';
} else {
    $email = $form_email !== '' ? $form_email : trim((string) ($user['email'] ?? ''));
    $phone_raw = $form_phone !== '' ? $form_phone : trim((string) ($user['phone'] ?? ''));
    if ($email === '' && $phone_raw === '') {
        echo json_encode(['success' => false, 'error' => 'Укажите телефон или e-mail в форме или в профиле']);
        exit;
    }
    if ($form_first !== '' && $form_last !== '') {
        $u_name = $form_first;
        $u_surname = $form_last;
    } else {
        $full_name = $form_name !== '' ? $form_name : trim((string) ($user['name'] ?? ''));
        if ($full_name === '') {
            echo json_encode(['success' => false, 'error' => 'Укажите ФИО в форме или в профиле']);
            exit;
        }
        $parts = preg_split('/\s+/u', $full_name, 2);
        $u_name = $parts[0] ?? '';
        $u_surname = $parts[1] ?? '';
    }
}
if ($is_guest_manager) {
    $phone = $phoneCheck['phone'];
} else {
    if ($phone_raw !== '') {
        $loggedPhone = th_lead_validate_ru_phone($phone_raw);
        $phone = $loggedPhone['ok'] ? $loggedPhone['phone'] : th_lead_normalize_ru_phone($phone_raw);
        if ($phone === '' && $email === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Укажите корректный телефон или e-mail']);
            exit;
        }
    } else {
        $phone = '';
    }
}

$source = trim((string)(getenv('UON_SOURCE') ?: ($_ENV['UON_SOURCE'] ?? 'Сайт')));
if ($source === '') $source = 'Сайт';

$status_id_without = getenv('UON_STATUS_WITHOUT_PAYMENT') ?: ($_ENV['UON_STATUS_WITHOUT_PAYMENT'] ?? '');
$status_id_with = getenv('UON_STATUS_WITH_PAYMENT') ?: ($_ENV['UON_STATUS_WITH_PAYMENT'] ?? '');
$status_id = $booking_type === 'with_payment'
    ? (is_numeric($status_id_with) ? (int) $status_id_with : null)
    : (is_numeric($status_id_without) ? (int) $status_id_without : null);

if ($departure_city === '' && $user !== null) {
    $departure_city = trim((string) ($user['city'] ?? ''));
}

// Цена для CRM (число)
$total_price = 0;
if ($price_str !== '') {
    $total_price = (int) preg_replace('/\D/', '', $price_str);
}
$nights = $nights_str !== '' ? (int) $nights_str : 0;
if ($nights_str !== '' && ($nights < 1 || $nights > 55)) {
    echo json_encode(['success' => false, 'error' => 'Укажите количество ночей от 1 до 55']);
    exit;
}

// Даты в формате Y-m-d H:i:s для U-ON
$now = date('Y-m-d H:i:s');
$r_dat_begin = $now;
$r_dat_end = $now;
if ($date_from !== '') {
    $s = trim($date_from);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        $r_dat_begin = $s . ' 00:00:00';
    } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) {
        $r_dat_begin = $m[3] . '-' . $m[2] . '-' . $m[1] . ' 00:00:00';
    } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $s, $m)) {
        $r_dat_begin = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . ' 00:00:00';
    }
}
if ($date_to !== '') {
    $s = trim($date_to);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        $r_dat_end = $s . ' 00:00:00';
    } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) {
        $r_dat_end = $m[3] . '-' . $m[2] . '-' . $m[1] . ' 00:00:00';
    } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $s, $m)) {
        $r_dat_end = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . ' 00:00:00';
    }
}

// ——— 1. Сначала сохраняем в локальную БД (как откладка в TravelHubNew) ———
ensureTourBookingsTable($pdo);
$local_booking_id = null;
try {
    $guestUid = tourBookingsResolveInsertUserId($pdo, $is_guest_manager, $user_id);
    $gf = $is_guest_manager ? $form_name : null;
    $gl = $is_guest_manager ? '' : null;
    $ge = $is_guest_manager ? $email : null;
    $gp = $is_guest_manager ? $phone_raw : null;
    $ins = $pdo->prepare('INSERT INTO tour_bookings (user_id, tour_link, hotel_name, country, price, nights, meal, uon_lead_id, booking_type, status, guest_first_name, guest_last_name, guest_email, guest_phone, request_source, idempotency_key) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([
        $guestUid,
        $tour_link,
        $hotel_name,
        $country,
        $price_str,
        $nights_str,
        $meal,
        $booking_type,
        'booked',
        $gf,
        $gl,
        $ge,
        $gp,
        'website',
        null,
    ]);
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $local_booking_id = $driver === 'sqlite' ? (string) $pdo->lastInsertId() : (string) $pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('[uon-booking] Insert tour_booking: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сохранения заявки']);
    exit;
}

// Уникальный внутренний id для CRM (связь заявки в CRM с нашей БД)
$r_id_internal = 'site_' . $local_booking_id;

// Примечание заявки (как в TravelHubNew: город вылета, ночей, состав, отель, страна, комментарий)
$note_lines = [];
if ($is_guest_manager) {
    $note_lines[] = 'Заявка без входа на сайт (менеджер)';
    if ($email === '') {
        $note_lines[] = 'E-mail в форме не указан (только телефон).';
    }
}
if ($departure_city !== '') $note_lines[] = 'Город вылета: ' . $departure_city;
if ($flight_info !== '') $note_lines[] = 'Перелёт (выбранный пакет): ' . $flight_info;
if ($date_from !== '' || $date_to !== '') $note_lines[] = 'Даты поездки: ' . trim($date_from . ' — ' . $date_to);
if ($nights > 0) $note_lines[] = 'Ночей: ' . $nights;
if ($meal !== '') $note_lines[] = 'Питание: ' . $meal;
if ($room_category !== '') $note_lines[] = 'Тип номера: ' . $room_category;
if ($placement_text !== '') $note_lines[] = 'Размещение: ' . $placement_text;
if ($tour_operator !== '') $note_lines[] = 'Туроператор: ' . $tour_operator;
if ($hotel_name !== '') $note_lines[] = 'Отель: ' . $hotel_name;
if ($country !== '') $note_lines[] = 'Страна: ' . $country;
if ($applied_promo !== '') {
    $note_lines[] = 'Промокод: ' . $applied_promo;
}
if ($tour_id_crm !== '') {
    $note_lines[] = 'ID тура (Tourvisor): ' . $tour_id_crm;
}
if ($search_adults_param !== null || $search_childs_param !== '') {
    $tParts = [];
    if ($search_adults_param !== null) {
        $tParts[] = 'взрослых в подборе: ' . $search_adults_param;
    }
    if ($search_childs_param !== '') {
        $tParts[] = 'дети (возраст, лет): ' . $search_childs_param;
    }
    if ($tParts !== []) {
        $note_lines[] = 'Состав из поиска: ' . implode('; ', $tParts);
    }
}
if ($note !== '') $note_lines[] = 'Комментарий: ' . $note;
if ($operator_tour_link !== '' && !uon_booking_should_omit_operator_link_note($tour_link, $operator_tour_link)) {
    $note_lines[] = 'Ссылка туроператора / витрина: ' . $operator_tour_link;
}
$note_lines[] = 'Страница тура на сайте: ' . $tour_link;
$note_text = implode("\n", $note_lines);

// Описание тура для примечания (в lead/create нет массива services — всё в note / budget / даты пожеланий)
$service_description_parts = array_filter([
    $hotel_name,
    $country,
    $nights > 0 ? $nights . ' н.' : null,
    $meal !== '' ? $meal : null,
    $room_category !== '' ? $room_category : null,
    $tour_operator !== '' ? $tour_operator : null,
    $departure_city !== '' ? 'Вылет: ' . $departure_city : null,
]);
$service_description = implode(' | ', $service_description_parts);
if ($service_description === '') $service_description = 'Тур';

// Тело обращения U-ON lead/create («Обращения» в CRM; не request/create — «Заявки»)
$date_from_ymd = null;
$date_to_ymd = null;
if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $r_dat_begin, $m)) {
    $date_from_ymd = $m[1];
}
if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $r_dat_end, $m)) {
    $date_to_ymd = $m[1];
}

$note_for_lead = $note_text;
if ($service_description !== '') {
    $note_for_lead .= "\nУслуга (кратко): " . $service_description;
}
if ($total_price > 0) {
    $note_for_lead .= "\nЦена на сайте: " . $total_price;
}

$body = [
    'r_id_internal' => $r_id_internal,
    'r_dat' => $now,
    'r_dat_lead' => $now,
    'source' => $source,
    'u_name' => $u_name,
    'u_surname' => $u_surname,
    'note' => $note_for_lead,
];
if ($date_from_ymd !== null) {
    $body['date_from'] = $date_from_ymd;
}
if ($date_to_ymd !== null) {
    $body['date_to'] = $date_to_ymd;
}
if ($total_price > 0) {
    $body['budget'] = $total_price;
}
if ($phone !== '') {
    $body['u_phone'] = $phone;
    $body['u_phone_mobile'] = $phone;
}
if ($email !== '') {
    $body['u_email'] = $email;
}
if ($status_id !== null) {
    $body['status_id'] = $status_id;
}

$api_key = trim((string)(getenv('UON_API_KEY') ?: ($_ENV['UON_API_KEY'] ?? '')));
if ($api_key === '') {
    $api_key = trim((string)(getenv('SOTA_API_KEY') ?: ($_ENV['SOTA_API_KEY'] ?? '')));
}
if ($api_key === '') {
    error_log('[uon-booking] UON_API_KEY не задан в .env — заявка сохранена в БД, в CRM не отправлена');
    $out = ['success' => true, 'booking_id' => $local_booking_id, 'crm_sent' => false, 'crm_error' => 'Не настроен ключ U-ON. Заявка сохранена. Укажите UON_API_KEY или SOTA_API_KEY в .env.'];
    echo json_encode($out);
    exit;
}

// Убираем null из тела (некоторые API не принимают null)
$body = array_filter($body, function ($v) { return $v !== null; });
$body_json = json_encode($body, JSON_UNESCAPED_UNICODE);
if ($body_json === false) {
    $body_json = '{}';
    error_log('[uon-booking] json_encode body failed');
}
if (defined('APP_DEBUG') && APP_DEBUG) {
    error_log('[uon-booking] Request body: ' . substr($body_json, 0, 1500));
}
$url = 'https://api.u-on.ru/' . $api_key . '/lead/create.json';

// ——— 2. Отправка в CRM (при ошибке не отказываем пользователю — заявка уже в БД) ———
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

$crm_sent = false;
$lead_id = null;
$crm_error = null;

$http_ok = $http_code >= 200 && $http_code < 300;

if ($curl_err !== '') {
    $crm_error = 'Ошибка соединения: ' . $curl_err;
    error_log('[uon-booking] cURL error: ' . $curl_err);
} elseif (!$http_ok) {
    $crm_error = 'CRM вернул код ' . $http_code;
    $response_preview = is_string($response) ? substr($response, 0, 800) : json_encode($response);
    error_log('[uon-booking] U-ON HTTP ' . $http_code . ' response: ' . $response_preview);
} else {
    $response_str = (string) $response;
    $data = null;
    if ($response_str !== '') {
        $data = json_decode($response_str, true);
        if (!is_array($data) && json_last_error() !== JSON_ERROR_NONE) {
            $crm_error = 'CRM вернул ответ в неожиданном формате';
            error_log('[uon-booking] U-ON ' . $http_code . ' invalid JSON: ' . substr($response_str, 0, 500));
            $data = null;
        }
    }
    $lead_id = null;
    if (is_array($data)) {
        $lead_id = uon_booking_extract_lead_id($data);
    }
    if ($crm_error === null && $lead_id !== null) {
        $crm_sent = true;
        try {
            $upd = $pdo->prepare('UPDATE tour_bookings SET uon_lead_id = ? WHERE id = ?');
            $upd->execute([(string) $lead_id, $local_booking_id]);
        } catch (Throwable $e) {
            error_log('[uon-booking] Update uon_lead_id: ' . $e->getMessage());
        }
    } elseif ($crm_error === null && uon_booking_response_has_apparent_error($data)) {
        $crm_error = 'CRM отклонил заявку';
        $response_preview = substr($response_str, 0, 800);
        error_log('[uon-booking] U-ON ' . $http_code . ' error body: ' . $response_preview);
    } elseif ($crm_error === null) {
        // 2xx без распознанного id (например 204 или нестандартный JSON) — как uon-lead.php: считаем доставку успешной
        $crm_sent = true;
        if ($response_str !== '') {
            error_log('[uon-booking] U-ON ' . $http_code . ' OK but lead id not parsed: ' . substr($response_str, 0, 800));
        }
    }
}

$out = ['success' => true, 'booking_id' => $local_booking_id, 'crm_sent' => $crm_sent];
if ($lead_id !== null) {
    $out['lead_id'] = $lead_id;
}
if ($crm_error !== null) {
    $out['crm_error'] = $crm_error;
}
echo json_encode($out);

/**
 * Не выводить в CRM отдельной строкой ссылку на витрину туроператора (tyrmarket и т.п.),
 * если менеджеру достаточно ссылки на страницу тура на нашем сайте или витрина уже «внутри» tour_link.
 */
function uon_booking_should_omit_operator_link_note(string $tour_link, string $operator_tour_link): bool {
    if ($operator_tour_link === '') {
        return true;
    }
    $tl = strtolower($tour_link);
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower(preg_replace('/:\d+$/', '', trim((string) $_SERVER['HTTP_HOST']))) : '';
    if ($host !== '' && str_contains($tl, $host) && str_contains($tl, 'tour-detail')) {
        return true;
    }
    if (stripos($tour_link, $operator_tour_link) !== false) {
        return true;
    }
    $enc = rawurlencode($operator_tour_link);
    if ($enc !== '' && str_contains($tour_link, $enc)) {
        return true;
    }

    return false;
}

/**
 * Достаёт id заявки из ответа U-ON (разные обёртки и вложенность).
 *
 * @param mixed $data
 * @return string|int|null
 */
function uon_booking_extract_lead_id($data) {
    if (!is_array($data)) {
        return null;
    }
    foreach (['id', 'id_system', 'r_id'] as $k) {
        if (!array_key_exists($k, $data)) {
            continue;
        }
        $v = $data[$k];
        if ($v === null || $v === '') {
            continue;
        }
        if (is_string($v) || is_int($v) || is_float($v)) {
            return $v;
        }
    }
    foreach (['message', 'request', 'data', 'result'] as $wrap) {
        if (isset($data[$wrap]) && is_array($data[$wrap])) {
            $inner = uon_booking_extract_lead_id($data[$wrap]);
            if ($inner !== null) {
                return $inner;
            }
        }
    }
    if (isset($data[0]) && is_array($data[0])) {
        return uon_booking_extract_lead_id($data[0]);
    }

    return null;
}

/**
 * @param mixed $data
 */
function uon_booking_response_has_apparent_error($data): bool {
    if (!is_array($data)) {
        return false;
    }
    if (array_key_exists('error', $data)) {
        $e = $data['error'];
        if (is_string($e) && trim($e) !== '') {
            return true;
        }
        if (is_array($e) && $e !== []) {
            return true;
        }
        if ($e === true) {
            return true;
        }
    }
    if (isset($data['errors']) && is_array($data['errors']) && $data['errors'] !== []) {
        return true;
    }
    if (array_key_exists('success', $data) && $data['success'] === false) {
        return true;
    }

    return false;
}
