<?php
/**
 * Direct Database Setup - Ciptakan tabel secara langsung
 * Akses: http://localhost/toko-buku/toko-buku/setup_admin_table.php
 */

$host = "localhost";
$username = "root";
$password = "";
$database = "toko-buku";

// Koneksi ke database
$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    echo "❌ Koneksi gagal: " . mysqli_connect_error();
    exit;
}

mysqli_set_charset($conn, "utf8mb4");

// Buat tabel admin jika belum ada
$sql_admin = "CREATE TABLE IF NOT EXISTS admin (
    id_admin INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_admin VARCHAR(100),
    status ENUM('aktif', 'tidak aktif', 'online', 'offline') DEFAULT 'offline',
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql_admin)) {
    echo "✅ Tabel admin berhasil dibuat!<br>";
    
    // Cek apakah sudah ada data admin default
    $check = mysqli_query($conn, "SELECT COUNT(*) as total FROM admin");
    $result = mysqli_fetch_assoc($check);
    
    if ($result['total'] == 0) {
        // Masukkan admin default jika belum ada
        $default_password = password_hash("admin123", PASSWORD_BCRYPT);
        $insert = mysqli_query($conn, "INSERT INTO admin (username, email, password, nama_admin, status) 
                                      VALUES ('admin', 'admin@toko-buku.local', '$default_password', 'Super Admin', 'aktif')");
        
        if ($insert) {
            echo "✅ Admin default berhasil dibuat!<br>";
            echo "📝 Username: <strong>admin</strong><br>";
            echo "🔐 Password: <strong>admin123</strong><br>";
            echo "<br><a href='login.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'>Login sekarang</a>";
        } else {
            echo "❌ Gagal membuat admin default: " . mysqli_error($conn);
        }
    } else {
        echo "✅ Tabel admin sudah memiliki data.<br>";
        echo "<br><a href='login.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'>Lanjut ke Login</a>";
    }
} else {
    echo "❌ Gagal membuat tabel admin: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin Table</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        a {
            margin-top: 20px;
        }
    </style>
</head>
<body>
</body>
</html>
