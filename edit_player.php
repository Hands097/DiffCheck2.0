<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager' || !isset($_GET['id'])) {
    header("Location: manager_dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$player_id = (int)$_GET['id'];

$query = mysqli_query($conn, "SELECT * FROM players WHERE id='$player_id' AND manager_id='$user_id'");
if (mysqli_num_rows($query) == 0) {
    echo "Player not found."; exit();
}
$player = mysqli_fetch_assoc($query);

if (isset($_POST['edit_player'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $ign = mysqli_real_escape_string($conn, $_POST['ign']);
    $game = mysqli_real_escape_string($conn, $_POST['game']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    mysqli_query($conn, "UPDATE players SET name='$name', ign='$ign', game='$game', role='$role' WHERE id='$player_id'");
    $_SESSION['system_message'] = "Player '$ign' updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: manager_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Player – DIFFCHECK</title>
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

        /* ── SIDEBAR (Simplified for Edit Page) ── */
        .sidebar { width: 260px; background: var(--bg-sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; flex-shrink: 0; z-index: 100; }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .brand-title { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; letter-spacing: 1.5px; color: var(--text-primary); text-transform: uppercase; line-height: 1; }
        .brand-title span { color: var(--teal); }
        .brand-subtitle { font-size: 10px; color: var(--text-secondary); letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; }

        .sidebar-nav { flex: 1; padding: 20px 0; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: var(--text-secondary); text-decoration: none; font-weight: 500; transition: 0.2s; border-left: 3px solid transparent; }
        .nav-item i { width: 20px; text-align: center; }
        .nav-item:hover { color: var(--text-primary); background: rgba(255,255,255,0.02); }
        .nav-item.active { background: rgba(0, 194, 203, 0.08); color: var(--teal); border-left-color: var(--teal); font-weight: 600; }

        /* ── MAIN CONTENT ── */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; background: var(--bg-base) url('pic/bg.png') center center / cover fixed; }
        .main-header { height: var(--topbar-h); padding: 0 30px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); background: rgba(13, 17, 23, 0.8); backdrop-filter: blur(10px); }
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
        
        optgroup { background: var(--bg-sidebar); color: var(--teal); font-weight: 700; font-family: 'Rajdhani', sans-serif; }
        option { color: var(--text-primary); font-weight: 400; font-family: 'Exo 2', sans-serif; }

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
        <a href="#" class="nav-item active"><i class="fa-solid fa-pen"></i> Edit Player</a>
    </nav>
</aside>

<main class="main-wrapper">
    <header class="main-header">
        <div class="page-title">Roster Management</div>
    </header>

    <div class="content-body">
        <div class="panel">
            <div class="panel-head"><i class="fa-solid fa-user-ninja"></i> Edit Player: <?php echo htmlspecialchars($player['ign']); ?></div>
            <div class="panel-body">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Real Name (Max 20 chars)</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($player['name']); ?>" maxlength="20" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">In-Game Name (Max 15 chars)</label>
                        <input type="text" name="ign" class="form-control" value="<?php echo htmlspecialchars($player['ign']); ?>" maxlength="15" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Game</label>
                        <select name="game" class="form-control" required>
                            <option value="Mobile Legends" <?php if($player['game']=='Mobile Legends') echo 'selected'; ?>>Mobile Legends</option>
                            <option value="Wild Rift" <?php if($player['game']=='Wild Rift') echo 'selected'; ?>>Wild Rift</option>
                            <option value="Honor of Kings" <?php if($player['game']=='Honor of Kings') echo 'selected'; ?>>Honor of Kings</option>
                            <option value="Valorant" <?php if($player['game']=='Valorant') echo 'selected'; ?>>Valorant</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role / Position</label>
                        <select name="role" class="form-control" required>
                            <optgroup label="MOBA Roles">
                                <option value="Mid" <?php if($player['role']=='Mid') echo 'selected'; ?>>Mid</option>
                                <option value="Jungle" <?php if($player['role']=='Jungle') echo 'selected'; ?>>Jungle</option>
                                <option value="EXP" <?php if($player['role']=='EXP') echo 'selected'; ?>>EXP</option>
                                <option value="Gold" <?php if($player['role']=='Gold') echo 'selected'; ?>>Gold</option>
                                <option value="Roam" <?php if($player['role']=='Roam') echo 'selected'; ?>>Roam</option>
                            </optgroup>
                            <optgroup label="Valorant Roles">
                                <option value="Duelist" <?php if($player['role']=='Duelist') echo 'selected'; ?>>Duelist</option>
                                <option value="Initiator" <?php if($player['role']=='Initiator') echo 'selected'; ?>>Initiator</option>
                                <option value="Controller" <?php if($player['role']=='Controller') echo 'selected'; ?>>Controller</option>
                                <option value="Sentinel" <?php if($player['role']=='Sentinel') echo 'selected'; ?>>Sentinel</option>
                            </optgroup>
                            <optgroup label="Universal">
                                <option value="Flex" <?php if($player['role']=='Flex') echo 'selected'; ?>>Flex</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <button type="submit" name="edit_player" class="btn-submit">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</main>

</body>
</html>