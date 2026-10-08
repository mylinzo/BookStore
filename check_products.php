<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=toko-buku", "root", "");
    $stmt = $pdo->query("DESCRIBE produk");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Struktur tabel produk:\n";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }

    echo "\nProduk tersedia:\n";
    $stmt = $pdo->query("SELECT * FROM produk LIMIT 3");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $p) {
        echo "- ID: " . $p['id_produk'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>