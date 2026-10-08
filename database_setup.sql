-- =====================================================
-- TOKO BUKU DATABASE SETUP
-- =====================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS `toko-buku` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `toko-buku`;

-- =====================================================
-- TABEL USERS
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id_user` INT PRIMARY KEY AUTO_INCREMENT,
    `nama` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nik` VARCHAR(16) UNIQUE,
    `role` ENUM('admin', 'super_admin', 'pembeli', 'penjual') DEFAULT 'pembeli',
    `status` ENUM('online', 'offline', 'banned') DEFAULT 'offline',
    `remember_token` VARCHAR(255),
    `last_activity` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL ADMIN (Required for login.php)
-- =====================================================
CREATE TABLE IF NOT EXISTS `admin` (
    `id_admin` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nama_admin` VARCHAR(100),
    `status` ENUM('aktif', 'tidak aktif', 'online', 'offline') DEFAULT 'offline',
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
INSERT INTO `admin` (`username`, `email`, `password`, `nama_admin`, `status`) 
VALUES ('admin', 'admin@toko-buku.local', '$2y$10$tZJk.JY3u1Z5QJ2.QM8cKe7rON8tDp8r5L5E8r5L5E8r5L5E8r5L5', 'Super Admin', 'aktif');

-- =====================================================
-- TABEL KATEGORI
-- =====================================================
CREATE TABLE IF NOT EXISTS `kategori` (
    `id_kategori` INT PRIMARY KEY AUTO_INCREMENT,
    `nama_kategori` VARCHAR(100) NOT NULL,
    `deskripsi` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL BUKU
-- =====================================================
CREATE TABLE IF NOT EXISTS `buku` (
    `id_buku` INT PRIMARY KEY AUTO_INCREMENT,
    `judul` VARCHAR(200) NOT NULL,
    `penulis` VARCHAR(100) NOT NULL,
    `penerbit` VARCHAR(100),
    `id_kategori` INT,
    `harga` DECIMAL(10, 2) NOT NULL,
    `stok` INT NOT NULL DEFAULT 0,
    `gambar` VARCHAR(255),
    `deskripsi` TEXT,
    `sinopsis` TEXT,
    `isbn` VARCHAR(20),
    `tahun_terbit` INT,
    `rating` DECIMAL(3, 2) DEFAULT 0,
    `jumlah_rating` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_kategori`) REFERENCES `kategori`(`id_kategori`) ON DELETE SET NULL,
    INDEX idx_kategori (`id_kategori`),
    FULLTEXT INDEX ft_judul_penulis (`judul`, `penulis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL KERANJANG
-- =====================================================
CREATE TABLE IF NOT EXISTS `keranjang` (
    `id_keranjang` INT PRIMARY KEY AUTO_INCREMENT,
    `id_user` INT NOT NULL,
    `id_buku` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `status` ENUM('aktif', 'checkout', 'dihapus') DEFAULT 'aktif',
    `tanggal_ditambahkan` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `tanggal_diupdate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE,
    FOREIGN KEY (`id_buku`) REFERENCES `buku`(`id_buku`) ON DELETE CASCADE,
    UNIQUE KEY unique_active_cart (`id_user`, `id_buku`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL TRANSAKSI
-- =====================================================
CREATE TABLE IF NOT EXISTS `transaksi` (
    `id_transaksi` INT PRIMARY KEY AUTO_INCREMENT,
    `id_pembeli` INT NOT NULL,
    `total_harga` DECIMAL(15, 2) NOT NULL,
    `status` ENUM('menunggu_pembayaran', 'dibayar', 'approve', 'diproses', 'dikirim', 'selesai', 'dibatalkan', 'ditolak', 'refund') DEFAULT 'menunggu_pembayaran',
    `metode_pembayaran` VARCHAR(50),
    `nomor_resi` VARCHAR(50),
    `alamat_pengiriman` TEXT,
    `catatan_admin` TEXT,
    `tanggal` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_pembeli`) REFERENCES `users`(`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL DETAIL TRANSAKSI
-- =====================================================
CREATE TABLE IF NOT EXISTS `detail_transaksi` (
    `id_detail` INT PRIMARY KEY AUTO_INCREMENT,
    `id_transaksi` INT NOT NULL,
    `id_buku` INT NOT NULL,
    `quantity` INT NOT NULL,
    `harga_satuan` DECIMAL(10, 2) NOT NULL,
    `subtotal` DECIMAL(15, 2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi`(`id_transaksi`) ON DELETE CASCADE,
    FOREIGN KEY (`id_buku`) REFERENCES `buku`(`id_buku`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL REVIEW
-- =====================================================
CREATE TABLE IF NOT EXISTS `review` (
    `id_review` INT PRIMARY KEY AUTO_INCREMENT,
    `id_buku` INT NOT NULL,
    `id_user` INT NOT NULL,
    `rating` INT CHECK (`rating` >= 1 AND `rating` <= 5),
    `komentar` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_buku`) REFERENCES `buku`(`id_buku`) ON DELETE CASCADE,
    FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL WISHLIST
-- =====================================================
CREATE TABLE IF NOT EXISTS `wishlist` (
    `id_wishlist` INT PRIMARY KEY AUTO_INCREMENT,
    `id_user` INT NOT NULL,
    `id_buku` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (`id_user`, `id_buku`),
    FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE,
    FOREIGN KEY (`id_buku`) REFERENCES `buku`(`id_buku`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL CHAT
-- =====================================================
CREATE TABLE IF NOT EXISTS `chat` (
    `id_chat` INT PRIMARY KEY AUTO_INCREMENT,
    `id_pengirim` INT NOT NULL,
    `id_penerima` INT NOT NULL,
    `pesan` TEXT NOT NULL,
    `tipe` ENUM('text', 'file', 'gambar') DEFAULT 'text',
    `file_path` VARCHAR(255),
    `dibaca` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_pengirim`) REFERENCES `users`(`id_user`) ON DELETE CASCADE,
    FOREIGN KEY (`id_penerima`) REFERENCES `users`(`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
