<?php
/**
 * Фон карточки «популярные направления» по slug страницы на сайте.
 */
declare(strict_types=1);

$hero = '/frontend/window/img/hero/e978c0767c0fe7bc778596c86b2b54f3%201.png';
$list = '/frontend/window/img/countries-list';

return [
    'turkey' => '/frontend/window/img/турция/ostrovok-filters-4-10.jpg',
    'egypt' => '/frontend/window/img/египет/photo_2025-11-27_17-02-33.jpg',
    'uae' => '/frontend/window/img/ОАЭ/293d346edb7b418fb57d0370087191ae.jpg',
    'thailand' => '/frontend/window/img/таиланд/f6abf1e77961201063281c7d41fea1ef.jpg',
    /** Главная «Сочи» — курортное фото (страница направления — russia.php). */
    'russia' => '/frontend/window/img/promo-departure-cities/sochi.png',
    'maldives' => $list . '/maldives.png',
    'montenegro' => $list . '/montenegro.png',
    'seychelles' => $list . '/seychelles.png',
    'india' => $list . '/india.png',
    'indonesia' => $list . '/indonesia.png',
    'sri-lanka' => $list . '/sri-lanka.png',
    'tunisia' => $list . '/tunisia.png',
    'vietnam' => $list . '/vietnam.png',
    'china' => $list . '/china.png',
    'cuba' => $list . '/cuba.png',
    'jordan' => $list . '/jordan.png',
    'oman' => $list . '/oman.png',
    'bahrain' => $list . '/bahrain.png',
    'qatar' => $list . '/qatar.png',
    'armenia' => $list . '/armenia.png',
    'abkhazia' => $list . '/abkhazia.png',
    'mauritius' => $list . '/mauritius.png',
    'philippines' => $list . '/philippines.png',
    'tanzania' => $list . '/tanzania.png',
    'venezuela' => $list . '/venezuela.png',
    '_default' => $hero,
];
