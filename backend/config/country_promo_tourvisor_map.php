<?php
/**
 * TourVisor countryId по slug — синхронизировано с frontend/window/promotions.php ($country_id_to_slug).
 * Используется: ссылки «Горящие туры» со страниц стран, cron promo_tours_refresh, fallback country_match_config.
 */
declare(strict_types=1);

$slugToId = [
    'turkey' => 4,
    'egypt' => 1,
    'thailand' => 2,
    'india' => 3,
    'tunisia' => 5,
    'greece' => 6,
    'indonesia' => 7,
    'maldives' => 8,
    'uae' => 9,
    'cuba' => 10,
    'dominican' => 11,
    'sri-lanka' => 12,
    'china' => 13,
    'spain' => 14,
    'cyprus' => 15,
    'vietnam' => 16,
    'montenegro' => 21,
    'mauritius' => 27,
    'seychelles' => 28,
    'jordan' => 29,
    'abkhazia' => 46,
    'russia' => 47,
    'armenia' => 53,
    'bahrain' => 59,
    'venezuela' => 90,
    /* Направления вне promotions.php — ID из справочника Tourvisor (при сомнении резолвится по API на странице) */
    'oman' => 32,
    'philippines' => 33,
    'tanzania' => 34,
    'qatar' => 60,
];

return [
    'slug_to_id' => $slugToId,
    'unique_country_ids' => array_values(array_unique(array_values($slugToId))),
];
