<?php
include 'koneksi.php';
require_once 'notificationsHelper.php';

class ChatHandlerPembeli {
    private $conn;
    private $user_id;
    
    public function __construct($conn, $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }
    
    // Send new message to penjual
    public function sendMessage($id_penerima, $pesan, $file = null) {
        $pesan = mysqli_real_escape_string($this->conn, $pesan ?? '');
        $file_path = null;
        $tipe = 'text';
        
        // Handle file upload
        if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_images = ['jpg', 'jpeg', 'png', 'gif'];
            $allowed_files = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
            
            if (in_array($ext, $allowed_images)) {
                $tipe = 'gambar';
            } elseif (in_array($ext, $allowed_files)) {
                $tipe = 'file';
            } else {
                return ['success' => false, 'message' => 'Tipe file tidak didukung'];
            }
            
            $filename = 'chat_' . time() . '_' . $this->user_id . '_' . uniqid() . '.' . $ext;
            $upload_dir = 'uploads/chat/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $file_path = $filename;
            } else {
                return ['success' => false, 'message' => 'Gagal upload file'];
            }
        }
        
        // Validasi: minimal ada pesan atau file
        if (empty($pesan) && empty($file_path)) {
            return ['success' => false, 'message' => 'Pesan atau file harus ada'];
        }
        
        $stmt = mysqli_prepare($this->conn, 
            "INSERT INTO chat (id_pengirim, id_penerima, pesan, tipe, file_path) 
             VALUES (?, ?, ?, ?, ?)"
        );
        
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . mysqli_error($this->conn)];
        }
        
        mysqli_stmt_bind_param($stmt, "iisss", $this->user_id, $id_penerima, $pesan, $tipe, $file_path);
        
        $success = mysqli_stmt_execute($stmt);
        $id_chat = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        
        if ($success) {
            // Create notification for recipient (chat)
            $short = mb_substr($pesan, 0, 200);
            // notify the recipient with proper arguments
            @createNotification(
                $this->conn,
                $id_penerima,           // user_id
                'chat',                 // type
                $id_chat,               // reference id
                'Pesan baru',           // title
                $short                 // message
                // link may be added later if needed
            );
            return [
                'success' => true,
                'id_chat' => $id_chat,
                'message' => 'Pesan berhasil dikirim'
            ];
        }
        
        return ['success' => false, 'message' => 'Gagal mengirim pesan: ' . mysqli_error($this->conn)];
    }
    
    // Mark messages as read
    public function markAsRead($sender_id) {
        $stmt = mysqli_prepare($this->conn, 
            "UPDATE chat SET dibaca = 1 
             WHERE id_pengirim = ? AND id_penerima = ? AND dibaca = 0"
        );
        mysqli_stmt_bind_param($stmt, "ii", $sender_id, $this->user_id);
        $success = mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        return [
            'success' => $success,
            'affected' => $affected
        ];
    }
    
    // Get all penjual for chat (from chat history only)
    public function getPenjualList() {
        $penjual = [];
        
        // Get penjual from chat history only
        $stmt = mysqli_prepare($this->conn, "
            SELECT DISTINCT u.id_user, u.nama, u.email, u.status, u.last_seen, u.is_online
            FROM users u
            WHERE u.id_user IN (
                SELECT DISTINCT id_pengirim FROM chat WHERE id_penerima = ?
                UNION
                SELECT DISTINCT id_penerima FROM chat WHERE id_pengirim = ?
            )
            AND u.role = 'penjual'
            AND u.id_user != ?
            ORDER BY u.nama ASC
        ");
        mysqli_stmt_bind_param($stmt, "iii", $this->user_id, $this->user_id, $this->user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $penjual[$row['id_user']] = $this->enrichPenjualData($row);
        }
        mysqli_stmt_close($stmt);
        
        // Sort by last message time
        uasort($penjual, function($a, $b) {
            $time_a = $a['last_time'] ? strtotime($a['last_time']) : 0;
            $time_b = $b['last_time'] ? strtotime($b['last_time']) : 0;
            return $time_b - $time_a;
        });
        
        return array_values($penjual);
    }
    
    // Enrich penjual data with last message and unread count
    private function enrichPenjualData($penjual) {
        // Get last message
        $stmt = mysqli_prepare($this->conn, "
            SELECT pesan, created_at, id_pengirim, id_penerima 
            FROM chat 
            WHERE (id_pengirim = ? AND id_penerima = ?) 
               OR (id_pengirim = ? AND id_penerima = ?) 
            ORDER BY created_at DESC LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, "iiii", 
            $this->user_id, $penjual['id_user'], 
            $penjual['id_user'], $this->user_id
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $last = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        // Get unread count
        $stmt = mysqli_prepare($this->conn, "
            SELECT COUNT(*) as count 
            FROM chat 
            WHERE id_pengirim = ? AND id_penerima = ? AND dibaca = 0
        ");
        mysqli_stmt_bind_param($stmt, "ii", $penjual['id_user'], $this->user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $unread = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        $penjual['last_message'] = $last ? $last['pesan'] : null;
        $penjual['last_time'] = $last ? $last['created_at'] : null;
        $penjual['unread_count'] = $unread ? (int)$unread['count'] : 0;
        $penjual['last_sender'] = $last ? $last['id_pengirim'] : null;
        $penjual['is_online'] = $penjual['is_online'] ?? ($penjual['status'] == 'online' ? 1 : 0);
        
        return $penjual;
    }
    
    // Get chat messages with specific penjual
    public function getMessages($penjual_id, $last_id = 0) {
        $messages = [];
        
        $stmt = mysqli_prepare($this->conn, "
            SELECT c.*, u.nama as sender_name, u.role as sender_role 
            FROM chat c 
            JOIN users u ON c.id_pengirim = u.id_user 
            WHERE ((c.id_pengirim = ? AND c.id_penerima = ?) 
                OR (c.id_pengirim = ? AND c.id_penerima = ?))
                AND c.id_chat > ?
            ORDER BY c.created_at ASC
        ");
        
        mysqli_stmt_bind_param($stmt, "iiiii", 
            $this->user_id, $penjual_id, 
            $penjual_id, $this->user_id,
            $last_id
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        return $messages;
    }
    
    // Get total unread count
    public function getTotalUnread() {
        $stmt = mysqli_prepare($this->conn, 
            "SELECT COUNT(*) as count FROM chat WHERE id_penerima = ? AND dibaca = 0"
        );
        mysqli_stmt_bind_param($stmt, "i", $this->user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row ? (int)$row['count'] : 0;
    }
    
    // Get unread counts per user
    public function getUnreadCounts() {
        $stmt = mysqli_prepare($this->conn, "
            SELECT id_pengirim as user_id, COUNT(*) as count 
            FROM chat 
            WHERE id_penerima = ? AND dibaca = 0 
            GROUP BY id_pengirim
        ");
        mysqli_stmt_bind_param($stmt, "i", $this->user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $unread_counts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $unread_counts[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        return $unread_counts;
    }
    
    // Get penjual info
    public function getPenjualInfo($penjual_id) {
        $stmt = mysqli_prepare($this->conn, "
            SELECT id_user, nama, email, status, is_online, last_seen, created_at 
            FROM users 
            WHERE id_user = ? AND role = 'penjual'
        ");
        mysqli_stmt_bind_param($stmt, "i", $penjual_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $penjual = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $penjual;
    }
    
    // Check if penjual is online
    public function isOnline($penjual_id) {
        $stmt = mysqli_prepare($this->conn, 
            "SELECT is_online, status FROM users WHERE id_user = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $penjual_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($user) {
            return $user['is_online'] == 1 || $user['status'] == 'online';
        }
        
        return false;
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'pembeli') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $user_id = $_SESSION['id_user'];
    $handler = new ChatHandlerPembeli($conn, $user_id);
    $action = $_GET['action'];
    
    switch ($action) {
        case 'send_message':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id_penerima = intval($_POST['id_penerima'] ?? 0);
                $pesan = $_POST['pesan'] ?? '';
                $file = $_FILES['file_attachment'] ?? null;
                
                if ($id_penerima <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID penerima tidak valid']);
                    exit;
                }
                
                $result = $handler->sendMessage($id_penerima, $pesan, $file);
                echo json_encode($result);
            }
            break;
            
        case 'mark_read':
            $sender_id = intval($_GET['sender'] ?? 0);
            $result = $handler->markAsRead($sender_id);
            echo json_encode($result);
            break;
            
        case 'get_penjual':
            $penjual = $handler->getPenjualList();
            echo json_encode(['success' => true, 'data' => $penjual]);
            break;
            
        case 'get_messages':
            $penjual_id = intval($_GET['user'] ?? 0);
            $last_id = intval($_GET['last_id'] ?? 0);
            $messages = $handler->getMessages($penjual_id, $last_id);
            
            // Mark as read
            if (!empty($messages)) {
                $handler->markAsRead($penjual_id);
            }
            
            // Get user online status
            $is_online = $handler->isOnline($penjual_id);
            $penjual_info = $handler->getPenjualInfo($penjual_id);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'is_online' => $is_online,
                'last_seen' => $penjual_info['last_seen'] ?? null
            ]);
            break;
            
        case 'get_unread_counts':
            $counts = $handler->getUnreadCounts();
            $total = $handler->getTotalUnread();
            echo json_encode([
                'success' => true,
                'counts' => $counts,
                'total' => $total
            ]);
            break;
            
        case 'get_penjual_info':
            $penjual_id = intval($_GET['id'] ?? 0);
            $penjual = $handler->getPenjualInfo($penjual_id);
            echo json_encode($penjual);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
    exit;
}
?>