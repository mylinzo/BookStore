<?php
/**
 * Halaman notifikasi untuk penjual (atau admin) melihat pesanan/chat/produk
 * menggunakan sistem notificationsHelper yang sama dengan dropdown navbar.
 */

session_start();
include 'koneksi.php';
require_once 'notificationsHelper.php';

// hanya boleh diakses oleh penjual atau super_admin
if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}
$user_id = intval($_SESSION['id_user']);
$user_role = $_SESSION['role'] ?? '';
if ($user_role !== 'penjual' && $user_role !== 'super_admin') {
    // bisa ditambahkan role lain jika perlu
    echo "<p>Anda tidak memiliki akses ke halaman ini.</p>";
    exit;
}

// ambil notifikasi terakhir (limit 200)
$notifications = getNotificationsList($conn, $user_id, 200);

// helper kecil untuk format waktu
function fmtTimeAgo($ts) {
    $now = time();
    $past = strtotime($ts);
    $diff = $now - $past;
    if ($diff < 60) return 'baru saja';
    if ($diff < 3600) return floor($diff/60) . ' menit yang lalu';
    if ($diff < 86400) return floor($diff/3600) . ' jam yang lalu';
    return floor($diff/86400) . ' hari yang lalu';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi Penjual</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background:#f3f4f6; color:#333; margin:0; padding:0; }
        .container { max-width:800px; margin:20px auto; padding:0 15px; }
        h1 { font-size:24px; margin-bottom:10px; }
        .actions { margin-bottom:20px; }
        .actions .btn { display:inline-block; padding:8px 14px; background:#8B4513; color:#fff; border-radius:4px; text-decoration:none; margin-right:10px; }
        .notif-list { display:flex; flex-direction:column; gap:12px; }
        .notif-card { background:#fff; padding:12px 15px; border-radius:8px; display:flex; gap:12px; align-items:center; box-shadow:0 1px 4px rgba(0,0,0,0.1); cursor:pointer; }
        .notif-card.unread { background:#fff7e6; border-left:4px solid #f59e0b; }
        .notif-icon { width:40px; height:40px; flex-shrink:0; border-radius:6px; background:#eee; display:flex; align-items:center; justify-content:center; font-size:18px; }
        .notif-img { width:40px; height:40px; object-fit:cover; border-radius:6px; }
        .notif-content { flex:1; }
        .notif-title { font-weight:600; margin-bottom:4px; }
        .notif-msg { font-size:14px; color:#555; margin-bottom:4px; }
        .notif-time { font-size:12px; color:#777; }
        @media (max-width:600px) { .notif-card { flex-direction:column; align-items:flex-start; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="actions">
            <a href="#" class="btn" onclick="markAll(event)"><i class="fas fa-envelope-open"></i> Tandai semua dibaca</a>
            <a href="javascript:history.back()" class="btn"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
        <h1>🔔 Notifikasi Anda</h1>
        <div class="notif-list" id="notifList">
            <?php if (empty($notifications)): ?>
                <p>Tidak ada notifikasi.</p>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <?php
                        // normalize fields coming from notifications table or legacy fallback
                        $notif_id = isset($n['id_notif']) ? intval($n['id_notif']) : (isset($n['id']) ? $n['id'] : null);
                        $type = $n['type'] ?? ($n['tipe'] ?? 'pesanan');
                        $icon = 'fa-bell';
                        if ($type === 'chat') $icon = 'fa-comment';
                        elseif ($type === 'pesanan' || $type === 'order') $icon = 'fa-shopping-cart';
                        elseif ($type === 'produk') $icon = 'fa-box';

                        // determine link
                        $link = '#';
                        if (!empty($n['link'])) {
                            $link = $n['link'];
                        } elseif (!empty($n['reference_id'])) {
                            $link = 'pesanan_penjual.php?id=' . intval($n['reference_id']);
                        } elseif (!empty($n['data_ref'])) {
                            $link = 'pesanan_penjual.php?id=' . intval($n['data_ref']);
                        } elseif (isset($n['id'])) {
                            $link = 'pesanan_penjual.php?id=' . intval($n['id']);
                        }

                        // determine unread status (support 'dibaca' or 'status')
                        $isUnread = false;
                        if (isset($n['dibaca'])) {
                            $isUnread = intval($n['dibaca']) === 0;
                        } elseif (isset($n['status'])) {
                            $isUnread = ($n['status'] === 'belum_dibaca');
                        }

                        $title = $n['title'] ?? ($n['pesan'] ?? ($n['message'] ?? 'Notifikasi'));
                        $message = $n['message'] ?? ($n['pesan'] ?? '');
                        $created = $n['created_at'] ?? ($n['tanggal'] ?? date('Y-m-d H:i:s'));
                        $data_id = $notif_id ? intval($notif_id) : htmlspecialchars($type . '_' . ($n['id'] ?? ''));
                    ?>
                    <div class="notif-card <?php echo $isUnread ? 'unread' : ''; ?>" data-id="<?php echo $data_id; ?>" data-link="<?php echo htmlspecialchars($link); ?>">
                        <div class="notif-icon">
                            <?php if (!empty($n['image'])): ?>
                                <img src="<?php echo htmlspecialchars($n['image']); ?>" class="notif-img" alt="">
                            <?php else: ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="notif-content">
                            <div class="notif-title"><?php echo htmlspecialchars($title); ?></div>
                            <div class="notif-msg"><?php echo htmlspecialchars($message); ?></div>
                            <div class="notif-time"><?php echo fmtTimeAgo($created); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.notif-card').forEach(el => {
            el.addEventListener('click', e => {
                const id = el.dataset.id;
                const link = el.dataset.link || '#';

                // if id is numeric (id_notif), send to API to mark read
                const numericId = parseInt(id, 10);
                if (!isNaN(numericId) && numericId > 0) {
                    fetch('api_mark_notification_read.php', {
                        method: 'POST',
                        headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({ id_notif: numericId })
                    }).finally(() => { window.location.href = link; });
                } else {
                    // fallback: just navigate
                    window.location.href = link;
                }
            });
        });

        function markAll(e) {
            e.preventDefault();
            fetch('api_mark_notifications_read.php', { method:'POST' })
                .then(r=>r.json())
                .then(d=>{ if(d.success) location.reload(); })
                .catch(()=>{});
        }
    </script>
</body>
</html>
