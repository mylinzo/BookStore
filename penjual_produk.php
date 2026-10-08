<?php
session_start();
include 'koneksi.php';
require_once 'notificationsHelper.php';

// Check user login and role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'penjual') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id_user'];
$user_name = $_SESSION['nama'];

// Initialize alert message variables
$alert_message = '';
$alert_type = ''; // 'success', 'error', 'warning'

// Check for alert messages in session
if (isset($_SESSION['alert_message'])) {
    $alert_message = $_SESSION['alert_message'];
    $alert_type = $_SESSION['alert_type'] ?? 'error';
    unset($_SESSION['alert_message']);
    unset($_SESSION['alert_type']);
}

// Create uploads directory if not exists
$upload_dir = 'uploads/produk/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ===== NOTIFIKASI - GUNAKAN HELPER YANG STANDARDIZED =====
$notifications = getNotifications($conn, $user_id, 'penjual');

// Handle CRUD operations for produk with image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $nama_buku = trim($_POST['nama_buku']);
        $id_kategori = $_POST['id_kategori'];
        $stok = $_POST['stok'];
        $harga = $_POST['harga'];
        $modal = $_POST['modal'];
        $deskripsi = trim($_POST['deskripsi']);
        $alamat_produk = trim($_POST['alamat_penjual'] ?? '');
        
        // Calculate margin and keuntungan
        $margin = $harga - $modal;
        $keuntungan = $margin * $stok;
        
        // Handle image upload
        $foto_produk = null;
        $upload_error = '';
        
        if (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['foto_produk']['name'];
            $file_tmp = $_FILES['foto_produk']['tmp_name'];
            $file_size = $_FILES['foto_produk']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file size (max 50MB)
            if ($file_size > 52428800) {
                $upload_error = 'Ukuran file terlalu besar. Maksimal 50MB';
            } else {
                $is_valid_image = false;
                $mime_type = '';
                
                // Prefer getimagesize for reliability
                $image_check = @getimagesize($file_tmp);
                if ($image_check !== false) {
                    $is_valid_image = true;
                    $mime_type = $image_check['mime'] ?? '';
                }
                
                // Fallback to finfo if available
                if (!$is_valid_image && function_exists('finfo_file')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $file_tmp);
                    finfo_close($finfo);
                    if (strpos($mime_type, 'image/') === 0) {
                        $is_valid_image = true;
                    }
                }
                
                if (!$is_valid_image) {
                    $upload_error = 'File bukan gambar yang valid. Pastikan file adalah image asli';
                } else {
                    // Map MIME to extension when possible
                    $ext_map = [
                        'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg',
                        'image/png' => 'png', 'image/gif' => 'gif',
                        'image/webp' => 'webp', 'image/bmp' => 'bmp',
                        'image/x-ms-bmp' => 'bmp', 'image/svg+xml' => 'svg',
                        'image/vnd.microsoft.icon' => 'ico', 'image/x-icon' => 'ico'
                    ];
                    
                    $save_ext = '';
                    if (!empty($mime_type) && isset($ext_map[$mime_type])) {
                        $save_ext = $ext_map[$mime_type];
                    } else {
                        $orig_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $save_ext = $orig_ext ?: 'img';
                    }
                    
                    // Generate unique filename using derived extension
                    $new_file_name = uniqid('produk_', true) . '.' . $save_ext;
                    $upload_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $foto_produk = $new_file_name;
                        if (!file_exists($upload_path)) {
                            $upload_error = 'File berhasil di-upload tapi tidak ditemukan di server';
                            $foto_produk = null;
                        }
                    } else {
                        $upload_error = 'Gagal menyimpan file. Pastikan folder uploads/produk writable';
                    }
                }
            }
        } elseif (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] !== UPLOAD_ERR_OK) {
            // Handle upload errors
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File terlalu besar (sesuai php.ini)',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (sesuai form)',
                UPLOAD_ERR_PARTIAL => 'File hanya ter-upload sebagian',
                UPLOAD_ERR_NO_FILE => 'Tidak ada file yang di-upload',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder temporer tidak ditemukan',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                UPLOAD_ERR_EXTENSION => 'Extension PHP stop file upload'
            ];
            $upload_error = $upload_errors[$_FILES['foto_produk']['error']] ?? 'Unknown upload error';
        }
        
        if ($_POST['action'] === 'add') {
            // Check if book name already exists
            $check_duplicate = mysqli_prepare($conn, "SELECT id_produk FROM produk WHERE nama_buku = ?");
            mysqli_stmt_bind_param($check_duplicate, "s", $nama_buku);
            mysqli_stmt_execute($check_duplicate);
            $result_duplicate = mysqli_stmt_get_result($check_duplicate);
            
            if (mysqli_num_rows($result_duplicate) > 0) {
                // Book name already exists - set session alert
                $_SESSION['alert_message'] = '⚠️ Nama buku sudah ada! Gunakan nama buku yang berbeda.';
                $_SESSION['alert_type'] = 'error';
                mysqli_stmt_close($check_duplicate);
                // Redirect to show the error message
                header("Location: penjual_produk.php");
                exit;
            } else {
                mysqli_stmt_close($check_duplicate);
                
                // Insert produk with image (simpan alamat penjual untuk produk jika ada)
                if ($foto_produk) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO produk (nama_buku, id_kategori, id_penjual, stok, harga, modal, margin, keuntungan, deskripsi, foto_produk, alamat_penjual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "siiiidddsss", $nama_buku, $id_kategori, $user_id, $stok, $harga, $modal, $margin, $keuntungan, $deskripsi, $foto_produk, $alamat_produk);
                } else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO produk (nama_buku, id_kategori, id_penjual, stok, harga, modal, margin, keuntungan, deskripsi, alamat_penjual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "siiiidddss", $nama_buku, $id_kategori, $user_id, $stok, $harga, $modal, $margin, $keuntungan, $deskripsi, $alamat_produk);
                }
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['alert_message'] = '✓ Produk berhasil ditambahkan!';
                    $_SESSION['alert_type'] = 'success';
                    
                    // create notification for admins about new product
                    $new_product_id = mysqli_insert_id($conn);
                    $msg = 'Produk baru ditambahkan oleh ' . htmlspecialchars($_SESSION['nama'] ?? 'Penjual');
                    
                    // fetch all super_admin users
                    $admres = mysqli_query($conn, "SELECT id_user FROM users WHERE role = 'super_admin'");
                    while ($admrow = mysqli_fetch_assoc($admres)) {
                        @createNotification(
                            $conn,
                            intval($admrow['id_user']), // admin user id
                            'produk',                    // type
                            $new_product_id,             // reference id
                            'Produk baru diajukan',     // title
                            $msg
                        );
                    }
                } else {
                    if ($upload_error) {
                        $_SESSION['alert_message'] = '⚠️ Produk disimpan tapi ada masalah upload gambar: ' . $upload_error;
                        $_SESSION['alert_type'] = 'warning';
                    }
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($_POST['action'] === 'edit') {
            $id_produk = $_POST['id_produk'];
            
            // Check if book name already exists (excluding current product)
            $check_duplicate = mysqli_prepare($conn, "SELECT id_produk FROM produk WHERE nama_buku = ? AND id_produk != ?");
            mysqli_stmt_bind_param($check_duplicate, "si", $nama_buku, $id_produk);
            mysqli_stmt_execute($check_duplicate);
            $result_duplicate = mysqli_stmt_get_result($check_duplicate);
            
            if (mysqli_num_rows($result_duplicate) > 0) {
                // Book name already exists - set session alert
                $_SESSION['alert_message'] = '⚠️ Nama buku sudah ada! Gunakan nama buku yang berbeda.';
                $_SESSION['alert_type'] = 'error';
                mysqli_stmt_close($check_duplicate);
                // Redirect to show the error message
                header("Location: penjual_produk.php");
                exit;
            } else {
                mysqli_stmt_close($check_duplicate);
                
                // Get current image
                $current_image = null;
                $stmt_check = mysqli_prepare($conn, "SELECT foto_produk FROM produk WHERE id_produk = ? AND id_penjual = ?");
                mysqli_stmt_bind_param($stmt_check, "ii", $id_produk, $user_id);
                mysqli_stmt_execute($stmt_check);
                $result_check = mysqli_stmt_get_result($stmt_check);
                if ($row = mysqli_fetch_assoc($result_check)) {
                    $current_image = $row['foto_produk'];
                }
                mysqli_stmt_close($stmt_check);
                
                // Handle image deletion if checkbox is checked
                if (isset($_POST['hapus_foto']) && $_POST['hapus_foto'] == '1') {
                    if ($current_image) {
                        $old_path = $upload_dir . $current_image;
                        if (file_exists($old_path)) {
                            @unlink($old_path);
                        }
                    }
                    $foto_produk = null;
                } elseif ($foto_produk) {
                    // New image uploaded, delete old one
                    if ($current_image) {
                        $old_path = $upload_dir . $current_image;
                        if (file_exists($old_path)) {
                            @unlink($old_path);
                        }
                    }
                    // Use new image filename
                } else {
                    // Keep current image (no new image selected)
                    $foto_produk = $current_image;
                }
                
                // Update produk with image and alamat
                if ($foto_produk) {
                    $stmt = mysqli_prepare($conn, "UPDATE produk SET nama_buku = ?, id_kategori = ?, stok = ?, harga = ?, modal = ?, margin = ?, keuntungan = ?, deskripsi = ?, foto_produk = ?, alamat_penjual = ? WHERE id_produk = ? AND id_penjual = ?");
                    mysqli_stmt_bind_param($stmt, "siiidddssssi", $nama_buku, $id_kategori, $stok, $harga, $modal, $margin, $keuntungan, $deskripsi, $foto_produk, $alamat_produk, $id_produk, $user_id);
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE produk SET nama_buku = ?, id_kategori = ?, stok = ?, harga = ?, modal = ?, margin = ?, keuntungan = ?, deskripsi = ?, foto_produk = NULL, alamat_penjual = ? WHERE id_produk = ? AND id_penjual = ?");
                    mysqli_stmt_bind_param($stmt, "siiiddddssi", $nama_buku, $id_kategori, $stok, $harga, $modal, $margin, $keuntungan, $deskripsi, $alamat_produk, $id_produk, $user_id);
                }
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['alert_message'] = '✓ Produk berhasil diupdate!';
                    $_SESSION['alert_type'] = 'success';
                } else {
                    if ($upload_error) {
                        $_SESSION['alert_message'] = '⚠️ Produk diupdate tapi ada masalah upload gambar: ' . $upload_error;
                        $_SESSION['alert_type'] = 'warning';
                    }
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($_POST['action'] === 'delete') {
            $id_produk = $_POST['id_produk'];
            
            // Check if stock is 0 before deleting
            $stmt_check = mysqli_prepare($conn, "SELECT stok, foto_produk FROM produk WHERE id_produk = ? AND id_penjual = ?");
            mysqli_stmt_bind_param($stmt_check, "ii", $id_produk, $user_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            $row = mysqli_fetch_assoc($result_check);
            mysqli_stmt_close($stmt_check);
            
            if ($row['stok'] == 0) {
                // Delete image file if exists
                if ($row['foto_produk'] && file_exists($upload_dir . $row['foto_produk'])) {
                    unlink($upload_dir . $row['foto_produk']);
                }
                
                $stmt = mysqli_prepare($conn, "DELETE FROM produk WHERE id_produk = ? AND id_penjual = ?");
                mysqli_stmt_bind_param($stmt, "ii", $id_produk, $user_id);
                mysqli_stmt_execute($stmt);
            }
        }
        
        header("Location: penjual_produk.php");
        exit;
    }
}

// Handle AJAX request for product data
if (isset($_POST['get_product'])) {
    header('Content-Type: application/json');
    
    $id_produk = $_POST['id_produk'];
    $stmt = mysqli_prepare($conn, "SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.id_kategori = k.id_kategori WHERE p.id_produk = ? AND p.id_penjual = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id_produk, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($product = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    }
    exit;
}

// Get all products for this seller with search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_kategori = isset($_GET['kategori']) && !empty($_GET['kategori']) ? intval($_GET['kategori']) : '';

// Build the query based on search parameters
$query = "SELECT p.*, k.nama_kategori FROM produk p
          LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
          WHERE p.id_penjual = ?";
$params = [$user_id];
$types = "i";

// Add search condition (pencarian berdasarkan nama buku)
if (!empty($search)) {
    $query .= " AND p.nama_buku LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

// Add category filter (pencarian berdasarkan kategori)
if (!empty($search_kategori)) {
    $query .= " AND p.id_kategori = ?";
    $params[] = $search_kategori;
    $types .= "i";
}

$query .= " ORDER BY p.created_at DESC";

// Prepare and execute the query
$stmt_produk = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_produk, $types, ...$params);
}
mysqli_stmt_execute($stmt_produk);
$produk = mysqli_stmt_get_result($stmt_produk);

// Get all categories for dropdown
$stmt_kategori = mysqli_prepare($conn, "SELECT * FROM kategori WHERE id_penjual IS NULL ORDER BY nama_kategori");
mysqli_stmt_execute($stmt_kategori);
$kategori = mysqli_stmt_get_result($stmt_kategori);

// Get kategori name for filter message
$kategori_name = '';
if (!empty($search_kategori)) {
    $stmt_kat_name = mysqli_prepare($conn, "SELECT nama_kategori FROM kategori WHERE id_kategori = ?");
    mysqli_stmt_bind_param($stmt_kat_name, "i", $search_kategori);
    mysqli_stmt_execute($stmt_kat_name);
    $result_kat_name = mysqli_stmt_get_result($stmt_kat_name);
    if ($row_kat = mysqli_fetch_assoc($result_kat_name)) {
        $kategori_name = $row_kat['nama_kategori'];
    }
    mysqli_stmt_close($stmt_kat_name);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Kelola Produk - Pustaka Aruna</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.5;
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 1rem 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.025em;
        }
        
        .logo i {
            opacity: 0.9;
        }
        
        .search-box {
            flex: 1;
            max-width: 500px;
            margin: 0 30px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-primary);
        }
        
        .search-box input:focus {
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
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
            color: white;
            cursor: pointer;
            transition: transform 0.2s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .notification-bell:hover {
            transform: scale(1.1);
            background: rgba(255, 255, 255, 0.2);
        }
        
        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
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
            right: 80px;
            width: 350px;
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            display: none;
            z-index: 1001;
        }
        
        .notification-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        .notification-header {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--background);
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
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: var(--background);
        }
        
        .notification-item.unread {
            background: var(--primary-light);
        }
        
        .notification-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--primary);
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
            color: var(--text-primary);
            margin-bottom: 3px;
        }
        
        .notification-message {
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        
        .notification-time {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 3px;
        }
        
        .notification-footer {
            padding: 12px;
            text-align: center;
            background: var(--background);
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
            background: rgba(255, 255, 255, 0.15);
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .user-profile:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
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
            color: white;
        }
        
        .user-info p {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .online-status {
            width: 10px;
            height: 10px;
            background: var(--success);
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
        
        /* Main Content */
        .content {
            padding: 2rem;
            overflow-y: auto;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 2rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .page-title h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.025em;
        }
        
        .page-title p {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .search-add-container {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .search-box-header {
            position: relative;
        }
        
        .search-box-header select {
            padding: 12px 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-sm);
            width: 250px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-primary);
            cursor: pointer;
        }
        
        .search-box-header select:focus {
            outline: none;
            border-color: white;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }
        
        .search-box-header button {
            margin-left: 10px;
            background: white;
            border: 2px solid white;
            color: var(--primary);
        }
        
        .search-box-header button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        /* Buttons */
        .btn-primary, .btn-secondary {
            padding: 12px 25px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: white;
            color: var(--primary);
            border: 2px solid white;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 2rem;
        }
        
        .product-card {
            background: var(--surface);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s;
            border: 1px solid var(--border);
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .product-image {
            height: 200px;
            width: 100%;
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1;
        }
        
        .badge-stock {
            background: var(--success-bg);
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .badge-low {
            background: var(--error-bg);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .no-image {
            text-align: center;
            color: var(--text-secondary);
        }
        
        .no-image i {
            font-size: 3rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        /* Alert Notifications */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            border-left: 4px solid;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: var(--success-bg);
            color: #166534;
            border-left-color: var(--success);
        }
        
        .alert-error {
            background: var(--error-bg);
            color: #991b1b;
            border-left-color: var(--error);
        }
        
        .alert-warning {
            background: var(--warning-bg);
            color: #92400e;
            border-left-color: var(--warning);
        }
        
        .alert i {
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0;
            opacity: 0.6;
            transition: opacity 0.2s;
        }
        
        .alert-close:hover {
            opacity: 1;
        }
        
        .product-content {
            padding: 1.5rem;
        }
        
        .product-category {
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .product-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .product-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 3px;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .profit-positive {
            color: var(--success);
        }
        
        .profit-negative {
            color: var(--error);
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            border-top: 1px solid var(--border);
            padding-top: 1.5rem;
        }
        
        .action-btn {
            flex: 1;
            padding: 10px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            border: 1px solid transparent;
            cursor: pointer;
        }
        
        .btn-edit {
            background: var(--background);
            color: var(--text-primary);
            border-color: var(--border);
        }
        
        .btn-edit:hover {
            background: var(--primary-light);
            border-color: var(--primary);
        }
        
        .btn-delete {
            background: var(--error-bg);
            color: var(--error);
            border-color: #fca5a5;
        }
        
        .btn-delete:hover {
            background: var(--error);
            color: white;
        }
        
        .btn-delete:disabled {
            background: var(--background);
            color: var(--text-secondary);
            border-color: var(--border);
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-detail {
            background: var(--info-bg);
            color: #1e40af;
            border-color: #93c5fd;
        }
        
        .btn-detail:hover {
            background: var(--info);
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto 1.5rem;
        }
        
        /* Modal Styling */
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
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background: var(--surface);
            border-radius: var(--radius);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--surface);
            z-index: 1;
            border-radius: var(--radius) var(--radius) 0 0;
        }
        
        .modal-header h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border: 1px solid var(--border);
        }
        
        .close-modal:hover {
            background: var(--background);
            color: var(--error);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
            overflow-y: auto;
            max-height: calc(90vh - 120px);
        }
        
        /* Form Styling */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            background: var(--surface);
            color: var(--text-primary);
            transition: all 0.2s;
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Image Upload Styling */
        .image-upload-container {
            background: var(--background);
            border: 2px dashed var(--border);
            border-radius: var(--radius-sm);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 1.5rem;
        }
        
        .image-upload-container:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        .image-upload-container i {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .image-upload-container p {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .image-upload-container span {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        #imagePreview {
            margin-top: 1rem;
        }
        
        #imagePreview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }
        
        .current-image {
            margin-top: 1rem;
        }
        
        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }
        
        .image-actions {
            margin-top: 1rem;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .remove-image {
            color: var(--error);
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            font-weight: 500;
        }
        
        .remove-image:hover {
            text-decoration: underline;
        }
        
        .calculation-group {
            background: var(--background);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }
        
        .calculation-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        
        .calculation-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .calc-label {
            color: var(--text-secondary);
        }
        
        .calc-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .calc-profit {
            color: var(--success);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }
        
        .btn-save {
            background: var(--primary);
            color: white;
            padding: 12px 30px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-cancel {
            background: var(--surface);
            color: var(--text-primary);
            padding: 12px 30px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .btn-cancel:hover {
            background: var(--background);
            border-color: var(--primary);
        }
        
        /* Detail Modal */
        .product-detail-modal {
            position: relative;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(248, 250, 252, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--border);
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header-top {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .search-add-container {
                width: 100%;
                justify-content: space-between;
            }
            
            .search-box-header select {
                width: 250px;
            }
            
            .notification-dropdown {
                right: 20px;
                width: 300px;
            }
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
            
            .product-details {
                grid-template-columns: 1fr;
            }
            
            .search-add-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box-header select {
                width: 100%;
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
            
            .main-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .notification-dropdown {
                width: 280px;
            }
            
            .user-info {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .modal-body {
                padding: 1.5rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .product-actions {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-save, .btn-cancel {
                width: 100%;
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
            background: var(--background);
        }
        
        .sidebar::-webkit-scrollbar-thumb,
        .content::-webkit-scrollbar-thumb,
        .notification-list::-webkit-scrollbar-thumb {
            background: var(--secondary);
            border-radius: 3px;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
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
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo-container">
            <a href="penjual_dashboard.php" class="logo">
                <i class="fas fa-book-open"></i>
                <span>Pustaka Aruna</span>
            </a>
        </div>
        
        <div class="search-box">
            <input type="text" placeholder="Cari berdasarkan nama buku..." id="navbarSearch" value="<?php echo htmlspecialchars($search); ?>">
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
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
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
                <h3>PENJUAL BUKU</h3>
                <p>@<?php echo htmlspecialchars($user_name); ?></p>
            </div>

            <!-- Main Menu -->
            <div class="sidebar-title">MENU UTAMA</div>
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="penjual_dashboard.php" class="sidebar-link">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="penjual_produk.php" class="sidebar-link active">
                        <i class="fas fa-book"></i> Produk Buku
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="pesanan_penjual.php" class="sidebar-link" id="sidebarApprove">
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

            <!-- Account Menu -->
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

        <!-- Main Content -->
        <main class="content">
            <!-- Header -->
            <div class="header">
                <div class="header-top">
                    <div class="page-title">
                        <h1><i class="fas fa-book"></i> Kelola Produk Buku</h1>
                        <p>"Atur dan kelola koleksi buku Anda dengan mudah"</p>
                    </div>
                    <div class="search-add-container">
                        <div class="search-box-header">
                            <select name="kategori" id="kategoriSelect">
                                <option value="">Kategori</option>
                                <?php
                                mysqli_data_seek($kategori, 0);
                                while ($k = mysqli_fetch_assoc($kategori)):
                                ?>
                                    <option value="<?php echo $k['id_kategori']; ?>" <?php echo $search_kategori == $k['id_kategori'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($k['nama_kategori']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button class="btn-primary" onclick="openModal('add')">
                            <i class="fas fa-plus"></i> Tambah Produk
                        </button>
                    </div>
                </div>
                
                <!-- Search Info -->
                <?php if (!empty($search) || !empty($search_kategori)): ?>
                    <div class="search-info" style="color: rgba(255, 255, 255, 0.9); font-weight: 500; margin-top: 15px;">
                        <i class="fas fa-search"></i> Hasil pencarian:
                        <?php if (!empty($search)): ?>
                            "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php endif; ?>
                        <?php if (!empty($search_kategori)): ?>
                            dalam kategori <strong><?php echo htmlspecialchars($kategori_name ?? ''); ?></strong>
                        <?php endif; ?>
                        - ditemukan <?php echo mysqli_num_rows($produk); ?> produk
                        <a href="penjual_produk.php" id="clearFilter" style="margin-left: 15px; color: white; text-decoration: underline;">
                            <i class="fas fa-times"></i> Hapus filter
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Alert Notification -->
            <?php if (!empty($alert_message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($alert_type); ?>" id="alertNotification">
                    <?php if ($alert_type === 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php elseif ($alert_type === 'error'): ?>
                        <i class="fas fa-exclamation-circle"></i>
                    <?php else: ?>
                        <i class="fas fa-info-circle"></i>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($alert_message); ?></span>
                    <button class="alert-close" onclick="closeAlert()"><i class="fas fa-times"></i></button>
                </div>
                <script>
                    // Auto-dismiss alert after 5 seconds
                    setTimeout(function() {
                        const alertEl = document.getElementById('alertNotification');
                        if (alertEl) {
                            alertEl.style.animation = 'slideDown 0.3s ease reverse';
                            setTimeout(() => alertEl.remove(), 300);
                        }
                    }, 5000);
                    
                    function closeAlert() {
                        const alertEl = document.getElementById('alertNotification');
                        if (alertEl) {
                            alertEl.style.animation = 'slideDown 0.3s ease reverse';
                            setTimeout(() => alertEl.remove(), 300);
                        }
                    }
                </script>
            <?php endif; ?>

            <!-- Products Grid -->
            <div id="productsGrid">
                <?php if (mysqli_num_rows($produk) > 0): ?>
                    <div class="products-grid">
                        <?php while ($produk_item = mysqli_fetch_assoc($produk)):
                            $profit_color = $produk_item['keuntungan'] >= 0 ? 'profit-positive' : 'profit-negative';
                        ?>
                            <div class="product-card" id="product-<?php echo $produk_item['id_produk']; ?>">
                                <div class="product-image">
                                    <?php if (!empty($produk_item['foto_produk']) && file_exists($upload_dir . $produk_item['foto_produk'])): ?>
                                        <img src="<?php echo htmlspecialchars($upload_dir . $produk_item['foto_produk']); ?>"
                                             alt="<?php echo htmlspecialchars($produk_item['nama_buku']); ?>"
                                             onerror="this.parentElement.innerHTML='<div class=&quot;no-image&quot;><i class=&quot;fas fa-book&quot;></i><p>Gambar tidak tersedia</p></div>'">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-book"></i>
                                            <p>Tidak ada gambar</p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-badge <?php echo $produk_item['stok'] > 5 ? 'badge-stock' : 'badge-low'; ?>">
                                        <?php echo $produk_item['stok']; ?> stok
                                    </div>
                                </div>
                                <div class="product-content">
                                    <div class="product-category">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($produk_item['nama_kategori'] ?? 'Tidak ada kategori'); ?>
                                    </div>
                                    <h3 class="product-title"><?php echo htmlspecialchars($produk_item['nama_buku']); ?></h3>
                                    <p class="product-description"><?php echo htmlspecialchars(substr($produk_item['deskripsi'] ?? '', 0, 100)); ?>...</p>
                                    <div class="product-price">Rp <?php echo number_format($produk_item['harga'], 0, ',', '.'); ?></div>
                                    <div class="product-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Modal</span>
                                            <span class="detail-value">Rp <?php echo number_format($produk_item['modal'], 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Stok</span>
                                            <span class="detail-value"><?php echo $produk_item['stok']; ?> unit</span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Keuntungan</span>
                                            <span class="detail-value <?php echo $profit_color; ?>">Rp <?php echo number_format($produk_item['keuntungan'], 0, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                    <div class="product-actions">
                                        <button class="action-btn btn-edit" onclick="loadProductForEdit(<?php echo $produk_item['id_produk']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="action-btn btn-detail" onclick="showDetail(<?php echo $produk_item['id_produk']; ?>)">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                        <button class="action-btn btn-delete" onclick="deleteProduct(<?php echo $produk_item['id_produk']; ?>)" 
                                                <?php echo $produk_item['stok'] > 0 ? 'disabled' : ''; ?> 
                                                title="<?php echo $produk_item['stok'] > 0 ? 'Stok harus 0 untuk menghapus' : 'Hapus produk ini'; ?>">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>Tidak ada produk</h3>
                        <p><?php echo !empty($search) || !empty($search_kategori) ?
                            'Tidak ditemukan produk dengan kriteria pencarian yang Anda masukkan.' :
                            'Belum ada produk yang ditambahkan. Mulai dengan menambahkan produk pertama Anda!'; ?></p>
                        <?php if (!empty($search) || !empty($search_kategori)): ?>
                            <a href="penjual_produk.php" class="btn-primary" style="margin-top: 1rem; text-decoration: none;">
                                <i class="fas fa-times"></i> Hapus Filter
                            </a>
                        <?php else: ?>
                            <button class="btn-primary" onclick="openModal('add')" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i> Tambah Produk Pertama
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Tambah Produk Baru</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="productForm" action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id_produk" id="formId">
                    <input type="hidden" name="hapus_foto" id="hapusFoto" value="0">
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">Foto Produk</label>
                            <div class="image-upload-container" onclick="document.getElementById('fileInput').click()" title="Klik untuk memilih gambar">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Klik untuk mengunggah foto produk</p>
                                <span style="display: block; margin-top: 8px; font-size: 0.85rem; color: var(--text-secondary);">
                                    ✓ Format: <strong>Semua jenis gambar diperbolehkan</strong><br>
                                    ✓ Ukuran: <strong>Max 50MB</strong>
                                </span>
                                <input type="file" id="fileInput" name="foto_produk" accept="image/*" style="display: none;" onchange="previewImage(this)">
                            </div>
                            <div id="imagePreview"></div>
                            <div id="currentImage" class="current-image" style="display: none;">
                                <p>Foto saat ini:</p>
                                <img id="currentImageSrc" src="" alt="Current Image">
                                <div class="image-actions">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="deleteImageCheckbox" name="deleteImage" value="1" onchange="toggleImageDelete()">
                                        <label for="deleteImageCheckbox">Hapus foto ini</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="nama_buku">Nama Buku *</label>
                            <input type="text" id="nama_buku" name="nama_buku" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="id_kategori">Kategori *</label>
                            <select id="id_kategori" name="id_kategori" class="form-select" required>
                                <option value="">Pilih Kategori</option>
                                <?php
                                mysqli_data_seek($kategori, 0);
                                while ($kat = mysqli_fetch_assoc($kategori)):
                                ?>
                                    <option value="<?php echo $kat['id_kategori']; ?>"><?php echo htmlspecialchars($kat['nama_kategori']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="stok">Stok *</label>
                            <input type="number" id="stok" name="stok" class="form-input" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="harga">Harga Jual (Rp) *</label>
                            <input type="number" id="harga" name="harga" class="form-input" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="modal">Modal (Rp) *</label>
                            <input type="number" id="modal" name="modal" class="form-input" min="0" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="deskripsi">Deskripsi</label>
                            <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="4"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label" for="alamat_penjual">Link Alamat Toko (opsional)</label>
                            <input type="url" id="alamat_penjual" name="alamat_penjual" class="form-textarea" placeholder="https://maps.google.com/... atau link website toko Anda"/>
                        </div>
                    </div>
                    
                    <div class="calculation-group">
                        <h4 style="margin-bottom: 1rem; color: var(--text-primary); font-weight: 600;">Perhitungan Otomatis</h4>
                        <div class="calculation-row">
                            <span class="calc-label">Harga Jual:</span>
                            <span class="calc-value" id="calcHarga">Rp 0</span>
                        </div>
                        <div class="calculation-row">
                            <span class="calc-label">Modal:</span>
                            <span class="calc-value" id="calcModal">Rp 0</span>
                        </div>
                        <div class="calculation-row">
                            <span class="calc-label">Keuntungan Total:</span>
                            <span class="calc-value calc-profit" id="calcKeuntungan">Rp 0</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Simpan Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Detail Product Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Detail Produk</h2>
                <button class="close-modal" onclick="closeDetailModal()">&times;</button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Detail content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentProductId = null;
        let categories = [];
        let searchTimeout;

        // ===== NOTIFICATION SYSTEM =====
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationCount = document.getElementById('notificationCount');
            const notificationList = document.getElementById('notificationList');
            const navbarSearch = document.getElementById('navbarSearch');
            const kategoriSelect = document.getElementById('kategoriSelect');
            const productsGrid = document.getElementById('productsGrid');
            const clearFilter = document.getElementById('clearFilter');

            // ===== SEARCH FUNCTIONALITY =====
            // Real-time search by book name (navbar search)
            if (navbarSearch) {
                navbarSearch.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const query = this.value.trim();
                    searchTimeout = setTimeout(() => {
                        if (query.length >= 2 || query.length === 0) {
                            performSearch(query, kategoriSelect ? kategoriSelect.value : '');
                        }
                    }, 500);
                });

                // Handle enter key
                navbarSearch.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(searchTimeout);
                        const query = this.value.trim();
                        performSearch(query, kategoriSelect ? kategoriSelect.value : '');
                    }
                });
            }

            // Category filter change
            if (kategoriSelect) {
                kategoriSelect.addEventListener('change', function() {
                    const kategori = this.value;
                    const searchQuery = navbarSearch ? navbarSearch.value.trim() : '';
                    performSearch(searchQuery, kategori);
                });
            }

            // Clear filter
            if (clearFilter) {
                clearFilter.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = 'penjual_produk.php';
                });
            }

            // Function to perform search via AJAX
            function performSearch(searchQuery, kategori) {
                showLoading();
                let url = 'penjual_produk.php?';
                if (searchQuery) {
                    url += 'search=' + encodeURIComponent(searchQuery) + '&';
                }
                if (kategori) {
                    url += 'kategori=' + encodeURIComponent(kategori);
                }

                // Use fetch to get the filtered page content
                fetch(url + '&ajax=1&t=' + Date.now())
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newGrid = doc.getElementById('productsGrid');
                        const newHeader = doc.querySelector('.header .search-info');
                        
                        if (newGrid && productsGrid) {
                            productsGrid.innerHTML = newGrid.innerHTML;
                        }
                        
                        // Update search info in header
                        const headerInfo = document.querySelector('.header .search-info');
                        if (headerInfo && newHeader) {
                            headerInfo.innerHTML = newHeader.innerHTML;
                        } else if (headerInfo && !newHeader) {
                            headerInfo.remove();
                        } else if (!headerInfo && newHeader) {
                            document.querySelector('.header').appendChild(newHeader.cloneNode(true));
                        }
                        
                        // Reattach event listeners to new elements
                        attachEventListeners();
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        showNotification('Terjadi kesalahan saat mencari', 'error');
                    })
                    .finally(() => {
                        hideLoading();
                    });
            }

            // Function to attach event listeners to dynamically loaded elements
            function attachEventListeners() {
                // Reattach edit, detail, delete button listeners
                document.querySelectorAll('.btn-edit').forEach(btn => {
                    btn.onclick = function() {
                        const productId = this.closest('.product-card').id.replace('product-', '');
                        loadProductForEdit(productId);
                    };
                });

                document.querySelectorAll('.btn-detail').forEach(btn => {
                    btn.onclick = function() {
                        const productId = this.closest('.product-card').id.replace('product-', '');
                        showDetail(productId);
                    };
                });

                document.querySelectorAll('.btn-delete').forEach(btn => {
                    btn.onclick = function() {
                        const productId = this.closest('.product-card').id.replace('product-', '');
                        deleteProduct(productId);
                    };
                });
            }

            // Load categories for modal
            const categorySelect = document.getElementById('id_kategori');
            if (categorySelect) {
                categories = Array.from(categorySelect.options).map(option => ({
                    value: option.value,
                    text: option.text
                }));
            }

            // Initial calculation
            updateCalculations();

            // Notification system
            if (notificationBell && notificationDropdown && notificationCount && notificationList) {
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

                // Load notifications function
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
                        notificationList.innerHTML = '<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-bell-slash" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 15px;"></i><p>Tidak ada notifikasi</p></div>';
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
                            <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--error); margin-bottom: 15px;"></i>
                            <p>Gagal memuat notifikasi</p>
                            <button onclick="location.reload()" style="margin-top: 10px; padding: 8px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">
                                <i class="fas fa-sync-alt"></i> Muat Ulang
                            </button>
                        </div>
                    `;
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
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

                // Check for new notifications every 10 seconds
                setInterval(() => {
                    fetch('api_check_new_notifications.php?t=' + Date.now())
                        .then(response => response.json())
                        .then(data => {
                            if (data.has_new && notificationDropdown.classList.contains('show')) {
                                loadNotifications();
                            }
                            if (data.total_unread != notificationCount.textContent) {
                                updateNotificationCount(data.total_unread);
                            }
                        })
                        .catch(error => console.error('Error checking notifications:', error));
                }, 10000);
            }
        });

        // ===== PRODUCT MANAGEMENT FUNCTIONS =====
        function openModal(action, productId = null) {
            const modal = document.getElementById('productModal');
            const modalTitle = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            const formId = document.getElementById('formId');
            
            if (action === 'add') {
                modalTitle.textContent = 'Tambah Produk Baru';
                formAction.value = 'add';
                formId.value = '';
                
                // Reset form
                document.getElementById('productForm').reset();
                document.getElementById('id_kategori').value = '';
                document.getElementById('hapusFoto').value = '0';
                document.getElementById('imagePreview').innerHTML = '';
                document.getElementById('currentImage').style.display = 'none';
                document.getElementById('deleteImageCheckbox').checked = false;
                document.getElementById('fileInput').value = '';
                
                // Reset calculations
                updateCalculations();
            }
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            const modal = document.getElementById('productModal');
            modal.style.display = 'none';
        }

        function closeDetailModal() {
            const modal = document.getElementById('detailModal');
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
                closeDetailModal();
            }
        }

        function updateCalculations() {
            const harga = parseFloat(document.getElementById('harga').value) || 0;
            const modal = parseFloat(document.getElementById('modal').value) || 0;
            const stok = parseInt(document.getElementById('stok').value) || 0;
            
            const margin = harga - modal;
            const keuntungan = margin * stok;
            
            document.getElementById('calcHarga').textContent = 'Rp ' + harga.toLocaleString('id-ID');
            document.getElementById('calcModal').textContent = 'Rp ' + modal.toLocaleString('id-ID');
            document.getElementById('calcKeuntungan').textContent = 'Rp ' + keuntungan.toLocaleString('id-ID');
            
            const profitElement = document.getElementById('calcKeuntungan');
            profitElement.style.color = keuntungan >= 0 ? 'var(--success)' : 'var(--error)';
        }

        document.getElementById('harga')?.addEventListener('input', updateCalculations);
        document.getElementById('modal')?.addEventListener('input', updateCalculations);
        document.getElementById('stok')?.addEventListener('input', updateCalculations);

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const currentImageDiv = document.getElementById('currentImage');
            const checkbox = document.getElementById('deleteImageCheckbox');
            
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '200px';
                    img.style.maxHeight = '200px';
                    img.style.borderRadius = 'var(--radius-sm)';
                    img.style.boxShadow = 'var(--shadow-md)';
                    img.style.border = '1px solid var(--border)';
                    
                    preview.innerHTML = `<p style="color: var(--text-primary); margin-bottom: 8px;">📁 ${file.name} (${fileSizeMB} MB)</p>`;
                    preview.appendChild(img);
                    
                    currentImageDiv.style.display = 'none';
                    checkbox.checked = false;
                    document.getElementById('hapusFoto').value = '0';
                };
                reader.readAsDataURL(file);
            }
        }

        function toggleImageDelete() {
            const checkbox = document.getElementById('deleteImageCheckbox');
            document.getElementById('hapusFoto').value = checkbox.checked ? '1' : '0';
        }

        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
        }

        function deleteProduct(productId) {
            const confirmed = confirm('Hapus produk ini? Tindakan ini tidak dapat dibatalkan.');
            if (!confirmed) return;
            
            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id_produk', productId);
            
            fetch('penjual_produk.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    showNotification('Produk berhasil dihapus!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error('Failed to delete');
                }
            })
            .catch(error => {
                console.error(error);
                hideLoading();
                showNotification('Gagal menghapus produk. Stok harus 0.', 'error');
            });
        }

        function loadProductForEdit(productId) {
            showLoading();
            
            const formData = new FormData();
            formData.append('get_product', 'true');
            formData.append('id_produk', productId);
            
            fetch('get_product_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalTitle').textContent = 'Edit Produk';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('formId').value = data.product.id_produk;
                    
                    document.getElementById('nama_buku').value = data.product.nama_buku;
                    document.getElementById('id_kategori').value = data.product.id_kategori;
                    document.getElementById('stok').value = data.product.stok;
                    document.getElementById('harga').value = data.product.harga;
                    document.getElementById('modal').value = data.product.modal;
                    document.getElementById('deskripsi').value = data.product.deskripsi;
                    document.getElementById('alamat_penjual').value = data.product.alamat_penjual || '';
                    
                    const currentImageDiv = document.getElementById('currentImage');
                    const imagePreviewDiv = document.getElementById('imagePreview');
                    
                    if (data.product.foto_produk) {
                        currentImageDiv.style.display = 'block';
                        document.getElementById('currentImageSrc').src = 'uploads/produk/' + data.product.foto_produk;
                        imagePreviewDiv.innerHTML = '';
                    } else {
                        currentImageDiv.style.display = 'none';
                    }
                    
                    document.getElementById('deleteImageCheckbox').checked = false;
                    document.getElementById('hapusFoto').value = '0';
                    document.getElementById('fileInput').value = '';
                    
                    updateCalculations();
                    document.getElementById('productModal').style.display = 'flex';
                } else {
                    showNotification('Gagal memuat data produk', 'error');
                }
            })
            .catch(error => {
                console.error(error);
                showNotification('Terjadi kesalahan', 'error');
            })
            .finally(() => hideLoading());
        }

        function showDetail(productId) {
            showLoading();
            
            const formData = new FormData();
            formData.append('get_product', 'true');
            formData.append('id_produk', productId);
            
            fetch('get_product_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const p = data.product;
                    const harga = parseInt(p.harga).toLocaleString('id-ID');
                    const modal = parseInt(p.modal).toLocaleString('id-ID');
                    const margin = (parseInt(p.harga) - parseInt(p.modal)).toLocaleString('id-ID');
                    const keuntungan = parseInt(p.keuntungan).toLocaleString('id-ID');
                    
                    let imageHtml = '';
                    if (p.foto_produk) {
                        imageHtml = `<img src="uploads/produk/${p.foto_produk}" alt="${p.nama_buku}"
                                style="width: 100%; height: 200px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border);">`;
                    } else {
                        imageHtml = `<div style="background: var(--background); height: 200px; border-radius: var(--radius-sm); border: 2px dashed var(--border);
                                display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-book" style="font-size: 4rem; color: var(--text-secondary); opacity: 0.5;"></i>
                            </div>`;
                    }
                    
                    const alamatHtml = p.alamat_penjual ?
                        `<div style="margin-bottom: 1.5rem;">
                            <h4 style="margin-bottom: 10px; color: var(--text-primary); font-weight: 600;">Alamat Toko</h4>
                            <a href="${p.alamat_penjual}" target="_blank" style="color: var(--primary); text-decoration: underline; word-break: break-all;">
                                ${p.alamat_penjual}
                            </a>
                        </div>` : '';
                    
                    const detailHTML = `
                        <div class="product-detail-modal">
                            <div style="margin-bottom: 2rem;">${imageHtml}</div>
                            <div style="margin-bottom: 1.5rem;">
                                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--text-primary);">${escapeHtml(p.nama_buku)}</h3>
                                <p style="color: var(--text-secondary); margin-bottom: 15px;">
                                    <i class="fas fa-tag" style="color: var(--primary);"></i> Kategori: ${escapeHtml(p.nama_kategori || 'Tidak ada')}
                                </p>
                                <p style="font-size: 2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 20px;">
                                    Rp ${harga}
                                </p>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 2rem;">
                                <div style="background: var(--background); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">Stok</div>
                                    <div style="font-size: 1.2rem; font-weight: 600; color: var(--text-primary);">${p.stok} unit</div>
                                </div>
                                <div style="background: var(--background); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">Modal</div>
                                    <div style="font-size: 1.2rem; font-weight: 600; color: var(--text-primary);">Rp ${modal}</div>
                                </div>
                                <div style="background: var(--background); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">Keuntungan</div>
                                    <div style="font-size: 1.2rem; font-weight: 600; color: var(--success);">Rp ${keuntungan}</div>
                                </div>
                            </div>
                            ${alamatHtml}
                            <div style="margin-bottom: 2rem;">
                                <h4 style="margin-bottom: 10px; color: var(--text-primary); font-weight: 600;">Deskripsi</h4>
                                <p style="color: var(--text-secondary); line-height: 1.6; white-space: pre-wrap;">${escapeHtml(p.deskripsi) || 'Tidak ada deskripsi'}</p>
                            </div>
                            <div style="display: flex; gap: 10px; justify-content: center; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                                <button onclick="loadProductForEdit(${p.id_produk}); closeDetailModal();"
                                    style="background: var(--primary); color: white; border: none; padding: 10px 30px; border-radius: var(--radius-sm); cursor: pointer; font-weight: 500;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="closeDetailModal()"
                                    style="background: var(--surface); color: var(--text-primary); border: 1px solid var(--border); padding: 10px 30px; border-radius: var(--radius-sm); cursor: pointer; font-weight: 500;">
                                    <i class="fas fa-times"></i> Tutup
                                </button>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('detailContent').innerHTML = detailHTML;
                    document.getElementById('detailModal').style.display = 'flex';
                } else {
                    showNotification('Gagal memuat detail produk', 'error');
                }
            })
            .catch(error => {
                console.error(error);
                showNotification('Terjadi kesalahan', 'error');
            })
            .finally(() => hideLoading());
        }

        function showNotification(message, type = 'success') {
            const existing = document.getElementById('notification');
            if (existing) existing.remove();
            
            const notification = document.createElement('div');
            notification.id = 'notification';
            notification.style.cssText = `
                position: fixed;
                top: 30px;
                right: 30px;
                padding: 16px 24px;
                border-radius: var(--radius-sm);
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 10px;
                z-index: 2500;
                animation: slideIn 0.3s ease-out;
                box-shadow: var(--shadow-md);
                max-width: 400px;
            `;
            
            if (type === 'success') {
                notification.style.background = 'var(--success-bg)';
                notification.style.color = '#166534';
                notification.style.border = '1px solid #86efac';
                notification.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
            } else if (type === 'error') {
                notification.style.background = 'var(--error-bg)';
                notification.style.color = '#991b1b';
                notification.style.border = '1px solid #fca5a5';
                notification.innerHTML = '<i class="fas fa-times-circle"></i> ' + message;
            } else {
                notification.style.background = 'var(--warning-bg)';
                notification.style.color = '#92400e';
                notification.style.border = '1px solid #fcd34d';
                notification.innerHTML = '<i class="fas fa-info-circle"></i> ' + message;
            }
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        document.getElementById('productForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const nama = document.getElementById('nama_buku').value.trim();
            const kategori = document.getElementById('id_kategori').value;
            const stok = document.getElementById('stok').value;
            const harga = document.getElementById('harga').value;
            const modal = document.getElementById('modal').value;
            const fileInput = document.getElementById('fileInput');
            const formAction = document.getElementById('formAction').value;
            const formId = document.getElementById('formId').value;
            
            if (!nama) {
                showNotification('Nama buku harus diisi!', 'error');
                document.getElementById('nama_buku').focus();
                return;
            }
            
            if (!kategori) {
                showNotification('Kategori harus dipilih!', 'error');
                document.getElementById('id_kategori').focus();
                return;
            }
            
            if (stok < 0) {
                showNotification('Stok tidak boleh negatif!', 'error');
                document.getElementById('stok').focus();
                return;
            }
            
            if (harga <= 0) {
                showNotification('Harga harus lebih dari 0!', 'error');
                document.getElementById('harga').focus();
                return;
            }
            
            if (modal < 0) {
                showNotification('Modal tidak boleh negatif!', 'error');
                document.getElementById('modal').focus();
                return;
            }
            
            if (fileInput.files.length > 0 && fileInput.files[0].size > 52428800) {
                showNotification('Ukuran file terlalu besar. Maksimal 50MB', 'error');
                return;
            }
            
            showLoading();
            
            const formData = new FormData(document.getElementById('productForm'));
            
            fetch('penjual_produk.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    const action = document.getElementById('formAction').value;
                    const text = action === 'add' ? 'ditambahkan' : 'diperbarui';
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        html: `<p style="font-size: 16px; color: var(--success);">Produk berhasil ${text}!</p>`,
                        confirmButtonText: 'OK',
                        confirmButtonColor: 'var(--success)',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            closeModal();
                            location.reload();
                        }
                    });
                } else {
                    throw new Error('Network error');
                }
            })
            .catch(error => {
                console.error(error);
                hideLoading();
                
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    html: '<p style="font-size: 16px; color: var(--error);">Gagal menyimpan produk. Silakan coba lagi.</p>',
                    confirmButtonText: 'OK',
                    confirmButtonColor: 'var(--error)'
                });
            });
        });

        function toggleUserMenu() {
            const menu = document.querySelector('.user-profile');
            menu.classList.toggle('active');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>