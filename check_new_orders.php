<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'penjual') {
    echo json_encode(['new_orders' => 0]);
    exit;
}

$user_id = $_SESSION['id'];
// Check for new orders in the last 5 minutes
$query = "SELECT COUNT(*) as count FROM transaksi t 
          JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi 
          JOIN produk p ON dt.id_produk = p.id_produk 
          WHERE p.id_penjual = ? AND t.status = 'menunggu_pembayaran' 
          AND t.tanggal >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

echo json_encode(['new_orders' => $data['count'] ?? 0]);
?>