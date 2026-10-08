<?php
include 'koneksi.php';
session_start();

// Cek user apakah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if (!$user_id) {
    header("location: login.php");
    exit;
}

// Get user info
$user_result = mysqli_query($conn, "SELECT * FROM users WHERE id_user = $user_id");
$user_info = mysqli_fetch_assoc($user_result);

// Update online status
$update_sql = "UPDATE users SET status = 'online' WHERE id_user = ?";
if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
    mysqli_stmt_bind_param($update_stmt, "i", $user_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // Update Profile
    if ($action === 'update_profile') {
        $nama = isset($_POST['nama']) ? mysqli_real_escape_string($conn, $_POST['nama']) : '';
        $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : '';

        // Validate
        if (empty($nama) || empty($email)) {
            $message = 'Nama dan email tidak boleh kosong!';
            $message_type = 'error';
        } else {
            // Check email exists (if different from current)
            if ($email !== $user_info['email']) {
                $check_email = mysqli_query($conn, "SELECT id_user FROM users WHERE email = '$email' LIMIT 1");
                if (mysqli_num_rows($check_email) > 0) {
                    $message = 'Email sudah digunakan!';
                    $message_type = 'error';
                } else {
                    // Update
                    $update_query = "UPDATE users SET nama = '$nama', email = '$email' WHERE id_user = $user_id";
                    if (mysqli_query($conn, $update_query)) {
                        $message = 'Profil berhasil diperbarui!';
                        $message_type = 'success';
                        // Refresh user info
                        $user_result = mysqli_query($conn, "SELECT * FROM users WHERE id_user = $user_id");
                        $user_info = mysqli_fetch_assoc($user_result);
                    } else {
                        $message = 'Gagal memperbarui profil!';
                        $message_type = 'error';
                    }
                }
            } else {
                // Just update name
                $update_query = "UPDATE users SET nama = '$nama' WHERE id_user = $user_id";
                if (mysqli_query($conn, $update_query)) {
                    $message = 'Profil berhasil diperbarui!';
                    $message_type = 'success';
                    // Refresh user info
                    $user_result = mysqli_query($conn, "SELECT * FROM users WHERE id_user = $user_id");
                    $user_info = mysqli_fetch_assoc($user_result);
                } else {
                    $message = 'Gagal memperbarui profil!';
                    $message_type = 'error';
                }
            }
        }
    }

    // Change Password
    if ($action === 'change_password') {
        $old_password = isset($_POST['old_password']) ? $_POST['old_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        // Validate
        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'Semua field password harus diisi!';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'Password baru tidak cocok!';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = 'Password minimal 6 karakter!';
            $message_type = 'error';
        } else {
            // Check old password
            if ($old_password === $user_info['password']) {
                // Update password
                $update_pwd = "UPDATE users SET password = '$new_password' WHERE id_user = $user_id";
                if (mysqli_query($conn, $update_pwd)) {
                    $message = 'Password berhasil diubah!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal mengubah password!';
                    $message_type = 'error';
                }
            } else {
                $message = 'Password lama tidak sesuai!';
                $message_type = 'error';
            }
        }
    }
}

