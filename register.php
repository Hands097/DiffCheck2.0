<?php
session_start();
include('db.php');
include('mailer.php');

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim(mysqli_real_escape_string($conn, $_POST['first_name']));
    $last_name  = trim(mysqli_real_escape_string($conn, $_POST['last_name']));
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $password   = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    if ($role !== 'manager' && $role !== 'organizer') {
        $role = 'manager';
    }

    if (empty($first_name) || empty($last_name)) {
        $error_msg = "First name and last name are required.";
    } elseif (strlen($first_name) > 30 || strlen($last_name) > 30) {
        $error_msg = "Names must not exceed 30 characters each.";
    } elseif (strlen($password) < 6 || strlen($password) > 15) {
        $error_msg = "Password must be between 6 and 15 characters.";
    } elseif ($password !== $confirm_password) {
        $error_msg = "Passwords do not match.";
    } else {
        $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $error_msg = "An account with that email already exists.";
        } else {
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $form_data = json_encode([
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'password'   => $hashed_password,
                'role'       => $role
            ]);

            mysqli_query($conn, "DELETE FROM otp_verifications WHERE email='$email'");
            mysqli_query($conn, "INSERT INTO otp_verifications (email, otp, form_data, expires_at) 
                VALUES ('$email', '$otp', '$form_data', '$expires_at')");

            if (sendOTP($email, $otp)) {
                $_SESSION['pending_email'] = $email;
                header("Location: verify_otp.php");
                exit();
            } else {
                $error_msg = "Failed to send verification email. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – DIFFCHECK</title>
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
            border-radius: 12px; display: flex; max-width: 900px; width: 100%; overflow: hidden;
        }

        .auth-left {
            flex: 1; padding: 50px 40px; display: flex; flex-direction: column;
            align-items: center; justify-content: center; border-right: 1px solid rgba(255,255,255,0.05);
        }

        .auth-image { width: 200px; height: 200px; object-fit: contain; margin-bottom: 30px; background: transparent; border-radius: 8px; }
        .auth-left p { font-size: 14px; color: var(--text-secondary); text-align: center; }
        .auth-left a { color: var(--teal); text-decoration: none; font-weight: 700; transition: 0.3s; }
        .auth-left a:hover { text-shadow: 0 0 10px var(--teal); }

        .auth-right { flex: 1.2; padding: 50px 40px; display: flex; flex-direction: column; justify-content: center; }

        .auth-header { text-align: center; margin-bottom: 30px; }
        .auth-header h2 { font-size: 28px; font-weight: 600; margin-bottom: 5px; }
        .auth-header p { color: var(--text-secondary); font-size: 14px; }

        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--text-primary); }
        .input-group label span { color: var(--danger); }
        .input-group input, .input-group select {
            width: 100%; padding: 12px 15px; border-radius: 6px; border: none; outline: none;
            font-size: 14px; font-family: 'Exo 2', sans-serif; background: #fff; color: #000;
        }

        .btn-submit {
            width: 100%; padding: 14px; background: var(--btn-blue); color: #fff; border: none;
            border-radius: 6px; font-size: 15px; font-weight: 700; cursor: pointer;
            transition: background 0.3s; margin-top: 15px;
        }
        .btn-submit:hover { background: var(--btn-blue-hover); }

        .error-msg {
            background: rgba(255, 71, 87, 0.1); color: var(--danger); padding: 10px;
            border-radius: 6px; border: 1px solid rgba(255, 71, 87, 0.3);
            margin-bottom: 20px; text-align: center; font-size: 13px; font-weight: 600;
        }

        @media (max-width: 768px) {
            .auth-card { flex-direction: column-reverse; }
            .auth-left { border-right: none; border-top: 1px solid rgba(255,255,255,0.05); padding: 30px; }
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-left">
            <img src="pic/logoV4.png" alt="DiffCheck Logo" class="auth-image">
            <p>Already have an account? <br><a href="login.php">Login here</a></p>
        </div>

        <div class="auth-right">
            <div class="auth-header">
                <h2>Create an Account</h2>
                <p>Join the ultimate esports management platform.</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="error-msg"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label>First Name <span>*</span></label>
                    <input type="text" name="first_name" maxlength="30" required>
                </div>

                <div class="input-group">
                    <label>Last Name <span>*</span></label>
                    <input type="text" name="last_name" maxlength="30" required>
                </div>

                <div class="input-group">
                    <label>Email Address <span>*</span></label>
                    <input type="email" name="email" required>
                </div>

                <div class="input-group">
                    <label>Password <span>*</span></label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" id="passwordInput" name="password" minlength="6" maxlength="15" required>
                        <button type="button" onclick="togglePassword()" title="Show/hide password"
                            style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; padding: 4px; color: #888;">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="input-group">
                    <label>Confirm Password <span>*</span></label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" id="confirmPasswordInput" name="confirm_password" minlength="6" maxlength="15" required>
                        <button type="button" onclick="toggleConfirmPassword()" title="Show/hide password"
                            style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; padding: 4px; color: #888;">
                            <svg id="eyeIconConfirm" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg id="eyeOffIconConfirm" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="input-group">
                    <label>I want to be a... <span>*</span></label>
                    <select name="role" required>
                        <option value="manager">Squad Manager (Join Tournaments)</option>
                        <option value="organizer">Tournament Organizer (Host Events)</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">Sign Up</button>
            </form>
        </div>

    </div>
</div>

<script>
    function togglePassword() {
        const input = document.getElementById('passwordInput');
        const eyeIcon = document.getElementById('eyeIcon');
        const eyeOffIcon = document.getElementById('eyeOffIcon');
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        eyeIcon.style.display = isPassword ? 'none' : 'block';
        eyeOffIcon.style.display = isPassword ? 'block' : 'none';
    }

    function toggleConfirmPassword() {
        const input = document.getElementById('confirmPasswordInput');
        const eyeIcon = document.getElementById('eyeIconConfirm');
        const eyeOffIcon = document.getElementById('eyeOffIconConfirm');
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        eyeIcon.style.display = isPassword ? 'none' : 'block';
        eyeOffIcon.style.display = isPassword ? 'block' : 'none';
    }
</script>

</body>
</html>