<?php
session_start();

// Include file koneksi
include 'koneksi.php';

// Inisialisasi variabel error
$error = "";
$success_message = "";

// JANGAN redirect jika sudah login - ini penyebab loop!
// Hapus bagian ini atau komentari:
// if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
//     if (isset($_SESSION["role"])) {
//         switch ($_SESSION["role"]) {
//             case 'super_admin':
//                 header("Location: admin_dashboard.php");
//                 exit;
//             case 'penjual':
//                 header("Location: penjual_dashboard.php");
//                 exit;
//             case 'pembeli':
//                 header("Location: pembeli_dashboard.php");
//                 exit;
//             default:
//                 header("Location: pembeli_dashboard.php");
//                 exit;
//         }
//     }
// }

// Auto-create test accounts on first load (sesuai ENUM)
$check_admin = mysqli_query($conn, "SELECT id_user FROM users WHERE email = 'admin@sarianggrek.com'");
if (!$check_admin || mysqli_num_rows($check_admin) == 0) {
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (nama, email, password, role, status) VALUES ('Super Administrator', 'admin@sarianggrek.com', '$hashed_password', 'super_admin', 'offline')");
}

$check_penjual = mysqli_query($conn, "SELECT id_user FROM users WHERE email = 'penjual@example.com'");
if (!$check_penjual || mysqli_num_rows($check_penjual) == 0) {
    $hashed_password = password_hash('penjual123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (nama, email, password, role, status) VALUES ('Seller User', 'penjual@example.com', '$hashed_password', 'penjual', 'offline')");
}

$check_pembeli = mysqli_query($conn, "SELECT id_user FROM users WHERE email = 'pembeli@example.com'");
if (!$check_pembeli || mysqli_num_rows($check_pembeli) == 0) {
    $hashed_password = password_hash('pembeli123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (nama, email, password, role, status) VALUES ('Buyer User', 'pembeli@example.com', '$hashed_password', 'pembeli', 'offline')");
}

// Proses login saat form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi!";
    } else {
        $sql = "SELECT id_user, nama, email, password, role, status FROM users WHERE email = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $id, $nama, $email_db, $hashed_password, $role, $status);
                mysqli_stmt_fetch($stmt);
                
                // Cek status akun
                if (isset($status) && $status === 'banned') {
                    $error = "Akun Anda tidak aktif. Hubungi administrator.";
                } else {
                    $password_valid = false;
                    
                    if (password_verify($password, $hashed_password)) {
                        $password_valid = true;
                    } elseif ($password === $hashed_password) {
                        $password_valid = true;
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $update_sql = "UPDATE users SET password = ? WHERE id_user = ?";
                        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                            mysqli_stmt_bind_param($update_stmt, "si", $new_hash, $id);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                        }
                    }
                    
                    if ($password_valid) {
                        session_regenerate_id(true);
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id_user"] = $id;
                        $_SESSION["nama"] = $nama;
                        $_SESSION["email"] = $email_db;
                        
                        // normalize role to avoid case differences
                        $role = strtolower($role);
                        
                        // if user has products but role is not penjual, upgrade them
                        if ($role !== 'penjual') {
                            $prod_check = mysqli_prepare($conn, "SELECT COUNT(*) FROM produk WHERE id_penjual = ?");
                            if ($prod_check) {
                                mysqli_stmt_bind_param($prod_check, "i", $id);
                                mysqli_stmt_execute($prod_check);
                                mysqli_stmt_bind_result($prod_check, $prod_count);
                                mysqli_stmt_fetch($prod_check);
                                mysqli_stmt_close($prod_check);
                                if ($prod_count > 0) {
                                    $role = 'penjual';
                                    // also update in database for consistency
                                    $upd = mysqli_prepare($conn, "UPDATE users SET role = 'penjual' WHERE id_user = ?");
                                    if ($upd) {
                                        mysqli_stmt_bind_param($upd, "i", $id);
                                        mysqli_stmt_execute($upd);
                                        mysqli_stmt_close($upd);
                                    }
                                }
                            }
                        }
                        $_SESSION["role"] = $role;
                        
                        // Update status ke online
                        $update_status_sql = "UPDATE users SET status = 'online' WHERE id_user = ?";
                        if ($update_status_stmt = mysqli_prepare($conn, $update_status_sql)) {
                            mysqli_stmt_bind_param($update_status_stmt, "i", $id);
                            mysqli_stmt_execute($update_status_stmt);
                            mysqli_stmt_close($update_status_stmt);
                        }
                        
                        // Jika remember me dicentang
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $expiry = time() + (30 * 24 * 60 * 60);
                            
                            $update_token_sql = "UPDATE users SET remember_token = ? WHERE id_user = ?";
                            if ($update_token_stmt = mysqli_prepare($conn, $update_token_sql)) {
                                mysqli_stmt_bind_param($update_token_stmt, "si", $token, $id);
                                mysqli_stmt_execute($update_token_stmt);
                                mysqli_stmt_close($update_token_stmt);
                                
                                setcookie('remember_token', $token, $expiry, "/", "", false, true);
                                setcookie('user_id', $id, $expiry, "/", "", false, true);
                            }
                        }
                        
                        // Redirect berdasarkan role
                        switch ($role) {
                            case 'super_admin':
                                header("Location: dashboard_admin.php");
                                exit;
                            case 'penjual':
                                header("Location: penjual_dashboard.php");
                                exit;
                            case 'pembeli':
                                header("Location: dashboard_pembeli.php");
                                exit;
                            default:
                                header("Location: dashboard_pembeli.php");
                                exit;
                        }
                    } else {
                        $error = "Password yang Anda masukkan salah!";
                    }
                }
            } else {
                $error = "Email tidak ditemukan!";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}

