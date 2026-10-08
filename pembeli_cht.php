<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'koneksi.php';
include 'chat_handler_pembeli.php';

// Check user login and role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'pembeli') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id_user'];
$user_name = $_SESSION['nama'];
$handler = new ChatHandlerPembeli($conn, $user_id);

// Handle sending new message via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $id_penerima = intval($_POST['id_penerima']);
    $pesan = $_POST['pesan'] ?? '';
    $file = $_FILES['file_attachment'] ?? null;
    $result = $handler->sendMessage($id_penerima, $pesan, $file);
    if ($result['success']) {
        header("Location: pembeli_cht.php?user=" . $id_penerima);
        exit;
    }
}

// Get selected penjual
$selected_penjual_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
$selected_penjual = null;
if ($selected_penjual_id > 0) {
    $selected_penjual = $handler->getPenjualInfo($selected_penjual_id);
}

// Get total unread
$total_unread = $handler->getTotalUnread();

// Get penjual list for sidebar
$penjual_list = $handler->getPenjualList();

// Get messages if selected penjual exists
$messages = [];
if ($selected_penjual_id > 0) {
    $messages = $handler->getMessages($selected_penjual_id, 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 Chat dengan Penjual - Pustaka Aruna</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            --radius: 12px;
            --radius-sm: 8px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        body {
            background: linear-gradient(135deg, var(--primary-light), var(--background));
            height: 100vh;
            overflow: hidden;
            padding: 20px;
            color: var(--text-primary);
        }

        .chat-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            height: calc(100vh - 40px);
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .chat-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .chat-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.025em;
        }

        .chat-header h1 i { color: rgba(255, 255, 255, 0.9); }

        .back-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-2px);
        }

        .unread-badge {
            background: var(--error);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .chat-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .chat-sidebar {
            width: 350px;
            background: var(--background);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .sidebar-header h2 {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-box { position: relative; }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--surface);
            color: var(--text-primary);
        }

        .search-box input::placeholder { color: var(--text-secondary); }
        .search-box input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.875rem; }

        .chat-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }

        .chat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            background: var(--surface);
            text-decoration: none;
            color: inherit;
            border: 1px solid transparent;
        }

        .chat-item:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            transform: translateX(2px);
        }

        .chat-item.active {
            background: var(--primary-light);
            border-left: 3px solid var(--primary);
            border-color: var(--primary);
        }

        .chat-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .chat-info { flex: 1; min-width: 0; }

        .chat-name-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .chat-name {
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.9375rem;
        }

        .online-dot { color: var(--success); font-size: 0.5rem; }
        .chat-time { font-size: 0.75rem; color: var(--text-secondary); }

        .chat-preview {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-count {
            background: var(--error);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--surface);
        }

        .chat-main-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface);
        }

        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .chat-user-details h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .chat-user-details p {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .online-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-online {
            background: var(--success);
            box-shadow: 0 0 0 2px var(--success-bg);
        }

        .status-offline { background: var(--secondary); }
        .last-seen { font-size: 0.75rem; color: var(--text-secondary); margin-left: 4px; }

        .chat-actions { display: flex; gap: 8px; }

        .action-btn {
            background: var(--background);
            border: 1px solid var(--border);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .action-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary);
            transform: scale(1.05);
        }

        .messages-container {
            flex: 1;
            padding: 20px 24px;
            overflow-y: auto;
            background: var(--background);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .date-divider {
            text-align: center;
            margin: 16px 0;
        }

        .date-divider span {
            background: var(--surface);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--text-secondary);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .message {
            display: flex;
            max-width: 75%;
            animation: fadeIn 0.25s ease;
        }

        .message.sent { align-self: flex-end; flex-direction: row-reverse; }
        .message.received { align-self: flex-start; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-primary);
            flex-shrink: 0;
            border: 1px solid var(--border);
        }

        .message.sent .message-avatar {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border: none;
            margin-left: 8px;
        }

        .message.received .message-avatar { margin-right: 8px; }

        .message-content {
            background: var(--surface);
            padding: 12px 16px;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .message.sent .message-content {
            background: var(--primary);
            color: white;
            border-radius: 16px 4px 16px 16px;
            border: none;
        }

        .message.received .message-content {
            border-radius: 4px 16px 16px 16px;
        }

        .message-text {
            line-height: 1.5;
            margin-bottom: 6px;
            word-wrap: break-word;
            font-size: 0.9375rem;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
        }

        .message.sent .message-time { color: rgba(255, 255, 255, 0.85); }
        .message.received .message-time { color: var(--text-secondary); }

        .attachment { margin-top: 8px; max-width: 280px; }

        .attachment.image img {
            max-width: 100%;
            max-height: 200px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: transform 0.2s ease;
            border: 1px solid var(--border);
        }

        .attachment.image img:hover { transform: scale(1.02); }

        .attachment.file {
            background: var(--background);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .attachment.file:hover {
            background: var(--primary-light);
            border-color: var(--primary);
        }

        .file-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-light);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1rem;
        }

        .message.sent .file-icon {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .file-info { flex: 1; }
        .file-name { font-weight: 500; font-size: 0.8125rem; margin-bottom: 2px; word-break: break-all; }
        .file-size { font-size: 0.75rem; opacity: 0.7; }

        .message-input-container {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }

        .input-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .attachment-btn {
            background: var(--background);
            border: 1px solid var(--border);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            cursor: pointer;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .attachment-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .message-input { flex: 1; position: relative; }

        .message-input textarea {
            width: 100%;
            padding: 12px 52px 12px 16px;
            border: 1px solid var(--border);
            border-radius: 24px;
            font-size: 0.9375rem;
            font-family: inherit;
            resize: none;
            max-height: 120px;
            min-height: 44px;
            transition: all 0.2s ease;
            background: var(--background);
            color: var(--text-primary);
        }

        .message-input textarea::placeholder { color: var(--text-secondary); }
        .message-input textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

        .send-btn {
            position: absolute;
            right: 6px;
            bottom: 6px;
            background: var(--primary);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .send-btn:hover {
            background: var(--primary-hover);
            transform: scale(1.05);
        }

        .send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .attachment-preview {
            margin-top: 12px;
            padding: 12px;
            background: var(--background);
            border-radius: var(--radius-sm);
            display: none;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--border);
        }

        .preview-item { display: flex; align-items: center; gap: 10px; flex: 1; }
        .preview-item img { width: 48px; height: 48px; border-radius: var(--radius-sm); object-fit: cover; border: 1px solid var(--border); }
        .preview-info { flex: 1; min-width: 0; }
        .preview-name { font-size: 0.8125rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
        .preview-size { font-size: 0.75rem; color: var(--text-secondary); }

        .remove-preview {
            background: none;
            border: none;
            color: var(--error);
            cursor: pointer;
            font-size: 1.125rem;
            padding: 4px;
            transition: all 0.2s ease;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
        }

        .remove-preview:hover { background: var(--error-bg); }

        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
            text-align: center;
        }

        .empty-state i { font-size: 4rem; color: var(--border); margin-bottom: 1.5rem; opacity: 0.5; }
        .empty-state h3 { color: var(--text-primary); margin-bottom: 8px; font-weight: 600; }
        .empty-state p { font-size: 0.9375rem; }

        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            background: var(--surface);
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-md);
        }

        .close-modal:hover {
            background: var(--error);
            color: white;
            transform: scale(1.1);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .chat-container { flex-direction: column; }
            .chat-sidebar { width: 100%; height: 300px; }
            .message { max-width: 85%; }
        }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .chat-wrapper { height: calc(100vh - 20px); }
            .chat-header h1 { font-size: 1.125rem; }
            .messages-container { padding: 16px; }
            .message { max-width: 90%; }
            .chat-sidebar { height: 250px; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--background); }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

        /* Focus visible */
        .search-box input:focus-visible,
        .message-input textarea:focus-visible,
        .send-btn:focus-visible,
        .action-btn:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Animation for notifications */
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
    </style>
