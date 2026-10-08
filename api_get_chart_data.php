<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

// Check user login and role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'penjual') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['id_user'];
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// Query untuk mendapatkan data per bulan
$months = [];
$sales = [];
$profit = [];
$margin = [];

for ($i = 1; $i <= 12; $i++) {
    $stmt = mysqli_prepare($conn, "SELECT 
        SUM(pes.total_harga) as total_penjualan,
        SUM(pr.keuntungan * pes.quantity) as total_keuntungan,
        SUM(pr.margin * pes.quantity) as total_margin,
        COUNT(DISTINCT pes.id_pesanan) as total_transaksi
    FROM pesanan pes
    JOIN produk pr ON pes.id_produk = pr.id_produk
    WHERE pr.id_penjual = ? 
    AND pes.status IN ('selesai', 'dikirim')
    AND YEAR(pes.created_at) = ?
    AND MONTH(pes.created_at) = ?");
    
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $tahun, $i);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $months[] = date('M', mktime(0, 0, 0, $i, 1));
    $sales[] = floatval($data['total_penjualan'] ?? 0);
    $profit[] = floatval($data['total_keuntungan'] ?? 0);
    $margin[] = floatval($data['total_margin'] ?? 0);
}

// Cek apakah ada data baru (dalam 5 menit terakhir)
$stmt_check = mysqli_prepare($conn, "SELECT COUNT(*) as new_count 
    FROM pesanan pes
    JOIN produk pr ON pes.id_produk = pr.id_produk
    WHERE pr.id_penjual = ? 
    AND pes.status IN ('selesai', 'dikirim')
    AND pes.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

mysqli_stmt_bind_param($stmt_check, "i", $user_id);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$new_data = mysqli_fetch_assoc($result_check);
mysqli_stmt_close($stmt_check);

echo json_encode([
    'success' => true,
    'months' => $months,
    'sales' => $sales,
    'profit' => $profit,
    'margin' => $margin,
    'has_new_data' => ($new_data['new_count'] > 0)
]);
?>