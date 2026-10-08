<?php
session_start();
include 'koneksi.php';

// Check if user is logged in and is pembeli
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

$pembeli_id = $_SESSION['id_user'];

// Get unread messages from penjual
$sql = "SELECT DISTINCT 
    c.id_pengirim,
    u.nama as pengirim_nama,
    c.pesan,
    c.created_at,
    COUNT(*) as unread_count
FROM chat c
JOIN users u ON c.id_pengirim = u.id_user
WHERE c.id_penerima = ? AND c.dibaca = 0 AND u.role = 'penjual'
GROUP BY c.id_pengirim, u.nama, c.pesan, c.created_at
ORDER BY c.created_at DESC
LIMIT 10";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $pembeli_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        'id_pengirim' => $row['id_pengirim'],
        'pengirim_nama' => htmlspecialchars($row['pengirim_nama']),
        'pesan' => htmlspecialchars($row['pesan']),
        'created_at' => $row['created_at'],
        'unread_count' => $row['unread_count']
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'messages' => $messages]);
?>
