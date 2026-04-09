<?php
session_start();
include('db.php');

// Protect the page: Only logged-in ADMINS can view this!
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = "";
$msg_type = "";

// ==========================================
// HANDLE USER DELETION (ULTIMATE FK FIX!)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $delete_id = (int)$_POST['user_id'];
    
    if ($delete_id === $_SESSION['user_id']) {
        $msg = "You cannot delete your own admin account!";
        $msg_type = "error";
    } else {
        // --- PART 1: IF THE USER IS A MANAGER ---
        $regs_query = mysqli_query($conn, "SELECT id FROM registrations WHERE manager_id='$delete_id'");
        if ($regs_query) {
            while ($reg = mysqli_fetch_assoc($regs_query)) {
                $r_id = $reg['id'];
                mysqli_query($conn, "UPDATE matches SET team1_id=NULL WHERE team1_id='$r_id'");
                mysqli_query($conn, "UPDATE matches SET team2_id=NULL WHERE team2_id='$r_id'");
                mysqli_query($conn, "UPDATE matches SET winner_id=NULL WHERE winner_id='$r_id'");
            }
        }
        mysqli_query($conn, "DELETE FROM squad_members WHERE squad_id IN (SELECT id FROM squads WHERE manager_id='$delete_id')");
        mysqli_query($conn, "DELETE FROM registrations WHERE manager_id='$delete_id'");
        mysqli_query($conn, "DELETE FROM squads WHERE manager_id='$delete_id'");

        // --- PART 2: IF THE USER IS AN ORGANIZER ---
        $tourneys_query = mysqli_query($conn, "SELECT id FROM tournaments WHERE organizer_id='$delete_id'");
        if ($tourneys_query) {
            while ($t = mysqli_fetch_assoc($tourneys_query)) {
                $t_id = $t['id'];
                mysqli_query($conn, "DELETE FROM matches WHERE tournament_id='$t_id'");
                mysqli_query($conn, "DELETE FROM registrations WHERE tournament_id='$t_id'");
            }
        }
        mysqli_query($conn, "DELETE FROM tournaments WHERE organizer_id='$delete_id'");

        // --- PART 3: DELETE THE USER ---
        $delete_query = mysqli_query($conn, "DELETE FROM users WHERE id='$delete_id'");
        
        if ($delete_query) {
            $msg = "User and all their associated data (Squads/Tournaments) were successfully deleted.";
            $msg_type = "success";
        } else {
            $msg = "Error deleting user from database.";
            $msg_type = "error";
        }
    }
}

// ==========================================
// FETCH DASHBOARD DATA
// ==========================================
$stat_users       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users"))['count'];
$stat_tournaments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tournaments WHERE is_deleted=0"))['count'];
$stat_squads      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM squads"))['count'];

