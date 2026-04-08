<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'organizer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- LOGIC: Update Profile ---
if (isset($_POST['update_profile'])) {
    $new_username = mysqli_real_escape_string($conn, $_POST['new_username']);
    $new_password = $_POST['new_password'];
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET username='$new_username', password='$hashed_password' WHERE id='$user_id'";
    } else {
        $update_sql = "UPDATE users SET username='$new_username' WHERE id='$user_id'";
    }
    mysqli_query($conn, $update_sql);
    $_SESSION['username'] = $new_username;
    $_SESSION['system_message'] = "Profile updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: organizer_dashboard.php"); exit();
}

// --- LOGIC: Create Tournament ---
if (isset($_POST['create_tournament'])) {
    $t_name      = mysqli_real_escape_string($conn, $_POST['t_name']);
    $t_game      = mysqli_real_escape_string($conn, $_POST['t_game']);
    $t_max_teams = (int)$_POST['t_max_teams'];
    $insert = mysqli_query($conn, "INSERT INTO tournaments (organizer_id, name, game, max_teams, status, is_deleted) VALUES ('$user_id', '$t_name', '$t_game', '$t_max_teams', 'pending', 0)");
    if ($insert) { $_SESSION['system_message'] = "Tournament '$t_name' successfully created!"; $_SESSION['msg_type'] = "success"; }
    else         { $_SESSION['system_message'] = "Database error. Could not create tournament."; $_SESSION['msg_type'] = "error"; }
    header("Location: organizer_dashboard.php"); exit();
}

// --- LOGIC: Archive Tournament ---
if (isset($_POST['archive_tournament'])) {
    $t_id    = (int)$_POST['tournament_id'];
    $check_q = mysqli_query($conn, "SELECT status FROM tournaments WHERE id='$t_id' AND organizer_id='$user_id'");
    if (mysqli_num_rows($check_q) > 0) {
        $t_status = mysqli_fetch_assoc($check_q)['status'];
        if ($t_status === 'pending') { mysqli_query($conn, "UPDATE tournaments SET is_deleted=1 WHERE id='$t_id'"); $_SESSION['system_message'] = "Tournament archived."; $_SESSION['msg_type'] = "success"; }
        else                         { $_SESSION['system_message'] = "Cannot archive an active or completed tournament!"; $_SESSION['msg_type'] = "error"; }
    }
    header("Location: organizer_dashboard.php"); exit();
}

// --- LOGIC: Restore Tournament ---
if (isset($_POST['restore_tournament'])) {
    $t_id = (int)$_POST['tournament_id'];
    mysqli_query($conn, "UPDATE tournaments SET is_deleted=0 WHERE id='$t_id' AND organizer_id='$user_id'");
    $_SESSION['system_message'] = "Tournament restored!"; $_SESSION['msg_type'] = "success";
    header("Location: organizer_dashboard.php"); exit();
}

// --- LOGIC: Approve / Reject Applications ---
if (isset($_POST['process_registration'])) {
    $reg_id = (int)$_POST['reg_id'];
    $action = mysqli_real_escape_string($conn, $_POST['action']);
    mysqli_query($conn, "UPDATE registrations SET status='$action' WHERE id='$reg_id'");
    $_SESSION['system_message'] = $action === 'approved' ? "Application APPROVED!" : "Application REJECTED.";
    $_SESSION['msg_type'] = "success";
    header("Location: organizer_dashboard.php"); exit();
}

// --- LOGIC: Start Tournament ---
if (isset($_POST['start_tournament'])) {
    $t_id = (int)$_POST['tournament_id'];
    mysqli_query($conn, "UPDATE tournaments SET status='active' WHERE id='$t_id' AND organizer_id='$user_id'");
    $_SESSION['system_message'] = "Bracket locked! Event is now ACTIVE."; $_SESSION['msg_type'] = "success";
    header("Location: organizer_dashboard.php"); exit();
}

// --- LOGIC: Complete Tournament ---
if (isset($_POST['complete_tournament'])) {
    $t_id = (int)$_POST['tournament_id'];
    mysqli_query($conn, "UPDATE tournaments SET status='completed' WHERE id='$t_id' AND organizer_id='$user_id'");
    $_SESSION['system_message'] = "Tournament marked as COMPLETED."; $_SESSION['msg_type'] = "success";
    header("Location: organizer_dashboard.php"); exit();
}

