<?php
session_start();
include 'koneksi.php';

// Check authentication
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id_pembeli = $_SESSION['id_user'];
$id_pesanan = $_GET['id_pesanan'] ?? 0;

header('Content-Type: application/json');

try {
    $query = "SELECT 
                p.*,
                pr.nama as nama_produk,
                pr.harga,
                pr.deskripsi as deskripsi_produk,
                pr.foto_produk,
                u.nama as nama_penjual,
                u.email as email_penjual,
                u.no_telepon as telepon_penjual
            FROM pesanan p
            JOIN produk pr ON p.id_produk = pr.id_produk
            JOIN users u ON p.id_penjual = u.id_user
            WHERE p.id_pesanan = ? AND p.id_pembeli = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id_pesanan, $id_pembeli);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'data' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Pesanan tidak ditemukan'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>