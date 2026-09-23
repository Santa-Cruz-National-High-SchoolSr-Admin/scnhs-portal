<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../login.php");
    exit();
}

// Create attendance table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lrn VARCHAR(20) NOT NULL,
    student_name VARCHAR(100),
    check_in_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    date DATE,
    FOREIGN KEY (student_id) REFERENCES students(id)
)");

// Handle attendance submission via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $response = array('success' => false, 'message' => '');
    
    if (isset($data['lrn'])) {
        $lrn = trim($data['lrn']);
        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $student_id = $student['id'];
            $student_name = $student['first_name'] . ' ' . $student['last_name'];
            $today = date('Y-m-d');
            
            $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ?");
            $check_stmt->bind_param("is", $student_id, $today);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $response['success'] = false;
                $response['message'] = 'Student already marked as present today!';
                $response['timestamp'] = date('H:i:s');
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO attendance (student_id, lrn, student_name, date) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("isss", $student_id, $lrn, $student_name, $today);
                if ($insert_stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Attendance recorded: ' . htmlspecialchars($student_name);
                    $response['timestamp'] = date('H:i:s');
                    $response['student_name'] = $student_name;
                    $response['lrn'] = $lrn;
                } else {
                    $response['message'] = 'Error recording attendance';
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        } else {
            $response['message'] = 'Student not found with LRN: ' . htmlspecialchars($lrn);
            $response['timestamp'] = date('H:i:s');
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid request';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get today's attendance summary
$today = date('Y-m-d');
$summary_stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE date = ?");
$summary_stmt->bind_param("s", $today);
$summary_stmt->execute();
$today_attendance = $summary_stmt->get_result()->fetch_assoc()['total'];

// Get today's attendance list
$attendance_stmt = $conn->prepare("SELECT student_name, lrn, check_in_time FROM attendance WHERE date = ? ORDER BY check_in_time DESC");
$attendance_stmt->bind_param("s", $today);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/logo.png">
    <title>Attendance Tracker | SCNHS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--bg-dark:#0b1120;--bg-panel:rgba(17,24,39,.75);--bg-panel-hover:rgba(31,41,55,.85);--text-main:#f9fafb;--text-muted:#9ca3af;--primary:#3b82f6;--primary-hover:#2563eb;--accent:#10b981;--danger:#ef4444;--border:rgba(255,255,255,.08);--glass:blur(16px)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-dark);background-image:radial-gradient(circle at 85% 15%,rgba(59,130,246,.08),transparent 25%),radial-gradient(circle at 15% 85%,rgba(16,185,129,.05),transparent 25%);background-attachment:fixed;color:var(--text-main);display:flex;min-height:100vh}
        
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:20px}
        .header h1{font-size:1.8rem;font-weight:600}.header p{color:var(--text-muted);margin-top:4px}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;margin-bottom:30px}
        .stat-card{background:var(--bg-panel);backdrop-filter:var(--glass);border:1px solid var(--border);border-radius:16px;padding:24px;display:flex;align-items:center;gap:20px;transition:transform .3s,box-shadow .3s}
        .stat-card:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(0,0,0,.4)}
        .stat-icon{width:56px;height:56px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem}
        .icon-blue{background:rgba(59,130,246,.1);color:var(--primary)}
        .icon-green{background:rgba(16,185,129,.1);color:var(--accent)}
        .icon-purple{background:rgba(139,92,246,.1);color:#8b5cf6}
        .stat-info h3{font-size:2rem;font-weight:700;color:#fff;line-height:1;margin-bottom:8px}
        .stat-info p{color:var(--text-muted);font-size:.9rem;font-weight:500}
        .scanner-input{width:100%;padding:16px 20px;background:rgba(0,0,0,.3);border:2px solid var(--border);border-radius:12px;color:#fff;font-family:inherit;font-size:1.1rem;transition:border-color .3s}
        .scanner-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 4px rgba(59,130,246,.15)}
        .scanner-input::placeholder{color:var(--text-muted)}
        .info-bar{background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);color:var(--primary);padding:14px 18px;border-radius:8px;margin-bottom:20px;font-size:.9rem;display:flex;align-items:center;gap:12px}
        .toast{padding:16px 20px;border-radius:10px;font-weight:600;text-align:center;display:none;animation:slideDown .4s ease-out;margin-bottom:20px}
        .toast.success{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:var(--accent);display:block}
        .toast.error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:var(--danger);display:block}
        .attendance-list{max-height:500px;overflow-y:auto}
        .attendance-item{padding:14px 0;border-bottom:1px solid rgba(255,255,255,.04);display:flex;justify-content:space-between;align-items:center;animation:slideDown .3s ease-out}
        .attendance-item:last-child{border-bottom:none}
        .att-info h4{font-weight:600;color:#fff;margin-bottom:2px}
        .att-info span{font-size:.85rem;color:var(--text-muted)}
        .att-time{color:var(--primary);font-weight:600;font-size:.95rem;white-space:nowrap}
        .no-records{text-align:center;padding:40px;color:var(--text-muted)}
        @keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        
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
                <div><h1>Attendance Tracker</h1><p>Scan student barcodes to record daily attendance.</p></div>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon icon-green"><i class="fas fa-user-check"></i></div><div class="stat-info"><h3 id="presentCount"><?php echo $today_attendance; ?></h3><p>Present Today</p></div></div>
            <div class="stat-card"><div class="stat-icon icon-blue"><i class="fas fa-clock"></i></div><div class="stat-info"><h3 id="liveTime"><?php echo date('H:i:s'); ?></h3><p>Current Time</p></div></div>
            <div class="stat-card"><div class="stat-icon icon-purple"><i class="fas fa-calendar-day"></i></div><div class="stat-info"><h3><?php echo date('l'); ?></h3><p><?php echo date('F j, Y'); ?></p></div></div>
        </div>

        <div class="panel">
            <div class="panel-title"><i class="fas fa-qrcode"></i><h2>Barcode Scanner</h2></div>
            <div class="info-bar"><i class="fas fa-info-circle"></i> Point the barcode scanner at the student ID card or type the LRN below and press Enter.</div>
            <input type="text" id="barcodeInput" class="scanner-input" placeholder="Place cursor here and scan barcode..." autofocus autocomplete="off">
            <div id="toast" class="toast" style="margin-top:20px"></div>
        </div>

        <div class="panel">
            <div class="panel-title"><i class="fas fa-list-check"></i><h2>Today's Attendance Record</h2></div>
            <div class="attendance-list" id="attendanceList">
                <?php if ($attendance_result->num_rows > 0): ?>
                    <?php while ($row = $attendance_result->fetch_assoc()): ?>
                    <div class="attendance-item">
                        <div class="att-info">
                            <h4><?php echo htmlspecialchars($row['student_name']); ?></h4>
                            <span>LRN: <?php echo htmlspecialchars($row['lrn']); ?></span>
                        </div>
                        <div class="att-time"><?php echo date('h:i A', strtotime($row['check_in_time'])); ?></div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-records"><i class="fas fa-clipboard" style="font-size:2rem;margin-bottom:12px;opacity:.5;display:block"></i>No attendance records yet today.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const barcodeInput = document.getElementById('barcodeInput');
        const toast = document.getElementById('toast');
        const attendanceList = document.getElementById('attendanceList');

        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const lrn = this.value.trim();
                if (!lrn) { showToast('Please scan a barcode or enter LRN', 'error'); return; }
                recordAttendance(lrn);
                this.value = '';
                this.focus();
            }
        });

        function recordAttendance(lrn) {
            fetch('attendance_tracker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lrn: lrn })
            })
            .then(r => r.json())
            .then(data => {
                showToast(data.message + (data.timestamp ? ' at ' + data.timestamp : ''), data.success ? 'success' : 'error');
                if (data.success) {
                    // Update counter
                    const cnt = document.getElementById('presentCount');
                    cnt.textContent = parseInt(cnt.textContent) + 1;
                    // Add item to top of list
                    const noRec = attendanceList.querySelector('.no-records');
                    if (noRec) noRec.remove();
                    const item = document.createElement('div');
                    item.className = 'attendance-item';
                    item.innerHTML = `<div class="att-info"><h4>${data.student_name}</h4><span>LRN: ${data.lrn}</span></div><div class="att-time">${data.timestamp}</div>`;
                    attendanceList.prepend(item);
                }
            })
            .catch(err => showToast('Error: ' + err.message, 'error'));
        }

        function showToast(msg, type) {
            toast.textContent = msg;
            toast.className = 'toast ' + type;
            if (type === 'success') setTimeout(() => { toast.className = 'toast'; }, 3000);
        }

        setInterval(() => {
            document.getElementById('liveTime').textContent = new Date().toLocaleTimeString('en-US', { hour12: false });
        }, 1000);
    </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
