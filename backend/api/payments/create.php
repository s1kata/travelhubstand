<?php
/**
 * API создания платежа для мобильного приложения
 * Эндпоинт: POST /backend/api/payments/create
 *
 * Принимает JSON: { provider, bookingId, amount, currency, description, returnUrl }
 * Возвращает: { success, paymentId, paymentUrl }
 */
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'payment-create.php';
