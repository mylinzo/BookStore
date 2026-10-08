<?php
/**
 * API untuk update quantity di keranjang
 * Endpoint: api_update_cart.php
 * Method: POST
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
$id_produk = (int)($_POST['id_produk'] ?? 0);
$id_penjual = (int)($_POST['id_penjual'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);

// Validasi input
if ($id_produk <= 0 || $id_penjual <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Parameter tidak valid'
    ]);
    exit;
}

try {
    // Check stok
    $check_query = "SELECT stok FROM produk WHERE id_produk = ? AND id_penjual = ?";
    $stmt_check = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt_check, "ii", $id_produk, $id_penjual);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $product = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Produk tidak ditemukan'
        ]);
        exit;
    }
    
    if ($quantity > $product['stok']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Stok tidak cukup. Stok tersedia: ' . $product['stok']
        ]);
        exit;
    }
    
    // Update quantity
    $update_query = "UPDATE keranjang_produk SET quantity = ? WHERE id_user = ? AND id_produk = ? AND id_penjual = ?";
    $stmt_update = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt_update, "iiii", $quantity, $user_id, $id_produk, $id_penjual);
    
    if (!mysqli_stmt_execute($stmt_update)) {
        throw new Exception('Gagal update quantity: ' . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt_update);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quantity berhasil diupdate'
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
