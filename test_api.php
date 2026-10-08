<?php
header('Content-Type: application/json; charset=utf-8');

include 'koneksi.php';

if (!isset($conn) || !$conn) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'mysqli_error' => mysqli_connect_error()
    ]);
    exit;
}

// Check if produk table exists
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'produk'");
$table_exists = mysqli_num_rows($check_table) > 0;

if (!$table_exists) {
    echo json_encode([
        'success' => false,
        'error' => 'Tabel produk tidak ada'
    ]);
    exit;
}

// Count products
$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE stok > 0");
$count_row = mysqli_fetch_assoc($count_result);
$total_products = $count_row['total'] ?? 0;

// Get sample products
$result = mysqli_query($conn, "SELECT * FROM produk WHERE stok > 0 LIMIT 3");
$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}

echo json_encode([
    'success' => true,
    'database_connected' => true,
    'table_exists' => $table_exists,
    'total_products' => $total_products,
    'sample_products' => $products
]);

mysqli_close($conn);
?>
