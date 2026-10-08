<?php
/**
 * Database Setup Script
 * Jalankan file ini sekali untuk membuat semua tabel yang diperlukan
 * Akses: http://localhost/toko-buku/toko-buku/setup_database.php
 */

$host = "localhost";
$user = "root";
$pass = "";
$db = "toko-buku";

// Koneksi tanpa database terlebih dahulu
$conn = mysqli_connect($host, $user, $pass);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Buat database jika belum ada
$sql_db = "CREATE DATABASE IF NOT EXISTS `toko-buku` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (mysqli_query($conn, $sql_db)) {
    echo "✓ Database 'toko-buku' berhasil dibuat/ada.<br>";
} else {
    echo "✗ Error membuat database: " . mysqli_error($conn) . "<br>";
}

// Pilih database
mysqli_select_db($conn, $db);

// Array untuk menyimpan hasil pembuatan tabel
$tables_status = [];

// ========== TABEL USERS ==========
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id_user INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nik VARCHAR(16) UNIQUE,
    role ENUM('admin', 'super_admin', 'pembeli', 'penjual') DEFAULT 'pembeli',
    status ENUM('online', 'offline', 'banned') DEFAULT 'offline',
    remember_token VARCHAR(255),
    last_activity TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql_users)) {
    $tables_status['users'] = '✓ Berhasil dibuat';
} else {
    $tables_status['users'] = '✗ ' . mysqli_error($conn);
}

// ========== TABEL KATEGORI ==========
$sql_kategori = "CREATE TABLE IF NOT EXISTS kategori (
    id_kategori INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql_kategori)) {
    $tables_status['kategori'] = '✓ Berhasil dibuat';
} else {
    $tables_status['kategori'] = '✗ ' . mysqli_error($conn);
}

// ========== TABEL BUKU ==========
$sql_buku = "CREATE TABLE IF NOT EXISTS buku (
    id_buku INT PRIMARY KEY AUTO_INCREMENT,
    judul VARCHAR(200) NOT NULL,
    penulis VARCHAR(100) NOT NULL,
    penerbit VARCHAR(100),
    id_kategori INT,
    harga DECIMAL(10, 2) NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    gambar VARCHAR(255),
    deskripsi TEXT,
    sinopsis TEXT,
    isbn VARCHAR(20),
    tahun_terbit INT,
    rating DECIMAL(3, 2) DEFAULT 0,
    jumlah_rating INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori) ON DELETE SET NULL,
    INDEX idx_kategori (id_kategori),
    FULLTEXT INDEX ft_judul_penulis (judul, penulis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql_buku)) {
    $tables_status['buku'] = '✓ Berhasil dibuat';
} else {
    $tables_status['buku'] = '✗ ' . mysqli_error($conn);
}

// ========== TABEL KERANJANG ==========
$sql_keranjang = "CREATE TABLE IF NOT EXISTS keranjang (
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

if (mysqli_query($conn, $sql_keranjang)) {
    $tables_status['keranjang'] = '✓ Berhasil dibuat';
} else {
    $tables_status['keranjang'] = '✗ ' . mysqli_error($conn);
}

// ========== TABEL TRANSAKSI ==========
$sql_transaksi = "CREATE TABLE IF NOT EXISTS transaksi (
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

if (mysqli_query($conn, $sql_transaksi)) {
    $tables_status['transaksi'] = '✓ Berhasil dibuat';
} else {
    $tables_status['transaksi'] = '✗ ' . mysqli_error($conn);
}

// ========== TABEL DETAIL TRANSAKSI ==========
$sql_detail_transaksi = "CREATE TABLE IF NOT EXISTS detail_transaksi (
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

if (mysqli_query($conn, $sql_detail_transaksi)) {
    $tables_status['detail_transaksi'] = '✓ Berhasil dibuat';
} else {
    $tables_status['detail_transaksi'] = '✗ ' . mysqli_error($conn);
}

// ========== TABEL REVIEW ==========
$sql_review = "CREATE TABLE IF NOT EXISTS review (
    id_review INT PRIMARY KEY AUTO_INCREMENT,
    id_buku INT NOT NULL,
    id_user INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    komentar TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_buku) REFERENCES buku(id_buku) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql_review)) {
    $tables_status['review'] = '✓ Berhasil dibuat';
} else {
    $tables_status['review'] = '✗ ' . mysqli_error($conn);
}

// ========== TABEL WISHLIST ==========
$sql_wishlist = "CREATE TABLE IF NOT EXISTS wishlist (
    id_wishlist INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    id_buku INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (id_user, id_buku),
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_buku) REFERENCES buku(id_buku) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql_wishlist)) {
    $tables_status['wishlist'] = '✓ Berhasil dibuat';
} else {
    $tables_status['wishlist'] = '✗ ' . mysqli_error($conn);
}

// ========== TABEL CHAT ==========
$sql_chat = "CREATE TABLE IF NOT EXISTS chat (
    id_chat INT PRIMARY KEY AUTO_INCREMENT,
    id_pengirim INT NOT NULL,
    id_penerima INT NOT NULL,
    pesan TEXT NOT NULL,
    tipe ENUM('text', 'file', 'gambar') DEFAULT 'text',
    file_path VARCHAR(255),
    dibaca BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pengirim) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_penerima) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql_chat)) {
    $tables_status['chat'] = '✓ Berhasil dibuat';
} else {
    $tables_status['chat'] = '✗ ' . mysqli_error($conn);
}

// ========== TABEL ADMIN ==========
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
    $tables_status['admin'] = '✓ Berhasil dibuat';
} else {
    $tables_status['admin'] = '✗ ' . mysqli_error($conn);
}

mysqli_close($conn);

// Tampilkan HTML
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup | BookVerse</title>
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
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        
        .status-item.success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        
        .status-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        
        .status-icon {
            font-size: 1.2rem;
            margin-right: 12px;
            min-width: 24px;
        }
        
        .status-text {
            font-size: 0.95rem;
            color: #333;
            word-break: break-word;
        }
        
        .success .status-text {
            color: #065f46;
        }
        
        .error .status-text {
            color: #7f1d1d;
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
        }
        
        a:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📚 Database Setup Complete</h1>
        <p class="subtitle">Pembuatan struktur database untuk BookVerse</p>
        
        <?php foreach ($tables_status as $table => $status): ?>
            <div class="status-item <?php echo strpos($status, '✓') !== false ? 'success' : 'error'; ?>">
                <span class="status-icon">
                    <?php echo strpos($status, '✓') !== false ? '✓' : '✗'; ?>
                </span>
                <span class="status-text">
                    <strong><?php echo ucfirst($table); ?></strong>: <?php echo $status; ?>
                </span>
            </div>
        <?php endforeach; ?>
        
        <div class="footer">
            ✅ Semua tabel berhasil dibuat! Aplikasi Anda siap digunakan.
            <div class="btn-group">
                <a href="login.php">← Kembali ke Login</a>
            </div>
        </div>
    </div>
</body>
</html>
