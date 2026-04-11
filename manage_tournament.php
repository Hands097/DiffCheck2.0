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
    echo "Tournament not found or you do not have permission.";
    exit();
}
$tournament = mysqli_fetch_assoc($check_query);

// --- LOGIC: Tournament Actions ---
if (isset($_POST['edit_tournament'])) {
    $new_name = mysqli_real_escape_string($conn, $_POST['tourna_name']);
    $new_game = mysqli_real_escape_string($conn, $_POST['game']);
    $new_max = (int)$_POST['max_teams'];
    mysqli_query($conn, "UPDATE tournaments SET name='$new_name', game='$new_game', max_teams='$new_max' WHERE id='$tournament_id'");
    $_SESSION['system_message'] = "Tournament details updated!";
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
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}
if (isset($_POST['reopen_tournament'])) {
    $check_matches = mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id='$tournament_id'");
    if (mysqli_num_rows($check_matches) > 0) {
        $_SESSION['system_message'] = "Error: You cannot re-open registration because the bracket has already been generated.";
    } else {
        mysqli_query($conn, "UPDATE tournaments SET status='pending' WHERE id='$tournament_id'");
        $_SESSION['system_message'] = "Registration re-opened! The public can now apply again.";
    }
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}
if (isset($_POST['finish_tournament'])) {
    mysqli_query($conn, "UPDATE tournaments SET status='completed' WHERE id='$tournament_id'");
    $_SESSION['system_message'] = "Tournament Officially Completed! The Champion has been crowned.";
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

if (isset($_POST['action_squad'])) {
    $reg_id = (int)$_POST['registration_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['action_type']);
    mysqli_query($conn, "UPDATE registrations SET status='$new_status' WHERE id='$reg_id' AND tournament_id='$tournament_id'");
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

// --- UPGRADED LOGIC: Generate Bracket with Auto-Byes ---
if (isset($_POST['generate_bracket'])) {
    $accepted_query = mysqli_query($conn, "SELECT id FROM registrations WHERE tournament_id='$tournament_id' AND status='accepted'");
    $teams = [];
    while($row = mysqli_fetch_assoc($accepted_query)) { $teams[] = $row['id']; }
    shuffle($teams);
    
    $match_number = 1;
    for ($i = 0; $i < count($teams); $i += 2) {
        $t1 = $teams[$i];
        
        if (isset($teams[$i+1])) {
            // Normal Match (2 Teams)
            $t2 = $teams[$i+1];
            mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, team1_id, team2_id) VALUES ('$tournament_id', 1, '$match_number', $t1, $t2)");
        } else {
            // BYE Match (Only 1 Team) - Auto Complete and advance!
            mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, team1_id, winner_id, status) VALUES ('$tournament_id', 1, '$match_number', $t1, $t1, 'completed')");
            
            // Push them immediately to Round 2
            $next_round = 2;
            $next_match_num = ceil($match_number / 2); 
            $check_next = mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id='$tournament_id' AND round_number='$next_round' AND match_number='$next_match_num'");
            
            if (mysqli_num_rows($check_next) > 0) {
                $next_match = mysqli_fetch_assoc($check_next);
                $next_id = $next_match['id'];
                if ($match_number % 2 != 0) {
                    mysqli_query($conn, "UPDATE matches SET team1_id='$t1' WHERE id='$next_id'");
                } else {
                    mysqli_query($conn, "UPDATE matches SET team2_id='$t1' WHERE id='$next_id'");
                }
            } else {
                if ($match_number % 2 != 0) {
                    mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, team1_id) VALUES ('$tournament_id', '$next_round', '$next_match_num', '$t1')");
                } else {
                    mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, team2_id) VALUES ('$tournament_id', '$next_round', '$next_match_num', '$t1')");
                }
            }
        }
        $match_number++;
    }
    $_SESSION['system_message'] = "Bracket Generated! Byes have been automatically advanced.";
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

