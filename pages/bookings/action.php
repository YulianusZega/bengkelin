<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/whatsapp.php';
requireLogin();
header('Content-Type: application/json');

$db     = getDB();
$action = $_POST['action'] ?? '';

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'Token tidak valid']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($action === 'confirm') {
    $db->prepare("UPDATE bookings SET status='confirmed' WHERE id=?")->execute([$id]);

    // Send WhatsApp confirmation
    try {
        $wa = new WhatsApp();
        if ($wa->isConfigured()) {
            $booking = $db->prepare("SELECT * FROM bookings WHERE id=?");
            $booking->execute([$id]);
            $bookingData = $booking->fetch();
            if ($bookingData) {
                $wa->sendBookingConfirm($bookingData);
                $db->prepare("UPDATE bookings SET wa_sent=1 WHERE id=?")->execute([$id]);
            }
        }
    } catch (Exception $e) { /* silent fail */ }

    echo json_encode(['success'=>true]);
} elseif ($action === 'cancel') {
    $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenal']);
}
