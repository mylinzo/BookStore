<?php
/**
 * API untuk mendapatkan data produk dari penjual
 * Digunakan oleh dashboard pembeli untuk menampilkan produk
 */

header('Content-Type: application/json; charset=utf-8');

// Error handling - output errors as JSON
ini_set('display_errors', 0);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $errstr
    ]);
    exit;
});

set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $exception->getMessage()
    ]);
    exit;
});

// Koneksi database
@include 'koneksi.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

try {
    // Check if produk table exists
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'produk'");
    
    if (!$check_table || mysqli_num_rows($check_table) === 0) {
        throw new Exception("Tabel produk belum ada. Silakan jalankan: setup_produk.php");
    }
    
    // Get parameters
    $kategori_id = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // Base query
    $query = "SELECT p.*, u.nama as nama_penjual, k.nama_kategori
              FROM produk p
              LEFT JOIN users u ON p.id_penjual = u.id_user
              LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
              WHERE p.stok > 0";
    
    // Filter by kategori
    if ($kategori_id) {
        $query .= " AND p.id_kategori = " . intval($kategori_id);
    }
    
    // Search
    if ($search) {
        $search_term = mysqli_real_escape_string($conn, $search);
        $query .= " AND (p.nama_buku LIKE '%$search_term%' OR p.deskripsi LIKE '%$search_term%' OR u.nama LIKE '%$search_term%')";
    }
    
    // Order by latest
    $query .= " ORDER BY p.created_at DESC";
    
    // Count total
    $count_query = "SELECT COUNT(*) as total FROM (" . $query . ") as count_table";
    $count_result = mysqli_query($conn, $count_query);
    
    if (!$count_result) {
        throw new Exception("Error counting products: " . mysqli_error($conn));
    }
    
    $count_row = mysqli_fetch_assoc($count_result);
    $total_products = $count_row['total'] ?? 0;
    
    // Pagination
    $query .= " LIMIT $limit OFFSET $offset";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception("Query error: " . mysqli_error($conn));
    }
    
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = [
            'id_produk' => (int)$row['id_produk'],
            'nama_buku' => $row['nama_buku'],
            'harga' => (float)$row['harga'],
            'stok' => (int)$row['stok'],
            'deskripsi' => $row['deskripsi'],
            'foto_produk' => $row['foto_produk'],
            'nama_penjual' => $row['nama_penjual'],
            'id_penjual' => (int)$row['id_penjual'],
            'nama_kategori' => $row['nama_kategori'],
            'modal' => (float)$row['modal'],
        ];
    }
    
    // Response
    echo json_encode([
        'success' => true,
        'data' => $products,
        'pagination' => [
            'total' => $total_products,
            'limit' => $limit,
            'offset' => $offset,
            'pages' => ceil($total_products / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
