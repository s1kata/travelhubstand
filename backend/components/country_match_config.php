<?php
/**
 * Единый конфиг сопоставления стран для фронтенд-страниц.
 * Не меняет API-логику, используется только в UI/рендеринге.
 */
return [
    'names' => [
        'turkey' => 'Турция',
        'egypt' => 'Египет',
        'thailand' => 'Таиланд',
        'uae' => 'ОАЭ',
        'russia' => 'Россия',
        'china' => 'Китай',
        'vietnam' => 'Вьетнам',
        'abkhazia' => 'Абхазия',
        'armenia' => 'Армения',
        'bahrain' => 'Бахрейн',
        'cuba' => 'Куба',
        'india' => 'Индия',
        'indonesia' => 'Индонезия',
        'jordan' => 'Иордания',
        'mauritius' => 'Маврикий',
        'maldives' => 'Мальдивы',
        'montenegro' => 'Черногория',
        'oman' => 'Оман',
        'philippines' => 'Филиппины',
        'qatar' => 'Катар',
        'seychelles' => 'Сейшелы',
        'sri-lanka' => 'Шри-Ланка',
        'tanzania' => 'Танзания',
        'tunisia' => 'Тунис',
        'venezuela' => 'Венесуэла',
    ],
    // Последний резервный слой только для UI, если справочник стран недоступен.
    'fallback_ids' => (static function (): array {
        $mapFile = dirname(__DIR__) . '/config/country_promo_tourvisor_map.php';
        if (is_file($mapFile)) {
            $pack = require $mapFile;
            if (!empty($pack['slug_to_id']) && is_array($pack['slug_to_id'])) {
                return $pack['slug_to_id'];
            }
        }
        return [
            'turkey' => 4, 'egypt' => 1, 'thailand' => 2, 'uae' => 9, 'russia' => 47,
            'vietnam' => 16, 'maldives' => 8, 'sri-lanka' => 12, 'abkhazia' => 46,
        ];
    })(),
    'aliases' => [
        'abkhazia' => ['абхазия', 'abkhazia'],
        'uae' => ['оаэ', 'uae', 'united arab emirates', 'арабские эмираты', 'эмирраты'],
        'sri-lanka' => ['шри-ланка', 'шри ланка', 'sri lanka', 'sri-lanka'],
        'montenegro' => ['черногория', 'montenegro', 'crna gora'],
        'thailand' => ['таиланд', 'тайланд', 'thailand'],
        'turkey' => ['турция', 'turkey', 'turkiye', 'türkiye'],
    ],
];
