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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Squad – DIFFCHECK</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Exo+2:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-base:       #0a0c10;
            --bg-sidebar:    #0d1117;
            --bg-card:       #131820;
            --border:        #1e2a38;
            --border-accent: #1b3a4b;
            --teal:          #00c2cb;
            --teal-dim:      #009da5;
            --teal-glow:     rgba(0,194,203,0.18);
            --text-primary:  #d8e8f0;
            --text-secondary:#6a8fa8;
            --text-muted:    #3d5468;
            --topbar-h:      65px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; }

        body { background: var(--bg-base); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; display: flex; }

        /* ── SIDEBAR ── */
        .sidebar { width: 260px; background: var(--bg-sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; flex-shrink: 0; z-index: 100; }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .brand-title { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; letter-spacing: 1.5px; color: var(--text-primary); text-transform: uppercase; line-height: 1; }
        .brand-title span { color: var(--teal); }
        .brand-subtitle { font-size: 10px; color: var(--text-secondary); letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; }

        .sidebar-nav { flex: 1; padding: 20px 0; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: var(--text-secondary); text-decoration: none; font-weight: 500; transition: 0.2s; border-left: 3px solid transparent; cursor: pointer; }
        .nav-item i { width: 20px; text-align: center; }
        .nav-item:hover { color: var(--text-primary); background: rgba(255,255,255,0.02); }
        .nav-item.active { background: rgba(0,194,203,0.08); color: var(--teal); border-left-color: var(--teal); font-weight: 600; }

        /* ── MAIN ── */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; background: var(--bg-base) url('pic/bg.png') center center / cover fixed; }
        .main-header { height: var(--topbar-h); padding: 0 30px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); background: rgba(13,17,23,0.8); backdrop-filter: blur(10px); }
        .page-title { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; letter-spacing: 1px; text-transform: uppercase; }

        .content-body { flex: 1; padding: 40px; overflow-y: auto; display: flex; justify-content: center; }

        /* ── FORM PANEL ── */
        .panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; width: 100%; max-width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .panel-head { background: rgba(0,0,0,0.2); padding: 20px 30px; border-bottom: 1px solid var(--border); font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: var(--teal); text-transform: uppercase; display: flex; align-items: center; gap: 10px; }
        .panel-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 1px; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-accent); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; border-radius: 6px; outline: none; transition: border-color .2s; }
        .form-control:focus { border-color: var(--teal); }

        .current-logo { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 2px solid var(--border-accent); margin-bottom: 10px; display: block; background: rgba(0,0,0,0.3); }

        .btn-submit { width: 100%; padding: 14px; background: var(--teal); color: #000; border: none; border-radius: 6px; font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: 0.3s; margin-top: 10px; letter-spacing: 1px; }
        .btn-submit:hover { background: var(--teal-dim); box-shadow: 0 0 15px var(--teal-glow); transform: translateY(-2px); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="brand-title">DIFF<span>CHECK</span></div>
        <div class="brand-subtitle">Tournament System</div>
    </div>
    <nav class="sidebar-nav">
        <a href="manager_dashboard.php" class="nav-item"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        <a href="#" class="nav-item active"><i class="fa-solid fa-shield-halved"></i> Edit Squad</a>
    </nav>
</aside>

<main class="main-wrapper">
    <header class="main-header">
        <div class="page-title">Squad Management</div>
    </header>

    <div class="content-body">
        <div class="panel">
            <div class="panel-head"><i class="fa-solid fa-shield-halved"></i> Edit Squad: <?php echo htmlspecialchars($squad['name']); ?></div>
            <div class="panel-body">
                <form method="POST" enctype="multipart/form-data">

                    <div class="form-group">
                        <label class="form-label">Current Logo</label>
                        <img src="uploads/squads/<?php echo htmlspecialchars($squad['logo']); ?>" alt="Squad Logo" class="current-logo" onerror="this.onerror=null;this.src='uploads/squads/default_logo.png';">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Upload New Logo</label>
                        <input type="file" name="squad_logo" class="form-control" accept="image/png, image/jpeg, image/jpg" style="padding: 9px 15px;">
                        <small style="color: var(--text-muted); font-size: 11px; margin-top: 5px; display: block;">Leave blank to keep current logo. JPG or PNG only.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Squad Name</label>
                        <input type="text" name="squad_name" class="form-control" value="<?php echo htmlspecialchars($squad['name']); ?>" maxlength="15" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Game (Locked)</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($squad['game']); ?>" disabled style="opacity: 0.5; cursor: not-allowed;">
                    </div>

                    <button type="submit" name="update_squad" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</main>

</body>
</html>