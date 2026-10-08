# 🔧 IMPLEMENTASI TECHNICAL - Approval System Detail

## 📁 File Structure

```
approval system terdiri dari 3 file utama:
├── penjual_approve.php          ← UI Dashboard penjual
├── transaction_logger.php       ← Helper class untuk logging
└── api_order_status.php         ← API untuk confirm delivery
```

---

## 1️⃣ FILE: penjual_approve.php

### Location
```
c:\laragon\www\toko-buku\toko-buku\penjual_approve.php
```

### Main Functions

#### A) Post Handler (Approve/Reject/Delete)
```php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $id_penjual = $_SESSION['user_id'];
        
        // APPROVE ACTION
        if ($_POST['action'] === 'approve') {
            $id_transaksi = intval($_POST['id_transaksi']);
            $resi = mysqli_real_escape_string($conn, $_POST['resi']);
            
            // 1. Verify transaksi & ownership
            $query = "SELECT * FROM transaksi 
                     WHERE id_transaksi = ? AND id_penjual = ? AND status = 'dibayar'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $id_transaksi, $id_penjual);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result->fetch_assoc()) {
                throw new Exception("Transaksi tidak ditemukan atau status tidak sesuai");
            }
            
            // 2. Update transaksi → dikirim
            $update_query = "UPDATE transaksi 
                             SET status = 'dikirim', resi = ?, updated_at = NOW() 
                             WHERE id_transaksi = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $resi, $id_transaksi);
            $update_stmt->execute();
            
            // 3. Get pesanan untuk update
            $pesanan_query = "SELECT id_pesanan, id_pembeli FROM transaksi 
                              WHERE id_transaksi = ?";
            $pesanan_stmt = $conn->prepare($pesanan_query);
            $pesanan_stmt->bind_param("i", $id_transaksi);
            $pesanan_stmt->execute();
            $pesanan_row = $pesanan_stmt->get_result()->fetch_assoc();
            
            // 4. Update pesanan → dikirim
            $pesanan_update = "UPDATE pesanan 
                               SET status = 'dikirim', nomor_resi = ? 
                               WHERE id_pesanan = ?";
            $pesanan_stmt = $conn->prepare($pesanan_update);
            $pesanan_stmt->bind_param("si", $resi, $pesanan_row['id_pesanan']);
            $pesanan_stmt->execute();
            
            // 5. Log ke transaction_logger
            include 'transaction_logger.php';
            $txn_logger = new TransactionLogger($conn);
            $keterangan = "Penjual menyetujui & input nomor resi: " . $resi;
            $txn_logger->logStatusChange(
                $id_transaksi, 
                'dibayar', 
                'dikirim', 
                $keterangan, 
                $id_penjual
            );
            
            // 6. Kirim notifikasi ke pembeli
            $notif_query = "INSERT INTO notifikasi 
                           (id_user, tipe, pesan, data_ref, status, created_at) 
                           VALUES (?, 'order_approved', ?, ?, 'unread', NOW())";
            $notif_msg = "Pesanan #" . str_pad($pesanan_row['id_pesanan'], 5, '0', STR_PAD_LEFT) . 
                        " telah disetujui dan sedang dikirim. Resi: " . $resi;
            $notif_stmt = $conn->prepare($notif_query);
            $notif_stmt->bind_param("isi", $pesanan_row['id_pembeli'], $notif_msg, $id_transaksi);
            $notif_stmt->execute();
            
            $_SESSION['success_msg'] = "Transaksi berhasil di-approve!";
            header("Location: penjual_approve.php");
            exit();
        }
        
        // REJECT ACTION
        else if ($_POST['action'] === 'reject') {
            $id_transaksi = intval($_POST['id_transaksi']);
            $alasan = mysqli_real_escape_string($conn, $_POST['alasan'] ?? '');
            
            // Same verification as approve
            // ...
            
            // Update status to ditolak
            $update_query = "UPDATE transaksi 
                             SET status = 'ditolak', updated_at = NOW() 
                             WHERE id_transaksi = ?";
            // ...
            
            // Log the rejection
            $keterangan = "Penjual menolak transaksi. Alasan: " . $alasan;
            $txn_logger->logStatusChange(
                $id_transaksi,
                'dibayar',
                'ditolak',
                $keterangan,
                $id_penjual
            );
            
            // Notify pembeli
            $notif_msg = "Pesanan #... telah ditolak. Alasan: " . $alasan;
            // Insert ke notifikasi table
            
            $_SESSION['success_msg'] = "Transaksi berhasil ditolak!";
            header("Location: penjual_approve.php");
        }
        
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
        header("Location: penjual_approve.php");
    }
}
```

