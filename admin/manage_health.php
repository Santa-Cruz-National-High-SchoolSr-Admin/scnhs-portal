<?php
/**
 * Admin: Manage Health Records (SF8)
 * Record student BMI, vision, blood type, and nutritional status.
 */
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) { header("Location: ../login.php"); exit(); }
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 900)) {
    session_unset(); session_destroy(); header("Location: ../login.php"); exit();
} else { $_SESSION['login_time'] = time(); }

require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();
function e($v) { return htmlspecialchars($v ?? '&mdash;', ENT_QUOTES, 'UTF-8'); }

$msg = $msgType = '';

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_health'])) {
    $sid    = intval($_POST['student_id']);
    $ht     = floatval($_POST['height_cm']);
    $wt     = floatval($_POST['weight_kg']);
    $bmi    = ($ht > 0 && $wt > 0) ? round($wt / (($ht/100) ** 2), 2) : null;

    // Nutritional status
    $ns = 'N/A';
    if ($bmi) {
        if ($bmi < 18.5) $ns = 'Underweight';
        elseif ($bmi < 25) $ns = 'Normal';
        elseif ($bmi < 30) $ns = 'Overweight';
        else $ns = 'Obese';
    }

    $vl      = trim($_POST['vision_left'] ?? '');
    $vr      = trim($_POST['vision_right'] ?? '');
    $bt      = trim($_POST['blood_type'] ?? '');
    $mh      = trim($_POST['medical_history'] ?? '');
    $rb      = trim($_POST['recorded_by'] ?? ($_SESSION['username']));

    $ins = $conn->prepare("INSERT INTO health_records (student_id, height_cm, weight_kg, bmi, nutritional_status, vision_left, vision_right, blood_type, medical_history, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $ins->bind_param("idddssssss", $sid, $ht, $wt, $bmi, $ns, $vl, $vr, $bt, $mh, $rb);
    if ($ins->execute()) {
        $msg = "Health record saved. BMI = <strong>{$bmi}</strong> ({$ns})";
        $msgType = 'success';
    } else {
        $msg = "Failed to save record.";
        $msgType = 'error';
    }
    $ins->close();
}

// Search students
$search = trim($_GET['q'] ?? '');
$sql = "SELECT s.id, s.lrn, s.first_name, s.last_name, s.grade, s.strand,
    (SELECT h.bmi FROM health_records h WHERE h.student_id = s.id ORDER BY h.recorded_at DESC LIMIT 1) AS latest_bmi,
    (SELECT h.nutritional_status FROM health_records h WHERE h.student_id = s.id ORDER BY h.recorded_at DESC LIMIT 1) AS ns
    FROM students s WHERE s.status = 'approved'" .
    ($search ? " AND (s.first_name LIKE '%$search%' OR s.last_name LIKE '%$search%' OR s.lrn LIKE '%$search%')" : '') .
    " ORDER BY s.last_name ASC LIMIT 100";
