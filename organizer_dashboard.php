<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'organizer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ── AJAX ACTIONS ──────────────────────────────────────────────────────────────
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    $id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($action === 'approve_reg') {
        $t_query = mysqli_query($conn, "SELECT r.tournament_id, t.max_teams FROM registrations r JOIN tournaments t ON r.tournament_id = t.id WHERE r.id='$id'");
        $t_data  = mysqli_fetch_assoc($t_query);
        $t_id    = $t_data['tournament_id'];
        $max     = $t_data['max_teams'];

        $c_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations WHERE tournament_id='$t_id' AND status='accepted'");
        $current = (int)mysqli_fetch_assoc($c_query)['count'];

        if ($current >= $max) {
            echo json_encode(['status' => 'error', 'message' => 'Tournament is already full! Cannot accept more teams.']);
            exit();
        }

        mysqli_query($conn, "UPDATE registrations SET status='accepted' WHERE id='$id'");
        $c_query2 = mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations WHERE tournament_id='$t_id' AND status='accepted'");
        $count    = (int)mysqli_fetch_assoc($c_query2)['count'];

        echo json_encode(['status' => 'success', 't_id' => $t_id, 'count' => $count, 'max' => $max]);
        exit();
    }

    if ($action === 'reject_reg') {
        mysqli_query($conn, "UPDATE registrations SET status='rejected' WHERE id='$id'");
        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($action === 'archive_tournament') {
        $status_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM tournaments WHERE id='$id' AND organizer_id='$user_id'"));
        if ($status_check['status'] === 'active') {
            echo json_encode(['status' => 'error', 'message' => 'Active tournaments cannot be archived.']);
            exit();
        }
        mysqli_query($conn, "UPDATE tournaments SET is_deleted=1 WHERE id='$id' AND organizer_id='$user_id'");
        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($action === 'set_winner') {
        $match_id  = isset($_POST['match_id'])  ? (int)$_POST['match_id']  : 0;
        $winner_id = isset($_POST['winner_id']) ? (int)$_POST['winner_id'] : 0;
        $score1    = isset($_POST['score1'])    ? (int)$_POST['score1']    : 0;
        $score2    = isset($_POST['score2'])    ? (int)$_POST['score2']    : 0;

        $mq = mysqli_query($conn, "
            SELECT m.* FROM matches m
            JOIN tournaments t ON m.tournament_id = t.id
            WHERE m.id='$match_id' AND t.organizer_id='$user_id'
        ");

        if (!$mq || mysqli_num_rows($mq) == 0) {
            echo json_encode(['status' => 'error', 'message' => 'Match not found']);
            exit();
        }
        $match = mysqli_fetch_assoc($mq);

        if ($winner_id !== (int)$match['team1_id'] && $winner_id !== (int)$match['team2_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid winner']);
            exit();
        }

        mysqli_query($conn, "UPDATE matches SET winner_id='$winner_id', score1='$score1', score2='$score2', status='completed' WHERE id='$match_id'");

        // Advance winner to next round
        $cur_round  = (int)$match['round_number'];
        $cur_match  = (int)$match['match_number'];
        $next_round = $cur_round + 1;
        $next_num   = (int)ceil($cur_match / 2);
        $slot       = ($cur_match % 2 === 1) ? 'team1_id' : 'team2_id';

        $nq = mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id='{$match['tournament_id']}' AND round_number='$next_round' AND match_number='$next_num'");
        if ($nq && mysqli_num_rows($nq) > 0) {
            $nm = mysqli_fetch_assoc($nq);
            mysqli_query($conn, "UPDATE matches SET `$slot`='$winner_id' WHERE id='{$nm['id']}'");
        }

        $wname = mysqli_fetch_assoc(mysqli_query($conn, "SELECT squad_name FROM registrations WHERE id='$winner_id'"));
        echo json_encode(['status' => 'success', 'winner_name' => $wname ? $wname['squad_name'] : '']);
        exit();
    }
}

// ── PROFILE UPDATE ─────────────────────────────────────────────────────────────
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
    header("Location: organizer_dashboard.php"); exit();
}

// ── CREATE TOURNAMENT ──────────────────────────────────────────────────────────
if (isset($_POST['create_tournament'])) {
    $t_name      = mysqli_real_escape_string($conn, $_POST['t_name']);
    $t_game      = mysqli_real_escape_string($conn, $_POST['t_game']);
    $t_desc      = mysqli_real_escape_string($conn, substr($_POST['t_desc'], 0, 300));
    $t_max_teams = (int)$_POST['t_max_teams'];

    $insert = mysqli_query($conn, "INSERT INTO tournaments (organizer_id, name, game, description, max_teams, status, is_deleted) VALUES ('$user_id', '$t_name', '$t_game', '$t_desc', '$t_max_teams', 'pending', 0)");

    if ($insert) { $_SESSION['system_message'] = "Tournament '$t_name' successfully created!"; $_SESSION['msg_type'] = "success"; }
    else         { $_SESSION['system_message'] = "Database error. Could not create tournament."; $_SESSION['msg_type'] = "error"; }
    header("Location: organizer_dashboard.php"); exit();
}

// ── RESTORE TOURNAMENT ─────────────────────────────────────────────────────────
if (isset($_POST['restore_tournament'])) {
    $t_id = (int)$_POST['tournament_id'];
    mysqli_query($conn, "UPDATE tournaments SET is_deleted=0 WHERE id='$t_id' AND organizer_id='$user_id'");
    $_SESSION['system_message'] = "Tournament restored!"; $_SESSION['msg_type'] = "success";
    header("Location: organizer_dashboard.php"); exit();
}

// ── START TOURNAMENT ───────────────────────────────────────────────────────────
if (isset($_POST['start_tournament'])) {
    $t_id = (int)$_POST['tournament_id'];

    $t_info    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT max_teams FROM tournaments WHERE id='$t_id'"));
    $max_teams = (int)$t_info['max_teams'];

    $rc_q      = mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations WHERE tournament_id='$t_id' AND status='accepted'");
    $reg_count = (int)mysqli_fetch_assoc($rc_q)['count'];

    if ($reg_count < 3) {
        $_SESSION['system_message'] = "Cannot start event. A minimum of 3 accepted squads is required.";
        $_SESSION['msg_type'] = "error";
        header("Location: organizer_dashboard.php"); exit();
    }

    $regs_q = mysqli_query($conn, "SELECT id FROM registrations WHERE tournament_id='$t_id' AND status='accepted' ORDER BY RAND()");
    $reg_ids = [];
    while ($reg = mysqli_fetch_assoc($regs_q)) { $reg_ids[] = $reg['id']; }

    $team_count   = count($reg_ids);
    $bracket_size = (int)pow(2, ceil(log(max(2, $team_count), 2)));
    $total_rounds = (int)log($bracket_size, 2);

    mysqli_query($conn, "DELETE FROM matches WHERE tournament_id='$t_id'");

    for ($r = 2; $r <= $total_rounds; $r++) {
        $matches_in_round = (int)($bracket_size / pow(2, $r));
        for ($m = 1; $m <= $matches_in_round; $m++) {
            mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, status) VALUES ('$t_id', '$r', '$m', 'pending')");
        }
    }

    $seeds = [1];
    for ($i = 2; $i <= $bracket_size; $i *= 2) {
        $new_seeds = [];
        foreach ($seeds as $seed) { $new_seeds[] = $seed; $new_seeds[] = $i + 1 - $seed; }
        $seeds = $new_seeds;
    }

    $match_number = 1;
    for ($i = 0; $i < $bracket_size; $i += 2) {
        $s1_index = $seeds[$i]   - 1;
        $s2_index = $seeds[$i+1] - 1;

        $t1_val = ($s1_index < $team_count) ? (int)$reg_ids[$s1_index] : 'NULL';
        $t2_val = ($s2_index < $team_count) ? (int)$reg_ids[$s2_index] : 'NULL';

        $winner = 'NULL'; $status = 'pending';
        if ($t1_val !== 'NULL' && $t2_val === 'NULL') { $winner = $t1_val; $status = 'completed'; }
        elseif ($t2_val !== 'NULL' && $t1_val === 'NULL') { $winner = $t2_val; $status = 'completed'; }
        elseif ($t1_val === 'NULL' && $t2_val === 'NULL') { $status = 'completed'; }

        mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, team1_id, team2_id, winner_id, status) VALUES ('$t_id', 1, '$match_number', $t1_val, $t2_val, $winner, '$status')");

        if ($winner !== 'NULL') {
            $next_round = 2; $next_num = (int)ceil($match_number / 2);
            $slot = ($match_number % 2 === 1) ? 'team1_id' : 'team2_id';
            mysqli_query($conn, "UPDATE matches SET $slot=$winner WHERE tournament_id='$t_id' AND round_number='$next_round' AND match_number='$next_num'");
        }
        $match_number++;
    }

    mysqli_query($conn, "UPDATE tournaments SET status='active' WHERE id='$t_id' AND organizer_id='$user_id'");
    $_SESSION['system_message'] = "Bracket generated successfully! Event is now ACTIVE."; $_SESSION['msg_type'] = "success";
    header("Location: organizer_dashboard.php"); exit();
}

