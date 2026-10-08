<?php
/**
 * API: Check New Notifications secara Real-time
 * Dipanggil berkala oleh JavaScript untuk checking notifikasi baru
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
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User ID not found']);
    exit;
}

$has_new_notifications = false;
$chat_count = 0;
$order_count = 0;

// Prefer notifications table if available
$resTables = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if ($resTables && mysqli_num_rows($resTables) > 0) {
    $stmt = mysqli_prepare($conn, "SELECT tipe, COUNT(*) as count FROM notifications WHERE user_id = ? AND status = 'belum_dibaca' GROUP BY tipe");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($r)) {
            if ($row['tipe'] === 'chat') $chat_count = intval($row['count']);
            if ($row['tipe'] === 'pesanan') $order_count = intval($row['count']);
        }
        mysqli_stmt_close($stmt);
    }
    $total_notifications = $chat_count + $order_count;
    $has_new_notifications = ($total_notifications > 0);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'has_new' => $has_new_notifications,
        'chat_count' => $chat_count,
        'order_count' => $order_count,
        'total_count' => $total_notifications,
        'user_role' => $user_role,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    mysqli_close($conn);
    exit;
}

// ===== UNTUK PENJUAL =====
if ($user_role === 'penjual') {
    
    // Check chat belum dibaca
    $sql_chat = "SELECT COUNT(*) as count FROM chat 
                 WHERE id_penerima = ? AND dibaca = 0";
    
    if ($stmt = mysqli_prepare($conn, $sql_chat)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $chat_count = intval($row['count']);
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Check pesanan menunggu pembayaran, sudah dibayar atau selesai
    $sql_orders = "SELECT COUNT(DISTINCT t.id_transaksi) as count 
                   FROM transaksi t 
                   JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi 
                   JOIN produk p ON dt.id_produk = p.id_produk 
                   WHERE p.id_penjual = ? AND t.status IN ('menunggu_pembayaran','dibayar','selesai')";
    
    if ($stmt = mysqli_prepare($conn, $sql_orders)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $order_count = intval($row['count']);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// ===== UNTUK PEMBELI =====
else if ($user_role === 'pembeli') {
    
    // Check chat belum dibaca
    $sql_chat = "SELECT COUNT(*) as count FROM chat 
                 WHERE id_penerima = ? AND dibaca = 0";
    
    if ($stmt = mysqli_prepare($conn, $sql_chat)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $chat_count = intval($row['count']);
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Check pesanan dengan status tertentu
    $sql_orders = "SELECT COUNT(DISTINCT t.id_transaksi) as count 
                   FROM transaksi t 
                   WHERE t.id_pembeli = ? AND t.status IN ('approve', 'dikirim')";
    
    if ($stmt = mysqli_prepare($conn, $sql_orders)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $order_count = intval($row['count']);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

$total_notifications = $chat_count + $order_count;
$has_new_notifications = ($total_notifications > 0);

// Return JSON response
http_response_code(200);
echo json_encode([
    'success' => true,
    'has_new' => $has_new_notifications,
    'chat_count' => $chat_count,
    'order_count' => $order_count,
    'total_count' => $total_notifications,
    'user_role' => $user_role,
    'timestamp' => date('Y-m-d H:i:s')
]);

mysqli_close($conn);
?>
