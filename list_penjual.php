<?php
/**
 * Halaman List Penjualan - Menampilkan pesanan yang telah disetujui/diselesaikan
 * Dengan tabel sederhana: ID Pesanan, Pembeli, Produk, Qty, Total, Status, Tanggal
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

// ===== NOTIFIKASI =====
$notifications = getNotifications($conn, $id_penjual, 'penjual');

// Get counts for approved orders
$approved_counts = [];
$approved_statuses = ['diproses', 'dikirim', 'selesai'];

foreach ($approved_statuses as $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_penjual = ? AND status = ?");
    $stmt->bind_param("is", $id_penjual, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $approved_counts[$status] = $row['count'] ?? 0;
    $stmt->close();
}

// Get total approved orders
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_penjual = ? AND status IN ('diproses', 'dikirim', 'selesai')");
$stmt->bind_param("i", $id_penjual);
$stmt->execute();
$result = $stmt->get_result();
$total_approved = $result->fetch_assoc()['count'] ?? 0;
$stmt->close();

// Get total revenue from completed orders
$stmt = $conn->prepare("SELECT SUM(total_harga) as total FROM pesanan WHERE id_penjual = ? AND status = 'selesai'");
$stmt->bind_param("i", $id_penjual);
$stmt->execute();
$result = $stmt->get_result();
$total_revenue = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Get average order value
$stmt = $conn->prepare("SELECT AVG(total_harga) as avg_value FROM pesanan WHERE id_penjual = ? AND status IN ('diproses', 'dikirim', 'selesai')");
$stmt->bind_param("i", $id_penjual);
$stmt->execute();
$result = $stmt->get_result();
$avg_order_value = $result->fetch_assoc()['avg_value'] ?? 0;
$stmt->close();

// Tutup koneksi
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Daftar Penjualan - Pustaka Aruna</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #8B4513;
            --secondary: #D2B48C;
            --danger: #A0522D;
            --warning: #CD853F;
            --dark: #5D4037;
            --light: #F5F5DC;
            --accent: #BC8F8F;
            --success: #2E7D32;
            --info: #1976D2;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: #F5F5DC;
            color: #5D4037;
            min-height: 100vh;
            background-image: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%238B4513" stroke-width="0.5" opacity="0.1"/></svg>');
        }

        /* Vintage overlay */
        .vintage-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(139, 69, 19, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(210, 180, 140, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 20px rgba(91, 64, 55, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--secondary);
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
            color: var(--secondary);
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
            border: 2px solid var(--secondary);
            border-radius: 30px;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: rgba(245, 245, 220, 0.9);
            color: var(--dark);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--light);
            box-shadow: 0 0 0 3px rgba(210, 180, 140, 0.3);
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Notification Styles */
        .notification-bell {
            position: relative;
            font-size: 1.3rem;
            color: var(--light);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .notification-bell:hover {
            transform: scale(1.1);
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 80px;
            width: 350px;
            background: linear-gradient(135deg, rgba(245, 245, 220, 0.95), rgba(210, 180, 140, 0.95));
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            border: 2px solid var(--secondary);
            display: none;
            z-index: 1001;
            backdrop-filter: blur(5px);
        }

        .notification-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .notification-header {
            padding: 15px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(139, 69, 19, 0.1);
        }

        .notification-header h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1rem;
            margin: 0;
        }

        .notification-header a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .notification-header a:hover {
            text-decoration: underline;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 15px;
            border-bottom: 1px solid rgba(139, 69, 19, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .notification-item:hover {
            background: rgba(210, 180, 140, 0.3);
        }

        .notification-item.unread {
            background: rgba(139, 69, 19, 0.1);
        }

        .notification-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--primary);
            margin-bottom: 3px;
        }

        .notification-message {
            font-size: 0.8rem;
            color: var(--dark);
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.7rem;
            color: rgba(93, 64, 55, 0.6);
            margin-top: 3px;
        }

        .notification-footer {
            padding: 12px;
            text-align: center;
            background: rgba(139, 69, 19, 0.05);
        }

        .notification-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: rgba(210, 180, 140, 0.3);
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(210, 180, 140, 0.5);
        }

        .user-profile:hover {
            background: rgba(210, 180, 140, 0.5);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            border: 2px solid var(--secondary);
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
            color: var(--secondary);
        }

        .online-status {
            width: 10px;
            height: 10px;
            background: #32CD32;
            border-radius: 50%;
            margin-left: 5px;
        }

        /* Main Container */
        .main-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 0;
            min-height: calc(100vh - 70px);
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #5D4037 0%, #3E2723 100%);
            color: var(--light);
            padding: 2rem 0;
            height: calc(100vh - 70px);
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            border-right: 2px solid var(--secondary);
        }

        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(210, 180, 140, 0.3);
            margin-bottom: 1rem;
        }

        .sidebar-title {
            font-family: 'Playfair Display', serif;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            padding: 0 1.5rem;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-item {
            margin: 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 1.5rem;
            color: rgba(245, 245, 220, 0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            font-size: 0.95rem;
        }

        .sidebar-link:hover {
            background: rgba(210, 180, 140, 0.2);
            color: var(--light);
            padding-left: 2rem;
        }

        .sidebar-link.active {
            background: rgba(139, 69, 19, 0.3);
            color: var(--light);
            border-left-color: var(--secondary);
            font-weight: 600;
        }

        .sidebar-link i {
            width: 20px;
            font-size: 1.1rem;
            color: var(--secondary);
        }

        .sidebar-badge {
            background: var(--danger);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: auto;
        }

        /* Main Content */
        .content {
            padding: 2rem;
            overflow-y: auto;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
            color: var(--light);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--secondary);
            box-shadow: 0 10px 30px rgba(46, 125, 50, 0.15);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(210, 180, 140, 0.2) 0%, transparent 50%);
        }

        .header::after {
            content: '💰';
            position: absolute;
            bottom: 10px;
            right: 20px;
            font-size: 4rem;
            opacity: 0.1;
            transform: rotate(-10deg);
        }

        .page-title h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title p {
            font-size: 1rem;
            opacity: 0.9;
            font-style: italic;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(245, 245, 220, 0.9), rgba(210, 180, 140, 0.8));
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(91, 64, 55, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid rgba(139, 69, 19, 0.2);
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
            background: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%238B4513" stroke-width="0.5" opacity="0.1"/></svg>');
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(91, 64, 55, 0.15);
            border-color: var(--secondary);
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
            position: relative;
            z-index: 1;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, #8B4513, #A0522D);
        }

        .stat-icon.processing {
            background: linear-gradient(135deg, #1976D2, #0D47A1);
        }

        .stat-icon.shipped {
            background: linear-gradient(135deg, #7B1FA2, #4A148C);
        }

        .stat-icon.completed {
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
        }

        .stat-icon.revenue {
            background: linear-gradient(135deg, #F57C00, #E65100);
        }

        .stat-icon.average {
            background: linear-gradient(135deg, #C2185B, #880E4F);
        }

        .stat-content {
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .stat-content h3 {
            font-size: 0.9rem;
            color: var(--dark);
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .stat-content .number {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-content .sub-text {
            font-size: 0.8rem;
            color: var(--dark);
            opacity: 0.7;
            margin-top: 5px;
        }

        /* Filters */
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(245, 245, 220, 0.9), rgba(210, 180, 140, 0.8));
            border-radius: 12px;
            border: 1px solid rgba(139, 69, 19, 0.2);
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid rgba(139, 69, 19, 0.3);
            background: rgba(245, 245, 220, 0.8);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            border-color: var(--primary);
            background: rgba(210, 180, 140, 0.5);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
            color: var(--light);
            border-color: var(--secondary);
        }

        /* Sales Table Container */
        .sales-table {
            background: linear-gradient(135deg, rgba(245, 245, 220, 0.9), rgba(210, 180, 140, 0.8));
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(91, 64, 55, 0.1);
            border: 1px solid rgba(139, 69, 19, 0.2);
            position: relative;
        }

        .sales-table::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%238B4513" stroke-width="0.5" opacity="0.1"/></svg>');
            pointer-events: none;
        }

        .table-header {
            padding: 1.5rem;
            background: rgba(139, 69, 19, 0.1);
            border-bottom: 2px solid rgba(139, 69, 19, 0.3);
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box-table {
            position: relative;
            width: 300px;
        }

        .search-box-table input {
            width: 100%;
            padding: 10px 15px 10px 45px;
            border: 2px solid rgba(139, 69, 19, 0.3);
            border-radius: 25px;
            font-size: 0.9rem;
            background: rgba(245, 245, 220, 0.9);
            color: var(--dark);
            transition: all 0.3s;
        }

        .search-box-table input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        .search-box-table i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            opacity: 0.7;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            position: relative;
            z-index: 1;
        }

        thead {
            background: rgba(139, 69, 19, 0.08);
        }

        th {
            padding: 1.2rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            border-bottom: 2px solid rgba(139, 69, 19, 0.2);
            color: var(--primary);
            font-family: 'Playfair Display', serif;
        }

        td {
            padding: 1.2rem;
            border-bottom: 1px solid rgba(139, 69, 19, 0.1);
            font-size: 0.9rem;
            color: var(--dark);
        }

        tbody tr {
            transition: all 0.3s;
        }

        tbody tr:hover {
            background: rgba(210, 180, 140, 0.2);
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
            color: var(--dark);
        }

        .buyer-email {
            font-size: 0.8rem;
            color: var(--dark);
            opacity: 0.7;
        }

        .product-name {
            font-weight: 600;
            color: var(--dark);
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        .status-diproses {
            background: rgba(25, 118, 210, 0.2);
            color: #0D47A1;
            border-color: #1976D2;
        }

        .status-dikirim {
            background: rgba(123, 31, 162, 0.2);
            color: #4A148C;
            border-color: #7B1FA2;
        }

        .status-selesai {
            background: rgba(46, 125, 50, 0.2);
            color: #1B5E20;
            border-color: #2E7D32;
        }

        /* Amount styling */
        .amount {
            font-weight: 600;
            color: var(--primary);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
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
            background: linear-gradient(135deg, #8B4513, #A0522D);
            color: white;
            border-color: #8B4513;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #A0522D, #8B4513);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(139, 69, 19, 0.2);
        }

        .btn-invoice {
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
            color: white;
            border-color: #2E7D32;
        }

        .btn-invoice:hover {
            background: linear-gradient(135deg, #1B5E20, #2E7D32);
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--dark);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: rgba(139, 69, 19, 0.3);
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        /* Modal */
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
            background: linear-gradient(135deg, rgba(245, 245, 220, 0.95), rgba(210, 180, 140, 0.9));
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            border: 2px solid var(--secondary);
            box-shadow: 0 20px 40px rgba(91, 64, 55, 0.3);
            position: relative;
        }

        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%238B4513" stroke-width="0.5" opacity="0.1"/></svg>');
            pointer-events: none;
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(139, 69, 19, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, rgba(245, 245, 220, 0.95), rgba(210, 180, 140, 0.9));
            z-index: 1;
            border-radius: 15px 15px 0 0;
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
            border: 1px solid rgba(139, 69, 19, 0.3);
        }

        .modal-close:hover {
            background: rgba(139, 69, 19, 0.1);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        /* Form Elements */
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
            border: 2px solid rgba(139, 69, 19, 0.3);
            border-radius: 10px;
            font-size: 0.95rem;
            background: rgba(245, 245, 220, 0.8);
            color: var(--dark);
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        /* Loading Spinner */
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

        /* Scrollbar */
        .sidebar::-webkit-scrollbar,
        .content::-webkit-scrollbar,
        .notification-list::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track,
        .content::-webkit-scrollbar-track,
        .notification-list::-webkit-scrollbar-track {
            background: rgba(210, 180, 140, 0.2);
        }

        .sidebar::-webkit-scrollbar-thumb,
        .content::-webkit-scrollbar-thumb,
        .notification-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .main-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .search-box-table {
                width: 250px;
            }
            
            .notification-dropdown {
                right: 20px;
                width: 300px;
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
                grid-template-columns: 1fr;
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
        }

        @media (max-width: 576px) {
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
            
            table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 10px;
            }
            
            .product-name {
                max-width: 120px;
            }
        }
    </style>
</head>
<body>
    <!-- Vintage overlay -->
    <div class="vintage-overlay"></div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo-container">
            <a href="penjual_dashboard.php" class="logo">
                <i class="fas fa-book-open"></i>
                <span>Pustaka Aruna</span>
            </a>
        </div>
        
        <div class="search-box">
            <input type="text" placeholder="Cari penjualan..." id="navbarSearch">
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
                    <div style="text-align: center; padding: 20px; color: var(--dark);">
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

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div style="font-family: 'Playfair Display', serif; font-size: 1.1rem; font-weight: 600; margin-bottom: 5px;">PENJUAL BUKU</div>
                <div style="font-size: 0.9rem; color: rgba(210, 180, 140, 0.8);">@<?php echo htmlspecialchars($user_name); ?></div>
            </div>

            <!-- Main Menu -->
            <div class="sidebar-title">Menu Utama</div>
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
                    <a href="pesanan_penjual.php" class="sidebar-link">
                        <i class="fas fa-inbox"></i> Pesanan Masuk
                        <?php if ($notifications['order'] > 0): ?>
                        <span class="sidebar-badge"><?php echo $notifications['order']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="list_penjualan.php" class="sidebar-link active">
                        <i class="fas fa-chart-bar"></i> Daftar Penjualan
                        <span class="sidebar-badge" style="background: var(--success);"><?php echo $total_approved; ?></span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="penjual_laporan.php" class="sidebar-link">
                        <i class="fas fa-file-alt"></i> Laporan
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

            <!-- Account Menu -->
            <div class="sidebar-title" style="margin-top: 2rem;">Akun Saya</div>
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

        <!-- Main Content -->
        <main class="content">
            <!-- Header -->
            <div class="header">
                <div class="page-title">
                    <h1><i class="fas fa-chart-bar"></i> Daftar Penjualan</h1>
                    <p>"Riwayat penjualan yang telah disetujui dan diselesaikan"</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Penjualan</h3>
                        <div class="number"><?php echo $total_approved; ?></div>
                        <div class="sub-text">Semua status</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon processing">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Sedang Diproses</h3>
                        <div class="number"><?php echo $approved_counts['diproses'] ?? 0; ?></div>
                        <div class="sub-text">Menunggu pengiriman</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon shipped">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Dalam Pengiriman</h3>
                        <div class="number"><?php echo $approved_counts['dikirim'] ?? 0; ?></div>
                        <div class="sub-text">Sedang dalam perjalanan</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Selesai</h3>
                        <div class="number"><?php echo $approved_counts['selesai'] ?? 0; ?></div>
                        <div class="sub-text">Pesanan diterima</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Pendapatan</h3>
                        <div class="number">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                        <div class="sub-text">Dari pesanan selesai</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon average">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Rata-rata Penjualan</h3>
                        <div class="number">Rp <?php echo number_format($avg_order_value, 0, ',', '.'); ?></div>
                        <div class="sub-text">Per transaksi</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <button class="filter-btn <?php echo ($filter === 'semua') ? 'active' : ''; ?>" onclick="filterOrders('semua')">
                    <i class="fas fa-list"></i> Semua
                </button>
                <button class="filter-btn <?php echo ($filter === 'diproses') ? 'active' : ''; ?>" onclick="filterOrders('diproses')">
                    <i class="fas fa-cog"></i> Sedang Diproses
                </button>
                <button class="filter-btn <?php echo ($filter === 'dikirim') ? 'active' : ''; ?>" onclick="filterOrders('dikirim')">
                    <i class="fas fa-truck"></i> Dikirim
                </button>
                <button class="filter-btn <?php echo ($filter === 'selesai') ? 'active' : ''; ?>" onclick="filterOrders('selesai')">
                    <i class="fas fa-check-circle"></i> Selesai
                </button>
                <button class="filter-btn <?php echo ($filter === 'bulan_ini') ? 'active' : ''; ?>" onclick="filterOrders('bulan_ini')">
                    <i class="fas fa-calendar-alt"></i> Bulan Ini
                </button>
            </div>

            <!-- Sales Table -->
            <div class="sales-table">
                <div class="table-header">
                    <h2>
                        <i class="fas fa-history"></i> Riwayat Penjualan
                    </h2>
                    <div class="search-box-table">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Cari penjualan...">
                    </div>
                </div>

                <div id="salesContainer">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Memuat data penjualan...
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Detail Modal -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detail Penjualan</h2>
                <button class="modal-close" onclick="closeModal('detailModal')">&times;</button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        let allSales = [];

        // Global helper to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : text;
            return div.innerHTML;
        }

        let currentFilter = '<?php echo $filter; ?>';

        // ===== NOTIFICATION SYSTEM =====
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationCount = document.getElementById('notificationCount');
            const notificationList = document.getElementById('notificationList');

            if (!notificationBell || !notificationDropdown || !notificationCount || !notificationList) {
                console.warn('Notification elements not found.');
                return;
            }

            // Load sales on page load
            loadSales(currentFilter);

            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function(e) {
                filterTable(e.target.value);
            });

            // Toggle dropdown
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                notificationDropdown.classList.toggle('show');
                if (notificationDropdown.classList.contains('show')) {
                    loadNotifications();
                    markNotificationsAsRead();
                }
            });

            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                }
            });

            // Prevent dropdown from closing when clicking inside
            notificationDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            function loadNotifications() {
                notificationList.innerHTML = '<div style="text-align: center; padding: 30px 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i><br>Memuat notifikasi...</div>';
                
                fetch('api_get_notifications.php?t=' + Date.now())
                    .then(response => {
                        if (!response.ok) throw new Error('Network error');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            displayNotifications(data.notifications);
                            updateNotificationCount(data.unread_total);
                        } else {
                            showErrorNotification();
                        }
                    })
                    .catch(error => {
                        console.error('Error loading notifications:', error);
                        showErrorNotification();
                    });
            }

            function displayNotifications(notifications) {
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
                            <div class="notification-icon">
                                <i class="fas ${icon}"></i>
                            </div>
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
                if (diff < 3600) {
                    const minutes = Math.floor(diff / 60);
                    return minutes + ' menit yang lalu';
                }
                if (diff < 86400) {
                    const hours = Math.floor(diff / 3600);
                    return hours + ' jam yang lalu';
                }
                const days = Math.floor(diff / 86400);
                return days + ' hari yang lalu';
            }

            function updateNotificationCount(count) {
                notificationCount.textContent = count;
                if (count > 0) {
                    notificationCount.style.display = 'flex';
                    notificationBell.classList.add('shake');
                    setTimeout(() => notificationBell.classList.remove('shake'), 500);
                } else {
                    notificationCount.style.display = 'none';
                }
            }

            function markNotificationsAsRead() {
                fetch('api_mark_notifications_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationCount(0);
                    }
                })
                .catch(error => console.error('Error marking as read:', error));
            }

            document.getElementById('markAllRead')?.addEventListener('click', function(e) {
                e.preventDefault();
                markNotificationsAsRead();
                setTimeout(() => loadNotifications(), 500);
            });
        });

        function loadSales(filter = 'semua') {
            const container = document.getElementById('salesContainer');
            container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Memuat data penjualan...</div>';

            let url;
            if (filter === 'bulan_ini') {
                url = 'api_penjualan.php?action=list&filter=bulan_ini';
            } else {
                url = filter === 'semua' ? 'api_penjualan.php?action=list&status=diproses,dikirim,selesai' : `api_penjualan.php?action=list&filter=${filter}`;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log('Data dari API:', data);
                    
                    if (data.success) {
                        allSales = data.data;
                        renderSales(allSales);
                    } else {
                        container.innerHTML = '<div class="empty-state"><i class="fas fa-chart-bar"></i><h3>Tidak ada data penjualan</h3><p>Belum ada penjualan yang tercatat</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error memuat data</h3><p>Terjadi kesalahan saat memuat data penjualan</p></div>';
                });
        }

        function renderSales(sales) {
            const container = document.getElementById('salesContainer');
            
            if (sales.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-chart-bar"></i><h3>Tidak ada data penjualan</h3><p>Belum ada penjualan dengan status ini</p></div>';
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

            sales.forEach(sale => {
                const statusClass = `status-${sale.status.replace('_', '-')}`;
                const statusText = sale.status.toUpperCase().replace('_', ' ');
                const tanggal = new Date(sale.created_at).toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });

                html += `
                    <tr>
                        <td><span class="order-id">#${sale.id_pesanan}</span></td>
                        <td>
                            <div class="buyer-info">
                                <div class="buyer-name">${escapeHtml(sale.nama_pembeli)}</div>
                                <div class="buyer-email">${escapeHtml(sale.email_pembeli)}</div>
                            </div>
                        </td>
                        <td>
                            <div class="product-name" title="${escapeHtml(sale.nama_produk || 'Produk')}">
                                ${escapeHtml(sale.nama_produk || 'Produk')}
                            </div>
                        </td>
                        <td><span class="amount">${sale.quantity}</span></td>
                        <td><span class="amount">Rp ${parseInt(sale.total_harga).toLocaleString('id-ID')}</span></td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${tanggal}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action btn-view" onclick="viewDetail(${sale.id_pesanan})" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-action btn-invoice" onclick="showInvoice(${sale.id_pesanan})" title="Lihat Invoice">
                                    <i class="fas fa-file-invoice"></i>
                                </button>
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
        }

        function filterTable(searchTerm) {
            if (!searchTerm.trim()) {
                renderSales(allSales);
                return;
            }
            
            const filtered = allSales.filter(sale => 
                sale.id_pesanan.toString().includes(searchTerm) ||
                (sale.nama_pembeli && sale.nama_pembeli.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (sale.email_pembeli && sale.email_pembeli.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (sale.nama_produk && sale.nama_produk.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (sale.status && sale.status.toLowerCase().includes(searchTerm.toLowerCase()))
            );
            renderSales(filtered);
        }

        function filterOrders(filter) {
            currentFilter = filter;
            loadSales(filter);

            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            
            const activeBtn = Array.from(document.querySelectorAll('.filter-btn')).find(btn => {
                const btnText = btn.textContent;
                if (filter === 'diproses') {
                    return btnText.includes('Sedang Diproses');
                } else if (filter === 'dikirim') {
                    return btnText.includes('Dikirim');
                } else if (filter === 'selesai') {
                    return btnText.includes('Selesai');
                } else if (filter === 'bulan_ini') {
                    return btnText.includes('Bulan Ini');
                } else {
                    return btnText.includes('Semua');
                }
            });
            
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
            
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('filter', filter);
            window.history.pushState({}, '', url);
        }

        function viewDetail(orderId) {
            fetch(`api_pesanan_penjual.php?action=detail&id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const order = data.data;
                        const tanggal = new Date(order.created_at).toLocaleDateString('id-ID', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });

                        let html = `
                            <div>
                                <div class="form-group">
                                    <label class="form-label">ID Pesanan</label>
                                    <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px; color: var(--primary); font-weight: 600;">#${order.id_pesanan}</div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pembeli</label>
                                    <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">
                                        ${escapeHtml(order.nama_pembeli)} (${escapeHtml(order.email_pembeli)})
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Produk</label>
                                    <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">
                                        ${escapeHtml(order.nama_produk || '-')}
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Jumlah</label>
                                    <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">
                                        ${order.quantity} unit @ Rp ${parseInt(order.harga || 0).toLocaleString('id-ID')}
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Total Harga</label>
                                    <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px; font-weight: 600; color: var(--primary); font-size: 16px;">
                                        Rp ${parseInt(order.total_harga).toLocaleString('id-ID')}
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Status</label>
                                    <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">
                                        <span class="status-badge status-${order.status.replace('_', '-')}">${order.status.toUpperCase().replace('_', ' ')}</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Alamat Pengiriman</label>
                                    <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px; white-space: pre-wrap;">${escapeHtml(order.alamat_pengiriman || '-')}</div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Nomor Resi</label>
                                    <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">${escapeHtml(order.nomor_resi || '-')}</div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tanggal Pesanan</label>
                                    <div style="padding: 10px; background: rgba(210, 180, 140, 0.2); border-radius: 8px;">${tanggal}</div>
                                </div>
                            </div>
                        `;

                        document.getElementById('detailContent').innerHTML = html;
                        openModal('detailModal');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Data tidak ditemukan'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan saat memuat detail'
                    });
                });
        }

        function showInvoice(orderId) {
            fetch(`api_pesanan_penjual.php?action=detail&id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const order = data.data;
                        const tanggal = new Date(order.created_at).toLocaleDateString('id-ID', {
                            day: '2-digit',
                            month: 'long',
                            year: 'numeric'
                        });

                        let html = `
                            <div style="text-align: center; margin-bottom: 2rem;">
                                <h2 style="font-family: 'Playfair Display', serif; color: var(--primary);">INVOICE</h2>
                                <p><strong>Pustaka Aruna</strong></p>
                                <p>Jl. Buku Indah No. 123, Jakarta</p>
                            </div>

                            <div style="margin-bottom: 2rem;">
                                <p><strong>No. Invoice:</strong> INV-${order.id_pesanan}</p>
                                <p><strong>No. Pesanan:</strong> #${order.id_pesanan}</p>
                                <p><strong>Tanggal:</strong> ${tanggal}</p>
                                <p><strong>Pembeli:</strong> ${escapeHtml(order.nama_pembeli)}</p>
                                <p><strong>Email:</strong> ${escapeHtml(order.email_pembeli)}</p>
                                <p><strong>Alamat:</strong> ${escapeHtml(order.alamat_pengiriman || '-')}</p>
                            </div>

                            <table style="width: 100%; border-collapse: collapse; margin-bottom: 2rem;">
                                <thead>
                                    <tr style="background: rgba(139, 69, 19, 0.1);">
                                        <th style="padding: 0.8rem; text-align: left;">Produk</th>
                                        <th style="padding: 0.8rem; text-align: center;">Qty</th>
                                        <th style="padding: 0.8rem; text-align: right;">Harga</th>
                                        <th style="padding: 0.8rem; text-align: right;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding: 0.8rem;">${escapeHtml(order.nama_produk || 'Produk')}</td>
                                        <td style="padding: 0.8rem; text-align: center;">${order.quantity}</td>
                                        <td style="padding: 0.8rem; text-align: right;">Rp ${parseInt(order.harga || 0).toLocaleString('id-ID')}</td>
                                        <td style="padding: 0.8rem; text-align: right;">Rp ${parseInt(order.total_harga).toLocaleString('id-ID')}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" style="padding: 0.8rem; text-align: right;"><strong>Total:</strong></td>
                                        <td style="padding: 0.8rem; text-align: right; font-weight: 700; color: var(--primary);">Rp ${parseInt(order.total_harga).toLocaleString('id-ID')}</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px dashed var(--primary);">
                                <p>Terima kasih telah berbelanja di Pustaka Aruna</p>
                            </div>
                        `;

                        Swal.fire({
                            title: 'Invoice',
                            html: html,
                            width: '800px',
                            showConfirmButton: true,
                            confirmButtonText: 'Tutup',
                            confirmButtonColor: '#8B4513'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Gagal membuat invoice'
                    });
                });
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('detailModal');
            }
        });

        // Auto-refresh every 60 seconds
        setInterval(() => loadSales(currentFilter), 60000);

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
    </script>
</body>
</html>