<?php
/**
 * Halaman Transaksi Pembayaran - Multi QRIS per Penjual
 */

session_start();
include 'koneksi.php';

// Check login
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pembeli') {
    header('Location: login.php');
    exit;
}

// ===== CHECK DATABASE SETUP =====
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'penjual_qris'");
if (mysqli_num_rows($check_table) == 0) {
    header('Location: setup_pembayaran_auto.php?redirect=transaksi');
    exit;
}

$id_pembeli = $_SESSION['id_user'];
$error = '';
$success = '';
$action = $_GET['action'] ?? 'pilih_metode';

// ===== HANDLE FORM SUBMISSION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_pembayaran') {
        $id_pesanan = isset($_POST['id_pesanan']) ? (int)$_POST['id_pesanan'] : 0;
        $id_penjual = isset($_POST['id_penjual']) ? (int)$_POST['id_penjual'] : 0;
        $metode = $_POST['metode_pembayaran'] ?? 'qris';

        // If only id_penjual provided, apply upload to all pending orders for that vendor
        if ($id_penjual && !$id_pesanan) {
            // fetch all relevant pesanan for this buyer & vendor
            $stmt = mysqli_prepare($conn, "SELECT id_pesanan, total_harga FROM pesanan WHERE id_pembeli = ? AND id_penjual = ? AND status = 'menunggu_pembayaran'");
            mysqli_stmt_bind_param($stmt, "ii", $id_pembeli, $id_penjual);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $orders = mysqli_fetch_all($res, MYSQLI_ASSOC);

            if (empty($orders)) {
                $error = 'Tidak ada pesanan untuk penjual ini yang bisa dibayar.';
            } else if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
                $error = 'File bukti pembayaran harus diupload';
            } else {
                $file = $_FILES['bukti_pembayaran'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
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
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                    $ext_map = ['image/jpeg'=>'jpg','image/pjpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/bmp'=>'bmp','image/svg+xml'=>'svg'];
                    $save_ext = $file_ext;
                    if ($is_image && !empty($mime_type) && isset($ext_map[$mime_type])) {
                        $save_ext = $ext_map[$mime_type];
                    }

                    $new_filename = 'bukti_vendor_' . $id_penjual . '_' . time() . '.' . $save_ext;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Insert pembayaran_detail per pesanan and update pesanan status
                        $stmt_ins = mysqli_prepare($conn, "INSERT INTO pembayaran_detail (id_pesanan, id_pembeli, id_penjual, metode_pembayaran, jumlah_pembayaran, bukti_pembayaran) VALUES (?, ?, ?, ?, ?, ?)");
                        foreach ($orders as $o) {
                            mysqli_stmt_bind_param($stmt_ins, "iiisds", $o['id_pesanan'], $id_pembeli, $id_penjual, $metode, $o['total_harga'], $new_filename);
                            mysqli_stmt_execute($stmt_ins);

                            $status = 'menunggu_pembayaran';
                            $stmt_up = mysqli_prepare($conn, "UPDATE pesanan SET status = ?, metode_pembayaran = ? WHERE id_pesanan = ?");
                            mysqli_stmt_bind_param($stmt_up, "ssi", $status, $metode, $o['id_pesanan']);
                            mysqli_stmt_execute($stmt_up);
                        }

                        $success = 'Bukti pembayaran berhasil diupload untuk penjual ini. Menunggu verifikasi penjual.';
                        $action = 'lihat_pesanan';
                    } else {
                        $error = 'Gagal menyimpan file bukti pembayaran';
                    }
                }
            }
        } else {
            // existing per-pesanan flow (single pesanan)
            $id_pesanan = (int)$id_pesanan;

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
                // Handle file upload (same logic)
                $file = $_FILES['bukti_pembayaran'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

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
                } else if ($file['size'] > 5242880) { // 5MB
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
                        // Insert payment record
                        $stmt = mysqli_prepare($conn, "INSERT INTO pembayaran_detail (id_pesanan, id_pembeli, id_penjual, metode_pembayaran, jumlah_pembayaran, bukti_pembayaran) VALUES (?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "iiisds", $id_pesanan, $id_pembeli, $id_penjual, $metode, $order['total_harga'], $new_filename);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            // Update pesanan status
                            $status = 'menunggu_pembayaran';
                            $stmt2 = mysqli_prepare($conn, "UPDATE pesanan SET status = ?, metode_pembayaran = ? WHERE id_pesanan = ?");
                            mysqli_stmt_bind_param($stmt2, "ssi", $status, $metode, $id_pesanan);
                            mysqli_stmt_execute($stmt2);
                            
                            $success = 'Bukti pembayaran berhasil diupload. Menunggu verifikasi penjual.';
                            $action = 'lihat_pesanan';
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
}

// ===== GET CART DATA (Active orders yang belum dibayar) =====
$stmt = mysqli_prepare($conn, "
    SELECT
        p.id_pesanan,
        p.id_penjual,
        p.id_produk,
        p.quantity,
        p.total_harga,
        p.status,
        p.metode_pembayaran,
        p.created_at,
        pr.nama_buku,
        pr.foto_produk,
        u.nama as nama_user,
        u.email
    FROM pesanan p
    JOIN produk pr ON p.id_produk = pr.id_produk
    JOIN users u ON p.id_penjual = u.id_user
    WHERE p.id_pembeli = ? AND p.status = 'menunggu_pembayaran'
    ORDER BY p.id_penjual, p.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $id_pembeli);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pesanan_list = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Group by vendor
$pesanan_by_vendor = [];
$total_semua = 0;
foreach ($pesanan_list as $p) {
    $vendor_id = $p['id_penjual'];
    if (!isset($pesanan_by_vendor[$vendor_id])) {
        $pesanan_by_vendor[$vendor_id] = [
            'vendor_id' => $vendor_id,
            'vendor_name' => $p['nama_user'],
            'vendor_email' => $p['email'],
            'pesanan' => [],
            'subtotal' => 0
        ];
    }
    $pesanan_by_vendor[$vendor_id]['pesanan'][] = $p;
    $pesanan_by_vendor[$vendor_id]['subtotal'] += $p['total_harga'];
    $total_semua += $p['total_harga'];
}

// Get penjual QRIS data
$qris_data = [];
$stmt = mysqli_prepare($conn, "SELECT id_penjual, nomor_rekening, nama_bank, nama_rekening FROM penjual_qris WHERE status = 'aktif'");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $qris_data[$row['id_penjual']] = $row;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Pembayaran - Aksara-Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .vendor-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .vendor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .vendor-info h3 {
            font-size: 18px;
            color: #667eea;
            margin-bottom: 5px;
        }

        .vendor-info p {
            font-size: 12px;
            color: #999;
        }

        .subtotal {
            background: #f8f9fa;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            color: #667eea;
            font-size: 16px;
        }

        .pesanan-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .pesanan-item:last-child {
            border-bottom: none;
        }

        .product-image {
            width: 80px;
            height: 80px;
            background: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .product-qty {
            font-size: 12px;
            color: #999;
        }

        .product-price {
            font-weight: 700;
            color: #667eea;
            font-size: 14px;
        }

        .payment-method {
            background: #f0f5ff;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 3px solid #667eea;
        }

        .payment-method h4 {
            font-size: 14px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .payment-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .payment-details div {
            background: white;
            padding: 8px;
            border-radius: 4px;
        }

        .payment-details strong {
            display: block;
            color: #667eea;
            margin-bottom: 3px;
        }

        .btn-upload {
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            width: 100%;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .btn-upload:hover {
            background: #218838;
        }

        .btn-upload:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .summary-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }

        .summary-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .summary-total {
            border-top: 2px solid #f0f0f0;
            padding-top: 15px;
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .back-btn:hover {
            color: #764ba2;
        }

        @media (max-width: 768px) {
            .payment-details {
                grid-template-columns: 1fr;
            }
            
            .vendor-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1><i class="fas fa-credit-card"></i> Transaksi Pembayaran</h1>
            <p>Lakukan pembayaran untuk pesanan Anda</p>
        </div>
    </header>

    <div class="container">
        <a href="keranjang_produk.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kembali ke Keranjang
        </a>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($pesanan_by_vendor)): ?>
            <div class="vendor-section">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Tidak ada pesanan yang perlu dibayar</h3>
                    <p>Kembali ke keranjang untuk menambah produk</p>
                </div>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 1fr 320px; gap: 20px;">
                <div>
                    <!-- PESANAN PER VENDOR -->
                    <?php foreach ($pesanan_by_vendor as $vendor): ?>
                        <div class="vendor-section">
                            <div class="vendor-header">
                                <div class="vendor-info">
                                    <h3><i class="fas fa-store"></i> <?php echo htmlspecialchars($vendor['vendor_name']); ?></h3>
                                    <p><?php echo htmlspecialchars($vendor['vendor_email']); ?></p>
                                </div>
                                <div class="subtotal">
                                    Rp <?php echo number_format($vendor['subtotal'], 0, ',', '.'); ?>
                                </div>
                            </div>

                            <!-- DAFTAR PRODUK -->
                            <?php foreach ($vendor['pesanan'] as $p): ?>
                                <div class="pesanan-item">
                                    <div class="product-image">
                                        <?php if ($p['foto_produk']): ?>
                                            <img src="uploads/produk/<?php echo htmlspecialchars($p['foto_produk']); ?>" alt="<?php echo htmlspecialchars($p['nama_buku']); ?>">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-image" style="color: #ccc; font-size: 30px;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <div class="product-name"><?php echo htmlspecialchars($p['nama_buku']); ?></div>
                                        <div class="product-qty">Qty: <?php echo $p['quantity']; ?></div>
                                        <div class="product-price">Rp <?php echo number_format($p['total_harga'], 0, ',', '.'); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- METODE PEMBAYARAN -->
                            <div class="payment-method">
                                <h4><i class="fas fa-university"></i> Metode Pembayaran: Transfer (TF)</h4>
                                
                                <?php if (isset($qris_data[$vendor['vendor_id']])): ?>
                                    <?php $qris = $qris_data[$vendor['vendor_id']]; ?>
                                    <div class="payment-details">
                                        <div>
                                            <strong>Nomor Rekening:</strong>
                                            <?php echo htmlspecialchars($qris['nomor_rekening']); ?>
                                        </div>
                                        <div>
                                            <strong>Nama Bank:</strong>
                                            <?php echo htmlspecialchars($qris['nama_bank']); ?>
                                        </div>
                                        <div style="grid-column: 1 / -1;">
                                            <strong>Atas Nama:</strong>
                                            <?php echo htmlspecialchars($qris['nama_rekening']); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p style="color: #999; font-size: 12px;">Penjual belum setup QRIS. Hubungi penjual.</p>
                                <?php endif; ?>

                                <!-- UPLOAD BUKTI PEMBAYARAN -->
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_pembayaran">
                                    <input type="hidden" name="id_penjual" value="<?php echo $vendor['vendor_id']; ?>">
                                    <input type="hidden" name="metode_pembayaran" value="tf">

                                    <div style="margin-top: 10px;">
                                        <label style="display: block; font-size: 12px; color: #7f8c8d; margin-bottom: 5px;">
                                            <strong>Upload Bukti Pembayaran untuk <?php echo htmlspecialchars($vendor['vendor_name']); ?> (Total: Rp <?php echo number_format($vendor['subtotal'],0,',','.'); ?>)</strong>
                                        </label>
                                        <input type="file" name="bukti_pembayaran" accept="image/*,.jfif,.pdf" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                        <small style="color: #999; display: block; margin-top: 5px;">JPG, PNG, GIF, atau PDF (Max 5MB). Upload satu bukti untuk semua pesanan dari penjual ini.</small>
                                    </div>

                                    <?php
                                    // Disable button if all pesanan for this vendor already pending
                                    $all_pending = true;
                                    foreach ($vendor['pesanan'] as $pp) {
                                        if ($pp['status'] !== 'menunggu_pembayaran') { $all_pending = false; break; }
                                    }
                                    ?>

                                    <button type="submit" class="btn-upload" <?php echo ($all_pending) ? 'disabled' : ''; ?>>
                                        <?php echo ($all_pending) ? 'Sudah Diupload ✓' : 'Kirim Bukti Pembayaran untuk Penjual Ini'; ?>
                                    </button>
                                </form>
                            </div>

                            <?php
                                $has_pending = false;
                                foreach ($vendor['pesanan'] as $pp) {
                                    if ($pp['status'] === 'menunggu_pembayaran') { $has_pending = true; break; }
                                }
                            ?>
                            <?php if ($has_pending): ?>
                                <div style="background: #d4edda; padding: 10px; border-radius: 4px; font-size: 12px; color: #155724;">
                                    <i class="fas fa-hourglass-half"></i> Menunggu verifikasi penjual untuk salah satu pesanan...
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- RINGKASAN -->
                <div class="summary-box">
                    <div class="summary-title">
                        <i class="fas fa-receipt"></i> Ringkasan Pembayaran
                    </div>

                    <?php foreach ($pesanan_by_vendor as $vendor): ?>
                        <div class="summary-item">
                            <span><?php echo substr(htmlspecialchars($vendor['vendor_name']), 0, 15); ?></span>
                            <strong>Rp <?php echo number_format($vendor['subtotal'], 0, ',', '.'); ?></strong>
                        </div>
                    <?php endforeach; ?>

                    <div class="summary-total">
                        Total: Rp <?php echo number_format($total_semua, 0, ',', '.'); ?>
                    </div>

                    <div style="background: #f0f5ff; padding: 12px; border-radius: 5px; margin-top: 15px; font-size: 12px; color: #667eea;">
                        <strong>Catatan:</strong> Setiap penjual memiliki QRIS terpisah. Upload bukti pembayaran untuk setiap penjual.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
