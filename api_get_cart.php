<?php
/**
 * API untuk get karanjang items
 * Endpoint: api_get_cart.php
 */

header('Content-Type: application/json; charset=utf-8');

session_start();
include 'koneksi.php';

// Check authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu'
    ]);
    exit;
}

$user_id = $_SESSION['id_user'] ?? 0;

try {
    // Get cart items
    $query = "SELECT kp.*, p.nama_buku, p.harga, p.foto_produk, p.stok, u.nama as nama_penjual
              FROM keranjang_produk kp
              JOIN produk p ON kp.id_produk = p.id_produk
              JOIN users u ON kp.id_penjual = u.id_user
              WHERE kp.id_user = ?
              ORDER BY u.nama, p.nama_buku";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => $cart_items,
        'count' => count($cart_items)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