// ── COMPLETE TOURNAMENT ────────────────────────────────────────────────────────
if (isset($_POST['complete_tournament'])) {
    $t_id = (int)$_POST['tournament_id'];
    mysqli_query($conn, "UPDATE tournaments SET status='completed' WHERE id='$t_id' AND organizer_id='$user_id'");
    $_SESSION['system_message'] = "Tournament marked as COMPLETED."; $_SESSION['msg_type'] = "success";
    header("Location: organizer_dashboard.php"); exit();
}

// ── FETCH DATA ─────────────────────────────────────────────────────────────────
$user_data          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name, last_name, created_at FROM users WHERE id='$user_id'"));
$tournaments_query  = mysqli_query($conn, "SELECT * FROM tournaments WHERE organizer_id='$user_id' AND is_deleted=0 ORDER BY created_at DESC");
$archived_query     = mysqli_query($conn, "SELECT * FROM tournaments WHERE organizer_id='$user_id' AND is_deleted=1 ORDER BY created_at DESC");

$applications_query = mysqli_query($conn, "
    SELECT r.id as reg_id, r.status as reg_status,
           t.name as t_name, t.game,
           CONCAT(u.first_name, ' ', u.last_name) as manager_name
    FROM registrations r
    JOIN tournaments t ON r.tournament_id = t.id
    JOIN users u ON r.manager_id = u.id
    WHERE t.organizer_id='$user_id' AND t.is_deleted=0 AND r.status='pending'
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

$game_counts = [];
$games_q = mysqli_query($conn, "SELECT game, COUNT(*) as cnt FROM tournaments WHERE organizer_id='$user_id' AND is_deleted=0 GROUP BY game ORDER BY cnt DESC");
while ($g = mysqli_fetch_assoc($games_q)) { $game_counts[] = $g; }

$monthly_t = [];
for ($i = 5; $i >= 0; $i--) {
    $label = date('M', strtotime("-$i months"));
    $y = date('Y', strtotime("-$i months"));
    $m = date('m', strtotime("-$i months"));
    $cnt_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tournaments WHERE organizer_id='$user_id' AND YEAR(created_at)='$y' AND MONTH(created_at)='$m'");
    $monthly_t[] = ['label' => $label, 'count' => (int)mysqli_fetch_assoc($cnt_q)['cnt']];
}

$total_regs    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM registrations r JOIN tournaments t ON r.tournament_id=t.id WHERE t.organizer_id='$user_id'"))['cnt'];
$accepted_regs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM registrations r JOIN tournaments t ON r.tournament_id=t.id WHERE t.organizer_id='$user_id' AND r.status='accepted'"))['cnt'];
$pending_regs  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM registrations r JOIN tournaments t ON r.tournament_id=t.id WHERE t.organizer_id='$user_id' AND r.status='pending'"))['cnt'];
$rejected_regs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM registrations r JOIN tournaments t ON r.tournament_id=t.id WHERE t.organizer_id='$user_id' AND r.status='rejected'"))['cnt'];

$js_game_labels    = json_encode(array_column($game_counts, 'game'));
$js_game_data      = json_encode(array_column($game_counts, 'cnt'));
$js_monthly_labels = json_encode(array_column($monthly_t,   'label'));
$js_monthly_data   = json_encode(array_column($monthly_t,   'count'));

$display_name    = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']);
$avatar_initials = strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1));

