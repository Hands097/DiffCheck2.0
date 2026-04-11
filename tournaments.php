<?php
session_start();
include('db.php');

$where_clauses = ["is_deleted=0"];

if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where_clauses[] = "name LIKE '%$search%'";
}

if (!empty($_GET['games'])) {
    $game_filters = [];
    foreach ($_GET['games'] as $game) {
        $clean_game = mysqli_real_escape_string($conn, $game);
        $game_filters[] = "'$clean_game'";
    }
    $where_clauses[] = "game IN (" . implode(',', $game_filters) . ")";
}

if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where_clauses[] = "status='$status'";
}

$order_by = "DESC";
if (isset($_GET['sort']) && $_GET['sort'] === 'oldest') {
    $order_by = "ASC";
}

$sql = "SELECT * FROM tournaments WHERE " . implode(' AND ', $where_clauses) . " ORDER BY created_at $order_by";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Tournaments – DIFFCHECK</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Exo+2:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bg-deep:       #0a0d10;
            --bg-panel:      #0f1318;
            --bg-card:       #131820;
            --bg-card-hover: #161d27;
            --border:        #1e2a38;
            --border-accent: #1b3a4b;
            --teal:          #00c2cb;
            --teal-dim:      #009da5;
            --teal-glow:     rgba(0,194,203,0.18);
            --teal-glow-sm:  rgba(0,194,203,0.08);
            --text-primary:  #d8e8f0;
            --text-secondary:#6a8fa8;
            --text-muted:    #3d5468;
            --status-open:   #00c2a0;
            --status-active: #4fa3e0;
            --status-done:   #5a6a78;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg-deep) url('pic/bg.png') center center / cover fixed;
            color: var(--text-primary);
            font-family: 'Exo 2', sans-serif;
            font-size: 14px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: var(--bg-panel);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-image {
            height: 36px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .topbar-back {
            display: flex; align-items: center; gap: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 12px; font-weight: 500;
            letter-spacing: 0.5px;
            transition: color .15s;
        }
        .topbar-back:hover { color: var(--teal); }

        .topbar-right {
            display: flex; align-items: center; gap: 16px;
        }

        /* ── AVATAR DROPDOWN ── */
        .user-menu { position: relative; }

        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--teal);
            border: 2px solid var(--teal-dim);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px; font-weight: 700;
            color: #000;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
            user-select: none;
            flex-shrink: 0;
        }
        .user-avatar:hover {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px var(--teal-glow);
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 200px;
            background: var(--bg-panel);
            border: 1px solid var(--border-accent);
            border-radius: 10px;
            overflow: hidden;
            opacity: 0;
            pointer-events: none;
            transform: translateY(-6px);
            transition: opacity .18s ease, transform .18s ease;
            z-index: 100;
        }
        .user-menu.open .user-dropdown {
            opacity: 1;
            pointer-events: all;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 14px 16px 12px;
            border-bottom: 1px solid var(--border);
        }
        .dropdown-name {
            font-family: 'Rajdhani', sans-serif;
            font-size: 14px; font-weight: 700;
            color: var(--text-primary);
            letter-spacing: 0.5px;
        }
        .dropdown-role {
            font-size: 11px;
            color: var(--teal);
            margin-top: 2px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .dropdown-items { padding: 6px 0; }

        .dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
            transition: background .15s, color .15s;
        }
        .dropdown-item:hover { background: var(--teal-glow-sm); color: var(--text-primary); }
        .dropdown-item .di-icon { width: 16px; text-align: center; font-size: 14px; flex-shrink: 0; }

        .dropdown-divider { height: 1px; background: var(--border); margin: 4px 0; }

        .dropdown-item.logout { color: #e05555; }
        .dropdown-item.logout:hover { background: rgba(224,85,85,0.08); color: #e05555; }

        /* ── GUEST BUTTONS ── */
        .guest-actions { display: flex; align-items: center; gap: 8px; }

        .btn-login {
            padding: 7px 18px;
            border-radius: 6px;
            border: 1px solid var(--border-accent);
            background: transparent;
            color: var(--text-secondary);
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px; font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: border-color .15s, color .15s;
        }
        .btn-login:hover { border-color: var(--teal); color: var(--teal); }

        .btn-register {
            padding: 7px 18px;
            border-radius: 6px;
            border: none;
            background: var(--teal);
            color: #000;
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px; font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-register:hover { background: var(--teal-dim); }

        /* ── PAGE ── */
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 24px;
            flex: 1;
        }

        .page-heading {
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-heading h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: 22px; font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .page-heading h1 span { color: var(--teal); }

        .count-badge {
            background: var(--teal-glow-sm);
            border: 1px solid var(--border-accent);
            color: var(--teal);
            font-size: 11px; font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
        }

        /* ── FILTER PANEL ── */
        .filter-panel {
            background: var(--bg-panel);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 28px;
        }

        .filter-header {
            padding: 13px 24px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 8px;
            border-radius: 10px 10px 0 0;
        }
        .filter-header-icon {
            width: 18px; height: 18px;
            display: flex; align-items: center; justify-content: center;
            color: var(--teal);
            font-size: 14px;
        }
        .filter-header-text {
            font-family: 'Rajdhani', sans-serif;
            font-size: 12px; font-weight: 700;
            letter-spacing: 2px;
            color: var(--teal);
            text-transform: uppercase;
        }

        .filter-body {
            padding: 20px 24px;
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr 1fr auto;
            gap: 24px;
            align-items: start;
            overflow: visible;
        }

        .filter-divider { display: none; }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: relative;
        }

        .filter-label {
            font-size: 10px; font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--text-muted);
            display: flex; align-items: center; gap: 6px;
        }
        .filter-label::before {
            content: '';
            display: inline-block;
            width: 4px; height: 4px;
            background: var(--teal);
            border-radius: 50%;
        }

        .filter-input,
        .filter-select {
            background: var(--bg-card);
            border: 1px solid var(--border-accent);
            border-radius: 7px;
            color: var(--text-primary);
            font-family: 'Exo 2', sans-serif;
            font-size: 13px;
            padding: 9px 13px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            width: 100%;
            -webkit-appearance: none;
            appearance: none;
        }
        .filter-input::placeholder { color: var(--text-muted); }
        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px var(--teal-glow);
        }

        .select-wrapper {
            position: relative;
        }
        .select-wrapper::after {
            content: '';
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            width: 0; height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 5px solid var(--text-muted);
            pointer-events: none;
        }
        .select-wrapper .filter-select { padding-right: 30px; }
        .filter-select option { background: #131820; }

        .custom-check {
            width: 14px; height: 14px;
            border: 1px solid var(--border-accent);
            border-radius: 3px;
            background: var(--bg-card);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: border-color .15s, background .15s;
        }

        /* ── GAMES DROPDOWN ── */
        .games-dropdown { position: relative; z-index: 100; }

        .games-trigger {
            background: var(--bg-card);
            border: 1px solid var(--border-accent);
            border-radius: 7px;
            color: var(--text-primary);
            font-family: 'Exo 2', sans-serif;
            font-size: 13px;
            padding: 9px 13px;
            display: flex; align-items: center; justify-content: space-between;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
            user-select: none;
        }
        .games-trigger:hover { border-color: var(--teal); }
        .games-dropdown.open .games-trigger {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px var(--teal-glow);
            border-radius: 7px 7px 0 0;
        }

        .games-arrow {
            font-size: 11px;
            color: var(--text-muted);
            transition: transform .2s;
            flex-shrink: 0;
        }
        .games-dropdown.open .games-arrow { transform: rotate(180deg); }

        .games-menu {
            position: absolute;
            top: 100%; left: 0; right: 0;
            background: var(--bg-card);
            border: 1px solid var(--teal);
            border-top: none;
            border-radius: 0 0 7px 7px;
            z-index: 9999;
            display: none;
            padding: 6px 0;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }
        .games-dropdown.open .games-menu { display: block; }

        .games-option {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 13px;
            color: var(--text-secondary);
            font-size: 13px;
            cursor: pointer;
            transition: background .15s, color .15s;
            user-select: none;
        }
        .games-option:hover { background: var(--teal-glow-sm); color: var(--text-primary); }
        .games-option input[type="checkbox"] { display: none; }
        .games-option input[type="checkbox"]:checked ~ span:last-child { color: var(--text-primary); }
        .games-option input[type="checkbox"]:checked + .custom-check {
            background: var(--teal);
            border-color: var(--teal);
        }
        .games-option input[type="checkbox"]:checked + .custom-check::after {
            content: '';
            width: 8px; height: 5px;
            border-left: 2px solid #000;
            border-bottom: 2px solid #000;
            transform: rotate(-45deg) translateY(-1px);
            display: block;
        }

        .filter-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            justify-content: center;
            padding-left: 24px;
        }

        .btn-apply {
            background: var(--teal);
            color: #000;
            border: none;
            border-radius: 7px;
            padding: 10px 26px;
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px; font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .15s, opacity .15s;
            white-space: nowrap;
        }
        .btn-apply:hover { background: var(--teal-dim); }

        .btn-clear {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 8px 26px;
            font-family: 'Exo 2', sans-serif;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            display: block;
            transition: color .15s, border-color .15s;
            white-space: nowrap;
        }
        .btn-clear:hover { color: var(--text-primary); border-color: var(--text-secondary); }

        /* ── CARDS ── */
        .tournament-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }

        .t-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 18px 20px;
            transition: border-color .2s, background .2s, box-shadow .2s;
            position: relative;
            overflow: hidden;
        }
        .t-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 3px; height: 100%;
            transition: background .2s;
            background: var(--border);
        }
        .t-card:hover { background: var(--bg-card-hover); border-color: var(--border-accent); box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .t-card:hover::before          { background: var(--teal); }
        .t-card.status-pending::before  { background: var(--status-open); }
        .t-card.status-active::before   { background: var(--status-active); }
        .t-card.status-completed::before{ background: var(--status-done); }

        .t-card-top {
            display: flex; align-items: flex-start; justify-content: space-between;
            margin-bottom: 12px;
        }

        .t-name {
            font-family: 'Rajdhani', sans-serif;
            font-size: 16px; font-weight: 700;
            color: var(--text-primary);
            letter-spacing: 0.5px;
            line-height: 1.2;
        }

        .status-chip {
            font-size: 10px; font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 20px;
            flex-shrink: 0;
            margin-left: 10px;
        }
        .chip-pending   { background: rgba(0,194,160,0.12);  color: var(--status-open);   border: 1px solid rgba(0,194,160,0.3); }
        .chip-active    { background: rgba(79,163,224,0.12); color: var(--status-active); border: 1px solid rgba(79,163,224,0.3); }
        .chip-completed { background: rgba(90,106,120,0.12); color: var(--status-done);   border: 1px solid rgba(90,106,120,0.3); }

        .t-meta {
            display: flex; flex-wrap: wrap; gap: 14px;
            margin-bottom: 12px;
        }
        .t-meta-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--text-secondary); }
        .t-meta-item .lbl { color: var(--text-muted); font-size: 11px; }

        .t-date { color: var(--text-muted); font-size: 11px; margin-bottom: 14px; }

        .btn-view {
            display: flex; align-items: center; justify-content: center; gap: 7px;
            background: transparent;
            border: 1px solid var(--teal);
            color: var(--teal);
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px; font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 8px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: background .15s, box-shadow .15s;
            width: 100%;
        }
        .btn-view:hover { background: var(--teal-glow); box-shadow: 0 0 10px var(--teal-glow); }
        .btn-view.register { background: var(--teal); color: #000; }
        .btn-view.register:hover { background: var(--teal-dim); box-shadow: 0 0 14px var(--teal-glow); }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state .icon { font-size: 40px; margin-bottom: 14px; }

        @media (max-width: 768px) {
            .filter-body { grid-template-columns: 1fr; }
            .topbar { padding: 0 16px; }
            .page { padding: 20px 16px; }
        }
        
        /* ── BRACKET PREVIEW BOX ── */
        .bracket-preview {
            height: 90px;
            background: var(--bg-panel);
            border: 1px dashed var(--border);
            border-radius: 6px;
            margin-bottom: 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--text-muted);
            transition: all .2s;
        }
        .bracket-preview svg { 
            width: 28px; 
            height: 28px; 
            opacity: 0.5; 
        }
        .bracket-preview .bp-text { 
            font-size: 11px; 
            font-weight: 600; 
            letter-spacing: 1px; 
            text-transform: uppercase; 
        }

        /* State when bracket is generated */
        .bracket-preview.generated {
            background: rgba(0, 194, 203, 0.04);
            border: 1px solid var(--border-accent);
            color: var(--teal);
        }
        .bracket-preview.generated svg { 
            opacity: 1; 
            stroke: var(--teal); 
        }

        /* modal css */
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

