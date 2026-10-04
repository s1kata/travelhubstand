<?php
/**
 * Реквизиты оператора ПД и юридические константы сайта.
 */
declare(strict_types=1);

if (!function_exists('th_legal_operator')) {
    /**
     * @return array<string, string>
     */
    function th_legal_operator(): array
    {
        return [
            'brand' => 'Travel Hub',
            'site' => 'travelhub63.ru',
            'site_url' => 'https://travelhub63.ru',
            'operator_name' => 'Индивидуальный предприниматель Смахтин Антон Валерьевич',
            'operator_short' => 'ИП Смахтин Антон Валерьевич',
            'ogrnip' => '321631300014692',
            'inn' => '631929635998',
            'legal_address' => '443022, г. Самара, ул. Юных Пионеров, д. 120-14',
            'postal_address' => '443022, г. Самара, ул. Ново-Садовая, д. 305А, офис 105',
            'email' => 'hello@travelhub63.ru',
            'phone' => '+7 (846) 254-16-56',
            'doc_date' => '16.08.2026',
        ];
    }

    /** @return list<array{name: string, inn: string, address: string, ogrn?: string}> */
    function th_legal_third_parties(): array
    {
        return [
            [
                'name' => 'ИП Куликов Никита Александрович',
                'inn' => '631219827328',
                'address' => '443011, г. Самара, ул. Степана Разина, д. 150-2',
            ],
            [
                'name' => 'ИП Смахтина Екатерина Дмитриевна',
                'inn' => '631702624487',
                'address' => '443022, г. Самара, ул. Вольская, д. 89-65',
            ],
            [
                'name' => 'ООО «МОЙ АГЕНТ»',
                'inn' => '7714352628',
                'ogrn' => '1157746779794',
                'address' => '143966, Московская обл., г. Реутов, ул. Победы, д. 9, пом. 9, ком. 207',
            ],
        ];
    }
}
