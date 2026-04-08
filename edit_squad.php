<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager' || !isset($_GET['id'])) {
    header("Location: manager_dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$squad_id = (int)$_GET['id'];

// 1. Get Squad Info
$squad_query = mysqli_query($conn, "SELECT * FROM squads WHERE id='$squad_id' AND manager_id='$user_id'");
if (mysqli_num_rows($squad_query) == 0) {
    echo "Squad not found."; exit();
}
$squad = mysqli_fetch_assoc($squad_query);
$squad_game = $squad['game'];

// 2. Handle Update
if (isset($_POST['edit_squad'])) {
    $name = mysqli_real_escape_string($conn, $_POST['squad_name']);
    $mains = $_POST['main_players'] ?? [];
    $subs = $_POST['sub_players'] ?? [];

    if (count($mains) !== 5) {
        $error_message = "ERROR: YOU MUST SELECT EXACTLY 5 MAIN PLAYERS.";
    } else {
        mysqli_query($conn, "UPDATE squads SET name='$name' WHERE id='$squad_id'");
        mysqli_query($conn, "DELETE FROM squad_members WHERE squad_id='$squad_id'");
        
        foreach ($mains as $p_id) {
            $p_id = (int)$p_id;
            mysqli_query($conn, "INSERT INTO squad_members (squad_id, player_id, member_type) VALUES ('$squad_id', '$p_id', 'main')");
        }
        foreach ($subs as $p_id) {
            $p_id = (int)$p_id;
            mysqli_query($conn, "INSERT INTO squad_members (squad_id, player_id, member_type) VALUES ('$squad_id', '$p_id', 'sub')");
        }
        
        $_SESSION['system_message'] = "Squad '" . htmlspecialchars($name) . "' updated successfully!";
        $_SESSION['msg_type'] = "success";
        header("Location: manager_dashboard.php");
        exit();
    }
}

// 3. Get all active players that match THIS squad's game
$players_query = mysqli_query($conn, "SELECT * FROM players WHERE manager_id='$user_id' AND status='active' AND game='$squad_game'");
$game_players = [];
while ($p = mysqli_fetch_assoc($players_query)) {
    $game_players[] = $p;
}

// 4. Get current members
$current_mains = [];
$current_subs = [];
$members_query = mysqli_query($conn, "SELECT player_id, member_type FROM squad_members WHERE squad_id='$squad_id'");
while ($m = mysqli_fetch_assoc($members_query)) {
    if ($m['member_type'] == 'main') {
        $current_mains[] = $m['player_id'];
    } else {
        $current_subs[] = $m['player_id'];
    }
}
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
            --bg-sidebar:    #0f1318;
            --bg-card:       #111820; /* Matches the dark panel in screenshot */
            --border:        #1e2a38;
            --border-accent: #1b3a4b;
            --teal:          #00c2cb;
            --teal-dim:      #009da5;
            --teal-glow:     rgba(0,194,203,0.15);
            --text-primary:  #ffffff;
            --text-secondary:#8ca8bc;
            --text-muted:    #3d5468;
            --red:           #ff4757;
            --topbar-h:      70px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; }

        body { background: var(--bg-base); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; display: flex; }

        /* ── SIDEBAR ── */
        .sidebar { width: 250px; background: var(--bg-sidebar); border-right: 1px solid rgba(255,255,255,0.03); display: flex; flex-direction: column; flex-shrink: 0; z-index: 100; }
        .sidebar-header { padding: 30px 20px; }
        .brand-title { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; letter-spacing: 1.5px; color: var(--text-primary); text-transform: uppercase; line-height: 1; }
        .brand-title span { color: var(--teal); }
        .brand-subtitle { font-size: 10px; color: var(--text-secondary); letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; }

        .sidebar-nav { flex: 1; padding: 20px 0; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: var(--text-secondary); text-decoration: none; font-weight: 500; font-size: 15px; transition: 0.2s; border-left: 4px solid transparent; }
        .nav-item i { width: 20px; text-align: center; font-size: 16px; }
        .nav-item:hover { color: var(--text-primary); background: rgba(255,255,255,0.02); }
        .nav-item.active { background: rgba(0, 194, 203, 0.05); color: var(--teal); border-left-color: var(--teal); font-weight: 600; }

        /* ── MAIN CONTENT ── */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; background: var(--bg-base) url('pic/bg.png') center center / cover fixed; }
        
        .main-header { height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; background: rgba(13, 17, 23, 0.95); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.03); }
        .page-title { font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: #fff; letter-spacing: 1.5px; text-transform: uppercase; }
        
        .content-body { flex: 1; padding: 40px; overflow-y: auto; display: flex; justify-content: center; align-items: flex-start; }
        
        /* ── FORM PANEL (Seamless Design) ── */
        .panel { background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 650px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); padding: 40px; border: 1px solid rgba(255,255,255,0.05); }

        .panel-header-custom { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .ph-left { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
        .ph-left i { color: var(--teal); font-size: 18px; }
        
        .ph-right { background: rgba(0, 194, 203, 0.1); color: var(--teal); border: 1px solid var(--teal); padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }

        .form-group { margin-bottom: 25px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px; }
        
        .form-control { width: 100%; padding: 14px 16px; background: rgba(0,0,0,0.4); border: 1px solid var(--border-accent); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; border-radius: 6px; outline: none; transition: border-color .2s; }
        .form-control:focus { border-color: var(--teal); box-shadow: 0 0 0 2px var(--teal-glow); }
        
        select[multiple] { height: 160px; padding: 8px; }
        select[multiple] option { padding: 12px 14px; margin-bottom: 4px; border-radius: 4px; font-weight: 500; border: 1px solid transparent; }
        select[multiple] option:hover { background: rgba(255,255,255,0.05); }
        select[multiple] option:checked { background: rgba(0, 194, 203, 0.15) !important; color: var(--teal) !important; border: 1px solid rgba(0, 194, 203, 0.3); }

        .btn-submit { width: 100%; padding: 16px; background: var(--teal); color: #000; border: none; border-radius: 6px; font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: 0.3s; margin-top: 15px; letter-spacing: 1.5px; }
        .btn-submit:hover { background: var(--teal-dim); box-shadow: 0 5px 15px var(--teal-glow); transform: translateY(-2px); }

        .alert-error { background: rgba(255, 71, 87, 0.1); color: var(--red); border-left: 4px solid var(--red); padding: 15px 20px; border-radius: 6px; margin-bottom: 25px; font-weight: 600; font-size: 13px; }
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
        <a href="#" class="nav-item active"><i class="fa-solid fa-shield"></i> Edit Squad</a>
    </nav>
</aside>

<main class="main-wrapper">
    <header class="main-header">
        <div class="page-title">SQUAD MANAGEMENT</div>
    </header>

    <div class="content-body">
        <div class="panel">
            
            <div class="panel-header-custom">
                <div class="ph-left">
                    <i class="fa-solid fa-shield-halved"></i>
                    EDIT: <?php echo htmlspecialchars($squad['name']); ?>
                </div>
                <div class="ph-right">
                    <?php echo htmlspecialchars($squad_game); ?>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST" onsubmit="return validateEditForm()">
                <div class="form-group">
                    <label class="form-label">SQUAD NAME (MAX 15 CHARS)</label>
                    <input type="text" name="squad_name" class="form-control" value="<?php echo htmlspecialchars($squad['name']); ?>" maxlength="15" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">MAIN ROSTER (REQUIRES EXACTLY 5. HOLD CTRL/CMD TO SELECT)</label>
                    <select name="main_players[]" id="main_players_select" class="form-control" multiple required>
                        <?php foreach ($game_players as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php if (in_array($p['id'], $current_mains)) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($p['ign']) . " - " . $p['role']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--red); font-size: 11px; margin-top: 8px; display: block;" id="squad_error_msg"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">SUBSTITUTES (OPTIONAL)</label>
                    <select name="sub_players[]" class="form-control" multiple>
                        <?php foreach ($game_players as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php if (in_array($p['id'], $current_subs)) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($p['ign']) . " - " . $p['role']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="edit_squad" class="btn-submit">SAVE ROSTER CHANGES</button>
            </form>
        </div>
    </div>
</main>

<script>
    function validateEditForm() {
        var mains = document.getElementById('main_players_select').selectedOptions;
        var errorMsg = document.getElementById('squad_error_msg');
        
        if (mains.length !== 5) {
            errorMsg.innerText = "CRITICAL: You must select exactly 5 Main Players. You currently have " + mains.length + " selected.";
            document.getElementById('main_players_select').style.border = "1px solid var(--red)";
            setTimeout(() => document.getElementById('main_players_select').style.border = "1px solid var(--border-accent)", 1500);
            return false; 
        }
        return true; 
    }
</script>

</body>
</html>