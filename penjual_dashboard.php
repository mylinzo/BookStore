<?php
session_start();
include 'koneksi.php';
require_once 'notificationsHelper.php';

// ========== LOGOUT HANDLING ==========
if (isset($_GET['logout'])) {
    if (isset($_SESSION["id_user"])) {
        $user_id = $_SESSION["id_user"];
        $update_sql = "UPDATE users SET status = 'offline' WHERE id_user = ?";
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
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header("Location: login.php?logout=success");
    exit;
}

// ========== AUTHENTICATION CHECK ==========
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php"); 
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'penjual') {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] == 'super_admin') header("Location: dashboard_admin.php");
        elseif ($_SESSION['role'] == 'pembeli') header("Location: dashboard_pembeli.php");
    }
    header("Location: login.php"); 
    exit;
}

// ========== INISIALISASI VARIABEL ==========
$user_id = $_SESSION['id_user'] ?? null;
$user_name = $_SESSION['nama'] ?? 'Penjual';
if (!$user_id) { 
    header("Location: login.php"); 
    exit; 
}

// Update online status
$update_sql = "UPDATE users SET status = 'online' WHERE id_user = ?";
if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
    mysqli_stmt_bind_param($update_stmt, "i", $user_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
}

// Get user data for avatar
$user_data = [];
$stmt_user = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_query = mysqli_stmt_get_result($stmt_user);
$user_data = mysqli_fetch_assoc($user_query);
mysqli_stmt_close($stmt_user);
if (!isset($_SESSION['avatar']) && !empty($user_data['avatar'])) {
    $_SESSION['avatar'] = $user_data['avatar'];
}

// ========== PAGE ROUTING ==========
$currentPage = isset($_GET['page']) ? $_GET['page'] : '';
$message = ''; 
$message_type = '';

// ========== KATEGORI CRUD HANDLING ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ➕ ADD KATEGORI
    if ($_POST['action'] === 'add') {
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        if (!empty($nama_kategori)) {
            // Cek duplikat (case-insensitive)
            $check = mysqli_prepare($conn, "SELECT id_kategori FROM kategori WHERE LOWER(nama_kategori) = LOWER(?)");
            mysqli_stmt_bind_param($check, "s", $nama_kategori);
            mysqli_stmt_execute($check);
            $result = mysqli_stmt_get_result($check);
            
            if (mysqli_num_rows($result) === 0) {
                $stmt = mysqli_prepare($conn, "INSERT INTO kategori (nama_kategori) VALUES (?)");
                mysqli_stmt_bind_param($stmt, "s", $nama_kategori);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "✅ Kategori berhasil ditambahkan!"; 
                    $message_type = "success";
                } else { 
                    $message = "❌ Gagal menambahkan kategori."; 
                    $message_type = "error"; 
                }
                mysqli_stmt_close($stmt);
            } else { 
                $message = "⚠️ Kategori dengan nama tersebut sudah ada."; 
                $message_type = "warning"; 
            }
            mysqli_stmt_close($check);
        } else { 
            $message = "⚠️ Nama kategori tidak boleh kosong."; 
            $message_type = "warning"; 
        }
        // Redirect ke halaman kategori
        header("Location: penjual_dashboard.php?page=kategori&msg=" . urlencode($message) . "&type=" . urlencode($message_type));
        exit;
    }
    
    // ✏️ EDIT KATEGORI
    elseif ($_POST['action'] === 'edit') {
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        
        if (!empty($nama_kategori) && $id_kategori > 0) {
            // Cek duplikat (kecuali kategori yang diedit)
            $check = mysqli_prepare($conn, "SELECT id_kategori FROM kategori WHERE LOWER(nama_kategori) = LOWER(?) AND id_kategori != ?");
            mysqli_stmt_bind_param($check, "si", $nama_kategori, $id_kategori);
            mysqli_stmt_execute($check);
            $result = mysqli_stmt_get_result($check);
            
            if (mysqli_num_rows($result) === 0) {
                $stmt = mysqli_prepare($conn, "UPDATE kategori SET nama_kategori = ? WHERE id_kategori = ?");
                mysqli_stmt_bind_param($stmt, "si", $nama_kategori, $id_kategori);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "✅ Kategori berhasil diperbarui!"; 
                    $message_type = "success";
                } else { 
                    $message = "❌ Gagal memperbarui kategori."; 
                    $message_type = "error"; 
                }
                mysqli_stmt_close($stmt);
            } else { 
                $message = "⚠️ Nama kategori sudah digunakan oleh kategori lain."; 
                $message_type = "warning"; 
            }
            mysqli_stmt_close($check);
        } else {
            $message = "⚠️ Data tidak valid."; 
            $message_type = "warning";
        }
        // Redirect ke halaman kategori
        header("Location: penjual_dashboard.php?page=kategori&msg=" . urlencode($message) . "&type=" . urlencode($message_type));
        exit;
    }
    
    // 🗑️ DELETE KATEGORI - DENGAN FOREIGN KEY CHECK
    elseif ($_POST['action'] === 'delete') {
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        
        if ($id_kategori > 0) {
            // 🔍 Cek apakah kategori masih digunakan produk
            $check_produk = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM produk WHERE id_kategori = ?");
            mysqli_stmt_bind_param($check_produk, "i", $id_kategori);
            mysqli_stmt_execute($check_produk);
            $produk_result = mysqli_stmt_get_result($check_produk);
            $produk_count_check = mysqli_fetch_assoc($produk_result);
            mysqli_stmt_close($check_produk);
            
            if ($produk_count_check['count'] > 0) {
                // ❌ Tidak bisa dihapus - masih ada produk
                $message = "⚠️ Kategori tidak dapat dihapus karena masih digunakan oleh <strong>{$produk_count_check['count']} produk</strong>. Pindahkan produk ke kategori lain terlebih dahulu.";
                $message_type = "warning";
            } else {
                // ✅ Aman dihapus
                $stmt = mysqli_prepare($conn, "DELETE FROM kategori WHERE id_kategori = ?");
                mysqli_stmt_bind_param($stmt, "i", $id_kategori);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "✅ Kategori berhasil dihapus!"; 
                    $message_type = "success";
                } else { 
                    $message = "❌ Gagal menghapus kategori."; 
                    $message_type = "error"; 
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $message = "⚠️ ID kategori tidak valid."; 
            $message_type = "warning";
        }
        // Redirect ke halaman kategori
        header("Location: penjual_dashboard.php?page=kategori&msg=" . urlencode($message) . "&type=" . urlencode($message_type));
        exit;
    }
}

// Ambil pesan dari URL parameter
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = $_GET['msg']; 
    $message_type = $_GET['type'];
}

