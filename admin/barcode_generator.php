<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../login.php");
    exit();
}

$sql = "SELECT id, lrn, first_name, last_name, grade, section FROM students ORDER BY last_name, first_name";
$result = $conn->query($sql);
$students = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/logo.png">
    <title>Barcode Generator | SCNHS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        :root{--bg-dark:#0b1120;--bg-panel:rgba(17,24,39,.75);--bg-panel-hover:rgba(31,41,55,.85);--text-main:#f9fafb;--text-muted:#9ca3af;--primary:#3b82f6;--primary-hover:#2563eb;--accent:#10b981;--danger:#ef4444;--border:rgba(255,255,255,.08);--glass:blur(16px)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-dark);background-image:radial-gradient(circle at 85% 15%,rgba(59,130,246,.08),transparent 25%),radial-gradient(circle at 15% 85%,rgba(16,185,129,.05),transparent 25%);background-attachment:fixed;color:var(--text-main);display:flex;min-height:100vh}
        
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:20px}
        .header h1{font-size:1.8rem;font-weight:600}.header p{color:var(--text-muted);margin-top:4px}
        .controls{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;margin-bottom:24px}
        .control-group{display:flex;flex-direction:column;gap:8px}
        .control-group label{font-size:.9rem;color:var(--text-muted)}
        .student-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px}
        .student-card{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:20px;text-align:center;transition:transform .3s,box-shadow .3s}
        .student-card:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(0,0,0,.3);background:rgba(255,255,255,.05)}
        .student-card h3{font-size:1.1rem;font-weight:600;margin-bottom:4px;color:#fff}
        .student-card p{font-size:.85rem;color:var(--text-muted);margin-bottom:16px}
        .barcode-wrap{background:#fff;padding:12px;border-radius:8px;display:inline-block}
        .barcode-wrap svg{width:100%;height:auto;max-width:200px;display:block}
        
        @media print{body{background:#fff!important;color:#000!important;display:block}.student-card{page-break-inside:avoid;margin-bottom:20px;border:1px solid #ccc;background:#fff;padding:15px;text-align:center;color:#000;width:45%;display:inline-block;margin-right:2%}.student-card h3{color:#000}.student-card p{color:#333}}
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
                <div><h1>Barcode Generator</h1><p>Generate and print student LRN barcodes for attendance tracking.</p></div>
            </div>
        </header>

        <div class="panel">
            <div class="controls">
                <div class="control-group"><label for="gradeFilter">Filter by Grade</label>
                    <select id="gradeFilter" class="form-control" onchange="filterStudents()">
                        <option value="">All Grades</option><option value="11">Grade 11</option><option value="12">Grade 12</option>
                    </select>
                </div>
                <div class="control-group"><label for="searchFilter">Search Student</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Search name or LRN..." onkeyup="filterStudents()">
                </div>
                <button type="button" onclick="window.print()" class="btn btn-primary" style="margin-bottom:2px"><i class="fas fa-print"></i> Print Barcodes</button>
            </div>

            <div class="student-grid" id="studentGrid">
                <?php foreach ($students as $s): ?>
                <div class="student-card" data-grade="<?php echo htmlspecialchars($s['grade']); ?>" data-name="<?php echo strtolower(htmlspecialchars($s['first_name'].' '.$s['last_name'])); ?>" data-lrn="<?php echo htmlspecialchars($s['lrn']); ?>">
                    <h3><?php echo htmlspecialchars($s['last_name'].', '.$s['first_name']); ?></h3>
                    <p>Grade <?php echo htmlspecialchars($s['grade']); ?> | <?php echo htmlspecialchars($s['section'] ?? 'No Section'); ?></p>
                    <div class="barcode-wrap"><svg class="barcode" jsbarcode-format="CODE128" jsbarcode-value="<?php echo htmlspecialchars($s['lrn']); ?>" jsbarcode-textmargin="0" jsbarcode-fontoptions="bold"></svg></div>
                    <div><button type="button" onclick="printSingle('<?php echo htmlspecialchars($s['lrn']); ?>')" class="btn btn-sm"><i class="fas fa-print"></i> Print Single</button></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($students)): ?>
                <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-users-slash" style="font-size:2rem;margin-bottom:12px;opacity:.5"></i><p>No students found.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        JsBarcode(".barcode").init();
        function filterStudents(){const g=document.getElementById('gradeFilter').value,s=document.getElementById('searchFilter').value.toLowerCase();document.querySelectorAll('.student-card').forEach(c=>{const mg=!g||c.dataset.grade===g,ms=!s||c.dataset.name.includes(s)||c.dataset.lrn.includes(s);c.style.display=mg&&ms?'':'none'})}
        function printSingle(lrn){document.querySelectorAll('.student-card').forEach(c=>c.style.display='none');document.querySelector(`.student-card[data-lrn="${lrn}"]`).style.display='';window.print();filterStudents()}
        </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