<header class="topbar">
<div class="topbar-left">
    <a href="
        <?php
        $r = strtolower($_SESSION['role'] ?? '');
        if ($r === 'admin') echo 'admin_dashboard.php';
        elseif ($r === 'manager') echo 'manager_dashboard.php';
        elseif ($r === 'organizer') echo 'organizer_dashboard.php';
        else echo 'index.php';
        ?>
    ">
        <img src="pic/DiffcheckLogoNoBG.png" alt="DiffCheck Logo" class="logo-image">
    </a>
</div>

    <div class="topbar-right">

        <?php if (isset($_SESSION['first_name'])): ?>
        <div class="user-menu" id="userMenu">
            <div class="user-avatar" id="avatarBtn">
                <?php echo strtoupper(substr($_SESSION['first_name'], 0, 2)); ?>
            </div>
            <div class="user-dropdown">
                <div class="dropdown-header">
<div class="dropdown-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="dropdown-role">
                        <?php 
                            $role = strtolower($_SESSION['role'] ?? '');
                            if ($role == 'admin') echo 'System Admin';
                            elseif ($role == 'organizer') echo 'Tournament Organizer';
                            else echo 'Squad Manager';
                        ?>
                    </div>
                </div>
                <div class="dropdown-items">
                    <?php 
                        // DYNAMIC DASHBOARD ROUTING
                        $dash_link = 'manager_dashboard.php';
                        if ($role == 'admin') $dash_link = 'admin_dashboard.php';
                        if ($role == 'organizer') $dash_link = 'organizer_dashboard.php';
                    ?>
                    <a href="<?php echo $dash_link; ?>" class="dropdown-item">
                        <span class="di-icon"><i class="fa-solid fa-chart-line"></i></span> Dashboard
                    </a>
                    <div class="dropdown-divider"></div>
                    <a onclick="document.getElementById('signout-modal').classList.add('active')" class="dropdown-item logout" style="cursor:pointer;">
                        <span class="di-icon">⏻</span> Sign Out
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="guest-actions">
            <a href="login.php" class="btn-login">Sign In</a>
            <a href="register.php" class="btn-register">Register</a>
        </div>
        <?php endif; ?>
    </div>
