<?php
/**
 * Halaman Transaksi Pembeli - Upload Bukti Pembayaran
 */

session_start();
include 'koneksi.php';

// Check login
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pembeli') {
    header('Location: login.php');
    exit;
}

$id_pembeli = $_SESSION['id_user'];
$error = '';
$success = '';
$action = $_GET['action'] ?? 'belanja';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_post = $_POST['action'] ?? '';
    
    if ($action_post === 'upload_pembayaran') {
        $id_pesanan = (int)$_POST['id_pesanan'];
        $id_penjual = (int)$_POST['id_penjual'];
        $metode_raw = $_POST['metode_pembayaran'] ?? 'qris';
        
        // Map form values to database ENUM values
        $metode_map = [
            'tf_bank' => 'rekening',
            'qris' => 'qris',
            'cash' => 'qris'
        ];
        $metode = $metode_map[$metode_raw] ?? 'qris';
        
        // Verify order belongs to this buyer
        $stmt = mysqli_prepare($conn, "SELECT id_pesanan, total_harga FROM pesanan WHERE id_pesanan = ? AND id_pembeli = ?");
        mysqli_stmt_bind_param($stmt, "ii", $id_pesanan, $id_pembeli);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($result);
        
        if (!$order) {
            $error = 'Pesanan tidak ditemukan';
        } else if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
            $error = 'File bukti pembayaran harus diupload';
        } else {
            $file = $_FILES['bukti_pembayaran'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // Allow PDF or any valid image (detected via getimagesize/finfo)
            $is_pdf = ($file_ext === 'pdf');
            $is_image = false;
            $mime_type = '';

            if (!$is_pdf) {
                $img = @getimagesize($file['tmp_name']);
                if ($img !== false) {
                    $is_image = true;
                    $mime_type = $img['mime'] ?? '';
                } elseif (function_exists('finfo_file')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    if (strpos($mime_type, 'image/') === 0) $is_image = true;
                }
            }

            if (!($is_pdf || $is_image)) {
                $error = 'Format file tidak valid. Unggah PDF atau file gambar.';
            } else if ($file['size'] > 5242880) {
                $error = 'Ukuran file maksimal 5MB';
            } else {
                $upload_dir = 'uploads/bukti_pembayaran/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Determine save extension
                $ext_map = ['image/jpeg'=>'jpg','image/pjpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/bmp'=>'bmp','image/svg+xml'=>'svg'];
                $save_ext = $file_ext;
                if ($is_image && !empty($mime_type) && isset($ext_map[$mime_type])) {
                    $save_ext = $ext_map[$mime_type];
                }

                $new_filename = 'bukti_' . $id_pesanan . '_' . time() . '.' . $save_ext;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO pembayaran_detail (id_pesanan, id_pembeli, id_penjual, metode_pembayaran, jumlah_pembayaran, bukti_pembayaran) VALUES (?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "iiisds", $id_pesanan, $id_pembeli, $id_penjual, $metode, $order['total_harga'], $new_filename);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $status = 'menunggu_verifikasi';
                        // UPDATE pesanan dengan status dan bukti_pembayaran
                        $stmt2 = mysqli_prepare($conn, "UPDATE pesanan SET status = ?, metode_pembayaran = ?, bukti_pembayaran = ? WHERE id_pesanan = ?");
                        mysqli_stmt_bind_param($stmt2, "sssi", $status, $metode, $new_filename, $id_pesanan);
                        mysqli_stmt_execute($stmt2);
                        
                        // Set session untuk highlight pesanan yang baru dibayar
                        $_SESSION['payment_success'] = true;
                        $_SESSION['payment_order_id'] = $id_pesanan;
                        $_SESSION['payment_message'] = 'Bukti pembayaran berhasil diupload. Menunggu verifikasi penjual.';
                        
                        // Redirect ke halaman pesanan_pembeli.php
                        header('Location: pesanan_pembeli.php?success=upload&id=' . $id_pesanan);
                        exit;
                    } else {
                        $error = 'Gagal menyimpan data pembayaran: ' . mysqli_error($conn);
                    }
                } else {
                    $error = 'Gagal menyimpan file bukti pembayaran';
                }
            }
        }
    }
}

