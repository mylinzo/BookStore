<?php
/**
 * Panduan Perbaikan Email OTP
 * Jalankan file ini untuk melihat panduan lengkap
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panduan Perbaikan Email OTP - Pustaka Aruna</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; padding: 20px; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #1e293b; margin-bottom: 30px; text-align: center; }
        h2 { color: #2563eb; margin: 30px 0 15px 0; }
        .step { background: #f1f5f9; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2563eb; }
        .step-number { display: inline-block; background: #2563eb; color: white; width: 30px; height: 30px; border-radius: 50%; text-align: center; line-height: 30px; font-weight: bold; margin-right: 10px; }
        .code { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; margin: 10px 0; overflow-x: auto; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 15px; border-radius: 6px; margin: 20px 0; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Panduan Perbaikan Email OTP</h1>

        <div class="warning">
            <strong>⚠️ Masalah:</strong> Sistem email OTP saat ini tidak berfungsi karena autentikasi Gmail gagal.
        </div>

        <h2>📋 Penyebab Masalah</h2>
        <ul style="margin-left: 20px;">
            <li>App Password Gmail tidak valid atau kedaluwarsa</li>
            <li>2-Factor Authentication (2FA) tidak aktif di akun Gmail</li>
            <li>Akun Gmail diblokir untuk akses aplikasi</li>
            <li>Konfigurasi SMTP salah</li>
        </ul>

        <h2>🛠️ Solusi Lengkap</h2>

        <div class="step">
            <span class="step-number">1</span>
            <strong>Aktifkan 2-Factor Authentication (2FA)</strong>
            <p>Buka <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a> → 2-Step Verification → Enable</p>
        </div>

        <div class="step">
            <span class="step-number">2</span>
            <strong>Generate App Password</strong>
            <p>
                Buka <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a><br>
                Pilih "Mail" → "Windows Computer" → Generate<br>
                Copy password yang dihasilkan (16 karakter)
            </p>
        </div>

        <div class="step">
            <span class="step-number">3</span>
            <strong>Update email_config.php</strong>
            <p>Buka file <code>email_config.php</code> dan update:</p>
            <div class="code">
define('SMTP_USERNAME', 'alinacavaluera3@gmail.com');<br>
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx'); // Ganti dengan App Password baru
            </div>
        </div>

        <div class="step">
            <span class="step-number">4</span>
            <strong>Test Email</strong>
            <p>Jalankan <code>simple_email_test.php</code> untuk memastikan email berfungsi</p>
        </div>

        <h2>🔄 Alternatif Jika Gmail Tidak Berfungsi</h2>

        <div class="step">
            <strong>Opsi 1: Gunakan Email Lain</strong>
            <p>Ganti SMTP settings untuk provider email lain (Yahoo, Outlook, dll)</p>
        </div>

        <div class="step">
            <strong>Opsi 2: Disable Email OTP (Sementara)</strong>
            <p>Untuk testing, Anda bisa menonaktifkan email requirement dan menggunakan debug OTP</p>
            <div class="code">
// Di forgot_password.php, cari bagian kirim email dan comment out:<br>
// if (sendOTPViaEmail($email, $user_name, $otp)) {<br>
// &nbsp;&nbsp;&nbsp;&nbsp;header("location: forgot_password.php?step=2");<br>
// } else {<br>
// &nbsp;&nbsp;&nbsp;&nbsp;$error = "Gagal mengirim OTP...";<br>
// }<br>
// // Ganti dengan:<br>
// $_SESSION['debug_otp'] = $otp;<br>
// header("location: forgot_password.php?step=2&debug=1");
            </div>
        </div>

        <div class="success">
            <strong>✅ Setelah diperbaiki:</strong> Email OTP akan berfungsi normal dan user bisa reset password via email.
        </div>

        <p style="text-align: center; margin-top: 40px; color: #64748b;">
            Butuh bantuan lebih lanjut? Cek file <code>simple_email_test.php</code> untuk testing.
        </p>
    </div>
</body>
</html>