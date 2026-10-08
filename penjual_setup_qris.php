<?php
/**
 * Penjual Setup QRIS / Rekening Bank
 */

session_start();
include 'koneksi.php';

// Check seller login
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penjual') {
    header('Location: login.php');
    exit;
}

$id_penjual = $_SESSION['id_user'];
$error = '';
$success = '';

// GET existing QRIS data
$stmt = mysqli_prepare($conn, "SELECT * FROM penjual_qris WHERE id_penjual = ?");
mysqli_stmt_bind_param($stmt, "i", $id_penjual);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$qris_data = mysqli_fetch_assoc($result);

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_identitas = trim($_POST['nomor_identitas'] ?? '');
    $nomor_rekening = trim($_POST['nomor_rekening'] ?? '');
    $nama_rekening = trim($_POST['nama_rekening'] ?? '');
    $nama_bank = trim($_POST['nama_bank'] ?? '');
    
    if (empty($nomor_identitas) || empty($nomor_rekening) || empty($nama_rekening) || empty($nama_bank)) {
        $error = 'Semua field harus diisi';
    } else {
        if ($qris_data) {
            // Update existing
            $stmt = mysqli_prepare($conn, "UPDATE penjual_qris SET nomor_identitas = ?, nomor_rekening = ?, nama_rekening = ?, nama_bank = ? WHERE id_penjual = ?");
            mysqli_stmt_bind_param($stmt, "ssssi", $nomor_identitas, $nomor_rekening, $nama_rekening, $nama_bank, $id_penjual);
        } else {
            // Insert new
            $stmt = mysqli_prepare($conn, "INSERT INTO penjual_qris (id_penjual, nomor_identitas, nomor_rekening, nama_rekening, nama_bank) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issss", $id_penjual, $nomor_identitas, $nomor_rekening, $nama_rekening, $nama_bank);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Data QRIS/Rekening berhasil disimpan✓';
            // Refresh data
            $stmt = mysqli_prepare($conn, "SELECT * FROM penjual_qris WHERE id_penjual = ?");
            mysqli_stmt_bind_param($stmt, "i", $id_penjual);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $qris_data = mysqli_fetch_assoc($result);
        } else {
            $error = 'Gagal menyimpan data: ' . mysqli_error($conn);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💳 Setup QRIS/Rekening Penjual - Aksara-Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 40px auto 0;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .header p {
            color: #9ca3af;
            font-size: 14px;
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
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1f2937;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', Arial;
            transition: all 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .info-box {
            background: #f0f5ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
            font-size: 13px;
            color: #667eea;
        }

        .info-box strong {
            display: block;
            margin-bottom: 8px;
        }

        .info-box ul {
            margin-left: 15px;
            line-height: 1.6;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #667eea;
            text-decoration: none;
            margin-top: 20px;
            font-size: 14px;
        }

        .back-btn:hover {
            color: #764ba2;
        }

        .status-badge {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-qrcode"></i> Setup QRIS/Rekening</h1>
            <p>Tambahkan metode pembayaran untuk toko Anda</p>
        </div>

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

        <div class="info-box">
            <strong><i class="fas fa-info-circle"></i> Informasi Penting</strong>
            <ul>
                <li>Data rekening akan ditampilkan kepada pembeli</li>
                <li>Pastikan nomor rekening sudah benar</li>
                <li>Pembeli akan upload bukti pembayaran ke rekening/QRIS Anda</li>
                <li>Anda harus verifikasi pembayaran secara manual</li>
            </ul>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="nomor_identitas">
                    <i class="fas fa-id-card"></i> Nomor Identitas (KTP/SIM)
                </label>
                <input type="text" id="nomor_identitas" name="nomor_identitas" 
                       placeholder="Contoh: 3173551234567890" 
                       value="<?php echo htmlspecialchars($qris_data['nomor_identitas'] ?? ''); ?>"
                       required maxlength="50">
                <small style="color: #9ca3af;">Untuk verifikasi pembayaran</small>
            </div>

            <div class="form-group">
                <label for="nomor_rekening">
                    <i class="fas fa-landmark"></i> Nomor Rekening Bank
                </label>
                <input type="text" id="nomor_rekening" name="nomor_rekening" 
                       placeholder="Contoh: 1234567890" 
                       value="<?php echo htmlspecialchars($qris_data['nomor_rekening'] ?? ''); ?>"
                       required maxlength="30">
            </div>

            <div class="form-group">
                <label for="nama_bank">
                    <i class="fas fa-building"></i> Nama Bank
                </label>
                <select id="nama_bank" name="nama_bank" required>
                    <option value="">-- Pilih Bank --</option>
                    <option value="BNI" <?php echo ($qris_data['nama_bank'] === 'BNI') ? 'selected' : ''; ?>>BNI (Bank Nasional Indonesia)</option>
                    <option value="BRI" <?php echo ($qris_data['nama_bank'] === 'BRI') ? 'selected' : ''; ?>>BRI (Bank Rakyat Indonesia)</option>
                    <option value="Mandiri" <?php echo ($qris_data['nama_bank'] === 'Mandiri') ? 'selected' : ''; ?>>Mandiri</option>
                    <option value="BCA" <?php echo ($qris_data['nama_bank'] === 'BCA') ? 'selected' : ''; ?>>BCA (Bank Central Asia)</option>
                    <option value="CIMB" <?php echo ($qris_data['nama_bank'] === 'CIMB') ? 'selected' : ''; ?>>CIMB Niaga</option>
                    <option value="OVO" <?php echo ($qris_data['nama_bank'] === 'OVO') ? 'selected' : ''; ?>>OVO</option>
                    <option value="DANA" <?php echo ($qris_data['nama_bank'] === 'DANA') ? 'selected' : ''; ?>>DANA</option>
                    <option value="GCash" <?php echo ($qris_data['nama_bank'] === 'GCash') ? 'selected' : ''; ?>>GCash</option>
                    <option value="Lainnya" <?php echo ($qris_data['nama_bank'] === 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                </select>
            </div>

            <div class="form-group">
                <label for="nama_rekening">
                    <i class="fas fa-user"></i> Nama Pemilik Rekening
                </label>
                <input type="text" id="nama_rekening" name="nama_rekening" 
                       placeholder="Nama sesuai rekening bank" 
                       value="<?php echo htmlspecialchars($qris_data['nama_rekening'] ?? ''); ?>"
                       required maxlength="100">
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> Simpan Data QRIS/Rekening
            </button>
        </form>

        <?php if ($qris_data): ?>
            <div class="status-badge">
                <i class="fas fa-check-circle"></i> Data sudah tersimpan
            </div>
        <?php endif; ?>

        <a href="penjual_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>
</body>
</html>