// Get pending orders - EXCLUDE spam sellers
$stmt = mysqli_prepare($conn, "
    SELECT
        p.id_pesanan,
        p.id_penjual,
        p.id_produk,
        p.quantity,
        p.total_harga,
        p.status,
        pr.nama_buku,
        pr.foto_produk,
        u.nama as nama_penjual,
        u.email
    FROM pesanan p
    JOIN produk pr ON p.id_produk = pr.id_produk
    JOIN users u ON p.id_penjual = u.id_user
    WHERE p.id_pembeli = ? 
    AND p.status IN ('menunggu_pembayaran', 'menunggu_verifikasi')
    AND LOWER(u.nama) NOT IN ('yeona', 'felicia')
    ORDER BY p.id_penjual, p.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $id_pembeli);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pesanan_list = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Group by vendor
$pesanan_by_vendor = [];
foreach ($pesanan_list as $p) {
    $vendor_id = $p['id_penjual'];
    if (!isset($pesanan_by_vendor[$vendor_id])) {
        $pesanan_by_vendor[$vendor_id] = [
            'vendor_id' => $vendor_id,
            'vendor_name' => $p['nama_penjual'],
            'vendor_email' => $p['email'],
            'pesanan' => [],
            'subtotal' => 0,
            'has_uploaded' => false
        ];
    }
    $pesanan_by_vendor[$vendor_id]['pesanan'][] = $p;
    $pesanan_by_vendor[$vendor_id]['subtotal'] += $p['total_harga'];
    
    // Check if any order from this vendor has status 'menunggu_verifikasi'
    if ($p['status'] === 'menunggu_verifikasi') {
        $pesanan_by_vendor[$vendor_id]['has_uploaded'] = true;
    }
}

