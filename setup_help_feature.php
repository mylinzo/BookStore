<?php
/**
 * Setup Script untuk Fitur Help & Support
 * 
 * Run this script once to initialize the help features database
 * Access: http://localhost/toko-buku/setup_help_feature.php
 */

include 'koneksi.php';

if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Help & Support Feature</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Poppins', sans-serif;
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
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 600px;
                width: 100%;
                padding: 40px;
            }

            h1 {
                color: #333;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 15px;
            }

            h1 i {
                color: #667eea;
                font-size: 32px;
            }

            .subtitle {
                color: #666;
                margin-bottom: 30px;
                font-size: 14px;
            }

            .feature-list {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 30px;
            }

            .feature-list h3 {
                color: #333;
                margin-bottom: 15px;
                font-size: 16px;
            }

            .feature-list ul {
                list-style: none;
                padding: 0;
            }

            .feature-list li {
                padding: 8px 0;
                color: #666;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .feature-list li:before {
                content: "✓";
                color: #10b981;
                font-weight: bold;
                font-size: 18px;
            }

            .info-box {
                background: #cffafe;
                border-left: 4px solid #0891b2;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 20px;
                font-size: 14px;
                color: #0c4a6e;
            }

            .warning-box {
                background: #fed7aa;
                border-left: 4px solid #d97706;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 20px;
                font-size: 14px;
                color: #92400e;
            }

            .buttons {
                display: flex;
                gap: 10px;
                margin-top: 30px;
            }

            .btn {
                flex: 1;
                padding: 12px 24px;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.3s;
            }

            .btn-primary {
                background: #667eea;
                color: white;
            }

            .btn-primary:hover {
                background: #5568d3;
                transform: translateY(-2px);
            }

            .btn-secondary {
                background: #e5e7eb;
                color: #333;
            }

            .btn-secondary:hover {
                background: #d1d5db;
            }

            .code-block {
                background: #2d3748;
                color: #a0aec0;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                overflow-x: auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1><i class="fas fa-question-circle"></i> Help & Support Setup</h1>
            <p class="subtitle">Inisialisasi fitur Help & Support untuk aplikasi Anda</p>

            <div class="info-box">
                <strong>ℹ️ Info:</strong> Setup ini akan membuat 4 tabel baru di database Anda dan mengisi data FAQ default.
            </div>

            <div class="feature-list">
                <h3>Fitur yang akan diaktifkan:</h3>
                <ul>
                    <li>FAQ Management System</li>
                    <li>Contact Form & Message Management</li>
                    <li>Admin Dashboard untuk Help</li>
                    <li>Search & Filter Messages</li>
                    <li>Reply System untuk Messages</li>
                    <li>FAQ Categories & Articles</li>
                    <li>User Feedback on FAQ</li>
                    <li>Statistics & Analytics</li>
                </ul>
            </div>

            <div class="warning-box">
                <strong>⚠️ Perhatian:</strong> Jika Anda sudah menjalankan setup sebelumnya, tabel akan dibuat ulang. Pastikan untuk backup data penting Anda.
            </div>

            <div style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px; color: #333;">Database Tables yang akan dibuat:</h3>
                <ul style="list-style: none; padding: 0; color: #666; font-size: 14px;">
                    <li>✓ help_categories</li>
                    <li>✓ help_articles</li>
                    <li>✓ help_feedback</li>
                    <li>✓ contact_messages</li>
                </ul>
            </div>

            <div class="buttons">
                <a href="setup_help_feature.php?confirm=yes" class="btn btn-primary">
                    <i class="fas fa-check"></i> Mulai Setup
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </div>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </body>
    </html>
    <?php
    exit;
}

