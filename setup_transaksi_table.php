<?php
/**
 * Setup Tabel Transaksi & Pencatatan Otomatis
 */

session_start();
include 'koneksi.php';

$output = [];
$success = true;

try {
    // 1. Buat tabel transaksi jika belum ada
    $create_transaksi = "CREATE TABLE IF NOT EXISTS transaksi (
        id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
        id_pesanan INT NOT NULL,
        id_pembeli INT NOT NULL,
        id_penjual INT NOT NULL,
        tanggal DATETIME DEFAULT NOW(),
        total_harga DECIMAL(12, 2) NOT NULL,
        status VARCHAR(50) DEFAULT 'menunggu_pembayaran',
        metode_pembayaran VARCHAR(50),
        bukti_pembayaran VARCHAR(255),
        resi VARCHAR(100),
        updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan),
        FOREIGN KEY (id_pembeli) REFERENCES users(id_user),
        FOREIGN KEY (id_penjual) REFERENCES users(id_user)
    )";
    
    if (mysqli_query($conn, $create_transaksi)) {
        $output[] = "✅ Tabel transaksi berhasil dibuat/diverifikasi";
    } else {
        $output[] = "❌ Error tabel transaksi: " . mysqli_error($conn);
        $success = false;
    }

    // 2. Buat tabel detail transaksi jika belum ada
    $create_detail = "CREATE TABLE IF NOT EXISTS detail_transaksi (
        id_detail INT AUTO_INCREMENT PRIMARY KEY,
        id_transaksi INT NOT NULL,
        id_produk INT NOT NULL,
        quantity INT NOT NULL,
        harga_satuan DECIMAL(12, 2) NOT NULL,
        subtotal DECIMAL(12, 2) NOT NULL,
        FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi),
        FOREIGN KEY (id_produk) REFERENCES produk(id_produk)
    )";
    
    if (mysqli_query($conn, $create_detail)) {
        $output[] = "✅ Tabel detail_transaksi berhasil dibuat/diverifikasi";
    } else {
        $output[] = "❌ Error detail_transaksi: " . mysqli_error($conn);
        $success = false;
    }

    // 3. Buat tabel log transaksi untuk audit
    $create_log = "CREATE TABLE IF NOT EXISTS log_transaksi (
        id_log INT AUTO_INCREMENT PRIMARY KEY,
        id_transaksi INT,
        status_lama VARCHAR(50),
        status_baru VARCHAR(50),
        keterangan TEXT,
        tanggal_perubahan DATETIME DEFAULT NOW(),
        user_id INT,
        FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi)
    )";
    
    if (mysqli_query($conn, $create_log)) {
        $output[] = "✅ Tabel log_transaksi berhasil dibuat/diverifikasi";
    } else {
        $output[] = "❌ Error log_transaksi: " . mysqli_error($conn);
        $success = false;
    }

    // 4. Buat tabel pembayaran detail jika belum ada
    $create_payment = "CREATE TABLE IF NOT EXISTS pembayaran_detail (
        id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
        id_pesanan INT NOT NULL,
        id_pembeli INT NOT NULL,
        id_penjual INT NOT NULL,
        metode_pembayaran VARCHAR(50),
        jumlah_pembayaran DECIMAL(12, 2),
        bukti_pembayaran VARCHAR(255),
        status VARCHAR(50) DEFAULT 'menunggu_verifikasi',
        tanggal_pembayaran DATETIME DEFAULT NOW(),
        FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan),
        FOREIGN KEY (id_pembeli) REFERENCES users(id_user),
        FOREIGN KEY (id_penjual) REFERENCES users(id_user)
    )";
    
    if (mysqli_query($conn, $create_payment)) {
        $output[] = "✅ Tabel pembayaran_detail berhasil dibuat/diverifikasi";
    } else {
        $output[] = "❌ Error pembayaran_detail: " . mysqli_error($conn);
        $success = false;
    }

    // 5. Sync data dari pesanan ke transaksi (jika ada)
    $sync_query = "INSERT INTO transaksi (id_pesanan, id_pembeli, id_penjual, tanggal, total_harga, status, metode_pembayaran, bukti_pembayaran)
                   SELECT id_pesanan, id_pembeli, id_penjual, created_at, total_harga, status, metode_pembayaran, bukti_pembayaran
                   FROM pesanan 
                   WHERE id_pesanan NOT IN (SELECT id_pesanan FROM transaksi)
                   AND status IN ('dibayar', 'diproses', 'dikirim', 'selesai')";
    
    $sync_result = mysqli_query($conn, $sync_query);
    if ($sync_result) {
        $affected = mysqli_affected_rows($conn);
        $output[] = "✅ Sinkronisasi data pesanan: $affected record(s) ditambahkan";
    } else {
        $output[] = "⚠️ Sinkronisasi data: " . mysqli_error($conn);
    }

    // 6. Sync detail transaksi dari keranjang
    $sync_detail = "INSERT INTO detail_transaksi (id_transaksi, id_produk, quantity, harga_satuan, subtotal)
                    SELECT t.id_transaksi, p.id_produk, t.quantity, t.total_harga/t.quantity, t.total_harga
                    FROM transaksi t
                    JOIN pesanan p ON t.id_pesanan = p.id_pesanan
                    WHERE NOT EXISTS (
                        SELECT 1 FROM detail_transaksi dt WHERE dt.id_transaksi = t.id_transaksi
                    )";
    
    $sync_detail_result = mysqli_query($conn, $sync_detail);
    if ($sync_detail_result) {
        $affected = mysqli_affected_rows($conn);
        $output[] = "✅ Sinkronisasi detail transaksi: $affected record(s) ditambahkan";
    }

    // 7. Verifikasi tabel penjual_qris
    $check_qris = "SHOW TABLES LIKE 'penjual_qris'";
    $qris_result = mysqli_query($conn, $check_qris);
    
    if (mysqli_num_rows($qris_result) == 0) {
        $create_qris = "CREATE TABLE IF NOT EXISTS penjual_qris (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_penjual INT NOT NULL UNIQUE,
            nomor_rekening VARCHAR(50),
            nama_bank VARCHAR(100),
            nama_rekening VARCHAR(100),
            status VARCHAR(20) DEFAULT 'aktif',
            FOREIGN KEY (id_penjual) REFERENCES users(id_user)
        )";
        
        if (mysqli_query($conn, $create_qris)) {
            $output[] = "✅ Tabel penjual_qris berhasil dibuat";
        }
    } else {
        $output[] = "✅ Tabel penjual_qris sudah ada";
    }

} catch (Exception $e) {
    $output[] = "❌ Error: " . $e->getMessage();
    $success = false;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Sistem Transaksi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #8B4513 0%, #D2B48C 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 2px solid #D2B48C;
        }

        h1 {
            color: #8B4513;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Playfair Display', serif;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .status-list {
            list-style: none;
            margin-bottom: 30px;
        }

        .status-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #ddd;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .status-item.success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }

        .status-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }

        .status-item.info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }

        .status-icon {
            font-size: 1.2rem;
            flex-shrink: 0;
            width: 24px;
            text-align: center;
        }

        .summary {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .summary-status {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .summary-message {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8B4513, #A0522D);
            color: white;
            border: 2px solid #D2B48C;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #8B4513;
            border: 2px solid #8B4513;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #0c3366;
            line-height: 1.6;
        }

        .info-box strong {
            color: #0c3366;
        }

        .feature-list {
            list-style: none;
            padding: 10px 0;
        }

        .feature-list li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .feature-list li:before {
            content: '✓';
            color: #28a745;
            font-weight: 600;
            width: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-database"></i>
            Setup Sistem Transaksi
        </h1>
        <p class="subtitle">Mengatur database dan pencatatan transaksi otomatis</p>

        <div class="summary">
            <div class="summary-status">
                <?php echo $success ? '✅ Sistem Siap!' : '⚠️ Setup dengan Peringatan'; ?>
            </div>
            <div class="summary-message">
                <?php echo $success 
                    ? 'Semua tabel database telah disiapkan dengan sukses' 
                    : 'Beberapa item perlu perhatian (lihat detail di bawah)'; ?>
            </div>
        </div>

        <ul class="status-list">
            <?php foreach ($output as $msg): ?>
                <?php 
                    $class = 'info';
                    if (strpos($msg, '✅') === 0) $class = 'success';
                    elseif (strpos($msg, '❌') === 0) $class = 'error';
                    elseif (strpos($msg, '⚠️') === 0) $class = 'info';
                ?>
                <li class="status-item <?php echo $class; ?>">
                    <div class="status-icon">
                        <?php 
                            if ($class === 'success') echo '<i class="fas fa-check-circle"></i>';
                            elseif ($class === 'error') echo '<i class="fas fa-times-circle"></i>';
                            else echo '<i class="fas fa-info-circle"></i>';
                        ?>
                    </div>
                    <div><?php echo $msg; ?></div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="info-box">
            <strong>📝 Fitur yang Telah Disetup:</strong>
            <ul class="feature-list">
                <li><strong>Pencatatan Transaksi Otomatis</strong> - Setiap pesanan tercatat di tabel transaksi</li>
                <li><strong>Detail Transaksi</strong> - Breakdown per produk dalam setiap transaksi</li>
                <li><strong>Log Audit</strong> - Setiap perubahan status tercatat dengan timestamp</li>
                <li><strong>Pembayaran Detail</strong> - Tracking bukti pembayaran dan verifikasi</li>
                <li><strong>QRIS Integration</strong> - Tabel untuk menyimpan data rekening penjual</li>
            </ul>
        </div>

        <div class="btn-group" style="margin-top: 30px;">
            <a href="penjual_laporan.php" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i> Lihat Laporan
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Kembali Home
            </a>
        </div>
    </div>
</body>
</html>
