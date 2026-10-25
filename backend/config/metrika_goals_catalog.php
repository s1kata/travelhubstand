<?php
/**
 * Каталог JS-целей reachGoal для счётчика Travel Hub.
 * Синхронизация: php backend/scripts/metrika_sync_goals.php
 *
 * @return array<int, array{id: string, name: string, funnel?: string, key?: bool}>
 */
declare(strict_types=1);

return [
    // —— Главная конверсия ——
    ['id' => 'lead_ok', 'name' => 'Заявка отправлена (lead_ok)', 'funnel' => 'lead', 'key' => true],
    ['id' => 'lead_submit_attempt', 'name' => 'Попытка отправки заявки', 'funnel' => 'lead'],
    ['id' => 'lead_validation_error', 'name' => 'Ошибка валидации формы', 'funnel' => 'lead'],
    ['id' => 'lead_err', 'name' => 'Ошибка отправки заявки', 'funnel' => 'lead'],
    ['id' => 'lead_form_start', 'name' => 'Фокус в форме лида', 'funnel' => 'lead'],
    ['id' => 'lead_cta_click', 'name' => 'Клик по CTA лида', 'funnel' => 'lead'],
    ['id' => 'card_booking_lead_ok', 'name' => 'Заявка с карточки (модалка)', 'funnel' => 'lead', 'key' => true],
    ['id' => 'booking_form_open', 'name' => 'Открыта форма бронирования', 'funnel' => 'lead'],
    ['id' => 'slow_search_lead', 'name' => 'Лид: долгий поиск', 'funnel' => 'lead'],
    ['id' => 'slow_search_lead_attempt', 'name' => 'Попытка лида при долгом поиске', 'funnel' => 'lead'],
    ['id' => 'abandon_sheet_lead', 'name' => 'Лид: лист ухода', 'funnel' => 'lead'],
    ['id' => 'empty_state_lead', 'name' => 'Лид: пустая выдача', 'funnel' => 'lead'],

    // —— Поиск туров ——
    ['id' => 'search_start', 'name' => 'Начало поиска', 'funnel' => 'search', 'key' => true],
    ['id' => 'search_results_shown', 'name' => 'Показ результатов поиска', 'funnel' => 'search', 'key' => true],
    ['id' => 'search_no_results', 'name' => 'Поиск: нет результатов', 'funnel' => 'search'],
    ['id' => 'search_error', 'name' => 'Ошибка поиска', 'funnel' => 'search', 'key' => true],

    // —— Страницы стран ——
    ['id' => 'country_search_start', 'name' => 'Поиск на странице страны', 'funnel' => 'country'],
    ['id' => 'country_search_results_shown', 'name' => 'Результаты на странице страны', 'funnel' => 'country'],
    ['id' => 'country_search_no_results', 'name' => 'Нет туров на странице страны', 'funnel' => 'country'],
    ['id' => 'country_search_error', 'name' => 'Ошибка поиска на странице страны', 'funnel' => 'country'],
    ['id' => 'country_page_open', 'name' => 'Переход на страну (клик)', 'funnel' => 'country', 'key' => true],
    ['id' => 'country_detail_open', 'name' => 'Открытие страны (детально)', 'funnel' => 'country'],
    ['id' => 'country_tour_open', 'name' => 'Тур со страницы страны', 'funnel' => 'country'],
    ['id' => 'country_page_view', 'name' => 'Просмотр страницы страны', 'funnel' => 'country'],
    ['id' => 'country_list_view', 'name' => 'Каталог стран', 'funnel' => 'country'],

    // —— Карточка тура ——
    ['id' => 'tour_detail_view', 'name' => 'Просмотр tour-detail', 'funnel' => 'tour', 'key' => true],
    ['id' => 'tour_detail_open', 'name' => 'Клик в tour-detail', 'funnel' => 'tour'],

    // —— Акции / VIP / календарь ——
    ['id' => 'promo_page_view', 'name' => 'Страница акций', 'funnel' => 'promo'],
    ['id' => 'vip_hotels_view', 'name' => 'VIP отели (список)', 'funnel' => 'vip'],
    ['id' => 'vip_hotel_detail_view', 'name' => 'VIP отель (деталь)', 'funnel' => 'vip'],
    ['id' => 'vip_hotel_open', 'name' => 'Клик VIP отель', 'funnel' => 'vip'],
    ['id' => 'calendar_page_view', 'name' => 'Календарь дат', 'funnel' => 'calendar'],
    ['id' => 'calendar_tour_open', 'name' => 'Тур из календаря', 'funnel' => 'calendar'],
    ['id' => 'calendar_day_select', 'name' => 'Выбор дня в календаре', 'funnel' => 'calendar'],
    ['id' => 'calendar_results_shown', 'name' => 'Календарь: есть результаты', 'funnel' => 'calendar'],
    ['id' => 'calendar_no_results', 'name' => 'Календарь: нет результатов', 'funnel' => 'calendar'],
    ['id' => 'calendar_map_loaded', 'name' => 'Календарь: карта загружена', 'funnel' => 'calendar'],
    ['id' => 'calendar_map_error', 'name' => 'Календарь: ошибка карты', 'funnel' => 'calendar'],

    // —— Офисы / связь ——
    ['id' => 'call_click', 'name' => 'Клик по телефону', 'funnel' => 'contact'],
    ['id' => 'max_click', 'name' => 'Клик MAX', 'funnel' => 'contact'],
    ['id' => 'chat_bar_click', 'name' => 'Клик чат-бар', 'funnel' => 'contact'],
    ['id' => 'office_detail_open', 'name' => 'Страница офиса', 'funnel' => 'office'],
    ['id' => 'offices_page_view', 'name' => 'Список офисов', 'funnel' => 'office'],
    ['id' => 'office_lead_open', 'name' => 'Лид с офиса', 'funnel' => 'office'],
    ['id' => 'office_call_click', 'name' => 'Звонок с офиса', 'funnel' => 'office'],
    ['id' => 'office_city_filter', 'name' => 'Фильтр офисов по городу', 'funnel' => 'office'],

    // —— Прочее поведение ——
    ['id' => 'site_page_view', 'name' => 'Просмотр страницы (тип)', 'funnel' => 'nav'],
    ['id' => 'site_entry', 'name' => 'Вход на сайт', 'funnel' => 'nav'],
    ['id' => 'hot_tour_click', 'name' => 'Клик горящий тур', 'funnel' => 'nav'],
    ['id' => 'compare_open', 'name' => 'Сравнение туров', 'funnel' => 'nav'],
    ['id' => 'compare_add', 'name' => 'Добавить в сравнение', 'funnel' => 'nav'],
    ['id' => 'compare_remove', 'name' => 'Убрать из сравнения', 'funnel' => 'nav'],
    ['id' => 'compare_clear', 'name' => 'Очистить сравнение', 'funnel' => 'nav'],
    ['id' => 'date_shift_click', 'name' => 'Сдвиг дат ±3 дня', 'funnel' => 'search'],
    ['id' => 'mood_click', 'name' => 'Чип настроения', 'funnel' => 'nav'],
    ['id' => 'showcase_departure_switch', 'name' => 'Смена вылета в витрине', 'funnel' => 'nav'],
    ['id' => 'promo_country_callback_click', 'name' => 'Акции: callback по стране', 'funnel' => 'promo'],
    ['id' => 'abandon_sheet_submit', 'name' => 'Лист ухода: отправка', 'funnel' => 'lead'],
    ['id' => 'abandon_sheet_timer_1', 'name' => 'Лист ухода: показ', 'funnel' => 'lead'],
    ['id' => 'abandon_sheet_skip_1', 'name' => 'Лист ухода: закрыт', 'funnel' => 'lead'],
    ['id' => 'wizard_step_1', 'name' => 'Визард шаг 1', 'funnel' => 'search'],
    ['id' => 'wizard_step_2', 'name' => 'Визард шаг 2', 'funnel' => 'search'],
    ['id' => 'wizard_step_3', 'name' => 'Визард шаг 3', 'funnel' => 'search'],
    ['id' => 'wizard_step_4', 'name' => 'Визард шаг 4', 'funnel' => 'search'],
    ['id' => 'wizard_step_5', 'name' => 'Визард шаг 5', 'funnel' => 'search'],

    // —— Перелёты (диагностика конверсии) ——
    ['id' => 'flight_load_ok', 'name' => 'Перелёт подгружен на карточке', 'funnel' => 'flight'],
    ['id' => 'flight_load_fail', 'name' => 'Перелёт не подгрузился', 'funnel' => 'flight'],
    ['id' => 'flight_stub_lead', 'name' => 'Лид: перелёт не загрузился', 'funnel' => 'flight'],
];
