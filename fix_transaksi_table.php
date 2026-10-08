<?php
/**
 * Fix Tabel Transaksi - Tambah kolom id_pesanan yang hilang
 * Jalankan di browser: http://localhost/toko-buku/toko-buku/fix_transaksi_table.php
 */

include 'koneksi.php';

$output = [];
$errors = [];

try {
    // 1. Check apakah tabel transaksi ada
    $check_table = "SHOW TABLES LIKE 'transaksi'";
    $result = mysqli_query($conn, $check_table);
    
    if (mysqli_num_rows($result) == 0) {
        // Jika tabel tidak ada, buat dari awal dengan struktur benar
        $create_transaksi = "CREATE TABLE transaksi (
            id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
            id_pesanan INT NOT NULL,
            id_pembeli INT NOT NULL,
            id_penjual INT NOT NULL,
            tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_harga DECIMAL(12, 2) NOT NULL,
            status VARCHAR(50) DEFAULT 'menunggu_pembayaran',
            metode_pembayaran VARCHAR(50),
            bukti_pembayaran VARCHAR(255),
            resi VARCHAR(100),
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE,
            FOREIGN KEY (id_pembeli) REFERENCES users(id_user) ON DELETE CASCADE,
            FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
            INDEX idx_pesanan (id_pesanan),
            INDEX idx_pembeli (id_pembeli),
            INDEX idx_penjual (id_penjual)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (mysqli_query($conn, $create_transaksi)) {
            $output[] = '✅ Tabel transaksi berhasil dibuat dengan struktur yang benar';
        } else {
            $errors[] = '❌ Gagal membuat tabel transaksi: ' . mysqli_error($conn);
        }
    } else {
        // Tabel sudah ada, check apakah ada kolom id_pesanan
        $check_column = "SHOW COLUMNS FROM transaksi LIKE 'id_pesanan'";
        $col_result = mysqli_query($conn, $check_column);
        
        if (mysqli_num_rows($col_result) == 0) {
            // Kolom tidak ada, tambahkan
            $add_column = "ALTER TABLE transaksi ADD COLUMN id_pesanan INT NOT NULL AFTER id_transaksi";
            
            if (mysqli_query($conn, $add_column)) {
                $output[] = '✅ Kolom id_pesanan berhasil ditambahkan ke tabel transaksi';
            } else {
                $errors[] = '❌ Gagal menambah kolom id_pesanan: ' . mysqli_error($conn);
            }
            
            // Jika ada data, coba update id_pesanan berdasarkan data pesanan
            $check_data = "SELECT COUNT(*) as count FROM transaksi";
            $data_result = mysqli_query($conn, $check_data);
            $data_row = mysqli_fetch_assoc($data_result);
            
            if ($data_row['count'] > 0) {
                // Ada data lama tanpa id_pesanan, coba hubungkan dengan pesanan
                $update_pesanan = "UPDATE transaksi t 
                                   JOIN pesanan p ON t.id_pembeli = p.id_pembeli 
                                   SET t.id_pesanan = p.id_pesanan 
                                   WHERE t.id_pesanan IS NULL OR t.id_pesanan = 0";
                
                if (mysqli_query($conn, $update_pesanan)) {
                    $affected = mysqli_affected_rows($conn);
                    $output[] = "✅ $affected records diperbarui dengan id_pesanan yang tepat";
                }
            }
        } else {
            $output[] = '✅ Kolom id_pesanan sudah ada di tabel transaksi';
        }
        
        // Check dan tambahkan Foreign Key jika belum ada
        $check_fk = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                     WHERE TABLE_NAME = 'transaksi' AND COLUMN_NAME = 'id_pesanan' AND REFERENCED_TABLE_NAME = 'pesanan'";
        $fk_result = mysqli_query($conn, $check_fk);
        
        if (mysqli_num_rows($fk_result) == 0) {
            // Foreign key tidak ada, tambahkan
            $add_fk = "ALTER TABLE transaksi ADD FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE";
            
            if (mysqli_query($conn, $add_fk)) {
                $output[] = '✅ Foreign Key untuk id_pesanan berhasil ditambahkan';
            } else {
                // Mungkin sudah ada atau ada error lain
                $output[] = '⚠️ Foreign Key sudah ada atau tidak dapat ditambahkan: ' . mysqli_error($conn);
            }
        } else {
            $output[] = '✅ Foreign Key untuk id_pesanan sudah ada';
        }
    }
    
    // 2. Verifikasi struktur tabel transaksi
    $verify_table = "SHOW COLUMNS FROM transaksi";
    $verify_result = mysqli_query($conn, $verify_table);
    $columns = [];
    
    while ($col = mysqli_fetch_assoc($verify_result)) {
        $columns[] = $col['Field'];
    }
    
    if (in_array('id_pesanan', $columns)) {
        $output[] = '✅ Struktur tabel transaksi sudah benar dan memiliki kolom id_pesanan';
    } else {
        $errors[] = '❌ Kolom id_pesanan masih tidak ada di tabel transaksi';
    }
    
    // 3. Perbaiki tabel detail_transaksi jika perlu
    $check_detail = "SHOW TABLES LIKE 'detail_transaksi'";
    $detail_result = mysqli_query($conn, $check_detail);
    
    if (mysqli_num_rows($detail_result) == 0) {
        $create_detail = "CREATE TABLE detail_transaksi (
            id_detail INT AUTO_INCREMENT PRIMARY KEY,
            id_transaksi INT NOT NULL,
            id_produk INT NOT NULL,
            quantity INT NOT NULL,
            harga_satuan DECIMAL(12, 2) NOT NULL,
            subtotal DECIMAL(12, 2) NOT NULL,
            FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE,
            FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (mysqli_query($conn, $create_detail)) {
            $output[] = '✅ Tabel detail_transaksi berhasil dibuat';
        } else {
            $errors[] = '❌ Gagal membuat tabel detail_transaksi: ' . mysqli_error($conn);
        }
    } else {
        $output[] = '✅ Tabel detail_transaksi sudah ada';
    }
    
    // 4. Verifikasi tabel pembayaran_detail
    $check_payment = "SHOW TABLES LIKE 'pembayaran_detail'";
    $payment_result = mysqli_query($conn, $check_payment);
    
    if (mysqli_num_rows($payment_result) == 0) {
        $create_payment = "CREATE TABLE pembayaran_detail (
            id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
            id_pesanan INT NOT NULL,
            id_pembeli INT NOT NULL,
            id_penjual INT NOT NULL,
            metode_pembayaran ENUM('qris', 'rekening', 'dompet_digital') DEFAULT 'qris',
            target_pembayaran VARCHAR(100),
            jumlah_pembayaran DECIMAL(15, 2) NOT NULL,
            bukti_pembayaran VARCHAR(255),
            status_pembayaran ENUM('menunggu', 'diverifikasi', 'diterima', 'ditolak') DEFAULT 'menunggu',
            catatan_verifikasi TEXT,
            dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            diverifikasi_pada TIMESTAMP NULL,
            diverifikasi_oleh INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE,
            FOREIGN KEY (id_pembeli) REFERENCES users(id_user) ON DELETE CASCADE,
            FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
            FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id_user) ON DELETE SET NULL,
            INDEX idx_pesanan (id_pesanan),
            INDEX idx_pembeli (id_pembeli),
            INDEX idx_penjual (id_penjual),
            INDEX idx_status (status_pembayaran)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (mysqli_query($conn, $create_payment)) {
            $output[] = '✅ Tabel pembayaran_detail berhasil dibuat';
        } else {
            $errors[] = '❌ Gagal membuat tabel pembayaran_detail: ' . mysqli_error($conn);
        }
    } else {
        $output[] = '✅ Tabel pembayaran_detail sudah ada';
    }
    
} catch (Exception $e) {
    $errors[] = '❌ Error: ' . $e->getMessage();
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Fix Tabel Transaksi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        
        .status-item {
            display: flex;
            align-items: flex-start;
            padding: 15px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            gap: 12px;
        }
        
        .status-item.success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        
        .status-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        
        .status-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        
        .status-icon {
            font-size: 1.2rem;
            min-width: 24px;
            margin-top: 2px;
        }
        
        .status-text {
            font-size: 0.95rem;
            color: #333;
            word-break: break-word;
            flex: 1;
        }
        
        .success .status-text {
            color: #065f46;
        }
        
        .error .status-text {
            color: #7f1d1d;
        }
        
        .warning .status-text {
            color: #92400e;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #666;
            font-size: 0.9rem;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
            font-weight: 500;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #764ba2;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .error-section {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .error-title {
            color: #7f1d1d;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .summary {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .summary-title {
            color: #065f46;
            font-weight: 600;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-tools"></i>
            Fix Tabel Transaksi
        </h1>
        <p class="subtitle">Perbaikan struktur database - Tambah kolom id_pesanan yang hilang</p>
        
        <?php if (!empty($output) && empty($errors)): ?>
            <div class="summary">
                <div class="summary-title">
                    <i class="fas fa-check-circle"></i> Perbaikan Berhasil!
                </div>
                <p>Semua perbaikan database telah diselesaikan dengan sukses. Aplikasi Anda sekarang siap digunakan.</p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($output)): ?>
            <div style="margin-bottom: 20px;">
                <h3 style="color: #333; margin-bottom: 15px; font-size: 16px;">
                    <i class="fas fa-check-circle" style="color: #10b981;"></i> Status Perbaikan:
                </h3>
                <?php foreach ($output as $msg): ?>
                    <div class="status-item success">
                        <span class="status-icon"><i class="fas fa-check"></i></span>
                        <span class="status-text"><?php echo htmlspecialchars($msg); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error-section">
                <div class="error-title">
                    <i class="fas fa-exclamation-circle"></i> Terjadi Kesalahan:
                </div>
                <?php foreach ($errors as $err): ?>
                    <div class="status-item error">
                        <span class="status-icon"><i class="fas fa-times"></i></span>
                        <span class="status-text"><?php echo htmlspecialchars($err); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Database Anda telah diperbaiki. Silahkan refresh halaman pesanan untuk melihat perubahan.</p>
            <div class="btn-group">
                <a href="pesanan_pembeli.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Kembali ke Pesanan
                </a>
                <a href="dashboard_pembeli.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
