<?php
/**
 * Database Repair Script
 * Script ini memperbaiki struktur tabel jika ada kolom yang missing
 * Akses: http://localhost/toko-buku/toko-buku/repair_database.php
 */

$host = "localhost";
$user = "root";
$pass = "";
$db = "toko-buku";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$repair_status = [];

// ========== PERBAIKI TABEL BUKU - TAMBAH KOLOM STOK JIKA BELUM ADA ==========
$check_stok = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'buku' AND COLUMN_NAME = 'stok'";

if ($stmt = mysqli_prepare($conn, $check_stok)) {
    mysqli_stmt_bind_param($stmt, "s", $db);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) == 0) {
        // Kolom stok tidak ada, tambahkan
        $alter_sql = "ALTER TABLE buku ADD COLUMN stok INT NOT NULL DEFAULT 0 AFTER harga";
        if (mysqli_query($conn, $alter_sql)) {
            $repair_status['stok_column'] = '✓ Kolom stok berhasil ditambahkan ke tabel buku';
        } else {
            $repair_status['stok_column'] = '✗ Gagal menambahkan kolom stok: ' . mysqli_error($conn);
        }
    } else {
        $repair_status['stok_column'] = '✓ Kolom stok sudah ada';
    }
    
    mysqli_stmt_close($stmt);
}

// ========== PERBAIKI TABEL KERANJANG - PASTIKAN SUDAH ADA ==========
$check_keranjang = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'keranjang'";

if ($stmt = mysqli_prepare($conn, $check_keranjang)) {
    mysqli_stmt_bind_param($stmt, "s", $db);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) == 0) {
        // Tabel keranjang tidak ada, buat
        $create_keranjang = "CREATE TABLE keranjang (
            id_keranjang INT PRIMARY KEY AUTO_INCREMENT,
            id_user INT NOT NULL,
            id_buku INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            status ENUM('aktif', 'checkout', 'dihapus') DEFAULT 'aktif',
            tanggal_ditambahkan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            tanggal_diupdate TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
            FOREIGN KEY (id_buku) REFERENCES buku(id_buku) ON DELETE CASCADE,
            UNIQUE KEY unique_active_cart (id_user, id_buku, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (mysqli_query($conn, $create_keranjang)) {
            $repair_status['keranjang_table'] = '✓ Tabel keranjang berhasil dibuat';
        } else {
            $repair_status['keranjang_table'] = '✗ Gagal membuat tabel keranjang: ' . mysqli_error($conn);
        }
    } else {
        $repair_status['keranjang_table'] = '✓ Tabel keranjang sudah ada';
    }
    
    mysqli_stmt_close($stmt);
}

// ========== PERBAIKI TABEL BUKU - TAMBAH INDEX ==========
$check_index = "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
               WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'buku' AND INDEX_NAME = 'idx_kategori'";

if ($stmt = mysqli_prepare($conn, $check_index)) {
    mysqli_stmt_bind_param($stmt, "s", $db);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) == 0) {
        $add_index = "ALTER TABLE buku ADD INDEX idx_kategori (id_kategori)";
        if (mysqli_query($conn, $add_index)) {
            $repair_status['index'] = '✓ Index kategori berhasil ditambahkan';
        } else {
            $repair_status['index'] = '⚠ Gagal menambahkan index (tidak fatal): ' . mysqli_error($conn);
        }
    } else {
        $repair_status['index'] = '✓ Index kategori sudah ada';
    }
    
    mysqli_stmt_close($stmt);
}

// ========== PERBAIKI TABEL TRANSAKSI - PASTIKAN ADA ==========
$check_transaksi = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'transaksi'";

if ($stmt = mysqli_prepare($conn, $check_transaksi)) {
    mysqli_stmt_bind_param($stmt, "s", $db);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) == 0) {
        $create_transaksi = "CREATE TABLE transaksi (
            id_transaksi INT PRIMARY KEY AUTO_INCREMENT,
            id_pembeli INT NOT NULL,
            total_harga DECIMAL(15, 2) NOT NULL,
            status ENUM('menunggu_pembayaran', 'dibayar', 'approve', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak', 'refund') DEFAULT 'menunggu_pembayaran',
            metode_pembayaran VARCHAR(50),
            nomor_resi VARCHAR(50),
            alamat_pengiriman TEXT,
            catatan_admin TEXT,
            tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (id_pembeli) REFERENCES users(id_user) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (mysqli_query($conn, $create_transaksi)) {
            $repair_status['transaksi_table'] = '✓ Tabel transaksi berhasil dibuat';
        } else {
            $repair_status['transaksi_table'] = '✗ Gagal membuat tabel transaksi: ' . mysqli_error($conn);
        }
    } else {
        $repair_status['transaksi_table'] = '✓ Tabel transaksi sudah ada';
    }
    
    mysqli_stmt_close($stmt);
}

// ========== PERBAIKI TABEL DETAIL TRANSAKSI - PASTIKAN ADA ==========
$check_detail = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'detail_transaksi'";

if ($stmt = mysqli_prepare($conn, $check_detail)) {
    mysqli_stmt_bind_param($stmt, "s", $db);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) == 0) {
        $create_detail = "CREATE TABLE detail_transaksi (
            id_detail INT PRIMARY KEY AUTO_INCREMENT,
            id_transaksi INT NOT NULL,
            id_buku INT NOT NULL,
            quantity INT NOT NULL,
            harga_satuan DECIMAL(10, 2) NOT NULL,
            subtotal DECIMAL(15, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE,
            FOREIGN KEY (id_buku) REFERENCES buku(id_buku) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (mysqli_query($conn, $create_detail)) {
            $repair_status['detail_transaksi_table'] = '✓ Tabel detail_transaksi berhasil dibuat';
        } else {
            $repair_status['detail_transaksi_table'] = '✗ Gagal membuat tabel detail_transaksi: ' . mysqli_error($conn);
        }
    } else {
        $repair_status['detail_transaksi_table'] = '✓ Tabel detail_transaksi sudah ada';
    }
    
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);

// Tampilkan HTML
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Repair | BookVerse</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
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
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            gap: 10px;
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
            margin-top: 2px;
            min-width: 24px;
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
        }
        
        a {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
            font-weight: 500;
        }
        
        a:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Repair Status</h1>
        <p class="subtitle">Perbaikan struktur database BookVerse</p>
        
        <?php foreach ($repair_status as $name => $status): 
            $is_success = strpos($status, '✓') !== false;
            $is_error = strpos($status, '✗') !== false;
            $is_warning = strpos($status, '⚠') !== false;
        ?>
            <div class="status-item <?php echo $is_success ? 'success' : ($is_error ? 'error' : ($is_warning ? 'warning' : '')); ?>">
                <span class="status-icon">
                    <?php if ($is_success) echo '✓'; elseif ($is_error) echo '✗'; else echo '⚠'; ?>
                </span>
                <span class="status-text"><?php echo $status; ?></span>
            </div>
        <?php endforeach; ?>
        
        <div class="footer">
            ✅ Database sudah diperbaiki! Sekarang aplikasi sudah siap digunakan.
            <div class="btn-group">
                <a href="keranjang.php">← Kembali ke Keranjang</a>
                <a href="login.php">Login →</a>
            </div>
        </div>
    </div>
</body>
</html>