if (isset($_POST['submit_winner'])) {
    $match_id = (int)$_POST['match_id'];
    $winner_id = (int)$_POST['winner_id'];
    $round_num = (int)$_POST['round_number'];
    $match_num = (int)$_POST['match_number'];

    mysqli_query($conn, "UPDATE matches SET winner_id='$winner_id', status='completed' WHERE id='$match_id'");

    $next_round = $round_num + 1;
    $next_match_num = ceil($match_num / 2); 
    
    $check_next = mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id='$tournament_id' AND round_number='$next_round' AND match_number='$next_match_num'");
    
    if (mysqli_num_rows($check_next) > 0) {
        $next_match = mysqli_fetch_assoc($check_next);
        $next_id = $next_match['id'];
        if ($match_num % 2 != 0) {
            mysqli_query($conn, "UPDATE matches SET team1_id='$winner_id' WHERE id='$next_id'");
        } else {
            mysqli_query($conn, "UPDATE matches SET team2_id='$winner_id' WHERE id='$next_id'");
        }
    } else {
        $check_sibling_matches = mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id='$tournament_id' AND round_number='$round_num'");
        if (mysqli_num_rows($check_sibling_matches) > 1) {
            if ($match_num % 2 != 0) {
                mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, team1_id) VALUES ('$tournament_id', '$next_round', '$next_match_num', '$winner_id')");
            } else {
                mysqli_query($conn, "INSERT INTO matches (tournament_id, round_number, match_number, team2_id) VALUES ('$tournament_id', '$next_round', '$next_match_num', '$winner_id')");
            }
        }
    }
    $_SESSION['system_message'] = "Winner advanced!";
    header("Location: manage_tournament.php?id=$tournament_id");
    exit();
}

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

$champion_name = null;
$r1_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM matches WHERE tournament_id='$tournament_id' AND round_number=1");
$r1_matches = mysqli_fetch_assoc($r1_query)['count'];
$final_round_number = $r1_matches > 0 ? ceil(log($r1_matches * 2, 2)) : 0;

