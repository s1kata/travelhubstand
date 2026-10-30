<?php
/**
 * Общая серверная валидация лидов (имя + телефон РФ).
 * Используется uon-lead, office-lead, uon-booking, registration и др. — не дублировать правила.
 *
 * Правила (синхронизировать с frontend/js/th-lead-capture.js):
 * - Имя: одно слово, кириллица, без латиницы и «мусорных» слов
 * - Телефон: мобильный РФ +79XXXXXXXXX, без «одинаковых» и очевидных последовательностей
 * - Город (регистрация): реальное РФ/СНГ название, без фантазий вроде «Луна»
 * - Пароль (регистрация): не короче 8 и не из списка очевидных
 */
declare(strict_types=1);

if (!function_exists('th_lead_normalize_ru_phone')) {
    /**
     * Нормализует российский мобильный номер к виду +79XXXXXXXXX или '' если невалиден.
     */
    function th_lead_normalize_ru_phone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (!is_string($digits) || $digits === '') {
            return '';
        }
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) !== 11 || $digits[0] !== '7') {
            return '';
        }
        $rest = substr($digits, 1);
        // Только мобильные РФ: +7 9XX …
        if ($rest === '' || $rest[0] !== '9') {
            return '';
        }
        if (preg_match('/^(\d)\1{9}$/', $rest)) {
            return '';
        }
        // Очевидные последовательности / «тестовые» хвосты
        if ($rest === '9012345678' || $rest === '9876543210' || $rest === '9123456789') {
            return '';
        }
        if (preg_match('/^9(\d)\1{8}$/', $rest)) {
            return '';
        }
        return '+' . $digits;
    }
}

if (!function_exists('th_lead_validate_person_name')) {
    /**
     * @return string|null текст ошибки на русском или null если ок
     */
    function th_lead_validate_person_name(string $name, int $minLen = 2, int $maxLen = 40): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '') {
            return 'Укажите имя';
        }
        $parts = preg_split('/[\s\-]+/u', $name, 2, PREG_SPLIT_NO_EMPTY) ?: [];
        $name = (string) ($parts[0] ?? $name);
        $name = preg_replace('/[^\p{Cyrillic}\-\']/u', '', $name) ?? $name;
        if ($name === '') {
            return 'Укажите имя';
        }
        if (mb_strlen($name) < $minLen) {
            return 'Укажите корректное имя';
        }
        if (mb_strlen($name) > $maxLen) {
            return 'Имя слишком длинное';
        }
        if (!preg_match('/^[\p{Cyrillic}\-\']+$/u', $name)) {
            if (preg_match('/\p{Latin}/u', $name)) {
                return 'Укажите имя русскими буквами';
            }
            return 'Укажите корректное имя';
        }
        if (preg_match('/^(.)\1+$/u', $name)) {
            return 'Укажите корректное имя';
        }
        if (!preg_match('/[аеёиоуыэюя]/iu', $name)) {
            return 'Укажите корректное имя';
        }
        $lower = mb_strtolower($name, 'UTF-8');
        $bannedExact = ['тест', 'test', 'asdf', 'qwerty', 'admin', 'user', 'имя', 'фамилия', 'фио', 'xxx', 'null', 'none'];
        if (in_array($lower, $bannedExact, true)) {
            return 'Укажите корректное имя';
        }
        foreach (['тест', ' test', 'qwerty', 'asdf', 'admin', 'xxx'] as $b) {
            if (mb_strpos($lower, $b) !== false) {
                return 'Укажите корректное имя';
            }
        }
        return null;
    }
}

if (!function_exists('th_lead_validate_city')) {
    /**
     * Город проживания при регистрации: кириллица, без фантазийных названий.
     * @return string|null текст ошибки или null если ок / пусто (город опционален)
     */
    function th_lead_validate_city(string $city, bool $required = false): ?string
    {
        $city = trim(preg_replace('/\s+/u', ' ', $city) ?? $city);
        if ($city === '') {
            return $required ? 'Укажите город' : null;
        }
        if (mb_strlen($city) < 2) {
            return 'Укажите корректный город';
        }
        if (mb_strlen($city) > 60) {
            return 'Название города слишком длинное';
        }
        if (!preg_match('/^[\p{Cyrillic}\s\-\'.]+$/u', $city)) {
            return 'Укажите город русскими буквами';
        }
        $lower = mb_strtolower($city, 'UTF-8');
        static $fakeCities = [
            'луна', 'марс', 'юпитер', 'солнце', 'земля', 'небо', 'море', 'океан',
            'тест', 'test', 'asdf', 'qwerty', 'xxx', 'nowhere', 'нигде', 'город',
            'москва москва', 'самара самара',
        ];
        if (in_array($lower, $fakeCities, true)) {
            return 'Укажите реальный город';
        }
        // Одно «космическое»/шуточное слово без типичных городских суффиксов
        static $nonsenseExact = ['луна', 'марс', 'венера', 'плутон', 'сатурн', 'нептун', 'меркурий'];
        if (in_array($lower, $nonsenseExact, true)) {
            return 'Укажите реальный город';
        }
        return null;
    }
}

