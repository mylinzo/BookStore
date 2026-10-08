<?php
session_start();
include 'koneksi.php';

// ========== LOGOUT HANDLING DI AWAL ==========
if (isset($_GET['logout'])) {
    // Update status user menjadi offline
    if (isset($_SESSION["id_user"])) {
        $user_id = $_SESSION["id_user"];
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
    header("Location: login.php?logout=success");
    exit;
}

// Check user login and role specifically for PEMBELI
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pembeli') {
    // Redirect to appropriate dashboard based on role
    if ($_SESSION['role'] == 'super_admin') {
        header("Location: dashboard_admin.php");
    } elseif ($_SESSION['role'] == 'penjual') {
        header("Location: penjual_dashboard.php");
    } else {
        header("Location: login.php");
    }
    exit;
}

// Get user data
$id_pembeli = $_SESSION['id_user'];
$nama_pembeli = $_SESSION['nama'] ?? 'Pembeli';
$email_pembeli = $_SESSION['email'] ?? '';

// Get order stats
$order_stats = [
    'semua' => 0,
    'menunggu_pembayaran' => 0,
    'dibayar' => 0,
    'diproses' => 0,
    'dikirim' => 0,
    'selesai' => 0,
    'ditolak' => 0,
    'dibatalkan' => 0
];

