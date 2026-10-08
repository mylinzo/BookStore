<?php
session_start();

// Include koneksi database
include 'koneksi.php';

// Update status ke offline sebelum logout
if (isset($_SESSION['id_user'])) {
    $user_id = $_SESSION['id_user'];
    $update_sql = "UPDATE users SET status = 'offline' WHERE id_user = ?";
    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "i", $user_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
    mysqli_close($conn);
}

// Hapus semua data session
$_SESSION = array();

// Hapus session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hapus remember me cookies
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, "/");
}
if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time() - 3600, "/");
}

// Hancurkan session
session_destroy();

// Redirect ke halaman login
header("Location: login.php?logout=success");
exit;
?>