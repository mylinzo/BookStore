<?php
/**
 * Konfigurasi Email - Untuk mengirim OTP via email
 * Menggunakan Gmail SMTP
 * 
 * PENTING: App Password Gmail TIDAK BOLEH ada spasi saat dipakai di code!
 */

// Use statements untuk PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ===== KONFIGURASI EMAIL =====
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'alinacavaluera3@gmail.com');
define('SMTP_PASSWORD', 'fdkn escm qthw vfys'); 
define('SENDER_EMAIL', 'alinacavaluera3@gmail.com'); 
define('SENDER_NAME', 'Pustaka Aruna');

// ===== FUNGSI UTAMA: KIRIM EMAIL OTP =====
function sendOTPViaEmail($email, $name, $otp, &$outError = null) {
    // Validasi input
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $outError = "Email penerima tidak valid.";
        return false;
    }
    
    if (empty($otp) || strlen($otp) !== 6) {
        $outError = "Kode OTP tidak valid.";
        return false;
    }

    // Cek apakah credentials masih default/placeholder
    if (strpos(SMTP_PASSWORD, 'your_app_password') !== false || 
        strpos(SMTP_USERNAME, 'your_email') !== false) {
        $outError = "Konfigurasi email belum diatur. Silakan update email_config.php dengan credentials Gmail Anda.";
        error_log("ERROR Email Config: " . $outError);
        return false;
    }

    try {
        // Load PHPMailer - Support Composer dan Manual Install
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php'; // Composer
        } elseif (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
            require_once __DIR__ . '/phpmailer/src/Exception.php';
            require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
            require_once __DIR__ . '/phpmailer/src/SMTP.php';
        } else {
            $outError = "PHPMailer tidak ditemukan. Install via Composer atau download manual.";
            error_log("ERROR: " . $outError);
            return false;
        }

        $mail = new PHPMailer(true);

        // === SMTP Configuration ===
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;
        
        // Debug mode - SET false untuk production!
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
        // $mail->Debugoutput = function($str, $level) { error_log("SMTP Debug [$level]: $str"); };

        // === Sender & Recipient ===
        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
        $mail->addAddress($email, $name);
        $mail->addReplyTo(SENDER_EMAIL, SENDER_NAME);

        // === Email Content ===
        $mail->isHTML(true);
        $mail->Subject = 'Kode OTP Reset Password - Pustaka Aruna';
        $mail->Body    = getOTPEmailHTML($name, $otp);
        $mail->AltBody = "Halo $name,\n\nKode OTP Anda: $otp\n\nKode berlaku 15 menit.\n\nJika Anda tidak meminta reset password, abaikan email ini.\n\n— Tim Pustaka Aruna";

        // === Send Email ===
        $mail->send();
        error_log("✓ Email OTP berhasil dikirim ke: $email");
        return true;

    } catch (Exception $e) {
        $outError = "Gagal mengirim email: " . $e->getMessage();
        error_log("✗ Email Error [$email]: " . $outError);
        return false;
    }
}

// ===== FUNCTION: Generate HTML Email OTP =====
function getOTPEmailHTML($name, $otp) {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeOtp  = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP - Pustaka Aruna</title>
    <style>
        body { font-family: 'Crimson Text', Georgia, serif; line-height: 1.6; background: #f5f1e8; padding: 20px; margin: 0; }
        .container { max-width: 600px; margin: 0 auto; background: #fffef9; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(139,69,19,0.15); border: 2px solid #d4af37; }
        .header { background: linear-gradient(135deg, #8b4513, #a0522d); padding: 30px 20px; text-align: center; color: #fffef9; border-bottom: 3px solid #d4af37; }
        .header h1 { font-size: 28px; margin: 0 0 8px; font-weight: 600; letter-spacing: 0.5px; }
        .header p { margin: 0; font-size: 16px; opacity: 0.95; font-style: italic; }
        .content { padding: 35px 30px; color: #5a4a3a; }
        .content h3 { font-size: 22px; color: #8b4513; margin: 0 0 20px; border-bottom: 2px dashed #d4af37; padding-bottom: 12px; }
        .content p { margin: 0 0 18px; font-size: 16px; }
        .otp-box { background: #fffef9; border: 3px dashed #d4af37; border-radius: 15px; padding: 25px; text-align: center; margin: 25px 0; }
        .otp-code { font-family: 'Courier New', monospace; font-size: 38px; font-weight: bold; letter-spacing: 12px; color: #8b4513; background: #fff; padding: 18px 25px; border-radius: 10px; display: inline-block; border: 2px solid #c9a87a; box-shadow: 0 4px 12px rgba(212,175,55,0.25); }
        .expiry { margin-top: 15px; font-size: 14px; color: #a0522d; font-weight: 500; }
        .note { background: rgba(212,175,55,0.12); padding: 15px 18px; border-radius: 10px; border-left: 4px solid #d4af37; margin: 25px 0; font-size: 15px; }
        .footer { background: #fffef9; padding: 25px 20px; text-align: center; border-top: 2px dashed #d4af37; color: #6a5a4a; font-size: 14px; }
        .footer hr { border: none; border-top: 1px solid #d4af37; margin: 15px 0; }
        @media (max-width: 600px) {
            .content { padding: 25px 20px; }
            .otp-code { font-size: 32px; letter-spacing: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📖 Pustaka Aruna</h1>
            <p>Toko Buku Klasik & Vintage</p>
        </div>
        <div class="content">
            <h3>Yth. {$safeName},</h3>
            <p>Kami menerima permintaan reset password untuk akun Pustaka Aruna Anda. Gunakan kode OTP berikut untuk verifikasi:</p>
            <div class="otp-box">
                <div class="otp-code">{$safeOtp}</div>
                <p class="expiry">⏱️ Berlaku 15 menit</p>
            </div>
            <div class="note">
                <strong>💡 Penting:</strong> Jika Anda <em>tidak</em> meminta reset password, abaikan email ini. Akun Anda tetap aman.
            </div>
            <p style="font-size:14px; color:#7a6a5a;">Jangan pernah membagikan kode OTP ini kepada siapapun, termasuk pihak yang mengaku dari Pustaka Aruna.</p>
        </div>
        <div class="footer">
            <p>Hormat kami,<br><strong>Tim Pustaka Aruna</strong></p>
            <hr>
            <p>📧 alinacavaluera3@gmail.com<br>🌐 www.pustakaaruna.com</p>
            <p style="font-size:12px; margin-top:12px; color:#8b6f47;">© <?= date('Y') ?> Pustaka Aruna - Jakarta, Indonesia</p>
        </div>
    </div>
</body>
</html>
HTML;
}
?>