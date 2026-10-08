<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set ke 1 hanya saat debugging

// Require email configuration
require_once 'email_config.php';

// Koneksi database
$host = "localhost";
$user = "root";
$pass = "";
$db   = "toko-buku";
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Inisialisasi variabel
$error = "";
$success = "";
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$email = "";

// ===== HANDLE RESEND OTP =====
if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    if (isset($_SESSION['reset_email'], $_SESSION['reset_user_id'])) {
        $email = $_SESSION['reset_email'];
        $user_id = $_SESSION['reset_user_id'];
        $user_name = $_SESSION['reset_user_name'] ?? 'Pengguna';
        
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $update_sql = "UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id_user = ?";
        if ($stmt = mysqli_prepare($conn, $update_sql)) {
            mysqli_stmt_bind_param($stmt, "ssi", $otp, $otp_expiry, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $_SESSION['debug_otp'] = $otp;
            $sendError = '';
            
            if (sendOTPViaEmail($email, $user_name, $otp, $sendError)) {
                $success = "Kode OTP baru telah dikirim ke $email.";
                $step = 2;
            } else {
                $error = "Gagal mengirim OTP. " . ($sendError ?: "Silakan coba lagi atau cek konfigurasi email.");
            }
        } else {
            $error = "Terjadi kesalahan sistem ketika mengirim ulang OTP.";
        }
    } else {
        header("Location: forgot_password.php");
        exit;
    }
}

// ===== STEP 1: VERIFIKASI EMAIL =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step == 1) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Email harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } else {
        $sql = "SELECT id_user, nama FROM users WHERE email = ? AND status != 'banned'";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) === 1) {
                mysqli_stmt_bind_result($stmt, $user_id, $user_name);
                mysqli_stmt_fetch($stmt);
                
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $update_sql = "UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id_user = ?";
                if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "ssi", $otp, $otp_expiry, $user_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                    
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_id'] = $user_id;
                    $_SESSION['reset_user_name'] = $user_name;
                    $_SESSION['debug_otp'] = $otp;
                    
                    $sendError = '';
                    if (sendOTPViaEmail($email, $user_name, $otp, $sendError)) {
                        header("Location: forgot_password.php?step=2");
                        exit;
                    } else {
                        $error = "Gagal mengirim OTP. " . ($sendError ?: "Silakan coba lagi atau cek konfigurasi email.");
                    }
                } else {
                    $error = "Terjadi kesalahan sistem.";
                }
            } else {
                $error = "Email tidak ditemukan atau akun diblokir!";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Terjadi kesalahan sistem.";
        }
    }
}

// ===== STEP 2: VERIFIKASI OTP =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step == 2) {
    if (!isset($_SESSION['reset_email'])) {
        header("Location: forgot_password.php");
        exit;
    }
    
    $otp_input = '';
    for ($i = 1; $i <= 6; $i++) {
        $otp_input .= $_POST["otp$i"] ?? '';
    }
    
    if (strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
        $error = "OTP harus 6 digit angka!";
    } else {
        $email = $_SESSION['reset_email'];
        $sql = "SELECT id_user, otp_code, otp_expiry FROM users WHERE email = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $user_id, $otp_db, $otp_expiry);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            
            $now = date('Y-m-d H:i:s');
            $debug_otp = $_SESSION['debug_otp'] ?? '';
            
            if ((($otp_input === $otp_db) || ($otp_input === $debug_otp)) && $otp_expiry > $now) {
                $_SESSION['otp_verified'] = true;
                header("Location: forgot_password.php?step=3");
                exit;
            } else {
                $error = ($otp_expiry <= $now) ? "OTP telah kadaluarsa! Silakan minta OTP baru." : "OTP tidak valid!";
            }
        }
    }
}

