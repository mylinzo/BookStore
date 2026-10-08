<?php
/**
 * Insert Sample Data Script
 * Script ini menambahkan data contoh untuk testing
 * Akses: http://localhost/toko-buku/toko-buku/insert_sample_data.php
 */

$host = "localhost";
$user = "root";
$pass = "";
$db = "toko-buku";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$insert_status = [];

// ========== INSERT DATA KATEGORI ==========
$check_kategori = "SELECT COUNT(*) as total FROM kategori";
$result = mysqli_query($conn, $check_kategori);
$row = mysqli_fetch_assoc($result);

if ($row['total'] == 0) {
    $kategori_data = [
        ['nama_kategori' => 'Fiksi', 'deskripsi' => 'Buku-buku cerita fiksi dan imajinasi'],
        ['nama_kategori' => 'Non-Fiksi', 'deskripsi' => 'Buku-buku faktual dan ilmu pengetahuan'],
        ['nama_kategori' => 'Self-Help', 'deskripsi' => 'Buku pengembangan diri'],
        ['nama_kategori' => 'Bisnis', 'deskripsi' => 'Buku tentang dunia bisnis'],
        ['nama_kategori' => 'Teknologi', 'deskripsi' => 'Buku tentang teknologi dan programming']
    ];
    
    $sql = "INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        foreach ($kategori_data as $kat) {
            mysqli_stmt_bind_param($stmt, "ss", $kat['nama_kategori'], $kat['deskripsi']);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $insert_status['kategori'] = '✓ 5 kategori berhasil ditambahkan';
    } else {
        $insert_status['kategori'] = '✗ Gagal insert kategori: ' . mysqli_error($conn);
    }
} else {
    $insert_status['kategori'] = '✓ Data kategori sudah ada (' . $row['total'] . ' data)';
}

// ========== INSERT DATA BUKU ==========
$check_buku = "SELECT COUNT(*) as total FROM buku";
$result = mysqli_query($conn, $check_buku);
$row = mysqli_fetch_assoc($result);

if ($row['total'] == 0) {
    $buku_data = [
        ['judul' => 'Laskar Pelangi', 'penulis' => 'Andrea Hirata', 'penerbit' => 'Bentang Pustaka', 'id_kategori' => 1, 'harga' => 85000, 'stok' => 20, 'isbn' => '978-9799063582', 'tahun_terbit' => 2005],
        ['judul' => 'Bumi', 'penulis' => 'Tere Liye', 'penerbit' => 'Wattpad Books', 'id_kategori' => 1, 'harga' => 65000, 'stok' => 15, 'isbn' => '978-6024324205', 'tahun_terbit' => 2014],
        ['judul' => 'Sebuah Saat untuk Bertemu', 'penulis' => 'Ika Natassa', 'penerbit' => 'Gramedia', 'id_kategori' => 1, 'harga' => 75000, 'stok' => 10, 'isbn' => '978-9799108394', 'tahun_terbit' => 2004],
        ['judul' => 'Atomic Habits', 'penulis' => 'James Clear', 'penerbit' => 'Avery', 'id_kategori' => 3, 'harga' => 200000, 'stok' => 25, 'isbn' => '978-0735211292', 'tahun_terbit' => 2018],
        ['judul' => 'Clean Code', 'penulis' => 'Robert C. Martin', 'penerbit' => 'Prentice Hall', 'id_kategori' => 5, 'harga' => 350000, 'stok' => 8, 'isbn' => '978-0132350884', 'tahun_terbit' => 2008],
        ['judul' => 'Sapiens', 'penulis' => 'Yuval Noah Harari', 'penerbit' => 'Harper', 'id_kategori' => 2, 'harga' => 150000, 'stok' => 12, 'isbn' => '978-0062316097', 'tahun_terbit' => 2011],
        ['judul' => 'Python untuk Pemula', 'penulis' => 'Ade Cahyadi', 'penerbit' => 'Informatika', 'id_kategori' => 5, 'harga' => 120000, 'stok' => 30, 'isbn' => '978-6024324212', 'tahun_terbit' => 2020]
    ];
    
    $sql = "INSERT INTO buku (judul, penulis, penerbit, id_kategori, harga, stok, isbn, tahun_terbit) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        foreach ($buku_data as $bk) {
            mysqli_stmt_bind_param($stmt, "sssiidsi", 
                $bk['judul'], $bk['penulis'], $bk['penerbit'], $bk['id_kategori'],
                $bk['harga'], $bk['stok'], $bk['isbn'], $bk['tahun_terbit']
            );
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $insert_status['buku'] = '✓ 7 buku berhasil ditambahkan';
    } else {
        $insert_status['buku'] = '✗ Gagal insert buku: ' . mysqli_error($conn);
    }
} else {
    $insert_status['buku'] = '✓ Data buku sudah ada (' . $row['total'] . ' data)';
}

