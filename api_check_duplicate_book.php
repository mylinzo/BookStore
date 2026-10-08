<?php
session_start();
include 'koneksi.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'penjual') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['nama_buku']) || empty(trim($data['nama_buku']))) {
    echo json_encode(['success' => false, 'message' => 'Nama buku tidak valid']);
    exit;
}

$nama_buku = trim($data['nama_buku']);
$id_produk = isset($data['id_produk']) ? intval($data['id_produk']) : 0;

// Check for duplicate
if ($id_produk > 0) {
    // For edit - exclude current product
    $stmt = mysqli_prepare($conn, "SELECT id_produk FROM produk WHERE nama_buku = ? AND id_produk != ?");
    mysqli_stmt_bind_param($stmt, "si", $nama_buku, $id_produk);
} else {
    // For add - check all
    $stmt = mysqli_prepare($conn, "SELECT id_produk FROM produk WHERE nama_buku = ?");
    mysqli_stmt_bind_param($stmt, "s", $nama_buku);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    // Duplicate found
    echo json_encode([
        'success' => false,
        'duplicate' => true,
        'message' => '⚠️ Nama buku sudah ada! Gunakan nama buku yang berbeda.'
    ]);
} else {
    // No duplicate
    echo json_encode([
        'success' => true,
        'duplicate' => false,
        'message' => 'OK'
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
