<?php
/**
 * Script untuk Setup Email OTP yang Benar
 * Jalankan file ini untuk memastikan konfigurasi email sudah benar
 */

// Cek apakah file email_config.php ada
if (!file_exists('email_config.php')) {
    die("❌ File email_config.php tidak ditemukan!\n");
}

require_once 'email_config.php';

echo "🔧 Email Configuration Checker\n";
echo "==============================\n\n";

// Cek credentials
echo "1. 📧 Email Credentials:\n";
echo "   Username: " . (SMTP_USERNAME !== 'your_email@gmail.com' ? '✅ SET' : '❌ NOT SET') . "\n";
echo "   Password: " . (SMTP_PASSWORD !== 'your_app_password' ? '✅ SET' : '❌ NOT SET') . "\n\n";

// Cek PHPMailer
echo "2. 📦 PHPMailer Status:\n";
echo "   Vendor folder: " . (file_exists('vendor') ? '✅ EXISTS' : '❌ NOT FOUND') . "\n";
echo "   Autoload: " . (file_exists('vendor/autoload.php') ? '✅ EXISTS' : '❌ NOT FOUND') . "\n";
echo "   PHPMailer: " . (file_exists('vendor/phpmailer') ? '✅ EXISTS' : '❌ NOT FOUND') . "\n\n";

// Cek koneksi SMTP
echo "3. 🌐 SMTP Connection Test:\n";
$connection = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 5);
if ($connection) {
    echo "   SMTP Connection: ✅ SUCCESS\n";
    fclose($connection);
} else {
    echo "   SMTP Connection: ❌ FAILED ($errstr)\n";
}
echo "\n";

// Test email dengan credentials saat ini
echo "4. 📨 Email Test:\n";
$test_result = sendOTPViaEmail('test@example.com', 'Test User', '123456');
echo "   Email Test: " . ($test_result ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";

echo "📋 Jika email test GAGAL, ikuti langkah berikut:\n";
echo "================================================\n";
echo "1. ✅ Aktifkan 2-Factor Authentication di Gmail\n";
echo "   Buka: https://myaccount.google.com/security\n";
echo "   → 2-Step Verification → Enable\n\n";

echo "2. 🔑 Generate App Password\n";
echo "   Buka: https://myaccount.google.com/apppasswords\n";
echo "   → Select 'Mail' dan 'Windows Computer'\n";
echo "   → Copy password (16 karakter)\n\n";

echo "3. ⚙️ Update email_config.php\n";
echo "   Ganti baris berikut:\n";
echo "   define('SMTP_USERNAME', 'your_email@gmail.com');\n";
echo "   define('SMTP_PASSWORD', 'your_app_password');\n\n";

echo "4. 🧪 Test lagi\n";
echo "   Jalankan: php email_setup_checker.php\n\n";

echo "💡 Tips:\n";
echo "   - Pastikan email Gmail Anda aktif dan tidak diblokir\n";
echo "   - App Password berbeda dengan password login\n";
echo "   - Jika masih gagal, coba email Gmail lain\n";
echo "   - Untuk testing sementara, gunakan debug mode di forgot_password.php\n\n";

echo "📁 File terkait:\n";
echo "   - email_config.php (konfigurasi email)\n";
echo "   - email_fix_guide.php (panduan lengkap)\n";
echo "   - simple_email_test.php (test cepat)\n";
echo "   - forgot_password.php (halaman reset password)\n\n";

if (!$test_result) {
    echo "🚨 STATUS: Email belum dikonfigurasi dengan benar\n";
    echo "   Gunakan debug mode untuk testing fitur reset password\n";
} else {
    echo "✅ STATUS: Email siap digunakan!\n";
}
?>