<?php
/**
 * Admin: Manage School Clearances
 * Toggle clearance status per department for each student.
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

// Handle toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_clearance'])) {
    $student_id = intval($_POST['student_id']);
    $field      = $_POST['field'] ?? '';
    $allowed = ['adviser_status','library_status','cashier_status','property_status','clinic_status','guidance_status'];
    if (in_array($field, $allowed)) {
        // Ensure row exists
        $conn->query("INSERT IGNORE INTO clearances (student_id) VALUES ($student_id)");
        $cur = $conn->query("SELECT `$field` FROM clearances WHERE student_id = $student_id")->fetch_assoc()[$field];
        $newVal = ($cur === 'cleared') ? 'pending' : 'cleared';
        $conn->query("UPDATE clearances SET `$field` = '$newVal' WHERE student_id = $student_id");
        $msg = "Clearance status updated.";
        $msgType = 'success';
    }
}

// Search
$search = trim($_GET['q'] ?? '');
$sqlWhere = $search ? "WHERE s.first_name LIKE '%$search%' OR s.last_name LIKE '%$search%' OR s.lrn LIKE '%$search%'" : '';

$query = "SELECT s.id, s.lrn, s.first_name, s.last_name, s.grade, s.strand, s.section,
    c.adviser_status, c.library_status, c.cashier_status, c.property_status, c.clinic_status, c.guidance_status
    FROM students s
    LEFT JOIN clearances c ON c.student_id = s.id
    WHERE s.status = 'approved' " . ($search ? "AND (s.first_name LIKE '%$search%' OR s.last_name LIKE '%$search%' OR s.lrn LIKE '%$search%')" : '') . "
    ORDER BY s.last_name ASC";

$result = $conn->query($query);
$students = [];
if ($result) while ($row = $result->fetch_assoc()) $students[] = $row;
$conn->close();

$depts = [
    'adviser_status'  => ['Adviser',   'fa-chalkboard-teacher'],
    'library_status'  => ['Library',   'fa-book'],
    'cashier_status'  => ['Cashier',   'fa-coins'],
    'property_status' => ['Property',  'fa-boxes'],
    'clinic_status'   => ['Clinic',    'fa-heartbeat'],
    'guidance_status' => ['Guidance',  'fa-hands-helping'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Clearances | Admin</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
<style>
    .toggle-btn {
        width: 38px; height: 20px; border-radius: 20px; border: none; cursor: pointer;
        transition: background .3s; position: relative; flex-shrink: 0;
    }
    .toggle-btn::after { content: ''; position: absolute; width: 14px; height: 14px; border-radius: 50%; background: #fff; top: 3px; transition: left .3s; }
    .toggle-btn.cleared { background: #10b981; }
    .toggle-btn.cleared::after { left: 21px; }
    .toggle-btn.pending { background: rgba(255,255,255,0.15); }
    .toggle-btn.pending::after { left: 3px; }
    .dept-col { text-align: center; min-width: 80px; }
    .dept-header { font-size: .75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
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
            <div><h1>Manage Clearances</h1><p>Toggle department clearance status per student</p></div>
        </div>
        <div class="user-profile"><i class="fas fa-user-circle"></i><span><?php echo e($_SESSION['username']); ?></span></div>
    </header>

    <?php if ($msg): ?>
    <div class="alert-msg alert-<?php echo $msgType; ?>"><i class="fas fa-check-circle"></i><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-header">
            <div class="panel-title"><i class="fas fa-check-double"></i><h2>Student Clearances</h2></div>
            <form method="GET" style="display:flex;gap:8px">
                <div class="search-wrap"><i class="fas fa-search"></i><input type="text" name="q" id="searchBox" placeholder="Search student..." value="<?php echo e($search); ?>"></div>
                <button type="submit" class="btn btn-primary" style="padding:10px 18px">Search</button>
            </form>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Grade / Strand</th>
                        <?php foreach ($depts as [$name, $icon]): ?>
                        <th class="dept-col"><i class="fas <?php echo $icon; ?>" style="margin-right:4px"></i><?php echo $name; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                <tr><td colspan="<?php echo 2 + count($depts); ?>" style="text-align:center;padding:40px;color:var(--text-muted)">No students found.</td></tr>
                <?php endif; ?>
                <?php foreach ($students as $stu): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:#fff"><?php echo e($stu['last_name'].', '.$stu['first_name']); ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted)"><?php echo e($stu['lrn']); ?></div>
                    </td>
                    <td style="font-size:.88rem">G<?php echo e($stu['grade']); ?> &nbsp;|&nbsp; <?php echo e($stu['strand']); ?><?php if ($stu['section']): ?><br><span style="color:var(--text-muted)"><?php echo e($stu['section']); ?></span><?php endif; ?></td>
                    <?php foreach ($depts as $field => [$name, $icon]):
                        $status = $stu[$field] ?? 'pending';
                    ?>
                    <td class="dept-col">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="student_id" value="<?php echo $stu['id']; ?>">
                            <input type="hidden" name="field" value="<?php echo $field; ?>">
                            <button type="submit" name="toggle_clearance" class="toggle-btn <?php echo $status; ?>" title="<?php echo ucfirst($status); ?> &mdash; Click to toggle"></button>
                        </form>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
