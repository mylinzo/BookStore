<?php
session_start();
include 'koneksi.php';

// Check authentication and admin role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'super_admin' && $_SESSION['role'] !== 'penjual') {
    header("Location: dashboard.php");
    exit;
}

// Handle reply to message
$reply_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $message_id = intval($_POST['message_id']);
    $reply_text = mysqli_real_escape_string($conn, $_POST['reply_text']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    
    $admin_id = $_SESSION['id_user'];
    
    $query = "UPDATE contact_messages 
              SET admin_reply = ?, status = ?, priority = ?, admin_replied_by = ?, replyed_at = NOW()
              WHERE id_message = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "sssii", $reply_text, $status, $priority, $admin_id, $message_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $reply_sent = true;
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'latest';

// Build query
$where_clause = "WHERE 1=1";

if ($filter_status !== 'all') {
    $filter_status = mysqli_real_escape_string($conn, $filter_status);
    $where_clause .= " AND status = '$filter_status'";
}

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_clause .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR subject LIKE '%$search%' OR message LIKE '%$search%')";
}

// Determine sort order
$order_clause = "ORDER BY ";
if ($sort === 'oldest') {
    $order_clause .= "created_at ASC";
} elseif ($sort === 'priority') {
    $order_clause .= "FIELD(priority, 'urgent', 'high', 'medium', 'low'), created_at DESC";
} else { // latest
    $order_clause .= "created_at DESC";
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM contact_messages $where_clause";
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_messages = $count_row['total'];

// Pagination
$per_page = 15;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;
$total_pages = ceil($total_messages / $per_page);

// Get messages
$query = "SELECT * FROM contact_messages $where_clause $order_clause LIMIT $per_page OFFSET $offset";
$result = mysqli_query($conn, $query);

// Get single message for detail view
$detail_message = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $message_id = intval($_GET['view']);
    $detail_query = "SELECT * FROM contact_messages WHERE id_message = ?";
    
    if ($stmt = mysqli_prepare($conn, $detail_query)) {
        mysqli_stmt_bind_param($stmt, "i", $message_id);
        mysqli_stmt_execute($stmt);
        $detail_result = mysqli_stmt_get_result($stmt);
        $detail_message = mysqli_fetch_assoc($detail_result);
        mysqli_stmt_close($stmt);
    }
}

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
                FROM contact_messages";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesan Help - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f9fafb;
            --border: #e5e7eb;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: var(--dark);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #6b7280;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .stat-total { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .stat-pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-replied { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-resolved { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .stat-urgent { background: linear-gradient(135deg, #ef4444, #dc2626); }

        .stat-info h3 {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .stat-info .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
        }

        /* Filters */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group select,
        .form-group input {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #059669;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Messages Table */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--light);
        }

        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
        }

        td {
            padding: 15px 20px;
            border-top: 1px solid var(--border);
            font-size: 14px;
        }

        tbody tr:hover {
            background-color: var(--light);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-replied { background: #d1fae5; color: #059669; }
        .status-resolved { background: #cffafe; color: #0891b2; }
        .status-closed { background: #f3f4f6; color: #6b7280; }

        .priority-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .priority-low { background: #e0e7ff; color: #4f46e5; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-urgent { background: #fecaca; color: #b91c1c; }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-view {
            background: var(--info);
            color: white;
        }

        .btn-view:hover {
            background: #1d4ed8;
        }

        .btn-delete {
            background: var(--danger);
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }

        .close-btn:hover {
            color: var(--dark);
        }

        .modal-body {
            padding: 20px;
        }

        .message-info {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }

        .message-info-row:last-child {
            border-bottom: none;
        }

        .message-info label {
            font-weight: 600;
            color: #6b7280;
        }

        .message-content {
            margin: 20px 0;
        }

        .message-text {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            line-height: 1.6;
        }

        .reply-form {
            margin-top: 20px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 120px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .success-alert {
            background: #d1fae5;
            color: #059669;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--dark);
            text-decoration: none;
        }

        .pagination a:hover {
            background: var(--light);
        }

        .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .filter-group {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-envelope"></i> Kelola Pesan Help & Support</h1>
            <p>Manage pesan dari pembeli dan penjual</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-total">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Pesan</h3>
                    <div class="value"><?php echo $stats['total'] ?? 0; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3>Pending</h3>
                    <div class="value"><?php echo $stats['pending'] ?? 0; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-replied">
                    <i class="fas fa-reply"></i>
                </div>
                <div class="stat-info">
                    <h3>Replied</h3>
                    <div class="value"><?php echo $stats['replied'] ?? 0; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-resolved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>Resolved</h3>
                    <div class="value"><?php echo $stats['resolved'] ?? 0; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-urgent">
                    <i class="fas fa-exclamation"></i>
                </div>
                <div class="stat-info">
                    <h3>Urgent</h3>
                    <div class="value"><?php echo $stats['urgent'] ?? 0; ?></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" style="display: grid; gap: 15px;">
                <div class="filter-group">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="replied" <?php echo $filter_status === 'replied' ? 'selected' : ''; ?>>Replied</option>
                            <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sort">Sort By</label>
                        <select name="sort" id="sort">
                            <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                            <option value="priority" <?php echo $sort === 'priority' ? 'selected' : ''; ?>>Priority</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Cari nama, email, atau subject..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="admin_help_messages.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <?php if ($reply_sent): ?>
        <div class="success-alert">
            <i class="fas fa-check-circle"></i>
            <span>Reply berhasil dikirim!</span>
        </div>
        <?php endif; ?>

        <!-- Messages Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">Pesan (<?php echo $total_messages; ?> total)</div>
            </div>

            <?php if ($total_messages > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Tanggal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>#<?php echo $row['id_message']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['subject'], 0, 30)); ?><?php echo strlen($row['subject']) > 30 ? '...' : ''; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="priority-badge priority-<?php echo $row['priority']; ?>">
                                <?php echo ucfirst($row['priority']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d M Y H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action btn-view" onclick="openModal(<?php echo $row['id_message']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination" style="padding: 20px;">
                <?php if ($current_page > 1): ?>
                    <a href="?page=1&status=<?php echo $filter_status; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i> First</a>
                    <a href="?page=<?php echo $current_page - 1; ?>&status=<?php echo $filter_status; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">Prev</a>
                <?php endif; ?>

                <?php
                $start = max(1, $current_page - 2);
                $end = min($total_pages, $current_page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>" 
                       class="<?php echo $i === $current_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>&status=<?php echo $filter_status; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                    <a href="?page=<?php echo $total_pages; ?>&status=<?php echo $filter_status; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">Last <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Tidak ada pesan</h3>
                <p>Tidak ada pesan yang sesuai dengan filter Anda</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Detail & Reply -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Detail Pesan</div>
                <button class="close-btn" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content loaded by AJAX -->
            </div>
        </div>
    </div>

    <script>
        function openModal(messageId) {
            const modal = document.getElementById('detailModal');
            const modalBody = document.getElementById('modalBody');

            // Load message detail
            fetch(`admin_help_api.php?action=detail&id=${messageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const msg = data.data;
                        const priorityClass = `priority-${msg.priority}`;
                        const statusClass = `status-${msg.status}`;

                        modalBody.innerHTML = `
                            <div class="message-info">
                                <div class="message-info-row">
                                    <label>Dari:</label>
                                    <span>${msg.name} (${msg.email})</span>
                                </div>
                                <div class="message-info-row">
                                    <label>Subject:</label>
                                    <span>${msg.subject}</span>
                                </div>
                                <div class="message-info-row">
                                    <label>Status:</label>
                                    <span><span class="status-badge ${statusClass}">${msg.status.charAt(0).toUpperCase() + msg.status.slice(1)}</span></span>
                                </div>
                                <div class="message-info-row">
                                    <label>Priority:</label>
                                    <span><span class="priority-badge ${priorityClass}">${msg.priority.charAt(0).toUpperCase() + msg.priority.slice(1)}</span></span>
                                </div>
                                <div class="message-info-row">
                                    <label>Tanggal:</label>
                                    <span>${new Date(msg.created_at).toLocaleString('id-ID')}</span>
                                </div>
                            </div>

                            <div class="message-content">
                                <h4 style="margin-bottom: 10px;">Pesan:</h4>
                                <div class="message-text">${msg.message}</div>
                            </div>

                            ${msg.admin_reply ? `
                                <div class="message-content">
                                    <h4 style="margin-bottom: 10px; color: var(--primary);">Reply Admin:</h4>
                                    <div class="message-text" style="border-left-color: var(--primary);">
                                        ${msg.admin_reply}
                                    </div>
                                    <p style="font-size: 12px; color: #6b7280; margin-top: 10px;">
                                        Dibalas pada: ${new Date(msg.replyed_at).toLocaleString('id-ID')}
                                    </p>
                                </div>
                            ` : `
                                <form method="POST" class="reply-form">
                                    <input type="hidden" name="message_id" value="${msg.id_message}">
                                    
                                    <div class="form-group" style="margin-bottom: 15px;">
                                        <label for="status">Ubah Status</label>
                                        <select name="status" required>
                                            <option value="${msg.status}" selected>${msg.status.charAt(0).toUpperCase() + msg.status.slice(1)}</option>
                                            <option value="replied">Replied</option>
                                            <option value="resolved">Resolved</option>
                                            <option value="closed">Closed</option>
                                        </select>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 15px;">
                                        <label for="priority">Priority</label>
                                        <select name="priority" required>
                                            <option value="${msg.priority}" selected>${msg.priority.charAt(0).toUpperCase() + msg.priority.slice(1)}</option>
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                            <option value="urgent">Urgent</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="reply_text">Balas Pesan</label>
                                        <textarea name="reply_text" placeholder="Ketik reply Anda di sini..." required></textarea>
                                    </div>

                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" name="send_reply" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Kirim Reply
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                                    </div>
                                </form>
                            `}
                        `;

                        modal.classList.add('active');
                    }
                })
                .catch(error => {
                    alert('Error loading message: ' + error);
                    closeModal();
                });
        }

        function closeModal() {
            const modal = document.getElementById('detailModal');
            modal.classList.remove('active');
        }

        // Close modal on background click
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
