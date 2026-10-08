<?php
/**
 * Verifikasi Pembayaran - Penjual
 * Penjual daftar pembayaran yang masuk dan verifikasi
 */

session_start();
include 'koneksi.php';

// Auto create notifikasi table if not exists
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

// Fix: Drop and recreate if column missing
$check_col = @mysqli_query($conn, "SELECT tipe FROM notifikasi LIMIT 1");
if (!$check_col) {
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

// Check seller login
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penjual') {
    header('Location: login.php');
    exit;
}

$id_penjual = $_SESSION['id_user'];
$error = '';
$success = '';

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_payment') {
        $id_pesanan = (int)$_POST['id_pesanan'];
        $verification_status = $_POST['verification_status'] ?? 'tolak'; // terima atau tolak
        $catatan = trim($_POST['catatan'] ?? '');
        
        // Verify this order belongs to this seller
        $stmt = mysqli_prepare($conn, "SELECT id_pesanan, id_pembeli FROM pesanan WHERE id_pesanan = ? AND id_penjual = ?");
        mysqli_stmt_bind_param($stmt, "ii", $id_pesanan, $id_penjual);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($result);
        
        if ($order) {
            if ($verification_status === 'terima') {
                $new_status = 'dibayar';
                $stmt = mysqli_prepare($conn, "UPDATE pesanan SET status = ?, catatan_penjual = ?, updated_at = NOW() WHERE id_pesanan = ?");
                mysqli_stmt_bind_param($stmt, "ssi", $new_status, $catatan, $id_pesanan);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Create notification for buyer
                    $id_pembeli = $order['id_pembeli'];
                    $notification_type = 'payment_verified';
                    $notification_msg = 'Pembayaran Anda telah diverifikasi. Pesanan akan segera diproses.';

                    $notif_stmt = mysqli_prepare($conn, "
                        INSERT INTO notifikasi (id_user, tipe, pesan, data_ref, status, created_at)
                        VALUES (?, ?, ?, ?, 'unread', NOW())
                    ");
                    mysqli_stmt_bind_param($notif_stmt, "isss", $id_pembeli, $notification_type, $notification_msg, $id_pesanan);
                    mysqli_stmt_execute($notif_stmt);

                    $success = 'Pembayaran berhasil diverifikasi! ✓';
                } else {
                    $error = 'Gagal memverifikasi pembayaran: ' . mysqli_error($conn);
                }
            } else {
                // Reject payment
                $new_status = 'menunggu_pembayaran';
                $stmt = mysqli_prepare($conn, "UPDATE pesanan SET status = ?, catatan_penjual = ?, updated_at = NOW() WHERE id_pesanan = ?");
                mysqli_stmt_bind_param($stmt, "ssi", $new_status, $catatan, $id_pesanan);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Create notification for buyer with rejection reason
                    $id_pembeli = $order['id_pembeli'];
                    $notification_type = 'payment_rejected';
                    $notification_msg = 'Pembayaran Anda ditolak. Alasan: ' . $catatan;

                    $notif_stmt = mysqli_prepare($conn, "
                        INSERT INTO notifikasi (id_user, tipe, pesan, data_ref, status, created_at)
                        VALUES (?, ?, ?, ?, 'unread', NOW())
                    ");
                    mysqli_stmt_bind_param($notif_stmt, "isss", $id_pembeli, $notification_type, $notification_msg, $id_pesanan);
                    mysqli_stmt_execute($notif_stmt);

                    $success = 'Pembayaran ditolak. Notifikasi dikirim ke pembeli.';
                } else {
                    $error = 'Gagal menolak pembayaran: ' . mysqli_error($conn);
                }
            }
        } else {
            $error = 'Pesanan tidak ditemukan atau Anda tidak memiliki akses!';
        }
    }
}

