<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: manager_dashboard.php");
    exit();
}

$squad_id = (int)$_GET['id'];

// Fetch current squad details
$squad_q = mysqli_query($conn, "SELECT * FROM squads WHERE id='$squad_id' AND manager_id='$user_id'");
if (mysqli_num_rows($squad_q) === 0) {
    header("Location: manager_dashboard.php");
    exit();
}
$squad = mysqli_fetch_assoc($squad_q);

// --- LOGIC: Update Squad ---
if (isset($_POST['update_squad'])) {
    $squad_name = mysqli_real_escape_string($conn, $_POST['squad_name']);
    $logo_filename = $squad['logo']; // Default to current logo

    // Handle new logo upload
    if (isset($_FILES['squad_logo']) && $_FILES['squad_logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['squad_logo']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $ext = pathinfo($_FILES['squad_logo']['name'], PATHINFO_EXTENSION);
            $new_logo_filename = uniqid('squad_') . '.' . $ext;
            $upload_dir = 'uploads/squads/';
            
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (move_uploaded_file($_FILES['squad_logo']['tmp_name'], $upload_dir . $new_logo_filename)) {
                // Delete old logo if it's not the default
                if ($logo_filename !== 'default_logo.png' && file_exists($upload_dir . $logo_filename)) {
                    unlink($upload_dir . $logo_filename);
                }
                $logo_filename = $new_logo_filename;
            }
        }
    }

    mysqli_query($conn, "UPDATE squads SET name='$squad_name', logo='$logo_filename' WHERE id='$squad_id' AND manager_id='$user_id'");
    
    $_SESSION['system_message'] = "Squad updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: manager_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Squad - DIFFCHECK</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Exo+2:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Including bare minimum matching styles from your dashboard */
        :root {
            --bg-base:       #0a0c10;
            --bg-card:       #131820;
            --border:        #1e2a38;
            --border-accent: #1b3a4b;
            --teal:          #00c2cb;
            --teal-dim:      #009da5;
            --text-primary:  #d8e8f0;
            --text-secondary:#6a8fa8;
        }
        body { background: var(--bg-base); color: var(--text-primary); font-family: 'Exo 2', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .panel-head { background: rgba(0,0,0,0.2); padding: 20px; border-bottom: 1px solid var(--border); font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: var(--teal); letter-spacing: 1px; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
        .panel-head a { color: var(--text-secondary); text-decoration: none; font-size: 14px; }
        .panel-head a:hover { color: var(--teal); }
        .panel-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-accent); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; border-radius: 6px; outline: none; box-sizing: border-box; }
        .btn-submit { display: block; width: 100%; padding: 12px; background: var(--teal); color: #000; border: none; border-radius: 6px; font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: var(--teal-dim); }
        .current-logo { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 2px solid var(--border-accent); margin-bottom: 10px; display: block; }
    </style>
</head>
<body>

<div class="panel">
    <div class="panel-head">
        <span><i class="fa-solid fa-pen"></i> Edit Squad</span>
        <a href="manager_dashboard.php"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-group">
                <label class="form-label">Current Logo</label>
                <img src="uploads/squads/<?php echo htmlspecialchars($squad['logo']); ?>" alt="Squad Logo" class="current-logo" onerror="this.src='default_logo.png';">
            </div>

            <div class="form-group">
                <label class="form-label">Upload New Logo</label>
                <input type="file" name="squad_logo" class="form-control" accept="image/png, image/jpeg, image/jpg" style="padding: 9px 15px;">
                <small style="color: var(--text-secondary); font-size: 11px; margin-top: 5px; display: block;">Leave blank to keep current logo.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Squad Name</label>
                <input type="text" name="squad_name" class="form-control" value="<?php echo htmlspecialchars($squad['name']); ?>" maxlength="15" required>
            </div>

            <div class="form-group">
                <label class="form-label">Game (Locked)</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($squad['game']); ?>" disabled style="opacity: 0.6;">
            </div>

            <button type="submit" name="update_squad" class="btn-submit">Save Changes</button>
        </form>
    </div>
</div>

</body>
</html>