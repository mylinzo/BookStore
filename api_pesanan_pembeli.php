<?php
/**
 * API untuk Pesanan Pembeli - Ambil data pesanan dengan status real-time
 */

// Set timeout to prevent hanging
set_time_limit(30);

// Header JSON dan error handling
@header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', '0');

// debug log every request (append)
file_put_contents(__DIR__ . '/api_debug.log', "\n----\n" . date('c') . " REQUEST: " . json_encode([ 'get'=>$_GET, 'post'=>$_POST ]) . "\nSESSION: " . json_encode($_SESSION) . "\n", FILE_APPEND);

try {
    session_start();
    @include 'koneksi.php';

    if (!isset($conn) || !$conn) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }

    // Check authentication
    if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pembeli') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $id_pembeli = $_SESSION['id_user'];
    $action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
    
    if (empty($action)) {
        throw new Exception('Action tidak dikirim');
    }

    switch ($action) {
        case 'list':
            $stmt = mysqli_prepare($conn, "
                SELECT 
                    p.id_pesanan, p.id_penjual, p.id_pembeli, p.id_produk, p.quantity,
                    p.total_harga, p.status, p.metode_pembayaran, p.kurir, p.bukti_pembayaran,
                    p.nomor_resi, p.alamat_pengiriman, p.catatan_pembeli, p.catatan_penjual,
                    p.biaya_pengiriman,
                    p.created_at, p.updated_at,
                    pr.nama_buku, pr.foto_produk, pr.harga as harga_satuan,
                    u.nama as nama_penjual, u.email as email_penjual, u.alamat_penjual,
                    up.nama as nama_pembeli, up.email as email_pembeli
                FROM pesanan p
                JOIN produk pr ON p.id_produk = pr.id_produk
                JOIN users u ON p.id_penjual = u.id_user
                JOIN users up ON p.id_pembeli = up.id_user
                WHERE p.id_pembeli = ?
                ORDER BY p.created_at DESC
            ");
            
            if (!$stmt) throw new Exception('Query error: ' . mysqli_error($conn));
            mysqli_stmt_bind_param($stmt, "i", $id_pembeli);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $orders = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $orders, 'count' => count($orders)]);
            mysqli_stmt_close($stmt);
            break;

        case 'detail':
            $id_pesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id_pesanan <= 0) throw new Exception('ID pesanan tidak valid');
            
            $stmt = mysqli_prepare($conn, "
                SELECT 
                    p.id_pesanan, p.id_penjual, p.id_pembeli, p.id_produk, p.quantity,
                    p.total_harga, p.status, p.metode_pembayaran, p.kurir, p.bukti_pembayaran,
                    p.nomor_resi, p.alamat_pengiriman, p.catatan_pembeli, p.catatan_penjual,
                    p.biaya_pengiriman,
                    p.created_at, p.updated_at,
                    pr.nama_buku, pr.foto_produk, pr.harga as harga_satuan,
                    u.nama as nama_penjual, u.email as email_penjual, u.alamat_penjual,
                    up.nama as nama_pembeli, up.email as email_pembeli
                FROM pesanan p
                JOIN produk pr ON p.id_produk = pr.id_produk
                JOIN users u ON p.id_penjual = u.id_user
                JOIN users up ON p.id_pembeli = up.id_user
                WHERE p.id_pesanan = ? AND p.id_pembeli = ?
            ");
            
            if (!$stmt) throw new Exception('Query error: ' . mysqli_error($conn));
            mysqli_stmt_bind_param($stmt, "ii", $id_pesanan, $id_pembeli);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $order = mysqli_fetch_assoc($result);
            
            if ($order) {
                echo json_encode(['success' => true, 'data' => $order]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
            }
            mysqli_stmt_close($stmt);
            break;

        case 'by_status':
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            $valid_status = ['menunggu_pembayaran', 'dibayar', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak', 'menunggu_verifikasi'];
            
            if (!in_array($status, $valid_status)) {
                throw new Exception('Status tidak valid: ' . $status);
            }
            
            $stmt = mysqli_prepare($conn, "
                SELECT 
                    p.id_pesanan, p.id_penjual, p.quantity, p.total_harga, p.status,
                    p.nomor_resi, p.created_at,
                    pr.nama_buku, u.nama as nama_penjual
                FROM pesanan p
                JOIN produk pr ON p.id_produk = pr.id_produk
                JOIN users u ON p.id_penjual = u.id_user
                WHERE p.id_pembeli = ? AND p.status = ?
                ORDER BY p.created_at DESC
            ");
            
            if (!$stmt) throw new Exception('Query error: ' . mysqli_error($conn));
            mysqli_stmt_bind_param($stmt, "is", $id_pembeli, $status);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $orders = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            
            echo json_encode(['success' => true, 'status' => $status, 'data' => $orders, 'count' => count($orders)]);
            mysqli_stmt_close($stmt);
            break;

        case 'stats':
            $stmt = mysqli_prepare($conn, "
                SELECT status, COUNT(*) as count, SUM(total_harga) as total
                FROM pesanan
                WHERE id_pembeli = ?
                GROUP BY status
            ");
            
            if (!$stmt) throw new Exception('Query error: ' . mysqli_error($conn));
            mysqli_stmt_bind_param($stmt, "i", $id_pembeli);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $stats = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $stats[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $stats]);
            mysqli_stmt_close($stmt);
            break;

        default:
            throw new Exception('Action tidak didukung: ' . htmlspecialchars($action));
    }

} catch (Exception $e) {
    @http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $t) {
    @http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Critical error: ' . $t->getMessage()]);
}

if (isset($conn)) {
    @mysqli_close($conn);
}
?>