// ===== STEP 3: RESET PASSWORD =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step == 3) {
    if (!isset($_SESSION['reset_email'], $_SESSION['otp_verified'])) {
        header("Location: forgot_password.php");
        exit;
    }
    
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm)) {
        $error = "Password dan konfirmasi password harus diisi!";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter!";
    } elseif ($password !== $confirm) {
        $error = "Password dan konfirmasi password tidak sama!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $email = $_SESSION['reset_email'];
        
        $update_sql = "UPDATE users SET password = ?, otp_code = NULL, otp_expiry = NULL WHERE email = ?";
        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
            mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $email);
            if (mysqli_stmt_execute($update_stmt)) {
                unset($_SESSION['reset_email'], $_SESSION['reset_user_id'], 
                      $_SESSION['reset_user_name'], $_SESSION['otp_verified'], $_SESSION['debug_otp']);
                $success = "Password berhasil direset! Silakan login dengan password baru Anda.";
                $step = 4;
            } else {
                $error = "Gagal mereset password. Silakan coba lagi.";
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Pustaka Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Crimson+Text:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue-light: #e3f2fd;
            --blue-primary: #1976d2;
            --blue-dark: #0d47a1;
            --blue-accent: #42a5f5;
            --blue-red: #d32f2f;
            --blue-green: #388e3c;
            --blue-white: #ffffff;
            --blue-border: #90caf9;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: var(--blue-light);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-image: url('data:image/svg+xml;utf8,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L60,60" stroke="%2390caf9" stroke-width="0.5" opacity="0.3"/><path d="M60,0 L0,60" stroke="%2390caf9" stroke-width="0.5" opacity="0.3"/></svg>');
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(66, 165, 245, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        .forgot-container { width: 100%; max-width: 550px; position: relative; z-index: 1; }
        .vintage-card {
            background: var(--blue-white);
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(25, 118, 210, 0.2);
            border: 3px solid var(--blue-border);
            overflow: hidden;
            position: relative;
            background-image: url('data:image/svg+xml;utf8,<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect width="20" height="20" fill="%23ffffff"/><path d="M0,0 L20,20" stroke="%2390caf9" stroke-width="0.3" opacity="0.2"/><path d="M20,0 L0,20" stroke="%2390caf9" stroke-width="0.3" opacity="0.2"/></svg>');
        }
        .vintage-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--blue-primary), var(--blue-accent), var(--blue-primary));
        }
        .vintage-header {
            background: linear-gradient(135deg, var(--blue-primary), var(--blue-accent));
            padding: 35px 25px;
            text-align: center;
            position: relative;
            border-bottom: 3px solid var(--blue-border);
        }
        .vintage-header::after {
            content: '';
            position: absolute;
            bottom: -3px; left: 50%;
            transform: translateX(-50%);
            width: 120px; height: 3px;
            background: var(--blue-accent);
            box-shadow: 0 0 10px rgba(66, 165, 245, 0.5);
        }
        .back-link {
            position: absolute;
            left: 25px; top: 50%;
            transform: translateY(-50%);
            color: var(--blue-white);
            font-size: 1.3rem;
            text-decoration: none;
            width: 45px; height: 45px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        .back-link:hover {
            background: var(--blue-white);
            color: var(--blue-primary);
            transform: translateY(-50%) scale(1.1);
        }
        .vintage-header h1 {
            font-family: 'Crimson Text', serif;
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
            color: var(--blue-white);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            letter-spacing: 1px;
        }
        .vintage-header p {
            font-family: 'Crimson Text', serif;
            font-size: 1.1rem;
            margin: 10px 0 0;
            color: var(--blue-white);
            font-style: italic;
            opacity: 0.9;
        }
        .vintage-body { padding: 40px 35px; }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
            padding: 0 15px;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px; left: 40px; right: 40px;
            height: 2px;
            background: var(--blue-border);
            z-index: 1;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        .step-circle {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--blue-light);
            border: 3px solid var(--blue-border);
            color: var(--blue-primary);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        .step.active .step-circle {
            background: var(--blue-accent);
            border-color: var(--blue-primary);
            color: var(--blue-white);
            box-shadow: 0 0 0 4px rgba(66, 165, 245, 0.3);
        }
        .step.completed .step-circle {
            background: var(--blue-green);
            border-color: var(--blue-green);
            color: var(--blue-white);
        }
        .step-label {
            font-size: 0.85rem;
            color: var(--blue-dark);
            font-weight: 500;
        }
        .step.active .step-label {
            color: var(--blue-primary);
            font-weight: 700;
        }
        .vintage-alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(227, 242, 253, 0.9);
        }
        .vintage-alert i { font-size: 1.1rem; }
        .vintage-alert.info { background: rgba(66, 165, 245, 0.1); color: var(--blue-primary); border-color: var(--blue-accent); }
        .vintage-alert.success { background: rgba(56, 142, 60, 0.1); color: var(--blue-green); border-color: var(--blue-green); }
        .vintage-alert.error { background: rgba(211, 47, 47, 0.1); color: var(--blue-red); border-color: var(--blue-red); }
        .vintage-alert.warning { background: rgba(66, 165, 245, 0.1); color: var(--blue-dark); border-color: var(--blue-dark); }
        .form-group { margin-bottom: 25px; }
        .form-label {
            display: block;
            margin-bottom: 10px;
            color: var(--blue-primary);
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
        }
        .form-label i { margin-right: 8px; color: var(--blue-accent); }
        .vintage-input {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid var(--blue-border);
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--blue-light);
            color: var(--blue-dark);
        }
        .vintage-input:focus {
            outline: none;
            border-color: var(--blue-accent);
            box-shadow: 0 0 0 4px rgba(66, 165, 245, 0.2);
            background-color: white;
        }
        .vintage-input::placeholder { color: #90caf9; font-style: italic; }
        .btn-vintage {
            width: 100%;
            background: linear-gradient(135deg, var(--blue-accent), var(--blue-primary));
            color: var(--blue-white);
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
            border: 2px solid transparent;
        }
        .btn-vintage:hover {
            background: linear-gradient(135deg, var(--blue-primary), var(--blue-dark));
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(25, 118, 210, 0.4);
            border-color: var(--blue-accent);
        }
        .btn-vintage:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .otp-container {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 30px 0;
        }
        .otp-input {
            width: 55px; height: 65px;
            text-align: center;
            font-size: 1.8rem;
            font-weight: 700;
            border: 2px solid var(--blue-border);
            border-radius: 12px;
            transition: all 0.3s ease;
            background-color: var(--blue-light);
            color: var(--blue-primary);
            font-family: 'Crimson Text', serif;
        }
        .otp-input:focus {
            border-color: var(--blue-accent);
            box-shadow: 0 0 0 4px rgba(66, 165, 245, 0.2);
            outline: none;
            background-color: white;
        }
        .otp-input.filled {
            border-color: var(--blue-accent);
            background: linear-gradient(135deg, rgba(66, 165, 245, 0.1), rgba(25, 118, 210, 0.1));
        }
        .resend-link {
            text-align: center;
            margin: 25px 0 15px;
            color: var(--blue-dark);
            font-size: 0.95rem;
        }
        .resend-link a {
            color: var(--blue-primary);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 2px dashed var(--blue-accent);
            padding-bottom: 2px;
        }
        .resend-link a:hover {
            color: var(--blue-accent);
            border-bottom-color: var(--blue-primary);
        }
        .password-wrapper { position: relative; }
        .toggle-password {
            position: absolute;
            right: 15px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--blue-primary);
            cursor: pointer;
            font-size: 1.1rem;
            padding: 5px;
        }
        .toggle-password:hover { color: var(--blue-accent); }
        .password-hint {
            font-size: 0.85rem;
            margin-top: 8px;
            color: var(--blue-dark);
        }
        .password-match { font-size: 0.9rem; margin-top: 8px; }
        .match-success { color: var(--blue-green); }
        .match-error { color: var(--blue-red); }
        .text-link { text-align: center; margin-top: 20px; }
        .text-link a {
            color: var(--blue-primary);
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .text-link a:hover { color: var(--blue-accent); }
        .text-link a i { margin-right: 5px; }
        .debug-box {
            margin-top: 25px;
            padding: 15px;
            background: rgba(66, 165, 245, 0.1);
            border-radius: 12px;
            border-left: 4px solid var(--blue-dark);
            font-size: 0.9rem;
        }
        @media (max-width: 576px) {
            .vintage-body { padding: 30px 20px; }
            .vintage-header h1 { font-size: 1.8rem; }
            .vintage-header p { font-size: 1rem; }
            .otp-container { gap: 8px; }
            .otp-input { width: 45px; height: 55px; font-size: 1.5rem; }
            .step-indicator::before { left: 30px; right: 30px; }
            .step-circle { width: 35px; height: 35px; font-size: 0.9rem; }
            .step-label { font-size: 0.75rem; }
        }
        .vintage-corner {
            position: absolute;
            width: 50px; height: 50px;
            border: 3px solid var(--blue-accent);
            opacity: 0.3;
            pointer-events: none;
        }
        .corner-tl { top: 20px; left: 20px; border-right: none; border-bottom: none; }
        .corner-tr { top: 20px; right: 20px; border-left: none; border-bottom: none; }
        .corner-bl { bottom: 20px; left: 20px; border-right: none; border-top: none; }
        .corner-br { bottom: 20px; right: 20px; border-left: none; border-top: none; }
    </style>
</head>
<body>
<div class="forgot-container">
    <div class="vintage-card">
        <div class="vintage-corner corner-tl"></div>
        <div class="vintage-corner corner-tr"></div>
        <div class="vintage-corner corner-bl"></div>
        <div class="vintage-corner corner-br"></div>
        
        <div class="vintage-header">
            <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i></a>
            <h1><i class="fas fa-feather-alt me-2"></i>Lupa Password</h1>
            <p>Pustaka Aruna - Toko Buku Klasik</p>
        </div>
        
        <div class="vintage-body">
            <!-- Debug Info (Hanya muncul jika ?debug=1) -->
            <?php if (isset($_SESSION['debug_otp']) && isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
            <div class="vintage-alert warning">
                <i class="fas fa-bug"></i>
                <strong>DEBUG MODE:</strong> OTP: <?php echo htmlspecialchars($_SESSION['debug_otp']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-circle"><?php echo $step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?></div>
                    <span class="step-label">Email</span>
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <div class="step-circle"><?php echo $step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?></div>
                    <span class="step-label">Verifikasi</span>
                </div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                    <div class="step-circle"><?php echo $step > 3 ? '<i class="fas fa-check"></i>' : '3'; ?></div>
                    <span class="step-label">Password</span>
                </div>
            </div>
            
            <!-- Success Message -->
            <?php if ($success): ?>
            <div class="vintage-alert success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php if ($step == 4): ?>
            <div class="text-center">
                <a href="login.php" class="btn-vintage">
                    <i class="fas fa-sign-in-alt"></i> Kembali ke Login
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if ($error): ?>
            <div class="vintage-alert error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <!-- STEP 1: Email Verification -->
            <?php if ($step == 1): ?>
            <form method="POST" action="forgot_password.php">
                <div class="vintage-alert info">
                    <i class="fas fa-info-circle"></i>
                    Masukkan email terdaftar Anda. Kami akan mengirimkan kode OTP untuk verifikasi.
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">
                        <i class="fas fa-envelope"></i> Email Terdaftar
                    </label>
                    <input type="email" class="vintage-input" id="email" name="email"
                           value="<?php echo htmlspecialchars($email); ?>"
                           placeholder="contoh@email.com" required>
                </div>
                <button type="submit" class="btn-vintage">
                    <i class="fas fa-paper-plane"></i> Kirim Kode OTP
                </button>
                <div class="text-link">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
                </div>
            </form>
            <?php endif; ?>
            
            <!-- STEP 2: OTP Verification -->
            <?php if ($step == 2): ?>
            <form method="POST" action="forgot_password.php?step=2" id="otpForm">
                <div class="vintage-alert info">
                    <i class="fas fa-key"></i>
                    Kode OTP telah dikirim ke <strong><?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></strong>. Masukkan 6 digit kode di bawah.
                </div>
                <div class="otp-container">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="text" class="otp-input" name="otp<?php echo $i; ?>" id="otp<?php echo $i; ?>" 
                           maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code">
                    <?php endfor; ?>
                </div>
                <div class="resend-link">
                    <p>Belum menerima kode? <a href="#" onclick="resendOTP(); return false;">Kirim ulang OTP</a></p>
                    <small style="color: var(--blue-dark);"><i class="fas fa-clock"></i> Kode berlaku 15 menit</small>
                </div>
                <button type="submit" class="btn-vintage" id="verifyBtn" disabled>
                    <i class="fas fa-check-circle"></i> Verifikasi OTP
                </button>
                <div class="text-link">
                    <a href="forgot_password.php"><i class="fas fa-arrow-left"></i> Ganti Email</a>
                </div>
            </form>
            <?php endif; ?>
            
            <!-- STEP 3: Reset Password -->
            <?php if ($step == 3): ?>
            <form method="POST" action="forgot_password.php?step=3" id="passwordForm">
                <div class="vintage-alert info">
                    <i class="fas fa-lock"></i>
                    Buat password baru untuk akun Anda.
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="fas fa-key"></i> Password Baru
                    </label>
                    <div class="password-wrapper">
                        <input type="password" class="vintage-input" id="password" name="password"
                               placeholder="Minimal 8 karakter" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-hint">
                        <i class="fas fa-info-circle"></i> Minimal 8 karakter, kombinasi huruf dan angka
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        <i class="fas fa-check-double"></i> Konfirmasi Password
                    </label>
                    <div class="password-wrapper">
                        <input type="password" class="vintage-input" id="confirm_password" name="confirm_password"
                               placeholder="Ulangi password" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatchMessage" class="password-match"></div>
                </div>
                <button type="submit" class="btn-vintage" id="resetBtn" disabled>
                    <i class="fas fa-redo-alt"></i> Reset Password
                </button>
                <div class="text-link">
                    <a href="login.php"><i class="fas fa-times"></i> Batalkan</a>
                </div>
            </form>
            <?php endif; ?>
            
            <!-- STEP 4: Success -->
            <?php if ($step == 4 && empty($success)): ?>
            <div class="vintage-alert success">
                <i class="fas fa-check-circle"></i>
                Password berhasil direset! Silakan login dengan password baru Anda.
            </div>
            <div class="text-center">
                <a href="login.php" class="btn-vintage">
                    <i class="fas fa-sign-in-alt"></i> Kembali ke Login
                </a>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const step = <?php echo json_encode($step); ?>;
    
    if (step === 1) {
        document.getElementById('email')?.focus();
    } else if (step === 2) {
        setupOTPInputs();
        document.getElementById('otp1')?.focus();
    } else if (step === 3) {
        document.getElementById('password')?.focus();
        setupPasswordValidation();
    }
});

function setupOTPInputs() {
    const otpInputs = document.querySelectorAll('.otp-input');
    
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 1) {
                this.classList.add('filled');
                if (index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            } else {
                this.classList.remove('filled');
            }
            checkOTPComplete();
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value === '' && index > 0) {
                otpInputs[index - 1].focus();
            }
        });
        
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text');
            const numbers = pasteData.replace(/[^0-9]/g, '').split('');
            numbers.forEach((num, i) => {
                if (i < otpInputs.length) {
                    otpInputs[i].value = num;
                    otpInputs[i].classList.add('filled');
                }
            });
            if (numbers.length >= otpInputs.length) {
                otpInputs[otpInputs.length - 1].focus();
            } else if (numbers.length > 0) {
                otpInputs[numbers.length].focus();
            }
            checkOTPComplete();
        });
    });
}

