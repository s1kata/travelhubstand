<?php
/**
 * Форма «Оставьте заявку» — обёртка над единым LeadCapture (жёсткая воронка).
 * Скрипт th-lead-capture.js подключается через design_system_head / страницу.
 */
$th_lead_id = isset($th_lead_id) ? (string) $th_lead_id : 'lead-form';
$th_lead_source = isset($th_lead_source) ? (string) $th_lead_source : 'home_bottom_lead';
$th_lead_title = isset($th_lead_title) ? (string) $th_lead_title : '';
$th_lead_sub = isset($th_lead_sub) ? (string) $th_lead_sub : 'Перезвоним в течение 15 минут. Без спама.';
$th_lead_submit = isset($th_lead_submit) ? (string) $th_lead_submit : 'Отправить заявку';
$th_lead_show_msg = !empty($th_lead_show_msg);
include __DIR__ . '/lead_capture.php';
