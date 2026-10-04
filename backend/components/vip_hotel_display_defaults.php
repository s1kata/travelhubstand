<?php
declare(strict_types=1);
/**
 * Тексты, фото (HTTPS) и JSON-поля для VIP-отелей Турции.
 * Используется: seed_vip_hotels.php, vip_hotels_apply_display_defaults.php, API/страница детали (обогащение).
 *
 * @return array<string, array<string, mixed>> slug => поля для UPDATE/INSERT
 */
function vip_hotel_display_defaults_by_slug(): array
{
    /** Стабильные превью: images.unsplash.com (picsum часто недоступен из РФ). */
    $img = static function (string $photoId): string {
        return 'https://images.unsplash.com/photo-' . $photoId . '?auto=format&fit=crop&w=1200&q=82';
    };

    return [
        'lara-barut-collection' => [
            'name' => 'Lara Barut Collection',
            'slug' => 'lara-barut-collection',
            'city' => 'Antalya',
            'rating' => '5*',
            'description' => 'Роскошный отель на побережье с частным пляжем и спа-центром.',
            'bio' => 'Курортный комплекс на первой береговой линии: несколько бассейнов, спа, рестораны и приватный пляж. Подходит для семейного отдыха и пар.',
            'cuisine' => 'Турецкая и международная кухня, шведский стол и à la carte.',
            'meal_plan' => 'Ultra All Inclusive / All Inclusive',
            'location' => 'Лара, район Антальи',
            'beach_type' => 'Песчаный пляж, пологий вход в море',
            'distance_to_airport' => 'Порядка 10–15 км до аэропорта Антальи',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1540541338287-41700207dee6'),
                $img('1542314831-d07562f7a3d3'),
            ],
            'features' => ['СПА и wellness', 'Несколько бассейнов', 'Детский клуб', 'Анимация', 'Фитнес'],
            'detailed_info' => [
                'infrastructure' => 'Бассейны, спа-центр, рестораны, бары, конференц-зоны.',
                'entertainment' => 'Вечерние шоу, спорт на пляже, водные виды спорта по сезону.',
                'spa' => 'Хаммам, массаж, процедуры по записи.',
                'for_children' => 'Детский клуб, мини-аквапарк (по сезону), игровые зоны.',
            ],
        ],
        'mardan-palace' => [
            'name' => 'Mardan Palace',
            'slug' => 'mardan-palace',
            'city' => 'Antalya',
            'rating' => '5*',
            'description' => 'Легендарный дворец с крупнейшим бассейном в Европе.',
            'bio' => 'Иконический отель премиум-класса: архитектура в стиле дворца, огромный бассейн, парк и высокий уровень сервиса.',
            'cuisine' => 'Авторская и интернациональная кухня, несколько ресторанов.',
            'meal_plan' => 'All Inclusive / по концепции отеля',
            'location' => 'Кунду / Лара, Анталья',
            'beach_type' => 'Собственный пляж, понтоны',
            'distance_to_airport' => 'Около 15 км до аэропорта Антальи',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1566073771259-6a8506099945'),
                $img('1571896349842-33c89424de2d'),
            ],
            'features' => ['Парк территории', 'Крупнейший бассейн', 'Премиум-сервис', 'СПА', 'Гольф рядом (по сезону)'],
            'detailed_info' => [
                'infrastructure' => 'Дворцовый комплекс, сады, несколько бассейнов, приватный пляж.',
                'entertainment' => 'Живая музыка, шоу-программы, спорт.',
                'spa' => 'Роскошный спа-центр, процедуры для восстановления.',
                'for_children' => 'Семейные номера и зоны отдыха; детские программы уточняйте на ресепшене.',
            ],
        ],
        'nirvana-cosmopolitan' => [
            'name' => 'Nirvana Cosmopolitan',
            'slug' => 'nirvana-cosmopolitan',
            'city' => 'Antalya',
            'rating' => '5*',
            'description' => 'Современный люксовый курорт с отличным сервисом.',
            'bio' => 'Современный high-rise курорт в центре Антальи с видом на море и город, пляж через дорогу или шаттл (актуально уточнять).',
            'cuisine' => 'Рестораны высокой кухни и кафе.',
            'meal_plan' => 'По концепции отеля (AI / HB и др.)',
            'location' => 'Центр Антальи, набережная',
            'beach_type' => 'Городской пляж / трансфер до пляжа',
            'distance_to_airport' => 'Около 15 км',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1551882547-7f02e0e61838'),
                $img('1618773905601-09c91d2a2aeb'),
            ],
            'features' => ['Панорамный вид', 'Руфтоп-бассейны', 'СПА', 'Центр города'],
            'detailed_info' => [
                'infrastructure' => 'Несколько бассейнов на крыше, фитнес, рестораны.',
                'entertainment' => 'Ночная жизнь Антальи рядом.',
                'spa' => 'Wellness-зона с процедурами.',
                'for_children' => 'Семейные номера; детские зоны уточняйте при бронировании.',
            ],
        ],
        'rixos-downtown-antalya' => [
            'name' => 'Rixos Downtown Antalya',
            'slug' => 'rixos-downtown-antalya',
            'city' => 'Antalya',
            'rating' => '5*',
            'description' => 'Отель в центре Антальи с видом на море.',
            'bio' => 'Отель сети Rixos в деловом и туристическом центре: удобно для прогулок, шопинга и экскурсий.',
            'cuisine' => 'Турецкая и мировая кухня.',
            'meal_plan' => 'По концепции отеля',
            'location' => 'Анталья, центр',
            'beach_type' => 'Пляж: шаттл / близость к морю (уточняйте при бронировании)',
            'distance_to_airport' => 'Около 15 км',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1520250497591-112f2f40a3f4'),
                $img('1440775970968-d067a6f205d0'),
            ],
            'features' => ['Бренд Rixos', 'Центр города', 'Бассейны', 'СПА'],
            'detailed_info' => [
                'infrastructure' => 'Номерной фонд, рестораны, бассейн, фитнес.',
                'entertainment' => 'Марина и старый город рядом.',
                'spa' => 'СПА и массаж.',
                'for_children' => 'Семейные номера.',
            ],
        ],
        'titanic-deluxe-lara' => [
            'name' => 'Titanic Deluxe Lara',
            'slug' => 'titanic-deluxe-lara',
            'city' => 'Antalya',
            'rating' => '5*',
            'description' => 'Большой семейный комплекс «всё включено» премиум-класса.',
            'bio' => 'Крупный курортный комплекс с аквазоной, множеством бассейнов и развлечениями для семей.',
            'cuisine' => 'Шведский стол и тематические рестораны.',
            'meal_plan' => 'Ultra All Inclusive',
            'location' => 'Лара, Анталья',
            'beach_type' => 'Широкий песчаный пляж',
            'distance_to_airport' => 'Около 12–18 км',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1576678927489-39bc6a96553a'),
                $img('1580619306748-99fe550aa754'),
            ],
            'features' => ['Аквапарк', 'Семейные номера', 'Анимация', 'СПА'],
            'detailed_info' => [
                'infrastructure' => 'Аквазона, бассейны, рестораны, спорт.',
                'entertainment' => 'Дневная и вечерняя анимация.',
                'spa' => 'СПА-центр.',
                'for_children' => 'Детский клуб, аквагорки.',
            ],
        ],
        'voyage-kundu-hotel' => [
            'name' => 'Voyage Kundu Hotel',
            'slug' => 'voyage-kundu-hotel',
            'city' => 'Antalya',
            'rating' => '5*',
            'description' => 'Популярный отель с аквапарком и анимацией.',
            'bio' => 'Популярный семейный отель на курорте Кунду: аквапарк, пляж и активная программа.',
            'cuisine' => 'Разнообразное питание, детское меню.',
            'meal_plan' => 'All Inclusive',
            'location' => 'Кунду, Анталья',
            'beach_type' => 'Песок',
            'distance_to_airport' => 'Около 10–15 км',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1596431477882-876ec374fe37'),
                $img('1507525428034-b723cf961d3e'),
            ],
            'features' => ['Аквапарк', 'Анимация', 'Детский клуб', 'Пляж'],
            'detailed_info' => [
                'infrastructure' => 'Аквапарк, бассейны, рестораны.',
                'entertainment' => 'Шоу, спорт, игры.',
                'spa' => 'СПА по записи.',
                'for_children' => 'Аквапарк и клуб.',
            ],
        ],
        'rixos-premium-belek' => [
            'name' => 'Rixos Premium Belek',
            'slug' => 'rixos-premium-belek',
            'city' => 'Belek',
            'rating' => '5*',
            'description' => 'Премиум-курорт в Белеке с гольф-полями.',
            'bio' => 'Премиум-курорт Rixos в Белеке: гольф-инфраструктура рядом, высокий уровень сервиса и зелёная территория.',
            'cuisine' => 'Авторская кухня и гастрономические вечера.',
            'meal_plan' => 'По концепции отеля (премиум AI)',
            'location' => 'Белек',
            'beach_type' => 'Песчаный пляж',
            'distance_to_airport' => 'Около 30–40 км до Антальи',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1582719478250-c89cae4dc85b'),
                $img('1502301100355-f9067ac3ce0f'),
            ],
            'features' => ['Гольф рядом', 'Премиум-сервис', 'СПА', 'Пляж'],
            'detailed_info' => [
                'infrastructure' => 'Парк, бассейны, рестораны, пляж.',
                'entertainment' => 'Спорт, вечерние программы.',
                'spa' => 'Wellness.',
                'for_children' => 'Семейные виллы и номера.',
            ],
        ],
        'maxx-royal-belek' => [
            'name' => 'Maxx Royal Belek',
            'slug' => 'maxx-royal-belek',
            'city' => 'Belek',
            'rating' => '5*',
            'description' => 'Эксклюзивный отель только для взрослых.',
            'bio' => 'Adults only концепция, приватность, гастрономия и сервис премиум-класса в Белеке.',
            'cuisine' => 'Fine dining и авторские рестораны.',
            'meal_plan' => 'По концепции отеля',
            'location' => 'Белек',
            'beach_type' => 'Пляж first line',
            'distance_to_airport' => 'Около 30–40 км',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1578683010238-dfdefdff3c4a'),
                $img('1615460549969-de7b9632f080'),
            ],
            'features' => ['Только для взрослых', 'Премиум-пляж', 'СПА', 'Гастрономия'],
            'detailed_info' => [
                'infrastructure' => 'Виллы и suites, рестораны, пляжный клуб.',
                'entertainment' => 'DJ, вечеринки (по календарю отеля).',
                'spa' => 'СПА и wellness.',
                'for_children' => 'Концепция 16+ / 18+ — уточняйте при бронировании.',
            ],
        ],
        'cornelia-diamond-golf' => [
            'name' => 'Cornelia Diamond Golf',
            'slug' => 'cornelia-diamond-golf',
            'city' => 'Belek',
            'rating' => '5*',
            'description' => 'Гольф-резорт мирового класса.',
            'bio' => 'Отель с акцентом на гольф и отдых на природе Белека: поля, тренировочные зоны и спокойная атмосфера.',
            'cuisine' => 'Рестораны на территории, международная кухня.',
            'meal_plan' => 'All Inclusive',
            'location' => 'Белек',
            'beach_type' => 'Пляж отеля',
            'distance_to_airport' => 'Около 30–40 км',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1535139262979-36761fd06b22'),
                $img('1544551763-931ef942bc45'),
            ],
            'features' => ['Гольф', 'СПА', 'Пляж', 'Бассейны'],
            'detailed_info' => [
                'infrastructure' => 'Гольф-поля рядом, клубный дом, бассейны.',
                'entertainment' => 'Гольф-турниры и обучение.',
                'spa' => 'Восстановление после игры.',
                'for_children' => 'Семейные программы по сезону.',
            ],
        ],
        'lykia-world-olive-village' => [
            'name' => 'Lykia World Olive Village',
            'slug' => 'lykia-world-olive-village',
            'city' => 'Kemer',
            'rating' => '5*',
            'description' => 'Семейный курорт с аквапарком в Кемере.',
            'bio' => 'Большой семейный курорт в Кемере: аквапарк, зелёная территория и развлечения для детей и взрослых.',
            'cuisine' => 'Шведский стол, детское меню.',
            'meal_plan' => 'All Inclusive',
            'location' => 'Кемер',
            'beach_type' => 'Галька / песок (участок)',
            'distance_to_airport' => 'Около 50–60 км до Антальи',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'images' => [
                $img('1570011545824-10ac3764ef24'),
                $img('1596434304485-8fc7d4dc28e1'),
            ],
            'features' => ['Аквапарк', 'Семейные номера', 'Анимация', 'Пляж'],
            'detailed_info' => [
                'infrastructure' => 'Аквапарк, бассейны, рестораны, пляж.',
                'entertainment' => 'Анимация, спорт.',
                'spa' => 'СПА.',
                'for_children' => 'Аквапарк, клуб, игры.',
            ],
        ],
    ];
}

