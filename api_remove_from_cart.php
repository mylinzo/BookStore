<?php
/**
 * API untuk menghapus item dari keranjang
 * Endpoint: api_remove_from_cart.php
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

// Validasi input
if ($id_produk <= 0 || $id_penjual <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Parameter tidak valid'
    ]);
    exit;
}

try {
    // Delete dari keranjang
    $delete_query = "DELETE FROM keranjang_produk WHERE id_user = ? AND id_produk = ? AND id_penjual = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt_delete, "iii", $user_id, $id_produk, $id_penjual);
    
    if (!mysqli_stmt_execute($stmt_delete)) {
        throw new Exception('Gagal menghapus item: ' . mysqli_error($conn));
    }
    
    $affected_rows = mysqli_stmt_affected_rows($stmt_delete);
    mysqli_stmt_close($stmt_delete);
    
    if ($affected_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Item tidak ditemukan di keranjang'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Item berhasil dihapus dari keranjang'
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
