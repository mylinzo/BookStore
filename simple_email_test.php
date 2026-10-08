<?php
/**
 * Simple Email Test Script
 */
require_once 'email_config.php';

echo "Testing email functionality...\n\n";

$test_email = 'alinacavaluera3@gmail.com'; // Gunakan email yang sama dengan SMTP_USERNAME
$test_name = 'Test User';
$test_otp = '123456';

echo "Sending test email to: $test_email\n";
echo "OTP: $test_otp\n\n";

echo "SMTP Config:\n";
echo "Host: " . SMTP_HOST . "\n";
echo "Port: " . SMTP_PORT . "\n";
echo "Username: " . SMTP_USERNAME . "\n";
echo "Password: " . (SMTP_PASSWORD ? "***SET***" : "***NOT SET***") . "\n\n";

$result = sendOTPViaEmail($test_email, $test_name, $test_otp);

if ($result) {
    echo "✅ SUCCESS: Email sent successfully!\n";
    echo "Check your email inbox (and spam folder).\n";
} else {
    echo "❌ FAILED: Email could not be sent.\n";
    echo "Check the error logs and email configuration.\n";
    echo "\nPossible solutions:\n";
    echo "1. Make sure 2FA is enabled on Gmail account\n";
    echo "2. Generate App Password from https://myaccount.google.com/apppasswords\n";
    echo "3. Update SMTP_PASSWORD in email_config.php with the App Password\n";
    echo "4. Make sure the Gmail account allows less secure apps or use App Password\n";
    echo "For immediate testing of forgot password feature:\n";
    echo "- The system now has debug mode when email fails\n";
    echo "- When email fails, OTP is stored in session\n";
    echo "- Check the debug info on forgot_password.php page\n";
    echo "- Use the OTP shown in debug box for testing\n";
    echo "\nSee email_fix_guide.php for complete Gmail setup instructions.\n";
}
?>