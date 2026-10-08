<?php
/**
 * Test inserting records with all canonical statuses
 */

include 'koneksi.php';

if (!$conn) {
    die("Database connection failed");
}

echo "=== TESTING STATUS INSERTS ===\n\n";

$statuses = ['menunggu_pembayaran', 'dibayar', 'approve', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak', 'refund'];

foreach ($statuses as $status) {
    // Test INSERT
    $sql = "INSERT INTO pesanan (id_pembeli, id_penjual, id_produk, quantity, total_harga, status) VALUES (1, 1, 1, 1, 10000, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $status);

    if (mysqli_stmt_execute($stmt)) {
        $id = mysqli_insert_id($conn);
        echo "✓ INSERT with status '$status' successful (ID: $id)\n";

        // Test UPDATE
        $update_sql = "UPDATE pesanan SET status = 'selesai' WHERE id_pesanan = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $id);

        if (mysqli_stmt_execute($update_stmt)) {
            echo "✓ UPDATE status to 'selesai' successful\n";
        } else {
            echo "✗ UPDATE failed: " . mysqli_error($conn) . "\n";
        }

        // Clean up - delete test record
        mysqli_query($conn, "DELETE FROM pesanan WHERE id_pesanan = $id");
        echo "✓ Test record cleaned up\n";

    } else {
        echo "✗ INSERT with status '$status' failed: " . mysqli_error($conn) . "\n";
    }

    echo "---\n";
}

mysqli_close($conn);

echo "\n=== TEST COMPLETE ===\n";
echo "If all tests passed, the 'Data truncated for column status' error is fixed!\n";
?>