#### B) Get Data untuk Display
```php
// Get count by status
$count_dibayar = $conn->query("SELECT COUNT(*) as count FROM transaksi 
                              WHERE id_penjual = " . $_SESSION['user_id'] . " 
                              AND status = 'dibayar'")
                      ->fetch_assoc();

$count_dikirim = $conn->query("SELECT COUNT(*) as count FROM transaksi 
                              WHERE id_penjual = " . $_SESSION['user_id'] . " 
                              AND status = 'dikirim'")
                      ->fetch_assoc();

$count_selesai = $conn->query("SELECT COUNT(*) as count FROM transaksi 
                              WHERE id_penjual = " . $_SESSION['user_id'] . " 
                              AND status = 'selesai'")
                      ->fetch_assoc();

// Get transaksi untuk table display
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'dibayar';
$query = "SELECT 
            t.id_transaksi, t.tanggal, t.total_harga, t.status,
            t.bukti_pembayaran, t.metode_pembayaran, t.resi,
            u.nama as pembeli_nama, 
            p.nama_buku, dt.quantity
          FROM transaksi t
          INNER JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
          INNER JOIN produk p ON dt.id_produk = p.id_produk
          INNER JOIN users u ON t.id_pembeli = u.id_user
          WHERE t.id_penjual = ? AND t.status = ?
          ORDER BY t.tanggal DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $_SESSION['user_id'], $status_filter);
$stmt->execute();
$transaksi_list = $stmt->get_result();
```

#### C) HTML Table Display
```html
<table class="table">
  <thead>
    <tr>
      <th>Kode</th>
      <th>Judul</th>
      <th>Qty</th>
      <th>Bukti</th>
      <th>Metode</th>
      <th>Total</th>
      <th>Status</th>
      <th>Resi</th>
      <th>Pembeli</th>
      <th>Aksi</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $transaksi_list->fetch_assoc()) { ?>
    <tr>
      <td>#<?php echo str_pad($row['id_transaksi'], 5, '0', STR_PAD_LEFT); ?></td>
      <td><?php echo htmlspecialchars($row['nama_buku']); ?></td>
      <td><?php echo $row['quantity']; ?></td>
      <td>
        <img src="<?php echo htmlspecialchars($row['bukti_pembayaran']); ?>" 
             alt="Bukti" 
             class="proof-thumb" 
             onclick="openProofModal('<?php echo htmlspecialchars($row['bukti_pembayaran']); ?>')">
      </td>
      <td><?php echo htmlspecialchars($row['metode_pembayaran']); ?></td>
      <td>Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
      <td>
        <?php 
        $status_label = $row['status'] == 'dibayar' ? 'Menunggu Approve' : 
                       ($row['status'] == 'dikirim' ? 'Sedang Dikirim' : ucfirst($row['status']));
        ?>
        <span class="badge badge-<?php echo $row['status']; ?>">
          <?php echo $status_label; ?>
        </span>
      </td>
      <td><?php echo $row['resi'] ? htmlspecialchars($row['resi']) : '-'; ?></td>
      <td><?php echo htmlspecialchars($row['pembeli_nama']); ?></td>
      <td>
        <?php if ($row['status'] == 'dibayar') { ?>
          <button class="btn btn-sm btn-success" 
                  onclick="openApproveModal(<?php echo $row['id_transaksi']; ?>)">
            ✅ Approve
          </button>
          <button class="btn btn-sm btn-danger" 
                  onclick="openRejectModal(<?php echo $row['id_transaksi']; ?>)">
            🚫 Tolak
          </button>
        <?php } ?>
        <button class="btn btn-sm btn-info" 
                onclick="showDetail(<?php echo $row['id_transaksi']; ?>)">
          👁️ Detail
        </button>
      </td>
    </tr>
    <?php } ?>
  </tbody>
</table>
```

