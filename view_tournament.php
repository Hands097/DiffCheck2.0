<?php
session_start();
include('db.php');

if (!isset($_GET['id'])) {
    header("Location: tournaments.php");
    exit();
}

$tournament_id = (int)$_GET['id'];

$query = mysqli_query($conn, "SELECT * FROM tournaments WHERE id='$tournament_id' AND is_deleted=0");
if (mysqli_num_rows($query) == 0) {
    echo "Tournament not found.";
    exit();
}
$tournament = mysqli_fetch_assoc($query);

$msg = "";
$msg_type = "";

if (isset($_POST['register_squad']) && isset($_SESSION['user_id']) && $_SESSION['role'] == 'manager') {
    $manager_id = $_SESSION['user_id'];
    $squad_name = mysqli_real_escape_string($conn, $_POST['squad_name']);

    $check_exist = mysqli_query($conn, "SELECT * FROM registrations WHERE tournament_id='$tournament_id' AND manager_id='$manager_id'");
    
    if (mysqli_num_rows($check_exist) > 0) {
        $msg = "You have already applied for this tournament.";
        $msg_type = "error";
    } else {
        mysqli_query($conn, "INSERT INTO registrations (tournament_id, manager_id, squad_name, status) VALUES ('$tournament_id', '$manager_id', '$squad_name', 'pending')");
        $msg = "Registration submitted successfully! Waiting for organizer approval.";
        $msg_type = "success";
    }
}

