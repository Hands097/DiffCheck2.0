<?php
ini_set('session.save_path', 'C:/xampp/tmp');
session_start();
include('db.php');
include('mailer.php');

$error_msg = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));

    $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    if (mysqli_num_rows($check) == 0) {
        $error_msg = "No account found with that email.";
    } else {
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        mysqli_query($conn, "DELETE FROM otp_verifications WHERE email='$email'");
        mysqli_query($conn, "INSERT INTO otp_verifications (email, otp, form_data, expires_at) 
            VALUES ('$email', '$otp', 'forgot_password', '$expires_at')");

        if (sendOTP($email, $otp)) {
            $_SESSION['reset_email'] = $email;
            header("Location: verify_forgot_otp.php");
            exit();
        } else {
            $error_msg = "Failed to send email. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – DIFFCHECK</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Exo+2:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep:       #0a0d10;
            --bg-card:       #131820;
            --teal:          #00c2cb;
            --teal-glow:     rgba(0, 194, 203, 0.4);
            --text-primary:  #ffffff;
            --text-secondary:#a0a0b5;
            --btn-blue:      #0d6efd;
            --btn-blue-hover:#0b5ed7;
            --danger:        #ff4757;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg-deep) url('pic/bg.png') center center / cover fixed;
            font-family: 'Exo 2', sans-serif;
            color: var(--text-primary);
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh;
        }
        .auth-wrapper { width: 100%; padding: 20px; display: flex; justify-content: center; }
        .auth-card {
            background: rgba(19, 24, 32, 0.85); backdrop-filter: blur(10px);
            border: 1px solid var(--teal); box-shadow: 0 0 20px var(--teal-glow);
            border-radius: 12px; max-width: 450px; width: 100%;
            padding: 50px 40px; text-align: center;
        }
        .auth-card img { width: 100px; margin-bottom: 20px; }
        .auth-card h2 { font-size: 26px; font-weight: 600; margin-bottom: 8px; }
        .auth-card p { color: var(--text-secondary); font-size: 14px; margin-bottom: 25px; }
        .input-group { margin-bottom: 15px; text-align: left; }
        .input-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px; }
        .input-group input {
            width: 100%; padding: 12px 15px; border-radius: 6px;
            border: none; outline: none; font-size: 14px;
            font-family: 'Exo 2', sans-serif; background: #fff; color: #000;
        }
        .btn-submit {
            width: 100%; padding: 14px; background: var(--btn-blue); color: #fff;
            border: none; border-radius: 6px; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: background 0.3s; margin-top: 10px;
        }
        .btn-submit:hover { background: var(--btn-blue-hover); }
        .error-msg {
            background: rgba(255, 71, 87, 0.1); color: var(--danger); padding: 10px;
            border-radius: 6px; border: 1px solid rgba(255, 71, 87, 0.3);
            margin-bottom: 20px; font-size: 13px; font-weight: 600;
        }
        .back-link { margin-top: 20px; font-size: 13px; color: var(--text-secondary); }
        .back-link a { color: var(--teal); text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <img src="pic/logoV4.png" alt="DiffCheck Logo">
        <h2>Forgot Password</h2>
        <p>Enter your registered email and we'll send you a verification code.</p>

        <?php if (!empty($error_msg)): ?>
            <div class="error-msg"><?= $error_msg ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <button type="submit" class="btn-submit">Send Code</button>
        </form>

        <div class="back-link">
            <p>Remembered it? <a href="login.php">Back to Login</a></p>
        </div>
    </div>
</div>
</body>
</html>