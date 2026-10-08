<?php
session_start();
// Koneksi ke database
include 'koneksi.php';
// Inisialisasi variabel
$nama = $email = $password = $confirm_password = $role = $nik = $alamat = $alamat_pembeli = $nama_bank = $nomor_rekening = "";
$nama_err = $email_err = $password_err = $confirm_password_err = $role_err = $nik_err = $alamat_err = $alamat_pembeli_err = $nama_bank_err = $nomor_rekening_err = $register_err = $register_success = "";
// Cek apakah form telah disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
// Validasi role
if (empty(trim($_POST["role"]))) {
$role_err = "Pilih tipe akun terlebih dahulu";
} else {
$role = trim($_POST["role"]);
// Validasi role HANYA pembeli atau penjual — super_admin tidak boleh register
if (!in_array($role, ['pembeli', 'penjual'])) {
$role_err = "Tipe akun tidak valid atau tidak diizinkan";
}
}
// Validasi nama
if (empty(trim($_POST["nama"]))) {
$nama_err = "Nama wajib diisi";
} else {
$nama = trim($_POST["nama"]);
if (strlen($nama) < 3) {
$nama_err = "Nama minimal 3 karakter";
} elseif (strlen($nama) > 100) {
$nama_err = "Nama maksimal 100 karakter";
}
}
// Validasi email
if (empty(trim($_POST["email"]))) {
$email_err = "Email wajib diisi";
} else {
$email = trim($_POST["email"]);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
$email_err = "Format email tidak valid";
} else {
$sql = "SELECT id_user FROM users WHERE email = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
mysqli_stmt_bind_param($stmt, "s", $param_email);
$param_email = $email;
if (mysqli_stmt_execute($stmt)) {
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) == 1) {
$email_err = "Email sudah terdaftar";
}
} else {
$register_err = "Terjadi kesalahan saat memeriksa email.";
}
mysqli_stmt_close($stmt);
}
}
}
// Validasi NIK hanya untuk pembeli dan penjual
if ($role == 'pembeli' || $role == 'penjual') {
if (empty(trim($_POST["nik"]))) {
$nik_err = "NIK wajib diisi untuk " . $role;
} else {
$nik = trim($_POST["nik"]);
// Validasi format NIK (16 digit angka)
if (!preg_match('/^\d{16}$/', $nik)) {
$nik_err = "NIK harus 16 digit angka";
} else {
// Cek apakah NIK sudah terdaftar
$sql_nik = "SELECT id_user FROM users WHERE nik = ?";
if ($stmt_nik = mysqli_prepare($conn, $sql_nik)) {
mysqli_stmt_bind_param($stmt_nik, "s", $param_nik);
$param_nik = $nik;
if (mysqli_stmt_execute($stmt_nik)) {
mysqli_stmt_store_result($stmt_nik);
if (mysqli_stmt_num_rows($stmt_nik) == 1) {
$nik_err = "NIK sudah terdaftar";
}
}
mysqli_stmt_close($stmt_nik);
}
}
}
} else {
$nik = ""; // NIK tidak wajib untuk super_admin
}
// Validasi password
if (empty(trim($_POST["password"]))) {
$password_err = "Kata sandi wajib diisi";
} else {
$password = trim($_POST["password"]);
if (strlen($password) < 8) {
$password_err = "Kata sandi minimal 8 karakter";
}
}
// Validasi konfirmasi password
if (empty(trim($_POST["confirm_password"]))) {
$confirm_password_err = "Konfirmasi kata sandi wajib diisi";
} else {
$confirm_password = trim($_POST["confirm_password"]);
if (empty($password_err) && ($password != $confirm_password)) {
$confirm_password_err = "Kata sandi tidak cocok";
}
}
// Ambil alamat penjual jika ada
$alamat = trim($_POST['alamat_penjual'] ?? '');
if ($role === 'penjual') {
if (empty($alamat)) {
$alamat_err = "Alamat toko wajib diisi untuk penjual";
} elseif (strlen($alamat) > 500) {
$alamat_err = "Alamat terlalu panjang";
}
}
// Ambil alamat pembeli jika ada
$alamat_pembeli = trim($_POST['alamat_pembeli'] ?? '');
if ($role === 'pembeli') {
if (empty($alamat_pembeli)) {
$alamat_pembeli_err = "Alamat wajib diisi untuk pembeli";
} elseif (strlen($alamat_pembeli) > 500) {
$alamat_pembeli_err = "Alamat terlalu panjang";
}
}
// Ambil data bank penjual jika ada
$nama_bank = trim($_POST['nama_bank'] ?? '');
$nomor_rekening = trim($_POST['nomor_rekening'] ?? '');
if ($role === 'penjual') {
if (empty($nama_bank)) {
$nama_bank_err = "Nama bank wajib diisi untuk penjual";
} elseif (strlen($nama_bank) > 100) {
$nama_bank_err = "Nama bank terlalu panjang";
}
if (empty($nomor_rekening)) {
$nomor_rekening_err = "Nomor rekening wajib diisi untuk penjual";
} elseif (!preg_match('/^\d{8,20}$/', $nomor_rekening)) {
$nomor_rekening_err = "Nomor rekening harus 8-20 digit angka";
}
}
// Validasi persetujuan syarat
if (!isset($_POST["agree_terms"])) {
$register_err = "Anda harus menyetujui Syarat Layanan dan Kebijakan Privasi";
}
// Jika tidak ada error, simpan ke database
if (empty($nama_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err) && empty($nik_err) && empty($alamat_err) && empty($alamat_pembeli_err) && empty($nama_bank_err) && empty($nomor_rekening_err) && empty($register_err)) {
// Hash password menggunakan password_hash
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
// Tentukan SQL berdasarkan apakah NIK diisi
// Sertakan alamat_penjual, alamat_pembeli, nama_bank, nomor_rekening
$param_alamat = ($role === 'penjual') ? $alamat : '';
$param_alamat_pembeli = ($role === 'pembeli') ? $alamat_pembeli : '';
$param_nama_bank = ($role === 'penjual') ? $nama_bank : '';
$param_nomor_rekening = ($role === 'penjual') ? $nomor_rekening : '';
if ($nik) {
$sql = "INSERT INTO users (nama, email, nik, alamat_penjual, alamat_pembeli, nama_bank, nomor_rekening, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'offline', NOW())";
if ($stmt = mysqli_prepare($conn, $sql)) {
mysqli_stmt_bind_param($stmt, "sssssssss", $param_nama, $param_email, $param_nik, $param_alamat, $param_alamat_pembeli, $param_nama_bank, $param_nomor_rekening, $param_password, $param_role);
$param_nik = $nik;
}
} else {
$sql = "INSERT INTO users (nama, email, alamat_penjual, alamat_pembeli, nama_bank, nomor_rekening, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'offline', NOW())";
if ($stmt = mysqli_prepare($conn, $sql)) {
mysqli_stmt_bind_param($stmt, "sssssssss", $param_nama, $param_email, $param_alamat, $param_alamat_pembeli, $param_nama_bank, $param_nomor_rekening, $param_password, $param_role);
}
}
if ($stmt) {
$param_nama = $nama;
$param_email = $email;
$param_password = $hashed_password;
$param_role = $role;
if (mysqli_stmt_execute($stmt)) {
$register_success = "Pendaftaran berhasil! Silakan login dengan akun Anda.";
// Reset form
$nama = $email = $password = $confirm_password = $role = $nik = "";
// Tutup statement
mysqli_stmt_close($stmt);
// Redirect setelah 2 detik
header("refresh:2;url=login.php?register=success");
exit;
} else {
$register_err = "Terjadi kesalahan saat menyimpan data. Error: " . mysqli_error($conn);
}
// Tutup statement jika gagal
if ($stmt) mysqli_stmt_close($stmt);
} else {
$register_err = "Terjadi kesalahan dalam persiapan database. Error: " . mysqli_error($conn);
}
}
}
// Jika user sudah login, redirect ke dashboard sesuai role
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
if (isset($_SESSION["role"])) {
switch ($_SESSION["role"]) {
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
}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar | Pustaka Aruna</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Crimson+Text:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
--primary-blue: #2563eb;
--primary-dark: #1d4ed8;
--primary-light: #3b82f6;
--secondary-blue: #1e40af;
--accent-blue: #60a5fa;
--background-light: #f0f9ff;
--background-white: #ffffff;
--text-dark: #1e293b;
--text-medium: #475569;
--text-light: #64748b;
--border-color: #e2e8f0;
--success-green: #10b981;
--error-red: #ef4444;
--warning-orange: #f59e0b;
--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
--shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
* {
margin: 0;
padding: 0;
box-sizing: border-box;
font-family: 'Poppins', sans-serif;
}
body {
background: linear-gradient(135deg, var(--background-light) 0%, #e0f2fe 100%);
color: var(--text-dark);
min-height: 100vh;
display: flex;
align-items: center;
justify-content: center;
padding: 20px;
}
.register-container {
width: 100%;
max-width: 520px;
}
.register-card {
background: var(--background-white);
border-radius: 16px;
box-shadow: var(--shadow-xl);
border: 1px solid var(--border-color);
overflow: hidden;
position: relative;
}
.register-card::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 4px;
background: linear-gradient(90deg, var(--primary-blue), var(--accent-blue));
}
.register-header {
background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
color: var(--background-white);
text-align: center;
padding: 35px 20px;
}
.logo {
font-family: 'Poppins', sans-serif;
font-size: 2rem;
margin-bottom: 10px;
display: flex;
align-items: center;
justify-content: center;
gap: 10px;
letter-spacing: 0.5px;
}
.logo i {
font-size: 2.1rem;
color: var(--background-white);
transform: none;
line-height: 1;
}
.logo-text {
display: flex;
flex-direction: column;
align-items: flex-start;
text-align: left;
}
.logo-text span {
line-height: 1.1;
}
.logo-subtitle {
font-size: 12px;
font-weight: 400;
color: rgba(255, 255, 255, 0.95);
margin-top: 0;
letter-spacing: 1px;
text-transform: uppercase;
opacity: 0.9;
}
.logo .logo-text span:first-child {
font-size: 1.7rem;
font-weight: 700;
}

.register-header h1 {
font-family: 'Poppins', sans-serif;
font-size: 1.6rem;
font-weight: 600;
margin-top: 15px;
color: var(--background-white);
}
.register-body {
padding: 35px;
}
.alert {
padding: 16px;
border-radius: 10px;
margin-bottom: 25px;
display: flex;
align-items: center;
border: 1px solid transparent;
position: relative;
overflow: hidden;
}
.alert::before {
content: '';
position: absolute;
left: 0;
top: 0;
width: 4px;
height: 100%;
background-color: currentColor;
opacity: 0.5;
}
.alert-success {
background-color: rgba(16, 185, 129, 0.1);
color: var(--success-green);
border-color: var(--success-green);
}
.alert-error {
background-color: rgba(239, 68, 68, 0.1);
color: var(--error-red);
border-color: var(--error-red);
}
.alert i {
margin-right: 12px;
font-size: 1.3rem;
}
.form-section {
margin-bottom: 22px;
}
.form-label {
display: flex;
align-items: center;
gap: 8px;
font-size: 14px;
font-weight: 600;
margin-bottom: 8px;
color: var(--text-dark);
text-transform: uppercase;
letter-spacing: 0.5px;
}
.form-label i {
font-size: 0.92rem;
color: var(--text-light);
}
.required-badge {
color: var(--error-red);
font-weight: 600;
}
.input-wrapper {
position: relative;
}
.form-control {
width: 100%;
padding: 14px 16px;
border-radius: 10px;
border: 2px solid var(--border-color);
font-size: 14px;
transition: all 0.3s ease;
background-color: var(--background-white);
color: var(--text-dark);
font-family: 'Poppins', sans-serif;
}
.form-control::placeholder {
color: var(--text-light);
font-style: normal;
}
textarea.form-control {
resize: vertical;
min-height: 80px;
font-family: 'Poppins', sans-serif;
}
.form-control:focus {
outline: none;
border-color: var(--primary-blue);
box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
}
.form-control.is-invalid {
border-color: var(--error-red) !important;
}
.invalid-feedback {
color: var(--error-red);
font-size: 0.85rem;
margin-top: 6px;
display: block;
font-weight: 500;
}
.input-icon {
position: absolute;
right: 16px;
top: 50%;
transform: translateY(-50%);
color: var(--text-light);
font-size: 1.1rem;
cursor: pointer;
transition: color 0.3s ease;
}
.input-icon:hover {
color: var(--primary-blue);
}
.input-icon-left {
position: absolute;
left: 16px;
top: 50%;
transform: translateY(-50%);
color: var(--text-light);
font-size: 1rem;
}
.input-icon-left + .form-control {
padding-left: 48px;
}
.role-selection {
display: grid;
grid-template-columns: repeat(2, 1fr);
gap: 14px;
margin-bottom: 25px;
}
@media (max-width: 576px) {
.role-selection {
grid-template-columns: 1fr;
}
}
.role-option input[type="radio"] {
display: none;
}
.role-label {
display: flex;
flex-direction: column;
align-items: center;
justify-content: center;
padding: 20px;
border: 2px solid var(--border-color);
border-radius: 12px;
cursor: pointer;
transition: all 0.3s ease;
background: var(--background-white);
text-align: center;
}
.role-label:hover {
border-color: var(--primary-light);
background: rgba(37, 99, 235, 0.05);
transform: translateY(-2px);
}
.role-option input[type="radio"]:checked + .role-label {
border-color: var(--primary-blue);
background: rgba(37, 99, 235, 0.1);
box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}
.role-label i {
font-size: 2rem;
color: var(--text-medium);
margin-bottom: 8px;
transition: color 0.3s ease;
}
.role-option input[type="radio"]:checked + .role-label i {
color: var(--primary-blue);
}
.role-label .role-name {
font-weight: 600;
font-size: 0.95rem;
color: var(--text-dark);
margin-bottom: 4px;
}
.role-label .role-desc {
font-size: 0.8rem;
color: var(--text-light);
margin-top: 2px;
}
.role-label .role-desc {
font-size: 0.8rem;
color: var(--text-light);
margin-top: 5px;
}
.terms-checkbox {
margin: 25px 0 30px;
}
.terms-label {
display: flex;
flex-wrap: wrap;
gap: 6px;
align-items: flex-start;
color: var(--text-medium);
font-size: 0.9rem;
line-height: 1.4;
}
.terms-label .terms-text {
margin-top: 2px;
}
.terms-link {
color: var(--primary-blue);
text-decoration: none;
font-weight: 600;
transition: color 0.3s ease;
}
.terms-link:hover {
color: var(--primary-dark);
text-decoration: underline;
}
.terms-check {
width: 18px;
height: 18px;
margin-right: 10px;
margin-top: 2px;
cursor: pointer;
accent-color: var(--primary-blue);
}
.terms-link {
color: var(--primary-blue);
text-decoration: none;
font-weight: 600;
transition: color 0.3s ease;
}
.terms-link:hover {
color: var(--primary-dark);
text-decoration: underline;
}
.btn-register {
width: 100%;
background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
color: var(--background-white);
border: none;
border-radius: 10px;
padding: 16px;
font-size: 16px;
font-weight: 600;
cursor: pointer;
transition: all 0.3s ease;
display: flex;
align-items: center;
justify-content: center;
gap: 10px;
position: relative;
overflow: hidden;
}
.btn-register i {
font-size: 1rem;
}
.btn-login-link i {
font-size: 0.9rem;
}
.btn-register::before {
content: '';
position: absolute;
top: 0;
left: -100%;
width: 100%;
height: 100%;
background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
transition: left 0.5s ease;
}
.btn-register:hover::before {
left: 100%;
}
.btn-register:hover {
background: linear-gradient(135deg, var(--primary-dark), var(--primary-blue));
transform: translateY(-2px);
box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
}
.btn-register:disabled {
opacity: 0.7;
cursor: not-allowed;
transform: none !important;
box-shadow: none !important;
}
.login-link {
text-align: center;
margin-top: 25px;
padding-top: 20px;
border-top: 1px solid var(--border-color);
color: var(--text-medium);
font-size: 14px;
}
.btn-login-link {
color: var(--primary-blue);
text-decoration: none;
font-weight: 600;
transition: color 0.3s ease;
display: inline-flex;
align-items: center;
gap: 5px;
}
.btn-login-link:hover {
color: var(--primary-dark);
text-decoration: underline;
}
.password-length {
margin-top: 8px;
font-size: 0.85rem;
color: var(--text-medium);
}
.password-length i {
margin-right: 5px;
}
.length-indicator {
display: inline-block;
width: 10px;
height: 10px;
border-radius: 50%;
margin-right: 3px;
background-color: var(--border-color);
}
.length-indicator.active {
background-color: var(--primary-blue);
}
.nik-info {
font-size: 0.85rem;
color: var(--text-light);
margin-top: 5px;
font-style: normal;
}
#nik-section {
transition: all 0.3s ease;
}
@keyframes fadeIn {
from {
opacity: 0;
transform: translateY(-10px);
}
to {
opacity: 1;
transform: translateY(0);
}
}
.alert {
animation: fadeIn 0.3s ease-out;
}
</style>
</head>
<body>
<div class="register-container">
<div class="register-card">
<div class="register-header">
<div class="logo">
<i class="fas fa-book-open"></i>
<div class="logo-text">
<span>Pustaka Aruna</span>
<span class="logo-subtitle">Registrasi</span>
</div>
</div>
<h1></h1>
</div>
<div class="register-body">
<?php if (!empty($register_err)): ?>
<div class="alert alert-error">
<i class="fas fa-exclamation-circle"></i>
<span><?php echo htmlspecialchars($register_err); ?></span>
</div>
<?php endif; ?>
<?php if (!empty($register_success)): ?>
<div class="alert alert-success">
<i class="fas fa-check-circle"></i>
<span><?php echo htmlspecialchars($register_success); ?></span>
<p style="margin-top: 10px; font-size: 0.9rem;">Anda akan dialihkan ke halaman login...</p>
</div>
<?php else: ?>
<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="registerForm">
<!-- Role Selection -->
<div class="form-section">
<label class="form-label">
<i class="fas fa-user-tag"></i> Role Akun <span class="required-badge">*</span>
</label>
<div class="role-selection">
<div class="role-option">
<input type="radio" id="pembeli" name="role" value="pembeli" <?php echo ($role === 'pembeli') ? 'checked' : ''; ?> required>
<label for="pembeli" class="role-label">
<i class="fas fa-users"></i>
<span class="role-name">Pembeli</span>
<span class="role-desc"></span>
</label>
</div>
<div class="role-option">
<input type="radio" id="penjual" name="role" value="penjual" <?php echo ($role === 'penjual') ? 'checked' : ''; ?> required>
<label for="penjual" class="role-label">
<i class="fas fa-store"></i>
<span class="role-name">Penjual</span>
<span class="role-desc"></span>
</label>
</div>
</div>
<?php if (!empty($role_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($role_err); ?></span>
<?php endif; ?>
</div>
<!-- Nama Lengkap -->
<div class="form-section">
<label for="nama" class="form-label">
<i class="fas fa-user"></i> Nama Lengkap <span class="required-badge">*</span>
</label>
<div class="input-wrapper">
<span class="input-icon-left"><i class="fas fa-user"></i></span>
<input type="text" class="form-control <?php echo (!empty($nama_err)) ? 'is-invalid' : ''; ?>"
id="nama" name="nama" placeholder="Masukkan nama lengkap"
value="<?php echo htmlspecialchars($nama); ?>" required>
</div>
<?php if (!empty($nama_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($nama_err); ?></span>
<?php endif; ?>
</div>
<!-- Email -->
<div class="form-section">
<label for="email" class="form-label">
<i class="fas fa-envelope"></i> Email <span class="required-badge">*</span>
</label>
<div class="input-wrapper">
<span class="input-icon-left"><i class="fas fa-envelope"></i></span>
<input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>"
id="email" name="email" placeholder="contoh@email.com"
value="<?php echo htmlspecialchars($email); ?>" required>
</div>
<?php if (!empty($email_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($email_err); ?></span>
<?php endif; ?>
</div>
<!-- NIK Section (only for pembeli and penjual) -->
<div class="form-section" id="nik-section" style="display: none;">
<label for="nik" class="form-label">
<i class="fas fa-id-card"></i> NIK <span class="required-badge">*</span>
</label>
<div class="input-wrapper">
<span class="input-icon-left"><i class="fas fa-id-card"></i></span>
<input type="text" class="form-control <?php echo (!empty($nik_err)) ? 'is-invalid' : ''; ?>"
id="nik" name="nik" placeholder="16 digit angka"
value="<?php echo htmlspecialchars($nik); ?>"
maxlength="16"
oninput="this.value = this.value.replace(/\D/g, '').slice(0, 16)">
</div>
<div class="nik-info">
<i class="fas fa-info-circle"></i> NIK wajib diisi untuk pembeli dan penjual (16 digit)
</div>
<?php if (!empty($nik_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($nik_err); ?></span>
<?php endif; ?>
</div>
<!-- Alamat Toko (untuk penjual) -->
<div class="form-section" id="alamat-section" style="display: none;">
<label for="alamat_penjual" class="form-label">
<i class="fas fa-map-marker-alt"></i> Alamat Toko <span class="required-badge">*</span>
</label>
<div class="input-wrapper">
<span class="input-icon-left"><i class="fas fa-map-marker-alt"></i></span>
<textarea id="alamat_penjual" name="alamat_penjual" class="form-control <?php echo (!empty($alamat_err)) ? 'is-invalid' : ''; ?>" placeholder="Masukkan alamat lengkap toko Anda" rows="3"><?php echo htmlspecialchars($alamat); ?></textarea>
</div>
<?php if (!empty($alamat_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($alamat_err); ?></span>
<?php endif; ?>
</div>
<!-- Alamat Pembeli (untuk pembeli) -->
<div class="form-section" id="alamat-pembeli-section" style="display: none;">
<label for="alamat_pembeli" class="form-label">
<i class="fas fa-home"></i> Alamat <span class="required-badge">*</span>
</label>
<div class="input-wrapper">
<span class="input-icon-left"><i class="fas fa-map-marker-alt"></i></span>
<textarea id="alamat_pembeli" name="alamat_pembeli" class="form-control <?php echo (!empty($alamat_pembeli_err)) ? 'is-invalid' : ''; ?>" placeholder="Masukkan alamat lengkap Anda" rows="3"><?php echo htmlspecialchars($alamat_pembeli); ?></textarea>
</div>
<?php if (!empty($alamat_pembeli_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($alamat_pembeli_err); ?></span>
<?php endif; ?>
</div>
<!-- Nama Bank (untuk penjual) -->
<div class="form-section" id="nama-bank-section" style="display: none;">
<label for="nama_bank" class="form-label">
<i class="fas fa-university"></i> Nama Bank <span class="required-badge">*</span>
</label>
<div class="input-wrapper">
<span class="input-icon-left"><i class="fas fa-university"></i></span>
<input type="text" id="nama_bank" name="nama_bank" class="form-control <?php echo (!empty($nama_bank_err)) ? 'is-invalid' : ''; ?>" placeholder="Contoh: BCA, BRI, Mandiri" value="<?php echo htmlspecialchars($nama_bank); ?>"/>
</div>
<?php if (!empty($nama_bank_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($nama_bank_err); ?></span>
<?php endif; ?>
</div>
<!-- Nomor Rekening (untuk penjual) -->
<div class="form-section" id="nomor-rekening-section" style="display: none;">
<label for="nomor_rekening" class="form-label">
<i class="fas fa-credit-card"></i> Nomor Rekening <span class="required-badge">*</span>
</label>
<div class="input-wrapper">
<span class="input-icon-left"><i class="fas fa-credit-card"></i></span>
<input type="text" id="nomor_rekening" name="nomor_rekening" class="form-control <?php echo (!empty($nomor_rekening_err)) ? 'is-invalid' : ''; ?>" placeholder="Masukkan nomor rekening (8-20 digit)" value="<?php echo htmlspecialchars($nomor_rekening); ?>" maxlength="20" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 20)"/>
</div>
<?php if (!empty($nomor_rekening_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($nomor_rekening_err); ?></span>
<?php endif; ?>
</div>
<!-- Password -->
<div class="form-section">
<label for="password" class="form-label">
<i class="fas fa-lock"></i> Password <span class="required-badge">*</span>
</label>
<div class="input-wrapper">
<span class="input-icon-left"><i class="fas fa-lock"></i></span>
<input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>"
id="password" name="password" placeholder="Minimal 8 karakter" required>
<span class="input-icon" id="togglePassword"><i class="fas fa-eye"></i></span>
</div>
<div class="password-length">
<i class="fas fa-info-circle"></i>
<span id="lengthText">Minimal 8 karakter</span>
<div style="margin-top: 5px;">
<?php for($i = 1; $i <= 8; $i++): ?>
<span class="length-indicator" id="indicator-<?php echo $i; ?>"></span>
<?php endfor; ?>
</div>
</div>
<?php if (!empty($password_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($password_err); ?></span>
<?php endif; ?>
</div>
<!-- Confirm Password -->
<div class="form-section">
<label for="confirm_password" class="form-label">
<i class="fas fa-lock"></i> Konfirmasi Password <span class="required-badge">*</span>
</label>
<div class="input-wrapper">
<span class="input-icon-left"><i class="fas fa-lock"></i></span>
<input type="password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>"
id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
<span class="input-icon" id="toggleConfirmPassword"><i class="fas fa-eye"></i></span>
</div>
<?php if (!empty($confirm_password_err)): ?>
<span class="invalid-feedback"><?php echo htmlspecialchars($confirm_password_err); ?></span>
<?php endif; ?>
</div>
<!-- Terms Agreement -->
<div class="terms-checkbox">
<label class="terms-label">
<input type="checkbox" class="terms-check" id="agree_terms" name="agree_terms" <?php echo (isset($_POST["agree_terms"])) ? 'checked' : ''; ?>>
<span class="terms-text">Saya setuju dengan </span>
<a href="#" class="terms-link">Syarat & Ketentuan</a>
<span class="terms-text">dan</span>
<a href="#" class="terms-link">Kebijakan Privasi</a>
</label>
</div>
<!-- Submit Button -->
<button type="submit" class="btn-register" id="submitBtn">
<i class="fas fa-user-plus"></i> Daftar Sekarang
</button>
</form>
<div class="login-link">
Sudah punya akun?
<a href="login.php" class="btn-login-link">
<i class="fas fa-sign-in-alt"></i> Login
</a>
</div>
<?php endif; ?>
</div>
</div>
</div>
<script>
// Toggle password visibility
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');
const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
const confirmPasswordInput = document.getElementById('confirm_password');
if (togglePassword) {
togglePassword.addEventListener('click', function() {
const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
passwordInput.setAttribute('type', type);
this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
});
}
if (toggleConfirmPassword) {
toggleConfirmPassword.addEventListener('click', function() {
const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
confirmPasswordInput.setAttribute('type', type);
this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
});
}
// Password strength indicator
const lengthText = document.getElementById('lengthText');
if (passwordInput) {
passwordInput.addEventListener('input', function() {
const password = this.value;
const length = password.length;
// Update text
if (length === 0) {
lengthText.textContent = "Minimal 8 karakter";
lengthText.style.color = "";
} else if (length < 8) {
lengthText.textContent = `${length}/8 karakter`;
lengthText.style.color = "#ef4444";
} else {
lengthText.textContent = `${length} karakter ✓`;
lengthText.style.color = "#10b981";
}
// Update indicators
for (let i = 1; i <= 8; i++) {
const indicator = document.getElementById(`indicator-${i}`);
if (indicator) {
if (i <= length) {
indicator.classList.add('active');
if (length >= 8) {
indicator.style.backgroundColor = "#10b981";
} else {
indicator.style.backgroundColor = "#2563eb";
}
} else {
indicator.classList.remove('active');
indicator.style.backgroundColor = "#e2e8f0";
}
}
}
});
}
// Show/hide NIK field based on role selection
const nikSection = document.getElementById('nik-section');
const nikInput = document.getElementById('nik');
const roleInputs = document.querySelectorAll('input[name="role"]');
function toggleNikField() {
const selectedRole = document.querySelector('input[name="role"]:checked');
if (selectedRole) {
if (selectedRole.value === 'pembeli' || selectedRole.value === 'penjual') {
nikSection.style.display = 'block';
nikInput.required = true;
} else {
nikSection.style.display = 'none';
nikInput.required = false;
nikInput.value = '';
}
}
}
// Show/hide NIK and Alamat fields based on role
function updateRoleFields() {
const role = document.querySelector('input[name="role"]:checked')?.value;
const nikSection = document.getElementById('nik-section');
const alamatSection = document.getElementById('alamat-section');
const alamatPembeliSection = document.getElementById('alamat-pembeli-section');
const namaBankSection = document.getElementById('nama-bank-section');
const nomorRekeningSection = document.getElementById('nomor-rekening-section');
// NIK untuk pembeli dan penjual
if (role === 'penjual' || role === 'pembeli') {
if (nikSection) nikSection.style.display = 'block';
} else {
if (nikSection) nikSection.style.display = 'none';
}
// Alamat pembeli hanya untuk pembeli
if (role === 'pembeli') {
if (alamatPembeliSection) alamatPembeliSection.style.display = 'block';
} else {
if (alamatPembeliSection) alamatPembeliSection.style.display = 'none';
}
// Alamat toko, nama bank, dan nomor rekening hanya untuk penjual
if (role === 'penjual') {
if (alamatSection) alamatSection.style.display = 'block';
if (namaBankSection) namaBankSection.style.display = 'block';
if (nomorRekeningSection) nomorRekeningSection.style.display = 'block';
} else {
if (alamatSection) alamatSection.style.display = 'none';
if (namaBankSection) namaBankSection.style.display = 'none';
if (nomorRekeningSection) nomorRekeningSection.style.display = 'none';
}
}
document.querySelectorAll('input[name="role"]').forEach(r => r.addEventListener('change', updateRoleFields));
// Initialize on load
updateRoleFields();
// Add event listeners to role inputs
roleInputs.forEach(input => {
input.addEventListener('change', toggleNikField);
});
// Initialize NIK field visibility
document.addEventListener('DOMContentLoaded', function() {
toggleNikField();
// Focus on name field on load
const namaField = document.getElementById('nama');
if (namaField && namaField.value === '') {
namaField.focus();
}
// Validate NIK input to only allow numbers
if (nikInput) {
nikInput.addEventListener('input', function() {
this.value = this.value.replace(/\D/g, '').slice(0, 16);
});
}
});
// Form validation
const registerForm = document.getElementById('registerForm');
if (registerForm) {
registerForm.addEventListener('submit', function(e) {
const password = document.getElementById('password').value;
const confirmPassword = document.getElementById('confirm_password').value;
const agreeTerms = document.getElementById('agree_terms');
const roleSelected = document.querySelector('input[name="role"]:checked');
const submitBtn = document.getElementById('submitBtn');
const nikInput = document.getElementById('nik');
const alamatPembeliInput = document.getElementById('alamat_pembeli');
const namaBankInput = document.getElementById('nama_bank');
const nomorRekeningInput = document.getElementById('nomor_rekening');
// Check role selection
if (!roleSelected) {
e.preventDefault();
alert('Silakan pilih tipe akun terlebih dahulu!');
return false;
}
// Validate NIK for pembeli and penjual
if (roleSelected.value === 'pembeli' || roleSelected.value === 'penjual') {
if (!nikInput.value || nikInput.value.length !== 16) {
e.preventDefault();
alert('NIK harus 16 digit angka!');
nikInput.focus();
return false;
}
}
// Validate address for pembeli
if (roleSelected.value === 'pembeli') {
if (!alamatPembeliInput.value.trim()) {
e.preventDefault();
alert('Alamat wajib diisi untuk pembeli!');
alamatPembeliInput.focus();
return false;
}
}
// Validate bank info for penjual
if (roleSelected.value === 'penjual') {
if (!namaBankInput.value.trim()) {
e.preventDefault();
alert('Nama bank wajib diisi untuk penjual!');
namaBankInput.focus();
return false;
}
if (!nomorRekeningInput.value.trim()) {
e.preventDefault();
alert('Nomor rekening wajib diisi untuk penjual!');
nomorRekeningInput.focus();
return false;
}
if (!/^\d{8,20}$/.test(nomorRekeningInput.value)) {
e.preventDefault();
alert('Nomor rekening harus 8-20 digit angka!');
nomorRekeningInput.focus();
return false;
}
}
// Check password match
if (password !== confirmPassword) {
e.preventDefault();
alert('Password dan konfirmasi password tidak cocok!');
return false;
}
// Check password length
if (password.length < 8) {
e.preventDefault();
alert('Password minimal 8 karakter!');
return false;
}
// Check terms agreement
if (!agreeTerms.checked) {
e.preventDefault();
alert('Anda harus menyetujui Syarat & Ketentuan dan Kebijakan Privasi!');
return false;
}
// Show loading state
if (submitBtn) {
submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
submitBtn.disabled = true;
}
return true;
});
}
</script>
</body>
</html>