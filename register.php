<?php
session_start();
include('db.php');

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    // Capture the role from the form
    $role = mysqli_real_escape_string($conn, $_POST['role']); 

    // SECURITY CHECK: Force the role to be either manager or organizer so no one can hack an admin account!
    if ($role !== 'manager' && $role !== 'organizer') {
        $role = 'manager'; 
    }

    // PHP Backend Validation
    if (strlen($username) < 4 || strlen($username) > 20) {
        $error_msg = "Username must be between 4 and 20 characters.";
    } elseif (strlen($password) < 6 || strlen($password) > 15) {
        $error_msg = "Password must be between 6 and 15 characters.";
    } else {
        // Check if email or username already exists
        $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' OR username='$username'");
        if (mysqli_num_rows($check) > 0) {
            $error_msg = "An account with that email or username already exists.";
        } else {
            // NOTE: If you are using plain text passwords, keep it as $password. 
            // If you want it hashed, use password_hash($password, PASSWORD_DEFAULT)
            $insert = mysqli_query($conn, "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$password', '$role')");
            
            if ($insert) {
                // Auto-login after successful registration
                $_SESSION['user_id'] = mysqli_insert_id($conn);
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                
                // Smart Redirect based on their choice!
                if ($role === 'organizer') {
                    header("Location: organizer_dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error_msg = "Error registering account. Please try again.";
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

        .error-msg { background: rgba(255, 71, 87, 0.1); color: var(--danger); padding: 10px; border-radius: 6px; border: 1px solid rgba(255, 71, 87, 0.3); margin-bottom: 20px; text-align: center; font-size: 13px; font-weight: 600; }

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
                        <label>Username <span>*</span></label>
                        <input type="text" name="username" minlength="4" maxlength="20" required>
                    </div>

                    <div class="input-group">
                        <label>Email Address <span>*</span></label>
                        <input type="email" name="email" required>
                    </div>
                    
                    <div class="input-group">
                        <label>Password <span>*</span></label>
                        <input type="password" name="password" minlength="6" maxlength="15" required>
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

</body>
</html>