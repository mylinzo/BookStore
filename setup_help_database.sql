-- Tabel untuk menyimpan pesan kontak/help
CREATE TABLE IF NOT EXISTS contact_messages (
    id_message INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    category VARCHAR(100),
    status ENUM('pending', 'replied', 'resolved', 'closed') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    admin_reply LONGTEXT,
    admin_replied_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    replyed_at TIMESTAMP NULL,
    INDEX (user_id),
    INDEX (status),
    INDEX (created_at)
);

-- Tabel untuk FAQ category
CREATE TABLE IF NOT EXISTS help_categories (
    id_category INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(255) NOT NULL,
    category_slug VARCHAR(255) UNIQUE NOT NULL,
    category_icon VARCHAR(50),
    category_description TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (category_slug)
);

-- Tabel untuk FAQ articles
CREATE TABLE IF NOT EXISTS help_articles (
    id_article INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    question VARCHAR(500) NOT NULL,
    answer LONGTEXT NOT NULL,
    article_slug VARCHAR(500) UNIQUE,
    views_count INT DEFAULT 0,
    helpful_count INT DEFAULT 0,
    unhelpful_count INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES help_categories(id_category) ON DELETE CASCADE,
    INDEX (category_id),
    INDEX (is_active)
);

-- Tabel untuk feedback FAQ
CREATE TABLE IF NOT EXISTS help_feedback (
    id_feedback INT PRIMARY KEY AUTO_INCREMENT,
    article_id INT NOT NULL,
    user_id INT,
    is_helpful BOOLEAN,
    feedback_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES help_articles(id_article) ON DELETE CASCADE,
    INDEX (article_id)
);

-- Insert default categories
INSERT INTO help_categories (category_name, category_slug, category_icon, category_description, sort_order) VALUES
('Pembayaran', 'pembayaran', 'fa-money-bill-wave', 'FAQ tentang metode pembayaran dan verifikasi', 1),
('Pengiriman', 'pengiriman', 'fa-truck', 'FAQ tentang pengiriman dan ekspedisi', 2),
('Produk & Stok', 'produk-stok', 'fa-box', 'FAQ tentang kelola produk dan stok', 3),
('Akun & Keamanan', 'akun-keamanan', 'fa-key', 'FAQ tentang akun dan keamanan', 4),
('Transaksi & Laporan', 'transaksi-laporan', 'fa-file-excel', 'FAQ tentang transaksi dan laporan', 5),
('Teknis & Support', 'teknis-support', 'fa-headset', 'FAQ tentang masalah teknis dan support', 6);

-- Insert default FAQ articles untuk Pembayaran
INSERT INTO help_articles (category_id, question, answer, article_slug, sort_order) VALUES
(1, 'Apa saja metode pembayaran yang diterima?', 'Kami menerima transfer bank (BNI, BRI, Mandiri, BCA), e-wallet (OVO, Dana, GoPay, ShopeePay), dan QRIS. Pilih metode yang paling nyaman untuk Anda.', 'metode-pembayaran', 1),
(1, 'Berapa lama waktu verifikasi pembayaran?', 'Verifikasi pembayaran dilakukan maksimal 1x24 jam pada hari kerja. Pastikan mengupload bukti transfer yang jelas dan sesuai dengan nominal yang tertera.', 'waktu-verifikasi-pembayaran', 2),
(1, 'Apa yang harus dilakukan jika pembayaran ditolak?', 'Pastikan bukti transfer jelas, jumlah sesuai, dan disertai keterangan. Cek kembali nomor rekening tujuan. Jika masih ditolak, hubungi admin support kami.', 'pembayaran-ditolak', 3),
(1, 'Bagaimana cara mendapatkan invoice?', 'Invoice akan otomatis dibuat setelah checkout dan bisa di-download dari halaman transaksi atau email Anda.', 'download-invoice', 4);

-- Insert default FAQ articles untuk Pengiriman
INSERT INTO help_articles (category_id, question, answer, article_slug, sort_order) VALUES
(2, 'Apa saja pilihan ekspedisi yang tersedia?', 'Kami bekerja sama dengan JNE, TIKI, POS Indonesia, SiCepat, dan J&T. Pilihan ekspedisi tersedia di halaman checkout.', 'pilihan-ekspedisi', 1),
(2, 'Berapa lama waktu pengiriman?', 'Pengiriman reguler 3-7 hari kerja, ekspress 1-3 hari kerja. Waktu pengiriman bisa berbeda tergantung lokasi Anda.', 'waktu-pengiriman', 2),
(2, 'Bagaimana cara melacak paket?', 'Masukkan nomor resi di website ekspedisi atau klik link lacak di halaman transaksi kami. Anda juga bisa menghubungi kurir.', 'lacak-paket', 3),
(2, 'Apakah bisa COD (Cash on Delivery)?', 'Saat ini belum tersedia layanan COD. Semua transaksi harus dibayar terlebih dahulu sebelum barang dikirim.', 'cod-tersedia', 4);

