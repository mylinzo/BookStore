<?php
session_start();
include 'koneksi.php';

// Check authentication
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id_pembeli = $_SESSION['id_user'];
$data = json_decode(file_get_contents('php://input'), true);
$id_pesanan = $data['id_pesanan'] ?? 0;

header('Content-Type: application/json');

try {
    // Check if order exists and can be cancelled
    $check_stmt = $conn->prepare("SELECT status, id_produk, quantity FROM pesanan WHERE id_pesanan = ? AND id_pembeli = ?");
    $check_stmt->bind_param("ii", $id_pesanan, $id_pembeli);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if (!$check_result->num_rows) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
        exit;
    }
    
    $order = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Check if order can be cancelled (only pending and paid orders)
    $allowed_statuses = ['menunggu_pembayaran', 'dibayar'];
    if (!in_array($order['status'], $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak dapat dibatalkan pada status ini']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update order status
        $update_stmt = $conn->prepare("UPDATE pesanan SET 
            status = 'dibatalkan',
            updated_at = NOW()
            WHERE id_pesanan = ?");
        
        $update_stmt->bind_param("i", $id_pesanan);
        $update_stmt->execute();
        
        // Restore product stock if order was paid
        if ($order['status'] === 'dibayar') {
            $restore_stmt = $conn->prepare("UPDATE produk SET stok = stok + ? WHERE id_produk = ?");
            $restore_stmt->bind_param("ii", $order['quantity'], $order['id_produk']);
            $restore_stmt->execute();
            $restore_stmt->close();
        }
        
        // If payment proof exists, add note
        $note_stmt = $conn->prepare("UPDATE pesanan SET catatan_pembeli = CONCAT(COALESCE(catatan_pembeli, ''), '\n[Dibatalkan pada ', NOW(), ']') WHERE id_pesanan = ?");
        $note_stmt->bind_param("i", $id_pesanan);
        $note_stmt->execute();
        $note_stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Pesanan berhasil dibatalkan'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>