$statuses = array_keys($order_stats);
foreach ($statuses as $status) {
    if ($status === 'semua') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_pembeli = ?");
        $stmt->bind_param("i", $id_pembeli);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_pembeli = ? AND status = ?");
        $stmt->bind_param("is", $id_pembeli, $status);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $order_stats[$status] = $row['count'] ?? 0;
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pesanan - Aksara-Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #10b981;
            --primary-dark: #059669;
            --secondary-color: #8b5cf6;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
            --gray-color: #9ca3af;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --success-color: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f3f4f6;
            color: var(--dark-color);
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-logout {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-logout:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .btn-back {
            background-color: white;
            color: var(--primary-color);
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background-color: #f0f9ff;
            transform: translateY(-2px);
        }

        /* Order Stats */
        .order-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .order-stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .order-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .order-stat-card.active {
            border-color: var(--primary-color);
            background-color: #f0f9ff;
        }

        .order-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 22px;
        }

        .stat-all .order-stat-icon { background: #e0e7ff; color: var(--info-color); }
        .stat-menunggu .order-stat-icon { background: #fef3c7; color: var(--warning-color); }
        .stat-dibayar .order-stat-icon { background: #dbeafe; color: var(--info-color); }
        .stat-diproses .order-stat-icon { background: #d1fae5; color: var(--primary-color); }
        .stat-dikirim .order-stat-icon { background: #e0e7ff; color: var(--secondary-color); }
        .stat-selesai .order-stat-icon { background: #ddd6fe; color: #8b5cf6; }
        .stat-ditolak .order-stat-icon { background: #fee2e2; color: var(--danger-color); }
        .stat-dibatalkan .order-stat-icon { background: #f3f4f6; color: var(--gray-color); }

        .order-stat-count {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark-color);
        }

        .order-stat-label {
            font-size: 14px;
            color: var(--gray-color);
            text-transform: uppercase;
            font-weight: 600;
        }

        /* Status Badge */
        .order-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-menunggu_pembayaran {
            background: #fef3c7;
            color: #92400e;
        }

        .status-dibayar {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-diproses {
            background: #d1fae5;
            color: #065f46;
        }

        .status-dikirim {
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-selesai {
            background: #ddd6fe;
            color: #5b21b6;
        }

        .status-ditolak {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .status-dibatalkan {
            background: #f3f4f6;
            color: #374151;
        }

        /* Order Actions */
        .order-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .btn-upload {
            background-color: var(--info-color);
            color: white;
        }

        .btn-upload:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }

        .btn-detail {
            background-color: var(--gray-color);
            color: white;
        }

        .btn-detail:hover {
            background-color: #6b7280;
            transform: translateY(-2px);
        }

        .btn-batal {
            background-color: var(--warning-color);
            color: white;
        }

        .btn-batal:hover {
            background-color: #d97706;
            transform: translateY(-2px);
        }

        .btn-chat {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-chat:hover {
            background-color: #7c3aed;
            transform: translateY(-2px);
        }

        .btn-tracking {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-tracking:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-terima {
            background-color: var(--success-color);
            color: white;
        }

        .btn-terima:hover {
            background-color: #059669;
            transform: translateY(-2px);
        }

        .btn-refund {
            background-color: #f59e0b;
            color: white;
        }

        .btn-refund:hover {
            background-color: #d97706;
            transform: translateY(-2px);
        }

        .btn-komplain {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-komplain:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }

        .table-header {
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .search-container {
            position: relative;
            width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
        }

        .btn-export {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #f9fafb;
        }

        th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 14px;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }

        td {
            padding: 18px 20px;
            font-size: 14px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background-color: #f9fafb;
        }

        .order-id {
            font-weight: 600;
            color: var(--info-color);
            font-family: 'Courier New', monospace;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            overflow: hidden;
            background-color: #f3f4f6;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-name {
            font-weight: 500;
        }

        .product-price {
            font-size: 12px;
            color: var(--gray-color);
        }

        .seller-info {
            display: flex;
            flex-direction: column;
        }

        .seller-name {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .seller-rating {
            font-size: 12px;
            color: var(--warning-color);
        }

        .date-time {
            display: flex;
            flex-direction: column;
        }

        .order-date {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .order-time {
            font-size: 12px;
            color: var(--gray-color);
        }

        .resi-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--dark-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-color);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .empty-state p {
            font-size: 14px;
            margin-bottom: 20px;
        }

        /* Timeline Status */
        .status-timeline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            flex: 1;
        }

        .timeline-step.completed .step-icon {
            background-color: var(--primary-color);
            color: white;
        }

        .timeline-step.active .step-icon {
            background-color: var(--info-color);
            color: white;
            animation: pulse 2s infinite;
        }

        .timeline-step.pending .step-icon {
            background-color: #f3f4f6;
            color: var(--gray-color);
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            font-size: 18px;
            z-index: 2;
        }

        .step-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--dark-color);
            text-align: center;
        }

        .step-line {
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background-color: #e5e7eb;
            z-index: 1;
        }

        .step-line.completed {
            background-color: var(--primary-color);
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }

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
            padding: 20px;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray-color);
            cursor: pointer;
            transition: color 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal:hover {
            background-color: #f3f4f6;
            color: var(--danger-color);
        }

        .modal-body {
            padding: 25px;
        }

        .order-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .detail-section {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-item {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
        }

        .detail-label {
            font-weight: 500;
            color: var(--gray-color);
        }

        .detail-value {
            font-weight: 500;
            color: var(--dark-color);
            text-align: right;
        }

        .proof-image {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #e5e7eb;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .order-stat-grid {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .search-container {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 1000px;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .order-stat-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .order-stat-card {
                padding: 20px;
            }
            
            .order-stat-count {
                font-size: 28px;
            }
            
            .modal-content {
                max-height: 80vh;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .order-stat-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                padding: 15px;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            .order-detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="page-title">
                <i class="fas fa-clipboard-check"></i>
                Status Pesanan
            </div>
            <div class="header-actions">
                <a href="dashboard_pembeli.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
                <button class="btn-logout" id="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </div>
    
    <!-- Main Container -->
    <div class="container">
        <!-- Order Stats -->
        <div class="order-stat-grid">
            <div class="order-stat-card stat-all active" onclick="filterOrders('semua')">
                <div class="order-stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="order-stat-count"><?php echo $order_stats['semua']; ?></div>
                <div class="order-stat-label">Semua Pesanan</div>
            </div>
            
            <div class="order-stat-card stat-menunggu" onclick="filterOrders('menunggu_pembayaran')">
                <div class="order-stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="order-stat-count"><?php echo $order_stats['menunggu_pembayaran']; ?></div>
                <div class="order-stat-label">Menunggu Bayar</div>
            </div>
            
            <div class="order-stat-card stat-dibayar" onclick="filterOrders('dibayar')">
                <div class="order-stat-icon">
                    <i class="fas fa-money-check"></i>
                </div>
                <div class="order-stat-count"><?php echo $order_stats['dibayar']; ?></div>
                <div class="order-stat-label">Sudah Dibayar</div>
            </div>
            
            <div class="order-stat-card stat-diproses" onclick="filterOrders('diproses')">
                <div class="order-stat-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="order-stat-count"><?php echo $order_stats['diproses']; ?></div>
                <div class="order-stat-label">Diproses</div>
            </div>
            
            <div class="order-stat-card stat-dikirim" onclick="filterOrders('dikirim')">
                <div class="order-stat-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="order-stat-count"><?php echo $order_stats['dikirim']; ?></div>
                <div class="order-stat-label">Dikirim</div>
            </div>
            
            <div class="order-stat-card stat-selesai" onclick="filterOrders('selesai')">
                <div class="order-stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="order-stat-count"><?php echo $order_stats['selesai']; ?></div>
                <div class="order-stat-label">Selesai</div>
            </div>
            
            <div class="order-stat-card stat-ditolak" onclick="filterOrders('ditolak')">
                <div class="order-stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="order-stat-count"><?php echo $order_stats['ditolak']; ?></div>
                <div class="order-stat-label">Ditolak</div>
            </div>
            
            <div class="order-stat-card stat-dibatalkan" onclick="filterOrders('dibatalkan')">
                <div class="order-stat-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="order-stat-count"><?php echo $order_stats['dibatalkan']; ?></div>
                <div class="order-stat-label">Dibatalkan</div>
            </div>
        </div>
        
        <!-- Orders Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">Daftar Pesanan</div>
                <div class="table-actions">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="search-order" placeholder="Cari pesanan...">
                    </div>
                    <button class="btn-export" onclick="exportOrders()">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Produk</th>
                        <th>Tanggal</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>No Resi</th>
                        <th>Penjual</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body">
                    <!-- Orders will be loaded by JavaScript -->
                </tbody>
            </table>
            
            <div id="orders-empty-state" class="empty-state" style="display: none;">
                <i class="fas fa-inbox"></i>
                <h3>Tidak ada pesanan</h3>
                <p>Belum ada pesanan dengan status ini</p>
                <a href="pesanan.php" class="btn-back" style="display: inline-flex;">
                    <i class="fas fa-shopping-bag"></i> Mulai Belanja
                </a>
            </div>
        </div>
    </div>
    
    <!-- Order Detail Modal -->
    <div class="modal" id="order-detail-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Detail Pesanan</div>
                <button class="close-modal" id="close-detail-modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Order detail will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Upload Proof Modal -->
    <div class="modal" id="upload-proof-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Upload Bukti Pembayaran</div>
                <button class="close-modal" id="close-upload-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="upload-form">
                    <!-- Upload form will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentFilter = 'semua';
        let currentOrders = [];
        
        // Format currency
        function formatCurrency(amount) {
            return 'Rp ' + parseInt(amount).toLocaleString('id-ID');
        }
        
        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Load orders
        function loadOrders(filter = 'semua') {
            const container = document.getElementById('orders-table-body');
            const emptyState = document.getElementById('orders-empty-state');
            
            container.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin"></i> Memuat pesanan...
                    </td>
                </tr>
            `;
            
            emptyState.style.display = 'none';
            
            const url = filter === 'semua' 
                ? `api_status_orders.php`
                : `api_status_orders.php?status=${filter}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    container.innerHTML = '';
                    
                    if (data.success && data.data.length > 0) {
                        currentOrders = data.data;
                        
                        data.data.forEach(order => {
                            const row = createOrderRow(order);
                            container.appendChild(row);
                        });
                    } else {
                        emptyState.style.display = 'block';
                        currentOrders = [];
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = `
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #ef4444;">
                                <i class="fas fa-exclamation-triangle"></i> Error memuat pesanan
                            </td>
                        </tr>
                    `;
                });
        }
        
        // Create order table row
        function createOrderRow(order) {
            const row = document.createElement('tr');
            
            const statusClass = `status-${order.status.replace('_', '-')}`;
            const statusText = getStatusText(order.status);
            
            const date = new Date(order.created_at);
            const orderDate = date.toLocaleDateString('id-ID');
            const orderTime = date.toLocaleTimeString('id-ID', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            row.innerHTML = `
                <td>
                    <span class="order-id">#${order.id_pesanan}</span>
                </td>
                <td>
                    <div class="product-info">
                        <div class="product-image">
                            <img src="${order.foto_produk ? `uploads/produk/${order.foto_produk}` : 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23f3f4f6%22 width=%22100%22 height=%22100%22/%3E%3C/svg%3E'}" 
                                 alt="${order.nama_produk}"
                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23f3f4f6%22 width=%22100%22 height=%22100%22/%3E%3C/svg%3E'">
                        </div>
                        <div>
                            <div class="product-name">${order.nama_produk}</div>
                            <div class="product-price">${formatCurrency(order.harga)}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="date-time">
                        <div class="order-date">${orderDate}</div>
                        <div class="order-time">${orderTime}</div>
                    </div>
                </td>
                <td>${order.quantity}</td>
                <td>${formatCurrency(order.total_harga)}</td>
                <td>
                    <span class="order-status ${statusClass}">${statusText}</span>
                </td>
                <td>
                    ${order.nomor_resi ? `
                        <span class="resi-number">${order.nomor_resi}</span>
                    ` : '-'}
                </td>
                <td>
                    <div class="seller-info">
                        <div class="seller-name">${order.nama_penjual}</div>
                        <div class="seller-rating">
                            <i class="fas fa-star"></i> 4.5
                        </div>
                    </div>
                </td>
                <td>
                    <div class="order-actions">
                        ${getOrderActions(order.status, order.id_pesanan)}
                    </div>
                </td>
            `;
            
            // Add event listeners for action buttons
            setTimeout(() => {
                addOrderActionListeners(row, order);
            }, 100);
            
            return row;
        }
        
        // Get status text in Bahasa Indonesia
        function getStatusText(status) {
            const statusMap = {
                'menunggu_pembayaran': 'MENUNGGU BAYAR',
                'dibayar': 'DIBAYAR',
                'diproses': 'DIPROSES',
                'dikirim': 'DIKIRIM',
                'selesai': 'SELESAI',
                'ditolak': 'DITOLAK',
                'dibatalkan': 'DIBATALKAN'
            };
            
            return statusMap[status] || status.toUpperCase();
        }
        
        // Get order actions based on status
        function getOrderActions(status, orderId) {
            let actions = '';
            
            switch(status) {
                case 'menunggu_pembayaran':
                    actions = `
                        <button class="btn-action btn-upload" data-action="upload" data-order="${orderId}">
                            <i class="fas fa-upload"></i> Upload Bukti
                        </button>
                        <button class="btn-action btn-detail" data-action="detail" data-order="${orderId}">
                            <i class="fas fa-eye"></i> Detail
                        </button>
                        <button class="btn-action btn-batal" data-action="cancel" data-order="${orderId}">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    `;
                    break;
                    
                case 'dibayar':
                    actions = `
                        <button class="btn-action btn-detail" data-action="detail" data-order="${orderId}">
                            <i class="fas fa-eye"></i> Detail
                        </button>
                        <button class="btn-action btn-batal" data-action="cancel" data-order="${orderId}">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    `;
                    break;
                    
                case 'diproses':
                    actions = `
                        <button class="btn-action btn-detail" data-action="detail" data-order="${orderId}">
                            <i class="fas fa-eye"></i> Detail
                        </button>
                        <button class="btn-action btn-chat" data-action="chat" data-order="${orderId}">
                            <i class="fas fa-comment"></i> Chat
                        </button>
                    `;
                    break;
                    
                case 'dikirim':
                    actions = `
                        <button class="btn-action btn-tracking" data-action="tracking" data-order="${orderId}">
                            <i class="fas fa-truck"></i> Lacak
                        </button>
                        <button class="btn-action btn-terima" data-action="receive" data-order="${orderId}">
                            <i class="fas fa-check"></i> Terima
                        </button>
                    `;
                    break;
                    
                case 'selesai':
                    actions = `
                        <button class="btn-action btn-detail" data-action="detail" data-order="${orderId}">
                            <i class="fas fa-eye"></i> Detail
                        </button>
                        <button class="btn-action btn-refund" data-action="refund" data-order="${orderId}">
                            <i class="fas fa-undo"></i> Refund
                        </button>
                    `;
                    break;
                    
                case 'ditolak':
                    actions = `
                        <button class="btn-action btn-detail" data-action="detail" data-order="${orderId}">
                            <i class="fas fa-eye"></i> Detail
                        </button>
                        <button class="btn-action btn-komplain" data-action="complain" data-order="${orderId}">
                            <i class="fas fa-exclamation-triangle"></i> Komplain
                        </button>
                    `;
                    break;
                    
                default:
                    actions = `
                        <button class="btn-action btn-detail" data-action="detail" data-order="${orderId}">
                            <i class="fas fa-eye"></i> Detail
                        </button>
                    `;
            }
            
            return actions;
        }
        
        // Add event listeners to order action buttons
        function addOrderActionListeners(row, order) {
            const buttons = row.querySelectorAll('.btn-action');
            
            buttons.forEach(button => {
                const action = button.getAttribute('data-action');
                const orderId = button.getAttribute('data-order');
                
                button.addEventListener('click', function() {
                    handleOrderAction(action, orderId, order);
                });
            });
        }
        
        // Handle order actions
        function handleOrderAction(action, orderId, order) {
            switch(action) {
                case 'detail':
                    showOrderDetail(orderId);
                    break;
                    
                case 'upload':
                    showUploadForm(orderId);
                    break;
                    
                case 'cancel':
                    cancelOrder(orderId);
                    break;
                    
                case 'chat':
                    window.location.href = `chat.php?penjual_id=${order.id_penjual}`;
                    break;
                    
                case 'tracking':
                    trackOrder(order);
                    break;
                    
                case 'receive':
                    confirmReceive(orderId);
                    break;
                    
                case 'refund':
                    requestRefund(orderId);
                    break;
                    
                case 'complain':
                    sendComplaint(orderId);
                    break;
            }
        }
        
        // Show order detail modal
        function showOrderDetail(orderId) {
            fetch(`api_status_order_detail.php?id_pesanan=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderOrderDetail(data.data);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error memuat detail pesanan');
                });
        }
        
        // Render order detail
        function renderOrderDetail(order) {
            const modalBody = document.getElementById('order-detail-modal').querySelector('.modal-body');
            
            const timeline = createTimeline(order.status);
            const detailContent = createDetailContent(order);
            
            modalBody.innerHTML = `
                <div class="status-timeline">
                    ${timeline}
                </div>
                <div class="order-detail-grid">
                    ${detailContent}
                </div>
            `;
            
            // Show modal
            document.getElementById('order-detail-modal').style.display = 'flex';
        }
        
        // Create timeline based on status
        function createTimeline(status) {
            const steps = [
                { id: 'menunggu_pembayaran', icon: 'fa-clock', label: 'Menunggu Bayar' },
                { id: 'dibayar', icon: 'fa-money-check', label: 'Dibayar' },
                { id: 'diproses', icon: 'fa-cogs', label: 'Diproses' },
                { id: 'dikirim', icon: 'fa-truck', label: 'Dikirim' },
                { id: 'selesai', icon: 'fa-check-circle', label: 'Selesai' }
            ];
            
            const statusIndex = steps.findIndex(step => step.id === status);
            
            let timelineHTML = '';
            
            steps.forEach((step, index) => {
                let stepClass = 'pending';
                let lineClass = '';
                
                if (index < statusIndex) {
                    stepClass = 'completed';
                    lineClass = 'completed';
                } else if (index === statusIndex) {
                    stepClass = 'active';
                }
                
                timelineHTML += `
                    <div class="timeline-step ${stepClass}">
                        <div class="step-icon">
                            <i class="fas ${step.icon}"></i>
                        </div>
                        <div class="step-label">${step.label}</div>
                        ${index < steps.length - 1 ? `
                            <div class="step-line ${lineClass}"></div>
                        ` : ''}
                    </div>
                `;
            });
            
            return timelineHTML;
        }
        
        // Create detail content
        function createDetailContent(order) {
            return `
                <div class="detail-section">
                    <div class="section-title">Informasi Pesanan</div>
                    <div class="detail-item">
                        <span class="detail-label">ID Pesanan:</span>
                        <span class="detail-value">#${order.id_pesanan}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Tanggal Pesanan:</span>
                        <span class="detail-value">${formatDateTime(order.created_at)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="order-status status-${order.status.replace('_', '-')}">
                                ${getStatusText(order.status)}
                            </span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Metode Pembayaran:</span>
                        <span class="detail-value">${order.metode_pembayaran || '-'}</span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <div class="section-title">Informasi Produk</div>
                    <div class="detail-item">
                        <span class="detail-label">Nama Produk:</span>
                        <span class="detail-value">${order.nama_produk}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Harga:</span>
                        <span class="detail-value">${formatCurrency(order.harga)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Quantity:</span>
                        <span class="detail-value">${order.quantity}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Harga:</span>
                        <span class="detail-value">${formatCurrency(order.total_harga)}</span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <div class="section-title">Informasi Pengiriman</div>
                    <div class="detail-item">
                        <span class="detail-label">Nomor Resi:</span>
                        <span class="detail-value">${order.nomor_resi || '-'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Alamat Pengiriman:</span>
                        <span class="detail-value" style="text-align: left; max-width: 200px;">
                            ${order.alamat_pembeli || order.alamat_pengiriman || '-'}
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Nama Penjual:</span>
                        <span class="detail-value">${order.nama_penjual}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Alamat Toko:</span>
                        <span class="detail-value" style="text-align: left; max-width: 200px;">
                            ${order.alamat_penjual ? `<a href="${order.alamat_penjual}" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: linear-gradient(135deg, var(--vintage-paper), var(--vintage-cream)); color: var(--vintage-brown); text-decoration: none; border-radius: 4px; border: 1px solid var(--vintage-border); font-weight: 500; font-size: 13px; transition: all 0.3s;"><i class="fas fa-map-marker-alt"></i> Buka Lokasi</a>` : '-'}
                        </span>
                    </div>
                </div>
                
                ${order.bukti_pembayaran ? `
                <div class="detail-section">
                    <div class="section-title">Bukti Pembayaran</div>
                    <img src="uploads/pembayaran/${order.bukti_pembayaran}" 
                         alt="Bukti Pembayaran" 
                         class="proof-image">
                </div>
                ` : ''}
            `;
        }
        
        // Show upload form
        function showUploadForm(orderId) {
            const modalBody = document.getElementById('upload-proof-modal').querySelector('.modal-body');
            
            modalBody.innerHTML = `
                <div style="max-width: 500px; margin: 0 auto;">
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin-bottom: 10px; color: #1f2937;">Upload Bukti Pembayaran</h3>
                        <p style="color: #6b7280; font-size: 14px;">Silakan upload bukti pembayaran untuk pesanan #${orderId}</p>
                    </div>
                    
                    <form id="uploadProofForm">
                        <input type="hidden" id="upload-order-id" value="${orderId}">
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">
                                Pilih File
                            </label>
                            <div style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 30px; text-align: center; cursor: pointer;"
                                 id="upload-area">
                                <div style="font-size: 48px; color: #9ca3af; margin-bottom: 15px;">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div style="margin-bottom: 10px; color: #374151; font-weight: 500;">
                                    Klik untuk upload bukti pembayaran
                                </div>
                                <div style="font-size: 12px; color: #9ca3af;">
                                    Format: JPG, PNG, PDF (Maks. 5MB)
                                </div>
                            </div>
                            <input type="file" id="proof-file" accept="image/*,.pdf" style="display: none;">
                        </div>
                        
                        <div id="file-preview" style="display: none; margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; 
                                      padding: 15px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-file-image" style="color: #10b981; font-size: 20px;"></i>
                                    <div>
                                        <div id="file-name" style="font-weight: 500;"></div>
                                        <div id="file-size" style="font-size: 12px; color: #9ca3af;"></div>
                                    </div>
                                </div>
                                <button type="button" onclick="removeFile()" 
                                        style="background: none; border: none; color: #ef4444; cursor: pointer;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 30px;">
                            <button type="button" onclick="closeModal('upload-proof-modal')" 
                                    style="flex: 1; padding: 12px; background: #f3f4f6; 
                                           border: 1px solid #d1d5db; border-radius: 6px; 
                                           color: #374151; font-weight: 500; cursor: pointer;">
                                Batal
                            </button>
                            <button type="submit" 
                                    style="flex: 1; padding: 12px; background: #10b981; 
                                           border: none; border-radius: 6px; 
                                           color: white; font-weight: 500; cursor: pointer;">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
            // Show modal
            document.getElementById('upload-proof-modal').style.display = 'flex';
            
            // Setup upload area
            setTimeout(() => {
                setupUploadArea();
            }, 100);
        }
        
        // Setup upload area
        function setupUploadArea() {
            const uploadArea = document.getElementById('upload-area');
            const fileInput = document.getElementById('proof-file');
            
            if (uploadArea && fileInput) {
                uploadArea.addEventListener('click', () => {
                    fileInput.click();
                });
                
                fileInput.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        const file = e.target.files[0];
                        showFilePreview(file);
                    }
                });
                
                // Setup form submission
                const form = document.getElementById('uploadProofForm');
                if (form) {
                    form.addEventListener('submit', handleUploadProof);
                }
            }
        }
        
        // Show file preview
        function showFilePreview(file) {
            const preview = document.getElementById('file-preview');
            const fileName = document.getElementById('file-name');
            const fileSize = document.getElementById('file-size');
            
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            preview.style.display = 'block';
        }
        
        // Remove file
        function removeFile() {
            const fileInput = document.getElementById('proof-file');
            const preview = document.getElementById('file-preview');
            
            fileInput.value = '';
            preview.style.display = 'none';
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Handle upload proof
        function handleUploadProof(e) {
            e.preventDefault();
            
            const orderId = document.getElementById('upload-order-id').value;
            const fileInput = document.getElementById('proof-file');
            
            if (!fileInput.files.length) {
                alert('Silakan pilih file terlebih dahulu');
                return;
            }
            
            const formData = new FormData();
            formData.append('id_pesanan', orderId);
            formData.append('bukti_pembayaran', fileInput.files[0]);
            
            fetch('api_status_upload_proof.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Bukti pembayaran berhasil diupload!');
                    closeModal('upload-proof-modal');
                    loadOrders(currentFilter);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error mengupload bukti pembayaran');
            });
        }
        
        // Cancel order
        function cancelOrder(orderId) {
            if (!confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')) {
                return;
            }
            
            fetch('api_status_cancel_order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id_pesanan: orderId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pesanan berhasil dibatalkan');
                    loadOrders(currentFilter);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error membatalkan pesanan');
            });
        }
        
        // Track order
        function trackOrder(order) {
            if (order.nomor_resi) {
                // Open tracking in new tab
                window.open(`https://tracking.jne.co.id/tracking.php?awb=${order.nomor_resi}`, '_blank');
            } else {
                alert('Nomor resi belum tersedia');
            }
        }
        
        // Confirm receive order
        function confirmReceive(orderId) {
            if (!confirm('Apakah Anda sudah menerima barang dengan kondisi baik?')) {
                return;
            }
            
            fetch('api_status_confirm_receive.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id_pesanan: orderId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pesanan telah diselesaikan. Terima kasih!');
                    loadOrders(currentFilter);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error mengonfirmasi penerimaan');
            });
        }
        
        // Request refund
        function requestRefund(orderId) {
            const reason = prompt('Silakan berikan alasan refund:');
            if (!reason) return;
            
            fetch('api_status_request_refund.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id_pesanan: orderId,
                    alasan: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Permintaan refund telah dikirim. Admin akan menghubungi Anda.');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error mengirim permintaan refund');
            });
        }
        
        // Send complaint
        function sendComplaint(orderId) {
            window.location.href = `help_chat.php?order=${orderId}`;
        }
        
        // Filter orders
        function filterOrders(filter) {
            currentFilter = filter;
            
            // Update active stat card
            document.querySelectorAll('.order-stat-card').forEach(card => {
                card.classList.remove('active');
            });
            
            document.querySelector(`.stat-${filter === 'semua' ? 'all' : filter}`).classList.add('active');
            
            // Load orders with filter
            loadOrders(filter);
        }
        
        // Search orders
        function setupSearch() {
            const searchInput = document.getElementById('search-order');
            
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const rows = document.querySelectorAll('#orders-table-body tr');
                    
                    rows.forEach(row => {
                        const cells = row.querySelectorAll('td');
                        let found = false;
                        
                        cells.forEach(cell => {
                            if (cell.textContent.toLowerCase().includes(searchTerm)) {
                                found = true;
                            }
                        });
                        
                        row.style.display = found ? '' : 'none';
                    });
                });
            }
        }
        
        // Export orders
        function exportOrders() {
            const filterText = currentFilter === 'semua' ? 'Semua' : getStatusText(currentFilter);
            const date = new Date().toLocaleDateString('id-ID');
            
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Laporan Pesanan - " + filterText + " - " + date + "\n\n";
            csvContent += "ID Pesanan,Produk,Tanggal,Qty,Total,Status,No Resi,Penjual\n";
            
            currentOrders.forEach(order => {
                const row = [
                    `#${order.id_pesanan}`,
                    order.nama_produk,
                    formatDate(order.created_at),
                    order.quantity,
                    order.total_harga,
                    getStatusText(order.status),
                    order.nomor_resi || '-',
                    order.nama_penjual
                ];
                csvContent += row.join(",") + "\n";
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `pesanan_${filterText}_${date}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial orders
            loadOrders('semua');
            
            // Setup search
            setupSearch();
            
            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                const detailModal = document.getElementById('order-detail-modal');
                const uploadModal = document.getElementById('upload-proof-modal');
                
                if (event.target === detailModal) {
                    closeModal('order-detail-modal');
                }
                
                if (event.target === uploadModal) {
                    closeModal('upload-proof-modal');
                }
            });
            
            // Close modal buttons
            document.getElementById('close-detail-modal')?.addEventListener('click', function() {
                closeModal('order-detail-modal');
            });
            
            document.getElementById('close-upload-modal')?.addEventListener('click', function() {
                closeModal('upload-proof-modal');
            });
            
            // Logout button
            document.getElementById('logout-btn').addEventListener('click', function() {
                if (confirm('Apakah Anda yakin ingin logout?')) {
                    window.location.href = 'status_pesanan.php?logout=true';
                }
            });
            
            // Auto-refresh every 60 seconds
            setInterval(() => {
                loadOrders(currentFilter);
            }, 60000);
        });
    </script>
</body>
</html>