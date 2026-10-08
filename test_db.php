<?php
include 'koneksi.php';

echo "<h1>Test Database</h1>";

// Check connection
if (!$conn) {
    echo "<p style='color: red;'>❌ Database connection failed: " . mysqli_connect_error() . "</p>";
    exit;
}

echo "<p style='color: green;'>✓ Database connected</p>";

// Check tables
$tables_query = "SHOW TABLES";
$tables_result = mysqli_query($conn, $tables_query);
echo "<h2>Available Tables:</h2>";
echo "<ul>";
while ($row = mysqli_fetch_array($tables_result)) {
    echo "<li>" . $row[0] . "</li>";
}
echo "</ul>";

// Check produk table
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'produk'");
if (mysqli_num_rows($check_table) > 0) {
    echo "<p style='color: green;'>✓ Tabel 'produk' ditemukan</p>";
    
    // Count products
    $count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk");
    $count_row = mysqli_fetch_assoc($count_result);
    echo "<p>Total produk: <strong>" . $count_row['total'] . "</strong></p>";
    
    // Count products with stok > 0
    $count_stok = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE stok > 0");
    $stok_row = mysqli_fetch_assoc($count_stok);
    echo "<p>Produk dengan stok > 0: <strong>" . $stok_row['total'] . "</strong></p>";
    
    // Show sample products
    echo "<h2>Sample Products (limit 5):</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Nama</th><th>Harga</th><th>Stok</th><th>Penjual ID</th></tr>";
    
    $sample = mysqli_query($conn, "SELECT id_produk, nama_buku, harga, stok, id_penjual FROM produk LIMIT 5");
    while ($row = mysqli_fetch_assoc($sample)) {
        echo "<tr>";
        echo "<td>" . $row['id_produk'] . "</td>";
        echo "<td>" . $row['nama_buku'] . "</td>";
        echo "<td>" . $row['harga'] . "</td>";
        echo "<td>" . $row['stok'] . "</td>";
        echo "<td>" . $row['id_penjual'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Tabel 'produk' tidak ditemukan</p>";
}

mysqli_close($conn);
?>
