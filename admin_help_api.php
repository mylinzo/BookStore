<?php
header('Content-Type: application/json');
session_start();
include 'koneksi.php';

// Check authentication and admin role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'penjual') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Get message detail
if ($action === 'detail' && $method === 'GET') {
    try {
        $message_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($message_id <= 0) {
            throw new Exception('ID pesan tidak valid');
        }
        
        $query = "SELECT * FROM contact_messages WHERE id_message = ?";
        
        if ($stmt = mysqli_prepare($conn, $query)) {
            mysqli_stmt_bind_param($stmt, "i", $message_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pesan tidak ditemukan']);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception('Error prepare statement: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get statistics
if ($action === 'stats' && $method === 'GET') {
    try {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
                    FROM contact_messages";
        
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }
        
        $stats = mysqli_fetch_assoc($result);
        echo json_encode(['success' => true, 'data' => $stats]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Delete message
if ($action === 'delete' && $method === 'DELETE') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $message_id = $data['id'] ?? 0;
        
        if ($message_id <= 0) {
            throw new Exception('ID pesan tidak valid');
        }
        
        $query = "DELETE FROM contact_messages WHERE id_message = ?";
        
        if ($stmt = mysqli_prepare($conn, $query)) {
            mysqli_stmt_bind_param($stmt, "i", $message_id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Pesan berhasil dihapus']);
            } else {
                throw new Exception('Gagal menghapus pesan');
            }
            
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception('Error prepare statement: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Update message status
if ($action === 'update-status' && $method === 'PUT') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $message_id = $data['id'] ?? 0;
        $status = $data['status'] ?? '';
        
        if ($message_id <= 0 || empty($status)) {
            throw new Exception('Data tidak valid');
        }
        
        $allowed_statuses = ['pending', 'replied', 'resolved', 'closed'];
        if (!in_array($status, $allowed_statuses)) {
            throw new Exception('Status tidak valid');
        }
        
        $query = "UPDATE contact_messages SET status = ? WHERE id_message = ?";
        
        if ($stmt = mysqli_prepare($conn, $query)) {
            mysqli_stmt_bind_param($stmt, "si", $status, $message_id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui']);
            } else {
                throw new Exception('Gagal memperbarui status');
            }
            
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception('Error prepare statement: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Export messages to CSV
if ($action === 'export' && $method === 'GET') {
    try {
        $status = isset($_GET['status']) && $_GET['status'] !== 'all' ? mysqli_real_escape_string($conn, $_GET['status']) : '';
        
        $where = '';
        if (!empty($status)) {
            $where = " WHERE status = '$status'";
        }
        
        $query = "SELECT id_message, name, email, subject, message, category, status, priority, created_at 
                  FROM contact_messages $where 
                  ORDER BY created_at DESC";
        
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }
        
        $messages = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        
        // Set CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="messages_' . date('Y-m-d') . '.csv"');
        
        // Create output
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers
        fputcsv($output, ['ID', 'Nama', 'Email', 'Subject', 'Message', 'Category', 'Status', 'Priority', 'Tanggal']);
        
        // Add data
        foreach ($messages as $message) {
            fputcsv($output, [
                $message['id_message'],
                $message['name'],
                $message['email'],
                $message['subject'],
                $message['message'],
                $message['category'],
                $message['status'],
                $message['priority'],
                $message['created_at']
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Default response
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
?>
