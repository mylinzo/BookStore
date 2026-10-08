<?php
$conn = mysqli_connect('localhost', 'root', '', 'toko-buku');

if (!$conn) {
    echo "Database connection failed: " . mysqli_connect_error();
    exit;
}

echo "=== ADMIN USERS IN DATABASE ===\n\n";

// Get admin users
$result = mysqli_query($conn, "SELECT id_user, nama, email, role, password FROM users WHERE role='admin' LIMIT 10");

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "ID: " . $row['id_user'] . "\n";
        echo "Nama: " . $row['nama'] . "\n";
        echo "Email: " . $row['email'] . "\n";
        echo "Password (from DB): " . $row['password'] . "\n";
        echo "---\n";
    }
} else {
    echo "❌ No admin users found!\n\n";
    
    // Show all users
    echo "=== ALL USERS IN DATABASE ===\n\n";
    $result = mysqli_query($conn, "SELECT id_user, nama, email, role FROM users LIMIT 10");
    
    if (mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            echo "ID: " . $row['id_user'] . " | Nama: " . $row['nama'] . " | Email: " . $row['email'] . " | Role: " . $row['role'] . "\n";
        }
    } else {
        echo "❌ No users found in database at all!\n";
    }
}

echo "\n=== TIPS ===\n";
echo "1. If no admin users, you can create one manually\n";
echo "2. Use the credentials below to login\n";
echo "3. Try email + password you see above\n";

mysqli_close($conn);
?>
