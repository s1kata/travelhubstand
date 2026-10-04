<?php
/**
 * Support chat bot (rule-based, no AI).
 * Shared endpoint for site widget and mobile app.
 *
 * Паттерны — как у крупных OTA / туроператоров:
 * FAQ-интенты + пошаговый intake заявки (куда → даты → туристы → бюджет → телефон).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/security_helper.php';
require_once dirname(__DIR__) . '/components/secure_logger.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
security_apply_default_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (security_rate_limit_exceeded('support_chat', 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Слишком много сообщений. Попробуйте через минуту.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$guard = security_guard_public_api('support_chat_api', is_string($raw) ? $raw : '', 40, 16384);
if (empty($guard['ok'])) {
    http_response_code((int) ($guard['code'] ?? 400));
    echo json_encode(['success' => false, 'error' => (string) ($guard['error'] ?? 'Bad request')], JSON_UNESCAPED_UNICODE);
    th_log_write('security_events', 'support_chat_guard_block', [
        'reason' => $guard['reason'] ?? 'unknown',
        'path' => '/backend/api/support-chat.php',
    ], 'warn');
    exit;
}
$in = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
if (!is_array($in)) {
    $in = [];
}

$message = trim((string) ($in['message'] ?? ''));
$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($in['sessionId'] ?? ''));
$channel = mb_substr(trim((string) ($in['channel'] ?? 'site')), 0, 24);

if ($sessionId === '') {
    try {
        $sessionId = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $sessionId = substr(md5((string) microtime(true)), 0, 16);
    }
}

/** @return array{flow:string,step:string,data:array<string,string>,updatedAt:int} */
function th_support_session_default(): array
{
    return [
        'flow' => 'idle',
        'step' => '',
        'data' => [],
        'updatedAt' => time(),
    ];
}

