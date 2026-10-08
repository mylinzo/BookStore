<?php
/**
 * API Notifikasi
 * Untuk mendapatkan notifikasi user dan menandai sebagai read
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

session_start();
@include 'koneksi.php';

// Auto create notifikasi table if not exists
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifikasi (
    id_notifikasi INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    tipe VARCHAR(50) DEFAULT 'info',
    pesan TEXT,
    data_ref INT,
    status ENUM('unread', 'read') DEFAULT 'unread',
    url_redirect VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    KEY id_user (id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Fix: Drop and recreate if column missing
$check_col = @mysqli_query($conn, "SELECT tipe FROM notifikasi LIMIT 1");
if (!$check_col) {
    @mysqli_query($conn, "DROP TABLE IF EXISTS notifikasi");
    @mysqli_query($conn, "CREATE TABLE notifikasi (
        id_notifikasi INT PRIMARY KEY AUTO_INCREMENT,
        id_user INT NOT NULL,
        tipe VARCHAR(50) DEFAULT 'info',
        pesan TEXT,
        data_ref INT,
        status ENUM('unread', 'read') DEFAULT 'unread',
        url_redirect VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL,
        KEY id_user (id_user)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Check authentication
if (!isset($_SESSION['id_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id_user = $_SESSION['id_user'];
$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_unread') {
        // Get unread notifications count
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) as total FROM notifikasi 
            WHERE id_user = ? AND status = 'unread'
        ");
        mysqli_stmt_bind_param($stmt, "i", $id_user);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'success' => true,
            'unread_count' => $data['total']
        ]);
        
    } else if ($action === 'get_all') {
        // Get all notifications with limit
        $limit = (int)($_GET['limit'] ?? 10);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $stmt = mysqli_prepare($conn, "
            SELECT id_notifikasi, tipe, pesan, data_ref, status, created_at 
            FROM notifikasi 
            WHERE id_user = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        mysqli_stmt_bind_param($stmt, "iii", $id_user, $limit, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        // Get total count
        $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM notifikasi WHERE id_user = ?");
        mysqli_stmt_bind_param($count_stmt, "i", $id_user);
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $count_data = mysqli_fetch_assoc($count_result);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'total' => $count_data['total']
        ]);
        
    } else if ($action === 'mark_read') {
        // Mark notification as read
        $id_notifikasi = (int)($_POST['id_notifikasi'] ?? 0);
        
        if ($id_notifikasi > 0) {
            $stmt = mysqli_prepare($conn, "
                UPDATE notifikasi 
                SET status = 'read', read_at = NOW()
                WHERE id_notifikasi = ? AND id_user = ?
            ");
            mysqli_stmt_bind_param($stmt, "ii", $id_notifikasi, $id_user);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            } else {
                throw new Exception('Gagal update notifikasi');
            }
        } else {
            throw new Exception('ID notifikasi tidak valid');
        }
        
    } else if ($action === 'mark_all_read') {
        // Mark all notifications as read
        $stmt = mysqli_prepare($conn, "
            UPDATE notifikasi 
            SET status = 'read', read_at = NOW()
            WHERE id_user = ? AND status = 'unread'
        ");
        mysqli_stmt_bind_param($stmt, "i", $id_user);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        } else {
            throw new Exception('Gagal update notifikasi');
        }
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn);
