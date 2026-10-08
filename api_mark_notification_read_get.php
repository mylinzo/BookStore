<?php
/**
 * API (GET) fallback: Mark single notification as read
 * Usage: api_mark_notification_read_get.php?id_notif=123
 */
session_start();
include 'koneksi.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : 0;
$id_notif = isset($_GET['id_notif']) ? intval($_GET['id_notif']) : 0;

if (!$user_id || !$id_notif) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'message' => 'Notifications table missing']);
    exit;
}

$stmt = mysqli_prepare($conn, "UPDATE notifications SET status = 'dibaca' WHERE id_notif = ? AND user_id = ? AND status = 'belum_dibaca'");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed']);
    exit;
}

mysqli_stmt_bind_param($stmt, "ii", $id_notif, $user_id);
mysqli_stmt_execute($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'affected' => $affected]);
mysqli_close($conn);
?>