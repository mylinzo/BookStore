<?php
/**
 * Setup Tarif Pengiriman
 * Buka di browser: http://localhost/toko-buku/setup_tarif_pengiriman.php
 * Tambahkan kolom biaya_pengiriman ke pesanan dan buat tabel tarif_pengiriman
 */
session_start();

$host = "localhost";
$username = "root";
$password = "";
$database = "toko-buku";

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("❌ Koneksi gagal: " . mysqli_connect_error());
}

try {
    // 1. ADD kolom biaya_pengiriman ke table pesanan (jika belum ada)
    $result = mysqli_query($conn, "SHOW COLUMNS FROM pesanan LIKE 'biaya_pengiriman'");
    if (!$result || mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE pesanan ADD COLUMN biaya_pengiriman INT DEFAULT 0 AFTER kurir";
        if (mysqli_query($conn, $sql)) {
            echo "✅ Kolom 'biaya_pengiriman' berhasil ditambahkan ke pesanan\n";
        } else {
            echo "❌ Error: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ℹ️ Kolom 'biaya_pengiriman' sudah ada\n";
    }

    // 2. CREATE table tarif_pengiriman (jika belum ada)
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'tarif_pengiriman'");
    if (!$result || mysqli_num_rows($result) == 0) {
        $sql = "CREATE TABLE tarif_pengiriman (
            id_tarif INT PRIMARY KEY AUTO_INCREMENT,
            kurir VARCHAR(50) UNIQUE NOT NULL,
            biaya_pengiriman INT NOT NULL,
            format_resi VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (mysqli_query($conn, $sql)) {
            echo "✅ Tabel 'tarif_pengiriman' berhasil dibuat\n";
        } else {
            echo "❌ Error: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ℹ️ Tabel 'tarif_pengiriman' sudah ada\n";
    }

    // 3. INSERT data tarif default (jika belum ada)
    $check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tarif_pengiriman");
    $row = mysqli_fetch_assoc($check);
    
    if ($row['cnt'] == 0) {
        $tarif_data = [
            ['JNE', 15000, '8 digit (contoh: 12345678)'],
            ['JNT', 12000, '10 digit (contoh: 1012345678)'],
            ['TIKI', 13000, '13 digit (contoh: 1234567890123)'],
            ['POS', 10000, '5+ digit'],
            ['SICEPAT', 11000, 'Alphanumeric 10+ char'],
            ['WAHANA', 12000, '9 digit'],
            ['NINJA', 11000, 'Alphanumeric 9+ char'],
            ['LION', 14000, '10 digit']
        ];

        $stmt = $conn->prepare("INSERT INTO tarif_pengiriman (kurir, biaya_pengiriman, format_resi) VALUES (?, ?, ?)");
        
        foreach ($tarif_data as $data) {
            $stmt->bind_param("sis", $data[0], $data[1], $data[2]);
            if ($stmt->execute()) {
                echo "✅ " . $data[0] . " - Rp " . number_format($data[1], 0) . " ditambahkan\n";
            } else {
                echo "❌ Error insert " . $data[0] . ": " . $stmt->error . "\n";
            }
        }
        $stmt->close();
    } else {
        echo "ℹ️ Data tarif sudah ada (" . $row['cnt'] . " records)\n";
    }

    echo "\n✅ Setup selesai!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

mysqli_close($conn);
?>