// Stats for charts
$role_counts = [];
$roles_q = mysqli_query($conn, "SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
while ($r = mysqli_fetch_assoc($roles_q)) {
    $role_counts[strtolower($r['role'])] = (int)$r['cnt'];
}

$game_counts = [];
$games_q = mysqli_query($conn, "SELECT game, COUNT(*) as cnt FROM tournaments WHERE is_deleted=0 GROUP BY game ORDER BY cnt DESC");
while ($g = mysqli_fetch_assoc($games_q)) {
    $game_counts[] = $g;
}

$status_counts = ['pending' => 0, 'active' => 0, 'completed' => 0];
$status_q = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM tournaments WHERE is_deleted=0 GROUP BY status");
while ($s = mysqli_fetch_assoc($status_q)) {
    $status_counts[$s['status']] = (int)$s['cnt'];
}

// Monthly user registrations (last 6 months)
$monthly_users = [];
for ($i = 5; $i >= 0; $i--) {
    $label = date('M', strtotime("-$i months"));
    $y  = date('Y', strtotime("-$i months"));
    $m  = date('m', strtotime("-$i months"));
    $cnt_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE YEAR(created_at)='$y' AND MONTH(created_at)='$m'");
    $monthly_users[] = ['label' => $label, 'count' => (int)mysqli_fetch_assoc($cnt_q)['cnt']];
}

// Squads per game
$squad_games = [];
$sq_g_q = mysqli_query($conn, "SELECT game, COUNT(*) as cnt FROM squads GROUP BY game ORDER BY cnt DESC");
while ($sg = mysqli_fetch_assoc($sq_g_q)) {
    $squad_games[] = $sg;
}

$users_query = mysqli_query($conn, "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");

// Encode for JS
$js_monthly_labels = json_encode(array_column($monthly_users, 'label'));
$js_monthly_counts = json_encode(array_column($monthly_users, 'count'));
$js_role_labels    = json_encode(array_keys($role_counts));
$js_role_data      = json_encode(array_values($role_counts));
$js_game_labels    = json_encode(array_column($game_counts, 'game'));
$js_game_data      = json_encode(array_column($game_counts, 'cnt'));
$js_status_data    = json_encode(array_values($status_counts));
$js_sq_labels      = json_encode(array_column($squad_games, 'game'));
$js_sq_data        = json_encode(array_column($squad_games, 'cnt'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – DIFFCHECK</title>
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
        .role-badge { display: inline-block; background: rgba(0,194,203,0.12); color: var(--teal); border: 1px solid var(--teal); font-family: 'Rajdhani', sans-serif; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; margin-top: 12px; letter-spacing: 1px; }

        .sidebar-nav { flex: 1; padding: 20px 0; overflow-y: auto; }
        .nav-category { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin: 15px 20px 5px; }

        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: var(--text-secondary); text-decoration: none; font-weight: 500; font-size: 14px; transition: 0.2s; border-left: 3px solid transparent; cursor: pointer; }
        .nav-item i { font-size: 16px; width: 20px; text-align: center; }
        .nav-item:hover { color: var(--text-primary); background: rgba(255,255,255,0.02); }
        .nav-item.active { background: rgba(0,194,203,0.08); color: var(--teal); border-left-color: var(--teal); font-weight: 600; }

        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--teal); color: #000; display: flex; align-items: center; justify-content: center; font-family: 'Rajdhani', sans-serif; font-weight: 700; font-size: 16px; text-transform: uppercase; }
        .user-info { flex: 1; overflow: hidden; }
        .user-name { font-weight: 600; color: #fff; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }

        /* ── MAIN CONTENT ── */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg-base) url('pic/bg.png') center center / cover fixed; }
        .main-header { height: var(--topbar-h); padding: 0 30px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; background: rgba(13, 17, 23, 0.8); backdrop-filter: blur(10px); }
        .page-title { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; letter-spacing: 1px; text-transform: uppercase; }

        .content-body { flex: 1; padding: 30px; overflow-y: auto; width: 100%; }

        /* ── TABS ── */
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* ── ALERTS ── */
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 25px; font-weight: 600; font-size: 14px; text-align: center; border: 1px solid transparent; }
        .alert-success { background: rgba(0,194,160,0.1); color: var(--green); border-color: rgba(0,194,160,0.3); }
        .alert-error { background: rgba(0,194,203,0.1); color: var(--teal); border-color: rgba(0,194,203,0.3); }

        /* ── STATS CARDS ── */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 25px; display: flex; align-items: center; gap: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--accent-color, var(--teal)); }
        .stat-icon { font-size: 36px; color: var(--accent-color, var(--teal)); opacity: 0.8; }
        .stat-info h3 { font-family: 'Rajdhani', sans-serif; font-size: 32px; font-weight: 700; color: #fff; margin-bottom: 2px; }
        .stat-info p { color: var(--text-secondary); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

        /* ── CHARTS GRID ── */
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .charts-grid .chart-full { grid-column: 1 / -1; }
        .chart-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 22px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .chart-panel-head { font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; color: var(--teal); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .chart-panel-head i { font-size: 15px; }
        .chart-wrap { position: relative; width: 100%; }
        .chart-legend { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 12px; font-size: 12px; color: var(--text-secondary); }
        .legend-item { display: inline-flex; align-items: center; gap: 5px; }
        .legend-swatch { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }

        /* ── PANEL & TABLES ── */
        .panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .panel-head { background: rgba(0,0,0,0.2); padding: 20px; border-bottom: 1px solid var(--border); font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: var(--teal); letter-spacing: 1px; text-transform: uppercase; display: flex; align-items: center; gap: 10px; }

        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: rgba(0,0,0,0.2); padding: 15px 20px; font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 15px 20px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        .badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-admin { background: rgba(0,194,203,0.12); color: var(--teal); border: 1px solid rgba(0,194,203,0.4); }
        .badge-organizer { background: rgba(155, 89, 182, 0.1); color: var(--purple); border: 1px solid rgba(155, 89, 182, 0.3); }
        .badge-manager { background: rgba(0, 194, 160, 0.1); color: var(--green); border: 1px solid rgba(0, 194, 160, 0.3); }

        .btn-delete { background: transparent; color: var(--teal); border: 1px solid var(--teal); padding: 6px 12px; border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 12px; font-family: 'Exo 2', sans-serif; display: inline-flex; align-items: center; gap: 5px; }
        .btn-delete:hover { background: var(--teal); color: #000; }

        /* added modal style */
        .modal-overlay {
        display: none; position: fixed; inset: 0; z-index: 9999;
        background: rgba(0,0,0,0.65); backdrop-filter: blur(4px);
        align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease; }
        .modal-box {
        background: var(--bg-card); border: 1px solid var(--border-accent);
        border-radius: 14px; padding: 40px 36px; width: 360px; max-width: 90vw;
        text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        animation: slideUp 0.25s ease;
        }
        @keyframes slideUp { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
        .modal-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: rgba(0,194,203,0.1); border: 1px solid rgba(0,194,203,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; color: var(--teal); margin: 0 auto 20px;
        }
        .modal-title {
            font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700;
            color: #fff; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;
        }
        .modal-text { color: var(--text-secondary); font-size: 14px; line-height: 1.6; margin-bottom: 28px; }
        .modal-actions { display: flex; gap: 12px; }
        .btn-modal-cancel {
            flex: 1; padding: 12px; border: 1px solid var(--border-accent); border-radius: 6px;
            background: transparent; color: var(--text-secondary); font-family: 'Rajdhani', sans-serif;
            font-size: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer;
        }
        .btn-modal-confirm {
            flex: 1; padding: 12px; border: none; border-radius: 6px;
            background: var(--teal); color: #000; font-family: 'Rajdhani', sans-serif;
            font-size: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
            cursor: pointer; text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center; gap: 8px;
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="brand-title">DIFF<span>CHECK</span></div>
        <div class="brand-subtitle">System Administration</div>
        <div class="role-badge"><i class="fa-solid fa-shield-halved"></i> SUPER ADMIN</div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-category">Core</div>
        <a class="nav-item active" onclick="switchTab('tab-overview', this)"><i class="fa-solid fa-chart-line"></i> Statistics</a>
        <a class="nav-item" onclick="switchTab('tab-users', this)"><i class="fa-solid fa-users-gear"></i> User Management</a>

        <div class="nav-category">Public Platform</div>
        <a href="tournaments.php" class="nav-item"><i class="fa-solid fa-trophy"></i> Browse Tournaments</a>
        <a href="index.php" class="nav-item"><i class="fa-solid fa-globe"></i> View Homepage</a>

        <div class="nav-category">System</div>
        <a onclick="document.getElementById('signout-modal').classList.add('active')" class="nav-item" style="color: var(--teal); cursor:pointer;"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-avatar"><?php echo substr($_SESSION['username'], 0, 2); ?></div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <div class="user-role">System Administrator</div>
        </div>
    </div>
</aside>

<main class="main-wrapper">
    <header class="main-header">
        <div class="page-title" id="page-title-display">Platform Statistics</div>
    </header>

    <div class="content-body">

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <!-- ======== STATISTICS TAB ======== -->
        <div id="tab-overview" class="tab-content active">

            <div class="stats-grid">
                <div class="stat-card" style="--accent-color: var(--teal);">
                    <i class="fa-solid fa-users stat-icon"></i>
                    <div class="stat-info">
                        <h3><?php echo $stat_users; ?></h3>
                        <p>Total Registered Users</p>
                    </div>
                </div>
                <div class="stat-card" style="--accent-color: var(--purple);">
                    <i class="fa-solid fa-trophy stat-icon"></i>
                    <div class="stat-info">
                        <h3><?php echo $stat_tournaments; ?></h3>
                        <p>Active Tournaments</p>
                    </div>
                </div>
                <div class="stat-card" style="--accent-color: var(--green);">
                    <i class="fa-solid fa-shield-cat stat-icon"></i>
                    <div class="stat-info">
                        <h3><?php echo $stat_squads; ?></h3>
                        <p>Formed Squads</p>
                    </div>
                </div>
            </div>

            <div class="charts-grid">

                <!-- User Registrations Over Time -->
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-user-plus"></i> User Registrations (Monthly)</div>
                    <div class="chart-wrap" style="height: 220px;">
                        <canvas id="chartMonthlyUsers"></canvas>
                    </div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2cb;"></span>New Users</div>
                    </div>
                </div>

                <!-- Users by Role -->
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-users"></i> Users by Role</div>
                    <div class="chart-wrap" style="height: 220px;">
                        <canvas id="chartRoles"></canvas>
                    </div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2cb;"></span>Manager <?php echo $role_counts['manager'] ?? 0; ?></div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#9b59b6;"></span>Organizer <?php echo $role_counts['organizer'] ?? 0; ?></div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2a0;"></span>Admin <?php echo $role_counts['admin'] ?? 0; ?></div>
                    </div>
                </div>

                <!-- Tournaments by Game -->
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-gamepad"></i> Tournaments by Game</div>
                    <div class="chart-wrap" style="height: 220px;">
                        <canvas id="chartGames"></canvas>
                    </div>
                </div>

                <!-- Tournament Status Breakdown -->
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-trophy"></i> Tournament Status</div>
                    <div class="chart-wrap" style="height: 220px;">
                        <canvas id="chartStatus"></canvas>
                    </div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#f39c12;"></span>Pending <?php echo $status_counts['pending']; ?></div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2cb;"></span>Active <?php echo $status_counts['active']; ?></div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#6a8fa8;"></span>Completed <?php echo $status_counts['completed']; ?></div>
                    </div>
                </div>

                <!-- Squads per Game -->
                <div class="chart-panel chart-full">
                    <div class="chart-panel-head"><i class="fa-solid fa-shield-halved"></i> Squads per Game</div>
                    <div class="chart-wrap" style="height: 200px;">
                        <canvas id="chartSquads"></canvas>
                    </div>
                </div>

            </div>
        </div>

        <!-- ======== USER MANAGEMENT TAB ======== -->
        <div id="tab-users" class="tab-content">
            <div class="panel">
                <div class="panel-head"><i class="fa-solid fa-database"></i> Database: Users Table</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Danger Zone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($users_query) > 0): ?>
                                <?php while ($u = mysqli_fetch_assoc($users_query)):
                                    $role = strtolower($u['role']);
                                    $badge_class = 'badge-manager';
                                    if ($role == 'admin') $badge_class = 'badge-admin';
                                    if ($role == 'organizer') $badge_class = 'badge-organizer';
                                ?>
                                    <tr>
                                        <td style="color: var(--text-muted); font-weight: 600;">#<?php echo $u['id']; ?></td>
                                        <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('CRITICAL WARNING: Are you sure you want to permanently delete <?php echo htmlspecialchars($u['username']); ?>? This will wipe ALL their squads, players, and tournament data!');">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                                    <button type="submit" name="delete_user" class="btn-delete"><i class="fa-solid fa-trash-can"></i> Erase User</button>
                                                <?php else: ?>
                                                    <span style="font-size: 11px; color: var(--text-muted); font-style: italic; font-weight: 600;">ACTIVE ADMIN</span>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">No users found in database.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
    Chart.defaults.color = '#6a8fa8';
    Chart.defaults.borderColor = '#1e2a38';

    // Monthly User Registrations
    new Chart(document.getElementById('chartMonthlyUsers'), {
        type: 'bar',
        data: {
            labels: <?php echo $js_monthly_labels; ?>,
            datasets: [{
                label: 'New Users',
                data: <?php echo $js_monthly_counts; ?>,
                backgroundColor: 'rgba(0,194,203,0.7)',
                borderRadius: 5,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#1e2a38' } },
                y: { grid: { color: '#1e2a38' }, beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    // Users by Role (Doughnut)
    new Chart(document.getElementById('chartRoles'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $js_role_labels; ?>,
            datasets: [{
                data: <?php echo $js_role_data; ?>,
                backgroundColor: ['#00c2cb','#9b59b6','#00c2a0','#f39c12'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { display: false } }
        }
    });

    // Tournaments by Game (Horizontal Bar)
    new Chart(document.getElementById('chartGames'), {
        type: 'bar',
        data: {
            labels: <?php echo $js_game_labels; ?>,
            datasets: [{
                data: <?php echo $js_game_data; ?>,
                backgroundColor: ['rgba(0,194,203,0.75)','rgba(155,89,182,0.75)','rgba(0,194,160,0.75)','rgba(243,156,18,0.75)'],
                borderRadius: 5,
                borderSkipped: false
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#1e2a38' }, beginAtZero: true, ticks: { precision: 0 } },
                y: { grid: { display: false } }
            }
        }
    });

    // Tournament Status (Doughnut)
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: {
            labels: ['Pending','Active','Completed'],
            datasets: [{
                data: <?php echo $js_status_data; ?>,
                backgroundColor: ['#f39c12','#00c2cb','#6a8fa8'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '60%',
            plugins: { legend: { display: false } }
        }
    });

    // Squads per Game (Bar)
    new Chart(document.getElementById('chartSquads'), {
        type: 'bar',
        data: {
            labels: <?php echo $js_sq_labels; ?>,
            datasets: [{
                label: 'Squads',
                data: <?php echo $js_sq_data; ?>,
                backgroundColor: ['rgba(0,194,203,0.7)','rgba(155,89,182,0.7)','rgba(0,194,160,0.7)','rgba(243,156,18,0.7)'],
                borderRadius: 5,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { autoSkip: false } },
                y: { grid: { color: '#1e2a38' }, beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    function switchTab(tabId, element) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');
        if (element && element.classList.contains('nav-item')) {
            element.classList.add('active');
        }

        let titleMap = {
            'tab-overview': 'Platform Statistics',
            'tab-users': 'User Management'
        };
        document.getElementById('page-title-display').innerText = titleMap[tabId];
    }
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
</body>
</html>