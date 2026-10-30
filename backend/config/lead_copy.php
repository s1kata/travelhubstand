<?php
/**
 * Единые тексты заявок на сайте (честно про звонок и «не берут трубку»).
 *
 * @return array{
 *   phone_display: string,
 *   worktime_hint: string,
 *   success_message: string,
 *   form_sub: string,
 *   cta_title: string,
 *   pickup_hint: string,
 *   modal_worktime_sub: string,
 *   modal_phone_hint: string,
 *   modal_submit_label: string
 * }
 */
declare(strict_types=1);

return [
    'phone_display' => '+7 (846) 254-16-56',
    'worktime_hint' => 'Позвоним с номера +7 (846) 254-16-56 в рабочее время (9:00–21:00 МСК). Сохраните его в контакты, чтобы не пропустить.',
    'pickup_hint' => 'Если неудобно говорить по телефону — напишите в MAX (кнопка внизу экрана).',
    'form_sub' => 'Оставьте телефон и, по желанию, удобное время для звонка. Ответьте на входящий с нашего номера.',
    'success_message' => 'Заявка принята. Ждите звонок с +7 (846) 254-16-56 (9:00–21:00 МСК). Не смогли ответить — напишите в MAX.',
    'cta_title' => 'Подберём тур под ваш запрос',
    'modal_worktime_sub' => 'Звонок с +7 (846) 254-16-56 (9:00–21:00 МСК). Неудобно говорить — MAX внизу экрана.',
    'modal_phone_hint' => 'Связь по заявке — с менеджером. Рекламу отправим только если отметите согласие ниже.',
    'modal_submit_label' => '✈ Отправить заявку менеджеру',
];
