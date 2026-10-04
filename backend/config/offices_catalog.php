<?php
/**
 * Единый каталог офисов: листинг + детальные страницы.
 */
declare(strict_types=1);

require_once __DIR__ . '/../components/office_folder_photos.php';

/** URL обложки-заглушки, если в папке офиса нет фото. */
function th_office_default_cover(): string
{
    return '/frontend/window/img/hero/e978c0767c0fe7bc778596c86b2b54f3%201.png';
}

/**
 * @return list<array<string,mixed>>
 */
function th_offices_raw(): array
{
    return [
        [
            'slug' => 'samara-funsun',
            'city' => 'samara',
            'city_name' => 'Самара',
            'name' => 'Fun&Sun — Парк Хаус',
            'db_name' => 'Fun&Sun',
            'brand' => 'Fun&Sun',
            'address' => 'г. Самара, Молл «Парк Хаус», Московское шоссе, 81Б, 2 этаж (рядом с МФЦ)',
            'address_short' => 'Молл «Парк Хаус», Московское шоссе, 81Б, 2 этаж',
            'geo' => 'Самара, Московское шоссе, 81Б',
            'lat' => 53.23345,
            'lon' => 50.20090,
            'phone' => '+7 (846) 254-16-56',
            'phone_tel' => '+78462541656',
            'email' => 'hello@travelhub63.ru',
            'hours' => 'пн–сб: 10:00–20:00, вс: 10:00–16:00',
            'blurb' => 'Пляжный отдых и семейные туры. Главный офис в Парк Хаус.',
            'description' => 'Офис Fun&Sun в молле «Парк Хаус». Подбираем пляжный отдых, семейные туры и горящие предложения. Можно зайти без записи или оставить заявку — перезвоним за 15 минут.',
            'services' => ['Пляжный отдых', 'Семейные туры', 'Горящие туры', 'Активный отдых', 'Экскурсионные туры', 'SPA и wellness'],
            'photo_slug' => 'fun-sun',
            'featured' => true,
            'legacy_files' => ['samara-funsun.php'],
        ],
        [
            'slug' => 'samara-funsun-gudok',
            'city' => 'samara',
            'city_name' => 'Самара',
            'name' => 'Fun&Sun — ТЦ «Гудок»',
            'db_name' => 'Fun&Sun (ТЦ «Гудок»)',
            'brand' => 'Fun&Sun',
            'address' => 'г. Самара, ТЦ «Гудок», ул. Красноармейская, 131 (цоколь, напротив «Ленты»)',
            'address_short' => 'ТЦ «Гудок», ул. Красноармейская, 131',
            'geo' => 'Самара, Красноармейская, 131',
            'lat' => 53.19090,
            'lon' => 50.11117,
            'phone' => '+7 (846) 255-01-15',
            'phone_tel' => '+78462550115',
            'email' => 'hello@travelhub63.ru',
            'hours' => 'пн–пт: 9:00–20:00, сб–вс: 10:00–18:00',
            'blurb' => 'Удобно в центре: подбор тура без очереди.',
            'description' => 'Офис Fun&Sun в ТЦ «Гудок». Рядом с гипермаркетом «Лента» — удобно заехать по делам. Поможем выбрать тур под бюджет и даты.',
            'services' => ['Пляжный отдых', 'Семейные туры', 'Горящие туры', 'Раннее бронирование', 'Визовая поддержка'],
            'photo_slug' => 'fun-sun-gudok',
            'featured' => false,
            'legacy_files' => ['samara-funsun-gudok.php', 'samara-anex.php'],
        ],
        [
            'slug' => 'samara-anex-moskovskoe',
            'city' => 'samara',
            'city_name' => 'Самара',
            'name' => 'Anex Tour — Московское шоссе',
            'db_name' => 'Anex Tour (Московское шоссе, 81Б)',
            'brand' => 'Anex Tour',
            'address' => 'г. Самара, Московское шоссе, 81Б',
            'address_short' => 'Московское шоссе, 81Б',
            'geo' => 'Самара, Московское шоссе, 81Б',
            'lat' => 53.23355,
            'lon' => 50.20120,
            'phone' => '+7 (846) 255-25-63',
            'phone_tel' => '+78462552563',
            'email' => 'hello@travelhub63.ru',
            'hours' => 'пн–сб: 10:00–20:00, вс: 10:00–16:00',
            'blurb' => 'Горящие туры и семейный отдых.',
            'description' => 'Офис Anex Tour на Московском шоссе, 81Б. Горящие туры, семейный отдых и сопровождение до вылета.',
            'services' => ['Горящие туры', 'Семейные путешествия', 'Экскурсионные программы', 'Корпоративный отдых', 'Визовая поддержка'],
            'photo_slug' => 'anex-tour-moskovskoe-81b',
            'featured' => true,
            'legacy_files' => ['samara-anex-moskovskoe.php'],
        ],
        [
            'slug' => 'samara-anex-apelsin',
            'city' => 'samara',
            'city_name' => 'Самара',
            'name' => 'Anex Tour — ТЦ «Апельсин»',
            'db_name' => 'Anex Tour (ТЦ «Апельсин»)',
            'brand' => 'Anex Tour',
            'address' => 'г. Самара, ТЦ «Апельсин», ул. Ново-Садовая, 305А (1 этаж, офис 105)',
            'address_short' => 'ТЦ «Апельсин», ул. Ново-Садовая, 305А',
            'geo' => 'Самара, Ново-Садовая, 305А',
            'lat' => 53.21255,
            'lon' => 50.17785,
            'phone' => '+7 (846) 955-01-70',
            'phone_tel' => '+78469550170',
            'email' => 'hello@travelhub63.ru',
            'hours' => 'ежедневно 10:00–19:00',
            'blurb' => 'Anex Tour в ТЦ «Апельсин» — подбор туров и авиабилетов.',
            'description' => 'Офис Anex Tour в торговом центре «Апельсин» на Ново-Садовой, 305А. Подбор туров, авиа- и ж/д билетов, горящие предложения. Удобная остановка «ТЦ Апельсин».',
            'services' => ['Горящие туры', 'Авиа- и ж/д билеты', 'Семейные путешествия', 'Экскурсионные программы', 'Визовая поддержка', 'Туры в кредит'],
            'photo_slug' => 'anex-apelsin',
            'featured' => false,
            'legacy_files' => ['samara-anex-apelsin.php'],
        ],
        [
            'slug' => 'samara-coral',
            'city' => 'samara',
            'city_name' => 'Самара',
            'name' => 'Coral Travel — ТЦ «Эль Рио»',
            'db_name' => 'Coral Travel',
            'brand' => 'Coral Travel',
            'address' => 'г. Самара, ТЦ «Эль Рио», Московское шоссе, 205 (3 этаж, у «Детского мира»)',
            'address_short' => 'ТЦ «Эль Рио», Московское шоссе, 205',
            'geo' => 'Самара, Московское шоссе, 205',
            'lat' => 53.22780,
            'lon' => 50.19640,
            'phone' => '+7 (846) 250-03-06',
            'phone_tel' => '+78462500306',
            'email' => 'hello@travelhub63.ru',
            'hours' => 'пн–сб 10:00–20:00, вс 11:00–20:00',
            'blurb' => 'Международный туроператор Coral Travel.',
            'description' => 'Офис Coral Travel в ТЦ «Эль Рио». Туры в Турцию, Египет, Грецию и другие направления — с личной консультацией менеджера.',
            'services' => ['Туры в Турцию', 'Отдых в Египте', 'Греция и Европа', 'Экзотические направления', 'Семейный отдых', 'Раннее бронирование'],
            'photo_slug' => 'coral-travel',
            'featured' => true,
            'legacy_files' => ['samara-coral.php'],
        ],
        [
            'slug' => 'moscow-coral-elite',
            'city' => 'moscow',
            'city_name' => 'Москва',
            'name' => 'Coral Elite Service',
            'db_name' => 'Coral Elite Service',
            'brand' => 'Coral Elite',
            'address' => 'г. Москва, Первомайская ул., 42, этаж 1',
            'address_short' => 'Первомайская ул., 42, этаж 1',
            'geo' => 'Москва, Первомайская ул., 42',
            'lat' => 55.794376,
            'lon' => 37.798078,
            'phone' => '+7 (495) 660-36-66',
            'phone_tel' => '+74956603666',
            'email' => 'moscow@travelhub63.ru',
            'hours' => 'ежедневно 10:00–20:00',
            'blurb' => 'Премиум-сервис в Москве.',
            'description' => 'Coral Elite Service на Первомайской — премиум-подбор туров, персональный менеджер и сопровождение для взыскательных клиентов.',
            'services' => ['VIP обслуживание', 'Эксклюзивные туры', 'Бизнес-путешествия', 'Семейный отдых премиум', 'Консьерж-сервис', 'Персональный менеджер'],
            'photo_slug' => 'coral-elite-service',
            'featured' => true,
            'legacy_files' => ['moscow-coral-elite.php'],
        ],
        [
            'slug' => 'moscow-anex',
            'city' => 'moscow',
            'city_name' => 'Москва',
            'name' => 'Anex Tour',
            'db_name' => 'Anex Tour',
            'brand' => 'Anex Tour',
            'address' => 'г. Москва, Первомайская ул., 42, этаж 1',
            'address_short' => 'Первомайская ул., 42, этаж 1',
            'geo' => 'Москва, Первомайская ул., 42',
            'lat' => 55.794376,
            'lon' => 37.798078,
            'phone' => '+7 (499) 322-02-89',
            'phone_tel' => '+74993220289',
            'email' => 'moscow@travelhub63.ru',
            'hours' => 'пн–пт: 9:00–21:00, сб–вс: 10:00–18:00',
            'blurb' => 'Семейный отдых и надёжные пакеты Anex.',
            'description' => 'Офис Anex Tour в Москве на Первомайской, 42. Семейный отдых, пакетные туры и помощь с документами.',
            'services' => ['Семейные туры', 'Горящие предложения', 'Пляжный отдых', 'Экскурсионные туры', 'Визовая поддержка'],
            'photo_slug' => 'anex-tour',
            'featured' => false,
            'legacy_files' => ['moscow-anex.php'],
        ],
    ];
}

