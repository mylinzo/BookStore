<?php
// Fix script untuk menambahkan kolom stok
$host = "localhost";
$user = "root";
$pass = "";
$db   = "toko-buku";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

echo "Mengecek dan menambahkan kolom stok...\n";

// Cek apakah kolom stok sudah ada
$result = mysqli_query($conn, "DESCRIBE buku");
$column_exists = false;

while ($row = mysqli_fetch_assoc($result)) {
    if ($row['Field'] == 'stok') {
        $column_exists = true;
        break;
    }
}

if (!$column_exists) {
    // Tambahkan kolom stok
    $sql = "ALTER TABLE buku ADD COLUMN stok INT NOT NULL DEFAULT 0 AFTER harga";
    if (mysqli_query($conn, $sql)) {
        echo "[SUCCESS] Kolom stok berhasil ditambahkan ke tabel buku!\n";
        
        // Set nilai default stok = 20 untuk semua buku
        $update_sql = "UPDATE buku SET stok = 20";
        if (mysqli_query($conn, $update_sql)) {
            echo "[SUCCESS] Stok default (20) sudah diset untuk semua buku\n";
        }
    } else {
        echo "[ERROR] Gagal menambahkan kolom: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "[INFO] Kolom stok sudah ada di tabel buku\n";
}

// Tampilkan struktur tabel
echo "\nStruktur tabel buku sekarang:\n";
$result = mysqli_query($conn, "DESCRIBE buku");
while ($row = mysqli_fetch_assoc($result)) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

mysqli_close($conn);
?>
