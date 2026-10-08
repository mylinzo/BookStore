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
// Get only penjual users with their bank account info
$penjuals = [];
$sql_penjual = "SELECT u.*,
COALESCE(pq.nama_bank, '') as nama_bank,
COALESCE(pq.nomor_rekening, '') as nomor_rekening,
COALESCE(pq.nama_rekening, '') as nama_rekening
FROM users u
LEFT JOIN penjual_qris pq ON u.id_user = pq.id_penjual
WHERE u.role = 'penjual'
ORDER BY u.nama";
$result_penjual = mysqli_query($conn, $sql_penjual);
if ($result_penjual) {
$penjuals = mysqli_fetch_all($result_penjual, MYSQLI_ASSOC);
}
// Handle Add/Edit/Delete Penjual
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
if (isset($_POST['action'])) {
$action = $_POST['action'];
// ========== HANDLE DELETE ==========
if ($action == 'delete_penjual' && isset($_POST['id_user'])) {
$id_user = $_POST['id_user'];
// Validasi ID user
if (empty($id_user)) {
header("Location: penjual_management.php?error=ID penjual tidak valid");
exit;
}
// Cek apakah penjual memiliki data terkait pada beberapa tabel/kolom yang mungkin ada
$total_buku = 0;
$checks = [
['table' => 'buku', 'column' => 'id_user'],
['table' => 'buku', 'column' => 'id_penjual'],
['table' => 'produk', 'column' => 'id_penjual'],
];
foreach ($checks as $c) {
// Periksa apakah kolom ada di database saat ini
$col_check_sql = "SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
if ($col_stmt = mysqli_prepare($conn, $col_check_sql)) {
$schema_name = $db;
mysqli_stmt_bind_param($col_stmt, "sss", $schema_name, $c['table'], $c['column']);
mysqli_stmt_execute($col_stmt);
$col_res = mysqli_stmt_get_result($col_stmt);
$col_row = mysqli_fetch_assoc($col_res);
mysqli_stmt_close($col_stmt);
if (!empty($col_row['c']) && $col_row['c'] > 0) {
// Kolom ada, hitung jumlah record yang terkait
$count_sql = "SELECT COUNT(*) as total FROM `" . $c['table'] . "` WHERE `" . $c['column'] . "` = ?";
if ($count_stmt = mysqli_prepare($conn, $count_sql)) {
mysqli_stmt_bind_param($count_stmt, "i", $id_user);
mysqli_stmt_execute($count_stmt);
$cnt_res = mysqli_stmt_get_result($count_stmt);
$cnt_row = mysqli_fetch_assoc($cnt_res);
$total_buku += (int)($cnt_row['total'] ?? 0);
mysqli_stmt_close($count_stmt);
}
}
}
}
if ($total_buku > 0) {
header("Location: penjual_management.php?error=Tidak dapat menghapus penjual karena masih memiliki $total_buku data terkait (produk/buku)");
exit;
}
// Cek apakah tabel buku ada, jika tidak ada maka langsung hapus
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'buku'");
if (mysqli_num_rows($table_check) == 0) {
// Tabel buku tidak ada, langsung hapus penjual
$sql = "DELETE FROM users WHERE id_user = ? AND role = 'penjual'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_user);
if (mysqli_stmt_execute($stmt)) {
if (mysqli_stmt_affected_rows($stmt) > 0) {
header("Location: penjual_management.php?success=Penjual berhasil dihapus");
} else {
header("Location: penjual_management.php?error=Penjual tidak ditemukan atau sudah dihapus");
}
} else {
header("Location: penjual_management.php?error=Gagal menghapus penjual: " . mysqli_error($conn));
}
mysqli_stmt_close($stmt);
exit;
}
// Hapus penjual
$sql = "DELETE FROM users WHERE id_user = ? AND role = 'penjual'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_user);
if (mysqli_stmt_execute($stmt)) {
if (mysqli_stmt_affected_rows($stmt) > 0) {
header("Location: penjual_management.php?success=Penjual berhasil dihapus");
} else {
header("Location: penjual_management.php?error=Penjual tidak ditemukan atau sudah dihapus");
}
} else {
header("Location: penjual_management.php?error=Gagal menghapus penjual: " . mysqli_error($conn));
}
mysqli_stmt_close($stmt);
exit;
}
// ========== HANDLE ADD/EDIT (dengan validasi NIK) ==========
if ($action == 'add_penjual' || $action == 'edit_penjual') {
$nama = mysqli_real_escape_string($conn, $_POST['nama'] ?? '');
$email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
$nik = mysqli_real_escape_string($conn, $_POST['nik'] ?? '');
$alamat_penjual = mysqli_real_escape_string($conn, $_POST['alamat_penjual'] ?? '');
$nama_bank = mysqli_real_escape_string($conn, $_POST['nama_bank'] ?? '');
$nomor_rekening = mysqli_real_escape_string($conn, $_POST['nomor_rekening'] ?? '');
$nama_rekening = mysqli_real_escape_string($conn, $_POST['nama_rekening'] ?? '');
$role = 'penjual';
$status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'offline');
// Validasi NIK untuk penjual (hanya untuk add/edit)
if (empty($nik)) {
header("Location: penjual_management.php?error=NIK wajib diisi untuk penjual");
exit;
}
// Validasi format NIK (16 digit angka)
if (!preg_match('/^\d{16}$/', $nik)) {
header("Location: penjual_management.php?error=NIK harus 16 digit angka");
exit;
}
// Validasi email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
header("Location: penjual_management.php?error=Format email tidak valid");
exit;
}
if ($action == 'add_penjual') {
// Validasi password untuk tambah
if (!isset($_POST['password']) || empty($_POST['password'])) {
header("Location: penjual_management.php?error=Password wajib diisi");
exit;
}
// Cek duplikasi email dan NIK
$check_sql = "SELECT id_user FROM users WHERE email = ? OR nik = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ss", $email, $nik);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);
if (mysqli_stmt_num_rows($check_stmt) > 0) {
mysqli_stmt_bind_result($check_stmt, $existing_id);
mysqli_stmt_fetch($check_stmt);
// Cek apakah email atau NIK yang duplikat
$check_email_sql = "SELECT id_user FROM users WHERE email = ?";
$check_email_stmt = mysqli_prepare($conn, $check_email_sql);
mysqli_stmt_bind_param($check_email_stmt, "s", $email);
mysqli_stmt_execute($check_email_stmt);
mysqli_stmt_store_result($check_email_stmt);
if (mysqli_stmt_num_rows($check_email_stmt) > 0) {
mysqli_stmt_close($check_email_stmt);
mysqli_stmt_close($check_stmt);
header("Location: penjual_management.php?error=Email sudah terdaftar");
exit;
}
$check_nik_sql = "SELECT id_user FROM users WHERE nik = ?";
$check_nik_stmt = mysqli_prepare($conn, $check_nik_sql);
mysqli_stmt_bind_param($check_nik_stmt, "s", $nik);
mysqli_stmt_execute($check_nik_stmt);
mysqli_stmt_store_result($check_nik_stmt);
if (mysqli_stmt_num_rows($check_nik_stmt) > 0) {
mysqli_stmt_close($check_nik_stmt);
mysqli_stmt_close($check_stmt);
header("Location: penjual_management.php?error=NIK sudah terdaftar");
exit;
}
}
mysqli_stmt_close($check_stmt);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$sql = "INSERT INTO users (nama, email, nik, alamat_penjual, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssss", $nama, $email, $nik, $alamat_penjual, $password, $role, $status);
if (mysqli_stmt_execute($stmt)) {
$id_user_baru = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);
// Insert bank account info to penjual_qris
if (!empty($nama_bank) || !empty($nomor_rekening) || !empty($nama_rekening)) {
$sql_qris = "INSERT INTO penjual_qris (id_penjual, nama_bank, nomor_rekening, nama_rekening, status) VALUES (?, ?, ?, ?, 'aktif')";
$stmt_qris = mysqli_prepare($conn, $sql_qris);
if ($stmt_qris) {
mysqli_stmt_bind_param($stmt_qris, "isss", $id_user_baru, $nama_bank, $nomor_rekening, $nama_rekening);
mysqli_stmt_execute($stmt_qris);
mysqli_stmt_close($stmt_qris);
}
}
header("Location: penjual_management.php?success=Penjual berhasil ditambahkan");
} else {
header("Location: penjual_management.php?error=Gagal menambahkan penjual: " . mysqli_error($conn));
}
exit;
} elseif ($action == 'edit_penjual' && isset($_POST['id_user'])) {
$id_user = $_POST['id_user'];
// Validasi ID user
if (empty($id_user)) {
header("Location: penjual_management.php?error=ID penjual tidak valid");
exit;
}
// Cek duplikasi email dan NIK (kecuali untuk user yang sedang diedit)
$check_sql = "SELECT id_user FROM users WHERE (email = ? OR nik = ?) AND id_user != ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ssi", $email, $nik, $id_user);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);
if (mysqli_stmt_num_rows($check_stmt) > 0) {
mysqli_stmt_bind_result($check_stmt, $existing_id);
mysqli_stmt_fetch($check_stmt);
// Cek apakah email yang duplikat
$check_email_sql = "SELECT id_user FROM users WHERE email = ? AND id_user != ?";
$check_email_stmt = mysqli_prepare($conn, $check_email_sql);
mysqli_stmt_bind_param($check_email_stmt, "si", $email, $id_user);
mysqli_stmt_execute($check_email_stmt);
mysqli_stmt_store_result($check_email_stmt);
if (mysqli_stmt_num_rows($check_email_stmt) > 0) {
mysqli_stmt_close($check_email_stmt);
mysqli_stmt_close($check_stmt);
header("Location: penjual_management.php?error=Email sudah terdaftar");
exit;
}
// Cek apakah NIK yang duplikat
$check_nik_sql = "SELECT id_user FROM users WHERE nik = ? AND id_user != ?";
$check_nik_stmt = mysqli_prepare($conn, $check_nik_sql);
mysqli_stmt_bind_param($check_nik_stmt, "si", $nik, $id_user);
mysqli_stmt_execute($check_nik_stmt);
mysqli_stmt_store_result($check_nik_stmt);
if (mysqli_stmt_num_rows($check_nik_stmt) > 0) {
mysqli_stmt_close($check_nik_stmt);
mysqli_stmt_close($check_stmt);
header("Location: penjual_management.php?error=NIK sudah terdaftar");
exit;
}
}
mysqli_stmt_close($check_stmt);
if (!empty($_POST['password'])) {
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$sql = "UPDATE users SET nama = ?, email = ?, nik = ?, alamat_penjual = ?, password = ?, status = ? WHERE id_user = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssssi", $nama, $email, $nik, $alamat_penjual, $password, $status, $id_user);
} else {
$sql = "UPDATE users SET nama = ?, email = ?, nik = ?, alamat_penjual = ?, status = ? WHERE id_user = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssi", $nama, $email, $nik, $alamat_penjual, $status, $id_user);
}
if (mysqli_stmt_execute($stmt)) {
mysqli_stmt_close($stmt);
// Update or insert bank account info to penjual_qris
$check_qris_sql = "SELECT id_qris FROM penjual_qris WHERE id_penjual = ?";
$check_qris_stmt = mysqli_prepare($conn, $check_qris_sql);
mysqli_stmt_bind_param($check_qris_stmt, "i", $id_user);
mysqli_stmt_execute($check_qris_stmt);
mysqli_stmt_store_result($check_qris_stmt);
if (mysqli_stmt_num_rows($check_qris_stmt) > 0) {
// Update existing
$sql_qris = "UPDATE penjual_qris SET nama_bank = ?, nomor_rekening = ?, nama_rekening = ? WHERE id_penjual = ?";
$stmt_qris = mysqli_prepare($conn, $sql_qris);
if ($stmt_qris) {
mysqli_stmt_bind_param($stmt_qris, "sssi", $nama_bank, $nomor_rekening, $nama_rekening, $id_user);
mysqli_stmt_execute($stmt_qris);
mysqli_stmt_close($stmt_qris);
}
} else {
// Insert new
if (!empty($nama_bank) || !empty($nomor_rekening) || !empty($nama_rekening)) {
$sql_qris = "INSERT INTO penjual_qris (id_penjual, nama_bank, nomor_rekening, nama_rekening, status) VALUES (?, ?, ?, ?, 'aktif')";
$stmt_qris = mysqli_prepare($conn, $sql_qris);
if ($stmt_qris) {
mysqli_stmt_bind_param($stmt_qris, "isss", $id_user, $nama_bank, $nomor_rekening, $nama_rekening);
mysqli_stmt_execute($stmt_qris);
mysqli_stmt_close($stmt_qris);
}
}
}
mysqli_stmt_close($check_qris_stmt);
header("Location: penjual_management.php?success=Penjual berhasil diperbarui");
} else {
header("Location: penjual_management.php?error=Gagal memperbarui penjual: " . mysqli_error($conn));
}
exit;
}
}
}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Management Penjual - Pustaka Aruna</title>
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
background-image: url('image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,100" stroke="%2390caf9" stroke-width="0.5" opacity="0.3"/><path d="M100,0 L0,100" stroke="%2390caf9" stroke-width="0.5" opacity="0.3"/></svg>');
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
background-image: url('image/svg+xml;utf8,<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect width="20" height="20" fill="%23ffffff"/><path d="M0,0 L20,20" stroke="%2390caf9" stroke-width="0.3" opacity="0.2"/><path d="M20,0 L0,20" stroke="%2390caf9" stroke-width="0.3" opacity="0.2"/></svg>');
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
.penjual-card {
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
.penjual-card::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 4px;
background: linear-gradient(90deg, var(--blue-accent), var(--blue-primary));
}
.penjual-card:hover {
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
.role-penjual {
background-color: rgba(25, 118, 210, 0.1);
color: var(--blue-primary);
border-color: var(--blue-primary);
}
.action-buttons {
display: flex;
gap: 8px;
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
max-width: 900px;
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
display: grid;
grid-template-columns: 1fr 1fr;
gap: 20px;
align-items: start;
max-height: 70vh;
overflow-y: auto;
}
.modal-body > .form-group:has(> label:contains("Informasi")),
.modal-body > div[style*="border-top"] {
grid-column: 1 / -1;
}
.modal-body > h5 {
grid-column: 1 / -1;
}
.modal-body > .form-group:nth-child(1),
.modal-body > .form-group:nth-child(2),
.modal-body > .form-group:nth-child(3),
.modal-body > .form-group:nth-child(4) {
grid-column: auto;
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
.modal-body {
grid-template-columns: 1fr;
max-height: 60vh;
}
.modal-content {
max-width: 95%;
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
<a href="pembeli_management.php" class="nav-item">
<i class="fas fa-users"></i>
<span class="nav-text">Pembeli</span>
</a>
<a href="penjual_management.php" class="nav-item active">
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
<div class="page-title">Management Penjual</div>
<div class="search-container">
<i class="fas fa-search search-icon"></i>
<input type="text" class="search-input" placeholder="Cari penjual..." id="search-input">
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
<!-- Penjual Management Cards -->
<div style="margin-bottom: 25px;">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
<div style="font-family: 'Inter', sans-serif; font-size: 20px; font-weight: 600; color: var(--blue-primary);">Daftar Penjual</div>
<button class="btn btn-primary" id="add-penjual-btn">
<i class="fas fa-plus"></i> Tambah Penjual
</button>
</div>
<div class="cards-container" id="penjual-cards-container">
<?php foreach ($penjuals as $penjual): ?>
<div class="penjual-card" data-penjual-id="<?php echo $penjual['id_user']; ?>">
<div class="card-header">
<div class="card-avatar">
<?php echo strtoupper(substr($penjual['nama'], 0, 1)); ?>
</div>
<div class="card-header-info">
<div class="card-name"><?php echo htmlspecialchars($penjual['nama']); ?></div>
<div class="card-email"><?php echo htmlspecialchars($penjual['email']); ?></div>
</div>
</div>
<div class="card-body">
<div class="card-field">
<div class="card-label">NIK</div>
<div class="card-value"><?php echo !empty($penjual['nik']) ? htmlspecialchars($penjual['nik']) : '-'; ?></div>
</div>
<div class="card-field">
<div class="card-label">Alamat Toko</div>
<div class="card-value"><?php echo !empty($penjual['alamat_penjual']) ? htmlspecialchars($penjual['alamat_penjual']) : '-'; ?></div>
</div>
<div class="card-field">
<div class="card-label">Status</div>
<div class="card-status-row">
<?php
$statusClass = $penjual['status'] === 'online' ? 'status-online' : 'status-offline';
$statusText = $penjual['status'] === 'online' ? 'Online' : 'Offline';
?>
<span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
</div>
</div>
<div class="card-field">
<div class="card-label">Bergabung</div>
<div class="card-value"><?php echo date('d M Y', strtotime($penjual['created_at'])); ?></div>
</div>
</div>
<div class="card-footer">
<div class="card-action-buttons">
<button class="action-btn edit-btn" data-id="<?php echo $penjual['id_user']; ?>" title="Edit Penjual">
<i class="fas fa-edit"></i>
</button>
<button class="action-btn detail-btn" data-id="<?php echo $penjual['id_user']; ?>" title="Lihat Detail">
<i class="fas fa-eye"></i>
</button>
<button class="action-btn delete-btn" data-id="<?php echo $penjual['id_user']; ?>" title="Hapus Penjual">
<i class="fas fa-trash"></i>
</button>
</div>
</div>
</div>
<?php endforeach; ?>
<?php if (empty($penjuals)): ?>
<div class="empty-state">
<i class="fas fa-inbox"></i>
<p>Tidak ada data penjual</p>
<small>Klik tombol "Tambah Penjual" untuk membuat data baru</small>
</div>
<?php endif; ?>
</div>
</div>
</div>
<!-- Penjual Detail Modal -->
<div class="modal" id="penjual-detail-modal">
<div class="modal-content">
<div class="modal-header">
<div class="modal-title">Detail Penjual</div>
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
<label class="form-label">Status</label>
<input type="text" class="form-control" id="detail-status" readonly>
</div>
<div class="form-group" style="grid-column: 1 / -1;">
<label class="form-label">Alamat Toko</label>
<textarea class="form-control" id="detail-alamat" readonly rows="3"></textarea>
</div>
<div class="form-group">
<label class="form-label">Bank Rekening</label>
<input type="text" class="form-control" id="detail-nama-bank" readonly placeholder="Belum diisi">
</div>
<div class="form-group">
<label class="form-label">Nomor Rekening</label>
<input type="text" class="form-control" id="detail-nomor-rekening" readonly placeholder="Belum diisi">
</div>
<div class="form-group" style="grid-column: 1 / -1;">
<label class="form-label">Nama Pemilik Rekening</label>
<input type="text" class="form-control" id="detail-nama-rekening" readonly placeholder="Belum diisi">
</div>
<div class="form-group">
<label class="form-label">Role</label>
<input type="text" class="form-control" id="detail-role" value="Penjual" readonly>
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
<!-- Add/Edit Penjual Modal -->
<div class="modal" id="penjual-form-modal">
<div class="modal-content">
<form method="POST" action="" id="penjual-form">
<div class="modal-header">
<div class="modal-title" id="form-modal-title">Tambah Penjual Baru</div>
<button type="button" class="close-modal" id="close-form-modal">&times;</button>
</div>
<div class="modal-body">
<input type="hidden" name="action" id="form-action" value="add_penjual">
<input type="hidden" name="id_user" id="penjual-id">
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
<small class="form-text">16 digit angka (wajib untuk penjual)</small>
</div>
<div class="form-group">
<label class="form-label">Password</label>
<input type="password" class="form-control" id="form-password" name="password">
<small class="form-text" id="password-help">Wajib diisi untuk penjual baru</small>
</div>
<div class="form-group">
<label class="form-label">Status *</label>
<select class="form-control" id="form-status" name="status" required>
<option value="offline">Offline</option>
<option value="online">Online</option>
</select>
</div>
<div class="form-group" style="grid-column: 1 / -1;">
<label class="form-label">Alamat Toko</label>
<textarea class="form-control" id="form-alamat" name="alamat_penjual" rows="3" placeholder="Masukkan alamat lengkap toko"></textarea>
<small class="form-text">Alamat lengkap toko penjual (opsional)</small>
</div>
<div style="border-top: 2px dashed var(--blue-border); padding-top: 15px; margin-top: 15px; grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
<h5 style="color: var(--blue-primary); margin-bottom: 0px; grid-column: 1 / -1;"><i class="fas fa-bank"></i> Informasi Rekening Bank</h5>
<div class="form-group">
<label class="form-label">Nama Bank</label>
<input type="text" class="form-control" id="form-nama-bank" name="nama_bank" placeholder="Contoh: BCA, Mandiri, BRI, dll">
<small class="form-text">Nama bank untuk rekening penjual</small>
</div>
<div class="form-group">
<label class="form-label">Nomor Rekening</label>
<input type="text" class="form-control" id="form-nomor-rekening" name="nomor_rekening" placeholder="Masukkan nomor rekening">
<small class="form-text">Nomor rekening untuk menerima pembayaran</small>
</div>
<div class="form-group" style="grid-column: 1 / -1;">
<label class="form-label">Nama Pemilik Rekening</label>
<input type="text" class="form-control" id="form-nama-rekening" name="nama_rekening" placeholder="Nama sesuai bukti rekening">
<small class="form-text">Nama pemilik sesuai dengan data di bank</small>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" id="cancel-form-btn">Batal</button>
<button type="submit" class="btn btn-primary" id="submit-form-btn">Simpan Penjual</button>
</div>
</form>
</div>
</div>
<!-- Delete Confirmation Modal -->
<div class="modal" id="delete-confirm-modal">
<div class="modal-content">
<form method="POST" action="" id="delete-form">
<div class="modal-header">
<div class="modal-title">Konfirmasi Hapus</div>
<button type="button" class="close-modal" id="close-delete-modal">&times;</button>
</div>
<div class="modal-body">
<p style="margin-bottom: 15px; color: var(--blue-dark);">Apakah Anda yakin ingin menghapus penjual ini? Tindakan ini tidak dapat dibatalkan.</p>
<p style="font-weight: 600; color: var(--blue-red);" id="delete-penjual-name"></p>
<input type="hidden" name="action" value="delete_penjual">
<input type="hidden" name="id_user" id="delete-penjual-id">
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" id="cancel-delete-btn">Batal</button>
<button type="submit" class="btn btn-danger">Hapus Penjual</button>
</div>
</form>
</div>
</div>
<script>
// User data from PHP
const penjuals = <?php echo json_encode($penjuals); ?>;
// DOM Elements
const penjualCardsContainer = document.getElementById('penjual-cards-container');
const penjualDetailModal = document.getElementById('penjual-detail-modal');
const penjualFormModal = document.getElementById('penjual-form-modal');
const deleteConfirmModal = document.getElementById('delete-confirm-modal');
const searchInput = document.getElementById('search-input');
const passwordHelp = document.getElementById('password-help');
const deleteForm = document.getElementById('delete-form');
const penjualForm = document.getElementById('penjual-form');
// Filter penjuals based on search input
function filterPenjuals(searchTerm) {
searchTerm = searchTerm.toLowerCase();
const cards = penjualCardsContainer.querySelectorAll('.penjual-card');
let visibleCount = 0;
cards.forEach(card => {
const nama = card.querySelector('.card-name')?.textContent.toLowerCase() || '';
const email = card.querySelector('.card-email')?.textContent.toLowerCase() || '';
const nik = card.querySelector('.card-field:nth-child(1) .card-value')?.textContent.toLowerCase() || '';
const alamat = card.querySelector('.card-field:nth-child(2) .card-value')?.textContent.toLowerCase() || '';
const match = nama.includes(searchTerm) || email.includes(searchTerm) || nik.includes(searchTerm) || alamat.includes(searchTerm);
if (match) {
card.style.display = '';
visibleCount++;
} else {
card.style.display = 'none';
}
});
// Show empty state if no results
let emptyState = penjualCardsContainer.querySelector('.empty-state');
if (visibleCount === 0) {
if (!emptyState) {
emptyState = document.createElement('div');
emptyState.className = 'empty-state';
emptyState.innerHTML = '<i class="fas fa-search"></i><p>Tidak ada penjual yang cocok</p><small>Coba ubah kata kunci pencarian</small>';
penjualCardsContainer.appendChild(emptyState);
}
} else if (emptyState) {
emptyState.remove();
}
}
// Open penjual detail modal
function openPenjualDetailModal(penjualId) {
const penjual = penjuals.find(u => u.id_user == penjualId);
if (!penjual) return;
document.getElementById('detail-nama').value = penjual.nama || '';
document.getElementById('detail-email').value = penjual.email || '';
document.getElementById('detail-nik').value = penjual.nik || '-';
document.getElementById('detail-alamat').value = penjual.alamat_penjual || '-';
document.getElementById('detail-nama-bank').value = penjual.nama_bank || '';
document.getElementById('detail-nomor-rekening').value = penjual.nomor_rekening || '';
document.getElementById('detail-nama-rekening').value = penjual.nama_rekening || '';
document.getElementById('detail-status').value = penjual.status === 'online' ? 'Online' : 'Offline';
if (penjual.created_at) {
const date = new Date(penjual.created_at);
document.getElementById('detail-created').value = date.toLocaleDateString('id-ID', {
year: 'numeric',
month: 'long',
day: 'numeric',
hour: '2-digit',
minute: '2-digit'
});
} else {
document.getElementById('detail-created').value = '-';
}
penjualDetailModal.style.display = 'flex';
}
// Open edit penjual modal
function openEditPenjualModal(penjualId) {
const penjual = penjuals.find(u => u.id_user == penjualId);
if (!penjual) return;
document.getElementById('form-modal-title').textContent = 'Edit Penjual';
document.getElementById('form-action').value = 'edit_penjual';
document.getElementById('penjual-id').value = penjual.id_user;
document.getElementById('form-nama').value = penjual.nama || '';
document.getElementById('form-email').value = penjual.email || '';
document.getElementById('form-nik').value = penjual.nik || '';
document.getElementById('form-alamat').value = penjual.alamat_penjual || '';
document.getElementById('form-nama-bank').value = penjual.nama_bank || '';
document.getElementById('form-nomor-rekening').value = penjual.nomor_rekening || '';
document.getElementById('form-nama-rekening').value = penjual.nama_rekening || '';
document.getElementById('form-password').value = '';
document.getElementById('form-password').required = false;
document.getElementById('form-status').value = penjual.status || 'offline';
passwordHelp.textContent = 'Kosongkan jika tidak ingin mengubah password';
penjualFormModal.style.display = 'flex';
}
// Open add penjual modal
function openAddPenjualModal() {
document.getElementById('form-modal-title').textContent = 'Tambah Penjual Baru';
document.getElementById('form-action').value = 'add_penjual';
penjualForm.reset();
document.getElementById('penjual-id').value = '';
document.getElementById('form-status').value = 'offline';
document.getElementById('form-password').required = true;
document.getElementById('form-nama-bank').value = '';
document.getElementById('form-nomor-rekening').value = '';
document.getElementById('form-nama-rekening').value = '';
passwordHelp.textContent = 'Wajib diisi untuk penjual baru';
penjualFormModal.style.display = 'flex';
}
// Open delete confirmation modal
function openDeleteConfirmModal(penjualId) {
const penjual = penjuals.find(u => u.id_user == penjualId);
if (!penjual) return;
document.getElementById('delete-penjual-name').textContent = `${penjual.nama} (${penjual.email}) - NIK: ${penjual.nik || '-'}`;
document.getElementById('delete-penjual-id').value = penjualId;
deleteConfirmModal.style.display = 'flex';
}
// Close modals
function closeAllModals() {
penjualDetailModal.style.display = 'none';
penjualFormModal.style.display = 'none';
deleteConfirmModal.style.display = 'none';
}
// Event listeners for modal close buttons
document.getElementById('close-detail-modal').addEventListener('click', closeAllModals);
document.getElementById('close-detail-btn').addEventListener('click', closeAllModals);
document.getElementById('close-form-modal').addEventListener('click', closeAllModals);
document.getElementById('cancel-form-btn').addEventListener('click', closeAllModals);
document.getElementById('close-delete-modal').addEventListener('click', closeAllModals);
document.getElementById('cancel-delete-btn').addEventListener('click', closeAllModals);
// Event listener for add penjual button
document.getElementById('add-penjual-btn').addEventListener('click', openAddPenjualModal);
// Event listener for sign out button
document.getElementById('signout-btn').addEventListener('click', function(e) {
e.preventDefault();
if (confirm('Anda yakin ingin keluar?')) {
window.location.href = 'dashboard_admin.php?logout=true';
}
});
// Event listener for search input
searchInput.addEventListener('input', function() {
filterPenjuals(this.value);
});
// Event listener for closing alerts
document.querySelectorAll('.close-alert').forEach(btn => {
btn.addEventListener('click', function() {
this.parentElement.style.display = 'none';
});
});
// Close modal when clicking outside
window.addEventListener('click', function(event) {
if (event.target === penjualDetailModal ||
event.target === penjualFormModal ||
event.target === deleteConfirmModal) {
closeAllModals();
}
});
// Attach event listeners to action buttons
function attachActionButtonListeners() {
// Edit buttons
document.querySelectorAll('.edit-btn').forEach(btn => {
btn.removeEventListener('click', handleEditClick);
btn.addEventListener('click', handleEditClick);
});
// Detail buttons
document.querySelectorAll('.detail-btn').forEach(btn => {
btn.removeEventListener('click', handleDetailClick);
btn.addEventListener('click', handleDetailClick);
});
// Delete buttons
document.querySelectorAll('.delete-btn').forEach(btn => {
btn.removeEventListener('click', handleDeleteClick);
btn.addEventListener('click', handleDeleteClick);
});
}
// Handler functions
function handleEditClick(e) {
e.preventDefault();
e.stopPropagation();
const penjualId = parseInt(this.getAttribute('data-id'));
if (!this.disabled) {
openEditPenjualModal(penjualId);
}
}
function handleDetailClick(e) {
e.preventDefault();
e.stopPropagation();
const penjualId = parseInt(this.getAttribute('data-id'));
openPenjualDetailModal(penjualId);
}
function handleDeleteClick(e) {
e.preventDefault();
e.stopPropagation();
const penjualId = parseInt(this.getAttribute('data-id'));
if (!this.disabled) {
openDeleteConfirmModal(penjualId);
}
}
// Initialize event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
attachActionButtonListeners();
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