/**
 * Подмешивает дефолты для известных slug, если в БД пустые картинки, короткий bio = description и т.д.
 *
 * @param array<string, mixed> $hotel Строка после json_decode(images/features/detailed_info)
 * @return array<string, mixed>
 */
function vip_hotels_enrich_hotel_array(array $hotel): array
{
    $defaults = vip_hotel_display_defaults_by_slug();
    $slug = isset($hotel['slug']) ? (string) $hotel['slug'] : '';
    if ($slug === '' || !isset($defaults[$slug])) {
        return $hotel;
    }
    $def = $defaults[$slug];

    $images = isset($hotel['images']) && is_array($hotel['images']) ? $hotel['images'] : [];
    $needImages = $images === [];
    if (!$needImages) {
        foreach ($images as $u) {
            if (!is_string($u) || trim($u) === '') {
                $needImages = true;
                break;
            }
            if (stripos($u, 'picsum.photos') !== false) {
                $needImages = true;
                break;
            }
        }
    }
    if ($needImages && !empty($def['images']) && is_array($def['images'])) {
        $hotel['images'] = $def['images'];
    }

    foreach (['cuisine', 'meal_plan', 'location', 'beach_type', 'distance_to_airport', 'check_in_time', 'check_out_time'] as $key) {
        $cur = isset($hotel[$key]) ? trim((string) $hotel[$key]) : '';
        $fallback = isset($def[$key]) ? trim((string) $def[$key]) : '';
        if ($cur === '' && $fallback !== '') {
            $hotel[$key] = $def[$key];
        }
    }

    $desc = isset($hotel['description']) ? trim((string) $hotel['description']) : '';
    $defDesc = isset($def['description']) ? trim((string) $def['description']) : '';
    if ($desc === '' && $defDesc !== '') {
        $hotel['description'] = $def['description'];
    }

    $bio = isset($hotel['bio']) ? trim((string) $hotel['bio']) : '';
    $defBio = isset($def['bio']) ? trim((string) $def['bio']) : '';
    if ($defBio !== '') {
        if ($bio === '' || ($bio === $desc && mb_strlen($defBio) > mb_strlen($bio) + 15)) {
            $hotel['bio'] = $def['bio'];
        }
    }

    $features = isset($hotel['features']) && is_array($hotel['features']) ? $hotel['features'] : [];
    $features = array_values(array_filter($features, static function ($x): bool {
        return is_string($x) ? trim($x) !== '' : (bool) $x;
    }));
    if ($features === [] && !empty($def['features']) && is_array($def['features'])) {
        $hotel['features'] = $def['features'];
    }

    $di = isset($hotel['detailed_info']) && is_array($hotel['detailed_info']) ? $hotel['detailed_info'] : [];
    $diNonEmpty = false;
    foreach ($di as $v) {
        if (is_string($v) && trim($v) !== '') {
            $diNonEmpty = true;
            break;
        }
    }
    if (!$diNonEmpty && !empty($def['detailed_info']) && is_array($def['detailed_info'])) {
        $hotel['detailed_info'] = $def['detailed_info'];
    }

    return $hotel;
}
