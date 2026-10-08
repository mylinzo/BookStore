<?php
/**
 * Script untuk menghapus produk spam dari penjual tertentu
 */
session_start();
include 'koneksi.php';

// Check admin access
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'super_admin') {
    die("Akses ditolak");
}

$action = $_GET['action'] ?? '';
$confirm = $_GET['confirm'] ?? '';

// Penjual yang akan dihapus produknya
$penjual_spam = ['Yeona', 'yeona', 'Felicia', 'felicia'];

// Get ID penjual spam
$sql_penjual = "SELECT id_user, nama FROM users WHERE nama IN ('" . implode("','", $penjual_spam) . "')";
$result_penjual = mysqli_query($conn, $sql_penjual);
$penjual_ids = [];
$penjual_info = [];

while ($row = mysqli_fetch_assoc($result_penjual)) {
    $penjual_ids[] = $row['id_user'];
    $penjual_info[$row['id_user']] = $row['nama'];
}

// Get total produk dari penjual spam
$produk_list = [];
if (!empty($penjual_ids)) {
    $placeholders = implode(',', $penjual_ids);
    $sql_produk = "SELECT id_produk, nama_produk, foto_produk, id_penjual FROM produk WHERE id_penjual IN ($placeholders) ORDER BY id_penjual, nama_produk";
    $result_produk = mysqli_query($conn, $sql_produk);
    
    while ($row = mysqli_fetch_assoc($result_produk)) {
        $produk_list[] = $row;
    }
}

// Handle delete action
$delete_result = null;
if ($action === 'delete' && $confirm === '1') {
    if (!empty($produk_list)) {
        $deleted_count = 0;
        foreach ($produk_list as $produk) {
            $id_produk = $produk['id_produk'];
            
            // Hapus file foto jika ada
            if (!empty($produk['foto_produk'])) {
                $foto_path = 'uploads/produk/' . $produk['foto_produk'];
                if (file_exists($foto_path)) {
                    @unlink($foto_path);
                }
            }
            
            // Hapus dari database
            $sql_delete = "DELETE FROM produk WHERE id_produk = ?";
            $stmt = mysqli_prepare($conn, $sql_delete);
            mysqli_stmt_bind_param($stmt, "i", $id_produk);
            
            if (mysqli_stmt_execute($stmt)) {
                $deleted_count++;
            }
        }
        $delete_result = $deleted_count;
        header("Location: delete_spam_products.php?success=1&count=" . $deleted_count);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Produk Spam</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
            border-bottom: 3px solid #d81b60;
            padding-bottom: 10px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        
        .product-count {
            font-size: 18px;
            font-weight: bold;
            color: #d81b60;
            margin: 20px 0;
        }
        
        .product-list {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .product-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-name {
            flex: 1;
        }
        
        .product-seller {
            background: #e0e0e0;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-delete {
            background: #d81b60;
            color: white;
        }
        
        .btn-delete:hover {
            background: #ad1457;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .no-products {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-trash-alt"></i> Hapus Produk Spam</h1>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <strong>Berhasil!</strong> <?php echo $_GET['count']; ?> produk spam telah dihapus.
            </div>
        <?php endif; ?>
        
        <?php if (empty($penjual_ids)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada penjual dengan nama 'Yeona' atau 'Felicia' di sistem.
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian!</strong> Operasi ini akan menghapus semua produk dari penjual berikut secara permanen.
            </div>
            
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="margin-bottom: 10px;">Penjual Spam:</h3>
                <ul style="margin-left: 20px;">
                    <?php foreach ($penjual_info as $id => $nama): ?>
                        <li><?php echo htmlspecialchars($nama); ?> (ID: <?php echo $id; ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <?php if (!empty($produk_list)): ?>
                <div class="product-count">
                    <i class="fas fa-box"></i> Total Produk: <span style="color: #d81b60;"><?php echo count($produk_list); ?></span> produk
                </div>
                
                <h3>Daftar Produk yang Akan Dihapus:</h3>
                <div class="product-list">
                    <?php foreach ($produk_list as $produk): ?>
                        <div class="product-item">
                            <span class="product-name">
                                <i class="fas fa-book"></i> <?php echo htmlspecialchars($produk['nama_produk']); ?>
                            </span>
                            <span class="product-seller"><?php echo htmlspecialchars($penjual_info[$produk['id_penjual']]); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-products">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                    <p>Tidak ada produk dari penjual spam</p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($produk_list)): ?>
                <div class="button-group">
                    <button class="btn btn-delete" onclick="confirmDelete()">
                        <i class="fas fa-trash-alt"></i> Hapus Semua Produk Spam
                    </button>
                    <button class="btn btn-back" onclick="history.back()">
                        <i class="fas fa-arrow-left"></i> Batal
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        function confirmDelete() {
            const count = <?php echo count($produk_list); ?>;
            if (confirm(`Apakah Anda yakin ingin menghapus ${count} produk spam ini? Tindakan ini tidak dapat dibatalkan!`)) {
                window.location.href = '?action=delete&confirm=1';
            }
        }
    </script>
</body>
</html>
