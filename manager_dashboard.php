<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- LOGIC: Update Profile ---
if (isset($_POST['update_profile'])) {
    $new_first_name = mysqli_real_escape_string($conn, $_POST['new_first_name']);
    $new_last_name  = mysqli_real_escape_string($conn, $_POST['new_last_name']);
    $new_password   = $_POST['new_password'];

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET first_name='$new_first_name', last_name='$new_last_name', password='$hashed_password' WHERE id='$user_id'";
    } else {
        $update_sql = "UPDATE users SET first_name='$new_first_name', last_name='$new_last_name' WHERE id='$user_id'";
    }
    mysqli_query($conn, $update_sql);
    $_SESSION['first_name'] = $new_first_name;
    $_SESSION['last_name']  = $new_last_name;
    $_SESSION['system_message'] = "Profile updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: manager_dashboard.php");
    exit();
}

// --- LOGIC: Add New Player ---
if (isset($_POST['add_player'])) {
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $last_name  = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $name       = $first_name . ' ' . $last_name;

    $ign  = mysqli_real_escape_string($conn, trim($_POST['ign']));
    $game = mysqli_real_escape_string($conn, $_POST['game']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    $moba_roles = ['Mid', 'Jungle', 'EXP', 'Gold', 'Roam', 'Flex'];
    $valo_roles = ['Duelist', 'Initiator', 'Controller', 'Sentinel', 'Flex'];

    if ($game == 'Valorant' && !in_array($role, $valo_roles)) {
        $_SESSION['system_message'] = "Error: Invalid role for Valorant.";
        $_SESSION['msg_type'] = "error";
    } elseif ($game != 'Valorant' && !in_array($role, $moba_roles)) {
        $_SESSION['system_message'] = "Error: Invalid role for a MOBA game.";
        $_SESSION['msg_type'] = "error";
    } else {
        mysqli_query($conn, "INSERT INTO players (manager_id, name, ign, game, role, status) VALUES ('$user_id', '$name', '$ign', '$game', '$role', 'active')");
        $_SESSION['system_message'] = "Player $ign added to roster!";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: manager_dashboard.php?tab=tab-players");
    exit();
}

// --- LOGIC: Change Status ---
if (isset($_POST['change_status'])) {
    $item_id    = (int)$_POST['item_id'];
    $type       = $_POST['type'];
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);

    if ($type == 'player') {
        mysqli_query($conn, "UPDATE players SET status='$new_status' WHERE id='$item_id' AND manager_id='$user_id'");
        $_SESSION['system_message'] = "Player status updated to $new_status.";
    } elseif ($type == 'squad') {
        mysqli_query($conn, "UPDATE squads SET status='$new_status' WHERE id='$item_id' AND manager_id='$user_id'");
        $_SESSION['system_message'] = "Squad status updated to $new_status.";
    }
    $_SESSION['msg_type'] = "success";
    header("Location: manager_dashboard.php");
    exit();
}

// --- LOGIC: Create Squad ---
if (isset($_POST['create_squad'])) {
    $squad_name = mysqli_real_escape_string($conn, $_POST['squad_name']);
    $squad_game = mysqli_real_escape_string($conn, $_POST['squad_game']);
    $mains = $_POST['main_players'] ?? [];
    $subs  = $_POST['sub_players']  ?? [];

    if (count($mains) !== 5) {
        $_SESSION['system_message'] = "Error: You must select exactly 5 Main players.";
        $_SESSION['msg_type'] = "error";
    } else {
        // Handle Logo Upload
        $logo_filename = 'default_logo.png';
        if (isset($_FILES['squad_logo']) && $_FILES['squad_logo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            if (in_array($_FILES['squad_logo']['type'], $allowed_types)) {
                $ext = pathinfo($_FILES['squad_logo']['name'], PATHINFO_EXTENSION);
                $logo_filename = uniqid('squad_') . '.' . $ext;
                $upload_dir = 'uploads/squads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                move_uploaded_file($_FILES['squad_logo']['tmp_name'], $upload_dir . $logo_filename);
            }
        }

        mysqli_query($conn, "INSERT INTO squads (manager_id, name, game, logo, status) VALUES ('$user_id', '$squad_name', '$squad_game', '$logo_filename', 'active')");
        $new_squad_id = mysqli_insert_id($conn);
        foreach ($mains as $p_id) { $p_id = (int)$p_id; mysqli_query($conn, "INSERT INTO squad_members (squad_id, player_id, member_type) VALUES ('$new_squad_id', '$p_id', 'main')"); }
        foreach ($subs  as $p_id) { $p_id = (int)$p_id; mysqli_query($conn, "INSERT INTO squad_members (squad_id, player_id, member_type) VALUES ('$new_squad_id', '$p_id', 'sub')"); }
        $_SESSION['system_message'] = "Squad '$squad_name' created successfully!";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: manager_dashboard.php");
    exit();
}

// --- LOGIC: Cancel Registration ---
if (isset($_POST['cancel_registration'])) {
    $reg_id  = (int)$_POST['reg_id'];
    $check_q = mysqli_query($conn, "SELECT t.status FROM registrations r JOIN tournaments t ON r.tournament_id = t.id WHERE r.id='$reg_id' AND r.manager_id='$user_id'");
    if (mysqli_num_rows($check_q) > 0) {
        $t_status = mysqli_fetch_assoc($check_q)['status'];
        if ($t_status === 'pending') {
            mysqli_query($conn, "DELETE FROM registrations WHERE id='$reg_id'");
            $_SESSION['system_message'] = "Registration successfully cancelled.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['system_message'] = "Cannot cancel. The bracket has already been locked!";
            $_SESSION['msg_type'] = "error";
        }
    }
    header("Location: manager_dashboard.php");
    exit();
}

// --- FETCH DATA ---
$user_data        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name, last_name, created_at FROM users WHERE id='$user_id'"));
$active_players   = mysqli_query($conn, "SELECT * FROM players WHERE manager_id='$user_id' AND status='active' ORDER BY game, ign");
$inactive_players = mysqli_query($conn, "SELECT * FROM players WHERE manager_id='$user_id' AND status='inactive'");
$active_squads    = mysqli_query($conn, "SELECT * FROM squads WHERE manager_id='$user_id' AND status='active'");
$inactive_squads  = mysqli_query($conn, "SELECT * FROM squads WHERE manager_id='$user_id' AND status='inactive'");

$registrations_query = mysqli_query($conn, "
    SELECT r.id as reg_id, r.status as reg_status,
           t.name as t_name, t.status as t_status, t.game
    FROM registrations r
    JOIN tournaments t ON r.tournament_id = t.id
    WHERE r.manager_id='$user_id'
    ORDER BY r.id DESC
");

$warnings_query = mysqli_query($conn, "
    SELECT ta.action_type, ta.reason, ta.created_at,
           s.name as squad_name,
           t.name as tournament_name
    FROM team_actions ta
    JOIN squads s ON ta.team_id = s.id
    LEFT JOIN tournaments t ON ta.tournament_id = t.id
    WHERE s.manager_id = '$user_id'
    ORDER BY ta.created_at DESC
");

$total_players          = mysqli_num_rows($active_players);
$total_squads           = mysqli_num_rows($active_squads);
$total_inactive_players = mysqli_num_rows($inactive_players);
$total_inactive_squads  = mysqli_num_rows($inactive_squads);
$total_registrations    = mysqli_num_rows($registrations_query);
mysqli_data_seek($registrations_query, 0);

$players_by_game = [];
$pbg_q = mysqli_query($conn, "SELECT game, COUNT(*) as cnt FROM players WHERE manager_id='$user_id' AND status='active' GROUP BY game");
while ($r = mysqli_fetch_assoc($pbg_q)) { $players_by_game[] = $r; }

$players_by_role = [];
$pbr_q = mysqli_query($conn, "SELECT role, COUNT(*) as cnt FROM players WHERE manager_id='$user_id' AND status='active' GROUP BY role ORDER BY cnt DESC");
while ($r = mysqli_fetch_assoc($pbr_q)) { $players_by_role[] = $r; }

$reg_pending = 0; $reg_active = 0; $reg_completed = 0;
$reg_stats_q = mysqli_query($conn, "SELECT t.status, COUNT(*) as cnt FROM registrations r JOIN tournaments t ON r.tournament_id = t.id WHERE r.manager_id='$user_id' GROUP BY t.status");
while ($rs = mysqli_fetch_assoc($reg_stats_q)) {
    if ($rs['status'] === 'pending')   $reg_pending   = (int)$rs['cnt'];
    if ($rs['status'] === 'active')    $reg_active    = (int)$rs['cnt'];
    if ($rs['status'] === 'completed') $reg_completed = (int)$rs['cnt'];
}

$tournaments_won_q = mysqli_query($conn, "
    SELECT COUNT(*) as cnt
    FROM matches m
    JOIN tournaments t ON m.tournament_id = t.id
    JOIN registrations r ON m.winner_id = r.id
    WHERE r.manager_id='$user_id'
      AND t.status='completed'
      AND m.round_number = (
          SELECT MAX(round_number) FROM matches WHERE tournament_id = m.tournament_id
      )
");
$tournaments_won = (int)mysqli_fetch_assoc($tournaments_won_q)['cnt'];

$player_options = [];
while ($p = mysqli_fetch_assoc($active_players)) { $player_options[] = $p; }
mysqli_data_seek($active_players, 0);

$js_pbg_labels = json_encode(array_column($players_by_game, 'game'));
$js_pbg_data   = json_encode(array_column($players_by_game, 'cnt'));
$js_pbr_labels = json_encode(array_column($players_by_role, 'role'));
$js_pbr_data   = json_encode(array_column($players_by_role, 'cnt'));

$display_name    = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']);
$avatar_initials = strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard – DIFFCHECK</title>
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
            --red:           #ff4757;
            --green:         #00c2a0;
            --orange:        #f39c12;
            --purple:        #9b59b6;
            --topbar-h:      65px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; }
        body { background: var(--bg-base); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; display: flex; }

        .sidebar { width: 260px; background: var(--bg-sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; flex-shrink: 0; z-index: 100; }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .brand-title { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; letter-spacing: 1.5px; color: var(--text-primary); text-transform: uppercase; line-height: 1; }
        .brand-title span { color: var(--teal); }
        .brand-subtitle { font-size: 10px; color: var(--text-secondary); letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; }
        .role-badge { display: inline-block; background: rgba(0,194,203,0.15); color: var(--teal); border: 1px solid var(--teal); font-family: 'Rajdhani', sans-serif; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; margin-top: 12px; letter-spacing: 1px; }

        .sidebar-nav { flex: 1; padding: 20px 0; overflow-y: auto; }
        .nav-category { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin: 15px 20px 5px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: var(--text-secondary); text-decoration: none; font-weight: 500; font-size: 14px; transition: 0.2s; border-left: 3px solid transparent; cursor: pointer; }
        .nav-item i { font-size: 16px; width: 20px; text-align: center; }
        .nav-item:hover { color: var(--text-primary); background: rgba(255,255,255,0.02); }
        .nav-item.active { background: rgba(0,194,203,0.08); color: var(--teal); border-left-color: var(--teal); font-weight: 600; }

        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 12px; cursor: pointer; transition: 0.2s; }
        .sidebar-footer:hover { background: rgba(255,255,255,0.02); }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--teal); color: #000; display: flex; align-items: center; justify-content: center; font-family: 'Rajdhani', sans-serif; font-weight: 700; font-size: 16px; text-transform: uppercase; }
        .user-info { flex: 1; overflow: hidden; }
        .user-name { font-weight: 600; color: #fff; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }

        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg-base) url('pic/bg.png') center center / cover fixed; }
        .main-header { height: var(--topbar-h); padding: 0 30px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; background: rgba(13,17,23,0.8); backdrop-filter: blur(10px); }
        .page-title { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; letter-spacing: 1px; text-transform: uppercase; }
        .content-body { flex: 1; padding: 30px; overflow-y: auto; width: 100%; }

        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .panel-head { background: rgba(0,0,0,0.2); padding: 20px; border-bottom: 1px solid var(--border); font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: var(--teal); letter-spacing: 1px; text-transform: uppercase; display: flex; align-items: center; gap: 10px; }
        .panel-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-accent); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; border-radius: 6px; outline: none; transition: border-color .2s; appearance: none; -webkit-appearance: none; }
        .form-control:focus { border-color: var(--teal); }
        select[multiple] { height: 150px; }
        select[multiple] option { padding: 8px 10px; margin-bottom: 2px; border-radius: 4px; }
        select[multiple] option:checked { background: var(--teal-glow); color: var(--teal); }

        .btn-submit { display: inline-block; padding: 12px 24px; background: var(--teal); color: #000; border: none; border-radius: 6px; font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; transition: 0.3s; width: 100%; }
        .btn-submit:hover { background: var(--teal-dim); box-shadow: 0 0 15px var(--teal-glow); transform: translateY(-2px); }

        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 25px; font-weight: 600; font-size: 14px; text-align: center; border: 1px solid transparent; }
        .alert-success { background: rgba(0,194,160,0.1); color: var(--green); border-color: rgba(0,194,160,0.3); }
        .alert-error { background: rgba(0,194,203,0.1); color: var(--teal); border-color: rgba(0,194,203,0.3); }

        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: rgba(0,0,0,0.2); padding: 15px 20px; font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border); }
        td { padding: 15px 20px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
        tr:hover td { background: rgba(0,194,203,0.03); }

        .badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-active  { background: rgba(0,194,203,0.1); color: var(--teal); border: 1px solid rgba(0,194,203,0.3); }
        .badge-pending { background: rgba(243,156,18,0.1); color: var(--orange); border: 1px solid rgba(243,156,18,0.3); }
        .badge-locked  { background: rgba(0,194,203,0.08); color: var(--teal-dim); border: 1px solid rgba(0,194,203,0.2); }
        .badge-dq      { background: rgba(255,71,87,0.1); color: var(--red); border: 1px solid rgba(255,71,87,0.3); }

        .action-cell { display: flex; gap: 8px; align-items: center; }
        .btn-action { padding: 6px 12px; border: 1px solid var(--border-accent); border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 12px; font-family: 'Exo 2', sans-serif; background: transparent; color: var(--text-secondary); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-edit   { color: var(--teal); border-color: var(--teal); }
        .btn-edit:hover { background: rgba(0,194,203,0.1); }
        .btn-danger { border-color: rgba(0,194,203,0.5); color: var(--teal); }
        .btn-danger:hover { background: var(--teal); color: #000; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 16px; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--accent-color, var(--teal)); }
        .stat-icon { font-size: 28px; color: var(--accent-color, var(--teal)); opacity: 0.8; }
        .stat-info h3 { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: #fff; }
        .stat-info p  { color: var(--text-secondary); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }

        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .chart-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
        .chart-panel-head { font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .chart-wrap { position: relative; width: 100%; }
        .chart-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; font-size: 12px; color: var(--text-secondary); }
        .legend-item { display: inline-flex; align-items: center; gap: 5px; }
        .legend-swatch { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } .charts-grid { grid-template-columns: 1fr; } }

        .modal-overlay { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease; }
        .modal-box { background: var(--bg-card); border: 1px solid var(--border-accent); border-radius: 14px; padding: 40px 36px; width: 360px; max-width: 90vw; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.6); animation: slideUp 0.25s ease; }
        @keyframes slideUp { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
        .modal-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(0,194,203,0.1); border: 1px solid rgba(0,194,203,0.25); display: flex; align-items: center; justify-content: center; font-size: 26px; color: var(--teal); margin: 0 auto 20px; }
        .modal-title { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
        .modal-text { color: var(--text-secondary); font-size: 14px; line-height: 1.6; margin-bottom: 28px; }
        .modal-actions { display: flex; gap: 12px; }
        .btn-modal-cancel { flex: 1; padding: 12px; border: 1px solid var(--border-accent); border-radius: 6px; background: transparent; color: var(--text-secondary); font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; }
        .btn-modal-confirm { flex: 1; padding: 12px; border: none; border-radius: 6px; background: var(--teal); color: #000; font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="organizer_dashboard.php" style="display:block">
            <img src="pic/DiffcheckLogoNoBG.png" alt="DiffCheck Logo" style="width:130px; object-fit:contain;">
        </a>
        <div class="brand-subtitle">Tournament System</div>
        <div class="role-badge"><i class="fa-solid fa-sitemap"></i> ORGANIZER</div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-category">Management</div>
        <a class="nav-item" onclick="switchTab('tab-stats', this)"><i class="fa-solid fa-chart-line"></i> My Statistics</a>
        <a class="nav-item active" onclick="switchTab('tab-squads', this)"><i class="fa-solid fa-shield"></i> My Squads</a>
        <a class="nav-item" onclick="switchTab('tab-players', this)"><i class="fa-solid fa-user-ninja"></i> Player Roster</a>
        <a class="nav-item" onclick="switchTab('tab-registrations', this)"><i class="fa-solid fa-ticket"></i> Registered Tournaments</a>
        <a class="nav-item" onclick="switchTab('tab-warnings', this)"><i class="fa-solid fa-triangle-exclamation"></i> Warnings & DQ</a>
        <a class="nav-item" onclick="switchTab('tab-archive', this)"><i class="fa-solid fa-box-archive"></i> Inactive</a>

        <div class="nav-category">Tournaments</div>
        <a href="tournaments.php" class="nav-item"><i class="fa-solid fa-trophy"></i> Browse Tournaments</a>

        <div class="nav-category">System</div>
        <a onclick="document.getElementById('signout-modal').classList.add('active')" class="nav-item" style="color: var(--teal); cursor:pointer;"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </nav>

    <div class="sidebar-footer" onclick="switchTab('tab-profile', this)">
        <div class="user-avatar"><?php echo $avatar_initials; ?></div>
        <div class="user-info">
            <div class="user-name"><?php echo $display_name; ?></div>
            <div class="user-role">Edit Profile</div>
        </div>
    </div>
</aside>

<main class="main-wrapper">
    <header class="main-header">
        <div class="page-title" id="page-title-display">Squad Management</div>
    </header>

    <div class="content-body">

        <?php if (isset($_SESSION['system_message'])): ?>
            <div class="alert alert-<?php echo isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success'; ?>">
                <i class="fa-solid fa-circle-info"></i> <?php echo htmlspecialchars($_SESSION['system_message']); ?>
            </div>
            <?php unset($_SESSION['system_message']); unset($_SESSION['msg_type']); ?>
        <?php endif; ?>

        <div id="tab-stats" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card" style="--accent-color: var(--teal);">
                    <i class="fa-solid fa-user-ninja stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $total_players; ?></h3><p>Active Players</p></div>
                </div>
                <div class="stat-card" style="--accent-color: var(--green);">
                    <i class="fa-solid fa-shield stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $total_squads; ?></h3><p>Active Squads</p></div>
                </div>
                <div class="stat-card" style="--accent-color: var(--orange);">
                    <i class="fa-solid fa-ticket stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $total_registrations; ?></h3><p>Tournaments Joined</p></div>
                </div>
                <div class="stat-card" style="--accent-color: var(--text-secondary);">
                    <i class="fa-solid fa-box-archive stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $total_inactive_players; ?></h3><p>Inactive Players</p></div>
                </div>
                <div class="stat-card" style="--accent-color: #f1c40f; margin-bottom: 24px;">
                    <i class="fa-solid fa-trophy stat-icon"></i>
                    <div class="stat-info">
                        <h3><?php echo $tournaments_won; ?></h3>
                        <p>Tournaments Won</p>
                    </div>
                </div>              
            </div>

            <div class="charts-grid">
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-gamepad"></i> Players by Game</div>
                    <div class="chart-wrap" style="height: 220px;"><canvas id="chartPBG"></canvas></div>
                    <div class="chart-legend">
                        <?php $game_colors = ['#00c2cb','#9b59b6','#00c2a0','#f39c12']; $ci = 0; foreach ($players_by_game as $pg): ?>
                            <div class="legend-item"><span class="legend-swatch" style="background:<?php echo $game_colors[$ci % 4]; ?>;"></span><?php echo htmlspecialchars($pg['game']); ?> (<?php echo $pg['cnt']; ?>)</div>
                        <?php $ci++; endforeach; ?>
                    </div>
                </div>
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-users"></i> Players by Role</div>
                    <div class="chart-wrap" style="height: 220px;"><canvas id="chartPBR"></canvas></div>
                </div>
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-trophy"></i> Tournament Status</div>
                    <div class="chart-wrap" style="height: 220px;"><canvas id="chartRegStatus"></canvas></div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#f39c12;"></span>Pending (<?php echo $reg_pending; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2cb;"></span>Active (<?php echo $reg_active; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#6a8fa8;"></span>Completed (<?php echo $reg_completed; ?>)</div>
                    </div>
                </div>
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-shield-halved"></i> Active and Inactive</div>
                    <div class="chart-wrap" style="height: 220px;"><canvas id="chartSquadInactive"></canvas></div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2cb;"></span>Active Squads (<?php echo $total_squads; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#3d5468;"></span>Archived (<?php echo $total_inactive_squads; ?>)</div>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-squads" class="tab-content active">
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><i class="fa-solid fa-plus"></i> Forge a New Squad</div>
                    <div class="panel-body">
                        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateSquadForm()">
                            <div class="form-group">
                                <label class="form-label">Squad Name (Max 15 chars)</label>
                                <input type="text" name="squad_name" class="form-control" maxlength="15" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Squad Logo (Optional)</label>
                                <input type="file" name="squad_logo" class="form-control" accept="image/png, image/jpeg, image/jpg" style="padding: 9px 15px; background: rgba(0,0,0,0.3);">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Select Game</label>
                                <select name="squad_game" id="squad_game_select" class="form-control" onchange="filterPlayersByGame()" required>
                                    <option value="" disabled selected>Choose a game...</option>
                                    <option value="Mobile Legends">Mobile Legends</option>
                                    <option value="Valorant">Valorant</option>
                                    <option value="Wild Rift">Wild Rift</option>
                                    <option value="Honor of Kings">Honor of Kings</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Select 5 Main Players (Hold CTRL/CMD)</label>
                                <select name="main_players[]" id="main_players_select" class="form-control player-select" multiple required>
                                    <?php foreach ($player_options as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" data-game="<?php echo htmlspecialchars($p['game']); ?>">
                                            <?php echo htmlspecialchars($p['ign']) . " - " . $p['role']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color: var(--teal); font-size: 11px; margin-top: 5px; display: block;" id="squad_error_msg"></small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Select Subs (Optional)</label>
                                <select name="sub_players[]" class="form-control player-select" multiple>
                                    <?php foreach ($player_options as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" data-game="<?php echo htmlspecialchars($p['game']); ?>">
                                            <?php echo htmlspecialchars($p['ign']) . " - " . $p['role']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="create_squad" class="btn-submit">Create Squad</button>
                        </form>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><i class="fa-solid fa-shield-halved"></i> Active Squads</div>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Name</th><th>Game</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (mysqli_num_rows($active_squads) > 0): ?>
                                    <?php mysqli_data_seek($active_squads, 0); while ($sq = mysqli_fetch_assoc($active_squads)): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($sq['name']); ?></td>
                                        <td><?php echo htmlspecialchars($sq['game']); ?></td>
                                        <td>
                                            <div class="action-cell">
                                                <a href="edit_squad.php?id=<?php echo $sq['id']; ?>" class="btn-action btn-edit"><i class="fa-solid fa-pen"></i> Edit</a>
                                                <form method='POST' style='display:inline;'>
                                                    <input type='hidden' name='item_id' value='<?php echo $sq['id']; ?>'>
                                                    <input type='hidden' name='type' value='squad'>
                                                    <input type='hidden' name='new_status' value='inactive'>
                                                    <button type='submit' name='change_status' class="btn-action btn-danger"><i class="fa-solid fa-box-archive"></i> Inactive</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">No active squads found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-players" class="tab-content">
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><i class="fa-solid fa-user-plus"></i> Draft New Player</div>
                    <div class="panel-body">
                        <form method="POST">
                            <div class="form-group" style="display: flex; gap: 15px;">
                                <div style="flex: 1;">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" maxlength="20" required>
                                </div>
                                <div style="flex: 1;">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" maxlength="20" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">In-Game Name (Max 15 chars)</label>
                                <input type="text" name="ign" class="form-control" maxlength="15" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Game Focus</label>
                                <select name="game" id="add_player_game" class="form-control" onchange="filterRolesForAddPlayer()" required>
                                    <option value="" disabled selected>Select Game...</option>
                                    <option value="Mobile Legends">Mobile Legends</option>
                                    <option value="Wild Rift">Wild Rift</option>
                                    <option value="Honor of Kings">Honor of Kings</option>
                                    <option value="Valorant">Valorant</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role / Position</label>
                                <select name="role" id="add_player_role" class="form-control" required>
                                    <option value="" disabled selected>Select Role...</option>
                                    <option value="Mid"        data-gametype="moba">Mid</option>
                                    <option value="Jungle"     data-gametype="moba">Jungle</option>
                                    <option value="EXP"        data-gametype="moba">EXP</option>
                                    <option value="Gold"       data-gametype="moba">Gold</option>
                                    <option value="Roam"       data-gametype="moba">Roam</option>
                                    <option value="Duelist"    data-gametype="valo">Duelist</option>
                                    <option value="Initiator"  data-gametype="valo">Initiator</option>
                                    <option value="Controller" data-gametype="valo">Controller</option>
                                    <option value="Sentinel"   data-gametype="valo">Sentinel</option>
                                    <option value="Flex"       data-gametype="both">Flex (Any Role)</option>
                                </select>
                            </div>
                            <button type="submit" name="add_player" class="btn-submit">Add to Roster</button>
                        </form>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><i class="fa-solid fa-users"></i> Active Roster</div>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>IGN</th><th>Role</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (count($player_options) > 0): ?>
                                    <?php foreach ($player_options as $p): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($p['ign']); ?><br><span style="font-weight: 400; font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($p['game']); ?></span></td>
                                        <td><span class="badge badge-active"><?php echo htmlspecialchars($p['role']); ?></span></td>
                                        <td>
                                            <div class="action-cell">
                                                <a href="edit_player.php?id=<?php echo $p['id']; ?>" class="btn-action btn-edit"><i class="fa-solid fa-pen"></i> Edit</a>
                                                <form method='POST' style='display:inline;'>
                                                    <input type='hidden' name='item_id' value='<?php echo $p['id']; ?>'>
                                                    <input type='hidden' name='type' value='player'>
                                                    <input type='hidden' name='new_status' value='inactive'>
                                                    <button type='submit' name='change_status' class="btn-action btn-danger"><i class="fa-solid fa-ban"></i> Inactive</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">No players in roster.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-registrations" class="tab-content">
            <div class="panel" style="max-width: 100%;">
                <div class="panel-head"><i class="fa-solid fa-ticket"></i> Tournament Registrations</div>
                <div class="panel-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Tournament Name</th><th>Game</th><th>Tournament Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (mysqli_num_rows($registrations_query) > 0): ?>
                                    <?php while ($r = mysqli_fetch_assoc($registrations_query)): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($r['t_name']); ?></td>
                                        <td><?php echo htmlspecialchars($r['game']); ?></td>
                                        <td>
                                            <?php if ($r['t_status'] === 'pending'): ?>
                                                <span class="badge badge-pending">PENDING (OPEN)</span>
                                            <?php else: ?>
                                                <span class="badge badge-locked">BRACKET LOCKED</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($r['t_status'] === 'pending'): ?>
                                                <form method="POST" style="display:inline;" id="cancel-reg-form">
                                                    <input type="hidden" name="reg_id" value="<?php echo $r['reg_id']; ?>">
                                                    <button type="button" class="btn-action btn-danger" onclick="document.getElementById('cancel-reg-modal').classList.add('active')">
                                                        <i class="fa-solid fa-xmark"></i> Cancel Registration
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: var(--text-muted); font-style: italic;"><i class="fa-solid fa-lock"></i> Cannot back out</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 40px;">Your squads have not joined any tournaments yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-warnings" class="tab-content">
            <div class="panel" style="max-width: 100%;">
                <div class="panel-head"><i class="fa-solid fa-triangle-exclamation"></i> Warnings & Disqualifications</div>
                <div class="panel-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Date Issued</th><th>Squad</th><th>Tournament</th><th>Action Type</th><th>Reason</th></tr></thead>
                            <tbody>
                                <?php if (mysqli_num_rows($warnings_query) > 0): ?>
                                    <?php while ($w = mysqli_fetch_assoc($warnings_query)): ?>
                                    <tr>
                                        <td style="color: var(--text-secondary); font-size: 12px;"><?php echo date("M d, Y h:i A", strtotime($w['created_at'])); ?></td>
                                        <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($w['squad_name']); ?></td>
                                        <td><?php echo htmlspecialchars($w['tournament_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if (strtolower($w['action_type']) === 'warning'): ?>
                                                <span class="badge badge-pending">WARNING</span>
                                            <?php elseif (strtolower($w['action_type']) === 'disqualified'): ?>
                                                <span class="badge badge-dq" style="background: rgba(255,71,87,0.1); color: var(--red); border: 1px solid rgba(255,71,87,0.3);">DISQUALIFIED</span>
                                            <?php else: ?>
                                                <span class="badge badge-locked"><?php echo strtoupper(htmlspecialchars($w['action_type'])); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 250px; word-wrap: break-word;"><?php echo htmlspecialchars($w['reason']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px;">No warnings or disqualifications recorded. Good job!</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-archive" class="tab-content">
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><i class="fa-solid fa-box-archive"></i> Inactive Squads</div>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Name</th><th>Game</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (mysqli_num_rows($inactive_squads) > 0): ?>
                                    <?php while ($sq = mysqli_fetch_assoc($inactive_squads)): ?>
                                    <tr style="opacity: 0.6;">
                                        <td><?php echo htmlspecialchars($sq['name']); ?></td>
                                        <td><?php echo htmlspecialchars($sq['game']); ?></td>
                                        <td>
                                            <form method='POST'>
                                                <input type='hidden' name='item_id' value='<?php echo $sq['id']; ?>'>
                                                <input type='hidden' name='type' value='squad'>
                                                <input type='hidden' name='new_status' value='active'>
                                                <button type='submit' name='change_status' class="btn-action">Restore</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">Archive is empty.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><i class="fa-solid fa-user-xmark"></i> Inactive Players</div>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>IGN</th><th>Game</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (mysqli_num_rows($inactive_players) > 0): ?>
                                    <?php while ($p = mysqli_fetch_assoc($inactive_players)): ?>
                                    <tr style="opacity: 0.6;">
                                        <td><?php echo htmlspecialchars($p['ign']); ?></td>
                                        <td><?php echo htmlspecialchars($p['game']); ?></td>
                                        <td>
                                            <form method='POST'>
                                                <input type='hidden' name='item_id' value='<?php echo $p['id']; ?>'>
                                                <input type='hidden' name='type' value='player'>
                                                <input type='hidden' name='new_status' value='active'>
                                                <button type='submit' name='change_status' class="btn-action">Restore</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">No Inactive players.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-profile" class="tab-content">
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><i class="fa-solid fa-gear"></i> Account Settings</div>
                    <div class="panel-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" name="new_first_name" class="form-control" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="new_last_name" class="form-control" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" name="new_password" class="form-control" minlength="6" maxlength="15">
                            </div>
                            <button type="submit" name="update_profile" class="btn-submit">Update Profile</button>
                        </form>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head"><i class="fa-solid fa-chart-pie"></i> Manager Stats</div>
                    <div class="panel-body" style="text-align: center;">
                        <div style="font-size: 60px; color: var(--teal); margin-bottom: 10px;"><i class="fa-solid fa-crown"></i></div>
                        <h3 style="font-family: 'Rajdhani', sans-serif; font-size: 24px; color: #fff;"><?php echo $display_name; ?></h3>
                        <p style="color: var(--text-secondary); margin-bottom: 20px;">Joined <?php echo date("F j, Y", strtotime($user_data['created_at'])); ?></p>
                        <div style="display: flex; justify-content: center; gap: 30px; margin-top: 20px;">
                            <div>
                                <div style="font-size: 24px; font-weight: 700; color: #fff;"><?php echo $total_players; ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Active Players</div>
                            </div>
                            <div>
                                <div style="font-size: 24px; font-weight: 700; color: #fff;"><?php echo $total_squads; ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Active Squads</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
    Chart.defaults.color = '#6a8fa8';
    Chart.defaults.borderColor = '#1e2a38';

    new Chart(document.getElementById('chartPBG'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $js_pbg_labels; ?>,
            datasets: [{ data: <?php echo $js_pbg_data; ?>, backgroundColor: ['#00c2cb','#9b59b6','#00c2a0','#f39c12'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('chartPBR'), {
        type: 'bar',
        data: {
            labels: <?php echo $js_pbr_labels; ?>,
            datasets: [{ data: <?php echo $js_pbr_data; ?>, backgroundColor: 'rgba(0,194,203,0.7)', borderRadius: 5, borderSkipped: false }]
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a38' }, beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } } }
    });

    new Chart(document.getElementById('chartRegStatus'), {
        type: 'doughnut',
        data: {
            labels: ['Pending','Active','Completed'],
            datasets: [{ data: [<?php echo $reg_pending; ?>, <?php echo $reg_active; ?>, <?php echo $reg_completed; ?>], backgroundColor: ['#f39c12','#00c2cb','#6a8fa8'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('chartSquadInactive'), {
        type: 'bar',
        data: {
            labels: ['Active Squads','Archived Squads','Active Players','Inactive Players'],
            datasets: [{ data: [<?php echo $total_squads; ?>, <?php echo $total_inactive_squads; ?>, <?php echo $total_players; ?>, <?php echo $total_inactive_players; ?>], backgroundColor: ['rgba(0,194,203,0.75)','rgba(61,84,104,0.6)','rgba(0,194,160,0.75)','rgba(61,84,104,0.6)'], borderRadius: 5, borderSkipped: false }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 30 } }, y: { grid: { color: '#1e2a38' }, beginAtZero: true, ticks: { precision: 0 } } } }
    });

    function switchTab(tabId, element) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        if (element && element.classList.contains('nav-item')) element.classList.add('active');
        const titleMap = {
            'tab-stats':         'My Statistics',
            'tab-squads':        'Squad Management',
            'tab-players':       'Player Roster',
            'tab-registrations': 'Tournament Registrations',
            'tab-warnings':      'Warnings & Disqualifications',
            'tab-archive':       'Archive & Restoration',
            'tab-profile':       'Account Settings'
        };
        document.getElementById('page-title-display').innerText = titleMap[tabId];
    }

    function validateSquadForm() {
        var mains    = document.getElementById('main_players_select').selectedOptions;
        var errorMsg = document.getElementById('squad_error_msg');
        if (mains.length !== 5) {
            errorMsg.innerText = "CRITICAL: You must select exactly 5 Main Players. You currently have " + mains.length + " selected.";
            document.getElementById('main_players_select').style.border = "1px solid var(--teal)";
            setTimeout(() => document.getElementById('main_players_select').style.border = "1px solid var(--border-accent)", 1500);
            return false;
        }
        return true;
    }

    function filterPlayersByGame() {
        var selectedGame = document.getElementById('squad_game_select').value;
        document.querySelectorAll('.player-select').forEach(function(list) {
            list.selectedIndex = -1;
            list.querySelectorAll('option').forEach(function(option) {
                option.style.display = option.getAttribute('data-game') === selectedGame ? 'block' : 'none';
            });
        });
    }

    function filterRolesForAddPlayer() {
        var selectedGame = document.getElementById('add_player_game').value;
        var roleDropdown = document.getElementById('add_player_role');
        roleDropdown.querySelectorAll('option').forEach(function(option) {
            if (option.value === "") return;
            var gameType = option.getAttribute('data-gametype');
            if (selectedGame === 'Valorant') option.style.display = (gameType === 'valo' || gameType === 'both') ? 'block' : 'none';
            else if (selectedGame !== "")    option.style.display = (gameType === 'moba' || gameType === 'both') ? 'block' : 'none';
        });
        roleDropdown.selectedIndex = 0;
    }

    window.onload = function() {
        filterPlayersByGame();
        filterRolesForAddPlayer();
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam && document.getElementById(tabParam)) {
            const matchingNav = document.querySelector(`[onclick*="'${tabParam}'"]`);
            switchTab(tabParam, matchingNav);
        }
    };
</script>

<div id="signout-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
        <div class="modal-title">Sign Out</div>
        <div class="modal-text">
            Are you sure you want to sign out?<br>
            <span style="color: var(--text-muted); font-size: 12px;">Your session will be ended and you'll be redirected to the homepage.</span>
        </div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="document.getElementById('signout-modal').classList.remove('active')">
                <i class="fa-solid fa-xmark"></i> Cancel
            </button>
            <a href="logout.php" class="btn-modal-confirm">
                <i class="fa-solid fa-right-from-bracket"></i> Sign Out
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const alert = document.querySelector('.alert');
    if (alert) {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 3000);
    }
    if (document.querySelector('.alert-success')) {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
    }
});
</script>

<div id="cancel-reg-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="modal-title">Cancel Registration</div>
        <div class="modal-text">Are you absolutely sure you want to back out?<br>
            <span style="color:var(--text-muted); font-size:12px;">This action cannot be undone.</span>
        </div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="document.getElementById('cancel-reg-modal').classList.remove('active')">Go Back</button>
            <button class="btn-modal-confirm" onclick="document.getElementById('cancel-reg-form').submit()"><i class="fa-solid fa-xmark"></i> Confirm</button>
        </div>
    </div>
</div>

</body>
</html>