<?php
// Test checkout dengan kurir
try {
    $pdo = new PDO("mysql:host=localhost;dbname=toko-buku", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Simulasi data checkout
    $id_pembeli = 19; // menggunakan ID pembeli yang valid
    $id_penjual = 55; // menggunakan ID penjual yang valid
    $id_produk = 18; // menggunakan ID produk yang valid
    $quantity = 1;
    $total_harga = 50000;
    $metode_pembayaran = 'transfer';
    $kurir = 'JNE'; // contoh kurir
    $alamat_pengiriman = 'Jl. Contoh No. 123';

    // Insert test order
    $stmt = $pdo->prepare("INSERT INTO pesanan (id_pembeli, id_penjual, id_produk, quantity, total_harga, status, metode_pembayaran, kurir, alamat_pengiriman, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW(), NOW())");

    $stmt->execute([$id_pembeli, $id_penjual, $id_produk, $quantity, $total_harga, $metode_pembayaran, $kurir, $alamat_pengiriman]);

    $order_id = $pdo->lastInsertId();

    echo "✅ Checkout berhasil dengan kurir: $kurir\n";
    echo "Order ID: $order_id\n";

    // Verifikasi data tersimpan
    $stmt = $pdo->prepare("SELECT kurir FROM pesanan WHERE id_pesanan = ?");
    $stmt->execute([$order_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Kurir tersimpan: " . $result['kurir'] . "\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>