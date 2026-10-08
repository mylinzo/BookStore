<?php
/**
 * API untuk mendapatkan daftar notifikasi
 */

session_start();
require_once 'koneksi.php';
require_once 'notificationsHelper.php';

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['id_user'];
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'penjual';

// Get notifications list
$notifications = getNotificationsList($conn, $user_id, 15);

// Hitung total unread
$unread_total = 0;
foreach ($notifications as $notif) {
    if (isset($notif['dibaca']) && $notif['dibaca'] == 0) {
        $unread_total++;
    }
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_total' => $unread_total
]);
?>