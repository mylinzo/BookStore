<?php
session_start();
include 'koneksi.php';

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id_user'] ?? $_SESSION['id'] ?? 0;

// Get parameters
if (!isset($_GET['id_produk']) || !isset($_GET['id_penjual'])) {
    header("Location: halaman_pembeli.php");
    exit;
}

$id_produk = (int)$_GET['id_produk'];
$id_penjual = (int)$_GET['id_penjual'];
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Get produk info
$sql = "SELECT * FROM produk WHERE id_produk = ? AND id_penjual = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $id_produk, $id_penjual);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$produk = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$produk) {
    header("Location: halaman_pembeli.php");
    exit;
}

// Get penjual info
$sql_penjual = "SELECT nama FROM users WHERE id_user = ?";
$stmt_penjual = mysqli_prepare($conn, $sql_penjual);
mysqli_stmt_bind_param($stmt_penjual, "i", $id_penjual);
mysqli_stmt_execute($stmt_penjual);
$result_penjual = mysqli_stmt_get_result($stmt_penjual);
$penjual = mysqli_fetch_assoc($result_penjual);
mysqli_stmt_close($stmt_penjual);
$penjual_nama = $penjual['nama'] ?? 'Toko';

// Check if already in cart - update quantity atau buat baru
$sql_check = "SELECT * FROM keranjang_produk WHERE id_user = ? AND id_produk = ? AND id_penjual = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "iii", $user_id, $id_produk, $id_penjual);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$existing = mysqli_fetch_assoc($result_check);
mysqli_stmt_close($stmt_check);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0 && $quantity <= $produk['stok']) {
        if ($existing) {
            // Update quantity
            $sql_update = "UPDATE keranjang_produk SET quantity = quantity + ? WHERE id_user = ? AND id_produk = ? AND id_penjual = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "iiii", $quantity, $user_id, $id_produk, $id_penjual);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        } else {
            // Insert baru
            $sql_insert = "INSERT INTO keranjang_produk (id_user, id_produk, id_penjual, quantity) VALUES (?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "iiii", $user_id, $id_produk, $id_penjual, $quantity);
            mysqli_stmt_execute($stmt_insert);
            mysqli_stmt_close($stmt_insert);
        }
        
        // Redirect ke keranjang dengan success message
        header("Location: konfirmasi.php?success=" . urlencode($produk['nama_buku'] . ' ditambahkan ke keranjang'));
        exit;
    }
}

