<?php
/**
 * API untuk Update Status Pesanan
 * Menangani confirm delivery, upload bukti pembayaran, dan status updates
 */

// Set timeout and error handling FIRST
set_time_limit(10);
// Error suppression and JSON header
@header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', '0');

session_start();
require_once 'koneksi.php';

// Fungsi untuk membuat notifikasi
function createNotification($conn, $user_id, $type, $reference_id, $message, $link = null) {
    try {
        $query = "INSERT INTO notifikasi (id_user, tipe, pesan, data_ref, url_redirect, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, 'unread', NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "isiss", $user_id, $type, $message, $reference_id, $link);
        return mysqli_stmt_execute($stmt);
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

try {
    // Check authentication
    if (!isset($_SESSION['id_user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $id_user = (int)$_SESSION['id_user'];
    $role = $_SESSION['role'] ?? 'pembeli';
    $action = trim($_POST['action'] ?? $_GET['action'] ?? '');
    $order_id = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);
    
    // Check if action is provided
    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action is required']);
        exit;
    }
    
    // ===== CONFIRM DELIVERY - Pembeli konfirmasi pesanan diterima =====
    if ($action === 'confirm_delivery') {
        // Hanya pembeli yang bisa konfirmasi
        if ($role !== 'pembeli') {
            throw new Exception('Hanya pembeli yang dapat mengkonfirmasi pesanan');
        }
        
        // Verify order exists and belongs to user with status 'dikirim'
        $check_query = "SELECT id_pesanan, id_pembeli, id_penjual, status FROM pesanan 
                        WHERE id_pesanan = ? AND id_pembeli = ? AND status = 'dikirim'";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $order_id, $id_user);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($result);
        
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan atau tidak dapat dikonfirmasi');
        }
        
        // Mulai transaksi
        mysqli_begin_transaction($conn);
        
        try {
            // Update status pesanan menjadi selesai
            $update_query = "UPDATE pesanan SET status = 'selesai', tanggal_selesai = NOW(), updated_at = NOW() 
                            WHERE id_pesanan = ? AND id_pembeli = ? AND status = 'dikirim'";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "ii", $order_id, $id_user);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Gagal update status: ' . mysqli_error($conn));
            }
            
            if (mysqli_affected_rows($conn) === 0) {
                throw new Exception('Tidak ada perubahan status');
            }
            
            // Buat notifikasi untuk penjual
            $notif_message = 'Pesanan #' . str_pad($order_id, 8, '0', STR_PAD_LEFT) . ' telah diterima pembeli';
            $notif_link = 'pesanan_penjual.php?id=' . $order_id;
            
            createNotification(
                $conn,
                $order['id_penjual'],
                'delivery_confirmed',
                $order_id,
                $notif_message,
                $notif_link
            );
            
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'message' => 'Pesanan berhasil dikonfirmasi diterima'
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    }
    
    // ===== UPLOAD PAYMENT PROOF - Upload bukti pembayaran =====
    elseif ($action === 'upload_payment_proof') {
        // Hanya pembeli yang bisa upload bukti
        if ($role !== 'pembeli') {
            throw new Exception('Hanya pembeli yang dapat upload bukti pembayaran');
        }
        
        // Verify order exists and belongs to user with status 'menunggu_pembayaran'
        $check_query = "SELECT p.id_pesanan, p.id_pembeli, p.id_penjual, p.total_harga, p.status, 
                               pr.nama_buku, u.nama as nama_penjual
                        FROM pesanan p
                        INNER JOIN produk pr ON p.id_produk = pr.id_produk
                        INNER JOIN users u ON p.id_penjual = u.id_user
                        WHERE p.id_pesanan = ? AND p.id_pembeli = ? AND p.status = 'menunggu_pembayaran'";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $order_id, $id_user);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($result);
        
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan atau sudah diproses');
        }
        
        // Validate file
        if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File tidak valid');
        }
        
        $file = $_FILES['bukti_pembayaran'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_ext)) {
            throw new Exception('Format file tidak didukung. Gunakan JPG, PNG, atau GIF');
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception('Ukuran file terlalu besar (maksimal 5MB)');
        }
        
        // Create upload directory
        $upload_dir = 'uploads/bukti_pembayaran';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Gagal membuat direktori upload');
            }
        }
        
        // Generate filename yang aman
        $filename = 'bukti_' . $order_id . '_' . time() . '.' . $file_ext;
        $filepath = $upload_dir . '/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Gagal upload file');
        }
        
        // Mulai transaksi
        mysqli_begin_transaction($conn);
        
        try {
            $metode = $_POST['metode_pembayaran'] ?? 'qris';
            
            // Validasi dan map metode pembayaran ke ENUM database
            $metode_map = [
                'qris' => 'qris',
                'transfer_bank' => 'rekening',
                'dompet_digital' => 'dompet_digital'
            ];
            $metode = $metode_map[$metode] ?? 'qris';
            
            // Insert ke tabel pembayaran_detail
            $insert_query = "INSERT INTO pembayaran_detail 
                            (id_pesanan, id_pembeli, id_penjual, metode_pembayaran, jumlah_pembayaran, bukti_pembayaran, status_pembayaran, dibuat_pada) 
                            VALUES (?, ?, ?, ?, ?, ?, 'menunggu', NOW())";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "iiisds", 
                $order_id, 
                $id_user, 
                $order['id_penjual'], 
                $metode, 
                $order['total_harga'], 
                $filename
            );
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Gagal menyimpan data pembayaran: ' . mysqli_error($conn));
            }
            
            $id_pembayaran = mysqli_insert_id($conn);
            
            // Update status pesanan menjadi 'menunggu_verifikasi' (bukan 'dibayar')
            $update_query = "UPDATE pesanan SET 
                            status = 'menunggu_verifikasi', 
                            metode_pembayaran = ?,
                            updated_at = NOW() 
                            WHERE id_pesanan = ? AND id_pembeli = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "sii", $metode, $order_id, $id_user);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Gagal update status pesanan: ' . mysqli_error($conn));
            }
            
            if (mysqli_affected_rows($conn) === 0) {
                throw new Exception('Pesanan tidak ditemukan atau sudah diproses');
            }
            
            // Buat notifikasi untuk penjual
            $notif_message = 'Pesanan #' . str_pad($order_id, 8, '0', STR_PAD_LEFT) . ' - Bukti pembayaran sudah diupload, menunggu verifikasi';
            $notif_link = 'verifikasi_pembayaran.php?id=' . $id_pembayaran;
            
            createNotification(
                $conn,
                $order['id_penjual'],
                'payment_proof',
                $order_id,
                $notif_message,
                $notif_link
            );
            
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'message' => 'Bukti pembayaran berhasil diupload. Menunggu verifikasi dari penjual.'
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            // Hapus file jika gagal
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            throw $e;
        }
    }
    
    // ===== CANCEL ORDER - Batalkan pesanan =====
    elseif ($action === 'cancel_order') {
        // Hanya pembeli yang bisa batalkan pesanan sendiri
        if ($role !== 'pembeli') {
            throw new Exception('Hanya pembeli yang dapat membatalkan pesanan');
        }
        
        $check_query = "SELECT id_pesanan, id_pembeli, status FROM pesanan 
                        WHERE id_pesanan = ? AND id_pembeli = ? AND status = 'menunggu_pembayaran'";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $order_id, $id_user);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($result);
        
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan atau tidak dapat dibatalkan');
        }
        
        $alasan = mysqli_real_escape_string($conn, $_POST['alasan'] ?? 'Dibatalkan oleh pembeli');
        
        $update_query = "UPDATE pesanan SET 
                        status = 'dibatalkan', 
                        catatan_pembeli = CONCAT(COALESCE(catatan_pembeli, ''), ' [BATAL: ', ?, ']'),
                        updated_at = NOW() 
                        WHERE id_pesanan = ? AND id_pembeli = ? AND status = 'menunggu_pembayaran'";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sii", $alasan, $order_id, $id_user);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Gagal batalkan pesanan: ' . mysqli_error($conn));
        }
        
        if (mysqli_affected_rows($conn) === 0) {
            throw new Exception('Pesanan tidak dapat dibatalkan');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Pesanan berhasil dibatalkan'
        ]);
    }
    
    // ===== REQUEST REFUND - Minta refund =====
    elseif ($action === 'request_refund') {
        // Hanya pembeli yang bisa minta refund
        if ($role !== 'pembeli') {
            throw new Exception('Hanya pembeli yang dapat meminta refund');
        }
        
        $check_query = "SELECT id_pesanan, id_pembeli, id_penjual, status FROM pesanan 
                        WHERE id_pesanan = ? AND id_pembeli = ? AND status = 'selesai'";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $order_id, $id_user);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($result);
        
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan atau tidak dapat direfund');
        }
        
        $alasan = mysqli_real_escape_string($conn, $_POST['alasan'] ?? '');
        if (empty($alasan)) {
            throw new Exception('Alasan refund harus diisi');
        }
        
        // Cek apakah sudah ada request refund sebelumnya
        $check_refund = "SELECT id_refund FROM refund_requests WHERE id_pesanan = ? AND status IN ('menunggu', 'diproses')";
        $stmt = mysqli_prepare($conn, $check_refund);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $refund_result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($refund_result) > 0) {
            throw new Exception('Sudah ada permintaan refund yang sedang diproses');
        }
        
        // Insert refund request
        $insert_query = "INSERT INTO refund_requests (id_pesanan, alasan, status, created_at) 
                        VALUES (?, ?, 'menunggu', NOW())";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "is", $order_id, $alasan);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Gagal mengajukan refund: ' . mysqli_error($conn));
        }
        
        $id_refund = mysqli_insert_id($conn);
        
        // Update status pesanan menjadi refund
        $update_query = "UPDATE pesanan SET 
                        status = 'refund', 
                        catatan_pembeli = CONCAT(COALESCE(catatan_pembeli, ''), ' [REFUND REQUEST] ', ?),
                        updated_at = NOW() 
                        WHERE id_pesanan = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $alasan, $order_id);
        mysqli_stmt_execute($stmt);
        
        // Notify admin/penjual
        $notif_message = 'Refund request untuk pesanan #' . str_pad($order_id, 8, '0', STR_PAD_LEFT);
        $notif_link = 'refund_detail.php?id=' . $id_refund;
        
        // Notifikasi untuk penjual
        createNotification(
            $conn,
            $order['id_penjual'],
            'refund_request',
            $id_refund,
            $notif_message,
            $notif_link
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Permintaan refund berhasil diajukan'
        ]);
    }
    
    else {
        throw new Exception('Action "' . htmlspecialchars($action) . '" tidak didukung');
    }
    
    
} catch (Exception $e) {
    @http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
} catch (Throwable $e) {
    @http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Critical error: ' . $e->getMessage()
    ]);
}
?>