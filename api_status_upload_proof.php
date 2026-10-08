<?php
session_start();
include 'koneksi.php';
require_once 'notificationsHelper.php';

// Check authentication
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id_pembeli = $_SESSION['id_user'];
$id_pesanan = $_POST['id_pesanan'] ?? 0;

header('Content-Type: application/json');

// Check if order exists and belongs to user
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

// Fetch seller info for this order (to create notification later)
$seller_id = null;
$prod_id = null;
$sstmt = $conn->prepare("SELECT id_penjual, id_produk FROM pesanan WHERE id_pesanan = ? LIMIT 1");
if ($sstmt) {
    $sstmt->bind_param("i", $id_pesanan);
    $sstmt->execute();
    $sres = $sstmt->get_result();
    if ($srow = $sres->fetch_assoc()) {
        $seller_id = intval($srow['id_penjual']);
        $prod_id = isset($srow['id_produk']) ? intval($srow['id_produk']) : null;
    }
    $sstmt->close();
}

// Check if order status allows upload
if ($order['status'] !== 'menunggu_pembayaran') {
    echo json_encode(['success' => false, 'message' => 'Status pesanan tidak memungkinkan upload bukti']);
    exit;
}

// Handle file upload
if (isset($_FILES['bukti_pembayaran'])) {
    $file = $_FILES['bukti_pembayaran'];
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak didukung']);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB']);
        exit;
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = 'bukti_' . time() . '_' . $id_pesanan . '.' . $file_extension;
    $upload_path = 'uploads/pembayaran/' . $file_name;
    
    // Create directory if not exists
    if (!is_dir('uploads/pembayaran')) {
        mkdir('uploads/pembayaran', 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Update database - HANYA ubah status ke 'dibayar', JANGAN otomatis approve
        // Tunggu approval dari penjual (seller)
        $update_stmt = $conn->prepare("UPDATE pesanan SET 
            bukti_pembayaran = ?, 
            status = 'dibayar',
            updated_at = NOW()
            WHERE id_pesanan = ? AND id_pembeli = ? AND status = 'menunggu_pembayaran'");
        
        $update_stmt->bind_param("sii", $file_name, $id_pesanan, $id_pembeli);
        
        if ($update_stmt->execute()) {
            if ($update_stmt->affected_rows > 0) {
                    // Create notification for seller if available
                    $buyer_name = getUserNameSafe($conn, $id_pembeli);
                    $message = 'Pembeli ' . $buyer_name . ' mengunggah bukti pembayaran untuk Pesanan #' . intval($id_pesanan);
                    if ($seller_id) {
                        @createNotification(
                            $conn,
                            $seller_id,                // recipient
                            'pesanan',                 // type
                            $id_pesanan,               // reference id
                            'Bukti pembayaran diterima',
                            $message,
                            'pesanan_penjual.php?id=' . $id_pesanan
                        );
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Bukti pembayaran berhasil diupload. Menunggu persetujuan dari penjual.',
                        'file_name' => $file_name
                    ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Pesanan tidak dalam status "Menunggu Pembayaran"']);
            }
            
            // Send notification to seller (optional)
            // You can implement notification system here
        } else {
            echo json_encode(['success' => false, 'message' => 'Error mengupdate database']);
        }
        
        $update_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error mengupload file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
}

$conn->close();
?>