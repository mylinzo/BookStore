<?php
/**
 * API: Mark All Notifications as Read
 * Menandai semua chat dan notifikasi sebagai dibaca
 * Dipanggil ketika user membuka notification dropdown
 * 
 * Support: PEMBELI dan PENJUAL
 */

session_start();
include 'koneksi.php';

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : 0;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User ID not found']);
    exit;
}

$marked_count = 0;

// Mark semua chat sebagai dibaca untuk user ini
$sql_update_chat = "UPDATE chat SET dibaca = 1 
                    WHERE id_penerima = ? AND dibaca = 0";

if ($stmt = mysqli_prepare($conn, $sql_update_chat)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $marked_count = mysqli_stmt_affected_rows($stmt);
    }
    mysqli_stmt_close($stmt);
}

// Mark notifications table entries as read if table exists
$res = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if ($res && mysqli_num_rows($res) > 0) {
    $sql_update_notif = "UPDATE notifications SET status = 'dibaca' WHERE user_id = ? AND status = 'belum_dibaca'";
    if ($nstmt = mysqli_prepare($conn, $sql_update_notif)) {
        mysqli_stmt_bind_param($nstmt, "i", $user_id);
        if (mysqli_stmt_execute($nstmt)) {
            $marked_count += mysqli_stmt_affected_rows($nstmt);
        }
        mysqli_stmt_close($nstmt);
    }
}

// Return JSON response
http_response_code(200);
echo json_encode([
    'success' => true,
    'marked_count' => $marked_count,
    'message' => $marked_count . ' notification(s) marked as read',
    'timestamp' => date('Y-m-d H:i:s')
]);

mysqli_close($conn);
?>