#### D) Modal untuk Approve
```html
<div id="approveModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h5>✅ Setujui & Kirim Pesanan</h5>
      <button type="button" class="close" onclick="closeApproveModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-info">
        📦 Pesanan akan berada dalam perjalanan. Input nomor resi
        agar pembeli bisa tracking.
      </div>
      <form id="approveForm" method="POST">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" id="approve_id_transaksi" name="id_transaksi">
        
        <div class="form-group">
          <label for="resi">Nomor Resi Pengiriman *</label>
          <input type="text" class="form-control" id="resi" name="resi" 
                 placeholder="Contoh: JNE1234567890" required>
          <small class="form-text text-muted">
            Resi akan dikirim ke pembeli untuk melacak paket
          </small>
        </div>
        
        <div class="form-group">
          <button type="button" class="btn btn-secondary" 
                  onclick="closeApproveModal()">Batal</button>
          <button type="submit" class="btn btn-success">
            Setujui & Kirim Pesanan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openApproveModal(id_transaksi) {
  document.getElementById('approve_id_transaksi').value = id_transaksi;
  document.getElementById('resi').value = '';
  document.getElementById('approveModal').classList.add('show');
}

function closeApproveModal() {
  document.getElementById('approveModal').classList.remove('show');
}

document.getElementById('approveForm')?.addEventListener('submit', function(e) {
  const resi = document.getElementById('resi').value.trim();
  if (!resi) {
    alert('Nomor resi tidak boleh kosong!');
    e.preventDefault();
  }
});
</script>
```

#### E) Modal untuk Reject
```html
<div id="rejectModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h5>🚫 Tolak Pesanan</h5>
      <button type="button" class="close" onclick="closeRejectModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-warning">
        ⚠️ Perhatian: Pesanan ini akan ditolak dan pembeli akan menerima notifikasi penolakan.
      </div>
      <form id="rejectForm" method="POST">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" id="reject_id_transaksi" name="id_transaksi">
        
        <div class="form-group">
          <label for="alasan">Alasan Penolakan (Optional)</label>
          <textarea class="form-control" id="alasan" name="alasan" rows="3"
                    placeholder="Contoh: Stok tidak tersedia, Pesanan tidak lengkap, dll..."></textarea>
          <small class="form-text text-muted">
            💬 Alasan akan dikirim ke pembeli untuk transparansi
          </small>
        </div>
        
        <div class="form-group">
          <button type="button" class="btn btn-secondary" 
                  onclick="closeRejectModal()">Batal</button>
          <button type="submit" class="btn btn-danger">
            Ya, Tolak Pesanan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
```

---

## 2️⃣ FILE: transaction_logger.php

### Location
```
c:\laragon\www\toko-buku\toko-buku\transaction_logger.php
```

### Class Definition
```php
class TransactionLogger {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Create transaksi from pesanan
     * Called during checkout
     */
    public function createTransactionFromOrder($id_pesanan, $id_pembeli, $id_penjual, $total_harga) {
        $query = "INSERT INTO transaksi 
                 (id_pesanan, id_pembeli, id_penjual, tanggal, total_harga, status, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), ?, 'menunggu_pembayaran', NOW(), NOW())";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => $this->conn->error];
        }
        
        $stmt->bind_param("iiid", $id_pesanan, $id_pembeli, $id_penjual, $total_harga);
        
        if ($stmt->execute()) {
            $id_transaksi = $this->conn->insert_id;
            return ['success' => true, 'id' => $id_transaksi];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Log status change to log_transaksi table
     * Auto-called by updateTransactionStatus & logStatusChange
     */
    public function logStatusChange($id_transaksi, $status_lama, $status_baru, $keterangan, $user_id) {
        $query = "INSERT INTO log_transaksi 
                 (id_transaksi, status_lama, status_baru, keterangan, user_id, tanggal_perubahan)
                 VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => $this->conn->error];
        }
        
        $stmt->bind_param("isssi", $id_transaksi, $status_lama, $status_baru, $keterangan, $user_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'id' => $this->conn->insert_id];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Add product detail to detail_transaksi
     * Called during checkout
     */
    public function addTransactionDetail($id_transaksi, $id_produk, $quantity, $harga_satuan) {
        $subtotal = $quantity * $harga_satuan;
        
        $query = "INSERT INTO detail_transaksi 
                 (id_transaksi, id_produk, quantity, harga_satuan, subtotal, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => $this->conn->error];
        }
        
        $stmt->bind_param("iiidd", $id_transaksi, $id_produk, $quantity, $harga_satuan, $subtotal);
        
        if ($stmt->execute()) {
            return ['success' => true, 'id' => $this->conn->insert_id];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Record payment proof
     * Called when pembeli upload bukti
     */
    public function recordPaymentProof($id_pesanan, $id_pembeli, $id_penjual, $metode, $jumlah, $filename) {
        $query = "INSERT INTO pembayaran_detail 
                 (id_pesanan, id_pembeli, id_penjual, metode, jumlah, bukti_pembayaran, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => $this->conn->error];
        }
        
        $stmt->bind_param("iiidss", $id_pesanan, $id_pembeli, $id_penjual, $metode, $jumlah, $filename);
        
        if ($stmt->execute()) {
            return ['success' => true, 'id' => $this->conn->insert_id];
        }
        
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Get audit trail for transaksi
     * Used for laporan/reporting
     */
    public function getAuditTrail($id_transaksi) {
        $query = "SELECT * FROM log_transaksi 
                 WHERE id_transaksi = ? 
                 ORDER BY tanggal_perubahan ASC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => $this->conn->error];
        }
        
        $stmt->bind_param("i", $id_transaksi);
        $stmt->execute();
        
        return ['success' => true, 'result' => $stmt->get_result()];
    }
}
```

