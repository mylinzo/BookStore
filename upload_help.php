<?php
/**
 * Fix Aplikasi Upload Gambar
 * Script untuk mengecek dan memperbaiki masalah upload
 */

session_start();

// Redirect jika tidak admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'penjual') {
    header('Location: ../login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Bantuan Upload - Toko Buku</title>
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
        }

        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        h1 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.5rem;
        }

        .info-box {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .info-box h2 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box h3 {
            color: #2c3e50;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }

        .checklist {
            list-style: none;
        }

        .checklist li {
            padding: 0.7rem 0;
            padding-left: 30px;
            position: relative;
            line-height: 1.6;
        }

        .checklist li:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.7rem;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checklist li.success:before {
            content: '✓';
            background: #2ecc71;
            border-color: #2ecc71;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            line-height: 1;
        }

        .checklist li.error:before {
            content: '✕';
            background: #e74c3c;
            border-color: #e74c3c;
            color: white;
            font-weight: bold;
        }

        .checklist li.warning:before {
            content: '⚠';
            background: #f39c12;
            border-color: #f39c12;
            color: white;
            font-weight: bold;
        }

        code {
            background: #f0f7ff;
            border: 1px solid #bbdefb;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #d32f2f;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .solution {
            background: #e8f5e9;
            border-left: 4px solid #2ecc71;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
        }

        .solution h4 {
            color: #2ecc71;
            margin-bottom: 0.5rem;
        }

        .solution ol {
            margin-left: 1.5rem;
            line-height: 1.8;
        }

        .solution li {
            margin-bottom: 0.5rem;
        }

        .format-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .format-card {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #ddd;
        }

        .format-card strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 0.5rem;
        }

        .format-card code {
            background: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .format-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-image"></i> Bantuan Upload Gambar Produk</h1>
            <p style="color: #7f8c8d;">Solusi untuk masalah gambar tidak muncul saat edit produk</p>
        </div>

        <!-- Format Mendukung -->
        <div class="info-box">
            <h2><i class="fas fa-check-circle" style="color: #2ecc71;"></i> Format Gambar yang Didukung</h2>
            
            <p style="margin-bottom: 1rem;">Gunakan format gambar asli. Jangan rename atau copy dari format lain.</p>
            
            <div class="format-grid">
                <div class="format-card">
                    <strong>JPEG</strong>
                    <code>.jpg</code><br>
                    <code>.jpeg</code>
                </div>
                <div class="format-card">
                    <strong>PNG</strong>
                    <code>.png</code>
                </div>
                <div class="format-card">
                    <strong>GIF</strong>
                    <code>.gif</code>
                </div>
                <div class="format-card">
                    <strong>WebP</strong>
                    <code>.webp</code>
                </div>
            </div>

            <h3>⚠️ FORMAT YANG TIDAK DIPERBOLEHKAN:</h3>
            <ul class="checklist">
                <li class="error">File dengan ekstensi .jpg tapi sebenarnya .txt atau format lain</li>
                <li class="error">File yang di-rename dari format lain (contoh: .docx direname jadi .jpg)</li>
                <li class="error">File RAW kamera (.raw, .cr2, .nef)</li>
                <li class="error">File vektor (.svg, .ai, .eps) - gunakan PNG/JPG export saja</li>
                <li class="error">File kompresi (.zip, .rar, .7z)</li>
            </ul>
        </div>

        <!-- Persyaratan Upload -->
        <div class="info-box">
            <h2><i class="fas fa-list-check"></i> Persyaratan Upload</h2>
            
            <ul class="checklist">
                <li class="success"><strong>Ukuran:</strong> Maksimal <code>50 MB</code> per file</li>
                <li class="success"><strong>Dimensi:</strong> Tidak ada batasan (Auto-scale di display)</li>
                <li class="success"><strong>Format:</strong> JPG, PNG, GIF, atau WebP</li>
                <li class="success"><strong>Jenis file:</strong> HARUS gambar asli, bukan file lain dengan ekstensi gambar</li>
            </ul>
        </div>

        <!-- Masalah Umum & Solusi -->
        <div class="info-box">
            <h2><i class="fas fa-wrench"></i> Masalah Umum & Solusi</h2>
            
            <h3>1. Gambar tidak tampil saat edit ganti gambar</h3>
            <div class="solution">
                <h4><i class="fas fa-check"></i> Solusi:</h4>
                <ol>
                    <li>Pastikan file adalah gambar ASLI (jangan rename dari format lain)</li>
                    <li>Gunakan gambar dari kamera atau download langsung (bukan screenshot yang direname)</li>
                    <li>Cek ukuran file: harus dibawah 50MB</li>
                    <li>Coba dengan format berbeda: JPG → PNG atau sebaliknya</li>
                    <li>Clear browser cache: Tekan <code>Ctrl + Shift + Del</code></li>
                </ol>
            </div>

            <h3>2. Error "Tipe file tidak valid"</h3>
            <div class="solution">
                <h4><i class="fas fa-check"></i> Solusi:</h4>
                <ol>
                    <li>File Anda adalah file lain yang direname dengan ekstensi .jpg/.png</li>
                    <li>Download gambar baru dari internet</li>
                    <li>Atau gunakan software edit gambar (Paint, GIMP, Photoshop) untuk export ulang</li>
                    <li>Pastikan pilih format PNG atau JPG saat export</li>
                </ol>
            </div>

            <h3>3. Error "Ukuran file terlalu besar"</h3>
            <div class="solution">
                <h4><i class="fas fa-check"></i> Solusi:</h4>
                <ol>
                    <li>File lebih besar dari 50MB</li>
                    <li>Kompres gambar menggunakan:
                        <ul style="margin-top: 0.5rem;">
                            <li><strong>Online:</strong> <code>tinypng.com</code>, <code>compressor.io</code></li>
                            <li><strong>Desktop:</strong> Paint, GIMP, Photoshop</li>
                            <li><strong>Windows:</strong> Klik kanan → Compress pictures</li>
                        </ul>
                    </li>
                </ol>
            </div>

            <h3>4. Proses upload sangat lambat</h3>
            <div class="solution">
                <h4><i class="fas fa-check"></i> Solusi:</h4>
                <ol>
                    <li>Kompres gambar terlebih dahulu</li>
                    <li>Gunakan browser modern (Chrome, Firefox, Edge)</li>
                    <li>Pastikan koneksi internet stabil</li>
                    <li>Update PHP/Server dengan kontribusi hosting (jika self-hosted)</li>
                </ol>
            </div>
        </div>

        <!-- Cara Optimasi Gambar -->
        <div class="info-box">
            <h2><i class="fas fa-image"></i> Cara Optimasi Gambar</h2>
            
            <h3>Metode 1: Online (Paling Mudah)</h3>
            <ol style="margin-left: 1.5rem; line-height: 1.8;">
                <li>Kunjungi <code>https://tinypng.com</code></li>
                <li>Drag & drop gambar Anda</li>
                <li>Download yang sudah dikompres</li>
                <li>Upload ke sistem</li>
            </ol>

            <h3>Metode 2: Windows Built-in</h3>
            <ol style="margin-left: 1.5rem; line-height: 1.8;">
                <li>Klik kanan file gambar</li>
                <li>Pilih <code>Compress pictures</code></li>
                <li>Pilih <code>Picture</code> (quality OK)</li>
                <li>Upload file yang sudah dikompres</li>
            </ol>

            <h3>Metode 3: Paint (Windows)</h3>
            <ol style="margin-left: 1.5rem; line-height: 1.8;">
                <li>Buka gambar dengan Paint</li>
                <li>File → Export As → Pilih format JPG</li>
                <li>Kurangi ukuran gambar (Image → Resize) jika perlu</li>
                <li>Upload file yang baru</li>
            </ol>
        </div>

        <!-- Tools & Debug -->
        <div class="info-box">
            <h2><i class="fas fa-tools"></i> Tools Testing</h2>
            
            <p style="margin-bottom: 1.5rem;">Gunakan tool berikut untuk test upload dan lihat diagnostic:</p>
            
            <div class="button-group">
                <a href="test_upload.php" class="btn btn-primary" target="_blank">
                    <i class="fas fa-vial"></i> Test Upload
                </a>
                <a href="penjual_produk.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Produk
                </a>
            </div>
        </div>

        <!-- Summary -->
        <div class="info-box" style="background: #e3f2fd; border-left: 4px solid #3498db;">
            <h2><i class="fas fa-lightbulb"></i> TIPS PENTING</h2>
            
            <ul class="checklist">
                <li class="success"><strong>Gunakan gambar ASLI</strong> - Download atau ambil dari kamera, bukan rename file</li>
                <li class="success"><strong>Kompres jika besar</strong> - Lebih cepat upload dan load</li>
                <li class="success"><strong>Format standar</strong> - JPG untuk foto, PNG untuk gambar transparan</li>
                <li class="success"><strong>Clear cache</strong> - Browser cache bisa menampilkan gambar lama</li>
                <li class="success"><strong>Tunggu proses</strong> - Jangan refresh saat upload berlangsung</li>
            </ul>
        </div>
    </div>
</body>
</html>