// Show form untuk select quantity
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah ke Keranjang - Pustaka Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --vintage-cream: #f5f1e8;
            --vintage-brown: #8b4513;
            --vintage-dark: #5d4037;
            --vintage-gold: #d4af37;
            --vintage-red: #8b0000;
            --vintage-green: #556b2f;
            --vintage-paper: #f8f4e6;
            --vintage-border: #c9a87a;
            --vintage-sepia: #704214;
            --vintage-tan: #d2b48c;
        }

        body {
            font-family: 'Lora', serif;
            background: var(--vintage-cream);
            background-image: 
                linear-gradient(rgba(139, 69, 19, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(139, 69, 19, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M20 20 Q 40 30, 60 20 T 100 20" stroke="%238b4513" fill="none" stroke-width="0.5" opacity="0.1"/><path d="M10 50 Q 30 60, 50 50 T 90 50" stroke="%238b4513" fill="none" stroke-width="0.5" opacity="0.1"/></svg>');
            pointer-events: none;
            z-index: -1;
        }

        .modal {
            background: var(--vintage-paper);
            border-radius: 20px;
            padding: 45px;
            max-width: 550px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(92, 64, 55, 0.3);
            border: 2px solid var(--vintage-gold);
            position: relative;
            overflow: hidden;
        }

        .modal::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--vintage-gold), var(--vintage-brown), var(--vintage-gold));
        }

        .modal::after {
            content: '✧';
            position: absolute;
            bottom: 15px;
            right: 25px;
            font-size: 24px;
            color: var(--vintage-gold);
            opacity: 0.2;
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px dashed var(--vintage-gold);
        }

        .product-image {
            width: 100px;
            height: 120px;
            background: var(--vintage-cream);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--vintage-brown);
            font-size: 40px;
            overflow: hidden;
            border: 2px solid var(--vintage-gold);
            box-shadow: 0 4px 10px rgba(92, 64, 55, 0.2);
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-details {
            flex: 1;
        }

        .product-details h2 {
            font-family: 'Cinzel', serif;
            font-size: 22px;
            color: var(--vintage-dark);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .product-details p {
            font-size: 14px;
            color: var(--vintage-brown);
            font-style: italic;
        }

        .product-seller {
            font-size: 13px;
            color: var(--vintage-gold);
            font-weight: 600;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .product-seller i {
            color: var(--vintage-gold);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-family: 'Cinzel', serif;
            font-weight: 600;
            color: var(--vintage-dark);
            font-size: 16px;
        }

        .form-info {
            background: var(--vintage-cream);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--vintage-border);
        }

        .form-info span {
            font-size: 15px;
            color: var(--vintage-dark);
        }

        .form-info strong {
            color: var(--vintage-brown);
            font-size: 20px;
            font-family: 'Cinzel', serif;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .qty-btn {
            width: 40px;
            height: 40px;
            border: 2px solid var(--vintage-border);
            background: var(--vintage-cream);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s;
            font-size: 18px;
            color: var(--vintage-dark);
        }

        .qty-btn:hover {
            background: linear-gradient(135deg, var(--vintage-gold), var(--vintage-brown));
            color: var(--vintage-paper);
            border-color: var(--vintage-gold);
            transform: translateY(-2px);
        }

        .qty-input {
            width: 70px;
            padding: 10px;
            border: 2px solid var(--vintage-border);
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
            background: var(--vintage-paper);
            color: var(--vintage-dark);
        }

        .qty-input:focus {
            outline: none;
            border-color: var(--vintage-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Cinzel', serif;
            font-size: 15px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-add {
            background: linear-gradient(135deg, var(--vintage-gold), var(--vintage-brown));
            color: var(--vintage-paper);
            border: 2px solid var(--vintage-gold);
        }

        .btn-add:hover {
            background: linear-gradient(135deg, var(--vintage-brown), var(--vintage-gold));
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
        }

        .btn-cancel {
            background: var(--vintage-cream);
            color: var(--vintage-brown);
            border: 2px solid var(--vintage-border);
        }

        .btn-cancel:hover {
            background: var(--vintage-paper);
            border-color: var(--vintage-gold);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(139, 69, 19, 0.2);
        }

        /* Decorative elements */
        .vintage-corner {
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 18px;
            color: var(--vintage-gold);
            opacity: 0.3;
        }

        .vintage-corner-right {
            position: absolute;
            bottom: 10px;
            left: 10px;
            font-size: 18px;
            color: var(--vintage-gold);
            opacity: 0.3;
            transform: rotate(180deg);
        }

        @media (max-width: 480px) {
            .modal {
                padding: 30px 20px;
            }
            
            .modal-header {
                flex-direction: column;
                text-align: center;
            }
            
            .product-image {
                margin: 0 auto;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Vintage decorative corners -->
    <div class="vintage-corner">✧</div>
    <div class="vintage-corner-right">✧</div>

    <div class="modal">
        <!-- Header dengan informasi produk -->
        <div class="modal-header">
            <div class="product-image">
                <?php if (!empty($produk['foto_produk']) && file_exists('uploads/produk/' . $produk['foto_produk'])): ?>
                    <img src="uploads/produk/<?php echo htmlspecialchars($produk['foto_produk']); ?>" 
                         alt="<?php echo htmlspecialchars($produk['nama_buku']); ?>">
                <?php else: ?>
                    <i class="fas fa-book-open"></i>
                <?php endif; ?>
            </div>
            <div class="product-details">
                <h2><?php echo htmlspecialchars($produk['nama_buku']); ?></h2>
                <p><?php echo htmlspecialchars($produk['nama_kategori'] ?? 'Koleksi Pustaka'); ?></p>
                <div class="product-seller">
                    <i class="fas fa-store-alt"></i> <?php echo htmlspecialchars($penjual_nama); ?>
                </div>
            </div>
        </div>

        <form method="POST">
            <!-- Informasi harga dan stok -->
            <div class="form-group">
                <div class="form-info">
                    <span>Harga per Unit</span>
                    <strong>Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></strong>
                </div>
                <div class="form-info">
                    <span>Stok Tersedia</span>
                    <strong><?php echo $produk['stok']; ?> unit</strong>
                </div>
            </div>

            <!-- Quantity selector -->
            <div class="form-group">
                <label class="form-label">Jumlah Pesanan</label>
                <div class="quantity-control">
                    <button type="button" class="qty-btn" onclick="decreaseQty()">−</button>
                    <input type="number" name="quantity" id="quantity" class="qty-input" value="1" min="1" max="<?php echo $produk['stok']; ?>">
                    <button type="button" class="qty-btn" onclick="increaseQty()">+</button>
                </div>
            </div>

            <!-- Subtotal -->
            <div class="form-group">
                <div class="form-info">
                    <span>Subtotal</span>
                    <strong id="subtotal">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></strong>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="btn-group">
                <button type="button" class="btn btn-cancel" onclick="window.history.back()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-add">
                    <i class="bi bi-wallet-fill"></i> Bayar
                </button>
            </div>
        </form>
    </div>

    <script>
        const maxStok = <?php echo $produk['stok']; ?>;
        const harga = <?php echo (float)$produk['harga']; ?>;

        function formatRupiah(angka) {
            return 'Rp ' + angka.toLocaleString('id-ID');
        }

        function updateSubtotal() {
            const qty = parseInt(document.getElementById('quantity').value);
            if (isNaN(qty) || qty < 1) {
                document.getElementById('quantity').value = 1;
                updateSubtotal();
                return;
            }
            if (qty > maxStok) {
                document.getElementById('quantity').value = maxStok;
                updateSubtotal();
                return;
            }
            const subtotal = qty * harga;
            document.getElementById('subtotal').textContent = formatRupiah(subtotal);
        }

        function increaseQty() {
            const input = document.getElementById('quantity');
            const current = parseInt(input.value);
            if (current < maxStok) {
                input.value = current + 1;
                updateSubtotal();
            }
        }

        function decreaseQty() {
            const input = document.getElementById('quantity');
            const current = parseInt(input.value);
            if (current > 1) {
                input.value = current - 1;
                updateSubtotal();
            }
        }

        // Event listeners
        document.getElementById('quantity').addEventListener('input', updateSubtotal);
        document.getElementById('quantity').addEventListener('change', updateSubtotal);

        // Validasi input langsung
        document.getElementById('quantity').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                updateSubtotal();
            }
        });
    </script>
</body>
</html>