// Get pending payments for this seller
$stmt = mysqli_prepare($conn, "
    SELECT 
        pd.id_pembayaran,
        pd.id_pesanan,
        pd.jumlah_pembayaran,
        pd.bukti_pembayaran,
        pd.status_pembayaran,
        pd.dibuat_pada,
        pd.metode_pembayaran,
        u.nama as pembeli_nama,
        u.email as pembeli_email,
        p.nama_buku,
        p.foto_produk,
        pe.quantity
    FROM pembayaran_detail pd
    JOIN users u ON pd.id_pembeli = u.id_user
    JOIN pesanan pe ON pd.id_pesanan = pe.id_pesanan
    JOIN produk p ON pe.id_produk = p.id_produk
    WHERE pd.id_penjual = ? AND pd.status_pembayaran IN ('menunggu', 'diverifikasi')
    ORDER BY pd.dibuat_pada DESC
");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payments = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get verified payments (history)
$stmt = mysqli_prepare($conn, "
    SELECT 
        pd.id_pembayaran,
        pd.id_pesanan,
        pd.jumlah_pembayaran,
        pd.status_pembayaran,
        pd.diverifikasi_pada,
        u.nama as pembeli_nama,
        p.nama_buku,
        pe.quantity
    FROM pembayaran_detail pd
    JOIN users u ON pd.id_pembeli = u.id_user
    JOIN pesanan pe ON pd.id_pesanan = pe.id_pesanan
    JOIN produk p ON pe.id_produk = p.id_produk
    WHERE pd.id_penjual = ? AND pd.status_pembayaran IN ('diterima', 'ditolak')
    ORDER BY pd.diverifikasi_pada DESC
    LIMIT 10
");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$verified = mysqli_fetch_all($result, MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✅ Verifikasi Pembayaran - Aksara-Aruna</title>
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

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        header h1 {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
            font-size: 28px;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
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

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1f2937;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .payment-card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .payment-image {
            width: 100px;
            height: 100px;
            background: #f0f0f0;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .payment-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .payment-info {
            flex: 1;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .payment-amount {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }

        .payment-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .payment-status.menunggu {
            background: #fef3c7;
            color: #92400e;
        }

        .payment-status.diverifikasi {
            background: #dbeafe;
            color: #1e40af;
        }

        .payment-status.diterima {
            background: #dcfce7;
            color: #166534;
        }

        .payment-status.ditolak {
            background: #fee2e2;
            color: #991b1b;
        }

        .payment-details {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }

        .payment-details strong {
            color: #1f2937;
        }

        .payment-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-accept {
            background: #28a745;
            color: white;
        }

        .btn-accept:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-view {
            background: #667eea;
            color: white;
        }

        .btn-view:hover {
            background: #5568d3;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .modal-close {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', Arial;
            resize: vertical;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .payment-card {
                grid-template-columns: 1fr;
            }
            
            .payment-actions {
                flex-direction: row;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1><i class="fas fa-check-circle"></i> Verifikasi Pembayaran</h1>
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
            </div>
        <?php endif; ?>

        <!-- PENDING PAYMENTS -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-hourglass-half"></i> Pembayaran Menunggu Verifikasi (<?php echo count($payments); ?>)
            </div>

            <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Tidak ada pembayaran yang perlu diverifikasi</h3>
                </div>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                    <div class="payment-card">
                        <div class="payment-image">
                            <?php if ($p['foto_produk']): ?>
                                <img src="uploads/produk/<?php echo htmlspecialchars($p['foto_produk']); ?>" alt="">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-image" style="color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="payment-info">
                            <div class="payment-header">
                                <div>
                                    <div class="payment-amount">Rp <?php echo number_format($p['jumlah_pembayaran'], 0, ',', '.'); ?></div>
                                    <div style="font-size: 12px; color: #999;"><?php echo htmlspecialchars($p['nama_buku']); ?></div>
                                </div>
                                <span class="payment-status <?php echo $p['status_pembayaran']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $p['status_pembayaran'])); ?>
                                </span>
                            </div>

                            <div class="payment-details">
                                <strong>Pembeli:</strong> <?php echo htmlspecialchars($p['pembeli_nama']); ?> (<?php echo htmlspecialchars($p['pembeli_email']); ?>)<br>
                                <strong>Qty:</strong> <?php echo $p['quantity']; ?> unit<br>
                                <strong>Metode:</strong> <?php echo strtoupper($p['metode_pembayaran']); ?><br>
                                <strong>Tanggal:</strong> <?php echo date('d M Y H:i', strtotime($p['dibuat_pada'])); ?>
                            </div>
                        </div>

                        <div class="payment-actions">
                            <?php if ($p['bukti_pembayaran']): ?>
                                <button class="btn btn-view" onclick="viewProof('uploads/bukti_pembayaran/<?php echo htmlspecialchars($p['bukti_pembayaran']); ?>')">
                                    <i class="fas fa-image"></i> Lihat Bukti
                                </button>
                            <?php endif; ?>

                            <button class="btn btn-accept" onclick="openModal('verify', <?php echo $p['id_pembayaran']; ?>, 'diterima')">
                                <i class="fas fa-check"></i> Terima
                            </button>

                            <button class="btn btn-reject" onclick="openModal('verify', <?php echo $p['id_pembayaran']; ?>, 'ditolak')">
                                <i class="fas fa-times"></i> Tolak
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- VERIFIED HISTORY -->
        <?php if (!empty($verified)): ?>
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-history"></i> Riwayat Verifikasi
                </div>

                <?php foreach ($verified as $v): ?>
                    <div style="background: white; padding: 15px; margin-bottom: 10px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600;">Rp <?php echo number_format($v['jumlah_pembayaran'], 0, ',', '.'); ?></div>
                            <small style="color: #999;"><?php echo htmlspecialchars($v['nama_buku']); ?> - <?php echo htmlspecialchars($v['pembeli_nama']); ?></small>
                        </div>
                        <span class="payment-status <?php echo $v['status_pembayaran']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $v['status_pembayaran'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL FOR VERIFICATION -->
    <div id="verifyModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('verify')">&times;</span>
            <div class="modal-header">Verifikasi Pembayaran</div>

            <form method="POST">
                <input type="hidden" name="action" value="verify_payment">
                <input type="hidden" name="id_pembayaran" id="id_pembayaran" value="">
                <input type="hidden" name="status" id="status" value="">

                <div class="form-group">
                    <label>Catatan (Opsional)</label>
                    <textarea name="catatan" placeholder="Contoh: Diterima, sudah cek bukti transfer" rows="4"></textarea>
                </div>

                <button type="submit" class="btn btn-accept" style="width: 100%; margin-top: 15px;">
                    Konfirmasi
                </button>
            </form>
        </div>
    </div>

    <!-- IMAGE VIEWER -->
    <div id="imageModal" class="modal" onclick="if(event.target === this) closeModal('image')">
        <div style="max-width: 800px; width: 90%;">
            <img id="imageContent" src="" alt="" style="width: 100%; border-radius: 8px;">
        </div>
    </div>

    <script>
        function openModal(type, id, status = null) {
            if (type === 'verify') {
                document.getElementById('id_pembayaran').value = id;
                document.getElementById('status').value = status || 'diterima';
                document.getElementById('verifyModal').classList.add('active');
            }
        }

        function closeModal(type) {
            if (type === 'verify') {
                document.getElementById('verifyModal').classList.remove('active');
            } else if (type === 'image') {
                document.getElementById('imageModal').classList.remove('active');
            }
        }

        function viewProof(path) {
            document.getElementById('imageContent').src = path;
            document.getElementById('imageModal').classList.add('active');
        }
    </script>
</body>
</html>
