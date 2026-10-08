<?php
/**
 * API untuk mendapatkan jumlah notifikasi untuk badge
 */

session_start();
require_once 'koneksi.php';
require_once 'notificationsHelper.php';

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['chat_count' => 0, 'order_count' => 0]);
    exit;
}

$user_id = $_SESSION['id_user'];

// Get notifications using the helper and return its breakdown
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'penjual';
$notif_info = getNotifications($conn, $user_id, $role);
$order_count = isset($notif_info['order']) ? $notif_info['order'] : 0;
$chat_count = isset($notif_info['chat']) ? $notif_info['chat'] : 0;

echo json_encode([
    'chat_count' => $chat_count,
    'order_count' => $order_count
]);
?>