if ($final_round_number > 0) {
    $final_match_query = mysqli_query($conn, "SELECT m.winner_id, r.squad_name FROM matches m LEFT JOIN registrations r ON m.winner_id = r.id WHERE m.tournament_id='$tournament_id' AND m.round_number='$final_round_number' AND m.status='completed'");
    if (mysqli_num_rows($final_match_query) > 0) {
        $final_match = mysqli_fetch_assoc($final_match_query);
        $champion_name = $final_match['squad_name'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage: <?php echo htmlspecialchars($tournament['name']); ?></title>
</head>
<body>

    <a href="organizer_dashboard.php">&larr; Back to Dashboard</a>
    <h1>Manage Tournament: <?php echo htmlspecialchars($tournament['name']); ?></h1>
    <p><strong>Status:</strong> <span style="color: <?php echo $tournament['status'] == 'pending' ? 'green' : ($tournament['status'] == 'active' ? 'blue' : 'gray'); ?>;"><?php echo strtoupper($tournament['status']); ?></span></p>

    <?php if (isset($_SESSION['system_message'])): ?>
        <div style='background-color: #ffffcc; padding: 10px; border: 1px solid #cccc00; margin-bottom: 15px;'>
            <strong>Notice: </strong><?php echo htmlspecialchars($_SESSION['system_message']); unset($_SESSION['system_message']); ?>
        </div>
    <?php endif; ?>

    <hr>
    <h2>Tournament Actions</h2>
    <form method="POST" style="display:inline;"><button type="submit" name="start_tournament" <?php if($tournament['status'] == 'active' || $tournament['status'] == 'completed') echo 'disabled'; ?>>Start Tournament Now</button></form>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');"><button type="submit" name="reopen_tournament" <?php if($tournament['status'] == 'pending' || mysqli_num_rows($matches_query) > 0) echo 'disabled'; ?>>Re-open Registration</button></form>
    <?php if ($champion_name && $tournament['status'] !== 'completed'): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('End the tournament and permanently crown the champion?');"><button type="submit" name="finish_tournament" style="background-color: gold; font-weight: bold; cursor: pointer;">🏆 Crown Champion & Finish</button></form>
    <?php endif; ?>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this tournament?');"><button type="submit" name="soft_delete" style="color: red; float: right;">Delete Tournament</button></form>

    <hr>
    <h2>Edit Details</h2>
    <form method="POST">
        <label>Name:</label> <input type="text" name="tourna_name" value="<?php echo htmlspecialchars($tournament['name']); ?>" required>
        <label>Game:</label>
        <select name="game" required>
            <option value="Mobile Legends" <?php if($tournament['game'] == 'Mobile Legends') echo 'selected'; ?>>Mobile Legends</option>
            <option value="Wild Rift" <?php if($tournament['game'] == 'Wild Rift') echo 'selected'; ?>>Wild Rift</option>
            <option value="Honor of Kings" <?php if($tournament['game'] == 'Honor of Kings') echo 'selected'; ?>>Honor of Kings</option>
            <option value="Valorant" <?php if($tournament['game'] == 'Valorant') echo 'selected'; ?>>Valorant</option>
        </select>
        <label>Max Teams:</label> <input type="number" name="max_teams" value="<?php echo $tournament['max_teams']; ?>" min="2" max="16" required>
        <button type="submit" name="edit_tournament" <?php if($tournament['status'] == 'completed') echo 'disabled'; ?>>Save</button>
    </form>

    <hr>
    <h2>Manage Squads (<?php echo mysqli_num_rows($squads_query); ?> Applied)</h2>
    <table border="1" cellpadding="5">
        <tr><th>Squad Name</th><th>Status</th><th>Action</th></tr>
        <?php while ($squad = mysqli_fetch_assoc($squads_query)): ?>
        <tr>
            <td><?php echo htmlspecialchars($squad['squad_name']); ?></td>
            <td><?php echo ucfirst($squad['status']); ?></td>
            <td>
                <?php if ($tournament['status'] == 'pending'): ?>
                    <form method='POST' style='display:inline;'><input type='hidden' name='registration_id' value='<?php echo $squad['id']; ?>'><input type='hidden' name='action_type' value='accepted'><button type='submit' name='action_squad'>Accept</button></form>
                    <form method='POST' style='display:inline;'><input type='hidden' name='registration_id' value='<?php echo $squad['id']; ?>'><input type='hidden' name='action_type' value='rejected'><button type='submit' name='action_squad'>Reject</button></form>
                <?php else: echo "<em>Live</em>"; endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <hr>
    <h2>Bracket Management</h2>
    <?php if ($champion_name): ?>
        <div style="background-color: #ffd700; padding: 20px; text-align: center; border: 3px solid #daa520; border-radius: 10px; margin-bottom: 20px;">
            <h1 style="margin: 0; color: #b8860b;">👑 TOURNAMENT CHAMPION 👑</h1>
            <h2 style="margin: 10px 0 0 0; font-size: 2em;"><?php echo htmlspecialchars($champion_name); ?></h2>
        </div>
    <?php endif; ?>

    <?php if ($tournament['status'] === 'active' && mysqli_num_rows($matches_query) == 0): ?>
        <p>The tournament is active, but no bracket exists yet.</p>
        <form method="POST"><button type="submit" name="generate_bracket" style="padding: 10px; font-weight: bold;">Make Bracket (Random Matchmaking)</button></form>
    <?php elseif (mysqli_num_rows($matches_query) > 0): ?>
        <?php 
        $current_round = 0;
        mysqli_data_seek($matches_query, 0); 
        while ($match = mysqli_fetch_assoc($matches_query)): 
            if ($current_round != $match['round_number']) {
                $current_round = $match['round_number'];
                echo "<h3>Round $current_round</h3>";
            }
        ?>
            <div style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; width: 400px; background: #f9f9f9;">
                <strong>Match <?php echo $match['match_number']; ?></strong><br><br>
                
                <?php 
                $team1 = $match['team1_name'] ? htmlspecialchars($match['team1_name']) : "<em>TBD / Bye</em>";
                $team2 = $match['team2_name'] ? htmlspecialchars($match['team2_name']) : "<em>TBD / Bye</em>";
                echo "$team1 <strong>VS</strong> $team2"; 
                ?>
                <br><br>
                
                <?php if ($match['status'] == 'pending' && $match['team1_id'] && $match['team2_id'] && $tournament['status'] !== 'completed'): ?>
                    <form method="POST">
                        <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                        <input type="hidden" name="round_number" value="<?php echo $match['round_number']; ?>">
                        <input type="hidden" name="match_number" value="<?php echo $match['match_number']; ?>">
                        <label>Select Winner: </label>
                        <select name="winner_id" required>
                            <option value="" disabled selected>Choose...</option>
                            <option value="<?php echo $match['team1_id']; ?>"><?php echo htmlspecialchars($match['team1_name']); ?></option>
                            <option value="<?php echo $match['team2_id']; ?>"><?php echo htmlspecialchars($match['team2_name']); ?></option>
                        </select>
                        <button type="submit" name="submit_winner">Submit Score</button>
                    </form>
                <?php elseif ($match['status'] == 'completed'): ?>
                    <p style="color: green;"><strong>Match Completed</strong></p>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

</body>
</html>