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
    // Check if order exists and can be confirmed
    $check_stmt = $conn->prepare("SELECT status FROM pesanan WHERE id_pesanan = ? AND id_pembeli = ?");
    $check_stmt->bind_param("ii", $id_pesanan, $id_pembeli);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if (!$check_result->num_rows) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
        exit;
    }
    
    $order = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Check if order can be confirmed (only shipped orders)
    if ($order['status'] !== 'dikirim') {
        echo json_encode(['success' => false, 'message' => 'Pesanan belum dikirim atau sudah diterima']);
        exit;
    }
    
    // Update order status to completed
    $update_stmt = $conn->prepare("UPDATE pesanan SET 
        status = 'selesai',
        updated_at = NOW(),
        catatan_pembeli = CONCAT(COALESCE(catatan_pembeli, ''), '\n[Diterima pada ', NOW(), ']')
        WHERE id_pesanan = ?");
    
    $update_stmt->bind_param("i", $id_pesanan);
    
    if ($update_stmt->execute()) {
        // create seller notification so they see the finished transaction
        // determine seller id for this order
        $seller_id = null;
        $sstmt = $conn->prepare("SELECT id_penjual FROM pesanan WHERE id_pesanan = ? LIMIT 1");
        if ($sstmt) {
            $sstmt->bind_param("i", $id_pesanan);
            $sstmt->execute();
            $sres = $sstmt->get_result();
            if ($srow = $sres->fetch_assoc()) {
                $seller_id = intval($srow['id_penjual']);
            }
            $sstmt->close();
        }

        if ($seller_id) {
            require_once 'notificationsHelper.php';
            $msg = 'Pesanan #' . intval($id_pesanan) . ' telah selesai (transaksi selesai)';
            @createNotification(
                $conn,
                $seller_id,             // recipient
                'pesanan',              // type
                $id_pesanan,            // reference id
                'Pesanan selesai',
                $msg,
                'pesanan_penjual.php?id=' . $id_pesanan
            );
        }

        echo json_encode([
            'success' => true,
            'message' => 'Pesanan telah diselesaikan'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error mengupdate status pesanan']);
    }
    
    $update_stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>