-- Insert default FAQ articles untuk Produk & Stok
INSERT INTO help_articles (category_id, question, answer, article_slug, sort_order) VALUES
(3, 'Bagaimana cara menambah stok produk?', 'Penjual bisa menambah stok di menu Penjual → Produk → Edit Produk → Update jumlah stok, lalu simpan.', 'tambah-stok', 1),
(3, 'Apa yang terjadi jika stok habis?', 'Produk akan otomatis tidak tampil di katalog pembeli. Penjual bisa hapus atau arsipkan produk jika stok 0.', 'stok-habis', 2),
(3, 'Bisakah mengubah harga produk?', 'Ya, penjual bisa edit harga produk kapan saja melalui menu Produk → Edit Produk → Update harga.', 'ubah-harga', 3),
(3, 'Bagaimana cara menghapus produk?', 'Produk hanya bisa dihapus jika stok 0. Klik tombol hapus di card produk atau menu edit produk.', 'hapus-produk', 4);

-- Insert default FAQ articles untuk Akun & Keamanan
INSERT INTO help_articles (category_id, question, answer, article_slug, sort_order) VALUES
(4, 'Bagaimana cara mengganti password?', 'Di menu My Account → Profil, masukkan password lama dan baru, lalu simpan perubahan.', 'ubah-password', 1),
(4, 'Apa yang harus dilakukan jika lupa password?', 'Klik "Lupa Password" di halaman login, ikuti instruksi reset password via email yang dikirim ke Anda.', 'lupa-password', 2),
(4, 'Bagaimana cara mengubah email?', 'Di menu My Account → Profil, ubah email dan konfirmasi via email verifikasi yang akan dikirim.', 'ubah-email', 3),
(4, 'Apakah bisa memiliki lebih dari 1 akun?', 'Setiap email hanya bisa mendaftar 1 akun. Untuk role berbeda harus menggunakan email yang berbeda.', 'multiple-akun', 4);

-- Insert default FAQ articles untuk Transaksi & Laporan  
INSERT INTO help_articles (category_id, question, answer, article_slug, sort_order) VALUES
(5, 'Bagaimana cara approve pembayaran?', 'Penjual bisa approve di menu Admin → Verifikasi Pembayaran → klik Approve → input nomor resi pengiriman.', 'approve-pembayaran', 1),
(5, 'Bagaimana menolak transaksi?', 'Di menu Admin → Verifikasi Pembayaran → klik Tolak → berikan alasan penolakan yang jelas.', 'tolak-transaksi', 2),
(5, 'Bagaimana cara download laporan?', 'Di menu Laporan → pilih periode tanggal → klik Download Excel untuk unduh laporan transaksi.', 'download-laporan', 3),
(5, 'Apakah ada batas waktu refund?', 'Refund bisa diajukan maksimal 7 hari setelah barang diterima. Hubungi support untuk proses refund.', 'refund-batas-waktu', 4);

-- Insert default FAQ articles untuk Teknis & Support
INSERT INTO help_articles (category_id, question, answer, article_slug, sort_order) VALUES
(6, 'Website error atau tidak bisa diakses?', 'Coba clear cache browser dengan Ctrl+Shift+Delete. Gunakan mode incognito jika masih error. Hubungi support jika berlanjut.', 'website-error', 1),
(6, 'Bagaimana cara menghubungi support?', 'Email: support@sarianggrek.com atau WhatsApp: 0812-3456-7890 (08:00-17:00 Senin-Jumat)', 'hubungi-support', 2),
(6, 'Apakah ada aplikasi mobile?', 'Saat ini hanya tersedia versi web yang responsif untuk mobile. Aplikasi mobile sedang dalam pengembangan.', 'aplikasi-mobile', 3),
(6, 'Bagaimana cara memberikan feedback?', 'Kirim email ke feedback@sarianggrek.com atau gunakan form kontak di halaman Help & Support.', 'feedback', 4);
