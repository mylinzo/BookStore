<?php
/**
 * API untuk update aktivitas user
 */

session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION['id_user'];

// Update last activity (if you have last_activity column)
// Jika tidak ada kolom last_activity, bisa menggunakan kolom updated_at
$update_sql = "UPDATE users SET status = 'online', last_activity = NOW() WHERE id_user = ?";
if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
    mysqli_stmt_bind_param($update_stmt, "i", $user_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>