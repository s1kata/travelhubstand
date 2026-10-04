<?php
declare(strict_types=1);
/**
 * Курорты Турции (TourVisor region id) для страницы акций.
 * Совпадают с VIP-поиском: см. backend/components/vip_hotels_tour_search.php
 * (Анталья 20, Белек 21, Кемер 22). Подмешиваются, если ответ /regions неполный.
 */
return [
    ['id' => 20, 'name' => 'Анталья'],
    ['id' => 21, 'name' => 'Белек'],
    ['id' => 22, 'name' => 'Кемер'],
];
