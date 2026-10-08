<?php
/**
 * Halaman Pesanan Pembeli - Management Pesanan dari Penjual
 * Menampilkan daftar pesanan dengan status pembayaran
 */
session_start();
require_once 'koneksi.php';

// Suppress error output to prevent interfering with page rendering
error_reporting(0);
ini_set('display_errors', 0);
// Check login dengan validasi role
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pembeli') {
$_SESSION['error_message'] = 'Silakan login terlebih dahulu';
header('Location: login.php');
exit;
}
$id_pembeli = (int)$_SESSION['id_user'];
$highlight_order = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0;
$filter_status = $_GET['status'] ?? 'all';
// Validasi filter status berdasarkan status yang ada di database
$valid_status = ['all', 'menunggu_pembayaran', 'diproses', 'dikirim', 'selesai', 'ditolak', 'refund'];
if (!in_array($filter_status, $valid_status)) {
$filter_status = 'all';
}
// Get notification messages from session
$notification = [
'type' => $_SESSION['notification_type'] ?? '',
'message' => $_SESSION['notification_message'] ?? ''
];
// Clear notifications after reading
if (!empty($notification['type'])) {
unset($_SESSION['notification_type'], $_SESSION['notification_message']);
}
/**
* Fungsi untuk mendapatkan daftar pesanan dengan filter
*/
function getPesananList($conn, $id_pembeli, $filter_status) {
$query = "
SELECT
p.id_pesanan,
p.id_penjual,
p.id_produk,
p.quantity,
p.total_harga,
p.status,
p.metode_pembayaran,
p.bukti_pembayaran,
p.nomor_resi,
p.alamat_pengiriman,
p.catatan_pembeli,
p.catatan_penjual,
p.created_at,
p.updated_at,
p.tanggal_selesai,
pr.nama_buku,
pr.harga,
pr.foto_produk,
u.nama as nama_penjual,
u.email as email_penjual,
u.alamat_penjual,
pd.id_pembayaran,
pd.status_pembayaran,
pd.bukti_pembayaran as bukti_pembayaran_detail,
pd.metode_pembayaran as metode_pembayaran_detail
FROM pesanan p
INNER JOIN produk pr ON p.id_produk = pr.id_produk
INNER JOIN users u ON p.id_penjual = u.id_user
LEFT JOIN pembayaran_detail pd ON p.id_pesanan = pd.id_pesanan
WHERE p.id_pembeli = ?
";
$params = [$id_pembeli];
$types = "i";
if ($filter_status !== 'all') {
$query .= " AND p.status = ?";
$params[] = $filter_status;
$types .= "s";
}
$query .= " ORDER BY p.created_at DESC";
try {
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
throw new Exception("Database error: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pesanan_list = [];
while ($row = mysqli_fetch_assoc($result)) {
// Tentukan metode pembayaran dan bukti dari tabel pembayaran_detail jika ada
if (!empty($row['metode_pembayaran_detail'])) {
$row['metode_pembayaran'] = $row['metode_pembayaran_detail'];
}
if (!empty($row['bukti_pembayaran_detail'])) {
$row['bukti_pembayaran'] = $row['bukti_pembayaran_detail'];
}
// Sanitasi output
$row['nama_buku'] = htmlspecialchars($row['nama_buku'] ?? '', ENT_QUOTES, 'UTF-8');
$row['nama_penjual'] = htmlspecialchars($row['nama_penjual'] ?? '', ENT_QUOTES, 'UTF-8');
$row['foto_produk'] = htmlspecialchars($row['foto_produk'] ?? '', ENT_QUOTES, 'UTF-8');
$row['metode_pembayaran'] = htmlspecialchars($row['metode_pembayaran'] ?? '', ENT_QUOTES, 'UTF-8');
$row['nomor_resi'] = htmlspecialchars($row['nomor_resi'] ?? '', ENT_QUOTES, 'UTF-8');
$row['alamat_penjual'] = htmlspecialchars($row['alamat_penjual'] ?? '', ENT_QUOTES, 'UTF-8');
$pesanan_list[] = $row;
}
mysqli_stmt_close($stmt);
return $pesanan_list;
} catch (Exception $e) {
error_log("Error in getPesananList: " . $e->getMessage());
return ['error' => 'Gagal memuat data pesanan: ' . $e->getMessage()];
}
}
/**
* Fungsi untuk mendapatkan data user
*/
function getUserData($conn, $id_user) {
try {
$stmt = mysqli_prepare($conn, "SELECT nama, avatar FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
return $data ?: ['nama' => 'Pembeli', 'avatar' => null];
} catch (Exception $e) {
error_log("Error in getUserData: " . $e->getMessage());
return ['nama' => 'Pembeli', 'avatar' => null];
}
}
/**
* Fungsi untuk mendapatkan informasi pembayaran
*/
function getPaymentInfo($conn, $id_pesanan) {
try {
$stmt = mysqli_prepare($conn,
"SELECT * FROM pembayaran_detail WHERE id_pesanan = ? ORDER BY dibuat_pada DESC LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
return $data;
} catch (Exception $e) {
error_log("Error in getPaymentInfo: " . $e->getMessage());
return null;
}
}
// Ambil data
$pesanan_list = getPesananList($conn, $id_pembeli, $filter_status);
$user_data = getUserData($conn, $id_pembeli);
// Status badge mapping sesuai dengan database
$status_badges = [
'menunggu_pembayaran' => [
'class' => 'warning',
'icon' => 'fa-hourglass-half',
'text' => 'Menunggu Pembayaran',
'color' => '#f59e0b'
],
'menunggu_verifikasi' => [
'class' => 'info',
'icon' => 'fa-clock',
'text' => 'Menunggu Verifikasi',
'color' => '#3b82f6'
],
'diproses' => [
'class' => 'info',
'icon' => 'fa-cogs',
'text' => 'Diproses Penjual',
'color' => '#3b82f6'
],
'dikirim' => [
'class' => 'primary',
'icon' => 'fa-truck',
'text' => 'Sedang Dikirim',
'color' => '#8b5cf6'
],
'selesai' => [
'class' => 'success',
'icon' => 'fa-check-circle',
'text' => 'Pesanan Selesai',
'color' => '#10b981'
],
'ditolak' => [
'class' => 'danger',
'icon' => 'fa-times-circle',
'text' => 'Pesanan Ditolak',
'color' => '#ef4444'
],
'refund' => [
'class' => 'warning',
'icon' => 'fa-rotate-left',
'text' => 'Refund',
'color' => '#f97316'
]
];
// Tambahkan status default untuk status yang tidak terdefinisi
function getStatusBadge($status) {
global $status_badges;
return $status_badges[$status] ?? [
'class' => 'secondary',
'icon' => 'fa-question-circle',
'text' => ucfirst(str_replace('_', ' ', $status)),
'color' => '#6c757d'
];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesanan Saya - Pustaka Aruna</title>
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
--primary: #2563eb;
--primary-hover: #1d4ed8;
--primary-light: #eff6ff;
--secondary: #64748b;
--success: #22c55e;
--success-bg: #f0fdf4;
--error: #ef4444;
--error-bg: #fef2f2;
--warning: #f59e0b;
--warning-bg: #fffbeb;
--info: #3b82f6;
--info-bg: #eff6ff;
--background: #f8fafc;
--surface: #ffffff;
--border: #e2e8f0;
--text-primary: #1e293b;
--text-secondary: #64748b;
--shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
--shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
--shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
--radius: 12px;
--radius-sm: 8px;
}

* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

body {
background-color: var(--background);
color: var(--text-primary);
min-height: 100vh;
line-height: 1.5;
}

.container { max-width: 1200px; margin: 0 auto; padding: 20px; }

/* Navbar */
.navbar {
background: linear-gradient(135deg, var(--primary), var(--primary-hover));
padding: 1rem 2rem;
border-radius: var(--radius);
margin-bottom: 20px;
display: flex;
justify-content: space-between;
align-items: center;
color: white;
box-shadow: var(--shadow-md);
}

.logo {
font-size: 1.25rem;
font-weight: 600;
text-decoration: none;
color: white;
display: flex;
align-items: center;
gap: 10px;
letter-spacing: -0.025em;
}

.logo i { opacity: 0.9; }

.nav-menu { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

.nav-link {
color: rgba(255, 255, 255, 0.9);
text-decoration: none;
padding: 8px 16px;
border-radius: var(--radius-sm);
transition: all 0.2s ease;
font-size: 0.9375rem;
font-weight: 500;
}

.nav-link:hover { background: rgba(255, 255, 255, 0.15); }

.user-info {
display: flex;
align-items: center;
gap: 10px;
background: rgba(255, 255, 255, 0.15);
padding: 6px 16px;
border-radius: 20px;
cursor: pointer;
transition: all 0.2s ease;
}

.user-info:hover { background: rgba(255, 255, 255, 0.25); }

.user-avatar {
width: 32px;
height: 32px;
border-radius: 50%;
overflow: hidden;
background: rgba(255, 255, 255, 0.3);
display: flex;
align-items: center;
justify-content: center;
}

.user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.user-avatar i { color: white; font-size: 18px; }

/* Header */
.header {
background: var(--surface);
padding: 24px 20px;
border-radius: var(--radius);
margin-bottom: 20px;
display: flex;
justify-content: space-between;
align-items: center;
box-shadow: var(--shadow-sm);
border: 1px solid var(--border);
flex-wrap: wrap;
gap: 16px;
}

.header h1 {
font-size: 1.5rem;
font-weight: 600;
color: var(--text-primary);
letter-spacing: -0.025em;
}

.header p { color: var(--text-secondary); font-size: 0.9375rem; margin-top: 4px; }

.header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* Buttons */
.btn {
padding: 10px 20px;
border-radius: var(--radius-sm);
font-weight: 500;
cursor: pointer;
border: none;
font-size: 0.9375rem;
transition: all 0.2s ease;
display: inline-flex;
align-items: center;
gap: 8px;
text-decoration: none;
white-space: nowrap;
}

.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: var(--shadow-md); }

.btn-secondary { background-color: var(--surface); color: var(--text-primary); border: 1px solid var(--border); }
.btn-secondary:hover { background-color: var(--background); transform: translateY(-1px); }

/* Alerts */
.alerts { margin-bottom: 20px; }

.alert {
padding: 14px 16px;
border-radius: var(--radius-sm);
display: flex;
align-items: flex-start;
gap: 10px;
margin-bottom: 10px;
font-size: 0.9375rem;
border: 1px solid transparent;
}

.alert i { font-size: 1rem; margin-top: 2px; flex-shrink: 0; }

.alert-success { background-color: var(--success-bg); border-left: 4px solid var(--success); color: #166534; }
.alert-info { background-color: var(--info-bg); border-left: 4px solid var(--info); color: #1e40af; }

/* Filters */
.filters {
display: flex;
gap: 8px;
margin-bottom: 20px;
flex-wrap: wrap;
background: var(--surface);
padding: 12px;
border-radius: var(--radius-sm);
box-shadow: var(--shadow-sm);
border: 1px solid var(--border);
}

.filter-btn {
padding: 8px 16px;
background: var(--background);
border: 1px solid var(--border);
border-radius: var(--radius-sm);
cursor: pointer;
font-weight: 500;
text-decoration: none;
color: var(--text-secondary);
transition: all 0.2s ease;
font-size: 0.875rem;
}

.filter-btn:hover { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }
.filter-btn.active { background: var(--primary-light); color: var(--primary); border-color: var(--primary); font-weight: 600; }

/* Orders Container */
.orders-container { display: flex; flex-direction: column; gap: 16px; }

/* Order Card */
.order-card {
background: var(--surface);
border-radius: var(--radius);
padding: 16px;
box-shadow: var(--shadow-sm);
transition: all 0.2s ease;
border-left: 4px solid var(--border);
border: 1px solid var(--border);
}

.order-card.highlight {
border-left-color: var(--success);
box-shadow: 0 0 0 2px var(--success-bg);
}

.order-card:hover { box-shadow: var(--shadow-md); border-color: var(--primary); }

.order-header {
display: flex;
justify-content: space-between;
align-items: flex-start;
margin-bottom: 16px;
padding-bottom: 12px;
border-bottom: 1px solid var(--border);
flex-wrap: wrap;
gap: 12px;
}

.order-info { flex: 1; min-width: 200px; }

.order-id { font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 4px; font-family: monospace; }

.seller-name {
font-size: 1rem;
font-weight: 600;
color: var(--text-primary);
display: flex;
align-items: center;
gap: 6px;
}

.seller-name i { color: var(--primary); }

.seller-address { margin-top: 6px; }
.seller-address a {
display: inline-flex;
align-items: center;
gap: 4px;
padding: 4px 10px;
background: var(--background);
color: var(--text-primary);
text-decoration: none;
border-radius: var(--radius-sm);
border: 1px solid var(--border);
font-size: 0.8125rem;
transition: all 0.2s ease;
}
.seller-address a:hover { background: var(--primary-light); border-color: var(--primary); }

.payment-badge {
display: inline-flex;
align-items: center;
gap: 4px;
padding: 4px 10px;
border-radius: 20px;
font-size: 0.75rem;
font-weight: 500;
background: var(--background);
border: 1px solid var(--border);
color: var(--text-secondary);
margin-top: 6px;
}
.payment-badge i { color: var(--primary); }

.order-status {
display: inline-flex;
align-items: center;
gap: 6px;
padding: 6px 12px;
border-radius: 20px;
font-weight: 500;
font-size: 0.8125rem;
white-space: nowrap;
border: 1px solid;
}

.order-status.warning { background: var(--warning-bg); color: #92400e; border-color: #fcd34d; }
.order-status.info { background: var(--info-bg); color: #1e40af; border-color: #93c5fd; }
.order-status.primary { background: var(--info-bg); color: #1e40af; border-color: #93c5fd; }
.order-status.success { background: var(--success-bg); color: #166534; border-color: #86efac; }
.order-status.danger { background: var(--error-bg); color: #991b1b; border-color: #fca5a5; }

.order-body { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }

.product-image {
width: 80px;
height: 100px;
background: var(--background);
border-radius: var(--radius-sm);
overflow: hidden;
flex-shrink: 0;
border: 1px solid var(--border);
display: flex;
align-items: center;
justify-content: center;
}
.product-image img { width: 100%; height: 100%; object-fit: cover; }
.product-image i { font-size: 2rem; color: var(--text-secondary); opacity: 0.5; }

.product-details { flex: 1; min-width: 200px; }

.product-name {
font-weight: 600;
color: var(--text-primary);
margin-bottom: 6px;
font-size: 1rem;
display: -webkit-box;
-webkit-line-clamp: 2;
-webkit-box-orient: vertical;
overflow: hidden;
}

.product-meta {
display: flex;
gap: 16px;
font-size: 0.8125rem;
color: var(--text-secondary);
margin-bottom: 8px;
flex-wrap: wrap;
}
.product-meta i { color: var(--primary); margin-right: 4px; }

.product-price { font-weight: 700; color: var(--text-primary); font-size: 1.125rem; }

.order-footer {
display: flex;
justify-content: space-between;
align-items: center;
padding-top: 12px;
border-top: 1px solid var(--border);
flex-wrap: wrap;
gap: 12px;
}

.order-date { font-size: 0.8125rem; color: var(--text-secondary); display: flex; align-items: center; gap: 4px; }
.order-date i { color: var(--primary); }

.order-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.btn-sm {
padding: 6px 14px;
font-size: 0.8125rem;
border-radius: var(--radius-sm);
cursor: pointer;
text-decoration: none;
display: inline-flex;
align-items: center;
gap: 4px;
transition: all 0.2s ease;
font-weight: 500;
border: 1px solid;
}

.btn-sm-primary { background: var(--primary); color: white; border-color: var(--primary); }
.btn-sm-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }

.btn-sm-success { background: var(--success); color: white; border-color: var(--success); }
.btn-sm-success:hover { background: #16a34a; transform: translateY(-1px); }

.btn-sm-secondary { background: var(--surface); color: var(--text-primary); border-color: var(--border); }
.btn-sm-secondary:hover { background: var(--background); border-color: var(--primary); }

/* Empty State */
.empty-state {
text-align: center;
padding: 60px 20px;
background: var(--surface);
border-radius: var(--radius);
box-shadow: var(--shadow-sm);
border: 1px solid var(--border);
}

.empty-state i { font-size: 4rem; color: var(--text-secondary); margin-bottom: 20px; opacity: 0.5; }
.empty-state h2 { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin-bottom: 10px; }
.empty-state p { color: var(--text-secondary); margin-bottom: 20px; font-size: 0.9375rem; }

/* Error message */
.error-message {
background: var(--error-bg);
border-left: 4px solid var(--error);
color: #991b1b;
padding: 14px 16px;
border-radius: var(--radius-sm);
margin-bottom: 20px;
display: flex;
align-items: center;
gap: 10px;
font-size: 0.9375rem;
}
.error-message i { font-size: 1rem; }

/* Loading spinner */
.loading-spinner {
display: inline-block;
width: 16px;
height: 16px;
border: 2px solid var(--border);
border-radius: 50%;
border-top-color: var(--primary);
animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Payment Method Selector */
.payment-method-selector {
margin: 12px 0;
padding: 12px;
background: var(--background);
border: 1px solid var(--border);
border-radius: var(--radius-sm);
}

.payment-method-label {
display: block;
font-size: 0.875rem;
font-weight: 500;
color: var(--text-primary);
margin-bottom: 10px;
}

.payment-options { display: flex; gap: 16px; flex-wrap: wrap; }

.payment-option {
display: flex;
align-items: center;
gap: 6px;
font-size: 0.875rem;
color: var(--text-secondary);
cursor: pointer;
padding: 8px 12px;
border-radius: var(--radius-sm);
transition: all 0.2s ease;
border: 1px solid transparent;
}

.payment-option:hover { background: var(--primary-light); border-color: #bfdbfe; }
.payment-option.selected { background: var(--primary-light); border-color: var(--primary); color: var(--primary); font-weight: 500; }
.payment-option input[type="radio"] { accent-color: var(--primary); width: 16px; height: 16px; cursor: pointer; }

/* Bank Details */
.bank-details {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
gap: 10px;
margin: 12px 0;
}

.bank-item {
background: var(--background);
padding: 10px 12px;
border-radius: var(--radius-sm);
border: 1px solid var(--border);
}

.bank-item strong {
display: block;
font-size: 0.75rem;
color: var(--text-secondary);
margin-bottom: 4px;
text-transform: uppercase;
letter-spacing: 0.025em;
}

.bank-item span { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
.bank-item.full-width { grid-column: 1 / -1; }

.instruction-box {
background: var(--warning-bg);
border: 1px solid #fcd34d;
padding: 12px;
border-radius: var(--radius-sm);
margin: 12px 0;
color: #92400e;
font-size: 0.875rem;
}
.instruction-box strong { display: block; margin-bottom: 6px; font-weight: 600; }
.instruction-box p { margin: 0; line-height: 1.4; }

.amount-box { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 8px; }

.amount-detail {
padding: 8px 12px;
background: var(--surface);
border: 1px solid var(--border);
border-radius: var(--radius-sm);
}
.amount-detail div:first-child { font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 2px; }
.amount-detail div:last-child { font-weight: 700; font-size: 1rem; color: var(--text-primary); }

/* Order Code Display (for Cash) */
.order-code-box {
background: var(--surface);
border: 2px dashed var(--border);
border-radius: var(--radius-sm);
padding: 20px;
text-align: center;
margin: 12px 0;
}

.order-code-label { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 8px; }

.order-code-value {
font-family: monospace;
font-size: 1.5rem;
font-weight: 700;
color: var(--primary);
letter-spacing: 2px;
background: var(--primary-light);
padding: 10px 20px;
border-radius: var(--radius-sm);
display: inline-block;
border: 1px solid #bfdbfe;
}

.order-code-note {
font-size: 0.8125rem;
color: var(--text-secondary);
margin-top: 12px;
line-height: 1.4;
}
.order-code-note i { color: var(--warning); margin-right: 4px; }

/* Upload Section */
.upload-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); }

.upload-hint { font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }

.file-input-wrapper { position: relative; display: inline-block; width: 100%; }

.file-input {
position: absolute;
left: 0;
top: 0;
opacity: 0;
width: 100%;
height: 100%;
cursor: pointer;
}

.file-label {
display: flex;
align-items: center;
justify-content: center;
gap: 8px;
width: 100%;
padding: 12px 20px;
background: var(--primary);
color: white;
border-radius: var(--radius-sm);
font-weight: 500;
font-size: 0.9375rem;
cursor: pointer;
transition: all 0.2s ease;
border: none;
}

.file-label:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: var(--shadow-md); }

.file-name-preview {
margin-top: 10px;
padding: 10px 12px;
background: var(--background);
border-radius: var(--radius-sm);
font-size: 0.875rem;
color: var(--text-primary);
display: none;
align-items: center;
gap: 8px;
border: 1px solid var(--border);
}
.file-name-preview.show { display: flex; }
.file-name-preview i { color: var(--success); }

.upload-progress { display: none; margin-top: 15px; text-align: center; }
.upload-progress.show { display: block; }

.upload-progress-inner {
display: inline-flex;
align-items: center;
gap: 8px;
padding: 10px 16px;
background: var(--info-bg);
border-radius: var(--radius-sm);
border: 1px solid #93c5fd;
color: #1e40af;
font-size: 0.875rem;
font-weight: 500;
}
.upload-progress-inner i { animation: spin 1s linear infinite; }

/* Responsive */
@media (max-width: 768px) {
.navbar { flex-direction: column; gap: 12px; text-align: center; }
.nav-menu { flex-wrap: wrap; justify-content: center; }
.header { flex-direction: column; align-items: flex-start; }
.order-header { flex-direction: column; align-items: flex-start; }
.order-body { flex-direction: column; }
.order-footer { flex-direction: column; align-items: flex-start; }
.filters { justify-content: center; }
.product-meta { gap: 10px; }
}

/* Scrollbar */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: var(--background); }
::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

/* Focus visible */
.filter-btn:focus-visible, .btn:focus-visible, .payment-option input[type="radio"]:focus-visible {
outline: 2px solid var(--primary);
outline-offset: 2px;
}

/* Animation */
@keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
.alert { animation: fadeIn 0.25s ease-out; }
</style>
</head>
<body>
<div class="container">
<!-- Navbar -->
<div class="navbar">
<a href="dashboard_pembeli.php" class="logo">
<i class="fas fa-book-open"></i>
Pustaka Aruna
</a>
<div class="nav-menu">
<div class="user-info" onclick="location.href='pembeli_account.php'">
<div class="user-avatar">
<?php
$avatar_path = '';
if (!empty($user_data['avatar'])) {
$avatar_file = 'uploads/avatars/' . $user_data['avatar'];
if (file_exists($avatar_file)) {
$avatar_path = $avatar_file . '?t=' . time();
}
}
if (!empty($avatar_path)):
?>
<img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Avatar" loading="lazy">
<?php else: ?>
<i class="fas fa-user-circle"></i>
<?php endif; ?>
</div>
<span><?php echo htmlspecialchars($user_data['nama'] ?? 'Pembeli'); ?></span>
</div>
</div>
</div>

<!-- Header -->
<div class="header">
<div>
<h1>Pesanan Saya</h1>
</div>
<div class="header-actions">
<a href="dashboard_pembeli.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>
</div>

<!-- Notifications -->
<?php if (!empty($notification['message'])): ?>
<div class="alerts">
<div class="alert alert-<?php echo $notification['type'] === 'success' ? 'success' : 'info'; ?>">
<i class="fas fa-<?php echo $notification['type'] === 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
<div>
<strong><?php echo $notification['type'] === 'success' ? 'Berhasil!' : 'Informasi'; ?></strong>
<p><?php echo htmlspecialchars($notification['message']); ?></p>
</div>
</div>
</div>
<?php endif; ?>

<!-- Error Message -->
<?php if (isset($pesanan_list['error'])): ?>
<div class="error-message">
<i class="fas fa-exclamation-triangle"></i>
<div>
<strong>Terjadi Kesalahan:</strong>
<p><?php echo htmlspecialchars($pesanan_list['error']); ?></p>
</div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filters">
<a href="?" class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>"><i class="fas fa-list"></i> Semua</a>
<a href="?status=menunggu_pembayaran" class="filter-btn <?php echo $filter_status === 'menunggu_pembayaran' ? 'active' : ''; ?>"><i class="fas fa-hourglass-half"></i> Menunggu Pembayaran</a>
<a href="?status=diproses" class="filter-btn <?php echo $filter_status === 'diproses' ? 'active' : ''; ?>"><i class="fas fa-cogs"></i> Diproses</a>
<a href="?status=dikirim" class="filter-btn <?php echo $filter_status === 'dikirim' ? 'active' : ''; ?>"><i class="fas fa-truck"></i> Dikirim</a>
<a href="?status=selesai" class="filter-btn <?php echo $filter_status === 'selesai' ? 'active' : ''; ?>"><i class="fas fa-check-circle"></i> Selesai</a>
<a href="?status=ditolak" class="filter-btn <?php echo $filter_status === 'ditolak' ? 'active' : ''; ?>"><i class="fas fa-times-circle"></i> Ditolak</a>
<a href="?status=refund" class="filter-btn <?php echo $filter_status === 'refund' ? 'active' : ''; ?>"><i class="fas fa-rotate-left"></i> Refund</a>
</div>

<!-- Orders List -->
<div class="orders-container">
<?php if (empty($pesanan_list) || isset($pesanan_list['error'])): ?>
<div class="empty-state">
<i class="fas fa-inbox"></i>
<h2>Belum ada pesanan</h2>
<p>Anda belum memiliki pesanan. Mulai berbelanja sekarang!</p>
<a href="halaman_pembeli.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> Belanja Sekarang</a>
</div>
<?php else: ?>
<?php foreach ($pesanan_list as $order):
$badge = getStatusBadge($order['status']);
$is_highlight = $highlight_order === (int)$order['id_pesanan'];
$payment_info = getPaymentInfo($conn, $order['id_pesanan']);
?>
<div class="order-card <?php echo $is_highlight ? 'highlight' : ''; ?>" data-order-id="<?php echo $order['id_pesanan']; ?>">
<!-- Header -->
<div class="order-header">
<div class="order-info">
<div class="order-id">Order #<?php echo str_pad($order['id_pesanan'], 8, '0', STR_PAD_LEFT); ?></div>
<div class="seller-name"><i class="fas fa-store-alt"></i> <?php echo htmlspecialchars($order['nama_penjual']); ?></div>
<?php if (!empty($order['alamat_penjual']) && filter_var($order['alamat_penjual'], FILTER_VALIDATE_URL)): ?>
<div class="seller-address">
<a href="<?php echo htmlspecialchars($order['alamat_penjual']); ?>" target="_blank" rel="noopener noreferrer">
<i class="fas fa-map-marker-alt"></i> Lihat Lokasi Toko
</a>
</div>
<?php endif; ?>
<?php if ($payment_info): ?>
<div class="payment-badge"><i class="fas fa-credit-card"></i> Pembayaran: <?php echo ucfirst($payment_info['status_pembayaran'] ?? 'menunggu'); ?></div>
<?php endif; ?>
</div>
<div class="order-status <?php echo $badge['class']; ?>" style="border-color: <?php echo $badge['color']; ?>; color: <?php echo $badge['color']; ?>;">
<i class="fas <?php echo $badge['icon']; ?>"></i> <?php echo $badge['text']; ?>
</div>
</div>

<!-- Body -->
<div class="order-body">
<div class="product-image">
<?php
$foto_produk_path = '';
if (!empty($order['foto_produk'])) {
$foto_file = 'uploads/produk/' . $order['foto_produk'];
if (file_exists($foto_file)) {
$foto_produk_path = $foto_file;
}
}
if (!empty($foto_produk_path)):
?>
<img src="<?php echo htmlspecialchars($foto_produk_path); ?>" alt="<?php echo htmlspecialchars($order['nama_buku']); ?>" loading="lazy">
<?php else: ?>
<i class="fas fa-book"></i>
<?php endif; ?>
</div>
<div class="product-details">
<div class="product-name"><?php echo htmlspecialchars($order['nama_buku']); ?></div>
<div class="product-meta">
<span><i class="fas fa-box"></i> Jumlah: <?php echo (int)$order['quantity']; ?></span>
<span><i class="fas fa-credit-card"></i> <?php echo !empty($order['metode_pembayaran']) ? ucfirst(htmlspecialchars($order['metode_pembayaran'])) : 'Belum dipilih'; ?></span>
<?php if (!empty($order['nomor_resi'])): ?><span><i class="fas fa-truck"></i> Resi: <?php echo htmlspecialchars($order['nomor_resi']); ?></span><?php endif; ?>
</div>
<div class="product-price">Rp <?php echo number_format((float)$order['total_harga'], 0, ',', '.'); ?></div>
<?php if (!empty($order['catatan_pembeli'])): ?>
<div style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 8px; font-style: italic;">
<i class="fas fa-comment"></i> Catatan: <?php echo htmlspecialchars($order['catatan_pembeli']); ?>
</div>
<?php endif; ?>
</div>
</div>

<!-- Footer with Payment Options -->
<div class="order-footer">
<div class="order-date"><i class="fas fa-calendar-alt"></i> <?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></div>
<div class="order-actions">
<?php if ($order['status'] === 'menunggu_pembayaran'): ?>
<!-- Payment Method Selector -->
<div class="payment-method-selector">
<label class="payment-method-label">Metode Pembayaran</label>
<div class="payment-options">
<label class="payment-option" onclick="selectPaymentMethod('cash', '<?php echo $order['id_pesanan']; ?>')">
<input type="radio" name="metode_pembayaran_<?php echo $order['id_pesanan']; ?>" value="cash" id="cash_<?php echo $order['id_pesanan']; ?>">
<span><i class="fas fa-money-bill-wave"></i> Cash</span>
</label>
<label class="payment-option selected" onclick="selectPaymentMethod('tf_bank', '<?php echo $order['id_pesanan']; ?>')">
<input type="radio" name="metode_pembayaran_<?php echo $order['id_pesanan']; ?>" value="tf_bank" id="tf_bank_<?php echo $order['id_pesanan']; ?>" checked>
<span><i class="fas fa-building-columns"></i> Transfer</span>
</label>
</div>
</div>

<!-- Bank Details Section (for TF) -->
<div id="bank-section-<?php echo $order['id_pesanan']; ?>" class="bank-transfer-section">
<?php if (!empty($order['alamat_penjual']) && filter_var($order['alamat_penjual'], FILTER_VALIDATE_URL)): ?>
<div class="bank-item full-width" style="margin-bottom: 12px;">
<a href="<?php echo htmlspecialchars($order['alamat_penjual']); ?>" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--background); color: var(--text-primary); text-decoration: none; border-radius: var(--radius-sm); border: 1px solid var(--border); font-weight: 500; font-size: 0.8125rem;">
<i class="fas fa-map-marker-alt"></i> Lihat Lokasi Toko
</a>
</div>
<?php endif; ?>
<div class="instruction-box">
<strong>Instruksi Pembayaran:</strong>
<p>Silakan transfer sesuai total pesanan, lalu unggah bukti pembayaran.</p>
<div class="amount-box">
<div class="amount-detail">
<div>Total Transfer</div>
<div>Rp <?php echo number_format((float)$order['total_harga'], 0, ',', '.'); ?></div>
</div>
</div>
</div>
</div>

<!-- Order Code Section (for Cash) -->
<div id="cash-section-<?php echo $order['id_pesanan']; ?>" class="cash-section" style="display: none;">
<div class="order-code-box">
<div class="order-code-label">Kode Pesanan Anda</div>
<div class="order-code-value">#<?php echo str_pad($order['id_pesanan'], 8, '0', STR_PAD_LEFT); ?></div>
<p class="order-code-note"><i class="fas fa-info-circle"></i> Tunjukkan kode ini kepada penjual saat mengambil barang. Pembayaran dilakukan secara tunai di tempat.</p>
</div>
<div class="alert alert-success" style="margin-bottom: 0;">
<i class="fas fa-check-circle"></i> Pesanan akan diproses setelah konfirmasi pembayaran tunai dari penjual.
</div>
</div>

<!-- Upload Section (only for TF) -->
<div id="upload-section-<?php echo $order['id_pesanan']; ?>" class="upload-section">
<form method="POST" enctype="multipart/form-data" id="uploadForm_<?php echo $order['id_pesanan']; ?>" action="transaksi_pembeli.php">
<input type="hidden" name="action" value="upload_pembayaran">
<input type="hidden" name="id_pesanan" value="<?php echo $order['id_pesanan']; ?>">
<input type="hidden" name="id_penjual" value="<?php echo $order['id_penjual']; ?>">
<input type="hidden" name="metode_pembayaran" id="metode_<?php echo $order['id_pesanan']; ?>" value="tf_bank">

<div class="upload-hint"><i class="fas fa-lightbulb"></i> Upload bukti transfer (foto/PDF, maks. 5MB)</div>

<div class="file-input-wrapper">
<input type="file" id="bukti_<?php echo $order['id_pesanan']; ?>" name="bukti_pembayaran" accept=".jpg,.jpeg,.png,.gif,.pdf" class="file-input" onchange="handleFileSelect(this, '<?php echo $order['id_pesanan']; ?>')">
<label for="bukti_<?php echo $order['id_pesanan']; ?>" class="file-label"><i class="fas fa-cloud-upload-alt"></i> Upload</label>
</div>

<div id="filePreview_<?php echo $order['id_pesanan']; ?>" class="file-name-preview">
<i class="fas fa-check-circle"></i><span id="fileName_<?php echo $order['id_pesanan']; ?>"></span>
</div>

<div id="uploadProgress_<?php echo $order['id_pesanan']; ?>" class="upload-progress">
<div class="upload-progress-inner"><i class="fas fa-spinner"></i><span>Sedang upload...</span></div>
</div>
</form>
</div>
<?php endif; ?>

<?php if ($order['status'] === 'dikirim'): ?>
<button onclick="confirmDelivery(<?php echo $order['id_pesanan']; ?>)" class="btn-sm btn-sm-success"><i class="fas fa-check-circle"></i> Terima Pesanan</button>
<?php endif; ?>
<?php if ($order['status'] === 'refund'): ?>
<span class="btn-sm btn-sm-secondary" style="opacity: 0.7; cursor: not-allowed;"><i class="fas fa-rotate-left"></i> Refund Diproses</span>
<?php endif; ?>
<?php if ($order['status'] === 'selesai'): ?>
<!-- Ulasan dihapus sesuai permintaan -->
<?php endif; ?>
<?php if ($order['status'] === 'ditolak' && !empty($order['catatan_penjual'])): ?>
<span class="btn-sm btn-sm-secondary" style="opacity: 0.7;" title="Alasan: <?php echo htmlspecialchars($order['catatan_penjual']); ?>"><i class="fas fa-info-circle"></i> Lihat Alasan</span>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

<!-- API Endpoint untuk konfirmasi pengiriman -->
<script>
// Configuration
const CONFIG = { API_URL: 'api_order_status.php', REFRESH_INTERVAL: 300000, DEBUG: true };
const utils = {
log: function(...args) { if (CONFIG.DEBUG) console.log(...args); },
error: function(...args) { if (CONFIG.DEBUG) console.error(...args); }
};

let loadingCount = 0;
function showLoading() { loadingCount++; if (loadingCount === 1) document.body.classList.add('loading'); }
function hideLoading() { loadingCount = Math.max(0, loadingCount - 1); if (loadingCount === 0) document.body.classList.remove('loading'); }

function showToast(message, type = 'info') {
let toast = document.getElementById('toast-notification');
if (!toast) {
toast = document.createElement('div');
toast.id = 'toast-notification';
toast.style.cssText = 'position: fixed; top: 20px; right: 20px; padding: 12px 24px; background: var(--surface); color: var(--text-primary); border-left: 4px solid var(--primary); border-radius: var(--radius-sm); box-shadow: var(--shadow-md); z-index: 9999; display: none; font-weight: 500; border: 1px solid var(--border);';
document.body.appendChild(toast);
}
toast.textContent = message;
toast.style.display = 'block';
toast.style.borderLeftColor = type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--error)' : 'var(--primary)';
setTimeout(() => { toast.style.display = 'none'; }, 3000);
}

// Payment method selection toggle
function selectPaymentMethod(method, orderId) {
document.querySelectorAll(`input[name="metode_pembayaran_${orderId}"]`).forEach(radio => {
radio.checked = (radio.value === method);
});
document.querySelectorAll(`.payment-option`).forEach(opt => opt.classList.remove('selected'));
document.querySelector(`.payment-option input[value="${method}"]`).closest('.payment-option').classList.add('selected');

const bankSection = document.getElementById(`bank-section-${orderId}`);
const cashSection = document.getElementById(`cash-section-${orderId}`);
const uploadSection = document.getElementById(`upload-section-${orderId}`);
const metodeInput = document.getElementById(`metode_${orderId}`);

if (method === 'cash') {
if (bankSection) bankSection.style.display = 'none';
if (cashSection) cashSection.style.display = 'block';
if (uploadSection) uploadSection.style.display = 'none';
if (metodeInput) metodeInput.value = 'cash';
} else {
if (bankSection) bankSection.style.display = 'block';
if (cashSection) cashSection.style.display = 'none';
if (uploadSection) uploadSection.style.display = 'block';
if (metodeInput) metodeInput.value = 'tf_bank';
}
}

// File handling
function handleFileSelect(fileInput, orderId) {
const file = fileInput.files[0];
if (!file) return;
const maxSize = 5 * 1024 * 1024;
if (file.size > maxSize) { alert('Ukuran file terlalu besar. Maksimal 5MB'); fileInput.value = ''; return; }
const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
if (!allowedTypes.includes(file.type)) { alert('Tipe file tidak valid. Gunakan JPG, PNG, GIF, atau PDF'); fileInput.value = ''; return; }

const preview = document.getElementById(`filePreview_${orderId}`);
const fileName = document.getElementById(`fileName_${orderId}`);
if (preview && fileName) { fileName.textContent = file.name; preview.classList.add('show'); }

const form = document.getElementById(`uploadForm_${orderId}`);
const progress = document.getElementById(`uploadProgress_${orderId}`);
if (form && progress) {
if (confirm('Apakah Anda yakin ingin mengupload bukti pembayaran ini?')) {
progress.classList.add('show');
form.submit();
} else { fileInput.value = ''; preview.classList.remove('show'); }
}
}

// Confirm delivery
async function confirmDelivery(orderId) {
if (!orderId) { utils.error('Order ID is required'); return; }
if (!confirm('Apakah Anda yakin pesanan telah diterima dengan baik?')) return;
const button = event?.target;
const originalText = button?.innerHTML;
try {
if (button) { button.disabled = true; button.innerHTML = '<span class="loading-spinner"></span> Memproses...'; }
showLoading();
const formData = new FormData();
formData.append('action', 'confirm_delivery');
formData.append('order_id', orderId);
const controller = new AbortController();
const timeoutId = setTimeout(() => controller.abort(), 10000);
const response = await fetch(CONFIG.API_URL, { method: 'POST', body: formData, signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
clearTimeout(timeoutId);
if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
const data = await response.json();
if (data.success) { showToast('✓ ' + data.message, 'success'); setTimeout(() => location.reload(), 1500); }
else throw new Error(data.message || 'Gagal mengkonfirmasi pesanan');
} catch (error) {
utils.error('Error confirming delivery:', error);
let errorMessage = 'Terjadi kesalahan: ';
if (error.name === 'AbortError') errorMessage += 'Waktu koneksi habis';
else errorMessage += error.message;
showToast('❌ ' + errorMessage, 'error');
alert('❌ ' + errorMessage);
} finally {
hideLoading();
if (button) { button.disabled = false; button.innerHTML = originalText; }
}
}

// Auto refresh
let refreshTimer;
function startAutoRefresh() {
if (refreshTimer) clearTimeout(refreshTimer);
refreshTimer = setTimeout(() => { if (!document.hidden) location.reload(); }, CONFIG.REFRESH_INTERVAL);
}
function stopAutoRefresh() { if (refreshTimer) { clearTimeout(refreshTimer); refreshTimer = null; } }
document.addEventListener('visibilitychange', () => { if (document.hidden) stopAutoRefresh(); else startAutoRefresh(); });

document.addEventListener('DOMContentLoaded', () => {
startAutoRefresh();
const highlightCard = document.querySelector('.order-card.highlight');
if (highlightCard) highlightCard.scrollIntoView({ behavior: 'smooth', block: 'center' });

// Initialize payment method to tf_bank for all orders
<?php foreach ($pesanan_list as $order): if ($order['status'] === 'menunggu_pembayaran'): ?>
selectPaymentMethod('tf_bank', '<?php echo $order['id_pesanan']; ?>');
<?php endif; endforeach; ?>

utils.log('Page initialized');
});
window.addEventListener('beforeunload', stopAutoRefresh);
window.confirmDelivery = confirmDelivery;
</script>
</body>
</html>