function checkOTPComplete() {
    const otpInputs = document.querySelectorAll('.otp-input');
    const verifyBtn = document.getElementById('verifyBtn');
    if (!verifyBtn) return;
    
    let isComplete = true;
    otpInputs.forEach(input => { if (input.value === '') isComplete = false; });
    
    verifyBtn.disabled = !isComplete;
}

function resendOTP() {
    if (confirm('Kirim ulang kode OTP ke email Anda?')) {
        window.location.href = 'forgot_password.php?resend=1';
    }
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggleBtn = field.nextElementSibling;
    const icon = toggleBtn.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function setupPasswordValidation() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const resetBtn = document.getElementById('resetBtn');
    const matchMessage = document.getElementById('passwordMatchMessage');
    
    function validatePasswords() {
        if (confirmPassword.value === '') {
            matchMessage.innerHTML = '';
            resetBtn.disabled = true;
            return;
        }
        if (password.value === confirmPassword.value) {
            matchMessage.innerHTML = '<i class="fas fa-check-circle"></i> Password sama!';
            matchMessage.className = 'password-match match-success';
            resetBtn.disabled = false;
        } else {
            matchMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i> Password tidak sama!';
            matchMessage.className = 'password-match match-error';
            resetBtn.disabled = true;
        }
    }
    
    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
}

// Form submission validation
document.getElementById('otpForm')?.addEventListener('submit', function(e) {
    const otpInputs = document.querySelectorAll('.otp-input');
    let otpValue = '';
    otpInputs.forEach(input => { otpValue += input.value; });
    
    if (otpValue.length !== 6) {
        e.preventDefault();
        alert('Harap isi semua digit OTP (6 angka)');
        return false;
    }
});

document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (password.value !== confirmPassword.value) {
        e.preventDefault();
        alert('Password dan konfirmasi password tidak sama!');
        return false;
    }
    if (password.value.length < 8) {
        e.preventDefault();
        alert('Password minimal 8 karakter!');
        return false;
    }
});
</script>
</body>
</html>