// ========== STATISTICS QUERIES ==========
$produk_count = ['count' => 0]; 
$transaksi_count = ['count' => 0]; 
$pending_count = ['count' => 0]; 
$revenue = ['total' => 0];

$produk_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM produk WHERE id_penjual = $user_id");
if ($produk_query) $produk_count = mysqli_fetch_assoc($produk_query);

$transaksi_query = mysqli_query($conn, "SELECT COUNT(DISTINCT t.id_transaksi) as count FROM transaksi t JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi JOIN produk p ON dt.id_produk = p.id_produk WHERE p.id_penjual = $user_id");
if ($transaksi_query) $transaksi_count = mysqli_fetch_assoc($transaksi_query);

$pending_query = mysqli_query($conn, "SELECT COUNT(DISTINCT t.id_transaksi) as count FROM transaksi t JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi JOIN produk p ON dt.id_produk = p.id_produk WHERE p.id_penjual = $user_id AND t.status = 'menunggu_pembayaran'");
if ($pending_query) $pending_count = mysqli_fetch_assoc($pending_query);

$revenue_query = mysqli_query($conn, "SELECT SUM(t.total_harga) as total FROM transaksi t JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi JOIN produk p ON dt.id_produk = p.id_produk WHERE p.id_penjual = $user_id AND t.status = 'selesai'");
if ($revenue_query) { 
    $revenue = mysqli_fetch_assoc($revenue_query); 
    if (!$revenue || !isset($revenue['total'])) $revenue = ['total' => 0]; 
}

// Recent orders
$recent_orders = mysqli_query($conn, "SELECT t.id_transaksi, t.tanggal, t.status, u.nama as pembeli, p.nama_buku, dt.jumlah FROM transaksi t JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi JOIN produk p ON dt.id_produk = p.id_produk JOIN users u ON t.id_pembeli = u.id_user WHERE p.id_penjual = $user_id ORDER BY t.tanggal DESC LIMIT 5");
if (!$recent_orders) $recent_orders = false;

// Recent chats
$recent_chats = mysqli_query($conn, "SELECT c.*, u.nama as pengirim_nama FROM chat c JOIN users u ON c.id_pengirim = u.id_user WHERE c.id_penerima = $user_id ORDER BY c.created_at DESC LIMIT 3");
if (!$recent_chats) $recent_chats = false;

// Low stock products
$low_stock = mysqli_query($conn, "SELECT * FROM produk WHERE id_penjual = $user_id AND stok < 5 ORDER BY stok ASC LIMIT 5");
if (!$low_stock) $low_stock = false;

// Get kategori list (hanya jika di halaman kategori)
$kategori_list = null;
if ($currentPage === 'kategori') {
    $stmt = mysqli_prepare($conn, "SELECT k.*, COUNT(p.id_produk) as product_count FROM kategori k LEFT JOIN produk p ON k.id_kategori = p.id_kategori GROUP BY k.id_kategori ORDER BY k.nama_kategori ASC");
    mysqli_stmt_execute($stmt);
    $kategori_list = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
}

// ========== NOTIFICATIONS ==========
$notifications = getNotifications($conn, $user_id, 'penjual');
$notifications_count = ['order' => 0, 'chat' => 0];
if (isset($notifications['list']) && is_array($notifications['list'])) {
    foreach ($notifications['list'] as $notif) {
        if (isset($notif['type'])) {
            if ($notif['type'] === 'order') $notifications_count['order']++;
            elseif ($notif['type'] === 'chat') $notifications_count['chat']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📚 Dashboard Penjual - Pustaka Aruna</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #2563eb; --primary-hover: #1d4ed8; --primary-light: #eff6ff;
    --secondary: #64748b; --success: #22c55e; --success-bg: #f0fdf4; --success-border: #86efac;
    --error: #ef4444; --error-bg: #fef2f2; --error-border: #fca5a5;
    --warning: #f59e0b; --warning-bg: #fffbeb; --warning-border: #fcd34d;
    --info: #3b82f6; --info-bg: #eff6ff; --info-border: #93c5fd;
    --background: #f8fafc; --surface: #ffffff; --border: #e2e8f0;
    --text-primary: #1e293b; --text-secondary: #64748b;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05); --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1); --radius: 12px; --radius-sm: 8px;
}
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
body { background-color: var(--background); color: var(--text-primary); min-height: 100vh; line-height: 1.5; }

/* Navbar */
.navbar { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); padding: 1rem 2rem; box-shadow: var(--shadow-md); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }
.logo-container { display: flex; align-items: center; gap: 15px; }
.logo { font-size: 1.5rem; font-weight: 600; color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; }
.logo i { opacity: 0.9; }
.search-box { flex: 1; max-width: 500px; margin: 0 30px; }
.search-box input { width: 100%; padding: 12px 20px; border: none; border-radius: var(--radius-sm); font-size: 0.9rem; transition: all 0.3s; background: rgba(255, 255, 255, 0.95); color: var(--text-primary); }
.search-box input:focus { outline: none; box-shadow: 0 0 0 3px var(--primary-light); }
.user-area { display: flex; align-items: center; gap: 20px; }

/* Notification */
.notification-bell { position: relative; font-size: 1.3rem; color: white; cursor: pointer; transition: transform 0.2s; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.15); }
.notification-bell:hover { background: rgba(255, 255, 255, 0.25); transform: scale(1.05); }
.notification-count { position: absolute; top: -4px; right: -4px; background: var(--error); color: white; font-size: 0.7rem; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; }
.notification-dropdown { position: absolute; top: 60px; right: 0; width: 350px; background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow-lg); border: 1px solid var(--border); display: none; z-index: 1001; }
.notification-dropdown.show { display: block; animation: slideDown 0.3s ease; }
.notification-header { padding: 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.notification-header h3 { font-size: 1rem; font-weight: 600; color: var(--text-primary); margin: 0; }
.notification-header a { color: var(--primary); text-decoration: none; font-size: 0.85rem; cursor: pointer; }
.notification-list { max-height: 400px; overflow-y: auto; }
.notification-item { padding: 15px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; transition: all 0.3s; cursor: pointer; }
.notification-item:hover { background: var(--background); }
.notification-item.unread { background: var(--primary-light); }
.notification-icon { width: 35px; height: 35px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.notification-content { flex: 1; }
.notification-title { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); margin-bottom: 3px; }
.notification-message { font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4; }
.notification-time { font-size: 0.7rem; color: var(--text-secondary); margin-top: 3px; }
.notification-footer { padding: 12px; text-align: center; background: var(--background); border-top: 1px solid var(--border); }
.notification-footer a { color: var(--primary); text-decoration: none; font-size: 0.9rem; font-weight: 600; }

.user-profile { display: flex; align-items: center; gap: 10px; padding: 8px 15px; background: rgba(255, 255, 255, 0.15); border-radius: 30px; cursor: pointer; transition: all 0.3s; }
.user-profile:hover { background: rgba(255, 255, 255, 0.25); }
.user-avatar { width: 40px; height: 40px; border-radius: 50%; background: white; color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem; overflow: hidden; }
.user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.user-info h4 { font-weight: 600; font-size: 0.9rem; color: white; }
.user-info p { font-size: 0.8rem; color: rgba(255, 255, 255, 0.8); }
.online-status { width: 8px; height: 8px; background: var(--success); border-radius: 50%; margin-left: 5px; }

/* Container & Sidebar */
.container { display: grid; grid-template-columns: 250px 1fr; gap: 0; min-height: calc(100vh - 70px); }
.sidebar { background: var(--surface); border-right: 1px solid var(--border); padding: 2rem 0; position: sticky; top: 70px; height: calc(100vh - 70px); overflow-y: auto; box-shadow: var(--shadow-sm); }
.sidebar-header { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid var(--border); margin-bottom: 1rem; }
.sidebar-header div:first-child { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
.sidebar-header div:last-child { font-size: 0.8125rem; color: var(--text-secondary); }
.sidebar-title { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin: 20px 1.5rem 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
.sidebar-menu { list-style: none; }
.sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 1.5rem; color: var(--text-secondary); text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; font-size: 0.9375rem; font-weight: 500; }
.sidebar-link:hover { background: var(--primary-light); color: var(--primary); }
.sidebar-link.active { background: var(--primary-light); color: var(--primary); border-left-color: var(--primary); font-weight: 600; }
.sidebar-link i { width: 20px; text-align: center; font-size: 1rem; color: var(--text-secondary); }
.sidebar-link.active i { color: var(--primary); }
.sidebar-badge { background: var(--error); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: auto; font-weight: 600; }

.calendar-widget { background: var(--background); margin: 1.5rem; padding: 1.5rem; border-radius: var(--radius-sm); text-align: center; border: 1px solid var(--border); }
.calendar-month { font-size: 0.9rem; font-weight: 600; margin-bottom: 10px; color: var(--text-secondary); }
.calendar-date { font-size: 2.5rem; font-weight: 700; color: var(--primary); margin-bottom: 5px; }
.calendar-day { font-size: 0.9rem; color: var(--text-secondary); }

/* Main Content */
.main-content { padding: 2rem; overflow-y: auto; background: var(--background); }
.welcome-banner { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: white; padding: 2.5rem; border-radius: var(--radius); margin-bottom: 2rem; position: relative; overflow: hidden; box-shadow: var(--shadow-md); }
.welcome-banner::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%); }
.welcome-banner h1 { font-size: 2rem; margin-bottom: 10px; font-weight: 600; position: relative; z-index: 1; }
.welcome-banner p { font-size: 1.1rem; opacity: 0.9; max-width: 600px; position: relative; z-index: 1; }
.welcome-actions { display: flex; gap: 15px; margin-top: 1.5rem; position: relative; z-index: 1; }