// Fetch all matches with team names
$matches_query = mysqli_query($conn, "
    SELECT m.*, 
           t1.squad_name AS team1_name, 
           t2.squad_name AS team2_name,
           w.squad_name  AS winner_name
    FROM matches m
    LEFT JOIN registrations t1 ON m.team1_id = t1.id
    LEFT JOIN registrations t2 ON m.team2_id = t2.id
    LEFT JOIN registrations w  ON m.winner_id = w.id
    WHERE m.tournament_id='$tournament_id' 
    ORDER BY m.round_number ASC, m.match_number ASC
");

$db_bracket = [];
while ($row = mysqli_fetch_assoc($matches_query)) {
    $db_bracket[$row['round_number']][$row['match_number']] = $row;
}

// ==========================================
// FETCH PLAYERS FOR EACH SQUAD (CRASH-PROOF)
// ==========================================
$squad_players = [];

try {
    // We join the bridge table (squad_members) to the players table safely.
    $players_sql = "
        SELECT r.squad_name, p.in_game_name AS player_name 
        FROM registrations r
        JOIN squads s ON r.squad_name = s.name AND r.manager_id = s.manager_id
        JOIN squad_members sm ON s.id = sm.squad_id
        JOIN players p ON sm.player_id = p.id
        WHERE r.tournament_id='$tournament_id'
    ";
    
    // The '@' suppresses warnings if the 'in_game_name' column isn't exact yet
    $safe_query = @mysqli_query($conn, $players_sql);

    if ($safe_query) {
        while ($p = mysqli_fetch_assoc($safe_query)) {
            if (!empty($p['player_name'])) {
                $squad_players[$p['squad_name']][] = $p['player_name'];
            }
        }
    }
} catch (Exception $e) {
    // If the tables aren't perfectly linked, do nothing and prevent crashing!
}
// ==========================================

// Dynamic bracket sizing
$champion_name = null;
$r1_query   = mysqli_query($conn, "SELECT COUNT(*) as count FROM matches WHERE tournament_id='$tournament_id' AND round_number=1");
$r1_matches = mysqli_fetch_assoc($r1_query)['count'];

if ($r1_matches > 0) {
    $normalized_teams = pow(2, ceil(log(max(2, $r1_matches * 2), 2)));
    $total_rounds     = (int)log($normalized_teams, 2);
} else {
    $normalized_teams = 0;
    $total_rounds     = 0;
}

if ($total_rounds > 0) {
    $final_q = mysqli_query($conn, "
        SELECT m.winner_id, r.squad_name
        FROM matches m
        LEFT JOIN registrations r ON m.winner_id = r.id
        WHERE m.tournament_id='$tournament_id'
          AND m.round_number='$total_rounds'
          AND m.status='completed'
    ");
    if (mysqli_num_rows($final_q) > 0) {
        $fm = mysqli_fetch_assoc($final_q);
        $champion_name = $fm['squad_name'];
    }
}

// Build bracket JSON for JS
$bracket_json = [];
for ($r = 1; $r <= $total_rounds; $r++) {
    $matches_in_round = $total_rounds > 0 ? (int)($normalized_teams / pow(2, $r)) : 0;
    $bracket_json[$r] = [];
    for ($m = 1; $m <= $matches_in_round; $m++) {
        $match = isset($db_bracket[$r][$m]) ? $db_bracket[$r][$m] : null;
        $bracket_json[$r][$m] = [
            'team1'  => $match ? ($match['team1_name'] ?? null) : null,
            'team2'  => $match ? ($match['team2_name'] ?? null) : null,
            'score1' => $match ? ($match['score1'] ?? null) : null,
            'score2' => $match ? ($match['score2'] ?? null) : null,
            'winner' => $match ? ($match['winner_id'] == ($match['team1_id'] ?? null) ? 'team1'
                               : ($match['winner_id'] == ($match['team2_id'] ?? null) ? 'team2' : null)) : null,
            'status' => $match ? $match['status'] : 'pending',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tournament['name']); ?> – DIFFCHECK</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Exo+2:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-deep:       #0a0d10;
            --bg-panel:      #0f1318;
            --bg-card:       #131820;
            --border:        #1e2a38;
            --border-accent: #1b3a4b;
            --teal:          #00c2cb;
            --teal-dim:      #009da5;
            --teal-glow:     rgba(0,194,203,0.18);
            --text-primary:  #d8e8f0;
            --text-secondary:#6a8fa8;
            --text-muted:    #3d5468;
            --red:           #e05555;
            --green:         #00c2a0;
            --status-open:   #00c2a0;
            --status-active: #4fa3e0;
            --status-done:   #5a6a78;
            --topbar-h:      65px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; }

        body {
            background: var(--bg-deep) url('pic/bg.png') center center / cover fixed;
            color: var(--text-primary);
            font-family: 'Exo 2', sans-serif;
            font-size: 14px;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: var(--bg-panel); border-bottom: 1px solid var(--border);
            padding: 0 32px; height: var(--topbar-h); display: flex; align-items: center;
            justify-content: space-between; flex-shrink: 0; z-index: 100;
        }
        .topbar-left { display: flex; align-items: center; gap: 12px; }
        .logo-box { width: 38px; height: 38px; background: var(--teal); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-family: 'Rajdhani', sans-serif; font-size: 10px; font-weight: 700; color: #000; }
        .topbar-title { font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; letter-spacing: 1.5px; color: var(--text-primary); text-transform: uppercase; }
        .topbar-title span { color: var(--teal); }
        .btn-back { color: var(--text-secondary); text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; transition: color .15s; }
        .btn-back:hover { color: var(--text-primary); }

        .page { flex: 1; min-height: 0; display: flex; flex-direction: column; padding: 28px 32px 24px; overflow: hidden; }

        .page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 18px; border-bottom: 1px solid var(--border); padding-bottom: 16px; flex-shrink: 0; }
        .page-title h1 { font-family: 'Rajdhani', sans-serif; font-size: 30px; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .page-meta { color: var(--text-secondary); font-size: 14px; }
        .page-meta strong { color: var(--text-primary); }

        .status-chip { font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 6px 16px; border-radius: 20px; }
        .chip-pending   { background: rgba(0,194,160,0.12);  color: var(--status-open);   border: 1px solid rgba(0,194,160,0.3); }
        .chip-active    { background: rgba(79,163,224,0.12); color: var(--status-active); border: 1px solid rgba(79,163,224,0.3); }
        .chip-completed { background: rgba(90,106,120,0.12); color: var(--status-done);   border: 1px solid rgba(90,106,120,0.3); }

        .alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 16px; font-weight: 500; font-size: 14px; border: 1px solid transparent; flex-shrink: 0; }
        .alert-success { background: rgba(0,194,160,0.1); color: var(--green); border-color: rgba(0,194,160,0.3); }
        .alert-error   { background: rgba(224,85,85,0.1); color: var(--red);   border-color: rgba(224,85,85,0.3); }

        .champion-banner { background: linear-gradient(135deg, rgba(0,194,203,0.1), rgba(0,194,203,0.02)); border: 1px solid var(--teal); color: var(--text-primary); padding: 20px; text-align: center; border-radius: 12px; margin-bottom: 16px; box-shadow: 0 4px 20px var(--teal-glow); flex-shrink: 0; }
        .champion-banner h3 { font-family: 'Rajdhani', sans-serif; font-size: 13px; color: var(--teal); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; }
        .champion-banner h1 { font-family: 'Rajdhani', sans-serif; font-size: 36px; font-weight: 700; letter-spacing: 1px; color: #fff; }

        .bracket-wrapper { flex: 1; min-height: 0; background: var(--bg-panel); border: 1px solid var(--border); border-radius: 12px; padding: 28px; overflow: auto; display: flex; align-items: center; justify-content: center; }
        .bracket { display: flex; align-items: stretch; min-width: max-content; min-height: max-content; gap: 0; }
        .round-pair { display: flex; align-items: stretch; flex-shrink: 0; }
        .round-col { display: flex; flex-direction: column; min-width: 190px; }
        .round-header { text-align: center; font-family: 'Rajdhani', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--teal); opacity: 0.8; padding-bottom: 14px; border-bottom: 1px solid var(--border); margin-bottom: 0; }
        .matches-col { display: flex; flex-direction: column; flex: 1; position: relative; }
        .match-slot { display: flex; align-items: center; flex: 1; padding: 8px 0; }

        .matchup { width: 180px; flex-shrink: 0; border: 1px solid var(--border-accent); border-radius: 6px; overflow: hidden; background: var(--bg-card); box-shadow: 0 4px 10px rgba(0,0,0,0.25); transition: border-color .2s, transform .2s, box-shadow .2s; position: relative; z-index: 2; cursor: pointer; }
        .matchup:hover { border-color: var(--teal); transform: translateY(-2px); box-shadow: 0 6px 15px var(--teal-glow); }

        .team-row { display: flex; justify-content: space-between; align-items: center; padding: 0 12px; height: 34px; font-size: 13px; font-weight: 500; color: var(--text-secondary); }
        .team-row + .team-row { border-top: 1px solid var(--border); }
        .team-row.winner { color: var(--text-primary); font-weight: 600; }
        .team-row.winner .score { color: var(--teal); }
        .team-row.tbd   { color: var(--text-muted); font-style: italic; }

        .team-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
        .score { font-family: 'Rajdhani', sans-serif; font-size: 12px; font-weight: 700; color: var(--text-muted); flex-shrink: 0; margin-left: 6px; }

        .connector-col { width: 28px; flex-shrink: 0; position: relative; }
        .connector-col svg { position: absolute; top: 0; left: 0; width: 100%; overflow: visible; pointer-events: none; }

        .panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; max-width: 600px; margin: 0 auto; }
        .panel-head { background: var(--bg-panel); padding: 16px 24px; border-bottom: 1px solid var(--border); font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 700; color: var(--text-primary); letter-spacing: 1px; text-transform: uppercase; text-align: center;}
        .panel-body { padding: 30px; text-align: center; }

        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 10px; text-align: left; }
        .form-control { background: var(--bg-panel); border: 1px solid var(--border-accent); color: var(--text-primary); font-family: 'Exo 2', sans-serif; font-size: 14px; padding: 12px 14px; border-radius: 6px; outline: none; transition: border-color .2s; width: 100%; margin-bottom: 20px; }
        .form-control:focus { border-color: var(--teal); }

        .btn { display: inline-flex; align-items: center; justify-content: center; font-family: 'Rajdhani', sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 12px 24px; border-radius: 6px; cursor: pointer; transition: all .2s; border: none; text-decoration: none; width: 100%; }
        .btn-primary { background: var(--teal); color: #000; }
        .btn-primary:hover { background: var(--teal-dim); box-shadow: 0 0 10px var(--teal-glow); }
        .btn-outline { background: transparent; border: 1px solid var(--border-accent); color: var(--text-secondary); margin-top: 10px; }
        .btn-outline:hover { border-color: var(--teal); color: var(--teal); }

        .empty-state { color: var(--text-muted); margin-bottom: 20px; line-height: 1.6; }

        /* ── MODAL UI ── */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10, 13, 16, 0.85); backdrop-filter: blur(5px); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity .3s; }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        .modal-content { background: var(--bg-card); border: 1px solid var(--border-accent); border-radius: 12px; padding: 40px; width: 550px; max-width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.8); position: relative; transform: translateY(20px); transition: transform .3s; text-align: center; }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .modal-close { position: absolute; top: 15px; right: 20px; background: transparent; border: none; color: var(--text-muted); font-size: 28px; cursor: pointer; transition: color .2s; }
        .modal-close:hover { color: var(--text-primary); }
        .vs-container { display: flex; align-items: flex-start; justify-content: space-between; margin: 30px 0; }
        .team-block { flex: 1; text-align: center; }
        .team-block h3 { font-family: 'Rajdhani', sans-serif; font-size: 24px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; margin: 0 auto; }
        .vs-badge { font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 700; color: var(--teal); background: rgba(0,194,203,0.1); padding: 8px 12px; border-radius: 6px; margin: 0 15px; border: 1px solid var(--teal); margin-top: 10px; }
        .match-status { font-size: 13px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 10px; }
        .player-list { list-style: none; padding: 0; margin-top: 15px; text-align: center; }
        .player-list li { font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .player-list li i { color: var(--teal); font-size: 10px; }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <div class="logo-box">DIFF<br>CHECK</div>
        <div class="topbar-title">DIFF<span>CHECK</span></div>
    </div>
    <a href="tournaments.php" class="btn-back">← Back to Tournaments</a>
</header>

<div class="page">

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div class="page-title">
            <h1><?php echo htmlspecialchars($tournament['name']); ?></h1>
            <div class="page-meta">
                <strong>Game:</strong> <?php echo htmlspecialchars($tournament['game']); ?> &nbsp;|&nbsp;
                <strong>Capacity:</strong> <?php echo $tournament['max_teams']; ?> Teams
            </div>
        </div>
        <div class="status-chip chip-<?php echo $tournament['status']; ?>">
            ● <?php echo strtoupper($tournament['status']); ?>
        </div>
    </div>

    <?php if ($tournament['status'] === 'active' || $tournament['status'] === 'completed'): ?>

        <?php if ($tournament['status'] === 'completed' && $champion_name): ?>
            <div class="champion-banner">
                <h3>Tournament Champion</h3>
                <h1>🏆 <?php echo htmlspecialchars($champion_name); ?> 🏆</h1>
            </div>
        <?php endif; ?>

        <?php if (!empty($db_bracket) && $total_rounds > 0): ?>

            <div class="bracket-wrapper">
                <div class="bracket" id="bracket-root"></div>
            </div>

            <script>
            const SQUAD_PLAYERS = <?php echo json_encode($squad_players, JSON_UNESCAPED_UNICODE); ?>;

            const BRACKET_DATA = {
                totalRounds:     <?php echo $total_rounds; ?>,
                normalizedTeams: <?php echo $normalized_teams; ?>,
                champion:        <?php echo $champion_name ? json_encode($champion_name) : 'null'; ?>,
                bracket:         <?php echo json_encode($bracket_json, JSON_UNESCAPED_UNICODE); ?>
            };

            const COLORS = { borderAccent:'#1b3a4b' };

            function getRoundLabel(r, total) {
                if (r === total)     return 'Grand Finals';
                if (r === total - 1) return 'Semi-Finals';
                if (r === total - 2 && total > 2) return 'Quarter-Finals';
                return 'Round ' + r;
            }

            function buildBracket() {
                const root = document.getElementById('bracket-root');
                const { totalRounds, normalizedTeams, bracket } = BRACKET_DATA;
                let html = '';

                for (let r = 1; r <= totalRounds; r++) {
                    const matchCount = normalizedTeams / Math.pow(2, r);
                    html += `<div class="round-pair">`;

                    if (r > 1) html += `<div class="connector-col" id="conn-left-${r}"><svg id="svg-left-${r}"></svg></div>`;

                    html += `<div class="round-col">
                        <div class="round-header">${getRoundLabel(r, totalRounds)}</div>
                        <div class="matches-col" id="matches-${r}">`;

                    for (let m = 1; m <= matchCount; m++) {
                        const match = bracket[r] && bracket[r][m] ? bracket[r][m] : null;
                        const t1 = match?.team1 || null;
                        const t2 = match?.team2 || null;
                        const s1 = (match?.score1 !== null && match?.score1 !== undefined) ? match.score1 : '';
                        const s2 = (match?.score2 !== null && match?.score2 !== undefined) ? match.score2 : '';
                        const winner = match?.winner || null;

                        const cls1 = !t1 ? 'tbd' : winner === 'team1' ? 'winner' : '';
                        const cls2 = !t2 ? 'tbd' : winner === 'team2' ? 'winner' : '';

                        html += `<div class="match-slot" id="slot-${r}-${m}">
                            <div class="matchup" onclick="openMatchModal(${r}, ${m})">
                                <div class="team-row ${cls1}">
                                    <span class="team-name">${t1 ? escHtml(t1) : 'TBD'}</span>
                                    <span class="score">${s1}</span>
                                </div>
                                <div class="team-row ${cls2}">
                                    <span class="team-name">${t2 ? escHtml(t2) : 'BYE / TBD'}</span>
                                    <span class="score">${s2}</span>
                                </div>
                            </div>
                        </div>`;
                    }

                    html += `</div></div>`;

                    if (r < totalRounds) html += `<div class="connector-col" id="conn-right-${r}"><svg id="svg-right-${r}"></svg></div>`;

                    html += `</div>`;
                }
                root.innerHTML = html;
            }

            function drawConnectors() {
                const { totalRounds, normalizedTeams } = BRACKET_DATA;
                const stroke = COLORS.borderAccent;

                for (let r = 1; r < totalRounds; r++) {
                    const matchCount     = normalizedTeams / Math.pow(2, r);
                    const matchesCol     = document.getElementById('matches-' + r);
                    const nextMatchesCol = document.getElementById('matches-' + (r + 1));
                    const rightCol       = document.getElementById('conn-right-' + r);
                    const leftCol        = document.getElementById('conn-left-' + (r + 1));
                    const svgRight       = document.getElementById('svg-right-' + r);
                    const svgLeft        = document.getElementById('svg-left-' + (r + 1));

                    if (!matchesCol || !nextMatchesCol || !rightCol || !leftCol || !svgRight || !svgLeft) continue;

                    const colRect = matchesCol.getBoundingClientRect();
                    const nextColRect = nextMatchesCol.getBoundingClientRect();
                    const rightRect = rightCol.getBoundingClientRect();
                    const leftRect = leftCol.getBoundingClientRect();

                    svgRight.setAttribute('viewBox', `0 0 28 ${colRect.height}`);
                    svgRight.style.height = colRect.height + 'px';
                    svgLeft.setAttribute('viewBox', `0 0 28 ${nextColRect.height}`);
                    svgLeft.style.height = nextColRect.height + 'px';

                    let rightLines = '';
                    let leftLines  = '';

                    for (let m = 1; m <= matchCount; m += 2) {
                        const slotA = document.getElementById(`slot-${r}-${m}`);
                        const slotB = document.getElementById(`slot-${r}-${m + 1}`);
                        if (!slotA || !slotB) continue;

                        const rA = slotA.getBoundingClientRect();
                        const rB = slotB.getBoundingClientRect();
                        const yA = (rA.top + rA.bottom) / 2 - rightRect.top;
                        const yB = (rB.top + rB.bottom) / 2 - rightRect.top;

                        rightLines += `<line x1="0" y1="${yA}"  x2="28" y2="${yA}"  stroke="${stroke}" stroke-width="1.5"/>`;
                        rightLines += `<line x1="0" y1="${yB}"  x2="28" y2="${yB}"  stroke="${stroke}" stroke-width="1.5"/>`;
                        rightLines += `<line x1="28" y1="${yA}" x2="28" y2="${yB}"  stroke="${stroke}" stroke-width="1.5"/>`;

                        const nextMatchIdx = Math.ceil(m / 2);
                        const nextSlot = document.getElementById(`slot-${r + 1}-${nextMatchIdx}`);
                        if (!nextSlot) continue;
                        const rN = nextSlot.getBoundingClientRect();
                        const yN = (rN.top + rN.bottom) / 2 - leftRect.top;
                        leftLines += `<line x1="0" y1="${yN}" x2="28" y2="${yN}" stroke="${stroke}" stroke-width="1.5"/>`;
                    }
                    svgRight.innerHTML = rightLines;
                    svgLeft.innerHTML  = leftLines;
                }
            }

            function escHtml(str) {
                return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function renderPlayers(teamName, listElementId) {
                const listEl = document.getElementById(listElementId);
                listEl.innerHTML = ''; 
                if (teamName && SQUAD_PLAYERS[teamName] && SQUAD_PLAYERS[teamName].length > 0) {
                    SQUAD_PLAYERS[teamName].forEach(player => {
                        listEl.innerHTML += `<li><i class="fa-solid fa-user"></i> ${escHtml(player)}</li>`;
                    });
                } else if (teamName && teamName !== 'BYE / TBD' && teamName !== 'TBD') {
                    listEl.innerHTML = `<li><em style="color: var(--text-muted); font-size: 11px;">No roster info</em></li>`;
                }
            }

            function openMatchModal(r, m) {
                const match = BRACKET_DATA.bracket[r][m];
                if (!match) return;

                document.getElementById('modalTeam1').textContent = match.team1 || 'TBD';
                document.getElementById('modalTeam2').textContent = match.team2 || 'BYE / TBD';
                document.getElementById('modalScore1').textContent = match.score1 !== null && match.score1 !== "" ? match.score1 : '-';
                document.getElementById('modalScore2').textContent = match.score2 !== null && match.score2 !== "" ? match.score2 : '-';

                renderPlayers(match.team1, 'modalPlayers1');
                renderPlayers(match.team2, 'modalPlayers2');

                const statusEl = document.getElementById('modalStatus');
                if(match.status === 'completed') {
                    statusEl.textContent = 'MATCH COMPLETED';
                    statusEl.style.color = 'var(--status-done)';
                } else {
                    statusEl.textContent = 'PENDING MATCHUP';
                    statusEl.style.color = 'var(--status-active)';
                }

                const winnerEl = document.getElementById('modalWinner');
                if (match.status === 'completed' && match.winner) {
                    const winnerName = match.winner === 'team1' ? match.team1 : match.team2;
                    winnerEl.innerHTML = `🏆 Winner: <span style="color: #fff;">${escHtml(winnerName)}</span>`;
                } else {
                    winnerEl.innerHTML = '';
                }

                document.getElementById('matchModal').classList.add('active');
            }

            function closeModal(e) {
                if(e) e.preventDefault();
                document.getElementById('matchModal').classList.remove('active');
            }

            buildBracket();
            requestAnimationFrame(() => requestAnimationFrame(drawConnectors));
            window.addEventListener('resize', drawConnectors);
            </script>

        <?php else: ?>
            <div class="panel">
                <div class="panel-body"><p class="empty-state">The tournament has started, but the bracket data could not be loaded.</p></div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="panel">
            <div class="panel-head">Tournament Registration</div>
            <div class="panel-body">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <p class="empty-state">Guests cannot register for tournaments. You must log in to a Manager account to enter a squad.</p>
                    <a href="login.php" class="btn btn-primary" style="margin-bottom: 10px;">Log In</a>
                    <a href="register.php" class="btn btn-outline">Sign Up</a>
                <?php elseif ($_SESSION['role'] === 'organizer'): ?>
                    <p class="empty-state">Organizers cannot play in tournaments. Please log into a Manager account to register a squad.</p>
                <?php elseif ($_SESSION['role'] === 'manager'): ?>
                    <?php
                    $manager_id = $_SESSION['user_id'];
                    $game = $tournament['game'];
                    $squads = mysqli_query($conn, "SELECT name FROM squads WHERE manager_id='$manager_id' AND game='$game' AND status='active'");
                    if (mysqli_num_rows($squads) > 0): ?>
                        <form method="POST" style="text-align: left;">
                            <label class="form-label">Select a Squad to Enter:</label>
                            <select name="squad_name" class="form-control" required>
                                <?php while ($s = mysqli_fetch_assoc($squads)): ?>
                                    <option value="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="register_squad" class="btn btn-primary">Submit Registration</button>
                        </form>
                    <?php else: ?>
                        <p class="empty-state">You do not have any active squads registered for <strong><?php echo htmlspecialchars($game); ?></strong>.</p>
                        <a href="manager_dashboard.php" class="btn btn-primary">Go to Dashboard to Create a Squad</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<div class="modal-overlay" id="matchModal" onclick="closeModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <button class="modal-close" onclick="closeModal(event)">&times;</button>
        <div class="match-status" id="modalStatus">STATUS</div>
        <div class="vs-container">
            <div class="team-block">
                <h3 id="modalTeam1">Team 1</h3>
                <div class="score" id="modalScore1" style="font-size: 32px; margin-top: 5px; color: var(--text-primary);">0</div>
                <ul class="player-list" id="modalPlayers1"></ul>
            </div>
            <div class="vs-badge">VS</div>
            <div class="team-block">
                <h3 id="modalTeam2">Team 2</h3>
                <div class="score" id="modalScore2" style="font-size: 32px; margin-top: 5px; color: var(--text-primary);">0</div>
                <ul class="player-list" id="modalPlayers2"></ul>
            </div>
        </div>
        <div id="modalWinner" style="margin-top: 25px; font-family: 'Rajdhani', sans-serif; color: var(--teal); font-weight: 700; font-size: 18px; letter-spacing: 1px;"></div>
    </div>
</div>

</body>
<footer style="text-align: center; padding: 24px; border-top: 1px solid #1e2a38; color: #3d5468; font-size: 13px; font-weight: 500; background: #0f1318; margin-top: auto; flex-shrink: 0;">
    &copy; 2026 <span style="color: #00c2cb; font-weight: 700; font-family: 'Rajdhani', sans-serif; letter-spacing: 1px;">DiffCheck</span>. All rights reserved.
</footer>
</html>