<?php
/**
 * Add tanggal_selesai column to pesanan table
 * Run this file in the browser to add the missing column
 */

include 'koneksi.php';

if (!$conn) {
    echo "Database connection failed";
    exit;
}

$sql = "ALTER TABLE pesanan ADD COLUMN tanggal_selesai TIMESTAMP NULL DEFAULT NULL AFTER updated_at";

if (mysqli_query($conn, $sql)) {
    echo "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:4px; font-family:Arial;'>
        ✓ Kolom 'tanggal_selesai' berhasil ditambahkan ke tabel pesanan
    </div>";
} else {
    // Check if column already exists
    if (strpos(mysqli_error($conn), 'Duplicate column') !== false) {
        echo "<div style='background:#cfe2ff; color:#084298; padding:15px; border-radius:4px; font-family:Arial;'>
            ℹ Kolom 'tanggal_selesai' sudah ada di tabel pesanan
        </div>";
    } else {
        echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:4px; font-family:Arial;'>
            ✗ Error: " . htmlspecialchars(mysqli_error($conn)) . "
        </div>";
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Column</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Menambahkan Kolom tanggal_selesai</h2>
        <p>Silakan cek pesan di atas dan kembali ke halaman utama.</p>
        <a href="dashboard_pembeli.php" class="btn">Kembali ke Dashboard</a>
    </div>
</body>
</html>