</header>

<div class="page">

    <div class="page-heading">
        <h1>Browse <span>Tournaments</span></h1>
        <div class="count-badge"><?php echo mysqli_num_rows($result); ?> found</div>
    </div>

    <div class="filter-panel">
        <div class="filter-header">
            <span class="filter-header-icon"><i class="fa-solid fa-sliders"></i></span>
            <span class="filter-header-text">Filters</span>
        </div>
        <form method="GET">
            <div class="filter-body">

                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input class="filter-input" type="text" name="search" placeholder="Tournament name..."
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>

                <div class="filter-divider"></div>

                <div class="filter-group">
                    <label class="filter-label">Games</label>
                    <div class="games-dropdown" id="gamesDropdown">
                        <div class="games-trigger" id="gamesTrigger">
                            <span id="gamesLabel">All Games</span>
                            <span class="games-arrow">▾</span>
                        </div>
                        <div class="games-menu" id="gamesMenu">
                            <?php
                            $all_games = ['Mobile Legends', 'Wild Rift', 'Honor of Kings', 'Valorant'];
                            foreach ($all_games as $g):
                                $checked = (isset($_GET['games']) && in_array($g, $_GET['games'])) ? 'checked' : '';
                            ?>
                            <label class="games-option">
                                <input type="checkbox" name="games[]" value="<?php echo $g; ?>" <?php echo $checked; ?> class="game-check">
                                <span class="custom-check"></span>
                                <?php echo $g; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="filter-divider"></div>

                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <div class="select-wrapper">
                        <select name="status" class="filter-select">
                            <option value="all">All Statuses</option>
                            <option value="pending"   <?php if(isset($_GET['status']) && $_GET['status']=='pending')   echo 'selected'; ?>>Pending (Open)</option>
                            <option value="active"    <?php if(isset($_GET['status']) && $_GET['status']=='active')    echo 'selected'; ?>>Active (Ongoing)</option>
                            <option value="completed" <?php if(isset($_GET['status']) && $_GET['status']=='completed') echo 'selected'; ?>>Completed</option>
                        </select>
                    </div>
                </div>

                <div class="filter-divider"></div>

                <div class="filter-group">
                    <label class="filter-label">Sort By</label>
                    <div class="select-wrapper">
                        <select name="sort" class="filter-select">
                            <option value="newest" <?php if(isset($_GET['sort']) && $_GET['sort']=='newest') echo 'selected'; ?>>Newest First</option>
                            <option value="oldest" <?php if(isset($_GET['sort']) && $_GET['sort']=='oldest') echo 'selected'; ?>>Oldest First</option>
                        </select>
                    </div>
                </div>

                <div class="filter-divider"></div>

                <div class="filter-actions">
                    <button type="submit" class="btn-apply">Apply</button>
                    <a href="tournaments.php" class="btn-clear">Clear</a>
                </div>

            </div>
        </form>
    </div>

    <div class="tournament-grid">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($t = mysqli_fetch_assoc($result)): ?>
                
                <div class="t-card status-<?php echo $t['status']; ?>">
                    <div class="t-card-top">
                        <div class="t-name"><?php echo htmlspecialchars($t['name']); ?></div>
                        <span class="status-chip chip-<?php echo $t['status']; ?>">
                            <?php echo strtoupper($t['status']); ?>
                        </span>
                    </div>

                    <?php if ($t['status'] === 'pending'): ?>
                        <div class="bracket-preview">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke-dasharray="4 4"></rect></svg>
                            <span class="bp-text">Awaiting Bracket</span>
                        </div>
                    <?php else: ?>
                        <div class="bracket-preview generated" style="padding-top: 10px; height: 100px;">
                            <svg viewBox="0 0 100 50" style="width: 120px; height: 50px; margin-bottom: 6px;">
                                <path d="M 22 5 h 9 v 5 h 9" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 22 15 h 9 v -5" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 22 35 h 9 v 5 h 9" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 22 45 h 9 v -5" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 60 10 h 9 v 15 h 9" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 60 40 h 9 v -15" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                
                                <rect x="2" y="2" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                <rect x="2" y="12" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                <rect x="2" y="32" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                <rect x="2" y="42" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                
                                <rect x="40" y="7" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                <rect x="40" y="37" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                
                                <rect x="78" y="22" width="20" height="6" rx="1" fill="var(--teal-glow-sm)" stroke="var(--teal)" stroke-width="1"/>
                            </svg>
                            <span class="bp-text">BRACKET LIVE</span>
                        </div>
                    <?php endif; ?>

                    <div class="t-meta">
                        <div class="t-meta-item"><span class="lbl">Game</span><?php echo htmlspecialchars($t['game']); ?></div>
                        <div class="t-meta-item"><span class="lbl">Max Teams</span><?php echo $t['max_teams']; ?></div>
                    </div>

                    <div class="t-date">Created: <?php echo date("M j, Y", strtotime($t['created_at'])); ?></div>

                    <form action="view_tournament.php" method="GET">
                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                        <?php if ($t['status'] === 'pending'): ?>
                            <button type="submit" class="btn-view register">✚ View &amp; Register</button>
                        <?php else: ?>
                            <button type="submit" class="btn-view">◈ View Bracket</button>
                        <?php endif; ?>
                    </form>
                </div>
                
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon"><i class="fa-solid fa-trophy"></i></div>
                <p>No tournaments found matching those filters.</p>
            </div>
        <?php endif; ?>
    </div>

