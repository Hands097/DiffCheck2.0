<?php
ob_start(); // Prevents "headers already sent" errors
session_start();
include('db.php'); 

// If already logged in, safely redirect to the proper dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role']; // It's already forced to lowercase now!
    if ($role === 'admin') { header("Location: admin_dashboard.php"); exit(); }
    elseif ($role === 'organizer') { header("Location: organizer_dashboard.php"); exit(); }
    elseif ($role === 'manager') { header("Location: manager_dashboard.php"); exit(); }
    else { header("Location: index.php"); exit(); }
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password']; 

    // Basic length check to match your registration rules
    if (strlen($password) < 6 || strlen($password) > 15) {
        $error_msg = "Password must be between 6 and 15 characters.";
    } else {
        // Look for the user by Email OR Username
        $query = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' OR username='$email'");
        
        if ($query && mysqli_num_rows($query) > 0) {
            $user = mysqli_fetch_assoc($query);
            
            // Check password (handles both plain text and hashed passwords safely)
            if ($password === $user['password'] || password_verify($password, $user['password'])) {
                
                // ----------------------------------------------------
                // THE FIX: Force the role to lowercase before saving it!
                // ----------------------------------------------------
                $safe_role = strtolower($user['role']);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $safe_role; // Now it will always match 'manager'
                
                // Route to the correct dashboard
                if ($safe_role === 'admin') {
                    header("Location: admin_dashboard.php");
                    exit();
                } 
                elseif ($safe_role === 'organizer') {
                    header("Location: organizer_dashboard.php");
                    exit();
                } 
                elseif ($safe_role === 'manager') {
                    header("Location: manager_dashboard.php");
                    exit();
                } 
                else {
                    header("Location: index.php");
                    exit();
                }
                
            } else {
                $error_msg = "Incorrect password. Please try again.";
            }
        } else {
            $error_msg = "No account found with that email or username.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – DIFFCHECK</title>
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
            display: flex; align-items: center; justify-content: center; min-height: 100vh;
        }

        .auth-wrapper { width: 100%; padding: 20px; display: flex; justify-content: center; }

        .auth-card {
            background: rgba(19, 24, 32, 0.85); backdrop-filter: blur(10px);
            border: 1px solid var(--teal); box-shadow: 0 0 20px var(--teal-glow);
            border-radius: 12px; display: flex; max-width: 850px; width: 100%; overflow: hidden;
        }

        .auth-left { flex: 1; padding: 50px 40px; display: flex; flex-direction: column; justify-content: center; }
        .auth-header { text-align: center; margin-bottom: 30px; }
        .auth-header h2 { font-size: 28px; font-weight: 600; margin-bottom: 5px; }
        .auth-header p { color: var(--text-secondary); font-size: 14px; }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--text-primary); }
        .input-group label span { color: var(--danger); }
        .input-group input { width: 100%; padding: 12px 15px; border-radius: 6px; border: none; outline: none; font-size: 14px; font-family: 'Exo 2', sans-serif; background: #fff; color: #000; }

        .btn-submit { width: 100%; padding: 14px; background: var(--btn-blue); color: #fff; border: none; border-radius: 6px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background 0.3s; margin-top: 10px; }
        .btn-submit:hover { background: var(--btn-blue-hover); }

        .auth-right { flex: 1; padding: 50px 40px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-left: 1px solid rgba(255,255,255,0.05); }
        .auth-image { width: 200px; height: 200px; object-fit: contain; margin-bottom: 30px; background: transparent; border-radius: 8px; }
        .auth-right p { font-size: 14px; color: var(--text-secondary); text-align: center; }
        .auth-right a { color: var(--teal); text-decoration: none; font-weight: 700; transition: 0.3s; }
        .auth-right a:hover { text-shadow: 0 0 10px var(--teal); }

        .error-msg { background: rgba(255, 71, 87, 0.1); color: var(--danger); padding: 10px; border-radius: 6px; border: 1px solid rgba(255, 71, 87, 0.3); margin-bottom: 20px; text-align: center; font-size: 13px; font-weight: 600; }

        @media (max-width: 768px) {
            .auth-card { flex-direction: column; }
            .auth-right { border-left: none; border-top: 1px solid rgba(255,255,255,0.05); padding: 30px; }
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card">
            
            <div class="auth-left">
                <div class="auth-header">
                    <h2>Welcome Back!</h2>
                    <p>We are delighted to see you again!</p>
                </div>

                <?php if (!empty($error_msg)): ?>
                    <div class="error-msg"><?php echo $error_msg; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="input-group">
                        <label>Email or Username <span>*</span></label>
                        <input type="text" name="email" required>
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

                    <button type="submit" class="btn-submit">Submit</button>
                </form>
            </div>

            <div class="auth-right">
                <img src="pic/logoV4.png" alt="DiffCheck Logo" class="auth-image">
                <p>Don't have an account yet? <br><br><a href="register.php">Register here</a></p>
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
        </script>

</body>
</html>
<?php ob_end_flush(); ?>