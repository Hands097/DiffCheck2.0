<?php
session_start();
include('db.php');

if (!isset($_SESSION['verified_reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['verified_reset_email'];
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 6 || strlen($new_password) > 15) {
        $error_msg = "Password must be between 6 and 15 characters.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "Passwords do not match.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $escaped_email = mysqli_real_escape_string($conn, $email);
        mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE email='$escaped_email'");
        unset($_SESSION['verified_reset_email']);
        header("Location: login.php?msg=Password+reset+successful");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – DIFFCHECK</title>
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
            --success:       #2ed573;
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
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <img src="pic/logoV4.png" alt="DiffCheck Logo">
        <h2>Reset Password</h2>
        <p>Enter your new password below.</p>

        <?php if (!empty($error_msg)): ?>
            <div class="error-msg"><?= $error_msg ?></div>
        <?php endif; ?>

<form method="POST">
    <div class="input-group">
        <label>New Password</label>
        <div style="position: relative; display: flex; align-items: center;">
            <input type="password" id="newPasswordInput" name="new_password" minlength="6" maxlength="15" required style="width:100%; padding:12px 15px; border-radius:6px; border:none; outline:none; font-size:14px; font-family:'Exo 2',sans-serif; background:#fff; color:#000;">
            <button type="button" onclick="togglePassword('newPasswordInput', 'eyeNew', 'eyeOffNew')"
                style="position:absolute; right:10px; background:none; border:none; cursor:pointer; padding:4px; color:#888;">
                <svg id="eyeNew" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="eyeOffNew" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    style="display:none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            </button>
        </div>
    </div>
    <div class="input-group">
        <label>Confirm New Password</label>
        <div style="position: relative; display: flex; align-items: center;">
            <input type="password" id="confirmPasswordInput" name="confirm_password" minlength="6" maxlength="15" required style="width:100%; padding:12px 15px; border-radius:6px; border:none; outline:none; font-size:14px; font-family:'Exo 2',sans-serif; background:#fff; color:#000;">
            <button type="button" onclick="togglePassword('confirmPasswordInput', 'eyeConfirm', 'eyeOffConfirm')"
                style="position:absolute; right:10px; background:none; border:none; cursor:pointer; padding:4px; color:#888;">
                <svg id="eyeConfirm" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="eyeOffConfirm" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    style="display:none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            </button>
        </div>
    </div>
    <button type="submit" class="btn-submit">Reset Password</button>
</form>

<script>
function togglePassword(inputId, eyeId, eyeOffId) {
    const input = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    const eyeOff = document.getElementById(eyeOffId);
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    eye.style.display = isPassword ? 'none' : 'block';
    eyeOff.style.display = isPassword ? 'block' : 'none';
}
</script>
    </div>
</div>
</body>
</html>