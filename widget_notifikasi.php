<?php
/**
 * Widget Notifikasi untuk Dashboard
 * File ini di-include di dashboard_pembeli.php
 * Menampilkan bell icon dengan unread count
 */

// Initialize notifikasi table
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
    // Column missing, drop and recreate
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

$unread_count = 0;
$recent_notifications = [];

// Get unread notifications
$unread_query = "
    SELECT COUNT(*) as unread_count 
    FROM notifikasi 
    WHERE id_user = ? AND status = 'unread'
";

$stmt = mysqli_prepare($conn, $unread_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_user']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $unread_data = mysqli_fetch_assoc($result);
    $unread_count = $unread_data['unread_count'] ?? 0;
}

// Get recent notifications
$recent_query = "
    SELECT id_notifikasi, tipe, pesan, status, created_at 
    FROM notifikasi 
    WHERE id_user = ?
    ORDER BY created_at DESC
    LIMIT 5
";

$stmt = mysqli_prepare($conn, $recent_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_user']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recent_notifications = mysqli_fetch_all($result, MYSQLI_ASSOC) ?? [];
}
?>

<style>
    .notification-widget {
        position: relative;
        display: inline-block;
    }

    .notification-bell {
        position: relative;
        cursor: pointer;
        font-size: 20px;
        color: #667eea;
        transition: all 0.3s ease;
    }

    .notification-bell:hover {
        transform: scale(1.1);
        color: #764ba2;
    }

    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 600;
        border: 2px solid white;
    }

    .notification-dropdown {
        position: absolute;
        top: 40px;
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        min-width: 320px;
        max-width: 380px;
        max-height: 500px;
        display: none;
        z-index: 1000;
        overflow: hidden;
        animation: slideDown 0.3s ease;
    }

    .notification-dropdown.active {
        display: flex;
        flex-direction: column;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-dropdown-header {
        padding: 16px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-dropdown-header h3 {
        font-size: 14px;
        font-weight: 600;
        color: #1f2937;
    }

    .notification-dropdown-header a {
        font-size: 12px;
        color: #667eea;
        text-decoration: none;
        cursor: pointer;
    }

    .notification-items {
        overflow-y: auto;
        max-height: 350px;
    }

    .notification-item {
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        cursor: pointer;
        transition: background 0.2s ease;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }

    .notification-item:hover {
        background: #f9fafb;
    }

    .notification-item.unread {
        background: #f0f4ff;
        border-left: 3px solid #667eea;
    }

    .notification-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 16px;
    }

    .notification-item-content {
        flex: 1;
    }

    .notification-item-title {
        font-size: 13px;
        font-weight: 500;
        color: #1f2937;
        margin-bottom: 2px;
    }

    .notification-item-message {
        font-size: 12px;
        color: #6b7280;
        line-height: 1.4;
        margin-bottom: 4px;
    }

    .notification-item-time {
        font-size: 11px;
        color: #9ca3af;
    }

    .notification-dropdown-empty {
        padding: 40px 16px;
        text-align: center;
        color: #9ca3af;
    }

    .notification-dropdown-empty i {
        font-size: 32px;
        color: #d1d5db;
        margin-bottom: 10px;
        display: block;
    }

    .notification-dropdown-footer {
        padding: 12px 16px;
        border-top: 1px solid #e5e7eb;
        text-align: center;
    }

    .notification-dropdown-footer a {
        font-size: 13px;
        color: #667eea;
        text-decoration: none;
        font-weight: 500;
    }

    .notification-dropdown-footer a:hover {
        text-decoration: underline;
    }
</style>

<div class="notification-widget">
    <div class="notification-bell" onclick="toggleNotificationDropdown(event)">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?>
            <div class="notification-badge"><?php echo min($unread_count, 99); ?></div>
        <?php endif; ?>
    </div>

    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-header">
            <h3>Notifikasi</h3>
            <a href="halaman_notifikasi.php">Lihat Semua</a>
        </div>

        <div class="notification-items" id="notificationItems">
            <?php if (empty($recent_notifications)): ?>
                <div class="notification-dropdown-empty">
                    <i class="fas fa-inbox"></i>
                    Tidak ada notifikasi
                </div>
            <?php else: ?>
                <?php foreach ($recent_notifications as $notif):
                    $type_icon = [
                        'payment_verified' => ['icon' => 'fa-check-circle', 'color' => '#10b981', 'bg' => '#d1fae5'],
                        'payment_rejected' => ['icon' => 'fa-times-circle', 'color' => '#ef4444', 'bg' => '#fee2e2'],
                        'delivery_confirmed' => ['icon' => 'fa-check-double', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
                        'order_shipped' => ['icon' => 'fa-truck', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
                        'payment_pending' => ['icon' => 'fa-hourglass-end', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
                    ];
                    $config = $type_icon[$notif['tipe']] ?? ['icon' => 'fa-bell', 'color' => '#667eea', 'bg' => '#eef2ff'];
                ?>
                <div class="notification-item <?php echo $notif['status'] === 'unread' ? 'unread' : ''; ?>" 
                     onclick="window.location.href='halaman_notifikasi.php'">
                    <div class="notification-item-icon" style="background-color: <?php echo $config['bg']; ?>;">
                        <i class="fas <?php echo $config['icon']; ?>" style="color: <?php echo $config['color']; ?>;"></i>
                    </div>
                    <div class="notification-item-content">
                        <div class="notification-item-title">
                            <?php echo ucfirst(str_replace('_', ' ', $notif['tipe'])); ?>
                        </div>
                        <div class="notification-item-message">
                            <?php echo substr($notif['pesan'], 0, 80) . (strlen($notif['pesan']) > 80 ? '...' : ''); ?>
                        </div>
                        <div class="notification-item-time">
                            <?php 
                                $ago = time() - strtotime($notif['created_at']);
                                if ($ago < 60) {
                                    echo 'Baru saja';
                                } elseif ($ago < 3600) {
                                    echo floor($ago / 60) . ' menit lalu';
                                } elseif ($ago < 86400) {
                                    echo floor($ago / 3600) . ' jam lalu';
                                } else {
                                    echo floor($ago / 86400) . ' hari lalu';
                                }
                            ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($recent_notifications)): ?>
            <div class="notification-dropdown-footer">
                <a href="halaman_notifikasi.php">Lihat Semua Notifikasi →</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleNotificationDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.toggle('active');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const widget = document.querySelector('.notification-widget');
        const dropdown = document.getElementById('notificationDropdown');
        if (!widget.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });

    // Auto-refresh notifications every 30 seconds
    setInterval(() => {
        fetch('api_notifikasi.php?action=get_unread')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.notification-badge');
                if (data.unread_count > 0) {
                    if (!badge) {
                        const notifBell = document.querySelector('.notification-bell');
                        const newBadge = document.createElement('div');
                        newBadge.className = 'notification-badge';
                        newBadge.textContent = Math.min(data.unread_count, 99);
                        notifBell.appendChild(newBadge);
                    } else {
                        badge.textContent = Math.min(data.unread_count, 99);
                    }
                } else if (badge) {
                    badge.remove();
                }
            });
    }, 30000);
</script>
