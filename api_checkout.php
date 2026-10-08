<?php
/**
 * API Checkout - Konversi Keranjang ke Pesanan
 * Diakses saat pembeli melakukan checkout
 */

header('Content-Type: application/json; charset=utf-8');

session_start();
include 'koneksi.php';
include 'transaction_logger.php';
require_once 'notificationsHelper.php';

// Check authentication
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id_pembeli = $_SESSION['id_user'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Backward compatibility: if called via POST without explicit action, assume checkout
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = 'checkout';
}

try {
    // ===== CREATE PESANAN FROM KERANJANG =====
    if ($action === 'checkout') {
        // Get cart items
        $cart_query = "SELECT * FROM keranjang_produk WHERE id_user = ?";
        $stmt = mysqli_prepare($conn, $cart_query);
        mysqli_stmt_bind_param($stmt, "i", $id_pembeli);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        if (empty($cart_items)) {
            throw new Exception('Keranjang kosong');
        }
        
        // Create pesanan for each item
        $pesanan_ids = [];
        mysqli_query($conn, "START TRANSACTION");
        
        foreach ($cart_items as $item) {
            $id_produk = $item['id_produk'];
            $id_penjual = $item['id_penjual'];
            $quantity = $item['quantity'];
            
            // Get product price
            $prod_query = "SELECT harga FROM produk WHERE id_produk = ?";
            $prod_stmt = mysqli_prepare($conn, $prod_query);
            mysqli_stmt_bind_param($prod_stmt, "i", $id_produk);
            mysqli_stmt_execute($prod_stmt);
            $prod_result = mysqli_stmt_get_result($prod_stmt);
            $prod = mysqli_fetch_assoc($prod_result);
            
            if (!$prod) {
                throw new Exception('Produk tidak ditemukan');
            }
            
            $total_harga = $prod['harga'] * $quantity;
            
            // Insert pesanan
            $pesanan_query = "INSERT INTO pesanan 
                            (id_pembeli, id_penjual, id_produk, quantity, total_harga, kurir, status)
                            VALUES (?, ?, ?, ?, ?, ?, 'menunggu_pembayaran')";
            
            $pesanan_stmt = mysqli_prepare($conn, $pesanan_query);
            mysqli_stmt_bind_param($pesanan_stmt, "iiisss", $id_pembeli, $id_penjual, $id_produk, $quantity, $total_harga, $kurir ?? 'JNE');
            
            if (!mysqli_stmt_execute($pesanan_stmt)) {
                throw new Exception('Gagal membuat pesanan: ' . mysqli_error($conn));
            }
            
            $id_pesanan = mysqli_insert_id($conn);
            $pesanan_ids[] = $id_pesanan;
            
            // Reduce stock (UPDATE produk)
            $update_stok_query = "UPDATE produk SET stok = stok - ? WHERE id_produk = ?";
            $update_stok_stmt = mysqli_prepare($conn, $update_stok_query);
            mysqli_stmt_bind_param($update_stok_stmt, "ii", $quantity, $id_produk);
            
            if (!mysqli_stmt_execute($update_stok_stmt)) {
                throw new Exception('Gagal update stok produk: ' . mysqli_error($conn));
            }
            
            // Create transaction record
            if (isset($txn_logger)) {
                $txn_result = $txn_logger->createTransactionFromOrder(
                    $id_pesanan,
                    $id_pembeli,
                    $id_penjual,
                    $total_harga
                );
                
                if ($txn_result['success']) {
                    $id_transaksi = $txn_result['id_transaksi'];
                    
                    // Add transaction detail
                    $txn_logger->addTransactionDetail(
                        $id_transaksi,
                        $id_produk,
                        $quantity,
                        $prod['harga']
                    );
                    
                    // Log initial status
                    $txn_logger->logStatusChange(
                        $id_transaksi,
                        null,
                        'menunggu_pembayaran',
                        'Pesanan baru dibuat dari checkout',
                        $id_pembeli
                    );
                    // Create notification for penjual (correct parameter order)
                    $buyer_name = getUserNameSafe($conn, $id_pembeli);
                    $message = 'Pesanan #' . intval($id_transaksi) . ' dari ' . $buyer_name;
                    @createNotification(
                        $conn,
                        $id_penjual,           // user_id
                        'pesanan',             // type
                        $id_transaksi,         // reference id (transaction)
                        'Pesanan baru masuk',  // title
                        $message,
                        'pesanan_penjual.php?id=' . $id_transaksi
                    );
                }
            }
        }
        
        // Clear cart
        $clear_query = "DELETE FROM keranjang_produk WHERE id_user = ?";
        $clear_stmt = mysqli_prepare($conn, $clear_query);
        mysqli_stmt_bind_param($clear_stmt, "i", $id_pembeli);
        
        if (!mysqli_stmt_execute($clear_stmt)) {
            throw new Exception('Gagal hapus keranjang');
        }
        
        mysqli_query($conn, "COMMIT");
        
        echo json_encode([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat',
            'pesanan_ids' => $pesanan_ids,
            'redirect' => 'pesanan_pembeli.php'
        ]);
    }
    
    // ===== GET CART SUMMARY =====
    elseif ($action === 'get_cart') {
        $cart_query = "SELECT 
                        kp.id_produk, kp.id_penjual, kp.quantity,
                        p.nama_buku, p.harga, p.stok,
                        u.nama as nama_penjual
                      FROM keranjang_produk kp
                      JOIN produk p ON kp.id_produk = p.id_produk
                      JOIN users u ON kp.id_penjual = u.id_user
                      WHERE kp.id_user = ?";
        
        $stmt = mysqli_prepare($conn, $cart_query);
        mysqli_stmt_bind_param($stmt, "i", $id_pembeli);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        $total = 0;
        $item_count = 0;
        
        foreach ($cart_items as &$item) {
            $item['subtotal'] = $item['harga'] * $item['quantity'];
            $total += $item['subtotal'];
            $item_count += $item['quantity'];
        }
        
        echo json_encode([
            'success' => true,
            'items' => $cart_items,
            'total' => $total,
            'item_count' => $item_count
        ]);
    }
    
    // ===== REMOVE FROM CART =====
    elseif ($action === 'remove_item') {
        $id_produk = (int)($_POST['id_produk'] ?? 0);
        $id_penjual = (int)($_POST['id_penjual'] ?? 0);
        
        if (!$id_produk || !$id_penjual) {
            throw new Exception('ID produk atau penjual tidak valid');
        }
        
        $delete_query = "DELETE FROM keranjang_produk 
                        WHERE id_user = ? AND id_produk = ? AND id_penjual = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "iii", $id_pembeli, $id_produk, $id_penjual);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Gagal hapus item');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Item berhasil dihapus'
        ]);
    }
    
    // ===== UPDATE QUANTITY =====
    elseif ($action === 'update_quantity') {
        $id_produk = (int)($_POST['id_produk'] ?? 0);
        $id_penjual = (int)($_POST['id_penjual'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        
        if (!$id_produk || !$id_penjual || $quantity < 1) {
            throw new Exception('Parameter tidak valid');
        }
        
        $update_query = "UPDATE keranjang_produk 
                        SET quantity = ?, updated_at = NOW()
                        WHERE id_user = ? AND id_produk = ? AND id_penjual = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "iiii", $quantity, $id_pembeli, $id_produk, $id_penjual);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Gagal update quantity');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Quantity berhasil diupdate'
        ]);
    }
    
    else {
        throw new Exception('Action tidak dikenali');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