if (!function_exists('th_lead_validate_password')) {
    /**
     * @return string|null текст ошибки или null если ок
     */
    function th_lead_validate_password(string $password, int $minLen = 8): ?string
    {
        $password = trim($password);
        if ($password === '') {
            return 'Пожалуйста, введите пароль.';
        }
        if (mb_strlen($password) < $minLen) {
            return 'Пароль должен содержать не менее ' . $minLen . ' символов.';
        }
        if (mb_strlen($password) > 128) {
            return 'Пароль слишком длинный.';
        }
        $lower = mb_strtolower($password, 'UTF-8');
        static $weak = [
            'qwerty', 'qwerty1', 'qwerty12', 'qwerty123', '123456', '1234567', '12345678',
            'password', 'password1', 'passw0rd', '111111', '11111111', '000000', '00000000',
            'abcdef', 'abcdefg', 'abcd1234', 'admin', 'admin123', 'letmein', 'welcome',
            'йцукен', 'йцукенг', 'пароль', 'пароль1', 'пароль12', 'пароль123',
            'travelhub', 'travel', 'samara', 'moscow',
        ];
        if (in_array($lower, $weak, true)) {
            return 'Пароль слишком простой. Придумайте более надёжный.';
        }
        if (preg_match('/^(.)\1+$/u', $password)) {
            return 'Пароль слишком простой. Придумайте более надёжный.';
        }
        if (preg_match('/^(0123456789|9876543210|qwertyuiop|asdfghjkl|zxcvbnm)/i', $password)) {
            return 'Пароль слишком простой. Придумайте более надёжный.';
        }
        return null;
    }
}

if (!function_exists('th_lead_validate_ru_phone')) {
    /**
     * @return array{ok:bool, phone:string, error:?string}
     */
    function th_lead_validate_ru_phone(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => false, 'phone' => '', 'error' => 'Укажите телефон'];
        }
        $phone = th_lead_normalize_ru_phone($raw);
        if ($phone === '') {
            return ['ok' => false, 'phone' => '', 'error' => 'Укажите корректный мобильный телефон РФ (+7 9XX…)'];
        }
        return ['ok' => true, 'phone' => $phone, 'error' => null];
    }
}

if (!function_exists('th_lead_is_agree_accepted')) {
    /**
     * Принимает типичные значения чекбокса/JSON: true, 1, "1", "on", "yes", "true".
     * @param mixed $value
     */
    function th_lead_is_agree_accepted($value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            return $v === '1' || $v === 'on' || $v === 'yes' || $v === 'true';
        }
        return false;
    }
}

if (!function_exists('th_lead_require_agree')) {
    /**
     * @param array<string, mixed> $input
     * @return string|null текст ошибки или null если согласие есть
     */
    function th_lead_require_agree(array $input, string $key = 'agree'): ?string
    {
        if (th_lead_is_agree_accepted($input[$key] ?? null)) {
            return null;
        }
        return 'Нужно согласие на обработку персональных данных';
    }
}

if (!function_exists('th_lead_ads_accepted')) {
    /**
     * @param array<string, mixed> $input
     */
    function th_lead_ads_accepted(array $input): bool
    {
        return th_lead_is_agree_accepted($input['agree_ads'] ?? null);
    }
}

if (!function_exists('th_lead_require_terms')) {
    /**
     * @param array<string, mixed> $input
     */
    function th_lead_require_terms(array $input): ?string
    {
        if (th_lead_is_agree_accepted($input['agree_terms'] ?? null)) {
            return null;
        }
        return 'Нужно принять Пользовательское соглашение';
    }
}
