<?php
/**
 * Единый NAP / мессенджеры Travel Hub.
 * Подключать через require_once — не дублировать ссылки по страницам.
 *
 * Каналы:
 *   MAX      — основной чат (вместо WhatsApp)
 *   Telegram — обратная связь @TravelHub63
 *   VK       — канал / сообщество
 */
declare(strict_types=1);

if (!function_exists('th_contacts')) {
    /**
     * @return array{
     *   brand:string,phone_display:string,phone_tel:string,phone_wa:string,
     *   max_url:string,wa_url:string,tg_url:string,tg_handle:string,vk_url:string,
     *   email:string,address_primary:string,address_short:string,city_primary:string,
     *   hours_short:string,hours_full:string
     * }
     */
    function th_contacts(): array
    {
        static $c = null;
        if ($c !== null) {
            return $c;
        }
        $max = 'https://max.ru/u/f9LHodD0cOJpBbwh-zr3lqTmDxZiZMLDP-FuyTUa8fyzWO3S2tgc4_Mirnk';
        $c = [
            'brand' => 'Travel Hub',
            'phone_display' => '+7 (846) 254-16-56',
            'phone_tel' => '+78462541656',
            'phone_wa' => '78462541656',
            // MAX — основной мессенджер для быстрой связи
            'max_url' => $max,
            // legacy alias: старый код с wa_url ведёт в MAX
            'wa_url' => $max,
            // Telegram — обратная связь
            'tg_url' => 'https://t.me/TravelHub63',
            'tg_handle' => '@TravelHub63',
            // VK — канал
            'vk_url' => 'https://vk.ru/hubtravel',
            'email' => 'hello@travelhub63.ru',
            'address_primary' => 'г. Самара, Московское шоссе, 81Б (молл «Парк Хаус»)',
            'address_short' => 'Самара, Московское шоссе, 81Б',
            'city_primary' => 'Самара',
            'hours_short' => 'пн–сб 10:00–20:00, вс 10:00–16:00',
            'hours_full' => 'Пн–Сб: 10:00–20:00, Вс: 10:00–16:00',
        ];
        return $c;
    }
}