function th_support_session_path(string $sessionId): string
{
    // Не sys_get_temp_dir — на хостинге tmp чистят / разные воркеры → intake «забывает» шаг
    $dir = '';
    if (function_exists('th_project_root')) {
        $dir = th_project_root() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'support_chat';
    } else {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'support_chat';
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // fallback если data недоступна на запись
    if (!is_dir($dir) || !is_writable($dir)) {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'th_support_chat';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    return $dir . DIRECTORY_SEPARATOR . 's_' . substr(hash('sha256', $sessionId), 0, 32) . '.json';
}

/** @return array{flow:string,step:string,data:array<string,string>,updatedAt:int} */
function th_support_session_load(string $sessionId): array
{
    $path = th_support_session_path($sessionId);
    if (!is_file($path)) {
        return th_support_session_default();
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return th_support_session_default();
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return th_support_session_default();
    }
    $updated = (int) ($data['updatedAt'] ?? 0);
    // Сессия intake — 2 часа
    if ($updated > 0 && (time() - $updated) > 7200) {
        @unlink($path);
        return th_support_session_default();
    }
    return [
        'flow' => (string) ($data['flow'] ?? 'idle'),
        'step' => (string) ($data['step'] ?? ''),
        'data' => is_array($data['data'] ?? null) ? array_map('strval', $data['data']) : [],
        'updatedAt' => $updated ?: time(),
    ];
}

/** @param array{flow:string,step:string,data:array<string,string>,updatedAt?:int} $session */
function th_support_session_save(string $sessionId, array $session): void
{
    $session['updatedAt'] = time();
    @file_put_contents(
        th_support_session_path($sessionId),
        json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function th_support_normalize(string $text): string
{
    $t = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $t = str_replace(['ё', 'Ё'], ['е', 'е'], $t);
    return trim($t);
}

function th_support_extract_phone(string $text): ?string
{
    if (!preg_match('/(?:\+?7|8)?[\s\-()]*(?:\d[\s\-()]*){10}/u', $text, $m)) {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $m[0] ?? '');
    if (!is_string($digits) || strlen($digits) < 10) {
        return null;
    }
    if (strlen($digits) === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
        $digits = '7' . substr($digits, 1);
    } elseif (strlen($digits) === 10) {
        $digits = '7' . $digits;
    }
    if (strlen($digits) !== 11 || $digits[0] !== '7') {
        return null;
    }
    return '+' . $digits;
}

/** Меню быстрых кнопок — как у Level.Travel / Onlinetours / Booking help. */
function th_support_menu_replies(): array
{
    return [
        'Подобрать тур',
        'Горящие туры',
        'Бронирование',
        'Оплата',
        'Документы и виза',
        'Отмена и возврат',
        'Статус заявки',
        'Перелёт и трансфер',
        'Отель и питание',
        'Цены и что входит',
        'Контакты',
        'Связаться с менеджером',
    ];
}

function th_support_json(
    string $sessionId,
    string $intent,
    string $reply,
    bool $handoff,
    array $quickReplies
): void {
    echo json_encode([
        'success' => true,
        'sessionId' => $sessionId,
        'intent' => $intent,
        'reply' => $reply,
        'handoff' => $handoff,
        'managerCta' => $handoff,
        'quickReplies' => array_values($quickReplies),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$session = th_support_session_load($sessionId);
$hay = th_support_normalize($message);

// ——— Приветствие ———
if ($message === '') {
    th_support_json(
        $sessionId,
        'greeting',
        "Здравствуйте! Я помощник TravelHub.\n\n"
        . "Отвечаю кнопками — выберите тему ниже. Для подбора тура нажмите «Подобрать тур»: "
        . "куда → даты → туристы → бюджет → передадим менеджеру.",
        false,
        th_support_menu_replies()
    );
}

// Сброс / меню
if (
    str_contains($hay, 'меню')
    || str_contains($hay, 'главн')
    || $hay === 'назад'
    || str_contains($hay, 'сброс')
    || str_contains($hay, 'начать сначала')
) {
    $session = th_support_session_default();
    th_support_session_save($sessionId, $session);
    th_support_json(
        $sessionId,
        'menu',
        "Хорошо, вернулись в меню. Чем помочь?",
        false,
        th_support_menu_replies()
    );
}

/**
 * Пошаговый intake только кнопками:
 * destination → dates → tourists → budget → confirm (без ввода телефона текстом)
 */
$startIntakeTriggers = [
    'подобрать тур',
    'подбор тура',
    'хочу в отпуск',
    'нужен тур',
    'помогите подобрать',
    'заявка на тур',
    'оставить заявку',
    'хочу поехать',
    'подберите',
];
$wantsIntake = false;
foreach ($startIntakeTriggers as $t) {
    if ($t !== '' && str_contains($hay, $t)) {
        $wantsIntake = true;
        break;
    }
}

if ($wantsIntake && ($session['flow'] ?? '') !== 'intake') {
    $session = [
        'flow' => 'intake',
        'step' => 'destination',
        'data' => [],
        'updatedAt' => time(),
    ];
    th_support_session_save($sessionId, $session);
    th_support_json(
        $sessionId,
        'intake_start',
        "Ок! Подберём тур по шагам — жмите кнопки.\n\n"
        . "1️⃣ Куда хотите поехать?",
        false,
        ['Турция', 'Египет', 'ОАЭ', 'Таиланд', 'Россия', 'Ещё не решил', 'Меню']
    );
}

if (($session['flow'] ?? '') === 'intake') {
    $step = (string) ($session['step'] ?? 'destination');
    $data = is_array($session['data'] ?? null) ? $session['data'] : [];

    // Повторное «Подобрать тур» на середине — не затираем, просим кнопку шага
    if ($wantsIntake && $step !== 'destination') {
        th_support_json(
            $sessionId,
            'intake_continue',
            "Заявка уже начата. Выберите вариант кнопкой ниже (или «Меню», чтобы начать заново).",
            false,
            $step === 'dates'
                ? ['Ближайшие 2 недели', 'Через месяц', '7–10 ночей', 'Ещё не решил', 'Меню']
                : ($step === 'tourists'
                    ? ['2 взрослых', '2 взрослых + ребёнок', '1 взрослый', 'Семья 2+2', 'Меню']
                    : ($step === 'budget'
                        ? ['До 100 тыс', 'До 150 тыс', 'До 200 тыс', 'Без ограничений', 'Меню']
                        : ['Передать менеджеру', 'Позвонить', 'Меню']))
        );
    }

    // Выход к менеджеру / звонок
    if (
        str_contains($hay, 'менеджер')
        || str_contains($hay, 'перезвон')
        || str_contains($hay, 'передать менеджеру')
        || str_contains($hay, 'живой')
        || str_contains($hay, 'позвонить')
    ) {
        $summaryParts = [];
        foreach (['destination' => 'Куда', 'dates' => 'Даты', 'tourists' => 'Туристы', 'budget' => 'Бюджет'] as $k => $label) {
            if (!empty($data[$k])) {
                $summaryParts[] = $label . ': ' . $data[$k];
            }
        }
        $summary = $summaryParts ? ("\n\nЗаявка:\n• " . implode("\n• ", $summaryParts)) : '';
        $session = th_support_session_default();
        th_support_session_save($sessionId, $session);
        th_support_json(
            $sessionId,
            'handoff',
            "Передаю менеджеру." . $summary . "\n\n"
            . "Позвоните +7 (846) 254-16-56 или нажмите кнопку звонка ниже — "
            . "перезвоним в рабочее время (обычно 15–30 минут, 9:00–21:00 МСК).",
            true,
            ['Меню', 'Контакты', 'Горящие туры']
        );
    }

    if ($step === 'destination') {
        $data['destination'] = mb_substr($message, 0, 120);
        $session['data'] = $data;
        $session['step'] = 'dates';
        th_support_session_save($sessionId, $session);
        th_support_json(
            $sessionId,
            'intake_dates',
            "Принял: «{$data['destination']}».\n\n"
            . "2️⃣ Когда хотите вылететь?",
            false,
            ['Ближайшие 2 недели', 'Через месяц', '7–10 ночей', 'На выходные', 'Ещё не решил', 'Меню']
        );
    }

    if ($step === 'dates') {
        $data['dates'] = mb_substr($message, 0, 120);
        $session['data'] = $data;
        $session['step'] = 'tourists';
        th_support_session_save($sessionId, $session);
        th_support_json(
            $sessionId,
            'intake_tourists',
            "Даты: «{$data['dates']}».\n\n"
            . "3️⃣ Сколько туристов?",
            false,
            ['2 взрослых', '2 взрослых + ребёнок', '1 взрослый', 'Семья 2+2', 'Меню']
        );
    }

    if ($step === 'tourists') {
        $data['tourists'] = mb_substr($message, 0, 120);
        $session['data'] = $data;
        $session['step'] = 'budget';
        th_support_session_save($sessionId, $session);
        th_support_json(
            $sessionId,
            'intake_budget',
            "Состав: «{$data['tourists']}».\n\n"
            . "4️⃣ Бюджет на весь тур (за всех)?",
            false,
            ['До 100 тыс', 'До 150 тыс', 'До 200 тыс', 'До 300 тыс', 'Без ограничений', 'Меню']
        );
    }

    if ($step === 'budget') {
        $data['budget'] = mb_substr($message, 0, 120);
        $session['data'] = $data;
        $session['step'] = 'confirm';
        th_support_session_save($sessionId, $session);
        th_support_json(
            $sessionId,
            'intake_confirm',
            "✅ Проверьте заявку:\n"
            . "• Куда: {$data['destination']}\n"
            . "• Даты: {$data['dates']}\n"
            . "• Туристы: {$data['tourists']}\n"
            . "• Бюджет: {$data['budget']}\n\n"
            . "Нажмите «Передать менеджеру» — свяжемся по телефону из профиля/звонка.",
            false,
            ['Передать менеджеру', 'Позвонить', 'Меню']
        );
    }

    if ($step === 'confirm') {
        // Любая кнопка кроме меню уже обработана выше как handoff; иначе повторим подтверждение
        th_support_json(
            $sessionId,
            'intake_confirm',
            "Заявка готова. Нажмите «Передать менеджеру» или «Позвонить».",
            false,
            ['Передать менеджеру', 'Позвонить', 'Меню']
        );
    }
}

// ——— FAQ / intents (как у крупных туроператоров) ———
$rules = [
    'hot' => [
        'keywords' => ['горящ', 'акци', 'скидк', 'дешев', 'горящие туры', 'last minute', 'ласт минут', 'что посоветуете', 'куда полететь'],
        'answer' => "🔥 Горящие туры — на главной в блоке «Горящие туры»: уже с датами и ценой.\n\n"
            . "Откройте карточку → сравните варианты → «Забронировать» или оставьте заявку менеджеру.\n"
            . "Нужен персональный подбор — нажмите «Подобрать тур».",
        'quick' => ['Подобрать тур', 'Цены и что входит', 'Связаться с менеджером', 'Меню'],
    ],
    'payment' => [
        'keywords' => ['оплат', 'т-касс', 'tinkoff', 'тинькофф', 'карт', 'чек', 'ссылка на оплату', 'рассрочк', 'кредит', 'сбп', 'перевод'],
        'answer' => "💳 Оплата:\n"
            . "• Онлайн картой через защищённую Т-Кассу (данные карты только на стороне банка)\n"
            . "• В офисе в Самаре\n"
            . "• Рассрочка/кредит — если доступны у туроператора по конкретному туру\n\n"
            . "После оплаты статус обновится в «Бронирования». Нужна персональная ссылка — оставьте телефон менеджеру.",
        'quick' => ['Бронирование', 'Статус заявки', 'Связаться с менеджером', 'Меню'],
    ],
    'booking' => [
        'keywords' => ['брони', 'заброни', 'как купить', 'оформить', 'заявк', 'заказать тур', 'как оформить'],
        'answer' => "📋 Как забронировать:\n"
            . "1) Найдите тур (Поиск или Горящие)\n"
            . "2) Откройте карточку → «Забронировать»\n"
            . "3) Укажите туристов и контакты\n"
            . "4) Менеджер подтвердит места (обычно до 15–30 минут) и пришлёт оплату\n\n"
            . "Без готового тура — «Подобрать тур»: соберём заявку по шагам.",
        'quick' => ['Подобрать тур', 'Оплата', 'Документы и виза', 'Меню'],
    ],
    'status' => [
        'keywords' => ['статус', 'моя бронь', 'моя заявка', 'где бронь', 'подтвержден', 'оплачен', 'номер брони', 'tvz'],
        'answer' => "📌 Статус заявки смотрите во вкладке «Бронирования».\n\n"
            . "Там видно: ожидает оплаты, оплачено, подтверждено, отменено.\n"
            . "Напишите номер брони или телефон — менеджер проверит вручную.",
        'quick' => ['Оплата', 'Отмена и возврат', 'Связаться с менеджером', 'Меню'],
    ],
    'price' => [
        'keywords' => ['цен', 'сколько стоит', 'стоимост', 'бюджет', 'дорого', 'недорого', 'что входит', 'включен', 'fuel', 'топливн', 'доплат'],
        'answer' => "💰 Цена на карточке — обычно за тур на выбранных туристов (часто 2 взрослых), с перелётом и проживанием, если это пакетный тур.\n\n"
            . "Итог может измениться из‑за дат, курса, топливного сбора или доплат оператора — перед оплатой менеджер подтвердит актуальную сумму.\n"
            . "Пришлите направление и даты — уточним варианты.",
        'quick' => ['Подобрать тур', 'Горящие туры', 'Оплата', 'Меню'],
    ],
    'documents' => [
        'keywords' => ['документ', 'паспорт', 'виза', 'загран', 'страховк', 'справк', 'приглашен', 'анкет'],
        'answer' => "🛂 Документы:\n"
            . "• Загранпаспорта всех туристов (срок действия — по правилам страны, часто 3–6 мес. после возвращения)\n"
            . "• Виза — если нужна; менеджер подскажет список и сроки\n"
            . "• Страховка часто уже в пакете — уточним по туру\n"
            . "• Для детей — свидетельства / согласия при необходимости\n\n"
            . "Напишите страну — скажем, что именно нужно.",
        'quick' => ['Виза', 'Страховка', 'Связаться с менеджером', 'Меню'],
    ],
    'visa' => [
        'keywords' => ['виза', 'шенген', 'безвиз', 'визовый'],
        'answer' => "✈️ По визам: часть направлений безвизовые или виза по прибытии, часть — заранее.\n\n"
            . "Сроки и список документов зависят от страны и гражданства. Назовите направление — подскажем общий порядок и передадим менеджеру за точным чек‑листом.",
        'quick' => ['Документы и виза', 'Подобрать тур', 'Меню'],
    ],
    'insurance' => [
        'keywords' => ['страхов', 'медицинск', 'франшиз'],
        'answer' => "🛡️ Медицинская страховка часто входит в пакетный тур. Расширенная / от невыезда — по желанию, менеджер добавит к заявке.\n"
            . "Уточните тур или направление — проверим, что уже включено.",
        'quick' => ['Документы и виза', 'Связаться с менеджером', 'Меню'],
    ],
    'cancel' => [
        'keywords' => ['отмен', 'возврат', 'вернуть деньги', 'штраф', 'перенос', 'аннуляц', 'не могу ехать'],
        'answer' => "↩️ Отмена и возврат зависят от туроператора и срока до вылета (часто есть шкала штрафов).\n\n"
            . "Напишите номер брони или телефон + причину — проверим правила и предложим: возврат, перенос или замену тура.",
        'quick' => ['Статус заявки', 'Связаться с менеджером', 'Меню'],
    ],
    'change' => [
        'keywords' => ['изменить бронь', 'поменять даты', 'замен', 'добавить туриста', 'убрать туриста'],
        'answer' => "✏️ Изменение брони (даты, состав, отель) возможно не всегда и может быть с доплатой — решает туроператор.\n"
            . "Опишите, что нужно поменять, и номер заявки — менеджер уточнит возможность.",
        'quick' => ['Статус заявки', 'Связаться с менеджером', 'Меню'],
    ],
    'flight' => [
        'keywords' => ['перелёт', 'рейс', 'багаж', 'вылет', 'аэропорт', 'трансфер', 'опоздан', 'задержк рейс', 'пересадк'],
        'answer' => "🛫 Перелёт и трансфер:\n"
            . "• В пакетном туре авиабилеты обычно уже включены — детали рейса приходят после подтверждения\n"
            . "• Трансфер аэропорт–отель — если указан в туре\n"
            . "• Багаж — по правилам авиакомпании / тарифу оператора\n\n"
            . "Нужны точные рейсы по вашей брони — напишите номер заявки.",
        'quick' => ['Статус заявки', 'Отель и питание', 'Меню'],
    ],
    'hotel' => [
        'keywords' => ['отел', 'номер', 'питание', 'all inclusive', 'всё включено', 'завтрак', 'ультра', 'пляж', 'звезд'],
        'answer' => "🏨 Отель и питание:\n"
            . "Тип номера и питание (RO / BB / HB / AI / UAI) указаны в карточке тура.\n"
            . "Смена номера или питания после брони — только через оператора, часто с доплатой.\n\n"
            . "Напишите название отеля или номер заявки — уточним детали.",
        'quick' => ['Цены и что входит', 'Подобрать тур', 'Меню'],
    ],
    'kids' => [
        'keywords' => ['ребён', 'дети', 'детск', 'младен', 'возраст реб'],
        'answer' => "👶 С детьми: укажите возраст каждого ребёнка в поиске — цена и размещение зависят от возраста.\n"
            . "Младенцы и доп. место в номере — по правилам отеля/оператора.\n"
            . "Можете пройти «Подобрать тур» — спросим состав семьи в заявке.",
        'quick' => ['Подобрать тур', 'Документы и виза', 'Меню'],
    ],
    'dates' => [
        'keywords' => ['дат', 'когда вылет', 'на сколько ноч', 'календар', 'на майские', 'на новый год'],
        'answer' => "📅 Даты: во вкладке «Поиск» шаг «Когда» — окно дат вылета, шаг «Ночей» — длительность.\n"
            . "В горящих турах даты уже на карточке.\n"
            . "Гибкие даты — напишите месяц/праздник в «Подобрать тур».",
        'quick' => ['Подобрать тур', 'Горящие туры', 'Меню'],
    ],
    'departure' => [
        'keywords' => ['вылет из', 'город вылета', 'из самары', 'из москвы', 'из уфы', 'из казани', 'без перелета'],
        'answer' => "✈️ Город вылета выбирается в поиске на первом шаге. Список стран подстраивается под рейсы из этого города — не все направления доступны из каждого города.\n"
            . "Нужен подбор «из моего города» — укажите город в «Подобрать тур».",
        'quick' => ['Подобрать тур', 'Поиск', 'Меню'],
    ],
    'bonus' => [
        'keywords' => ['бонус', 'кэшбэк', 'кэшбек', 'cashback', 'скидка постоянн', 'программ лоял'],
        'answer' => "⭐ Бонусы и акции отображаются в профиле (если доступны для вашего аккаунта).\n"
            . "Условия списания/начисления зависят от акции и типа тура — менеджер подскажет по конкретной брони.",
        'quick' => ['Оплата', 'Связаться с менеджером', 'Меню'],
    ],
    'app' => [
        'keywords' => ['приложен', 'app store', 'google play', 'ios', 'android', 'не работает', 'вылетает', 'ошибка в прилож'],
        'answer' => "📱 Вы в приложении TravelHub.\n"
            . "• Поиск туров — вкладка «Поиск»\n"
            . "• Горящие — на главной\n"
            . "• Заявки — «Бронирования»\n"
            . "• Избранное — сердечко\n\n"
            . "Если что-то сломалось: опишите экран и что нажали — передадим в поддержку. Или обновите приложение / перезапустите.",
        'quick' => ['Подобрать тур', 'Связаться с менеджером', 'Меню'],
    ],
    'contacts' => [
        'keywords' => ['контакт', 'телефон', 'адрес', 'офиговор', 'офис', 'где вы', 'график', 'время работ', 'email', 'почта'],
        'answer' => "📞 Контакты TravelHub:\n"
            . "• Телефон: +7 (846) 254-16-56\n"
            . "• Email: hello@travelhub63.ru\n"
            . "• Офис: Самара, Московское шоссе, 81Б, ТЦ «Парк Хаус»\n"
            . "• Обычно на связи ежедневно 9:00–21:00 (МСК)\n\n"
            . "Можно сразу «Связаться с менеджером» из чата.",
        'quick' => ['Связаться с менеджером', 'Подобрать тур', 'Меню'],
    ],
    'complaint' => [
        'keywords' => ['жалоб', 'претенз', 'недоволен', 'обман', 'возврат денег срочно', 'плохое обслуживание'],
        'answer' => "Нам важно разобраться. Опишите ситуацию и номер заявки (если есть) — передадим старшему менеджеру.\n"
            . "Также можно написать на hello@travelhub63.ru с темой «Претензия».",
        'quick' => ['Связаться с менеджером', 'Статус заявки', 'Контакты'],
        'handoff' => true,
    ],
    'handoff' => [
        'keywords' => ['менеджер', 'перезвон', 'живой человек', 'оператор', 'помогите', 'консультант', 'свяжитесь', 'передать менеджеру', 'позвонить'],
        'answer' => "Передаю менеджеру.\n\n"
            . "Позвоните +7 (846) 254-16-56 или нажмите кнопку звонка в чате — "
            . "перезвоним в рабочее время, обычно за 15–30 минут.\n"
            . "Или оформите «Подобрать тур» кнопками — передадим готовую заявку.",
        'quick' => ['Подобрать тур', 'Контакты', 'Меню'],
        'handoff' => true,
    ],
];

$bestKey = 'fallback';
$bestScore = 0;
foreach ($rules as $key => $rule) {
    $tokens = is_array($rule['keywords'] ?? null) ? $rule['keywords'] : [];
    $score = 0;
    foreach ($tokens as $token) {
        $token = (string) $token;
        if ($token === '') {
            continue;
        }
        if (str_contains($hay, $token)) {
            // Более длинный токен = точнее («горящие туры» важнее «тур»)
            $score += max(2, function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') : strlen($token));
        }
    }
    if ($score > $bestScore) {
        $bestScore = $score;
        $bestKey = $key;
    }
}

$intent = $bestKey;
$reply = "Выберите тему кнопкой ниже — так я отвечу точнее.\n\n"
    . "«Подобрать тур» — заявка по шагам. «Связаться с менеджером» — сразу человек.";
$quick = th_support_menu_replies();
$handoff = false;

if ($bestScore > 0 && isset($rules[$bestKey])) {
    $rule = $rules[$bestKey];
    $reply = (string) ($rule['answer'] ?? $reply);
    $quick = is_array($rule['quick'] ?? null) ? $rule['quick'] : $quick;
    $handoff = !empty($rule['handoff']);
}

// Телефон в любом сообщении → усиливаем handoff
$phoneInMsg = th_support_extract_phone($message);
if ($phoneInMsg && ($intent === 'handoff' || $intent === 'fallback' || $intent === 'complaint')) {
    $handoff = true;
    $reply .= "\n\nТелефон «{$phoneInMsg}» принял — менеджер сможет перезвонить.";
}

if ($intent === 'handoff' || str_contains($hay, 'перезвон') || str_contains($hay, 'менеджер')) {
    $handoff = true;
}
if ($intent === 'fallback') {
    $handoff = true;
    $quick = ['Подобрать тур', 'Горящие туры', 'Оплата', 'Связаться с менеджером', 'Меню'];
}

$storePlainText = filter_var(getenv('TH_SUPPORT_CHAT_LOG_STORE_TEXT') ?: ($_ENV['TH_SUPPORT_CHAT_LOG_STORE_TEXT'] ?? '0'), FILTER_VALIDATE_BOOLEAN);
$ctx = [
    'sessionIdHash' => th_log_hash($sessionId),
    'channel' => $channel,
    'intent' => $intent,
    'handoff' => $handoff,
    'messageLen' => function_exists('mb_strlen') ? mb_strlen($message) : strlen($message),
    'messageHash' => th_log_hash($message),
    'matchScore' => $bestScore,
];
if ($storePlainText) {
    $ctx['messagePreview'] = mb_substr($message, 0, 160);
}
th_log_write('support_chat', 'message_processed', $ctx, 'info');

th_support_json($sessionId, $intent, $reply, $handoff, $quick);
