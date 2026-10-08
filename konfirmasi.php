<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id_user'] ?? $_SESSION['id'] ?? 0;

// ===== SETUP TARIF PENGIRIMAN (Run once) =====
function setupTarifPengiriman($conn) {
    // Check if table exists
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'tarif_pengiriman'");
    if (!$result || mysqli_num_rows($result) == 0) {
        $sql = "CREATE TABLE tarif_pengiriman (
            id_tarif INT PRIMARY KEY AUTO_INCREMENT,
            kurir VARCHAR(50) UNIQUE NOT NULL,
            biaya_pengiriman INT NOT NULL,
            format_resi VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        mysqli_query($conn, $sql);
        
        // Insert default data
        $tarif_data = [
            ['JNE', 15000, '8 digit (12345678)'],
            ['JNT', 12000, '10 digit (1012345678)'],
            ['TIKI', 13000, '13 digit (1234567890123)'],
            ['POS', 10000, '5+ digit'],
            ['SICEPAT', 11000, 'Alphanumeric 10+ char'],
            ['WAHANA', 12000, '9 digit'],
            ['NINJA', 11000, 'Alphanumeric 9+ char'],
            ['LION', 14000, '10 digit']
        ];
        $stmt = $conn->prepare("INSERT INTO tarif_pengiriman (kurir, biaya_pengiriman, format_resi) VALUES (?, ?, ?)");
        foreach ($tarif_data as $data) {
            $stmt->bind_param("sis", $data[0], $data[1], $data[2]);
            $stmt->execute();
        }
        $stmt->close();
    }
    
    // Check if pesanan has biaya_pengiriman column
    $result = mysqli_query($conn, "SHOW COLUMNS FROM pesanan LIKE 'biaya_pengiriman'");
    if (!$result || mysqli_num_rows($result) == 0) {
        mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN biaya_pengiriman INT DEFAULT 0 AFTER kurir");
    }
}

setupTarifPengiriman($conn);

// Handle actions
$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_qty') {
        $id_produk = (int)$_POST['id_produk'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity > 0) {
            $sql = "UPDATE keranjang_produk SET quantity = ? WHERE id_user = ? AND id_produk = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iii", $quantity, $user_id, $id_produk);
            if (mysqli_stmt_execute($stmt)) {
                $pesan = 'success';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'remove') {
        $id_produk = (int)$_POST['id_produk'];
        $sql = "DELETE FROM keranjang_produk WHERE id_user = ? AND id_produk = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $id_produk);
        if (mysqli_stmt_execute($stmt)) {
            $pesan = 'deleted';
        }
        mysqli_stmt_close($stmt);
    } elseif ($action === 'checkout') {
        // read payment method (cash or tf_bank)
        $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';
        $kurir = $_POST['kurir'] ?? '';
        // sanitize choice
        if ($metode_pembayaran !== 'cash' && $metode_pembayaran !== 'tf_bank') {
            $metode_pembayaran = '';
        }
        if (empty($kurir)) {
            $kurir = '';
        }

        // Get tarif pengiriman
        $biaya_pengiriman = 0;
        $stmt_tarif = $conn->prepare("SELECT biaya_pengiriman FROM tarif_pengiriman WHERE kurir = ?");
        $stmt_tarif->bind_param("s", $kurir);
        $stmt_tarif->execute();
        $result_tarif = $stmt_tarif->get_result();
        if ($row_tarif = $result_tarif->fetch_assoc()) {
            $biaya_pengiriman = (int)$row_tarif['biaya_pengiriman'];
        }
        $stmt_tarif->close();

        // Create order
        $sql_cart = "SELECT * FROM keranjang_produk WHERE id_user = ?";
        $stmt = mysqli_prepare($conn, $sql_cart);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        
        if (!empty($cart_items)) {
            $pesanan_ids = [];
            
            // For each seller/product, create separate order
            foreach ($cart_items as $item) {
                $id_produk = $item['id_produk'];
                $id_penjual = $item['id_penjual'];
                $quantity = $item['quantity'];
                
                // Get product price
                $sql_prod = "SELECT harga FROM produk WHERE id_produk = ?";
                $stmt_prod = mysqli_prepare($conn, $sql_prod);
                mysqli_stmt_bind_param($stmt_prod, "i", $id_produk);
                mysqli_stmt_execute($stmt_prod);
                $result_prod = mysqli_stmt_get_result($stmt_prod);
                $prod = mysqli_fetch_assoc($result_prod);
                mysqli_stmt_close($stmt_prod);
                
                if ($prod) {
                    $harga_produk = $prod['harga'] * $quantity;
                    // Total = harga produk + ongkir
                    $total_harga = $harga_produk + $biaya_pengiriman;
                    
                    // Insert pesanan with selected payment method, kurir, and biaya_pengiriman
                    $sql_order = "INSERT INTO pesanan (id_pembeli, id_penjual, id_produk, quantity, total_harga, metode_pembayaran, kurir, biaya_pengiriman, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'menunggu_pembayaran')";
                    $stmt_order = mysqli_prepare($conn, $sql_order);
                    mysqli_stmt_bind_param($stmt_order, "iiiiissi", $user_id, $id_penjual, $id_produk, $quantity, $total_harga, $metode_pembayaran, $kurir, $biaya_pengiriman);
                    mysqli_stmt_execute($stmt_order);
                    $pesanan_ids[] = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt_order);
                    
                    // Reduce stock
                    $sql_update_stok = "UPDATE produk SET stok = stok - ? WHERE id_produk = ?";
                    $stmt_update_stok = mysqli_prepare($conn, $sql_update_stok);
                    mysqli_stmt_bind_param($stmt_update_stok, "ii", $quantity, $id_produk);
                    mysqli_stmt_execute($stmt_update_stok);
                    mysqli_stmt_close($stmt_update_stok);
                }
            }
            
            // Clear cart
            $sql_clear = "DELETE FROM keranjang_produk WHERE id_user = ?";
            $stmt_clear = mysqli_prepare($conn, $sql_clear);
            mysqli_stmt_bind_param($stmt_clear, "i", $user_id);
            mysqli_stmt_execute($stmt_clear);
            mysqli_stmt_close($stmt_clear);
            
            // Redirect ke pesanan_pembeli.php
            header("Location: pesanan_pembeli.php");
            exit;
        }
    }
}