### Usage Examples

#### Example 1: Create Transaction at Checkout
```php
include 'transaction_logger.php';
$txn_logger = new TransactionLogger($conn);

// After creating pesanan
$id_pesanan = 1001;
$id_pembeli = 5;
$id_penjual = 3;
$total_harga = 150000;

$result = $txn_logger->createTransactionFromOrder(
    $id_pesanan, $id_pembeli, $id_penjual, $total_harga
);

if ($result['success']) {
    $id_transaksi = $result['id']; // Use this for further operations
}
```

#### Example 2: Log Product Details
```php
// For each item in cart
$id_transaksi = 1;
$id_produk = 101;
$quantity = 2;
$harga_satuan = 75000;

$txn_logger->addTransactionDetail(
    $id_transaksi, $id_produk, $quantity, $harga_satuan
);
// Subtotal auto-calculated: 2 × 75000 = 150000
```

#### Example 3: Log Status Change (at Approve)
```php
// When penjual approves
$id_transaksi = 1;
$resi = "JNE1234567890";
$keterangan = "Penjual menyetujui & input nomor resi: " . $resi;
$user_id = 3; // penjual_id

$txn_logger->logStatusChange(
    $id_transaksi,
    'dibayar',
    'dikirim',
    $keterangan,
    $user_id
);

// Database entry:
// id_transaksi: 1
// status_lama: 'dibayar'
// status_baru: 'dikirim'
// keterangan: 'Penjual menyetujui & input nomor resi: JNE1234567890'
// user_id: 3 (penjual)
// tanggal_perubahan: 2024-01-15 14:30:45
```

#### Example 4: Get Audit Trail (for Reporting)
```php
$id_transaksi = 1;
$audit = $txn_logger->getAuditTrail($id_transaksi);

if ($audit['success']) {
    while ($log = $audit['result']->fetch_assoc()) {
        echo "Tanggal: " . $log['tanggal_perubahan'] . "\n";
        echo "Perubahan: " . $log['status_lama'] . " → " . $log['status_baru'] . "\n";
        echo "Keterangan: " . $log['keterangan'] . "\n";
        echo "---\n";
    }
}
```

---

## 3️⃣ DATABASE SCHEMA

### transaksi table
```sql
CREATE TABLE transaksi (
  id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan INT NOT NULL,
  id_pembeli INT NOT NULL,
  id_penjual INT NOT NULL,
  tanggal DATETIME,
  total_harga DECIMAL(12,2),
  status ENUM('menunggu_pembayaran', 'dibayar', 'dikirim', 'selesai', 'ditolak', 'refund'),
  metode_pembayaran VARCHAR(50),
  bukti_pembayaran VARCHAR(255),
  resi VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan),
  FOREIGN KEY (id_pembeli) REFERENCES users(id_user),
  FOREIGN KEY (id_penjual) REFERENCES users(id_user),
  INDEX idx_penjual_status (id_penjual, status),
  INDEX idx_tanggal (tanggal)
);
```

### log_transaksi table
```sql
CREATE TABLE log_transaksi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_transaksi INT NOT NULL,
  status_lama VARCHAR(50),
  status_baru VARCHAR(50),
  keterangan TEXT,
  user_id INT,
  tanggal_perubahan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi),
  FOREIGN KEY (user_id) REFERENCES users(id_user),
  INDEX idx_transaksi (id_transaksi),
  INDEX idx_tanggal (tanggal_perubahan)
);
```

