<?php
// Alternative connection using PDO
try {
    $pdo = new PDO("mysql:host=localhost;dbname=toko-buku", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM pesanan LIKE 'kurir'");
    $exists = $stmt->rowCount() > 0;

    if (!$exists) {
        // Add column
        $pdo->exec("ALTER TABLE pesanan ADD COLUMN kurir VARCHAR(50) DEFAULT NULL AFTER metode_pembayaran");
        echo "✅ Kolom 'kurir' berhasil ditambahkan ke tabel pesanan\n";
    } else {
        echo "ℹ️  Kolom 'kurir' sudah ada di tabel pesanan\n";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>