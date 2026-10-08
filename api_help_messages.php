<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $nama = mysqli_real_escape_string($conn, $data['nama'] ?? '');
            $email = mysqli_real_escape_string($conn, $data['email'] ?? '');
            $pesan = mysqli_real_escape_string($conn, $data['pesan'] ?? '');
            $user_id = $_SESSION['id_user'] ?? 0;
            
            // Simpan ke tabel help_chat (buat tabel baru)
            $stmt = mysqli_prepare($conn, "INSERT INTO help_chat (id_user, nama, email, pesan, tipe) VALUES (?, ?, ?, ?, 'pembeli')");
            mysqli_stmt_bind_param($stmt, "isss", $user_id, $nama, $email, $pesan);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            echo json_encode(['success' => $success]);
        }
        break;
        
    case 'list':
        $stmt = mysqli_prepare($conn, "SELECT * FROM help_chat WHERE id_user = ? ORDER BY created_at ASC LIMIT 50");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_user']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $messages = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        echo json_encode(['success' => true, 'data' => $messages]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>