function th_office_page_url(string $slug): string
{
    return '/frontend/window/offices/office.php?slug=' . rawurlencode($slug);
}

/**
 * @return array<string,mixed>|null
 */
function th_office_by_slug(string $slug): ?array
{
    $slug = strtolower(trim($slug));
    foreach (th_offices_catalog() as $o) {
        if (($o['slug'] ?? '') === $slug) {
            return $o;
        }
    }
    return null;
}

/**
 * @return list<array<string,mixed>>
 */
function th_offices_catalog(): array
{
    static $list = null;
    if ($list !== null) {
        return $list;
    }

    $list = [];
    foreach (th_offices_raw() as $o) {
        $photos = get_office_photos_from_folder($o['city'], $o['db_name'], $o['photo_slug']);
        $cover = $photos[0]['image_url'] ?? th_office_default_cover();
        $gallery = array_map(static function ($p) {
            return $p['image_url'];
        }, $photos);
        $o['cover'] = $cover;
        $o['gallery'] = $gallery;
        $o['photos'] = $photos;
        $o['photos_count'] = count($photos);
        $o['page_url'] = th_office_page_url((string) $o['slug']);
        $list[] = $o;
    }

    return $list;
}

/**
 * @return list<array<string,mixed>>
 */
function th_offices_by_city(string $city): array
{
    $city = strtolower(trim($city));
    return array_values(array_filter(th_offices_catalog(), static function ($o) use ($city) {
        return ($o['city'] ?? '') === $city;
    }));
}

/**
 * Точки для интерактивной карты офисов (Yandex Maps JS API).
 *
 * @return list<array{name:string,lat:float,lon:float,geo:string,address:string,phone:string,page_url:string}>
 */
function th_offices_map_points(string $city): array
{
    $points = [];
    foreach (th_offices_by_city($city) as $o) {
        $points[] = [
            'name' => (string) ($o['name'] ?? ''),
            'lat' => isset($o['lat']) ? (float) $o['lat'] : 0.0,
            'lon' => isset($o['lon']) ? (float) $o['lon'] : 0.0,
            'geo' => (string) ($o['geo'] ?? $o['address'] ?? ''),
            'address' => (string) ($o['address'] ?? ''),
            'phone' => (string) ($o['phone'] ?? ''),
            'page_url' => (string) ($o['page_url'] ?? ''),
        ];
    }

    return $points;
}