/* Buttons */
.btn-primary, .btn-secondary, .btn-danger, .btn-warning { padding: 12px 24px; border-radius: var(--radius-sm); text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border: none; cursor: pointer; font-size: 0.95rem; }
.btn-primary { background: white; color: var(--primary); }
.btn-primary:hover { background: var(--primary-light); transform: translateY(-2px); }
.btn-secondary { background: var(--background); color: var(--text-primary); border: 1px solid var(--border); }
.btn-secondary:hover { background: var(--border); transform: translateY(-2px); }
.btn-danger { background: var(--error); color: white; }
.btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
.btn-warning { background: var(--warning); color: var(--text-primary); }
.btn-warning:hover { background: #d97706; transform: translateY(-2px); }
.btn-sm { padding: 6px 14px; font-size: 0.85rem; border-radius: 6px; }
.btn-icon { padding: 8px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 2rem; }
.stat-card { background: var(--surface); border-radius: var(--radius); padding: 1.8rem; box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 20px; transition: all 0.2s; border: 1px solid var(--border); cursor: pointer; }
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--primary); }
.stat-icon { width: 70px; height: 70px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 2rem; flex-shrink: 0; color: white; }
.stat-icon.brown { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-icon.tan { background: linear-gradient(135deg, #22c55e, #16a34a); }
.stat-icon.rust { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.stat-icon.copper { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.stat-content h3 { font-size: 0.95rem; color: var(--text-secondary); font-weight: 500; margin-bottom: 8px; }
.stat-content .number { font-size: 2.2rem; font-weight: 700; color: var(--text-primary); line-height: 1; margin-bottom: 5px; }
.stat-content .trend { font-size: 0.85rem; display: flex; align-items: center; gap: 5px; }
.trend.up { color: var(--success); } .trend.down { color: var(--error); }

/* Content Grid */
.content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem; }
.orders-card { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow-sm); overflow: hidden; border: 1px solid var(--border); }
.card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--background); }
.card-header h2 { font-size: 1.125rem; font-weight: 600; color: var(--text-primary); }
.card-header a { color: var(--primary); text-decoration: none; font-weight: 500; font-size: 0.9rem; display: flex; align-items: center; gap: 5px; }
.card-body { padding: 0; }
.table-responsive { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; }
.table th { background: var(--background); padding: 1rem 1.5rem; text-align: left; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border); font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.025em; }
.table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); font-size: 0.9375rem; color: var(--text-primary); }
.table tbody tr { transition: all 0.2s; }
.table tbody tr:hover { background: var(--background); }

