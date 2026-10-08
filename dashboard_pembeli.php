<?php
session_start();
include 'koneksi.php';

// ========== LOGOUT HANDLING DI AWAL ==========
if (isset($_GET['logout'])) {
    if (isset($_SESSION["id_user"])) {
        $user_id = $_SESSION["id_user"];
        $update_sql = "UPDATE users SET status = 'offline', is_online = 0, last_seen = NOW() WHERE id_user = ?";
        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
            mysqli_stmt_bind_param($update_stmt, "i", $user_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
    }
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
    session_destroy();
    header('Location: login.php');
    exit;
}

// ========== AUTHENTICATION CHECK ==========
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'pembeli') {
    header('Location: login.php');
    exit;
}

// Get current user info
$user_id = $_SESSION['id_user'];
$current_user = [];
$user_query = "SELECT id_user, nama, email, nik, alamat_pembeli, avatar, status FROM users WHERE id_user = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
if ($user_result && mysqli_num_rows($user_result) > 0) {
    $current_user = mysqli_fetch_assoc($user_result);
}
mysqli_stmt_close($user_stmt);

// Ensure unread chat count is defined
$unread_chat_count = 0;
if (isset($_SESSION['id_user']) && isset($_SESSION['role']) && $_SESSION['role'] === 'pembeli') {
    if (file_exists(__DIR__ . '/chat_handler_pembeli.php')) {
        include_once 'chat_handler_pembeli.php';
        $chatHandler = new ChatHandlerPembeli($conn, $_SESSION['id_user']);
        $unread_chat_count = (int) $chatHandler->getTotalUnread();
    }
}

// Handle refund request
if (isset($_POST['request_refund'])) {
    $id_pesanan = mysqli_real_escape_string($conn, $_POST['id_pesanan']);
    $alasan_refund = mysqli_real_escape_string($conn, $_POST['alasan_refund']);
    $check_sql = "SELECT status FROM pesanan WHERE id_pesanan = ? AND id_pembeli = ? AND (status = 'dikirim' OR status = 'selesai')";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $id_pesanan, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_result) > 0) {
        $catatan = "[REFUND REQUEST] " . $alasan_refund;
        $update_sql = "UPDATE pesanan SET status = 'ditolak', catatan_pembeli = ?, updated_at = NOW() WHERE id_pesanan = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $catatan, $id_pesanan);
        if (mysqli_stmt_execute($update_stmt)) {
            echo "<script>alert('Permintaan refund berhasil diajukan. Tim kami akan memprosesnya.'); window.location.href = 'dashboard_pembeli.php';</script>";
        } else {
            echo "<script>alert('Gagal mengajukan refund. Silakan coba lagi.');</script>";
        }
        mysqli_stmt_close($update_stmt);
    } else {
        echo "<script>alert('Pesanan tidak dapat direfund.');</script>";
    }
    mysqli_stmt_close($check_stmt);
}