$result = $conn->query($sql);
$students = [];
if ($result) while ($row = $result->fetch_assoc()) $students[] = $row;
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Health Records | Admin</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
<style>
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 100; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal { background: #111827; border: 1px solid var(--border); border-radius: 20px; padding: 32px; width: 90%; max-width: 540px; max-height: 90vh; overflow-y: auto; }
    .modal h3 { font-size: 1.1rem; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
    .ns-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: .8rem; font-weight: 600; }
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
            <div><h1>Health Records (SF8)</h1><p>Record and manage student BMI and health assessments</p></div>
        </div>
        <div class="user-profile"><i class="fas fa-user-circle"></i><span><?php echo e($_SESSION['username']); ?></span></div>
    </header>

    <?php if ($msg): ?>
    <div class="alert-msg alert-<?php echo $msgType; ?>"><i class="fas fa-<?php echo $msgType==='success'?'check-circle':'exclamation-circle'; ?>"></i><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-header">
            <div class="panel-title"><i class="fas fa-heartbeat"></i><h2>Students</h2></div>
            <form method="GET" style="display:flex;gap:8px">
                <div class="search-wrap"><i class="fas fa-search"></i><input type="text" name="q" id="searchBox" placeholder="Search..." value="<?php echo e($search); ?>"></div>
                <button type="submit" class="btn btn-primary" style="padding:10px 18px">Go</button>
            </form>
        </div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Student</th><th>Grade / Strand</th><th>Latest BMI</th><th>Nutritional Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php if (empty($students)): ?>
                <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)">No students found.</td></tr>
                <?php endif; ?>
                <?php foreach ($students as $stu):
                    $bmi = $stu['latest_bmi'];
                    $ns  = $stu['ns'];
                    $nsColor = !$ns ? '#9ca3af' : match($ns) { 'Normal'=>'#10b981','Underweight'=>'#3b82f6','Overweight'=>'#f59e0b','Obese'=>'#ef4444', default=>'#9ca3af' };
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:#fff"><?php echo e($stu['last_name'].', '.$stu['first_name']); ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted)"><?php echo e($stu['lrn']); ?></div>
                    </td>
                    <td>G<?php echo e($stu['grade']); ?> | <?php echo e($stu['strand']); ?></td>
                    <td style="font-weight:700;color:#ef4444"><?php echo $bmi ?: '&mdash;'; ?></td>
                    <td>
                        <?php if ($ns): ?>
                        <span class="ns-badge" style="background:<?php echo $nsColor; ?>22;color:<?php echo $nsColor; ?>;border:1px solid <?php echo $nsColor; ?>44"><?php echo e($ns); ?></span>
                        <?php else: ?><span style="color:var(--text-muted)">Not recorded</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" onclick="openModal(<?php echo $stu['id']; ?>, '<?php echo addslashes($stu['last_name'].', '.$stu['first_name']); ?>')"
                            class="btn btn-print"><i class="fas fa-plus"></i> Add Record</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <h3><i class="fas fa-heartbeat" style="color:#ef4444;margin-right:8px"></i>New Health Assessment &mdash; <span id="modalStudentName"></span></h3>
        <form method="POST">
            <input type="hidden" name="student_id" id="modalStudentId">
            <div class="form-row">
                <div class="form-group"><label>Height (cm)</label><input type="number" name="height_cm" class="form-control" placeholder="e.g. 165" step="0.1" min="50" max="250" required></div>
                <div class="form-group"><label>Weight (kg)</label><input type="number" name="weight_kg" class="form-control" placeholder="e.g. 58" step="0.1" min="10" max="300" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Vision (Left Eye)</label><input type="text" name="vision_left" class="form-control" placeholder="e.g. 20/20"></div>
                <div class="form-group"><label>Vision (Right Eye)</label><input type="text" name="vision_right" class="form-control" placeholder="e.g. 20/20"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Blood Type</label>
                    <select name="blood_type" class="form-control">
                        <option value="">Unknown</option>
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                        <option value="<?php echo $bt; ?>"><?php echo $bt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Recorded By</label><input type="text" name="recorded_by" class="form-control" value="<?php echo e($_SESSION['username']); ?>"></div>
            </div>
            <div class="form-group"><label>Medical History / Notes</label><textarea name="medical_history" class="form-control" rows="3" placeholder="Allergies, chronic conditions, etc. (optional)"></textarea></div>
            <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:16px"><i class="fas fa-calculator" style="color:#ef4444;margin-right:5px"></i>BMI will be automatically calculated from height and weight.</p>
            <div style="display:flex;gap:10px">
                <button type="submit" name="save_health" class="btn btn-primary" style="flex:1;padding:12px"><i class="fas fa-save"></i> Save Record</button>
                <button type="button" onclick="closeModal()" class="btn btn-danger" style="padding:12px 20px">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, name) {
    document.getElementById('modalStudentId').value = id;
    document.getElementById('modalStudentName').textContent = name;
    document.getElementById('modalOverlay').classList.add('open');
}
function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }
document.getElementById('modalOverlay').addEventListener('click', e => { if(e.target===document.getElementById('modalOverlay')) closeModal(); });
</script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
