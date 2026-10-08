<?php
session_start();
include 'koneksi.php';
require_once 'notificationsHelper.php';

// Check user login and role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'penjual') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id_user'];
$user_name = $_SESSION['nama'];

// Handle sending new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $id_penerima = $_POST['id_penerima'];
    $pesan = mysqli_real_escape_string($conn, $_POST['pesan']);
    
    // Handle file upload
    $file_path = null;
    $tipe = 'text';
    
    if (isset($_FILES['file_attachment']) && $_FILES['file_attachment']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['file_attachment']['name'], PATHINFO_EXTENSION));
        $allowed_images = ['jpg', 'jpeg', 'png', 'gif'];
        $allowed_files = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        
        if (in_array($ext, $allowed_images)) {
            $tipe = 'gambar';
        } elseif (in_array($ext, $allowed_files)) {
            $tipe = 'file';
        }
        
        $filename = 'chat_' . time() . '_' . $user_id . '_' . uniqid() . '.' . $ext;
        $upload_dir = 'uploads/chat/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        move_uploaded_file($_FILES['file_attachment']['tmp_name'], $upload_dir . $filename);
        $file_path = $filename;
    }
    
    $stmt = mysqli_prepare($conn, "INSERT INTO chat (id_pengirim, id_penerima, pesan, tipe, file_path) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iisss", $user_id, $id_penerima, $pesan, $tipe, $file_path);
    mysqli_stmt_execute($stmt);
    $id_chat = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Create notification for recipient (proper parameter order)
    $short = mb_substr($pesan, 0, 200);
    // title could be short preview or generic text
    $title = 'Pesan baru dari ' . ($_SESSION['nama'] ?? 'Pembeli');
    @createNotification(
        $conn,
        intval($id_penerima),    // user_id (receiver)
        'chat',                  // type
        $id_chat,                // reference_id
        $title,
        $short,
        'penjual_chat.php?user=' . intval($id_penerima)
    );
    
    header("Location: penjual_chat.php?user=" . $id_penerima);
    exit;
}

// Handle marking messages as read
if (isset($_GET['mark_read']) && isset($_GET['sender'])) {
    $sender_id = intval($_GET['sender']);
    $stmt_mark = mysqli_prepare($conn, "UPDATE chat SET dibaca = 1 WHERE id_pengirim = ? AND id_penerima = ?");
    mysqli_stmt_bind_param($stmt_mark, "ii", $sender_id, $user_id);
    mysqli_stmt_execute($stmt_mark);
    mysqli_stmt_close($stmt_mark);
    echo json_encode(['success' => true]);
    exit;
}

