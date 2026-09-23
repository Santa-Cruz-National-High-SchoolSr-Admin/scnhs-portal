<?php
/**
 * Admin: Manage SSLG Candidates & View Election Results
 */
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) { header("Location: ../login.php"); exit(); }
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 900)) {
    session_unset(); session_destroy(); header("Location: ../login.php"); exit();
} else { $_SESSION['login_time'] = time(); }

require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();
function e($v) { return htmlspecialchars($v ?? '&mdash;�', ENT_QUOTES, 'UTF-8'); }

$msg = $msgType = '';

// Add candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_candidate'])) {
    $pos    = trim($_POST['position']);
    $name   = trim($_POST['full_name']);
    $grade  = trim($_POST['grade_level'] ?? '');
    $strand = trim($_POST['strand'] ?? '');
    $plat   = trim($_POST['platform'] ?? '');
    if ($pos && $name) {
        $ins = $conn->prepare("INSERT INTO sslg_candidates (position, full_name, grade_level, strand, platform) VALUES (?,?,?,?,?)");
        $ins->bind_param("sssss", $pos, $name, $grade, $strand, $plat);
        if ($ins->execute()) { $msg = "Candidate added!"; $msgType = 'success'; }
        else { $msg = "Failed to add candidate."; $msgType = 'error'; }
        $ins->close();
    }
}

// Delete candidate
if (isset($_GET['delete_candidate'])) {
    $id = intval($_GET['delete_candidate']);
    $conn->query("DELETE FROM sslg_candidates WHERE id = $id");
    $conn->query("DELETE FROM sslg_votes WHERE candidate_id = $id");
    $msg = "Candidate removed."; $msgType = 'success';
}

// Fetch candidates with vote counts
$candidates = [];
$res = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM sslg_votes v WHERE v.candidate_id = c.id) as vote_count FROM sslg_candidates c ORDER BY c.position, vote_count DESC");
if ($res) while ($row = $res->fetch_assoc()) $candidates[$row['position']][] = $row;

// Total voters
$totalVoters = $conn->query("SELECT COUNT(DISTINCT student_id) FROM sslg_votes")->fetch_row()[0] ?? 0;
$totalStudents = $conn->query("SELECT COUNT(*) FROM students WHERE status='approved'")->fetch_row()[0] ?? 1;

$conn->close();

