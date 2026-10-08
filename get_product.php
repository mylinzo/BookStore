<?php
session_start();
include 'koneksi.php';

// Check if user is logged in and is penjual
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'penjual') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$id_produk = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get product data - ensure it belongs to this seller
$query = "SELECT id_produk, nama_buku, id_kategori, stok, harga, modal, margin, keuntungan, deskripsi 
          FROM produk 
          WHERE id_produk = ? AND id_penjual = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $id_produk, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $product = mysqli_fetch_assoc($result);
    header('Content-Type: application/json');
    echo json_encode($product);
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Product not found']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
