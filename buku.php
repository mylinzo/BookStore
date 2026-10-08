<?php
include 'koneksi.php';
session_start();

// Check user login
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$urut = isset($_GET['urut']) ? $_GET['urut'] : 'terbaru';

// Build query - search from produk table by nama_buku
$query = "SELECT p.*, u.nama as nama_penjual FROM produk p LEFT JOIN users u ON p.id_penjual = u.id_user WHERE 1=1";
$params = array();
$types = "";

if (!empty($search)) {
    $query .= " AND p.nama_buku LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= "s";
}

if (!empty($kategori)) {
    $query .= " AND p.id_kategori = ?";
    $params[] = intval($kategori);
    $types .= "i";
}

// Sort
switch ($urut) {
    case 'harga_rendah':
        $query .= " ORDER BY p.harga ASC";
        break;
    case 'harga_tinggi':
        $query .= " ORDER BY p.harga DESC";
        break;
    case 'rating':
        $query .= " ORDER BY p.rating DESC";
        break;
    case 'terbaru':
    default:
        $query .= " ORDER BY p.created_at DESC";
        break;
}

// Execute query
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    if ($types) {
        $bind_params = array($types);
        for ($i = 0; $i < count($params); $i++) {
            $bind_params[] = &$params[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt), $bind_params));
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

// Get categories
$kategori_result = mysqli_query($conn, "SELECT * FROM kategori");

