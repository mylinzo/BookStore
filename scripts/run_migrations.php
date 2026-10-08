<?php
// scripts/run_migrations.php
// Run DB migrations safely: only add columns if missing.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../koneksi.php';

$checks = [
    ['table'=>'users','column'=>'alamat_penjual','sql'=>'ALTER TABLE `users` ADD COLUMN `alamat_penjual` TEXT NULL'],
    ['table'=>'produk','column'=>'alamat_penjual','sql'=>'ALTER TABLE `produk` ADD COLUMN `alamat_penjual` TEXT NULL'],
];

$ok = true;
foreach ($checks as $c) {
    $table = $c['table'];
    $col = $c['column'];
    $sql = $c['sql'];

    $check_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . mysqli_real_escape_string($conn, $table) . "' AND COLUMN_NAME = '" . mysqli_real_escape_string($conn, $col) . "'";
    $res = mysqli_query($conn, $check_sql);
    if (!$res) {
        echo "ERROR: gagal memeriksa kolom $col pada tabel $table: " . mysqli_error($conn) . "\n";
        $ok = false;
        continue;
    }
    if (mysqli_num_rows($res) > 0) {
        echo "SKIP: kolom $col pada tabel $table sudah ada.\n";
        continue;
    }

    if (mysqli_query($conn, $sql)) {
        echo "OK: kolom $col ditambahkan ke tabel $table.\n";
    } else {
        echo "ERROR: gagal menambahkan kolom $col ke tabel $table: " . mysqli_error($conn) . "\n";
        $ok = false;
    }
}

if ($ok) {
    echo "\nMigrasi selesai tanpa error.\n";
    exit(0);
} else {
    echo "\nMigrasi selesai dengan beberapa error. Periksa pesan di atas.\n";
    exit(2);
}
