-- ========== TABEL PRODUK (untuk penjual) ==========
CREATE TABLE IF NOT EXISTS produk (
    id_produk INT PRIMARY KEY AUTO_INCREMENT,
    nama_buku VARCHAR(200) NOT NULL,
    id_kategori INT,
    id_penjual INT NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    harga DECIMAL(15, 2) NOT NULL,
    modal DECIMAL(15, 2),
    margin DECIMAL(15, 2),
    keuntungan DECIMAL(15, 2),
    deskripsi TEXT,
    foto_produk VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori) ON DELETE SET NULL,
    FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
    INDEX idx_penjual (id_penjual),
    INDEX idx_kategori (id_kategori),
    INDEX idx_stok (stok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== TABEL KERANJANG PRODUK (untuk produk dari penjual) ==========
CREATE TABLE IF NOT EXISTS keranjang_produk (
    id_keranjang INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    id_produk INT NOT NULL,
    id_penjual INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
    FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
    UNIQUE KEY unique_cart (id_user, id_produk, id_penjual)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== TABEL PESANAN (untuk pesan produk dari penjual) ==========
CREATE TABLE IF NOT EXISTS pesanan (
    id_pesanan INT PRIMARY KEY AUTO_INCREMENT,
    id_pembeli INT NOT NULL,
    id_penjual INT NOT NULL,
    id_produk INT NOT NULL,
    quantity INT NOT NULL,
    total_harga DECIMAL(15, 2) NOT NULL,
    status ENUM('menunggu_pembayaran', 'dibayar', 'approve', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak', 'refund') DEFAULT 'menunggu_pembayaran',
    metode_pembayaran VARCHAR(50),
    bukti_pembayaran VARCHAR(255),
    nomor_resi VARCHAR(50),
    alamat_pengiriman TEXT,
    catatan_pembeli TEXT,
    catatan_penjual TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pembeli) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_penjual) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
    INDEX idx_pembeli (id_pembeli),
    INDEX idx_penjual (id_penjual),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== ALTER TABEL USERS untuk menambah role penjual ==========
ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'pembeli', 'penjual') DEFAULT 'pembeli';

-- ========== QUERY untuk membuat halaman pesanan pembeli ==========
-- Query untuk tampilkan pesanan berdasarkan pembeli dengan info penjual dan produk:
-- SELECT p.*, pr.nama_buku, pr.foto_produk, u.nama as nama_penjual, u.email as email_penjual
-- FROM pesanan p
-- JOIN produk pr ON p.id_produk = pr.id_produk
-- JOIN users u ON p.id_penjual = u.id_user
-- WHERE p.id_pembeli = {user_id}
-- ORDER BY p.created_at DESC;