/* Badges */
.badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; border: 1px solid; }
.badge-success { background: var(--success-bg); color: #166534; border-color: var(--success-border); }
.badge-warning { background: var(--warning-bg); color: #92400e; border-color: var(--warning-border); }
.badge-danger { background: var(--error-bg); color: #991b1b; border-color: var(--error-border); }
.badge-info { background: var(--info-bg); color: #1e40af; border-color: var(--info-border); }
.badge-primary { background: var(--primary-light); color: #1e40af; border-color: var(--info-border); }
.badge-muted { background: var(--background); color: var(--text-secondary); border-color: var(--border); }

/* Side Widgets */
.side-widget { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow-sm); margin-bottom: 2rem; overflow: hidden; border: 1px solid var(--border); }
.widget-header { padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--background); display: flex; justify-content: space-between; align-items: center; }
.widget-header h3 { font-size: 1rem; font-weight: 600; color: var(--text-primary); margin: 0; }
.widget-badge { background: var(--error); color: white; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
.widget-body { padding: 1.5rem; }

/* Chat List */
.chat-list { list-style: none; }
.chat-item { padding: 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 15px; transition: all 0.2s; cursor: pointer; }
.chat-item:hover { background: var(--background); }
.chat-item:last-child { border-bottom: none; }
.chat-item.unread { background: var(--primary-light); }
.chat-avatar { width: 45px; height: 45px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
.chat-content { flex: 1; }
.chat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
.chat-name { font-weight: 600; color: var(--text-primary); }
.chat-time { font-size: 0.8rem; color: var(--text-secondary); }
.chat-message { font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.unread-dot { width: 10px; height: 10px; background: var(--primary); border-radius: 50%; flex-shrink: 0; }

/* Product List */
.product-item { display: flex; align-items: center; gap: 15px; padding: 1rem; border-bottom: 1px solid var(--border); }
.product-item:last-child { border-bottom: none; }
.product-icon { width: 40px; height: 40px; border-radius: var(--radius-sm); background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 1px solid var(--border); }
.product-info h4 { font-weight: 600; font-size: 0.95rem; margin-bottom: 3px; color: var(--text-primary); }
.product-info p { font-size: 0.85rem; color: var(--text-secondary); }
.product-stock { margin-left: auto; font-weight: 700; font-size: 0.9rem; }
.stock-low { color: var(--error); } .stock-ok { color: var(--success); }

/* Quick Actions */
.quick-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 1rem; }
.action-btn { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 15px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: all 0.2s; text-decoration: none; color: inherit; position: relative; }
.action-btn:hover { background: var(--background); border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.action-badge { position: absolute; top: -5px; right: -5px; background: var(--error); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
.action-icon { width: 45px; height: 45px; border-radius: var(--radius-sm); background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.action-text h4 { font-weight: 600; font-size: 0.9rem; margin-bottom: 3px; color: var(--text-primary); }
.action-text p { font-size: 0.8rem; color: var(--text-secondary); }

/* Footer */
.footer { text-align: center; padding: 2rem; color: var(--text-secondary); font-size: 0.9rem; border-top: 1px solid var(--border); margin-top: 2rem; background: var(--surface); border-radius: var(--radius); }

/* ========== KATEGORI SECTION ========== */
.kategori-section { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow-sm); border: 1px solid var(--border); overflow: hidden; }
.kategori-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); background: var(--background); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
.kategori-header h2 { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 10px; }
.kategori-header h2 i { color: var(--primary); }
.kategori-table th { background: linear-gradient(135deg, var(--primary-light), var(--background)); }
.kategori-table td { vertical-align: middle; }
.kategori-name { font-weight: 500; color: var(--text-primary); }
.kategori-count { font-size: 0.85rem; color: var(--text-secondary); }
.kategori-actions { display: flex; gap: 8px; align-items: center; }
.kategori-empty { text-align: center; padding: 3rem 2rem; color: var(--text-secondary); }
.kategori-empty i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.3; display: block; }

/* Modal */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center; animation: fadeIn 0.2s ease; }
.modal-overlay.show { display: flex; }
.modal { background: var(--surface); border-radius: var(--radius); width: 90%; max-width: 450px; box-shadow: var(--shadow-lg); animation: slideUp 0.3s ease; border: 1px solid var(--border); }
.modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin: 0; }
.modal-close { background: none; border: none; font-size: 1.5rem; color: var(--text-secondary); cursor: pointer; transition: color 0.2s; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
.modal-close:hover { background: var(--background); color: var(--error); }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary); font-size: 0.9rem; }
.form-group input { width: 100%; padding: 12px 15px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 1rem; transition: border-color 0.2s; }
.form-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
.form-help { font-size: 0.8rem; color: var(--text-secondary); margin-top: 5px; }

/* Toast */
.toast-container { position: fixed; top: 90px; right: 20px; z-index: 3000; display: flex; flex-direction: column; gap: 10px; max-width: 400px; }
.toast { padding: 15px 20px; border-radius: var(--radius-sm); box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease; border-left: 4px solid; background: var(--surface); }
.toast.success { border-color: var(--success); }
.toast.error { border-color: var(--error); }
.toast.warning { border-color: var(--warning); }
.toast i { font-size: 1.2rem; }
.toast.success i { color: var(--success); }
.toast.error i { color: var(--error); }
.toast.warning i { color: var(--warning); }
.toast-content { flex: 1; }
.toast-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 3px; }
.toast-message { font-size: 0.85rem; color: var(--text-secondary); }
.toast-close { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.2rem; padding: 5px; border-radius: 50%; transition: all 0.2s; }
.toast-close:hover { background: var(--background); color: var(--text-primary); }

/* Delete Modal */
.modal-delete .modal-header { background: var(--error-bg); border-bottom-color: var(--error-border); }
.modal-delete .modal-header h3 { color: var(--error); }
.modal-delete .modal-body { padding: 1.5rem 2rem; }
.delete-warning { display: flex; gap: 15px; align-items: flex-start; padding: 15px; background: var(--warning-bg); border: 1px solid var(--warning-border); border-radius: var(--radius-sm); margin-bottom: 1rem; }
.delete-warning i { font-size: 1.5rem; color: var(--warning); flex-shrink: 0; margin-top: 2px; }
.delete-warning-text { font-size: 0.9rem; color: var(--text-primary); }
.delete-warning-text strong { font-weight: 600; }
.delete-info { font-size: 0.85rem; color: var(--text-secondary); padding: 10px; background: var(--background); border-radius: var(--radius-sm); }
.delete-info i { margin-right: 5px; }

/* Animations */
@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideIn { from { opacity: 0; transform: translateX(100px); } to { opacity: 1; transform: translateX(0); } }
@keyframes shake { 0%, 100% { transform: rotate(0); } 25% { transform: rotate(15deg); } 75% { transform: rotate(-15deg); } }
.shake { animation: shake 0.5s ease; }
.pulse-animation { animation: pulse 1s; }

/* Responsive */
@media (max-width: 1200px) { .content-grid { grid-template-columns: 1fr; } .notification-dropdown { right: 20px; width: 300px; } }
@media (max-width: 992px) { .container { grid-template-columns: 1fr; } .sidebar { position: static; height: auto; display: none; } .sidebar.active { display: block; } .stats-grid { grid-template-columns: repeat(2, 1fr); } .navbar { flex-wrap: wrap; } .search-box { order: 3; width: 100%; margin: 15px 0 0; max-width: 100%; } }
@media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } .quick-actions { grid-template-columns: 1fr; } .welcome-banner { padding: 1.5rem; } .welcome-banner h1 { font-size: 1.5rem; } .user-info { display: none; } .notification-dropdown { width: 280px; } .kategori-header { flex-direction: column; align-items: stretch; } }

