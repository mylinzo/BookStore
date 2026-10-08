<?php
session_start();

// Koneksi ke database
$host = "localhost";
$user = "root";
$pass = "";
$db   = "toko-buku"; 

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// ========== LOGOUT HANDLING DI AWAL ==========
if (isset($_GET['logout'])) {
    // Update status user menjadi offline jika ada session
    if (isset($_SESSION["id"])) {
        $user_id = $_SESSION["id"];
        $update_sql = "UPDATE users SET status = 'offline' WHERE id_user = ?";
        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
            mysqli_stmt_bind_param($update_stmt, "i", $user_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
    }
    
    // Hapus cookie remember me
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $sql = "UPDATE users SET remember_token = NULL WHERE remember_token = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $token);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Hapus semua data session
    $_SESSION = array();
    
    // Hapus cookie session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Hancurkan session
    session_destroy();
    
    // Redirect ke login dengan parameter
    header("location: login.php?logout=success");
    exit; // PASTIKAN keluar setelah redirect
}

// ========== CEK LOGIN STATUS ==========
// Jika tidak ada session loggedin, redirect ke login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// ========== AMBIL DATA USER ==========
$user_id = isset($_SESSION["id"]) ? $_SESSION["id"] : 0;
$sql = "SELECT nama, email, role, status FROM users WHERE id_user = ?";
$nama = $email = $role = $status = '';
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $nama, $email, $role, $status);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

// ========== CEK ROLE ==========
// Jika user bukan pembeli, redirect ke halaman admin
if ($role !== 'pembeli') {
    header("location: admin_dashboard.php");
    exit;
}

// ========== UPDATE STATUS ONLINE ==========
$update_sql = "UPDATE users SET status = 'online' WHERE id_user = ?";
if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
    mysqli_stmt_bind_param($update_stmt, "i", $user_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
}

// ========== QUERY DATA DASHBOARD ==========
// Total pesanan
$sql_pesanan = "SELECT COUNT(*) as total FROM transaksi WHERE id_pembeli = ?";
$total_pesanan = 0;
if ($stmt = mysqli_prepare($conn, $sql_pesanan)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);
    $total_pesanan = $total;
    mysqli_stmt_close($stmt);
}

// Total transaksi selesai
$sql_selesai = "SELECT COUNT(*) as total FROM transaksi WHERE id_pembeli = ? AND status = 'selesai'";
$total_selesai = 0;
if ($stmt = mysqli_prepare($conn, $sql_selesai)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);
    $total_selesai = $total;
    mysqli_stmt_close($stmt);
}

// Total pesanan menunggu
$sql_menunggu = "SELECT COUNT(*) as total FROM transaksi WHERE id_pembeli = ? AND status = 'menunggu_pembayaran'";
$total_menunggu = 0;
if ($stmt = mysqli_prepare($conn, $sql_menunggu)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);
    $total_menunggu = $total;
    mysqli_stmt_close($stmt);
}

// Buku terbaru (dari tabel buku)
$sql_buku = "SELECT id_buku, judul, penulis, harga, gambar FROM buku ORDER BY id_buku DESC LIMIT 6";
$buku_terbaru = [];
if ($result = mysqli_query($conn, $sql_buku)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $buku_terbaru[] = $row;
    }
    mysqli_free_result($result);
}

// Pesanan terbaru
$sql_pesanan_terbaru = "SELECT t.id_transaksi, t.total_harga, t.status, t.tanggal, 
                               COUNT(dt.id_detail) as jumlah_item
                        FROM transaksi t
                        LEFT JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
                        WHERE t.id_pembeli = ?
                        GROUP BY t.id_transaksi
                        ORDER BY t.tanggal DESC 
                        LIMIT 5";