</head>
<body>
    <div class="chat-wrapper">
        <!-- Header -->
        <div class="chat-header">
            <h1>
                <i class="fas fa-comments"></i>
                Chat dengan Penjual
                <span class="unread-badge" id="total-unread-badge" style="<?php echo $total_unread > 0 ? '' : 'display: none;'; ?>">
                    <?php echo $total_unread; ?> baru
                </span>
            </h1>
            <a href="dashboard_pembeli.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Dashboard
            </a>
        </div>

        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Sidebar - Chat List -->
            <div class="chat-sidebar">
                <div class="sidebar-header">
                    <h2><i class="fas fa-store"></i> Daftar Penjual</h2>
                    <div class="search-box">
                        <input type="text" placeholder="Cari penjual..." id="searchChat">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                <div class="chat-list" id="chatList">
                    <?php if (!empty($penjual_list)): ?>
                        <?php foreach ($penjual_list as $penjual):
                            $is_active = $selected_penjual_id == $penjual['id_user'];
                            $last_time = $penjual['last_time'] ? date('H:i', strtotime($penjual['last_time'])) : '';
                            $last_message = $penjual['last_message'] ? htmlspecialchars(substr($penjual['last_message'], 0, 50)) : 'Belum ada pesan';
                            if ($penjual['last_sender'] == $user_id) { $last_message = 'Anda: ' . $last_message; }
                            $is_online = false;
                            if (isset($penjual['is_online']) && $penjual['is_online'] == 1) { $is_online = true; }
                            elseif (isset($penjual['status']) && $penjual['status'] == 'online') { $is_online = true; }
                        ?>
                        <div class="chat-item <?php echo $is_active ? 'active' : ''; ?>" onclick="window.location.href='pembeli_cht.php?user=<?php echo $penjual['id_user']; ?>'">
                            <div class="chat-avatar"><?php echo strtoupper(substr($penjual['nama'], 0, 1)); ?></div>
                            <div class="chat-info">
                                <div class="chat-name-row">
                                    <div class="chat-name">
                                        <?php echo htmlspecialchars($penjual['nama']); ?>
                                        <?php if ($is_online): ?><i class="fas fa-circle online-dot" title="Online"></i><?php endif; ?>
                                    </div>
                                    <div class="chat-time"><?php echo $last_time; ?></div>
                                </div>
                                <div class="chat-preview"><?php echo $last_message; ?></div>
                            </div>
                            <?php if ($penjual['unread_count'] > 0): ?><div class="unread-badge"><?php echo $penjual['unread_count']; ?></div><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 2rem;">
                            <i class="fas fa-store"></i>
                            <p>Belum ada penjual</p>
                            <p style="font-size: 0.8125rem; margin-top: 10px; color: var(--text-secondary);">Penjual akan muncul setelah Anda memesan produk</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="chat-main">
                <?php if ($selected_penjual): ?>
                    <!-- Chat Header -->
                    <div class="chat-main-header">
                        <div class="chat-user-info">
                            <div class="chat-user-avatar" id="chat-user-avatar">
                                <?php echo strtoupper(substr($selected_penjual['nama'] ?? 'P', 0, 1)); ?>
                            </div>
                            <div class="chat-user-details">
                                <h3 id="chat-user-name"><?php echo htmlspecialchars($selected_penjual['nama'] ?? ''); ?></h3>
                                <p id="chat-user-email"><?php echo htmlspecialchars($selected_penjual['email'] ?? ''); ?></p>
                                <div class="online-status" id="online-status-container">
                                    <?php
                                    $is_online = false;
                                    if (isset($selected_penjual['is_online']) && $selected_penjual['is_online'] == 1) { $is_online = true; }
                                    elseif (isset($selected_penjual['status']) && $selected_penjual['status'] == 'online') { $is_online = true; }
                                    ?>
                                    <div class="status-dot <?php echo $is_online ? 'status-online' : 'status-offline'; ?>" id="status-dot"></div>
                                    <span id="status-text"><?php echo $is_online ? 'Online' : 'Offline'; ?></span>
                                    <?php if (!$is_online && isset($selected_penjual['last_seen']) && $selected_penjual['last_seen']): ?>
                                        <span class="last-seen" id="last-seen-text">• <?php echo time_elapsed_string($selected_penjual['last_seen']); ?></span>
                                    <?php else: ?>
                                        <span class="last-seen" id="last-seen-text" style="display: none;"></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="chat-actions">
                            <button class="action-btn" title="Lihat Profil" onclick="showPenjualInfo(<?php echo $selected_penjual_id; ?>)"><i class="fas fa-store-alt"></i></button>
                            <button class="action-btn" title="Segarkan" onclick="refreshChat()"><i class="fas fa-sync-alt"></i></button>
                        </div>
                    </div>

                    <!-- Messages Container -->
                    <div class="messages-container" id="messagesContainer">
                        <?php if (!empty($messages)): ?>
                            <?php
                            $current_date = null;
                            foreach ($messages as $message):
                                $message_date = date('Y-m-d', strtotime($message['created_at']));
                                $is_sent = $message['id_pengirim'] == $user_id;
                                if ($current_date != $message_date):
                                    $current_date = $message_date;
                                    $date_label = date('d M Y', strtotime($message_date));
                                    if ($date_label == date('d M Y')) { $date_label = 'Hari Ini'; }
                                    elseif ($date_label == date('d M Y', strtotime('-1 day'))) { $date_label = 'Kemarin'; }
                            ?>
                            <div class="date-divider"><span><?php echo $date_label; ?></span></div>
                            <?php endif; ?>
                            <div class="message <?php echo $is_sent ? 'sent' : 'received'; ?>" id="message-<?php echo $message['id_chat']; ?>">
                                <div class="message-avatar"><?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?></div>
                                <div class="message-content">
                                    <?php if ($message['tipe'] == 'text'): ?>
                                        <div class="message-text"><?php echo nl2br(htmlspecialchars($message['pesan'])); ?></div>
                                    <?php elseif ($message['tipe'] == 'gambar'): ?>
                                        <?php if (!empty($message['pesan'])): ?><div class="message-text"><?php echo nl2br(htmlspecialchars($message['pesan'])); ?></div><?php endif; ?>
                                        <div class="attachment image">
                                            <img src="uploads/chat/<?php echo htmlspecialchars($message['file_path']); ?>" alt="Gambar" onclick="showImageModal(this.src)" loading="lazy">
                                        </div>
                                    <?php elseif ($message['tipe'] == 'file'): ?>
                                        <?php if (!empty($message['pesan'])): ?><div class="message-text"><?php echo nl2br(htmlspecialchars($message['pesan'])); ?></div><?php endif; ?>
                                        <a href="uploads/chat/<?php echo htmlspecialchars($message['file_path']); ?>" class="attachment file" download target="_blank">
                                            <div class="file-icon"><i class="fas fa-file"></i></div>
                                            <div class="file-info">
                                                <div class="file-name"><?php echo htmlspecialchars(basename($message['file_path'])); ?></div>
                                                <?php $file_path = 'uploads/chat/' . $message['file_path']; if (file_exists($file_path)): ?><div class="file-size"><?php echo formatFileSize(filesize($file_path)); ?></div><?php endif; ?>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                    <div class="message-time">
                                        <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                        <?php if ($is_sent): ?><?php if ($message['dibaca']): ?><i class="fas fa-check-double" style="color: #4ade80;" title="Telah dibaca"></i><?php else: ?><i class="fas fa-check" title="Terkirim"></i><?php endif; ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" id="empty-messages">
                                <i class="fas fa-comment-dots"></i>
                                <h3>Mulai Percakapan</h3>
                                <p>Kirim pesan pertama kepada <?php echo htmlspecialchars($selected_penjual['nama'] ?? ''); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Message Input -->
                    <div class="message-input-container">
                        <form id="messageForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id_penerima" value="<?php echo $selected_penjual_id; ?>">
                            <input type="hidden" name="send_message" value="1">
                            <div class="attachment-preview" id="attachmentPreview"></div>
                            <div class="input-form">
                                <button type="button" class="attachment-btn" onclick="document.getElementById('fileInput').click()" title="Lampirkan file"><i class="fas fa-paperclip"></i></button>
                                <input type="file" id="fileInput" name="file_attachment" style="display: none;" onchange="handleFileSelect(event)" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                <div class="message-input">
                                    <textarea name="pesan" id="messageText" placeholder="Ketik pesan..." rows="1"></textarea>
                                    <button type="submit" class="send-btn" id="sendButton"><i class="fas fa-paper-plane"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Empty Chat Selection -->
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>Pilih Percakapan</h3>
                        <p>Pilih penjual dari daftar untuk memulai percakapan</p>
                        <p style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 16px;">
                            <i class="fas fa-info-circle"></i> Anda dapat chat dengan penjual setelah memesan produk
                        </p>
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
        // Global variables
        const currentUserId = <?php echo $user_id; ?>;
        const selectedPenjualId = <?php echo $selected_penjual_id ?: 0; ?>;
        let lastMessageId = <?php echo !empty($messages) ? end($messages)['id_chat'] : 0; ?>;
        let chatRefreshInterval;
        let unreadRefreshInterval;

        document.addEventListener('DOMContentLoaded', function() {
            if (selectedPenjualId > 0) {
                markMessagesAsRead(selectedPenjualId);
                startRealTimeUpdates();
                scrollToBottom();
            }
            initEventListeners();
        });

        function loadPenjualList() {
            const chatList = document.getElementById('chatList');
            fetch('chat_handler_pembeli.php?action=get_penjual')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        chatList.innerHTML = '';
                        data.data.forEach(penjual => { chatList.appendChild(createChatItem(penjual)); });
                    }
                })
                .catch(error => console.error('Error loading penjual list:', error));
        }

        function createChatItem(penjual) {
            const div = document.createElement('a');
            div.href = `pembeli_cht.php?user=${penjual.id_user}`;
            div.className = `chat-item ${selectedPenjualId == penjual.id_user ? 'active' : ''}`;
            div.setAttribute('data-user-id', penjual.id_user);
            const isOnline = penjual.is_online == 1 || penjual.status === 'online';
            const lastTime = penjual.last_time ? formatTime(penjual.last_time) : '';
            let lastMessage = penjual.last_message ? escapeHtml(penjual.last_message.substring(0, 50)) : 'Belum ada pesan';
            if (penjual.last_sender == currentUserId) { lastMessage = 'Anda: ' + lastMessage; }
            div.innerHTML = `
                <div class="chat-avatar">${penjual.nama ? penjual.nama.charAt(0).toUpperCase() : 'P'}</div>
                <div class="chat-info">
                    <div class="chat-name-row">
                        <div class="chat-name">${escapeHtml(penjual.nama || 'Penjual')}${isOnline ? '<i class="fas fa-circle online-dot" title="Online"></i>' : ''}</div>
                        <div class="chat-time">${lastTime}</div>
                    </div>
                    <div class="chat-preview">${lastMessage}</div>
                </div>
                ${penjual.unread_count > 0 ? `<div class="unread-count">${penjual.unread_count}</div>` : ''}
            `;
            return div;
        }

        function loadMessages(penjualId) {
            if (!penjualId) return;
            fetch(`chat_handler_pembeli.php?action=get_messages&user=${penjualId}&last_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(message => { addMessageToChat(message); lastMessageId = Math.max(lastMessageId, message.id_chat); });
                        updateUserStatus(data.is_online, data.last_seen);
                        scrollToBottom();
                    }
                })
                .catch(error => console.error('Error loading messages:', error));
        }

        function addMessageToChat(message) {
            const container = document.getElementById('messagesContainer');
            if (!container) return;
            if (document.getElementById(`message-${message.id_chat}`)) return;
            const isSent = message.id_pengirim == currentUserId;
            const messageDate = new Date(message.created_at);
            const dateStr = formatDate(messageDate);
            const emptyMessage = document.getElementById('empty-messages');
            if (emptyMessage) emptyMessage.style.display = 'none';
            const lastDivider = container.querySelector('.date-divider:last-child');
            let lastDate = '';
            if (lastDivider) { const span = lastDivider.querySelector('span'); if (span) lastDate = span.textContent; }
            if (lastDate !== dateStr) {
                const dateDivider = document.createElement('div');
                dateDivider.className = 'date-divider';
                dateDivider.innerHTML = `<span>${dateStr}</span>`;
                container.appendChild(dateDivider);
            }
            const messageEl = document.createElement('div');
            messageEl.className = `message ${isSent ? 'sent' : 'received'}`;
            messageEl.id = `message-${message.id_chat}`;
            let content = `<div class="message-avatar">${message.sender_name ? message.sender_name.charAt(0).toUpperCase() : 'U'}</div><div class="message-content">`;
            if (message.pesan) { content += `<div class="message-text">${escapeHtml(message.pesan).replace(/\n/g, '<br>')}</div>`; }
            if (message.file_path) {
                if (message.tipe === 'gambar') {
                    content += `<div class="attachment image"><img src="uploads/chat/${escapeHtml(message.file_path)}" alt="Gambar" onclick="showImageModal(this.src)" loading="lazy"></div>`;
                } else if (message.tipe === 'file') {
                    const fileName = message.file_path.split('/').pop();
                    content += `<a href="uploads/chat/${escapeHtml(message.file_path)}" class="attachment file" download target="_blank"><div class="file-icon"><i class="fas fa-file"></i></div><div class="file-info"><div class="file-name">${escapeHtml(fileName)}</div></div></a>`;
                }
            }
            const timeStr = formatTime(message.created_at);
            let readStatus = '';
            if (isSent) { readStatus = message.dibaca ? '<i class="fas fa-check-double" style="color: #4ade80;" title="Telah dibaca"></i>' : '<i class="fas fa-check" title="Terkirim"></i>'; }
            content += `<div class="message-time">${timeStr} ${readStatus}</div></div>`;
            messageEl.innerHTML = content;
            container.appendChild(messageEl);
        }

        function sendMessage() {
            const textarea = document.getElementById('messageText');
            const message = textarea.value.trim();
            const fileInput = document.getElementById('fileInput');
            const idPenerimaInput = document.querySelector('input[name="id_penerima"]');
            const id_penerima = idPenerimaInput ? parseInt(idPenerimaInput.value) : selectedPenjualId;
            if (!message && (!fileInput.files || fileInput.files.length === 0)) { showNotification('Silakan tulis pesan atau lampirkan file', 'warning'); return; }
            if (!id_penerima || id_penerima <= 0) { showNotification('Silakan pilih penjual terlebih dahulu', 'warning'); return; }
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('id_penerima', id_penerima);
            formData.append('pesan', message);
            if (fileInput.files && fileInput.files[0]) { formData.append('file_attachment', fileInput.files[0]); }
            fetch('chat_handler_pembeli.php?action=send_message', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        textarea.value = '';
                        textarea.style.height = 'auto';
                        removeAttachment();
                        loadMessages(id_penerima);
                        scrollToBottom();
                    } else { showNotification('Gagal mengirim pesan: ' + (data.message || 'Unknown error'), 'danger'); }
                })
                .catch(error => { showNotification('Gagal mengirim pesan: ' + error.message, 'danger'); });
        }

        function markMessagesAsRead(senderId) {
            fetch(`chat_handler_pembeli.php?action=mark_read&sender=${senderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.affected > 0) {
                        updateUnreadCounts();
                        document.querySelectorAll('.message.received .fa-check').forEach(icon => { icon.className = 'fas fa-check-double'; icon.style.color = '#4ade80'; });
                        loadPenjualList();
                    }
                })
                .catch(error => console.error('Error marking as read:', error));
        }

        function updateUnreadCounts() {
            fetch('chat_handler_pembeli.php?action=get_unread_counts')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const headerBadge = document.getElementById('total-unread-badge');
                        if (headerBadge) {
                            if (data.total > 0) { headerBadge.textContent = `${data.total} baru`; headerBadge.style.display = 'inline-block'; }
                            else { headerBadge.style.display = 'none'; }
                        }
                        data.counts.forEach(item => {
                            const chatItem = document.querySelector(`.chat-item[data-user-id="${item.user_id}"]`);
                            if (chatItem) {
                                let badge = chatItem.querySelector('.unread-count');
                                if (item.count > 0) {
                                    if (badge) { badge.textContent = item.count; }
                                    else { chatItem.insertAdjacentHTML('beforeend', `<div class="unread-count">${item.count}</div>`); }
                                } else if (badge) { badge.remove(); }
                            }
                        });
                    }
                })
                .catch(error => console.error('Error updating unread counts:', error));
        }

        function showPenjualInfo(penjualId) {
            fetch(`chat_handler_pembeli.php?action=get_penjual_info&id=${penjualId}`)
                .then(response => response.json())
                .then(penjual => {
                    if (penjual) {
                        const lastSeen = penjual.last_seen ? new Date(penjual.last_seen).toLocaleString('id-ID') : 'Tidak diketahui';
                        const isOnline = penjual.is_online == 1 || penjual.status === 'online';
                        const info = `📋 INFORMASI PENJUAL\nNama: ${penjual.nama || '-'}\nEmail: ${penjual.email || '-'}\nStatus: ${isOnline ? '🟢 Online' : '⚫ Offline'}\nTerakhir Online: ${lastSeen}`;
                        alert(info);
                    }
                })
                .catch(error => { console.error('Error:', error); alert('Gagal memuat informasi penjual'); });
        }

        function updateUserStatus(isOnline, lastSeen) {
            const statusDot = document.getElementById('status-dot');
            const statusText = document.getElementById('status-text');
            const lastSeenText = document.getElementById('last-seen-text');
            if (statusDot && statusText) {
                if (isOnline) {
                    statusDot.className = 'status-dot status-online';
                    statusText.textContent = 'Online';
                    if (lastSeenText) lastSeenText.style.display = 'none';
                } else {
                    statusDot.className = 'status-dot status-offline';
                    statusText.textContent = 'Offline';
                    if (lastSeenText && lastSeen) { lastSeenText.textContent = `• ${timeAgo(lastSeen)}`; lastSeenText.style.display = 'inline'; }
                }
            }
        }

        function startRealTimeUpdates() {
            if (chatRefreshInterval) clearInterval(chatRefreshInterval);
            if (unreadRefreshInterval) clearInterval(unreadRefreshInterval);
            chatRefreshInterval = setInterval(() => { if (selectedPenjualId > 0) { loadMessages(selectedPenjualId); } }, 3000);
            unreadRefreshInterval = setInterval(() => { updateUnreadCounts(); loadPenjualList(); }, 5000);
        }

        function refreshChat() { if (selectedPenjualId > 0) { loadMessages(selectedPenjualId); markMessagesAsRead(selectedPenjualId); showNotification('Percakapan disegarkan', 'success'); } }

        function formatDate(date) {
            const today = new Date();
            const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
            if (date.toDateString() === today.toDateString()) { return 'Hari Ini'; }
            else if (date.toDateString() === yesterday.toDateString()) { return 'Kemarin'; }
            else { return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }); }
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }

        function timeAgo(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            if (seconds < 60) return 'baru saja';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' menit yang lalu';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' jam yang lalu';
            return formatDate(date);
        }

        function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

        function formatBytes(bytes, decimals = 2) {
            if (!bytes || bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function scrollToBottom() { const container = document.getElementById('messagesContainer'); if (container) { container.scrollTop = container.scrollHeight; } }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? 'var(--success)' : type === 'danger' ? 'var(--error)' : type === 'warning' ? 'var(--warning)' : 'var(--info)';
            const icon = type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle';
            notification.style.cssText = `position: fixed; top: 20px; right: 20px; background: ${bgColor}; color: white; padding: 12px 20px; border-radius: var(--radius-sm); box-shadow: var(--shadow-md); z-index: 9999; animation: slideIn 0.3s ease; display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500;`;
            notification.innerHTML = `<i class="fas fa-${icon}"></i><span>${message}</span>`;
            document.body.appendChild(notification);
            setTimeout(() => { notification.style.animation = 'slideOut 0.3s ease'; setTimeout(() => notification.remove(), 300); }, 3000);
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.size > 10 * 1024 * 1024) { showNotification('Ukuran file maksimal 10MB', 'warning'); document.getElementById('fileInput').value = ''; return; }
            const preview = document.getElementById('attachmentPreview');
            const reader = new FileReader();
            reader.onload = function(e) {
                if (file.type.startsWith('image/')) {
                    preview.innerHTML = `<div class="preview-item"><img src="${e.target.result}" alt="Preview"><div class="preview-info"><div class="preview-name">${escapeHtml(file.name)}</div><div class="preview-size">${formatBytes(file.size)}</div></div><button type="button" class="remove-preview" onclick="removeAttachment()"><i class="fas fa-times-circle"></i></button></div>`;
                } else {
                    let icon = 'fa-file';
                    if (file.name.match(/\.(pdf)$/i)) icon = 'fa-file-pdf';
                    else if (file.name.match(/\.(doc|docx)$/i)) icon = 'fa-file-word';
                    else if (file.name.match(/\.(xls|xlsx)$/i)) icon = 'fa-file-excel';
                    else if (file.name.match(/\.(txt)$/i)) icon = 'fa-file-alt';
                    preview.innerHTML = `<div class="preview-item"><div class="file-icon"><i class="fas ${icon}"></i></div><div class="preview-info"><div class="preview-name">${escapeHtml(file.name)}</div><div class="preview-size">${formatBytes(file.size)}</div></div><button type="button" class="remove-preview" onclick="removeAttachment()"><i class="fas fa-times-circle"></i></button></div>`;
                }
                preview.style.display = 'flex';
            };
            if (file.type.startsWith('image/')) { reader.readAsDataURL(file); } else { reader.readAsArrayBuffer(file); }
        }

        function removeAttachment() { document.getElementById('fileInput').value = ''; document.getElementById('attachmentPreview').innerHTML = ''; document.getElementById('attachmentPreview').style.display = 'none'; }

        function showImageModal(src) { const modal = document.getElementById('imageModal'); const modalImg = document.getElementById('modalImage'); modalImg.src = src; modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
        function closeImageModal(event) { if (event) event.stopPropagation(); const modal = document.getElementById('imageModal'); modal.style.display = 'none'; document.body.style.overflow = 'auto'; }

        function initEventListeners() {
            const searchInput = document.getElementById('searchChat');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const chatItems = document.querySelectorAll('.chat-item');
                    chatItems.forEach(item => {
                        const name = item.querySelector('.chat-name').textContent.toLowerCase();
                        item.style.display = name.includes(searchTerm) ? 'flex' : 'none';
                    });
                });
            }
            const textarea = document.getElementById('messageText');
            if (textarea) {
                textarea.addEventListener('input', function() { this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 120) + 'px'; });
            }
            const messageForm = document.getElementById('messageForm');
            if (messageForm) { messageForm.addEventListener('submit', function(e) { e.preventDefault(); sendMessage(); }); }
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeImageModal(); } });
            const messagesContainer = document.getElementById('messagesContainer');
            if (messagesContainer) {
                let markReadTimeout;
                messagesContainer.addEventListener('scroll', function() {
                    clearTimeout(markReadTimeout);
                    markReadTimeout = setTimeout(() => {
                        const isNearBottom = this.scrollHeight - this.scrollTop - this.clientHeight < 100;
                        if (isNearBottom && selectedPenjualId > 0) { markMessagesAsRead(selectedPenjualId); }
                    }, 500);
                });
            }
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } } @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }`;
        document.head.appendChild(style);
    </script>
</body>
</html>
<?php
// Helper function untuk format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
// Helper function untuk format time elapsed
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' tahun yang lalu';
    if ($diff->m > 0) return $diff->m . ' bulan yang lalu';
    if ($diff->d > 0) return $diff->d . ' hari yang lalu';
    if ($diff->h > 0) return $diff->h . ' jam yang lalu';
    if ($diff->i > 0) return $diff->i . ' menit yang lalu';
    return 'baru saja';
}
?>