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
    exit;
}

// ========== CEK LOGIN STATUS ==========
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

// ========== QUERY DATA PESANAN ==========
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

// Ambil semua pesanan user
$sql_semua_pesanan = "SELECT t.id_transaksi, t.total_harga, t.status, t.tanggal, 
                               COUNT(dt.id_detail) as jumlah_item
                        FROM transaksi t
                        LEFT JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
                        WHERE t.id_pembeli = ?
                        GROUP BY t.id_transaksi
                        ORDER BY t.tanggal DESC";
$semua_pesanan = [];
if ($stmt = mysqli_prepare($conn, $sql_semua_pesanan)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $semua_pesanan[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// ========== HANDLE ACTION PESANAN ==========
$pesan_success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $transaksi_id = isset($_POST['transaksi_id']) ? $_POST['transaksi_id'] : 0;
        
        if ($action == 'hapus' && $transaksi_id > 0) {
            // Cek apakah transaksi milik user ini
            $check_sql = "SELECT id_transaksi FROM transaksi WHERE id_transaksi = ? AND id_pembeli = ?";
            if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
                mysqli_stmt_bind_param($check_stmt, "ii", $transaksi_id, $user_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    // Hapus detail transaksi terlebih dahulu
                    $delete_detail_sql = "DELETE FROM detail_transaksi WHERE id_transaksi = ?";
                    if ($delete_detail_stmt = mysqli_prepare($conn, $delete_detail_sql)) {
                        mysqli_stmt_bind_param($delete_detail_stmt, "i", $transaksi_id);
                        mysqli_stmt_execute($delete_detail_stmt);
                        mysqli_stmt_close($delete_detail_stmt);
                    }
                    
                    // Hapus transaksi
                    $delete_sql = "DELETE FROM transaksi WHERE id_transaksi = ?";
                    if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                        mysqli_stmt_bind_param($delete_stmt, "i", $transaksi_id);
                        if (mysqli_stmt_execute($delete_stmt)) {
                            $pesan_success = "Pesanan berhasil dihapus.";
                        }
                        mysqli_stmt_close($delete_stmt);
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
        }
        
        // Redirect untuk refresh data
        header("location: pesanan.php?success=" . urlencode($pesan_success));
        exit;
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan | Toko Buku Online Premium</title>
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

        /* Page Title */
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            color: var(--primary-color);
            background: rgba(67, 97, 238, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        /* Pesanan Stats */
        .pesanan-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .stat-card.all {
            border-top-color: var(--primary-color);
        }

        .stat-card.waiting {
            border-top-color: var(--warning-color);
        }

        .stat-card.approved {
            border-top-color: var(--success-color);
        }

        .stat-card.rejected {
            border-top-color: var(--danger-color);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.all {
            background: var(--gradient-primary);
        }

        .stat-icon.waiting {
            background: var(--gradient-warning);
        }

        .stat-icon.approved {
            background: var(--gradient-success);
        }

        .stat-icon.rejected {
            background: var(--gradient-danger);
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 0.9rem;
            color: var(--text-light);
            margin: 0;
        }

        /* Pesanan Cards */
        .pesanan-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .pesanan-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .pesanan-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .pesanan-header {
            background: var(--gradient-primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pesanan-id {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .pesanan-date {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .pesanan-body {
            padding: 20px;
        }

        .pesanan-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .pesanan-label {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .pesanan-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .pesanan-total {
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 1rem;
            color: var(--text-light);
        }

        .total-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .pesanan-footer {
            padding: 15px 20px;
            background: #f9f9f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border-color);
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

        .pesanan-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-detail {
            background: var(--primary-color);
            color: white;
        }

        .btn-detail:hover {
            background: var(--secondary-color);
        }

        .btn-hapus {
            background: #fee2e2;
            color: var(--danger-color);
        }

        .btn-hapus:hover {
            background: #fecaca;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #e9ecef;
        }

        .empty-state h4 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .empty-state p {
            margin-bottom: 25px;
            color: var(--text-light);
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-belanja {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-belanja:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            color: white;
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

        /* Modal */
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

        /* Success Alert */
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.2);
            color: #065f46;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
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
            
            .pesanan-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .pesanan-cards {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .pesanan-stats {
                grid-template-columns: 1fr;
            }
            
            .pesanan-cards {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .page-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .pesanan-footer {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .pesanan-actions {
                width: 100%;
                justify-content: space-between;
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
            <a href="dashboard.php" class="nav-item">
                <div class="nav-icon"><i class="fas fa-home"></i></div>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <a href="pesanan.php" class="nav-item active">
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
            
            <a href="pesanan.php?logout=true" class="nav-item" style="color: var(--danger-color);">
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
        
        <!-- Page Title -->
        <div class="page-title">
            <i class="fas fa-shopping-cart"></i>
            <div>
                <h1 style="font-size: 1.8rem; margin: 0;">Pesanan</h1>
                <p style="font-size: 0.9rem; color: var(--text-light); margin: 5px 0 0 0;">Kelola dan lacak semua pesanan Anda</p>
            </div>
        </div>
        
        <!-- Success Message -->
        <?php if (isset($_GET['success']) && !empty($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Pesanan Stats -->
        <div class="pesanan-stats">
            <div class="stat-card all">
                <div class="stat-icon all">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_pesanan; ?></h3>
                    <p>Total Pesanan</p>
                </div>
            </div>
            
            <div class="stat-card waiting">
                <div class="stat-icon waiting">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_menunggu; ?></h3>
                    <p>Menunggu Konfirmasi</p>
                </div>
            </div>
            
            <div class="stat-card approved">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php 
                        // Hitung pesanan disetujui
                        $total_approved = 0;
                        foreach ($semua_pesanan as $pesanan) {
                            if ($pesanan['status'] == 'approve' || $pesanan['status'] == 'selesai') {
                                $total_approved++;
                            }
                        }
                        echo $total_approved;
                    ?></h3>
                    <p>Pesanan Disetujui</p>
                </div>
            </div>
            
            <div class="stat-card rejected">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php 
                        // Hitung pesanan ditolak
                        $total_rejected = 0;
                        foreach ($semua_pesanan as $pesanan) {
                            if ($pesanan['status'] == 'ditolak') {
                                $total_rejected++;
                            }
                        }
                        echo $total_rejected;
                    ?></h3>
                    <p>Pesanan Ditolak</p>
                </div>
            </div>
        </div>
        
        <!-- Pesanan List -->
        <?php if (count($semua_pesanan) > 0): ?>
            <div class="pesanan-cards">
                <?php foreach ($semua_pesanan as $pesanan): ?>
                <div class="pesanan-card">
                    <div class="pesanan-header">
                        <div class="pesanan-id">Pesanan #A<?php echo str_pad($pesanan['id_transaksi'], 3, '0', STR_PAD_LEFT); ?></div>
                        <div class="pesanan-date"><?php echo date('d/m/Y', strtotime($pesanan['tanggal'])); ?></div>
                    </div>
                    
                    <div class="pesanan-body">
                        <div class="pesanan-detail">
                            <span class="pesanan-label">Jumlah Item:</span>
                            <span class="pesanan-value"><?php echo $pesanan['jumlah_item']; ?> item</span>
                        </div>
                        <div class="pesanan-detail">
                            <span class="pesanan-label">Status Pembayaran:</span>
                            <span class="pesanan-value">Lunas</span>
                        </div>
                        <div class="pesanan-detail">
                            <span class="pesanan-label">Metode Pengiriman:</span>
                            <span class="pesanan-value">JNE Regular</span>
                        </div>
                        
                        <div class="pesanan-total">
                            <span class="total-label">Total Pembayaran:</span>
                            <span class="total-value">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <div class="pesanan-footer">
                        <div>
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
                        </div>
                        
                        <div class="pesanan-actions">
                            <a href="detail_pesanan.php?id=<?php echo $pesanan['id_transaksi']; ?>" class="btn-action btn-detail">
                                <i class="fas fa-eye"></i> Detail
                            </a>
                            <?php if ($pesanan['status'] == 'menunggu_pembayaran'): ?>
                            <button class="btn-action btn-hapus" onclick="hapusPesanan(<?php echo $pesanan['id_transaksi']; ?>)">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h4>Belum ada pesanan</h4>
                <p>Anda belum melakukan pemesanan apapun. Mulailah berbelanja buku favorit Anda sekarang!</p>
                <a href="buku.php" class="btn-belanja">
                    <i class="fas fa-shopping-bag"></i> Mulai Belanja
                </a>
            </div>
        <?php endif; ?>
        
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
        
        // Notification click
        const notificationBtn = document.querySelector('.notification-btn');
        if (notificationBtn) {
            notificationBtn.addEventListener('click', function() {
                alert('Fitur notifikasi akan segera hadir!');
            });
        }
        
        // Hapus pesanan
        function hapusPesanan(id) {
            if (confirm('Apakah Anda yakin ingin menghapus pesanan ini?')) {
                // Buat form untuk submit POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'pesanan.php';
                
                // Tambahkan input action
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'hapus';
                form.appendChild(actionInput);
                
                // Tambahkan input transaksi_id
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'transaksi_id';
                idInput.value = id;
                form.appendChild(idInput);
                
                // Submit form
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Resize handling
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
            }
        });
    </script>
    
    <!-- Modal for Pesanan Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pesanan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Detail pesanan akan ditampilkan di sini.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary">Cetak Invoice</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>