// Get seller QRIS data
$qris_data = [];
$stmt = mysqli_prepare($conn, "SELECT id_penjual, nomor_rekening, nama_bank, nama_rekening FROM penjual_qris WHERE status = 'aktif'");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $qris_data[$row['id_penjual']] = $row;
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 'upload') {
    $success = 'Bukti pembayaran berhasil diupload. Silakan cek status pesanan Anda.';
}
            
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Bukti Pembayaran - Pustaka Aruna</title>
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
            min-height: 100vh;
            line-height: 1.5;
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            padding: 24px 0;
            color: white;
            box-shadow: var(--shadow-md);
        }

        .page-header h1 {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.025em;
        }

        .page-header h1 i { opacity: 0.9; }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* Alerts */
        .alert {
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.9375rem;
            border: 1px solid transparent;
        }

        .alert i { font-size: 1rem; margin-top: 2px; flex-shrink: 0; }

        .alert-error {
            background-color: var(--error-bg);
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert-success {
            background-color: var(--success-bg);
            color: #166534;
            border-color: #bbf7d0;
        }

        /* Vendor Section */
        .vendor-section {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .vendor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 12px;
        }

        .vendor-info h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vendor-info h3 i { color: var(--primary); }

        .vendor-info p {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .subtotal {
            background: var(--primary-light);
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            color: var(--primary);
            font-size: 1rem;
            border: 1px solid #bfdbfe;
        }

        /* Order Items */
        .pesanan-item {
            display: flex;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }

        .pesanan-item:last-child { border-bottom: none; }

        .item-image {
            width: 80px;
            height: 100px;
            background: var(--background);
            border-radius: var(--radius-sm);
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-image img { width: 100%; height: 100%; object-fit: cover; }
        .item-image i { font-size: 2rem; color: var(--text-secondary); opacity: 0.5; }

        .item-info { flex: 1; min-width: 0; }

        .item-name {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--text-primary);
            font-size: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .item-meta {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .item-price {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.9375rem;
        }

        .item-status { margin-top: 8px; }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-warning {
            background-color: var(--warning-bg);
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .status-info {
            background-color: var(--info-bg);
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        /* Payment Info Box */
        .payment-info {
            background: var(--background);
            padding: 16px;
            border-radius: var(--radius-sm);
            margin: 16px 0;
            border-left: 4px solid var(--primary);
            border: 1px solid var(--border);
        }

        .payment-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-info h4 i { color: var(--primary); }

        .bank-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .bank-item {
            background: var(--surface);
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }

        .bank-item strong {
            display: block;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .bank-item span {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .bank-item.full-width { grid-column: 1 / -1; }

        .instruction-box {
            background: var(--warning-bg);
            border: 1px solid #fcd34d;
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            color: #92400e;
            font-size: 0.875rem;
        }

        .instruction-box strong { display: block; margin-bottom: 6px; font-weight: 600; }
        .instruction-box p { margin: 0; line-height: 1.4; }

        .amount-box {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .amount-detail {
            padding: 8px 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }

        .amount-detail div:first-child {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 2px;
        }

        .amount-detail div:last-child {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
        }

        /* Order Code Display (for Cash) */
        .order-code-box {
            background: var(--surface);
            border: 2px dashed var(--border);
            border-radius: var(--radius-sm);
            padding: 20px;
            text-align: center;
            margin: 16px 0;
        }

        .order-code-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .order-code-value {
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 2px;
            background: var(--primary-light);
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            display: inline-block;
            border: 1px solid #bfdbfe;
        }

        .order-code-note {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-top: 12px;
            line-height: 1.4;
        }

        .order-code-note i { color: var(--warning); margin-right: 4px; }

        /* Payment Method Selector */
        .payment-method-selector {
            margin: 16px 0;
            padding: 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }

        .payment-method-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .payment-options {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .payment-option:hover {
            background: var(--primary-light);
            border-color: #bfdbfe;
        }

        .payment-option input[type="radio"] {
            accent-color: var(--primary);
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .payment-option.selected {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 500;
        }

        /* Upload Section */
        .upload-section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .upload-hint {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 20px;
            background: var(--primary);
            color: white;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 0.9375rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }

        .file-label:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .file-label:active { transform: translateY(0); }

        .file-name-preview {
            margin-top: 10px;
            padding: 10px 12px;
            background: var(--background);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            color: var(--text-primary);
            display: none;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border);
        }

        .file-name-preview.show { display: flex; }
        .file-name-preview i { color: var(--success); }

        .upload-progress {
            display: none;
            margin-top: 15px;
            text-align: center;
        }

        .upload-progress.show { display: block; }

        .upload-progress-inner {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--info-bg);
            border-radius: var(--radius-sm);
            border: 1px solid #93c5fd;
            color: #1e40af;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .upload-progress-inner i { animation: spin 1s linear infinite; }

        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

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

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background-color: var(--surface);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background-color: var(--background);
            transform: translateY(-1px);
        }

        .btn-block { width: 100%; justify-content: center; }

        .btn-view-orders {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--primary);
            color: white;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
        }

        .btn-view-orders:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
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
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 24px;
            font-size: 0.9375rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .vendor-header { flex-direction: column; align-items: flex-start; }
            .bank-details { grid-template-columns: 1fr; }
            .payment-options { flex-direction: column; gap: 8px; }
            .pesanan-item { flex-direction: column; align-items: flex-start; }
            .item-actions { width: 100%; justify-content: flex-end; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--background); }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

        /* Focus visible */
        .payment-option input[type="radio"]:focus-visible,
        .file-label:focus-visible,
        .btn:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Animation */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .alert { animation: fadeIn 0.25s ease-out; }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="page-header">
        <h1><i class="fas fa-credit-card"></i> Upload Bukti Pembayaran</h1>
    </header>

    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <div style="margin-top: 10px;">
                    <a href="pesanan_pembeli.php" class="btn-view-orders">
                        <i class="fas fa-list"></i> Lihat Semua Pesanan
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($pesanan_by_vendor)): ?>
            <div class="vendor-section">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Tidak ada pesanan yang perlu dibayar</h3>
                    <p>Anda tidak memiliki pesanan dengan status menunggu pembayaran.</p>
                    <a href="dashboard_pembeli.php?action=belanja" class="btn-view-orders" style="margin-top: 15px;">
                        <i class="fas fa-shopping-bag"></i> Belanja Sekarang
                    </a>
                </div>
            </div>
        <?php else: ?>

            <?php foreach ($pesanan_by_vendor as $vendor): ?>
                <div class="vendor-section">
                    <div class="vendor-header">
                        <div class="vendor-info">
                            <h3><i class="fas fa-store"></i> <?php echo htmlspecialchars($vendor['vendor_name']); ?></h3>
                            <p><?php echo htmlspecialchars($vendor['vendor_email']); ?></p>
                        </div>
                        <div class="subtotal">
                            Total: Rp <?php echo number_format($vendor['subtotal'], 0, ',', '.'); ?>
                        </div>
                    </div>

                    <!-- DAFTAR PESANAN -->
                    <?php foreach ($vendor['pesanan'] as $index => $p): ?>
                        <div class="pesanan-item">
                            <div class="item-image">
                                <?php if ($p['foto_produk']): ?>
                                    <img src="uploads/produk/<?php echo htmlspecialchars($p['foto_produk']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-book"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-info">
                                <div class="item-name"><?php echo htmlspecialchars($p['nama_buku']); ?></div>
                                <div class="item-meta">
                                    Jumlah: <?php echo $p['quantity']; ?> x Rp <?php echo number_format($p['total_harga'] / $p['quantity'], 0, ',', '.'); ?>
                                </div>
                                <div class="item-price">Rp <?php echo number_format($p['total_harga'], 0, ',', '.'); ?></div>
                                <div class="item-status">
                                    <?php if ($p['status'] == 'menunggu_verifikasi'): ?>
                                        <span class="status-badge status-info">
                                            <i class="fas fa-hourglass-half"></i> Menunggu Verifikasi
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-warning">
                                            <i class="fas fa-clock"></i> Menunggu Pembayaran
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- METODE PEMBAYARAN DAN UPLOAD BUKTI -->
                    <div class="payment-info">
                        <h4><i class="fas fa-university"></i> Pilih Metode Pembayaran</h4>
                        
                        <!-- Payment Method Selector -->
                        <div class="payment-method-selector">
                            <label class="payment-method-label">Metode Pembayaran</label>
                            <div class="payment-options">
                                <label class="payment-option" onclick="selectPaymentMethod('cash', '<?php echo $vendor['vendor_id']; ?>')">
                                    <input type="radio" name="metode_pembayaran_<?php echo $vendor['vendor_id']; ?>" value="cash" id="cash_<?php echo $vendor['vendor_id']; ?>">
                                    <span><i class="fas fa-money-bill-wave"></i> Cash / Tunai</span>
                                </label>
                                <label class="payment-option selected" onclick="selectPaymentMethod('tf_bank', '<?php echo $vendor['vendor_id']; ?>')">
                                    <input type="radio" name="metode_pembayaran_<?php echo $vendor['vendor_id']; ?>" value="tf_bank" id="tf_bank_<?php echo $vendor['vendor_id']; ?>" checked>
                                    <span><i class="fas fa-building-columns"></i> Transfer Bank</span>
                                </label>
                            </div>
                        </div>

                        <?php if (isset($qris_data[$vendor['vendor_id']])): ?>
                            <?php $qris = $qris_data[$vendor['vendor_id']]; ?>
                            
                            <!-- Bank Details (only shown for TF) -->
                            <div id="bank-details-<?php echo $vendor['vendor_id']; ?>" class="bank-transfer-section">
                                <div class="bank-details">
                                    <div class="bank-item">
                                        <strong>No. Rekening</strong>
                                        <span><?php echo htmlspecialchars($qris['nomor_rekening']); ?></span>
                                    </div>
                                    <div class="bank-item">
                                        <strong>Bank</strong>
                                        <?php echo htmlspecialchars($qris['nama_bank']); ?>
                                    </div>
                                    <div class="bank-item full-width">
                                        <strong>Atas Nama</strong>
                                        <?php echo htmlspecialchars($qris['nama_rekening']); ?>
                                    </div>
                                </div>

                                <div class="instruction-box">
                                    <strong>Instruksi Pembayaran:</strong>
                                    <p>Silakan transfer sesuai total pesanan via QRIS, lalu unggah bukti pembayaran.</p>
                                    <div class="amount-box">
                                        <div class="amount-detail">
                                            <div>Total QRIS</div>
                                            <div>Rp <?php echo number_format($vendor['subtotal'], 0, ',', '.'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Code Display (only shown for Cash) -->
                            <div id="cash-section-<?php echo $vendor['vendor_id']; ?>" class="cash-section" style="display: none;">
                                <div class="order-code-box">
                                    <div class="order-code-label">Kode Pesanan Anda</div>
                                    <div class="order-code-value">#<?php echo str_pad($vendor['pesanan'][0]['id_pesanan'], 8, '0', STR_PAD_LEFT); ?></div>
                                    <p class="order-code-note">
                                        <i class="fas fa-info-circle"></i>
                                        Tunjukkan kode ini kepada penjual saat mengambil barang. Pembayaran dilakukan secara tunai di tempat.
                                    </p>
                                </div>
                                <div class="alert alert-success" style="margin-bottom: 0;">
                                    <i class="fas fa-check-circle"></i>
                                    Pesanan akan diproses setelah konfirmasi pembayaran tunai dari penjual.
                                </div>
                            </div>

                            <!-- CEK APAKAH SUDAH PERNAH UPLOAD -->
                            <?php 
                            $has_pending_payment = false;
                            $has_uploaded = false;
                            
                            foreach ($vendor['pesanan'] as $p) {
                                if ($p['status'] == 'menunggu_pembayaran') {
                                    $has_pending_payment = true;
                                } else if ($p['status'] == 'menunggu_verifikasi') {
                                    $has_uploaded = true;
                                }
                            }
                            ?>

                            <!-- UPLOAD SECTION (only for TF and pending payment) -->
                            <?php if ($has_pending_payment): ?>
                                <div id="upload-section-<?php echo $vendor['vendor_id']; ?>" class="upload-section">
                                    <form method="POST" enctype="multipart/form-data" id="uploadForm_<?php echo $vendor['vendor_id']; ?>">
                                        <input type="hidden" name="action" value="upload_pembayaran">
                                        <input type="hidden" name="id_pesanan" value="<?php echo $vendor['pesanan'][0]['id_pesanan']; ?>">
                                        <input type="hidden" name="id_penjual" value="<?php echo $vendor['vendor_id']; ?>">
                                        <input type="hidden" name="metode_pembayaran" id="metode_<?php echo $vendor['vendor_id']; ?>" value="tf_bank">

                                        <div class="upload-hint">
                                            <i class="fas fa-lightbulb"></i>
                                            Upload bukti transfer (foto/PDF, maks. 5MB)
                                        </div>

                                        <div class="file-input-wrapper">
                                            <input type="file" 
                                                   id="bukti_<?php echo $vendor['vendor_id']; ?>" 
                                                   name="bukti_pembayaran" 
                                                   accept=".jpg,.jpeg,.png,.gif,.pdf"
                                                   class="file-input"
                                                   onchange="handleFileSelect(this, '<?php echo $vendor['vendor_id']; ?>')">
                                            <label for="bukti_<?php echo $vendor['vendor_id']; ?>" class="file-label">
                                                <i class="fas fa-cloud-upload-alt"></i> Pilih File Bukti
                                            </label>
                                        </div>

                                        <div id="filePreview_<?php echo $vendor['vendor_id']; ?>" class="file-name-preview">
                                            <i class="fas fa-check-circle"></i>
                                            <span id="fileName_<?php echo $vendor['vendor_id']; ?>"></span>
                                        </div>

                                        <div id="uploadProgress_<?php echo $vendor['vendor_id']; ?>" class="upload-progress">
                                            <div class="upload-progress-inner">
                                                <i class="fas fa-spinner"></i>
                                                <span>Sedang upload...</span>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="alert alert-error" style="margin-top: 10px;">
                                <i class="fas fa-exclamation-triangle"></i>
                                Penjual belum mengatur data rekening. Silakan hubungi penjual untuk informasi pembayaran.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Tombol Lihat Semua Pesanan -->
            <div style="text-align: center; margin: 20px 0;">
                <a href="pesanan_pembeli.php" class="btn-view-orders" style="padding: 12px 30px;">
                    <i class="fas fa-list"></i> Lihat Semua Pesanan Saya
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Payment method selection toggle
        function selectPaymentMethod(method, vendorId) {
            // Update radio button state
            document.querySelectorAll(`input[name="metode_pembayaran_${vendorId}"]`).forEach(radio => {
                radio.checked = (radio.value === method);
            });
            
            // Update visual selection
            document.querySelectorAll(`.payment-option`).forEach(opt => {
                opt.classList.remove('selected');
            });
            document.querySelector(`.payment-option input[value="${method}"]`).closest('.payment-option').classList.add('selected');
            
            // Toggle sections
            const bankSection = document.getElementById(`bank-details-${vendorId}`);
            const cashSection = document.getElementById(`cash-section-${vendorId}`);
            const uploadSection = document.getElementById(`upload-section-${vendorId}`);
            const metodeInput = document.getElementById(`metode_${vendorId}`);
            
            if (method === 'cash') {
                if (bankSection) bankSection.style.display = 'none';
                if (cashSection) cashSection.style.display = 'block';
                if (uploadSection) uploadSection.style.display = 'none';
                if (metodeInput) metodeInput.value = 'cash';
            } else {
                if (bankSection) bankSection.style.display = 'block';
                if (cashSection) cashSection.style.display = 'none';
                if (uploadSection) uploadSection.style.display = 'block';
                if (metodeInput) metodeInput.value = 'tf_bank';
            }
        }

        // Handle file selection and preview
        function handleFileSelect(fileInput, vendorId) {
            const file = fileInput.files[0];
            
            if (!file) return;
            
            // Validasi ukuran file (5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('Ukuran file terlalu besar. Maksimal 5MB');
                fileInput.value = '';
                return;
            }
            
            // Validasi tipe file
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                alert('Tipe file tidak valid. Gunakan JPG, PNG, GIF, atau PDF');
                fileInput.value = '';
                return;
            }
            
            // Tampilkan preview nama file
            const preview = document.getElementById(`filePreview_${vendorId}`);
            const fileName = document.getElementById(`fileName_${vendorId}`);
            if (preview && fileName) {
                fileName.textContent = file.name;
                preview.classList.add('show');
            }
            
            // Auto-submit form
            const form = document.getElementById(`uploadForm_${vendorId}`);
            const progress = document.getElementById(`uploadProgress_${vendorId}`);
            
            if (form && progress) {
                if (confirm('Apakah Anda yakin ingin mengupload bukti pembayaran ini?')) {
                    progress.classList.add('show');
                    form.submit();
                } else {
                    fileInput.value = '';
                    preview.classList.remove('show');
                }
            }
        }

        // Auto-hide alert after 5 seconds
        document.querySelectorAll('.alert-success').forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s, transform 0.5s';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-8px)';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });

        // Initialize: set default payment method to tf_bank
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($pesanan_by_vendor as $vendor): ?>
                selectPaymentMethod('tf_bank', '<?php echo $vendor['vendor_id']; ?>');
            <?php endforeach; ?>
        });
    </script>
</body>
</html>