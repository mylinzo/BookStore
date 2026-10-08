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
    header("location: dashboard_pembeli.php");
    exit;
}

// ========== UPDATE STATUS ONLINE ==========
$update_sql = "UPDATE users SET status = 'online' WHERE id_user = ?";
if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
    mysqli_stmt_bind_param($update_stmt, "i", $user_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
}

// ========== HANDLE ADD PRODUCT FROM DASHBOARD ==========
if (isset($_GET['add_produk']) && !empty($_GET['add_produk'])) {
    $id_produk = (int)$_GET['add_produk'];
    $id_penjual = isset($_GET['id_penjual']) ? (int)$_GET['id_penjual'] : 0;
    
    // Cek apakah produk sudah ada di keranjang
    $check_sql = "SELECT id_keranjang, quantity FROM keranjang 
                  WHERE id_user = ? AND id_buku = ? AND status = 'aktif'";
    $keranjang_exists = false;
    $existing_id = 0;
    $existing_qty = 0;
    
    if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $id_produk);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if ($check_row = mysqli_fetch_assoc($check_result)) {
            $keranjang_exists = true;
            $existing_id = $check_row['id_keranjang'];
            $existing_qty = $check_row['quantity'];
        }
        mysqli_stmt_close($check_stmt);
    }
    
    // Cek stok produk terlebih dahulu
    $stok_sql = "SELECT COALESCE(stok, 0) as stok FROM buku WHERE id_buku = ?";
    $available_stok = 0;
    
    if ($stok_stmt = mysqli_prepare($conn, $stok_sql)) {
        mysqli_stmt_bind_param($stok_stmt, "i", $id_produk);
        mysqli_stmt_execute($stok_stmt);
        mysqli_stmt_bind_result($stok_stmt, $available_stok);
        mysqli_stmt_fetch($stok_stmt);
        mysqli_stmt_close($stok_stmt);
    }
    
    if ($available_stok > 0) {
        if ($keranjang_exists) {
            // Update quantity jika sudah ada
            if ($existing_qty < $available_stok) {
                $update_qty_sql = "UPDATE keranjang SET quantity = quantity + 1 WHERE id_keranjang = ?";
                if ($update_qty_stmt = mysqli_prepare($conn, $update_qty_sql)) {
                    mysqli_stmt_bind_param($update_qty_stmt, "i", $existing_id);
                    mysqli_stmt_execute($update_qty_stmt);
                    mysqli_stmt_close($update_qty_stmt);
                }
            }
        } else {
            // Insert produk baru ke keranjang
            $insert_sql = "INSERT INTO keranjang (id_user, id_buku, quantity, status, tanggal_ditambahkan) 
                          VALUES (?, ?, 1, 'aktif', NOW())";
            if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                mysqli_stmt_bind_param($insert_stmt, "ii", $user_id, $id_produk);
                mysqli_stmt_execute($insert_stmt);
                mysqli_stmt_close($insert_stmt);
            }
        }
    }
    
    // Redirect tanpa parameter untuk menghindari double submission
    header("location: keranjang.php");
    exit;
}

// ========== QUERY DATA KERANJANG ==========
// Ambil data keranjang user
// Query dengan error handling untuk kolom yang mungkin tidak ada
$sql_keranjang = "SELECT k.id_keranjang, k.id_buku, k.quantity, 
                         b.judul, b.penulis, b.harga, b.gambar, 
                         COALESCE(b.stok, 0) as stok
                  FROM keranjang k
                  JOIN buku b ON k.id_buku = b.id_buku
                  WHERE k.id_user = ? AND k.status = 'aktif'
                  ORDER BY k.tanggal_ditambahkan DESC";
$items_keranjang = [];
$total_harga = 0;
$total_items = 0;

