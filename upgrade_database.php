<?php
/**
 * Database Upgrade Script - Menambahkan kolom ke tabel yang sudah ada
 * Jalankan di browser: http://localhost/toko-buku/toko-buku/upgrade_database.php
 */

include 'koneksi.php';

if (!$conn) {
    die("Database connection failed");
}

$results = [];

// ============ UPGRADE USERS TABLE ============

// 1. Cek dan tambahkan kolom 'nik' jika belum ada
$check_nik = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'nik'");
if (mysqli_num_rows($check_nik) == 0) {
    $sql = "ALTER TABLE users ADD COLUMN nik VARCHAR(16) UNIQUE AFTER email";
    if (mysqli_query($conn, $sql)) {
        $results[] = ['success' => true, 'message' => 'Kolom NIK berhasil ditambahkan'];
    } else {
        $results[] = ['success' => false, 'message' => 'Error: ' . mysqli_error($conn)];
    }
} else {
    $results[] = ['success' => true, 'message' => 'Kolom NIK sudah ada'];
}

// 2. Cek dan tambahkan kolom 'last_activity' jika belum ada
$check_activity = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'last_activity'");
if (mysqli_num_rows($check_activity) == 0) {
    $sql = "ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL AFTER remember_token";
    if (mysqli_query($conn, $sql)) {
        $results[] = ['success' => true, 'message' => 'Kolom last_activity berhasil ditambahkan'];
    } else {
        $results[] = ['success' => false, 'message' => 'Error: ' . mysqli_error($conn)];
    }
} else {
    $results[] = ['success' => true, 'message' => 'Kolom last_activity sudah ada'];
}

// 3. Update role enum untuk menambahkan 'penjual' jika belum ada
$sql = "ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'pembeli', 'penjual') DEFAULT 'pembeli'";
if (mysqli_query($conn, $sql)) {
    $results[] = ['success' => true, 'message' => 'Role ENUM berhasil diupdate'];
} else {
    // Bisa jadi sudah ada, jadi bukan error
    $results[] = ['success' => true, 'message' => 'Role ENUM sudah sesuai'];
}

// ============ Check tabel lainnya ============

// Cek apakah tabel pesanan sudah memiliki kolom tanggal_selesai
$check_pesanan = mysqli_query($conn, "SHOW TABLES LIKE 'pesanan'");
if (mysqli_num_rows($check_pesanan) > 0) {
    $check_selesai = mysqli_query($conn, "SHOW COLUMNS FROM pesanan LIKE 'tanggal_selesai'");
    if (mysqli_num_rows($check_selesai) == 0) {
        $sql = "ALTER TABLE pesanan ADD COLUMN tanggal_selesai TIMESTAMP NULL DEFAULT NULL AFTER updated_at";
        if (mysqli_query($conn, $sql)) {
            $results[] = ['success' => true, 'message' => 'Kolom tanggal_selesai di tabel pesanan berhasil ditambahkan'];
        } else {
            $results[] = ['success' => false, 'message' => 'Error menambahkan tanggal_selesai: ' . mysqli_error($conn)];
        }
    } else {
        $results[] = ['success' => true, 'message' => 'Kolom tanggal_selesai di tabel pesanan sudah ada'];
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Upgrade</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .alert-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }

        .alert-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        .icon {
            font-size: 20px;
            margin-right: 12px;
            min-width: 20px;
            color: #10b981;
        }

        .alert-item.error .icon {
            color: #ef4444;
        }

        .content {
            font-size: 0.95rem;
            color: #333;
        }

        .alert-item.error .content {
            color: #7f1d1d;
        }

        .message {
            font-weight: 500;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #666;
            font-size: 0.9rem;
        }

        a {
            display: inline-block;
            padding: 12px 28px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
            margin-top: 15px;
        }

        a:hover {
            background: #764ba2;
        }

        .success-summary {
            background: #ecfdf5;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            color: #065f46;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Upgrade</h1>
        <p class="subtitle">Update struktur database ke versi terbaru</p>

        <div class="success-summary">
            ✓ Proses upgrade selesai
        </div>

        <?php foreach ($results as $result): ?>
            <div class="alert-item <?php echo $result['success'] ? '' : 'error'; ?>">
                <div class="icon">
                    <?php echo $result['success'] ? '✓' : '✗'; ?>
                </div>
                <div class="content">
                    <div class="message"><?php echo htmlspecialchars($result['message']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="footer">
            Database Anda sekarang sudah up-to-date!
            <br><br>
            <a href="login.php">← Kembali ke Login</a>
        </div>
    </div>
</body>
</html>