// ========== INSERT DATA USER ==========
$check_user = "SELECT COUNT(*) as total FROM users WHERE role = 'pembeli'";
$result = mysqli_query($conn, $check_user);
$row = mysqli_fetch_assoc($result);

if ($row['total'] == 0) {
    $user_data = [
        ['nama' => 'Yeona', 'email' => 'yeona@gmail.com', 'password' => 'yeona1022', 'role' => 'pembeli'],
        ['nama' => 'Budi Santoso', 'email' => 'budi@example.com', 'password' => 'password123', 'role' => 'pembeli'],
        ['nama' => 'Siti Nurhaliza', 'email' => 'siti@example.com', 'password' => 'password123', 'role' => 'pembeli']
    ];
    
    $sql = "INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        foreach ($user_data as $usr) {
            mysqli_stmt_bind_param($stmt, "ssss", $usr['nama'], $usr['email'], $usr['password'], $usr['role']);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $insert_status['user'] = '✓ 3 user pembeli berhasil ditambahkan';
    } else {
        $insert_status['user'] = '✗ Gagal insert user: ' . mysqli_error($conn);
    }
} else {
    $insert_status['user'] = '✓ Data user pembeli sudah ada (' . $row['total'] . ' data)';
}

// ========== CEK ADMIN USER ==========
$check_admin = "SELECT COUNT(*) as total FROM users WHERE role = 'admin'";
$result = mysqli_query($conn, $check_admin);
$row = mysqli_fetch_assoc($result);

if ($row['total'] == 0) {
    // Hash password admin
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        $nama = "Admin BookVerse";
        $email = "admin@bookverse.com";
        $role = "admin";
        mysqli_stmt_bind_param($stmt, "ssss", $nama, $email, $admin_password, $role);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $insert_status['admin'] = '✓ Admin user berhasil dibuat (Email: admin@bookverse.com, Password: admin123)';
    } else {
        $insert_status['admin'] = '✗ Gagal membuat admin user: ' . mysqli_error($conn);
    }
} else {
    $insert_status['admin'] = '✓ Admin user sudah ada';
}

mysqli_close($conn);

// Tampilkan HTML
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Sample Data | BookVerse</title>
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
            max-width: 650px;
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
            align-items: flex-start;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            gap: 10px;
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
            margin-top: 2px;
            min-width: 24px;
        }
        
        .status-text {
            font-size: 0.95rem;
            color: #333;
            word-break: break-word;
            flex: 1;
        }
        
        .success .status-text {
            color: #065f46;
        }
        
        .error .status-text {
            color: #7f1d1d;
        }
        
        .creds {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 0.9rem;
        }
        
        .creds strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .creds code {
            display: block;
            background: white;
            padding: 5px 10px;
            border-radius: 4px;
            margin: 3px 0;
            font-family: 'Courier New', monospace;
            color: #667eea;
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
            flex-wrap: wrap;
        }
        
        a {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
            font-weight: 500;
        }
        
        a:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📚 Insert Sample Data</h1>
        <p class="subtitle">Menambahkan data contoh untuk testing</p>
        
        <?php foreach ($insert_status as $name => $status): 
            $is_success = strpos($status, '✓') !== false;
        ?>
            <div class="status-item <?php echo $is_success ? 'success' : 'error'; ?>">
                <span class="status-icon">
                    <?php echo $is_success ? '✓' : '✗'; ?>
                </span>
                <span class="status-text"><?php echo $status; ?></span>
            </div>
        <?php endforeach; ?>
        
        <div class="creds">
            <strong>📝 AKUN UNTUK LOGIN:</strong>
            <code><strong>Pembeli:</strong></code>
            <code>Email: yeona@gmail.com</code>
            <code>Password: yeona1022</code>
            
            <code style="margin-top: 10px;"><strong>Admin:</strong></code>
            <code>Email: admin@bookverse.com</code>
            <code>Password: admin123</code>
        </div>
        
        <div class="footer">
            ✅ Data contoh sudah ditambahkan! Sekarang Anda bisa test aplikasi.
            <div class="btn-group">
                <a href="login.php">← Login</a>
                <a href="keranjang.php">Keranjang →</a>
                <a href="dashboard.php">Dashboard →</a>
            </div>
        </div>
    </div>
</body>
</html>