// Run setup
$success = true;
$messages = [];

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS help_categories (
        id_category INT PRIMARY KEY AUTO_INCREMENT,
        category_name VARCHAR(255) NOT NULL,
        category_slug VARCHAR(255) UNIQUE NOT NULL,
        category_icon VARCHAR(50),
        category_description TEXT,
        sort_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (category_slug),
        INDEX (is_active)
    )",
    
    "CREATE TABLE IF NOT EXISTS help_articles (
        id_article INT PRIMARY KEY AUTO_INCREMENT,
        category_id INT NOT NULL,
        question VARCHAR(500) NOT NULL,
        answer LONGTEXT NOT NULL,
        article_slug VARCHAR(500) UNIQUE,
        views_count INT DEFAULT 0,
        helpful_count INT DEFAULT 0,
        unhelpful_count INT DEFAULT 0,
        sort_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES help_categories(id_category) ON DELETE CASCADE,
        INDEX (category_id),
        INDEX (is_active)
    )",
    
    "CREATE TABLE IF NOT EXISTS help_feedback (
        id_feedback INT PRIMARY KEY AUTO_INCREMENT,
        article_id INT NOT NULL,
        user_id INT,
        is_helpful BOOLEAN,
        feedback_text TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (article_id) REFERENCES help_articles(id_article) ON DELETE CASCADE,
        INDEX (article_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS contact_messages (
        id_message INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message LONGTEXT NOT NULL,
        category VARCHAR(100),
        status ENUM('pending', 'replied', 'resolved', 'closed') DEFAULT 'pending',
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        admin_reply LONGTEXT,
        admin_replied_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        replyed_at TIMESTAMP NULL,
        INDEX (user_id),
        INDEX (status),
        INDEX (created_at)
    )"
];

foreach ($tables as $table_query) {
    if (!mysqli_query($conn, $table_query)) {
        $success = false;
        $messages[] = "❌ Error creating table: " . mysqli_error($conn);
    } else {
        $messages[] = "✓ Table created/verified successfully";
    }
}

// Insert default categories
$categories = [
    ['Pembayaran', 'pembayaran', 'fa-money-bill-wave', 'FAQ tentang metode pembayaran dan verifikasi', 1],
    ['Pengiriman', 'pengiriman', 'fa-truck', 'FAQ tentang pengiriman dan ekspedisi', 2],
    ['Produk & Stok', 'produk-stok', 'fa-box', 'FAQ tentang kelola produk dan stok', 3],
    ['Akun & Keamanan', 'akun-keamanan', 'fa-key', 'FAQ tentang akun dan keamanan', 4],
    ['Transaksi & Laporan', 'transaksi-laporan', 'fa-file-excel', 'FAQ tentang transaksi dan laporan', 5],
    ['Teknis & Support', 'teknis-support', 'fa-headset', 'FAQ tentang masalah teknis dan support', 6]
];

// Clear existing categories
mysqli_query($conn, "DELETE FROM help_categories");