// Update online status if logged in
if ($user_id) {
    $update_sql = "UPDATE users SET status = 'online' WHERE id_user = ?";
    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "i", $user_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 Katalog Buku - Toko Buku Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        /* Navbar */
        .navbar {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            sticky: top;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: #2c3e50;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo:hover {
            color: #3498db;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            color: #555;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #3498db;
        }

        .btn-nav {
            padding: 8px 16px;
            background: #3498db;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s;
        }

        .btn-nav:hover {
            background: #2980b9;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 2rem;
            margin: 2rem 0;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        .filter-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            padding: 10px 15px;
            border: 1.5px solid #ecf0f1;
            border-radius: 8px;
            font-family: 'Poppins', Arial, sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-cari {
            padding: 10px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-cari:hover {
            background: #2980b9;
        }

        .btn-reset {
            padding: 10px 20px;
            background: #ecf0f1;
            color: #333;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-reset:hover {
            background: #bdc3c7;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 24px;
            margin-bottom: 3rem;
        }

        .book-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }

        .book-image {
            width: 100%;
            height: 280px;
            background: #ecf0f1;
            overflow: hidden;
            position: relative;
        }

        .book-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .book-card:hover .book-image img {
            transform: scale(1.05);
        }

        .stok-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #27ae60;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stok-badge.habis {
            background: #e74c3c;
        }

        .book-content {
            padding: 18px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .book-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 8px;
            line-height: 1.4;
            min-height: 2.8em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .book-author {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 10px;
            font-weight: 400;
        }

        .book-price {
            color: #27ae60;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 15px;
            margin-top: auto;
        }

        .book-actions {
            display: flex;
            gap: 8px;
        }

        .btn-detail {
            flex: 1;
            padding: 10px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .btn-detail:hover {
            background: #2980b9;
        }

        .btn-cart {
            padding: 10px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-cart:hover {
            background: #229954;
        }

        .btn-cart:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 2rem;
            margin-top: 4rem;
        }

        @media (max-width: 768px) {
            .filter-content {
                grid-template-columns: 1fr;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 16px;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .navbar-content {
                flex-direction: column;
                gap: 10px;
            }

            .nav-links {
                flex-direction: column;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="halaman_pembeli.php" class="logo">
                <i class="fas fa-book"></i> Toko Buku
            </a>
            <div class="nav-links">
                <a href="halaman_pembeli.php">Beranda</a>
                <a href="buku.php">Katalog Buku</a>
                <?php if ($user_id): ?>
                    <a href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang</a>
                    <a href="laporan.php"><i class="fas fa-receipt"></i> Pesanan</a>
                    <a href="logout.php" class="btn-nav">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn-nav">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-book-open"></i> Katalog Buku</h1>
            <p>Jelajahi koleksi lengkap buku kami</p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="container">
            <form method="GET" action="buku.php" id="filterForm" style="display: contents;">
                <div class="filter-content">
                    <div class="form-group">
                        <label for="search">Cari Buku</label>
                        <input type="text" id="search" name="search" placeholder="Nama buku..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="form-group">
                        <label for="kategori">Kategori</label>
                        <select id="kategori" name="kategori">
                            <option value="">Semua Kategori</option>
                            <?php while ($kat = mysqli_fetch_assoc($kategori_result)): ?>
                                <option value="<?php echo $kat['id_kategori']; ?>" <?php echo ($kategori == $kat['id_kategori']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kat['nama_kategori']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="urut">Urutkan Berdasarkan</label>
                        <select id="urut" name="urut">
                            <option value="terbaru" <?php echo ($urut == 'terbaru') ? 'selected' : ''; ?>>Terbaru</option>
                            <option value="harga_rendah" <?php echo ($urut == 'harga_rendah') ? 'selected' : ''; ?>>Harga Terendah</option>
                            <option value="harga_tinggi" <?php echo ($urut == 'harga_tinggi') ? 'selected' : ''; ?>>Harga Tertinggi</option>
                            <option value="rating" <?php echo ($urut == 'rating') ? 'selected' : ''; ?>>Rating Tertinggi</option>
                        </select>
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="btn-cari"><i class="fas fa-search"></i> Cari</button>
                        <a href="buku.php" class="btn-reset"><i class="fas fa-redo"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Books Grid -->
    <div class="container">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <div class="books-grid" id="booksGrid">
                <?php while ($buku = mysqli_fetch_assoc($result)): 
                    $is_stok = $buku['stok'] > 0;
                ?>
                    <div class="book-card">
                        <div class="book-image">
                            <img src="<?php echo htmlspecialchars('uploads/produk/' . $buku['foto_produk']); ?>" alt="<?php echo htmlspecialchars($buku['nama_buku']); ?>" onerror="this.src='https://via.placeholder.com/220x280?text=No+Image'">
                            <span class="stok-badge <?php echo !$is_stok ? 'habis' : ''; ?>">
                                <?php echo $is_stok ? 'Stok: ' . $buku['stok'] : 'Habis'; ?>
                            </span>
                        </div>
                        <div class="book-content">
                            <h3 class="book-title"><?php echo htmlspecialchars($buku['nama_buku']); ?></h3>
                            <p class="book-author">
                                <i class="fas fa-store"></i> <?php echo htmlspecialchars($buku['nama_penjual'] ?? 'Toko'); ?>
                            </p>
                            <div class="book-price">Rp <?php echo number_format($buku['harga'], 0, ',', '.'); ?></div>
                            <div class="book-actions">
                                <a href="pesanan_produk.php?id=<?php echo $buku['id_produk']; ?>" class="btn-detail">
                                    <i class="fas fa-info-circle"></i> Detail
                                </a>
                                <?php if ($is_stok && $user_id): ?>
                                    <button class="btn-cart" onclick="addToCart(<?php echo $buku['id_produk']; ?>, '<?php echo htmlspecialchars($buku['nama_buku']); ?>')">
                                        <i class="fas fa-shopping-cart"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <h2>Buku Tidak Ditemukan</h2>
                <p>Coba gunakan filter atau kata kunci lain</p>
                <a href="buku.php" class="btn-cari" style="margin-top: 20px;">Reset Filter</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 Toko Buku Online. Semua hak cipta dilindungi.</p>
    </footer>

    <script>
        // end of addToCart
        }

        // dynamic filtering
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const params = new URLSearchParams(new FormData(this));
                fetch('buku.php?' + params.toString())
                    .then(r => r.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newGrid = doc.getElementById('booksGrid');
                        const currentGrid = document.getElementById('booksGrid');
                        if (newGrid && currentGrid) {
                            currentGrid.innerHTML = newGrid.innerHTML;
                        }
                    });
            });
        }
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Berhasil ditambahkan ke keranjang!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Gagal menambahkan ke keranjang');
            });
        }
    </script>
    <script>
        // dynamic filtering without reload
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const params = new URLSearchParams(new FormData(this));
                fetch('buku.php?' + params.toString())
                    .then(r => r.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newGrid = doc.getElementById('booksGrid');
                        const currentGrid = document.getElementById('booksGrid');
                        if (newGrid && currentGrid) {
                            currentGrid.innerHTML = newGrid.innerHTML;
                        }
                    });
            });
        }
    </script>
</body>
</html>
