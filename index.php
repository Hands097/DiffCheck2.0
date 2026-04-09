<?php
session_start();
include('db.php');

$newest_query = mysqli_query($conn, "SELECT * FROM tournaments WHERE is_deleted=0 ORDER BY created_at DESC LIMIT 5");
$hottest_query = mysqli_query($conn, "SELECT * FROM tournaments WHERE is_deleted=0 AND status='active' LIMIT 5");

$newest = [];
while ($t = mysqli_fetch_assoc($newest_query)) $newest[] = $t;

$hottest = [];
while ($t = mysqli_fetch_assoc($hottest_query)) $hottest[] = $t;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiffCheck – Welcome</title>
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
            --red:           #e05555;
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
            z-index: 100;
        }

        .topbar-left { display: flex; align-items: center; gap: 12px; }

        .logo-image {
            height: 36px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .topbar-right { display: flex; align-items: center; gap: 8px; }

        .btn-login {
            padding: 7px 18px;
            border-radius: 6px;
            border: 1px solid var(--border-accent);
            background: transparent;
            color: var(--text-secondary);
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase;
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
            letter-spacing: 1px; text-transform: uppercase;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-register:hover { background: var(--teal-dim); }

        /* Avatar dropdown */
        .user-menu { position: relative; }
        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--teal);
            border: 2px solid var(--teal-dim);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px; font-weight: 700;
            color: #000; cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
            user-select: none;
        }
        .user-avatar:hover { border-color: var(--teal); box-shadow: 0 0 0 3px var(--teal-glow); }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px); right: 0;
            width: 200px;
            background: var(--bg-panel);
            border: 1px solid var(--border-accent);
            border-radius: 10px;
            overflow: hidden;
            opacity: 0; pointer-events: none;
            transform: translateY(-6px);
            transition: opacity .18s ease, transform .18s ease;
            z-index: 200;
        }
        .user-menu.open .user-dropdown { opacity: 1; pointer-events: all; transform: translateY(0); }

        .dropdown-header { padding: 14px 16px 12px; border-bottom: 1px solid var(--border); }
        .dropdown-name { font-family: 'Rajdhani', sans-serif; font-size: 14px; font-weight: 700; color: var(--text-primary); }
        .dropdown-role { font-size: 11px; color: var(--teal); margin-top: 2px; }
        .dropdown-items { padding: 6px 0; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 16px;
            color: var(--text-secondary);
            text-decoration: none; font-size: 13px;
            transition: background .15s, color .15s;
        }
        .dropdown-item:hover { background: var(--teal-glow-sm); color: var(--text-primary); }
        .dropdown-item .di-icon { width: 16px; text-align: center; font-size: 14px; flex-shrink: 0; }
        .dropdown-divider { height: 1px; background: var(--border); margin: 4px 0; }
        .dropdown-item.logout { color: var(--red); }
        .dropdown-item.logout:hover { background: rgba(224,85,85,0.08); color: var(--red); }

        /* ── HERO ── */
        .hero {
            padding: 80px 32px 60px;
            text-align: center;
            max-width: 700px;
            margin: 0 auto;
        }

        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--teal-glow-sm);
            border: 1px solid var(--border-accent);
            color: var(--teal);
            font-size: 11px; font-weight: 600;
            letter-spacing: 2px; text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 20px;
            margin-bottom: 24px;
        }

        .hero h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: 52px; font-weight: 700;
            line-height: 1.05;
            letter-spacing: 1px;
            color: var(--text-primary);
            margin-bottom: 16px;
            text-transform: uppercase;
        }
        .hero h1 span { color: var(--teal); }

        .hero p {
            color: var(--text-secondary);
            font-size: 15px; line-height: 1.7;
            margin-bottom: 32px;
        }

        .hero-btns { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }

        .btn-hero-primary {
            background: var(--teal);
            color: #000;
            border: none;
            border-radius: 7px;
            padding: 12px 32px;
            font-family: 'Rajdhani', sans-serif;
            font-size: 14px; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-hero-primary:hover { background: var(--teal-dim); }

        .btn-hero-secondary {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-accent);
            border-radius: 7px;
            padding: 12px 32px;
            font-family: 'Rajdhani', sans-serif;
            font-size: 14px; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase;
            text-decoration: none;
            transition: border-color .15s, color .15s;
        }
        .btn-hero-secondary:hover { border-color: var(--teal); color: var(--teal); }

        /* ── PAGE CONTENT ── */
        .page { max-width: 1100px; margin: 0 auto; padding: 0 32px 60px; }

        /* ── SECTION HEADER ── */
        .section-head {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 18px;
        }
        .section-head-left { display: flex; align-items: center; gap: 12px; }
        .section-icon {
            width: 32px; height: 32px;
            background: var(--teal-glow-sm);
            border: 1px solid var(--border-accent);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
        }
        .section-title {
            font-family: 'Rajdhani', sans-serif;
            font-size: 18px; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase;
            color: var(--text-primary);
        }
        .section-title span { color: var(--teal); }

        .section-view-all {
            color: var(--teal);
            text-decoration: none;
            font-size: 12px; font-weight: 600;
            letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
            transition: opacity .15s;
        }
        .section-view-all:hover { opacity: 0.7; }

        /* ── CAROUSEL ── */
        .carousel-wrap {
            position: relative;
            margin-bottom: 48px;
        }

        .carousel-track-outer {
            overflow: hidden;
            border-radius: 10px;
        }

        .carousel-track {
            display: flex;
            gap: 16px;
            transition: transform .35s cubic-bezier(.4,0,.2,1);
            will-change: transform;
        }

        /* ── TOURNAMENT CARD ── */
        .t-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            flex: 0 0 calc(33.333% - 11px);
            min-width: 0;
            position: relative;
            overflow: hidden;
            transition: border-color .2s, background .2s, transform .2s;
        }
        .t-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 3px; height: 100%;
            background: var(--border);
            transition: background .2s;
        }
        .t-card:hover { background: var(--bg-card-hover); border-color: var(--border-accent); transform: translateY(-2px); }
        .t-card:hover::before { background: var(--teal); }
        .t-card.status-pending::before  { background: var(--status-open); }
        .t-card.status-active::before   { background: var(--status-active); }
        .t-card.status-completed::before{ background: var(--status-done); }

        .t-card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 10px; }

        .t-name {
            font-family: 'Rajdhani', sans-serif;
            font-size: 15px; font-weight: 700;
            color: var(--text-primary);
            letter-spacing: 0.5px; line-height: 1.2;
            padding-right: 8px;
        }

        .status-chip {
            font-size: 10px; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase;
            padding: 3px 9px; border-radius: 20px; flex-shrink: 0;
        }
        .chip-pending   { background: rgba(0,194,160,0.12);  color: var(--status-open);   border: 1px solid rgba(0,194,160,0.3); }
        .chip-active    { background: rgba(79,163,224,0.12); color: var(--status-active); border: 1px solid rgba(79,163,224,0.3); }
        .chip-completed { background: rgba(90,106,120,0.12); color: var(--status-done);   border: 1px solid rgba(90,106,120,0.3); }

        .t-meta { display: flex; gap: 14px; margin-bottom: 14px; flex-wrap: wrap; }
        .t-meta-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--text-secondary); }
        .t-meta-item .lbl { color: var(--text-muted); font-size: 11px; }

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
        /* Generated / live bracket state — matches tournaments.php */
        .bracket-preview.generated {
            background: rgba(0, 194, 203, 0.04);
            border: 1px solid var(--border-accent);
            color: var(--teal);
            padding-top: 10px;
            height: 100px;
        }
        .bracket-preview.generated svg {
            opacity: 1;
            stroke: var(--teal);
        }

        .btn-card {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            width: 100%;
            background: transparent;
            border: 1px solid var(--teal);
            color: var(--teal);
            font-family: 'Rajdhani', sans-serif;
            font-size: 12px; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase;
            padding: 7px;
            border-radius: 6px;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-card:hover { background: var(--teal-glow); }
        .btn-card.solid { background: var(--teal); color: #000; }
        .btn-card.solid:hover { background: var(--teal-dim); }

        /* Carousel nav arrows */
        .carousel-nav {
            display: flex; gap: 8px;
            align-items: center;
        }
        .carousel-btn {
            width: 32px; height: 32px;
            background: var(--bg-card);
            border: 1px solid var(--border-accent);
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 14px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: background .15s, color .15s, border-color .15s;
            user-select: none;
        }
        .carousel-btn:hover { background: var(--teal-glow-sm); color: var(--teal); border-color: var(--teal); }
        .carousel-btn:disabled { opacity: 0.3; cursor: default; }

        /* Carousel dots */
        .carousel-dots { display: flex; gap: 6px; align-items: center; }
        .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--border-accent);
            transition: background .2s, transform .2s;
            cursor: pointer;
        }
        .dot.active { background: var(--teal); transform: scale(1.3); }

        /* Empty state */
        .empty-carousel {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }
        .empty-carousel .icon { font-size: 28px; margin-bottom: 10px; }

        /* ── DIVIDER ── */
        .section-divider {
            height: 1px;
            background: var(--border);
            margin: 0 0 48px;
            opacity: 0.5;
        }

        @media (max-width: 900px) {
            .t-card { flex: 0 0 calc(50% - 8px); }
            .hero h1 { font-size: 36px; }
        }
        @media (max-width: 600px) {
            .t-card { flex: 0 0 100%; }
            .topbar { padding: 0 16px; }
            .page { padding: 0 16px 40px; }
            .hero { padding: 50px 16px 40px; }
            .hero h1 { font-size: 28px; }
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
        <img src="pic/DiffcheckLogoNoBG.png" alt="DiffCheck Logo" class="logo-image">
    </div>

    <div class="topbar-right">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="login.php" class="btn-login">Sign In</a>
            <a href="register.php" class="btn-register">Register</a>
        <?php else: ?>
            <div class="user-menu" id="userMenu">
                <div class="user-avatar" id="avatarBtn">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)); ?>
                </div>
                <div class="user-dropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-name"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
                        <div class="dropdown-role">Coach / Manager</div>
                    </div>
                    <div class="dropdown-items">
                        <a href="<?php echo $_SESSION['role'] === 'manager' ? 'manager_dashboard.php' : 'organizer_dashboard.php'; ?>" class="dropdown-item">
                            <span class="di-icon">📊</span> Dashboard
                        </a>
                        <div class="dropdown-divider"></div>
                        <a onclick="document.getElementById('signout-modal').classList.add('active')" class="dropdown-item logout" style="cursor:pointer;">
                            <span class="di-icon">⏻</span> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>

