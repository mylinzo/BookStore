<?php
/**
 * Help & Support Feature - Quick Access Index
 * 
 * Halaman ini menyediakan akses cepat ke semua fitur Help & Support
 */

session_start();
include 'koneksi.php';

// Check authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support Features</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            font-size: 32px;
        }

        .header h1 i {
            color: #667eea;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        .card-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .card-user { background: linear-gradient(135deg, #667eea, #764ba2); }
        .card-admin { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .card-setup { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .card-docs { background: linear-gradient(135deg, #43e97b, #38f9d7); }

        .card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .card-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #333;
            font-size: 13px;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .info-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .info-section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-section h2 i {
            color: #667eea;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .info-item strong {
            color: #333;
        }

        .info-item .path {
            color: #667eea;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #d1fae5;
            color: #059669;
        }

        .footer {
            text-align: center;
            color: white;
            font-size: 12px;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .header {
                padding: 25px;
            }

            .header h1 {
                font-size: 24px;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-question-circle"></i> Help & Support Features</h1>
            <p>Akses cepat ke fitur Help & Support yang telah diimplementasikan</p>
            <span class="status-badge" style="margin-top: 15px;"><i class="fas fa-check-circle"></i> v1.0 - Ready</span>
        </div>

        <!-- Quick Access -->
        <div class="grid">
            <!-- User Features -->
            <div class="card">
                <div class="card-icon card-user">
                    <i class="fas fa-user"></i>
                </div>
                <h3>User Features</h3>
                <p>Akses fitur Help & Support untuk pengguna (Pembeli dan Penjual)</p>
                <div class="card-links">
                    <a href="help.php" class="btn btn-primary">
                        <i class="fas fa-book"></i> Help Page
                    </a>
                    <a href="help.php#faq" class="btn btn-secondary">
                        <i class="fas fa-search"></i> Search FAQ
                    </a>
                </div>
            </div>

            <!-- Admin Features -->
            <div class="card">
                <div class="card-icon card-admin">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Admin Features</h3>
                <p>Dashboard untuk admin mengelola pesan dan FAQ</p>
                <div class="card-links">
                    <?php if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'penjual'): ?>
                    <a href="admin_help_messages.php" class="btn btn-primary">
                        <i class="fas fa-envelope"></i> Manage Messages
                    </a>
                    <a href="admin_help_messages.php?status=pending" class="btn btn-secondary">
                        <i class="fas fa-clock"></i> Pending Messages
                    </a>
                    <?php else: ?>
                    <button class="btn btn-primary" disabled>
                        <i class="fas fa-lock"></i> Admin Only
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Setup -->
            <div class="card">
                <div class="card-icon card-setup">
                    <i class="fas fa-tools"></i>
                </div>
                <h3>Setup & Configuration</h3>
                <p>Setup database dan inisialisasi fitur Help</p>
                <div class="card-links">
                    <a href="setup_help_feature.php" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Run Setup
                    </a>
                    <a href="#" onclick="alert('Buka file setup_help_database.sql di database manager Anda')" class="btn btn-secondary">
                        <i class="fas fa-database"></i> SQL File
                    </a>
                </div>
            </div>

            <!-- Documentation -->
            <div class="card">
                <div class="card-icon card-docs">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3>Documentation</h3>
                <p>Baca dokumentasi lengkap dan panduan penggunaan</p>
                <div class="card-links">
                    <a href="#docs" class="btn btn-primary">
                        <i class="fas fa-book-open"></i> View Docs
                    </a>
                    <a href="HELP_FEATURES_SUMMARY.md" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-external-link-alt"></i> Summary
                    </a>
                </div>
            </div>
        </div>

        <!-- Files & Paths -->
        <div class="info-section" id="docs">
            <h2><i class="fas fa-folder"></i> Files & Paths</h2>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Frontend</strong>
                    <span class="path">help.php</span>
                </div>
                <div class="info-item">
                    <strong>User API</strong>
                    <span class="path">api_help_messages.php</span>
                </div>
                <div class="info-item">
                    <strong>FAQ API</strong>
                    <span class="path">api_faq.php</span>
                </div>
                <div class="info-item">
                    <strong>Admin Panel</strong>
                    <span class="path">admin_help_messages.php</span>
                </div>
                <div class="info-item">
                    <strong>Admin API</strong>
                    <span class="path">admin_help_api.php</span>
                </div>
                <div class="info-item">
                    <strong>Setup Script</strong>
                    <span class="path">setup_help_feature.php</span>
                </div>
                <div class="info-item">
                    <strong>Database Schema</strong>
                    <span class="path">setup_help_database.sql</span>
                </div>
                <div class="info-item">
                    <strong>Full Documentation</strong>
                    <span class="path">DOKUMENTASI_HELP_SUPPORT.md</span>
                </div>
                <div class="info-item">
                    <strong>Setup Guide</strong>
                    <span class="path">SETUP_HELP_FEATURES.md</span>
                </div>
                <div class="info-item">
                    <strong>Feature Summary</strong>
                    <span class="path">HELP_FEATURES_SUMMARY.md</span>
                </div>
            </div>
        </div>

        <!-- Features Overview -->
        <div class="info-section">
            <h2><i class="fas fa-star"></i> Features Overview</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <h3 style="color: #667eea; margin-bottom: 10px; font-size: 14px;"><i class="fas fa-check"></i> User Features</h3>
                    <ul style="color: #666; font-size: 13px; line-height: 1.8;">
                        <li>✓ FAQ Search</li>
                        <li>✓ Contact Form</li>
                        <li>✓ Message History</li>
                        <li>✓ FAQ Feedback</li>
                        <li>✓ Quick Links</li>
                        <li>✓ Mobile Responsive</li>
                    </ul>
                </div>
                <div>
                    <h3 style="color: #667eea; margin-bottom: 10px; font-size: 14px;"><i class="fas fa-check"></i> Admin Features</h3>
                    <ul style="color: #666; font-size: 13px; line-height: 1.8;">
                        <li>✓ Dashboard Stats</li>
                        <li>✓ Message Management</li>
                        <li>✓ Reply System</li>
                        <li>✓ Filter & Search</li>
                        <li>✓ Export to CSV</li>
                        <li>✓ Pagination</li>
                    </ul>
                </div>
                <div>
                    <h3 style="color: #667eea; margin-bottom: 10px; font-size: 14px;"><i class="fas fa-check"></i> Technical</h3>
                    <ul style="color: #666; font-size: 13px; line-height: 1.8;">
                        <li>✓ SQL Protection</li>
                        <li>✓ Input Validation</li>
                        <li>✓ Auth Required</li>
                        <li>✓ Indexed DB</li>
                        <li>✓ Error Handling</li>
                        <li>✓ REST API</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- API Endpoints -->
        <div class="info-section">
            <h2><i class="fas fa-plug"></i> API Endpoints</h2>
            <div style="background: #1a202c; color: #a0aec0; padding: 20px; border-radius: 10px; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto;">
                <div style="margin-bottom: 15px;">
                    <span style="color: #90cdf4;">POST</span> /api_help_messages.php?action=create<br>
                    <span style="color: #90cdf4;">GET</span> /api_help_messages.php?action=list<br>
                    <span style="color: #90cdf4;">GET</span> /api_help_messages.php?action=detail&id=1<br>
                </div>
                <div style="margin-bottom: 15px;">
                    <span style="color: #90cdf4;">GET</span> /api_faq.php?action=categories<br>
                    <span style="color: #90cdf4;">GET</span> /api_faq.php?action=search&q=keyword<br>
                    <span style="color: #90cdf4;">POST</span> /api_faq.php?action=feedback<br>
                </div>
                <div>
                    <span style="color: #90cdf4;">GET</span> /admin_help_api.php?action=stats<br>
                    <span style="color: #90cdf4;">GET</span> /admin_help_api.php?action=export<br>
                </div>
            </div>
        </div>

        <!-- Database Tables -->
        <div class="info-section">
            <h2><i class="fas fa-database"></i> Database Tables</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px;">
                    <strong style="color: #333;">help_categories</strong>
                    <p style="color: #666; font-size: 12px; margin-top: 8px;">FAQ kategori dengan 6 default entries</p>
                </div>
                <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px;">
                    <strong style="color: #333;">help_articles</strong>
                    <p style="color: #666; font-size: 12px; margin-top: 8px;">FAQ artikel dengan 12 default entries</p>
                </div>
                <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px;">
                    <strong style="color: #333;">help_feedback</strong>
                    <p style="color: #666; font-size: 12px; margin-top: 8px;">User feedback pada artikel FAQ</p>
                </div>
                <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px;">
                    <strong style="color: #333;">contact_messages</strong>
                    <p style="color: #666; font-size: 12px; margin-top: 8px;">Pesan kontak dari pengguna</p>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="info-section" style="background: linear-gradient(135deg, #667eea15, #764ba215);">
            <h2><i class="fas fa-arrow-right"></i> Next Steps</h2>
            <ol style="color: #666; line-height: 2; padding-left: 20px;">
                <li><strong>Run Setup:</strong> Kunjungi <a href="setup_help_feature.php" style="color: #667eea;">setup_help_feature.php</a> untuk membuat database tables</li>
                <li><strong>Test Features:</strong> Coba Help page dan submit test message</li>
                <li><strong>Configure Email:</strong> Edit email settings di api_help_messages.php (optional)</li>
                <li><strong>Add to Menu:</strong> Integrasi Help link ke navigation yang lain</li>
                <li><strong>Monitor:</strong> Check admin dashboard untuk messages</li>
            </ol>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Help & Support Feature v1.0 • Created: January 28, 2025</p>
            <p style="margin-top: 10px; font-size: 11px; opacity: 0.8;">© 2025 Aksara-Aruna Bookstore. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
