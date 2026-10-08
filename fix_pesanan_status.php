<?php
/**
 * Fix pesanan status ENUM - Add missing statuses
 */

include 'koneksi.php';

if (!$conn) {
    die("Database connection failed");
}

echo "=== FIXING PESANAN STATUS ENUM ===\n\n";

// Alter the status column to include all canonical statuses
$sql = "ALTER TABLE pesanan MODIFY COLUMN status ENUM('menunggu_pembayaran', 'dibayar', 'approve', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak', 'refund') DEFAULT 'menunggu_pembayaran'";

if (mysqli_query($conn, $sql)) {
    echo "✓ Status ENUM updated successfully!\n";
    echo "New ENUM values: 'menunggu_pembayaran', 'dibayar', 'approve', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak', 'refund'\n\n";
} else {
    echo "✗ Error updating status ENUM: " . mysqli_error($conn) . "\n\n";
}

// Verify the change
echo "=== VERIFYING TABLE STRUCTURE ===\n";
$result = mysqli_query($conn, "DESCRIBE pesanan");
$status_found = false;

while ($row = mysqli_fetch_assoc($result)) {
    if ($row['Field'] === 'status') {
        echo "Status column type: " . $row['Type'] . "\n";
        $status_found = true;
        break;
    }
}

if (!$status_found) {
    echo "Status column not found!\n";
}

mysqli_close($conn);

echo "\n=== DONE ===\n";
echo "The 'Data truncated for column status' error should now be fixed.\n";
echo "You can now use all status values including 'approve' and 'refund'.\n";
?>