<div class="hero">
    <div class="hero-eyebrow">🎮 Esports Tournament Platform</div>
    <h1>Find & Join the Best<br><span>Esports Tournaments</span></h1>
    <p>Compete against the best, track your progress, and climb the ranks across Mobile Legends, Valorant, Wild Rift, and more.</p>
    <div class="hero-btns">
        <a href="tournaments.php" class="btn-hero-primary">Browse Tournaments</a>
        <?php if (!isset($_SESSION['user_id'])): ?>
        <a href="register.php" class="btn-hero-secondary">Create Account</a>
        <?php endif; ?>
    </div>
</div>

<div class="page">

    <!-- ── NEWEST TOURNAMENTS ── -->
    <div class="section-head">
        <div class="section-head-left">
            <div class="section-icon">🆕</div>
            <div class="section-title">Newest <span>Tournaments</span></div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;">
            <div class="carousel-dots" id="newestDots"></div>
            <div class="carousel-nav">
                <button class="carousel-btn" id="newestPrev">◀</button>
                <button class="carousel-btn" id="newestNext">▶</button>
            </div>
            <a href="tournaments.php" class="section-view-all">View All →</a>
        </div>
    </div>

    <?php if (count($newest) > 0): ?>
    <div class="carousel-wrap">
        <div class="carousel-track-outer">
            <div class="carousel-track" id="newestTrack">
                <?php foreach ($newest as $t): ?>
                <div class="t-card status-<?php echo $t['status']; ?>">
                    <div class="t-card-top">
                        <div class="t-name"><?php echo htmlspecialchars($t['name']); ?></div>
                        <span class="status-chip chip-<?php echo $t['status']; ?>"><?php echo strtoupper($t['status']); ?></span>
                    </div>

                    <?php if ($t['status'] === 'pending'): ?>
                        <div class="bracket-preview">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke-dasharray="4 4"></rect>
                            </svg>
                            <span class="bp-text">Awaiting Bracket</span>
                        </div>
                    <?php else: ?>
                        <div class="bracket-preview generated">
                            <svg viewBox="0 0 100 50" style="width: 120px; height: 50px; margin-bottom: 6px;">
                                <path d="M 22 5 h 9 v 5 h 9"  fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 22 15 h 9 v -5"    fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 22 35 h 9 v 5 h 9" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 22 45 h 9 v -5"    fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 60 10 h 9 v 15 h 9" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                                <path d="M 60 40 h 9 v -15"   fill="none" stroke="var(--border-accent)" stroke-width="1"/>

                                <rect x="2"  y="2"  width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                <rect x="2"  y="12" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                <rect x="2"  y="32" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                <rect x="2"  y="42" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>

                                <rect x="40" y="7"  width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                                <rect x="40" y="37" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>

                                <rect x="78" y="22" width="20" height="6" rx="1" fill="var(--teal-glow-sm)" stroke="var(--teal)" stroke-width="1"/>
                            </svg>
                            <span class="bp-text">BRACKET LIVE</span>
                        </div>
                    <?php endif; ?>

                    <div class="t-meta">
                        <div class="t-meta-item"><span class="lbl">Game</span><?php echo htmlspecialchars($t['game']); ?></div>
                        <div class="t-meta-item"><span class="lbl">Teams</span><?php echo $t['max_teams']; ?></div>
                    </div>

                    <a href="view_tournament.php?id=<?php echo $t['id']; ?>" class="btn-card <?php echo $t['status'] === 'pending' ? 'solid' : ''; ?>">
                        <?php echo $t['status'] === 'pending' ? '✚ Register' : '◈ View Bracket'; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-carousel" style="margin-bottom:48px;">
        <div class="icon">🏆</div>
        <p>No tournaments available yet.</p>
    </div>
    <?php endif; ?>

    <div class="section-divider"></div>

    <!-- ── HOTTEST ONGOING ── -->
    <div class="section-head">
        <div class="section-head-left">
            <div class="section-icon">🔥</div>
            <div class="section-title">Hottest <span>Ongoing</span></div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;">
            <div class="carousel-dots" id="hottestDots"></div>
            <div class="carousel-nav">
                <button class="carousel-btn" id="hottestPrev">◀</button>
                <button class="carousel-btn" id="hottestNext">▶</button>
            </div>
            <a href="tournaments.php?status=active" class="section-view-all">View All →</a>
        </div>
    </div>

    <?php if (count($hottest) > 0): ?>
    <div class="carousel-wrap">
        <div class="carousel-track-outer">
            <div class="carousel-track" id="hottestTrack">
                <?php foreach ($hottest as $t): ?>
                <div class="t-card status-active">
                    <div class="t-card-top">
                        <div class="t-name"><?php echo htmlspecialchars($t['name']); ?></div>
                        <span class="status-chip chip-active">LIVE</span>
                    </div>
                    <!-- All hottest tournaments are active → always show the live bracket diagram -->
                    <div class="bracket-preview generated">
                        <svg viewBox="0 0 100 50" style="width: 120px; height: 50px; margin-bottom: 6px;">
                            <path d="M 22 5 h 9 v 5 h 9"  fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                            <path d="M 22 15 h 9 v -5"    fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                            <path d="M 22 35 h 9 v 5 h 9" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                            <path d="M 22 45 h 9 v -5"    fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                            <path d="M 60 10 h 9 v 15 h 9" fill="none" stroke="var(--border-accent)" stroke-width="1"/>
                            <path d="M 60 40 h 9 v -15"   fill="none" stroke="var(--border-accent)" stroke-width="1"/>

                            <rect x="2"  y="2"  width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                            <rect x="2"  y="12" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                            <rect x="2"  y="32" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                            <rect x="2"  y="42" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>

                            <rect x="40" y="7"  width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>
                            <rect x="40" y="37" width="20" height="6" rx="1" fill="var(--bg-panel)" stroke="var(--border-accent)" stroke-width="1"/>

                            <rect x="78" y="22" width="20" height="6" rx="1" fill="var(--teal-glow-sm)" stroke="var(--teal)" stroke-width="1"/>
                        </svg>
                        <span class="bp-text">BRACKET LIVE</span>
                    </div>
                    <div class="t-meta">
                        <div class="t-meta-item"><span class="lbl">Game</span><?php echo htmlspecialchars($t['game']); ?></div>
                        <div class="t-meta-item"><span class="lbl">Teams</span><?php echo $t['max_teams']; ?></div>
                    </div>
                    <a href="view_tournament.php?id=<?php echo $t['id']; ?>" class="btn-card">◈ View Bracket</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-carousel" style="margin-bottom:48px;">
        <div class="icon">🔥</div>
        <p>No active tournaments right now.</p>
    </div>
    <?php endif; ?>

