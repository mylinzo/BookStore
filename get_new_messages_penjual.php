<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'penjual') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['id_user'];
$selected_user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;

if ($selected_user_id > 0) {
    // Get last message ID from client
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    // Get messages after last_id
    $stmt = mysqli_prepare($conn, "
        SELECT c.*, u.nama as sender_name, u.role as sender_role 
        FROM chat c 
        JOIN users u ON c.id_pengirim = u.id_user 
        WHERE ((c.id_pengirim = ? AND c.id_penerima = ?) OR (c.id_pengirim = ? AND c.id_penerima = ?))
        AND c.id_chat > ?
        ORDER BY c.created_at ASC
    ");
    
    mysqli_stmt_bind_param($stmt, "iiiii", $user_id, $selected_user_id, $selected_user_id, $user_id, $last_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $messages = [];
    $last_message_id = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
        $last_message_id = $row['id_chat'];
    }
    mysqli_stmt_close($stmt);
    
    // Mark as read
    $stmt_read = mysqli_prepare($conn, "UPDATE chat SET dibaca = 1 WHERE id_pengirim = ? AND id_penerima = ?");
    mysqli_stmt_bind_param($stmt_read, "ii", $selected_user_id, $user_id);
    mysqli_stmt_execute($stmt_read);
    mysqli_stmt_close($stmt_read);
    
    // Get user online status
    $stmt_status = mysqli_prepare($conn, "SELECT is_online, status, last_seen FROM users WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt_status, "i", $selected_user_id);
    mysqli_stmt_execute($stmt_status);
    $result_status = mysqli_stmt_get_result($stmt_status);
    $user_status = mysqli_fetch_assoc($result_status);
    mysqli_stmt_close($stmt_status);
    
    $is_online = false;
    if ($user_status) {
        if (isset($user_status['is_online']) && $user_status['is_online'] == 1) {
            $is_online = true;
        } elseif (isset($user_status['status']) && $user_status['status'] == 'online') {
            $is_online = true;
        }
    }
    
    echo json_encode([
        'messages' => $messages,
        'last_id' => $last_message_id,
        'user_status' => $is_online ? 'online' : 'offline',
        'last_seen' => $user_status ? $user_status['last_seen'] : null
    ]);
} else {
    echo json_encode(['messages' => []]);
}
?>