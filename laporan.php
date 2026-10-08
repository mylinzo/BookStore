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

// ========== FILTER DATA ==========
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : '';
$filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

// ========== QUERY DATA LAPORAN ==========
// Query dasar untuk laporan transaksi
 $sql_laporan = "SELECT t.id_transaksi, t.tanggal, t.total_harga, t.status, u.alamat_penjual as alamat_penjual,
                       GROUP_CONCAT(b.judul SEPARATOR ', ') as items,
                       COUNT(dt.id_detail) as jumlah_item,
                       t.bukti_transfer as bukti_pembayaran, t.resi as nomor_resi
          FROM transaksi t
          LEFT JOIN users u ON t.id_penjual = u.id_user
          LEFT JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
          LEFT JOIN buku b ON dt.id_produk = b.id_buku
                WHERE t.id_pembeli = ?";
                
$params = array($user_id);
$types = "i";

// Tambahkan filter jika ada
if (!empty($filter_status)) {
    $sql_laporan .= " AND t.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_akhir)) {
    $sql_laporan .= " AND DATE(t.tanggal) BETWEEN ? AND ?";
    $params[] = $filter_tanggal_mulai;
    $params[] = $filter_tanggal_akhir;
    $types .= "ss";
} elseif (!empty($filter_tanggal_mulai)) {
    $sql_laporan .= " AND DATE(t.tanggal) >= ?";
    $params[] = $filter_tanggal_mulai;
    $types .= "s";
} elseif (!empty($filter_tanggal_akhir)) {
    $sql_laporan .= " AND DATE(t.tanggal) <= ?";
    $params[] = $filter_tanggal_akhir;
    $types .= "s";
}

$sql_laporan .= " GROUP BY t.id_transaksi ORDER BY t.tanggal DESC";

$laporan_data = [];
$total_seluruh = 0;
$total_transaksi = 0;

if ($stmt = mysqli_prepare($conn, $sql_laporan)) {
    // Bind parameters secara dinamis
    if (count($params) > 0) {
        $bind_params = array($types);
        for ($i = 0; $i < count($params); $i++) {
            $bind_params[] = &$params[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt), $bind_params));
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $laporan_data[] = $row;
        $total_seluruh += $row['total_harga'];
        $total_transaksi++;
    }
    mysqli_stmt_close($stmt);
}

// ========== STATISTIK LAPORAN ==========
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

// Total selesai
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

// Total ditolak
$sql_ditolak = "SELECT COUNT(*) as total FROM transaksi WHERE id_pembeli = ? AND status = 'ditolak'";
$total_ditolak = 0;
if ($stmt = mysqli_prepare($conn, $sql_ditolak)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);
    $total_ditolak = $total;
    mysqli_stmt_close($stmt);
}

