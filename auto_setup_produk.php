<?php
/**
 * Auto Setup untuk Tabel Produk
 * Jalankan SEKALI: http://localhost/toko-buku/toko-buku/auto_setup_produk.php
 */

header('Content-Type: text/html; charset=utf-8');

include 'koneksi.php';

$setup_results = [];
$errors = [];

try {
    // ========== CRE ATE TABEL PRODUK ==========
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
        $setup_results[] = '✓ Tabel produk berhasil dibuat/sudah ada';
    } else {
        $errors[] = '✗ Error membuat tabel produk: ' . mysqli_error($conn);
    }

    // ========== CREATE TABEL KERANJANG_PRODUK ==========
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
        $setup_results[] = '✓ Tabel keranjang_produk berhasil dibuat/sudah ada';
    } else {
        $errors[] = '✗ Error membuat tabel keranjang_produk: ' . mysqli_error($conn);
    }

    // ========== CREATE TABEL PESANAN ==========
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
        $setup_results[] = '✓ Tabel pesanan berhasil dibuat/sudah ada';
    } else {
        $errors[] = '✗ Error membuat tabel pesanan: ' . mysqli_error($conn);
    }

    // ========== UPDATE USERS TABLE ==========
    $alter_users = "ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'pembeli', 'penjual') DEFAULT 'pembeli'";
    @mysqli_query($conn, $alter_users);
    $setup_results[] = '✓ Role penjual ditambahkan ke tabel users';

    // ========== INSERT SAMPLE DATA ==========
    // Check if sample data already exists
    $check_sample = mysqli_query($conn, "SELECT COUNT(*) as count FROM produk");
    $sample_row = mysqli_fetch_assoc($check_sample);
    
    if ($sample_row['count'] == 0) {
        // Get or create a seller user
        $seller_check = mysqli_query($conn, "SELECT id_user FROM users WHERE role = 'penjual' LIMIT 1");
        $seller = mysqli_fetch_assoc($seller_check);
        
        if ($seller) {
            $seller_id = $seller['id_user'];
        } else {
            // Create a sample seller if not exists
            $seller_email = 'penjual@pustaka-aruna.local';
            $seller_check_email = mysqli_query($conn, "SELECT id_user FROM users WHERE email = '$seller_email'");
            
            if (mysqli_num_rows($seller_check_email) == 0) {
                $seller_name = 'Pustaka Aruna Store';
                $seller_pass = password_hash('password123', PASSWORD_DEFAULT);
                $insert_seller = "INSERT INTO users (nama, email, password, role, status) VALUES ('$seller_name', '$seller_email', '$seller_pass', 'penjual', 'online')";
                
                if (mysqli_query($conn, $insert_seller)) {
                    $seller_id = mysqli_insert_id($conn);
                    $setup_results[] = '✓ Akun penjual sampel berhasil dibuat (email: ' . $seller_email . ')';
                } else {
                    $errors[] = '✗ Error membuat akun penjual: ' . mysqli_error($conn);
                    $seller_id = 1;
                }
            } else {
                $seller_result = mysqli_fetch_assoc($seller_check_email);
                $seller_id = $seller_result['id_user'];
            }
        }

        // Insert sample products
        $sample_products = [
            [
                'nama' => 'Aku Lulus Ujian',
                'harga' => 45000,
                'stok' => 15,
                'deskripsi' => 'Buku motivasi untuk meraih kesuksesan ujian',
            ],
            [
                'nama' => 'Jalan Menuju Mimpi',
                'harga' => 52000,
                'stok' => 12,
                'deskripsi' => 'Inspirasi untuk mencapai impian Anda',
            ],
            [
                'nama' => 'Cinta di Musim Semi',
                'harga' => 48000,
                'stok' => 20,
                'deskripsi' => 'Novel romance yang menyentuh hati',
            ],
            [
                'nama' => 'Dunia Teknologi Modern',
                'harga' => 65000,
                'stok' => 8,
                'deskripsi' => 'Panduan lengkap teknologi terkini',
            ],
            [
                'nama' => 'Psikologi Bisnis',
                'harga' => 58000,
                'stok' => 10,
                'deskripsi' => 'Memahami psikologi dalam dunia bisnis',
            ],
            [
                'nama' => 'Petualangan Fantasi',
                'harga' => 55000,
                'stok' => 18,
                'deskripsi' => 'Kisah petualangan yang penuh misteri',
            ],
        ];

        $inserted_count = 0;
        foreach ($sample_products as $product) {
            $insert_product = "INSERT INTO produk (nama_buku, id_penjual, harga, stok, deskripsi, modal) 
                              VALUES ('" . mysqli_real_escape_string($conn, $product['nama']) . "', $seller_id, " . 
                              $product['harga'] . ", " . $product['stok'] . ", '" . 
                              mysqli_real_escape_string($conn, $product['deskripsi']) . "', " . 
                              ($product['harga'] * 0.6) . ")";
            
            if (mysqli_query($conn, $insert_product)) {
                $inserted_count++;
            }
        }

        if ($inserted_count > 0) {
            $setup_results[] = "✓ " . $inserted_count . " produk sampel berhasil ditambahkan";
        }
    } else {
        $setup_results[] = "✓ Produk sudah ada di database (" . $sample_row['count'] . " produk)";
    }

} catch (Exception $e) {
    $errors[] = '✗ Error: ' . $e->getMessage();
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Setup Produk - Pustaka Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #8b4513 0%, #d4af37 100%);
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
            color: #8b4513;
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
            border-left: 4px solid #2ecc71;
            background: #f0fdf4;
        }

        .status-item.error {
            border-left-color: #e74c3c;
            background: #fadbd8;
        }

        .status-icon {
            font-size: 18px;
            margin-right: 15px;
            min-width: 20px;
            text-align: center;
        }

        .status-item.error .status-icon {
            color: #e74c3c;
        }

        .status-item.success .status-icon {
            color: #2ecc71;
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
            background: linear-gradient(135deg, #8b4513, #d4af37);
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
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.3);
        }

        .success-box {
            background: #d1f3d1;
            border: 1px solid #7ec97e;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: #2d5a2d;
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
            <h1><i class="fas fa-book"></i> Setup Produk Pustak a Aruna</h1>
            <p>Inisialisasi Tabel dan Data Sampel</p>
        </div>

        <?php if (empty($errors)): ?>
            <div class="success-box">
                <i class="fas fa-check-circle"></i>
                <h2>✓ Setup Berhasil!</h2>
                <p style="margin-top: 10px;">Semua tabel dan data sampel siap. Dashboard akan menampilkan produk.</p>
            </div>
        <?php endif; ?>

        <div class="status-list">
            <?php foreach ($setup_results as $result): ?>
                <div class="status-item success">
                    <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="status-text">
                        <strong><?php echo $result; ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error): ?>
                <div class="status-item error">
                    <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="status-text">
                        <strong><?php echo $error; ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <a href="dashboard_pembeli.php" class="btn">
                <i class="fas fa-arrow-right"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
</body>
</html>
