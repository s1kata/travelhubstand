<?php
declare(strict_types=1);
/**
 * Fallback-курорты по countryId (TourVisor), если /regions вернул пусто или ошибку.
 * ID совпадают с TourVisor PRO API.
 */
return [
    1 => [
        ['id' => 5, 'name' => 'Хургада', 'countryId' => 1],
        ['id' => 6, 'name' => 'Шарм-эль-Шейх', 'countryId' => 1],
        ['id' => 8, 'name' => 'Марса-Алам', 'countryId' => 1],
        ['id' => 9, 'name' => 'Каир', 'countryId' => 1],
        ['id' => 10, 'name' => 'Дахаб', 'countryId' => 1],
    ],
    4 => [
        ['id' => 20, 'name' => 'Анталья', 'countryId' => 4],
        ['id' => 21, 'name' => 'Белек', 'countryId' => 4],
        ['id' => 22, 'name' => 'Кемер', 'countryId' => 4],
    ],
    16 => [
        ['id' => 104, 'name' => 'Фукуок', 'countryId' => 16],
        ['id' => 87, 'name' => 'Нячанг', 'countryId' => 16],
    ],
];