if ($stmt = mysqli_prepare($conn, $sql_keranjang)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $row['subtotal'] = $row['harga'] * $row['quantity'];
        $total_harga += $row['subtotal'];
        $total_items += $row['quantity'];
        $items_keranjang[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// ========== HANDLE ACTIONS KERANJANG ==========
$pesan_success = '';
$pesan_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'update_quantity') {
            $keranjang_id = isset($_POST['keranjang_id']) ? $_POST['keranjang_id'] : 0;
            $new_quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            
            if ($new_quantity > 0) {
                // Cek stok buku
                $check_stock_sql = "SELECT COALESCE(b.stok, 0) as stok FROM keranjang k 
                                    JOIN buku b ON k.id_buku = b.id_buku 
                                    WHERE k.id_keranjang = ?";
                if ($check_stmt = mysqli_prepare($conn, $check_stock_sql)) {
                    mysqli_stmt_bind_param($check_stmt, "i", $keranjang_id);
                    mysqli_stmt_execute($check_stmt);
                    mysqli_stmt_bind_result($check_stmt, $stok);
                    mysqli_stmt_fetch($check_stmt);
                    mysqli_stmt_close($check_stmt);
                    
                    if ($new_quantity <= $stok) {
                        $update_sql = "UPDATE keranjang SET quantity = ? WHERE id_keranjang = ? AND id_user = ?";
                        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                            mysqli_stmt_bind_param($update_stmt, "iii", $new_quantity, $keranjang_id, $user_id);
                            if (mysqli_stmt_execute($update_stmt)) {
                                $pesan_success = "Jumlah item berhasil diupdate.";
                            }
                            mysqli_stmt_close($update_stmt);
                        }
                    } else {
                        $pesan_error = "Stok tidak mencukupi. Stok tersedia: " . $stok;
                    }
                }
            }
        }
        elseif ($action == 'remove_item') {
            $keranjang_id = isset($_POST['keranjang_id']) ? $_POST['keranjang_id'] : 0;
            
            $delete_sql = "DELETE FROM keranjang WHERE id_keranjang = ? AND id_user = ?";
            if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                mysqli_stmt_bind_param($delete_stmt, "ii", $keranjang_id, $user_id);
                if (mysqli_stmt_execute($delete_stmt)) {
                    $pesan_success = "Item berhasil dihapus dari keranjang.";
                }
                mysqli_stmt_close($delete_stmt);
            }
        }
        elseif ($action == 'clear_cart') {
            $delete_all_sql = "DELETE FROM keranjang WHERE id_user = ? AND status = 'aktif'";
            if ($delete_all_stmt = mysqli_prepare($conn, $delete_all_sql)) {
                mysqli_stmt_bind_param($delete_all_stmt, "i", $user_id);
                if (mysqli_stmt_execute($delete_all_stmt)) {
                    $pesan_success = "Keranjang berhasil dikosongkan.";
                }
                mysqli_stmt_close($delete_all_stmt);
            }
        }
        elseif ($action == 'checkout') {
            // Proses checkout
            if (count($items_keranjang) > 0) {
                // Mulai transaksi
                mysqli_begin_transaction($conn);
                
                try {
                    // 1. Buat transaksi baru
                    $transaksi_sql = "INSERT INTO transaksi (id_pembeli, total_harga, status, tanggal) 
                                      VALUES (?, ?, 'menunggu_pembayaran', NOW())";
                    if ($transaksi_stmt = mysqli_prepare($conn, $transaksi_sql)) {
                        mysqli_stmt_bind_param($transaksi_stmt, "id", $user_id, $total_harga);
                        mysqli_stmt_execute($transaksi_stmt);
                        $transaksi_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($transaksi_stmt);
                        
                        // 2. Simpan detail transaksi
                        $detail_sql = "INSERT INTO detail_transaksi (id_transaksi, id_buku, quantity, harga_satuan, subtotal) 
                                       VALUES (?, ?, ?, ?, ?)";
                        $detail_stmt = mysqli_prepare($conn, $detail_sql);
                        
                        foreach ($items_keranjang as $item) {
                            $subtotal = $item['harga'] * $item['quantity'];
                            mysqli_stmt_bind_param($detail_stmt, "iiidd", 
                                $transaksi_id, 
                                $item['id_buku'], 
                                $item['quantity'], 
                                $item['harga'], 
                                $subtotal
                            );
                            mysqli_stmt_execute($detail_stmt);
                            
                            // 3. Update stok buku
                            $update_stok_sql = "UPDATE buku SET stok = stok - ? WHERE id_buku = ?";
                            $update_stok_stmt = mysqli_prepare($conn, $update_stok_sql);
                            mysqli_stmt_bind_param($update_stok_stmt, "ii", $item['quantity'], $item['id_buku']);
                            mysqli_stmt_execute($update_stok_stmt);
                            mysqli_stmt_close($update_stok_stmt);
                        }
                        mysqli_stmt_close($detail_stmt);
                        
                        // 4. Update status keranjang
                        $update_keranjang_sql = "UPDATE keranjang SET status = 'checkout' WHERE id_user = ? AND status = 'aktif'";
                        $update_keranjang_stmt = mysqli_prepare($conn, $update_keranjang_sql);
                        mysqli_stmt_bind_param($update_keranjang_stmt, "i", $user_id);
                        mysqli_stmt_execute($update_keranjang_stmt);
                        mysqli_stmt_close($update_keranjang_stmt);
                        
                        // Commit transaksi
                        mysqli_commit($conn);
                        
                        $pesan_success = "Checkout berhasil! Pesanan Anda telah dibuat dengan ID: A" . str_pad($transaksi_id, 3, '0', STR_PAD_LEFT);
                        
                        // Redirect setelah 2 detik
                        header("refresh:2;url=pesanan.php");
                    }
                } catch (Exception $e) {
                    // Rollback jika ada error
                    mysqli_rollback($conn);
                    $pesan_error = "Terjadi kesalahan saat checkout. Silakan coba lagi.";
                }
            } else {
                $pesan_error = "Keranjang kosong. Tambahkan item terlebih dahulu.";
            }
        }
        
        // Redirect untuk refresh data
        if (!empty($pesan_success) || !empty($pesan_error)) {
            $redirect_url = "keranjang.php";
            if (!empty($pesan_success)) {
                $redirect_url .= "?success=" . urlencode($pesan_success);
            }
            if (!empty($pesan_error)) {
                $redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . "error=" . urlencode($pesan_error);
            }
            header("location: " . $redirect_url);
            exit;
        }
    }
}

