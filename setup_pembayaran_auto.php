<?php
/**
 * Auto Setup Pembayaran - Jalankan SEKALI: http://localhost/toko-buku/toko-buku/setup_pembayaran_auto.php
 */

include 'koneksi.php';

$msg = [];
$errors = [];

try {
    // 1. Check & Create tabel pembayaran_detail
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
        $msg[] = '✓ Tabel pembayaran_detail dibuat/sudah ada';
    } else {
        $errors[] = 'pembayaran_detail: ' . mysqli_error($conn);
    }

    // 2. Check & Create tabel penjual_qris
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
        $msg[] = '✓ Tabel penjual_qris dibuat/sudah ada';
    } else {
        $errors[] = 'penjual_qris: ' . mysqli_error($conn);
    }

    // 3. Check & Add columns to pesanan table
    $sql = "ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS metode_pembayaran VARCHAR(50) DEFAULT 'belum_pilih'";
    if (mysqli_query($conn, $sql)) {
        $msg[] = '✓ Kolom metode_pembayaran ditambahkan/sudah ada';
    } else {
        $errors[] = 'metode_pembayaran: ' . mysqli_error($conn);
    }

    $sql = "ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS bukti_pembayaran VARCHAR(255)";
    if (mysqli_query($conn, $sql)) {
        $msg[] = '✓ Kolom bukti_pembayaran ditambahkan/sudah ada';
    } else {
        $errors[] = 'bukti_pembayaran: ' . mysqli_error($conn);
    }

    // 4. Create folder uploads/bukti_pembayaran jika belum ada
    if (!is_dir('uploads/bukti_pembayaran')) {
        mkdir('uploads/bukti_pembayaran', 0777, true);
        $msg[] = '✓ Folder uploads/bukti_pembayaran dibuat';
    } else {
        $msg[] = '✓ Folder uploads/bukti_pembayaran sudah ada';
    }

} catch (Exception $e) {
    $errors[] = 'Exception: ' . $e->getMessage();
}

mysqli_close($conn);
$success = empty($errors);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Pembayaran - Aksara-Aruna</title>
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
            gap: 12px;
        }

        .status-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        .status-icon {
            font-size: 18px;
            min-width: 20px;
            color: #10b981;
        }

        .status-item.error .status-icon {
            color: #ef4444;
        }

        .status-text {
            flex: 1;
        }

        .status-text p {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.5;
        }

        .footer {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
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

        @media (max-width: 600px) {
            .container {
                padding: 25px;
            }

            .header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-database"></i> Setup Database Pembayaran</h1>
            <p>Persiapan sistem QRIS dan verifikasi pembayaran</p>
        </div>

        <?php if ($success && !empty($msg)): ?>
            <div class="success-box">
                <i class="fas fa-check-circle"></i>
                <h2>✓ Setup Berhasil!</h2>
                <p style="margin-top: 10px;">Database pembayaran telah siap digunakan.</p>
            </div>
        <?php endif; ?>

        <div class="status-list">
            <?php foreach ($msg as $message): ?>
                <div class="status-item">
                    <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="status-text">
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error): ?>
                <div class="status-item error">
                    <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="status-text">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <h3 style="color: #1f2937; margin-bottom: 10px;">📋 Yang Telah Disiapkan:</h3>
            <ul style="text-align: left; margin: 15px auto; max-width: 400px; color: #6b7280; font-size: 13px; line-height: 1.8;">
                <li><strong>✓ Tabel pembayaran_detail</strong> - Menyimpan bukti & verifikasi pembayaran</li>
                <li><strong>✓ Tabel penjual_qris</strong> - Data QRIS/rekening setiap penjual</li>
                <li><strong>✓ Kolom pesanan</strong> - metode_pembayaran & bukti_pembayaran</li>
                <li><strong>✓ Folder upload</strong> - uploads/bukti_pembayaran/</li>
            </ul>

            <p style="color: #9ca3af; font-size: 12px; margin-top: 20px;">
                Sekarang Anda bisa akses:
            </p>
            <ul style="text-align: center; margin: 10px 0; color: #667eea; font-size: 13px;">
                <li><strong>Pembeli:</strong> transaksi.php</li>
                <li><strong>Penjual Setup:</strong> penjual_setup_qris.php</li>
                <li><strong>Penjual Verifikasi:</strong> penjual_verifikasi_pembayaran.php</li>
            </ul>

            <a href="dashboard.php" class="btn">
                <i class="fas fa-arrow-right"></i> Lanjut ke Dashboard
            </a>
        </div>
    </div>
</body>
</html>
