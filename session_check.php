<?php
/**
 * Session Check Handler
 * File ini dijalankan di awal setiap halaman untuk mengecek dan mengelola session
 * Letakkan ini persiapan setelah session_start()
 */

// Konfigurasi timeout session (dalam detik)
$session_timeout = 30 * 60; // 30 menit

// Jika belum ada session start atau tidak ada login, tidak perlu check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    return;
}

// Check jika user memiliki last activity time
if (isset($_SESSION['last_activity'])) {
    $elapsed = time() - $_SESSION['last_activity'];
    
    // Jika sudah melampaui timeout
    if ($elapsed > $session_timeout) {
        // Update status ke offline di database
        if (isset($_SESSION['id_user'])) {
            include 'koneksi.php';
            $user_id = $_SESSION['id_user'];
            $update_sql = "UPDATE users SET status = 'offline' WHERE id_user = ?";
            if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "i", $user_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
            mysqli_close($conn);
        }
        
        // Destroy session
        $_SESSION = array();
        session_destroy();
        
        // Redirect ke login dengan pesan
        header("Location: login.php?timeout=true");
        exit;
    }
}

// Update last activity time
$_SESSION['last_activity'] = time();
?>
