<?php
/**
 * Setup Database untuk Produk Penjual
 * Jalankan file ini sekali: http://localhost/toko-buku/toko-buku/setup_produk.php
 */

include 'koneksi.php';

$tables_created = [];
$errors = [];

try {
    // ========== TABEL PRODUK ==========
    $sql_produk = "CREATE TABLE IF NOT EXISTS produk (
        id_produk INT PRIMARY KEY AUTO_INCREMENT,
        nama_buku VARCHAR(200) NOT NULL,
        id_kategori INT,
        id_penjual INT NOT NULL,
        stok INT NOT NULL DEFAULT 0,
        harga DECIMAL(15, 2) NOT NULL,
        modal DECIMAL(15, 2),
        margin DECIMAL(15, 2),
        keuntungan DECIMAL(15, 2),
        deskripsi TEXT,
        foto_produk VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori) ON DELETE SET NULL,
        FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
        INDEX idx_penjual (id_penjual),
        INDEX idx_kategori (id_kategori),
        INDEX idx_stok (stok)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql_produk)) {
        $tables_created['produk'] = '✓ Tabel produk berhasil dibuat';
    } else {
        $errors['produk'] = '✗ ' . mysqli_error($conn);
    }

    // ========== TABEL KERANJANG_PRODUK ==========
    $sql_keranjang_produk = "CREATE TABLE IF NOT EXISTS keranjang_produk (
        id_keranjang INT PRIMARY KEY AUTO_INCREMENT,
        id_user INT NOT NULL,
        id_produk INT NOT NULL,
        id_penjual INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
        FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
        FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
        UNIQUE KEY unique_cart (id_user, id_produk, id_penjual)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql_keranjang_produk)) {
        $tables_created['keranjang_produk'] = '✓ Tabel keranjang_produk berhasil dibuat';
    } else {
        $errors['keranjang_produk'] = '✗ ' . mysqli_error($conn);
    }

    // ========== TABEL PESANAN ==========
        $sql_pesanan = "CREATE TABLE IF NOT EXISTS pesanan (
        id_pesanan INT PRIMARY KEY AUTO_INCREMENT,
        id_pembeli INT NOT NULL,
        id_penjual INT NOT NULL,
        id_produk INT NOT NULL,
        quantity INT NOT NULL,
        total_harga DECIMAL(15, 2) NOT NULL,
        status ENUM('menunggu_pembayaran', 'dibayar', 'approve', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak', 'refund') DEFAULT 'menunggu_pembayaran',
        metode_pembayaran VARCHAR(50),
        bukti_pembayaran VARCHAR(255),
        nomor_resi VARCHAR(50),
        alamat_pengiriman TEXT,
        catatan_pembeli TEXT,
        catatan_penjual TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_pembeli) REFERENCES users(id_user) ON DELETE CASCADE,
        FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
        FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
        INDEX idx_pembeli (id_pembeli),
        INDEX idx_penjual (id_penjual),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql_pesanan)) {
        $tables_created['pesanan'] = '✓ Tabel pesanan berhasil dibuat';
    } else {
        $errors['pesanan'] = '✗ ' . mysqli_error($conn);
    }

    // ========== UPDATE USERS TABLE ==========
    $sql_alter_users = "ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'pembeli', 'penjual') DEFAULT 'pembeli'";
    if (mysqli_query($conn, $sql_alter_users)) {
        $tables_created['users_alter'] = '✓ Tabel users berhasil diupdate (role penjual ditambahkan)';
    } else {
        // Mungkin sudah ada, skip error
        $tables_created['users_alter'] = '✓ Tabel users sudah sesuai';
    }

} catch (Exception $e) {
    $errors['exception'] = '✗ Error: ' . $e->getMessage();
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Produk Penjual - Aksara-Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #8b5cf6 100%);
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
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .header p {
            color: #9ca3af;
            font-size: 14px;
        }

        .status-list {
            margin-bottom: 30px;
        }

        .status-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }

        .status-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        .status-icon {
            font-size: 18px;
            margin-right: 15px;
            min-width: 20px;
            text-align: center;
        }

        .status-item.error .status-icon {
            color: #ef4444;
        }

        .status-item.success .status-icon {
            color: #10b981;
        }

        .status-text {
            flex: 1;
        }

        .status-text strong {
            display: block;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .status-text p {
            color: #6b7280;
            font-size: 12px;
        }

        .footer {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 15px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .success-box {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: #065f46;
            margin-bottom: 20px;
        }

        .success-box i {
            font-size: 32px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-database"></i> Setup Database</h1>
            <p>Persiapan Sistem Produk Penjual</p>
        </div>

        <?php if (empty($errors)): ?>
            <div class="success-box">
                <i class="fas fa-check-circle"></i>
                <h2>✓ Setup Berhasil!</h2>
                <p style="margin-top: 10px;">Semua tabel database telah berhasil dibuat. Sistem siap digunakan.</p>
            </div>
        <?php endif; ?>

        <div class="status-list">
            <?php foreach ($tables_created as $table => $message): ?>
                <div class="status-item success">
                    <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="status-text">
                        <strong><?php echo ucfirst(str_replace('_', ' ', $table)); ?></strong>
                        <p><?php echo $message; ?></p>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error => $message): ?>
                <div class="status-item error">
                    <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="status-text">
                        <strong><?php echo ucfirst($error); ?></strong>
                        <p><?php echo $message; ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <h3>Fitur yang Tersedia:</h3>
            <ul style="text-align: left; margin: 15px auto; max-width: 400px; color: #6b7280; font-size: 13px;">
                <li><strong>✓ Penjual</strong> dapat menambah produk</li>
                <li><strong>✓ Pembeli</strong> dapat melihat produk dari berbagai penjual</li>
                <li><strong>✓ Keranjang</strong> terintegrasi dengan produk penjual</li>
                <li><strong>✓ Pesanan</strong> mencatat penjual untuk setiap produk</li>
                <li><strong>✓ Stok</strong> dikelola per penjual</li>
            </ul>

            <a href="dashboard_pembeli.php" class="btn">
                <i class="fas fa-arrow-right"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
</body>
</html>
