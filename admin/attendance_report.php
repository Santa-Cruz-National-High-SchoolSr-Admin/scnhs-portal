<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../login.php");
    exit();
}

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_section = isset($_GET['section']) ? $_GET['section'] : '';

if ($filter_section) {
    $stmt = $conn->prepare("SELECT a.*, s.grade, s.section FROM attendance a LEFT JOIN students s ON a.student_id = s.id WHERE a.date = ? AND s.section = ? ORDER BY a.check_in_time DESC");
    $stmt->bind_param("ss", $filter_date, $filter_section);
} else {
    $stmt = $conn->prepare("SELECT a.*, s.grade, s.section FROM attendance a LEFT JOIN students s ON a.student_id = s.id WHERE a.date = ? ORDER BY a.check_in_time DESC");
    $stmt->bind_param("s", $filter_date);
}
$stmt->execute();
$attendance_result = $stmt->get_result();

$total_students = $conn->query("SELECT COUNT(DISTINCT id) as total FROM students")->fetch_assoc()['total'];
$present_stmt = $conn->prepare("SELECT COUNT(*) as present FROM attendance WHERE date = ?");
$present_stmt->bind_param("s", $filter_date);
$present_stmt->execute();
$present_students = $present_stmt->get_result()->fetch_assoc()['present'];
$absent_students = $total_students - $present_students;
$attendance_rate = $total_students > 0 ? round(($present_students / $total_students) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/logo.png">
    <title>Attendance Report | SCNHS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--bg-dark:#0b1120;--bg-panel:rgba(17,24,39,.75);--bg-panel-hover:rgba(31,41,55,.85);--text-main:#f9fafb;--text-muted:#9ca3af;--primary:#3b82f6;--primary-hover:#2563eb;--accent:#10b981;--danger:#ef4444;--warning:#f59e0b;--border:rgba(255,255,255,.08);--glass:blur(16px)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-dark);background-image:radial-gradient(circle at 85% 15%,rgba(59,130,246,.08),transparent 25%),radial-gradient(circle at 15% 85%,rgba(16,185,129,.05),transparent 25%);background-attachment:fixed;color:var(--text-main);display:flex;min-height:100vh}
        
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:20px}
        .header h1{font-size:1.8rem;font-weight:600}.header p{color:var(--text-muted);margin-top:4px}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin-bottom:30px}
        .stat-card{background:var(--bg-panel);backdrop-filter:var(--glass);border:1px solid var(--border);border-radius:16px;padding:24px;display:flex;align-items:center;gap:20px;transition:transform .3s,box-shadow .3s}
        .stat-card:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(0,0,0,.4)}
        .stat-icon{width:56px;height:56px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem}
        .icon-blue{background:rgba(59,130,246,.1);color:var(--primary)}.icon-green{background:rgba(16,185,129,.1);color:var(--accent)}.icon-red{background:rgba(239,68,68,.1);color:var(--danger)}.icon-yellow{background:rgba(245,158,11,.1);color:var(--warning)}
        .stat-info h3{font-size:2rem;font-weight:700;color:#fff;line-height:1;margin-bottom:8px}.stat-info p{color:var(--text-muted);font-size:.9rem;font-weight:500}
        .controls{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end}
        .control-group{display:flex;flex-direction:column;gap:8px}
        .control-group label{font-size:.9rem;color:var(--text-muted)}
        .table-responsive{overflow-x:auto}
        table{width:100%;border-collapse:collapse;text-align:left}
        th{padding:16px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;white-space:nowrap}
        td{padding:16px;border-bottom:1px solid rgba(255,255,255,.03);color:#e5e7eb;vertical-align:middle;white-space:nowrap}
        tr:hover td{background:rgba(255,255,255,.02)}
        .badge{padding:4px 10px;border-radius:12px;font-size:.8rem;font-weight:600}
        .badge-present{background:rgba(16,185,129,.1);color:var(--accent)}
        .no-data{text-align:center;padding:40px;color:var(--text-muted)}
        
        @media print{body{background:#fff!important;color:#000!important;display:block}.stat-card{background:#f5f5f5!important;border:1px solid #ddd!important}.stat-info h3{color:#000!important}.stat-info p,table{border:1px solid #ddd}th{background:#333!important;color:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}td{border-bottom:1px solid #ddd!important;color:#000!important}}
    </style>
    <link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <?php include 'includes/sidebar.php'; ?>
    </aside>

    <main class="main-content">
        <header class="header">
            <div style="display:flex;align-items:center;gap:16px">
                <button type="button" class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
                <div><h1>Attendance Report</h1><p>View and analyze student attendance records for <?php echo date('F j, Y', strtotime($filter_date)); ?>.</p></div>
            </div>
        </header>

        <!-- Filters -->
        <div class="panel">
            <div class="controls">
                <div class="control-group"><label for="dateFilter">Select Date</label>
                    <input type="date" id="dateFilter" class="form-control" value="<?php echo $filter_date; ?>">
                </div>
                <div class="control-group"><label for="sectionFilter">Section</label>
                    <select id="sectionFilter" class="form-control">
                        <option value="">All Sections</option>
                        <?php
                        $sec_result = $conn->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL ORDER BY section");
                        while ($sec = $sec_result->fetch_assoc()):
                            $sel = ($filter_section === $sec['section']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($sec['section']); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($sec['section']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-primary" onclick="applyFilters()"><i class="fas fa-filter"></i> Apply</button>
                <button type="button" class="btn btn-success" onclick="exportToCSV()"><i class="fas fa-file-csv"></i> Export CSV</button>
                <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon icon-blue"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo $total_students; ?></h3><p>Total Students</p></div></div>
            <div class="stat-card"><div class="stat-icon icon-green"><i class="fas fa-user-check"></i></div><div class="stat-info"><h3><?php echo $present_students; ?></h3><p>Present</p></div></div>
            <div class="stat-card"><div class="stat-icon icon-red"><i class="fas fa-user-xmark"></i></div><div class="stat-info"><h3><?php echo $absent_students; ?></h3><p>Absent</p></div></div>
            <div class="stat-card"><div class="stat-icon icon-yellow"><i class="fas fa-chart-line"></i></div><div class="stat-info"><h3><?php echo $attendance_rate; ?>%</h3><p>Attendance Rate</p></div></div>
        </div>

        <!-- Attendance Table -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-table"></i><h2>Attendance Records</h2></div>
            </div>
            <div class="table-responsive">
                <?php if ($attendance_result->num_rows > 0): ?>
                <table id="reportTable">
                    <thead><tr><th>Student Name</th><th>LRN</th><th>Grade</th><th>Section</th><th>Check-in Time</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php while ($row = $attendance_result->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:500"><?php echo htmlspecialchars($row['student_name']); ?></td>
                            <td><span style="font-family:monospace;background:rgba(255,255,255,.05);padding:4px 8px;border-radius:4px"><?php echo htmlspecialchars($row['lrn']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['grade'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['section'] ?? 'N/A'); ?></td>
                            <td><?php echo date('h:i A', strtotime($row['check_in_time'])); ?></td>
                            <td><span class="badge badge-present">Present</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data"><i class="fas fa-clipboard-list" style="font-size:2rem;margin-bottom:12px;opacity:.5;display:block"></i>No attendance records found for the selected date.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function applyFilters(){
            const d=document.getElementById('dateFilter').value,s=document.getElementById('sectionFilter').value,p=new URLSearchParams();
            if(d)p.append('date',d);if(s)p.append('section',s);
            window.location.href='?'+p.toString();
        }
        function exportToCSV(){
            const t=document.getElementById('reportTable');if(!t){alert('No data to export');return}
            let csv=[];t.querySelectorAll('tr').forEach(r=>{const cols=[];r.querySelectorAll('td,th').forEach(c=>cols.push('"'+c.textContent.replace(/"/g,'""')+'"'));csv.push(cols.join(','))});
            const blob=new Blob([csv.join('\n')],{type:'text/csv'}),a=document.createElement('a');
            a.href=URL.createObjectURL(blob);a.download='attendance_'+document.getElementById('dateFilter').value+'.csv';a.click();
        }
        </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
