<?php
// quick_delete_pesanan.php
// Usage (open in browser while logged in as penjual):
// quick_delete_pesanan.php?ids=54,53,52,47

session_start();
include 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penjual') {
    header('Location: login.php');
    exit;
}

$id_penjual = $_SESSION['id_user'];

$ids_param = isset($_GET['ids']) ? trim($_GET['ids']) : '';
if (empty($ids_param)) {
    header('Location: pesanan_penjual.php?msg=' . urlencode('Tidak ada ID pesanan yang diberikan'));
    exit;
}

$ids = array_filter(array_map('intval', explode(',', $ids_param)));
if (empty($ids)) {
    header('Location: pesanan_penjual.php?msg=' . urlencode('Format ID tidak valid'));
    exit;
}

$deleted = [];
$skipped = [];
$upload_dir = 'uploads/bukti_pembayaran/';

foreach ($ids as $id) {
    // Verify the order belongs to this seller
    $stmt = mysqli_prepare($conn, "SELECT id_pesanan, bukti_pembayaran FROM pesanan WHERE id_pesanan = ? AND id_penjual = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $id_penjual);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        // delete associated file if exists
        if (!empty($row['bukti_pembayaran'])) {
            $file = $upload_dir . $row['bukti_pembayaran'];
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        // delete the pesanan row
        $del = mysqli_prepare($conn, "DELETE FROM pesanan WHERE id_pesanan = ? AND id_penjual = ?");
        mysqli_stmt_bind_param($del, "ii", $id, $id_penjual);
        mysqli_stmt_execute($del);
        if (mysqli_stmt_affected_rows($del) > 0) {
            $deleted[] = $id;
        } else {
            $skipped[] = $id;
        }
        mysqli_stmt_close($del);
    } else {
        $skipped[] = $id;
    }
    mysqli_stmt_close($stmt);
}

$msg_parts = [];
if (!empty($deleted)) $msg_parts[] = 'Terhapus: ' . implode(',', $deleted);
if (!empty($skipped)) $msg_parts[] = 'Tidak diproses/di-skip: ' . implode(',', $skipped);

$msg = implode(' | ', $msg_parts);
if (empty($msg)) $msg = 'Tidak ada perubahan.';

header('Location: pesanan_penjual.php?msg=' . urlencode($msg));
exit;
?>