<?php
/**
 * Тела запросов U-ON для CRM (обращения lead/create).
 * Синхронизировано с server/crm/submitBookingCore.js и SotaCrmService.
 */
declare(strict_types=1);

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
    // РФ: 8XXXXXXXXXX / 7XXXXXXXXXX / 10 цифр без кода
    if (strlen($digits) === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
        return '+7' . substr($digits, 1);
    }
    if (strlen($digits) === 10) {
        return '+7' . $digits;
    }
    // Международный E.164 (без +)
    if (strlen($digits) >= 11 && strlen($digits) <= 15) {
        return '+' . $digits;
    }
    return '';
}

function crm_to_datetime(?string $s): string
{
    $now = date('Y-m-d H:i:s');
    if ($s === null || trim($s) === '') {
        return $now;
    }
    $trimmed = trim($s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
        return $trimmed . ' 00:00:00';
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $trimmed, $m)) {
        return sprintf('%04d-%02d-%02d 00:00:00', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    return $now;
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function crm_build_lead_create_body(array $payload): array
{
    $contact = is_array($payload['contactInfo'] ?? null) ? $payload['contactInfo'] : [];
    $phone = crm_normalize_phone((string) ($contact['phone'] ?? ''));
    $email = trim((string) ($contact['email'] ?? ''));
    if ($phone === '' && $email === '') {
        throw new InvalidArgumentException('Для создания обращения нужен телефон или email клиента');
    }

    $nameRaw = trim((string) ($contact['name'] ?? ''));
    $nameParts = $nameRaw !== '' ? preg_split('/\s+/', $nameRaw) : [];
    $uName = is_array($nameParts) && count($nameParts) > 0 ? (string) $nameParts[0] : '';
    $uSurname = is_array($nameParts) && count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';

    $type = (string) ($payload['type'] ?? 'tour');
    $isHotel = $type === 'hotel';
    $party = is_array($payload['party'] ?? null) ? $payload['party'] : [];
    $snapshot = is_array($payload['tourSnapshot'] ?? null) ? $payload['tourSnapshot'] : [];
    $nights = (int) ($payload['nights'] ?? ($snapshot['nights'] ?? 0));
    $adults = max(0, (int) ($party['adults'] ?? 0));
    $childrenAges = is_array($party['childrenAges'] ?? null) ? $party['childrenAges'] : [];
    $childrenCount = count($childrenAges);
    $partyText = $childrenCount > 0
        ? sprintf('%d взр., %d дет. (%s)', $adults, $childrenCount, implode(', ', array_map('strval', $childrenAges)))
        : sprintf('%d взр., 0 дет.', $adults);

    $tourOperator = trim((string) ($payload['tourOperator'] ?? ($snapshot['operatorName'] ?? '')));

    if ($isHotel) {
        $parts = array_filter([
            'Отель:',
            $snapshot['hotelName'] ?? null,
            $snapshot['regionName'] ?? null,
            $nights > 0 ? $nights . ' н.' : null,
        ]);
        $serviceDescription = implode(' ', $parts);
    } else {
        $parts = array_filter([
            $snapshot['hotelName'] ?? null,
            $snapshot['regionName'] ?? null,
            $nights > 0 ? $nights . ' н.' : null,
        ]);
        $serviceDescription = implode(', ', $parts) ?: 'Тур';
    }

    $noteLines = [];
    $departure = trim((string) ($payload['departureCity'] ?? ''));
    if ($departure !== '') {
        $noteLines[] = 'Город вылета: ' . $departure;
    }
    if ($nights > 0) {
        $noteLines[] = 'Ночей: ' . $nights;
    }
    $noteLines[] = 'Состав: ' . $partyText;
    if ($tourOperator !== '') {
        $noteLines[] = 'Туроператор: ' . $tourOperator;
    }
    if (!empty($snapshot['hotelName'])) {
        $noteLines[] = 'Отель: ' . $snapshot['hotelName'];
    }
    if (!empty($snapshot['countryName'])) {
        $noteLines[] = 'Страна: ' . $snapshot['countryName'];
    }
    if (!empty($snapshot['regionName'])) {
        $noteLines[] = 'Регион: ' . $snapshot['regionName'];
    }
    $special = trim((string) ($payload['specialRequests'] ?? ''));
    if ($special !== '') {
        $noteLines[] = 'Комментарий: ' . $special;
    }
    if (!empty($snapshot['tourPackageUrl'])) {
        $noteLines[] = 'Ссылка на тур (Tourvisor): ' . $snapshot['tourPackageUrl'];
    }
    $note = implode("\n", $noteLines);

    $now = date('Y-m-d H:i:s');
    $rDatBegin = crm_to_datetime(isset($payload['startDate']) ? (string) $payload['startDate'] : null);
    $rDatEnd = crm_to_datetime(isset($payload['endDate']) ? (string) $payload['endDate'] : null);

    $body = [
        'r_id_internal' => (string) ($payload['idempotencyKey'] ?? ''),
        'r_dat' => $now,
        'r_dat_lead' => $now,
        'date_from' => substr($rDatBegin, 0, 10),
        'date_to' => substr($rDatEnd, 0, 10),
        'tourist_count' => (string) $adults,
        'tourist_child_count' => (string) $childrenCount,
        'budget' => max(0, (int) round((float) ($payload['totalPrice'] ?? 0))),
        'requirements_note' => $note,
        'source' => $isHotel ? 'TravelHub App (Отель)' : 'TravelHub App',
        'u_name' => $uName,
        'u_surname' => $uSurname,
        'note' => trim('Подбор: ' . $serviceDescription . "\n" . $note),
    ];

    if ($nights > 0) {
        $body['nights_from'] = (string) $nights;
        $body['nights_to'] = (string) $nights;
    }
    if ($tourOperator !== '') {
        $body['r_tour_operator'] = $tourOperator;
    }
    if (!empty($snapshot['tourPackageUrl'])) {
        $body['r_tour_operator_link'] = $snapshot['tourPackageUrl'];
    }
    if ($phone !== '') {
        $body['u_phone'] = $phone;
        $body['u_phone_mobile'] = $phone;
    }
    if ($email !== '') {
        $body['u_email'] = $email;
    }

    return array_filter($body, static fn ($v) => $v !== null && $v !== '');
}

/** @deprecated используйте crm_build_lead_create_body */
function crm_build_request_create_body(array $payload): array
{
    return crm_build_lead_create_body($payload);
}
