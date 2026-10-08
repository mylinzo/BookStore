<?php
/**
 * API untuk Get Data Pembeli Terbaru
 * Digunakan untuk real-time update status pembeli di admin dashboard
 */

session_start();
include 'koneksi.php';

// Check authentication - hanya admin/super_admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Get semua pembeli dengan status terbaru
    $sql = "SELECT 
                id_user,
                nama,
                email,
                nik,
                status,
                last_activity,
                created_at,
                role
            FROM users 
            WHERE role = 'pembeli' 
            ORDER BY 
                CASE 
                    WHEN status = 'online' THEN 0
                    ELSE 1
                END,
                last_activity DESC";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    $pembelis = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $pembelis,
        'timestamp' => time()
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
