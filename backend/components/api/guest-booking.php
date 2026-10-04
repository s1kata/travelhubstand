<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_number = $_POST['booking_number'] ?? '';
    $type = $_POST['type'] ?? '';
    $dt = date('Y-m-d H:i:s');
    file_put_contents(__DIR__.'/bookings.log', "$dt\t$type\t$booking_number\n", FILE_APPEND);
    header("Location: /frontend/guest-$type.php?success=1"); exit;
}
http_response_code(405);
echo "Метод не поддерживается";
