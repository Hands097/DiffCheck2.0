<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
if (!isset($_GET['id'])) {
    header("Location: organizer_dashboard.php");
    exit();
}

$tournament_id = (int)$_GET['id'];

$check_query = mysqli_query($conn, "SELECT * FROM tournaments WHERE id='$tournament_id' AND organizer_id='$user_id' AND is_deleted=0");
if (mysqli_num_rows($check_query) == 0) {
    header("Location: organizer_dashboard.php");
    exit();
}
$tournament = mysqli_fetch_assoc($check_query);

// --- LOGIC: Tournament Actions ---
if (isset($_POST['edit_tournament'])) {
    $new_name = mysqli_real_escape_string($conn, $_POST['tourna_name']);
    $new_game = mysqli_real_escape_string($conn, $_POST['game']);
    $new_max  = (int)$_POST['max_teams'];
    mysqli_query($conn, "UPDATE tournaments SET name='$new_name', game='$new_game', max_teams='$new_max' WHERE id='$tournament_id'");
    $_SESSION['system_message'] = "Tournament details updated!";
    $_SESSION['msg_type'] = "success";
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

if (isset($_POST['soft_delete'])) {
    mysqli_query($conn, "UPDATE tournaments SET is_deleted=1 WHERE id='$tournament_id'");
    header("Location: organizer_dashboard.php");
    exit();
}

if (isset($_POST['start_tournament'])) {
    mysqli_query($conn, "UPDATE tournaments SET status='active' WHERE id='$tournament_id'");
    $_SESSION['system_message'] = "Tournament Started! You can now generate the bracket.";
    $_SESSION['msg_type'] = "success";
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

if (isset($_POST['reopen_tournament'])) {
    $check_matches = mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id='$tournament_id'");
    if (mysqli_num_rows($check_matches) > 0) {
        $_SESSION['system_message'] = "Error: Cannot re-open registration because the bracket has already been generated.";
        $_SESSION['msg_type'] = "error";
    } else {
        mysqli_query($conn, "UPDATE tournaments SET status='pending' WHERE id='$tournament_id'");
        $_SESSION['system_message'] = "Registration re-opened! The public can now apply again.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

if (isset($_POST['finish_tournament'])) {
    mysqli_query($conn, "UPDATE tournaments SET status='completed' WHERE id='$tournament_id'");
    $_SESSION['system_message'] = "Tournament Officially Completed! The Champion has been crowned.";
    $_SESSION['msg_type'] = "success";
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

if (isset($_POST['action_squad'])) {
    $reg_id     = (int)$_POST['registration_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['action_type']);
    if ($new_status === 'accepted') {
        $accepted_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM registrations WHERE tournament_id='$tournament_id' AND status='accepted'"))['c'];
        if ($accepted_count >= $tournament['max_teams']) {
            $_SESSION['system_message'] = "Error: Maximum teams ({$tournament['max_teams']}) already reached.";
            $_SESSION['msg_type'] = "error";
            header("Location: manage_tournament.php?id=$tournament_id");
            exit();
        }
    }
    mysqli_query($conn, "UPDATE registrations SET status='$new_status' WHERE id='$reg_id' AND tournament_id='$tournament_id'");
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

if (isset($_POST['generate_bracket'])) {
    $accepted_query = mysqli_query($conn, "SELECT id FROM registrations WHERE tournament_id='$tournament_id' AND status='accepted'");
    $teams = [];
    while ($row = mysqli_fetch_assoc($accepted_query)) { $teams[] = $row['id']; }
    shuffle($teams);

    $match_number = 1;
    for ($i = 0; $i < count($teams); $i += 2) {
        $t1 = $teams[$i];
        if (isset($teams[$i + 1])) {
            $t2 = $teams[$i + 1];
            mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, team1_id, team2_id) VALUES ('$tournament_id', 1, '$match_number', $t1, $t2)");
        } else {
            mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, team1_id, winner_id, status) VALUES ('$tournament_id', 1, '$match_number', $t1, $t1, 'completed')");
            $next_round     = 2;
            $next_match_num = ceil($match_number / 2);
            $check_next     = mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id='$tournament_id' AND round_number='$next_round' AND match_number='$next_match_num'");
            if (mysqli_num_rows($check_next) > 0) {
                $next_id = mysqli_fetch_assoc($check_next)['id'];
                $slot    = ($match_number % 2 != 0) ? 'team1_id' : 'team2_id';
                mysqli_query($conn, "UPDATE matches SET $slot='$t1' WHERE id='$next_id'");
            } else {
                $slot = ($match_number % 2 != 0) ? 'team1_id' : 'team2_id';
                mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, $slot) VALUES ('$tournament_id', '$next_round', '$next_match_num', '$t1')");
            }
        }
        $match_number++;
    }
    $_SESSION['system_message'] = "Bracket Generated! Byes have been automatically advanced.";
    $_SESSION['msg_type'] = "success";
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

if (isset($_POST['submit_winner'])) {
    $match_id  = (int)$_POST['match_id'];
    $winner_id = (int)$_POST['winner_id'];
    $round_num = (int)$_POST['round_number'];
    $match_num = (int)$_POST['match_number'];

    // Validate winner belongs to this match
    $validate = mysqli_query($conn, "SELECT id FROM matches WHERE id='$match_id' AND tournament_id='$tournament_id' AND (team1_id='$winner_id' OR team2_id='$winner_id')");
    if (mysqli_num_rows($validate) === 0) {
        $_SESSION['system_message'] = "Error: Invalid winner selection.";
        $_SESSION['msg_type'] = "error";
        header("Location: manage_tournament.php?id=$tournament_id");
        exit();
    }

    mysqli_query($conn, "UPDATE matches SET winner_id='$winner_id', status='completed' WHERE id='$match_id'");

    $next_round     = $round_num + 1;
    $next_match_num = ceil($match_num / 2);
    $check_next     = mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id='$tournament_id' AND round_number='$next_round' AND match_number='$next_match_num'");

    if (mysqli_num_rows($check_next) > 0) {
        $next_id = mysqli_fetch_assoc($check_next)['id'];
        $slot    = ($match_num % 2 != 0) ? 'team1_id' : 'team2_id';
        mysqli_query($conn, "UPDATE matches SET $slot='$winner_id' WHERE id='$next_id'");
    } else {
        $check_siblings = mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id='$tournament_id' AND round_number='$round_num'");
        if (mysqli_num_rows($check_siblings) > 1) {
            $slot = ($match_num % 2 != 0) ? 'team1_id' : 'team2_id';
            mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, $slot) VALUES ('$tournament_id', '$next_round', '$next_match_num', '$winner_id')");
        }
    }
    $_SESSION['system_message'] = "Winner advanced!";
    $_SESSION['msg_type'] = "success";
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

// --- FETCH DATA ---
$squads_query = mysqli_query($conn, "SELECT * FROM registrations WHERE tournament_id='$tournament_id'");
$matches_query = mysqli_query($conn, "
    SELECT m.*,
           t1.squad_name AS team1_name,
           t2.squad_name AS team2_name
    FROM matches m
    LEFT JOIN registrations t1 ON m.team1_id = t1.id
    LEFT JOIN registrations t2 ON m.team2_id = t2.id
    WHERE m.tournament_id='$tournament_id'
    ORDER BY m.round_number ASC, m.match_number ASC
");

// Champion detection — use actual max round, not a calculation
$champion_name      = null;
$max_round_result   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(round_number) as max_r FROM matches WHERE tournament_id='$tournament_id'"));
$final_round_number = $max_round_result['max_r'] ?? 0;

if ($final_round_number > 0) {
    $final_match_query = mysqli_query($conn, "
        SELECT m.winner_id, r.squad_name
        FROM matches m
        LEFT JOIN registrations r ON m.winner_id = r.id
        WHERE m.tournament_id='$tournament_id' AND m.round_number='$final_round_number' AND m.status='completed'
    ");
    if (mysqli_num_rows($final_match_query) > 0) {
        $champion_name = mysqli_fetch_assoc($final_match_query)['squad_name'];
    }
}

$matches_count = mysqli_num_rows($matches_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage: <?php echo htmlspecialchars($tournament['name']); ?> – DIFFCHECK</title>
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
            --teal-glow:     rgba(0,194,203,0.18);
            --teal-dim:      #009da5;
            --orange:        #f39c12;
            --red:           #ff4757;
            --green:         #2ed573;
            --gold:          #f0c040;
            --purple:        #a29bfe;
            --text-primary:  #d8e8f0;
            --text-secondary:#6a8fa8;
            --text-muted:    #3d5468;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg-deep) url('pic/bg.png') center center / cover fixed;
            font-family: 'Exo 2', sans-serif;
            color: var(--text-primary);
            min-height: 100vh;
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
        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .logo-image  { height: 34px; object-fit: contain; }
        .topbar-title { font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 700; color: #fff; letter-spacing: 1px; }
        .topbar-back {
            display: inline-flex; align-items: center; gap: 7px;
            color: var(--text-secondary); font-size: 13px; font-weight: 600;
            text-decoration: none; padding: 7px 14px;
            border: 1px solid var(--border-accent); border-radius: 6px;
            transition: .2s;
        }
        .topbar-back:hover { color: var(--teal); border-color: var(--teal); }

        /* ── LAYOUT ── */
        .page-wrap { max-width: 1100px; margin: 0 auto; padding: 32px 24px 60px; }

        /* ── TOAST ── */
        .toast {
            padding: 14px 20px; border-radius: 8px; margin-bottom: 24px;
            font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 10px;
        }
        .toast.success { background: rgba(46,213,115,0.1); border: 1px solid rgba(46,213,115,0.3); color: var(--green); }
        .toast.error   { background: rgba(255,71,87,0.1);  border: 1px solid rgba(255,71,87,0.3);  color: var(--red); }

        /* ── SECTION HEADER ── */
        .section-head {
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px; font-weight: 700; letter-spacing: 2px;
            text-transform: uppercase; color: var(--text-secondary);
            margin: 32px 0 16px; padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 8px;
        }
        .section-head i { color: var(--teal); }

        /* ── PANEL ── */
        .panel {
            background: var(--bg-card);
            border: 1px solid var(--border-accent);
            border-radius: 12px;
            padding: 24px 28px;
            margin-bottom: 24px;
        }

        /* ── TOURNAMENT HEADER CARD ── */
        .t-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 16px;
            margin-bottom: 24px;
        }
        .t-title { font-family: 'Rajdhani', sans-serif; font-size: 28px; font-weight: 700; color: #fff; }
        .t-meta  { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }
        .badge {
            display: inline-block; padding: 4px 12px; border-radius: 4px;
            font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
        }
        .badge-pending   { background: rgba(243,156,18,0.15); color: var(--orange); border: 1px solid rgba(243,156,18,0.3); }
        .badge-active    { background: rgba(0,194,203,0.12);  color: var(--teal);   border: 1px solid rgba(0,194,203,0.25); }
        .badge-completed { background: rgba(90,106,120,0.2);  color: #7a9ab0;       border: 1px solid #2d4050; }

        /* ── ACTION BUTTONS ── */
        .actions-row { display: flex; flex-wrap: wrap; gap: 10px; }
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 6px; font-family: 'Exo 2', sans-serif;
            font-size: 13px; font-weight: 700; cursor: pointer; transition: .2s;
            border: 1px solid transparent; text-decoration: none;
        }
        .btn:disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }
        .btn-teal    { background: var(--teal); color: #000; border-color: var(--teal); }
        .btn-teal:hover { background: var(--teal-dim); }
        .btn-outline { background: transparent; color: var(--text-secondary); border-color: var(--border-accent); }
        .btn-outline:hover { border-color: var(--teal); color: var(--teal); }
        .btn-orange  { background: transparent; color: var(--orange); border-color: rgba(243,156,18,0.4); }
        .btn-orange:hover { background: var(--orange); color: #000; }
        .btn-red     { background: transparent; color: var(--red); border-color: rgba(255,71,87,0.4); }
        .btn-red:hover { background: var(--red); color: #fff; }
        .btn-gold    { background: var(--gold); color: #000; border-color: var(--gold); font-weight: 700; }
        .btn-gold:hover { background: #d4a820; }

        /* ── EDIT FORM ── */
        .edit-grid { display: grid; grid-template-columns: 1fr 1fr 120px auto; gap: 14px; align-items: end; flex-wrap: wrap; }
        @media (max-width: 700px) { .edit-grid { grid-template-columns: 1fr 1fr; } }
        .form-group label { display: block; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 12px;
            background: rgba(0,0,0,0.3); border: 1px solid var(--border-accent);
            color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 13px;
            border-radius: 6px; outline: none; transition: border-color .2s;
        }
        .form-group input:focus, .form-group select:focus { border-color: var(--teal); }
        .form-group select option { background: #1a2230; }

        /* ── SQUADS TABLE ── */
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th {
            text-align: left; padding: 10px 14px;
            font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
            color: var(--text-secondary); border-bottom: 1px solid var(--border);
        }
        .data-table td { padding: 12px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(255,255,255,0.02); }
        .squad-status-accepted { color: var(--green); font-weight: 700; }
        .squad-status-rejected { color: var(--red); font-weight: 700; }
        .squad-status-pending  { color: var(--orange); font-weight: 700; }
        .squad-actions { display: flex; gap: 6px; }
        .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 4px; }

        /* ── BRACKET ── */
        .bracket-wrap { display: flex; gap: 24px; overflow-x: auto; padding-bottom: 12px; }
        .bracket-round { min-width: 220px; }
        .round-label {
            font-family: 'Rajdhani', sans-serif; font-size: 11px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase; color: var(--text-secondary);
            margin-bottom: 12px; text-align: center;
        }
        .match-card {
            background: var(--bg-panel); border: 1px solid var(--border-accent);
            border-radius: 8px; padding: 14px 16px; margin-bottom: 12px;
        }
        .match-label { font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; }
        .match-team {
            padding: 8px 10px; border-radius: 5px; font-size: 13px; font-weight: 600;
            margin-bottom: 4px; background: rgba(255,255,255,0.03);
            border: 1px solid var(--border); color: var(--text-primary);
            display: flex; align-items: center; gap: 6px;
        }
        .match-team.winner { border-color: var(--teal); background: rgba(0,194,203,0.08); color: var(--teal); }
        .match-team.tbd    { color: var(--text-muted); font-style: italic; }
        .match-vs { text-align: center; font-size: 10px; color: var(--text-muted); font-weight: 700; letter-spacing: 1px; margin: 2px 0; }
        .match-completed-tag { font-size: 11px; color: var(--green); font-weight: 700; margin-top: 8px; display: flex; align-items: center; gap: 5px; }

        /* winner select form inside match card */
        .winner-form { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); }
        .winner-form label { font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--text-secondary); display: block; margin-bottom: 6px; }
        .winner-form select {
            width: 100%; padding: 8px 10px; background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-accent); color: var(--text-primary);
            font-family: 'Exo 2', sans-serif; font-size: 13px; border-radius: 5px; outline: none;
            margin-bottom: 8px; transition: border-color .2s;
        }
        .winner-form select:focus { border-color: var(--teal); }
        .winner-form select option { background: #1a2230; }

        /* ── CHAMPION BANNER ── */
        .champion-banner {
            background: linear-gradient(135deg, rgba(240,192,64,0.15), rgba(240,192,64,0.05));
            border: 2px solid rgba(240,192,64,0.4);
            border-radius: 12px; padding: 28px; text-align: center; margin-bottom: 24px;
        }
        .champion-banner .crown { font-size: 40px; margin-bottom: 8px; }
        .champion-banner .champ-label { font-size: 11px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; color: var(--gold); margin-bottom: 6px; }
        .champion-banner .champ-name  { font-family: 'Rajdhani', sans-serif; font-size: 36px; font-weight: 700; color: #fff; }

        /* ── MODALS ── */
        .modal-overlay { display: none; position: fixed; inset: 0; z-index: 3000; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; animation: fadeIn .2s ease; }
        .modal-box { background: var(--bg-card); border: 1px solid var(--border-accent); border-radius: 14px; padding: 40px 36px; width: 380px; max-width: 92vw; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.6); }
        .modal-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 20px; }
        .modal-icon.teal   { background: rgba(0,194,203,0.1);  border: 1px solid rgba(0,194,203,0.25);  color: var(--teal); }
        .modal-icon.orange { background: rgba(243,156,18,0.1); border: 1px solid rgba(243,156,18,0.25); color: var(--orange); }
        .modal-icon.red    { background: rgba(255,71,87,0.1);  border: 1px solid rgba(255,71,87,0.25);  color: var(--red); }
        .modal-icon.gold   { background: rgba(240,192,64,0.1); border: 1px solid rgba(240,192,64,0.25); color: var(--gold); }
        .modal-title { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
        .modal-text  { color: var(--text-secondary); font-size: 14px; line-height: 1.6; margin-bottom: 28px; }
        .modal-actions { display: flex; gap: 12px; }
        .btn-modal-cancel  { flex: 1; padding: 12px; border: 1px solid var(--border-accent); border-radius: 6px; background: transparent; color: var(--text-secondary); font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; transition: .2s; }
        .btn-modal-cancel:hover { border-color: var(--text-secondary); color: #fff; }
        .btn-modal-confirm { flex: 1; padding: 12px; border: none; border-radius: 6px; font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; transition: .2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-modal-confirm.teal  { background: var(--teal);   color: #000; }
        .btn-modal-confirm.teal:hover  { background: var(--teal-dim); }
        .btn-modal-confirm.orange { background: var(--orange); color: #000; }
        .btn-modal-confirm.orange:hover { background: #d68910; }
        .btn-modal-confirm.red   { background: var(--red);    color: #fff; }
        .btn-modal-confirm.red:hover   { background: #e0303f; }
        .btn-modal-confirm.gold  { background: var(--gold);   color: #000; }
        .btn-modal-confirm.gold:hover  { background: #d4a820; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); font-size: 14px; }
        .empty-state i { font-size: 32px; margin-bottom: 12px; display: block; }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <img src="pic/DiffcheckLogoNoBG.png" class="logo-image" alt="DIFFCHECK">
        <span class="topbar-title">MANAGE TOURNAMENT</span>
    </div>
    <a href="organizer_dashboard.php" class="topbar-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="page-wrap">

    <!-- TOAST -->
    <?php if (isset($_SESSION['system_message'])): ?>
        <?php $msg_type = $_SESSION['msg_type'] ?? 'success'; ?>
        <div class="toast <?php echo $msg_type; ?>">
            <i class="fa-solid fa-<?php echo $msg_type === 'success' ? 'circle-check' : 'circle-exclamation'; ?>"></i>
            <?php echo htmlspecialchars($_SESSION['system_message']); unset($_SESSION['system_message']); unset($_SESSION['msg_type']); ?>
        </div>
    <?php endif; ?>

    <!-- TOURNAMENT HEADER -->
    <div class="panel">
        <div class="t-header">
            <div>
                <div class="t-title"><?php echo htmlspecialchars($tournament['name']); ?></div>
                <div class="t-meta"><?php echo htmlspecialchars($tournament['game']); ?> &nbsp;·&nbsp; Max <?php echo $tournament['max_teams']; ?> Teams</div>
            </div>
            <?php
                if ($tournament['status'] === 'pending')   echo '<span class="badge badge-pending">PENDING</span>';
                if ($tournament['status'] === 'active')    echo '<span class="badge badge-active">ACTIVE</span>';
                if ($tournament['status'] === 'completed') echo '<span class="badge badge-completed">COMPLETED</span>';
            ?>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="actions-row">
            <?php if ($tournament['status'] === 'pending'): ?>
                <button class="btn btn-teal" onclick="openModal('modal-start')">
                    <i class="fa-solid fa-play"></i> Start Tournament
                </button>
            <?php endif; ?>

            <?php if ($tournament['status'] === 'active'): ?>
                <button class="btn btn-outline" onclick="openModal('modal-reopen')" <?php if ($matches_count > 0) echo 'disabled'; ?>>
                    <i class="fa-solid fa-rotate-left"></i> Re-open Registration
                </button>
            <?php endif; ?>

            <?php if ($champion_name && $tournament['status'] !== 'completed'): ?>
                <button class="btn btn-gold" onclick="openModal('modal-finish')">
                    <i class="fa-solid fa-crown"></i> Crown Champion &amp; Finish
                </button>
            <?php endif; ?>

            <?php if ($tournament['status'] !== 'completed'): ?>
                <button class="btn btn-red" style="margin-left:auto;" onclick="openModal('modal-delete')">
                    <i class="fa-solid fa-trash-can"></i> Delete Tournament
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- EDIT DETAILS -->
    <div class="section-head"><i class="fa-solid fa-pen-to-square"></i> Edit Details</div>
    <div class="panel">
        <form method="POST">
            <div class="edit-grid">
                <div class="form-group">
                    <label>Tournament Name</label>
                    <input type="text" name="tourna_name" value="<?php echo htmlspecialchars($tournament['name']); ?>" required <?php if ($tournament['status'] === 'completed') echo 'disabled'; ?>>
                </div>
                <div class="form-group">
                    <label>Game</label>
                    <select name="game" required <?php if ($tournament['status'] === 'completed') echo 'disabled'; ?>>
                        <?php foreach (['Mobile Legends','Wild Rift','Honor of Kings','Valorant'] as $g): ?>
                            <option value="<?php echo $g; ?>" <?php if ($tournament['game'] === $g) echo 'selected'; ?>><?php echo $g; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Max Teams</label>
                    <input type="number" name="max_teams" value="<?php echo $tournament['max_teams']; ?>" min="2" max="16" required <?php if ($tournament['status'] === 'completed') echo 'disabled'; ?>>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" name="edit_tournament" class="btn btn-teal" <?php if ($tournament['status'] === 'completed') echo 'disabled'; ?>>
                        <i class="fa-solid fa-floppy-disk"></i> Save
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- MANAGE SQUADS -->
    <?php $total_squads = mysqli_num_rows($squads_query); ?>
    <div class="section-head"><i class="fa-solid fa-users"></i> Squads (<?php echo $total_squads; ?> Applied)</div>
    <div class="panel" style="padding: 0; overflow: hidden;">
        <?php if ($total_squads > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Squad Name</th>
                    <th>Status</th>
                    <?php if ($tournament['status'] === 'pending'): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php while ($squad = mysqli_fetch_assoc($squads_query)): ?>
                <tr>
                    <td style="font-weight:600; color:#fff;"><?php echo htmlspecialchars($squad['squad_name']); ?></td>
                    <td>
                        <span class="squad-status-<?php echo $squad['status']; ?>">
                            <?php echo ucfirst($squad['status']); ?>
                        </span>
                    </td>
                    <?php if ($tournament['status'] === 'pending'): ?>
                    <td>
                        <div class="squad-actions">
                            <form method="POST" style="display:inline;" id="accept-form-<?php echo $squad['id']; ?>">
                                <input type="hidden" name="registration_id" value="<?php echo $squad['id']; ?>">
                                <input type="hidden" name="action_type" value="accepted">
                                <button type="button" class="btn btn-outline btn-sm"
                                    onclick="openSquadModal('modal-accept', <?php echo $squad['id']; ?>, '<?php echo htmlspecialchars(addslashes($squad['squad_name'])); ?>')">
                                    <i class="fa-solid fa-check"></i> Accept
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" id="reject-form-<?php echo $squad['id']; ?>">
                                <input type="hidden" name="registration_id" value="<?php echo $squad['id']; ?>">
                                <input type="hidden" name="action_type" value="rejected">
                                <button type="button" class="btn btn-red btn-sm"
                                    onclick="openSquadModal('modal-reject', <?php echo $squad['id']; ?>, '<?php echo htmlspecialchars(addslashes($squad['squad_name'])); ?>')">
                                    <i class="fa-solid fa-xmark"></i> Reject
                                </button>
                            </form>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state"><i class="fa-solid fa-users-slash"></i>No squads have applied yet.</div>
        <?php endif; ?>
    </div>

    <!-- BRACKET -->
    <div class="section-head"><i class="fa-solid fa-sitemap"></i> Bracket</div>

    <?php if ($champion_name): ?>
    <div class="champion-banner">
        <div class="crown">👑</div>
        <div class="champ-label">Tournament Champion</div>
        <div class="champ-name"><?php echo htmlspecialchars($champion_name); ?></div>
    </div>
    <?php endif; ?>

    <?php if ($tournament['status'] === 'active' && $matches_count === 0): ?>
    <div class="panel" style="text-align:center; padding: 36px;">
        <p style="color:var(--text-secondary); margin-bottom:18px; font-size:14px;">
            The tournament is active. Generate the bracket to begin matchmaking.
        </p>
        <button class="btn btn-teal" onclick="openModal('modal-bracket')">
            <i class="fa-solid fa-sitemap"></i> Generate Bracket
        </button>
    </div>

    <?php elseif ($matches_count > 0): ?>
        <?php
        mysqli_data_seek($matches_query, 0);
        $rounds = [];
        while ($match = mysqli_fetch_assoc($matches_query)) {
            $rounds[$match['round_number']][] = $match;
        }
        ?>
        <div class="bracket-wrap">
        <?php foreach ($rounds as $round_num => $round_matches): ?>
            <div class="bracket-round">
                <div class="round-label">Round <?php echo $round_num; ?></div>
                <?php foreach ($round_matches as $match): ?>
                <div class="match-card">
                    <div class="match-label">Match <?php echo $match['match_number']; ?></div>

                    <?php
                    $t1_name   = $match['team1_name'] ?: null;
                    $t2_name   = $match['team2_name'] ?: null;
                    $winner_id = $match['winner_id'];
                    ?>
                    <div class="match-team <?php echo (!$t1_name ? 'tbd' : ($winner_id == $match['team1_id'] ? 'winner' : '')); ?>">
                        <?php if ($winner_id == $match['team1_id']): ?><i class="fa-solid fa-crown" style="font-size:11px;"></i><?php endif; ?>
                        <?php echo $t1_name ? htmlspecialchars($t1_name) : 'TBD / Bye'; ?>
                    </div>
                    <div class="match-vs">VS</div>
                    <div class="match-team <?php echo (!$t2_name ? 'tbd' : ($winner_id == $match['team2_id'] ? 'winner' : '')); ?>">
                        <?php if ($winner_id == $match['team2_id']): ?><i class="fa-solid fa-crown" style="font-size:11px;"></i><?php endif; ?>
                        <?php echo $t2_name ? htmlspecialchars($t2_name) : 'TBD / Bye'; ?>
                    </div>

                    <?php if ($match['status'] === 'completed'): ?>
                        <div class="match-completed-tag"><i class="fa-solid fa-circle-check"></i> Completed</div>

                    <?php elseif ($match['status'] === 'pending' && $match['team1_id'] && $match['team2_id'] && $tournament['status'] !== 'completed'): ?>
                        <div class="winner-form">
                            <label>Select Winner</label>
                            <form method="POST" id="winner-form-<?php echo $match['id']; ?>">
                                <input type="hidden" name="match_id"     value="<?php echo $match['id']; ?>">
                                <input type="hidden" name="round_number" value="<?php echo $match['round_number']; ?>">
                                <input type="hidden" name="match_number" value="<?php echo $match['match_number']; ?>">
                                <select name="winner_id" id="winner-select-<?php echo $match['id']; ?>" required>
                                    <option value="" disabled selected>Choose winner…</option>
                                    <option value="<?php echo $match['team1_id']; ?>"><?php echo htmlspecialchars($match['team1_name']); ?></option>
                                    <option value="<?php echo $match['team2_id']; ?>"><?php echo htmlspecialchars($match['team2_name']); ?></option>
                                </select>
                                <button type="button" class="btn btn-teal" style="width:100%;"
                                    onclick="openWinnerModal(<?php echo $match['id']; ?>, '<?php echo htmlspecialchars(addslashes($match['team1_name'])); ?>', '<?php echo htmlspecialchars(addslashes($match['team2_name'])); ?>')">
                                    <i class="fa-solid fa-trophy"></i> Submit Winner
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div><!-- /page-wrap -->

<!-- ══════════════════ MODALS ══════════════════ -->

<!-- Start Tournament -->
<div id="modal-start" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-start')">
    <div class="modal-box">
        <div class="modal-icon teal"><i class="fa-solid fa-play"></i></div>
        <div class="modal-title">Start Tournament</div>
        <div class="modal-text">Start <strong style="color:#fff;"><?php echo htmlspecialchars($tournament['name']); ?></strong>? Squads will no longer be able to apply once the tournament is active.</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('modal-start')">Cancel</button>
            <form method="POST" style="flex:1;">
                <button type="submit" name="start_tournament" class="btn-modal-confirm teal" style="width:100%;"><i class="fa-solid fa-play"></i> Start</button>
            </form>
        </div>
    </div>
</div>

<!-- Re-open Registration -->
<div id="modal-reopen" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-reopen')">
    <div class="modal-box">
        <div class="modal-icon orange"><i class="fa-solid fa-rotate-left"></i></div>
        <div class="modal-title">Re-open Registration</div>
        <div class="modal-text">This will set the tournament back to <strong style="color:#fff;">PENDING</strong> so squads can apply again. This is only allowed if no bracket has been generated yet.</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('modal-reopen')">Cancel</button>
            <form method="POST" style="flex:1;">
                <button type="submit" name="reopen_tournament" class="btn-modal-confirm orange" style="width:100%;"><i class="fa-solid fa-rotate-left"></i> Re-open</button>
            </form>
        </div>
    </div>
</div>

<!-- Crown Champion & Finish -->
<div id="modal-finish" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-finish')">
    <div class="modal-box">
        <div class="modal-icon gold"><i class="fa-solid fa-crown"></i></div>
        <div class="modal-title">Crown Champion</div>
        <div class="modal-text">Officially end the tournament and permanently crown <strong style="color:var(--gold);"><?php echo htmlspecialchars((string)$champion_name); ?></strong> as champion? This cannot be undone.</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('modal-finish')">Cancel</button>
            <form method="POST" style="flex:1;">
                <button type="submit" name="finish_tournament" class="btn-modal-confirm gold" style="width:100%;"><i class="fa-solid fa-crown"></i> Confirm</button>
            </form>
        </div>
    </div>
</div>

<!-- Generate Bracket -->
<div id="modal-bracket" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-bracket')">
    <div class="modal-box">
        <div class="modal-icon teal"><i class="fa-solid fa-sitemap"></i></div>
        <div class="modal-title">Generate Bracket</div>
        <div class="modal-text">This will randomly seed all accepted squads into a bracket. Byes are handled automatically. The bracket <strong style="color:#fff;">cannot be regenerated</strong> once created.</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('modal-bracket')">Cancel</button>
            <form method="POST" style="flex:1;">
                <button type="submit" name="generate_bracket" class="btn-modal-confirm teal" style="width:100%;"><i class="fa-solid fa-sitemap"></i> Generate</button>
            </form>
        </div>
    </div>
</div>

<!-- Delete Tournament -->
<div id="modal-delete" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-delete')">
    <div class="modal-box">
        <div class="modal-icon red"><i class="fa-solid fa-trash-can"></i></div>
        <div class="modal-title">Delete Tournament</div>
        <div class="modal-text">Delete <strong style="color:#fff;"><?php echo htmlspecialchars($tournament['name']); ?></strong>? It will be archived and hidden from the public.</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('modal-delete')">Cancel</button>
            <form method="POST" style="flex:1;">
                <button type="submit" name="soft_delete" class="btn-modal-confirm red" style="width:100%;"><i class="fa-solid fa-trash-can"></i> Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Accept Squad -->
<div id="modal-accept" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-accept')">
    <div class="modal-box">
        <div class="modal-icon teal"><i class="fa-solid fa-check"></i></div>
        <div class="modal-title">Accept Squad</div>
        <div class="modal-text">Accept <strong id="modal-accept-name" style="color:#fff;"></strong> into the tournament?</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('modal-accept')">Cancel</button>
            <button class="btn-modal-confirm teal" id="modal-accept-btn" onclick="submitSquadForm('accept')"><i class="fa-solid fa-check"></i> Accept</button>
        </div>
    </div>
</div>

<!-- Reject Squad -->
<div id="modal-reject" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-reject')">
    <div class="modal-box">
        <div class="modal-icon red"><i class="fa-solid fa-xmark"></i></div>
        <div class="modal-title">Reject Squad</div>
        <div class="modal-text">Reject <strong id="modal-reject-name" style="color:#fff;"></strong> from the tournament?</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('modal-reject')">Cancel</button>
            <button class="btn-modal-confirm red" onclick="submitSquadForm('reject')"><i class="fa-solid fa-xmark"></i> Reject</button>
        </div>
    </div>
</div>

<!-- Submit Winner -->
<div id="modal-winner" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-winner')">
    <div class="modal-box">
        <div class="modal-icon gold"><i class="fa-solid fa-trophy"></i></div>
        <div class="modal-title">Confirm Winner</div>
        <div class="modal-text">Declare <strong id="modal-winner-name" style="color:var(--gold);"></strong> as the winner of this match? This action advances them to the next round.</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('modal-winner')">Cancel</button>
            <button class="btn-modal-confirm gold" id="modal-winner-btn"><i class="fa-solid fa-trophy"></i> Confirm</button>
        </div>
    </div>
</div>

<script>
let _activeSquadId   = null;
let _activeMatchId   = null;

function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function openSquadModal(modalId, squadId, squadName) {
    _activeSquadId = squadId;
    document.getElementById(modalId === 'modal-accept' ? 'modal-accept-name' : 'modal-reject-name').textContent = squadName;
    openModal(modalId);
}

function submitSquadForm(type) {
    document.getElementById(type + '-form-' + _activeSquadId).querySelector('[name=action_squad]') ||
        (() => {
            const form = document.getElementById(type + '-form-' + _activeSquadId);
            const btn  = document.createElement('input');
            btn.type   = 'hidden';
            btn.name   = 'action_squad';
            btn.value  = '1';
            form.appendChild(btn);
            form.submit();
        })();
    const form = document.getElementById(type + '-form-' + _activeSquadId);
    if (!form.querySelector('[name=action_squad]')) {
        const h = document.createElement('input');
        h.type = 'hidden'; h.name = 'action_squad'; h.value = '1';
        form.appendChild(h);
    }
    form.submit();
}

function openWinnerModal(matchId, team1Name, team2Name) {
    const select = document.getElementById('winner-select-' + matchId);
    if (!select.value) { select.focus(); return; }
    const selectedName = select.options[select.selectedIndex].text;
    _activeMatchId = matchId;
    document.getElementById('modal-winner-name').textContent = selectedName;
    document.getElementById('modal-winner-btn').onclick = function() {
        const form = document.getElementById('winner-form-' + matchId);
        if (!form.querySelector('[name=submit_winner]')) {
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = 'submit_winner'; h.value = '1';
            form.appendChild(h);
        }
        form.submit();
    };
    openModal('modal-winner');
}

// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});
</script>
</body>
</html>
