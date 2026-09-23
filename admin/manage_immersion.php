<?php
/**
 * Admin: Manage Work Immersion (Grade 12)
 * Assign companies to students and view their hour logs.
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

// Handle company assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_immersion'])) {
    $sid     = intval($_POST['student_id']);
    $company = trim($_POST['company_name']);
    $addr    = trim($_POST['company_address']);
    $sup     = trim($_POST['supervisor_name']);
    $supcon  = trim($_POST['supervisor_contact']);
    $reqhrs  = intval($_POST['required_hours'] ?: 80);
    $start   = $_POST['start_date'] ?: null;
    $end     = $_POST['end_date'] ?: null;

    $check = $conn->prepare("SELECT id FROM immersion_records WHERE student_id = ?");
    $check->bind_param("i", $sid);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $s = $conn->prepare("UPDATE immersion_records SET company_name=?, company_address=?, supervisor_name=?, supervisor_contact=?, required_hours=?, start_date=?, end_date=?, status='ongoing' WHERE student_id=?");
        $s->bind_param("ssssissi", $company, $addr, $sup, $supcon, $reqhrs, $start, $end, $sid);
    } else {
        $s = $conn->prepare("INSERT INTO immersion_records (student_id, company_name, company_address, supervisor_name, supervisor_contact, required_hours, start_date, end_date, status) VALUES (?,?,?,?,?,?,?,'ongoing',?)");
        $s->bind_param("issssiis", $sid, $company, $addr, $sup, $supcon, $reqhrs, $start, $end);
    }
    $check->close();
    if ($s->execute()) {
        $msg = "Immersion record saved for student ID {$sid}.";
        $msgType = 'success';
    } else {
        $msg = "Failed to save record.";
        $msgType = 'error';
    }
    $s->close();
}

// Fetch grade 12 students with immersion info
$search = trim($_GET['q'] ?? '');
$sql = "SELECT s.id, s.lrn, s.first_name, s.last_name, s.strand, s.section,
    ir.company_name, ir.completed_hours, ir.required_hours, ir.status
    FROM students s
    LEFT JOIN immersion_records ir ON ir.student_id = s.id
    WHERE s.grade = 12 AND s.status = 'approved'" .
    ($search ? " AND (s.first_name LIKE '%$search%' OR s.last_name LIKE '%$search%' OR s.lrn LIKE '%$search%')" : '') .
    " ORDER BY s.last_name ASC";
$result = $conn->query($sql);
$students = [];
if ($result) while ($row = $result->fetch_assoc()) $students[] = $row;
$conn->close();

$stColors = ['not_started'=>'#9ca3af','ongoing'=>'#f59e0b','completed'=>'#10b981'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Work Immersion | Admin</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
<style>
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 100; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal { background: #111827; border: 1px solid var(--border); border-radius: 20px; padding: 32px; width: 90%; max-width: 560px; max-height: 90vh; overflow-y: auto; }
    .modal h3 { font-size: 1.1rem; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
    .progress-mini { height: 8px; border-radius: 100px; background: rgba(255,255,255,.08); overflow: hidden; margin-top: 4px; }
    .progress-mini-fill { height: 100%; border-radius: 100px; background: linear-gradient(90deg,#f59e0b,#fbbf24); }
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
            <div><h1>Work Immersion</h1><p>Assign companies and track hours for Grade 12 students</p></div>
        </div>
        <div class="user-profile"><i class="fas fa-user-circle"></i><span><?php echo e($_SESSION['username']); ?></span></div>
    </header>

    <?php if ($msg): ?>
    <div class="alert-msg alert-<?php echo $msgType; ?>"><i class="fas fa-check-circle"></i><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-header">
            <div class="panel-title"><i class="fas fa-briefcase"></i><h2>Grade 12 Students</h2></div>
            <form method="GET" style="display:flex;gap:8px">
                <div class="search-wrap"><i class="fas fa-search"></i><input type="text" name="q" id="searchBox" placeholder="Search..." value="<?php echo e($search); ?>"></div>
                <button type="submit" class="btn btn-primary" style="padding:10px 18px">Search</button>
            </form>
        </div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Student</th><th>Strand</th><th>Company</th><th>Hours Progress</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php if (empty($students)): ?>
                <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">No Grade 12 students found.</td></tr>
                <?php endif; ?>
                <?php foreach ($students as $stu):
                    $prog = $stu['required_hours'] ? min(100, round(($stu['completed_hours']/$stu['required_hours'])*100)) : 0;
                    $stColor = $stColors[$stu['status'] ?? 'not_started'];
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:#fff"><?php echo e($stu['last_name'].', '.$stu['first_name']); ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted)"><?php echo e($stu['lrn']); ?></div>
                    </td>
                    <td><?php echo e($stu['strand']); ?></td>
                    <td style="font-size:.88rem"><?php echo $stu['company_name'] ? e($stu['company_name']) : '<span style="color:var(--text-muted)">Not assigned</span>'; ?></td>
                    <td style="min-width:140px">
                        <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:4px"><?php echo ($stu['completed_hours']??0); ?>h / <?php echo ($stu['required_hours']??80); ?>h</div>
                        <div class="progress-mini"><div class="progress-mini-fill" style="width:<?php echo $prog; ?>%"></div></div>
                    </td>
                    <td><span style="color:<?php echo $stColor; ?>;font-weight:600"><?php echo ucfirst(str_replace('_',' ',$stu['status']??'not_started')); ?></span></td>
                    <td>
                        <button type="button" onclick="openModal(<?php echo $stu['id']; ?>, '<?php echo addslashes($stu['last_name'].', '.$stu['first_name']); ?>')" class="btn btn-print"><i class="fas fa-edit"></i> Assign</button>
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
        <h3><i class="fas fa-building" style="color:#f59e0b;margin-right:8px"></i>Assign Immersion &mdash; <span id="modalName"></span></h3>
        <form method="POST">
            <input type="hidden" name="student_id" id="modalId">
            <div class="form-group"><label>Company / Partner Name</label><input type="text" name="company_name" class="form-control" placeholder="e.g. SM Starmall Alabang" required></div>
            <div class="form-group"><label>Company Address</label><textarea name="company_address" class="form-control" rows="2" placeholder="Full address..."></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Supervisor Name</label><input type="text" name="supervisor_name" class="form-control" placeholder="Supervisor"></div>
                <div class="form-group"><label>Supervisor Contact</label><input type="text" name="supervisor_contact" class="form-control" placeholder="09XX-XXX-XXXX"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control"></div>
                <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control"></div>
            </div>
            <div class="form-group"><label>Required Hours</label><input type="number" name="required_hours" class="form-control" value="80" min="40" max="200"></div>
            <div style="display:flex;gap:10px;margin-top:4px">
                <button type="submit" name="save_immersion" class="btn btn-primary" style="flex:1;padding:12px"><i class="fas fa-save"></i> Save Assignment</button>
                <button type="button" onclick="closeModal()" class="btn btn-danger" style="padding:12px 20px">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
function openModal(id,name) {
    document.getElementById('modalId').value=id;
    document.getElementById('modalName').textContent=name;
    document.getElementById('modalOverlay').classList.add('open');
}
function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }
document.getElementById('modalOverlay').addEventListener('click',e=>{ if(e.target===document.getElementById('modalOverlay')) closeModal(); });
</script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