$positions = ['President','Vice President','Secretary','Treasurer','Auditor','Public Information Officer','Business Manager','Muse','Escort'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SSLG Management | Admin</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
<style>
    .tabs { display: flex; gap: 0; border-bottom: 1px solid var(--border); margin-bottom: 28px; }
    .tab { padding: 12px 24px; cursor: pointer; font-weight: 600; color: var(--text-muted); border-bottom: 2px solid transparent; transition: all .2s; }
    .tab.active { color: #6366f1; border-bottom-color: #6366f1; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .add-form { background: rgba(0,0,0,.15); border: 1px solid var(--border); border-radius: 14px; padding: 24px; margin-bottom: 24px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }

    .position-block { margin-bottom: 32px; }
    .position-heading { font-size: 1rem; font-weight: 700; color: #6366f1; padding: 10px 0; border-bottom: 1px solid rgba(99,102,241,0.2); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
    .candidate-row { display: flex; align-items: center; gap: 14px; padding: 12px 16px; background: rgba(0,0,0,.15); border-radius: 10px; margin-bottom: 8px; }
    .cand-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg,#6366f1,#8b5cf6); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 1rem; flex-shrink: 0; }
    .cand-name { font-weight: 600; color: #fff; flex: 1; }
    .cand-meta { font-size: .8rem; color: var(--text-muted); }
    .vote-bar-wrap { width: 120px; flex-shrink: 0; }
    .vote-bar-bg { height: 6px; border-radius: 100px; background: rgba(255,255,255,.08); }
    .vote-bar-fill { height: 100%; border-radius: 100px; background: #6366f1; }
    .vote-count { font-size: .82rem; font-weight: 700; color: #6366f1; text-align: right; margin-bottom: 3px; }
    .del-btn { color: #ef4444; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.2); padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: .82rem; font-weight: 600; transition: all .2s; }
    .del-btn:hover { background: #ef4444; color: #fff; }
    .turnout-bar { height: 12px; background: rgba(255,255,255,.06); border-radius: 100px; overflow: hidden; margin-top: 8px; }
    .turnout-fill { height: 100%; background: linear-gradient(90deg,#6366f1,#8b5cf6); border-radius: 100px; }
</style>
</head>
<body>
<aside class="sidebar" id="sidebar">
        <?php include 'includes/sidebar.php'; ?>
    </aside>
<main class="main-content">
    <header class="header">
        <div style="display:flex;align-items:center;gap:16px">
            <button type="button" class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
            <div><h1>SSLG Management</h1><p>Manage candidates and view live election results</p></div>
        </div>
        <div class="user-profile"><i class="fas fa-user-circle"></i><span><?php echo e($_SESSION['username']); ?></span></div>
    </header>

    <!-- Voter Turnout -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-purple"><i class="fas fa-vote-yea"></i></div>
            <div class="stat-info"><h3><?php echo $totalVoters; ?></h3><p>Students Voted</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-blue"><i class="fas fa-users"></i></div>
            <div class="stat-info"><h3><?php echo $totalStudents; ?></h3><p>Total Enrolled</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-green"><i class="fas fa-percentage"></i></div>
            <div class="stat-info">
                <h3><?php echo $totalStudents > 0 ? round(($totalVoters/$totalStudents)*100) : 0; ?>%</h3>
                <p>Voter Turnout</p>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert-msg alert-<?php echo $msgType; ?>"><i class="fas fa-check-circle"></i><?php echo e($msg); ?></div>
    <?php endif; ?>

    <div class="panel">
        <div class="tabs">
            <div class="tab active" onclick="switchTab('candidates')">&#128100; Candidates</div>
            <div class="tab" onclick="switchTab('results')">&#128202; Live Results</div>
        </div>

        <!-- Candidates Tab -->
        <div class="tab-content active" id="tab-candidates">
            <!-- Add Form -->
            <div class="add-form">
                <h3 style="margin-bottom:18px;font-size:1rem"><i class="fas fa-plus-circle" style="color:#6366f1;margin-right:8px"></i>Add New Candidate</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Position</label>
                            <select name="position" class="form-control" required>
                                <option value="">Select position...</option>
                                <?php foreach ($positions as $p): ?>
                                <option value="<?php echo e($p); ?>"><?php echo e($p); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" placeholder="Full name of candidate" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Grade Level</label>
                            <select name="grade_level" class="form-control">
                                <option value="">Any</option>
                                <option value="11">Grade 11</option>
                                <option value="12">Grade 12</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Strand</label>
                            <input type="text" name="strand" class="form-control" placeholder="e.g. HUMSS, STEM">
                        </div>
                    </div>
                    <div class="form-group"><label>Platform / Campaign Promise</label><textarea name="platform" class="form-control" rows="2" placeholder="Brief platform statement..."></textarea></div>
                    <button type="submit" name="add_candidate" class="btn btn-primary"><i class="fas fa-plus"></i> Add Candidate</button>
                </form>
            </div>

            <!-- Candidates List -->
            <?php if (empty($candidates)): ?>
            <div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-user-times" style="font-size:3rem;opacity:.2;display:block;margin-bottom:14px"></i>No candidates yet.</div>
            <?php else: ?>
            <?php foreach ($candidates as $pos => $cands): ?>
            <div class="position-block">
                <div class="position-heading"><i class="fas fa-user-tie"></i><?php echo e($pos); ?> <span style="color:var(--text-muted);font-weight:400;font-size:.85rem">(<?php echo count($cands); ?> candidate<?php echo count($cands)>1?'s':''; ?>)</span></div>
                <?php foreach ($cands as $c): ?>
                <div class="candidate-row">
                    <div class="cand-avatar"><?php echo strtoupper(substr($c['full_name'],0,1)); ?></div>
                    <div style="flex:1">
                        <div class="cand-name"><?php echo e($c['full_name']); ?></div>
                        <div class="cand-meta"><?php echo $c['grade_level'] ? 'G'.$c['grade_level'] : ''; ?><?php echo $c['strand'] ? ' | '.$c['strand'] : ''; ?></div>
                    </div>
                    <a href="?delete_candidate=<?php echo $c['id']; ?>" class="del-btn" onclick="return confirm('Remove this candidate?')"><i class="fas fa-trash"></i></a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Results Tab -->
        <div class="tab-content" id="tab-results">
            <?php if (empty($candidates)): ?>
            <div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-chart-bar" style="font-size:3rem;opacity:.2;display:block;margin-bottom:14px"></i>No candidates &mdash;� no results to show.</div>
            <?php else: ?>
            <?php foreach ($candidates as $pos => $cands):
                $maxVotes = max(array_column($cands, 'vote_count') ?: [1]);
                $maxVotes = $maxVotes ?: 1;
            ?>
            <div class="position-block">
                <div class="position-heading"><i class="fas fa-trophy"></i><?php echo e($pos); ?></div>
                <?php foreach ($cands as $i => $c): ?>
                <div class="candidate-row" style="<?php echo $i===0?'background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15)':''; ?>">
                    <?php if ($i===0): ?><span style="margin-right:4px;font-size:1.2rem">ðŸ¥‡</span><?php endif; ?>
                    <div class="cand-avatar"><?php echo strtoupper(substr($c['full_name'],0,1)); ?></div>
                    <div style="flex:1">
                        <div class="cand-name"><?php echo e($c['full_name']); ?></div>
                        <div class="cand-meta"><?php echo $c['strand'] ?: 'SCNHS'; ?></div>
                    </div>
                    <div class="vote-bar-wrap">
                        <div class="vote-count"><?php echo $c['vote_count']; ?> vote<?php echo $c['vote_count']!=1?'s':''; ?></div>
                        <div class="vote-bar-bg"><div class="vote-bar-fill" style="width:<?php echo round(($c['vote_count']/$maxVotes)*100); ?>%;background:<?php echo $i===0?'#6366f1':'rgba(99,102,241,0.4)'; ?>"></div></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>
function switchTab(t) {
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('tab-'+t).classList.add('active');
}
</script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