/* Scrollbar */
.sidebar::-webkit-scrollbar, .main-content::-webkit-scrollbar, .notification-list::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-track, .main-content::-webkit-scrollbar-track, .notification-list::-webkit-scrollbar-track { background: var(--background); }
.sidebar::-webkit-scrollbar-thumb, .main-content::-webkit-scrollbar-thumb, .notification-list::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 3px; }
.sidebar::-webkit-scrollbar-thumb:hover, .main-content::-webkit-scrollbar-thumb:hover, .notification-list::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

/* Focus */
.sidebar-link:focus-visible, .btn:focus-visible, .search-box input:focus-visible, .form-group input:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

/* Utility */
.text-muted { color: var(--text-secondary) !important; font-size: 0.85rem; }
.text-success { color: var(--success) !important; }
.text-warning { color: var(--warning) !important; }
.text-error { color: var(--error) !important; }
.d-flex { display: flex !important; }
.align-items-center { align-items: center !important; }
.gap-2 { gap: 0.5rem !important; }
.flex-wrap { flex-wrap: wrap !important; }
</style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar">
    <div class="logo-container">
        <a href="penjual_dashboard.php" class="logo"><i class="fas fa-book-open"></i><span>Pustaka Aruna</span></a>
    </div>
    <div class="search-box"><input type="text" placeholder="Cari buku atau pelanggan..." id="searchInput"></div>
    <div class="user-area">
        <div class="notification-bell" id="notificationBell"><i class="fas fa-bell"></i><span class="notification-count" id="notificationCount"><?php echo $notifications['total']; ?></span></div>
        <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header"><h3>Notifikasi</h3><a href="#" id="markAllRead">Tandai semua dibaca</a></div>
            <div class="notification-list" id="notificationList"><div style="text-align: center; padding: 30px 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i><br>Memuat notifikasi...</div></div>
            <div class="notification-footer"><a href="penjual_notifikasi.php">Lihat Semua Notifikasi</a></div>
        </div>
        <div class="user-profile" onclick="toggleUserMenu()">
            <div class="user-avatar">
                <?php 
                $avatar_path = !empty($user_data['avatar']) ? 'uploads/avatars/' . $user_data['avatar'] : ''; 
                if (!empty($avatar_path) && file_exists($avatar_path)): ?>
                <img src="<?php echo $avatar_path . '?t=' . time(); ?>" alt="Avatar">
                <?php else: ?><?php echo strtoupper(substr($user_name ?? 'P', 0, 1)); ?><?php endif; ?>
            </div>
            <div class="user-info"><h4><?php echo htmlspecialchars($user_name ?? 'Penjual'); ?></h4><p>Penjual <span class="online-status"></span></p></div>
            <i class="fas fa-chevron-down"></i>
        </div>
    </div>