// Hitung total pesanan untuk badge
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

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang | Toko Buku Online Premium</title>
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

        /* Cart Stats */
        .cart-stats {
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

        .stat-card.items {
            border-top-color: var(--primary-color);
        }

        .stat-card.total {
            border-top-color: var(--success-color);
        }

        .stat-card.discount {
            border-top-color: var(--accent-color);
        }

        .stat-card.shipping {
            border-top-color: var(--info-color);
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

        .stat-icon.items {
            background: var(--gradient-primary);
        }

        .stat-icon.total {
            background: var(--gradient-success);
        }

        .stat-icon.discount {
            background: linear-gradient(135deg, #f72585 0%, #7209b7 100%);
        }

        .stat-icon.shipping {
            background: var(--gradient-warning);
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

        /* Cart Table */
        .cart-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }

        .cart-header {
            background: var(--gradient-primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-title {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .clear-cart-btn {
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

        .clear-cart-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table thead {
            background: #f8f9ff;
        }

        .cart-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid var(--border-color);
        }

        .cart-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .product-image {
            width: 60px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            background: linear-gradient(135deg, #f0f4ff 0%, #e6f7ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .product-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .product-details p {
            font-size: 0.85rem;
            color: var(--text-light);
            margin: 0;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qty-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--light-color);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .qty-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .qty-input {
            width: 50px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            padding: 5px;
            font-size: 0.9rem;
        }

        .price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color);
        }

        .subtotal {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .remove-btn {
            background: none;
            border: none;
            color: var(--danger-color);
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
            padding: 5px;
        }

        .remove-btn:hover {
            transform: scale(1.2);
        }

        /* Cart Summary */
        .cart-summary {
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

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px dashed var(--border-color);
        }

        .summary-label {
            font-size: 0.95rem;
            color: var(--text-light);
        }

        .summary-value {
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid var(--border-color);
        }

        .total-label {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .total-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        /* Cart Actions */
        .cart-actions {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }

        .btn-continue {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-continue:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-checkout {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-checkout:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
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

        .btn-shopping {
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

        .btn-shopping:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            color: white;
        }

        /* Success/Error Alert */
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #065f46;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #7f1d1d;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            
            .cart-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .cart-table {
                display: block;
                overflow-x: auto;
            }
            
            .cart-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            .cart-stats {
                grid-template-columns: 1fr;
            }
            
            .cart-table thead {
                display: none;
            }
            
            .cart-table tr {
                display: block;
                margin-bottom: 20px;
                border: 1px solid var(--border-color);
                border-radius: 10px;
                padding: 15px;
            }
            
            .cart-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid var(--border-color);
            }
            
            .cart-table td:last-child {
                border-bottom: none;
            }
            
            .cart-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--dark-color);
                margin-right: 10px;
            }
            
            .product-info {
                width: 100%;
            }
            
            .quantity-control {
                justify-content: flex-end;
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
            
            .product-info {
                flex-direction: column;
                text-align: center;
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
            
            <a href="keranjang.php" class="nav-item active">
                <div class="nav-icon"><i class="fas fa-shopping-bag"></i></div>
                <span class="nav-text">Keranjang</span>
                <?php if ($total_items > 0): ?>
                <span class="nav-badge"><?php echo $total_items; ?></span>
                <?php endif; ?>
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
            
            <a href="keranjang.php?logout=true" class="nav-item" style="color: var(--danger-color);">
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
            <i class="fas fa-shopping-bag"></i>
            <div>
                <h1 style="font-size: 1.8rem; margin: 0;">Keranjang</h1>
                <p style="font-size: 0.9rem; color: var(--text-light); margin: 5px 0 0 0;">Kelola item belanja Anda</p>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success']) && !empty($_GET['success'])): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error']) && !empty($_GET['error'])): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Cart Stats -->
        <div class="cart-stats">
            <div class="stat-card items">
                <div class="stat-icon items">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_items; ?></h3>
                    <p>Total Item</p>
                </div>
            </div>
            
            <div class="stat-card total">
                <div class="stat-icon total">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3>Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></h3>
                    <p>Total Harga</p>
                </div>
            </div>
            
            <div class="stat-card discount">
                <div class="stat-icon discount">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="stat-info">
                    <h3>Rp 0</h3>
                    <p>Diskon</p>
                </div>
            </div>
            
            <div class="stat-card shipping">
                <div class="stat-icon shipping">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="stat-info">
                    <h3>Rp 0</h3>
                    <p>Ongkir</p>
                </div>
            </div>
        </div>
        
        <!-- Cart Items -->
        <?php if (count($items_keranjang) > 0): ?>
        <div class="cart-container">
            <div class="cart-header">
                <h3 class="cart-title">Item Keranjang (<?php echo count($items_keranjang); ?>)</h3>
                <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengosongkan keranjang?')">
                    <input type="hidden" name="action" value="clear_cart">
                    <button type="submit" class="clear-cart-btn">
                        <i class="fas fa-trash-alt"></i> Kosongkan Keranjang
                    </button>
                </form>
            </div>
            
            <table class="cart-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Produk</th>
                        <th style="width: 15%;">Harga</th>
                        <th style="width: 20%;">Jumlah</th>
                        <th style="width: 15%;">Subtotal</th>
                        <th style="width: 10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items_keranjang as $item): ?>
                    <tr>
                        <td data-label="Produk">
                            <div class="product-info">
                                <div class="product-image">
                                    <?php if (!empty($item['gambar'])): ?>
                                        <img src="/assets/img/<?php echo htmlspecialchars($item['gambar']); ?>" alt="<?php echo htmlspecialchars($item['judul']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-book" style="font-size: 2rem; color: #4361ee;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="product-details">
                                    <h4><?php echo htmlspecialchars($item['judul']); ?></h4>
                                    <p><?php echo htmlspecialchars($item['penulis']); ?></p>
                                    <p style="font-size: 0.8rem; color: #10b981;">
                                        <i class="fas fa-check-circle"></i> Stok: <?php echo $item['stok']; ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td data-label="Harga">
                            <div class="price">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></div>
                        </td>
                        <td data-label="Jumlah">
                            <form method="POST" class="quantity-form">
                                <input type="hidden" name="action" value="update_quantity">
                                <input type="hidden" name="keranjang_id" value="<?php echo $item['id_keranjang']; ?>">
                                <div class="quantity-control">
                                    <button type="button" class="qty-btn minus" onclick="updateQuantity(<?php echo $item['id_keranjang']; ?>, 'decrease')">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" name="quantity" class="qty-input" 
                                           value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stok']; ?>"
                                           id="qty-<?php echo $item['id_keranjang']; ?>"
                                           onchange="updateQuantityInput(<?php echo $item['id_keranjang']; ?>)">
                                    <button type="button" class="qty-btn plus" onclick="updateQuantity(<?php echo $item['id_keranjang']; ?>, 'increase')">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                        </td>
                        <td data-label="Subtotal">
                            <div class="subtotal">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></div>
                        </td>
                        <td data-label="Aksi">
                            <form method="POST" onsubmit="return confirm('Hapus item ini dari keranjang?')">
                                <input type="hidden" name="action" value="remove_item">
                                <input type="hidden" name="keranjang_id" value="<?php echo $item['id_keranjang']; ?>">
                                <button type="submit" class="remove-btn">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Cart Summary -->
        <div class="cart-summary">
            <h3 class="summary-title">Ringkasan Belanja</h3>
            
            <div class="summary-row">
                <span class="summary-label">Subtotal (<?php echo $total_items; ?> item):</span>
                <span class="summary-value">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></span>
            </div>
            
            <div class="summary-row">
                <span class="summary-label">Diskon:</span>
                <span class="summary-value">- Rp 0</span>
            </div>
            
            <div class="summary-row">
                <span class="summary-label">Biaya Pengiriman:</span>
                <span class="summary-value">Rp 0</span>
            </div>
            
            <div class="summary-total">
                <span class="total-label">Total Pembayaran:</span>
                <span class="total-value">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></span>
            </div>
        </div>
        
        <!-- Cart Actions -->
        <div class="cart-actions">
            <a href="buku.php" class="btn-continue">
                <i class="fas fa-arrow-left"></i> Lanjutkan Belanja
            </a>
            
            <form method="POST">
                <input type="hidden" name="action" value="checkout">
                <button type="submit" class="btn-checkout" onclick="return confirm('Apakah Anda yakin ingin melanjutkan checkout?')">
                    <i class="fas fa-credit-card"></i> Lanjut ke Checkout
                </button>
            </form>
        </div>
        
        <?php else: ?>
            <!-- Empty Cart State -->
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h4>Keranjang Belanja Kosong</h4>
                <p>Belum ada item di keranjang Anda. Mulailah berbelanja buku favorit Anda sekarang!</p>
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
        
        // Quantity update functions
        function updateQuantity(cartId, action) {
            const input = document.getElementById('qty-' + cartId);
            let currentValue = parseInt(input.value);
            const max = parseInt(input.max);
            
            if (action === 'increase') {
                if (currentValue < max) {
                    input.value = currentValue + 1;
                    submitQuantityUpdate(cartId, input.value);
                } else {
                    alert('Stok tidak mencukupi. Stok tersedia: ' + max);
                }
            } else if (action === 'decrease') {
                if (currentValue > 1) {
                    input.value = currentValue - 1;
                    submitQuantityUpdate(cartId, input.value);
                }
            }
        }
        
        function updateQuantityInput(cartId) {
            const input = document.getElementById('qty-' + cartId);
            const value = parseInt(input.value);
            const max = parseInt(input.max);
            
            if (value < 1) {
                input.value = 1;
            } else if (value > max) {
                input.value = max;
                alert('Stok tidak mencukupi. Stok tersedia: ' + max);
            }
            
            if (value >= 1 && value <= max) {
                submitQuantityUpdate(cartId, input.value);
            }
        }
        
        function submitQuantityUpdate(cartId, quantity) {
            // Buat form untuk submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'keranjang.php';
            
            // Tambahkan input action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_quantity';
            form.appendChild(actionInput);
            
            // Tambahkan input keranjang_id
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'keranjang_id';
            idInput.value = cartId;
            form.appendChild(idInput);
            
            // Tambahkan input quantity
            const qtyInput = document.createElement('input');
            qtyInput.type = 'hidden';
            qtyInput.name = 'quantity';
            qtyInput.value = quantity;
            form.appendChild(qtyInput);
            
            // Submit form
            document.body.appendChild(form);
            form.submit();
        }
        
        // Resize handling
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>