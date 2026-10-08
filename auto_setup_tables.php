<?php
/**
 * Auto Setup Database - Jalankan di browser: http://localhost/toko-buku/toko-buku/auto_setup_tables.php
 */

ob_start();
include 'koneksi.php';

$msg = [];
$errors = [];

// 1. Create produk table
$sql = "CREATE TABLE IF NOT EXISTS produk (
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

if (mysqli_query($conn, $sql)) {
    $msg[] = 'Tabel produk OK';
} else {
    $errors[] = mysqli_error($conn);
}

// 2. Create keranjang_produk table
$sql = "CREATE TABLE IF NOT EXISTS keranjang_produk (
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
    UNIQUE KEY unique_cart (id_user, id_produk, id_penjual),
    INDEX idx_user (id_user),
    INDEX idx_produk (id_produk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql)) {
    $msg[] = 'Tabel keranjang_produk OK';
} else {
    $errors[] = mysqli_error($conn);
}

// 3. Create pesanan table
$sql = "CREATE TABLE IF NOT EXISTS pesanan (
    id_pesanan INT PRIMARY KEY AUTO_INCREMENT,
    id_pembeli INT NOT NULL,
    id_penjual INT NOT NULL,
    id_produk INT NOT NULL,
    quantity INT NOT NULL,
    total_harga DECIMAL(15, 2) NOT NULL,
    status ENUM('menunggu_pembayaran', 'dibayar', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak') DEFAULT 'menunggu_pembayaran',
    metode_pembayaran VARCHAR(50),
    bukti_pembayaran VARCHAR(255),
    nomor_resi VARCHAR(50),
    alamat_pengiriman TEXT,
    catatan_pembeli TEXT,
    catatan_penjual TEXT,
    tanggal_selesai TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pembeli) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
    INDEX idx_pembeli (id_pembeli),
    INDEX idx_penjual (id_penjual),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql)) {
    $msg[] = 'Tabel pesanan OK';
} else {
    $errors[] = mysqli_error($conn);
}

// 4. Update users table (tambah role penjual kalau belum ada)
$sql = "ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'pembeli', 'penjual') DEFAULT 'pembeli'";
if (mysqli_query($conn, $sql)) {
    $msg[] = 'Tabel users diupdate';
} else {
    // Mungkin sudah ada, tidak apa
    $msg[] = 'Tabel users sudah sesuai';
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Setup Database</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            margin: 8px 0;
            border-left: 4px solid #28a745;
            border-radius: 4px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin: 8px 0;
            border-left: 4px solid #dc3545;
            border-radius: 4px;
        }
        .btn {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ Setup Database Berhasil</h1>

        <?php if (!empty($msg)): ?>
            <h3>Tabel yang dibuat:</h3>
            <?php foreach ($msg as $m): ?>
                <div class="success">✓ <?php echo htmlspecialchars($m); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <h3>Peringatan:</h3>
            <?php foreach ($errors as $e): ?>
                <div class="error">✗ <?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p style="margin-top: 30px; color: #666;">
            Database sudah siap. Anda sekarang bisa menggunakan sistem.
        </p>

        <a href="dashboard.php" class="btn">Kembali ke Dashboard</a>
    </div>
</body>
</html>