// --- FETCH DATA ---
$user_data        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username, created_at FROM users WHERE id='$user_id'"));
$tournaments_query= mysqli_query($conn, "SELECT * FROM tournaments WHERE organizer_id='$user_id' AND is_deleted=0 ORDER BY created_at DESC");
$archived_query   = mysqli_query($conn, "SELECT * FROM tournaments WHERE organizer_id='$user_id' AND is_deleted=1 ORDER BY created_at DESC");
$applications_query = mysqli_query($conn, "
    SELECT r.id as reg_id, r.status as reg_status,
           t.name as t_name, t.game,
           u.username as manager_name
    FROM registrations r
    JOIN tournaments t ON r.tournament_id = t.id
    JOIN users u ON r.manager_id = u.id
    WHERE t.organizer_id='$user_id' AND t.status='pending' AND t.is_deleted=0
    ORDER BY r.id DESC
");

$total_tournaments = 0; $active_tournaments = 0; $completed_tournaments = 0; $pending_tournaments = 0;
$my_tournaments = [];
while ($t = mysqli_fetch_assoc($tournaments_query)) {
    $my_tournaments[] = $t;
    $total_tournaments++;
    if ($t['status'] === 'active')    $active_tournaments++;
    if ($t['status'] === 'completed') $completed_tournaments++;
    if ($t['status'] === 'pending')   $pending_tournaments++;
}

// Chart data
$game_counts = [];
$games_q = mysqli_query($conn, "SELECT game, COUNT(*) as cnt FROM tournaments WHERE organizer_id='$user_id' AND is_deleted=0 GROUP BY game ORDER BY cnt DESC");
while ($g = mysqli_fetch_assoc($games_q)) { $game_counts[] = $g; }

// Monthly tournaments created (last 6 months)
$monthly_t = [];
for ($i = 5; $i >= 0; $i--) {
    $label = date('M', strtotime("-$i months"));
    $y = date('Y', strtotime("-$i months"));
    $m = date('m', strtotime("-$i months"));
    $cnt_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tournaments WHERE organizer_id='$user_id' AND YEAR(created_at)='$y' AND MONTH(created_at)='$m'");
    $monthly_t[] = ['label' => $label, 'count' => (int)mysqli_fetch_assoc($cnt_q)['cnt']];
}

// Total registrations across my tournaments
$total_regs    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM registrations r JOIN tournaments t ON r.tournament_id=t.id WHERE t.organizer_id='$user_id'"))['cnt'];
$approved_regs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM registrations r JOIN tournaments t ON r.tournament_id=t.id WHERE t.organizer_id='$user_id' AND r.status='approved'"))['cnt'];
$pending_regs  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM registrations r JOIN tournaments t ON r.tournament_id=t.id WHERE t.organizer_id='$user_id' AND r.status='pending'"))['cnt'];
$rejected_regs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM registrations r JOIN tournaments t ON r.tournament_id=t.id WHERE t.organizer_id='$user_id' AND r.status='rejected'"))['cnt'];

$js_game_labels    = json_encode(array_column($game_counts, 'game'));
$js_game_data      = json_encode(array_column($game_counts, 'cnt'));
$js_monthly_labels = json_encode(array_column($monthly_t, 'label'));
$js_monthly_data   = json_encode(array_column($monthly_t, 'count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard – DIFFCHECK</title>
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
            --purple:        #9b59b6;
            --orange:        #f39c12;
            --topbar-h:      65px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; }

        body {
            background: var(--bg-base); color: var(--text-primary);
            font-family: 'Exo 2', sans-serif; font-size: 14px; display: flex;
        }

        /* ── SIDEBAR ── */
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

        /* ── MAIN CONTENT ── */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg-base) url('pic/bg.png') center center / cover fixed; }
        .main-header { height: var(--topbar-h); padding: 0 30px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; background: rgba(13, 17, 23, 0.8); backdrop-filter: blur(10px); }
        .page-title { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; letter-spacing: 1px; text-transform: uppercase; }

        .content-body { flex: 1; padding: 30px; overflow-y: auto; max-width: 1400px; margin: 0 auto; width: 100%; }

        /* ── TABS ── */
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* ── ALERTS ── */
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 25px; font-weight: 600; font-size: 14px; text-align: center; border: 1px solid transparent; }
        .alert-success { background: rgba(0,194,160,0.1); color: var(--green); border-color: rgba(0,194,160,0.3); }
        .alert-error   { background: rgba(0,194,203,0.1); color: var(--teal); border-color: rgba(0,194,203,0.3); }

        /* ── STATS CARDS ── */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 16px; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--accent-color, var(--teal)); }
        .stat-icon { font-size: 28px; color: var(--accent-color, var(--teal)); opacity: 0.8; }
        .stat-info h3 { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: #fff; }
        .stat-info p  { color: var(--text-secondary); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }

        /* ── CHARTS ── */
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .charts-grid .chart-full { grid-column: 1 / -1; }
        .chart-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
        .chart-panel-head { font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .chart-wrap { position: relative; width: 100%; }
        .chart-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; font-size: 12px; color: var(--text-secondary); }
        .legend-item { display: inline-flex; align-items: center; gap: 5px; }
        .legend-swatch { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }

        /* ── PANELS & FORMS ── */
        .panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .panel-head { background: rgba(0,0,0,0.2); padding: 20px; border-bottom: 1px solid var(--border); font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: var(--teal); letter-spacing: 1px; text-transform: uppercase; display: flex; align-items: center; gap: 10px; }
        .panel-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-accent); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; border-radius: 6px; outline: none; transition: border-color .2s; }
        .form-control:focus { border-color: var(--teal); }

        .btn-submit { display: inline-block; padding: 14px 24px; background: var(--teal); color: #000; border: none; border-radius: 6px; font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; transition: 0.3s; width: 100%; }
        .btn-submit:hover { background: var(--teal-dim); transform: translateY(-2px); }

        /* ── TABLES ── */
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: rgba(0,0,0,0.2); padding: 15px 20px; font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border); }
        td { padding: 15px 20px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        .badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-pending   { background: rgba(243,156,18,0.1); color: var(--orange); border: 1px solid rgba(243,156,18,0.3); }
        .badge-active    { background: rgba(0,194,203,0.1); color: var(--teal); border: 1px solid rgba(0,194,203,0.3); }
        .badge-completed { background: rgba(90,106,120,0.1); color: var(--text-secondary); border: 1px solid rgba(90,106,120,0.3); }
        .badge-locked    { background: rgba(0,194,203,0.08); color: var(--teal-dim); border: 1px solid rgba(0,194,203,0.2); }

        .action-cell { display: flex; gap: 8px; align-items: center; }
        .btn-action { padding: 6px 12px; border: 1px solid var(--border-accent); border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 12px; font-family: 'Exo 2', sans-serif; background: transparent; color: var(--text-secondary); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-action:hover { border-color: var(--teal); color: var(--teal); }
        .btn-success { border-color: rgba(0,194,160,0.5); color: var(--green); }
        .btn-success:hover { background: var(--green); color: #000; border-color: var(--green); }
        .btn-danger  { border-color: rgba(0,194,203,0.5); color: var(--teal); }
        .btn-danger:hover  { background: var(--teal); color: #000; border-color: var(--teal); }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } .charts-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="brand-title">DIFF<span>CHECK</span></div>
        <div class="brand-subtitle">Tournament System</div>
        <div class="role-badge"><i class="fa-solid fa-sitemap"></i> ORGANIZER</div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-category">Event Management</div>
        <a class="nav-item active" onclick="switchTab('tab-overview', this)"><i class="fa-solid fa-chart-line"></i> Statistics</a>
        <a class="nav-item" onclick="switchTab('tab-create', this)"><i class="fa-solid fa-plus-circle"></i> Create Event</a>
        <a class="nav-item" onclick="switchTab('tab-manage', this)"><i class="fa-solid fa-list-check"></i> Manage Registrations</a>
        <a class="nav-item" onclick="switchTab('tab-archive', this)"><i class="fa-solid fa-box-archive"></i> Archived Events</a>

        <div class="nav-category">Public Platform</div>
        <a href="tournaments.php" class="nav-item"><i class="fa-solid fa-trophy"></i> Browse Events</a>

        <div class="nav-category">System</div>
        <a href="logout.php" class="nav-item" style="color: var(--teal);"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </nav>

    <div class="sidebar-footer" onclick="switchTab('tab-profile', this)">
        <div class="user-avatar"><?php echo substr($_SESSION['username'], 0, 2); ?></div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <div class="user-role">Edit Profile</div>
        </div>
    </div>
</aside>

<main class="main-wrapper">
    <header class="main-header">
        <div class="page-title" id="page-title-display">Statistics</div>
    </header>

    <div class="content-body">

        <?php if (isset($_SESSION['system_message'])): ?>
            <div class="alert alert-<?php echo isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success'; ?>">
                <i class="fa-solid fa-circle-info"></i> <?php echo htmlspecialchars($_SESSION['system_message']); ?>
            </div>
            <?php unset($_SESSION['system_message']); unset($_SESSION['msg_type']); ?>
        <?php endif; ?>

        <!-- ======== STATISTICS TAB ======== -->
        <div id="tab-overview" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card" style="--accent-color: var(--orange);">
                    <i class="fa-solid fa-calendar-day stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $total_tournaments; ?></h3><p>Total Events</p></div>
                </div>
                <div class="stat-card" style="--accent-color: var(--teal);">
                    <i class="fa-solid fa-play stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $active_tournaments; ?></h3><p>Active Events</p></div>
                </div>
                <div class="stat-card" style="--accent-color: var(--green);">
                    <i class="fa-solid fa-flag-checkered stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $completed_tournaments; ?></h3><p>Completed</p></div>
                </div>
                <div class="stat-card" style="--accent-color: var(--purple);">
                    <i class="fa-solid fa-users stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $total_regs; ?></h3><p>Total Registrations</p></div>
                </div>
            </div>

            <div class="charts-grid">
                <!-- Tournaments Created Monthly -->
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-calendar-plus"></i> Events Created (Monthly)</div>
                    <div class="chart-wrap" style="height: 220px;"><canvas id="chartMonthly"></canvas></div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2cb;"></span>Tournaments Created</div>
                    </div>
                </div>

                <!-- Tournament Status Breakdown -->
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-trophy"></i> Tournament Status</div>
                    <div class="chart-wrap" style="height: 220px;"><canvas id="chartStatus"></canvas></div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#f39c12;"></span>Pending (<?php echo $pending_tournaments; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2cb;"></span>Active (<?php echo $active_tournaments; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#6a8fa8;"></span>Completed (<?php echo $completed_tournaments; ?>)</div>
                    </div>
                </div>

                <!-- Tournaments by Game -->
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-gamepad"></i> Events by Game</div>
                    <div class="chart-wrap" style="height: 220px;"><canvas id="chartGames"></canvas></div>
                </div>

                <!-- Registration Status -->
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-inbox"></i> Registration Status</div>
                    <div class="chart-wrap" style="height: 220px;"><canvas id="chartRegs"></canvas></div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#f39c12;"></span>Pending (<?php echo $pending_regs; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2a0;"></span>Approved (<?php echo $approved_regs; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#3d5468;"></span>Rejected (<?php echo $rejected_regs; ?>)</div>
                    </div>
                </div>

                <!-- My Tournaments full-width table -->
                <div class="chart-panel chart-full" style="padding: 0;">
                    <div class="panel-head" style="border-radius: 12px 12px 0 0;"><i class="fa-solid fa-trophy"></i> Your Tournaments</div>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Tournament Name</th><th>Game</th><th>Max Teams</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (count($my_tournaments) > 0): ?>
                                    <?php foreach ($my_tournaments as $t): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($t['name']); ?></td>
                                        <td><?php echo htmlspecialchars($t['game']); ?></td>
                                        <td><?php echo htmlspecialchars($t['max_teams']); ?></td>
                                        <td>
                                            <?php if ($t['status'] === 'pending')   echo '<span class="badge badge-pending">PENDING</span>'; ?>
                                            <?php if ($t['status'] === 'active')    echo '<span class="badge badge-active">ACTIVE</span>'; ?>
                                            <?php if ($t['status'] === 'completed') echo '<span class="badge badge-completed">COMPLETED</span>'; ?>
                                        </td>
                                        <td>
                                            <div class="action-cell">
                                                <a href="view_tournament.php?id=<?php echo $t['id']; ?>" class="btn-action"><i class="fa-solid fa-eye"></i> View</a>
                                                <?php if ($t['status'] === 'pending'): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Archive this tournament?');">
                                                        <input type="hidden" name="tournament_id" value="<?php echo $t['id']; ?>">
                                                        <button type="submit" name="archive_tournament" class="btn-action btn-danger"><i class="fa-solid fa-trash-can"></i> Archive</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No tournaments yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======== CREATE EVENT TAB ======== -->
        <div id="tab-create" class="tab-content">
            <div class="panel" style="max-width: 600px; margin: 0 auto;">
                <div class="panel-head"><i class="fa-solid fa-plus-circle"></i> Host a New Event</div>
                <div class="panel-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Tournament Name</label>
                            <input type="text" name="t_name" class="form-control" placeholder="e.g. DiffCheck Summer Showdown" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Select Game</label>
                            <select name="t_game" class="form-control" required>
                                <option value="" disabled selected>Choose game...</option>
                                <option value="Mobile Legends">Mobile Legends</option>
                                <option value="Valorant">Valorant</option>
                                <option value="Wild Rift">Wild Rift</option>
                                <option value="Honor of Kings">Honor of Kings</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Teams / Bracket Size</label>
                            <select name="t_max_teams" class="form-control" required>
                                <option value="4">4 Teams</option>
                                <option value="8">8 Teams</option>
                                <option value="16">16 Teams</option>
                                <option value="32">32 Teams</option>
                            </select>
                        </div>
                        <button type="submit" name="create_tournament" class="btn-submit">Publish Tournament</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ======== MANAGE REGISTRATIONS TAB ======== -->
        <div id="tab-manage" class="tab-content">
            <div class="panel">
                <div class="panel-head"><i class="fa-solid fa-inbox"></i> Manager Applications</div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Tournament</th><th>Manager Name</th><th>Game</th><th>Status</th><th>Decision</th></tr></thead>
                        <tbody>
                            <?php if (mysqli_num_rows($applications_query) > 0): ?>
                                <?php while ($app = mysqli_fetch_assoc($applications_query)): ?>
                                <tr>
                                    <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($app['t_name']); ?></td>
                                    <td style="color: var(--teal); font-weight: 600;"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($app['manager_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['game']); ?></td>
                                    <td>
                                        <?php if ($app['reg_status'] === 'pending')  echo '<span class="badge badge-pending">PENDING</span>'; ?>
                                        <?php if ($app['reg_status'] === 'approved') echo '<span class="badge badge-active">APPROVED</span>'; ?>
                                        <?php if ($app['reg_status'] === 'rejected') echo '<span class="badge badge-locked">REJECTED</span>'; ?>
                                    </td>
                                    <td>
                                        <div class="action-cell">
                                            <?php if ($app['reg_status'] !== 'approved'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="reg_id" value="<?php echo $app['reg_id']; ?>">
                                                    <input type="hidden" name="action" value="approved">
                                                    <button type="submit" name="process_registration" class="btn-action btn-success"><i class="fa-solid fa-check"></i> Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($app['reg_status'] !== 'rejected'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="reg_id" value="<?php echo $app['reg_id']; ?>">
                                                    <input type="hidden" name="action" value="rejected">
                                                    <button type="submit" name="process_registration" class="btn-action btn-danger"><i class="fa-solid fa-xmark"></i> Reject</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No pending applications found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><i class="fa-solid fa-server"></i> Bracket Controls</div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Tournament Name</th><th>Game</th><th>Approved Squads</th><th>Status</th><th>Controls</th></tr></thead>
                        <tbody>
                            <?php if (count($my_tournaments) > 0): ?>
                                <?php foreach ($my_tournaments as $t):
                                    $t_id = $t['id'];
                                    $rc_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations WHERE tournament_id='$t_id' AND status='approved'");
                                    $reg_count = $rc_q ? (int)mysqli_fetch_assoc($rc_q)['count'] : 0;
                                ?>
                                <tr>
                                    <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($t['name']); ?></td>
                                    <td><?php echo htmlspecialchars($t['game']); ?></td>
                                    <td>
                                        <span style="font-weight: 700; color: <?php echo ($reg_count >= $t['max_teams']) ? 'var(--green)' : 'var(--orange)'; ?>;">
                                            <?php echo $reg_count; ?> / <?php echo $t['max_teams']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($t['status'] === 'pending')   echo '<span class="badge badge-pending">PENDING</span>'; ?>
                                        <?php if ($t['status'] === 'active')    echo '<span class="badge badge-active">ACTIVE</span>'; ?>
                                        <?php if ($t['status'] === 'completed') echo '<span class="badge badge-completed">COMPLETED</span>'; ?>
                                    </td>
                                    <td>
                                        <div class="action-cell">
                                            <?php if ($t['status'] === 'pending'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Lock the bracket? No more registrations will be accepted!');">
                                                    <input type="hidden" name="tournament_id" value="<?php echo $t['id']; ?>">
                                                    <button type="submit" name="start_tournament" class="btn-action btn-success"><i class="fa-solid fa-play"></i> Start / Lock Bracket</button>
                                                </form>
                                            <?php elseif ($t['status'] === 'active'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Mark tournament as fully completed?');">
                                                    <input type="hidden" name="tournament_id" value="<?php echo $t['id']; ?>">
                                                    <button type="submit" name="complete_tournament" class="btn-action"><i class="fa-solid fa-flag-checkered"></i> Mark Completed</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Event Closed</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No tournaments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======== ARCHIVE TAB ======== -->
        <div id="tab-archive" class="tab-content">
            <div class="panel">
                <div class="panel-head"><i class="fa-solid fa-box-archive"></i> Archived Tournaments</div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Tournament Name</th><th>Game</th><th>Max Teams</th><th>Date</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (mysqli_num_rows($archived_query) > 0): ?>
                                <?php while ($arc = mysqli_fetch_assoc($archived_query)): ?>
                                <tr style="opacity: 0.6;">
                                    <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($arc['name']); ?></td>
                                    <td><?php echo htmlspecialchars($arc['game']); ?></td>
                                    <td><?php echo htmlspecialchars($arc['max_teams']); ?></td>
                                    <td><?php echo date("M j, Y", strtotime($arc['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Restore this tournament?');">
                                            <input type="hidden" name="tournament_id" value="<?php echo $arc['id']; ?>">
                                            <button type="submit" name="restore_tournament" class="btn-action btn-success"><i class="fa-solid fa-trash-arrow-up"></i> Restore</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No archived tournaments.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======== PROFILE TAB ======== -->
        <div id="tab-profile" class="tab-content">
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><i class="fa-solid fa-gear"></i> Account Settings</div>
                    <div class="panel-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" name="new_username" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
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
                    <div class="panel-head"><i class="fa-solid fa-id-card"></i> Organizer Profile</div>
                    <div class="panel-body" style="text-align: center;">
                        <div style="font-size: 60px; color: var(--teal); margin-bottom: 10px;"><i class="fa-solid fa-sitemap"></i></div>
                        <h3 style="font-family: 'Rajdhani', sans-serif; font-size: 24px; color: #fff;">Event Organizer</h3>
                        <p style="color: var(--text-secondary); margin-bottom: 20px;">Joined <?php echo date("F j, Y", strtotime($user_data['created_at'])); ?></p>
                        <div style="display: flex; justify-content: center; gap: 30px; margin-top: 20px;">
                            <div>
                                <div style="font-size: 24px; font-weight: 700; color: #fff;"><?php echo $total_tournaments; ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Hosted Events</div>
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

    // Monthly events
    new Chart(document.getElementById('chartMonthly'), {
        type: 'bar',
        data: {
            labels: <?php echo $js_monthly_labels; ?>,
            datasets: [{ label: 'Events', data: <?php echo $js_monthly_data; ?>, backgroundColor: 'rgba(0,194,203,0.7)', borderRadius: 5, borderSkipped: false }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a38' } }, y: { grid: { color: '#1e2a38' }, beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // Tournament status doughnut
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: {
            labels: ['Pending','Active','Completed'],
            datasets: [{ data: [<?php echo $pending_tournaments; ?>, <?php echo $active_tournaments; ?>, <?php echo $completed_tournaments; ?>], backgroundColor: ['#f39c12','#00c2cb','#6a8fa8'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
    });

    // By game horizontal bar
    new Chart(document.getElementById('chartGames'), {
        type: 'bar',
        data: {
            labels: <?php echo $js_game_labels; ?>,
            datasets: [{ data: <?php echo $js_game_data; ?>, backgroundColor: ['rgba(0,194,203,0.75)','rgba(155,89,182,0.75)','rgba(0,194,160,0.75)','rgba(243,156,18,0.75)'], borderRadius: 5, borderSkipped: false }]
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a38' }, beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } } }
    });

    // Registration status doughnut
    new Chart(document.getElementById('chartRegs'), {
        type: 'doughnut',
        data: {
            labels: ['Pending','Approved','Rejected'],
            datasets: [{ data: [<?php echo $pending_regs; ?>, <?php echo $approved_regs; ?>, <?php echo $rejected_regs; ?>], backgroundColor: ['#f39c12','#00c2a0','#3d5468'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
    });

    function switchTab(tabId, element) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        if (element && element.classList.contains('nav-item')) element.classList.add('active');
        let titleMap = {
            'tab-overview': 'Statistics',
            'tab-create':   'Launch New Event',
            'tab-manage':   'Registration & Bracket Control',
            'tab-archive':  'Archived Events',
            'tab-profile':  'Account Settings'
        };
        document.getElementById('page-title-display').innerText = titleMap[tabId];
    }
</script>

</body>
</html>