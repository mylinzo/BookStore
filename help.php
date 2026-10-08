<?php
session_start();
include 'koneksi.php';
// Check user login and role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}
$user_name = $_SESSION['nama'];
$user_role = $_SESSION['role'];
// FAQ Data
$faqs = [
    [
        'category' => 'Pembayaran',
        'questions' => [
            [
                'question' => 'Apa saja metode pembayaran yang diterima?',
                'answer' => 'Kami menerima transfer bank (BNI, BRI, Mandiri, BCA), e-wallet (OVO, Dana, GoPay, ShopeePay), dan QRIS.',
                'icon' => 'fa-money-bill-wave'
            ],
            [
                'question' => 'Berapa lama waktu verifikasi pembayaran?',
                'answer' => 'Verifikasi pembayaran dilakukan maksimal 1x24 jam pada hari kerja. Pastikan mengupload bukti transfer yang jelas.',
                'icon' => 'fa-clock'
            ],
            [
                'question' => 'Apa yang harus dilakukan jika pembayaran ditolak?',
                'answer' => 'Pastikan bukti transfer jelas, jumlah sesuai, dan disertai keterangan. Hubungi admin jika masih ditolak.',
                'icon' => 'fa-times-circle'
            ],
            [
                'question' => 'Bagaimana cara mendapatkan invoice?',
                'answer' => 'Invoice akan otomatis dibuat setelah checkout dan bisa di-download dari halaman transaksi.',
                'icon' => 'fa-file-invoice'
            ]
        ]
    ],
    [
        'category' => 'Pengiriman',
        'questions' => [
            [
                'question' => 'Apa saja pilihan ekspedisi yang tersedia?',
                'answer' => 'Kami bekerja sama dengan JNE, TIKI, POS Indonesia, SiCepat, dan J&T. Pilihan tersedia di checkout.',
                'icon' => 'fa-truck'
            ],
            [
                'question' => 'Berapa lama waktu pengiriman?',
                'answer' => 'Pengiriman reguler 3-7 hari kerja, ekspress 1-3 hari kerja. Waktu bisa berbeda tergantung lokasi.',
                'icon' => 'fa-shipping-fast'
            ],
            [
                'question' => 'Bagaimana cara melacak paket?',
                'answer' => 'Masukkan nomor resi di website ekspedisi atau klik link lacak di halaman transaksi.',
                'icon' => 'fa-search-location'
            ],
            [
                'question' => 'Apakah bisa COD (Cash on Delivery)?',
                'answer' => 'Saat ini belum tersedia layanan COD. Semua transaksi harus dibayar terlebih dahulu.',
                'icon' => 'fa-money-bill'
            ]
        ]
    ],
    [
        'category' => 'Produk & Stok',
        'questions' => [
            [
                'question' => 'Bagaimana cara menambah stok produk?',
                'answer' => 'Penjual bisa menambah stok di menu Produk → Edit Produk → Update jumlah stok.',
                'icon' => 'fa-boxes'
            ],
            [
                'question' => 'Apa yang terjadi jika stok habis?',
                'answer' => 'Produk akan otomatis tidak tampil di katalog pembeli. Penjual bisa hapus produk jika stok 0.',
                'icon' => 'fa-exclamation-triangle'
            ],
            [
                'question' => 'Bisakah mengubah harga produk?',
                'answer' => 'Ya, penjual bisa edit harga produk kapan saja melalui menu Produk → Edit Produk.',
                'icon' => 'fa-tag'
            ],
            [
                'question' => 'Bagaimana cara menghapus produk?',
                'answer' => 'Produk hanya bisa dihapus jika stok 0. Klik tombol hapus di card produk.',
                'icon' => 'fa-trash'
            ]
        ]
    ],
    [
        'category' => 'Akun & Keamanan',
        'questions' => [
            [
                'question' => 'Bagaimana cara mengganti password?',
                'answer' => 'Di menu My Account → tab Profil, masukkan password lama dan baru, lalu simpan.',
                'icon' => 'fa-key'
            ],
            [
                'question' => 'Apa yang harus dilakukan jika lupa password?',
                'answer' => 'Klik "Lupa Password" di halaman login, ikuti instruksi reset password via email.',
                'icon' => 'fa-question-circle'
            ],
            [
                'question' => 'Bagaimana cara mengubah email?',
                'answer' => 'Di menu My Account → tab Profil, ubah email dan konfirmasi via email verifikasi.',
                'icon' => 'fa-envelope'
            ],
            [
                'question' => 'Apakah bisa memiliki lebih dari 1 akun?',
                'answer' => 'Setiap email hanya bisa mendaftar 1 akun. Untuk role berbeda harus email berbeda.',
                'icon' => 'fa-user-plus'
            ]
        ]
    ],
    [
        'category' => 'Transaksi & Laporan',
        'questions' => [
            [
                'question' => 'Bagaimana cara approve pembayaran?',
                'answer' => 'Penjual bisa approve di menu Approve → klik Approve → input nomor resi.',
                'icon' => 'fa-check-circle'
            ],
            [
                'question' => 'Bagaimana menolak transaksi?',
                'answer' => 'Di menu Approve → klik Tolak → berikan alasan penolakan.',
                'icon' => 'fa-times-circle'
            ],
            [
                'question' => 'Bagaimana cara download laporan?',
                'answer' => 'Di menu Laporan → pilih periode → klik Download Excel.',
                'icon' => 'fa-file-excel'
            ],
            [
                'question' => 'Apakah ada batas waktu refund?',
                'answer' => 'Refund bisa diajukan maksimal 7 hari setelah barang diterima.',
                'icon' => 'fa-undo'
            ]
        ]
    ],
    [
        'category' => 'Teknis & Support',
        'questions' => [
            [
                'question' => 'Website error atau tidak bisa diakses?',
                'answer' => 'Coba clear cache browser atau gunakan mode incognito. Jika masih error, hubungi support.',
                'icon' => 'fa-exclamation-triangle'
            ],
            [
                'question' => 'Bagaimana cara menghubungi support?',
                'answer' => 'Email: support@sarianggrek.com atau WhatsApp: 0812-3456-7890 (08:00-17:00).',
                'icon' => 'fa-headset'
            ],
            [
                'question' => 'Apakah ada aplikasi mobile?',
                'answer' => 'Saat ini hanya tersedia versi web yang responsive untuk mobile.',
                'icon' => 'fa-mobile-alt'
            ],
            [
                'question' => 'Bagaimana cara memberikan feedback?',
                'answer' => 'Kirim email ke feedback@sarianggrek.com atau gunakan form kontak di website.',
                'icon' => 'fa-comment-alt'
            ]
        ]
    ]
];
// Handle contact form submission
$contact_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    // Save to database (you need to create a contact_messages table)
    $stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (name, email, subject, message, user_id, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $subject, $message, $_SESSION['id']);
    mysqli_stmt_execute($stmt);
    // In production, you would also send an email
    $contact_sent = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>❓ Help & Support - Sari Anggrek</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --primary-lighter: #60a5fa;
            --primary-bg: #eff6ff;
            --secondary: #64748b;
            --secondary-light: #94a3b8;
            --success: #10b981;
            --success-bg: #d1fae5;
            --error: #ef4444;
            --error-bg: #fee2e2;
            --warning: #f59e0b;
            --warning-bg: #fef3c7;
            --info: #3b82f6;
            --info-bg: #dbeafe;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
            position: relative;
        }
        /* Header */
        .header {
            background: var(--surface);
            padding: 3rem 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
        }
        .header h1 {
            color: var(--text-primary);
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .header h1 i {
            color: var(--primary);
        }
        .header p {
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
            font-size: 1.1rem;
        }
        /* Search Bar */
        .search-section {
            background: var(--surface);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid var(--border);
        }
        .search-container {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        .search-input {
            width: 100%;
            padding: 16px 60px 16px 25px;
            border: 2px solid var(--border);
            border-radius: 50px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            background: var(--surface);
            transition: all 0.3s;
            color: var(--text-primary);
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }
        .search-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary);
            border: none;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .search-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-50%) scale(1.05);
        }
        .search-hint {
            margin-top: 15px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        /* Quick Links */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        .quick-link-card {
            background: var(--surface);
            padding: 2rem 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        .quick-link-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transition: transform 0.3s;
        }
        .quick-link-card:hover::before {
            transform: scaleX(1);
        }
        .quick-link-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }
        .link-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }
        .quick-link-card h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 1.2rem;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
        }
        .quick-link-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        /* FAQ Section */
        .faq-section {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .section-header {
            padding: 2rem 2.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--background);
        }
        .section-header h2 {
            color: var(--text-primary);
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
        }
        .section-header h2 i {
            color: var(--primary);
        }
        .category-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 1.5rem 2.5rem;
            background: var(--background);
            border-bottom: 1px solid var(--border-light);
        }
        .category-tab {
            padding: 10px 20px;
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }
        .category-tab:hover {
            border-color: var(--primary-light);
            color: var(--primary);
            background: var(--primary-bg);
        }
        .category-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .faq-content {
            padding: 2.5rem;
            background: var(--surface);
        }
        .faq-category {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        .faq-category.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .faq-item {
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: all 0.3s;
            background: var(--surface);
        }
        .faq-item:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        .faq-item.active {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        .faq-question {
            padding: 1.5rem;
            background: var(--surface);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--text-primary);
            border: none;
            width: 100%;
            text-align: left;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .faq-question:hover {
            background: var(--primary-bg);
        }
        .faq-question i {
            transition: transform 0.3s;
            color: var(--primary);
        }
        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }
        .faq-answer {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s;
            background: var(--background);
        }
        .faq-item.active .faq-answer {
            padding: 1.5rem;
            max-height: 500px;
            border-top: 1px solid var(--border-light);
        }
        .faq-answer-content {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--text-secondary);
        }
        .answer-icon {
            color: var(--primary);
            font-size: 1.2rem;
            margin-top: 3px;
            flex-shrink: 0;
        }
        /* Contact Section */
        .contact-section {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .contact-info {
            padding: 3rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        .contact-info h2 {
            margin-bottom: 2rem;
            font-size: 1.8rem;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
        }
        .contact-info h2 i {
            margin-right: 10px;
        }
        .contact-methods {
            display: flex;
            flex-direction: column;
            gap: 1.8rem;
            margin-bottom: 2rem;
        }
        .contact-method {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .method-icon {
            width: 55px;
            height: 55px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            backdrop-filter: blur(5px);
        }
        .method-details h4 {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1rem;
        }
        .method-details p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .contact-hours {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        .hours-title {
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 1.1rem;
        }
        .hours-list {
            list-style: none;
        }
        .hours-list li {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 0.9rem;
        }
        .hours-list li:last-child {
            border-bottom: none;
        }
        /* Contact Form */
        .contact-form {
            padding: 3rem;
            background: var(--background);
        }
        .contact-form h2 {
            color: var(--text-primary);
            margin-bottom: 1.8rem;
            font-family: 'Inter', sans-serif;
            font-size: 1.8rem;
            font-weight: 600;
        }
        .contact-form h2 i {
            color: var(--primary);
            margin-right: 10px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        .form-input, .form-textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            background: var(--surface);
            color: var(--text-primary);
        }
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }
        .form-textarea {
            min-height: 160px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn-submit {
            background: var(--primary);
            color: white;
            padding: 14px 35px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }
        /* Success Message */
        .success-message {
            background: var(--success-bg);
            color: #065f46;
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.4s ease;
            border: 1px solid var(--success);
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .success-message i {
            font-size: 1.8rem;
            color: var(--success);
        }
        .success-message h4 {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .success-message p {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        /* Footer */
        .footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
        }
        /* Responsive */
        @media (max-width: 992px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
            .contact-info {
                padding: 2.5rem;
            }
            .contact-form {
                padding: 2.5rem;
            }
        }
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .quick-links {
                grid-template-columns: repeat(2, 1fr);
            }
            .category-tabs {
                padding: 1.5rem;
            }
            .faq-content {
                padding: 1.5rem;
            }
        }
        @media (max-width: 576px) {
            .quick-links {
                grid-template-columns: 1fr;
            }
            .category-tabs {
                justify-content: center;
            }
            .faq-question {
                font-size: 0.95rem;
            }
            .contact-info h2,
            .contact-form h2 {
                font-size: 1.5rem;
            }
            .header {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-question-circle"></i> Help & Support</h1>
            <p>Temukan jawaban untuk pertanyaan umum atau hubungi tim support kami</p>
        </div>
        
        <!-- Search Section -->
        <div class="search-section">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Cari bantuan..." id="searchHelp">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
            <div class="search-hint">
                <i class="fas fa-lightbulb" style="margin-right: 5px;"></i>
                Coba kata kunci: "pembayaran", "pengiriman", "stok", "password", "laporan"
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="quick-links">
            <a href="#faq" class="quick-link-card" onclick="showCategory('pembayaran')">
                <div class="link-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h3>Pembayaran</h3>
                <p>Metode pembayaran dan verifikasi</p>
            </a>
            <a href="#faq" class="quick-link-card" onclick="showCategory('pengiriman')">
                <div class="link-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <h3>Pengiriman</h3>
                <p>Ekspedisi dan lacak paket</p>
            </a>
            <a href="#faq" class="quick-link-card" onclick="showCategory('produk')">
                <div class="link-icon">
                    <i class="fas fa-box"></i>
                </div>
                <h3>Produk & Stok</h3>
                <p>Kelola produk dan stok</p>
            </a>
            <a href="#faq" class="quick-link-card" onclick="showCategory('transaksi')">
                <div class="link-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <h3>Transaksi</h3>
                <p>Approve dan tolak pembayaran</p>
            </a>
        </div>
        
        <!-- FAQ Section -->
        <div class="faq-section" id="faq">
            <div class="section-header">
                <h2><i class="fas fa-comments"></i> Pertanyaan yang Sering Ditanyakan (FAQ)</h2>
            </div>
            <div class="category-tabs" id="categoryTabs">
                <?php foreach ($faqs as $index => $category): ?>
                <button class="category-tab <?php echo $index === 0 ? 'active' : ''; ?>"
                    data-category="<?php echo strtolower(explode(' ', $category['category'])[0]); ?>">
                    <?php echo $category['category']; ?>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="faq-content">
                <?php foreach ($faqs as $index => $category):
                $category_id = strtolower(explode(' ', $category['category'])[0]);
                ?>
                <div class="faq-category <?php echo $index === 0 ? 'active' : ''; ?>"
                    id="category-<?php echo $category_id; ?>">
                    <?php foreach ($category['questions'] as $qIndex => $question): ?>
                    <div class="faq-item" id="faq-<?php echo $category_id . '-' . $qIndex; ?>">
                        <button class="faq-question" onclick="toggleFAQ('<?php echo $category_id . '-' . $qIndex; ?>')">
                            <span>
                                <i class="fas <?php echo $question['icon']; ?>" style="margin-right: 12px;"></i>
                                <?php echo $question['question']; ?>
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <div class="answer-icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div>
                                    <?php echo nl2br(htmlspecialchars($question['answer'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Contact Section -->
        <div class="contact-section">
            <div class="contact-grid">
                <!-- Contact Info -->
                <div class="contact-info">
                    <h2><i class="fas fa-headset"></i> Hubungi Kami</h2>
                    <div class="contact-methods">
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="method-details">
                                <h4>Email Support</h4>
                                <p>support@sarianggrek.com</p>
                            </div>
                        </div>
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div class="method-details">
                                <h4>Telepon/WhatsApp</h4>
                                <p>+62 812 3456 7890</p>
                            </div>
                        </div>
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="method-details">
                                <h4>Alamat Kantor</h4>
                                <p>Jl. Anggrek No. 123, Jakarta</p>
                            </div>
                        </div>
                    </div>
                    <div class="contact-hours">
                        <div class="hours-title"><i class="fas fa-clock" style="margin-right: 8px;"></i>Jam Operasional</div>
                        <ul class="hours-list">
                            <li><span>Senin - Jumat</span><span>08:00 - 17:00</span></li>
                            <li><span>Sabtu</span><span>08:00 - 12:00</span></li>
                            <li><span>Minggu</span><span>Libur</span></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="contact-form">
                    <h2><i class="fas fa-paper-plane"></i> Kirim Pesan</h2>
                    <?php if ($contact_sent): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <h4>Pesan Terkirim!</h4>
                            <p>Tim support kami akan membalas pesan Anda dalam 1x24 jam.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="name">Nama Lengkap</label>
                                <input type="text" id="name" name="name" class="form-input"
                                    value="<?php echo htmlspecialchars($user_name); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-input"
                                    value="<?php echo htmlspecialchars($_SESSION['email']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="subject">Subjek</label>
                            <input type="text" id="subject" name="subject" class="form-input"
                                placeholder="Contoh: Masalah pembayaran" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="message">Pesan</label>
                            <textarea id="message" name="message" class="form-textarea"
                                placeholder="Jelaskan masalah atau pertanyaan Anda secara detail..." required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Kirim Pesan
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><i class="fas fa-copyright"></i> 2025 Sari Anggrek Bookstore. Semua hak dilindungi.</p>
            <p style="font-size: 0.85rem; margin-top: 10px; color: var(--text-muted);">
                Versi 1.0 | Terakhir diperbarui: <?php echo date('d M Y'); ?>
            </p>
        </div>
    </div>
    
    <script>
        // FAQ functionality
        function toggleFAQ(id) {
            const faqItem = document.getElementById('faq-' + id);
            faqItem.classList.toggle('active');
        }
        
        // Category tabs
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const category = this.dataset.category;
                // Update active tab
                document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                // Show selected category
                document.querySelectorAll('.faq-category').forEach(cat => {
                    cat.classList.remove('active');
                });
                document.getElementById('category-' + category).classList.add('active');
                // Close all FAQ items when switching category
                document.querySelectorAll('.faq-item').forEach(item => {
                    item.classList.remove('active');
                });
            });
        });
        
        function showCategory(category) {
            const tab = document.querySelector(`[data-category="${category}"]`);
            if (tab) {
                tab.click();
            }
        }
        
        // Search functionality
        document.getElementById('searchHelp').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            if (searchTerm.length < 2) return;
            // Search through all FAQ items
            document.querySelectorAll('.faq-item').forEach(item => {
                const question = item.querySelector('.faq-question').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                    // Expand the FAQ item
                    item.classList.add('active');
                    // Show the category
                    const categoryId = item.closest('.faq-category').id.replace('category-', '');
                    showCategory(categoryId);
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Auto-expand FAQ on page load if URL has hash
        window.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.substring(1);
            if (hash) {
                const targetElement = document.getElementById(hash);
                if (targetElement && targetElement.classList.contains('faq-item')) {
                    targetElement.classList.add('active');
                    // Scroll to the element
                    setTimeout(() => {
                        targetElement.scrollIntoView({ behavior: 'smooth' });
                    }, 100);
                }
            }
        });
        
        // Contact form validation
        document.querySelector('form[method="POST"]')?.addEventListener('submit', function(e) {
            const message = document.getElementById('message');
            if (message.value.trim().length < 10) {
                e.preventDefault();
                alert('Pesan harus minimal 10 karakter');
                message.focus();
                message.style.borderColor = '#ef4444';
            }
        });
        
        // Real-time character counter for message
        const messageField = document.getElementById('message');
        if (messageField) {
            const counter = document.createElement('div');
            counter.style.cssText = 'text-align: right; font-size: 0.85rem; color: var(--text-muted); margin-top: 5px;';
            counter.textContent = '0/500 karakter';
            messageField.parentNode.appendChild(counter);
            messageField.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = `${length}/500 karakter`;
                if (length < 10) {
                    counter.style.color = '#ef4444';
                } else if (length > 400) {
                    counter.style.color = '#f59e0b';
                } else {
                    counter.style.color = 'var(--text-muted)';
                }
                // Limit to 500 characters
                if (length > 500) {
                    this.value = this.value.substring(0, 500);
                }
            });
        }
    </script>
</body>
</html>