</div> <script>
    // Avatar dropdown
    const menu = document.getElementById('userMenu');
    const btn  = document.getElementById('avatarBtn');
    if (btn) {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('open');
        });
        document.addEventListener('click', () => menu.classList.remove('open'));
    }

    // Games dropdown
    const gamesDrop    = document.getElementById('gamesDropdown');
    const gamesTrigger = document.getElementById('gamesTrigger');
    const gamesLabel   = document.getElementById('gamesLabel');
    const gameChecks   = document.querySelectorAll('.game-check');

    function updateGamesLabel() {
        const selected = [...gameChecks].filter(c => c.checked).map(c => c.value);
        if (selected.length === 0) {
            gamesLabel.textContent = 'All Games';
        } else if (selected.length === 1) {
            gamesLabel.textContent = selected[0];
        } else {
            gamesLabel.textContent = selected.length + ' Games Selected';
        }
    }

    // Initialize label on page load
    if (gamesDrop) {
        updateGamesLabel();

        // 1. Open/Close the dropdown when clicking the trigger
        gamesTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            gamesDrop.classList.toggle('open');
        });

        // 2. Prevent clicks inside the options from closing the menu
        document.querySelectorAll('.games-option').forEach(opt => {
            opt.addEventListener('click', (e) => {
                e.stopPropagation(); 
            });
        });

        // 3. Let the browser check the box natively, just update the label
        gameChecks.forEach(chk => {
            chk.addEventListener('change', updateGamesLabel);
        });

        // 4. Close dropdown if clicking anywhere else on the page
        document.addEventListener('click', (e) => {
            if (!gamesDrop.contains(e.target)) {
                gamesDrop.classList.remove('open');
            }
        });
    }
</script>

<footer style="text-align: center; padding: 24px; border-top: 1px solid #1e2a38; color: #3d5468; font-size: 13px; font-weight: 500; background: #0f1318; margin-top: auto; flex-shrink: 0;">
    &copy; 2026 <span style="color: #00c2cb; font-weight: 700; font-family: 'Rajdhani', sans-serif; letter-spacing: 1px;">DiffCheck</span>. All rights reserved.
</footer>

<!-- modal page -->
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