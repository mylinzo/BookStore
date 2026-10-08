<?php
/**
 * Test API - Check manual status pembeli
 * Akses: http://localhost/toko-buku/toko-buku/test_pembeli_status.php
 */

session_start();
include 'koneksi.php';

// Check if logged in as admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die('Harus login dulu');
}

if ($_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'admin') {
    die('Hanya admin yang bisa akses');
}

echo "<h1>Test Status Pembeli</h1>";
echo "<style>table { border-collapse: collapse; } td, th { border: 1px solid #ddd; padding: 8px; }</style>";

// Get semua pembeli
$sql = "SELECT id_user, nama, email, status, last_activity FROM users WHERE role = 'pembeli' ORDER BY last_activity DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo "Error: " . mysqli_error($conn);
    exit;
}

$pembelis = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo "<table>";
echo "<tr><th>ID</th><th>Nama</th><th>Email</th><th>Status</th><th>Last Activity</th></tr>";

foreach ($pembelis as $pembeli) {
    $status_class = $pembeli['status'] === 'online' ? 'style="color:green; font-weight:bold;"' : 'style="color:red;"';
    echo "<tr>";
    echo "<td>{$pembeli['id_user']}</td>";
    echo "<td>" . htmlspecialchars($pembeli['nama']) . "</td>";
    echo "<td>" . htmlspecialchars($pembeli['email']) . "</td>";
    echo "<td {$status_class}>" . htmlspecialchars($pembeli['status']) . "</td>";
    echo "<td>" . htmlspecialchars($pembeli['last_activity']) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h3>Query Debug</h3>";
echo "<pre>";
echo "SQL: " . $sql . "\n\n";
echo "Total pembeli: " . count($pembelis) . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";

echo "<hr>";
echo "<a href='pembeli_management.php'>← Kembali</a>";

mysqli_close($conn);
?>
