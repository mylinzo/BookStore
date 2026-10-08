<?php
/**
 * API untuk menandai semua notifikasi sebagai dibaca
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

// Mark all as read
$success = markNotificationsAsRead($conn, $user_id);

echo json_encode(['success' => $success]);
?>