<?php
/**
 * TransactionLogger - Fungsi Helper untuk Pencatatan Transaksi Otomatis
 * Include ini di koneksi.php atau file lain yang perlu gunakan
 */

class TransactionLogger {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Log perubahan status transaksi
     */
    public function logStatusChange($id_transaksi, $status_lama, $status_baru, $keterangan = '', $user_id = null) {
        $query = "INSERT INTO log_transaksi (id_transaksi, status_lama, status_baru, keterangan, user_id) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $query);
        if (!$stmt) {
            return ['success' => false, 'error' => mysqli_error($this->conn)];
        }
        
        mysqli_stmt_bind_param($stmt, "isssi", $id_transaksi, $status_lama, $status_baru, $keterangan, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => mysqli_stmt_error($stmt)];
        }
    }
    
    /**
     * Update status transaksi dan log perubahan
     */
    public function updateTransactionStatus($id_transaksi, $status_baru, $keterangan = '', $user_id = null) {
        // Get current status
        $current = mysqli_fetch_assoc(mysqli_query($this->conn, 
            "SELECT status FROM transaksi WHERE id_transaksi = $id_transaksi"));
        
        if (!$current) {
            return ['success' => false, 'error' => 'Transaksi tidak ditemukan'];
        }
        
        $status_lama = $current['status'];
        
        // Update status
        $update_query = "UPDATE transaksi SET status = ?, updated_at = NOW() WHERE id_transaksi = ?";
        $stmt = mysqli_prepare($this->conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $status_baru, $id_transaksi);
        
        if (!mysqli_stmt_execute($stmt)) {
            return ['success' => false, 'error' => mysqli_stmt_error($stmt)];
        }
        
        // Log the change
        $this->logStatusChange($id_transaksi, $status_lama, $status_baru, $keterangan, $user_id);
        
        // Optional: notify seller when transaction reaches important milestones
        if (in_array($status_baru, ['dibayar', 'selesai'])) {
            // find seller id for this transaction
            $tran = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT id_penjual FROM transaksi WHERE id_transaksi = $id_transaksi LIMIT 1"));
            $seller_id = $tran['id_penjual'] ?? null;
            if ($seller_id) {
                require_once 'notificationsHelper.php';
                $message = '';
                $title = '';
                if ($status_baru === 'dibayar') {
                    $title = 'Pembayaran diterima';
                    $message = 'Transaksi #' . $id_transaksi . ' telah dibayar oleh pembeli';
                } elseif ($status_baru === 'selesai') {
                    $title = 'Transaksi selesai';
                    $message = 'Transaksi #' . $id_transaksi . ' telah selesai';
                }
                if (!empty($message)) {
                    @createNotification(
                        $this->conn,
                        $seller_id,          // recipient
                        'pesanan',           // type
                        $id_transaksi,       // reference id
                        $title,
                        $message,
                        'pesanan_penjual.php?id=' . $id_transaksi
                    );
                }
            }
        }
        
        return ['success' => true, 'status_lama' => $status_lama, 'status_baru' => $status_baru];
    }
    
    /**
     * Buat transaksi baru dari pesanan
     */
    public function createTransactionFromOrder($id_pesanan, $id_pembeli, $id_penjual, $total_harga) {
        $query = "INSERT INTO transaksi (id_pesanan, id_pembeli, id_penjual, total_harga, status) 
                  VALUES (?, ?, ?, ?, 'menunggu_pembayaran')";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "iiid", $id_pesanan, $id_pembeli, $id_penjual, $total_harga);
        
        if (mysqli_stmt_execute($stmt)) {
            $id_transaksi = mysqli_insert_id($this->conn);
            return ['success' => true, 'id_transaksi' => $id_transaksi];
        } else {
            return ['success' => false, 'error' => mysqli_stmt_error($stmt)];
        }
    }
    
    /**
     * Add detail to transaction
     */
    public function addTransactionDetail($id_transaksi, $id_produk, $quantity, $harga_satuan) {
        $subtotal = $quantity * $harga_satuan;
        
        // Check if already exists
        $check = mysqli_fetch_assoc(mysqli_query($this->conn, 
            "SELECT id_detail FROM detail_transaksi WHERE id_transaksi = $id_transaksi AND id_produk = $id_produk"));
        
        if ($check) {
            // Update existing
            $update = "UPDATE detail_transaksi SET quantity = quantity + ?, subtotal = quantity * ? 
                       WHERE id_transaksi = ? AND id_produk = ?";
            $stmt = mysqli_prepare($this->conn, $update);
            mysqli_stmt_bind_param($stmt, "idii", $quantity, $harga_satuan, $id_transaksi, $id_produk);
            
            if (mysqli_stmt_execute($stmt)) {
                return ['success' => true];
            }
        } else {
            // Insert new
            $insert = "INSERT INTO detail_transaksi (id_transaksi, id_produk, quantity, harga_satuan, subtotal) 
                       VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->conn, $insert);
            mysqli_stmt_bind_param($stmt, "iiidd", $id_transaksi, $id_produk, $quantity, $harga_satuan, $subtotal);
            
            if (mysqli_stmt_execute($stmt)) {
                return ['success' => true, 'id_detail' => mysqli_insert_id($this->conn)];
            }
        }
        
        return ['success' => false, 'error' => 'Gagal menambah detail transaksi'];
    }
    
    /**
     * Record payment proof upload
     */
    public function recordPaymentProof($id_pesanan, $id_pembeli, $id_penjual, $metode, $jumlah, $filename) {
        $query = "INSERT INTO pembayaran_detail (id_pesanan, id_pembeli, id_penjual, metode_pembayaran, jumlah_pembayaran, bukti_pembayaran) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "iiisds", $id_pesanan, $id_pembeli, $id_penjual, $metode, $jumlah, $filename);
        
        if (mysqli_stmt_execute($stmt)) {
            return ['success' => true, 'id_pembayaran' => mysqli_insert_id($this->conn)];
        } else {
            return ['success' => false, 'error' => mysqli_stmt_error($stmt)];
        }
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($id_penjual = null, $limit = 100) {
        $where = '';
        if ($id_penjual) {
            $where = " WHERE id_penjual = $id_penjual";
        }
        
        $query = "SELECT 
                    t.*,
                    u.nama as nama_pembeli,
                    u.email as email_pembeli,
                    COUNT(dt.id_detail) as jumlah_produk
                  FROM transaksi t
                  LEFT JOIN users u ON t.id_pembeli = u.id_user
                  LEFT JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
                  $where
                  GROUP BY t.id_transaksi
                  ORDER BY t.tanggal DESC
                  LIMIT $limit";
        
        $result = mysqli_query($this->conn, $query);
        if ($result) {
            return mysqli_fetch_all($result, MYSQLI_ASSOC);
        }
        return [];
    }
    
    /**
     * Get status flow audit trail
     */
    public function getAuditTrail($id_transaksi) {
        $query = "SELECT 
                    log.*,
                    u.nama as user_nama
                  FROM log_transaksi log
                  LEFT JOIN users u ON log.user_id = u.id_user
                  WHERE id_transaksi = ?
                  ORDER BY tanggal_perubahan ASC";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $id_transaksi);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    /**
     * Get monthly revenue summary
     */
    public function getMonthlySummary($id_penjual, $year, $month = null) {
        $where = " WHERE t.id_penjual = $id_penjual AND YEAR(t.tanggal) = $year";
        if ($month) {
            $where .= " AND MONTH(t.tanggal) = $month";
        }
        
        $query = "SELECT 
                    COUNT(t.id_transaksi) as total_transaksi,
                    SUM(t.total_harga) as total_penjualan,
                    AVG(t.total_harga) as avg_transaksi,
                    COUNT(DISTINCT DATE(t.tanggal)) as hari_aktif
                  FROM transaksi t
                  $where
                  AND t.status IN ('dibayar', 'diproses', 'dikirim', 'selesai')";
        
        $result = mysqli_query($this->conn, $query);
        return mysqli_fetch_assoc($result);
    }
}

// Initialize if connected
if (isset($conn)) {
    $txn_logger = new TransactionLogger($conn);
}
?>
