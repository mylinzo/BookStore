<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=toko-buku", "root", "");
    $stmt = $pdo->prepare("SELECT id_user, nama, role FROM users WHERE role = 'penjual' LIMIT 5");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Penjual tersedia:\n";
    foreach ($users as $u) {
        echo "- ID: " . $u['id_user'] . ", Nama: " . $u['nama'] . ", Role: " . $u['role'] . "\n";
    }

    if (empty($users)) {
        echo "Tidak ada penjual ditemukan. Mencari semua role...\n";
        $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($roles as $r) {
            echo "- Role: " . $r['role'] . ", Count: " . $r['count'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>