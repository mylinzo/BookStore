<?php
/**
 * Setup Database Pembayaran QRIS
 * Jalankan: http://localhost/toko-buku/toko-buku/pembayaran_setup.php
 */

include 'koneksi.php';

$msg = [];
$errors = [];

// 1. Update pesanan table - tambah kolom pembayaran
$sql = "ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS metode_pembayaran VARCHAR(50) DEFAULT 'belum_pilih'";
if (mysqli_query($conn, $sql)) {
    $msg[] = 'Kolom metode_pembayaran ditambahkan ke tabel pesanan';
} else {
    $msg[] = 'Kolom metode_pembayaran sudah ada di tabel pesanan';
}

$sql = "ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS bukti_pembayaran VARCHAR(255)";
if (mysqli_query($conn, $sql)) {
    $msg[] = 'Kolom bukti_pembayaran ditambahkan';
} else {
    $msg[] = 'Kolom bukti_pembayaran sudah ada';
}

// 2. Create tabel pembayaran_detail (untuk tracking setiap payment attempt)
$sql = "CREATE TABLE IF NOT EXISTS pembayaran_detail (
    id_pembayaran INT PRIMARY KEY AUTO_INCREMENT,
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

if (mysqli_query($conn, $sql)) {
    $msg[] = 'Tabel pembayaran_detail dibuat';
} else {
    $errors[] = mysqli_error($conn);
}

// 3. Create tabel penjual_qris (untuk simpan QRIS setiap penjual)
$sql = "CREATE TABLE IF NOT EXISTS penjual_qris (
    id_qris INT PRIMARY KEY AUTO_INCREMENT,
    id_penjual INT NOT NULL UNIQUE,
    nomor_identitas VARCHAR(50),
    nomor_rekening VARCHAR(50),
    nama_rekening VARCHAR(100),
    nama_bank VARCHAR(50),
    qris_string LONGTEXT,
    qris_gambar VARCHAR(255),
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
    INDEX idx_penjual (id_penjual)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql)) {
    $msg[] = 'Tabel penjual_qris dibuat';
} else {
    $errors[] = mysqli_error($conn);
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Pembayaran QRIS</title>
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
            text-align: center;
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
        }
        .btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ Setup Pembayaran Selesai</h1>

        <?php if (!empty($msg)): ?>
            <h3>Status:</h3>
            <?php foreach ($msg as $m): ?>
                <div class="success">✓ <?php echo htmlspecialchars($m); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <h3>Error:</h3>
            <?php foreach ($errors as $e): ?>
                <div class="error">✗ <?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p style="margin-top: 20px; color: #666;">Database pembayaran sudah siap!</p>
        <a href="dashboard.php" class="btn">Kembali ke Dashboard</a>
    </div>
</body>
</html>