// Build bracket JSON for all active/completed tournaments
$all_brackets = [];
foreach ($my_tournaments as $t) {
    if ($t['status'] !== 'active' && $t['status'] !== 'completed') continue;
    $tid = $t['id'];

    $r1q = mysqli_query($conn, "SELECT COUNT(*) as count FROM matches WHERE tournament_id='$tid' AND round_number=1");
    if (!$r1q) continue;
    $r1m = (int)mysqli_fetch_assoc($r1q)['count'];
    if ($r1m == 0) continue;

    $norm   = (int)pow(2, ceil(log(max(2, $r1m * 2), 2)));
    $rounds = (int)log($norm, 2);

    $mq = mysqli_query($conn, "
        SELECT m.id as match_id, m.*,
               t1.squad_name AS team1_name,
               t2.squad_name AS team2_name,
               w.squad_name  AS winner_name
        FROM matches m
        LEFT JOIN registrations t1 ON m.team1_id = t1.id
        LEFT JOIN registrations t2 ON m.team2_id = t2.id
        LEFT JOIN registrations w  ON m.winner_id = w.id
        WHERE m.tournament_id='$tid'
        ORDER BY m.round_number ASC, m.match_number ASC
    ");

    $db_b = [];
    while ($row = mysqli_fetch_assoc($mq)) {
        $db_b[$row['round_number']][$row['match_number']] = $row;
    }

    $bracket_json = [];
    for ($r = 1; $r <= $rounds; $r++) {
        $mc = (int)($norm / pow(2, $r));
        for ($m = 1; $m <= $mc; $m++) {
            $mx = isset($db_b[$r][$m]) ? $db_b[$r][$m] : null;
            $bracket_json[$r][$m] = [
                'match_id' => $mx ? (int)$mx['match_id']  : null,
                'team1_id' => $mx ? (int)$mx['team1_id']  : null,
                'team2_id' => $mx ? (int)$mx['team2_id']  : null,
                'team1'    => $mx ? ($mx['team1_name']  ?? null) : null,
                'team2'    => $mx ? ($mx['team2_name']  ?? null) : null,
                'score1'   => $mx ? ($mx['score1']      ?? null) : null,
                'score2'   => $mx ? ($mx['score2']      ?? null) : null,
                'winner'   => $mx ? ($mx['winner_id'] == ($mx['team1_id'] ?? null) ? 'team1'
                                  : ($mx['winner_id'] == ($mx['team2_id'] ?? null) ? 'team2' : null)) : null,
                'status'   => $mx ? $mx['status'] : 'pending',
            ];
        }
    }

    // Resolve champion from final match
    $champion_name = null;
    $final_match = isset($db_b[$rounds][1]) ? $db_b[$rounds][1] : null;
    if ($final_match && $final_match['status'] === 'completed' && !empty($final_match['winner_name'])) {
        $champion_name = $final_match['winner_name'];
    }

    $all_brackets[$tid] = [
        'name'        => $t['name'],
        'status'      => $t['status'],
        'totalRounds' => $rounds,
        'normTeams'   => $norm,
        'bracket'     => $bracket_json,
        'champion'    => $champion_name,
    ];
}
$js_all_brackets = json_encode($all_brackets, JSON_UNESCAPED_UNICODE);
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
            --gold:          #f5c842;
            --gold-glow:     rgba(245,200,66,0.20);
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

        /* ── MAIN ── */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg-base) url('pic/bg.png') center center / cover fixed; }
        .main-header { height: var(--topbar-h); padding: 0 30px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; background: rgba(13,17,23,0.8); backdrop-filter: blur(10px); }
        .page-title { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; letter-spacing: 1px; text-transform: uppercase; }

        .content-body { flex: 1; padding: 30px; overflow-y: auto; max-width: 1400px; margin: 0 auto; width: 100%; }

        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* ── ALERTS ── */
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 25px; font-weight: 600; font-size: 14px; text-align: center; border: 1px solid transparent; }
        .alert-success { background: rgba(0,194,160,0.1); color: var(--green); border-color: rgba(0,194,160,0.3); }
        .alert-error   { background: rgba(0,194,203,0.1); color: var(--teal); border-color: rgba(0,194,203,0.3); }

        /* ── STATS ── */
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

        /* ── PANELS ── */
        .panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .panel-head { background: rgba(0,0,0,0.2); padding: 20px; border-bottom: 1px solid var(--border); font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: var(--teal); letter-spacing: 1px; text-transform: uppercase; display: flex; align-items: center; gap: 10px; }
        .panel-body { padding: 30px; }

        /* ── FORMS ── */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-accent); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; border-radius: 6px; outline: none; transition: border-color .2s; appearance: none; -webkit-appearance: none; }
        .form-control:focus { border-color: var(--teal); }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .btn-submit { display: inline-block; padding: 14px 24px; background: var(--teal); color: #000; border: none; border-radius: 6px; font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; transition: 0.3s; width: 100%; }
        .btn-submit:hover { background: var(--teal-dim); transform: translateY(-2px); }

        /* ── TABLE ── */
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: rgba(0,0,0,0.2); padding: 15px 20px; font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border); }
        td { padding: 15px 20px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        .badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-pending   { background: rgba(243,156,18,0.1); color: var(--orange); border: 1px solid rgba(243,156,18,0.3); }
        .badge-active    { background: rgba(0,194,203,0.1); color: var(--teal); border: 1px solid rgba(0,194,203,0.3); }
        .badge-completed { background: rgba(90,106,120,0.1); color: var(--text-secondary); border: 1px solid rgba(90,106,120,0.3); }

        .action-cell { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-action { padding: 6px 12px; border: 1px solid var(--border-accent); border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 12px; font-family: 'Exo 2', sans-serif; background: transparent; color: var(--text-secondary); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-action:hover { border-color: var(--teal); color: var(--teal); }
        .btn-action:disabled { opacity: 0.5; cursor: not-allowed; border-color: var(--border-accent) !important; color: var(--text-secondary) !important; background: transparent !important; }
        .btn-success { border-color: rgba(0,194,160,0.5); color: var(--green); }
        .btn-success:hover { background: var(--green); color: #000; border-color: var(--green); }
        .btn-danger  { border-color: rgba(255,71,87,0.5);  color: var(--red); }
        .btn-danger:hover  { background: var(--red);   color: #000; border-color: var(--red); }
        .btn-archive { border-color: rgba(243,156,18,0.5); color: var(--orange); }
        .btn-archive:hover { background: var(--orange); color: #000; border-color: var(--orange); }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } .charts-grid { grid-template-columns: 1fr; } }

        /* ── BRACKET (organizer) ── */
        .bracket-tournament-select { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .bracket-tab-btn { padding: 8px 18px; border: 1px solid var(--border-accent); border-radius: 6px; background: transparent; color: var(--text-secondary); font-family: 'Exo 2', sans-serif; font-size: 13px; font-weight: 600; cursor: pointer; transition: .2s; }
        .bracket-tab-btn:hover { border-color: var(--teal); color: var(--teal); }
        .bracket-tab-btn.active { background: rgba(0,194,203,0.12); border-color: var(--teal); color: var(--teal); }

        .bracket-scroll { width: 100%; height: 580px; overflow: auto; background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 10px; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .bracket-root   { display: flex; align-items: stretch; min-width: max-content; margin: auto; }

        .b-round-pair   { display: flex; align-items: stretch; flex-shrink: 0; }
        .b-round-col    { display: flex; flex-direction: column; min-width: 185px; }
        .b-round-header { text-align: center; font-family: 'Rajdhani', sans-serif; font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--teal); opacity: 0.75; padding-bottom: 12px; border-bottom: 1px solid var(--border); margin-bottom: 10px; }
        .b-matches-col  { display: flex; flex-direction: column; justify-content: space-around; flex: 1; position: relative; }
        .b-match-slot   { display: flex; align-items: center; justify-content: center; flex: 1; padding: 10px 0; }

        .b-matchup { width: 175px; flex-shrink: 0; border: 1px solid var(--border-accent); border-radius: 6px; overflow: hidden; background: var(--bg-card); transition: border-color .2s, transform .15s; cursor: pointer; }
        .b-matchup:hover { border-color: var(--teal); transform: translateY(-2px); }
        .b-matchup.completed { border-color: rgba(0,194,160,0.35); }
        .b-matchup.is-bye { border-color: rgba(243,156,18,0.4); cursor: default; }
        .b-matchup.is-bye:hover { transform: none; border-color: rgba(243,156,18,0.4); }

        .b-team-row { display: flex; justify-content: space-between; align-items: center; padding: 0 10px; height: 32px; font-size: 12px; font-weight: 500; color: var(--text-secondary); }
        .b-team-row + .b-team-row { border-top: 1px solid var(--border); }
        .b-team-row.winner { color: #fff; font-weight: 700; background: rgba(0,194,160,0.08); }
        .b-team-row.winner .b-score { color: var(--teal); }
        .b-team-row.loser  { opacity: 0.4; }
        .b-team-row.tbd    { color: var(--text-muted); font-style: italic; }
        .b-team-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 125px; }
        .b-score { font-family: 'Rajdhani', sans-serif; font-size: 12px; font-weight: 700; color: var(--text-muted); flex-shrink: 0; margin-left: 4px; }

        .b-connector { width: 26px; flex-shrink: 0; position: relative; }
        .b-connector svg { position: absolute; top: 0; left: 0; width: 100%; overflow: visible; pointer-events: none; }

        /* ── CHAMPION COLUMN (organizer bracket) ── */
        .b-champ-col {
            display: flex; flex-direction: column; min-width: 155px;
            align-items: center; justify-content: center;
        }
        .b-champ-header {
            font-family: 'Rajdhani', sans-serif; font-size: 12px; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase; color: var(--gold);
            opacity: 0.9; padding-bottom: 12px; border-bottom: 1px solid rgba(245,200,66,0.25);
            margin-bottom: 10px; width: 100%; text-align: center;
        }
        .b-champ-matches { display: flex; flex-direction: column; justify-content: center; align-items: center; flex: 1; }
        .b-champ-card {
            width: 145px; background: linear-gradient(135deg, rgba(245,200,66,0.10), rgba(245,200,66,0.03));
            border: 1.5px solid var(--gold); border-radius: 8px; padding: 14px 10px;
            text-align: center; box-shadow: 0 4px 18px var(--gold-glow);
            animation: bChampPulse 3s ease-in-out infinite;
        }
        @keyframes bChampPulse {
            0%,100% { box-shadow: 0 4px 18px var(--gold-glow); }
            50%      { box-shadow: 0 4px 28px rgba(245,200,66,0.35); }
        }
        .b-champ-card .b-trophy    { font-size: 24px; margin-bottom: 6px; }
        .b-champ-card .b-champ-name { font-family: 'Rajdhani', sans-serif; font-size: 14px; font-weight: 700; color: #fff; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 121px; margin: 0 auto; }
        .b-champ-card .b-champ-label { font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--gold); opacity: 0.85; margin-top: 4px; }

        /* ── WINNER MODAL ── */
        .wmodal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10,12,16,0.88); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; pointer-events: none; transition: opacity .25s; }
        .wmodal-overlay.open { opacity: 1; pointer-events: auto; }
        .wmodal { background: var(--bg-card); border: 1px solid var(--border-accent); border-radius: 14px; padding: 34px 38px; width: 520px; max-width: 94vw; max-height: 92vh; overflow-y: auto; position: relative; transform: translateY(16px); transition: transform .25s; text-align: center; }
        .wmodal-overlay.open .wmodal { transform: translateY(0); }
        .wmodal-close { position: absolute; top: 12px; right: 16px; background: none; border: none; color: var(--text-muted); font-size: 26px; cursor: pointer; }
        .wmodal-close:hover { color: #fff; }
        .wmodal-status { font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; }
        .wmodal-title  { font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: #fff; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 22px; }
        .wmodal-vs     { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 26px; }
        .wmodal-team   { flex: 1; text-align: center; min-width: 0; }
        .wmodal-team h3 { font-family: 'Rajdhani', sans-serif; font-size: 18px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wmodal-score-big { font-family: 'Rajdhani', sans-serif; font-size: 36px; font-weight: 700; color: #fff; line-height: 1; margin: 4px 0; }
        .wmodal-vs-badge { font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700; color: var(--teal); background: rgba(0,194,203,0.1); padding: 7px 10px; border-radius: 6px; border: 1px solid var(--teal); margin-top: 10px; flex-shrink: 0; }
        .wmodal-divider { border-top: 1px solid var(--border); margin: 0 0 20px; }
        .wmodal-section-title { font-family: 'Rajdhani', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 16px; }
        .score-row  { display: flex; align-items: center; justify-content: center; gap: 14px; margin-bottom: 18px; }
        .score-wrap { display: flex; flex-direction: column; align-items: center; gap: 5px; flex: 1; }
        .score-wrap label { font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: 1px; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
        .score-input { width: 76px; padding: 9px 10px; background: rgba(0,0,0,0.35); border: 1px solid var(--border-accent); color: #fff; font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; text-align: center; border-radius: 6px; outline: none; transition: border-color .2s; }
        .score-input:focus { border-color: var(--teal); }
        .score-sep { font-family: 'Rajdhani', sans-serif; font-size: 20px; color: var(--text-muted); padding-top: 18px; }
        .winner-btns { display: flex; gap: 10px; }
        .btn-pick-winner { flex: 1; padding: 12px 14px; border-radius: 7px; border: 2px solid var(--border-accent); background: transparent; color: var(--text-secondary); font-family: 'Rajdhani', sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; transition: all .2s; }
        .btn-pick-winner:hover  { border-color: var(--teal); color: var(--teal); }
        .btn-pick-winner.chosen { background: var(--teal); border-color: var(--teal); color: #000; }
        .wmodal-feedback  { margin-top: 12px; font-size: 13px; font-weight: 700; letter-spacing: 1px; color: var(--teal); min-height: 18px; }
        .completed-result { background: rgba(0,194,160,0.08); border: 1px solid rgba(0,194,160,0.25); border-radius: 8px; padding: 14px; }
        .completed-result p { font-family: 'Rajdhani', sans-serif; font-size: 17px; color: var(--green); font-weight: 700; letter-spacing: 1px; }
    
    /* Modal Styles */
        .modal-overlay { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease; }
        .modal-box { background: var(--bg-card); border: 1px solid var(--border-accent); border-radius: 14px; padding: 40px 36px; width: 360px; max-width: 90vw; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.6); }
        .modal-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(0,194,203,0.1); border: 1px solid rgba(0,194,203,0.25); display: flex; align-items: center; justify-content: center; font-size: 26px; color: var(--teal); margin: 0 auto 20px; }
        .modal-title { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
        .modal-text { color: var(--text-secondary); font-size: 14px; line-height: 1.6; margin-bottom: 28px; }
        .modal-actions { display: flex; gap: 12px; }
        .btn-modal-cancel { flex: 1; padding: 12px; border: 1px solid var(--border-accent); border-radius: 6px; background: transparent; color: var(--text-secondary); font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; }
        .btn-modal-confirm { flex: 1; padding: 12px; border: none; border-radius: 6px; background: var(--teal); color: #000; font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    
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
        <div class="nav-category">Event Management</div>
        <a class="nav-item active" onclick="switchTab('tab-overview', this)"><i class="fa-solid fa-chart-line"></i> Statistics</a>
        <a class="nav-item" onclick="switchTab('tab-create',   this)"><i class="fa-solid fa-plus-circle"></i> Create Event</a>
        <a class="nav-item" onclick="switchTab('tab-manage',   this)"><i class="fa-solid fa-list-check"></i> Manage Registrations</a>
        <a class="nav-item" onclick="switchTab('tab-archive',  this)"><i class="fa-solid fa-box-archive"></i> Archived Events</a>

        <div class="nav-category">Public Platform</div>
        <a href="tournaments.php" class="nav-item"><i class="fa-solid fa-trophy"></i> Browse Events</a>

        <div class="nav-category">System</div>
        <a onclick="document.getElementById('signout-modal').classList.add('active')" class="nav-item" style="color:var(--teal); cursor:pointer;"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
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
        <div class="page-title" id="page-title-display">Statistics</div>
    </header>

    <div class="content-body">

        <?php if (isset($_SESSION['system_message'])): ?>
            <div class="alert alert-<?php echo isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success'; ?>">
                <i class="fa-solid fa-circle-info"></i> <?php echo htmlspecialchars($_SESSION['system_message']); ?>
            </div>
            <?php unset($_SESSION['system_message']); unset($_SESSION['msg_type']); ?>
        <?php endif; ?>

        <!-- ══ STATISTICS ══════════════════════════════════════════════════════ -->
        <div id="tab-overview" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card" style="--accent-color:var(--orange);">
                    <i class="fa-solid fa-calendar-day stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $total_tournaments; ?></h3><p>Total Events</p></div>
                </div>
                <div class="stat-card" style="--accent-color:var(--teal);">
                    <i class="fa-solid fa-play stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $active_tournaments; ?></h3><p>Active Events</p></div>
                </div>
                <div class="stat-card" style="--accent-color:var(--green);">
                    <i class="fa-solid fa-flag-checkered stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $completed_tournaments; ?></h3><p>Completed</p></div>
                </div>
                <div class="stat-card" style="--accent-color:var(--purple);">
                    <i class="fa-solid fa-users stat-icon"></i>
                    <div class="stat-info"><h3><?php echo $total_regs; ?></h3><p>Total Registrations</p></div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-calendar-plus"></i> Events Created (Monthly)</div>
                    <div class="chart-wrap" style="height:220px;"><canvas id="chartMonthly"></canvas></div>
                    <div class="chart-legend"><div class="legend-item"><span class="legend-swatch" style="background:#00c2cb;"></span>Tournaments Created</div></div>
                </div>
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-trophy"></i> Tournament Status</div>
                    <div class="chart-wrap" style="height:220px;"><canvas id="chartStatus"></canvas></div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#f39c12;"></span>Pending (<?php echo $pending_tournaments; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2cb;"></span>Active (<?php echo $active_tournaments; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#6a8fa8;"></span>Completed (<?php echo $completed_tournaments; ?>)</div>
                    </div>
                </div>
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-gamepad"></i> Events by Game</div>
                    <div class="chart-wrap" style="height:220px;"><canvas id="chartGames"></canvas></div>
                </div>
                <div class="chart-panel">
                    <div class="chart-panel-head"><i class="fa-solid fa-inbox"></i> Registration Status</div>
                    <div class="chart-wrap" style="height:220px;"><canvas id="chartRegs"></canvas></div>
                    <div class="chart-legend">
                        <div class="legend-item"><span class="legend-swatch" style="background:#f39c12;"></span>Pending (<?php echo $pending_regs; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#00c2a0;"></span>Accepted (<?php echo $accepted_regs; ?>)</div>
                        <div class="legend-item"><span class="legend-swatch" style="background:#3d5468;"></span>Rejected (<?php echo $rejected_regs; ?>)</div>
                    </div>
                </div>

                <div class="chart-panel chart-full" style="padding:0;">
                    <div class="panel-head" style="border-radius:12px 12px 0 0;"><i class="fa-solid fa-trophy"></i> Your Tournaments</div>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Tournament Name</th><th>Game</th><th>Max Teams</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (count($my_tournaments) > 0): ?>
                                    <?php foreach ($my_tournaments as $t): ?>
                                    <tr>
                                        <td style="font-weight:700;color:#fff;"><?php echo htmlspecialchars($t['name']); ?></td>
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
                                                <?php if ($t['status'] === 'pending' || $t['status'] === 'completed'): ?>
                                                    <button type="button" onclick="handleAjaxAction('archive_tournament', <?php echo $t['id']; ?>, this)" class="btn-action btn-archive">
                                                        <i class="fa-solid fa-trash-can"></i> Archive
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-action" style="opacity:0.5;cursor:not-allowed;" onclick="alert('You cannot archive an event while it is active!')">
                                                        <i class="fa-solid fa-box-archive"></i> Archive
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px;">No tournaments yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ CREATE EVENT ════════════════════════════════════════════════════ -->
        <div id="tab-create" class="tab-content">
            <div class="panel" style="max-width:600px;margin:0 auto;">
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
                            <label class="form-label">Tournament Description (Max 300 chars)</label>
                            <textarea name="t_desc" class="form-control" rows="3" maxlength="300" placeholder="Brief details about the event, rules, or prizes..." required></textarea>
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

        <!-- ══ MANAGE REGISTRATIONS ════════════════════════════════════════════ -->
        <div id="tab-manage" class="tab-content">
            <div class="panel">
                <div class="panel-head"><i class="fa-solid fa-inbox"></i> Manager Applications</div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Tournament</th><th>Manager Name</th><th>Game</th><th>Decision</th></tr></thead>
                        <tbody>
                            <?php if (mysqli_num_rows($applications_query) > 0): ?>
                                <?php while ($app = mysqli_fetch_assoc($applications_query)): ?>
                                <tr>
                                    <td style="font-weight:700;color:#fff;"><?php echo htmlspecialchars($app['t_name']); ?></td>
                                    <td style="color:var(--teal);font-weight:600;"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($app['manager_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['game']); ?></td>
                                    <td>
                                        <div class="action-cell">
                                            <button type="button" onclick="handleAjaxAction('approve_reg', <?php echo $app['reg_id']; ?>, this)" class="btn-action btn-success">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                            <button type="button" onclick="handleAjaxAction('reject_reg', <?php echo $app['reg_id']; ?>, this)" class="btn-action btn-danger">
                                                <i class="fa-solid fa-xmark"></i> Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:20px;">No pending applications found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><i class="fa-solid fa-server"></i> Bracket Controls</div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Tournament Name</th><th>Game</th><th>Accepted Squads</th><th>Status</th><th>Controls</th></tr></thead>
                        <tbody>
                            <?php if (count($my_tournaments) > 0): ?>
                                <?php foreach ($my_tournaments as $t):
                                    $t_id = $t['id'];
                                    $rc_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations WHERE tournament_id='$t_id' AND status='accepted'");
                                    $reg_count = $rc_q ? (int)mysqli_fetch_assoc($rc_q)['count'] : 0;
                                ?>
                                <tr>
                                    <td style="font-weight:700;color:#fff;"><?php echo htmlspecialchars($t['name']); ?></td>
                                    <td><?php echo htmlspecialchars($t['game']); ?></td>
                                    <td>
                                        <span id="bracket-count-<?php echo $t['id']; ?>" style="font-weight:700;color:<?php echo ($reg_count >= $t['max_teams']) ? 'var(--green)' : 'var(--orange)'; ?>;">
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
                                                <?php if ($reg_count >= 3): ?>
                                                    <form method="POST" style="display:inline;" id="lock-bracket-form">
                                                        <input type="hidden" name="tournament_id" value="<?php echo $t['id']; ?>">
                                                            <button type="button" class="btn-action" onclick="document.getElementById('lock-bracket-modal').classList.add('active')">
                                                                <i class="fa-solid fa-lock"></i> Lock Bracket
                                                            </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="btn-action" style="opacity:0.5;cursor:not-allowed;" onclick="alert('You need a minimum of 3 accepted squads to start the tournament!')">
                                                        <i class="fa-solid fa-play"></i> Start / Lock Bracket
                                                    </button>
                                                <?php endif; ?>
                                            <?php elseif ($t['status'] === 'active'): ?>
                                                <form method="POST" style="display:inline;" id="complete-tournament-form">
                                                    <input type="hidden" name="tournament_id" value="<?php echo $t['id']; ?>">
                                                        <button type="button" class="btn-action" onclick="document.getElementById('complete-tournament-modal').classList.add('active')">
                                                            <i class="fa-solid fa-flag-checkered"></i> Complete
                                                        </button>
                                                </form>
                                            <?php else: ?>
                                                <span style="font-size:11px;color:var(--text-muted);font-style:italic;">Event Closed</span>
                                            <?php endif; ?>

                                            <?php if ($t['status'] === 'pending' || $t['status'] === 'completed'): ?>
                                                <button type="button" onclick="handleAjaxAction('archive_tournament', <?php echo $t['id']; ?>, this)" class="btn-action btn-archive">
                                                    <i class="fa-solid fa-box-archive"></i> Archive
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn-action" style="opacity:0.5;cursor:not-allowed;" onclick="alert('You cannot archive an event while it is active!')">
                                                    <i class="fa-solid fa-box-archive"></i> Archive
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px;">No tournaments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php
            $active_tournaments_list = array_filter($my_tournaments, fn($t) => $t['status'] === 'active' || $t['status'] === 'completed');
            if (!empty($active_tournaments_list)):
            ?>
            <div class="panel">
                <div class="panel-head"><i class="fa-solid fa-diagram-project"></i> Live Bracket &amp; Winner Picker</div>
                <div style="padding:20px 20px 0;">
                    <div class="bracket-tournament-select">
                        <?php $first = true; foreach ($active_tournaments_list as $t): ?>
                            <?php if (!isset($all_brackets[$t['id']])) continue; ?>
                            <button class="bracket-tab-btn <?php echo $first ? 'active' : ''; ?>"
                                    onclick="showBracket(<?php echo $t['id']; ?>, this)">
                                <?php echo htmlspecialchars($t['name']); ?>
                                <span style="font-size:10px;margin-left:5px;opacity:.7;">(<?php echo strtoupper($t['status']); ?>)</span>
                            </button>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>
                <div style="padding:0 20px 20px;">
                    <div class="bracket-scroll" id="bracketScrollArea">
                        <div class="bracket-root" id="bracketRoot"></div>
                    </div>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:10px;text-align:center;">
                        <i class="fa-solid fa-circle-info"></i> Click a match card to set scores and pick the winner.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Winner Modal -->
        <div class="wmodal-overlay" id="wmodal" onclick="wmodalClose(event)">
            <div class="wmodal" onclick="event.stopPropagation()">
                <button class="wmodal-close" onclick="wmodalForceClose()">&times;</button>
                <div class="wmodal-status" id="wmodalStatus">STATUS</div>
                <div class="wmodal-title">Match Details</div>
                <div class="wmodal-vs">
                    <div class="wmodal-team">
                        <h3 id="wmodalTeam1">—</h3>
                        <div class="wmodal-score-big" id="wmodalScore1">-</div>
                    </div>
                    <div class="wmodal-vs-badge">VS</div>
                    <div class="wmodal-team">
                        <h3 id="wmodalTeam2">—</h3>
                        <div class="wmodal-score-big" id="wmodalScore2">-</div>
                    </div>
                </div>

                <div id="wmodalCompleted" style="display:none;" class="completed-result">
                    <p id="wmodalCompletedWinner"></p>
                </div>

                <div id="wmodalPicker" style="display:none;">
                    <div class="wmodal-divider"></div>
                    <div class="wmodal-section-title"><i class="fa-solid fa-trophy"></i> Set Match Result</div>
                    <input type="hidden" id="wmodalMatchId">
                    <div class="score-row">
                        <div class="score-wrap">
                            <label id="wmodalScoreLabel1">Team 1</label>
                            <input type="number" id="wmodalInputScore1" class="score-input" min="0" value="0">
                        </div>
                        <div class="score-sep">:</div>
                        <div class="score-wrap">
                            <label id="wmodalScoreLabel2">Team 2</label>
                            <input type="number" id="wmodalInputScore2" class="score-input" min="0" value="0">
                        </div>
                    </div>
                    <div class="winner-btns">
                        <button id="wmodalBtnT1" class="btn-pick-winner" data-team-id="" onclick="submitWinner('team1')">🏆 Team 1 Wins</button>
                        <button id="wmodalBtnT2" class="btn-pick-winner" data-team-id="" onclick="submitWinner('team2')">🏆 Team 2 Wins</button>
                    </div>
                    <div class="wmodal-feedback" id="wmodalFeedback"></div>
                </div>
            </div>
        </div>

        <!-- ══ ARCHIVED EVENTS ═════════════════════════════════════════════════ -->
        <div id="tab-archive" class="tab-content">
            <div class="panel">
                <div class="panel-head"><i class="fa-solid fa-box-archive"></i> Archived Tournaments</div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Tournament Name</th><th>Game</th><th>Max Teams</th><th>Date</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (mysqli_num_rows($archived_query) > 0): ?>
                                <?php while ($arc = mysqli_fetch_assoc($archived_query)): ?>
                                <tr style="opacity:0.6;">
                                    <td style="font-weight:700;color:#fff;"><?php echo htmlspecialchars($arc['name']); ?></td>
                                    <td><?php echo htmlspecialchars($arc['game']); ?></td>
                                    <td><?php echo htmlspecialchars($arc['max_teams']); ?></td>
                                    <td><?php echo date("M j, Y", strtotime($arc['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" id="restore-tournament-form">
                                            <input type="hidden" name="tournament_id" value="<?php echo $arc['id']; ?>">
                                                <button type="button" class="btn-action" onclick="document.getElementById('restore-tournament-modal').classList.add('active')">
                                                    <i class="fa-solid fa-rotate-left"></i> Restore
                                                </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px;">No archived tournaments.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══ PROFILE ═════════════════════════════════════════════════════════ -->
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
                    <div class="panel-head"><i class="fa-solid fa-id-card"></i> Organizer Profile</div>
                    <div class="panel-body" style="text-align:center;">
                        <div style="font-size:60px;color:var(--teal);margin-bottom:10px;"><i class="fa-solid fa-sitemap"></i></div>
                        <h3 style="font-family:'Rajdhani',sans-serif;font-size:24px;color:#fff;"><?php echo $display_name; ?></h3>
                        <p style="color:var(--text-secondary);margin-bottom:20px;">Joined <?php echo date("F j, Y", strtotime($user_data['created_at'])); ?></p>
                        <div style="display:flex;justify-content:center;gap:30px;margin-top:20px;">
                            <div>
                                <div style="font-size:24px;font-weight:700;color:#fff;"><?php echo $total_tournaments; ?></div>
                                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Hosted Events</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /content-body -->
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
// ── AJAX HELPER ───────────────────────────────────────────────────────────────
function handleAjaxAction(action, id, buttonElement) {
    if (action === 'archive_tournament' && !confirm('Are you sure you want to archive this tournament?')) return;

    const row = buttonElement.closest('tr');
    const fd  = new URLSearchParams();
    fd.append('ajax_action', action);
    fd.append('id', id);

    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
    .then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); }
        catch(e) { alert("DATABASE ERROR:\n\n" + text); throw new Error("PHP Crash"); }
    })
    .then(data => {
        if (data.status === 'success') {
            row.style.transition = 'opacity .4s ease, transform .4s ease';
            row.style.opacity    = '0';
            row.style.transform  = 'scale(0.95) translateX(-10px)';
            setTimeout(() => row.remove(), 400);

            if (action === 'approve_reg' && data.t_id) {
                const counter = document.getElementById('bracket-count-' + data.t_id);
                if (counter) {
                    counter.innerText   = data.count + ' / ' + data.max;
                    counter.style.color = (data.count >= data.max) ? 'var(--green)' : 'var(--orange)';
                    if (data.count === 3) setTimeout(() => location.reload(), 500);
                }
            }
        } else if (data.status === 'error') {
            alert('⚠️ ' + data.message);
        }
    })
    .catch(err => { if (err.message !== 'PHP Crash') alert('Network error. Check connection.'); });
}

// ── CHARTS ────────────────────────────────────────────────────────────────────
Chart.defaults.color       = '#6a8fa8';
Chart.defaults.borderColor = '#1e2a38';

new Chart(document.getElementById('chartMonthly'), {
    type: 'bar',
    data: { labels: <?php echo $js_monthly_labels; ?>, datasets: [{ label:'Events', data:<?php echo $js_monthly_data; ?>, backgroundColor:'rgba(0,194,203,0.7)', borderRadius:5, borderSkipped:false }] },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ grid:{ color:'#1e2a38' } }, y:{ grid:{ color:'#1e2a38' }, beginAtZero:true, ticks:{ precision:0 } } } }
});
new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: { labels:['Pending','Active','Completed'], datasets:[{ data:[<?php echo $pending_tournaments; ?>,<?php echo $active_tournaments; ?>,<?php echo $completed_tournaments; ?>], backgroundColor:['#f39c12','#00c2cb','#6a8fa8'], borderWidth:0 }] },
    options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{ display:false } } }
});
new Chart(document.getElementById('chartGames'), {
    type: 'bar',
    data: { labels:<?php echo $js_game_labels; ?>, datasets:[{ data:<?php echo $js_game_data; ?>, backgroundColor:['rgba(0,194,203,0.75)','rgba(155,89,182,0.75)','rgba(0,194,160,0.75)','rgba(243,156,18,0.75)'], borderRadius:5, borderSkipped:false }] },
    options: { indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ grid:{ color:'#1e2a38' }, beginAtZero:true, ticks:{ precision:0 } }, y:{ grid:{ display:false } } } }
});
new Chart(document.getElementById('chartRegs'), {
    type: 'doughnut',
    data: { labels:['Pending','Accepted','Rejected'], datasets:[{ data:[<?php echo $pending_regs; ?>,<?php echo $accepted_regs; ?>,<?php echo $rejected_regs; ?>], backgroundColor:['#f39c12','#00c2a0','#3d5468'], borderWidth:0 }] },
    options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{ display:false } } }
});

// ── TAB SWITCHING ─────────────────────────────────────────────────────────────
function switchTab(tabId, element) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');

    if (!element) element = document.querySelector(`.nav-item[onclick*="${tabId}"]`);
    if (element && element.classList.contains('nav-item')) element.classList.add('active');

    const titleMap = {
        'tab-overview': 'Statistics',
        'tab-create':   'Launch New Event',
        'tab-manage':   'Registration & Bracket Control',
        'tab-archive':  'Archived Events',
        'tab-profile':  'Account Settings'
    };
    document.getElementById('page-title-display').innerText = titleMap[tabId] || '';
    sessionStorage.setItem('activeDiffCheckTab', tabId);
}

document.addEventListener('DOMContentLoaded', () => {
    const saved = sessionStorage.getItem('activeDiffCheckTab');
    if (saved) switchTab(saved, null);
    initBracket();
});

// ── BRACKET DATA & RENDERER ───────────────────────────────────────────────────
const ALL_BRACKETS = <?php echo $js_all_brackets; ?>;
let activeTid   = null;
let activeRound = null;
let activeMatch = null;

const BSTROKE = '#1e2a38';
const GSTROKE = '#b8902a';  // gold connector

function initBracket() {
    const tids = Object.keys(ALL_BRACKETS);
    if (tids.length > 0) showBracket(parseInt(tids[0]), null);
}

function showBracket(tid, btnEl) {
    activeTid = tid;
    document.querySelectorAll('.bracket-tab-btn').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');
    const data = ALL_BRACKETS[tid];
    if (!data) { document.getElementById('bracketRoot').innerHTML = '<p style="color:var(--text-muted);padding:20px;">No bracket data.</p>'; return; }
    renderBracket(data);
}

function getRoundLabel(r, total) {
    if (r === total)     return 'Grand Finals';
    if (r === total - 1) return 'Semi-Finals';
    if (r === total - 2 && total > 2) return 'Quarter-Finals';
    return 'Round ' + r;
}

function esc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderBracket(data) {
    const root = document.getElementById('bracketRoot');
    const { totalRounds, normTeams, bracket, champion } = data;
    let html = '';

    for (let r = 1; r <= totalRounds; r++) {
        const mc = normTeams / Math.pow(2, r);
        html += `<div class="b-round-pair">`;
        if (r > 1) html += `<div class="b-connector" id="bc-left-${r}"><svg id="bsvg-left-${r}"></svg></div>`;
        html += `<div class="b-round-col"><div class="b-round-header">${getRoundLabel(r, totalRounds)}</div><div class="b-matches-col" id="bm-${r}">`;

        for (let m = 1; m <= mc; m++) {
            const match  = bracket[r] && bracket[r][m] ? bracket[r][m] : null;
            const t1     = match?.team1 || null;
            const t2     = match?.team2 || null;
            let s1       = (match?.score1 !== null && match?.score1 !== undefined && match?.score1 !== '') ? match.score1 : '';
            let s2       = (match?.score2 !== null && match?.score2 !== undefined && match?.score2 !== '') ? match.score2 : '';
            const winner = match?.winner || null;
            const status = match?.status || 'pending';
            const isBye  = status === 'completed' && (!t1 || !t2);

            let t1d = t1 ? esc(t1) : 'TBD';
            let t2d = t2 ? esc(t2) : 'TBD';
            if (isBye) { if (!t1) s2 = 'WIN'; if (!t2) s1 = 'WIN'; }

            const c1 = !t1 ? 'tbd' : (winner === 'team1' ? 'winner' : (winner === 'team2' ? 'loser' : ''));
            const c2 = !t2 ? 'tbd' : (winner === 'team2' ? 'winner' : (winner === 'team1' ? 'loser' : ''));
            const cardCls   = `b-matchup${status === 'completed' ? ' completed' : ''}${isBye ? ' is-bye' : ''}`;
            const clickEvt  = isBye ? '' : `onclick="openWModal(${r}, ${m})"`;

            html += `<div class="b-match-slot" id="bs-${r}-${m}">
                <div class="${cardCls}" ${clickEvt}>
                    <div class="b-team-row ${c1}"><span class="b-team-name">${t1d}</span><span class="b-score">${s1}</span></div>
                    <div class="b-team-row ${c2}"><span class="b-team-name">${t2d}</span><span class="b-score">${s2}</span></div>
                </div>
            </div>`;
        }

        html += `</div></div>`;
        if (r < totalRounds) html += `<div class="b-connector" id="bc-right-${r}"><svg id="bsvg-right-${r}"></svg></div>`;
        html += `</div>`;
    }

    // ── Champion column ──
    if (champion) {
        html += `
        <div class="b-round-pair">
            <div class="b-connector" id="bc-champ-left"><svg id="bsvg-champ-left"></svg></div>
            <div class="b-round-col b-champ-col" id="bc-champ-col">
                <div class="b-round-header b-champ-header">Champion</div>
                <div class="b-matches-col b-champ-matches" id="bm-champ">
                    <div class="b-match-slot" id="bs-champ">
                        <div class="b-champ-card">
                            <div class="b-trophy">🏆</div>
                            <div class="b-champ-name">${esc(champion)}</div>
                            <div class="b-champ-label">Winner</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }

    root.innerHTML = html;
    requestAnimationFrame(() => requestAnimationFrame(() => drawBracketConnectors(data)));
}

function drawBracketConnectors(data) {
    const { totalRounds, normTeams, champion } = data;

    // Standard round connectors
    for (let r = 1; r < totalRounds; r++) {
        const mc    = normTeams / Math.pow(2, r);
        const colEl = document.getElementById('bm-'      + r);
        const ncolEl= document.getElementById('bm-'      + (r+1));
        const rCol  = document.getElementById('bc-right-'+ r);
        const lCol  = document.getElementById('bc-left-' + (r+1));
        const svgR  = document.getElementById('bsvg-right-'+ r);
        const svgL  = document.getElementById('bsvg-left-' + (r+1));
        if (!colEl||!ncolEl||!rCol||!lCol||!svgR||!svgL) continue;

        const cRect  = colEl.getBoundingClientRect();
        const ncRect = ncolEl.getBoundingClientRect();
        const rRect  = rCol.getBoundingClientRect();
        const lRect  = lCol.getBoundingClientRect();

        svgR.setAttribute('viewBox', `0 0 26 ${cRect.height}`);  svgR.style.height = cRect.height  + 'px';
        svgL.setAttribute('viewBox', `0 0 26 ${ncRect.height}`); svgL.style.height = ncRect.height + 'px';

        let rLines = '', lLines = '';
        for (let m = 1; m <= mc; m += 2) {
            const sA = document.getElementById(`bs-${r}-${m}`);
            const sB = document.getElementById(`bs-${r}-${m+1}`);
            if (!sA||!sB) continue;
            const yA = (sA.getBoundingClientRect().top + sA.getBoundingClientRect().bottom)/2 - rRect.top;
            const yB = (sB.getBoundingClientRect().top + sB.getBoundingClientRect().bottom)/2 - rRect.top;
            rLines += `<line x1="0" y1="${yA}" x2="26" y2="${yA}" stroke="${BSTROKE}" stroke-width="1.5"/>`;
            rLines += `<line x1="0" y1="${yB}" x2="26" y2="${yB}" stroke="${BSTROKE}" stroke-width="1.5"/>`;
            rLines += `<line x1="26" y1="${yA}" x2="26" y2="${yB}" stroke="${BSTROKE}" stroke-width="1.5"/>`;
            const ns = document.getElementById(`bs-${r+1}-${Math.ceil(m/2)}`);
            if (!ns) continue;
            const yN = (ns.getBoundingClientRect().top + ns.getBoundingClientRect().bottom)/2 - lRect.top;
            lLines += `<line x1="0" y1="${yN}" x2="26" y2="${yN}" stroke="${BSTROKE}" stroke-width="1.5"/>`;
        }
        svgR.innerHTML = rLines;
        svgL.innerHTML = lLines;
    }

    // Gold connector from Grand Finals → Champion card
    if (champion) {
        const finalSlot = document.getElementById(`bs-${totalRounds}-1`);
        const champSlot = document.getElementById('bs-champ');
        const champConn = document.getElementById('bc-champ-left');
        const svgChamp  = document.getElementById('bsvg-champ-left');

        if (finalSlot && champSlot && champConn && svgChamp) {
            const fRect  = finalSlot.getBoundingClientRect();
            const cRect  = champSlot.getBoundingClientRect();
            const ccRect = champConn.getBoundingClientRect();

            svgChamp.setAttribute('viewBox', `0 0 26 ${ccRect.height}`);
            svgChamp.style.height = ccRect.height + 'px';

            const yF = (fRect.top + fRect.bottom)/2 - ccRect.top;
            const yC = (cRect.top + cRect.bottom)/2 - ccRect.top;

            svgChamp.innerHTML = `
                <line x1="0" y1="${yF}" x2="26" y2="${yF}" stroke="${GSTROKE}" stroke-width="1.5"/>
                <line x1="26" y1="${yF}" x2="26" y2="${yC}" stroke="${GSTROKE}" stroke-width="1.5"/>
                <line x1="0"  y1="${yC}" x2="26" y2="${yC}" stroke="${GSTROKE}" stroke-width="1.5"/>`;
        }
    }
}

// ── WINNER MODAL ──────────────────────────────────────────────────────────────
function openWModal(r, m) {
    const data  = ALL_BRACKETS[activeTid];
    if (!data) return;
    const match = data.bracket[r] && data.bracket[r][m] ? data.bracket[r][m] : null;
    if (!match) return;

    activeRound = r; activeMatch = m;

    const t1 = match.team1 || 'TBD';
    const t2 = match.team2 || 'TBD';

    document.getElementById('wmodalTeam1').textContent   = t1;
    document.getElementById('wmodalTeam2').textContent   = t2;
    document.getElementById('wmodalScore1').textContent  = (match.score1 !== null && match.score1 !== '') ? match.score1 : '-';
    document.getElementById('wmodalScore2').textContent  = (match.score2 !== null && match.score2 !== '') ? match.score2 : '-';

    const statusEl  = document.getElementById('wmodalStatus');
    const picker    = document.getElementById('wmodalPicker');
    const completed = document.getElementById('wmodalCompleted');

    const canPick = data.status === 'active' && match.status !== 'completed' && match.team1 !== null && match.team2 !== null;

    if (match.status === 'completed') {
        statusEl.textContent = '✓ MATCH COMPLETED'; statusEl.style.color = 'var(--green)';
        picker.style.display    = 'none';
        completed.style.display = 'block';
        const wName = match.winner === 'team1' ? match.team1 : (match.winner === 'team2' ? match.team2 : '—');
        document.getElementById('wmodalCompletedWinner').textContent = '🏆 Winner: ' + wName;
    } else if (canPick) {
        statusEl.textContent = '● PENDING'; statusEl.style.color = 'var(--orange)';
        completed.style.display = 'none';
        picker.style.display    = 'block';
        document.getElementById('wmodalScoreLabel1').textContent  = t1;
        document.getElementById('wmodalScoreLabel2').textContent  = t2;
        document.getElementById('wmodalInputScore1').value        = '';
        document.getElementById('wmodalInputScore2').value        = '';
        document.getElementById('wmodalBtnT1').textContent        = '🏆 ' + t1 + ' Wins';
        document.getElementById('wmodalBtnT2').textContent        = '🏆 ' + t2 + ' Wins';
        document.getElementById('wmodalBtnT1').dataset.teamId     = match.team1_id;
        document.getElementById('wmodalBtnT2').dataset.teamId     = match.team2_id;
        document.getElementById('wmodalBtnT1').classList.remove('chosen');
        document.getElementById('wmodalBtnT2').classList.remove('chosen');
        document.getElementById('wmodalMatchId').value            = match.match_id;
        document.getElementById('wmodalFeedback').textContent     = '';
    } else {
        statusEl.textContent = '● PENDING'; statusEl.style.color = 'var(--orange)';
        completed.style.display = 'none';
        picker.style.display    = 'none';
    }

    document.getElementById('wmodal').classList.add('open');
}

function submitWinner(team) {
    const matchId  = document.getElementById('wmodalMatchId').value;
    const score1   = parseInt(document.getElementById('wmodalInputScore1').value) || 0;
    const score2   = parseInt(document.getElementById('wmodalInputScore2').value) || 0;
    const winnerId = document.getElementById(team === 'team1' ? 'wmodalBtnT1' : 'wmodalBtnT2').dataset.teamId;

    if (!winnerId || winnerId === '0') { alert('Team data missing.'); return; }

    document.getElementById('wmodalBtnT1').classList.toggle('chosen', team === 'team1');
    document.getElementById('wmodalBtnT2').classList.toggle('chosen', team === 'team2');
    document.getElementById('wmodalFeedback').textContent = 'Saving...';

    const fd = new URLSearchParams();
    fd.append('ajax_action', 'set_winner');
    fd.append('match_id',    matchId);
    fd.append('winner_id',   winnerId);
    fd.append('score1',      score1);
    fd.append('score2',      score2);

    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
    .then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); }
        catch(e) { alert("DATABASE ERROR DETECTED:\n\n" + text); throw new Error("PHP Crash"); }
    })
    .then(data => {
        if (data.status === 'success') {
            const bd    = ALL_BRACKETS[activeTid];
            const match = bd.bracket[activeRound][activeMatch];
            match.status = 'completed';
            match.score1 = score1;
            match.score2 = score2;
            match.winner = team;

            // Advance winner in local bracket data
            const nextR  = activeRound + 1;
            const nextM  = Math.ceil(activeMatch / 2);
            const slot   = activeMatch % 2 === 1 ? 'team1'    : 'team2';
            const slotId = activeMatch % 2 === 1 ? 'team1_id' : 'team2_id';
            if (bd.bracket[nextR] && bd.bracket[nextR][nextM]) {
                bd.bracket[nextR][nextM][slot]   = data.winner_name;
                bd.bracket[nextR][nextM][slotId] = parseInt(winnerId);
            }

            // If this was the Grand Final, set the champion
            if (activeRound === bd.totalRounds) {
                bd.champion = data.winner_name;
            }

            document.getElementById('wmodalFeedback').textContent = '✓ Saved!';
            renderBracket(bd);
            setTimeout(wmodalForceClose, 800);
        } else {
            document.getElementById('wmodalFeedback').textContent = '✗ ' + (data.message || 'Error');
        }
    })
    .catch(err => {
        document.getElementById('wmodalFeedback').textContent = err.message === 'PHP Crash' ? '✗ Check Error Popup' : '✗ Network error';
    });
}

function wmodalClose(e)  { if (e.target.id === 'wmodal') document.getElementById('wmodal').classList.remove('open'); }
function wmodalForceClose() { document.getElementById('wmodal').classList.remove('open'); }

window.addEventListener('resize', () => {
    if (activeTid && ALL_BRACKETS[activeTid]) drawBracketConnectors(ALL_BRACKETS[activeTid]);
});
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
            <button class="btn-modal-cancel" onclick="document.getElementById('signout-modal').classList.remove('active')">Cancel</button>
            <a href="logout.php" class="btn-modal-confirm"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
        </div>
    </div>
</div>
</body>
</html>

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

<!-- Lock Bracket Modal -->
<div id="lock-bracket-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-lock"></i></div>
        <div class="modal-title">Lock Bracket</div>
        <div class="modal-text">Lock the bracket? No more registrations will be accepted!<br>
            <span style="color:var(--text-muted); font-size:12px;">This cannot be undone.</span>
        </div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="document.getElementById('lock-bracket-modal').classList.remove('active')">Cancel</button>
            <button class="btn-modal-confirm" onclick="document.getElementById('lock-bracket-form').submit()"><i class="fa-solid fa-lock"></i> Lock</button>
        </div>
    </div>
</div>

<!-- Complete Tournament Modal -->
 <div id="complete-tournament-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-flag-checkered"></i></div>
        <div class="modal-title">Complete Tournament</div>
        <div class="modal-text">Mark this tournament as fully completed?<br>
            <span style="color:var(--text-muted); font-size:12px;">This cannot be undone.</span>
        </div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="document.getElementById('complete-tournament-modal').classList.remove('active')">Cancel</button>
            <button class="btn-modal-confirm" onclick="document.getElementById('complete-tournament-form').submit()"><i class="fa-solid fa-flag-checkered"></i> Complete</button>
        </div>
    </div>
</div>

<!-- Restore Tournament Modal -->
 <div id="restore-tournament-modal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-rotate-left"></i></div>
        <div class="modal-title">Restore Tournament</div>
        <div class="modal-text">Restore this tournament back to active?</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="document.getElementById('restore-tournament-modal').classList.remove('active')">Cancel</button>
            <button class="btn-modal-confirm" onclick="document.getElementById('restore-tournament-form').submit()"><i class="fa-solid fa-rotate-left"></i> Restore</button>
        </div>
    </div>
</div>

</body>
</html>