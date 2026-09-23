<?php
/**
 * Admin: Manage Document Requests
 * View, update status, and add remarks to student document requests.
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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $req_id  = intval($_POST['req_id']);
    $status  = in_array($_POST['status'], ['pending','processing','ready','released']) ? $_POST['status'] : 'pending';
    $remarks = trim($_POST['remarks'] ?? '');
    $s = $conn->prepare("UPDATE document_requests SET status = ?, remarks = ? WHERE id = ?");
    $s->bind_param("ssi", $status, $remarks, $req_id);
    if ($s->execute()) {
        $msg = "Request #$req_id updated to <strong>" . ucfirst($status) . "</strong>.";
        $msgType = 'success';
    } else {
        $msg = "Update failed.";
        $msgType = 'error';
    }
    $s->close();
}

// Fetch all requests with student info
$filter = $_GET['status'] ?? 'all';
$query = "SELECT dr.*, CONCAT(s.last_name, ', ', s.first_name) AS student_name, s.lrn, s.grade, s.strand, s.section
    FROM document_requests dr
    JOIN students s ON s.id = dr.student_id" .
    ($filter !== 'all' ? " WHERE dr.status = '" . $conn->real_escape_string($filter) . "'" : '') .
    " ORDER BY dr.requested_at DESC";
$result = $conn->query($query);
$requests = [];
if ($result) while ($row = $result->fetch_assoc()) $requests[] = $row;

$counts = [];
foreach (['pending','processing','ready','released'] as $s2) {
    $r = $conn->query("SELECT COUNT(*) as c FROM document_requests WHERE status = '$s2'");
    $counts[$s2] = $r->fetch_assoc()['c'];
}
$conn->close();

$statusColors = [
    'pending'    => ['#f59e0b', 'rgba(245,158,11,0.1)'],
    'processing' => ['#3b82f6', 'rgba(59,130,246,0.1)'],
    'ready'      => ['#10b981', 'rgba(16,185,129,0.1)'],
    'released'   => ['#6b7280', 'rgba(107,114,128,0.1)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Requests | Admin</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
<style>
    .filter-tabs { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px; }
    .filter-tab {
        padding: 8px 18px; border-radius: 30px; font-size: 0.85rem; font-weight: 600;
        text-decoration: none; border: 1px solid var(--border); color: var(--text-muted);
        transition: all .25s; display: flex; align-items: center; gap: 7px;
    }
    .filter-tab:hover { border-color: var(--primary); color: #fff; }
    .filter-tab.active { background: rgba(59,130,246,0.1); border-color: var(--primary); color: var(--primary); }
    .filter-tab .count-bubble {
        background: rgba(255,255,255,0.1); padding: 1px 8px; border-radius: 10px; font-size: 0.8rem;
    }
    .req-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: center; padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.04); }
    .req-row:last-child { border-bottom: none; }
    .student-name { font-weight: 600; color: #fff; margin-bottom: 3px; }
    .req-meta { font-size: .82rem; color: var(--text-muted); }
    .doc-type { font-weight: 600; color: #e5e7eb; margin-bottom: 3px; }
    .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 20px; font-size: .8rem; font-weight: 600; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 100; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal { background: #111827; border: 1px solid var(--border); border-radius: 20px; padding: 32px; width: 90%; max-width: 500px; }
    .modal h3 { font-size: 1.2rem; margin-bottom: 20px; }
    .modal-btns { display: flex; gap: 10px; margin-top: 20px; }
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
            <div>
                <h1>Document Requests</h1>
                <p>Review and update student document request statuses</p>
            </div>
        </div>
        <div class="user-profile"><i class="fas fa-user-circle"></i><span><?php echo e($_SESSION['username']); ?></span></div>
    </header>

    <!-- Stats -->
    <div class="stats-grid">
        <?php foreach (['pending'=>['warning','clock'],'processing'=>['blue','spinner'],'ready'=>['green','check-circle'],'released'=>['purple','box-open']] as $s2=>[$ic,$ico]): ?>
        <div class="stat-card">
            <div class="stat-icon icon-<?php echo $ic; ?>"><i class="fas fa-<?php echo $ico; ?>"></i></div>
            <div class="stat-info">
                <h3><?php echo $counts[$s2] ?? 0; ?></h3>
                <p><?php echo ucfirst($s2); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($msg): ?>
    <div class="alert-msg alert-<?php echo $msgType; ?>"><i class="fas fa-check-circle"></i><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-header">
            <div class="panel-title"><i class="fas fa-file-alt"></i><h2>All Requests</h2></div>
        </div>
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <?php foreach (['all'=>'All','pending'=>'Pending','processing'=>'Processing','ready'=>'Ready','released'=>'Released'] as $val=>$label): ?>
            <a href="?status=<?php echo $val; ?>" class="filter-tab <?php echo $filter===$val?'active':''; ?>">
                <?php echo $label; ?>
                <?php if ($val !== 'all'): ?><span class="count-bubble"><?php echo $counts[$val]??0; ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($requests)): ?>
        <div style="text-align:center;padding:50px;color:var(--text-muted)"><i class="fas fa-inbox" style="font-size:3rem;opacity:.2;display:block;margin-bottom:14px"></i>No requests found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Student</th><th>Document</th><th>Purpose</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $req):
                    [$sc, $sb] = $statusColors[$req['status']] ?? $statusColors['pending'];
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:#fff"><?php echo e($req['student_name']); ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted)"><?php echo e($req['lrn']); ?> &nbsp;|&nbsp; G<?php echo e($req['grade']); ?> - <?php echo e($req['strand']); ?></div>
                    </td>
                    <td style="font-weight:600"><?php echo e($req['document_type']); ?></td>
                    <td style="font-size:.85rem;color:var(--text-muted);max-width:200px"><?php echo e(substr($req['purpose'],0,70)).(strlen($req['purpose'])>70?'...':''); ?></td>
                    <td style="font-size:.85rem"><?php echo date('M d, Y', strtotime($req['requested_at'])); ?></td>
                    <td>
                        <span class="status-badge" style="background:<?php echo $sb; ?>;color:<?php echo $sc; ?>">
                            <?php echo ucfirst($req['status']); ?>
                        </span>
                        <?php if ($req['remarks']): ?><div style="font-size:.78rem;color:#f59e0b;margin-top:4px"><?php echo e($req['remarks']); ?></div><?php endif; ?>
                    </td>
                    <td>
                        <button type="button" onclick="openModal(<?php echo $req['id']; ?>, '<?php echo $req['status']; ?>', '<?php echo addslashes($req['remarks']); ?>')"
                            class="btn btn-print"><i class="fas fa-edit"></i> Update</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Update Modal -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <h3><i class="fas fa-edit" style="color:var(--primary);margin-right:8px"></i>Update Request Status</h3>
        <form method="POST">
            <input type="hidden" name="req_id" id="modalReqId">
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="modalStatus" class="form-control">
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="ready">Ready for Pickup</option>
                    <option value="released">Released</option>
                </select>
            </div>
            <div class="form-group">
                <label>Remarks / Notes (Optional)</label>
                <textarea name="remarks" id="modalRemarks" class="form-control" rows="3" placeholder="e.g. Please bring your registration form when claiming..."></textarea>
            </div>
            <div class="modal-btns">
                <button type="submit" name="update_status" class="btn btn-primary" style="flex:1;padding:12px">Save Changes</button>
                <button type="button" onclick="closeModal()" class="btn btn-danger" style="padding:12px 20px">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, status, remarks) {
    document.getElementById('modalReqId').value = id;
    document.getElementById('modalStatus').value = status;
    document.getElementById('modalRemarks').value = remarks;
    document.getElementById('modalOverlay').classList.add('open');
}
function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }
document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