// TANPA ORDERS - Ambil semua pembeli yang pernah chat dengan penjual ini
$stmt_chat_users = mysqli_prepare($conn, "
    SELECT DISTINCT u.id_user, u.nama, u.email, u.status, u.last_seen, u.is_online
    FROM users u
    WHERE u.id_user IN (
        SELECT DISTINCT id_pengirim FROM chat WHERE id_penerima = ?
        UNION
        SELECT DISTINCT id_penerima FROM chat WHERE id_pengirim = ?
    )
    AND u.role = 'pembeli'
    AND u.id_user != ?
    ORDER BY u.nama ASC
");
mysqli_stmt_bind_param($stmt_chat_users, "iii", $user_id, $user_id, $user_id);
mysqli_stmt_execute($stmt_chat_users);
$chat_users_result = mysqli_stmt_get_result($stmt_chat_users);

// Juga ambil semua pembeli yang mungkin belum pernah chat
$stmt_all_pembeli = mysqli_prepare($conn, "
    SELECT id_user, nama, email, status, last_seen, is_online
    FROM users 
    WHERE role = 'pembeli' 
    AND id_user NOT IN (
        SELECT DISTINCT id_pengirim FROM chat WHERE id_penerima = ?
        UNION
        SELECT DISTINCT id_penerima FROM chat WHERE id_pengirim = ?
    )
    ORDER BY nama ASC
");
mysqli_stmt_bind_param($stmt_all_pembeli, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt_all_pembeli);
$all_pembeli_result = mysqli_stmt_get_result($stmt_all_pembeli);

// Gabungkan hasil
$chat_partners = [];

// Add users who have chatted
while ($row = mysqli_fetch_assoc($chat_users_result)) {
    $chat_partners[$row['id_user']] = $row;
}

// Add users who haven't chatted
while ($row = mysqli_fetch_assoc($all_pembeli_result)) {
    $chat_partners[$row['id_user']] = $row;
}

// Get last message and unread count for each partner
foreach ($chat_partners as $id => $partner) {
    // Get last message
    $stmt_last = mysqli_prepare($conn, "
        SELECT pesan, created_at, id_pengirim, id_penerima 
        FROM chat 
        WHERE (id_pengirim = ? AND id_penerima = ?) 
           OR (id_pengirim = ? AND id_penerima = ?) 
        ORDER BY created_at DESC LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt_last, "iiii", $user_id, $partner['id_user'], $partner['id_user'], $user_id);
    mysqli_stmt_execute($stmt_last);
    $last_message = mysqli_stmt_get_result($stmt_last);
    $last = mysqli_fetch_assoc($last_message);
    mysqli_stmt_close($stmt_last);
    
    // Get unread count
    $stmt_unread = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM chat WHERE id_pengirim = ? AND id_penerima = ? AND dibaca = 0");
    mysqli_stmt_bind_param($stmt_unread, "ii", $partner['id_user'], $user_id);
    mysqli_stmt_execute($stmt_unread);
    $unread_result = mysqli_stmt_get_result($stmt_unread);
    $unread = mysqli_fetch_assoc($unread_result);
    mysqli_stmt_close($stmt_unread);
    
    $chat_partners[$id]['last_message'] = $last ? $last['pesan'] : null;
    $chat_partners[$id]['last_time'] = $last ? $last['created_at'] : null;
    $chat_partners[$id]['unread_count'] = $unread ? $unread['count'] : 0;
    $chat_partners[$id]['last_sender'] = $last ? $last['id_pengirim'] : null;
}

// Sort by last message time
uasort($chat_partners, function($a, $b) {
    $time_a = $a['last_time'] ? strtotime($a['last_time']) : 0;
    $time_b = $b['last_time'] ? strtotime($b['last_time']) : 0;
    return $time_b - $time_a;
});

// Get selected user chat
$selected_user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
$selected_user = null;
$chat_messages = [];

if ($selected_user_id > 0) {
    // Get user info
    $stmt_user = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt_user, "i", $selected_user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    $selected_user = mysqli_fetch_assoc($result_user);
    mysqli_stmt_close($stmt_user);
    
    if ($selected_user) {
        // Get chat messages with this user
        $stmt_messages = mysqli_prepare($conn, "
            SELECT c.*, u.nama as sender_name, u.role as sender_role 
            FROM chat c 
            JOIN users u ON c.id_pengirim = u.id_user 
            WHERE (c.id_pengirim = ? AND c.id_penerima = ?) 
               OR (c.id_pengirim = ? AND c.id_penerima = ?) 
            ORDER BY c.created_at ASC
        ");
        mysqli_stmt_bind_param($stmt_messages, "iiii", $user_id, $selected_user_id, $selected_user_id, $user_id);
        mysqli_stmt_execute($stmt_messages);
        $chat_messages = mysqli_stmt_get_result($stmt_messages);
    }
}

// Count total unread messages
$stmt_total_unread = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM chat WHERE id_penerima = ? AND dibaca = 0");
mysqli_stmt_bind_param($stmt_total_unread, "i", $user_id);
mysqli_stmt_execute($stmt_total_unread);
$result_total_unread = mysqli_stmt_get_result($stmt_total_unread);
$total_unread = mysqli_fetch_assoc($result_total_unread);
mysqli_stmt_close($stmt_total_unread);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 Chat dengan Pembeli - Aksara-Aruna</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* [CSS SAMA SEPERTI SEBELUMNYA, TIDAK DIUBAH] */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --secondary: #64748b;
            --success: #22c55e;
            --success-bg: #f0fdf4;
            --error: #ef4444;
            --error-bg: #fef2f2;
            --warning: #f59e0b;
            --warning-bg: #fffbeb;
            --info: #3b82f6;
            --info-bg: #eff6ff;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            /* legacy vintage variables mapped */
            --vintage-brown: var(--primary);
            --vintage-gold: var(--info);
            --vintage-cream: var(--background);
            --vintage-dark: var(--text-primary);
            --vintage-red: var(--error);
            --vintage-green: var(--success);
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
            height: 100vh;
            overflow: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
        }

        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--vintage-dark);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .unread-badge-header {
            background: var(--vintage-gold);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .chat-container {
            display: flex;
            flex: 1;
            gap: 20px;
            min-height: 0;
        }

        .chat-sidebar {
            width: 350px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #ecf0f1;
        }

        .sidebar-header h2 {
            color: var(--vintage-brown);
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 30px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--vintage-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }

        .chat-list {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .chat-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 1rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 10px;
            position: relative;
        }

        .chat-item:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .chat-item.active {
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(139, 69, 19, 0.1));
            border-left: 4px solid var(--vintage-gold);
        }

        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--vintage-brown), var(--vintage-gold));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .chat-info {
            flex: 1;
            min-width: 0;
        }

        .chat-name-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .chat-name {
            font-weight: 600;
            color: var(--vintage-brown);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-time {
            font-size: 0.75rem;
            color: #95a5a6;
        }

        .chat-preview {
            font-size: 0.85rem;
            color: #7f8c8d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-badge {
            background: var(--vintage-gold);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
        }

        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chat-user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--vintage-brown), var(--vintage-gold));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .chat-user-details h3 {
            color: var(--vintage-brown);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .chat-user-details p {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .online-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-online {
            background: var(--vintage-green);
            box-shadow: 0 0 0 2px rgba(46, 204, 113, 0.2);
        }

        .status-offline {
            background: #95a5a6;
        }

        .chat-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            background: #f8f9fa;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            color: var(--vintage-brown);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .action-btn:hover {
            background: #e9ecef;
            color: var(--vintage-gold);
            transform: scale(1.1);
        }

        .messages-container {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .date-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }

        .date-divider span {
            background: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #7f8c8d;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .message {
            display: flex;
            max-width: 70%;
            animation: fadeIn 0.3s ease;
        }

        .message.sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message.received {
            align-self: flex-start;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .message.sent .message-avatar {
            background: linear-gradient(135deg, var(--vintage-brown), var(--vintage-gold));
            color: white;
            margin-left: 10px;
        }

        .message.received .message-avatar {
            margin-right: 10px;
        }

        .message-content {
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .message.sent .message-content {
            background: linear-gradient(135deg, var(--vintage-brown), var(--vintage-dark));
            color: white;
            border-radius: 15px 15px 0 15px;
        }

        .message.received .message-content {
            border-radius: 15px 15px 15px 0;
        }

        .message-text {
            line-height: 1.5;
            margin-bottom: 8px;
            word-wrap: break-word;
        }

        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
        }

        .message.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
        }

        .message.received .message-time {
            color: #95a5a6;
        }

        .attachment {
            margin-top: 10px;
            max-width: 300px;
        }

        .attachment.image img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .attachment.image img:hover {
            transform: scale(1.05);
        }

        .attachment.file {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
        }

        .attachment.file:hover {
            background: #e9ecef;
        }

        .message.sent .attachment.file {
            background: rgba(255, 255, 255, 0.2);
        }

        .message.sent .attachment.file:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .file-icon {
            width: 40px;
            height: 40px;
            background: var(--vintage-brown);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .message.sent .file-icon {
            background: white;
            color: var(--vintage-brown);
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 3px;
            word-break: break-all;
        }

        .file-size {
            font-size: 0.75rem;
            color: #95a5a6;
        }

        .message.sent .file-size {
            color: rgba(255, 255, 255, 0.8);
        }

        .message-input-container {
            padding: 1.5rem;
            border-top: 1px solid #ecf0f1;
            background: white;
        }

        .input-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .attachment-btn {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            color: var(--vintage-brown);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s;
        }

        .attachment-btn:hover {
            background: #e9ecef;
            border-color: var(--vintage-gold);
            color: var(--vintage-gold);
        }

        .message-input {
            flex: 1;
            position: relative;
        }

        .message-input textarea {
            width: 100%;
            padding: 15px 60px 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 0.95rem;
            font-family: inherit;
            resize: none;
            max-height: 120px;
            min-height: 50px;
            transition: all 0.3s;
        }

        .message-input textarea:focus {
            outline: none;
            border-color: var(--vintage-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .send-btn {
            position: absolute;
            right: 8px;
            bottom: 8px;
            background: var(--vintage-brown);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .send-btn:hover {
            background: var(--vintage-dark);
            transform: scale(1.1);
        }

        .send-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }

        .attachment-preview {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .preview-item {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .preview-item img {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            object-fit: cover;
        }

        .preview-info {
            flex: 1;
            min-width: 0;
        }

        .preview-name {
            font-size: 0.9rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
        }

        .preview-size {
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        .remove-preview {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 5px;
            transition: all 0.3s;
        }

        .remove-preview:hover {
            transform: scale(1.2);
        }

        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            color: #7f8c8d;
            text-align: center;
        }

        .empty-state i {
            font-size: 5rem;
            color: #e0e0e0;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .typing-indicator {
            display: none;
            padding: 10px 20px;
            background: white;
            border-radius: 20px;
            margin-top: 10px;
            font-size: 0.85rem;
            color: #7f8c8d;
            align-self: flex-start;
        }

        .typing-indicator span {
            display: inline-block;
            animation: typing 1.5s infinite;
        }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }

        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            background: none;
            border: none;
            color: white;
            font-size: 2.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .close-modal:hover {
            transform: scale(1.2);
        }

        @media (max-width: 992px) {
            .chat-container {
                flex-direction: column;
            }
            
            .chat-sidebar {
                width: 100%;
                height: 300px;
            }
            
            .chat-main {
                flex: 1;
            }
            
            .message {
                max-width: 85%;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
                height: calc(100vh - 20px);
            }
            
            .header h1 {
                font-size: 1.4rem;
            }
            
            .messages-container {
                padding: 1rem;
            }
            
            .message {
                max-width: 90%;
            }
        }

        @media (max-width: 576px) {
            .chat-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .chat-actions {
                align-self: flex-end;
            }
            
            .input-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .attachment-btn {
                align-self: flex-start;
            }
        }

        .chat-list::-webkit-scrollbar,
        .messages-container::-webkit-scrollbar {
            width: 6px;
        }

        .chat-list::-webkit-scrollbar-track,
        .messages-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .chat-list::-webkit-scrollbar-thumb,
        .messages-container::-webkit-scrollbar-thumb {
            background: #bdc3c7;
            border-radius: 3px;
        }

        .chat-list::-webkit-scrollbar-thumb:hover,
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-comments"></i> 
                Chat dengan Pembeli
                <?php if ($total_unread['count'] > 0): ?>
                <span class="unread-badge-header">
                    <?php echo $total_unread['count']; ?> pesan baru
                </span>
                <?php endif; ?>
            </h1>
            <a href="penjual_dashboard.php" class="action-btn" title="Kembali ke Dashboard">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>

        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Sidebar - Chat List -->
            <div class="chat-sidebar">
                <div class="sidebar-header">
                    <h2><i class="fas fa-users"></i> Daftar Pembeli</h2>
                    <div class="search-box">
                        <input type="text" placeholder="Cari pembeli..." id="searchChat">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                
                <div class="chat-list" id="chatList">
                    <?php if (!empty($chat_partners)): ?>
                        <?php foreach ($chat_partners as $partner): 
                            $is_active = $selected_user_id == $partner['id_user'];
                            $last_time = $partner['last_time'] ? date('H:i', strtotime($partner['last_time'])) : '';
                            $last_message = $partner['last_message'] ? htmlspecialchars(substr($partner['last_message'], 0, 50)) : 'Belum ada pesan';
                            if ($partner['last_sender'] == $user_id) {
                                $last_message = 'Anda: ' . $last_message;
                            }
                            
                            // Tentukan status online/offline
                            $is_online = false;
                            if (isset($partner['is_online']) && $partner['is_online'] == 1) {
                                $is_online = true;
                            } elseif (isset($partner['status']) && $partner['status'] == 'online') {
                                $is_online = true;
                            }
                        ?>
                        <div class="chat-item <?php echo $is_active ? 'active' : ''; ?>" 
                             onclick="window.location.href='penjual_chat.php?user=<?php echo $partner['id_user']; ?>'">
                            <div class="chat-avatar">
                                <?php echo strtoupper(substr($partner['nama'], 0, 1)); ?>
                            </div>
                            <div class="chat-info">
                                <div class="chat-name-row">
                                    <div class="chat-name">
                                        <?php echo htmlspecialchars($partner['nama']); ?>
                                        <?php if ($is_online): ?>
                                        <i class="fas fa-circle" style="color: #2ecc71; font-size: 0.6rem; margin-left: 5px;" title="Online"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chat-time"><?php echo $last_time; ?></div>
                                </div>
                                <div class="chat-preview">
                                    <?php echo $last_message; ?>
                                </div>
                            </div>
                            <?php if ($partner['unread_count'] > 0): ?>
                            <div class="unread-badge"><?php echo $partner['unread_count']; ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 2rem;">
                            <i class="fas fa-user-friends" style="font-size: 3rem;"></i>
                            <p style="margin-top: 1rem;">Belum ada pembeli</p>
                            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Pembeli akan muncul setelah mereka chat dengan Anda</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="chat-main">
                <?php if ($selected_user): ?>
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="chat-user-info">
                        <div class="chat-user-avatar">
                            <?php echo strtoupper(substr($selected_user['nama'], 0, 1)); ?>
                        </div>
                        <div class="chat-user-details">
                            <h3><?php echo htmlspecialchars($selected_user['nama']); ?></h3>
                            <p><?php echo $selected_user['email']; ?></p>
                            <div class="online-status">
                                <?php 
                                $is_online = false;
                                if (isset($selected_user['is_online']) && $selected_user['is_online'] == 1) {
                                    $is_online = true;
                                } elseif (isset($selected_user['status']) && $selected_user['status'] == 'online') {
                                    $is_online = true;
                                }
                                ?>
                                <div class="status-dot <?php echo $is_online ? 'status-online' : 'status-offline'; ?>"></div>
                                <span><?php echo $is_online ? 'Online' : 'Offline'; ?></span>
                                <?php if (!$is_online && isset($selected_user['last_seen'])): ?>
                                <span style="font-size: 0.7rem; color: #95a5a6; margin-left: 5px;">
                                    • <?php echo time_elapsed_string($selected_user['last_seen']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-actions">
                        <button class="action-btn" title="Lihat Profil" onclick="showUserInfo(<?php echo $selected_user['id_user']; ?>)">
                            <i class="fas fa-user"></i>
                        </button>
                        <button class="action-btn" title="Segarkan" onclick="refreshChat()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages Container -->
                <div class="messages-container" id="messagesContainer">
                    <?php 
                    $current_date = null;
                    if (mysqli_num_rows($chat_messages) > 0):
                        mysqli_data_seek($chat_messages, 0);
                        while ($message = mysqli_fetch_assoc($chat_messages)): 
                            $message_date = date('Y-m-d', strtotime($message['created_at']));
                            $is_sent = $message['id_pengirim'] == $user_id;
                            
                            if ($current_date != $message_date):
                                $current_date = $message_date;
                                $date_label = date('d M Y', strtotime($message_date));
                                if ($date_label == date('d M Y')) {
                                    $date_label = 'Hari Ini';
                                } elseif ($date_label == date('d M Y', strtotime('-1 day'))) {
                                    $date_label = 'Kemarin';
                                }
                    ?>
                    <div class="date-divider">
                        <span><?php echo $date_label; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="message <?php echo $is_sent ? 'sent' : 'received'; ?>" id="message-<?php echo $message['id_chat']; ?>">
                        <div class="message-avatar">
                            <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                        </div>
                        <div class="message-content">
                            <?php if ($message['tipe'] == 'text'): ?>
                                <div class="message-text"><?php echo nl2br(htmlspecialchars($message['pesan'])); ?></div>
                            
                            <?php elseif ($message['tipe'] == 'gambar'): ?>
                                <?php if (!empty($message['pesan'])): ?>
                                <div class="message-text"><?php echo nl2br(htmlspecialchars($message['pesan'])); ?></div>
                                <?php endif; ?>
                                <div class="attachment image">
                                    <img src="uploads/chat/<?php echo htmlspecialchars($message['file_path']); ?>" 
                                         alt="Gambar" 
                                         onclick="showImageModal(this.src)"
                                         loading="lazy">
                                </div>
                            
                            <?php elseif ($message['tipe'] == 'file'): ?>
                                <?php if (!empty($message['pesan'])): ?>
                                <div class="message-text"><?php echo nl2br(htmlspecialchars($message['pesan'])); ?></div>
                                <?php endif; ?>
                                <a href="uploads/chat/<?php echo htmlspecialchars($message['file_path']); ?>" 
                                   class="attachment file" 
                                   download
                                   target="_blank">
                                    <div class="file-icon">
                                        <i class="fas fa-file"></i>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-name"><?php echo htmlspecialchars(basename($message['file_path'])); ?></div>
                                        <?php 
                                        $file_path = 'uploads/chat/' . $message['file_path'];
                                        if (file_exists($file_path)):
                                        ?>
                                        <div class="file-size"><?php echo formatFileSize(filesize($file_path)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endif; ?>
                            
                            <div class="message-time">
                                <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                <?php if ($is_sent): ?>
                                    <?php if ($message['dibaca']): ?>
                                    <i class="fas fa-check-double" style="color: #4CAF50;" title="Telah dibaca"></i>
                                    <?php else: ?>
                                    <i class="fas fa-check" title="Terkirim"></i>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-dots"></i>
                        <h3>Mulai Percakapan</h3>
                        <p>Kirim pesan pertama kepada <?php echo htmlspecialchars($selected_user['nama']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Typing Indicator -->
                    <div class="typing-indicator" id="typingIndicator">
                        <span>.</span><span>.</span><span>.</span> sedang mengetik
                    </div>
                </div>

                <!-- Message Input -->
                <div class="message-input-container">
                    <form id="messageForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id_penerima" value="<?php echo $selected_user_id; ?>">
                        <input type="hidden" name="send_message" value="1">
                        
                        <!-- Attachment Preview -->
                        <div class="attachment-preview" id="attachmentPreview"></div>
                        
                        <div class="input-form">
                            <button type="button" class="attachment-btn" onclick="document.getElementById('fileInput').click()" title="Lampirkan file">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <input type="file" id="fileInput" name="file_attachment" style="display: none;" onchange="handleFileSelect(event)" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                            
                            <div class="message-input">
                                <textarea name="pesan" id="messageText" placeholder="Ketik pesan..." rows="1"></textarea>
                                <button type="submit" class="send-btn" id="sendButton">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <!-- Empty Chat Selection -->
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>Pilih Percakapan</h3>
                    <p>Pilih pembeli dari daftar untuk memulai percakapan</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="image-modal" id="imageModal" onclick="closeImageModal()">
        <button class="close-modal" onclick="closeImageModal(event)">&times;</button>
        <img id="modalImage" src="" alt="">
    </div>

    <script>
        let messageCheckInterval = null;
        let isCheckingMessages = false;
        let lastMessageCount = 0;
        const selectedUserId = <?php echo $selected_user_id ?: 0; ?>;
        
        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const container = document.getElementById('messagesContainer');
            if (container) {
                container.scrollTo({
                    top: container.scrollHeight,
                    behavior: 'smooth'
                });
            }
        }
        
        // Scroll to bottom on page load
        window.addEventListener('load', function() {
            setTimeout(scrollToBottom, 100);
            
            // Mulai polling hanya jika ada user yang dipilih
            if (selectedUserId > 0) {
                startMessagePolling();
                // Mark as read setelah 1.5 detik (tunggu UI load)
                setTimeout(markMessagesAsRead, 1500);
            }
        });
        
        // Auto-resize textarea
        const textarea = document.getElementById('messageText');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }
        
        // Handle file selection
        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Check file size (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('Ukuran file maksimal 10MB');
                document.getElementById('fileInput').value = '';
                return;
            }
            
            const preview = document.getElementById('attachmentPreview');
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (file.type.startsWith('image/')) {
                    preview.innerHTML = `
                        <div class="preview-item">
                            <img src="${e.target.result}" alt="Preview">
                            <div class="preview-info">
                                <div class="preview-name">${file.name}</div>
                                <div class="preview-size">${formatBytes(file.size)}</div>
                            </div>
                            <button type="button" class="remove-preview" onclick="removeAttachment()">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>
                    `;
                } else {
                    let icon = 'fa-file';
                    if (file.name.match(/\.(pdf)$/i)) icon = 'fa-file-pdf';
                    else if (file.name.match(/\.(doc|docx)$/i)) icon = 'fa-file-word';
                    else if (file.name.match(/\.(xls|xlsx)$/i)) icon = 'fa-file-excel';
                    else if (file.name.match(/\.(txt)$/i)) icon = 'fa-file-alt';
                    
                    preview.innerHTML = `
                        <div class="preview-item">
                            <div class="file-icon">
                                <i class="fas ${icon}"></i>
                            </div>
                            <div class="preview-info">
                                <div class="preview-name">${file.name}</div>
                                <div class="preview-size">${formatBytes(file.size)}</div>
                            </div>
                            <button type="button" class="remove-preview" onclick="removeAttachment()">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>
                    `;
                }
                preview.style.display = 'flex';
            };
            
            if (file.type.startsWith('image/')) {
                reader.readAsDataURL(file);
            } else {
                reader.readAsArrayBuffer(file);
                setTimeout(() => {
                    preview.innerHTML = preview.innerHTML;
                }, 100);
            }
        }
        
        function removeAttachment() {
            document.getElementById('fileInput').value = '';
            document.getElementById('attachmentPreview').innerHTML = '';
            document.getElementById('attachmentPreview').style.display = 'none';
        }
        
        // Image modal functions
        function showImageModal(src) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modalImg.src = src;
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeImageModal(event) {
            if (event) event.stopPropagation();
            const modal = document.getElementById('imageModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
        
        // Format file size
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
        // Real-time chat updates dengan debouncing
        function checkNewMessages() {
            if (!selectedUserId) return;
            if (isCheckingMessages) return; // Cegah request ganda
            
            isCheckingMessages = true;
            
            fetch(`get_new_messages_penjual.php?user=${selectedUserId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.messages && data.messages.length > 0) {
                        const newMessages = data.messages.filter(msg => 
                            !document.getElementById(`message-${msg.id_chat}`)
                        );
                        
                        if (newMessages.length > 0) {
                            newMessages.forEach(message => {
                                addMessageToChat(message);
                            });
                            scrollToBottom();
                            lastMessageCount = data.messages.length;
                            
                            // Tandai pesan sebagai dibaca otomatis
                            markMessagesAsRead();
                        }
                    }
                })
                .catch(error => console.error('Error checking new messages:', error))
                .finally(() => {
                    isCheckingMessages = false;
                });
        }
        
        function addMessageToChat(message) {
            const container = document.getElementById('messagesContainer');
            if (!container) return;
            
            // Cek apakah pesan sudah ada
            if (document.getElementById(`message-${message.id_chat}`)) return;
            
            const isSent = message.id_pengirim == <?php echo $user_id; ?>;
            const messageHtml = `
                <div class="message ${isSent ? 'sent' : 'received'}" id="message-${message.id_chat}">
                    <div class="message-avatar">
                        ${message.sender_name.charAt(0).toUpperCase()}
                    </div>
                    <div class="message-content">
                        ${message.pesan ? `<div class="message-text">${escapeHtml(message.pesan).replace(/\n/g, '<br>')}</div>` : ''}
                        ${message.file_path ? generateAttachmentHtml(message) : ''}
                        <div class="message-time">
                            ${formatTime(message.created_at)}
                            ${isSent ? '<i class="fas fa-check" title="Terkirim"></i>' : ''}
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', messageHtml);
        }
        
        function generateAttachmentHtml(message) {
            if (message.tipe === 'gambar') {
                return `
                    <div class="attachment image">
                        <img src="uploads/chat/${escapeHtml(message.file_path)}" 
                             alt="Gambar" 
                             onclick="showImageModal(this.src)"
                             loading="lazy">
                    </div>
                `;
            } else if (message.tipe === 'file') {
                return `
                    <a href="uploads/chat/${escapeHtml(message.file_path)}" 
                       class="attachment file" 
                       download
                       target="_blank">
                        <div class="file-icon">
                            <i class="fas fa-file"></i>
                        </div>
                        <div class="file-info">
                            <div class="file-name">${escapeHtml(message.file_path.split('/').pop())}</div>
                        </div>
                    </a>
                `;
            }
            return '';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }
        
        let isUpdatingCounts = false;
        function updateUnreadCounts() {
            if (isUpdatingCounts) return;
            if (!selectedUserId) return; // Jangan update jika tidak ada user yang dipilih
            
            isUpdatingCounts = true;
            
            fetch('get_unread_counts_penjual.php')
                .then(response => response.json())
                .then(data => {
                    let totalUnread = 0;
                    
                    data.forEach(item => {
                        // Jangan update badge untuk chat yang sedang dibuka
                        if (item.user_id == selectedUserId) {
                            return;
                        }
                        
                        totalUnread += item.count;
                        const chatItem = document.querySelector(`.chat-item[onclick*="user=${item.user_id}"]`);
                        if (chatItem) {
                            const badge = chatItem.querySelector('.unread-badge');
                            if (item.count > 0) {
                                if (badge) {
                                    badge.textContent = item.count;
                                } else {
                                    chatItem.insertAdjacentHTML('beforeend', `<div class="unread-badge">${item.count}</div>`);
                                }
                            } else if (badge) {
                                badge.remove();
                            }
                        }
                    });
                })
                .catch(error => console.error('Error updating counts:', error))
                .finally(() => {
                    isUpdatingCounts = false;
                });
        }
        
        // Mulai polling pesan
        function startMessagePolling() {
            if (messageCheckInterval) {
                clearInterval(messageCheckInterval);
            }
            // Polling setiap 8 detik (lebih lama untuk mengurangi spam)
            messageCheckInterval = setInterval(checkNewMessages, 8000);
        }
        
        // Hentikan polling
        function stopMessagePolling() {
            if (messageCheckInterval) {
                clearInterval(messageCheckInterval);
                messageCheckInterval = null;
            }
        }
        
        // Refresh chat manually
        function refreshChat() {
            checkNewMessages();
            showNotification('Percakapan disegarkan');
        }
        
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--primary);
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.2);
                z-index: 9999;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Search chat
        document.getElementById('searchChat')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const chatItems = document.querySelectorAll('.chat-item');
            
            chatItems.forEach(item => {
                const name = item.querySelector('.chat-name').textContent.toLowerCase();
                if (name.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Handle chat item click
        document.querySelectorAll('.chat-item').forEach(item => {
            item.addEventListener('click', function() {
                // Tandai pesan sebagai dibaca setelah 500ms (saat navigasi)
                setTimeout(markMessagesAsRead, 500);
            });
        });
        
        // Show user info
        function showUserInfo(userId) {
            fetch(`get_user_info.php?id=${userId}`)
                .then(response => response.json())
                .then(user => {
                    const lastSeen = user.last_seen ? new Date(user.last_seen).toLocaleString('id-ID') : 'Tidak diketahui';
                    const info = `
                        📋 INFORMASI PEMBELI
                        
                        Nama: ${user.nama}
                        Email: ${user.email}
                        Status: ${user.is_online == 1 ? '🟢 Online' : '⚫ Offline'}
                        Terakhir Online: ${lastSeen}
                        Terdaftar: ${new Date(user.created_at).toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric'
                        })}
                    `;
                    alert(info);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Gagal memuat informasi pengguna');
                });
        }
        
        // Form validation
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            const messageText = document.getElementById('messageText').value.trim();
            const fileInput = document.getElementById('fileInput').files[0];
            
            if (!messageText && !fileInput) {
                e.preventDefault();
                alert('Silakan tulis pesan atau lampirkan file');
            }
        });
        
        let isMarkingAsRead = false;
        // Auto-mark as read when viewing messages
        function markMessagesAsRead() {
            if (!selectedUserId || isMarkingAsRead) return;
            
            isMarkingAsRead = true;
            
            fetch(`penjual_chat.php?mark_read=1&sender=${selectedUserId}`)
                .then(() => {
                    const unreadBadge = document.querySelector(`.chat-item.active .unread-badge`);
                    if (unreadBadge) unreadBadge.remove();
                })
                .catch(error => console.error('Error marking as read:', error))
                .finally(() => {
                    isMarkingAsRead = false;
                });
        }
        
        // Mark messages as read when scrolling near bottom
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer) {
            let markReadTimeout;
            messagesContainer.addEventListener('scroll', function() {
                clearTimeout(markReadTimeout);
                markReadTimeout = setTimeout(() => {
                    const isNearBottom = this.scrollHeight - this.scrollTop - this.clientHeight < 100;
                    if (isNearBottom) {
                        markMessagesAsRead();
                    }
                }, 500);
            });
        }
        
        // Hentikan polling saat keluar halaman
        window.addEventListener('beforeunload', stopMessagePolling);
    </script>
</body>
</html>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Helper function to format time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    // Calculate weeks without adding dynamic property to DateInterval
    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);
    
    $string = array();
    
    if ($diff->y > 0) $string['y'] = $diff->y . ' tahun';
    if ($diff->m > 0) $string['m'] = $diff->m . ' bulan';
    if ($weeks > 0) $string['w'] = $weeks . ' minggu';
    if ($days > 0) $string['d'] = $days . ' hari';
    if ($diff->h > 0) $string['h'] = $diff->h . ' jam';
    if ($diff->i > 0) $string['i'] = $diff->i . ' menit';
    if ($diff->s > 0) $string['s'] = $diff->s . ' detik';
    
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
}
?>