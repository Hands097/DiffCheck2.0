<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Load .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

function sendOTP($toEmail, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom($_ENV['MAIL_USERNAME'], 'DiffCheck');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Your DiffCheck Verification Code';
        $mail->Body    = "
            <div style='font-family:sans-serif; max-width:400px; margin:auto;'>
                <h2 style='color:#00c2cb;'>DiffCheck Email Verification</h2>
                <p>Your one-time verification code is:</p>
                <h1 style='letter-spacing:8px; color:#333;'>$otp</h1>
                <p style='color:#888; font-size:12px;'>This code expires in 10 minutes.</p>
            </div>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}