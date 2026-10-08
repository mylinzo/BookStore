<?php
/**
 * Halaman Notifikasi User
 * Menampilkan semua notifikasi pembayaran, pengiriman, dll
 */

session_start();
include 'koneksi.php';

// Check login
if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

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

$id_user = $_SESSION['id_user'];

// Get notifications
$query = "
    SELECT id_notifikasi, tipe, pesan, data_ref, status, created_at 
    FROM notifikasi 
    WHERE id_user = ?
    ORDER BY created_at DESC
    LIMIT 100
";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id_user);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $notifications = [];
}

// Notification icons and colors
$notification_config = [
    'payment_verified' => [
        'icon' => 'fa-check-circle',
        'color' => '#10b981',
        'bg_color' => '#d1fae5',
        'label' => 'Pembayaran Terverifikasi'
    ],
    'payment_rejected' => [
        'icon' => 'fa-times-circle',
        'color' => '#ef4444',
        'bg_color' => '#fee2e2',
        'label' => 'Pembayaran Ditolak'
    ],
    'delivery_confirmed' => [
        'icon' => 'fa-check-double',
        'color' => '#3b82f6',
        'bg_color' => '#dbeafe',
        'label' => 'Pesanan Dikonfirmasi Diterima'
    ],
    'order_shipped' => [
        'icon' => 'fa-truck',
        'color' => '#f59e0b',
        'bg_color' => '#fef3c7',
        'label' => 'Pesanan Dikirim'
    ],
    'payment_pending' => [
        'icon' => 'fa-hourglass-end',
        'color' => '#8b5cf6',
        'bg_color' => '#ede9fe',
        'label' => 'Menunggu Pembayaran'
    ]
];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Aksara-Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f3f4f6;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid #e5e7eb;
        }

        .notification-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .notification-card.unread {
            background: #f9fafb;
            border-left-color: #667eea;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 22px;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .notification-time {
            font-size: 12px;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .notification-badge {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: auto;
            align-self: flex-start;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 15px;
        }

        .empty-state h2 {
            color: #6b7280;
            font-size: 22px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #9ca3af;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .notification-card {
                padding: 15px;
                gap: 12px;
            }

            .notification-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>🔔 Notifikasi</h1>
            </div>
            <div class="header-actions">
                <button class="btn" onclick="markAllAsRead()" title="Tandai semua sebagai dibaca">
                    <i class="fas fa-envelope-open"></i> Tandai Semua Dibaca
                </button>
                <a href="javascript:history.back()" class="btn">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="notifications-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h2>Tidak ada notifikasi</h2>
                    <p>Anda tidak memiliki notifikasi saat ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif):
                    $config = $notification_config[$notif['tipe']] ?? [
                        'icon' => 'fa-bell',
                        'color' => '#667eea',
                        'bg_color' => '#eef2ff',
                        'label' => ucfirst(str_replace('_', ' ', $notif['tipe']))
                    ];
                    $time_diff = time() - strtotime($notif['created_at']);
                    $time_text = $this->getTimeAgo($time_diff);
                ?>
                <div class="notification-card <?php echo $notif['status'] === 'unread' ? 'unread' : ''; ?>" 
                     onclick="markAsRead(<?php echo $notif['id_notifikasi']; ?>)">
                    <div class="notification-icon" style="background-color: <?php echo $config['bg_color']; ?>;">
                        <i class="fas <?php echo $config['icon']; ?>" style="color: <?php echo $config['color']; ?>;"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title"><?php echo $config['label']; ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notif['pesan']); ?></div>
                        <div class="notification-time">
                            <i class="fas fa-clock"></i>
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
                    <?php if ($notif['status'] === 'unread'): ?>
                        <div class="notification-badge">BARU</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function markAsRead(notificationId) {
            fetch('api_notifikasi.php?action=mark_read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_notifikasi: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function markAllAsRead() {
            if (confirm('Tandai semua notifikasi sebagai sudah dibaca?')) {
                fetch('api_notifikasi.php?action=mark_all_read', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
    </script>
</body>
</html>
