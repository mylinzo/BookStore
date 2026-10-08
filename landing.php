<?php
include 'koneksi.php';
session_start();

$user_id = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : null;
$user_name = isset($_SESSION['nama']) ? $_SESSION['nama'] : '';

// Ambil produk tersedia
$query = "SELECT p.*, k.nama_kategori, u.nama AS nama_penjual FROM produk p
LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
LEFT JOIN users u ON p.id_penjual = u.id_user
WHERE p.stok > 0
ORDER BY p.created_at DESC";
$result = mysqli_query($conn, $query);

// Ambil total keranjang
$cart_count = 0;
if ($user_id) {
    $cart_query = "SELECT SUM(quantity) as total FROM keranjang WHERE id_user = '$user_id'";
    $cart_result = mysqli_query($conn, $cart_query);
    if ($cart_result) {
        $cart_data = mysqli_fetch_assoc($cart_result);
        $cart_count = $cart_data['total'] ?? 0;
    } else {
        $cart_query = "SELECT SUM(jumlah) as total FROM keranjang WHERE id_user = '$user_id'";
        $cart_result = mysqli_query($conn, $cart_query);
        if ($cart_result) {
            $cart_data = mysqli_fetch_assoc($cart_result);
            $cart_count = $cart_data['total'] ?? 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pustaka Alina - Toko Buku Online Premium</title>
    
    <!-- Bootstrap 5 & Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    
    <style>
        :root {
            /* Premium Bookstore Color Palette */
            --primary-color: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary-color: #7c3aed;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-color: #f8fafc;
            --dark-color: #0f172a;
            --gray-100: #f8fafc;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            
            /* Gradients */
            --gradient-primary: linear-gradient(135deg, #1e40af, #7c3aed);
            --gradient-secondary: linear-gradient(135deg, #06b6d4, #3b82f6);
            --gradient-dark: linear-gradient(135deg, #0f172a, #1e293b);
            --gradient-success: linear-gradient(135deg, #10b981, #059669);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #d97706);
            --gradient-danger: linear-gradient(135deg, #ef4444, #dc2626);
            
            /* Shadows & Radius */
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.15);
            --shadow-xl: 0 30px 80px rgba(0, 0, 0, 0.2);
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 30px;
            --radius-xl: 40px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', 'Poppins', sans-serif;
            color: var(--text-primary);
            overflow-x: hidden;
            scroll-behavior: smooth;
            background: linear-gradient(180deg, #ffffff 0%, #f0f7ff 100%);
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 18px 0;
            box-shadow: var(--shadow-sm);
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .navbar.scrolled {
            padding: 12px 0;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-md);
        }

        .logo {
            font-weight: 800;
            font-size: 28px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            transition: transform 0.3s;
            text-decoration: none;
        }
        .logo:hover { transform: scale(1.02); }
        .logo i { margin-right: 12px; font-size: 32px; }

        .btn-custom {
            background: var(--gradient-primary);
            color: white;
            border-radius: 50px;
            padding: 12px 32px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .btn-custom::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.7s;
            z-index: -1;
        }
        .btn-custom:hover::before { left: 100%; }
        .btn-custom:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            margin: 0 10px;
        }
        .nav-link:hover, .nav-link.active { color: var(--primary-color); }

        .cart-icon {
            position: relative;
            margin-left: 20px;
        }
        .cart-badge {
            position: absolute;
            top: -8px; right: -10px;
            background: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            min-width: 20px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);
            min-height: 95vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            margin-top: 80px;
            padding: 60px 0;
        }
        .hero-bg-pattern {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(30, 64, 175, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(124, 58, 237, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(6, 182, 212, 0.03) 0%, transparent 50%);
            z-index: 1;
        }
        .hero-content { position: relative; z-index: 2; }

        .hero-title {
            font-size: 3.8rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.1;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: float 6s ease-in-out infinite;
        }
        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
            max-width: 600px;
            font-weight: 400;
        }
        .hero-stats {
            display: flex;
            gap: 40px;
            margin-top: 40px;
        }
        .stat-item { display: flex; flex-direction: column; }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .hero-image {
            position: relative;
            animation: float 6s ease-in-out infinite;
        }
        .hero-image img {
            max-width: 100%;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            border: 5px solid white;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        /* Section Styling */
        .section-title {
            font-size: 2.8rem;
            font-weight: 800;
            margin: 0 auto 1rem;
            text-align: center;
            position: relative;
            display: block;
            left: auto;
            transform: none;
            max-width: 100%;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 5px;
            background: var(--gradient-primary);
            border-radius: 3px;
        }
        .section-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            text-align: center;
            max-width: 700px;
            margin: 0 auto 60px;
        }

        /* Products Section - Horizontal Scroll */
        .products-section {
            padding: 100px 0;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            position: relative;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 0 20px;
        }
        .scroll-controls {
            display: flex;
            gap: 12px;
        }
        .scroll-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--gray-200);
            color: var(--primary-color);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: var(--shadow-sm);
        }
        .scroll-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
            box-shadow: var(--shadow-md);
        }

        .products-horizontal {
            display: flex;
            gap: 24px;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding: 20px 20px 40px;
            scrollbar-width: thin;
            scroll-snap-type: x mandatory;
        }
        .products-horizontal::-webkit-scrollbar { height: 8px; }
        .products-horizontal::-webkit-scrollbar-track {
            background: var(--gray-200);
            border-radius: 10px;
        }
        .products-horizontal::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }

        .product-card {
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            border: none; /* remove line around card */
            position: relative;
            min-width: auto;
            max-width: 100%;
            flex-shrink: 0;
            scroll-snap-align: none;
        }

        @media (max-width: 1200px) {
            .products-grid { grid-template-columns: repeat(3, minmax(220px, 1fr)); }
        }
        @media (max-width: 992px) {
            .products-grid { grid-template-columns: repeat(2, minmax(220px, 1fr)); }
        }
        @media (max-width: 768px) {
            .products-grid { grid-template-columns: 1fr; }
            .product-img-container { height: 280px; }
        }

        /* Product Card - Premium Style */
        .product-card {
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            min-width: 300px;
            max-width: 300px;
            flex-shrink: 0;
            scroll-snap-align: start;
        }
        .product-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: var(--shadow-xl);
        }

        .product-img-container {
            position: relative;
            overflow: hidden;
            height: 240px; /* diperkecil agar tidak terlalu panjang */
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            cursor: pointer;
        }
        .product-img {
            height: 100%;
            width: 100%;
            object-fit: cover;
            transition: all 0.5s ease;
        }
        .product-card:hover .product-img { transform: scale(1.08); }

        .product-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(to top, rgba(30, 64, 175, 0.85), transparent 60%);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 18px;
            opacity: 0;
            transition: all 0.35s ease;
        }
        .product-card:hover .product-overlay { opacity: 1; }

        .product-badge {
            position: absolute;
            top: 20px; left: 20px;
            background: var(--gradient-success);
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            z-index: 2;
            box-shadow: var(--shadow-sm);
        }
        .product-badge.low-stock { background: var(--gradient-warning); }
        .product-badge.new { background: var(--gradient-primary); }

        .card-body { padding: 18px 16px; }

        .product-title {
            font-size: 1.05rem;
            line-height: 1.25;
            margin-bottom: 0.5rem;
            height: 2.6em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-description {
            color: var(--text-secondary);
            font-size: 0.92rem;
            margin: 0.6rem 0;
            line-height: 1.35;
            max-height: 3.2em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-price {
            font-size: 1.15rem;
            margin-bottom: 0.45rem;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            margin: 0.2rem 0 0.55rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .product-stock {
            font-size: 0.8rem;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .btn-add-cart,
        .btn-love {
            flex: 1;
            padding: 7px 8px;
            font-size: 0.85rem;
            border-radius: 999px;
            font-weight: 700;
        }

        .btn-love { padding: 7px 8px; }
            font-weight: 700;
            color: var(--text-primary);
            font-size: 1.25rem;
            margin-bottom: 10px;
            line-height: 1.4;
            cursor: pointer;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .product-title:hover { color: var(--primary-color); }

        .product-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-meta {
            display: flex;
            gap: 12px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .product-meta i { color: var(--primary-color); margin-right: 4px; }

        .product-price {
            font-weight: 800;
            color: var(--primary-color);
            font-size: 1.6rem;
            margin-bottom: 15px;
        }

        .product-stock {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .stock-available {
            background: #dcfce7;
            color: #16a34a;
        }
        .stock-low {
            background: #fed7aa;
            color: #ea580c;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 8px;
            color: #f59e0b;
            font-size: 0.95rem;
        }
        .product-rating .rating-value {
            margin-left: 8px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.85rem;
        }

        .product-actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        .btn-add-cart {
            flex: 1;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }
        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-add-cart:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
            transform: none;
        }
        

        /* Features Section */
        .features-section {
            padding: 100px 0;
            background: white;
        }
        .feature-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 40px 30px;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: all 0.4s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: var(--gradient-primary);
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }
        .feature-icon {
            font-size: 3.5rem;
            margin-bottom: 25px;
            display: inline-block;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            transition: all 0.5s;
        }
        .feature-card:hover .feature-icon {
            transform: scale(1.2) rotate(10deg);
        }
        .feature-title {
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 15px;
            font-size: 1.4rem;
        }
        .feature-text {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.7;
        }

        /* Stats Section */
        .stats-section {
            padding: 80px 0;
            background: var(--gradient-primary);
            color: white;
            position: relative;
        }
        .stat-card {
            text-align: center;
            padding: 30px;
            position: relative;
            z-index: 2;
        }
        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            line-height: 1;
        }
        .stat-text {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        /* Testimonials */
        .testimonials-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }
        .testimonial-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 40px 30px;
            box-shadow: var(--shadow-md);
            height: 100%;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.4s;
            position: relative;
        }
        .testimonial-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        .testimonial-quote {
            font-size: 4rem;
            color: var(--primary-color);
            opacity: 0.1;
            position: absolute;
            top: 10px; left: 20px;
            font-family: Georgia, serif;
        }
        .testimonial-text {
            font-size: 1.05rem;
            line-height: 1.7;
            color: var(--text-primary);
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .testimonial-avatar {
            width: 56px; height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }
        .testimonial-info h5 {
            font-weight: 700;
            margin-bottom: 3px;
            color: var(--text-primary);
        }
        .testimonial-info p {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        /* Newsletter */
        .newsletter-section {
            padding: 100px 0;
            background: var(--gradient-primary);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .newsletter-section::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            opacity: 0.3;
        }
        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        .newsletter-content { position: relative; z-index: 2; }
        .newsletter-title {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: white;
        }
        .newsletter-subtitle {
            font-size: 1.15rem;
            margin-bottom: 2.5rem;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto 2.5rem;
        }
        .newsletter-form {
            max-width: 500px;
            margin: 0 auto;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .form-control-newsletter {
            flex: 1;
            min-width: 250px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50px;
            padding: 16px 25px;
            font-size: 1rem;
            box-shadow: var(--shadow-md);
        }
        .btn-newsletter {
            background: white;
            color: var(--primary-color);
            border: none;
            border-radius: 50px;
            padding: 16px 40px;
            font-weight: 700;
            transition: all 0.3s;
            box-shadow: var(--shadow-md);
            cursor: pointer;
        }
        .btn-newsletter:hover {
            background: var(--light-color);
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        /* Footer */
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 80px 0 30px;
            position: relative;
        }
        .footer::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: var(--gradient-primary);
        }
        .footer-title {
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.4rem;
            color: white;
            position: relative;
            padding-bottom: 15px;
        }
        .footer-title::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0;
            width: 40px; height: 3px;
            background: var(--accent-color);
        }
        .footer-links {
            list-style: none;
            padding: 0;
        }
        .footer-links li { margin-bottom: 12px; }
        .footer-links a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .footer-links a:hover {
            color: var(--accent-color);
            padding-left: 5px;
        }
        .footer-links i { width: 20px; text-align: center; }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px; height: 48px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1.2rem;
        }
        .social-link:hover {
            background: var(--accent-color);
            transform: translateY(-5px) rotate(10deg);
            box-shadow: var(--shadow-md);
        }

        .copyright {
            text-align: center;
            padding-top: 40px;
            margin-top: 60px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.65);
            font-size: 0.95rem;
        }

        /* Back to top button */
        .back-to-top {
            position: fixed;
            bottom: 40px; right: 40px;
            width: 56px; height: 56px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            border: 2px solid white;
        }
        .back-to-top.active {
            opacity: 1;
            visibility: visible;
        }
        .back-to-top:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: var(--shadow-xl);
        }

        /* Modal Styles - Premium */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(15, 23, 42, 0.85);
            animation: fadeIn 0.3s ease;
            backdrop-filter: blur(5px);
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            width: 90%;
            max-width: 850px;
            border-radius: var(--radius-lg);
            animation: slideIn 0.4s ease;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            border: none !important;
        }
        .modal, .modal-content, .modal-header, .modal-body, .modal-book-detail, .modal-meta {
            border: none !important;
        }
        .modal-content hr,
        .modal-header::before,
        .modal-header::after {
            display: none !important;
            content: none !important;
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            background: var(--gradient-primary);
            color: white;
            padding: 25px 30px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: none !important; /* Tanpa garis/ornamen */
            border-bottom: none !important;
            box-shadow: none !important;
        }
        .modal-header::before,
        .modal-header::after {
            content: none; /* Hapus pseudo decorative line */
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
        }
        .close {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }
        .modal-body { padding: 30px; }
        .modal-book-detail {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .modal-book-image {
            flex: 1;
            min-width: 250px;
        }
        .modal-book-image img {
            width: 100%;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
        }
        .modal-book-info { flex: 2; }
        .modal-book-info h3 {
            font-size: 1.9rem;
            color: var(--text-primary);
            margin-bottom: 12px;
            font-weight: 700;
        }
        .modal-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            color: var(--text-secondary);
            font-size: 0.95rem;
            flex-wrap: wrap;
        }
        .modal-meta i { color: var(--primary-color); }
        .modal-description {
            margin: 20px 0;
            line-height: 1.7;
            color: var(--gray-700);
            font-size: 1rem;
        }
        .modal-price {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin: 20px 0;
        }
        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        .btn-modal-cart {
            background: var(--gradient-primary);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-modal-cart:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        .btn-modal-cart:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
            transform: none;
        }
        .btn-modal-close {
            background: var(--gray-100);
            color: var(--text-primary);
            padding: 14px 30px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-modal-close:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-title { font-size: 3rem; }
            .section-title { font-size: 2.5rem; }
        }
        @media (max-width: 768px) {
            .hero-title { font-size: 2.5rem; }
            .hero-stats { flex-direction: column; gap: 20px; }
            .section-title { font-size: 2rem; }
            .newsletter-form { flex-direction: column; }
            .btn-newsletter { width: 100%; }
            .modal-book-detail { flex-direction: column; }
            .scroll-controls { display: none; }
            .products-grid { grid-template-columns: 1fr; gap: 20px; padding: 10px 10px 30px; }
            .product-card { min-width: auto; max-width: 100%; }
        }
        @media (max-width: 576px) {
            .hero-title { font-size: 2rem; }
            .hero-subtitle { font-size: 1rem; }
            .product-actions { flex-direction: column; }
            .btn-detail { width: 100%; border-radius: 50px; }
        }

        /* Loading & Utilities */
        .loading {
            display: inline-block;
            width: 50px; height: 50px;
            border: 3px solid rgba(30, 64, 175, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        .empty-state i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        .empty-state h3 {
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        .empty-state p { color: var(--text-secondary); }

        /* Custom Cursor (Optional - can be disabled) */
        .custom-cursor {
            width: 20px; height: 20px;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            position: fixed;
            pointer-events: none;
            z-index: 9999;
            transition: transform 0.15s;
            mix-blend-mode: difference;
            display: none; /* Disabled by default for better mobile UX */
        }
        @media (min-width: 1024px) {
            .custom-cursor { display: block; }
        }
        .cursor-dot {
            width: 5px; height: 5px;
            background: var(--primary-color);
            border-radius: 50%;
            position: fixed;
            pointer-events: none;
            z-index: 9999;
            display: none;
        }
        @media (min-width: 1024px) {
            .cursor-dot { display: block; }
        }
    </style>
</head>
<body>

<!-- Custom Cursor -->
<div class="custom-cursor"></div>
<div class="cursor-dot"></div>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <a class="navbar-brand logo" href="landing.php">
            <i class="fas fa-book-open"></i>Pustaka Alina 
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav align-items-center">
                <li class="nav-item me-2">
                    <a href="login.php" class="btn btn-custom">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                </li>
                <li class="nav-item">
                    <a href="register.php" class="btn btn-custom">
                        <i class="fas fa-user-plus me-2"></i>Daftar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section" id="home">
    <div class="hero-bg-pattern"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="hero-content">
                    <h1 class="hero-title">Temukan Buku Impianmu</h1>
                    <p class="hero-subtitle">Lebih dari 1000+ koleksi buku terbaik dari berbagai genre. Baca, belajar, dan tumbuh bersama Pustaka Alina.</p>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-value"></span>
                            <span class="stat-label"></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"></span>
                            <span class="stat-label"></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"></span>
                            <span class="stat-label"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                <div class="hero-image">
                    <img src="https://images.unsplash.com/photo-1495446815901-a7297e633e8d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80" alt="Premium Books Collection">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Products Section -->
<section class="products-section" id="produk">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">Koleksi Buku Tersedia</h2>
        <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Temukan buku-buku berkualitas dari berbagai genre untuk menemani hari-harimu</p>

        <div class="section-header">
            <div></div>
            <div class="scroll-controls"></div>
        </div>

        <?php if($result && mysqli_num_rows($result) > 0): ?>
            <div class="products-horizontal" id="productsHorizontal">
                <?php 
                $index = 0;
                while($book = mysqli_fetch_assoc($result)): 
                    $badge = '';
                    if($book['stok'] <= 5 && $book['stok'] > 0) {
                        $badge = '<div class="product-badge low-stock"><i class="fas fa-fire me-1"></i>Stok Terbatas</div>';
                    } elseif($index % 5 == 0) {
                        $badge = '<div class="product-badge new"><i class="fas fa-star me-1"></i>Baru</div>';
                    }
                ?>
                <div class="product-card" data-aos="fade-up" data-aos-delay="<?= ($index % 4) * 100 ?>">
                    <?= $badge ?>
                    <div class="product-img-container" onclick="showBookDetail(<?= intval($book['id_produk']); ?>)">
                        <?php if(!empty($book['foto_produk']) && file_exists('uploads/produk/' . $book['foto_produk'])): ?>
                            <img src="uploads/produk/<?= htmlspecialchars($book['foto_produk']) ?>" 
                                 class="product-img" 
                                 alt="<?= htmlspecialchars($book['nama_buku']) ?>">
                        <?php else: ?>
                            <div class="product-img d-flex align-items-center justify-content-center">
                                <i class="fas fa-book text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="product-overlay">
                           
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="product-title" onclick="showBookDetail(<?= intval($book['id_produk']); ?>)">
                            <?= htmlspecialchars($book['nama_buku']) ?>
                        </h5>
                        <div class="product-rating">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star-half-alt text-warning"></i>
                            <span class="rating-value">4.5</span>
                        </div>
                        <div class="product-meta">
                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($book['nama_kategori'] ?: 'Umum') ?></span>
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($book['nama_penjual'] ?: 'Admin') ?></span>
                        </div>
                        <p class="product-description">
                            <?= !empty($book['deskripsi']) ? htmlspecialchars(substr($book['deskripsi'], 0, 90)) . (strlen($book['deskripsi']) > 90 ? '...' : '') : 'Deskripsi buku tersedia di detail.' ?>
                        </p>
                        <p class="product-price">Rp <?= number_format($book['harga'], 0, ',', '.') ?></p>
                        <div class="product-stock <?= ($book['stok'] > 5 ? 'stock-available' : 'stock-low') ?>">
                            <i class="fas <?= ($book['stok'] > 0 ? 'fa-check-circle' : 'fa-times-circle') ?>"></i>
                            <?php 
                            if($book['stok'] > 10) echo 'Tersedia';
                            elseif($book['stok'] > 0) echo 'Stok: ' . intval($book['stok']);
                            else echo 'Habis';
                            ?>
                        </div>
                        <div class="product-actions">
                            <button class="btn-add-cart" onclick="window.location.href='login.php'">
                                <i class="fas fa-cart-plus"></i> Keranjang
                            </button>
                        </div>
                    </div>
                </div>
                <?php $index++; endwhile; ?>
            </div>
        <?php else: ?>
            <div class="col-12" data-aos="fade-up">
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>Koleksi Buku Segera Hadir</h3>
                    <p>Kami sedang menyiapkan buku-buku terbaik untuk Anda</p>
                    <div class="loading mx-auto mt-4"></div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-5" data-aos="fade-up" data-aos-delay="300">
        
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <h2 class="section-title text-center mb-5" data-aos="fade-up">Kenapa Memilih Pustaka Alina?</h2>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card">
                    <i class="fas fa-truck-fast feature-icon"></i>
                    <h4 class="feature-title">Gratis Ongkir</h4>
                    <p class="feature-text">Gratis pengiriman ke seluruh Indonesia dengan minimum pembelian tertentu</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card">
                    <i class="fas fa-shield-heart feature-icon"></i>
                    <h4 class="feature-title">Garansi Original</h4>
                    <p class="feature-text">100% buku original dengan jaminan kualitas dan garansi pengembalian</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-card">
                    <i class="fas fa-headset feature-icon"></i>
                    <h4 class="feature-title">Support 24/7</h4>
                    <p class="feature-text">Tim customer service siap membantu Anda kapan saja via chat atau telepon</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                <div class="feature-card">
                    <i class="fas fa-book-bookmark feature-icon"></i>
                    <h4 class="feature-title">Koleksi Premium</h4>
                    <p class="feature-text">Ribuan judul buku dari penerbit ternama lokal dan internasional</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 col-6" data-aos="zoom-in">
                <div class="stat-card">
                    <div class="stat-number">10K+</div>
                    <div class="stat-text">Pembaca Bahagia</div>
                </div>
            </div>
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="100">
                <div class="stat-card">
                    <div class="stat-number">1K+</div>
                    <div class="stat-text">Judul Buku</div>
                </div>
            </div>
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="200">
                <div class="stat-card">
                    <div class="stat-number">50+</div>
                    <div class="stat-text">Kota Terjangkau</div>
                </div>
            </div>
            <div class="col-md-3 col-6" data-aos="zoom-in" data-aos-delay="300">
                <div class="stat-card">
                    <div class="stat-number">99%</div>
                    <div class="stat-text">Kepuasan Pelanggan</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials-section" id="testimonial">
    <div class="container">
        <h2 class="section-title text-center mb-5" data-aos="fade-up">Kata Mereka Tentang Kami</h2>
        <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Pengalaman nyata dari para pembaca setia Pustaka Alina</p>
        
        <div class="swiper testimonial-swiper" data-aos="fade-up" data-aos-delay="200">
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <div class="testimonial-card">
                        <div class="testimonial-quote">"</div>
                        <p class="testimonial-text">Belanja buku di Pustaka Alina sangat menyenangkan. Packing rapi, buku original, dan pengiriman cepat. Sudah langganan sejak tahun lalu!</p>
                        <div class="testimonial-author">
                            <img src="https://randomuser.me/api/portraits/women/32.jpg" alt="Customer" class="testimonial-avatar">
                            <div class="testimonial-info">
                                <h5>Sarah Amelia</h5>
                                <p>Pelanggan sejak 2023</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="testimonial-card">
                        <div class="testimonial-quote">"</div>
                        <p class="testimonial-text">Koleksi bukunya lengkap dan harganya kompetitif. Customer service-nya juga responsif. Recommended banget untuk pecinta buku!</p>
                        <div class="testimonial-author">
                            <img src="https://randomuser.me/api/portraits/men/54.jpg" alt="Customer" class="testimonial-avatar">
                            <div class="testimonial-info">
                                <h5>Budi Santoso</h5>
                                <p>Guru & Penulis</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="testimonial-card">
                        <div class="testimonial-quote">"</div>
                        <p class="testimonial-text">Pertama kali coba beli buku online di sini, ternyata pelayanannya memuaskan. Buku sampai dalam kondisi sempurna. Pasti belanja lagi!</p>
                        <div class="testimonial-author">
                            <img src="https://randomuser.me/api/portraits/women/67.jpg" alt="Customer" class="testimonial-avatar">
                            <div class="testimonial-info">
                                <h5>Dewi Lestari</h5>
                                <p>Mahasiswa</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-content text-center">
            <h2 class="newsletter-title" data-aos="fade-up">Dapatkan Update Terbaru</h2>
            <p class="newsletter-subtitle" data-aos="fade-up" data-aos-delay="100">Berlangganan newsletter kami untuk mendapatkan promo eksklusif dan rekomendasi buku terbaru</p>
            <div class="newsletter-form" data-aos="fade-up" data-aos-delay="200">
                <input type="email" class="form-control form-control-newsletter" placeholder="Masukkan email Anda">
                <button class="btn btn-newsletter">Berlangganan</button>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer" id="kontak">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <h4 class="footer-title">Pustaka Alina</h4>
                <p class="mb-4" style="color: rgba(255,255,255,0.85);">Toko buku online terpercaya yang menyediakan berbagai koleksi buku berkualitas untuk mendukung minat baca masyarakat Indonesia.</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-whatsapp"></i></a>
                    <a href="mailto:info@pustakaalina.com" class="social-link"><i class="fas fa-envelope"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 mb-4">
                <h4 class="footer-title">Menu</h4>
                <ul class="footer-links">
                    <li><a href="#home"><i class="fas fa-home"></i> Beranda</a></li>
                    <li><a href="#produk"><i class="fas fa-book"></i> Koleksi</a></li>
                    <li><a href="#testimonial"><i class="fas fa-comments"></i> Testimoni</a></li>
                    <li><a href="#"><i class="fas fa-info-circle"></i> Tentang Kami</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-6 mb-4">
                <h4 class="footer-title">Bantuan</h4>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-question-circle"></i> FAQ</a></li>
                    <li><a href="#"><i class="fas fa-undo"></i> Pengembalian</a></li>
                    <li><a href="#"><i class="fas fa-shield"></i> Privasi</a></li>
                    <li><a href="#"><i class="fas fa-file-contract"></i> Syarat & Ketentuan</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <h4 class="footer-title">Kontak Kami</h4>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-map-marker-alt"></i> Jl Marzuki 8 Kp. Jembatan Rt 09/014, Penggilingan, Cakung, Jakarta Timur.</a></li>
                    <li><a href="tel:+622112345678"><i class="fas fa-phone"></i>085773475477</a></li>
                    <li><a href="mailto:info@pustakaalina.com"><i class="fas fa-envelope"></i> alinacavaluera3@gmail.com</a></li>
                    <li><a href="#"><i class="fas fa-clock"></i> Senin - Minggu: 09:00 - 21:00</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">
            &copy; <?= date('Y'); ?> Pustaka Alina. | Designed with <i>Alina Cavaluera</i> 
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<a href="#" class="back-to-top">
    <i class="fas fa-chevron-up"></i>
</a>

<!-- Modal for Book Detail -->
<div id="bookModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-book me-2"></i>Detail Buku</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="modal-book-detail">
                <div class="modal-book-image">
                    <img id="modalBookImage" src="" alt="Book Image">
                </div>
                <div class="modal-book-info">
                    <h3 id="modalBookTitle"></h3>
                    <div class="modal-meta">
                        <span><i class="fas fa-tag"></i> <span id="modalBookCategory"></span></span>
                        <span><i class="fas fa-user"></i> <span id="modalBookSeller"></span></span>
                    </div>
                    <div class="modal-description">
                        <strong>Deskripsi:</strong><br>
                        <span id="modalBookDescription"></span>
                    </div>
                    <div class="modal-price">
                        Rp <span id="modalBookPrice"></span>
                    </div>
                    <div id="modalBookStock"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
    // Initialize AOS
    AOS.init({ duration: 1000, easing: 'ease-out-cubic', once: false, offset: 100, mirror: true });

    // Initialize Swiper for testimonials
    const swiper = new Swiper('.testimonial-swiper', {
        slidesPerView: 1, spaceBetween: 30, loop: true,
        autoplay: { delay: 5000, disableOnInteraction: false },
        pagination: { el: '.swiper-pagination', clickable: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        breakpoints: {
            768: { slidesPerView: 2 },
            992: { slidesPerView: 3 }
        }
    });

    // Custom Cursor
    const cursor = document.querySelector('.custom-cursor');
    const cursorDot = document.querySelector('.cursor-dot');
    if (cursor && cursorDot) {
        document.addEventListener('mousemove', (e) => {
            cursor.style.left = e.clientX + 'px';
            cursor.style.top = e.clientY + 'px';
            cursorDot.style.left = e.clientX + 'px';
            cursorDot.style.top = e.clientY + 'px';
        });
        document.addEventListener('mousedown', () => {
            cursor.style.transform = 'scale(0.8)';
            cursorDot.style.transform = 'scale(0.8)';
        });
        document.addEventListener('mouseup', () => {
            cursor.style.transform = 'scale(1)';
            cursorDot.style.transform = 'scale(1)';
        });
        document.querySelectorAll('a, button, .btn, .product-card, .feature-card').forEach(el => {
            el.addEventListener('mouseenter', () => {
                cursor.style.transform = 'scale(1.5)';
                cursor.style.borderColor = 'var(--accent-color)';
            });
            el.addEventListener('mouseleave', () => {
                cursor.style.transform = 'scale(1)';
                cursor.style.borderColor = 'var(--primary-color)';
            });
        });
    }

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        const backToTop = document.querySelector('.back-to-top');
        if (window.scrollY > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        if (window.scrollY > 500) {
            backToTop.classList.add('active');
        } else {
            backToTop.classList.remove('active');
        }
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 90,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Horizontal scroll controls for products container
    const productsContainer = document.getElementById('productsHorizontal');
    // Tombol scroll sudah dihapus, jadi tidak ada event listener tambahan.

    // Modal functionality
    const modal = document.getElementById('bookModal');
    const closeBtn = document.querySelector('.close');
    const closeModalBtn = document.querySelector('.btn-modal-close');
    
    function closeModal() { modal.style.display = 'none'; }
    if (closeBtn) closeBtn.onclick = closeModal;
    if (closeModalBtn) closeModalBtn.onclick = closeModal;
    window.onclick = function(event) { if (event.target == modal) closeModal(); }

    // Book data for modal
    const booksDataArray = <?php 
    mysqli_data_seek($result, 0);
    $books_array = [];
    while($book = mysqli_fetch_assoc($result)) {
        $books_array[] = [
            'id_produk' => $book['id_produk'],
            'nama_buku' => $book['nama_buku'],
            'deskripsi' => $book['deskripsi'] ?? 'Tidak ada deskripsi',
            'harga' => $book['harga'],
            'stok' => $book['stok'],
            'foto_produk' => $book['foto_produk'],
            'nama_kategori' => $book['nama_kategori'] ?? 'Umum',
            'nama_penjual' => $book['nama_penjual'] ?? 'Admin'
        ];
    }
    echo json_encode($books_array);
    ?>;

    function showBookDetail(bookId) {
        const book = booksDataArray.find(b => b.id_produk == bookId);
        if (!book) return;
        
        document.getElementById('modalBookTitle').textContent = book.nama_buku;
        document.getElementById('modalBookCategory').textContent = book.nama_kategori;
        document.getElementById('modalBookSeller').textContent = book.nama_penjual;
        document.getElementById('modalBookDescription').textContent = book.deskripsi;
        document.getElementById('modalBookPrice').textContent = new Intl.NumberFormat('id-ID').format(book.harga);
        
        const stockElement = document.getElementById('modalBookStock');
        if (book.stok > 0) {
            stockElement.innerHTML = `<span class="product-stock stock-available"><i class="fas fa-check-circle me-1"></i>Stok: ${book.stok} buku tersedia</span>`;
        } else {
            stockElement.innerHTML = `<span class="product-stock stock-low"><i class="fas fa-times-circle me-1"></i>Stok Habis</span>`;
        }
        
        const imageElement = document.getElementById('modalBookImage');
        if (book.foto_produk && book.foto_produk !== '') {
            imageElement.src = 'uploads/produk/' + book.foto_produk;
        } else {
            imageElement.src = 'https://via.placeholder.com/400x500?text=Pustaka+Alina';
        }
        
        // Tombol tambah ke keranjang di modal dihapus sesuai permintaan.
        modal.style.display = 'block';
    }

    function addToCart(productId) {
        <?php if($user_id): ?>
            fetch('api_add_to_cart.php?id=' + productId, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        cartBadge.textContent = data.cart_count;
                    } else if (data.cart_count > 0) {
                        const cartIcon = document.querySelector('.cart-icon');
                        const badge = document.createElement('span');
                        badge.className = 'cart-badge';
                        badge.textContent = data.cart_count;
                        cartIcon.appendChild(badge);
                    }
                    showNotification('success', 'Berhasil menambahkan ke keranjang!');
                } else {
                    showNotification('error', data.message || 'Gagal menambahkan ke keranjang');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Terjadi kesalahan, silakan coba lagi.');
            });
        <?php else: ?>
            window.location.href = 'login.php';
        <?php endif; ?>
    }

    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 15px; box-shadow: var(--shadow-lg); border: none;';
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-3 fs-4"></i>
                <div>
                    <h6 class="mb-0">${type === 'success' ? 'Berhasil!' : 'Gagal!'}</h6>
                    <small>${message}</small>
                </div>
            </div>
        `;
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100px)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Newsletter subscription
    document.querySelector('.btn-newsletter')?.addEventListener('click', function() {
        const emailInput = document.querySelector('.form-control-newsletter');
        const email = emailInput.value;
        if (email && email.includes('@')) {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            this.disabled = true;
            setTimeout(() => {
                showNotification('success', `Terima kasih! Kami akan mengirim update ke ${email}`);
                this.innerHTML = originalText;
                this.disabled = false;
                emailInput.value = '';
            }, 1500);
        } else {
            emailInput.classList.add('is-invalid');
            setTimeout(() => emailInput.classList.remove('is-invalid'), 2000);
        }
    });

    // Back to top
    document.querySelector('.back-to-top')?.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Animation on scroll for product cards
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.product-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease';
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 200 * index);
        });
    });
</script>
</body>
</html>