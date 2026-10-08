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
$alasan = $data['alasan'] ?? '';

header('Content-Type: application/json');

try {
    // Check if order exists and can be refunded
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
    
    // Check if order can be refunded (only completed orders within 7 days)
    if ($order['status'] !== 'selesai') {
        echo json_encode(['success' => false, 'message' => 'Hanya pesanan yang sudah selesai dapat direfund']);
        exit;
    }
    
    // Check if already requested refund
    $refund_check = $conn->prepare("SELECT * FROM refund_requests WHERE id_pesanan = ?");
    $refund_check->bind_param("i", $id_pesanan);
    $refund_check->execute();
    $refund_result = $refund_check->get_result();
    
    if ($refund_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Permintaan refund sudah pernah diajukan']);
        exit;
    }
    $refund_check->close();
    
    // Create refund request
    $insert_stmt = $conn->prepare("INSERT INTO refund_requests 
        (id_pesanan, alasan, status, created_at) 
        VALUES (?, ?, 'menunggu', NOW())");
    
    $insert_stmt->bind_param("is", $id_pesanan, $alasan);
    
    if ($insert_stmt->execute()) {
        // Update order note
        $note_stmt = $conn->prepare("UPDATE pesanan SET 
            catatan_pembeli = CONCAT(COALESCE(catatan_pembeli, ''), '\n[Permintaan refund diajukan pada ', NOW(), ' - ', ?, ']')
            WHERE id_pesanan = ?");
        
        $note_stmt->bind_param("si", $alasan, $id_pesanan);
        $note_stmt->execute();
        $note_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Permintaan refund telah dikirim'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error mengajukan refund']);
    }
    
    $insert_stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>