$pesanan_terbaru = [];
if ($stmt = mysqli_prepare($conn, $sql_pesanan_terbaru)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $pesanan_terbaru[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Toko Buku Online Premium</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #f72585;
            --light-color: #f8f9ff;
            --dark-color: #2b2d42;
            --success-color: #4cc9f0;
            --warning-color: #f59e0b;
            --danger-color: #e74c3c;
            --info-color: #06b6d4;
            --border-color: #e6e8f0;
            --text-light: #6c757d;
            --gradient-primary: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            --gradient-success: linear-gradient(135deg, #4cc9f0 0%, #4895ef 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            --gradient-danger: linear-gradient(135deg, #e74c3c 0%, #ef4444 100%);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.15);
            --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.08);
            --shadow-sm: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9ff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 280px;
            background: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: var(--shadow-md);
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            background: var(--gradient-primary);
            padding: 25px 20px;
            color: white;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .logo i {
            margin-right: 12px;
            color: #f72585;
            font-size: 2rem;
        }

        .brand-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .user-profile {
            padding: 25px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .user-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .user-info p {
            font-size: 0.85rem;
            color: var(--text-light);
            margin: 0;
        }

        .user-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #10b981;
            border-radius: 50%;
            margin-left: 5px;
            vertical-align: middle;
        }

        .nav-menu {
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 14px 25px;
            color: var(--dark-color);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .nav-item:hover {
            background-color: rgba(67, 97, 238, 0.05);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .nav-item.active {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            font-weight: 500;
        }

        .nav-icon {
            width: 24px;
            margin-right: 15px;
            font-size: 1.2rem;
            text-align: center;
        }

        .nav-text {
            font-size: 0.95rem;
            font-weight: 500;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--primary-color);
            color: white;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-bar {
            flex: 1;
            max-width: 500px;
        }

        .search-input {
            width: 100%;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            font-size: 1.3rem;
            color: var(--dark-color);
            cursor: pointer;
            padding: 8px;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Dashboard Stats */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .stat-card.pesanan {
            border-top-color: var(--primary-color);
        }

        .stat-card.status {
            border-top-color: var(--warning-color);
        }

        .stat-card.chat {
            border-top-color: var(--info-color);
        }

        .stat-card.laporan {
            border-top-color: var(--success-color);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: white;
        }

        .stat-icon.pesanan {
            background: var(--gradient-primary);
        }

        .stat-icon.status {
            background: var(--gradient-warning);
        }

        .stat-icon.chat {
            background: var(--gradient-success);
        }

        .stat-icon.laporan {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
        }

        .stat-title {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
        }

        .stat-change {
            font-size: 0.85rem;
            color: #10b981;
            font-weight: 500;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            margin-top: 30px;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--dark-color);
        }

        .section-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .section-link:hover {
            text-decoration: underline;
        }

        /* Buku Terbaru */
        .buku-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .buku-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .buku-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .buku-image {
            height: 150px;
            background: linear-gradient(135deg, #f0f4ff 0%, #e6f7ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .buku-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .buku-details {
            padding: 15px;
        }

        .buku-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 48px;
        }

        .buku-author {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .buku-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .btn-beli {
            width: 100%;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-beli:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Pesanan Terbaru */
        .pesanan-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }

        .table {
            margin: 0;
        }

        .table thead {
            background: var(--gradient-primary);
            color: white;
        }

        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: var(--border-color);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-menunggu {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .status-disetujui {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-ditolak {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-dikirim {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--text-light);
            font-size: 0.85rem;
            border-top: 1px solid var(--border-color);
            margin-top: 30px;
        }

        .footer a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .top-bar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .search-bar {
                max-width: 100%;
            }
            
            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .buku-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .buku-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .buku-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 1.2rem;
            cursor: pointer;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
        }

        @media (max-width: 992px) {
            .menu-toggle {
                display: block;
            }
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
            padding: 20px;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 25px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #e9ecef;
        }

        .empty-state h4 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .empty-state p {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-book-reader"></i>
                BookVerse
            </div>
            <div class="brand-subtitle">Toko Buku Online Premium</div>
        </div>
        
        <!-- User Profile -->
        <div class="user-profile">
            <div class="user-avatar">
                <?php echo isset($nama) ? strtoupper(substr($nama, 0, 1)) : 'U'; ?>
            </div>
            <div class="user-info">
                <h3><?php echo isset($nama) ? htmlspecialchars($nama) : 'User'; ?> <span class="user-status"></span></h3>
                <p><?php echo isset($email) ? htmlspecialchars($email) : ''; ?></p>
                <p><small>Pembeli</small></p>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item active">
                <div class="nav-icon"><i class="fas fa-home"></i></div>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <a href="pesanan.php" class="nav-item">
                <div class="nav-icon"><i class="fas fa-shopping-cart"></i></div>
                <span class="nav-text">Pesanan</span>
                <?php if ($total_pesanan > 0): ?>
                <span class="nav-badge"><?php echo $total_pesanan; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="keranjang.php" class="nav-item">
                <div class="nav-icon"><i class="fas fa-shopping-bag"></i></div>
                <span class="nav-text">Keranjang</span>
            </a>
            
            <a href="status.php" class="nav-item">
                <div class="nav-icon"><i class="fas fa-clipboard-list"></i></div>
                <span class="nav-text">Status</span>
                <?php if ($total_menunggu > 0): ?>
                <span class="nav-badge"><?php echo $total_menunggu; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="chat.php" class="nav-item">
                <div class="nav-icon"><i class="fas fa-comments"></i></div>
                <span class="nav-text">Chat</span>
                <span class="nav-badge">3</span>
            </a>
            
            <a href="laporan.php" class="nav-item">
                <div class="nav-icon"><i class="fas fa-chart-bar"></i></div>
                <span class="nav-text">Laporan</span>
            </a>
            
            <a href="account.php" class="nav-item">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                <span class="nav-text">My Account</span>
            </a>
            
            <div class="nav-item" style="margin-top: 20px;">
                <div class="nav-icon"><i class="fas fa-question-circle"></i></div>
                <span class="nav-text">Help</span>
            </div>
            
            <a href="dashboard.php?logout=true" class="nav-item" style="color: var(--danger-color);">
                <div class="nav-icon"><i class="fas fa-sign-out-alt"></i></div>
                <span class="nav-text">Sign Out</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="search-bar">
                <input type="text" class="search-input" placeholder="Search Here...">
            </div>
            
            <div class="top-bar-actions">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <div class="user-info-small">
                    <strong><?php echo isset($nama) ? htmlspecialchars($nama) : 'User'; ?></strong>
                    <span style="color: var(--text-light); font-size: 0.9rem;">Pembeli</span>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Stats -->
        <div class="dashboard-stats">
            <div class="stat-card pesanan">
                <div class="stat-icon pesanan">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-title">Total Pesanan</div>
                <div class="stat-value"><?php echo $total_pesanan; ?></div>
                <div class="stat-change">+2 dari bulan lalu</div>
            </div>
            
            <div class="stat-card status">
                <div class="stat-icon status">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-title">Selesai</div>
                <div class="stat-value"><?php echo $total_selesai; ?></div>
                <div class="stat-change">+1 minggu ini</div>
            </div>
            
            <div class="stat-card chat">
                <div class="stat-icon chat">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-title">Pesan Baru</div>
                <div class="stat-value">3</div>
                <div class="stat-change">Belum dibaca</div>
            </div>
            
            <div class="stat-card laporan">
                <div class="stat-icon laporan">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-title">Total Belanja</div>
                <div class="stat-value">Rp 150.000</div>
                <div class="stat-change">+10% dari bulan lalu</div>
            </div>
        </div>
        
        <!-- Buku Terbaru Section -->
        <div class="section-header">
            <h2 class="section-title">Buku Terbaru</h2>
            <a href="buku.php" class="section-link">
                Lihat Semua <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="buku-grid">
            <?php if (count($buku_terbaru) > 0): ?>
                <?php foreach ($buku_terbaru as $buku): ?>
                <div class="buku-card">
                    <div class="buku-image">
                        <?php if (!empty($buku['gambar'])): ?>
                            <img src="/assets/img/<?php echo htmlspecialchars($buku['gambar']); ?>" alt="<?php echo htmlspecialchars($buku['judul']); ?>">
                        <?php else: ?>
                            <i class="fas fa-book" style="font-size: 3rem; color: #4361ee;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="buku-details">
                        <h3 class="buku-title"><?php echo htmlspecialchars($buku['judul']); ?></h3>
                        <p class="buku-author"><?php echo htmlspecialchars($buku['penulis']); ?></p>
                        <div class="buku-price">Rp <?php echo number_format($buku['harga'], 0, ',', '.'); ?></div>
                        <button class="btn-beli" data-buku-id="<?php echo $buku['id_buku']; ?>">Beli Sekarang</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h4>Tidak ada buku tersedia</h4>
                        <p>Silakan cek kembali nanti.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pesanan Terbaru Section -->
        <div class="section-header">
            <h2 class="section-title">Pesanan Terbaru</h2>
            <a href="pesanan.php" class="section-link">
                Lihat Semua <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="pesanan-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Kode Pesanan</th>
                        <th>Jumlah Item</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pesanan_terbaru) > 0): ?>
                        <?php foreach ($pesanan_terbaru as $pesanan): ?>
                        <tr>
                            <td>A<?php echo str_pad($pesanan['id_transaksi'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $pesanan['jumlah_item']; ?> item</td>
                            <td>Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></td>
                            <td>
                                <?php 
                                $status_class = 'status-menunggu';
                                $status_text = 'Menunggu Pembayaran';
                                
                                if ($pesanan['status'] == 'menunggu_pembayaran') {
                                    $status_class = 'status-menunggu';
                                    $status_text = 'Menunggu Pembayaran';
                                } elseif ($pesanan['status'] == 'dibayar') {
                                    $status_class = 'status-dibayar';
                                    $status_text = 'Menunggu Verifikasi';
                                } elseif ($pesanan['status'] == 'approve') {
                                    $status_class = 'status-disetujui';
                                    $status_text = 'Disetujui';
                                } elseif ($pesanan['status'] == 'dikirim') {
                                    $status_class = 'status-dikirim';
                                    $status_text = 'Dikirim';
                                } elseif ($pesanan['status'] == 'selesai') {
                                    $status_class = 'status-dikirim';
                                    $status_text = 'Selesai';
                                } elseif ($pesanan['status'] == 'ditolak') {
                                    $status_class = 'status-ditolak';
                                    $status_text = 'Ditolak';
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($pesanan['tanggal'])); ?></td>
                            <td>
                                <a href="detail_pesanan.php?id=<?php echo $pesanan['id_transaksi']; ?>" class="btn btn-sm btn-primary">Detail</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="empty-state py-0">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h5>Belum ada pesanan</h5>
                                    <p>Mulai belanja buku sekarang!</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            © 2024 BookVerse. Semua hak dilindungi.
            <a href="privacy.php">Kebijakan Privasi</a> • 
            <a href="terms.php">Syarat Layanan</a>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992) {
                    if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            });
        }
        
        // Handle beli buku button
        const beliButtons = document.querySelectorAll('.btn-beli');
        beliButtons.forEach(button => {
            button.addEventListener('click', function() {
                const bukuId = this.getAttribute('data-buku-id');
                
                // Simulasi tambah ke keranjang
                const modal = new bootstrap.Modal(document.getElementById('beliModal'));
                modal.show();
                
                // Dalam implementasi nyata, ajax request ke server
                console.log('Menambahkan buku ID:', bukuId, 'ke keranjang');
            });
        });
        
        // Notification click
        const notificationBtn = document.querySelector('.notification-btn');
        if (notificationBtn) {
            notificationBtn.addEventListener('click', function() {
                // Dalam implementasi nyata, tampilkan daftar notifikasi
                alert('Fitur notifikasi akan segera hadir!');
            });
        }
        
        // Search functionality
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const query = this.value.trim();
                    if (query) {
                        window.location.href = 'search.php?q=' + encodeURIComponent(query);
                    }
                }
            });
        }
        
        // Auto-update online status
        setInterval(() => {
            // Kirim ping ke server untuk tetap online
            fetch('ping.php')
                .catch(err => console.log('Ping failed'));
        }, 30000); // Setiap 30 detik
        
        // Resize handling
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
            }
        });
    </script>
    
    <!-- Modal for Buy Confirmation -->
    <div class="modal fade" id="beliModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambahkan ke Keranjang</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Buku telah ditambahkan ke keranjang belanja Anda.</p>
                    <p>Apakah Anda ingin melanjutkan belanja atau langsung ke keranjang?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Lanjut Belanja</button>
                    <a href="keranjang.php" class="btn btn-primary">Lihat Keranjang</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html