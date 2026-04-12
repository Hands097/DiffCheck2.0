<?php
ini_set('session.save_path', 'C:/xampp/tmp');
session_start();
include('db.php');

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_otp = trim($_POST['otp']);
    $escaped_email = mysqli_real_escape_string($conn, $email);

    $result = mysqli_query($conn, "SELECT * FROM otp_verifications 
        WHERE email='$escaped_email' AND otp='$entered_otp'");

    if (mysqli_num_rows($result) > 0) {
        mysqli_query($conn, "DELETE FROM otp_verifications WHERE email='$escaped_email'");
        $_SESSION['verified_reset_email'] = $email;
        unset($_SESSION['reset_email']);
        header("Location: reset_password.php");
        exit();
    } else {
        $error_msg = "Invalid or expired OTP. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code – DIFFCHECK</title>
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
        .auth-card p span { color: var(--teal); font-weight: 600; }
        .otp-input {
            width: 100%; padding: 14px; text-align: center;
            font-size: 26px; letter-spacing: 10px; font-weight: 700;
            border-radius: 6px; border: none; outline: none;
            background: #fff; color: #000; margin-bottom: 15px;
        }
        .btn-submit {
            width: 100%; padding: 14px; background: var(--btn-blue); color: #fff;
            border: none; border-radius: 6px; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: background 0.3s;
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
        <h2>Enter Verification Code</h2>
        <p>We sent a 6-digit code to<br><span><?= htmlspecialchars($email) ?></span></p>

        <?php if (!empty($error_msg)): ?>
            <div class="error-msg"><?= $error_msg ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="otp" class="otp-input" maxlength="6" placeholder="______" required autofocus>
            <button type="submit" class="btn-submit">Verify Code</button>
        </form>

        <div class="back-link">
            <p>Wrong email? <a href="forgot_password.php">Go back</a></p>
        </div>
    </div>
</div>
</body>
</html>