<?php
// api_delete_pesanan.php
// POST JSON: { "id_pesanan": 123 }

session_start();
header('Content-Type: application/json; charset=utf-8');
include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode harus POST']);
    exit;
}

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penjual') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || !isset($data['id_pesanan'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$id_pesanan = intval($data['id_pesanan']);
$id_penjual = $_SESSION['id_user'];
$upload_dir = 'uploads/bukti_pembayaran/';

// Verify ownership
$stmt = mysqli_prepare($conn, "SELECT bukti_pembayaran FROM pesanan WHERE id_pesanan = ? AND id_penjual = ?");
mysqli_stmt_bind_param($stmt, 'ii', $id_pesanan, $id_penjual);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan atau bukan milik Anda']);
    exit;
}

// Delete associated file if present
if (!empty($row['bukti_pembayaran'])) {
    $file = $upload_dir . $row['bukti_pembayaran'];
    if (file_exists($file)) {
        @unlink($file);
    }
}

// Delete pesanan
$del = mysqli_prepare($conn, "DELETE FROM pesanan WHERE id_pesanan = ? AND id_penjual = ?");
mysqli_stmt_bind_param($del, 'ii', $id_pesanan, $id_penjual);
mysqli_stmt_execute($del);
$affected = mysqli_stmt_affected_rows($del);
mysqli_stmt_close($del);

if ($affected > 0) {
    echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dihapus', 'deleted_id' => $id_pesanan]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus pesanan']);
}
exit;
?>