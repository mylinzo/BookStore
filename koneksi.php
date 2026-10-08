<?php
$host = "localhost";
$username = "root"; // Ganti dengan username database Anda
$password = ""; // Ganti dengan password database Anda
$database = "toko-buku"; // Nama database sesuai file SQL
require_once 'notificationsHelper.php';
// Buat koneksi
$conn = mysqli_connect($host, $username, $password, $database);

// Cek koneksi
if (!$conn) {
    // Check if this is an API call
    if (strpos($_SERVER['REQUEST_URI'], 'api_') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . mysqli_connect_error()
        ]);
        exit;
    } else {
        die("Koneksi database gagal: " . mysqli_connect_error());
    }
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");
?>