</nav>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Container -->
<div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header"><div>PENJUAL</div><div>@<?php echo htmlspecialchars($user_name ?? 'Penjual'); ?></div></div>
        <div class="calendar-widget">
            <div class="calendar-month"><?php echo date('F Y'); ?></div>
            <div class="calendar-date"><?php echo date('d'); ?></div>
            <div class="calendar-day"><?php echo date('l'); ?></div>
        </div>
        <div class="sidebar-title">Menu Utama</div>
        <ul class="sidebar-menu">
            <li class="sidebar-item"><a href="penjual_dashboard.php" class="sidebar-link<?php echo $currentPage === '' ? ' active' : ''; ?>"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li class="sidebar-item"><a href="penjual_produk.php" class="sidebar-link"><i class="fas fa-book"></i> Produk Buku</a></li>
            <li class="sidebar-item"><a href="pesanan_penjual.php" class="sidebar-link" id="approveLink"><i class="fas fa-inbox"></i> Approve<?php if ($notifications['order'] > 0): ?><span class="sidebar-badge"><?php echo $notifications['order']; ?></span><?php endif; ?></a></li>
            <li class="sidebar-item"><a href="penjual_dashboard.php?page=kategori" class="sidebar-link<?php echo $currentPage === 'kategori' ? ' active' : ''; ?>"><i class="fas fa-layer-group"></i> Kategori</a></li>
            <li class="sidebar-item"><a href="penjual_laporan.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> Laporan</a></li>
            <li class="sidebar-item"><a href="penjual_chat.php" class="sidebar-link" id="chatLink"><i class="fas fa-comments"></i> Chat<?php if ($notifications['chat'] > 0): ?><span class="sidebar-badge"><?php echo $notifications['chat']; ?></span><?php endif; ?></a></li>
        </ul>
        <div class="sidebar-title" style="margin-top: 2rem;">Akun Saya</div>
        <ul class="sidebar-menu">
            <li class="sidebar-item"><a href="penjual_account.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profil Saya</a></li>
            <li class="sidebar-item"><a href="?logout=true" class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
            <li class="sidebar-item"><a href="help.php" class="sidebar-link"><i class="fas fa-question-circle"></i> Bantuan</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($currentPage === 'kategori'): ?>
        <!-- ========== KATEGORI MANAGEMENT ========== -->
        <div class="kategori-section">
            <div class="kategori-header">
                <h2><i class="fas fa-layer-group"></i> Kelola Kategori</h2>
                <button class="btn-primary btn-sm" onclick="openModal('add')"><i class="fas fa-plus"></i> Tambah Kategori</button>
            </div>
            <div class="table-responsive">
                <table class="table kategori-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Nama Kategori</th>
                            <th style="width: 120px; text-align: center;">Jumlah Produk</th>
                            <th style="width: 150px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($kategori_list && mysqli_num_rows($kategori_list) > 0): 
                            $no = 1;
                            while ($kat = mysqli_fetch_assoc($kategori_list)): 
                        ?>
                        <tr>
                            <td><strong><?php echo $no++; ?></strong></td>
                            <td><span class="kategori-name"><?php echo htmlspecialchars($kat['nama_kategori']); ?></span></td>
                            <td style="text-align: center;"><span class="badge badge-muted"><?php echo $kat['product_count']; ?> produk</span></td>
                            <td style="text-align: right;">
                                <div class="kategori-actions" style="justify-content: flex-end;">
                                    <button class="btn-secondary btn-sm btn-icon" onclick="openModal('edit', <?php echo $kat['id_kategori']; ?>, '<?php echo addslashes(htmlspecialchars($kat['nama_kategori'])); ?>')" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn-danger btn-sm btn-icon" onclick="confirmDelete(<?php echo $kat['id_kategori']; ?>, '<?php echo addslashes(htmlspecialchars($kat['nama_kategori'])); ?>', <?php echo $kat['product_count']; ?>)" title="Hapus"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="kategori-empty"><i class="fas fa-folder-open"></i>Belum ada kategori.<br><small>Klik "Tambah Kategori" untuk memulai.</small></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Add/Edit -->
        <div class="modal-overlay" id="kategoriModal">
            <div class="modal">
                <form method="POST" id="kategoriForm">
                    <input type="hidden" name="action" id="modalAction" value="add">
                    <input type="hidden" name="id_kategori" id="modalKategoriId">
                    <div class="modal-header">
                        <h3 id="modalTitle">Tambah Kategori</h3>
                        <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nama_kategori">Nama Kategori <span class="text-error">*</span></label>
                            <input type="text" id="nama_kategori" name="nama_kategori" required placeholder="Contoh: Novel, Komik, Pelajaran" maxlength="100">
                            <div class="form-help">Nama kategori harus unik dan maksimal 100 karakter.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                        <button type="submit" class="btn-primary" id="modalSubmit">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Delete Confirmation -->
        <div class="modal-overlay modal-delete" id="deleteModal">
            <div class="modal">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_kategori" id="deleteKategoriId">
                    <div class="modal-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h3>
                        <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p style="margin-bottom: 1rem;">Apakah Anda yakin ingin menghapus kategori <strong id="deleteKategoriName"></strong>?</p>
                        <div class="delete-warning" id="deleteWarning" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="delete-warning-text">
                                <strong>Perhatian!</strong><br>
                                Kategori ini masih digunakan oleh <span id="deleteProductCount"></span> produk.<br>
                                <small>Pindahkan produk ke kategori lain terlebih dahulu sebelum menghapus.</small>
                            </div>
                        </div>
                        <div class="delete-info"><i class="fas fa-info-circle"></i> Aksi ini tidak dapat dibatalkan.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
                        <button type="submit" class="btn-danger" id="deleteSubmitBtn">Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1>Selamat Datang, <?php echo htmlspecialchars($user_name ?? 'Penjual'); ?>! </h1>
            <p>Kelola toko buku Anda dengan mudah dan pantau semua aktivitas penjualan</p>
            <div class="welcome-actions">
                <a href="penjual_produk.php?action=add" class="btn-primary"><i class="fas fa-plus"></i> Tambah Buku Baru</a>
                <a href="penjual_laporan.php" class="btn-secondary"><i class="fas fa-chart-bar"></i> Lihat Laporan</a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='penjual_produk.php';">
                <div class="stat-icon brown"><i class="fas fa-book"></i></div>
                <div class="stat-content"><h3>Total Produk</h3><div class="number"><?php echo $produk_count['count']; ?></div><div class="trend up"><i class="fas fa-arrow-up"></i> Aktif</div></div>
            </div>
            <div class="stat-card" onclick="window.location.href='pesanan_penjual.php';">
                <div class="stat-icon tan"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-content"><h3>Total Transaksi</h3><div class="number"><?php echo $transaksi_count['count']; ?></div><div class="trend up"><i class="fas fa-arrow-up"></i> Semua waktu</div></div>
            </div>
            <div class="stat-card" onclick="window.location.href='pesanan_penjual.php?filter=menunggu_pembayaran';">
                <div class="stat-icon rust"><i class="fas fa-clock"></i></div>
                <div class="stat-content"><h3>Menunggu Approve</h3><div class="number" id="pendingCount"><?php echo $pending_count['count']; ?></div><div class="trend down"><i class="fas fa-arrow-down"></i> Perlu diproses</div></div>
            </div>
            <div class="stat-card" onclick="window.location.href='penjual_laporan.php';">
                <div class="stat-icon copper"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-content"><h3>Total Pendapatan</h3><div class="number">Rp <?php echo number_format($revenue['total'] ?? 0, 0, ',', '.'); ?></div><div class="trend up"><i class="fas fa-arrow-up"></i> Dari penjualan</div></div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Orders -->
            <div class="orders-card">
                <div class="card-header"><h2>Transaksi Terbaru</h2><a href="pesanan_penjual.php">Lihat Semua <i class="fas fa-arrow-right"></i></a></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>ID Transaksi</th><th>Pembeli</th><th>Buku</th><th>Tanggal</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if ($recent_orders && mysqli_num_rows($recent_orders) > 0): while ($order = mysqli_fetch_assoc($recent_orders)):
                                    $status_class = 'badge-warning';
                                    if ($order['status'] === 'selesai') { $status_class = 'badge-success'; }
                                    elseif ($order['status'] === 'approve') { $status_class = 'badge-primary'; }
                                    elseif ($order['status'] === 'ditolak') { $status_class = 'badge-danger'; }
                                    elseif ($order['status'] === 'refund') { $status_class = 'badge-info'; }
                                ?>
                                <tr><td><strong>#<?php echo $order['id_transaksi']; ?></strong></td><td><?php echo htmlspecialchars($order['pembeli']); ?></td><td><?php echo htmlspecialchars($order['nama_buku']) . ' (' . $order['jumlah'] . ' item)'; ?></td><td><?php echo date('d M Y', strtotime($order['tanggal'])); ?></td><td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($order['status']); ?></span></td></tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);"><i class="fas fa-shopping-cart" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: var(--text-secondary); opacity: 0.3;"></i>Belum ada transaksi</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Side Widgets -->
            <div>
                <!-- Recent Messages -->
                <div class="side-widget">
                    <div class="widget-header"><h3>Pesan Terbaru</h3><?php if ($notifications['chat'] > 0): ?><span class="widget-badge"><?php echo $notifications['chat']; ?> baru</span><?php endif; ?></div>
                    <div class="widget-body">
                        <ul class="chat-list">
                            <?php if ($recent_chats && mysqli_num_rows($recent_chats) > 0): while ($chat = mysqli_fetch_assoc($recent_chats)):
                                $unread_class = ($chat['dibaca'] == 0) ? 'unread' : '';
                            ?>
                            <li class="chat-item <?php echo $unread_class; ?>" onclick="window.location.href='penjual_chat.php?user=<?php echo $chat['id_pengirim']; ?>'">
                                <div class="chat-avatar"><?php echo strtoupper(substr($chat['pengirim_nama'], 0, 1)); ?></div>
                                <div class="chat-content">
                                    <div class="chat-header"><span class="chat-name"><?php echo htmlspecialchars($chat['pengirim_nama']); ?></span><span class="chat-time"><?php echo date('H:i', strtotime($chat['created_at'])); ?></span></div>
                                    <div class="chat-message"><?php echo htmlspecialchars(substr($chat['pesan'], 0, 60)); ?>...</div>
                                </div>
                                <?php if ($chat['dibaca'] == 0): ?><div class="unread-dot"></div><?php endif; ?>
                            </li>
                            <?php endwhile; else: ?>
                            <li style="text-align: center; padding: 1.5rem; color: var(--text-secondary);"><i class="fas fa-comments" style="font-size: 2rem; margin-bottom: 1rem; display: block; opacity: 0.3;"></i>Belum ada pesan</li>
                            <?php endif; ?>
                        </ul>
                        <a href="penjual_chat.php" style="display: block; text-align: center; padding: 10px; background: var(--background); border-radius: var(--radius-sm); margin-top: 15px; text-decoration: none; color: var(--primary); font-weight: 500; border: 1px solid var(--border);"><i class="fas fa-comments"></i> Buka Chat</a>
                    </div>
                </div>
                <!-- Low Stock -->
                <div class="side-widget">
                    <div class="widget-header"><h3>Stok Sedikit</h3></div>
                    <div class="widget-body">
                        <?php if ($low_stock && mysqli_num_rows($low_stock) > 0): while ($product = mysqli_fetch_assoc($low_stock)): ?>
                        <div class="product-item">
                            <div class="product-icon"><i class="fas fa-book"></i></div>
                            <div class="product-info"><h4><?php echo htmlspecialchars($product['nama_buku']); ?></h4><p>Stok: <?php echo $product['stok']; ?> unit</p></div>
                            <div class="product-stock <?php echo $product['stok'] < 3 ? 'stock-low' : 'stock-ok'; ?>"><?php echo $product['stok']; ?></div>
                        </div>
                        <?php endwhile; else: ?>
                        <div style="text-align: center; padding: 1.5rem; color: var(--text-secondary);"><i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: var(--success);"></i>Semua stok aman</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="side-widget">
            <div class="widget-header"><h3>Aksi Cepat</h3></div>
            <div class="widget-body">
                <div class="quick-actions">
                    <a href="penjual_produk.php?action=add" class="action-btn"><div class="action-icon"><i class="fas fa-plus"></i></div><div class="action-text"><h4>Tambah Buku</h4><p>Tambah buku baru ke katalog</p></div></a>
                    <a href="pesanan_penjual.php" class="action-btn" id="quickApproveBtn"><div class="action-icon" style="background: linear-gradient(135deg, var(--success), #16a34a);"><i class="fas fa-check-circle"></i></div><div class="action-text"><h4>Approve Pesanan</h4><p>Validasi pembayaran pelanggan</p></div><?php if ($notifications['order'] > 0): ?><span class="action-badge"><?php echo $notifications['order']; ?></span><?php endif; ?></a>
                    <a href="penjual_laporan.php" class="action-btn"><div class="action-icon" style="background: linear-gradient(135deg, var(--warning), #d97706);"><i class="fas fa-chart-bar"></i></div><div class="action-text"><h4>Laporan Penjualan</h4><p>Analisis pendapatan & keuntungan</p></div></a>
                    <a href="penjual_chat.php" class="action-btn" id="quickChatBtn"><div class="action-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-hover));"><i class="fas fa-comments"></i></div><div class="action-text"><h4>Chat Pelanggan</h4><p>Balas pesan pelanggan</p></div><?php if ($notifications['chat'] > 0): ?><span class="action-badge"><?php echo $notifications['chat']; ?></span><?php endif; ?></a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>© 2025 Pustaka Aruna. Semua hak dilindungi.</p>
            <p style="font-size: 0.8rem; margin-top: 10px; color: var(--text-secondary);">Dashboard Penjual - Versi 1.0</p>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ========== SHOW TOAST ==========
    <?php if (!empty($message)): ?>
    showToast('<?php echo addslashes($message); ?>', '<?php echo $message_type; ?>');
    <?php endif; ?>

    // ========== MODAL FUNCTIONS ==========
    const kategoriModal = document.getElementById('kategoriModal');
    const deleteModal = document.getElementById('deleteModal');
    
    // Fungsi untuk membuka modal tambah/edit kategori
    window.openModal = function(type, id = null, name = '') {
        const form = document.getElementById('kategoriForm');
        const action = document.getElementById('modalAction');
        const kategoriId = document.getElementById('modalKategoriId');
        const namaInput = document.getElementById('nama_kategori');
        const title = document.getElementById('modalTitle');
        
        if (type === 'add') {
            action.value = 'add';
            kategoriId.value = '';
            namaInput.value = '';
            title.textContent = 'Tambah Kategori';
            document.getElementById('modalSubmit').textContent = 'Simpan';
        } else if (type === 'edit') {
            action.value = 'edit';
            kategoriId.value = id;
            namaInput.value = name;
            title.textContent = 'Edit Kategori';
            document.getElementById('modalSubmit').textContent = 'Update';
        }
        kategoriModal.classList.add('show');
        setTimeout(() => namaInput.focus(), 100);
    }
    
    // Fungsi untuk menutup modal kategori
    window.closeModal = function() { 
        kategoriModal.classList.remove('show'); 
        document.getElementById('kategoriForm').reset(); 
    }
    
    // Fungsi untuk konfirmasi hapus
    window.confirmDelete = function(id, name, productCount) {
        document.getElementById('deleteKategoriId').value = id;
        document.getElementById('deleteKategoriName').textContent = name;
        const warning = document.getElementById('deleteWarning');
        const submitBtn = document.getElementById('deleteSubmitBtn');
        
        if (productCount > 0) {
            warning.style.display = 'flex';
            document.getElementById('deleteProductCount').textContent = productCount;
            submitBtn.disabled = true;
            submitBtn.title = 'Tidak dapat menghapus karena masih ada produk';
        } else {
            warning.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.title = '';
        }
        deleteModal.classList.add('show');
    }
    
    // Fungsi untuk menutup modal hapus
    window.closeDeleteModal = function() { 
        deleteModal.classList.remove('show'); 
    }
    
    // Tutup modal jika klik di luar
    [kategoriModal, deleteModal].forEach(modal => {
        modal.addEventListener('click', function(e) { 
            if (e.target === this) this.classList.remove('show'); 
        });
    });
    
    // Tutup modal dengan tombol ESC
    document.addEventListener('keydown', function(e) { 
        if (e.key === 'Escape') { 
            closeModal(); 
            closeDeleteModal(); 
        } 
    });
    
    // ========== TOAST ==========
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-circle' };
        const titles = { success: 'Berhasil', error: 'Error', warning: 'Peringatan' };
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<i class="fas ${icons[type] || 'fa-info-circle'}"></i><div class="toast-content"><div class="toast-title">${titles[type] || 'Info'}</div><div class="toast-message">${message}</div></div><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>`;
        container.appendChild(toast);
        setTimeout(() => { 
            toast.style.opacity = '0'; 
            toast.style.transform = 'translateX(100px)'; 
            setTimeout(() => toast.remove(), 300); 
        }, 5000);
    }
    
    // ========== NOTIFICATION SYSTEM ==========
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationCount = document.getElementById('notificationCount');
    const notificationList = document.getElementById('notificationList');
    const markAllReadBtn = document.getElementById('markAllRead');
    
    if (notificationBell && notificationDropdown && notificationCount && notificationList) {
        loadNotifications();
        
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
            if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) 
                notificationDropdown.classList.remove('show'); 
        });
        
        notificationDropdown.addEventListener('click', function(e) { 
            e.stopPropagation(); 
        });
        
        function loadNotifications() {
            notificationList.innerHTML = '<div style="text-align: center; padding: 30px 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i><br>Memuat notifikasi...</div>';
            fetch('api_get_notifications.php?t=' + Date.now())
                .then(response => { if (!response.ok) throw new Error('Network error'); return response.json(); })
                .then(data => { if (data.success) { displayNotifications(data.notifications); updateNotificationCount(data.unread_total); } else { showErrorNotification(); } })
                .catch(error => { console.error('Error:', error); showErrorNotification(); });
        }
        
        function displayNotifications(notifications) {
            if (!notifications || notifications.length === 0) { 
                notificationList.innerHTML = '<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-bell-slash" style="font-size: 3rem; color: var(--text-secondary); opacity: 0.3; margin-bottom: 15px;"></i><p style="color: var(--text-secondary);">Tidak ada notifikasi</p></div>'; 
                return; 
            }
            let html = ''; 
            notifications.forEach(notif => { 
                const icon = notif.type === 'chat' ? 'fa-comment' : 'fa-shopping-cart'; 
                const unreadClass = notif.dibaca == 0 ? 'unread' : ''; 
                const time = timeAgo(notif.created_at); 
                html += `<div class="notification-item ${unreadClass}" onclick="window.location.href='${notif.link}'"><div class="notification-icon"><i class="fas ${icon}"></i></div><div class="notification-content"><div class="notification-title">${escapeHtml(notif.title)}</div><div class="notification-message">${escapeHtml(notif.message)}</div><div class="notification-time">${time}</div></div></div>`; 
            });
            notificationList.innerHTML = html;
        }
        
        function escapeHtml(text) { 
            const div = document.createElement('div'); 
            div.textContent = text; 
            return div.innerHTML; 
        }
        
        function showErrorNotification() { 
            notificationList.innerHTML = '<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--error); margin-bottom: 15px;"></i><p style="color: var(--text-secondary);">Gagal memuat notifikasi</p><button onclick="location.reload()" style="margin-top: 10px; padding: 8px 20px; background: var(--primary); color: white; border: none; border-radius: var(--radius-sm); cursor: pointer;"><i class="fas fa-sync-alt"></i> Muat Ulang</button></div>'; 
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
            notificationCount.style.display = count > 0 ? 'flex' : 'none'; 
            if (count > 0) { 
                notificationBell.classList.add('shake'); 
                setTimeout(() => notificationBell.classList.remove('shake'), 500); 
            } 
        }
        
        function markNotificationsAsRead() { 
            fetch('api_mark_notifications_read.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } })
                .then(r => r.json())
                .then(data => { if (data.success) { updateNotificationCount(0); updateSidebarBadges(); } })
                .catch(e => console.error('Error:', e)); 
        }
        
        if (markAllReadBtn) { 
            markAllReadBtn.addEventListener('click', function(e) { 
                e.preventDefault(); 
                markNotificationsAsRead(); 
                setTimeout(() => loadNotifications(), 500); 
            }); 
        }
        
        function updateSidebarBadges() { 
            fetch('api_get_notifications_count.php')
                .then(r => r.json())
                .then(data => { 
                    ['approveLink', 'chatLink', 'quickApproveBtn', 'quickChatBtn'].forEach(id => { 
                        const el = document.getElementById(id); 
                        if (el) { 
                            const countKey = id.includes('chat') ? 'chat_count' : 'order_count'; 
                            const badgeClass = id.includes('sidebar') ? 'sidebar-badge' : 'action-badge'; 
                            let badge = el.querySelector(`.${badgeClass}`); 
                            if (data[countKey] > 0) { 
                                if (!badge) { 
                                    badge = document.createElement('span'); 
                                    badge.className = badgeClass; 
                                    el.appendChild(badge); 
                                } 
                                badge.textContent = data[countKey]; 
                            } else if (badge) { 
                                badge.remove(); 
                            } 
                        } 
                    }); 
                }); 
        }
        
        function checkNewNotifications() { 
            fetch('api_check_new_notifications.php?t=' + Date.now())
                .then(r => r.json())
                .then(data => { 
                    if (data.has_new) { 
                        updateNotificationCount(data.total_unread); 
                        const pendingElement = document.getElementById('pendingCount'); 
                        if (pendingElement && pendingElement.textContent != data.order_count) { 
                            pendingElement.textContent = data.order_count; 
                            pendingElement.classList.add('pulse-animation'); 
                            setTimeout(() => pendingElement.classList.remove('pulse-animation'), 1000); 
                        } 
                        if (notificationDropdown.classList.contains('show')) loadNotifications(); 
                        updateSidebarBadges(); 
                    } 
                }); 
        }
        
        setInterval(checkNewNotifications, 10000); 
        updateSidebarBadges();
    }
    
    // Search
    const searchInput = document.getElementById('searchInput');
    if (searchInput) { 
        searchInput.addEventListener('keypress', function(e) { 
            if (e.key === 'Enter') { 
                const query = this.value.trim(); 
                if (query !== '') window.location.href = 'penjual_produk.php?search=' + encodeURIComponent(query); 
            } 
        }); 
    }
    
    // User menu
    window.toggleUserMenu = function() { 
        const menu = document.querySelector('.user-profile'); 
        if (menu) menu.classList.toggle('active'); 
    };
    
    // DateTime
    function updateDateTime() { 
        const now = new Date(); 
        const dateEl = document.querySelector('.calendar-date'); 
        const dayEl = document.querySelector('.calendar-day'); 
        if (dateEl) dateEl.textContent = now.getDate().toString().padStart(2, '0'); 
        if (dayEl) { 
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']; 
            dayEl.textContent = days[now.getDay()]; 
        } 
    }
    setInterval(updateDateTime, 60000); 
    updateDateTime();
    
    // Hover effects
    document.querySelectorAll('.stat-card, .action-btn, .chat-item, .product-item').forEach(item => { 
        item.addEventListener('mouseenter', function() { 
            this.style.transform = 'translateY(-3px)'; 
            this.style.transition = 'transform 0.3s'; 
        }); 
        item.addEventListener('mouseleave', function() { 
            this.style.transform = 'translateY(0)'; 
        }); 
    });
});
</script>
</body>
</html>