</div>

<script>
    // Avatar dropdown
    const menu = document.getElementById('userMenu');
    const btn  = document.getElementById('avatarBtn');
    if (btn) {
        btn.addEventListener('click', e => { e.stopPropagation(); menu.classList.toggle('open'); });
        document.addEventListener('click', () => menu.classList.remove('open'));
    }

    // Carousel factory
    function initCarousel(trackId, prevId, nextId, dotsId) {
        const track  = document.getElementById(trackId);
        if (!track) return;
        const cards  = track.querySelectorAll('.t-card');
        const prev   = document.getElementById(prevId);
        const next   = document.getElementById(nextId);
        const dotsEl = document.getElementById(dotsId);

        if (cards.length === 0) return;

        const perView = window.innerWidth < 600 ? 1 : window.innerWidth < 900 ? 2 : 3;
        const pages   = Math.ceil(cards.length / perView);
        let current   = 0;

        // Build dots
        for (let i = 0; i < pages; i++) {
            const d = document.createElement('div');
            d.className = 'dot' + (i === 0 ? ' active' : '');
            d.addEventListener('click', () => goTo(i));
            dotsEl.appendChild(d);
        }

        function goTo(idx) {
            current = Math.max(0, Math.min(idx, pages - 1));
            const cardW  = cards[0].offsetWidth + 16;
            track.style.transform = `translateX(-${current * perView * cardW}px)`;
            dotsEl.querySelectorAll('.dot').forEach((d, i) => d.classList.toggle('active', i === current));
            prev.disabled = current === 0;
            next.disabled = current === pages - 1;
        }

        prev.addEventListener('click', () => goTo(current - 1));
        next.addEventListener('click', () => goTo(current + 1));
        goTo(0);
    }

    initCarousel('newestTrack',  'newestPrev',  'newestNext',  'newestDots');
    initCarousel('hottestTrack', 'hottestPrev', 'hottestNext', 'hottestDots');
</script>

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
<footer style="text-align: center; padding: 24px; border-top: 1px solid #1e2a38; color: #3d5468; font-size: 13px; font-weight: 500; background: #0f1318; margin-top: auto; flex-shrink: 0;">
    &copy; 2026 <span style="color: #00c2cb; font-weight: 700; font-family: 'Rajdhani', sans-serif; letter-spacing: 1px;">DiffCheck</span>. All rights reserved.
</footer>
</html>