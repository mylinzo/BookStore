<?php
session_start();
include 'koneksi.php';

// Check authentication
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id_pembeli = $_SESSION['id_user'];
$status = $_GET['status'] ?? 'semua';

header('Content-Type: application/json');

try {
    // Build query
    $query = "SELECT 
                p.id_pesanan,
                p.id_produk,
                pr.nama as nama_produk,
                pr.harga,
                pr.foto_produk,
                p.quantity,
                p.total_harga,
                p.status,
                p.metode_pembayaran,
                p.nomor_resi,
                p.bukti_pembayaran,
                p.alamat_pengiriman,
                u.alamat_pembeli,
                p.catatan_pembeli,
                p.catatan_penjual,
                p.created_at,
                p.updated_at,
                u.nama as nama_penjual,
                u.email as email_penjual,
                u.alamat_penjual
            FROM pesanan p
            JOIN produk pr ON p.id_produk = pr.id_produk
            JOIN users u ON p.id_penjual = u.id_user
            WHERE p.id_pembeli = ?";
    
    $params = [$id_pembeli];
    $types = "i";
    
    if ($status !== 'semua') {
        $query .= " AND p.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $query .= " ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $orders,
        'total' => count($orders)
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>