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
// Set charset untuk menghindari masalah karakter
mysqli_set_charset($conn, "utf8");
// ========== LOGOUT HANDLING DI AWAL ==========
if (isset($_GET['logout'])) {
if (isset($_SESSION["id_user"])) {
$user_id = $_SESSION["id_user"];
$update_sql = "UPDATE users SET status = 'offline' WHERE id_user = ?";
if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
mysqli_stmt_bind_param($update_stmt, "i", $user_id);
mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);
}
}
$_SESSION = array();
session_destroy();
header("Location: login.php?logout=success");
exit;
}
// Check user login and role specifically for SUPER_ADMIN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
header("Location: login.php");
exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
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
// ========== GET USER DATA ==========
$user_id = $_SESSION['id_user'] ?? 0;
$current_user = null;
// Get current user data
$sql_user = "SELECT * FROM users WHERE id_user = ?";
if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$current_user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);
}
// Get only pembeli users
$pembelis = [];
$sql_pembeli = "SELECT * FROM users WHERE role = 'pembeli' ORDER BY nama";
$result_pembeli = mysqli_query($conn, $sql_pembeli);
if ($result_pembeli) {
$pembelis = mysqli_fetch_all($result_pembeli, MYSQLI_ASSOC);
}
// Handle Add/Edit Pembeli
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
if (isset($_POST['action'])) {
$action = $_POST['action'];
$role = 'pembeli'; // Always set to pembeli
$status = 'offline'; // Status otomatis offline saat pembeli baru, berubah otomatis saat login
// Only collect and validate form fields for add/edit actions
if ($action == 'add_pembeli' || $action == 'edit_pembeli') {
$nama = mysqli_real_escape_string($conn, $_POST['nama'] ?? '');
$email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
$nik = mysqli_real_escape_string($conn, $_POST['nik'] ?? '');
$alamat_pembeli = mysqli_real_escape_string($conn, $_POST['alamat_pembeli'] ?? '');
// Validasi NIK untuk pembeli
if (empty($nik)) {
header("Location: pembeli_management.php?error=NIK wajib diisi untuk pembeli");
exit;
}
// Validasi format NIK (16 digit angka)
if (!preg_match('/^\d{16}$/', $nik)) {
header("Location: pembeli_management.php?error=NIK harus 16 digit angka");
exit;
}
}
if ($action == 'add_pembeli') {
if (!isset($_POST['password']) || empty($_POST['password'])) {
header("Location: pembeli_management.php?error=Password is required");
exit;
}
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$sql = "INSERT INTO users (nama, email, nik, alamat_pembeli, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssss", $nama, $email, $nik, $alamat_pembeli, $password, $role, $status);
try {
mysqli_stmt_execute($stmt);
} catch (mysqli_sql_exception $e) {
if ($e->getCode() == 1062) { // Duplicate entry
if (strpos($e->getMessage(), 'email') !== false) {
header("Location: pembeli_management.php?error=Email sudah terdaftar");
} elseif (strpos($e->getMessage(), 'nik') !== false) {
header("Location: pembeli_management.php?error=NIK sudah terdaftar");
} else {
header("Location: pembeli_management.php?error=" . urlencode($e->getMessage()));
}
exit;
} else {
header("Location: pembeli_management.php?error=" . urlencode($e->getMessage()));
exit;
}
}
header("Location: pembeli_management.php?success=Pembeli berhasil ditambahkan");
exit;
} elseif ($action == 'edit_pembeli' && isset($_POST['id_user'])) {
$id_user = $_POST['id_user'];
// Validasi NIK untuk edit
if (empty($nik)) {
header("Location: pembeli_management.php?error=NIK wajib diisi untuk pembeli");
exit;
}
// Validasi format NIK (16 digit angka)
if (!preg_match('/^\d{16}$/', $nik)) {
header("Location: pembeli_management.php?error=NIK harus 16 digit angka");
exit;
}
if (!empty($_POST['password'])) {
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$sql = "UPDATE users SET nama = ?, email = ?, nik = ?, alamat_pembeli = ?, password = ? WHERE id_user = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssi", $nama, $email, $nik, $alamat_pembeli, $password, $id_user);
} else {
$sql = "UPDATE users SET nama = ?, email = ?, nik = ?, alamat_pembeli = ? WHERE id_user = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssi", $nama, $email, $nik, $alamat_pembeli, $id_user);
}
try {
mysqli_stmt_execute($stmt);
} catch (mysqli_sql_exception $e) {
if ($e->getCode() == 1062) { // Duplicate entry
if (strpos($e->getMessage(), 'email') !== false) {
header("Location: pembeli_management.php?error=Email sudah terdaftar");
} elseif (strpos($e->getMessage(), 'nik') !== false) {
header("Location: pembeli_management.php?error=NIK sudah terdaftar");
} else {
header("Location: pembeli_management.php?error=" . urlencode($e->getMessage()));
}
exit;
} else {
header("Location: pembeli_management.php?error=" . urlencode($e->getMessage()));
exit;
}
}
header("Location: pembeli_management.php?success=Pembeli berhasil diperbarui");
exit;
} elseif ($action == 'delete_pembeli' && isset($_POST['id_user'])) {
$id_user = $_POST['id_user'];
$sql = "DELETE FROM users WHERE id_user = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
header("Location: pembeli_management.php?success=Pembeli berhasil dihapus");
exit;
}
}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Management Pembeli - Pustaka Aruna</title>
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
/* Cards Grid */
.cards-container {
display: grid;
grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
gap: 20px;
margin-bottom: 30px;
}
.pembeli-card {
background: linear-gradient(135deg, var(--blue-white), var(--blue-light));
border-radius: 12px;
padding: 0;
box-shadow: 0 6px 20px rgba(25, 118, 210, 0.1);
border: 2px solid var(--blue-border);
position: relative;
overflow: hidden;
transition: all 0.3s ease;
display: flex;
flex-direction: column;
}
.pembeli-card::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 4px;
background: linear-gradient(90deg, var(--blue-accent), var(--blue-primary));
}
.pembeli-card:hover {
transform: translateY(-5px);
box-shadow: 0 12px 30px rgba(25, 118, 210, 0.2);
}
.card-header {
padding: 20px;
border-bottom: 2px solid var(--blue-border);
background: rgba(25, 118, 210, 0.05);
display: flex;
align-items: center;
gap: 15px;
}
.card-avatar {
width: 60px;
height: 60px;
border-radius: 8px;
background: linear-gradient(135deg, var(--blue-primary), var(--blue-accent));
display: flex;
align-items: center;
justify-content: center;
color: var(--blue-white);
font-size: 24px;
font-weight: 600;
border: 2px solid var(--blue-accent);
flex-shrink: 0;
}
.card-header-info {
flex: 1;
}
.card-name {
font-family: 'Inter', sans-serif;
font-size: 18px;
font-weight: 600;
color: var(--blue-primary);
margin-bottom: 5px;
}
.card-email {
font-size: 12px;
color: var(--blue-dark);
opacity: 0.8;
word-break: break-word;
}
.card-body {
padding: 20px;
flex: 1;
}
.card-field {
margin-bottom: 15px;
}
.card-field:last-child {
margin-bottom: 0;
}
.card-label {
font-size: 11px;
font-weight: 600;
color: var(--blue-primary);
text-transform: uppercase;
letter-spacing: 0.5px;
margin-bottom: 6px;
opacity: 0.8;
}
.card-value {
font-size: 14px;
color: var(--blue-dark);
word-break: break-word;
}
.card-status-row {
display: flex;
gap: 10px;
align-items: center;
flex-wrap: wrap;
}
.card-footer {
padding: 15px 20px;
border-top: 2px solid var(--blue-border);
background: rgba(25, 118, 210, 0.05);
display: flex;
gap: 8px;
justify-content: space-between;
}
.card-action-buttons {
display: flex;
gap: 8px;
}
.empty-state {
grid-column: 1 / -1;
text-align: center;
padding: 60px 30px;
color: var(--blue-primary);
}
.empty-state i {
font-size: 48px;
color: var(--blue-accent);
margin-bottom: 15px;
opacity: 0.6;
}
.empty-state p {
font-size: 16px;
margin-bottom: 10px;
}
.status-badge {
padding: 6px 12px;
border-radius: 20px;
font-size: 12px;
font-weight: 500;
display: inline-block;
min-width: 70px;
text-align: center;
border: 1px solid;
}
.status-online {
background-color: rgba(56, 142, 60, 0.1);
color: var(--blue-green);
border-color: var(--blue-green);
}
.status-offline {
background-color: rgba(25, 118, 210, 0.1);
color: var(--blue-primary);
border-color: var(--blue-border);
}
.role-badge {
padding: 6px 12px;
border-radius: 20px;
font-size: 12px;
font-weight: 500;
display: inline-block;
min-width: 80px;
text-align: center;
border: 1px solid;
}
.role-pembeli {
background-color: rgba(67, 97, 238, 0.1);
color: #4361ee;
border-color: #4361ee;
}
.action-buttons {
display: flex;
gap: 8px;
flex-wrap: nowrap;
align-items: center;
justify-content: flex-end;
}
.action-btn {
width: 36px;
height: 36px;
border-radius: 8px;
display: flex;
align-items: center;
justify-content: center;
cursor: pointer;
transition: all 0.3s ease;
border: 2px solid;
font-size: 14px;
background-color: var(--blue-white);
}
.action-btn:hover {
transform: translateY(-2px);
box-shadow: 0 4px 8px rgba(25, 118, 210, 0.2);
}
.edit-btn {
color: var(--blue-accent);
border-color: var(--blue-accent);
}
.edit-btn:hover {
background-color: rgba(66, 165, 245, 0.1);
}
.detail-btn {
color: var(--blue-primary);
border-color: var(--blue-primary);
}
.detail-btn:hover {
background-color: rgba(25, 118, 210, 0.1);
}
.delete-btn {
color: var(--blue-red);
border-color: var(--blue-red);
}
.delete-btn:hover {
background-color: rgba(211, 47, 47, 0.1);
}
.action-btn:disabled {
opacity: 0.5;
cursor: not-allowed;
transform: none;
box-shadow: none;
}
.btn {
padding: 12px 24px;
border-radius: 8px;
font-weight: 500;
cursor: pointer;
border: none;
font-size: 14px;
transition: all 0.3s ease;
display: inline-flex;
align-items: center;
gap: 8px;
text-decoration: none;
position: relative;
overflow: hidden;
font-family: 'Poppins', sans-serif;
}
/* Ensure buttons inside table cells don't expand to full width on small screens */
.action-cell {
white-space: nowrap;
}
table td .btn {
width: auto;
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
transform: translateY(-2px);
box-shadow: 0 8px 20px rgba(25, 118, 210, 0.4);
}
.btn-secondary {
background-color: var(--blue-light);
color: var(--blue-dark);
border: 2px solid var(--blue-border);
}
.btn-secondary:hover {
background-color: rgba(144, 202, 249, 0.2);
transform: translateY(-2px);
box-shadow: 0 4px 15px rgba(25, 118, 210, 0.2);
}
/* Modal Styles */
.modal {
display: none;
position: fixed;
top: 0;
left: 0;
width: 100%;
height: 100%;
background-color: rgba(0, 0, 0, 0.7);
z-index: 1000;
align-items: center;
justify-content: center;
backdrop-filter: blur(3px);
}
.modal-content {
background: linear-gradient(135deg, var(--blue-white), var(--blue-light));
border-radius: 12px;
width: 90%;
max-width: 500px;
overflow: hidden;
box-shadow: 0 15px 50px rgba(25, 118, 210, 0.3);
border: 3px solid var(--blue-border);
position: relative;
}
.modal-content::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 4px;
background: linear-gradient(90deg, var(--blue-accent), var(--blue-primary));
}
.modal-header {
padding: 20px;
border-bottom: 2px solid var(--blue-border);
display: flex;
justify-content: space-between;
align-items: center;
background: rgba(25, 118, 210, 0.05);
}
.modal-title {
font-family: 'Inter', sans-serif;
font-size: 20px;
font-weight: 600;
color: var(--blue-primary);
}
.close-modal {
background: none;
border: none;
font-size: 24px;
color: var(--blue-primary);
cursor: pointer;
transition: color 0.3s ease;
width: 36px;
height: 36px;
display: flex;
align-items: center;
justify-content: center;
border-radius: 50%;
}
.close-modal:hover {
color: var(--blue-red);
background-color: rgba(211, 47, 47, 0.1);
}
.modal-body {
padding: 25px;
}
.form-group {
margin-bottom: 20px;
}
.form-label {
display: block;
font-size: 14px;
font-weight: 500;
margin-bottom: 8px;
color: var(--blue-primary);
text-transform: uppercase;
letter-spacing: 0.5px;
}
.form-control {
width: 100%;
padding: 12px 15px;
border-radius: 8px;
border: 2px solid var(--blue-border);
font-size: 14px;
transition: all 0.3s ease;
background-color: var(--blue-light);
color: var(--blue-dark);
font-family: 'Poppins', sans-serif;
}
.form-control:focus {
outline: none;
border-color: var(--blue-accent);
box-shadow: 0 0 0 3px rgba(66, 165, 245, 0.2);
}
.form-control[readonly] {
background-color: rgba(144, 202, 249, 0.1);
cursor: not-allowed;
}
textarea.form-control {
resize: vertical;
min-height: 80px;
font-family: 'Poppins', sans-serif;
}
.form-text {
display: block;
margin-top: 6px;
font-size: 12px;
color: var(--blue-primary);
font-style: italic;
}
.modal-footer {
padding: 20px;
border-top: 2px solid var(--blue-border);
display: flex;
justify-content: flex-end;
gap: 12px;
background: rgba(25, 118, 210, 0.05);
}
.btn-danger {
background: linear-gradient(135deg, var(--blue-red), #a52a2a);
color: white;
border: 2px solid var(--blue-red);
}
.btn-danger:hover {
background: linear-gradient(135deg, #a52a2a, var(--blue-red));
transform: translateY(-2px);
box-shadow: 0 8px 20px rgba(211, 47, 47, 0.4);
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
.cards-container {
grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
}
}
@media (max-width: 768px) {
.header {
flex-direction: column;
align-items: flex-start;
gap: 20px;
}
.search-container {
width: 100%;
}
.cards-container {
grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
gap: 15px;
}
.card-header {
padding: 15px;
}
.card-body {
padding: 15px;
}
.card-footer {
padding: 12px 15px;
}
.btn {
width: 100%;
justify-content: center;
}
.modal-content {
width: 95%;
margin: 10px;
}
}
@media (max-width: 480px) {
.cards-container {
grid-template-columns: 1fr;
}
.page-title {
font-size: 24px;
}
.card-avatar {
width: 50px;
height: 50px;
font-size: 20px;
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
/* Animation for modals */
@keyframes modalFadeIn {
from {
opacity: 0;
transform: translateY(-20px);
}
to {
opacity: 1;
transform: translateY(0);
}
}
.modal-content {
animation: modalFadeIn 0.3s ease-out;
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
<div class="user-profile">
<div class="avatar">
<?php
$firstLetter = 'A';
if (isset($current_user['nama']) && !empty($current_user['nama'])) {
$firstLetter = strtoupper(substr($current_user['nama'], 0, 1));
}
echo $firstLetter;
?>
</div>
<div class="user-name">
<?php
if (isset($current_user['nama'])) {
echo htmlspecialchars($current_user['nama']);
} else {
echo 'Administrator';
}
?>
</div>
<div class="user-role">Super Admin</div>
</div>
<div class="nav-menu">
<a href="dashboard_admin.php" class="nav-item">
<i class="fas fa-tachometer-alt"></i>
<span class="nav-text">Dashboard</span>
</a>
<a href="pembeli_management.php" class="nav-item active">
<i class="fas fa-users"></i>
<span class="nav-text">Pembeli</span>
</a>
<a href="penjual_management.php" class="nav-item">
<i class="fas fa-user-tie"></i>
<span class="nav-text">Penjual</span>
</a>
</div>
<div class="sidebar-footer">
<a href="#" class="nav-item">
<i class="fas fa-question-circle"></i>
<span class="nav-text">Bantuan</span>
</a>
<a href="#" class="nav-item" id="signout-btn">
<i class="fas fa-sign-out-alt"></i>
<span class="nav-text">Keluar</span>
</a>
</div>
</div>
<!-- Main Content -->
<div class="main-content">
<div class="header">
<div class="page-title">Management Pembeli</div>
<div class="search-container">
<i class="fas fa-search search-icon"></i>
<input type="text" class="search-input" placeholder="Cari pembeli..." id="search-input">
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
<!-- Pembeli Management Cards -->
<div style="margin-bottom: 25px;">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
<div style="font-family: 'Inter', sans-serif; font-size: 20px; font-weight: 600; color: var(--blue-primary);">Daftar Pembeli</div>
</div>
<div class="cards-container" id="pembeli-cards-container">
<?php foreach ($pembelis as $pembeli): ?>
<div class="pembeli-card" data-pembeli-id="<?php echo $pembeli['id_user']; ?>">
<div class="card-header">
<div class="card-avatar">
<?php echo strtoupper(substr($pembeli['nama'], 0, 1)); ?>
</div>
<div class="card-header-info">
<div class="card-name"><?php echo htmlspecialchars($pembeli['nama']); ?></div>
<div class="card-email"><?php echo htmlspecialchars($pembeli['email']); ?></div>
</div>
</div>
<div class="card-body">
<div class="card-field">
<div class="card-label">NIK</div>
<div class="card-value"><?php echo !empty($pembeli['nik']) ? htmlspecialchars($pembeli['nik']) : '-'; ?></div>
</div>
<div class="card-field">
<div class="card-label">Alamat</div>
<div class="card-value"><?php echo !empty($pembeli['alamat_pembeli']) ? htmlspecialchars($pembeli['alamat_pembeli']) : '-'; ?></div>
</div>
<div class="card-field">
<div class="card-label">Status</div>
<div class="card-status-row">
<?php
$statusClass = $pembeli['status'] === 'online' ? 'status-online' : 'status-offline';
$statusText = $pembeli['status'] === 'online' ? 'Online' : 'Offline';
?>
<span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
</div>
</div>
<div class="card-field">
<div class="card-label">Bergabung</div>
<div class="card-value"><?php echo date('d M Y', strtotime($pembeli['created_at'])); ?></div>
</div>
</div>
<div class="card-footer">
<div class="card-action-buttons">
<button class="action-btn edit-btn" data-id="<?php echo $pembeli['id_user']; ?>" title="Edit Pembeli">
<i class="fas fa-edit"></i>
</button>
<button class="action-btn detail-btn" data-id="<?php echo $pembeli['id_user']; ?>" title="Lihat Detail">
<i class="fas fa-eye"></i>
</button>
<button class="action-btn delete-btn" data-id="<?php echo $pembeli['id_user']; ?>" title="Hapus Pembeli">
<i class="fas fa-trash"></i>
</button>
</div>
</div>
</div>
<?php endforeach; ?>
<?php if (empty($pembelis)): ?>
<div class="empty-state">
<i class="fas fa-inbox"></i>
<p>Tidak ada data pembeli</p>
<small>Klik tombol "Tambah Pembeli" untuk membuat data baru</small>
</div>
<?php endif; ?>
</div>
</div>
</div>
<!-- Pembeli Detail Modal -->
<div class="modal" id="pembeli-detail-modal">
<div class="modal-content">
<div class="modal-header">
<div class="modal-title">Detail Pembeli</div>
<button class="close-modal" id="close-detail-modal">&times;</button>
</div>
<div class="modal-body">
<div class="form-group">
<label class="form-label">Nama</label>
<input type="text" class="form-control" id="detail-nama" readonly>
</div>
<div class="form-group">
<label class="form-label">Email</label>
<input type="email" class="form-control" id="detail-email" readonly>
</div>
<div class="form-group">
<label class="form-label">NIK</label>
<input type="text" class="form-control" id="detail-nik" readonly>
</div>
<div class="form-group">
<label class="form-label">Alamat</label>
<textarea class="form-control" id="detail-alamat" readonly rows="3"></textarea>
</div>
<div class="form-group">
<label class="form-label">Role</label>
<input type="text" class="form-control" id="detail-role" value="Pembeli" readonly>
</div>
<div class="form-group">
<label class="form-label">Status</label>
<input type="text" class="form-control" id="detail-status" readonly>
</div>
<div class="form-group">
<label class="form-label">Dibuat Pada</label>
<input type="text" class="form-control" id="detail-created" readonly>
</div>
</div>
<div class="modal-footer">
<button class="btn btn-secondary" id="close-detail-btn">Tutup</button>
</div>
</div>
</div>
<!-- Add/Edit Pembeli Modal -->
<div class="modal" id="pembeli-form-modal">
<div class="modal-content">
<form method="POST" action="">
<div class="modal-header">
<div class="modal-title" id="form-modal-title">Tambah Pembeli Baru</div>
<button type="button" class="close-modal" id="close-form-modal">&times;</button>
</div>
<div class="modal-body">
<input type="hidden" name="action" id="form-action" value="add_pembeli">
<input type="hidden" name="id_user" id="pembeli-id">
<div class="form-group">
<label class="form-label">Nama *</label>
<input type="text" class="form-control" id="form-nama" name="nama" required>
</div>
<div class="form-group">
<label class="form-label">Email *</label>
<input type="email" class="form-control" id="form-email" name="email" required>
</div>
<div class="form-group">
<label class="form-label">NIK *</label>
<input type="text" class="form-control" id="form-nik" name="nik"
pattern="\d{16}"
title="NIK harus 16 digit angka"
required
maxlength="16"
oninput="this.value = this.value.replace(/\D/g, '').slice(0, 16)">
<small class="form-text">16 digit angka (wajib untuk pembeli)</small>
</div>
<div class="form-group">
<label class="form-label">Alamat</label>
<textarea class="form-control" id="form-alamat" name="alamat_pembeli" rows="3" placeholder="Alamat pembeli (opsional)"></textarea>
</div>
<div class="form-group">
<label class="form-label">Password *</label>
<input type="password" class="form-control" id="form-password" name="password" required>
<small class="form-text" id="password-help">Wajib diisi untuk pembeli baru</small>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" id="cancel-form-btn">Batal</button>
<button type="submit" class="btn btn-primary" id="submit-form-btn">Simpan Pembeli</button>
</div>
</form>
</div>
</div>
<!-- Delete Confirmation Modal -->
<div class="modal" id="delete-confirm-modal">
<div class="modal-content">
<form method="POST" action="">
<div class="modal-header">
<div class="modal-title">Konfirmasi Hapus</div>
<button type="button" class="close-modal" id="close-delete-modal">&times;</button>
</div>
<div class="modal-body">
<p style="margin-bottom: 15px; color: var(--blue-dark);">Apakah Anda yakin ingin menghapus pembeli ini? Tindakan ini tidak dapat dibatalkan.</p>
<p style="font-weight: 600; color: var(--blue-red);" id="delete-pembeli-name"></p>
<input type="hidden" name="action" value="delete_pembeli">
<input type="hidden" name="id_user" id="delete-pembeli-id">
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" id="cancel-delete-btn">Batal</button>
<button type="submit" class="btn btn-danger">Hapus Pembeli</button>
</div>
</form>
</div>
</div>
<script>
// User data from PHP
const pembelis = <?php echo json_encode($pembelis); ?>;
// DOM Elements
const pembeliCardsContainer = document.getElementById('pembeli-cards-container');
const pembeliDetailModal = document.getElementById('pembeli-detail-modal');
const pembeliFormModal = document.getElementById('pembeli-form-modal');
const deleteConfirmModal = document.getElementById('delete-confirm-modal');
const searchInput = document.getElementById('search-input');
const passwordHelp = document.getElementById('password-help');
// Filter pembelis based on search input
function filterPembelis(searchTerm) {
searchTerm = searchTerm.toLowerCase();
const cards = pembeliCardsContainer.querySelectorAll('.pembeli-card');
let visibleCount = 0;
cards.forEach(card => {
const nama = card.querySelector('.card-name')?.textContent.toLowerCase() || '';
const email = card.querySelector('.card-email')?.textContent.toLowerCase() || '';
const nik = card.querySelector('.card-value')?.textContent.toLowerCase() || '';
const match = nama.includes(searchTerm) || email.includes(searchTerm) || nik.includes(searchTerm);
if (match) {
card.style.display = '';
visibleCount++;
} else {
card.style.display = 'none';
}
});
// Show empty state if no results
let emptyState = pembeliCardsContainer.querySelector('.empty-state');
if (visibleCount === 0 && !emptyState) {
emptyState = document.createElement('div');
emptyState.className = 'empty-state';
emptyState.innerHTML = '<i class="fas fa-search"></i><p>Tidak ada pembeli yang cocok</p><small>Coba ubah kata kunci pencarian</small>';
pembeliCardsContainer.appendChild(emptyState);
} else if (visibleCount > 0 && emptyState) {
emptyState.remove();
}
}
// Open pembeli detail modal
function openPembeliDetailModal(pembeliId) {
const pembeli = pembelis.find(u => u.id_user == pembeliId);
if (!pembeli) return;
document.getElementById('detail-nama').value = pembeli.nama;
document.getElementById('detail-email').value = pembeli.email;
document.getElementById('detail-nik').value = pembeli.nik || '-';
document.getElementById('detail-alamat').value = pembeli.alamat_pembeli || '-';
document.getElementById('detail-status').value = pembeli.status === 'online' ? 'Online' : 'Offline';
document.getElementById('detail-created').value = new Date(pembeli.created_at).toLocaleDateString('id-ID', {
year: 'numeric',
month: 'long',
day: 'numeric',
hour: '2-digit',
minute: '2-digit'
});
pembeliDetailModal.style.display = 'flex';
}
// Open edit pembeli modal
function openEditPembeliModal(pembeliId) {
const pembeli = pembelis.find(u => u.id_user == pembeliId);
if (!pembeli) return;
document.getElementById('form-modal-title').textContent = 'Edit Pembeli';
document.getElementById('form-action').value = 'edit_pembeli';
document.getElementById('pembeli-id').value = pembeli.id_user;
document.getElementById('form-nama').value = pembeli.nama;
document.getElementById('form-email').value = pembeli.email;
document.getElementById('form-nik').value = pembeli.nik || '';
document.getElementById('form-alamat').value = pembeli.alamat_pembeli || '';
document.getElementById('form-password').value = '';
document.getElementById('form-password').required = false;
passwordHelp.textContent = 'Kosongkan jika tidak ingin mengubah password';
pembeliFormModal.style.display = 'flex';
}
// Open add pembeli modal
function openAddPembeliModal() {
document.getElementById('form-modal-title').textContent = 'Tambah Pembeli Baru';
document.getElementById('form-action').value = 'add_pembeli';
document.getElementById('pembeli-form-modal').querySelector('form').reset();
document.getElementById('pembeli-id').value = '';
document.getElementById('form-alamat').value = '';
document.getElementById('form-password').required = true;
passwordHelp.textContent = 'Wajib diisi untuk pembeli baru';
pembeliFormModal.style.display = 'flex';
}
// Open delete confirmation modal
function openDeleteConfirmModal(pembeliId) {
const pembeli = pembelis.find(u => u.id_user == pembeliId);
if (!pembeli) return;
document.getElementById('delete-pembeli-name').textContent = `${pembeli.nama} (${pembeli.email}) - NIK: ${pembeli.nik || '-'}`;
document.getElementById('delete-pembeli-id').value = pembeliId;
deleteConfirmModal.style.display = 'flex';
}
// Close modals
function closeAllModals() {
pembeliDetailModal.style.display = 'none';
pembeliFormModal.style.display = 'none';
deleteConfirmModal.style.display = 'none';
}
// Event listeners for modal close buttons
document.getElementById('close-detail-modal').addEventListener('click', closeAllModals);
document.getElementById('close-detail-btn').addEventListener('click', closeAllModals);
document.getElementById('close-form-modal').addEventListener('click', closeAllModals);
document.getElementById('cancel-form-btn').addEventListener('click', closeAllModals);
document.getElementById('close-delete-modal').addEventListener('click', closeAllModals);
document.getElementById('cancel-delete-btn').addEventListener('click', closeAllModals);
// Event listener for sign out button
document.getElementById('signout-btn').addEventListener('click', function(e) {
e.preventDefault();
if (confirm('Anda yakin ingin keluar?')) {
window.location.href = 'dashboard_admin.php?logout=true';
}
});
// Event listener for search input
searchInput.addEventListener('input', function() {
filterPembelis(this.value);
});
// Event listener for closing alerts
document.querySelectorAll('.close-alert').forEach(btn => {
btn.addEventListener('click', function() {
this.parentElement.style.display = 'none';
});
});
// Close modal when clicking outside
window.addEventListener('click', function(event) {
if (event.target === pembeliDetailModal ||
event.target === pembeliFormModal ||
event.target === deleteConfirmModal) {
closeAllModals();
}
});
// Attach event listeners to action buttons dynamically
function attachActionButtonListeners() {
// Edit buttons
document.querySelectorAll('.edit-btn').forEach(btn => {
btn.addEventListener('click', function(e) {
e.stopPropagation();
const pembeliId = parseInt(this.getAttribute('data-id'));
openEditPembeliModal(pembeliId);
});
});
// Detail buttons
document.querySelectorAll('.detail-btn').forEach(btn => {
btn.addEventListener('click', function(e) {
e.stopPropagation();
const pembeliId = parseInt(this.getAttribute('data-id'));
openPembeliDetailModal(pembeliId);
});
});
// Delete buttons
document.querySelectorAll('.delete-btn').forEach(btn => {
btn.addEventListener('click', function(e) {
e.stopPropagation();
const pembeliId = parseInt(this.getAttribute('data-id'));
openDeleteConfirmModal(pembeliId);
});
});
}
// ===== AUTO-REFRESH STATUS PEMBELI =====
let lastPembeliStatus = {}; // Track status pembeli sebelumnya
let refreshInterval = null; // Store interval ID
let currentRefreshSpeed = 10000; // Default 10 detik untuk detect login
// Fungsi untuk update interval refresh
function updateRefreshInterval(newSpeed) {
if (currentRefreshSpeed !== newSpeed) {
currentRefreshSpeed = newSpeed;
if (refreshInterval) {
clearInterval(refreshInterval);
}
refreshInterval = setInterval(refreshPembeliStatus, currentRefreshSpeed);
console.log(`Refresh interval updated to: ${newSpeed}ms`);
}
}
// Fungsi untuk refresh data pembeli terbaru dari API
function refreshPembeliStatus() {
// Tambahkan timestamp untuk cache busting
const timestamp = new Date().getTime();
fetch(`api_get_pembelis.php?t=${timestamp}`, {
method: 'GET',
headers: {
'Content-Type': 'application/json',
'Cache-Control': 'no-cache'
}
})
.then(response => response.json())
.then(data => {
console.log('Data pembeli terbaru:', data);
if (data.success && data.data) {
// Update pembelis variable dengan data terbaru
pembeli_updated = data.data;
// Detect status changes
let hasLogin = false;
let hasLogout = false;
data.data.forEach(pembeli => {
const lastStatus = lastPembeliStatus[pembeli.id_user];
// Detect login (offline -> online)
if (lastStatus === 'offline' && pembeli.status === 'online') {
hasLogin = true;
console.log(`${pembeli.nama} login! Status: ${pembeli.status}`);
}
// Detect logout (online -> offline)
if (lastStatus === 'online' && pembeli.status === 'offline') {
hasLogout = true;
console.log(`${pembeli.nama} logout! Status: ${pembeli.status}`);
}
// Update last status
lastPembeliStatus[pembeli.id_user] = pembeli.status;
});
// Update interval based on activity
if (hasLogin) {
updateRefreshInterval(10000); // 10 detik saat ada login
} else if (hasLogout) {
updateRefreshInterval(60000); // 1 menit saat ada logout
}
// Render ulang cards dengan data terbaru
renderPembeliCards(pembeli_updated);
// Re-attach event listeners ke tombol action yang baru
attachActionButtonListeners();
} else {
console.error('API error:', data.message);
}
})
.catch(error => {
console.error('Error refresh pembeli:', error);
});
}
// Fungsi untuk render cards pembeli dengan data terbaru
function renderPembeliCards(pembeliList) {
const container = document.getElementById('pembeli-cards-container');
if (!pembeliList || pembeliList.length === 0) {
container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>Tidak ada data pembeli</p><small>Klik tombol "Tambah Pembeli" untuk membuat data baru</small></div>';
return;
}
let html = '';
pembeliList.forEach((pembeli) => {
const statusClass = pembeli.status === 'online' ? 'status-online' : 'status-offline';
const statusText = pembeli.status === 'online' ? 'Online' : 'Offline';
const createdDate = new Date(pembeli.created_at).toLocaleDateString('id-ID', {
year: 'numeric',
month: 'long',
day: 'numeric'
});
html += `
<div class="pembeli-card" data-pembeli-id="${pembeli.id_user}">
<div class="card-header">
<div class="card-avatar">
${escapeHtml(pembeli.nama.substring(0, 1).toUpperCase())}
</div>
<div class="card-header-info">
<div class="card-name">${escapeHtml(pembeli.nama)}</div>
<div class="card-email">${escapeHtml(pembeli.email)}</div>
</div>
</div>
<div class="card-body">
<div class="card-field">
<div class="card-label">NIK</div>
<div class="card-value">${pembeli.nik ? escapeHtml(pembeli.nik) : '-'}</div>
</div>
<div class="card-field">
<div class="card-label">Alamat</div>
<div class="card-value">${pembeli.alamat_pembeli ? escapeHtml(pembeli.alamat_pembeli) : '-'}</div>
</div>
<div class="card-field">
<div class="card-label">Status</div>
<div class="card-status-row">
<span class="status-badge ${statusClass}">${statusText}</span>
</div>
</div>
<div class="card-field">
<div class="card-label">Bergabung</div>
<div class="card-value">${createdDate}</div>
</div>
</div>
<div class="card-footer">
<div class="card-action-buttons">
<button class="action-btn edit-btn" data-id="${pembeli.id_user}" title="Edit Pembeli">
<i class="fas fa-edit"></i>
</button>
<button class="action-btn detail-btn" data-id="${pembeli.id_user}" title="Lihat Detail">
<i class="fas fa-eye"></i>
</button>
<button class="action-btn delete-btn" data-id="${pembeli.id_user}" title="Hapus Pembeli">
<i class="fas fa-trash"></i>
</button>
</div>
</div>
</div>
`;
});
container.innerHTML = html;
// Re-attach event listeners setelah render ulang
attachActionButtonListeners();
}
// Fungsi untuk escape HTML
function escapeHtml(text) {
const div = document.createElement('div');
div.textContent = text;
return div.innerHTML;
}
// Initialize pembeli_updated dengan data awal
let pembeli_updated = pembelis;
// Initialize lastPembeliStatus dengan status awal dari pembelis
pembelis.forEach(pembeli => {
lastPembeliStatus[pembeli.id_user] = pembeli.status;
console.log(`Init: ${pembeli.nama} (${pembeli.email}) = ${pembeli.status}`);
});
console.log('Initial lastPembeliStatus:', lastPembeliStatus);
// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
console.log('DOMContentLoaded - attaching event listeners');
// Attach initial event listeners
attachActionButtonListeners();
// Auto-refresh pembeli status - default 10 detik untuk detect login
updateRefreshInterval(10000);
// Jalankan refresh pertama kali saat halaman load
console.log('DOMContentLoaded - starting first refresh');
refreshPembeliStatus();
});
</script>
</body>
</html>
<?php
// Tutup koneksi database jika masih terbuka
if (isset($conn)) {
mysqli_close($conn);
}
?>