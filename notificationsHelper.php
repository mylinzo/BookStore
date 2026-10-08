<?php
/**
 * notificationsHelper.php
 * Helper functions for notification system
 * Diperbaiki untuk menampilkan chat terbaru dan pesanan masuk TANPA DUPLIKASI CHAT
 */

/**
 * Mendapatkan semua notifikasi untuk user
 */
function getNotifications($conn, $user_id, $role = 'penjual') {
    $notifications = [];
    $total_unread = 0;
    
    if (!$conn || !$user_id) {
        return [
            'total' => 0,
            'order' => 0,
            'chat' => 0,
            'list' => []
        ];
    }
    
    // Cek apakah ada tabel notifications
    $table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    
    if ($table_exists && mysqli_num_rows($table_exists) > 0) {
        // Prioritaskan ambil dari tabel notifications jika ada
        return getNotificationsFromTable($conn, $user_id, $role);
    }
    
    // Fallback ke metode langsung dari tabel pesanan dan chat
    return getNotificationsFallback($conn, $user_id, $role);
}

/**
 * Mendapatkan notifikasi dari tabel notifications
 */
function getNotificationsFromTable($conn, $user_id, $role = 'penjual') {
    $notifications = [];
    $total_unread = 0;
    $order_count = 0;
    $chat_count = 0;
    
    // Ambil dari tabel notifications
    $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Mapping ke format yang seragam
            $type = $row['type'];
            
            $notif = [
                'type' => $type,
                'id' => $row['reference_id'] ?? $row['id_notif'],
                'title' => $row['title'],
                'message' => $row['message'],
                'created_at' => $row['created_at'],
                'link' => $row['link'] ?? '#',
                'dibaca' => ($row['status'] === 'dibaca') ? 1 : 0,
                'status' => $row['status'] ?? 'belum_dibaca'
            ];
            $notifications[] = $notif;
            
            if ($row['status'] === 'belum_dibaca') {
                $total_unread++;
                if ($type === 'order' || $type === 'pesanan') {
                    $order_count++;
                } elseif ($type === 'chat') {
                    $chat_count++;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Gabungkan dengan notifikasi real-time dari database utama
    $realtime_notifications = getRealtimeNotifications($conn, $user_id, $role);
    
    // Merge tanpa duplikasi (khusus untuk chat)
    $all_notifications = mergeNotificationsWithoutDuplicateChat($notifications, $realtime_notifications);
    
    // Urutkan berdasarkan created_at (terbaru dulu)
    usort($all_notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Hitung ulang total unread
    $total_unread = 0;
    $order_count = 0;
    $chat_count = 0;
    
    foreach ($all_notifications as $notif) {
        if (isset($notif['dibaca']) && $notif['dibaca'] == 0) {
            $total_unread++;
            if ($notif['type'] === 'order' || $notif['type'] === 'pesanan') {
                $order_count++;
            } elseif ($notif['type'] === 'chat') {
                $chat_count++;
            }
        }
    }
    
    return [
        'total' => $total_unread,
        'order' => $order_count,
        'chat' => $chat_count,
        'list' => array_slice($all_notifications, 0, 30)
    ];
}

/**
 * Menggabungkan notifikasi tanpa duplikasi chat
 * Fungsi ini hanya mencegah duplikasi untuk notifikasi chat
 */
function mergeNotificationsWithoutDuplicateChat($notifications1, $notifications2) {
    $merged = [];
    $seen_chat_ids = []; // Khusus untuk melacak chat ID yang sudah ada
    
    // Proses notifikasi pertama (dari tabel notifications)
    foreach ($notifications1 as $notif) {
        $merged[] = $notif;
        
        // Catat chat ID jika ini adalah notifikasi chat
        if ($notif['type'] === 'chat' && isset($notif['id'])) {
            $seen_chat_ids['chat_' . $notif['id']] = true;
        }
    }
    
    // Proses notifikasi kedua (real-time), hanya tambahkan jika chat ID belum ada
    foreach ($notifications2 as $notif) {
        if ($notif['type'] === 'chat') {
            // Untuk chat, cek apakah sudah ada berdasarkan ID chat
            $chat_key = 'chat_' . $notif['id'];
            if (!isset($seen_chat_ids[$chat_key])) {
                $merged[] = $notif;
                $seen_chat_ids[$chat_key] = true;
            }
        } else {
            // Untuk order, tetap tambahkan semua (bisa duplikat tapi jarang)
            $merged[] = $notif;
        }
    }
    
    return $merged;
}

/**
 * Mendapatkan notifikasi real-time dari database utama (pesanan dan chat)
 */
function getRealtimeNotifications($conn, $user_id, $role) {
    $notifications = [];
    
    if ($role === 'penjual') {
        // NOTIFIKASI PESANAN MASUK - Berdasarkan pesanan yang sudah berhasil transaksi
        // Menampilkan semua pesanan dengan status yang relevan untuk penjual
        $order_query = "
            SELECT 
                'order' as type,
                p.id_pesanan as id,
                p.status,
                p.total_harga,
                p.created_at,
                p.bukti_pembayaran,
                u.nama as pembeli_nama,
                CONCAT('Pesanan #', p.id_pesanan, ' dari ', u.nama) as title,
                CASE 
                    WHEN p.status = 'menunggu_pembayaran' THEN CONCAT('Menunggu pembayaran - Total: Rp ', FORMAT(p.total_harga, 0, 'id_ID'))
                    WHEN p.status = 'menunggu_verifikasi' THEN CONCAT('Menunggu verifikasi pembayaran - Total: Rp ', FORMAT(p.total_harga, 0, 'id_ID'))
                    WHEN p.status = 'diproses' THEN CONCAT('Pesanan perlu diproses - Total: Rp ', FORMAT(p.total_harga, 0, 'id_ID'))
                    WHEN p.status = 'dikirim' THEN 'Pesanan dalam pengiriman'
                    WHEN p.status = 'selesai' THEN 'Pesanan selesai'
                    WHEN p.status = 'dibatalkan' THEN 'Pesanan dibatalkan'
                    WHEN p.status = 'refund' THEN 'Pengajuan refund'
                    ELSE CONCAT('Status: ', p.status)
                END as message,
                CONCAT('pesanan_penjual.php?id=', p.id_pesanan) as link,
                CASE 
                    WHEN p.status IN ('menunggu_pembayaran', 'menunggu_verifikasi', 'diproses', 'refund') THEN 0
                    ELSE 1
                END as dibaca,
                p.updated_at
            FROM pesanan p
            JOIN users u ON p.id_pembeli = u.id_user
            WHERE p.id_penjual = ?
            ORDER BY p.created_at DESC
            LIMIT 30
        ";
        
        $stmt = mysqli_prepare($conn, $order_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $order_result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($order_result)) {
                // Set created_at ke updated_at jika ada, agar notifikasi lebih real-time
                if (!empty($row['updated_at']) && $row['updated_at'] != $row['created_at']) {
                    $row['created_at'] = $row['updated_at'];
                }
                unset($row['updated_at']);
                $notifications[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        
        // NOTIFIKASI CHAT TERBARU - Hanya ambil chat yang belum dibaca
        // dan pastikan tidak ada duplikasi dengan menggunakan GROUP BY
        $chat_query = "
            SELECT 
                'chat' as type,
                MAX(c.id_chat) as id,
                MAX(c.dibaca) as dibaca,
                MAX(c.created_at) as created_at,
                CONCAT('Pesan dari ', u.nama) as title,
                LEFT(MAX(c.pesan), 100) as message,
                CONCAT('penjual_chat.php?user=', c.id_pengirim) as link
            FROM chat c
            JOIN users u ON c.id_pengirim = u.id_user
            WHERE c.id_penerima = ?
            GROUP BY c.id_pengirim, DATE(c.created_at)
            HAVING MAX(c.dibaca) = 0
            ORDER BY MAX(c.created_at) DESC
            LIMIT 30
        ";
        
        $stmt = mysqli_prepare($conn, $chat_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $chat_result = mysqli_stmt_get_result($stmt);
            
            // Gunakan array untuk memastikan tidak ada duplikasi ID chat
            $processed_chat_ids = [];
            
            while ($row = mysqli_fetch_assoc($chat_result)) {
                // Pastikan ID chat unik
                if (!isset($processed_chat_ids[$row['id']])) {
                    $notifications[] = $row;
                    $processed_chat_ids[$row['id']] = true;
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // Ambil juga 3 chat terbaru (meskipun sudah dibaca) untuk konteks
        $recent_chat_query = "
            SELECT 
                'chat' as type,
                c.id_chat as id,
                c.dibaca,
                c.created_at,
                CONCAT('Pesan dari ', u.nama) as title,
                LEFT(c.pesan, 100) as message,
                CONCAT('penjual_chat.php?user=', c.id_pengirim) as link
            FROM chat c
            JOIN users u ON c.id_pengirim = u.id_user
            WHERE c.id_penerima = ?
            ORDER BY c.created_at DESC
            LIMIT 3
        ";
        
        $stmt = mysqli_prepare($conn, $recent_chat_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $recent_result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($recent_result)) {
                // Hanya tambahkan jika belum ada di notifikasi
                $exists = false;
                foreach ($notifications as $existing) {
                    if ($existing['type'] === 'chat' && $existing['id'] == $row['id']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $notifications[] = $row;
                }
            }
            mysqli_stmt_close($stmt);
        }
        
    } elseif ($role === 'pembeli') {
        // NOTIFIKASI PESANAN UNTUK PEMBELI
        $order_query = "
            SELECT 
                'order' as type,
                p.id_pesanan as id,
                p.status,
                p.total_harga,
                p.created_at,
                CONCAT('Pesanan #', p.id_pesanan) as title,
                CASE
                    WHEN p.status = 'menunggu_pembayaran' THEN 'Silakan lakukan pembayaran'
                    WHEN p.status = 'menunggu_verifikasi' THEN 'Pembayaran sedang diverifikasi'
                    WHEN p.status = 'diproses' THEN 'Pesanan sedang diproses penjual'
                    WHEN p.status = 'dikirim' THEN 'Pesanan dalam perjalanan'
                    WHEN p.status = 'selesai' THEN 'Pesanan telah diterima'
                    WHEN p.status = 'dibatalkan' THEN 'Pesanan dibatalkan'
                    WHEN p.status = 'refund' THEN 'Pengajuan refund diproses'
                    ELSE CONCAT('Status: ', p.status)
                END as message,
                CONCAT('riwayat_pembelian.php?id=', p.id_pesanan) as link,
                CASE 
                    WHEN p.status IN ('menunggu_pembayaran', 'menunggu_verifikasi', 'diproses', 'dikirim') THEN 0
                    ELSE 1
                END as dibaca,
                p.updated_at
            FROM pesanan p
            WHERE p.id_pembeli = ?
            ORDER BY p.created_at DESC
            LIMIT 30
        ";
        
        $stmt = mysqli_prepare($conn, $order_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $order_result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($order_result)) {
                if (!empty($row['updated_at']) && $row['updated_at'] != $row['created_at']) {
                    $row['created_at'] = $row['updated_at'];
                }
                unset($row['updated_at']);
                $notifications[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        
        // NOTIFIKASI CHAT UNTUK PEMBELI - Hanya chat yang belum dibaca
        $chat_query = "
            SELECT 
                'chat' as type,
                MAX(c.id_chat) as id,
                MAX(c.dibaca) as dibaca,
                MAX(c.created_at) as created_at,
                CONCAT('Pesan dari ', u.nama) as title,
                LEFT(MAX(c.pesan), 100) as message,
                CONCAT('pembeli_chat.php?user=', c.id_pengirim) as link
            FROM chat c
            JOIN users u ON c.id_pengirim = u.id_user
            WHERE c.id_penerima = ?
            GROUP BY c.id_pengirim, DATE(c.created_at)
            HAVING MAX(c.dibaca) = 0
            ORDER BY MAX(c.created_at) DESC
            LIMIT 30
        ";
        
        $stmt = mysqli_prepare($conn, $chat_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $chat_result = mysqli_stmt_get_result($stmt);
            
            $processed_chat_ids = [];
            
            while ($row = mysqli_fetch_assoc($chat_result)) {
                if (!isset($processed_chat_ids[$row['id']])) {
                    $notifications[] = $row;
                    $processed_chat_ids[$row['id']] = true;
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // Ambil juga 3 chat terbaru untuk konteks
        $recent_chat_query = "
            SELECT 
                'chat' as type,
                c.id_chat as id,
                c.dibaca,
                c.created_at,
                CONCAT('Pesan dari ', u.nama) as title,
                LEFT(c.pesan, 100) as message,
                CONCAT('pembeli_chat.php?user=', c.id_pengirim) as link
            FROM chat c
            JOIN users u ON c.id_pengirim = u.id_user
            WHERE c.id_penerima = ?
            ORDER BY c.created_at DESC
            LIMIT 3
        ";
        
        $stmt = mysqli_prepare($conn, $recent_chat_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $recent_result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($recent_result)) {
                $exists = false;
                foreach ($notifications as $existing) {
                    if ($existing['type'] === 'chat' && $existing['id'] == $row['id']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $notifications[] = $row;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    return $notifications;
}

/**
 * Fungsi fallback untuk mendapatkan notifikasi langsung dari database
 */
function getNotificationsFallback($conn, $user_id, $role) {
    return getRealtimeNotifications($conn, $user_id, $role);
}

/**
 * Membuat notifikasi baru (simpan ke tabel notifications)
 * Dilengkapi pengecekan duplikasi untuk chat
 */
function createNotification($conn, $user_id, $type, $reference_id, $title, $message, $link = '#') {
    // Buat tabel notifications jika belum ada
    $create_table = "CREATE TABLE IF NOT EXISTS notifications (
        id_notif INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(20) NOT NULL,
        reference_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link VARCHAR(255),
        status ENUM('belum_dibaca', 'dibaca') DEFAULT 'belum_dibaca',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        read_at DATETIME NULL,
        INDEX (user_id, status),
        INDEX (type, reference_id)
    )";
    mysqli_query($conn, $create_table);
    
    // Untuk chat, cek apakah sudah ada notifikasi serupa dalam 1 jam terakhir
    if ($type === 'chat') {
        $check_query = "SELECT id_notif FROM notifications 
                        WHERE user_id = ? AND type = 'chat' AND reference_id = ? 
                        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $reference_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Notifikasi chat sudah ada dalam 1 jam terakhir, jangan buat duplikat
            mysqli_stmt_close($check_stmt);
            return false;
        }
        mysqli_stmt_close($check_stmt);
    }
    
    // Simpan notifikasi
    $insert = "INSERT INTO notifications (user_id, type, reference_id, title, message, link, created_at) 
               VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $insert);
    mysqli_stmt_bind_param($stmt, "isssss", $user_id, $type, $reference_id, $title, $message, $link);
    
    if (mysqli_stmt_execute($stmt)) {
        $id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return $id;
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Mendapatkan jumlah notifikasi yang belum dibaca
 */
function getUnreadCount($conn, $user_id, $role = 'penjual') {
    // Cek tabel notifications dulu
    $table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    
    if ($table_exists && mysqli_num_rows($table_exists) > 0) {
        $query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'belum_dibaca'";
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            return (int)$data['total'];
        }
    }
    
    // Fallback - hitung manual
    $total = 0;
    
    if ($role === 'penjual') {
        // Hitung pesanan dengan status menunggu
        $order_query = "SELECT COUNT(*) as count FROM pesanan WHERE id_penjual = ? AND status IN ('menunggu_pembayaran', 'menunggu_verifikasi', 'diproses', 'refund')";
        $stmt = mysqli_prepare($conn, $order_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $total += (int)$row['count'];
            mysqli_stmt_close($stmt);
        }
        
        // Hitung chat belum dibaca (hitung per pengirim, bukan per pesan)
        $chat_query = "SELECT COUNT(DISTINCT id_pengirim) as count FROM chat WHERE id_penerima = ? AND dibaca = 0";
        $stmt = mysqli_prepare($conn, $chat_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $total += (int)$row['count'];
            mysqli_stmt_close($stmt);
        }
    } elseif ($role === 'pembeli') {
        // Hitung pesanan dengan status penting
        $order_query = "SELECT COUNT(*) as count FROM pesanan WHERE id_pembeli = ? AND status IN ('menunggu_pembayaran', 'menunggu_verifikasi', 'diproses', 'dikirim')";
        $stmt = mysqli_prepare($conn, $order_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $total += (int)$row['count'];
            mysqli_stmt_close($stmt);
        }
        
        // Hitung chat belum dibaca (hitung per pengirim)
        $chat_query = "SELECT COUNT(DISTINCT id_pengirim) as count FROM chat WHERE id_penerima = ? AND dibaca = 0";
        $stmt = mysqli_prepare($conn, $chat_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $total += (int)$row['count'];
            mysqli_stmt_close($stmt);
        }
    }
    
    return $total;
}

/**
 * Mendapatkan daftar notifikasi untuk AJAX API
 */
function getNotificationsList($conn, $user_id, $limit = 10) {
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'penjual';
    
    // Gunakan fungsi getNotifications yang sudah ada
    $notifData = getNotifications($conn, $user_id, $role);
    
    // Format untuk API
    $list = [];
    foreach (array_slice($notifData['list'], 0, $limit) as $notif) {
        $icon = ($notif['type'] === 'chat') ? 'fa-comment' : 'fa-shopping-cart';
        
        $list[] = [
            'id' => isset($notif['id']) ? $notif['id'] : '',
            'type' => isset($notif['type']) ? $notif['type'] : 'order',
            'title' => isset($notif['title']) ? $notif['title'] : 'Notifikasi',
            'message' => isset($notif['message']) ? $notif['message'] : '',
            'created_at' => isset($notif['created_at']) ? $notif['created_at'] : date('Y-m-d H:i:s'),
            'link' => isset($notif['link']) ? $notif['link'] : '#',
            'dibaca' => isset($notif['dibaca']) ? (int)$notif['dibaca'] : 0,
            'icon' => $icon
        ];
    }
    
    return $list;
}

/**
 * Menandai notifikasi sebagai dibaca
 */
function markNotificationsAsRead($conn, $user_id, $type = 'all', $role = 'penjual') {
    $success = false;
    
    if (!$conn || !$user_id) {
        return false;
    }
    
    // Update di tabel notifications
    $table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    if ($table_exists && mysqli_num_rows($table_exists) > 0) {
        if ($type === 'all') {
            $query = "UPDATE notifications SET status = 'dibaca', read_at = NOW() WHERE user_id = ? AND status = 'belum_dibaca'";
            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $success = true;
                mysqli_stmt_close($stmt);
            }
        } else {
            $query = "UPDATE notifications SET status = 'dibaca', read_at = NOW() WHERE user_id = ? AND type = ? AND status = 'belum_dibaca'";
            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "is", $user_id, $type);
                mysqli_stmt_execute($stmt);
                $success = true;
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Update chat yang belum dibaca
    if ($type === 'all' || $type === 'chat') {
        $chat_query = "UPDATE chat SET dibaca = 1 WHERE id_penerima = ? AND dibaca = 0";
        $stmt = mysqli_prepare($conn, $chat_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $success = true;
        }
    }
    
    return $success;
}
?>