// Get cart items
$sql_cart = "SELECT kp.*, p.nama_buku, p.harga, p.foto_produk, u.nama as nama_penjual 
             FROM keranjang_produk kp
             JOIN produk p ON kp.id_produk = p.id_produk
             JOIN users u ON kp.id_penjual = u.id_user
             WHERE kp.id_user = ?
             ORDER BY u.nama, p.nama_buku";
$stmt = mysqli_prepare($conn, $sql_cart);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Calculate totals
$total_harga = 0;
$total_qty = 0;
foreach ($cart_items as $item) {
    $total_harga += ($item['harga'] * $item['quantity']);
    $total_qty += $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Belanja - Pustaka Aruna</title>
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
        }

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
            width: 250px;
            background-color: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .sidebar-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            text-align: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
        }

        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .sidebar-title i { color: rgba(255, 255, 255, 0.9); }

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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
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
            padding: 16px 20px;
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.025em;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Cart Container */
        .cart-container {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
        }

        .cart-items {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .seller-section {
            margin-bottom: 20px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }

        .seller-header {
            background: var(--background);
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .seller-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .seller-header i { color: var(--primary); }

        .seller-items { padding: 12px; }

        .cart-item {
            display: flex;
            gap: 16px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            background: var(--surface);
            transition: all 0.2s ease;
            align-items: center;
        }

        .cart-item:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .item-image {
            width: 80px;
            height: 100px;
            background: var(--background);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 2rem;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid var(--border);
        }

        .item-image img { width: 100%; height: 100%; object-fit: cover; }

        .item-details { flex: 1; min-width: 0; }

        .item-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            font-size: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .item-seller {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .item-price {
            font-size: 0.875rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 12px;
            min-width: 140px;
        }

        .qty-control {
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 4px;
            background: var(--background);
        }

        .qty-btn {
            width: 28px;
            height: 28px;
            border: none;
            background: var(--surface);
            cursor: pointer;
            color: var(--text-primary);
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover { background: var(--primary-light); color: var(--primary); }

        .qty-input {
            width: 40px;
            text-align: center;
            border: none;
            font-weight: 600;
            background: transparent;
            color: var(--text-primary);
            font-size: 0.9375rem;
        }

        .item-total {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .remove-btn {
            background: var(--error);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.8125rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .remove-btn:hover { background: #dc2626; transform: translateY(-1px); }

        /* Cart Summary */
        .cart-summary {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            position: sticky;
            top: 24px;
            height: fit-content;
        }

        .summary-header {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 6px 0;
            font-size: 0.9375rem;
        }

        .summary-label { color: var(--text-secondary); }
        .summary-value { font-weight: 600; color: var(--text-primary); }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin: 16px 0;
            padding-top: 12px;
            border-top: 2px solid var(--border);
            font-size: 1.25rem;
            font-weight: 700;
        }

        .total-amount { color: var(--primary); }

        .payment-methods {
            margin: 16px 0;
            padding: 12px;
            background: var(--background);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }

        .payment-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: block;
        }

        .payment-options {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.875rem;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .payment-option input[type="radio"] {
            accent-color: var(--primary);
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .shipping-methods {
            margin: 16px 0;
            padding: 12px;
            background: var(--background);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }

        .shipping-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: block;
        }

        .shipping-select {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'Inter', Arial, sans-serif;
            font-size: 0.875rem;
            background: white;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .shipping-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-checkout {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-checkout:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .summary-note {
            text-align: center;
            margin-top: 16px;
            color: var(--text-secondary);
            font-size: 0.8125rem;
            font-style: italic;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 24px;
            font-size: 0.9375rem;
        }

        .empty-state a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
        }

        .empty-state a:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9375rem;
            background: var(--success-bg);
            border-left-color: var(--success);
            color: #166534;
        }

        .alert i { font-size: 1rem; }

        /* Responsive */
        @media (max-width: 1024px) {
            .cart-container { grid-template-columns: 1fr; }
            .cart-summary { position: static; }
        }

        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-title span, .nav-text { display: none; }
            .sidebar-title { justify-content: center; }
            .main-content { margin-left: 70px; }
            .header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .cart-item { flex-direction: column; align-items: flex-start; }
            .item-actions { flex-direction: row; width: 100%; justify-content: space-between; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--background); }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

        /* Focus visible */
        .qty-input:focus-visible, .btn-checkout:focus-visible, .nav-item:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Animation */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .alert { animation: fadeIn 0.25s ease-out; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">
                <i class="fas fa-book-open"></i>
                <span>Pustaka Aruna</span>
            </div>
        </div>
       
            <a href="konfirmasi.php" class="nav-item active">
                <i class="fas fa-shopping-cart"></i>
                <span class="nav-text">Konfirmasi</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if ($pesan === 'success'): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i>
                <span>Konfirmasi diperbarui</span>
            </div>
        <?php elseif ($pesan === 'deleted'): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i>
                <span>Item dihapus dari konfirmasi</span>
            </div>
        <?php endif; ?>

        <div class="header">
            <h1 class="page-title">Konfirmasi Belanja</h1>
            <div class="page-subtitle">
                <i class="fas fa-store"></i>
                <span></span>
            </div>
        </div>

        <?php if (empty($cart_items)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h3>Keranjang Belanja Kosong</h3>
                <p>Belum ada produk di keranjang Anda</p>
                <a href="dashboard_pembeli.php">
                    <i class="fas fa-shopping-bag"></i> Mulai Belanja
                </a>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <!-- Cart Items -->
                <div class="cart-items">
                    <?php 
                    $current_seller = '';
                    foreach ($cart_items as $item): 
                        if ($current_seller !== $item['nama_penjual']):
                            if ($current_seller !== '') echo '</div></div>';
                            echo '<div class="seller-section">';
                            echo '<div class="seller-header">';
                            echo '<i class="fas fa-store"></i>';
                            echo '<h3>' . htmlspecialchars($item['nama_penjual']) . '</h3>';
                            echo '</div>';
                            echo '<div class="seller-items">';
                            $current_seller = $item['nama_penjual'];
                        endif;
                    ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <?php if ($item['foto_produk']): ?>
                                    <img src="uploads/produk/<?php echo htmlspecialchars($item['foto_produk']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['nama_buku']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-book"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['nama_buku']); ?></div>
                                <div class="item-seller">
                                    <i class="fas fa-store"></i>
                                    <?php echo htmlspecialchars($item['nama_penjual']); ?>
                                </div>
                                <div class="item-price">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?> /buku</div>
                            </div>
                            <div class="item-actions">
                                <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                                    <input type="hidden" name="action" value="update_qty">
                                    <input type="hidden" name="id_produk" value="<?php echo $item['id_produk']; ?>">
                                    <div class="qty-control">
                                        <button type="button" class="qty-btn" onclick="decreaseQty(this)">−</button>
                                        <input type="number" class="qty-input" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" onchange="this.form.submit()">
                                        <button type="button" class="qty-btn" onclick="increaseQty(this)">+</button>
                                    </div>
                                </form>
                                <div class="item-total">Rp <?php echo number_format($item['harga'] * $item['quantity'], 0, ',', '.'); ?></div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="id_produk" value="<?php echo $item['id_produk']; ?>">
                                    <button type="submit" class="remove-btn" onclick="return confirm('Hapus item ini?')">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div></div>
                </div>

                <!-- Summary -->
                <div class="cart-summary">
                    <div class="summary-header">
                        <i class="fas fa-receipt"></i>
                        <span>Ringkasan Belanja</span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Jumlah Item</span>
                        <span class="summary-value"><?php echo $total_qty; ?> buku</span>
                    </div>
                    
                    <?php foreach ($cart_items as $item): ?>
                    <div class="summary-item">
                        <span class="summary-label"><?php echo htmlspecialchars(substr($item['nama_buku'], 0, 20)) . (strlen($item['nama_buku']) > 20 ? '...' : ''); ?></span>
                        <span class="summary-value"><?php echo $item['quantity']; ?> x Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></span>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="summary-item" style="border-top: 1px solid #e0e0e0; padding-top: 12px; margin-top: 12px;">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value" id="subtotalDisplay">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">Ongkos Pengiriman</span>
                        <span class="summary-value" id="ongkirDisplay">Rp 0</span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Total:</span>
                        <span class="total-amount" id="totalDisplay">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></span>
                        <input type="hidden" id="baseTotal" value="<?php echo $total_harga; ?>">
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="checkout">
                        
                        <div class="payment-methods">
                            <label class="payment-label">Metode Pembayaran</label>
                            <div class="payment-options">
                                <label class="payment-option">
                                    <input type="radio" name="metode_pembayaran" value="cash" required checked>
                                    <span>Cash</span>
                                </label>
                                <label class="payment-option">
                                    <input type="radio" name="metode_pembayaran" value="tf_bank">
                                    <span>Transfer</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="shipping-methods">
                            <label class="shipping-label">Pilih Kurir Pengiriman</label>
                            <select name="kurir" id="kurirSelect" class="shipping-select" required onchange="updateOngkir()">
                                <option value="">Pilih Kurir</option>
                                <option value="JNE">JNE Express</option>
                                <option value="JNT">J&T Express</option>
                                <option value="TIKI">TIKI</option>
                                <option value="POS">POS Indonesia</option>
                                <option value="SICEPAT">SiCepat</option>
                                <option value="WAHANA">Wahana</option>
                                <option value="NINJA">Ninja Express</option>
                                <option value="LION">Lion Parcel</option>
                            </select>
                            <small class="form-text text-muted" id="formatResiInfo" style="margin-top: 8px; display: none;">
                                <i class="fas fa-info-circle"></i> Format No. Resi: <span id="formatResiText"></span>
                            </small>
                        </div>
                        
                        <button type="submit" class="btn-checkout">
                            <i class="fas fa-arrow-right"></i> Lanjutkan ke Checkout
                        </button>
                    </form>
                    
                    <p class="summary-note">
                        <i class="fas fa-info-circle"></i>
                        Setiap pembelian adalah petualangan baru
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function increaseQty(btn) {
            const input = btn.previousElementSibling;
            input.value = parseInt(input.value) + 1;
            input.form.submit();
        }

        function decreaseQty(btn) {
            const input = btn.nextElementSibling;
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                input.form.submit();
            }
        }

        // ===== UPDATE ONGKIR & TOTAL =====
        function updateOngkir() {
            const kurirSelect = document.getElementById('kurirSelect');
            const kurir = kurirSelect.value;
            const baseTotal = parseInt(document.getElementById('baseTotal').value);
            
            if (!kurir) {
                document.getElementById('ongkirDisplay').textContent = 'Rp 0';
                document.getElementById('totalDisplay').textContent = 'Rp ' + formatRupiah(baseTotal);
                document.getElementById('formatResiInfo').style.display = 'none';
                return;
            }

            // Fetch tarif pengiriman
            fetch('api_get_tarif.php?kurir=' + encodeURIComponent(kurir))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const biaya = data.data.biaya_pengiriman;
                        const total = baseTotal + biaya;
                        
                        // Update display
                        document.getElementById('ongkirDisplay').textContent = 'Rp ' + formatRupiah(biaya);
                        document.getElementById('totalDisplay').textContent = 'Rp ' + formatRupiah(total);
                        
                        // Show format resi info
                        document.getElementById('formatResiText').textContent = data.data.format_resi;
                        document.getElementById('formatResiInfo').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching tarif:', error);
                    document.getElementById('ongkirDisplay').textContent = 'Error';
                });
        }

        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 0,
                useGrouping: true
            }).format(amount);
        }

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-8px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>