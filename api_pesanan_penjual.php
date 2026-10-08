<?php
/**
 * API untuk Pesanan Penjual - DISESUAIKAN dengan struktur database
 */

// Disable error output to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();
include 'koneksi.php';

// Check authentication
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penjual') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id_penjual = $_SESSION['id_user'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'list':
        listOrders();
        break;
    case 'detail':
        getDetail();
        break;
    case 'approve_pembayaran':
        approvePayment();
        break;
    case 'reject_pembayaran':
        rejectPayment();
        break;
    case 'refund_pesanan':
        refundPesanan();
        break;
    case 'update_status':
        updateStatus();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
        exit;
}

function listOrders() {
    global $conn, $id_penjual;

    try {
        $filter = $_GET['filter'] ?? 'semua';

        // Query disesuaikan dengan struktur database
        $query = "SELECT
                    p.id_pesanan,
                    p.id_pembeli,
                    u.nama as nama_pembeli,
                    u.email as email_pembeli,
                    u.alamat_pembeli,
                    p.alamat_pengiriman,
                    p.id_produk,
                    -- Anda perlu menyesuaikan dengan tabel produk Anda
                    -- Jika tabel produk bernama 'produk' dengan kolom 'nama'
                    -- Ganti 'produk' dan 'nama' sesuai struktur Anda
                    (SELECT nama_buku FROM produk WHERE id_produk = p.id_produk) as nama_produk,
                    p.quantity,
                    p.total_harga,
                    p.status,
                    p.metode_pembayaran,
                    p.kurir,
                    p.nomor_resi,
                    p.bukti_pembayaran,
                    p.created_at
                FROM pesanan p
                JOIN users u ON p.id_pembeli = u.id_user
                WHERE p.id_penjual = ?";

        $params = [$id_penjual];
        $types = "i";

        if ($filter !== 'semua') {
            $query .= " AND p.status = ?";
            $params[] = $filter;
            $types .= "s";
        }

        $query .= " ORDER BY p.created_at DESC";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute statement: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $orders]);
        $stmt->close();

    } catch (Exception $e) {
        error_log('API Error in listOrders: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getDetail() {
    global $conn;
    
    $id_pesanan = $_GET['id'] ?? 0;
    
    $stmt = $conn->prepare("SELECT 
                p.*,
                u.nama as nama_pembeli,
                u.email as email_pembeli,
                u.alamat_pembeli,
                pr.nama_buku as nama_produk,
                pr.foto_produk,
                pr.harga,
                pr.deskripsi,
                pr.id_kategori
            FROM pesanan p
            JOIN users u ON p.id_pembeli = u.id_user
            LEFT JOIN produk pr ON p.id_produk = pr.id_produk
            WHERE p.id_pesanan = ?");
    $stmt->bind_param("i", $id_pesanan);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
    }
    
    $stmt->close();
}

function approvePayment() {
    global $conn, $id_penjual;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id_pesanan = $data['id_pesanan'] ?? 0;
    
    // PENTING: Periksa apakah pesanan valid, milik penjual ini, dan statusnya TEPAT dibayar
    // Jangan biarkan auto-approve - harus manual dari seller
    $stmt = $conn->prepare("SELECT id_penjual, status, bukti_pembayaran FROM pesanan WHERE id_pesanan = ? AND id_penjual = ?");
    $stmt->bind_param("ii", $id_pesanan, $id_penjual);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result->num_rows) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak valid atau tidak milik Anda']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // STRICT CHECK: Status HARUS 'dibayar' dan ada bukti pembayaran
    if ($order['status'] !== 'dibayar') {
        echo json_encode(['success' => false, 'message' => 'Pesanan harus berstatus "Dibayar" untuk dapat disetujui. Status saat ini: ' . $order['status']]);
        exit;
    }
    
    if (empty($order['bukti_pembayaran'])) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada bukti pembayaran untuk pesanan ini']);
        exit;
    }
    
    // Update status ke diproses - HANYA setelah verifikasi semua kondisi
    $stmt = $conn->prepare("UPDATE pesanan SET status = 'diproses', updated_at = NOW() WHERE id_pesanan = ? AND id_penjual = ? AND status = 'dibayar'");
    $stmt->bind_param("ii", $id_pesanan, $id_penjual);
    
    if ($stmt->execute()) {
        // Verifikasi update berhasil
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Pesanan berhasil disetujui. Anda dapat memproses pengiriman sekarang.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Pesanan tidak bisa disetujui. Mungkin sudah ada perubahan status dari pihak lain.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyetujui pesanan: ' . $conn->error]);
    }
    
    $stmt->close();
}