// Total pengeluaran
$sql_pengeluaran = "SELECT COALESCE(SUM(total_harga), 0) as total FROM transaksi WHERE id_pembeli = ? AND status IN ('selesai', 'approve')";
$total_pengeluaran = 0;
if ($stmt = mysqli_prepare($conn, $sql_pengeluaran)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);
    $total_pengeluaran = $total;
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan | Toko Buku Online Premium</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Datepicker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

        /* Laporan Stats */
        .laporan-stats {
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

        .stat-card.transaksi {
            border-top-color: var(--primary-color);
        }

        .stat-card.selesai {
            border-top-color: var(--success-color);
        }

        .stat-card.ditolak {
            border-top-color: var(--danger-color);
        }

        .stat-card.pengeluaran {
            border-top-color: var(--accent-color);
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

        .stat-icon.transaksi {
            background: var(--gradient-primary);
        }

        .stat-icon.selesai {
            background: var(--gradient-success);
        }

        .stat-icon.ditolak {
            background: var(--gradient-danger);
        }

        .stat-icon.pengeluaran {
            background: linear-gradient(135deg, #f72585 0%, #7209b7 100%);
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

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }

        .filter-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control, .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn-filter {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            height: 45px;
        }

        .btn-filter:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-reset {
            background: #f1f5f9;
            color: var(--dark-color);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 25px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            height: 45px;
            text-decoration: none;
        }

        .btn-reset:hover {
            background: #e2e8f0;
            color: var(--dark-color);
        }

        /* Laporan Table */
        .laporan-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }

        .laporan-header {
            background: var(--gradient-primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .laporan-title {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .export-actions {
            display: flex;
            gap: 10px;
        }

        .btn-export {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-export:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .laporan-table {
            width: 100%;
            border-collapse: collapse;
        }

        .laporan-table thead {
            background: #f8f9ff;
        }

        .laporan-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid var(--border-color);
        }

        .laporan-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .kode-pesanan {
            font-weight: 600;
            color: var(--primary-color);
        }

        .items-list {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bukti-pembayaran {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .bukti-pembayaran:hover {
            transform: scale(1.1);
        }

        .bukti-pembayaran img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-bukti {
            color: var(--text-light);
            font-size: 0.9rem;
            font-style: italic;
        }

        .metode-bayar {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .metode-transfer {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }

        .metode-cod {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .metode-ewallet {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .total-harga {
            font-weight: 700;
            color: var(--dark-color);
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

        .status-selesai {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        /* Summary */
        .summary-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }

        .summary-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .summary-card {
            background: #f8f9ff;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid var(--primary-color);
        }

        .summary-label {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
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

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        .pagination {
            display: flex;
            gap: 5px;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: white;
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }

        .page-link:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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

        .modal-body {
            padding: 25px;
            text-align: center;
        }

        .bukti-modal {
            max-width: 100%;
            max-height: 500px;
            border-radius: 10px;
            margin-bottom: 20px;
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
            
            .laporan-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .laporan-table {
                display: block;
                overflow-x: auto;
            }
            
            .export-actions {
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 768px) {
            .laporan-stats {
                grid-template-columns: 1fr;
            }
            
            .laporan-table thead {
                display: none;
            }
            
            .laporan-table tr {
                display: block;
                margin-bottom: 20px;
                border: 1px solid var(--border-color);
                border-radius: 10px;
                padding: 15px;
            }
            
            .laporan-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid var(--border-color);
            }
            
            .laporan-table td:last-child {
                border-bottom: none;
            }
            
            .laporan-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--dark-color);
                margin-right: 10px;
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
            
            .laporan-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
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
            </a>
            
            <a href="chat.php" class="nav-item">
                <div class="nav-icon"><i class="fas fa-comments"></i></div>
                <span class="nav-text">Chat</span>
                <span class="nav-badge">3</span>
            </a>
            
            <a href="laporan.php" class="nav-item active">
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
            
            <a href="laporan.php?logout=true" class="nav-item" style="color: var(--danger-color);">
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
            <i class="fas fa-chart-bar"></i>
            <div>
                <h1 style="font-size: 1.8rem; margin: 0;">Laporan Transaksi</h1>
                <p style="font-size: 0.9rem; color: var(--text-light); margin: 5px 0 0 0;">Riwayat dan statistik pembelian Anda</p>
            </div>
        </div>
        
        <!-- Laporan Stats -->
        <div class="laporan-stats">
            <div class="stat-card transaksi">
                <div class="stat-icon transaksi">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_pesanan; ?></h3>
                    <p>Total Transaksi</p>
                </div>
            </div>
            
            <div class="stat-card selesai">
                <div class="stat-icon selesai">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_selesai; ?></h3>
                    <p>Transaksi Selesai</p>
                </div>
            </div>
            
            <div class="stat-card ditolak">
                <div class="stat-icon ditolak">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_ditolak; ?></h3>
                    <p>Transaksi Ditolak</p>
                </div>
            </div>
            
            <div class="stat-card pengeluaran">
                <div class="stat-icon pengeluaran">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3>Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?></h3>
                    <p>Total Pengeluaran</p>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i> Filter Laporan
            </h3>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Status Transaksi</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="menunggu_pembayaran" <?php echo $filter_status == 'menunggu_pembayaran' ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                        <option value="dibayar" <?php echo $filter_status == 'dibayar' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                        <option value="approve" <?php echo $filter_status == 'approve' ? 'selected' : ''; ?>>Disetujui</option>
                        <option value="dikirim" <?php echo $filter_status == 'dikirim' ? 'selected' : ''; ?>>Dikirim</option>
                        <option value="selesai" <?php echo $filter_status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="ditolak" <?php echo $filter_status == 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" class="form-control datepicker" 
                           value="<?php echo $filter_tanggal_mulai; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" class="form-control datepicker" 
                           value="<?php echo $filter_tanggal_akhir; ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Terapkan Filter
                    </button>
                </div>
                
                <div class="form-group">
                    <a href="laporan.php" class="btn-reset">
                        <i class="fas fa-redo"></i> Reset Filter
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Summary Section -->
        <div class="summary-section">
            <h3 class="summary-title">Ringkasan Laporan</h3>
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-label">Jumlah Transaksi</div>
                    <div class="summary-value"><?php echo $total_transaksi; ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Total Pengeluaran</div>
                    <div class="summary-value">Rp <?php echo number_format($total_seluruh, 0, ',', '.'); ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Rata-rata per Transaksi</div>
                    <div class="summary-value">
                        <?php 
                        $rata_rata = $total_transaksi > 0 ? $total_seluruh / $total_transaksi : 0;
                        echo 'Rp ' . number_format($rata_rata, 0, ',', '.');
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Laporan Table -->
        <?php if (count($laporan_data) > 0): ?>
        <div class="laporan-container">
            <div class="laporan-header">
                <h3 class="laporan-title">Detail Transaksi (<?php echo count($laporan_data); ?>)</h3>
                <div class="export-actions">
                    <button class="btn-export" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button class="btn-export" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button class="btn-export" onclick="printLaporan()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="laporan-table">
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Tanggal</th>
                            <th>Merek/Items</th>
                            <th>QTY</th>
                            <th>Bukti</th>
                            <th>Alamat Penjual</th>
                            <th>Total Harga</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laporan_data as $laporan): 
                            $items = explode(', ', $laporan['items']);
                            $first_item = !empty($items[0]) ? $items[0] : 'Tidak ada item';
                        ?>
                        <tr>
                            <td data-label="Kode Pesanan">
                                <span class="kode-pesanan">A<?php echo str_pad($laporan['id_transaksi'], 3, '0', STR_PAD_LEFT); ?></span>
                            </td>
                            <td data-label="Tanggal">
                                <?php echo date('d/m/Y', strtotime($laporan['tanggal'])); ?>
                            </td>
                            <td data-label="Merek/Items">
                                <div class="items-list" title="<?php echo htmlspecialchars($laporan['items']); ?>">
                                    <?php echo htmlspecialchars($first_item); ?>
                                    <?php if (count($items) > 1): ?>
                                        <span style="color: var(--text-light);"> +<?php echo count($items) - 1; ?> lainnya</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="QTY">
                                <?php echo $laporan['jumlah_item']; ?>
                            </td>
                            <td data-label="Bukti">
                                <?php if (!empty($laporan['bukti_pembayaran'])): ?>
                                    <div class="bukti-pembayaran" onclick="showBukti('<?php echo $laporan['bukti_pembayaran']; ?>')">
                                        <img src="/assets/bukti/<?php echo htmlspecialchars($laporan['bukti_pembayaran']); ?>" alt="Bukti Pembayaran">
                                    </div>
                                <?php else: ?>
                                    <span class="no-bukti">Tidak ada</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Alamat Penjual">
                                <?php echo !empty($laporan['alamat_penjual']) ? '<a href="' . htmlspecialchars($laporan['alamat_penjual']) . '" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: linear-gradient(135deg, var(--vintage-paper), var(--vintage-cream)); color: var(--vintage-brown); text-decoration: none; border-radius: 4px; border: 1px solid var(--vintage-border); font-weight: 500; font-size: 13px; transition: all 0.3s;"><i class="fas fa-map-marker-alt"></i> Lihat Lokasi</a>' : '-'; ?>
                            </td>
                            <td data-label="Total Harga">
                                <span style="color: var(--primary-color); font-weight: 600;">Rp <?php echo number_format($laporan['total_harga'], 0, ',', '.'); ?></span>
                            </td>
                            <td data-label="Status">
                                <?php 
                                $status_class = 'status-menunggu';
                                $status_text = 'Menunggu Pembayaran';
                                
                                if ($laporan['status'] == 'menunggu_pembayaran') {
                                    $status_class = 'status-menunggu';
                                    $status_text = 'Menunggu Pembayaran';
                                } elseif ($laporan['status'] == 'dibayar') {
                                    $status_class = 'status-dibayar';
                                    $status_text = 'Menunggu Verifikasi';
                                } elseif ($laporan['status'] == 'approve') {
                                    $status_class = 'status-disetujui';
                                    $status_text = 'Disetujui';
                                } elseif ($laporan['status'] == 'dikirim') {
                                    $status_class = 'status-dikirim';
                                    $status_text = 'Dikirim';
                                } elseif ($laporan['status'] == 'selesai') {
                                    $status_class = 'status-selesai';
                                    $status_text = 'Selesai';
                                } elseif ($laporan['status'] == 'ditolak') {
                                    $status_class = 'status-ditolak';
                                    $status_text = 'Ditolak';
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="pagination-container">
            <ul class="pagination">
                <li class="page-item">
                    <a href="#" class="page-link active">1</a>
                </li>
                <li class="page-item">
                    <a href="#" class="page-link">2</a>
                </li>
                <li class="page-item">
                    <a href="#" class="page-link">3</a>
                </li>
                <li class="page-item">
                    <a href="#" class="page-link">Next</a>
                </li>
            </ul>
        </div>
        
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h4>Tidak ada data laporan</h4>
                <p>Belum ada transaksi yang tercatat. Mulailah berbelanja untuk melihat laporan transaksi Anda.</p>
                <a href="buku.php" class="btn-shopping">
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
    <!-- Flatpickr Datepicker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
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
        
        // Datepicker initialization
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            locale: 'id'
        });
        
        // Show bukti pembayaran modal
        function showBukti(imageSrc) {
            const modal = new bootstrap.Modal(document.getElementById('buktiModal'));
            const modalImage = document.getElementById('modalBuktiImage');
            modalImage.src = '/assets/bukti/' + imageSrc;
            modalImage.alt = 'Bukti Pembayaran';
            modal.show();
        }
        
        // Export functions
        function exportToPDF() {
            alert('Fitur ekspor PDF akan segera hadir!');
            // Implementasi ekspor PDF di sini
        }
        
        function exportToExcel() {
            alert('Fitur ekspor Excel akan segera hadir!');
            // Implementasi ekspor Excel di sini
        }
        
        function printLaporan() {
            window.print();
        }
        
        // Resize handling
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
            }
        });
    </script>
    
    <!-- Modal for Bukti Pembayaran -->
    <div class="modal fade" id="buktiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bukti Pembayaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <img id="modalBuktiImage" src="" alt="Bukti Pembayaran" class="bukti-modal">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="printImage()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>