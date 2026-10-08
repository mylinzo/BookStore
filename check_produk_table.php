<?php
include 'koneksi.php';

if (!$conn) {
    echo "Database connection failed";
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Check Produk Table Structure</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f5f5f5; }
        code { background: #f0f0f0; padding: 2px 5px; }
    </style>
</head>
<body>
    <h1>Struktur Tabel 'produk'</h1>
    
    <?php
    $result = mysqli_query($conn, "DESCRIBE produk");
    
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td><code>" . $row['Field'] . "</code></td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?? '-') . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<h2>Kolom yang Tersedia:</h2>";
        echo "<ul>";
        $result = mysqli_query($conn, "DESCRIBE produk");
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<li>" . $row['Field'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>Tabel 'produk' tidak ditemukan atau error: " . mysqli_error($conn) . "</p>";
    }
    
    mysqli_close($conn);
    ?>
</body>
</html>
