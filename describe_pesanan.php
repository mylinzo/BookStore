<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=toko-buku", "root", "");
    $stmt = $pdo->query("DESCRIBE pesanan");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Struktur tabel pesanan:\n";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>