function rejectPayment() {
    global $conn, $id_penjual;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id_pesanan = $data['id_pesanan'] ?? 0;
    $alasan = $data['alasan'] ?? '';
    
    // PENTING: Verifikasi pesanan milik penjual dan status tepat
    $stmt = $conn->prepare("SELECT id_penjual, status FROM pesanan WHERE id_pesanan = ? AND id_penjual = ?");
    $stmt->bind_param("ii", $id_pesanan, $id_penjual);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result->num_rows) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak valid atau tidak milik Anda']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // STRICT CHECK: Status HARUS 'dibayar' untuk bisa ditolak
    if ($order['status'] !== 'dibayar') {
        echo json_encode(['success' => false, 'message' => 'Hanya pesanan dengan status "Dibayar" yang dapat ditolak. Status saat ini: ' . $order['status']]);
        exit;
    }
    
    if (empty($alasan)) {
        echo json_encode(['success' => false, 'message' => 'Harap berikan alasan penolakan']);
        exit;
    }
    
    // Update status ke ditolak dengan alasan
    $catatan = '[DITOLAK] ' . $alasan . ' [Waktu: ' . date('Y-m-d H:i:s') . ']';
    $stmt = $conn->prepare("UPDATE pesanan SET status = 'ditolak', catatan_penjual = ?, updated_at = NOW() WHERE id_pesanan = ? AND id_penjual = ? AND status = 'dibayar'");
    $stmt->bind_param("sii", $catatan, $id_pesanan, $id_penjual);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Pesanan berhasil ditolak. Pembeli akan menerima notifikasi.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Pesanan tidak bisa ditolak. Mungkin sudah ada perubahan status dari pihak lain.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menolak pesanan: ' . $conn->error]);
    }
    
    $stmt->close();
}

function refundPesanan() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id_pesanan = $data['id_pesanan'] ?? 0;
    $alasan_refund = $data['alasan_refund'] ?? '';
    $catatan_refund = $data['catatan_refund'] ?? '';
    
    // Update status ke refund
    $catatan = "REFUND: Alasan - " . $alasan_refund;
    if (!empty($catatan_refund)) {
        $catatan .= " | Catatan - " . $catatan_refund;
    }
    
    $stmt = $conn->prepare("UPDATE pesanan SET status = 'refund', catatan_penjual = ?, updated_at = NOW() WHERE id_pesanan = ?");
    $stmt->bind_param("si", $catatan, $id_pesanan);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Refund berhasil diproses']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memproses refund: ' . $conn->error]);
    }
    
    $stmt->close();
}

function updateStatus() {
    global $conn;

    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input: ' . json_last_error_msg());
        }

        $id_pesanan = $data['id_pesanan'] ?? 0;
        $status = $data['status'] ?? '';
        $nomor_resi = $data['nomor_resi'] ?? '';
        $catatan = $data['catatan'] ?? '';
        $bayar = $data['bayar'] ?? null;
        $kembalian = $data['kembalian'] ?? null;

        // Validasi status yang diizinkan
        $allowed_statuses = ['diproses', 'dikirim', 'selesai', 'dibatalkan'];
        if (!in_array($status, $allowed_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
            return;
        }

        $query = "UPDATE pesanan SET status = ?, updated_at = NOW()";
        $params = [$status];
        $types = "s";

        if (!empty($nomor_resi)) {
            $query .= ", nomor_resi = ?";
            $params[] = $nomor_resi;
            $types .= "s";
        }

        if (!empty($catatan)) {
            // Tambahkan catatan baru ke catatan_penjual
            $query .= ", catatan_penjual = CONCAT(COALESCE(catatan_penjual, ''), '\n[', NOW(), '] ', ?)";
            $params[] = "Pengiriman: " . $catatan . " (Resi: " . $nomor_resi . ")";
            $types .= "s";
        }

        // Simpan data pembayaran cash jika ada
        if ($bayar !== null) {
            $query .= ", bayar = ?";
            $params[] = $bayar;
            $types .= "i";
        }

        if ($kembalian !== null) {
            $query .= ", kembalian = ?";
            $params[] = $kembalian;
            $types .= "i";
        }

        $query .= " WHERE id_pesanan = ?";
        $params[] = $id_pesanan;
        $types .= "i";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }

        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status pesanan berhasil diperbarui']);
        } else {
            throw new Exception('Failed to execute update: ' . $stmt->error);
        }

        $stmt->close();

    } catch (Exception $e) {
        error_log('API Error in updateStatus: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

$conn->close();
?>