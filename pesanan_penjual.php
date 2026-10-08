<?php
/**
* Halaman Pesanan Penjual - Kelola pesanan dari pembeli
*/
session_start();
include 'koneksi.php';
require_once 'notificationsHelper.php';

// Check authentication
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penjual') {
    header('Location: login.php');
    exit;
}

$id_penjual = $_SESSION['id_user'];
$user_name = $_SESSION['nama'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'semua';

// Get user data untuk avatar
$user_data = [];
$stmt_user = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_user, "i", $id_penjual);
mysqli_stmt_execute($stmt_user);
$user_query = mysqli_stmt_get_result($stmt_user);
$user_data = mysqli_fetch_assoc($user_query);
mysqli_stmt_close($stmt_user);

// ===== NOTIFIKASI - GUNAKAN HELPER YANG STANDARDIZED =====
$notifications = getNotifications($conn, $id_penjual, 'penjual');

// Get counts for each status
$status_counts = [];
$statuses = ['menunggu_pembayaran', 'dibayar', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak', 'refund'];
foreach ($statuses as $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_penjual = ? AND status = ?");
    $stmt->bind_param("is", $id_penjual, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $status_counts[$status] = $row['count'] ?? 0;
    $stmt->close();
}

// Get total pending (menunggu verifikasi/approval)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_penjual = ? AND status IN ('dibayar', 'menunggu_verifikasi')");
$stmt->bind_param("i", $id_penjual);
$stmt->execute();
$result = $stmt->get_result();
$pending_row = $result->fetch_assoc();
$pending_count = $pending_row['count'] ?? 0;
$stmt->close();

// Tutup koneksi
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Pesanan Masuk - Pustaka Aruna</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-width: 250px;
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
            --dark: #1e293b;
            --light: #ffffff;
            --accent: #3b82f6;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', Arial, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            background-image: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%232563eb" stroke-width="0.5" opacity="0.05"/></svg>');
        }
        
        .vintage-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 80%, rgba(37, 99, 235, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(100, 116, 139, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }
        
        /* ========== NAVBAR ========== */
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            padding: 1rem 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--primary-light);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--light);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 1px;
        }
        
        .logo i {
            color: var(--primary-light);
            font-size: 2rem;
        }
        
        .search-box {
            flex: 1;
            max-width: 500px;
            margin: 0 30px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid var(--border);
            border-radius: 30px;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: var(--surface);
            color: var(--text-primary);
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .user-area {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notification-bell {
            position: relative;
            font-size: 1.3rem;
            color: var(--light);
            cursor: pointer;
            transition: transform 0.2s;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.15);
        }
        
        .notification-bell:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.1);
        }
        
        .notification-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--error);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            width: 350px;
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            display: none;
            z-index: 1001;
        }
        
        .notification-dropdown.show {
            display: block;
            animation: slideDown 0.2s ease;
        }
        
        .notification-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .notification-header a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .notification-header a:hover {
            text-decoration: underline;
        }
        
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: var(--background);
        }
        
        .notification-item.unread {
            background: var(--primary-light);
        }
        
        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.875rem;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .notification-message {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        .notification-footer {
            padding: 12px 16px;
            text-align: center;
            background: var(--background);
            border-top: 1px solid var(--border);
        }
        
        .notification-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .user-profile:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: white;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info h4 {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--light);
        }
        
        .user-info p {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .online-status {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            margin-left: 4px;
        }
        
        /* Sidebar (Penjual Produk style) */
        .main-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 0;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            background: var(--surface);
            color: var(--text-primary);
            padding: 2rem 0;
            height: calc(100vh - 70px);
            overflow-y: auto;
            box-shadow: var(--shadow-sm);
            border-right: 1px solid var(--border);
            position: sticky;
            top: 70px;
        }

        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }

        .sidebar-header h3 {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .sidebar-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            padding: 0 1.5rem;
        }

        .sidebar-menu {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .sidebar-item {
            margin: 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            font-size: 0.9375rem;
            font-weight: 500;
        }

        .sidebar-link:hover {
            background: var(--primary-light);
            color: var(--primary);
            padding-left: 1.8rem;
        }

        .sidebar-link.active {
            background: var(--primary-light);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 600;
        }

        .sidebar-link i {
            width: 20px;
            font-size: 1rem;
            color: var(--text-secondary);
        }

        .sidebar-link.active i {
            color: var(--primary);
        }

        .sidebar-badge {
            background: var(--error);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: auto;
        }

        .content {
            padding: 2rem;
            overflow-y: auto;
        }

        /* ========== HEADER ========== */
        .header {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.85), rgba(30, 64, 175, 0.85));
            color: var(--light);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(59, 130, 246, 0.2) 0%, transparent 50%);
        }
        
        .page-title h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        
        .page-title p {
            font-size: 1rem;
            opacity: 0.9;
            font-style: italic;
            position: relative;
            z-index: 1;
        }
        
        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.85));
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid rgba(37, 99, 235, 0.15);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%232563eb" stroke-width="0.5" opacity="0.05"/></svg>');
            pointer-events: none;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            position: relative;
            z-index: 1;
        }
        
        .stat-icon.pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .stat-icon.approved {
            background: linear-gradient(135deg, var(--success), #16a34a);
        }
        
        .stat-icon.shipped {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
        }
        
        .stat-icon.completed {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        
        .stat-content {
            position: relative;
            z-index: 1;
        }
        
        .stat-content h3 {
            font-size: 0.9rem;
            color: var(--text-secondary);
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .stat-content .number {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* ========== FILTERS ========== */
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.85));
            border-radius: var(--radius-lg);
            border: 1px solid rgba(37, 99, 235, 0.15);
        }
        
        .filter-btn {
            padding: 10px 20px;
            border: 2px solid rgba(37, 99, 235, 0.25);
            background: rgba(248, 250, 252, 0.8);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: var(--light);
            border-color: var(--primary);
        }
        
        /* ========== ORDERS TABLE ========== */
        .orders-table {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.85));
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(37, 99, 235, 0.15);
            position: relative;
        }
        
        .orders-table::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%232563eb" stroke-width="0.5" opacity="0.05"/></svg>');
            pointer-events: none;
        }
        
        .table-header {
            padding: 1.5rem;
            background: rgba(37, 99, 235, 0.08);
            border-bottom: 2px solid rgba(37, 99, 235, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .table-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            color: var(--primary);
        }
        
        .search-box-table {
            position: relative;
            width: 300px;
        }
        
        .search-box-table input {
            width: 100%;
            padding: 10px 15px 10px 45px;
            border: 2px solid var(--border);
            border-radius: 25px;
            font-size: 0.9rem;
            background: var(--surface);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        
        .search-box-table input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .search-box-table i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            opacity: 0.7;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            position: relative;
            z-index: 1;
        }
        
        thead {
            background: rgba(37, 99, 235, 0.05);
        }
        
        th {
            padding: 1.2rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            border-bottom: 2px solid rgba(37, 99, 235, 0.2);
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        td {
            padding: 1.2rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        
        tbody tr {
            transition: all 0.3s;
        }
        
        tbody tr:hover {
            background: rgba(37, 99, 235, 0.05);
        }
        
        .order-id {
            font-weight: 700;
            color: var(--primary);
            font-family: 'Playfair Display', serif;
        }
        
        .buyer-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .buyer-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .buyer-email {
            font-size: 0.8rem;
            color: var(--text-secondary);
            opacity: 0.7;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }
        
        .status-menunggu_pembayaran {
            background: rgba(245, 158, 11, 0.2);
            color: #92400e;
            border-color: #f59e0b;
        }
        
        .status-dibayar, .status-menunggu_verifikasi {
            background: rgba(16, 185, 129, 0.2);
            color: #065f46;
            border-color: #10b981;
            animation: pulse-approval 2s infinite;
        }
        
        @keyframes pulse-approval {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
            }
        }
        
        .status-diproses {
            background: rgba(59, 130, 246, 0.2);
            color: #1e40af;
            border-color: #3b82f6;
        }
        
        .status-dikirim {
            background: rgba(139, 92, 246, 0.2);
            color: #3730a3;
            border-color: #8b5cf6;
        }
        
        .status-selesai {
            background: rgba(34, 197, 94, 0.2);
            color: #166534;
            border-color: #22c55e;
        }
        
        .status-dibatalkan {
            background: rgba(107, 114, 128, 0.2);
            color: #374151;
            border-color: #6b7280;
        }
        
        .status-ditolak {
            background: rgba(239, 68, 68, 0.2);
            color: #7f1d1d;
            border-color: #ef4444;
        }
        
        .status-refund {
            background: rgba(243, 156, 18, 0.2);
            color: #b45309;
            border-color: #f59e0b;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 8px 15px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid transparent;
        }
        
        .btn-view {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border-color: var(--primary);
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, var(--primary-hover), var(--primary));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-invoice {
            background: linear-gradient(135deg, #6f5ae0, #5a46c8);
            color: white;
            border-color: #6f5ae0;
        }
        
        .btn-invoice:hover {
            background: linear-gradient(135deg, #5a46c8, #6f5ae0);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-cash {
            background: linear-gradient(135deg, #DAA520, #FFD700);
            color: white;
            border-color: #DAA520;
        }
        
        .btn-cash:hover {
            background: linear-gradient(135deg, #FFD700, #DAA520);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-approve {
            background: linear-gradient(135deg, var(--success), #16a34a);
            color: white;
            border-color: var(--success);
            animation: pulse-button 2s infinite;
        }
        
        @keyframes pulse-button {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 139, 34, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(34, 139, 34, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(34, 139, 34, 0);
            }
        }
        
        .btn-approve:hover {
            background: linear-gradient(135deg, #16a34a, var(--success));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            animation: none;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #DC143C, #FF4500);
            color: white;
            border-color: #DC143C;
        }
        
        .btn-reject:hover {
            background: linear-gradient(135deg, #FF4500, #DC143C);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-ship {
            background: linear-gradient(135deg, #4169E1, #1E90FF);
            color: white;
            border-color: #4169E1;
        }
        
        .btn-ship:hover {
            background: linear-gradient(135deg, #1E90FF, #4169E1);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #6B7280, #4B5563);
            color: white;
            border-color: #6B7280;
        }
        
        .btn-delete:hover {
            background: linear-gradient(135deg, #4B5563, #6B7280);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-refund {
            background: linear-gradient(135deg, #F39C12, #D68910);
            color: white;
            border-color: #F39C12;
        }
        
        .btn-refund:hover {
            background: linear-gradient(135deg, #D68910, #F39C12);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-proof {
            background: linear-gradient(135deg, #6f5ae0, #5a46c8);
            color: white;
            border-color: #6f5ae0;
            padding: 6px 10px;
        }
        
        .btn-proof:hover {
            background: linear-gradient(135deg, #5a46c8, #6f5ae0);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-primary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            border: 2px solid var(--border);
            box-shadow: var(--shadow-lg);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%232563eb" stroke-width="0.5" opacity="0.05"/></svg>');
            pointer-events: none;
        }
        
        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(37, 99, 235, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
            z-index: 1;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .modal-header h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            border: 1px solid rgba(37, 99, 235, 0.3);
        }
        
        .modal-close:hover {
            background: rgba(37, 99, 235, 0.1);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
            flex: 1;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(37, 99, 235, 0.3);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            background: rgba(255, 255, 255, 0.8);
            color: var(--text-primary);
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-control:read-only {
            background-color: rgba(37, 99, 235, 0.05);
            border-color: rgba(37, 99, 235, 0.15);
            color: rgba(30, 41, 59, 0.8);
            cursor: not-allowed;
        }
        
        .form-control:read-only:focus {
            box-shadow: none;
            border-color: rgba(37, 99, 235, 0.2);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(37, 99, 235, 0.3);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            position: relative;
            z-index: 1001;
            background: white;
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-hover), var(--primary));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid rgba(37, 99, 235, 0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(37, 99, 235, 0.1);
            border-color: var(--primary);
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid transparent;
            position: relative;
            z-index: 1;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: #166534;
            border-color: var(--success);
        }
        
        .alert-error {
            background: var(--error-bg);
            color: #7f1d1d;
            border-color: var(--error);
        }
        
        .loading-spinner {
            text-align: center;
            padding: 2rem;
        }
        
        .fa-spinner {
            color: var(--primary);
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: rotate(0); }
            25% { transform: rotate(15deg); }
            75% { transform: rotate(-15deg); }
        }
        
        .shake {
            animation: shake 0.5s ease;
        }
        
        .sidebar::-webkit-scrollbar,
        .main-content::-webkit-scrollbar,
        .notification-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track,
        .main-content::-webkit-scrollbar-track,
        .notification-list::-webkit-scrollbar-track {
            background: var(--background);
        }
        
        .sidebar::-webkit-scrollbar-thumb,
        .main-content::-webkit-scrollbar-thumb,
        .notification-list::-webkit-scrollbar-thumb {
            background: var(--secondary);
            border-radius: 3px;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .sidebar-logo-text,
            .sidebar .sidebar-user-name,
            .sidebar .sidebar-user-role,
            .sidebar .sidebar-nav-text {
                display: none;
            }
            
            .sidebar .sidebar-header,
            .sidebar .sidebar-user-profile {
                padding: 16px;
            }
            
            .sidebar .sidebar-avatar {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .sidebar .sidebar-nav-item {
                padding: 12px;
                justify-content: center;
            }
            
            .sidebar .sidebar-nav-item .sidebar-badge {
                position: absolute;
                top: 8px;
                right: 8px;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .search-box-table {
                width: 100%;
            }
            
            .filters {
                justify-content: center;
            }
            
            .filter-btn {
                flex: 1;
                min-width: 120px;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .navbar {
                flex-wrap: wrap;
                padding: 1rem;
            }
            
            .search-box {
                order: 3;
                width: 100%;
                margin: 15px 0 0;
                max-width: 100%;
            }
            
            .user-info {
                display: none;
            }
            
            .notification-dropdown {
                width: 280px;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .modal-body {
                padding: 1.5rem;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 10px;
            }
        }
        
        /* ========== INVOICE RECEIPT STYLE (KARCIS/STRUK) ========== */
        .invoice-receipt {
            background: white;
            padding: 0;
            max-width: 400px;
            margin: 0 auto;
            font-family: 'Share Tech Mono', monospace;
            border: 2px solid var(--primary);
            border-radius: var(--radius);
        }
        
        .invoice-receipt-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            padding: 20px;
            text-align: center;
            border-bottom: 3px dashed var(--primary-light);
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
        
        .invoice-info-row:last-child {
            border-bottom: none;
        }
        
        .invoice-info-label {
            color: var(--primary);
            font-weight: 600;
        }
        
        .invoice-info-value {
            color: var(--text-primary);
            text-align: right;
        }
        
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
        
        .invoice-item-name {
            flex: 1;
            color: var(--text-primary);
        }
        
        .invoice-item-qty {
            width: 50px;
            text-align: center;
            color: var(--primary);
        }
        
        .invoice-item-price {
            width: 100px;
            text-align: right;
            color: var(--text-primary);
        }
        
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
        
        .invoice-footer p {
            margin: 5px 0;
        }
        
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
        
        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            #invoiceContent, #invoiceContent * {
                visibility: visible;
            }
            
            #invoiceContent {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .modal-footer, .modal-header {
                display: none !important;
            }
            
            .invoice-receipt {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>
    <!-- Vintage overlay -->
    <div class="vintage-overlay"></div>
    
    <!-- ========== NAVBAR ========== -->
    <nav class="navbar">
        <div class="logo-container">
            <a href="penjual_dashboard.php" class="logo">
                <i class="fas fa-book-open"></i>
                <span>Pustaka Aruna</span>
            </a>
        </div>
        
        <div class="search-box">
            <input type="text" placeholder="Cari pesanan..." id="navbarSearch">
        </div>
        
        <div class="user-area">
            <div class="notification-bell" id="notificationBell">
                <i class="fas fa-bell"></i>
                <span class="notification-count" id="notificationCount"><?php echo $notifications['total']; ?></span>
            </div>
            
            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h3>Notifikasi</h3>
                    <a href="#" id="markAllRead">Tandai semua dibaca</a>
                </div>
                <div class="notification-list" id="notificationList">
                    <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Memuat notifikasi...</p>
                    </div>
                </div>
                <div class="notification-footer">
                    <a href="penjual_notifikasi.php">Lihat Semua Notifikasi</a>
                </div>
            </div>
            
            <div class="user-profile" onclick="toggleUserMenu()">
                <div class="user-avatar">
                    <?php
                    $avatar_path = !empty($user_data['avatar']) ? 'uploads/avatars/' . $user_data['avatar'] : '';
                    if (!empty($avatar_path) && file_exists($avatar_path)):
                    ?>
                        <img src="<?php echo $avatar_path . '?t=' . time(); ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($user_name); ?></h4>
                    <p>Penjual <span class="online-status"></span></p>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </nav>
    
    <!-- ========== MAIN CONTAINER ========== -->
    <div class="main-container">
        <!-- ========== SIDEBAR ========== -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>PENJUAL BUKU</h3>
                <p>@<?php echo htmlspecialchars($user_name); ?></p>
            </div>

            <div class="sidebar-title">MENU UTAMA</div>
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="penjual_dashboard.php" class="sidebar-link">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="penjual_produk.php" class="sidebar-link">
                        <i class="fas fa-book"></i> Produk Buku
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="pesanan_penjual.php" class="sidebar-link active">
                        <i class="fas fa-inbox"></i> Pesanan Masuk
                        <?php if ($notifications['order'] > 0): ?>
                            <span class="sidebar-badge"><?php echo $notifications['order']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="penjual_laporan.php" class="sidebar-link">
                        <i class="fas fa-chart-bar"></i> Laporan
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="penjual_chat.php" class="sidebar-link" id="sidebarChat">
                        <i class="fas fa-comments"></i> Chat
                        <?php if ($notifications['chat'] > 0): ?>
                            <span class="sidebar-badge"><?php echo $notifications['chat']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <div class="sidebar-title" style="margin-top: 2rem;">AKUN SAYA</div>
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="penjual_account.php" class="sidebar-link">
                        <i class="fas fa-user-circle"></i> Profil Saya
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="logout.php" class="sidebar-link">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="help.php" class="sidebar-link">
                        <i class="fas fa-question-circle"></i> Bantuan
                    </a>
                </li>
            </ul>
        </aside>

        <!-- ========== MAIN CONTENT ========== -->
        <main class="content">
            <!-- Header -->
            <div class="header">
                <div class="page-title">
                    <h1><i class="fas fa-inbox"></i> Pesanan Masuk</h1>
                    <p>"Kelola dan pantau semua pesanan dari pembeli"</p>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Menunggu Verifikasi</h3>
                        <div class="number" id="pendingCount"><?php echo $pending_count; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon approved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Disetujui</h3>
                        <div class="number"><?php echo $status_counts['diproses'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon shipped">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Dikirim</h3>
                        <div class="number"><?php echo $status_counts['dikirim'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Selesai</h3>
                        <div class="number"><?php echo $status_counts['selesai'] ?? 0; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <button class="filter-btn <?php echo ($filter === 'semua') ? 'active' : ''; ?>" onclick="filterOrders('semua')">
                    <i class="fas fa-list"></i> Semua
                </button>
                <button class="filter-btn <?php echo ($filter === 'menunggu_pembayaran') ? 'active' : ''; ?>" onclick="filterOrders('menunggu_pembayaran')">
                    <i class="fas fa-hourglass-half"></i> Menunggu Pembayaran
                </button>
                <button class="filter-btn <?php echo ($filter === 'dibayar' || $filter === 'menunggu_verifikasi') ? 'active' : ''; ?>" onclick="filterOrders('dibayar')">
                    <i class="fas fa-credit-card"></i> Perlu Verifikasi
                </button>
                <button class="filter-btn <?php echo ($filter === 'diproses') ? 'active' : ''; ?>" onclick="filterOrders('diproses')">
                    <i class="fas fa-cog"></i> Sedang Diproses
                </button>
                <button class="filter-btn <?php echo ($filter === 'dikirim') ? 'active' : ''; ?>" onclick="filterOrders('dikirim')">
                    <i class="fas fa-truck"></i> Dikirim
                </button>
                <button class="filter-btn <?php echo ($filter === 'selesai') ? 'active' : ''; ?>" onclick="filterOrders('selesai')">
                    <i class="fas fa-check"></i> Selesai
                </button>
                <button class="filter-btn <?php echo ($filter === 'ditolak') ? 'active' : ''; ?>" onclick="filterOrders('ditolak')">
                    <i class="fas fa-times"></i> Ditolak
                </button>
                <button class="filter-btn <?php echo ($filter === 'refund') ? 'active' : ''; ?>" onclick="filterOrders('refund')">
                    <i class="fas fa-undo"></i> Refund
                </button>
            </div>
            
            <!-- Orders Table -->
            <div class="orders-table">
                <div class="table-header">
                    <h2>Daftar Pesanan</h2>
                    <div class="search-box-table">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Cari pesanan...">
                    </div>
                </div>
                <div id="ordersContainer">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Memuat pesanan...
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modals (Detail, Approve, Reject, Ship, Refund, Proof, Cash, Invoice) -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detail Pesanan</h2>
                <button class="modal-close" onclick="closeModal('detailModal')">&times;</button>
            </div>
            <div class="modal-body" id="detailContent"></div>
        </div>
    </div>
    
    <!-- Invoice Modal (RECEIPT/KARCIS STYLE) -->
    <div class="modal" id="invoiceModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="fas fa-receipt"></i> Invoice / Struk</h2>
                <button class="modal-close" onclick="closeModal('invoiceModal')">&times;</button>
            </div>
            <div class="modal-body" id="invoiceContent" style="padding: 0;">
                <!-- Invoice receipt will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('invoiceModal')">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="printInvoice()">
                    <i class="fas fa-print"></i> Cetak Invoice
                </button>
            </div>
        </div>
    </div>
    
    <!-- Approve Modal -->
    <div class="modal" id="approveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Setujui Pembayaran</h2>
                <button class="modal-close" onclick="closeModal('approveModal')">&times;</button>
            </div>
            <form onsubmit="approvePayment(event)">
                <div class="modal-body">
                    <div id="approveAlertContainer"></div>
                    <div style="background: rgba(34, 139, 34, 0.1); border: 2px solid #228B22; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #228B22;"><strong><i class="fas fa-info-circle"></i> Informasi:</strong></p>
                        <p style="margin: 8px 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                            Dengan menyetujui pembayaran, status pesanan akan berubah menjadi <strong>SEDANG DIPROSES</strong>.
                        </p>
                    </div>
                    <input type="hidden" id="approveOrderId">
                    <div class="form-group">
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea class="form-control" id="approveNote" placeholder="Catatan untuk pembeli..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Setujui Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Tolak Pembayaran</h2>
                <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
            </div>
            <form onsubmit="rejectPayment(event)">
                <div class="modal-body">
                    <div id="rejectAlertContainer"></div>
                    <div style="background: rgba(220, 20, 60, 0.1); border: 2px solid #DC143C; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #DC143C;"><strong><i class="fas fa-exclamation-circle"></i> Peringatan:</strong></p>
                        <p style="margin: 8px 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                            Menolak pembayaran akan membatalkan pesanan.
                        </p>
                    </div>
                    <input type="hidden" id="rejectOrderId">
                    <div class="form-group">
                        <label class="form-label">Alasan Penolakan <span style="color: #DC143C;">*</span></label>
                        <textarea class="form-control" id="rejectReason" required placeholder="Jelaskan alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-times"></i> Tolak Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Ship Modal -->
    <div class="modal" id="shipModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Kirim Pesanan</h2>
                <button class="modal-close" onclick="closeModal('shipModal')">&times;</button>
            </div>
            <form onsubmit="shipOrder(event)">
                <div class="modal-body">
                    <div id="shipAlertContainer"></div>
                    <div style="background: rgba(65, 105, 225, 0.1); border: 2px solid #4169E1; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #4169E1;"><strong><i class="fas fa-info-circle"></i> Informasi Pengiriman:</strong></p>
                        <p style="margin: 8px 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                            Kurir yang dipilih pembeli: <strong id="selectedKurir">-</strong><br>
                            Masukkan nomor resi sesuai kurir tersebut. Status akan berubah menjadi <strong>DIKIRIM</strong>.<br>
                            Format No. Resi: <strong id="selectedKurirFormat" style="color: #2563eb;">-</strong>
                        </p>
                    </div>
                    <input type="hidden" id="shipOrderId">
                    <input type="hidden" id="shipOrderKurir">
                    <div class="form-group">
                        <label class="form-label">Nomor Resi <span style="color: #DC143C;">*</span></label>
                        <input type="text" class="form-control" id="trackingNumber" required placeholder="Masukkan nomor resi...">
                        <small class="form-text text-muted" id="resiFormatHint">Masukkan nomor resi sesuai kurir yang dipilih pembeli</small>
                        <div id="resiValidationError" style="color: #DC143C; font-size: 0.85rem; margin-top: 5px; display: none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catatan Pengiriman</label>
                        <textarea class="form-control" id="shipNote" placeholder="Link tracking, estimasi waktu..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('shipModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-truck"></i> Kirim Pesanan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Refund Modal -->
    <div class="modal" id="refundModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Proses Refund</h2>
                <button class="modal-close" onclick="closeModal('refundModal')">&times;</button>
            </div>
            <form onsubmit="refundOrder(event)">
                <div class="modal-body">
                    <div id="refundAlertContainer"></div>
                    <input type="hidden" id="refundOrderId">
                    <div style="background: rgba(243, 156, 18, 0.1); border: 2px solid #F39C12; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <p style="margin: 0; color: var(--primary);"><strong><i class="fas fa-info-circle"></i> Perhatian:</strong></p>
                        <p style="margin: 5px 0 0; color: var(--text-secondary);">Refund akan memproses pengembalian dana.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alasan Refund</label>
                        <textarea class="form-control" id="refundReason" required placeholder="Alasan refund..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catatan / Instruksi Refund</label>
                        <textarea class="form-control" id="refundNote" placeholder="Informasi tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('refundModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-undo"></i> Proses Refund
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Proof Modal -->
    <div class="modal" id="proofModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2>Bukti Pembayaran</h2>
                <button class="modal-close" onclick="closeModal('proofModal')">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center; padding: 2rem;">
                <img id="proofImage" src="" alt="Bukti Pembayaran" style="max-width: 100%; max-height: 600px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('proofModal')">Tutup</button>
                <a id="proofDownload" href="#" class="btn btn-primary" download>
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>
    </div>
    
    <!-- Cash Modal -->
    <div class="modal" id="cashModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-cash-register"></i> Transaksi Tunai</h2>
                <button class="modal-close" onclick="closeModal('cashModal')">&times;</button>
            </div>
            <form id="cashForm" onsubmit="submitCash(event)">
                <div class="modal-body">
                    <input type="hidden" id="cashOrderId" value="">
                    <div class="form-group">
                        <label class="form-label">Total Harga</label>
                        <div id="cashTotal" class="form-control" readonly style="background:#f5f5f5; font-weight: bold; color: var(--text-primary);"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bayar (Rp)</label>
                        <input type="number" id="cashBayar" class="form-control" placeholder="0" min="0" oninput="calculateChange()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kembalian</label>
                        <div id="cashKembalian" class="form-control" readonly style="background:#f0fdf4; color: var(--success); font-weight: bold;">Rp 0</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catatan (opsional)</label>
                        <input type="text" id="cashCatatan" class="form-control" placeholder="Mis. bayar di kasir 1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('cashModal')">Batal</button>
                    <button type="submit" id="btnSelesaiInvoice" class="btn btn-primary">
                        <i class="fas fa-check"></i> Selesai & Cetak Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let allOrders = [];
        let currentFilter = '<?php echo $filter; ?>';
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : text;
            return div.innerHTML;
        }
        
        // ===== WAIT FOR DOM TO BE FULLY LOADED =====
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Loaded, initializing page...');
            
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationCount = document.getElementById('notificationCount');
            const notificationList = document.getElementById('notificationList');
            const searchInput = document.getElementById('searchInput');
            const ordersContainer = document.getElementById('ordersContainer');
            
            // Validate elements exist
            if (!ordersContainer) {
                console.error('ordersContainer not found!');
                return;
            }
            
            // Load orders immediately on page load
            loadOrders(currentFilter);
            
            // Search functionality
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    filterTable(e.target.value);
                });
            }
            
            // Notification system
            if (notificationBell && notificationDropdown && notificationCount && notificationList) {
                notificationBell.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    notificationDropdown.classList.toggle('show');
                    if (notificationDropdown.classList.contains('show')) {
                        loadNotifications();
                        markNotificationsAsRead();
                    }
                });
                
                document.addEventListener('click', function(e) {
                    if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                        notificationDropdown.classList.remove('show');
                    }
                });
                
                notificationDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            
            // Auto-refresh every 30 seconds
            setInterval(() => {
                loadOrders(currentFilter);
            }, 30000);
            
            console.log('Page initialization complete');
        });
        
        function loadOrders(filter = 'semua') {
            const container = document.getElementById('ordersContainer');
            if (!container) {
                console.error('ordersContainer not found in loadOrders');
                return;
            }
            
            console.log('Loading orders with filter:', filter);
            container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Memuat pesanan...</div>';
            
            let url;
            if (filter === 'dibayar') {
                url = 'api_pesanan_penjual.php?action=list&filter=dibayar,menunggu_verifikasi';
            } else {
                url = filter === 'semua' ? 'api_pesanan_penjual.php?action=list' : `api_pesanan_penjual.php?action=list&filter=${filter}`;
            }
            
            console.log('Fetching from URL:', url);
            
            fetch(url, { credentials: 'same-origin' })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    if (data.success && data.data) {
                        allOrders = data.data;
                        console.log('Orders loaded:', allOrders.length);
                        renderOrders(allOrders);
                    } else {
                        console.error('API returned error:', data);
                        container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>Tidak ada pesanan</h3><p>' + (data.message || 'Belum ada pesanan yang masuk') + '</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error memuat pesanan</h3><p>' + error.message + '</p><button onclick="loadOrders(\'' + filter + '\')" class="btn btn-primary" style="margin-top: 15px;"><i class="fas fa-sync"></i> Coba Lagi</button></div>';
                });
        }
        
        function renderOrders(orders) {
            const container = document.getElementById('ordersContainer');
            if (!container) return;
            
            if (!orders || orders.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>Tidak ada pesanan</h3><p>Belum ada pesanan dengan status ini</p></div>';
                return;
            }
            
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>ID Pesanan</th>
                            <th>Pembeli</th>
                            <th>Produk</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            orders.forEach(order => {
                let statusClass = '';
                if (order.status === 'dibayar' || order.status === 'menunggu_verifikasi') {
                    statusClass = 'status-dibayar';
                } else {
                    statusClass = `status-${order.status.replace('_', '-')}`;
                }
                
                let statusText = order.status.toUpperCase().replace('_', ' ');
                if (order.status === 'menunggu_verifikasi') {
                    statusText = 'MENUNGGU VERIFIKASI';
                } else if (order.status === 'dibayar') {
                    statusText = 'SUDAH DIBAYAR';
                }
                
                const tanggal = new Date(order.created_at).toLocaleDateString('id-ID');
                
                html += `
                    <tr>
                        <td><span class="order-id">#${order.id_pesanan}</span></td>
                        <td>
                            <div class="buyer-info">
                                <div class="buyer-name">${escapeHtml(order.nama_pembeli)}</div>
                                <div class="buyer-email">${escapeHtml(order.email_pembeli)}</div>
                            </div>
                        </td>
                        <td>${escapeHtml(order.nama_produk || 'Produk')}</td>
                        <td>${order.quantity}</td>
                        <td>Rp ${parseInt(order.total_harga).toLocaleString('id-ID')}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${tanggal}</td>
                        <td>
                            <div class="action-buttons">
                                ${getActionButtons(order)}
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            container.innerHTML = html;
            attachViewListeners();
        }
        
        function getActionButtons(order) {
            let buttons = '';
            const orderId = order.id_pesanan;
            const status = order.status;
            const buktiPembayaran = order.bukti_pembayaran || '';
            
            if (buktiPembayaran && status !== 'menunggu_pembayaran') {
                const imgPath = 'uploads/bukti_pembayaran/' + buktiPembayaran;
                buttons += `
                    <button class="btn-action btn-proof" onclick="showProofModal('${buktiPembayaran}', ${orderId})" title="Lihat Bukti Pembayaran" style="padding:6px;">
                        <img src="${imgPath}" alt="Bukti" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,0.06);">
                    </button>`;
            }
            
            // PERUBAHAN: Untuk metode pembayaran cash dan transfer, tampilkan tombol Invoice bukan View
            const transferMethods = ['tf_bank', 'transfer', 'tf'];
            const isInvoiceMethod = order.metode_pembayaran === 'cash' || transferMethods.includes(order.metode_pembayaran);

            if (isInvoiceMethod) {
                buttons += `<button class="btn-action btn-invoice" onclick="showInvoice(${orderId})"><i class="fas fa-receipt"></i> Invoice</button>`;
            } else {
                buttons += `<button class="btn-action btn-view" data-order-id="${orderId}"><i class="fas fa-eye"></i> View</button>`;
            }
            
            const statusLower = status.toLowerCase();
            
            if (statusLower === 'menunggu_pembayaran' && order.metode_pembayaran === 'cash') {
                buttons += `<button class="btn-action btn-cash" onclick="openCashModal(${orderId})"><i class="fas fa-cash-register"></i> Bayar</button>`;
            }
            
            if (statusLower === 'dibayar' || statusLower === 'menunggu_verifikasi') {
                buttons += `<button class="btn-action btn-approve" onclick="openApproveModal(${orderId})"><i class="fas fa-check"></i> Setujui</button>`;
                buttons += `<button class="btn-action btn-reject" onclick="openRejectModal(${orderId})"><i class="fas fa-times"></i> Tolak</button>`;
            } else if (statusLower === 'diproses' && order.metode_pembayaran !== 'cash') {
                buttons += `<button class="btn-action btn-ship" onclick="openShipModal(${orderId})"><i class="fas fa-truck"></i> Kirim</button>`;
            } else if (statusLower === 'ditolak') {
                buttons += `<button class="btn-action btn-refund" onclick="openRefundModal(${orderId})"><i class="fas fa-undo"></i> Refund</button>`;
            }
            
            return buttons;
        }
        
        function showProofModal(buktiPembayaran, orderId) {
            const proofImage = document.getElementById('proofImage');
            const proofDownload = document.getElementById('proofDownload');
            const imagePath = 'uploads/bukti_pembayaran/' + buktiPembayaran;
            
            proofImage.src = imagePath;
            proofDownload.href = imagePath;
            proofDownload.download = 'bukti_pembayaran_' + orderId + '.' + buktiPembayaran.split('.').pop();
            
            openModal('proofModal');
        }
        
        // ===== INVOICE FUNCTION (RECEIPT/KARCIS STYLE) =====
        function showInvoice(orderId) {
            const container = document.getElementById('invoiceContent');
            container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Memuat invoice...</div>';
            openModal('invoiceModal');
            
            fetch(`api_pesanan_penjual.php?action=detail&id=${orderId}`, { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.data) {
                        container.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Gagal memuat data invoice</div>';
                        return;
                    }
                    
                    const order = data.data;
                    const tanggal = new Date(order.created_at).toLocaleString('id-ID');
                    const statusClass = order.status === 'selesai' ? 'invoice-status-badge' : '';
                    const statusText = order.status.toUpperCase().replace('_', ' ');
                    
                    container.innerHTML = `
                        <div class="invoice-receipt">
                            <div class="invoice-receipt-header">
                                <h2><i class="fas fa-book-open"></i> PUSTAKA ARUNA</h2>
                                <p>Invoice / Struk Pembayaran</p>
                                <span class="invoice-payment-badge"><i class="fas fa-money-bill-wave"></i> ${order.metode_pembayaran === 'cash' ? 'CASH / TUNAI' : 'TRANSFER BANK'}</span>
                            </div>
                            <div class="invoice-receipt-body">
                                <div class="invoice-info-row">
                                    <span class="invoice-info-label">No. Invoice</span>
                                    <span class="invoice-info-value">#${String(order.id_pesanan).padStart(8, '0')}</span>
                                </div>
                                <div class="invoice-info-row">
                                    <span class="invoice-info-label">Tanggal</span>
                                    <span class="invoice-info-value">${tanggal}</span>
                                </div>
                                <div class="invoice-info-row">
                                    <span class="invoice-info-label">Pembeli</span>
                                    <span class="invoice-info-value">${escapeHtml(order.nama_pembeli)}</span>
                                </div>
                                <div class="invoice-info-row">
                                    <span class="invoice-info-label">Email</span>
                                    <span class="invoice-info-value">${escapeHtml(order.email_pembeli)}</span>
                                </div>
                                <div class="invoice-info-row">
                                    <span class="invoice-info-label">Status</span>
                                    <span class="invoice-info-value"><span class="${statusClass}">${statusText}</span></span>
                                </div>
                                <div class="invoice-items">
                                    <div class="invoice-item" style="border-bottom: 1px dashed #ddd; padding-bottom: 10px; margin-bottom: 10px;">
                                        <span class="invoice-item-name" style="font-weight: 600;">ITEM</span>
                                        <span class="invoice-item-qty" style="font-weight: 600;">QTY</span>
                                        <span class="invoice-item-price" style="font-weight: 600;">TOTAL</span>
                                    </div>
                                    <div class="invoice-item">
                                        <span class="invoice-item-name">${escapeHtml(order.nama_produk)}</span>
                                        <span class="invoice-item-qty">${order.quantity}</span>
                                        <span class="invoice-item-price">Rp ${parseInt(order.total_harga).toLocaleString('id-ID')}</span>
                                    </div>
                                </div>
                                <div class="invoice-total">
                                    <div class="invoice-total-row">
                                        <span>Subtotal</span>
                                        <span>Rp ${parseInt(order.total_harga).toLocaleString('id-ID')}</span>
                                    </div>
                                    <div class="invoice-total-row">
                                        <span>Diskon</span>
                                        <span>Rp 0</span>
                                    </div>
                                    <div class="invoice-total-row final">
                                        <span>TOTAL BAYAR</span>
                                        <span>Rp ${parseInt(order.total_harga).toLocaleString('id-ID')}</span>
                                    </div>
                                    ${order.metode_pembayaran === 'cash' ? `
                                    <div class="invoice-total-row" style="border-top: 1px dashed var(--border); padding-top: 10px; margin-top: 10px;">
                                        <span>Bayar</span>
                                        <span>Rp ${parseInt(order.bayar || order.total_harga).toLocaleString('id-ID')}</span>
                                    </div>
                                    <div class="invoice-total-row" style="color: var(--success); font-weight: 600;">
                                        <span>KEMBALIAN</span>
                                        <span>Rp ${parseInt((order.bayar || order.total_harga) - order.total_harga).toLocaleString('id-ID')}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="invoice-footer">
                                <p><i class="fas fa-heart"></i> Terima kasih telah berbelanja!</p>
                                <p>Pustaka Aruna - Menjaga Warisan Literasi</p>
                                <p style="font-size: 0.75rem; margin-top: 10px; opacity: 0.7;">Invoice ini sah dan dapat digunakan sebagai bukti transaksi</p>
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Error: ' + error.message + '</div>';
                });
        }
        
        function printInvoice() {
            const content = document.getElementById('invoiceContent').innerHTML;
            const win = window.open('', '_blank', 'width=500,height=700');
            win.document.write(`
                <html>
                <head>
                    <title>Invoice - Pustaka Aruna</title>
                    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
                    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                    <style>
                        body {
                            font-family: 'Share Tech Mono', monospace;
                            padding: 0;
                            margin: 0;
                        }
                        .invoice-receipt {
                            background: white;
                            padding: 0;
                            max-width: 400px;
                            margin: 0 auto;
                            border: 1px solid #ddd;
                        }
                        .invoice-receipt-header {
                            background: linear-gradient(135deg, #8B4513, #A0522D);
                            color: white;
                            padding: 20px;
                            text-align: center;
                            border-bottom: 3px dashed #D2B48C;
                        }
                        .invoice-receipt-header h2 {
                            font-family: 'Playfair Display', serif;
                            font-size: 1.5rem;
                            margin-bottom: 5px;
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
                            border-bottom: 1px dashed #ddd;
                            font-size: 0.9rem;
                        }
                        .invoice-info-row:last-child {
                            border-bottom: none;
                        }
                        .invoice-info-label {
                            color: #8B4513;
                            font-weight: 600;
                        }
                        .invoice-info-value {
                            color: #5D4037;
                            text-align: right;
                        }
                        .invoice-items {
                            margin: 20px 0;
                            border-top: 2px solid #8B4513;
                            border-bottom: 2px solid #8B4513;
                            padding: 15px 0;
                        }
                        .invoice-item {
                            display: flex;
                            justify-content: space-between;
                            padding: 8px 0;
                            font-size: 0.9rem;
                        }
                        .invoice-item-name {
                            flex: 1;
                            color: #5D4037;
                        }
                        .invoice-item-qty {
                            width: 50px;
                            text-align: center;
                            color: #8B4513;
                        }
                        .invoice-item-price {
                            width: 100px;
                            text-align: right;
                            color: #5D4037;
                        }
                        .invoice-total {
                            margin-top: 15px;
                            padding-top: 15px;
                            border-top: 2px dashed #8B4513;
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
                            color: #8B4513;
                            border-top: 2px solid #8B4513;
                            padding-top: 15px;
                            margin-top: 10px;
                        }
                        .invoice-footer {
                            text-align: center;
                            padding: 20px;
                            background: #f8f9fa;
                            border-top: 3px dashed #D2B48C;
                            font-size: 0.85rem;
                            color: #8B4513;
                        }
                        .invoice-footer p {
                            margin: 5px 0;
                        }
                        .invoice-payment-badge {
                            display: inline-block;
                            padding: 5px 15px;
                            background: linear-gradient(135deg, #DAA520, #FFD700);
                            color: white;
                            border-radius: 20px;
                            font-size: 0.8rem;
                            font-weight: 600;
                            margin-top: 10px;
                        }
                        .invoice-status-badge {
                            display: inline-block;
                            padding: 5px 15px;
                            background: rgba(34, 139, 34, 0.2);
                            color: #228B22;
                            border-radius: 20px;
                            font-size: 0.8rem;
                            font-weight: 600;
                            border: 1px solid #228B22;
                        }
                        @media print {
                            body {
                                padding: 0;
                                margin: 0;
                            }
                            .invoice-receipt {
                                box-shadow: none;
                                border: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${content}
                    <script>
                        window.onload = function() {
                            window.print();
                        }
                    <\/script>
                </body>
                </html>
            `);
            win.document.close();
        }
        
        function openCashModal(orderId) {
            fetch(`api_pesanan_penjual.php?action=detail&id=${orderId}`, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    
                    const order = data.data;
                    document.getElementById('cashOrderId').value = orderId;
                    document.getElementById('cashTotal').textContent = 'Rp ' + parseInt(order.total_harga).toLocaleString('id-ID');
                    document.getElementById('cashCatatan').value = '';
                    document.getElementById('cashBayar').value = '';
                    document.getElementById('cashKembalian').textContent = 'Rp 0';
                    document.getElementById('cashKembalian').style.color = 'var(--text-secondary)';
                    document.getElementById('cashKembalian').style.background = 'var(--surface)';
                    
                    openModal('cashModal');
                    
                    // Focus pada input bayar setelah modal terbuka
                    setTimeout(() => {
                        const bayarEl = document.getElementById('cashBayar');
                        if (bayarEl) {
                            bayarEl.focus();
                            bayarEl.select();
                        }
                        
                        // Pastikan tombol submit tidak disabled
                        const submitBtn = document.getElementById('btnSelesaiInvoice');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-check"></i> Selesai & Cetak Invoice';
                            submitBtn.style.cursor = 'pointer';
                            submitBtn.style.opacity = '1';
                        }
                    }, 100);
                });
        }
        
        function calculateChange() {
            const totalText = document.getElementById('cashTotal').textContent;
            const total = parseInt(totalText.replace(/[^\d]/g, ''));
            const bayar = parseInt(document.getElementById('cashBayar').value) || 0;
            const kembalian = bayar - total;
            const kembalianEl = document.getElementById('cashKembalian');
            
            if (kembalian >= 0) {
                kembalianEl.textContent = 'Rp ' + kembalian.toLocaleString('id-ID');
                kembalianEl.style.color = 'var(--success)';
                kembalianEl.style.background = '#f0fdf4';
            } else {
                kembalianEl.textContent = 'Kurang Rp ' + Math.abs(kembalian).toLocaleString('id-ID');
                kembalianEl.style.color = 'var(--error)';
                kembalianEl.style.background = '#fef2f2';
            }
        }
        
        function submitCash(e) {
            e.preventDefault();
            
            const orderId = document.getElementById('cashOrderId').value;
            const catatan = document.getElementById('cashCatatan').value;
            const bayarInput = document.getElementById('cashBayar');
            const bayar = parseInt(bayarInput.value) || 0;
            const total = parseInt(document.getElementById('cashTotal').textContent.replace(/[^\d]/g, ''));
            
            // Validasi input bayar
            if (!bayarInput.value || bayar <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Silakan masukkan nominal pembayaran!',
                    confirmButtonColor: '#2563eb'
                });
                bayarInput.focus();
                return;
            }
            
            if (bayar < total) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Uang Kurang',
                    text: 'Nominal bayar kurang dari total harga!',
                    confirmButtonColor: '#2563eb'
                });
                bayarInput.focus();
                return;
            }
            
            const kembalian = bayar - total;
            // Simpan informasi pembayaran dalam catatan
            const catatanLengkap = `PEMBAYARAN TUNAI - Bayar: Rp ${bayar.toLocaleString('id-ID')}, Kembalian: Rp ${kembalian.toLocaleString('id-ID')}${catatan ? ', Catatan: ' + catatan : ''}`;
            
            // Disable tombol submit untuk mencegah double submit
            const submitBtn = document.getElementById('btnSelesaiInvoice');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            }
            
            // PERBAIKAN: Hapus field 'bayar' dan 'kembalian' karena kolom tidak ada di database
            fetch('api_pesanan_penjual.php?action=update_status', {
                credentials: 'same-origin',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_pesanan: orderId,
                    status: 'selesai',
                    catatan: catatanLengkap
                    // HAPUS: bayar dan kembalian karena kolom tidak ada di database
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    // Tutup modal cash
                    closeModal('cashModal');
                    
                    // Reload orders
                    loadOrders(currentFilter);
                    
                    // Tampilkan invoice
                    setTimeout(() => {
                        showInvoice(orderId);
                    }, 500);
                    
                    // Tampilkan pesan sukses
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Transaksi tunai berhasil diproses. Invoice akan ditampilkan.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Gagal memproses tunai: ' + res.message,
                        confirmButtonColor: '#2563eb'
                    });
                    
                    // Re-enable tombol jika gagal
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-check"></i> Selesai & Cetak Invoice';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan: ' + err.message,
                    confirmButtonColor: '#2563eb'
                });
                
                // Re-enable tombol jika error
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Selesai & Cetak Invoice';
                }
            });
        }
        
        function filterOrders(filter) {
            currentFilter = filter;
            loadOrders(filter);
            
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = Array.from(document.querySelectorAll('.filter-btn')).find(btn => {
                const t = btn.textContent;
                if (filter === 'dibayar' || filter === 'menunggu_verifikasi') return t.includes('Perlu Verifikasi');
                if (filter === 'diproses') return t.includes('Sedang Diproses');
                if (filter === 'dikirim') return t.includes('Dikirim');
                if (filter === 'selesai') return t.includes('Selesai');
                if (filter === 'ditolak') return t.includes('Ditolak');
                if (filter === 'refund') return t.includes('Refund');
                if (filter === 'menunggu_pembayaran') return t.includes('Menunggu Pembayaran');
                return t.includes('Semua');
            });
            if (activeBtn) activeBtn.classList.add('active');
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('filter', filter);
            window.history.pushState({}, '', url);
        }
        
        function attachViewListeners() {
            document.querySelectorAll('.btn-view').forEach(btn => {
                btn.removeEventListener('click', handleViewClick);
                btn.addEventListener('click', handleViewClick);
            });
        }
        
        function handleViewClick(e) {
            const btn = e.currentTarget || e.target;
            const orderId = btn.getAttribute('data-order-id');
            
            if (!orderId) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'ID pesanan tidak ditemukan'
                });
                return;
            }
            
            const idNum = parseInt(orderId);
            if (isNaN(idNum)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'ID pesanan tidak valid'
                });
                return;
            }
            
            viewDetail(idNum);
        }
        
        function filterTable(searchTerm) {
            const filtered = allOrders.filter(order =>
                order.id_pesanan.toString().includes(searchTerm) ||
                order.nama_pembeli.toLowerCase().includes(searchTerm.toLowerCase()) ||
                order.email_pembeli.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (order.nama_produk && order.nama_produk.toLowerCase().includes(searchTerm.toLowerCase()))
            );
            renderOrders(filtered);
        }
        
        function viewDetail(orderId) {
            fetch(`api_pesanan_penjual.php?action=detail&id=${orderId}`, { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const order = data.data;
                        const tanggal = new Date(order.created_at).toLocaleDateString('id-ID', {
                            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                        });
                        
                        const fotoProduk = order.foto_produk ? `uploads/produk/${order.foto_produk}` : '';
                        
                        let html = `<div>`;
                        
                        if (fotoProduk) {
                            html += `<div class="form-group">
                                <label class="form-label">📦 Foto Produk</label>
                                <div style="padding: 15px; background: rgba(210, 180, 140, 0.1); border-radius: 8px; text-align: center;">
                                    <img src="${fotoProduk}" alt="Foto Produk" style="max-width: 100%; max-height: 300px; border-radius: 8px;">
                                </div>
                            </div>`;
                        }
                        
                        html += `<div class="form-group">
                            <label class="form-label">📚 Detail Produk</label>
                            <div style="padding: 15px; background: rgba(139, 69, 19, 0.05); border: 2px solid rgba(210, 180, 140, 0.3); border-radius: 8px;">
                                <div style="margin-bottom: 8px;"><strong style="color: var(--primary);">Nama:</strong> ${order.nama_produk || '-'}</div>
                                <div style="margin-bottom: 8px;"><strong style="color: var(--primary);">Harga Satuan:</strong> Rp ${order.harga ? parseInt(order.harga).toLocaleString('id-ID') : '-'}</div>
                                <div style="margin-bottom: 8px;"><strong style="color: var(--primary);">Jumlah:</strong> ${order.quantity} unit</div>
                            </div>
                        </div>`;
                        
                        html += `<div class="form-group">
                            <label class="form-label">ID Pesanan</label>
                            <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px; color: var(--primary); font-weight: 600;">#${order.id_pesanan}</div>
                        </div>`;
                        
                        html += `<div class="form-group">
                            <label class="form-label">Pembeli</label>
                            <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">${order.nama_pembeli} (${order.email_pembeli})</div>
                        </div>`;
                        
                        html += `<div class="form-group">
                            <label class="form-label">Total Harga</label>
                            <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px; font-weight: 600; color: var(--primary); font-size: 16px;">Rp ${parseInt(order.total_harga).toLocaleString('id-ID')}</div>
                        </div>`;
                        
                        html += `<div class="form-group">
                            <label class="form-label">Metode Pembayaran</label>
                            <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">${order.metode_pembayaran || '-'}</div>
                        </div>`;
                        
                        html += `<div class="form-group">
                            <label class="form-label">Status</label>
                            <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">
                                <span class="status-badge status-${order.status.replace('_', '-')}">${order.status.toUpperCase().replace('_', ' ')}</span>
                            </div>
                        </div>`;
                        
                        html += `<div class="form-group">
                            <label class="form-label">Alamat Pengiriman</label>
                            <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px; white-space: pre-wrap;">${order.alamat_pembeli || order.alamat_pengiriman || '-'}</div>
                        </div>`;
                        
                        html += `<div class="form-group">
                            <label class="form-label">Nomor Resi</label>
                            <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">${order.nomor_resi || '-'}</div>
                        </div>`;
                        
                        html += `<div class="form-group">
                            <label class="form-label">Tanggal Pesanan</label>
                            <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">${tanggal}</div>
                        </div>`;
                        
                        html += `</div>`;
                        
                        document.getElementById('detailContent').innerHTML = html;
                        openModal('detailModal');
                    } else {
                        alert('Error: ' + (data.message || 'Data tidak ditemukan'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                });
        }
        
        function openApproveModal(orderId) {
            document.getElementById('approveOrderId').value = orderId;
            document.getElementById('approveNote').value = '';
            document.getElementById('approveAlertContainer').innerHTML = '';
            openModal('approveModal');
        }
        
        function openRejectModal(orderId) {
            document.getElementById('rejectOrderId').value = orderId;
            document.getElementById('rejectReason').value = '';
            document.getElementById('rejectAlertContainer').innerHTML = '';
            openModal('rejectModal');
        }
        
        function openShipModal(orderId) {
            // Find the order data
            const order = allOrders.find(o => o.id_pesanan == orderId);
            document.getElementById('shipOrderId').value = orderId;
            document.getElementById('shipOrderKurir').value = order.kurir || '';
            document.getElementById('selectedKurir').textContent = order.kurir || 'Tidak dipilih';
            document.getElementById('trackingNumber').value = '';
            document.getElementById('shipNote').value = '';
            document.getElementById('shipAlertContainer').innerHTML = '';
            document.getElementById('resiValidationError').innerHTML = '';
            document.getElementById('resiValidationError').style.display = 'none';
            
            // Fetch format resi dari API
            if (order.kurir) {
                fetch('api_get_tarif.php?kurir=' + encodeURIComponent(order.kurir))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('selectedKurirFormat').textContent = data.data.format_resi || '-';
                            document.getElementById('resiFormatHint').textContent = 'Format: ' + data.data.format_resi;
                        }
                    })
                    .catch(error => console.error('Error fetching format:', error));
            } else {
                document.getElementById('selectedKurirFormat').textContent = '-';
            }
            
            openModal('shipModal');
        }
        
        function openRefundModal(orderId) {
            document.getElementById('refundOrderId').value = orderId;
            document.getElementById('refundReason').value = '';
            document.getElementById('refundNote').value = '';
            document.getElementById('refundAlertContainer').innerHTML = '';
            openModal('refundModal');
        }
        
        function approvePayment(event) {
            event.preventDefault();
            const orderId = document.getElementById('approveOrderId').value;
            const note = document.getElementById('approveNote').value.trim();
            const alertContainer = document.getElementById('approveAlertContainer');
            
            fetch('api_pesanan_penjual.php?action=update_status', {
                credentials: 'same-origin',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_pesanan: parseInt(orderId),
                    status: 'diproses',
                    catatan_penjual: note
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(alertContainer, 'success', 'Pembayaran disetujui. Status: SEDANG DIPROSES.');
                    setTimeout(() => {
                        closeModal('approveModal');
                        loadOrders(currentFilter);
                    }, 1500);
                } else {
                    showAlert(alertContainer, 'error', data.message || 'Gagal menyetujui pembayaran');
                }
            })
            .catch(error => {
                showAlert(alertContainer, 'error', 'Error: ' + error.message);
            });
        }
        
        function rejectPayment(event) {
            event.preventDefault();
            const orderId = document.getElementById('rejectOrderId').value;
            const alasan = document.getElementById('rejectReason').value;
            const alertContainer = document.getElementById('rejectAlertContainer');
            
            if (!alasan) {
                showAlert(alertContainer, 'error', 'Alasan penolakan wajib diisi.');
                return;
            }
            
            fetch('api_pesanan_penjual.php?action=reject_pembayaran', {
                credentials: 'same-origin',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_pesanan: parseInt(orderId),
                    alasan: alasan
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(alertContainer, 'success', data.message);
                    setTimeout(() => {
                        closeModal('rejectModal');
                        loadOrders(currentFilter);
                    }, 1500);
                } else {
                    showAlert(alertContainer, 'error', data.message || 'Gagal menolak pesanan');
                }
            })
            .catch(error => {
                showAlert(alertContainer, 'error', 'Error: ' + error.message);
            });
        }
        
        function validateTrackingNumber(kurir, trackingNumber) {
            // Validasi format nomor resi berdasarkan kurir
            const validations = {
                'JNE': { pattern: /^\d{8}$/, message: 'JNE: 8 digit (12345678)' },
                'JNT': { pattern: /^\d{10}$/, message: 'JNT: 10 digit (1012345678)' },
                'TIKI': { pattern: /^\d{13}$/, message: 'TIKI: 13 digit (1234567890123)' },
                'POS': { pattern: /^\d{5,}$/, message: 'POS: minimal 5 digit' },
                'SICEPAT': { pattern: /^[A-Z0-9]{10,}$/i, message: 'SiCepat: alphanumeric minimal 10 karakter' },
                'WAHANA': { pattern: /^\d{9}$/, message: 'Wahana: 9 digit' },
                'NINJA': { pattern: /^[A-Z0-9]{9,}$/i, message: 'Ninja: alphanumeric minimal 9 karakter' },
                'LION': { pattern: /^\d{10}$/, message: 'Lion: 10 digit' }
            };

            if (validations[kurir]) {
                return validations[kurir].pattern.test(trackingNumber) ? 
                    { valid: true } : 
                    { valid: false, message: validations[kurir].message };
            }
            return { valid: true };
        }

        function shipOrder(event) {
            event.preventDefault();
            const orderId = document.getElementById('shipOrderId').value;
            const trackingNumber = document.getElementById('trackingNumber').value.trim();
            const kurir = document.getElementById('shipOrderKurir').value;
            const shipNote = document.getElementById('shipNote').value;
            const alertContainer = document.getElementById('shipAlertContainer');
            const errorContainer = document.getElementById('resiValidationError');
            
            if (!trackingNumber) {
                showAlert(alertContainer, 'error', 'Nomor resi wajib diisi.');
                return;
            }
            
            // Validasi format resi
            const validation = validateTrackingNumber(kurir, trackingNumber);
            if (!validation.valid) {
                errorContainer.innerHTML = '<strong>❌ Format tidak valid!</strong> ' + validation.message;
                errorContainer.style.display = 'block';
                return;
            } else {
                errorContainer.style.display = 'none';
            }
            
            fetch('api_pesanan_penjual.php?action=update_status', {
                credentials: 'same-origin',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_pesanan: parseInt(orderId),
                    status: 'dikirim',
                    nomor_resi: trackingNumber,
                    catatan: shipNote
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(alertContainer, 'success', 'Pesanan berhasil dikirim!');
                    setTimeout(() => {
                        closeModal('shipModal');
                        loadOrders(currentFilter);
                    }, 1500);
                } else {
                    showAlert(alertContainer, 'error', data.message || 'Gagal mengirim pesanan');
                }
            })
            .catch(error => {
                showAlert(alertContainer, 'error', 'Error: ' + error.message);
            });
        }
        
        function refundOrder(event) {
            event.preventDefault();
            const orderId = document.getElementById('refundOrderId').value;
            const refundReason = document.getElementById('refundReason').value;
            const refundNote = document.getElementById('refundNote').value;
            const alertContainer = document.getElementById('refundAlertContainer');
            
            if (!refundReason) {
                showAlert(alertContainer, 'error', 'Alasan refund wajib diisi.');
                return;
            }
            
            Swal.fire({
                title: 'Konfirmasi Refund?',
                html: '<p style="font-size: 16px; color: #8B4513;"><strong>Alasan Refund:</strong> ' + refundReason + '</p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#F39C12',
                cancelButtonColor: '#A0522D',
                confirmButtonText: 'Ya, Proses Refund',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api_pesanan_penjual.php?action=refund_pesanan', {
                        credentials: 'same-origin',
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id_pesanan: parseInt(orderId),
                            alasan_refund: refundReason,
                            catatan_refund: refundNote
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Refund Berhasil!',
                                text: data.message || 'Refund telah diproses',
                                confirmButtonColor: '#2E7D32'
                            }).then(() => {
                                closeModal('refundModal');
                                loadOrders(currentFilter);
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: data.message || 'Gagal memproses refund',
                                confirmButtonColor: '#A0522D'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Kesalahan!',
                            text: 'Error: ' + error.message,
                            confirmButtonColor: '#A0522D'
                        });
                    });
                }
            });
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            
            // Clear alert containers
            const alertContainers = ['approveAlertContainer', 'rejectAlertContainer', 'shipAlertContainer', 'refundAlertContainer'];
            alertContainers.forEach(id => {
                const container = document.getElementById(id);
                if (container) container.innerHTML = '';
            });
        }
        
        function showAlert(container, type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            container.innerHTML = `<div class="alert ${alertClass}"><i class="${icon}"></i>${message}</div>`;
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('detailModal');
                closeModal('approveModal');
                closeModal('rejectModal');
                closeModal('shipModal');
                closeModal('refundModal');
                closeModal('proofModal');
                closeModal('invoiceModal');
            }
        });
        
        function toggleUserMenu() {
            const menu = document.querySelector('.user-profile');
            menu.classList.toggle('active');
        }
        
        document.getElementById('navbarSearch')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                filterTable(query);
            }
        });
        
        window.addEventListener('focus', function() {
            loadOrders(currentFilter);
        });
        
        // ===== NOTIFICATION FUNCTIONS =====
        function loadNotifications() {
            const notificationList = document.getElementById('notificationList');
            if (!notificationList) return;
            
            notificationList.innerHTML = '<div style="text-align: center; padding: 30px 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i><br>Memuat notifikasi...</div>';
            
            fetch('api_get_notifications.php?t=' + Date.now())
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(data => {
                    if (data.success) {
                        displayNotifications(data.notifications);
                        updateNotificationCount(data.unread_total);
                    } else {
                        showErrorNotification();
                    }
                })
                .catch(() => showErrorNotification());
        }
        
        function displayNotifications(notifications) {
            const notificationList = document.getElementById('notificationList');
            if (!notificationList) return;
            
            if (!notifications || notifications.length === 0) {
                notificationList.innerHTML = '<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-bell-slash" style="font-size: 3rem; color: rgba(139, 69, 19, 0.3); margin-bottom: 15px;"></i><p>Tidak ada notifikasi</p></div>';
                return;
            }
            
            let html = '';
            notifications.forEach(notif => {
                const icon = notif.type === 'chat' ? 'fa-comment' : 'fa-shopping-cart';
                const unreadClass = notif.dibaca == 0 ? 'unread' : '';
                const time = timeAgo(notif.created_at);
                
                html += `
                    <div class="notification-item ${unreadClass}" onclick="window.location.href='${notif.link}'">
                        <div class="notification-icon"><i class="fas ${icon}"></i></div>
                        <div class="notification-content">
                            <div class="notification-title">${escapeHtml(notif.title)}</div>
                            <div class="notification-message">${escapeHtml(notif.message)}</div>
                            <div class="notification-time">${time}</div>
                        </div>
                    </div>
                `;
            });
            
            notificationList.innerHTML = html;
        }
        
        function showErrorNotification() {
            const notificationList = document.getElementById('notificationList');
            if (!notificationList) return;
            
            notificationList.innerHTML = `
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--danger); margin-bottom: 15px;"></i>
                    <p>Gagal memuat notifikasi</p>
                    <button onclick="location.reload()" style="margin-top: 10px; padding: 8px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-sync-alt"></i> Muat Ulang
                    </button>
                </div>
            `;
        }
        
        function timeAgo(timestamp) {
            const now = new Date();
            const past = new Date(timestamp);
            const diff = Math.floor((now - past) / 1000);
            
            if (diff < 60) return 'baru saja';
            if (diff < 3600) return Math.floor(diff / 60) + ' menit yang lalu';
            if (diff < 86400) return Math.floor(diff / 3600) + ' jam yang lalu';
            return Math.floor(diff / 86400) + ' hari yang lalu';
        }
        
        function updateNotificationCount(count) {
            const notificationCount = document.getElementById('notificationCount');
            if (!notificationCount) return;
            
            notificationCount.textContent = count;
            notificationCount.style.display = count > 0 ? 'flex' : 'none';
        }
        
        function markNotificationsAsRead() {
            fetch('api_mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationCount(0);
                    updateSidebarBadges();
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        document.getElementById('markAllRead')?.addEventListener('click', function(e) {
            e.preventDefault();
            markNotificationsAsRead();
            setTimeout(() => loadNotifications(), 500);
        });
        
        function updateSidebarBadges() {
            fetch('api_get_notifications_count.php')
                .then(response => response.json())
                .then(data => {
                    // Update badge untuk halaman saat ini
                    const currentPageBadge = document.querySelector('.sidebar-nav-item.active .sidebar-badge');
                    if (currentPageBadge) {
                        if (data.order_count > 0) {
                            currentPageBadge.textContent = data.order_count;
                            currentPageBadge.style.display = 'inline-block';
                        } else {
                            currentPageBadge.style.display = 'none';
                        }
                    }
                    
                    // Update badge chat
                    const chatBadge = document.querySelector('#sidebarChat .sidebar-badge');
                    if (chatBadge) {
                        if (data.chat_count > 0) {
                            chatBadge.textContent = data.chat_count;
                            chatBadge.style.display = 'inline-block';
                        } else {
                            chatBadge.style.display = 'none';
                        }
                    }
                    
                    // Update pending count di stats card
                    const pendingCountEl = document.getElementById('pendingCount');
                    if (pendingCountEl && pendingCountEl.textContent != data.order_count) {
                        pendingCountEl.textContent = data.order_count;
                    }
                })
                .catch(error => console.error('Error updating badges:', error));
        }
    </script>
</body>
</html>