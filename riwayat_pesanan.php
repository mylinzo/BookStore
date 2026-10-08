<?php
/**
 * Halaman Riwayat Pesanan Pembeli - Integrasi dengan penjual
 * Menampilkan semua pesanan dengan status real-time
 */

session_start();
include 'koneksi.php';

// Check authentication
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pembeli') {
    header('Location: login.php');
    exit;
}

$id_pembeli = $_SESSION['id_user'];
$nama_pembeli = $_SESSION['nama'];

// Create notifikasi table if not exists
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifikasi (
    id_notifikasi INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    tipe VARCHAR(50) DEFAULT 'info',
    pesan TEXT,
    data_ref INT,
    status ENUM('unread', 'read') DEFAULT 'unread',
    url_redirect VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    KEY id_user (id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Check & fix notifikasi table
$check_col = @mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='notifikasi' AND COLUMN_NAME='tipe' AND TABLE_SCHEMA=DATABASE()");
if (!$check_col || mysqli_num_rows($check_col) == 0) {
    @mysqli_query($conn, "DROP TABLE IF EXISTS notifikasi");
    @mysqli_query($conn, "CREATE TABLE notifikasi (
        id_notifikasi INT PRIMARY KEY AUTO_INCREMENT,
        id_user INT NOT NULL,
        tipe VARCHAR(50) DEFAULT 'info',
        pesan TEXT,
        data_ref INT,
        status ENUM('unread', 'read') DEFAULT 'unread',
        url_redirect VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL,
        KEY id_user (id_user)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan Saya - Aksara-Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
        }

        .navbar {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-brand {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-back {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header-section h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .header-section p {
            color: #666;
            font-size: 14px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }

        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .filter-tab.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-left: 5px solid;
        }

        .order-card.status-menunggu_pembayaran {
            border-left-color: #f59e0b;
        }

        .order-card.status-dibayar {
            border-left-color: #3b82f6;
        }

        .order-card.status-diproses {
            border-left-color: #8b5cf6;
        }

        .order-card.status-dikirim {
            border-left-color: #06b6d4;
        }

        .order-card.status-selesai {
            border-left-color: #10b981;
        }

        .order-card.status-ditolak {
            border-left-color: #ef4444;
        }

        .order-card:hover {
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            transform: translateY(-8px);
        }

        .card-header {
            padding: 18px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e5e7eb 100%);
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }

        .order-id {
            font-weight: 700;
            color: #667eea;
            font-size: 15px;
        }

        .order-date {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-menunggu_pembayaran {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-dibayar {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-diproses {
            background: #ddd6fe;
            color: #5b21b6;
        }

        .badge-dikirim {
            background: #cffafe;
            color: #164e63;
        }

        .badge-selesai {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-ditolak {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .card-body {
            padding: 18px;
        }

        .product-info {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .product-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
        }

        .seller-info {
            margin-bottom: 12px;
        }

        .seller-label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .seller-name {
            font-weight: 600;
            font-size: 13px;
            color: #333;
        }

        .tracking-section {
            background: #f0fdf4;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: none;
        }

        .tracking-section.show {
            display: block;
        }

        .tracking-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .tracking-number {
            font-weight: 600;
            color: #059669;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }

        .price-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .price-label {
            color: #666;
            font-size: 12px;
        }

        .price-value {
            font-weight: 700;
            color: #667eea;
            font-size: 15px;
        }

        .timeline {
            margin-bottom: 12px;
        }

        .timeline-item {
            display: flex;
            gap: 8px;
            font-size: 11px;
            margin-bottom: 6px;
        }

        .timeline-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #e5e7eb;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .timeline-item.completed .timeline-dot {
            background: #10b981;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
        }

        .timeline-item.active .timeline-dot {
            background: #667eea;
            animation: pulse 1.5s infinite;
        }

        .timeline-text {
            color: #666;
        }

        .timeline-item.completed .timeline-text {
            color: #059669;
            font-weight: 600;
        }

        .timeline-item.active .timeline-text {
            color: #667eea;
            font-weight: 600;
        }

        .card-actions {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-detail {
            background: #667eea;
            color: white;
        }

        .btn-detail:hover {
            background: #5568d3;
        }

        .btn-chat {
            background: #f3f4f6;
            color: #333;
        }

        .btn-chat:hover {
            background: #e5e7eb;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            display: block;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @media (max-width: 768px) {
            .orders-grid {
                grid-template-columns: 1fr;
            }

            .filter-tabs {
                gap: 8px;
            }

            .filter-tab {
                padding: 8px 15px;
                font-size: 12px;
            }

            .header-section h1 {
                font-size: 20px;
            }

            .navbar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-history"></i> Riwayat Pesanan
        </div>
        <div class="navbar-actions">
            <a href="dashboard_pembeli.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header-section">
            <h1>Riwayat Pesanan Anda</h1>
            <p>Pantau status pesanan dan lihat update real-time dari penjual</p>
        </div>

        <!-- Filters -->
        <div class="filter-tabs">
            <button class="filter-tab active" onclick="filterOrders('semua')">
                <i class="fas fa-list"></i> Semua
            </button>
            <button class="filter-tab" onclick="filterOrders('menunggu_pembayaran')">
                <i class="fas fa-hourglass"></i> Menunggu Pembayaran
            </button>
            <button class="filter-tab" onclick="filterOrders('dibayar')">
                <i class="fas fa-credit-card"></i> Sudah Dibayar
            </button>
            <button class="filter-tab" onclick="filterOrders('diproses')">
                <i class="fas fa-cog"></i> Diproses
            </button>
            <button class="filter-tab" onclick="filterOrders('dikirim')">
                <i class="fas fa-truck"></i> Dikirim
            </button>
            <button class="filter-tab" onclick="filterOrders('selesai')">
                <i class="fas fa-check"></i> Selesai
            </button>
            <button class="filter-tab" onclick="filterOrders('ditolak')">
                <i class="fas fa-times"></i> Ditolak
            </button>
        </div>

        <!-- Orders Grid -->
        <div class="orders-grid" id="ordersContainer">
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin"></i> Memuat pesanan...
            </div>
        </div>
    </div>

    <script>
        let currentFilter = 'semua';
        
        document.addEventListener('DOMContentLoaded', function() {
            loadOrders('semua');
            
            // Auto-refresh setiap 20 detik untuk update status real-time
            setInterval(() => loadOrders(currentFilter), 20000);
        });

        function loadOrders(filter) {
            currentFilter = filter;
            const container = document.getElementById('ordersContainer');
            container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Memuat pesanan...</div>';

            const url = filter === 'semua' ? 'api_pesanan_pembeli.php?action=list' : `api_pesanan_pembeli.php?action=by_status&status=${filter}`;
            
            fetch(url, { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.length > 0) {
                        renderOrders(data.data);
                    } else {
                        container.innerHTML = '<div style="grid-column: 1 / -1;"><div class="empty-state"><i class="fas fa-inbox"></i><h3>Belum ada pesanan</h3><p>Mulai belanja sekarang untuk membuat pesanan pertama Anda</p></div></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<div style="grid-column: 1 / -1;"><div class="empty-state" style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i><h3>Error</h3><p>Gagal memuat pesanan. Coba lagi nanti.</p></div></div>';
                });
        }

        function renderOrders(orders) {
            const container = document.getElementById('ordersContainer');
            let html = '';

            orders.forEach(order => {
                const statusClass = `status-${order.status.replace('_', '-')}`;
                const badgeClass = `badge-${order.status.replace('_', '-')}`;
                const tanggal = new Date(order.created_at).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });

                const statusText = getStatusText(order.status);
                const timeline = getTimeline(order.status);

                html += `
                    <div class="order-card ${statusClass}">
                        <div class="card-header">
                            <div>
                                <div class="order-id">#${order.id_pesanan}</div>
                                <div class="order-date">${tanggal}</div>
                            </div>
                            <span class="status-badge ${badgeClass}">${statusText}</span>
                        </div>
                        <div class="card-body">
                            <div class="product-info">
                                <div class="product-title">${order.nama_buku}</div>
                                <div class="product-meta">
                                    <span>Qty: ${order.quantity}</span>
                                    <span>Rp ${parseInt(order.harga_satuan).toLocaleString('id-ID')}</span>
                                </div>
                            </div>

                            <div class="seller-info">
                                <div class="seller-label">Penjual</div>
                                <div class="seller-name">${order.nama_penjual}</div>
                            </div>

                            ${order.nomor_resi ? `
                                <div class="tracking-section show">
                                    <div class="tracking-label"><i class="fas fa-box"></i> Resi</div>
                                    <div class="tracking-number">${order.nomor_resi}</div>
                                </div>
                            ` : ''}

                            <div class="timeline">
                                ${timeline}
                            </div>

                            <div class="price-section">
                                <span class="price-label">Total</span>
                                <span class="price-value">Rp ${parseInt(order.total_harga).toLocaleString('id-ID')}</span>
                            </div>

                            <div class="card-actions">
                                <button class="btn-small btn-detail" onclick="viewDetail(${order.id_pesanan})">
                                    <i class="fas fa-eye"></i> Detail
                                </button>
                                <button class="btn-small btn-chat" onclick="contactSeller(${order.id_penjual})">
                                    <i class="fas fa-comments"></i> Chat
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function getStatusText(status) {
            const map = {
                'menunggu_pembayaran': 'Menunggu Pembayaran',
                'dibayar': 'Sudah Dibayar',
                'diproses': 'Diproses',
                'dikirim': 'Sedang Dikirim',
                'selesai': 'Selesai',
                'ditolak': 'Ditolak',
                'dibatalkan': 'Dibatalkan'
            };
            return map[status] || status;
        }

        function getTimeline(status) {
            const steps = [
                {status: 'menunggu_pembayaran', label: 'Tunggu', icon: 'fa-hourglass'},
                {status: 'dibayar', label: 'Bayar', icon: 'fa-credit-card'},
                {status: 'diproses', label: 'Proses', icon: 'fa-cog'},
                {status: 'dikirim', label: 'Kirim', icon: 'fa-truck'},
                {status: 'selesai', label: 'Selesai', icon: 'fa-check'}
            ];

            const statuses = ['menunggu_pembayaran', 'dibayar', 'diproses', 'dikirim', 'selesai'];
            const currentIndex = statuses.indexOf(status);

            let html = '';
            steps.forEach(step => {
                const stepIndex = statuses.indexOf(step.status);
                let className = '';
                
                if (stepIndex < currentIndex) {
                    className = 'completed';
                } else if (stepIndex === currentIndex) {
                    className = 'active';
                }

                html += `
                    <div class="timeline-item ${className}">
                        <div class="timeline-dot">
                            ${className === 'completed' ? '<i class="fas fa-check" style="font-size: 8px;"></i>' : ''}
                        </div>
                        <span class="timeline-text">${step.label}</span>
                    </div>
                `;
            });

            return html;
        }

        function filterOrders(filter) {
            // Update button status
            document.querySelectorAll('.filter-tab').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            loadOrders(filter);
        }

        function viewDetail(orderId) {
            alert('Fitur detail pesanan segera hadir!');
        }

        function contactSeller(sellerId) {
            // Redirect to chat page
            window.location.href = `penjual_chat.php?id_penjual=${sellerId}`;
        }
    </script>
</body>
</html>
