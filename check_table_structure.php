<?php
include 'koneksi.php';

if (!$conn) {
    echo "Database connection failed";
    exit;
}

echo "=== TABLE STRUCTURE: users ===\n\n";

// Get table structure
$result = mysqli_query($conn, "DESCRIBE users");

if ($result) {
    echo "Columns in 'users' table:\n";
    echo "=====================================\n";
    while($row = mysqli_fetch_assoc($result)) {
        echo "Column: " . $row['Field'] . "\n";
        echo "Type: " . $row['Type'] . "\n";
        echo "Null: " . $row['Null'] . "\n";
        echo "Key: " . $row['Key'] . "\n";
        echo "Default: " . ($row['Default'] ?? 'NULL') . "\n";
        echo "Extra: " . $row['Extra'] . "\n";
        echo "---\n";
    }
} else {
    echo "Error: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