// Pesan sukses
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success_message = "Anda berhasil logout!";
}
if (isset($_GET['register']) && $_GET['register'] == 'success') {
    $success_message = "Registrasi berhasil! Silakan login.";
}
if (isset($_GET['reset']) && $_GET['reset'] == 'success') {
    $success_message = "Password berhasil direset! Silakan login dengan password baru.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Pustaka Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --secondary: #64748b;
            --success: #22c55e;
            --error: #ef4444;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius: 12px;
            --radius-sm: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.5;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            text-align: center;
            padding: 32px 24px 24px;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .logo-text {
            text-align: left;
        }

        .logo-title {
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.025em;
        }

        .logo-subtitle {
            font-size: 0.875rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 16px;
            letter-spacing: -0.025em;
        }

        .login-body {
            padding: 28px 32px 32px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.875rem;
            border: 1px solid transparent;
        }

        .alert i {
            font-size: 1rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            background-color: var(--surface);
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1rem;
            pointer-events: none;
        }

        .input-icon .form-control {
            padding-left: 42px;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 0.875rem;
            flex-wrap: wrap;
            gap: 12px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
            border-radius: 4px;
        }

        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .forgot-password:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 14px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .register-link {
            display: block;
            margin-top: 12px;
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .register-link a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .demo-accounts {
            background-color: var(--primary-light);
            border: 1px solid #bfdbfe;
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            margin-top: 24px;
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .demo-accounts h4 {
            font-size: 0.875rem;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .demo-accounts ul {
            list-style: none;
            padding-left: 0;
        }

        .demo-accounts li {
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }

        .demo-accounts i {
            color: var(--primary);
            font-size: 0.75rem;
        }

        .demo-accounts code {
            background: rgba(255, 255, 255, 0.7);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.75rem;
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-body {
                padding: 24px;
            }
            
            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .login-header {
                padding: 24px 20px 20px;
            }
            
            .logo-title {
                font-size: 1.125rem;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert {
            animation: fadeIn 0.25s ease-out;
        }

        /* Focus visible for accessibility */
        .form-control:focus-visible,
        .btn-login:focus-visible,
        .forgot-password:focus-visible,
        .register-link a:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="logo-text">
                        <div class="logo-title">Pustaka Aruna</div>
                        <div class="logo-subtitle">Marketplace Buku</div>
                    </div>
                </div>
                <h1>Masuk ke Akun</h1>
            </div>
            
            <div class="login-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="loginForm">
                    <div class="form-group">
                        <label class="form-label" for="email">
                            Email
                        </label>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="nama@contoh.com" required autocomplete="email">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">
                            Password
                        </label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="••••••••" required autocomplete="current-password">
                        </div>
                    </div>
                    
                    <div class="remember-forgot">
                        <label class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <span>Ingat saya</span>
                        </label>
                        <a href="forgot_password.php" class="forgot-password">Lupa password?</a>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>
                </form>
                
                <div class="login-footer">
                    Belum punya akun?
                    <div class="register-link">
                        <a href="register.php">
                            <i class="fas fa-user-plus"></i> Daftar gratis
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple form validation feedback
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            
            if (!email.value.trim() || !password.value.trim()) {
                e.preventDefault();
                alert('Silakan lengkapi email dan password');
                return false;
            }
        });

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