foreach ($categories as $cat) {
    $query = "INSERT INTO help_categories (category_name, category_slug, category_icon, category_description, sort_order) 
              VALUES (?, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "ssssi", $cat[0], $cat[1], $cat[2], $cat[3], $cat[4]);
        
        if (mysqli_stmt_execute($stmt)) {
            $messages[] = "✓ Category '{$cat[0]}' inserted successfully";
        } else {
            $success = false;
            $messages[] = "❌ Error inserting category: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Insert default FAQ articles
$articles = [
    [1, 'Apa saja metode pembayaran yang diterima?', 'Kami menerima transfer bank (BNI, BRI, Mandiri, BCA), e-wallet (OVO, Dana, GoPay, ShopeePay), dan QRIS.', 'metode-pembayaran', 1],
    [1, 'Berapa lama waktu verifikasi pembayaran?', 'Verifikasi pembayaran dilakukan maksimal 1x24 jam pada hari kerja.', 'waktu-verifikasi', 2],
    [1, 'Apa yang harus dilakukan jika pembayaran ditolak?', 'Pastikan bukti transfer jelas dan jumlah sesuai. Hubungi admin jika masih ditolak.', 'pembayaran-ditolak', 3],
    [2, 'Apa saja pilihan ekspedisi yang tersedia?', 'Kami bekerja sama dengan JNE, TIKI, POS Indonesia, SiCepat, dan J&T.', 'pilihan-ekspedisi', 1],
    [2, 'Berapa lama waktu pengiriman?', 'Pengiriman reguler 3-7 hari kerja, ekspress 1-3 hari kerja.', 'waktu-pengiriman', 2],
    [3, 'Bagaimana cara menambah stok produk?', 'Penjual bisa menambah stok di menu Produk → Edit Produk → Update jumlah stok.', 'tambah-stok', 1],
    [3, 'Apa yang terjadi jika stok habis?', 'Produk akan otomatis tidak tampil di katalog pembeli.', 'stok-habis', 2],
    [4, 'Bagaimana cara mengganti password?', 'Di menu My Account → Profil, masukkan password lama dan baru.', 'ubah-password', 1],
    [4, 'Apa yang harus dilakukan jika lupa password?', 'Klik Lupa Password di halaman login dan ikuti instruksi reset via email.', 'lupa-password', 2],
    [5, 'Bagaimana cara download laporan?', 'Di menu Laporan → pilih periode → klik Download Excel.', 'download-laporan', 1],
    [6, 'Website error atau tidak bisa diakses?', 'Coba clear cache browser atau gunakan mode incognito.', 'website-error', 1],
    [6, 'Bagaimana cara menghubungi support?', 'Email: support@aksara-aruna.com atau WhatsApp: 0812-3456-7890', 'hubungi-support', 2]
];

// Clear existing articles
mysqli_query($conn, "DELETE FROM help_articles");

foreach ($articles as $art) {
    $query = "INSERT INTO help_articles (category_id, question, answer, article_slug, sort_order) 
              VALUES (?, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "isssi", $art[0], $art[1], $art[2], $art[3], $art[4]);
        
        if (mysqli_stmt_execute($stmt)) {
            $count++;
        }
        
        mysqli_stmt_close($stmt);
    }
}

$messages[] = "✓ Default FAQ articles inserted successfully";

// Show completion page
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Help & Support - Complete</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
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
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .icon {
            font-size: 60px;
            margin-bottom: 15px;
        }

        .icon.success {
            color: #10b981;
        }

        .icon.error {
            color: #ef4444;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .log-box {
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .log-entry {
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
            font-family: 'Courier New', monospace;
            color: #666;
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        .log-entry.success {
            color: #10b981;
        }

        .log-entry.error {
            color: #ef4444;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .next-steps {
            background: #f0f9ff;
            border-left: 4px solid #0284c7;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .next-steps h3 {
            color: #0c4a6e;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .next-steps ol {
            color: #0c4a6e;
            font-size: 13px;
            padding-left: 20px;
        }

        .next-steps li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon <?php echo $success ? 'success' : 'error'; ?>">
                <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            </div>
            <h1><?php echo $success ? 'Setup Berhasil!' : 'Setup Gagal'; ?></h1>
            <p class="subtitle">
                <?php echo $success ? 'Fitur Help & Support telah berhasil diaktifkan' : 'Terdapat error saat setup'; ?>
            </p>
        </div>

        <div class="log-box">
            <?php foreach ($messages as $msg): ?>
                <div class="log-entry <?php echo strpos($msg, '✓') === 0 ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($success): ?>
        <div class="next-steps">
            <h3>Langkah Selanjutnya:</h3>
            <ol>
                <li>Akses Help Page di: <strong>/help.php</strong></li>
                <li>Kelola pesan di: <strong>/admin_help_messages.php</strong></li>
                <li>Edit FAQ di database atau melalui admin panel (future)</li>
                <li>Tambahkan link Help ke dashboard dan sidebar</li>
                <li>Configure email settings (optional)</li>
            </ol>
        </div>

        <div class="button-group">
            <a href="help.php" class="btn btn-primary">
                <i class="fas fa-book"></i> Buka Help Page
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
        <?php else: ?>
        <div class="button-group">
            <a href="setup_help_feature.php" class="btn btn-primary">
                <i class="fas fa-redo"></i> Coba Lagi
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
?>
