<?php
session_start();
// ========== KONEKSI DATABASE ==========
$host = "localhost";
$user = "root";
$pass = "";
$db   = "toko-buku";
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
die("Koneksi gagal: " . mysqli_connect_error());
}
// ========== LOGOUT HANDLING DI AWAL ==========
if (isset($_GET['logout'])) {
// Update status user menjadi offline
if (isset($_SESSION["id_user"])) {
$user_id = $_SESSION["id_user"];
$update_sql = "UPDATE users SET status = 'offline' WHERE id_user = ?";
if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
mysqli_stmt_bind_param($update_stmt, "i", $user_id);
mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);
}
}
// Hapus cookie remember me
if (isset($_COOKIE['remember_token'])) {
$token = $_COOKIE['remember_token'];
$sql = "UPDATE users SET remember_token = NULL WHERE remember_token = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
}
setcookie('remember_token', '', time() - 3600, '/');
}
// Hapus semua data session
$_SESSION = array();
// Hancurkan session
session_destroy();
// Redirect ke login dengan parameter
header("Location: login.php?logout=success");
exit;
}
// Check user login and role specifically for SUPER_ADMIN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
header("Location: login.php");
exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
// Redirect to appropriate dashboard based on role
if (isset($_SESSION['role'])) {
if ($_SESSION['role'] == 'penjual') {
header("Location: penjual_dashboard.php");
} elseif ($_SESSION['role'] == 'pembeli') {
header("Location: dashboard_pembeli.php");
} else {
header("Location: login.php");
}
} else {
header("Location: login.php");
}
exit;
}
// ========== SET USER STATUS TO ONLINE ==========
$user_id = $_SESSION['id_user'] ?? 0;
if ($user_id) {
$update_online_sql = "UPDATE users SET status = 'online' WHERE id_user = ?";
if ($update_online_stmt = mysqli_prepare($conn, $update_online_sql)) {
mysqli_stmt_bind_param($update_online_stmt, "i", $user_id);
mysqli_stmt_execute($update_online_stmt);
mysqli_stmt_close($update_online_stmt);
}
}
// ========== GET USER DATA ==========
$current_user = null;
$admin_count = 0;
$penjual_count = 0;
$pembeli_count = 0;
// Get current user data
$sql_user = "SELECT * FROM users WHERE id_user = ?";
if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$current_user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);
}
// Get counts for dashboard cards
if ($conn) {
// Total Admin (super_admin role)
$sql_admin_count = "SELECT COUNT(*) as count FROM users WHERE role = 'super_admin'";
$result_admin = mysqli_query($conn, $sql_admin_count);
if ($result_admin) {
$admin_count = mysqli_fetch_assoc($result_admin)['count'];
}
// Total Penjual
$sql_penjual_count = "SELECT COUNT(*) as count FROM users WHERE role = 'penjual'";
$result_penjual = mysqli_query($conn, $sql_penjual_count);
if ($result_penjual) {
$penjual_count = mysqli_fetch_assoc($result_penjual)['count'];
}
// Total Pembeli
$sql_pembeli_count = "SELECT COUNT(*) as count FROM users WHERE role = 'pembeli'";
$result_pembeli = mysqli_query($conn, $sql_pembeli_count);
if ($result_pembeli) {
$pembeli_count = mysqli_fetch_assoc($result_pembeli)['count'];
}
}
// Get all users for the table (if needed later)
$users = [];
$sql_users = "SELECT * FROM users ORDER BY role, nama";
$result_users = mysqli_query($conn, $sql_users);
if ($result_users) {
$users = mysqli_fetch_all($result_users, MYSQLI_ASSOC);
}
// Handle Add/Edit User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
$action = $_POST['action'];
$nama = mysqli_real_escape_string($conn, $_POST['nama'] ?? '');
$email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
$role = mysqli_real_escape_string($conn, $_POST['role'] ?? '');
$status = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
if ($action == 'add') {
$password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (nama, email, password, role, status) VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssss", $nama, $email, $password, $role, $status);
mysqli_stmt_execute($stmt);
header("Location: dashboard_admin.php?success=User added successfully");
exit;
} elseif ($action == 'edit' && isset($_POST['id_user'])) {
$id_user = $_POST['id_user'];
if (!empty($_POST['password'])) {
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$sql = "UPDATE users SET nama = ?, email = ?, password = ?, role = ?, status = ? WHERE id_user = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssi", $nama, $email, $password, $role, $status, $id_user);
} else {
$sql = "UPDATE users SET nama = ?, email = ?, role = ?, status = ? WHERE id_user = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssi", $nama, $email, $role, $status, $id_user);
}
mysqli_stmt_execute($stmt);
header("Location: dashboard_admin.php?success=User updated successfully");
exit;
} elseif ($action == 'delete' && isset($_POST['id_user'])) {
$id_user = $_POST['id_user'];
// Check if user is trying to delete themselves
if ($id_user == $_SESSION['id_user']) {
header("Location: dashboard_admin.php?error=Cannot delete your own account");
exit;
}
$sql = "DELETE FROM users WHERE id_user = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
header("Location: dashboard_admin.php?success=User deleted successfully");
exit;
}
}
// ========== HANDLE HELP CONTACT FORM ==========
$help_success = '';
$help_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['help_action'])) {
$help_name = mysqli_real_escape_string($conn, $_POST['help_name'] ?? '');
$help_email = mysqli_real_escape_string($conn, $_POST['help_email'] ?? '');
$help_subject = mysqli_real_escape_string($conn, $_POST['help_subject'] ?? '');
$help_message = mysqli_real_escape_string($conn, $_POST['help_message'] ?? '');
if (empty($help_name) || empty($help_email) || empty($help_subject) || empty($help_message)) {
$help_error = "Semua field harus diisi!";
} elseif (!filter_var($help_email, FILTER_VALIDATE_EMAIL)) {
$help_error = "Format email tidak valid!";
} else {
// Simpan ke database (opsional)
$sql = "INSERT INTO help_requests (user_id, name, email, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
if ($stmt = mysqli_prepare($conn, $sql)) {
mysqli_stmt_bind_param($stmt, "issss", $user_id, $help_name, $help_email, $help_subject, $help_message);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
// Kirim email ke admin (simulasi)
$help_success = "Pesan bantuan Anda telah terkirim. Tim support akan segera merespon via email.";
} else {
$help_error = "Gagal mengirim pesan. Silakan coba lagi.";
}
}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin - Pustaka-Aruna</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
--blue-light: #e3f2fd;
--blue-primary: #1976d2;
--blue-dark: #0d47a1;
--blue-accent: #42a5f5;
--blue-lighter: #64b5f6;
--blue-bg: #f0f7ff;
--blue-red: #d32f2f;
--blue-green: #388e3c;
--blue-white: #ffffff;
--blue-border: #90caf9;
--sidebar-width: 250px;
--header-height: 70px;
}
* {
margin: 0;
padding: 0;
box-sizing: border-box;
font-family: 'Poppins', sans-serif;
}
body {
background-color: var(--blue-light);
color: var(--blue-dark);
display: flex;
min-height: 100vh;
background-image: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,100" stroke="%2390caf9" stroke-width="0.5" opacity="0.3"/><path d="M100,0 L0,100" stroke="%2390caf9" stroke-width="0.5" opacity="0.3"/></svg>');
}
/* Sidebar Styles - Blue Modern Design */
.sidebar {
width: var(--sidebar-width);
background-color: var(--blue-white);
border-right: 3px solid var(--blue-border);
display: flex;
flex-direction: column;
position: fixed;
height: 100vh;
z-index: 100;
box-shadow: 5px 0 15px rgba(25, 118, 210, 0.1);
background-image: url('data:image/svg+xml;utf8,<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect width="20" height="20" fill="%23ffffff"/><path d="M0,0 L20,20" stroke="%2390caf9" stroke-width="0.3" opacity="0.2"/><path d="M20,0 L0,20" stroke="%2390caf9" stroke-width="0.3" opacity="0.2"/></svg>');
}
.logo-container {
padding: 25px 20px;
border-bottom: 2px dashed var(--blue-border);
text-align: center;
background: linear-gradient(135deg, rgba(25, 118, 210, 0.1), rgba(66, 165, 245, 0.1));
}
.logo {
font-family: 'Inter', sans-serif;
font-size: 28px;
font-weight: 600;
color: var(--blue-primary);
text-decoration: none;
display: flex;
align-items: center;
justify-content: center;
gap: 12px;
letter-spacing: 1px;
}
.logo i {
color: var(--blue-accent);
text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}
.logo-text {
display: flex;
flex-direction: column;
align-items: center;
position: relative;
}
.logo-text::after {
content: '';
position: absolute;
bottom: -8px;
width: 60%;
height: 2px;
background: linear-gradient(90deg, transparent, var(--blue-accent), transparent);
}
.logo-subtitle {
font-size: 11px;
font-weight: 400;
color: var(--blue-primary);
margin-top: 5px;
letter-spacing: 2px;
text-transform: uppercase;
opacity: 0.8;
}
.user-profile {
padding: 25px 20px;
display: flex;
flex-direction: column;
align-items: center;
border-bottom: 2px dashed var(--blue-border);
background: rgba(227, 242, 253, 0.5);
}
.avatar {
width: 80px;
height: 80px;
border-radius: 50%;
background: linear-gradient(135deg, var(--blue-primary), var(--blue-accent));
display: flex;
align-items: center;
justify-content: center;
color: var(--blue-white);
font-size: 28px;
font-weight: 600;
margin-bottom: 15px;
border: 3px solid var(--blue-accent);
box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
}
.user-name {
font-family: 'Inter', sans-serif;
font-weight: 600;
font-size: 18px;
margin-bottom: 8px;
color: var(--blue-dark);
text-align: center;
}
.user-role {
font-size: 11px;
color: var(--blue-primary);
background-color: var(--blue-light);
padding: 5px 15px;
border-radius: 20px;
border: 1px solid var(--blue-border);
letter-spacing: 1px;
text-transform: uppercase;
}
.nav-menu {
flex: 1;
padding: 25px 0;
overflow-y: auto;
}
.nav-item {
padding: 16px 25px;
display: flex;
align-items: center;
gap: 15px;
color: var(--blue-dark);
text-decoration: none;
transition: all 0.3s ease;
border-left: 4px solid transparent;
margin: 5px 15px;
border-radius: 0 8px 8px 0;
position: relative;
overflow: hidden;
}
.nav-item::before {
content: '';
position: absolute;
left: 0;
top: 0;
width: 0;
height: 100%;
background: linear-gradient(90deg, rgba(25, 118, 210, 0.1), transparent);
transition: width 0.3s ease;
}
.nav-item:hover::before {
width: 100%;
}
.nav-item:hover {
background-color: rgba(227, 242, 253, 0.7);
color: var(--blue-red);
transform: translateX(5px);
}
.nav-item.active {
background-color: var(--blue-light);
color: var(--blue-red);
border-left-color: var(--blue-accent);
box-shadow: inset 3px 0 8px rgba(25, 118, 210, 0.2);
}
.nav-item.active::before {
width: 100%;
}
.nav-item i {
width: 22px;
text-align: center;
font-size: 18px;
color: var(--blue-primary);
}
.nav-item.active i {
color: var(--blue-red);
}
.nav-text {
font-size: 15px;
font-weight: 500;
position: relative;
}
.sidebar-footer {
padding: 20px;
border-top: 2px dashed var(--blue-border);
background: rgba(25, 118, 210, 0.05);
}
.sidebar-footer .nav-item {
margin: 0;
border-radius: 8px;
}
/* Main Content Styles */
.main-content {
flex: 1;
margin-left: var(--sidebar-width);
padding: 25px;
overflow-x: hidden;
background-color: var(--blue-white);
min-height: 100vh;
border-left: 1px solid var(--blue-border);
}
.header {
display: flex;
justify-content: space-between;
align-items: center;
margin-bottom: 35px;
padding-bottom: 20px;
border-bottom: 2px solid var(--blue-border);
position: relative;
}
.header::after {
content: '';
position: absolute;
bottom: -2px;
left: 0;
width: 100px;
height: 3px;
background: var(--blue-accent);
}
.page-title {
font-family: 'Inter', sans-serif;
font-size: 32px;
font-weight: 600;
color: var(--blue-primary);
letter-spacing: 0.5px;
text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}
.search-container {
position: relative;
width: 300px;
}
.search-input {
width: 100%;
padding: 12px 15px 12px 45px;
border-radius: 8px;
border: 2px solid var(--blue-border);
font-size: 14px;
transition: all 0.3s ease;
background-color: var(--blue-light);
color: var(--blue-dark);
font-family: 'Poppins', sans-serif;
}
.search-input::placeholder {
color: rgba(25, 118, 210, 0.6);
font-style: italic;
}
.search-input:focus {
outline: none;
border-color: var(--blue-accent);
box-shadow: 0 0 0 3px rgba(66, 165, 245, 0.2);
}
.search-icon {
position: absolute;
left: 15px;
top: 50%;
transform: translateY(-50%);
color: var(--blue-primary);
}
/* Alert Messages */
.alert {
padding: 18px;
border-radius: 8px;
margin-bottom: 25px;
display: flex;
justify-content: space-between;
align-items: center;
border: 2px solid transparent;
position: relative;
overflow: hidden;
}
.alert::before {
content: '';
position: absolute;
left: 0;
top: 0;
width: 8px;
height: 100%;
background-color: currentColor;
opacity: 0.3;
}
.alert-success {
background-color: rgba(56, 142, 60, 0.1);
color: var(--blue-green);
border-color: var(--blue-green);
}
.alert-error {
background-color: rgba(211, 47, 47, 0.1);
color: var(--blue-red);
border-color: var(--blue-red);
}
.close-alert {
background: none;
border: none;
font-size: 22px;
cursor: pointer;
color: inherit;
opacity: 0.7;
transition: opacity 0.3s ease;
width: 30px;
height: 30px;
display: flex;
align-items: center;
justify-content: center;
border-radius: 50%;
}
.close-alert:hover {
opacity: 1;
background-color: rgba(0, 0, 0, 0.1);
}
/* Dashboard Cards */
.dashboard-cards {
display: grid;
grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
gap: 25px;
margin-bottom: 35px;
}
.card {
background: linear-gradient(135deg, var(--blue-white), var(--blue-light));
border-radius: 12px;
padding: 25px;
box-shadow: 0 6px 20px rgba(25, 118, 210, 0.1);
transition: all 0.4s ease;
border: 2px solid var(--blue-border);
position: relative;
overflow: hidden;
}
.card::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 4px;
background: linear-gradient(90deg, var(--blue-accent), var(--blue-primary));
}
.card:hover {
transform: translateY(-8px);
box-shadow: 0 12px 30px rgba(25, 118, 210, 0.2);
border-color: var(--blue-accent);
}
.card-icon {
width: 60px;
height: 60px;
border-radius: 12px;
display: flex;
align-items: center;
justify-content: center;
font-size: 24px;
margin-bottom: 20px;
border: 2px solid;
position: relative;
background-color: var(--blue-white);
}
.card-icon.admin {
border-color: var(--blue-red);
color: var(--blue-red);
background: linear-gradient(135deg, rgba(211, 47, 47, 0.1), transparent);
}
.card-icon.pembeli {
border-color: var(--blue-green);
color: var(--blue-green);
background: linear-gradient(135deg, rgba(56, 142, 60, 0.1), transparent);
}
.card-icon.penjual {
border-color: var(--blue-primary);
color: var(--blue-primary);
background: linear-gradient(135deg, rgba(25, 118, 210, 0.1), transparent);
}
.card-title {
font-size: 14px;
color: var(--blue-primary);
margin-bottom: 8px;
text-transform: uppercase;
letter-spacing: 1px;
font-weight: 500;
}
.card-value {
font-family: 'Inter', sans-serif;
font-size: 36px;
font-weight: 600;
color: var(--blue-dark);
margin-bottom: 15px;
}
.card-change {
font-size: 13px;
color: var(--blue-accent);
display: flex;
align-items: center;
gap: 8px;
}
/* Hero Section */
.hero-section {
background: linear-gradient(135deg, #1976d2e6, rgba(13, 71, 161, 0.9));
border-radius: 15px;
padding: 35px;
color: var(--blue-white);
margin-bottom: 35px;
display: flex;
justify-content: space-between;
align-items: center;
position: relative;
overflow: hidden;
border: 3px solid var(--blue-accent);
}
.hero-section::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
bottom: 0;
background-image: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,100" stroke="%2342a5f5" stroke-width="0.5" opacity="0.2"/><path d="M100,0 L0,100" stroke="%2342a5f5" stroke-width="0.5" opacity="0.2"/></svg>');
opacity: 0.3;
}
.hero-content h1 {
font-family: 'Inter', sans-serif;
font-size: 36px;
margin-bottom: 15px;
text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}
.hero-content p {
font-size: 18px;
opacity: 0.9;
margin-bottom: 25px;
max-width: 500px;
line-height: 1.6;
}
.hero-buttons {
display: flex;
gap: 20px;
}
.btn {
padding: 14px 28px;
border-radius: 8px;
font-weight: 500;
cursor: pointer;
border: none;
font-size: 15px;
transition: all 0.3s ease;
display: inline-flex;
align-items: center;
gap: 10px;
text-decoration: none;
position: relative;
overflow: hidden;
font-family: 'Poppins', sans-serif;
}
.btn::before {
content: '';
position: absolute;
top: 0;
left: -100%;
width: 100%;
height: 100%;
background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
transition: left 0.5s ease;
}
.btn:hover::before {
left: 100%;
}
.btn-primary {
background: linear-gradient(135deg, var(--blue-accent), var(--blue-primary));
color: var(--blue-white);
border: 2px solid var(--blue-accent);
}
.btn-primary:hover {
background: linear-gradient(135deg, var(--blue-primary), var(--blue-dark));
transform: translateY(-3px);
box-shadow: 0 8px 20px rgba(66, 165, 245, 0.4);
}
.btn-outline {
background: transparent;
color: var(--blue-white);
border: 2px solid var(--blue-accent);
}
.btn-outline:hover {
background: rgba(66, 165, 245, 0.2);
transform: translateY(-3px);
box-shadow: 0 8px 20px rgba(66, 165, 245, 0.3);
}
.hero-section i[class*="fa-book-open"] {
font-size: 120px;
opacity: 0.8;
color: var(--blue-accent);
filter: drop-shadow(2px 4px 6px rgba(0, 0, 0, 0.3));
animation: float 3s ease-in-out infinite;
}
@keyframes float {
0%, 100% { transform: translateY(0px); }
50% { transform: translateY(-10px); }
}
/* Quick Stats */
.table-container {
background: linear-gradient(135deg, var(--blue-white), var(--blue-light));
border-radius: 12px;
padding: 25px;
box-shadow: 0 6px 20px rgba(25, 118, 210, 0.1);
border: 2px solid var(--blue-border);
position: relative;
overflow: hidden;
margin-bottom: 35px;
}
.table-container::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 4px;
background: linear-gradient(90deg, var(--blue-green), var(--blue-accent));
}
.table-container h3 {
font-family: 'Inter', sans-serif;
font-size: 24px;
margin-bottom: 25px;
color: var(--blue-primary);
padding-bottom: 10px;
border-bottom: 2px solid var(--blue-border);
}
.stats-grid {
display: grid;
grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
gap: 20px;
}
.stat-box {
background: var(--blue-light);
padding: 20px;
border-radius: 10px;
border: 1px solid var(--blue-border);
transition: all 0.3s ease;
position: relative;
overflow: hidden;
}
.stat-box:hover {
transform: translateY(-5px);
box-shadow: 0 8px 20px rgba(25, 118, 210, 0.15);
border-color: var(--blue-accent);
}
.stat-box::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 4px;
background: var(--blue-accent);
transform: scaleX(0);
transition: transform 0.3s ease;
}
.stat-box:hover::before {
transform: scaleX(1);
}
.stat-label {
font-size: 13px;
color: var(--blue-primary);
margin-bottom: 8px;
text-transform: uppercase;
letter-spacing: 1px;
font-weight: 500;
}
.stat-value {
font-family: 'Inter', sans-serif;
font-size: 32px;
font-weight: 600;
color: var(--blue-dark);
}
/* ===== HELP SECTION STYLES ===== */
.help-section {
background: linear-gradient(135deg, var(--blue-white), var(--blue-light));
border-radius: 12px;
padding: 30px;
box-shadow: 0 6px 20px rgba(25, 118, 210, 0.1);
border: 2px solid var(--blue-border);
position: relative;
overflow: hidden;
margin-top: 35px;
}
.help-section::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 4px;
background: linear-gradient(90deg, var(--blue-accent), var(--blue-primary));
}
.help-header {
display: flex;
align-items: center;
gap: 20px;
margin-bottom: 30px;
padding-bottom: 15px;
border-bottom: 2px dashed var(--blue-border);
}
.help-icon {
width: 70px;
height: 70px;
border-radius: 50%;
background: linear-gradient(135deg, var(--blue-accent), var(--blue-primary));
display: flex;
align-items: center;
justify-content: center;
color: var(--blue-white);
font-size: 30px;
box-shadow: 0 4px 15px rgba(66, 165, 245, 0.3);
}
.help-header h2 {
font-family: 'Inter', sans-serif;
font-size: 28px;
color: var(--blue-primary);
}
.help-header p {
color: var(--blue-dark);
font-size: 14px;
opacity: 0.8;
}
.help-grid {
display: grid;
grid-template-columns: 1.5fr 1fr;
gap: 30px;
}
.help-faq h3, .help-contact h3 {
font-family: 'Inter', sans-serif;
font-size: 22px;
color: var(--blue-primary);
margin-bottom: 20px;
padding-bottom: 8px;
border-bottom: 2px solid var(--blue-border);
}
.faq-item {
background: var(--blue-light);
border-radius: 10px;
margin-bottom: 15px;
border: 1px solid var(--blue-border);
overflow: hidden;
transition: all 0.3s ease;
}
.faq-item:hover {
border-color: var(--blue-accent);
box-shadow: 0 4px 12px rgba(66, 165, 245, 0.2);
}
.faq-question {
padding: 18px 20px;
display: flex;
justify-content: space-between;
align-items: center;
cursor: pointer;
background: linear-gradient(90deg, rgba(25, 118, 210, 0.05), transparent);
font-weight: 500;
color: var(--blue-primary);
}
.faq-question i {
color: var(--blue-accent);
transition: transform 0.3s ease;
}
.faq-question.active i {
transform: rotate(180deg);
}
.faq-answer {
padding: 0 20px;
max-height: 0;
overflow: hidden;
transition: max-height 0.3s ease, padding 0.3s ease;
color: var(--blue-dark);
font-size: 14px;
line-height: 1.8;
border-top: 1px solid transparent;
}
.faq-answer.show {
padding: 20px;
max-height: 200px;
border-top-color: var(--blue-border);
}
.faq-answer ul {
padding-left: 20px;
margin: 10px 0;
}
.faq-answer li {
margin-bottom: 8px;
}
.faq-answer i {
margin-right: 8px;
color: var(--blue-accent);
}
.contact-form {
background: var(--blue-light);
border-radius: 10px;
padding: 25px;
border: 1px solid var(--blue-border);
}
.form-group {
margin-bottom: 20px;
}
.form-group label {
display: block;
margin-bottom: 8px;
color: var(--blue-primary);
font-weight: 500;
font-size: 14px;
}
.form-group label i {
margin-right: 8px;
color: var(--blue-accent);
}
.form-control {
width: 100%;
padding: 14px 18px;
border: 2px solid var(--blue-border);
border-radius: 8px;
font-size: 14px;
transition: all 0.3s ease;
background-color: var(--blue-white);
color: var(--blue-dark);
font-family: 'Poppins', sans-serif;
}
.form-control:focus {
outline: none;
border-color: var(--blue-accent);
box-shadow: 0 0 0 4px rgba(66, 165, 245, 0.1);
background-color: white;
}
textarea.form-control {
resize: vertical;
min-height: 120px;
}
.btn-help {
background: linear-gradient(135deg, var(--blue-green), var(--blue-primary));
color: var(--blue-white);
border: none;
border-radius: 8px;
padding: 16px 25px;
font-weight: 600;
cursor: pointer;
transition: all 0.3s ease;
display: inline-flex;
align-items: center;
gap: 10px;
font-size: 16px;
width: 100%;
justify-content: center;
border: 2px solid transparent;
}
.btn-help:hover {
background: linear-gradient(135deg, var(--blue-primary), var(--blue-green));
transform: translateY(-3px);
box-shadow: 0 8px 20px rgba(56, 142, 60, 0.4);
border-color: var(--blue-accent);
}
.contact-info {
margin-top: 25px;
padding: 20px;
background: linear-gradient(135deg, rgba(66, 165, 245, 0.1), rgba(25, 118, 210, 0.1));
border-radius: 8px;
border: 1px dashed var(--blue-accent);
}
.info-item {
display: flex;
align-items: center;
gap: 15px;
margin-bottom: 15px;
color: var(--blue-dark);
}
.info-item i {
width: 35px;
height: 35px;
border-radius: 50%;
background: var(--blue-white);
border: 2px solid var(--blue-accent);
display: flex;
align-items: center;
justify-content: center;
color: var(--blue-primary);
}
.info-item span {
font-size: 14px;
}
.help-support-hours {
display: flex;
align-items: center;
gap: 15px;
margin-top: 15px;
padding-top: 15px;
border-top: 1px solid var(--blue-border);
color: var(--blue-dark);
font-size: 13px;
}
.help-support-hours i {
color: var(--blue-accent);
}
/* Responsive */
@media (max-width: 1024px) {
.sidebar {
width: 70px;
}
.logo-text span:not(.logo-subtitle),
.nav-text,
.user-name,
.user-role {
display: none;
}
.logo {
justify-content: center;
}
.main-content {
margin-left: 70px;
}
.user-profile {
padding: 15px 5px;
}
.avatar {
width: 50px;
height: 50px;
font-size: 20px;
}
.page-title {
font-size: 28px;
}
.help-grid {
grid-template-columns: 1fr;
}
}
@media (max-width: 768px) {
.dashboard-cards {
grid-template-columns: 1fr;
}
.header {
flex-direction: column;
align-items: flex-start;
gap: 20px;
}
.search-container {
width: 100%;
}
.hero-section {
flex-direction: column;
text-align: center;
gap: 25px;
}
.hero-buttons {
flex-direction: column;
width: 100%;
}
.btn {
width: 100%;
justify-content: center;
}
.stats-grid {
grid-template-columns: 1fr;
}
.hero-content h1 {
font-size: 28px;
}
.help-header {
flex-direction: column;
text-align: center;
}
}
/* Scrollbar Styling */
::-webkit-scrollbar {
width: 8px;
}
::-webkit-scrollbar-track {
background: var(--blue-light);
}
::-webkit-scrollbar-thumb {
background: var(--blue-primary);
border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
background: var(--blue-accent);
}
/* Modal Help */
.help-modal {
display: none;
position: fixed;
z-index: 1000;
left: 0;
top: 0;
width: 100%;
height: 100%;
background-color: rgba(0, 0, 0, 0.6);
backdrop-filter: blur(5px);
}
.help-modal-content {
background: linear-gradient(135deg, var(--blue-white), var(--blue-light));
margin: 5% auto;
padding: 30px;
border: 3px solid var(--blue-accent);
border-radius: 20px;
width: 90%;
max-width: 600px;
max-height: 80vh;
overflow-y: auto;
position: relative;
box-shadow: 0 20px 60px rgba(25, 118, 210, 0.3);
}
.help-modal-close {
position: absolute;
right: 25px;
top: 15px;
font-size: 30px;
font-weight: bold;
color: var(--blue-primary);
cursor: pointer;
transition: all 0.3s ease;
width: 40px;
height: 40px;
display: flex;
align-items: center;
justify-content: center;
border-radius: 50%;
}
.help-modal-close:hover {
color: var(--blue-red);
background: rgba(211, 47, 47, 0.1);
transform: rotate(90deg);
}
.help-modal-title {
font-family: 'Inter', sans-serif;
font-size: 28px;
color: var(--blue-primary);
margin-bottom: 25px;
padding-bottom: 10px;
border-bottom: 2px dashed var(--blue-accent);
}
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
<div class="logo-container">
<a href="dashboard_admin.php" class="logo">
<i class="fas fa-book-open"></i>
<div class="logo-text">
<span>Pustaka Aruna</span>
<span class="logo-subtitle">Admin Panel</span>
</div>
</a>
</div>
<!-- user-profile removed -->
<div class="nav-menu">
<a href="dashboard_admin.php" class="nav-item active">
<i class="fas fa-tachometer-alt"></i>
<span class="nav-text">Dashboard</span>
<a href="pembeli_management.php" class="nav-item">
<i class="fas fa-users"></i>
<span class="nav-text">Pembeli</span>
</a>
<a href="penjual_management.php" class="nav-item">
<i class="fas fa-user-tie"></i>
<span class="nav-text">Account Penjual</span>
</a>
</div>
<div class="sidebar-footer">
<a href="#" class="nav-item" id="help-btn">
<i class="fas fa-question-circle"></i>
<span class="nav-text">Help & Support</span>
</a>
<a href="#" class="nav-item" id="signout-btn">
<i class="fas fa-sign-out-alt"></i>
<span class="nav-text">Sign Out</span>
</a>
</div>
</div>
<!-- Main Content -->
<div class="main-content">
<div class="header">
<div class="page-title">Dashboard Modern</div>
<div class="search-container">
<i class="fas fa-search search-icon"></i>
<input type="text" class="search-input" placeholder="Cari di sini...">
</div>
</div>
<!-- Alert Messages -->
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
<span><?php echo htmlspecialchars($_GET['success']); ?></span>
<button class="close-alert">&times;</button>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-error">
<span><?php echo htmlspecialchars($_GET['error']); ?></span>
<button class="close-alert">&times;</button>
</div>
<?php endif; ?>
<?php if ($help_success): ?>
<div class="alert alert-success">
<span><?php echo $help_success; ?></span>
<button class="close-alert">&times;</button>
</div>
<?php endif; ?>
<?php if ($help_error): ?>
<div class="alert alert-error">
<span><?php echo $help_error; ?></span>
<button class="close-alert">&times;</button>
</div>
<?php endif; ?>
<!-- Dashboard Cards -->
<div class="dashboard-cards">
<div class="card">
<div class="card-icon admin">
<i class="fas fa-user-shield"></i>
</div>
<div class="card-title">Total Admin</div>
<div class="card-value"><?php echo $admin_count; ?></div>
<div class="card-change">
<i class="fas fa-feather-alt"></i> Administrator Sistem
</div>
</div>
<div class="card">
<div class="card-icon penjual">
<i class="fas fa-user-tie"></i>
</div>
<div class="card-title">Account Penjual</div>
<div class="card-value"><?php echo $penjual_count; ?></div>
<div class="card-change">
<i class="fas fa-feather-alt"></i> Penjual Terdaftar
</div>
</div>
<div class="card">
<div class="card-icon pembeli">
<i class="fas fa-users"></i>
</div>
<div class="card-title">Account Pembeli</div>
<div class="card-value"><?php echo $pembeli_count; ?></div>
<div class="card-change">
<i class="fas fa-feather-alt"></i> Pembeli Aktif
</div>
</div>
</div>
<!-- Hero Section -->
<div class="hero-section">
<div class="hero-content">
<h1>Pustaka-Aruna</h1>
<p>Menjaga tradisi literasi dengan sentuhan modern. Selalu di depan melayani kebutuhan literasi Anda.</p>
<div class="hero-buttons">
<a href="admin_management.php" class="btn btn-primary">
<i class="fas fa-user-shield"></i> Kelola Admin
</a>
<a href="pembeli_management.php" class="btn btn-outline">
<i class="fas fa-users"></i> Lihat Pembeli
</a>
</div>
</div>
<div>
<i class="fas fa-book-open"></i>
</div>
</div>
<!-- Quick Stats -->
<div class="table-container">
<h3>Statistik Sistem</h3>
<div class="stats-grid">
<div class="stat-box">
<div class="stat-label">Total User</div>
<div class="stat-value">
<?php echo $admin_count + $penjual_count + $pembeli_count; ?>
</div>
</div>
<div class="stat-box">
<div class="stat-label">User Online</div>
<div class="stat-value">
<?php
if ($conn) {
$sql_online = "SELECT COUNT(*) as count FROM users WHERE status = 'online'";
$result_online = mysqli_query($conn, $sql_online);
if ($result_online) {
$online_count = mysqli_fetch_assoc($result_online)['count'];
echo $online_count;
} else {
echo '0';
}
} else {
echo '0';
}
?>
</div>
</div>
<div class="stat-box">
<div class="stat-label">Hari Ini</div>
<div class="stat-value">
<?php
if ($conn) {
$sql_today = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()";
$result_today = mysqli_query($conn, $sql_today);
if ($result_today) {
$today_count = mysqli_fetch_assoc($result_today)['count'];
echo $today_count;
} else {
echo '0';
}
} else {
echo '0';
}
?>
</div>
</div>
</div>
</div>
<!-- Help Section -->
<div class="help-section" id="helpSection">
<div class="help-header">
<div class="help-icon">
<i class="fas fa-question-circle"></i>
</div>
<div>
<h2>Pusat Bantuan & Support</h2>
<p>Temukan jawaban untuk pertanyaan umum atau hubungi tim support kami</p>
</div>
</div>
<div class="help-grid">
<!-- FAQ Section -->
<div class="help-faq">
<h3><i class="fas fa-question-circle me-2"></i> Pertanyaan Umum</h3>
<div class="faq-item">
<div class="faq-question" onclick="toggleFAQ(this)">
<span>Bagaimana cara menambahkan admin baru?</span>
<i class="fas fa-chevron-down"></i>
</div>
<div class="faq-answer">
Untuk menambahkan admin baru, Anda dapat:
<ul>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Klik menu "Account Penjual" di sidebar</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Klik tombol "Tambah Penjual"</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Isi form data yang diperlukan</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Pilih role "Penjual" atau "Admin"</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Klik "Simpan" untuk menyelesaikan</li>
</ul>
</div>
</div>
<div class="faq-item">
<div class="faq-question" onclick="toggleFAQ(this)">
<span>Bagaimana cara mengelola kategori buku?</span>
<i class="fas fa-chevron-down"></i>
</div>
<div class="faq-answer">
Untuk mengelola kategori buku:
<ul>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Buka menu "Kategori" di sidebar</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Klik "Tambah Kategori" untuk menambah</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Gunakan tombol Edit untuk mengubah</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Gunakan tombol Hapus untuk menghapus</li>
</ul>
</div>
</div>
<div class="faq-item">
<div class="faq-question" onclick="toggleFAQ(this)">
<span>Bagaimana cara melihat statistik pengguna?</span>
<i class="fas fa-chevron-down"></i>
</div>
<div class="faq-answer">
Statistik pengguna dapat dilihat langsung di dashboard:
<ul>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Card "Total Admin" untuk jumlah admin</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Card "Account Penjual" untuk jumlah penjual</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Card "Account Pembeli" untuk jumlah pembeli</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Statistik online di bagian bawah</li>
</ul>
</div>
</div>
<div class="faq-item">
<div class="faq-question" onclick="toggleFAQ(this)">
<span>Apa yang harus dilakukan jika lupa password?</span>
<i class="fas fa-chevron-down"></i>
</div>
<div class="faq-answer">
Jika lupa password, Anda dapat:
<ul>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Klik "Lupa Password" di halaman login</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Masukkan email terdaftar</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Ikuti instruksi reset via email</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Atau hubungi super admin lain</li>
</ul>
</div>
</div>
<div class="faq-item">
<div class="faq-question" onclick="toggleFAQ(this)">
<span>Bagaimana cara menghubungi tim support?</span>
<i class="fas fa-chevron-down"></i>
</div>
<div class="faq-answer">
Anda dapat menghubungi tim support melalui:
<ul>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Form kontak di sebelah kanan</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Email: support@pustakaaruna.com</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> Telepon: (021) 1234-5678</li>
<li><i class="fas fa-check-circle" style="color: var(--blue-green);"></i> WhatsApp: 0812-3456-7890</li>
</ul>
</div>
</div>
</div>
<!-- Contact Form -->
<div class="help-contact">
<h3><i class="fas fa-envelope me-2"></i> Hubungi Kami</h3>
<form method="POST" action="" class="contact-form" id="helpContactForm">
<input type="hidden" name="help_action" value="send">
<div class="form-group">
<label for="help_name">
<i class="fas fa-user"></i> Nama Lengkap
</label>
<input type="text" class="form-control" id="help_name" name="help_name"
value="<?php echo isset($current_user['nama']) ? htmlspecialchars($current_user['nama']) : ''; ?>"
placeholder="Masukkan nama Anda" required>
</div>
<div class="form-group">
<label for="help_email">
<i class="fas fa-envelope"></i> Email
</label>
<input type="email" class="form-control" id="help_email" name="help_email"
value="<?php echo isset($current_user['email']) ? htmlspecialchars($current_user['email']) : ''; ?>"
placeholder="Masukkan email Anda" required>
</div>
<div class="form-group">
<label for="help_subject">
<i class="fas fa-tag"></i> Subjek
</label>
<input type="text" class="form-control" id="help_subject" name="help_subject"
placeholder="Contoh: Masalah login, Bantuan teknis, dll" required>
</div>
<div class="form-group">
<label for="help_message">
<i class="fas fa-comment"></i> Pesan
</label>
<textarea class="form-control" id="help_message" name="help_message"
placeholder="Tulis pesan Anda di sini..." required></textarea>
</div>
<button type="submit" class="btn-help">
<i class="fas fa-paper-plane"></i> Kirim Pesan
</button>
</form>
<div class="contact-info">
<div class="info-item">
<i class="fas fa-phone-alt"></i>
<span>(021) 1234-5678</span>
</div>
<div class="info-item">
<i class="fas fa-envelope"></i>
<span>support@pustakaaruna.com</span>
</div>
<div class="info-item">
<i class="fas fa-clock"></i>
<span>Senin - Jumat, 09:00 - 17:00</span>
</div>
<div class="help-support-hours">
<i class="fas fa-feather-alt"></i>
<span>Tim support siap membantu Anda dalam 1x24 jam</span>
</div>
</div>
</div>
</div>
</div>
</div>
<!-- Help Modal (untuk tampilan popup) -->
<div id="helpModal" class="help-modal">
<div class="help-modal-content">
<span class="help-modal-close" onclick="closeHelpModal()">&times;</span>
<h2 class="help-modal-title">
<i class="fas fa-question-circle me-2"></i> Pusat Bantuan
</h2>
<div style="max-height: 60vh; overflow-y: auto; padding-right: 10px;">
<!-- Copy konten help section di sini untuk modal -->
<div class="help-faq">
<h3 style="font-size: 20px; margin-bottom: 15px;">Pertanyaan Umum</h3>
<div class="faq-item">
<div class="faq-question" onclick="toggleFAQ(this)">
<span>Bagaimana cara menambahkan admin baru?</span>
<i class="fas fa-chevron-down"></i>
</div>
<div class="faq-answer">
Untuk menambahkan admin baru, Anda dapat:
<ul>
<li>Klik menu "Account Penjual" di sidebar</li>
<li>Klik tombol "Tambah Penjual"</li>
<li>Isi form data yang diperlukan</li>
<li>Pilih role "Penjual" atau "Admin"</li>
<li>Klik "Simpan" untuk menyelesaikan</li>
</ul>
</div>
</div>
<div class="faq-item">
<div class="faq-question" onclick="toggleFAQ(this)">
<span>Bagaimana cara mengelola kategori buku?</span>
<i class="fas fa-chevron-down"></i>
</div>
<div class="faq-answer">
Untuk mengelola kategori buku:
<ul>
<li>Buka menu "Kategori" di sidebar</li>
<li>Klik "Tambah Kategori" untuk menambah</li>
<li>Gunakan tombol Edit untuk mengubah</li>
<li>Gunakan tombol Hapus untuk menghapus</li>
</ul>
</div>
</div>
<div class="faq-item">
<div class="faq-question" onclick="toggleFAQ(this)">
<span>Bagaimana cara menghubungi tim support?</span>
<i class="fas fa-chevron-down"></i>
</div>
<div class="faq-answer">
Hubungi kami di:
<br>Email: support@pustakaaruna.com
<br>Telepon: (021) 1234-5678
<br>WhatsApp: 0812-3456-7890
</div>
</div>
</div>
</div>
</div>
</div>
<script>
// Event listener untuk sign out button
document.getElementById('signout-btn').addEventListener('click', function(e) {
e.preventDefault();
if (confirm('Anda yakin ingin keluar?')) {
window.location.href = 'dashboard_admin.php?logout=true';
}
});
// Event listener untuk help button
document.getElementById('help-btn').addEventListener('click', function(e) {
e.preventDefault();
// Scroll ke help section
document.getElementById('helpSection').scrollIntoView({
behavior: 'smooth'
});
// Atau tampilkan modal (opsional)
// document.getElementById('helpModal').style.display = 'block';
});
// Fungsi untuk toggle FAQ
function toggleFAQ(element) {
const answer = element.nextElementSibling;
const icon = element.querySelector('i');
// Toggle class active pada question
element.classList.toggle('active');
// Toggle class show pada answer
answer.classList.toggle('show');
// Rotate icon
if (answer.classList.contains('show')) {
icon.style.transform = 'rotate(180deg)';
} else {
icon.style.transform = 'rotate(0deg)';
}
}
// Fungsi untuk menutup modal
function closeHelpModal() {
document.getElementById('helpModal').style.display = 'none';
}
// Event listener untuk closing alerts
document.querySelectorAll('.close-alert').forEach(btn => {
btn.addEventListener('click', function() {
this.parentElement.style.display = 'none';
});
});
// Search functionality
const searchInput = document.querySelector('.search-input');
if (searchInput) {
searchInput.addEventListener('keypress', function(e) {
if (e.key === 'Enter') {
alert('Fitur pencarian dalam pengembangan...');
this.value = '';
}
});
}
// Form submission
const helpForm = document.getElementById('helpContactForm');
if (helpForm) {
helpForm.addEventListener('submit', function(e) {
const name = document.getElementById('help_name').value.trim();
const email = document.getElementById('help_email').value.trim();
const subject = document.getElementById('help_subject').value.trim();
const message = document.getElementById('help_message').value.trim();
if (!name || !email || !subject || !message) {
e.preventDefault();
alert('Semua field harus diisi!');
return false;
}
if (!email.includes('@') || !email.includes('.')) {
e.preventDefault();
alert('Format email tidak valid!');
return false;
}
});
}
// Add some modern effects
document.addEventListener('DOMContentLoaded', function() {
// Animate cards on load
const cards = document.querySelectorAll('.card');
cards.forEach((card, index) => {
card.style.opacity = '0';
card.style.transform = 'translateY(20px)';
setTimeout(() => {
card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
card.style.opacity = '1';
card.style.transform = 'translateY(0)';
}, index * 100);
});
// Animate FAQ items
const faqItems = document.querySelectorAll('.faq-item');
faqItems.forEach((item, index) => {
item.style.opacity = '0';
item.style.transform = 'translateX(-20px)';
setTimeout(() => {
item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
item.style.opacity = '1';
item.style.transform = 'translateX(0)';
}, 300 + (index * 100));
});
// ===== UPDATE STATUS ONLINE/OFFLINE =====
function updateUserActivity() {
fetch('api_update_activity.php', {
method: 'POST',
headers: {
'Content-Type': 'application/json',
}
}).catch(err => {
console.log('Activity update failed (silent)');
});
}
updateUserActivity(); // Jalankan saat halaman load
setInterval(updateUserActivity, 5 * 60 * 1000); // Setiap 5 menit
document.addEventListener('click', updateUserActivity);
document.addEventListener('keypress', updateUserActivity);
// Buka FAQ pertama secara default
const firstFAQ = document.querySelector('.faq-item .faq-question');
if (firstFAQ) {
setTimeout(() => {
firstFAQ.click();
}, 500);
}
});
// Modal click outside to close
window.onclick = function(event) {
const modal = document.getElementById('helpModal');
if (event.target == modal) {
modal.style.display = 'none';
}
}
</script>
</body>
</html>
<?php
// Tutup koneksi database jika masih terbuka
if (isset($conn)) {
mysqli_close($conn);
}
?>