// Get user statistics
$transaksi_count = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as count FROM transaksi WHERE id_pembeli = $user_id")
);
$cart_count = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as count FROM keranjang WHERE id_user = $user_id AND status = 'aktif'")
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👤 Akun Saya - Toko Buku Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        /* Navbar */
        .navbar {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            sticky: top;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: #2c3e50;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            color: #555;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #3498db;
        }

        .btn-logout {
            padding: 8px 16px;
            background: #e74c3c;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #c0392b;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Main Grid */
        .account-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            height: fit-content;
            position: sticky;
            top: 80px;
        }

        .profile-card {
            padding: 2rem;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 600;
            margin: 0 auto 15px;
            border: 3px solid white;
        }

        .profile-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-email {
            font-size: 0.9rem;
            opacity: 0.9;
            word-break: break-all;
        }

        .profile-status {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.9rem;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            margin-top: 8px;
            font-weight: 600;
        }

        .status-online {
            background: #27ae60;
        }

        .status-offline {
            background: #95a5a6;
        }

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border-top: 1px solid #ecf0f1;
        }

        .stat-item {
            padding: 1.5rem;
            text-align: center;
            border-right: 1px solid #ecf0f1;
        }

        .stat-item:nth-child(2n) {
            border-right: none;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        /* Menu Links */
        .menu-links {
            padding: 1rem 0;
            border-top: 1px solid #ecf0f1;
        }

        .menu-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #ecf0f1;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            color: #555;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-item:hover {
            background: #f8f9fa;
            color: #3498db;
        }

        .menu-item i {
            width: 20px;
            color: #3498db;
        }

        /* Content */
        .content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 2rem;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #ecf0f1;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid #ecf0f1;
            border-radius: 8px;
            font-family: 'Poppins', Arial, sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 2rem;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        /* Info Box */
        .info-box {
            background: #ecf0f1;
            border-left: 4px solid #3498db;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .account-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .stat-item {
                border-right: none;
                border-bottom: 1px solid #ecf0f1;
            }

            .stat-item:last-child {
                border-bottom: none;
            }

            .navbar-content {
                flex-direction: column;
                gap: 10px;
            }

            .nav-links {
                flex-direction: column;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="halaman_pembeli.php" class="logo">
                <i class="fas fa-book"></i> Toko Buku
            </a>
            <div class="nav-links">
                <a href="halaman_pembeli.php">Beranda</a>
                <a href="buku.php">Katalog</a>
                <a href="keranjang.php">Keranjang</a>
                <a href="laporan.php">Pesanan</a>
                <a href="chat.php">Chat</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Container -->
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user-circle"></i> Akun Saya</h1>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Main Grid -->
        <div class="account-grid">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user_info['nama'], 0, 1)); ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user_info['nama']); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($user_info['email']); ?></div>
                    <div class="profile-status">
                        <div>Status</div>
                        <span class="status-badge status-<?php echo $user_info['status']; ?>">
                            <?php echo ucfirst($user_info['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $transaksi_count['count']; ?></div>
                        <div class="stat-label">Transaksi</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $cart_count['count']; ?></div>
                        <div class="stat-label">Di Keranjang</div>
                    </div>
                </div>

                <div class="menu-links">
                    <a class="menu-item active" data-tab="profile" onclick="switchTab(event)">
                        <i class="fas fa-user"></i> Profil
                    </a>
                    <a class="menu-item" data-tab="password" onclick="switchTab(event)">
                        <i class="fas fa-lock"></i> Ubah Password
                    </a>
                    <a href="laporan.php" class="menu-item">
                        <i class="fas fa-receipt"></i> Pesanan Saya
                    </a>
                    <a href="keranjang.php" class="menu-item">
                        <i class="fas fa-shopping-cart"></i> Keranjang
                    </a>
                    <a href="chat.php" class="menu-item">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                </div>
            </div>

            <!-- Content -->
            <div class="content">
                <!-- Profile Section -->
                <div class="content-section active" id="profile">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i> Informasi Profil
                    </h2>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label for="nama">Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($user_info['nama']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_info['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="role">Tipe Akun</label>
                            <input type="text" id="role" value="<?php echo ucfirst($user_info['role']); ?>" disabled style="background: #ecf0f1; cursor: not-allowed;">
                        </div>

                        <div class="form-group">
                            <label for="created">Bergabung Sejak</label>
                            <input type="text" value="<?php echo date('d M Y', strtotime($user_info['created_at'])); ?>" disabled style="background: #ecf0f1; cursor: not-allowed;">
                        </div>

                        <div class="form-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Password Section -->
                <div class="content-section" id="password">
                    <h2 class="section-title">
                        <i class="fas fa-lock"></i> Ubah Password
                    </h2>

                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        Password minimal 6 karakter untuk keamanan akun Anda.
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="old_password">Password Lama</label>
                            <input type="password" id="old_password" name="old_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password Baru</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>

                        <div class="form-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Ubah Password
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(event) {
            event.preventDefault();
            
            // Remove active class from all menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Remove active class from all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Add active class to clicked menu item
            event.target.closest('.menu-item').classList.add('active');
            
            // Add active class to corresponding section
            const tab = event.target.closest('.menu-item').dataset.tab;
            document.getElementById(tab).classList.add('active');
        }
    </script>
</body>
</html>
