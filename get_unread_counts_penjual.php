<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'penjual') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['id_user'];

$stmt = mysqli_prepare($conn, "
    SELECT id_pengirim as user_id, COUNT(*) as count 
    FROM chat 
    WHERE id_penerima = ? AND dibaca = 0 
    GROUP BY id_pengirim
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$unread_counts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $unread_counts[] = $row;
}
mysqli_stmt_close($stmt);

echo json_encode($unread_counts);
?>