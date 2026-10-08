<?php
/**
 * Check Database Setup
 * Gunakan di awal halaman untuk memastikan database sudah disetup
 */

include 'koneksi.php';

$setup_needed = false;
$setup_message = '';

// Check if required tables exist
$required_tables = ['users', 'kategori', 'produk'];
$missing_tables = [];

foreach ($required_tables as $table) {
    $check_result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$check_result || mysqli_num_rows($check_result) === 0) {
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    $setup_needed = true;
    $setup_message = 'Tabel ' . implode(', ', $missing_tables) . ' belum ada. Silakan jalankan setup.';
}

// Check if produk table has data
if (!in_array('produk', $missing_tables)) {
    $count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM produk WHERE stok > 0");
    if ($count_result) {
        $count_row = mysqli_fetch_assoc($count_result);
        $has_products = $count_row['count'] > 0;
    } else {
        $has_products = false;
    }
} else {
    $has_products = false;
}

mysqli_close($conn);

// If setup needed, return setup info
if ($setup_needed) {
    return array(
        'setup_needed' => true,
        'message' => $setup_message,
        'redirect_url' => 'auto_setup_produk.php',
        'has_products' => false
    );
}

return array(
    'setup_needed' => false,
    'has_products' => $has_products,
    'message' => ''
);
?>
