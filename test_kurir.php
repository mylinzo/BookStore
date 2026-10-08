<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=toko-buku", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT kurir FROM pesanan LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result !== false) {
        echo "✅ Kolom kurir dapat diakses\n";
        echo "Nilai kurir: " . ($result['kurir'] ?? 'NULL') . "\n";
    } else {
        echo "ℹ️  Tabel pesanan kosong\n";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>