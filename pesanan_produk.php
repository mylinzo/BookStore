<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id_user'] ?? $_SESSION['id'] ?? 0;

// Get pesanan grouped by penjual
$sql_pesanan = "SELECT p.*, 
                pb.nama_buku, pb.foto_produk, pb.id_penjual,
                u.nama as nama_penjual, u.email as email_penjual
         FROM pesanan p
         JOIN produk pb ON p.id_produk = pb.id_produk
         JOIN users u ON p.id_penjual = u.id_user
         WHERE p.id_pembeli = ?
         ORDER BY u.nama, p.created_at DESC";

$stmt = mysqli_prepare($conn, $sql_pesanan);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pesanan_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Get status warna
function getStatusBadge($status) {
    $badges = [
        'menunggu_pembayaran' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-clock', 'text' => 'Menunggu Pembayaran'],
        'dibayar' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'fa-check-circle', 'text' => 'Dibayar'],
        'diproses' => ['bg' => '#dbeafe', 'color' => '#0c4a6e', 'icon' => 'fa-cog', 'text' => 'Diproses'],
        'dikirim' => ['bg' => '#bfdbfe', 'color' => '#1e40af', 'icon' => 'fa-truck', 'text' => 'Dikirim'],
        'selesai' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'fa-check', 'text' => 'Selesai'],
        'dibatalkan' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-times', 'text' => 'Dibatalkan'],
        'ditolak' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-times-circle', 'text' => 'Ditolak']
    ];
    return $badges[$status] ?? $badges['menunggu_pembayaran'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Aksara-Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10b981;
            --secondary: #8b5cf6;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #9ca3af;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f3f4f6;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: white;
            padding: 20px;
            border-right: 1px solid #e5e7eb;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar h2 {
            color: var(--primary);
            font-size: 18px;
            margin-bottom: 30px;
        }

        .sidebar a {
            display: block;
            color: var(--dark);
            text-decoration: none;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
            transition: color 0.3s;
        }

        .sidebar a:hover, .sidebar a.active {
            color: var(--primary);
            font-weight: 600;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        .header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            background: rgba(16, 185, 129, 0.1);
            padding: 12px 15px;
            border-radius: 8px;
            color: var(--primary);
        }

        .seller-group {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .seller-header {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 15px;
        }

        .seller-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .seller-info h3 {
            color: var(--dark);
            font-size: 16px;
            margin-bottom: 5px;
        }

        .seller-info p {
            font-size: 12px;
            color: var(--gray);
        }

        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 10px;
            align-items: center;
        }

        .order-image {
            width: 80px;
            height: 100px;
            background: #f3f4f6;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            font-size: 32px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .order-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-details {
            flex: 1;
        }

        .order-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .order-meta {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 8px;
        }

        .order-price {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
        }

        .order-status-qty {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .quantity-badge {
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--dark);
        }

        .status-badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .order-date {
            color: var(--gray);
            font-size: 11px;
            margin-top: 8px;
        }

        .order-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-detail {
            background: #dbeafe;
            color: #0c4a6e;
        }

        .btn-detail:hover {
            background: #bfdbfe;
        }

        .btn-cancel {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-cancel:hover {
            background: #fecaca;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
        }

        .empty-state i {
            font-size: 48px;
            color: #e5e7eb;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--gray);
            margin-bottom: 20px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #059669;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            overflow-x: auto;
        }

        .tab {
            padding: 10px 15px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .tab:hover, .tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .order-item {
                flex-direction: column;
            }

            .order-status-qty {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2><i class="fas fa-store"></i> Aksara-Aruna</h2>
        <a href="dashboard_pembeli.php">⌂ Dashboard</a>
        <a href="halaman_pembeli.php">🛍️ Belanja</a>
        <a href="keranjang_produk.php">🛒 Keranjang</a>
        <a href="pesanan.php" class="active">📦 Pesanan</a>
        <a href="chat.php">💬 Chat</a>
        <a href="account.php">👤 Akun</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">
                <i class="fas fa-shopping-bag"></i>
                Pesanan Saya
            </h1>
        </div>

        <?php if (empty($pesanan_list)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Belum Ada Pesanan</h3>
                <p>Anda belum memiliki pesanan. Mulai belanja sekarang!</p>
                <a href="halaman_pembeli.php" class="btn-primary">
                    <i class="fas fa-shopping-bag"></i> Belanja Sekarang
                </a>
            </div>
        <?php else: ?>
            <div class="tabs">
                <button class="tab active" onclick="filterStatus(this, 'semua')">Semua</button>
                <button class="tab" onclick="filterStatus(this, 'menunggu_pembayaran')">Menunggu Pembayaran</button>
                <button class="tab" onclick="filterStatus(this, 'dibayar')">Dibayar</button>
                <button class="tab" onclick="filterStatus(this, 'diproses')">Diproses</button>
                <button class="tab" onclick="filterStatus(this, 'dikirim')">Dikirim</button>
                <button class="tab" onclick="filterStatus(this, 'selesai')">Selesai</button>
            </div>

            <?php 
            // Group by penjual
            $grouped = [];
            foreach ($pesanan_list as $order) {
                $seller = $order['nama_penjual'];
                if (!isset($grouped[$seller])) {
                    $grouped[$seller] = [];
                }
                $grouped[$seller][] = $order;
            }
            
            foreach ($grouped as $seller_name => $orders): 
            ?>
                <div class="seller-group">
                    <div class="seller-header">
                        <div class="seller-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="seller-info">
                            <h3><?php echo htmlspecialchars($seller_name); ?></h3>
                            <p>Toko penjual</p>
                        </div>
                    </div>

                    <?php foreach ($orders as $order): 
                        $badge = getStatusBadge($order['status']);
                    ?>
                        <div class="order-item" data-status="<?php echo $order['status']; ?>">
                            <div class="order-image">
                                <?php if ($order['foto_produk']): ?>
                                    <img src="uploads/produk/<?php echo htmlspecialchars($order['foto_produk']); ?>" 
                                         alt="<?php echo htmlspecialchars($order['nama_buku']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-book"></i>
                                <?php endif; ?>
                            </div>

                            <div class="order-details">
                                <div class="order-title"><?php echo htmlspecialchars($order['nama_buku']); ?></div>
                                <div class="order-meta">
                                    <span>💰 Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></span>
                                    <span> • Kode: <?php echo str_pad($order['id_pesanan'], 5, '0', STR_PAD_LEFT); ?></span>
                                </div>
                                <div class="order-date">
                                    📅 <?php echo date('d M Y H:i', strtotime($order['created_at'])); ?>
                                </div>
                            </div>

                            <div class="order-status-qty">
                                <div class="quantity-badge">
                                    <?php echo $order['quantity']; ?> unit
                                </div>
                                <div class="status-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                                    <i class="fas <?php echo $badge['icon']; ?>"></i>
                                    <?php echo $badge['text']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function filterStatus(btn, status) {
            // Update active tab
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');

            // Filter orders
            document.querySelectorAll('.order-item').forEach(item => {
                if (status === 'semua' || item.getAttribute('data-status') === status) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });

            // Hide empty seller groups
            document.querySelectorAll('.seller-group').forEach(group => {
                const hasVisible = group.querySelector('.order-item[style="display: flex;"], .order-item:not([style*="display: none"])');
                group.style.display = hasVisible ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>