// Handle konfirmasi diterima
if (isset($_POST['confirm_received'])) {
    $id_pesanan = intval($_POST['id_pesanan']);
    $check_sql = "SELECT status FROM pesanan WHERE id_pesanan = ? AND id_pembeli = ? AND status = 'dikirim'";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $id_pesanan, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_result) > 0) {
        $update_sql = "UPDATE pesanan SET status = 'selesai', tanggal_selesai = NOW() WHERE id_pesanan = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $id_pesanan);
        if (mysqli_stmt_execute($update_stmt)) {
            echo "<script>alert('Pesanan telah dikonfirmasi diterima. Terima kasih!'); window.location.href = 'dashboard_pembeli.php';</script>";
        } else {
            echo "<script>alert('Gagal mengkonfirmasi pesanan.');</script>";
        }
        mysqli_stmt_close($update_stmt);
    }
    mysqli_stmt_close($check_stmt);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pembeli - Pustaka Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --secondary: #64748b;
            --success: #22c55e;
            --success-bg: #f0fdf4;
            --error: #ef4444;
            --error-bg: #fef2f2;
            --warning: #f59e0b;
            --warning-bg: #fffbeb;
            --info: #3b82f6;
            --info-bg: #eff6ff;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius: 12px;
            --radius-sm: 8px;
            --sidebar-width: 250px;
        }

        @media print { .no-print { display: none !important; } }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        body {
            background-color: var(--background);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            line-height: 1.5;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .logo-container {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            text-align: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            color: white;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .logo-text { text-align: left; }
        .logo-title { font-size: 1.1rem; font-weight: 600; }
        .logo-subtitle { font-size: 0.75rem; opacity: 0.9; }

        .user-profile {
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-bottom: 1px solid var(--border);
            background: var(--background);
        }

        .avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            border: 3px solid white;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-name { font-weight: 600; font-size: 1rem; margin-bottom: 4px; color: var(--text-primary); text-align: center; }
        .user-role {
            font-size: 0.75rem;
            color: var(--primary);
            background-color: var(--primary-light);
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .nav-menu { flex: 1; padding: 16px 0; overflow-y: auto; }

        .nav-item {
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            margin: 4px 16px;
            border-radius: 0 8px 8px 0;
            font-size: 0.9375rem;
            font-weight: 500;
        }

        .nav-item:hover {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .nav-item.active {
            background-color: var(--primary-light);
            color: var(--primary);
            border-left-color: var(--primary);
        }

        .nav-item i { width: 20px; text-align: center; font-size: 1rem; }
        .nav-item.active i { color: var(--primary); }
        .nav-text { flex: 1; }

        .badge {
            background: var(--error);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: auto;
        }

        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: var(--background);
        }

        .sidebar-footer .nav-item { margin: 4px 0; border-radius: 8px; }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 24px;
            overflow-x: hidden;
            background-color: var(--background);
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.025em;
        }

        .search-container {
            position: relative;
            width: 280px;
        }

        .search-input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            background-color: var(--surface);
            color: var(--text-primary);
        }

        .search-input::placeholder { color: var(--text-secondary); }
        .search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); }

        .header-actions { display: flex; align-items: center; gap: 12px; }
        .header-actions .btn-logout { display: none !important; }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            border: none;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: var(--shadow-md); }

        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #16a34a; transform: translateY(-1px); box-shadow: var(--shadow-md); }

        .btn-secondary { background-color: var(--surface); color: var(--text-primary); border: 1px solid var(--border); }
        .btn-secondary:hover { background-color: var(--background); transform: translateY(-1px); }

        .btn-danger { background: var(--error); color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-1px); box-shadow: var(--shadow-md); }

        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #d97706; transform: translateY(-1px); box-shadow: var(--shadow-md); }

        .btn-info { background: var(--info); color: white; }
        .btn-info:hover { background: #2563eb; transform: translateY(-1px); box-shadow: var(--shadow-md); }

        .btn-logout {
            background: var(--error);
            color: white;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            border: none;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .btn-logout:hover { background: #dc2626; transform: translateY(-1px); }
        .btn-sm { padding: 6px 12px; font-size: 0.8125rem; }
        .btn-block { width: 100%; justify-content: center; }

        /* Notification Bell */
        .notification-bell {
            position: relative;
            font-size: 1.25rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--surface);
            border: 1px solid var(--border);
        }

        .notification-bell:hover {
            background: var(--primary-light);
            color: var(--primary);
            transform: scale(1.05);
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--error);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid var(--surface);
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: fixed;
            top: 60px;
            right: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show { display: block; }

        .notification-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.25rem;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .notification-close:hover { opacity: 1; }

        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .notification-item:hover { background: var(--background); }
        .notification-item:last-child { border-bottom: none; }

        .notification-sender {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9375rem;
        }

        .notification-message {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notification-time { font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px; opacity: 0.8; }

        .notification-empty {
            padding: 32px 16px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }

        .notification-view-all {
            padding: 12px 16px;
            text-align: center;
            border-top: 1px solid var(--border);
            background: var(--background);
            cursor: pointer;
            color: var(--primary);
            font-weight: 500;
            font-size: 0.875rem;
            transition: background 0.2s;
        }

        .notification-view-all:hover { background: var(--primary-light); }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            border-radius: var(--radius);
            padding: 28px;
            color: white;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-content h1 { font-size: 1.75rem; margin-bottom: 8px; font-weight: 600; }
        .hero-content p { font-size: 1rem; margin-bottom: 16px; opacity: 0.95; }
        .hero-buttons { display: flex; gap: 12px; }

        .btn-outline {
            background-color: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        .btn-outline:hover {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: white;
            transform: translateY(-1px);
        }

        /* Product Cards */
        .products-container { margin-bottom: 24px; }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); }

        .see-all-btn {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s ease;
        }

        .see-all-btn:hover { color: var(--primary-hover); text-decoration: underline; }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .product-card {
            background: var(--surface);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .product-image {
            height: 150px;
            background-color: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 2.5rem;
            overflow: hidden;
        }

        .product-image img { width: 100%; height: 100%; object-fit: cover; }

        .product-info { padding: 14px; }

        .product-category {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .product-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.9375rem;
        }

        .product-stock { color: var(--success); font-weight: 500; }
        .product-price { color: var(--text-primary); font-weight: 600; }
        .product-actions { display: flex; gap: 8px; }

        /* Tables */
        .table-container {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 0;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .table-header {
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            background: var(--background);
        }

        .table-title { font-size: 1.125rem; font-weight: 600; color: var(--text-primary); }
        .table-actions { display: flex; gap: 8px; }

        table { width: 100%; border-collapse: collapse; }

        thead { background-color: var(--background); }

        th {
            padding: 14px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.8125rem;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        td {
            padding: 14px 20px;
            font-size: 0.9375rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
        }

        tbody tr:hover { background-color: var(--background); }

        /* Status Badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 500;
            display: inline-block;
            min-width: 85px;
            text-align: center;
            border: 1px solid;
        }

        .status-menunggu { background-color: var(--warning-bg); color: #b45309; border-color: #fcd34d; }
        .status-dibayar { background-color: var(--info-bg); color: #1e40af; border-color: #93c5fd; }
        .status-diproses { background-color: var(--info-bg); color: #0e7490; border-color: #67e8f9; }
        .status-dikirim { background-color: var(--success-bg); color: #15803d; border-color: #86efac; }
        .status-selesai { background-color: var(--success-bg); color: #166534; border-color: #4ade80; }
        .status-ditolak { background-color: var(--error-bg); color: #991b1b; border-color: #fca5a5; }
        .status-refund-diajukan { background-color: var(--warning-bg); color: #b45309; border-color: #fcd34d; }
        .status-refund-diproses { background-color: var(--warning-bg); color: #b45309; border-color: #fcd34d; }
        .status-refund-selesai { background-color: var(--success-bg); color: #15803d; border-color: #4ade80; }
        .status-refund-ditolak { background-color: var(--error-bg); color: #991b1b; border-color: #fca5a5; }

        /* Cart Summary */
        .cart-summary {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .total-label { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
        .total-amount { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: var(--surface);
            border-radius: var(--radius);
            width: 95%;
            max-width: 700px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
        }

        #invoice-modal .modal-content {
            width: 95%;
            max-width: 600px;
            min-width: 320px;
        }

        #invoice-modal .modal-body {
            max-height: 78vh;
            overflow-y: auto;
            padding: 12px 16px;
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--background);
        }

        .modal-title { font-size: 1.125rem; font-weight: 600; color: var(--text-primary); }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.2s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal:hover { color: var(--error); background-color: var(--error-bg); }

        .modal-body { padding: 20px; }

        .form-group { margin-bottom: 16px; }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            background-color: var(--surface);
            color: var(--text-primary);
        }

        .form-control::placeholder { color: var(--text-secondary); }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .form-control[readonly] { background-color: var(--background); cursor: not-allowed; }
        textarea.form-control { resize: vertical; min-height: 100px; }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: var(--background);
        }

        /* Avatar Upload */
        .avatar-upload-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 24px;
        }

        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            border: 4px solid white;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .avatar-preview:hover { transform: scale(1.03); }
        .avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-preview i { font-size: 48px; color: white; }

        .avatar-upload-input { display: none; }

        .avatar-upload-label {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .avatar-upload-label:hover { background: var(--primary-hover); transform: translateY(-1px); }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 16px;
            overflow-x: auto;
            background: var(--surface);
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
            padding: 0 8px;
        }

        .tab {
            padding: 12px 16px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
            font-size: 0.9375rem;
        }

        .tab:hover { color: var(--primary); background-color: var(--primary-light); border-radius: var(--radius-sm); }
        .tab.active { color: var(--primary); border-bottom-color: var(--primary); background-color: var(--primary-light); border-radius: var(--radius-sm); }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Invoice Styles */
        .invoice-receipt {
            background: white;
            padding: 0;
            max-width: 520px;
            width: 100%;
            margin: 0 auto;
            font-family: 'Share Tech Mono', monospace;
            border: 2px solid var(--primary);
            border-radius: var(--radius);
        }

        .invoice-receipt-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            padding: 14px;
            text-align: center;
            border-bottom: 3px dashed var(--primary-light);
        }

        .invoice-receipt-header h2 {
            font-family: 'Inter', sans-serif;
            font-size: 1.3rem;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .invoice-receipt-header h2 {
            font-family: 'Inter', sans-serif;
            font-size: 1.5rem;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .invoice-receipt-header p {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .invoice-receipt-body {
            padding: 20px;
        }

        .invoice-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border);
            font-size: 0.9rem;
        }

        .invoice-info-row:last-child { border-bottom: none; }

        .invoice-info-label { color: var(--primary); font-weight: 600; }
        .invoice-info-value { color: var(--text-primary); text-align: right; }

        .invoice-items {
            margin: 20px 0;
            border-top: 2px solid var(--primary);
            border-bottom: 2px solid var(--primary);
            padding: 15px 0;
        }

        .invoice-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.9rem;
        }

        .invoice-item-name { flex: 1; color: var(--text-primary); }
        .invoice-item-qty { width: 50px; text-align: center; color: var(--primary); }
        .invoice-item-price { width: 100px; text-align: right; color: var(--text-primary); }

        .invoice-total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px dashed var(--primary);
        }

        .invoice-total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 1rem;
        }

        .invoice-total-row.final {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            border-top: 2px solid var(--primary);
            padding-top: 15px;
            margin-top: 10px;
        }

        .invoice-footer {
            text-align: center;
            padding: 20px;
            background: var(--background);
            border-top: 3px dashed var(--primary-light);
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .invoice-footer p { margin: 5px 0; }

        .invoice-payment-badge {
            display: inline-block;
            padding: 5px 15px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .invoice-status-badge {
            display: inline-block;
            padding: 5px 15px;
            background: var(--success-bg);
            color: var(--success);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid var(--success);
        }

        @media print {
            body * { visibility: hidden; }
            #invoice-content, #invoice-content * { visibility: visible; }
            #invoice-modal .modal-content { border: none; box-shadow: none; }
            #invoice-modal { width: 100%; height: 100%; }
        }

        .payment-upload {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius-sm);
            padding: 24px;
            text-align: center;
            margin-top: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: var(--background);
        }

        .upload-area:hover { border-color: var(--primary); background-color: var(--primary-light); }
        .upload-icon { font-size: 2.5rem; color: var(--text-secondary); margin-bottom: 12px; opacity: 0.7; }

        /* Help Guide */
        .help-guide-container { max-width: 100%; margin: 16px 0; }

        .help-guide-search {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .help-guide-search input {
            flex: 1;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 0.9375rem;
            outline: none;
            transition: all 0.2s ease;
            background-color: var(--background);
            color: var(--text-primary);
        }

        .help-guide-search input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

        .help-sections {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .help-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }

        .help-section:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .help-section-header {
            background: var(--background);
            padding: 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .help-section-header i {
            font-size: 1.25rem;
            color: var(--primary);
        }

        .help-section-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .help-section-content {
            padding: 16px;
            max-height: 400px;
            overflow-y: auto;
        }

        .help-item {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .help-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .help-item-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s ease;
        }

        .help-item-title:hover {
            color: var(--primary);
        }

        .help-item-title i {
            font-size: 0.75rem;
            transition: transform 0.2s ease;
        }

        .help-item-content {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
            display: none;
            animation: slideDown 0.3s ease forwards;
        }

        .help-item-content.expanded {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 300px;
            }
        }

        .help-no-results {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .help-no-results i {
            font-size: 2rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .help-no-results p {
            margin: 0;
            font-size: 0.9375rem;
        }

        /* Status Cards */
        .status-card {
            background: var(--surface);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-bottom: 12px;
            border-left: 4px solid;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .status-card.dikirim { border-left-color: var(--success); }
        .status-card.ditolak { border-left-color: var(--error); }
        .status-card.refund { border-left-color: var(--warning); }

        .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .status-order-id { font-weight: 600; color: var(--text-primary); }
        .status-date { font-size: 0.8125rem; color: var(--text-secondary); }

        .status-body {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
        }

        .status-product-image {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .status-product-image img { width: 100%; height: 100%; object-fit: cover; }
        .status-product-info { flex: 1; }
        .status-product-name { font-weight: 600; margin-bottom: 4px; font-size: 0.9375rem; }
        .status-product-seller { font-size: 0.8125rem; color: var(--text-secondary); }

        .status-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 12px;
            border-top: 1px solid var(--border);
            padding-top: 12px;
            flex-wrap: wrap;
        }

        .tracking-info {
            background: var(--background);
            padding: 10px;
            border-radius: var(--radius-sm);
            margin-top: 10px;
            font-size: 0.8125rem;
        }

        .tracking-info .label { font-weight: 600; color: var(--text-primary); }

        .refund-info {
            background: var(--warning-bg);
            border: 1px solid #fcd34d;
            padding: 10px;
            border-radius: var(--radius-sm);
            margin-top: 10px;
            font-size: 0.8125rem;
        }

        .refund-info .reason {
            margin-top: 6px;
            padding: 8px;
            background: white;
            border-radius: var(--radius-sm);
            font-style: italic;
            border: 1px solid var(--border);
        }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            border-left: 4px solid;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.9375rem;
        }

        .alert i { font-size: 1rem; margin-top: 2px; flex-shrink: 0; }
        .alert-success { background-color: var(--success-bg); border-left-color: var(--success); color: #166534; }
        .alert-danger { background-color: var(--error-bg); border-left-color: var(--error); color: #991b1b; }
        .alert-warning { background-color: var(--warning-bg); border-left-color: var(--warning); color: #92400e; }
        .alert-info { background-color: var(--info-bg); border-left-color: var(--info); color: #1e40af; }

        /* Cart Items */
        .cart-item-card {
            display: flex;
            gap: 16px;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            background: var(--surface);
            align-items: center;
        }

        .cart-item-image {
            width: 70px;
            height: 90px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-item-image img { width: 100%; height: 100%; object-fit: cover; }
        .cart-item-image i { font-size: 1.5rem; color: var(--text-secondary); }

        .cart-item-info { flex: 1; }
        .cart-item-info h4 { margin: 0 0 4px 0; color: var(--text-primary); font-size: 1rem; font-weight: 600; }
        .cart-item-info p { margin: 4px 0; color: var(--text-secondary); font-size: 0.875rem; }

        .cart-item-actions {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .quantity-control {
            display: flex;
            gap: 6px;
            align-items: center;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 4px;
        }

        .qty-btn-minus, .qty-btn-plus {
            background: none;
            border: none;
            width: 28px;
            height: 28px;
            cursor: pointer;
            font-size: 1rem;
            color: var(--text-primary);
            transition: all 0.2s;
            border-radius: 4px;
        }

        .qty-btn-minus:hover, .qty-btn-plus:hover { background: var(--background); }
        .qty-display { width: 40px; text-align: center; border: none; background: none; font-weight: 600; color: var(--text-primary); font-size: 0.9375rem; }

        .cart-item-subtotal { font-weight: 600; color: var(--text-primary); min-width: 100px; text-align: right; font-size: 0.9375rem; }

        .btn-remove {
            background: var(--error);
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.8125rem;
            transition: all 0.2s;
        }

        .btn-remove:hover { background: #dc2626; }

        .cart-total-section {
            background: var(--surface);
            padding: 16px 20px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-top: 16px;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { width: 70px; }
            .logo-text span:not(.logo-subtitle), .nav-text, .user-name, .user-role { display: none; }
            .logo { justify-content: center; }
            .main-content { margin-left: 70px; }
            .user-profile { padding: 16px 8px; }
            .avatar { width: 45px; height: 45px; font-size: 18px; }
            .page-title { font-size: 1.25rem; }
            .badge { position: absolute; top: 8px; right: 12px; }
        }

        @media (max-width: 768px) {
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
            .header { flex-direction: column; align-items: flex-start; }
            .search-container { width: 100%; }
            .hero-section { flex-direction: column; text-align: center; gap: 16px; }
            .hero-buttons { justify-content: center; }
            .table-container { overflow-x: auto; }
            table { min-width: 650px; }
            .help-sections { grid-template-columns: 1fr; }
            .status-actions { justify-content: flex-start; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--background); }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

        /* Focus visible */
        .form-control:focus-visible, .btn:focus-visible, .nav-item:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Animation */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .alert { animation: fadeIn 0.25s ease-out; }

        @keyframes slideInRight { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes slideOutRight { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(20px); } }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-container">
            <a href="dashboard_pembeli.php" class="logo">
                <div class="logo-icon"><i class="fas fa-book-open"></i></div>
                <div class="logo-text">
                    <div class="logo-title">Pustaka Aruna</div>
                    <div class="logo-subtitle">BookStore</div>
                </div>
            </a>
        </div>
        <div class="user-profile">
            <div class="avatar">
                <?php
                $avatar_path = !empty($current_user['avatar']) ? 'uploads/avatars/' . $current_user['avatar'] : '';
                if (!empty($avatar_path) && file_exists($avatar_path)):
                ?>
                    <img src="<?php echo $avatar_path . '?t=' . time(); ?>" alt="Avatar">
                <?php else: ?>
                    <?php
                    $firstLetter = 'P';
                    if (isset($current_user['nama']) && !empty($current_user['nama'])) {
                        $firstLetter = strtoupper(substr($current_user['nama'], 0, 1));
                    }
                    echo $firstLetter;
                    ?>
                <?php endif; ?>
            </div>
            <div class="user-name">
                <?php
                if (isset($current_user['nama'])) {
                    echo htmlspecialchars($current_user['nama']);
                } else {
                    echo 'Pembeli';
                }
                ?>
            </div>
            <div class="user-role">Pembeli</div>
        </div>
        <div class="nav-menu">
            <a href="#" class="nav-item active" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i><span class="nav-text">Dashboard</span></a>
            <a href="#" class="nav-item" data-tab="pesanan"><i class="fas fa-shopping-bag"></i><span class="nav-text">Pesanan</span></a>
            <a href="#" class="nav-item" data-tab="keranjang"><i class="fas fa-shopping-cart"></i><span class="nav-text">Keranjang</span></a>
            <a href="#" class="nav-item" data-tab="status"><i class="fas fa-clipboard-check"></i><span class="nav-text">Status</span></a>
            <a href="#" class="nav-item" data-tab="laporan"><i class="fas fa-chart-bar"></i><span class="nav-text">Laporan</span></a>
            <a href="pembeli_cht.php" class="nav-item"><i class="fas fa-comment-dots"></i><span class="nav-text">Chat Penjual</span></a>
            <a href="#" class="nav-item" data-tab="myaccount"><i class="fas fa-user-circle"></i><span class="nav-text">My Account</span></a>
        </div>
        <div class="sidebar-footer">
            <a href="#" class="nav-item" data-tab="help" id="help-btn"><i class="fas fa-headset"></i><span class="nav-text">Help & Support</span></a>
            <a href="#" class="nav-item" id="signout-btn"><i class="fas fa-sign-out-alt"></i><span class="nav-text">Keluar</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Notification Dropdown -->
        <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header">
                <span>Notifikasi</span>
                <button class="notification-close" onclick="closeNotificationDropdown()"><i class="fas fa-times"></i></button>
            </div>
            <div id="notificationContent"></div>
            <a href="pembeli_cht.php" class="notification-view-all"><i class="fas fa-comment-dots"></i> Lihat Semua Chat</a>
        </div>

        <!-- Dashboard Tab -->
        <div class="tab-content active" id="dashboard-tab">
            <div class="header">
                <div class="page-title">Dashboard</div>
                <div class="header-actions">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Cari produk...">
                    </div>
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_chat_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_chat_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-logout" id="logout-btn-header"><i class="fas fa-sign-out-alt"></i> Keluar</button>
                </div>
            </div>

            <!-- Hero Section -->
            <div class="hero-section">
                <div class="hero-content">
                    <h1>Pustaka Aruna</h1>
                    <p>Selalu di depan melayani kebutuhan literasi Anda</p>
                    <div class="hero-buttons">

                        
                    </div>
                </div>
                <div><i class="fas fa-book-open" style="font-size: 80px; opacity: 0.3;"></i></div>
            </div>

            <!-- Products Section -->
            <div class="products-container">
                <div class="section-header">
                    <div class="section-title"></div>
                </div>
                <div class="products-grid" id="products-grid"></div>
            </div>
        </div>

        <!-- Pesanan Tab -->
        <div class="tab-content" id="pesanan-tab">
            <div class="header">
                <div class="page-title">Daftar Pesanan</div>
                <div class="header-actions">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Cari produk...">
                    </div>
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_chat_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_chat_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-logout" id="logout-btn-header"><i class="fas fa-sign-out-alt"></i> Keluar</button>
                </div>
            </div>
            <div class="products-container">
                <div class="section-header"><div class="section-title"></div></div>
                <div class="products-grid" id="pesanan-products"></div>
            </div>
        </div>

        <!-- Keranjang Tab -->
        <div class="tab-content" id="keranjang-tab">
            <div class="header">
                <div class="page-title">Keranjang</div>
                <div class="header-actions">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Cari produk...">
                    </div>
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_chat_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_chat_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-logout" id="logout-btn-header"><i class="fas fa-sign-out-alt"></i> Keluar</button>
                </div>
            </div>
            <div style="padding: 20px;">
                <div id="cart-items-container"></div>
            </div>
        </div>

        <!-- Status Tab -->
        <div class="tab-content" id="status-tab">
            <div class="header">
                <div class="page-title">Status Pesanan</div>
                <div class="header-actions">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="status-search" placeholder="Cari pesanan...">
                    </div>
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_chat_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_chat_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-logout" id="logout-btn-header"><i class="fas fa-sign-out-alt"></i> Keluar</button>
                </div>
            </div>
            <div class="tabs" id="status-tabs">
                <div class="tab active" data-status-filter="all">Semua</div>
                <div class="tab" data-status-filter="dikirim">Dikirim</div>
                <div class="tab" data-status-filter="ditolak">Ditolak</div>
                <div class="tab" data-status-filter="refund">Refund</div>
                <div class="tab" data-status-filter="selesai">Selesai</div>
            </div>
            <div id="status-cards-container"></div>
        </div>

        <!-- Laporan Tab -->
        <div class="tab-content" id="laporan-tab">
            <div class="header">
                <div class="page-title">Laporan</div>
                <div class="header-actions">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="laporan-search" placeholder="Cari transaksi...">
                    </div>
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_chat_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_chat_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary" onclick="exportLaporan()"><i class="fas fa-download"></i> Export</button>
                    <button class="btn-logout" id="logout-btn-header"><i class="fas fa-sign-out-alt"></i> Keluar</button>
                </div>
            </div>
            <div class="table-container">
                <div class="table-header"><div class="table-title">Riwayat Transaksi</div></div>
                <table>
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th><th>Produk</th><th>QTY</th><th>Total Harga</th>
                            <th>Metode Pembayaran</th><th>Alamat Penjual</th><th>Status</th><th>Tanggal</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="laporan-table-body"></tbody>
                </table>
            </div>
        </div>

        <!-- Help Guide Tab -->
        <div class="tab-content" id="help-tab">
            <div class="header">
                <div class="page-title">Panduan & Bantuan</div>
                <div class="header-actions">
                    <button class="btn-logout" id="logout-btn-header"><i class="fas fa-sign-out-alt"></i> Keluar</button>
                </div>
            </div>
            <div class="help-guide-container">
                <div class="help-guide-search">
                    <input type="text" id="help-guide-search" placeholder="Cari panduan...">
                </div>
                <div class="help-sections" id="help-sections-container">
                    <!-- Section: Pemesanan -->
                    <div class="help-section">
                        <div class="help-section-header">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>Pemesanan</h3>
                        </div>
                        <div class="help-section-content">
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Bagaimana cara memesan buku?</div>
                                <div class="help-item-content">1. Cari buku yang ingin Anda beli 2. Klik tombol "Tambah ke Keranjang" 3. Pergi ke keranjang dan klik "Checkout" 4. Pilih metode pembayaran dan alamat pengiriman 5. Konfirmasi pesanan</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Bisakah saya mengubah pesanan saya?</div>
                                <div class="help-item-content">Anda dapat mengubah pesanan sebelum penjual mengkonfirmasi. Setelah dikonfirmasi, Anda tidak dapat mengubah pesanan, tetapi Anda dapat membatalkan atau meminta refund.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Berapa lama waktu pemrosesan pesanan?</div>
                                <div class="help-item-content">Penjual biasanya memproses pesanan dalam 1-2 hari kerja. Waktu pengiriman tergantung dari lokasi dan metode pengiriman yang dipilih.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Pembayaran -->
                    <div class="help-section">
                        <div class="help-section-header">
                            <i class="fas fa-credit-card"></i>
                            <h3>Pembayaran</h3>
                        </div>
                        <div class="help-section-content">
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Metode pembayaran apa saja yang tersedia?</div>
                                <div class="help-item-content">Kami menerima berbagai metode pembayaran termasuk transfer bank, e-wallet, dan kartu kredit. Pilih metode yang paling sesuai untuk Anda saat checkout.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Apakah pembayaran saya aman?</div>
                                <div class="help-item-content">Ya, kami menggunakan enkripsi SSL dan sistem keamanan tingkat enterprise untuk melindungi informasi pembayaran Anda.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Kapan dana saya akan diproses?</div>
                                <div class="help-item-content">Dana biasanya diproses dalam 1-24 jam setelah konfirmasi pembayaran tergantung dari metode pembayaran yang Anda gunakan.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Pengiriman -->
                    <div class="help-section">
                        <div class="help-section-header">
                            <i class="fas fa-truck"></i>
                            <h3>Pengiriman</h3>
                        </div>
                        <div class="help-section-content">
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Bagaimana cara melacak pesanan saya?</div>
                                <div class="help-item-content">Anda dapat melacak pesanan di dashboard dalam tab "Pesanan Saya" atau "Laporan". Kami akan memberikan nomor resi pengiriman setelah penjual mengirim barang.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Berapa biaya pengiriman?</div>
                                <div class="help-item-content">Biaya pengiriman akan dihitung berdasarkan lokasi pengiriman dan dipilih oleh penjual. Anda dapat melihat detail biaya sebelum checkout.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Apa yang harus saya lakukan jika pesanan hilang atau rusak?</div>
                                <div class="help-item-content">Hubungi penjual segera melalui fitur chat dan laporkan masalah Anda. Sertakan foto bukti kerusakan atau hilangnya barang. Kami akan membantu proses klaim.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Refund & Pembatalan -->
                    <div class="help-section">
                        <div class="help-section-header">
                            <i class="fas fa-undo"></i>
                            <h3>Refund & Pembatalan</h3>
                        </div>
                        <div class="help-section-content">
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Bagaimana cara membatalkan pesanan?</div>
                                <div class="help-item-content">Anda dapat membatalkan pesanan sebelum penjual mengkonfirmasi. Setelah dikonfirmasi, Anda dapat meminta refund dengan memberikan alasan yang jelas.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Berapa lama proses refund?</div>
                                <div class="help-item-content">Proses refund biasanya memakan waktu 1-7 hari kerja setelah dikonfirmasi, tergantung dari metode pembayaran yang digunakan.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Alasan apa saja yang diterima untuk refund?</div>
                                <div class="help-item-content">Kami menerima refund untuk alasan seperti: barang tidak sesuai deskripsi, barang rusak/cacat, hingga transmisi ke alamat salah. Tim kami akan mengevaluasi setiap permintaan.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Akun & Profil -->
                    <div class="help-section">
                        <div class="help-section-header">
                            <i class="fas fa-user"></i>
                            <h3>Akun & Profil</h3>
                        </div>
                        <div class="help-section-content">
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Bagaimana cara mengubah profil saya?</div>
                                <div class="help-item-content">Klik menu "My Account" di dashboard dan klik tombol "Edit Profil". Anda dapat mengubah nama, email, alamat, dan avatar profil Anda.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Bagaimana cara mengganti password?</div>
                                <div class="help-item-content">Klik profil Anda, lalu pilih "Pengaturan Keamanan". Masukkan password lama Anda dan password baru dua kali untuk konfirmasi.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Bagaimana jika saya lupa password?</div>
                                <div class="help-item-content">Klik "Lupa Password?" di halaman login dan masukkan email Anda. Kami akan mengirim link reset password ke email Anda.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Keamanan -->
                    <div class="help-section">
                        <div class="help-section-header">
                            <i class="fas fa-shield-alt"></i>
                            <h3>Keamanan</h3>
                        </div>
                        <div class="help-section-content">
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Bagaimana data saya dilindungi?</div>
                                <div class="help-item-content">Data Anda dilindungi dengan enkripsi tingkat enterprise, firewall canggih, dan sistem keamanan berlapis. Kami mematuhi standar keamanan data internasional.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Apakah akun saya bisa diretas?</div>
                                <div class="help-item-content">Meskipun kami memiliki keamanan tingkat tinggi, selalu gunakan password yang kuat dan jangan bagikan kata sandi Anda kepada siapa pun. Aktifkan verifikasi dua faktor jika tersedia.</div>
                            </div>
                            <div class="help-item">
                                <div class="help-item-title"><i class="fas fa-chevron-right"></i> Apa yang harus saya lakukan jika akun saya diretas?</div>
                                <div class="help-item-content">Hubungi tim support kami segera melalui email atau nomor kontak yang tersedia. Kami akan membantu mengamankan akun Anda dan menginvestigasi aktivitas mencurigakan.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Account Tab -->
        <div class="tab-content" id="myaccount-tab">
            <div class="header">
                <div class="page-title">My Account</div>
                <div class="header-actions">
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_chat_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_chat_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-logout" id="logout-btn-header"><i class="fas fa-sign-out-alt"></i> Keluar</button>
                </div>
            </div>
            <?php if (!empty($update_message)) echo $update_message; ?>
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">Informasi Akun</div>
                    <div><button class="btn btn-primary" id="edit-account-btn"><i class="fas fa-edit"></i> Edit Profil</button></div>
                </div>
                <div style="padding: 20px;">
                    <div class="avatar-upload-container">
                        <div class="avatar-preview" onclick="document.getElementById('avatar-upload').click()">
                            <?php
                            $avatar_path = !empty($current_user['avatar']) ? 'uploads/avatars/' . $current_user['avatar'] : '';
                            if (!empty($avatar_path) && file_exists($avatar_path)):
                            ?>
                                <img src="<?php echo $avatar_path . '?t=' . time(); ?>" alt="Avatar" id="profile-avatar-img">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                        </div>
                        <form method="POST" enctype="multipart/form-data" id="avatar-form" style="display: none;">
                            <input type="file" id="avatar-upload" name="avatar" accept="image/*" onchange="this.form.submit()" class="avatar-upload-input">
                        </form>
                        <label for="avatar-upload" class="avatar-upload-label"><i class="fas fa-camera"></i> Upload Foto Profil</label>
                        <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 8px;">Format: JPG, PNG, GIF, WebP, BMP (Max 5MB)</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" id="account-nama" value="<?php echo isset($current_user['nama']) ? htmlspecialchars($current_user['nama']) : 'Pembeli'; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="account-email" value="<?php echo isset($current_user['email']) ? htmlspecialchars($current_user['email']) : 'pembeli@example.com'; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">NIK</label>
                        <input type="text" class="form-control" id="account-nik" value="<?php echo isset($current_user['nik']) ? htmlspecialchars($current_user['nik']) : ''; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" id="account-alamat" rows="3" readonly><?php echo isset($current_user['alamat_pembeli']) ? htmlspecialchars($current_user['alamat_pembeli']) : '-'; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" id="account-role" value="Pembeli" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals (Refund, Confirm, Order Detail, Invoice, Edit Account) -->
        <!-- Refund Modal -->
        <div class="modal" id="refund-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title">Ajukan Refund</div>
                    <button class="close-modal" id="close-refund-modal">&times;</button>
                </div>
                <form method="POST" id="refund-form">
                    <div class="modal-body">
                        <div class="alert alert-info"><i class="fas fa-info-circle"></i> Anda dapat mengajukan refund untuk pesanan dengan status Dikirim atau Selesai.</div>
                        <input type="hidden" name="id_pesanan" id="refund-order-id">
                        <input type="hidden" name="request_refund" value="1">
                        <div class="form-group">
                            <label class="form-label">Alasan Refund</label>
                            <textarea class="form-control" name="alasan_refund" id="refund-reason" rows="4" required placeholder="Jelaskan alasan Anda mengajukan refund..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Catatan:</label>
                            <ul style="color: var(--text-secondary); font-size: 0.875rem; margin-left: 20px;">
                                <li>Refund akan diproses dalam 1x24 jam</li>
                                <li>Dana akan dikembalikan ke metode pembayaran awal</li>
                                <li>Pastikan alasan Anda jelas dan dapat dipertanggungjawabkan</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancel-refund-btn">Batal</button>
                        <button type="submit" class="btn btn-warning">Ajukan Refund</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Confirm Received Modal -->
        <div class="modal" id="confirm-received-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title">Konfirmasi Pesanan Diterima</div>
                    <button class="close-modal" id="close-confirm-modal">&times;</button>
                </div>
                <form method="POST" id="confirm-received-form">
                    <div class="modal-body">
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Apakah Anda yakin telah menerima pesanan ini?</div>
                        <input type="hidden" name="id_pesanan" id="confirm-order-id">
                        <input type="hidden" name="confirm_received" value="1">
                        <p>Dengan mengkonfirmasi, Anda menyatakan bahwa pesanan telah diterima dalam kondisi baik. Status pesanan akan berubah menjadi Selesai.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancel-confirm-btn">Batal</button>
                        <button type="submit" class="btn btn-success">Ya, Terima</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Order Detail Modal -->
        <div class="modal" id="order-detail-modal">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <div class="modal-title">Detail Pesanan</div>
                    <button class="close-modal" id="close-order-detail-modal">&times;</button>
                </div>
                <div class="modal-body" id="order-detail-content"></div>
            </div>
        </div>

        <!-- Invoice Modal -->
        <div class="modal" id="invoice-modal">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <div class="modal-title">Invoice Pembayaran</div>
                    <button class="close-modal" id="close-invoice-modal">&times;</button>
                </div>
                <div class="modal-body" id="invoice-content"></div>
            </div>
        </div>

        <!-- Edit Account Modal -->
        <div class="modal" id="edit-account-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title">Edit Profil</div>
                    <button class="close-modal" id="close-edit-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="edit-account-form">
                        <div class="form-group">
                            <label class="form-label">Nama</label>
                            <input type="text" class="form-control" id="edit-nama" name="nama" value="<?php echo isset($current_user['nama']) ? htmlspecialchars($current_user['nama']) : 'Pembeli'; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit-email" name="email" value="<?php echo isset($current_user['email']) ? htmlspecialchars($current_user['email']) : 'pembeli@example.com'; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">NIK</label>
                            <input type="text" class="form-control" id="edit-nik" name="nik" value="<?php echo isset($current_user['nik']) ? htmlspecialchars($current_user['nik']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" id="edit-alamat" name="alamat_pembeli" rows="3"><?php echo isset($current_user['alamat_pembeli']) ? htmlspecialchars($current_user['alamat_pembeli']) : ''; ?></textarea>
                        </div>
                        <input type="hidden" name="update_profile" value="1">
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancel-edit-btn">Batal</button>
                    <button class="btn btn-primary" id="save-account-btn">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript (semua fungsi tetap sama, hanya styling alert yang disesuaikan) -->
    <script>
        function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        function formatCurrency(amount) { return 'Rp ' + parseInt(amount).toLocaleString('id-ID'); }
        function formatDate(dateString) { if (!dateString) return '-'; const date = new Date(dateString); return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
        function formatTimeAgo(dateString) { const date = new Date(dateString); const now = new Date(); const diffMs = now - date; const diffMins = Math.floor(diffMs / 60000); const diffHours = Math.floor(diffMs / 3600000); const diffDays = Math.floor(diffMs / 86400000); if (diffMins < 1) return 'Baru saja'; if (diffMins < 60) return diffMins + ' menit lalu'; if (diffHours < 24) return diffHours + ' jam lalu'; if (diffDays < 7) return diffDays + ' hari lalu'; return date.toLocaleDateString('id-ID'); }
        function getStatusBadgeClass(status) { const map = { 'menunggu_pembayaran': 'status-menunggu', 'dibayar': 'status-dibayar', 'diproses': 'status-diproses', 'dikirim': 'status-dikirim', 'selesai': 'status-selesai', 'dibatalkan': 'status-ditolak', 'ditolak': 'status-ditolak' }; return map[status] || 'status-menunggu'; }
        function getStatusText(status) { const map = { 'menunggu_pembayaran': 'Menunggu Pembayaran', 'dibayar': 'Dibayar', 'diproses': 'Diproses', 'dikirim': 'Dikirim', 'selesai': 'Selesai', 'dibatalkan': 'Dibatalkan', 'ditolak': 'Ditolak' }; return map[status] || status; }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.style.cssText = `position: fixed; top: 20px; right: 20px; z-index: 9999; animation: slideInRight 0.3s ease; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);`;
            notification.innerHTML = `<div style="display: flex; align-items: center; gap: 10px;"><i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i><span>${message}</span></div>`;
            document.body.appendChild(notification);
            setTimeout(() => { notification.style.animation = 'slideOutRight 0.3s ease'; setTimeout(() => notification.remove(), 300); }, 3000);
        }

        document.getElementById('notificationBell').addEventListener('click', function(e) { e.stopPropagation(); const dropdown = document.getElementById('notificationDropdown'); dropdown.classList.toggle('show'); if (dropdown.classList.contains('show')) { loadNotifications(); } });
        function closeNotificationDropdown() { document.getElementById('notificationDropdown').classList.remove('show'); }
        document.addEventListener('click', function(e) { const dropdown = document.getElementById('notificationDropdown'); const bell = document.getElementById('notificationBell'); if (!dropdown.contains(e.target) && !bell.contains(e.target)) { dropdown.classList.remove('show'); } });

        function loadNotifications() {
            const contentDiv = document.getElementById('notificationContent');
            contentDiv.innerHTML = '<div class="notification-item"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>';
            fetch('api_get_notifications.php?t=' + Date.now()).then(response => response.json()).then(data => {
                if (data.success && data.notifications.length > 0) {
                    contentDiv.innerHTML = '';
                    data.notifications.forEach(notif => {
                        contentDiv.innerHTML += `<div class="notification-item" onclick="window.location.href='${notif.type === 'chat' ? 'pembeli_cht.php' : 'dashboard_pembeli.php'}'"><div class="notification-sender"><i class="fas ${notif.icon}"></i> ${notif.title}</div><div class="notification-message">${escapeHtml(notif.message)}</div><div class="notification-time">${formatTimeAgo(notif.time)}</div></div>`;
                    });
                } else { contentDiv.innerHTML = '<div class="notification-empty"><i class="fas fa-check-circle"></i> Tidak ada notifikasi baru</div>'; }
            }).catch(() => { contentDiv.innerHTML = '<div class="notification-empty"><i class="fas fa-exclamation-circle"></i> Gagal memuat notifikasi</div>'; });
        }

        function loadProducts() {
            const productsGrid = document.getElementById('products-grid');
            productsGrid.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Memuat produk...</div>';
            fetch('api_get_products.php?limit=6').then(response => response.json()).then(data => {
                productsGrid.innerHTML = '';
                if (data && data.success && data.data && data.data.length > 0) {
                    data.data.forEach(product => {
                        const productCard = document.createElement('div');
                        productCard.className = 'product-card';
                        productCard.innerHTML = `<div class="product-image">${product.foto_produk ? `<img src="uploads/produk/${product.foto_produk}" alt="${escapeHtml(product.nama_buku)}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23f1f5f9%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2255%22 font-size=%2214%22 fill=%22%2364748b%22 text-anchor=%22middle%22%3E${escapeHtml(product.nama_buku.charAt(0))}%3C/text%3E%3C/svg%3E'">` : '<i class="fas fa-book"></i>'}</div><div class="product-info"><div class="product-category"><i class="fas fa-tag"></i> ${escapeHtml(product.nama_penjual || 'Penjual')}</div><div class="product-title">${escapeHtml(product.nama_buku)}</div><div class="product-meta"><div class="product-stock">Stok: ${product.stok}</div><div class="product-price">${formatCurrency(product.harga)}</div></div><div class="product-actions"><button class="btn btn-success btn-block btn-sm" onclick="addProductToCart('${product.id_produk}', '${product.id_penjual}', this)"><i class="fas fa-shopping-cart"></i> Tambah Keranjang</button></div></div>`;
                        productsGrid.appendChild(productCard);
                    });
                } else { productsGrid.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">Belum ada produk</div>'; }
            }).catch(() => { productsGrid.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--error);">Error memuat produk</div>'; });
        }

        function addProductToCart(productId, sellerId, button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menambahkan...';
            button.disabled = true;
            fetch('api_add_to_cart.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id_produk=${productId}&id_penjual=${sellerId}&quantity=1` })
            .then(response => response.json()).then(data => {
                if (data.success) { showNotification('Produk berhasil ditambahkan ke keranjang!', 'success'); button.innerHTML = '<i class="fas fa-check"></i> Ditambahkan!'; setTimeout(() => { button.innerHTML = originalText; button.disabled = false; }, 2000); if (document.getElementById('cart-items-container')) { loadCartItems(); } }
                else { showNotification(data.message || 'Gagal menambahkan ke keranjang', 'danger'); button.innerHTML = originalText; button.disabled = false; }
            }).catch(() => { showNotification('Error koneksi', 'danger'); button.innerHTML = originalText; button.disabled = false; });
        }

        function loadCartItems() {
            const container = document.getElementById('cart-items-container');
            if (!container) return;
            container.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Memuat keranjang...</div>';
            fetch('api_get_cart.php').then(response => response.json()).then(data => {
                container.innerHTML = '';
                if (data.success && data.data && data.data.length > 0) {
                    let totalPrice = 0;
                    const cartTable = document.createElement('div');
                    cartTable.className = 'cart-items-table';
                    data.data.forEach(item => {
                        const subtotal = item.harga * item.quantity;
                        totalPrice += subtotal;
                        const itemCard = document.createElement('div');
                        itemCard.className = 'cart-item-card';
                        itemCard.innerHTML = `<div class="cart-item-image">${item.foto_produk ? `<img src="uploads/produk/${item.foto_produk}" alt="${escapeHtml(item.nama_buku)}">` : '<i class="fas fa-book"></i>'}</div><div class="cart-item-info"><h4>${escapeHtml(item.nama_buku)}</h4><p>${escapeHtml(item.nama_penjual)}</p><p style="color: var(--text-secondary);">Rp ${parseInt(item.harga).toLocaleString('id-ID')}</p></div><div class="cart-item-actions"><div class="quantity-control"><button class="qty-btn-minus" onclick="updateCartQuantity(${item.id_keranjang}, ${item.quantity - 1})">-</button><input type="number" value="${item.quantity}" min="1" readonly class="qty-display"><button class="qty-btn-plus" onclick="updateCartQuantity(${item.id_keranjang}, ${item.quantity + 1})">+</button></div><div class="cart-item-subtotal">Rp ${subtotal.toLocaleString('id-ID')}</div><button class="btn-remove" onclick="removeCartItem(${item.id_keranjang})"><i class="fas fa-trash"></i> Hapus</button></div>`;
                        cartTable.appendChild(itemCard);
                    });
                    container.appendChild(cartTable);
                    const totalSection = document.createElement('div');
                    totalSection.className = 'cart-total-section';
                    totalSection.innerHTML = `<div class="cart-total"><span>Total Harga:</span><span style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">Rp ${totalPrice.toLocaleString('id-ID')}</span></div><div style="display: flex; gap: 10px; margin-top: 16px;"><button class="btn btn-secondary" style="flex: 1;" onclick="document.querySelector('[data-tab=\"dashboard\"]').click()"><i class="fas fa-arrow-left"></i> Lanjut Belanja</button><button class="btn btn-success" style="flex: 1;" onclick="proceedToCheckout(this)"><i class="fas fa-credit-card"></i> Checkout</button></div>`;
                    container.appendChild(totalSection);
                } else { container.innerHTML = '<div style="text-align: center; padding: 60px 20px;"><i class="fas fa-shopping-bag" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 16px;"></i><p style="color: var(--text-secondary);">Keranjang Anda kosong</p><button class="btn btn-primary" style="margin-top: 16px;" onclick="document.querySelector(\'[data-tab=\"dashboard\"]\').click()">Mulai Belanja</button></div>'; }
            }).catch(() => { container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--error);">Error memuat keranjang</div>'; });
        }

        function loadPesanan() {
            const container = document.getElementById('pesanan-products');
            if (!container) return;
            container.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Memuat pesanan...</div>';
            fetch('api_pesanan_pembeli.php?action=list', { credentials: 'same-origin' }).then(response => response.json()).then(data => {
                container.innerHTML = '';
                if (data.success && data.data && data.data.length > 0) {
                    data.data.forEach(order => {
                        const statusBadgeClass = getStatusBadgeClass(order.status);
                        const statusText = getStatusText(order.status);
                        const orderCard = document.createElement('div');
                        orderCard.className = 'product-card';
                        orderCard.innerHTML = `<div class="product-image" style="position: relative;">${order.foto_produk ? `<img src="uploads/produk/${order.foto_produk}" alt="${escapeHtml(order.nama_buku)}" style="width: 100%; height: 100%; object-fit: cover;">` : '<i class="fas fa-book"></i>'}<span class="status-badge ${statusBadgeClass}" style="position: absolute; top: 10px; right: 10px;">${statusText}</span></div><div class="product-info"><div class="product-category"><i class="fas fa-user"></i> ${escapeHtml(order.nama_penjual || 'Penjual')}</div><div class="product-title">${escapeHtml(order.nama_buku)}</div><div class="product-meta"><div class="product-stock">Qty: ${order.quantity}</div><div class="product-price">${formatCurrency(order.total_harga)}</div></div><div style="font-size: 0.8125rem; color: var(--text-secondary); margin: 8px 0;"><i class="fas fa-calendar"></i> ${formatDate(order.created_at)}</div><div class="product-actions"><button class="btn btn-primary btn-block btn-sm" onclick="showOrderDetail('${order.id_pesanan}')"><i class="fas fa-eye"></i> Lihat Detail</button><button class="btn btn-secondary btn-block btn-sm" onclick="window.location.href='pembeli_cht.php?seller=${order.id_penjual}'"><i class="fas fa-comments"></i> Chat</button></div></div>`;
                        container.appendChild(orderCard);
                    });
                } else { container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">Belum ada pesanan</div>'; }
            }).catch(() => { container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--error);">Error memuat pesanan</div>'; });
        }

        function updateCartQuantity(cartId, newQuantity) { if (newQuantity <= 0) { removeCartItem(cartId); return; } fetch('api_update_cart.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id_keranjang=${cartId}&quantity=${newQuantity}` }).then(response => response.json()).then(data => { if (data.success) { loadCartItems(); showNotification('Keranjang diupdate', 'success'); } else { showNotification(data.message || 'Gagal update keranjang', 'danger'); } }).catch(() => {}); }
        function removeCartItem(cartId) { if (!confirm('Hapus item dari keranjang?')) return; fetch('api_remove_from_cart.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id_keranjang=${cartId}` }).then(response => response.json()).then(data => { if (data.success) { loadCartItems(); showNotification('Item dihapus', 'success'); } else { showNotification(data.message || 'Gagal menghapus item', 'danger'); } }).catch(() => {}); }
        function proceedToCheckout(button) { const btn = button || document.querySelector('.cart-total-section .btn-success'); if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengalihkan...'; } setTimeout(() => { window.location.href = 'konfirmasi.php'; }, 300); }

        function loadStatusCards(filter = 'all') {
            const container = document.getElementById('status-cards-container');
            container.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Memuat status pesanan...</div>';
            fetch('api_pesanan_pembeli.php?action=list', { credentials: 'same-origin' }).then(response => response.json()).then(data => {
                container.innerHTML = '';
                if (data.success && data.data && data.data.length > 0) {
                    let filteredOrders = data.data;
                    if (filter === 'dikirim') filteredOrders = data.data.filter(o => o.status === 'dikirim');
                    else if (filter === 'ditolak') filteredOrders = data.data.filter(o => o.status === 'ditolak' || o.status === 'dibatalkan');
                    else if (filter === 'refund') filteredOrders = data.data.filter(o => o.status.startsWith('refund'));
                    else if (filter === 'selesai') filteredOrders = data.data.filter(o => o.status === 'selesai');
                    if (filteredOrders.length === 0) { container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">Tidak ada pesanan dengan status ini</div>'; return; }
                    filteredOrders.forEach(order => {
                        const statusClass = getStatusBadgeClass(order.status);
                        const statusText = getStatusText(order.status);
                        const card = document.createElement('div');
                        card.className = `status-card ${order.status}`;
                        let actionButtons = '';
                        if (order.status === 'dikirim') actionButtons = `<button class="btn btn-success btn-sm" onclick="openConfirmReceivedModal('${order.id_pesanan}')"><i class="fas fa-check"></i> Konfirmasi Diterima</button><button class="btn btn-warning btn-sm" onclick="openRefundModal('${order.id_pesanan}')"><i class="fas fa-undo"></i> Ajukan Refund</button>`;
                        else if (order.status === 'selesai') actionButtons = `<button class="btn btn-warning btn-sm" onclick="openRefundModal('${order.id_pesanan}')"><i class="fas fa-undo"></i> Ajukan Refund</button><button class="btn btn-info btn-sm" onclick="showInvoice('${order.id_pesanan}')"><i class="fas fa-file-invoice"></i> Invoice</button>`;
                        else if (order.status.startsWith('refund')) actionButtons = `<button class="btn btn-info btn-sm" onclick="trackRefund('${order.id_pesanan}')"><i class="fas fa-search"></i> Lacak Refund</button>`;
                        else if (order.status === 'ditolak' || order.status === 'dibatalkan') actionButtons = `<button class="btn btn-primary btn-sm" onclick="reorder('${order.id_pesanan}')"><i class="fas fa-redo"></i> Pesan Lagi</button>`;
                        else actionButtons = `<button class="btn btn-info btn-sm" onclick="showOrderDetail('${order.id_pesanan}')"><i class="fas fa-eye"></i> Detail</button>`;
                        let trackingInfo = '';
                        if (order.status === 'dikirim' && order.nomor_resi) trackingInfo = `<div class="tracking-info"><span class="label"><i class="fas fa-truck"></i> No. Resi:</span> ${escapeHtml(order.nomor_resi)}<br><span class="label"><i class="fas fa-calendar"></i> Dikirim:</span> ${formatDate(order.tanggal_dikirim)}</div>`;
                        let refundInfo = '';
                        if (order.status === 'ditolak' && order.catatan_pembeli && order.catatan_pembeli.startsWith('[REFUND REQUEST]')) {
                            const refundReason = order.catatan_pembeli.replace('[REFUND REQUEST] ', '');
                            let trackingLocation = '';
                            if (order.catatan_penjual || order.nomor_resi) trackingLocation = `<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);"><span class="label"><i class="fas fa-map-marker-alt"></i> Lokasi Ekspeditasi:</span><div style="margin-top: 6px; font-size: 0.8125rem; color: var(--text-secondary); white-space: pre-wrap;">${order.nomor_resi ? `<strong>Resi: </strong>${escapeHtml(order.nomor_resi)}<br>` : ''}${order.catatan_penjual ? `${escapeHtml(order.catatan_penjual)}` : 'Belum ada informasi tracking'}</div></div>`;
                            refundInfo = `<div class="refund-info"><span class="label"><i class="fas fa-info-circle"></i> Alasan Refund:</span><div class="reason">${escapeHtml(refundReason)}</div><small>Diajukan: ${formatDate(order.updated_at)}</small>${trackingLocation}</div>`;
                        }
                        card.innerHTML = `<div class="status-header"><span class="status-order-id">#${escapeHtml(order.id_pesanan)}</span><span class="status-date">${formatDate(order.created_at)}</span></div><div class="status-body"><div class="status-product-image">${order.foto_produk ? `<img src="uploads/produk/${order.foto_produk}" alt="${escapeHtml(order.nama_buku)}">` : '<i class="fas fa-book"></i>'}</div><div class="status-product-info"><div class="status-product-name">${escapeHtml(order.nama_buku)}</div><div class="status-product-seller"><i class="fas fa-store"></i> ${escapeHtml(order.nama_penjual)}</div><div style="margin-top: 6px;"><span class="status-badge ${statusClass}">${statusText}</span></div><div style="margin-top: 8px;"><strong>Jumlah:</strong> ${order.quantity} x ${formatCurrency(order.harga_satuan)}<br><strong>Total:</strong> ${formatCurrency(order.total_harga)}</div>${order.metode_pembayaran ? `<div style="margin-top: 6px; font-size: 0.8125rem;"><i class="fas fa-credit-card"></i> ${escapeHtml(order.metode_pembayaran)}</div>` : ''}</div></div>${trackingInfo}${refundInfo}<div class="status-actions">${actionButtons}<button class="btn btn-secondary btn-sm" onclick="window.location.href='pembeli_cht.php?seller=${order.id_penjual}'"><i class="fas fa-comments"></i> Chat</button></div>`;
                        container.appendChild(card);
                    });
                } else { container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">Belum ada pesanan</div>'; }
            }).catch(() => { container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--error);">Error memuat status pesanan</div>'; });
        }

        function loadLaporan() {
            const tbody = document.getElementById('laporan-table-body');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Memuat laporan...</td></tr>';
            fetch('api_pesanan_pembeli.php?action=list', { credentials: 'same-origin' }).then(response => response.json()).then(data => {
                tbody.innerHTML = '';
                if (data.success && data.data && data.data.length > 0) {
                    data.data.forEach(order => {
                        const statusBadgeClass = getStatusBadgeClass(order.status);
                        const statusText = getStatusText(order.status);
                        const row = document.createElement('tr');
                        row.innerHTML = `<td><strong>${escapeHtml(order.id_pesanan)}</strong></td><td>${escapeHtml(order.nama_buku)}</td><td>${order.quantity}</td><td>${formatCurrency(order.total_harga)}</td><td>${escapeHtml(order.metode_pembayaran || '-')}</td><td>${order.alamat_penjual ? `<a href="${order.alamat_penjual}" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--background); color: var(--text-primary); text-decoration: none; border-radius: var(--radius-sm); border: 1px solid var(--border); font-weight: 500; font-size: 0.8125rem;"><i class="fas fa-map-marker-alt"></i> Lihat Lokasi</a>` : '-'}</td><td><span class="status-badge ${statusBadgeClass}">${statusText}</span></td><td>${formatDate(order.created_at)}</td><td><button class="btn btn-info btn-sm" onclick="showInvoice('${order.id_pesanan}')"><i class="fas fa-file-invoice"></i> Invoice</button></td>`;
                        tbody.appendChild(row);
                    });
                } else { tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px; color: var(--text-secondary);">Belum ada transaksi</td></tr>'; }
            }).catch(() => { tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px; color: var(--error);">Error memuat laporan</td></tr>'; });
        }

        function openRefundModal(orderId) { document.getElementById('refund-order-id').value = orderId; document.getElementById('refund-modal').style.display = 'flex'; }
        function openConfirmReceivedModal(orderId) { document.getElementById('confirm-order-id').value = orderId; document.getElementById('confirm-received-modal').style.display = 'flex'; }

        function showOrderDetail(orderId) {
            const modal = document.getElementById('order-detail-modal');
            const content = document.getElementById('order-detail-content');
            content.innerHTML = '<div style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> Memuat detail...</div>';
            modal.style.display = 'flex';
            fetch(`api_pesanan_pembeli.php?action=detail&id=${orderId}`, { credentials: 'same-origin' }).then(response => response.json()).then(data => {
                if (data.success && data.data) {
                    const order = data.data;
                    const statusBadgeClass = getStatusBadgeClass(order.status);
                    const statusText = getStatusText(order.status);
                    content.innerHTML = `<div style="margin-bottom: 20px;"><h3 style="color: var(--text-primary); margin-bottom: 10px;">#${escapeHtml(order.id_pesanan)}</h3><span class="status-badge ${statusBadgeClass}">${statusText}</span></div><div style="display: flex; gap: 20px; margin-bottom: 20px;"><div style="width: 100px; height: 100px; border-radius: var(--radius-sm); overflow: hidden; background: var(--background); display: flex; align-items: center; justify-content: center;">${order.foto_produk ? `<img src="uploads/produk/${order.foto_produk}" style="width: 100%; height: 100%; object-fit: cover;">` : '<i class="fas fa-book" style="font-size: 2rem; color: var(--text-secondary);"></i>'}</div><div style="flex: 1;"><h4 style="margin-bottom: 8px;">${escapeHtml(order.nama_buku)}</h4><p><i class="fas fa-store"></i> ${escapeHtml(order.nama_penjual)}</p><p>Jumlah: ${order.quantity} x ${formatCurrency(order.harga_satuan)}</p><p><strong>Total: ${formatCurrency(order.total_harga)}</strong></p></div></div><div style="border-top: 1px solid var(--border); padding-top: 15px;"><h4 style="margin-bottom: 10px;">Informasi Pengiriman</h4><p><strong>Alamat:</strong> ${escapeHtml(order.alamat_pengiriman || '-')}</p>${order.nomor_resi ? `<p><strong>No. Resi:</strong> ${escapeHtml(order.nomor_resi)}</p>` : ''}</div><div style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 15px;"><h4 style="margin-bottom: 10px;">Informasi Pembayaran</h4><p><strong>Metode:</strong> ${escapeHtml(order.metode_pembayaran || '-')}</p>${order.bukti_pembayaran ? `<p><strong>Bukti Bayar:</strong><button class="btn btn-sm btn-info" onclick="window.open('uploads/${order.bukti_pembayaran}', '_blank')"><i class="fas fa-image"></i> Lihat</button></p>` : ''}</div>${order.catatan_pembeli && order.catatan_pembeli.startsWith('[REFUND REQUEST]') ? `<div style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 15px;"><h4 style="margin-bottom: 10px;">Informasi Refund</h4><p><strong>Alasan:</strong> ${escapeHtml(order.catatan_pembeli.replace('[REFUND REQUEST] ', ''))}</p><p><strong>Diajukan:</strong> ${formatDate(order.updated_at)}</p>${order.status === 'refund' ? `<p><strong>Catatan Penjual:</strong> ${escapeHtml(order.catatan_penjual || '-')}</p>` : ''}</div>` : ''}`;
                } else { content.innerHTML = '<div class="alert alert-danger">Gagal memuat detail pesanan</div>'; }
            }).catch(() => { content.innerHTML = '<div class="alert alert-danger">Error memuat detail pesanan</div>'; });
        }

        function showInvoice(orderId) {
            const modal = document.getElementById('invoice-modal');
            const content = document.getElementById('invoice-content');
            content.innerHTML = '<div style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> Memuat invoice...</div>';
            modal.style.display = 'flex';
            const checkoutAlamat = sessionStorage.getItem('checkout_alamat') || '-';
            const checkoutMetode = sessionStorage.getItem('checkout_metode') || '-';
            fetch(`api_pesanan_pembeli.php?action=detail&id=${orderId}`, { credentials: 'same-origin' }).then(response => response.json()).then(data => {
                if (data.success && data.data) {
                    const order = data.data;
                    let metodeDisplay = order.metode_pembayaran || checkoutMetode || '-';
                    if (metodeDisplay === 'qris' || metodeDisplay === 'tf' || metodeDisplay === 'transfer') metodeDisplay = '<i class="fas fa-university"></i> Transfer (TF)';
                    const alamatDisplay = checkoutAlamat !== '-' ? checkoutAlamat : (order.alamat_pengiriman || '-');
                    const statusClass = getStatusBadgeClass(order.status);
                    const statusText = getStatusText(order.status);
                    const ongkirValue = parseInt(order.biaya_pengiriman || order.ongkir || 0);
                    const subtotalValue = parseInt(order.total_harga || 0);
                    const totalBayarValue = subtotalValue + ongkirValue;

                    content.innerHTML = `
                        <div class="invoice-receipt">
                            <div class="invoice-receipt-header">
                                <h2><i class="fas fa-book-open"></i> PUSTAKA ARUNA</h2>
                                <p>Invoice / Struk Pembayaran</p>
                                <span class="invoice-payment-badge"><i class="fas fa-money-bill-wave"></i> ${order.metode_pembayaran === 'cash' ? 'CASH / TUNAI' : 'TRANSFER BANK'}</span>
                            </div>
                            <div class="invoice-receipt-body">
                                <div class="invoice-info-row"><span class="invoice-info-label">No. Invoice</span><span class="invoice-info-value">#${String(order.id_pesanan).padStart(8, '0')}</span></div>
                                <div class="invoice-info-row"><span class="invoice-info-label">Tanggal</span><span class="invoice-info-value">${formatDate(order.created_at)}</span></div>
                                <div class="invoice-info-row"><span class="invoice-info-label">Pembeli</span><span class="invoice-info-value">${escapeHtml(order.nama_pembeli || '<?php echo addslashes($current_user["nama"] ?? "Pembeli"); ?>')}</span></div>
                                <div class="invoice-info-row"><span class="invoice-info-label">Kurir</span><span class="invoice-info-value">${escapeHtml(order.kurir || 'JNE')}</span></div>
                                <div class="invoice-info-row"><span class="invoice-info-label">Status</span><span class="invoice-info-value"><span class="invoice-status-badge">${statusText}</span></span></div>

                                <div class="invoice-items">
                                    <div class="invoice-item" style="border-bottom: 1px dashed #ddd; padding-bottom: 10px; margin-bottom: 10px;">
                                        <span class="invoice-item-name" style="font-weight: 600;">ITEM</span>
                                        <span class="invoice-item-qty" style="font-weight: 600;">QTY</span>
                                        <span class="invoice-item-price" style="font-weight: 600;">TOTAL</span>
                                    </div>
                                    <div class="invoice-item">
                                        <span class="invoice-item-name">${escapeHtml(order.nama_buku || order.nama_produk || '-')}</span>
                                        <span class="invoice-item-qty">${order.quantity || 1}</span>
                                        <span class="invoice-item-price">${formatCurrency(subtotalValue)}</span>
                                    </div>
                                </div>

                                <div class="invoice-total">
                                    <div class="invoice-total-row"><span>Subtotal</span><span>${formatCurrency(subtotalValue)}</span></div>
                                    <div class="invoice-total-row"><span>Ongkir</span><span>${formatCurrency(ongkirValue)}</span></div>
                                    <div class="invoice-total-row final"><span>TOTAL BAYAR</span><span>${formatCurrency(totalBayarValue)}</span></div>
                                    ${order.metode_pembayaran === 'cash' ? `
                                    <div class="invoice-total-row" style="border-top: 1px dashed var(--border); padding-top: 10px; margin-top: 10px;"><span>Bayar</span><span>${formatCurrency(order.bayar || totalBayarValue)}</span></div>
                                    <div class="invoice-total-row" style="color: var(--success); font-weight: 600;"><span>KEMBALIAN</span><span>${formatCurrency((order.bayar || totalBayarValue) - totalBayarValue)}</span></div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="invoice-footer">
                                <p><i class="fas fa-heart"></i> Terima kasih telah berbelanja!</p>
                                <p>Pustaka Aruna - Menjaga Warisan Literasi</p>
                                <p style="font-size: 0.75rem; margin-top: 10px; opacity: 0.7;">Invoice ini sah dan dapat digunakan sebagai bukti transaksi</p>
                            </div>
                        </div>
                        <div class="no-print" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                            <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                        </div>
                    `;
                } else { content.innerHTML = '<div class="alert alert-danger">Gagal memuat invoice</div>'; }
            }).catch(() => { content.innerHTML = '<div class="alert alert-danger">Error memuat invoice</div>'; });
        }

        function trackRefund(orderId) { showOrderDetail(orderId); }
        function reorder(orderId) { if (confirm('Pesan lagi produk ini?')) { window.location.href = `reorder.php?id=${orderId}`; } }
        function exportLaporan() { window.location.href = 'export_laporan_pembeli.php'; }

        function initTabs() {
            const navItems = document.querySelectorAll('.nav-item[data-tab]');
            const tabContents = document.querySelectorAll('.tab-content');
            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('data-tab');
                    if (!tabId) return;
                    navItems.forEach(navItem => navItem.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                    if (tabId === 'laporan') loadLaporan();
                    else if (tabId === 'status') loadStatusCards('all');
                    else if (tabId === 'pesanan') loadPesanan();
                    else if (tabId === 'keranjang') loadCartItems();
                });
            });
        }

        function initStatusFilters() {
            const filterTabs = document.querySelectorAll('[data-status-filter]');
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    filterTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    loadStatusCards(this.getAttribute('data-status-filter'));
                });
            });
        }

        function initSearch() {
            const statusSearch = document.getElementById('status-search');
            if (statusSearch) {
                statusSearch.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    document.querySelectorAll('.status-card').forEach(card => {
                        card.style.display = card.textContent.toLowerCase().includes(searchTerm) ? 'block' : 'none';
                    });
                });
            }
            const laporanSearch = document.getElementById('laporan-search');
            if (laporanSearch) {
                laporanSearch.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    document.querySelectorAll('#laporan-table-body tr').forEach(row => {
                        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
                    });
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadProducts();
            initTabs();
            initStatusFilters();
            initSearch();

            // Hapus tombol logout merah di header (dekat ikon lonceng)
            document.querySelectorAll('.header-actions .btn-logout').forEach(btn => btn.remove());

            function updateNotificationBadge() {
                fetch('api_get_notifications.php?t=' + Date.now()).then(response => response.json()).then(data => {
                    document.querySelectorAll('#notificationBell').forEach(bell => {
                        const oldBadge = bell.querySelector('.notification-badge');
                        if (oldBadge) oldBadge.remove();
                        if (data.unread_count > 0) {
                            const badge = document.createElement('span');
                            badge.className = 'notification-badge';
                            badge.textContent = data.unread_count;
                            bell.appendChild(badge);
                        }
                    });
                }).catch(() => {});
            }
            updateNotificationBadge();
            setInterval(updateNotificationBadge, 30000);

            document.getElementById('signout-btn').addEventListener('click', function(e) { e.preventDefault(); if (confirm('Apakah Anda yakin ingin keluar?')) { window.location.href = 'dashboard_pembeli.php?logout=true'; } });
            document.querySelectorAll('#logout-btn-header').forEach(btn => {
                btn.addEventListener('click', function(e) { e.preventDefault(); if (confirm('Apakah Anda yakin ingin logout?')) { window.location.href = 'dashboard_pembeli.php?logout=true'; } });
            });

            const editAccountBtn = document.getElementById('edit-account-btn');
            const editModal = document.getElementById('edit-account-modal');
            const closeEditBtn = document.getElementById('close-edit-modal');
            const cancelEditBtn = document.getElementById('cancel-edit-btn');
            const saveAccountBtn = document.getElementById('save-account-btn');
            if (editAccountBtn) editAccountBtn.addEventListener('click', function() { editModal.style.display = 'flex'; document.getElementById('edit-nama').value = document.getElementById('account-nama').value; document.getElementById('edit-email').value = document.getElementById('account-email').value; document.getElementById('edit-nik').value = document.getElementById('account-nik').value; document.getElementById('edit-alamat').value = document.getElementById('account-alamat').value; });
            if (closeEditBtn) closeEditBtn.addEventListener('click', () => { editModal.style.display = 'none'; });
            if (cancelEditBtn) cancelEditBtn.addEventListener('click', () => { editModal.style.display = 'none'; });
            if (saveAccountBtn) saveAccountBtn.addEventListener('click', () => { document.getElementById('edit-account-form').submit(); });

            window.onclick = function(event) { if (event.target.classList.contains('modal')) { event.target.style.display = 'none'; } };
            document.getElementById('close-refund-modal').addEventListener('click', () => { document.getElementById('refund-modal').style.display = 'none'; });
            document.getElementById('cancel-refund-btn').addEventListener('click', () => { document.getElementById('refund-modal').style.display = 'none'; });
            document.getElementById('close-confirm-modal').addEventListener('click', () => { document.getElementById('confirm-received-modal').style.display = 'none'; });
            document.getElementById('cancel-confirm-btn').addEventListener('click', () => { document.getElementById('confirm-received-modal').style.display = 'none'; });
            document.getElementById('close-order-detail-modal').addEventListener('click', () => { document.getElementById('order-detail-modal').style.display = 'none'; });
            document.getElementById('close-invoice-modal').addEventListener('click', () => { document.getElementById('invoice-modal').style.display = 'none'; });

            // Initialize Help Guide
            function initHelpGuide() {
                const searchInput = document.getElementById('help-guide-search');
                const helpItems = document.querySelectorAll('.help-item-title');
                const sections = document.querySelectorAll('.help-section');

                // Toggle help items
                helpItems.forEach(title => {
                    title.addEventListener('click', function() {
                        const content = this.nextElementSibling;
                        const icon = this.querySelector('i');
                        const isExpanded = content.classList.contains('expanded');
                        
                        // Close other items in the same section
                        const sectionItems = this.closest('.help-section-content').querySelectorAll('.help-item-content');
                        const sectionTitles = this.closest('.help-section-content').querySelectorAll('.help-item-title');
                        sectionItems.forEach(item => item.classList.remove('expanded'));
                        sectionTitles.forEach(t => t.querySelector('i').style.transform = 'rotate(0deg)');
                        
                        // Toggle current item
                        if (!isExpanded) {
                            content.classList.add('expanded');
                            icon.style.transform = 'rotate(90deg)';
                        }
                    });
                });

                // Search functionality
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const container = document.getElementById('help-sections-container');
                        let hasResults = false;

                        sections.forEach(section => {
                            const title = section.querySelector('.help-section-header h3').textContent.toLowerCase();
                            const items = section.querySelectorAll('.help-item-title');
                            let sectionHasMatch = title.includes(searchTerm);
                            let itemsWithMatch = 0;

                            items.forEach(item => {
                                const itemText = item.textContent.toLowerCase();
                                if (itemText.includes(searchTerm) || searchTerm === '') {
                                    item.closest('.help-item').style.display = 'block';
                                    itemsWithMatch++;
                                    sectionHasMatch = true;
                                } else {
                                    item.closest('.help-item').style.display = 'none';
                                }
                            });

                            // Show/hide section
                            if (sectionHasMatch && itemsWithMatch > 0) {
                                section.style.display = 'block';
                                hasResults = true;
                            } else if (!searchTerm) {
                                section.style.display = 'block';
                                items.forEach(item => item.closest('.help-item').style.display = 'block');
                                hasResults = true;
                            } else {
                                section.style.display = 'none';
                            }
                        });

                        // Show no results message
                        let noResultsDiv = container.querySelector('.help-no-results');
                        if (!hasResults && searchTerm) {
                            if (!noResultsDiv) {
                                noResultsDiv = document.createElement('div');
                                noResultsDiv.className = 'help-no-results';
                                noResultsDiv.innerHTML = '<i class="fas fa-search"></i><p>Tidak ada hasil yang ditemukan</p>';
                                container.appendChild(noResultsDiv);
                            }
                            noResultsDiv.style.display = 'block';
                        } else if (noResultsDiv) {
                            noResultsDiv.style.display = 'none';
                        }
                    });
                }
            }

            initHelpGuide();

            function updateUserActivity() { fetch('api_update_activity.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } }).catch(() => {}); }
            updateUserActivity();
            setInterval(updateUserActivity, 5 * 60 * 1000);
            document.addEventListener('click', updateUserActivity);
            document.addEventListener('keypress', updateUserActivity);
        });
    </script>
</body>
</html>