### pembayaran_detail table
```sql
CREATE TABLE pembayaran_detail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_pesanan INT NOT NULL,
  id_pembeli INT NOT NULL,
  id_penjual INT NOT NULL,
  metode VARCHAR(50),
  jumlah DECIMAL(12,2),
  bukti_pembayaran VARCHAR(255),
  status ENUM('pending', 'confirmed', 'rejected'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan),
  FOREIGN KEY (id_pembeli) REFERENCES users(id_user),
  FOREIGN KEY (id_penjual) REFERENCES users(id_user),
  INDEX idx_pesanan (id_pesanan)
);
```

---

## 🔍 QUERY EXAMPLES FOR DEBUGGING

### Get all logs for one transaksi
```sql
SELECT * FROM log_transaksi 
WHERE id_transaksi = 1 
ORDER BY tanggal_perubahan ASC;
```

### Get pending approvals for one penjual
```sql
SELECT 
  t.id_transaksi, t.tanggal, t.total_harga,
  u.nama as pembeli_nama,
  p.nama_buku,
  dt.quantity
FROM transaksi t
JOIN users u ON t.id_pembeli = u.id_user
JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
JOIN produk p ON dt.id_produk = p.id_produk
WHERE t.id_penjual = 3 AND t.status = 'dibayar'
ORDER BY t.tanggal DESC;
```

### Get approval timeline for one pesanan
```sql
SELECT 
  lt.tanggal_perubahan,
  lt.status_lama,
  lt.status_baru,
  lt.keterangan,
  u.nama as user_name
FROM log_transaksi lt
JOIN transaksi t ON lt.id_transaksi = t.id_transaksi
LEFT JOIN users u ON lt.user_id = u.id_user
WHERE t.id_pesanan = 1001
ORDER BY lt.tanggal_perubahan ASC;
```

### Verify both pesanan & transaksi are in sync
```sql
SELECT 
  p.id_pesanan, p.status as pesanan_status,
  t.id_transaksi, t.status as transaksi_status,
  t.resi
FROM pesanan p
LEFT JOIN transaksi t ON p.id_pesanan = t.id_pesanan
WHERE p.id_pesanan = 1001;

-- Both status should be same (dikirim, selesai, etc)
-- resi should be same if status = dikirim
```

---

## 🐛 DEBUGGING CHECKLIST

```
[ ] Check penjual_approve.php line 1-50 untuk include files
    - include 'koneksi.php' ✅
    - include 'transaction_logger.php' ✅

[ ] Check POST form submission
    - Action field kosong? Should be 'approve' or 'reject'
    - id_transaksi field exist? Should be hidden input
    - resi/alasan field populated?

[ ] Check database after approve
    - transaksi.status = 'dikirim' ✅
    - transaksi.resi = '...' ✅
    - pesanan.status = 'dikirim' ✅
    - pesanan.nomor_resi = '...' ✅
    - log_transaksi entry created ✅

[ ] Check notification sent
    - SELECT * FROM notifikasi WHERE tipe = 'order_approved' ORDER BY created_at DESC LIMIT 1
    - Should have pesan với resi number

[ ] Check TransactionLogger execution
    - File exists: c:/laragon/www/toko-buku/toko-buku/transaction_logger.php
    - Class instantiated: $txn_logger = new TransactionLogger($conn)
    - Method called: $txn_logger->logStatusChange(...)

[ ] Check error messages
    - Look for try-catch exception
    - Check PHP error log
    - Browser console for JavaScript errors
```

---

## 🚀 DEPLOYMENT CHECKLIST

```
[ ] All 3 files exist:
    [✅] penjual_approve.php
    [✅] transaction_logger.php 
    [✅] api_order_status.php

[ ] Database tables created:
    [✅] transaksi
    [✅] log_transaksi
    [✅] pembayaran_detail
    [✅] detail_transaksi

[ ] File permissions (644 for PHP files)

[ ] Directory exists: uploads/bukti_pembayaran/

[ ] Test all 4 test cases:
    [ ] Test 1: Pembeli upload bukti
    [ ] Test 2: Penjual approve + resi
    [ ] Test 3: Penjual reject + alasan
    [ ] Test 4: Pembeli confirm terima

[ ] Production ready:
    [ ] No debug console.log() statements
    [ ] No hardcoded test data
    [ ] Error handling working (try-catch)
    [ ] Notifications sending correctly
```

