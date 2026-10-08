<?php
/**
 * Database Setup untuk Notifikasi System
 * Script untuk membuat tabel notifikasi jika belum ada
 */

$host = "localhost";
$user = "root";
$pass = "";
$db = "toko-buku";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$created = false;

// Create notifications table
$sql_notifikasi = "CREATE TABLE IF NOT EXISTS notifikasi (
    id_notifikasi INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    tipe VARCHAR(50) NOT NULL COMMENT 'payment_verified, payment_rejected, delivery_confirmed, order_shipped, etc',
    pesan TEXT NOT NULL,
    data_ref INT COMMENT 'Reference to order/payment ID',
    status ENUM('unread', 'read') DEFAULT 'unread',
    url_redirect VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    INDEX idx_user_status (id_user, status),
    INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql_notifikasi)) {
    echo "✓ Tabel notifikasi berhasil dibuat/ada<br>";
    $created = true;
} else {
    echo "✗ Error membuat tabel notifikasi: " . mysqli_error($conn) . "<br>";
}

// Add column if needed
$check_table = "SHOW TABLES LIKE 'pesanan'";
$result = mysqli_query($conn, $check_table);

if (mysqli_num_rows($result) > 0) {
    // Check if catatan_penjual column exists
    $check_col = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='pesanan' AND COLUMN_NAME='catatan_penjual' AND TABLE_SCHEMA=?";
    $stmt = mysqli_prepare($conn, $check_col);
    mysqli_stmt_bind_param($stmt, "s", $db);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) == 0) {
        $alter = "ALTER TABLE pesanan ADD COLUMN catatan_penjual TEXT AFTER catatan_pembeli";
        if (mysqli_query($conn, $alter)) {
            echo "✓ Kolom catatan_penjual berhasil ditambahkan<br>";
            $created = true;
        } else {
            echo "⚠ Kolom catatan_penjual mungkin sudah ada atau error: " . mysqli_error($conn) . "<br>";
        }
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Setup Notifikasi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin: 15px 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        a:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ Setup Notifikasi Selesai</h1>
        <div class="success">
            <p>Database berhasil di-setup untuk notification system!</p>
            <p>Tabel dan kolom yang diperlukan telah dibuat/diverifikasi.</p>
        </div>
        <p><strong>Fitur yang tersedia:</strong></p>
        <ul>
            <li>✓ Notifikasi verifikasi pembayaran</li>
            <li>✓ Notifikasi penolakan pembayaran</li>
            <li>✓ Notifikasi pengiriman pesanan</li>
            <li>✓ Notifikasi penerimaan pesanan</li>
            <li>✓ Status read/unread notifications</li>
        </ul>
        <a href="javascript:history.back()">← Kembali</a>
    </div>
</body>
</html>
