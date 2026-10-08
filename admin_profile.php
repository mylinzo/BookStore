<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id_user'];

// Get current user
$current_user = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) $current_user = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $password_raw = $_POST['password'] ?? '';

    // no avatar upload — keep profile simple with initial letter only

    if (empty($error)) {
        $fields = [];
        $types = '';
        $params = [];

        $fields[] = 'nama = ?'; $types .= 's'; $params[] = $nama;
        $fields[] = 'email = ?'; $types .= 's'; $params[] = $email;

        if (!empty($password_raw)) {
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
            $fields[] = 'password = ?'; $types .= 's'; $params[] = $password_hash;
        }

        // avatar kept as initial letter; do not update avatar file column

        $params[] = $user_id; $types .= 'i';

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id_user = ?';
        $stmt_upd = mysqli_prepare($conn, $sql);
        if ($stmt_upd) {
            // bind params dynamically
            $bind_names[] = $types;
            for ($i = 0; $i < count($params); $i++) {
                $bind_name = 'bind' . $i;
                $$bind_name = $params[$i];
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array(array($stmt_upd, 'bind_param'), $bind_names);
            if (mysqli_stmt_execute($stmt_upd)) {
                mysqli_stmt_close($stmt_upd);
                header('Location: admin_profile.php?success=Profil berhasil diperbarui');
                exit;
            } else {
                $error = 'Gagal menyimpan ke database.';
            }
        } else {
            $error = 'Query update gagal disiapkan.';
        }
    }
}

// Refresh current user data after possible update
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) $current_user = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil Admin - Pustaka Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Crimson+Text:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root{
            --vintage-paper: #f8f4e6;
            --vintage-cream: #fff7ea;
            --vintage-border: #c9a87a;
            --vintage-brown: #5d4037;
            --vintage-gold: #d4af37;
            --vintage-red: #8b2b2b;
            --vintage-dark: #3b2f2a;
            --sidebar-width: 250px;
        }

        *{box-sizing:border-box}
        body{font-family:'Poppins', Arial, sans-serif;background:var(--vintage-cream);color:var(--vintage-dark);margin:0}

        /* Card */
        .card{background:var(--vintage-paper);padding:28px;border-radius:10px;max-width:980px;margin:0 auto;box-shadow:0 10px 30px rgba(91,64,55,0.06);border:2px dashed var(--vintage-border)}

        .page-header{display:flex;align-items:center;gap:18px;margin-bottom:18px}
        .page-title{font-family:'Crimson Text', serif;font-size:26px;color:var(--vintage-brown);font-weight:600;margin:0}

        /* Avatar */
        .avatar-preview{width:88px;height:88px;border-radius:50%;overflow:hidden;border:4px solid var(--vintage-gold);display:flex;align-items:center;justify-content:center;font-size:34px;color:var(--vintage-paper);background:linear-gradient(135deg,var(--vintage-brown),var(--vintage-gold))}

        /* Form layout */
        .form-row{display:flex;gap:28px;align-items:flex-start}
        .form-col-left{flex:0 0 160px;text-align:center}
        .form-col-right{flex:1}
        .form-group{margin-bottom:14px}
        label{display:block;margin-bottom:6px;font-weight:600;color:var(--vintage-brown);font-size:14px}
        input[type=text], input[type=email], input[type=password]{width:100%;padding:10px 12px;border:1px solid var(--vintage-border);border-radius:8px;background:var(--vintage-cream);color:var(--vintage-dark);font-size:14px}

        /* Buttons */
        .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;background:linear-gradient(135deg,var(--vintage-brown),var(--vintage-red));color:#fff;border-radius:8px;text-decoration:none;border:none;cursor:pointer;font-weight:600}
        .btn.secondary{background:transparent;color:var(--vintage-brown);border:2px solid var(--vintage-border);padding:8px 12px}
        .btn-back{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border:2px solid var(--vintage-border);background:transparent;color:var(--vintage-brown);border-radius:8px;text-decoration:none;font-weight:600}
        .btn-back i{color:var(--vintage-brown)}

        .msg{padding:10px;margin-bottom:12px;border-radius:8px}
        .msg.success{background:#eef9ef;color:#116644;border:1px solid rgba(17,102,68,0.08)}
        .msg.error{background:#fff1f1;color:#8b2b2b;border:1px solid rgba(139,43,43,0.06)}

        @media(max-width:900px){
            .form-row{flex-direction:column}
            .form-col-left{flex:0 0 auto}
        }
        /* Layout to match admin pages */
        /* layout for standalone profile page */
        .profile-wrapper{padding:40px;background:var(--vintage-cream);min-height:100vh}
        .main-content{padding:0;margin:0}
    </style>
</head>
<body>
    <div class="profile-wrapper">
        <div class="card">
            <div class="page-header">
                <a href="dashboard_admin.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
                <h2 class="page-title">Profil Saya</h2>
            </div>
        <?php if (!empty($_GET['success'])): ?>
            <div class="msg success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-row">
                <div class="form-col-left">
                    <div class="avatar-preview" aria-hidden="true">
                        <?php echo strtoupper(substr($current_user['nama'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div style="margin-top:10px;font-size:13px;color:#666"></div>
                </div>
                <div class="form-col-right">
                    <div class="form-group">
                        <label for="nama">Nama</label>
                        <input id="nama" type="text" name="nama" value="<?php echo htmlspecialchars($current_user['nama'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($current_user['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span style="font-weight:400;font-size:12px;display:block;margin-top:4px;color:#7a5a48">(kosongkan jika tidak ingin mengubah)</span></label>
                        <input id="password" type="password" name="password" value="">
                    </div>
                    <div style="margin-top:8px">
                        <button class="btn" type="submit">Simpan Perubahan</button>
                        <a href="dashboard_admin.php" class="btn secondary" style="margin-left:10px;">Batal</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
