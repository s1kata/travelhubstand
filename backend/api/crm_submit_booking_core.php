<?php
/**
 * Тело U-ON lead/create («обращение», см. https://api.u-on.ru/doc — POST …/lead/create.json).
 * Раньше использовался request/create («заявка»); для CRM нужны обращения.
 */
declare(strict_types=1);

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function crm_build_request_create_body(array $payload): array
{
    $ci = is_array($payload['contactInfo'] ?? null) ? $payload['contactInfo'] : [];
    $phone = crm_normalize_phone((string) ($ci['phone'] ?? ''));
    $email = trim((string) ($ci['email'] ?? ''));
    if ($phone === '' && $email === '') {
        throw new InvalidArgumentException('Для создания обращения нужен телефон или email клиента');
    }

    $nameRaw = trim((string) ($ci['name'] ?? ''));
    $nameParts = $nameRaw === '' ? [] : preg_split('/\s+/', $nameRaw, -1, PREG_SPLIT_NO_EMPTY);
    $uName = $nameParts[0] ?? '';
    $uSurname = implode(' ', array_slice($nameParts, 1));

    $isHotel = ($payload['type'] ?? '') === 'hotel';
    $ts = is_array($payload['tourSnapshot'] ?? null) ? $payload['tourSnapshot'] : [];
    $party = is_array($payload['party'] ?? null) ? $payload['party'] : [];

    $nights = (int) ($payload['nights'] ?? 0);
    if ($nights <= 0 && isset($ts['nights'])) {
        $nights = (int) $ts['nights'];
    }
    $adults = max(0, (int) ($party['adults'] ?? 0));
    $childrenAges = is_array($party['childrenAges'] ?? null) ? $party['childrenAges'] : [];
    $childrenCount = count($childrenAges);
    $partyText = $childrenCount > 0
        ? sprintf('%d взр., %d дет. (%s)', $adults, $childrenCount, implode(', ', array_map('strval', $childrenAges)))
        : sprintf('%d взр., 0 дет.', $adults);

    $tourOperator = trim((string) ($payload['tourOperator'] ?? $ts['operatorName'] ?? ''));

    $serviceDescription = $isHotel
        ? implode(' ', array_filter([
            'Отель:',
            $ts['hotelName'] ?? null,
            $ts['regionName'] ?? null,
            $nights > 0 ? $nights . ' н.' : null,
        ]))
        : implode(', ', array_filter([
            $ts['hotelName'] ?? null,
            $ts['regionName'] ?? null,
            $nights > 0 ? $nights . ' н.' : null,
        ]));
    if ($serviceDescription === '') {
        $serviceDescription = 'Тур';
    }

    $noteLines = [];
    $depCity = trim((string) ($payload['departureCity'] ?? ''));
    if ($depCity !== '') {
        $noteLines[] = 'Город вылета: ' . $depCity;
    }
    if ($nights > 0) {
        $noteLines[] = 'Ночей: ' . $nights;
    }
    $noteLines[] = 'Состав: ' . $partyText;
    if ($tourOperator !== '') {
        $noteLines[] = 'Туроператор: ' . $tourOperator;
    }
    if (!empty($ts['hotelName'])) {
        $noteLines[] = 'Отель: ' . $ts['hotelName'];
    }
    if (!empty($ts['countryName'])) {
        $noteLines[] = 'Страна: ' . $ts['countryName'];
    }
    if (!empty($ts['regionName'])) {
        $noteLines[] = 'Регион: ' . $ts['regionName'];
    }
    $spec = trim((string) ($payload['specialRequests'] ?? ''));
    if ($spec !== '') {
        $noteLines[] = 'Комментарий: ' . $spec;
    }
    if (!empty($ts['tourPackageUrl'])) {
        $noteLines[] = 'Ссылка на тур (Tourvisor): ' . $ts['tourPackageUrl'];
    }
    $note = implode("\n", array_filter($noteLines));

    $now = gmdate('Y-m-d H:i:s');
    $rDatBegin = crm_to_datetime($payload['startDate'] ?? null, $now);
    $rDatEnd = crm_to_datetime($payload['endDate'] ?? null, $now);

    $totalPrice = $payload['totalPrice'] ?? 0;
    $priceNum = is_numeric($totalPrice) ? (int) $totalPrice : 0;

    $descParts = array_filter([
        $serviceDescription,
        $depCity !== '' ? 'Вылет: ' . $depCity : '',
        $nights > 0 ? 'Ночей: ' . $nights : '',
        'Состав: ' . $partyText,
        $tourOperator !== '' ? 'Туроператор: ' . $tourOperator : '',
    ], static fn ($x) => $x !== null && $x !== '');
    $serviceSummary = implode(' | ', $descParts);
    if ($serviceSummary !== '') {
        $note .= ($note !== '' ? "\n" : '') . 'Услуга (кратко): ' . $serviceSummary;
    }
    if ($priceNum > 0) {
        $note .= ($note !== '' ? "\n" : '') . 'Сумма в приложении: ' . $priceNum;
    }

    $dateFromYmd = null;
    $dateToYmd = null;
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $rDatBegin, $m)) {
        $dateFromYmd = $m[1];
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $rDatEnd, $m)) {
        $dateToYmd = $m[1];
    }

    $body = [
        'r_id_internal' => $payload['idempotencyKey'],
        'r_dat' => $now,
        'r_dat_lead' => $now,
        'source' => $isHotel ? 'TravelHub App (Отель)' : 'TravelHub App',
        'u_name' => $uName,
        'u_surname' => $uSurname,
        'u_phone' => $phone !== '' ? $phone : null,
        'u_phone_mobile' => $phone !== '' ? $phone : null,
        'u_email' => $email !== '' ? $email : null,
        'note' => $note,
    ];

    if ($dateFromYmd !== null) {
        $body['date_from'] = $dateFromYmd;
    }
    if ($dateToYmd !== null) {
        $body['date_to'] = $dateToYmd;
    }
    if ($priceNum > 0) {
        $body['budget'] = $priceNum;
    }

    return array_filter($body, static fn ($v) => $v !== null);
}

function crm_normalize_phone(string $phone): string
{
    $raw = trim($phone);
    if ($raw === '') {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
        return '+7' . substr($digits, 1);
    }
    if (strlen($digits) === 10) {
        return '+7' . $digits;
    }
    if (strlen($digits) >= 11 && strlen($digits) <= 15) {
        return '+' . $digits;
    }

    return '';
}

/**
 * @param mixed $s
 */
function crm_to_datetime($s, string $now): string
{
    if (!is_string($s) || trim($s) === '') {
        return $now;
    }
    $trimmed = trim($s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
        return $trimmed . ' 00:00:00';
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $trimmed, $m)) {
        $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);

        return $m[3] . '-' . $mo . '-' . $d . ' 00:00:00';
    }

    return $now;
}
