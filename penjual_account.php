<?php
session_start();
include 'koneksi.php';
// Check user login and role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'penjual') {
header("Location: login.php");
exit;
}
$user_id = $_SESSION['id_user'];
$user_name = $_SESSION['nama'];
// Get user data
$stmt_user = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_query = mysqli_stmt_get_result($stmt_user);
$user_data = mysqli_fetch_assoc($user_query);
mysqli_stmt_close($stmt_user);
// Handle profile update
$update_success = false;
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (isset($_POST['update_profile'])) {
$nama = mysqli_real_escape_string($conn, $_POST['nama']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];
// Verify current password if trying to change password
if (!empty($new_password)) {
if (password_verify($current_password, $user_data['password']) || $current_password == $user_data['password']) {
if ($new_password === $confirm_password) {
// Hash new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, email = ?, password = ? WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, "sssi", $nama, $email, $hashed_password, $user_id);
} else {
$update_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Password baru tidak cocok!</div>';
}
} else {
$update_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Password saat ini salah!</div>';
}
} else {
// Update without changing password
$stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, email = ? WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, "ssi", $nama, $email, $user_id);
}
if (isset($stmt)) {
if (mysqli_stmt_execute($stmt)) {
// Update session data
$_SESSION['nama'] = $nama;
$_SESSION['email'] = $email;
$user_name = $nama;
$update_success = true;
$update_message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profil berhasil diperbarui!</div>';
// Refresh user data
$stmt_refresh = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_refresh, "i", $user_id);
mysqli_stmt_execute($stmt_refresh);
$result_refresh = mysqli_stmt_get_result($stmt_refresh);
$user_data = mysqli_fetch_assoc($result_refresh);
mysqli_stmt_close($stmt_refresh);
} else {
$update_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal memperbarui profil!</div>';
}
}
}
// Handle avatar upload
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
$file_tmp = $_FILES['avatar']['tmp_name'];
$file_size = $_FILES['avatar']['size'];
$file_name = $_FILES['avatar']['name'];
$file_type = $_FILES['avatar']['type'];
// Batas ukuran file (5MB)
if ($file_size > 5242880) {
$update_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Ukuran file terlalu besar. Maksimal 5MB</div>';
} else {
// Deteksi apakah file adalah gambar
$is_image = false;
$image_info = @getimagesize($file_tmp);
if ($image_info !== false) {
$is_image = true;
$mime_type = $image_info['mime'];
// Daftar mime type yang diizinkan
$allowed_mimes = [
'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp',
'image/bmp', 'image/svg+xml', 'image/tiff', 'image/x-icon',
'image/vnd.microsoft.icon', 'image/jp2', 'image/jpx', 'image/jpm',
'image/heic', 'image/heif', 'image/avif', 'image/jxl'
];
// Cek mime type
if (!in_array($mime_type, $allowed_mimes)) {
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$allowed_extensions = [
'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff', 'ico',
'jfif', 'pjpeg', 'jpe', 'jp2', 'jpx', 'jpm', 'heic', 'heif', 'avif', 'jxl'
];
if (!in_array($file_ext, $allowed_extensions)) {
$is_image = false;
}
}
}
if ($is_image) {
$original_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
if (empty($original_ext) && isset($mime_type)) {
$mime_to_ext = [
'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png',
'image/gif' => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp',
'image/svg+xml' => 'svg', 'image/tiff' => 'tiff', 'image/x-icon' => 'ico',
'image/vnd.microsoft.icon' => 'ico', 'image/jp2' => 'jp2', 'image/jpx' => 'jpx',
'image/jpm' => 'jpm', 'image/heic' => 'heic', 'image/heif' => 'heif',
'image/avif' => 'avif', 'image/jxl' => 'jxl'
];
$original_ext = $mime_to_ext[$mime_type] ?? 'img';
}
$filename = 'avatar_' . $user_id . '_' . time() . '.' . $original_ext;
$upload_dir = 'uploads/avatars/';
if (!is_dir($upload_dir)) {
mkdir($upload_dir, 0777, true);
file_put_contents($upload_dir . '.htaccess', "Options -Indexes
Deny from all
<FilesMatch '\.(jpg|jpeg|png|gif|webp|bmp|svg|tiff|ico|heic|avif|jxl)$'>
Allow from all
</FilesMatch>");
}
// Hapus avatar lama jika ada
if (!empty($user_data['avatar'])) {
$old_file = $upload_dir . $user_data['avatar'];
if (file_exists($old_file)) {
@unlink($old_file);
}
}
// Upload file
if (move_uploaded_file($file_tmp, $upload_dir . $filename)) {
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'avatar'");
if (mysqli_num_rows($check_column) == 0) {
mysqli_query($conn, "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email");
}
$stmt_avatar = mysqli_prepare($conn, "UPDATE users SET avatar = ? WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_avatar, "si", $filename, $user_id);
if (mysqli_stmt_execute($stmt_avatar)) {
mysqli_stmt_close($stmt_avatar);
$update_success = true;
$update_message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Foto profil berhasil diupload!</div>';
$stmt_refresh = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_refresh, "i", $user_id);
mysqli_stmt_execute($stmt_refresh);
$result_refresh = mysqli_stmt_get_result($stmt_refresh);
$user_data = mysqli_fetch_assoc($result_refresh);
$_SESSION['avatar'] = $filename;
mysqli_stmt_close($stmt_refresh);
} else {
$update_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal menyimpan ke database!</div>';
}
} else {
$update_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal mengupload foto. Coba lagi.</div>';
}
} else {
$update_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> File bukan gambar yang valid.</div>';
}
}
}
}
// Set session avatar jika ada
if (!isset($_SESSION['avatar']) && !empty($user_data['avatar'])) {
$_SESSION['avatar'] = $user_data['avatar'];
}
// Get seller statistics
$stmt_products = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM produk WHERE id_penjual = ?");
mysqli_stmt_bind_param($stmt_products, "i", $user_id);
mysqli_stmt_execute($stmt_products);
$total_products = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_products));
mysqli_stmt_close($stmt_products);
$stmt_sales = mysqli_prepare($conn, "SELECT COUNT(DISTINCT t.id_transaksi) as count FROM transaksi t
JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
JOIN produk p ON dt.id_produk = p.id_produk
WHERE p.id_penjual = ? AND t.status IN ('selesai', 'dikirim')");
mysqli_stmt_bind_param($stmt_sales, "i", $user_id);
mysqli_stmt_execute($stmt_sales);
$total_sales = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_sales));
mysqli_stmt_close($stmt_sales);
$stmt_revenue = mysqli_prepare($conn, "SELECT SUM(t.total_harga) as total FROM transaksi t
JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
JOIN produk p ON dt.id_produk = p.id_produk
WHERE p.id_penjual = ? AND t.status IN ('selesai', 'dikirim')");
mysqli_stmt_bind_param($stmt_revenue, "i", $user_id);
mysqli_stmt_execute($stmt_revenue);
$total_revenue = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_revenue));
mysqli_stmt_close($stmt_revenue);
$joined_date = date('d M Y', strtotime($user_data['created_at'] ?? date('Y-m-d')));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>👤 My Account - Pustaka Aruna</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
--primary: #2563eb;
--primary-dark: #1d4ed8;
--primary-light: #3b82f6;
--primary-lighter: #60a5fa;
--primary-bg: #eff6ff;
--secondary: #64748b;
--secondary-light: #94a3b8;
--success: #10b981;
--success-bg: #d1fae5;
--success-dark: #059669;
--error: #ef4444;
--error-bg: #fee2e2;
--warning: #f59e0b;
--warning-bg: #fef3c7;
--info: #3b82f6;
--info-bg: #dbeafe;
--background: #f8fafc;
--surface: #ffffff;
--border: #e2e8f0;
--border-light: #f1f5f9;
--text-primary: #1e293b;
--text-secondary: #64748b;
--text-muted: #94a3b8;
--shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
--shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
--shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
--shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
--radius: 12px;
--radius-sm: 8px;
--radius-lg: 16px;
}
* {
margin: 0;
padding: 0;
box-sizing: border-box;
}
body {
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
background-color: var(--background);
color: var(--text-primary);
min-height: 100vh;
line-height: 1.6;
}
.container {
max-width: 1200px;
margin: 2rem auto;
padding: 0 20px;
}
/* Header */
.header {
background: var(--surface);
padding: 2.5rem;
border-radius: var(--radius-lg);
box-shadow: var(--shadow-lg);
margin-bottom: 2.5rem;
border: 1px solid var(--border);
position: relative;
overflow: hidden;
}
.header::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 4px;
background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
}
.header h1 {
color: var(--text-primary);
font-size: 2rem;
margin-bottom: 0.5rem;
display: flex;
align-items: center;
gap: 12px;
font-family: 'Inter', sans-serif;
font-weight: 700;
letter-spacing: -0.025em;
}
.header h1 i {
color: var(--primary);
}
.header p {
color: var(--text-secondary);
font-size: 1rem;
}
/* Dashboard Back Button */
.dashboard-back-btn {
position: absolute;
top: 2.5rem;
right: 2.5rem;
background: linear-gradient(135deg, var(--primary), var(--primary-dark));
color: white;
padding: 12px 25px;
border-radius: 25px;
text-decoration: none;
font-weight: 600;
font-size: 0.95rem;
display: flex;
align-items: center;
gap: 10px;
transition: all 0.3s;
box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
border: 2px solid transparent;
}
.dashboard-back-btn:hover {
background: linear-gradient(135deg, var(--primary-dark), var(--primary));
transform: translateY(-2px);
box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
border-color: var(--primary-light);
}
.dashboard-back-btn i {
font-size: 1rem;
}
/* Content Grid */
.content-grid {
display: grid;
grid-template-columns: 350px 1fr;
gap: 2.5rem;
}
/* Profile Card */
.profile-card {
background: var(--surface);
border-radius: var(--radius-lg);
box-shadow: var(--shadow-lg);
overflow: hidden;
position: sticky;
top: 2rem;
border: 1px solid var(--border);
}
.profile-header {
background: linear-gradient(135deg, var(--primary), var(--primary-dark));
padding: 3rem 2rem 2rem;
text-align: center;
position: relative;
}
.avatar-container {
position: relative;
display: inline-block;
margin-bottom: 1.5rem;
}
.avatar {
width: 160px;
height: 160px;
border-radius: 50%;
border: 5px solid var(--surface);
object-fit: cover;
background: linear-gradient(135deg, var(--primary-light), var(--primary));
display: flex;
align-items: center;
justify-content: center;
color: var(--surface);
font-size: 3.5rem;
font-weight: 700;
margin: 0 auto;
box-shadow: var(--shadow-lg);
}
.avatar img {
width: 100%;
height: 100%;
border-radius: 50%;
object-fit: cover;
}
.avatar-text {
position: absolute;
top: 50%;
left: 50%;
transform: translate(-50%, -50%);
color: var(--surface);
font-size: 3.5rem;
font-weight: 700;
}
.change-avatar-btn {
position: absolute;
bottom: 10px;
right: 10px;
background: var(--surface);
color: var(--primary);
border: 2px solid var(--primary);
width: 45px;
height: 45px;
border-radius: 50%;
cursor: pointer;
display: flex;
align-items: center;
justify-content: center;
box-shadow: var(--shadow-md);
transition: all 0.3s;
font-size: 1.1rem;
}
.change-avatar-btn:hover {
background: var(--primary);
color: var(--surface);
transform: scale(1.1);
border-color: var(--primary-light);
}
.profile-name {
color: var(--surface);
font-size: 1.8rem;
font-weight: 700;
margin-bottom: 5px;
text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.profile-email {
color: rgba(255, 255, 255, 0.9);
font-size: 1rem;
margin-bottom: 15px;
}
.profile-role {
display: inline-block;
background: rgba(255, 255, 255, 0.15);
color: var(--surface);
padding: 8px 20px;
border-radius: 20px;
font-size: 0.9rem;
font-weight: 600;
border: 1px solid rgba(255,255,255,0.3);
}
.profile-body {
padding: 2.5rem 2rem;
}
.profile-info {
margin-bottom: 2rem;
}
.info-item {
display: flex;
justify-content: space-between;
padding: 12px 0;
border-bottom: 1px solid var(--border-light);
}
.info-item:last-child {
border-bottom: none;
}
.info-label {
color: var(--text-secondary);
font-weight: 500;
font-size: 0.9rem;
}
.info-value {
color: var(--text-primary);
font-weight: 600;
font-size: 0.9rem;
}
/* Stats Cards */
.stats-cards {
display: grid;
grid-template-columns: repeat(3, 1fr);
gap: 15px;
margin-top: 2.5rem;
}
.stat-card {
background: var(--primary-bg);
padding: 1.2rem;
border-radius: var(--radius);
text-align: center;
border: 1px solid var(--border);
transition: all 0.3s;
}
.stat-card:hover {
transform: translateY(-5px);
box-shadow: var(--shadow-md);
border-color: var(--primary-light);
}
.stat-number {
font-size: 1.5rem;
font-weight: 700;
color: var(--primary);
margin-bottom: 5px;
}
.stat-label {
font-size: 0.85rem;
color: var(--text-secondary);
font-weight: 500;
}
/* Settings Card */
.settings-card {
background: var(--surface);
border-radius: var(--radius-lg);
box-shadow: var(--shadow-lg);
overflow: hidden;
border: 1px solid var(--border);
}
.settings-header {
padding: 1.8rem 2.5rem;
border-bottom: 1px solid var(--border);
background: var(--background);
}
.settings-header h2 {
color: var(--text-primary);
font-size: 1.4rem;
font-weight: 600;
font-family: 'Inter', sans-serif;
}
.settings-body {
padding: 2.5rem;
}
/* Tabs */
.settings-tabs {
display: flex;
border-bottom: 1px solid var(--border);
margin-bottom: 2.5rem;
background: var(--background);
border-radius: var(--radius) var(--radius) 0 0;
}
.tab-btn {
padding: 15px 25px;
background: none;
border: none;
cursor: pointer;
font-weight: 500;
color: var(--text-secondary);
position: relative;
transition: all 0.3s;
font-size: 0.95rem;
font-family: 'Inter', sans-serif;
}
.tab-btn:hover {
color: var(--primary);
background: var(--primary-bg);
}
.tab-btn.active {
color: var(--primary);
background: var(--surface);
font-weight: 600;
}
.tab-btn.active::after {
content: '';
position: absolute;
bottom: -1px;
left: 0;
width: 100%;
height: 3px;
background: var(--primary);
}
/* Tab Content */
.tab-content {
display: none;
}
.tab-content.active {
display: block;
animation: fadeIn 0.4s ease;
}
@keyframes fadeIn {
from { opacity: 0; transform: translateY(10px); }
to { opacity: 1; transform: translateY(0); }
}
/* Form Styles */
.form-group {
margin-bottom: 2rem;
}
.form-label {
display: block;
margin-bottom: 10px;
font-weight: 600;
color: var(--text-primary);
font-size: 0.95rem;
}
.form-input {
width: 100%;
padding: 14px 18px;
border: 2px solid var(--border);
border-radius: var(--radius-sm);
font-size: 1rem;
transition: all 0.3s;
background: var(--surface);
color: var(--text-primary);
font-family: 'Inter', sans-serif;
}
.form-input:focus {
outline: none;
border-color: var(--primary);
box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
}
.input-with-icon {
position: relative;
}
.input-icon {
position: absolute;
left: 18px;
top: 50%;
transform: translateY(-50%);
color: var(--text-muted);
font-size: 1.1rem;
}
.input-with-icon .form-input {
padding-left: 50px;
}
.form-hint {
font-size: 0.85rem;
color: var(--text-muted);
margin-top: 8px;
}
.form-row {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 20px;
}
.form-actions {
display: flex;
gap: 15px;
margin-top: 2.5rem;
padding-top: 2rem;
border-top: 1px solid var(--border);
}
.btn-primary {
background: var(--primary);
color: white;
padding: 14px 35px;
border-radius: 25px;
border: none;
cursor: pointer;
font-weight: 600;
font-size: 1rem;
display: flex;
align-items: center;
gap: 10px;
transition: all 0.3s;
font-family: 'Inter', sans-serif;
box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.btn-primary:hover {
background: var(--primary-dark);
transform: translateY(-2px);
box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
}
.btn-secondary {
background: var(--surface);
color: var(--text-secondary);
padding: 14px 35px;
border-radius: 25px;
border: 2px solid var(--border);
cursor: pointer;
font-weight: 600;
font-size: 1rem;
display: flex;
align-items: center;
gap: 10px;
transition: all 0.3s;
font-family: 'Inter', sans-serif;
}
.btn-secondary:hover {
background: var(--background);
border-color: var(--primary-light);
color: var(--primary);
transform: translateY(-2px);
}
/* Alert Messages */
.alert {
padding: 1.2rem;
border-radius: var(--radius);
margin-bottom: 2rem;
display: flex;
align-items: center;
gap: 15px;
border: 1px solid;
animation: slideIn 0.4s ease;
}
@keyframes slideIn {
from { opacity: 0; transform: translateY(-10px); }
to { opacity: 1; transform: translateY(0); }
}
.alert-success {
background: var(--success-bg);
color: #065f46;
border-color: var(--success);
}
.alert-danger {
background: var(--error-bg);
color: #991b1b;
border-color: var(--error);
}
.alert i {
font-size: 1.3rem;
}
/* Security Items */
.security-item {
background: var(--background);
padding: 2rem;
border-radius: var(--radius);
margin-bottom: 1.5rem;
border: 1px solid var(--border);
transition: all 0.3s;
}
.security-item:hover {
border-color: var(--primary-light);
box-shadow: var(--shadow-sm);
}
.security-header {
display: flex;
justify-content: space-between;
align-items: center;
margin-bottom: 15px;
}
.security-title {
font-weight: 600;
color: var(--text-primary);
font-size: 1rem;
}
.security-status {
font-size: 0.85rem;
font-weight: 600;
padding: 6px 15px;
border-radius: 20px;
border: 1px solid;
}
.status-enabled {
background: var(--success-bg);
color: #065f46;
border-color: var(--success);
}
.status-disabled {
background: var(--error-bg);
color: #991b1b;
border-color: var(--error);
}
.security-description {
color: var(--text-secondary);
font-size: 0.9rem;
margin-bottom: 1.5rem;
line-height: 1.6;
}
/* Activity Log */
.activity-list {
max-height: 400px;
overflow-y: auto;
padding-right: 10px;
}
.activity-list::-webkit-scrollbar {
width: 6px;
}
.activity-list::-webkit-scrollbar-thumb {
background: var(--primary-light);
border-radius: 3px;
}
.activity-item {
display: flex;
gap: 20px;
padding: 1.5rem;
border-bottom: 1px solid var(--border-light);
transition: all 0.3s;
background: var(--surface);
margin-bottom: 10px;
border-radius: var(--radius);
border: 1px solid var(--border);
}
.activity-item:last-child {
border-bottom: none;
}
.activity-item:hover {
background: var(--primary-bg);
border-color: var(--primary-light);
transform: translateX(5px);
}
.activity-icon {
width: 50px;
height: 50px;
border-radius: var(--radius);
background: var(--primary);
color: white;
display: flex;
align-items: center;
justify-content: center;
flex-shrink: 0;
font-size: 1.3rem;
}
.activity-content {
flex: 1;
}
.activity-title {
font-weight: 600;
color: var(--text-primary);
margin-bottom: 5px;
font-size: 0.95rem;
}
.activity-description {
color: var(--text-secondary);
font-size: 0.9rem;
margin-bottom: 8px;
}
.activity-time {
font-size: 0.8rem;
color: var(--text-muted);
}
/* Modal for Avatar Upload */
.modal {
display: none;
position: fixed;
top: 0;
left: 0;
width: 100%;
height: 100%;
background: rgba(30, 41, 59, 0.8);
z-index: 1000;
align-items: center;
justify-content: center;
padding: 20px;
backdrop-filter: blur(3px);
}
.modal-content {
background: var(--surface);
border-radius: var(--radius-lg);
width: 90%;
max-width: 500px;
box-shadow: var(--shadow-xl);
border: 1px solid var(--border);
overflow: hidden;
animation: modalSlideIn 0.3s ease;
}
@keyframes modalSlideIn {
from { opacity: 0; transform: translateY(-20px); }
to { opacity: 1; transform: translateY(0); }
}
.modal-header {
padding: 1.8rem 2.5rem;
border-bottom: 1px solid var(--border);
background: var(--background);
display: flex;
justify-content: space-between;
align-items: center;
}
.modal-header h3 {
color: var(--text-primary);
font-size: 1.3rem;
font-weight: 600;
font-family: 'Inter', sans-serif;
}
.close-modal {
background: none;
border: none;
font-size: 1.8rem;
cursor: pointer;
color: var(--text-muted);
transition: all 0.3s;
width: 40px;
height: 40px;
border-radius: 50%;
display: flex;
align-items: center;
justify-content: center;
}
.close-modal:hover {
color: var(--text-primary);
background: var(--background);
transform: rotate(90deg);
}
.modal-body {
padding: 2.5rem;
}
.avatar-upload {
border: 3px dashed var(--border);
border-radius: var(--radius);
padding: 3rem 2rem;
text-align: center;
cursor: pointer;
transition: all 0.3s;
margin-bottom: 2rem;
background: var(--background);
}
.avatar-upload:hover {
border-color: var(--primary);
background: var(--primary-bg);
}
.avatar-upload input {
display: none;
}
.upload-label {
display: flex;
flex-direction: column;
align-items: center;
gap: 20px;
color: var(--text-secondary);
}
.upload-label i {
font-size: 3.5rem;
color: var(--primary);
}
.upload-label span {
font-size: 1.1rem;
font-weight: 600;
}
.upload-label small {
font-size: 0.85rem;
color: var(--text-muted);
margin-top: 5px;
}
.avatar-preview {
width: 180px;
height: 180px;
border-radius: 50%;
margin: 0 auto 2rem;
overflow: hidden;
border: 3px solid var(--primary);
box-shadow: var(--shadow-md);
}
.avatar-preview img {
width: 100%;
height: 100%;
object-fit: cover;
}
/* Preferences */
.preference-option {
display: flex;
align-items: center;
gap: 15px;
padding: 15px;
background: var(--background);
border-radius: var(--radius);
margin-bottom: 15px;
transition: all 0.3s;
border: 1px solid var(--border);
}
.preference-option:hover {
border-color: var(--primary-light);
background: var(--primary-bg);
}
.preference-option input[type="checkbox"] {
width: 20px;
height: 20px;
accent-color: var(--primary);
}
.preference-option label {
flex: 1;
color: var(--text-primary);
font-size: 0.95rem;
cursor: pointer;
}
/* Responsive */
@media (max-width: 992px) {
.content-grid {
grid-template-columns: 1fr;
}
.profile-card {
position: static;
}
.stats-cards {
grid-template-columns: repeat(3, 1fr);
}
.dashboard-back-btn {
position: static;
margin-top: 1.5rem;
display: inline-flex;
}
.header {
padding: 2rem 1.5rem;
}
}
@media (max-width: 768px) {
.form-row {
grid-template-columns: 1fr;
}
.stats-cards {
grid-template-columns: repeat(2, 1fr);
}
.settings-tabs {
flex-wrap: wrap;
}
.tab-btn {
flex: 1;
text-align: center;
padding: 12px 15px;
font-size: 0.9rem;
}
.header {
padding: 2rem 1.5rem;
}
.header h1 {
font-size: 1.6rem;
}
.settings-body {
padding: 1.5rem;
}
.dashboard-back-btn {
position: static;
margin-top: 1.5rem;
width: 100%;
justify-content: center;
}
}
@media (max-width: 576px) {
.stats-cards {
grid-template-columns: 1fr;
}
.form-actions {
flex-direction: column;
}
.btn-primary, .btn-secondary {
width: 100%;
justify-content: center;
}
.profile-header {
padding: 2.5rem 1.5rem 1.5rem;
}
.avatar {
width: 140px;
height: 140px;
}
.profile-body {
padding: 2rem 1.5rem;
}
}
</style>
</head>
<body>
<div class="container">
<!-- Header -->
<div class="header">
<h1><i class="fas fa-user-circle"></i> My Account</h1>
<p>Kelola profil, pengaturan akun, dan keamanan akun Anda</p>
<!-- Dashboard Back Button -->
<a href="penjual_dashboard.php" class="dashboard-back-btn">
<i class="fas fa-arrow-left"></i>
Kembali ke Dashboard
</a>
</div>
<!-- Content Grid -->
<div class="content-grid">
<!-- Profile Card -->
<div class="profile-card">
<div class="profile-header">
<div class="avatar-container">
<div class="avatar">
<?php
$avatar_path = !empty($user_data['avatar']) ? 'uploads/avatars/' . $user_data['avatar'] : '';
if (!empty($avatar_path) && file_exists($avatar_path)):
?>
<img src="<?php echo $avatar_path . '?t=' . time(); ?>" alt="Avatar">
<?php else: ?>
<div class="avatar-text"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
<?php endif; ?>
</div>
<button class="change-avatar-btn" onclick="openAvatarModal()" title="Ubah Foto Profil">
<i class="fas fa-camera"></i>
</button>
</div>
<div class="profile-name"><?php echo htmlspecialchars($user_name); ?></div>
<div class="profile-email"><?php echo htmlspecialchars($user_data['email'] ?? ''); ?></div>
<div class="profile-role"><i class="fas fa-store"></i> Penjual</div>
</div>
<div class="profile-body">
<div class="profile-info">
<div class="info-item">
<span class="info-label"><i class="fas fa-circle" style="font-size: 8px; margin-right: 8px; color: <?php echo ($user_data['status'] ?? 'offline') == 'online' ? 'var(--success)' : 'var(--text-muted)'; ?>;"></i>Status</span>
<span class="info-value"><?php echo ($user_data['status'] ?? 'offline') == 'online' ? 'Online' : 'Offline'; ?></span>
</div>
<div class="info-item">
<span class="info-label"><i class="fas fa-calendar"></i> Bergabung</span>
<span class="info-value"><?php echo $joined_date; ?></span>
</div>
<div class="info-item">
<span class="info-label"><i class="fas fa-id-card"></i> ID Pengguna</span>
<span class="info-value">#<?php echo str_pad($user_id, 4, '0', STR_PAD_LEFT); ?></span>
</div>
</div>
<div class="stats-cards">
<div class="stat-card">
<div class="stat-number"><?php echo $total_products['count'] ?? 0; ?></div>
<div class="stat-label"><i class="fas fa-book"></i> Produk</div>
</div>
<div class="stat-card">
<div class="stat-number"><?php echo $total_sales['count'] ?? 0; ?></div>
<div class="stat-label"><i class="fas fa-shopping-cart"></i> Penjualan</div>
</div>
<div class="stat-card">
<div class="stat-number">Rp <?php echo number_format($total_revenue['total'] ?? 0, 0, ',', '.'); ?></div>
<div class="stat-label"><i class="fas fa-wallet"></i> Pendapatan</div>
</div>
</div>
</div>
</div>
<!-- Settings Card -->
<div class="settings-card">
<div class="settings-header">
<h2><i class="fas fa-cog"></i> Pengaturan Akun</h2>
</div>
<!-- Tabs -->
<div class="settings-tabs">
<button class="tab-btn active" data-tab="profile"><i class="fas fa-user"></i> Profil</button>
<button class="tab-btn" data-tab="security"><i class="fas fa-shield-alt"></i> Keamanan</button>
<button class="tab-btn" data-tab="activity"><i class="fas fa-history"></i> Aktivitas</button>
<button class="tab-btn" data-tab="preferences"><i class="fas fa-sliders-h"></i> Preferensi</button>
</div>
<div class="settings-body">
<!-- Update Messages -->
<?php if (!empty($update_message)): ?>
<?php echo $update_message; ?>
<?php endif; ?>
<!-- Profile Tab -->
<div id="profile-tab" class="tab-content active">
<form method="POST">
<div class="form-group">
<label class="form-label" for="nama">Nama Lengkap</label>
<div class="input-with-icon">
<i class="fas fa-user input-icon"></i>
<input type="text" id="nama" name="nama" class="form-input"
value="<?php echo htmlspecialchars($user_data['nama'] ?? ''); ?>" required>
</div>
</div>
<div class="form-group">
<label class="form-label" for="email">Email</label>
<div class="input-with-icon">
<i class="fas fa-envelope input-icon"></i>
<input type="email" id="email" name="email" class="form-input"
value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
</div>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label" for="current_password">Password Saat Ini</label>
<div class="input-with-icon">
<i class="fas fa-lock input-icon"></i>
<input type="password" id="current_password" name="current_password"
class="form-input" placeholder="Kosongkan jika tidak ingin mengubah">
</div>
</div>
<div class="form-group">
<label class="form-label" for="new_password">Password Baru</label>
<div class="input-with-icon">
<i class="fas fa-key input-icon"></i>
<input type="password" id="new_password" name="new_password"
class="form-input" placeholder="Minimal 8 karakter">
</div>
</div>
</div>
<div class="form-group">
<label class="form-label" for="confirm_password">Konfirmasi Password Baru</label>
<div class="input-with-icon">
<i class="fas fa-lock input-icon"></i>
<input type="password" id="confirm_password" name="confirm_password"
class="form-input">
</div>
<div class="form-hint" id="passwordHint"></div>
</div>
<div class="form-actions">
<button type="button" class="btn-secondary" onclick="resetForm()">
<i class="fas fa-redo"></i> Reset
</button>
<button type="submit" name="update_profile" class="btn-primary">
<i class="fas fa-save"></i> Simpan Perubahan
</button>
</div>
</form>
</div>
<!-- Security Tab -->
<div id="security-tab" class="tab-content">
<div class="security-item">
<div class="security-header">
<div class="security-title"><i class="fas fa-mobile-alt"></i> Autentikasi Dua Faktor</div>
<div class="security-status status-disabled">Nonaktif</div>
</div>
<div class="security-description">
Tambahkan lapisan keamanan ekstra ke akun Anda dengan verifikasi dua langkah.
</div>
<button class="btn-secondary" style="padding: 10px 25px;">
<i class="fas fa-plus"></i> Aktifkan
</button>
</div>
<div class="security-item">
<div class="security-header">
<div class="security-title"><i class="fas fa-desktop"></i> Sesi Aktif</div>
<div class="security-status status-enabled">1 Aktif</div>
</div>
<div class="security-description">
Anda saat ini masuk di perangkat ini.
</div>
<button class="btn-secondary" style="padding: 10px 25px;">
<i class="fas fa-list"></i> Lihat Semua Sesi
</button>
</div>
<div class="security-item">
<div class="security-header">
<div class="security-title"><i class="fas fa-envelope"></i> Email Terverifikasi</div>
<div class="security-status status-enabled">Terverifikasi</div>
</div>
<div class="security-description">
<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>
</div>
<button class="btn-secondary" style="padding: 10px 25px;">
<i class="fas fa-edit"></i> Ubah Email
</button>
</div>
</div>
<!-- Activity Tab -->
<div id="activity-tab" class="tab-content">
<div class="activity-list">
<div class="activity-item">
<div class="activity-icon">
<i class="fas fa-sign-in-alt"></i>
</div>
<div class="activity-content">
<div class="activity-title">Login Berhasil</div>
<div class="activity-description">Anda berhasil login ke akun</div>
<div class="activity-time"><i class="fas fa-clock"></i> Hari ini, <?php echo date('H:i'); ?></div>
</div>
</div>
<div class="activity-item">
<div class="activity-icon">
<i class="fas fa-plus-circle"></i>
</div>
<div class="activity-content">
<div class="activity-title">Produk Ditambahkan</div>
<div class="activity-description">Menambahkan produk baru ke katalog</div>
<div class="activity-time"><i class="fas fa-clock"></i> Kemarin, 14:30</div>
</div>
</div>
<div class="activity-item">
<div class="activity-icon">
<i class="fas fa-check-circle"></i>
</div>
<div class="activity-content">
<div class="activity-title">Transaksi Disetujui</div>
<div class="activity-description">Menyetujui pembayaran pesanan #001</div>
<div class="activity-time"><i class="fas fa-clock"></i> 2 hari lalu, 10:15</div>
</div>
</div>
<div class="activity-item">
<div class="activity-icon">
<i class="fas fa-key"></i>
</div>
<div class="activity-content">
<div class="activity-title">Password Diubah</div>
<div class="activity-description">Password akun berhasil diperbarui</div>
<div class="activity-time"><i class="fas fa-clock"></i> 1 minggu lalu, 09:45</div>
</div>
</div>
</div>
</div>
<!-- Preferences Tab -->
<div id="preferences-tab" class="tab-content">
<div class="form-group">
<label class="form-label"><i class="fas fa-bell"></i> Notifikasi</label>
<div class="preference-option">
<input type="checkbox" id="notif_order" checked>
<label for="notif_order">Notifikasi Email untuk pesanan baru</label>
</div>
<div class="preference-option">
<input type="checkbox" id="notif_chat" checked>
<label for="notif_chat">Notifikasi Chat real-time</label>
</div>
<div class="preference-option">
<input type="checkbox" id="notif_promo">
<label for="notif_promo">Promosi dan penawaran khusus</label>
</div>
</div>
<div class="form-group">
<label class="form-label" for="language"><i class="fas fa-language"></i> Bahasa</label>
<select id="language" class="form-input">
<option value="id" selected>Bahasa Indonesia</option>
<option value="en">English</option>
</select>
</div>
<div class="form-group">
<label class="form-label" for="timezone"><i class="fas fa-globe"></i> Zona Waktu</label>
<select id="timezone" class="form-input">
<option value="Asia/Jakarta" selected>WIB (Jakarta)</option>
<option value="Asia/Makassar">WITA (Makassar)</option>
<option value="Asia/Jayapura">WIT (Jayapura)</option>
</select>
</div>
<div class="form-actions" style="border-top: none; padding-top: 0;">
<button type="button" class="btn-primary">
<i class="fas fa-save"></i> Simpan Preferensi
</button>
</div>
</div>
</div>
</div>
</div>
</div>
<!-- Avatar Upload Modal -->
<div id="avatarModal" class="modal">
<div class="modal-content">
<div class="modal-header">
<h3><i class="fas fa-camera"></i> Ubah Foto Profil</h3>
<button class="close-modal" onclick="closeAvatarModal()">&times;</button>
</div>
<div class="modal-body">
<form method="POST" enctype="multipart/form-data">
<div class="avatar-upload" onclick="document.getElementById('avatarInput').click()">
<input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="previewAvatar(this)">
<label for="avatarInput" class="upload-label">
<i class="fas fa-cloud-upload-alt"></i>
<span>Klik untuk upload foto</span>
<small>JPG, PNG, GIF, WebP, BMP, SVG (Max 5MB)</small>
</label>
</div>
<div class="avatar-preview" id="avatarPreview" style="display: none;">
<img id="previewImg" src="" alt="Preview">
</div>
<div class="form-actions" style="border-top: none; padding-top: 0;">
<button type="button" class="btn-secondary" onclick="closeAvatarModal()">
<i class="fas fa-times"></i> Batal
</button>
<button type="submit" class="btn-primary">
<i class="fas fa-upload"></i> Upload Foto
</button>
</div>
</form>
</div>
</div>
</div>
<script>
// Tab functionality
document.querySelectorAll('.tab-btn').forEach(btn => {
btn.addEventListener('click', function() {
const tabId = this.dataset.tab;
document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
this.classList.add('active');
document.querySelectorAll('.tab-content').forEach(content => {
content.classList.remove('active');
});
document.getElementById(tabId + '-tab').classList.add('active');
});
});
// Avatar modal functions
function openAvatarModal() {
document.getElementById('avatarModal').style.display = 'flex';
}
function closeAvatarModal() {
document.getElementById('avatarModal').style.display = 'none';
document.getElementById('avatarPreview').style.display = 'none';
document.getElementById('avatarInput').value = '';
}
// Avatar preview
function previewAvatar(input) {
const preview = document.getElementById('avatarPreview');
const previewImg = document.getElementById('previewImg');
if (input.files && input.files[0]) {
if (input.files[0].size > 5242880) {
alert('Ukuran file terlalu besar. Maksimal 5MB');
input.value = '';
preview.style.display = 'none';
return;
}
const fileType = input.files[0].type;
const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'];
if (!validTypes.includes(fileType)) {
alert('Format file tidak didukung. Harap upload file gambar (JPG, PNG, GIF, WebP, BMP, SVG).');
input.value = '';
preview.style.display = 'none';
return;
}
const reader = new FileReader();
reader.onload = function(e) {
previewImg.src = e.target.result;
preview.style.display = 'block';
}
reader.readAsDataURL(input.files[0]);
}
}
// Reset form
function resetForm() {
document.getElementById('nama').value = '<?php echo htmlspecialchars($user_data['nama'] ?? ''); ?>';
document.getElementById('email').value = '<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>';
document.getElementById('current_password').value = '';
document.getElementById('new_password').value = '';
document.getElementById('confirm_password').value = '';
document.getElementById('passwordHint').textContent = '';
}
// Password validation
document.getElementById('new_password')?.addEventListener('input', function() {
const password = this.value;
const confirm = document.getElementById('confirm_password');
const hint = document.getElementById('passwordHint');
if (password.length > 0 && password.length < 8) {
this.style.borderColor = 'var(--error)';
hint.textContent = 'Password minimal 8 karakter';
hint.style.color = 'var(--error)';
} else {
this.style.borderColor = password === confirm.value ? 'var(--success)' : 'var(--border)';
hint.textContent = '';
}
if (confirm.value.length > 0) {
confirm.style.borderColor = password === confirm.value ? 'var(--success)' : 'var(--border)';
}
});
document.getElementById('confirm_password')?.addEventListener('input', function() {
const password = document.getElementById('new_password').value;
const confirm = this.value;
const hint = document.getElementById('passwordHint');
this.style.borderColor = password === confirm ? 'var(--success)' : 'var(--error)';
if (password.length >= 8 && confirm.length > 0) {
hint.textContent = password === confirm ? 'Password cocok' : 'Password tidak cocok';
hint.style.color = password === confirm ? 'var(--success)' : 'var(--error)';
}
});
// Close modal when clicking outside
window.onclick = function(event) {
if (event.target.classList.contains('modal')) {
closeAvatarModal();
}
}
// Drag and drop untuk avatar upload
const avatarUpload = document.querySelector('.avatar-upload');
avatarUpload.addEventListener('dragover', function(e) {
e.preventDefault();
this.style.borderColor = 'var(--primary)';
this.style.background = 'var(--primary-bg)';
});
avatarUpload.addEventListener('dragleave', function(e) {
e.preventDefault();
this.style.borderColor = 'var(--border)';
this.style.background = var(--background);
});
avatarUpload.addEventListener('drop', function(e) {
e.preventDefault();
this.style.borderColor = 'var(--border)';
this.style.background = 'var(--background)';
const files = e.dataTransfer.files;
if (files.length > 0) {
document.getElementById('avatarInput').files = files;
previewAvatar(document.getElementById('avatarInput'));
}
});
</script>
</body>
</html>