<?php
/**
 * Test Email Script
 * Untuk mengecek apakah email bisa dikirim
 */
require_once 'email_config.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email - Pustaka Aruna</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f1e8;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        h1 {
            color: #8b4513;
            margin-bottom: 30px;
            text-align: center;
        }
        .info-box {
            background: #f5f1e8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #d4af37;
        }
        .info-box strong {
            color: #8b4513;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #8b4513;
            font-weight: 600;
        }
        input[type="email"],
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #c9a87a;
            border-radius: 6px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        input[type="email"]:focus,
        input[type="text"]:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #d4af37, #8b4513);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }
        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 8px;
            display: none;
        }
        .result.success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
            display: block;
        }
        .result.error {
            background: #f8d7da;
            border: 2px solid #f5222d;
            color: #721c24;
            display: block;
        }
        .result.warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            display: block;
        }
        .checklist {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .checklist p {
            margin: 10px 0;
            color: #654321;
        }
        .check {
            color: #28a745;
            font-weight: bold;
        }
        .cross {
            color: #f5222d;
            font-weight: bold;
        }
        code {
            background: #f5f1e8;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #8b4513;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Test Email Sender</h1>
        
        <div class="info-box">
            <strong>⚠️ PENTING:</strong> Sebelum test, pastikan Anda sudah update <code>email_config.php</code> dengan:
            <br>1. Email Gmail Anda (ganti <code>your_email@gmail.com</code>)
            <br>2. App Password dari Gmail (ganti <code>your_app_password</code>)
        </div>

        <div class="checklist">
            <p><strong>Checklist Email Config:</strong></p>
            <p>
                <?php 
                if (SMTP_USERNAME === 'your_email@gmail.com') {
                    echo '<span class="cross">✗</span> SMTP_USERNAME masih placeholder';
                } else {
                    echo '<span class="check">✓</span> SMTP_USERNAME sudah diset: ' . SMTP_USERNAME;
                }
                ?>
            </p>
            <p>
                <?php 
                if (SMTP_PASSWORD === 'your_app_password') {
                    echo '<span class="cross">✗</span> SMTP_PASSWORD masih placeholder';
                } else {
                    echo '<span class="check">✓</span> SMTP_PASSWORD sudah diset (****)';
                }
                ?>
            </p>
            <p>
                <?php 
                if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                    echo '<span class="check">✓</span> PHPMailer sudah terinstall';
                } else {
                    echo '<span class="cross">✗</span> PHPMailer belum terinstall - jalankan: composer install';
                }
                ?>
            </p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email Tujuan:</label>
                <input type="email" id="email" name="email" placeholder="test@example.com" value="<?php echo $_POST['email'] ?? ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="name">Nama (opsional):</label>
                <input type="text" id="name" name="name" placeholder="Nama Anda" value="<?php echo $_POST['name'] ?? 'Test User'; ?>">
            </div>

            <button type="submit" name="test_email">📧 Kirim Email Test</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
            $email = $_POST['email'] ?? '';
            $name = $_POST['name'] ?? 'Test User';
            $test_otp = '123456';

            if (empty($email)) {
                echo '<div class="result error"><strong>Error:</strong> Email tidak boleh kosong!</div>';
            } else {
                // Test send email
                $result = sendOTPViaEmail($email, $name, $test_otp);

                if ($result) {
                    echo '<div class="result success">
                        <strong>✓ Email berhasil dikirim!</strong>
                        <p>Email test dengan OTP: <code>' . $test_otp . '</code> telah dikirim ke <code>' . htmlspecialchars($email) . '</code></p>
                        <p>Silakan cek inbox email Anda (atau folder Spam/Promotions).</p>
                    </div>';
                } else {
                    echo '<div class="result error">
                        <strong>✗ Email gagal dikirim!</strong>
                        <p>Kemungkinan penyebab:</p>
                        <ul style="margin-left: 20px;">
                            <li>Email & Password di email_config.php masih placeholder</li>
                            <li>Gmail App Password tidak valid (bukan password biasa)</li>
                            <li>Perlu aktifkan "Less secure app access" di Gmail (jika pakai password biasa)</li>
                            <li>SMTP_PORT atau SMTP_HOST salah</li>
                            <li>Koneksi internet bermasalah</li>
                        </ul>
                        <p><strong>Debug Info:</strong> Cek file <code>logs/error_log</code> atau PHP error log untuk error detail.</p>
                    </div>';
                }
            }
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9rem; color: #654321;">
            <h3>📋 Panduan Setup Gmail:</h3>
            <ol style="margin-left: 20px;">
                <li>Buka <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">Google Account Settings</a></li>
                <li>Pilih "App passwords" (membutuhkan 2-factor authentication)</li>
                <li>Select "Mail" dan "Windows Computer"</li>
                <li>Copy App Password yang digenerate</li>
                <li>Update di <code>email_config.php</code>:
                    <pre style="background: #f5f1e8; padding: 10px; margin: 10px 0; border-radius: 4px;">define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx'); // Ganti dengan App Password</pre>
                </li>
                <li>Jalankan test ini lagi</li>
            </ol>
        </div>
    </div>
</body>
</html>
