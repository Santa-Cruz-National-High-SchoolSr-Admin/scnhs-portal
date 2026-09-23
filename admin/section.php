<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

require_once dirname(__DIR__) . '/auth_guard.php';
enforce_auth('admin');

$msg = "";
$msgType = "";

if (isset($_GET['success'])) {
    $msg = $_GET['success'];
    $msgType = "success";
}

// Handle section enrollment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_section'])) {
    $section_name = trim($_POST['section_name']);
    $grade_level = trim($_POST['grade_level']);
    $teacher_name = trim($_POST['teacher_name']);
    $track = trim($_POST['track']);
    $strand = trim($_POST['strand']);
    $section_code = bin2hex(random_bytes(4));

    $sql = "INSERT INTO sections (section_name, grade_level, teacher_name, track, strand, section_code) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $section_name, $grade_level, $teacher_name, $track, $strand, $section_code);

    if ($stmt->execute()) {
        $section_id = $stmt->insert_id;
        $assign_stmt = $conn->prepare("SELECT id FROM students WHERE grade_level = ? AND strand = ? AND section_id IS NULL");
        $assign_stmt->bind_param("ss", $grade_level, $strand);
        $assign_stmt->execute();
        $students = $assign_stmt->get_result();
        $update_stmt = $conn->prepare("UPDATE students SET section_id = ?, section_code = ? WHERE id = ?");
        while ($student = $students->fetch_assoc()) {
            $update_stmt->bind_param("isi", $section_id, $section_code, $student['id']);
            $update_stmt->execute();
        }
        $assign_stmt->close();
        $update_stmt->close();
        header("Location: section.php?success=Section added successfully");
        exit();
    } else {
        $msg = "Error: " . $stmt->error;
        $msgType = "error";
    }
}

// Handle section update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_section'])) {
    $section_id = intval($_POST['section_id']);
    $section_name = trim($_POST['section_name']);
    $grade_level = trim($_POST['grade_level']);
    $teacher_name = trim($_POST['teacher_name']);
    $track = trim($_POST['track']);
    $strand = trim($_POST['strand']);

    $sql = "UPDATE sections SET section_name = ?, grade_level = ?, teacher_name = ?, track = ?, strand = ? WHERE section_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $section_name, $grade_level, $teacher_name, $track, $strand, $section_id);

    if ($stmt->execute()) {
        header("Location: section.php?success=Section updated successfully");
        exit();
    } else {
        $msg = "Error updating section: " . $stmt->error;
        $msgType = "error";
    }
}

// Handle section deletion
if (isset($_GET['delete_section'])) {
    $section_id = intval($_GET['delete_section']);
    $conn->query("DELETE FROM sections WHERE section_id = $section_id");
    header("Location: section.php?success=Section deleted successfully");
    exit();
}

// Handle student removal
if (isset($_GET['drop_student']) && is_numeric($_GET['drop_student'])) {
    $student_id = intval($_GET['drop_student']);
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    if ($stmt->execute()) {
        header("Location: section.php?success=Student dropped successfully");
        exit();
    } else {
        $msg = "Error deleting student: " . $stmt->error;
        $msgType = "error";
    }
    $stmt->close();
}

// Export data to Excel
if (isset($_GET['export'])) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=sections.xls");
    $output = fopen("php://output", "w");
    fputcsv($output, ["Section ID", "Section Name", "Grade Level", "Teacher Name", "Track", "Strand"]);
    $query = $conn->query("SELECT * FROM sections");
    while ($row = $query->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// Fetch sections
$sections = $conn->query("SELECT * FROM sections ORDER BY section_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/logo.png">
    <title>Manage Sections | SCNHS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--bg-dark:#0b1120;--bg-panel:rgba(17,24,39,.75);--bg-panel-hover:rgba(31,41,55,.85);--text-main:#f9fafb;--text-muted:#9ca3af;--primary:#3b82f6;--primary-hover:#2563eb;--accent:#10b981;--danger:#ef4444;--border:rgba(255,255,255,.08);--glass:blur(16px)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-dark);background-image:radial-gradient(circle at 85% 15%,rgba(59,130,246,.08),transparent 25%),radial-gradient(circle at 15% 85%,rgba(16,185,129,.05),transparent 25%);background-attachment:fixed;color:var(--text-main);display:flex;min-height:100vh}
        
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:20px}
        .header h1{font-size:1.8rem;font-weight:600}.header p{color:var(--text-muted);margin-top:4px}
        .table-responsive{overflow-x:auto}
        table{width:100%;border-collapse:collapse;text-align:left}
        th{padding:16px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;white-space:nowrap}
        td{padding:16px;border-bottom:1px solid rgba(255,255,255,.03);color:#e5e7eb;vertical-align:middle;white-space:nowrap}
        tr:hover td{background:rgba(255,255,255,.02)}
        .code-badge{font-family:monospace;background:rgba(59,130,246,.1);color:var(--primary);padding:4px 8px;border-radius:4px;font-weight:bold}
        @media(max-width:1024px){}
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
                <div><h1>Manage Sections</h1><p>Create and organize class sections and assign teachers.</p></div>
            </div>
            <div style="display:flex;align-items:center;gap:16px">
                <a href="?export=true" class="btn btn-success"><i class="fas fa-file-excel"></i> Export Sections</a>
                <div class="user-profile">
                    <i class="fas fa-user-circle"></i>
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>
        </header>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msgType; ?>">
                <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Add Section Form -->
            <div class="panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-plus-circle"></i><h2>Add New Section</h2></div></div>
                <form action="section.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-group"><label for="section_name">Section Name</label>
                        <input type="text" id="section_name" name="section_name" class="form-control" placeholder="e.g. Aristotle" required>
                    </div>
                    <div class="form-group"><label for="grade_level">Grade Level</label>
                        <select id="grade_level" name="grade_level" class="form-control" required>
                            <option value="">Select Grade</option><option value="11">Grade 11</option><option value="12">Grade 12</option>
                        </select>
                    </div>
                    <div class="form-group"><label for="track">Track</label>
                        <select id="track" name="track" class="form-control" required>
                            <option value="">Select Track</option><option value="Academic">Academic</option><option value="TVL">TVL</option>
                        </select>
                    </div>
                    <div class="form-group"><label for="strand">Strand</label>
                        <select id="strand" name="strand" class="form-control" required>
                            <option value="">Select Strand</option><option value="STEM">STEM</option><option value="ABM">ABM</option><option value="HUMSS">HUMSS</option><option value="GAS">GAS</option><option value="ICT">ICT</option><option value="HE">HE</option><option value="IA">IA</option>
                        </select>
                    </div>
                    <div class="form-group"><label for="teacher_name">Adviser / Teacher Name</label>
                        <input type="text" id="teacher_name" name="teacher_name" class="form-control" placeholder="e.g. Mrs. Dela Cruz" required>
                    </div>
                    <button type="submit" name="add_section" class="btn btn-primary"><i class="fas fa-save"></i> Create Section</button>
                </form>
            </div>

            <!-- Sections List -->
            <div class="panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i><h2>Active Sections</h2></div></div>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Code</th><th>Section Name</th><th>Grade & Strand</th><th>Adviser</th><th style="text-align:right">Action</th></tr></thead>
                        <tbody>
                            <?php if ($sections->num_rows > 0): ?>
                                <?php while ($row = $sections->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="code-badge"><?php echo htmlspecialchars($row['section_code']); ?></span></td>
                                    <td style="font-weight:500"><?php echo htmlspecialchars($row['section_name']); ?></td>
                                    <td>Grade <?php echo htmlspecialchars($row['grade_level']); ?><br><span style="font-size:.85em;color:var(--text-muted)"><?php echo htmlspecialchars($row['track']); ?> &mdash; <?php echo htmlspecialchars($row['strand']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                    <td style="text-align:right">
                                        <button type="button" class="btn btn-primary" style="margin-right:5px;"
                                            data-id="<?php echo $row['section_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($row['section_name']); ?>"
                                            data-grade="<?php echo htmlspecialchars($row['grade_level']); ?>"
                                            data-track="<?php echo htmlspecialchars($row['track']); ?>"
                                            data-strand="<?php echo htmlspecialchars($row['strand']); ?>"
                                            data-teacher="<?php echo htmlspecialchars($row['teacher_name']); ?>"
                                            onclick="openEditModal(this)"><i class="fas fa-edit"></i></button>
                                        <a href="?delete_section=<?php echo $row['section_id']; ?>" onclick="return confirm('Are you sure you want to delete this section?')" class="btn btn-danger"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-folder-open" style="font-size:2rem;margin-bottom:12px;opacity:.5;display:block"></i>No sections created yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Section Modal -->
    <div id="editModal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div class="panel" style="width:100%;max-width:500px;margin:20px;">
            <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
                <div class="panel-title"><i class="fas fa-edit"></i><h2>Edit Section</h2></div>
                <button type="button" onclick="closeEditModal()" style="background:none;border:none;color:var(--text-main);font-size:1.5rem;cursor:pointer;">&times;</button>
            </div>
            <form action="section.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="edit_section" value="1">
                <input type="hidden" id="edit_section_id" name="section_id" value="">
                
                <div class="form-group"><label for="edit_section_name">Section Name</label>
                    <input type="text" id="edit_section_name" name="section_name" class="form-control" required>
                </div>
                <div class="form-group"><label for="edit_grade_level">Grade Level</label>
                    <select id="edit_grade_level" name="grade_level" class="form-control" required>
                        <option value="11">Grade 11</option><option value="12">Grade 12</option>
                    </select>
                </div>
                <div class="form-group"><label for="edit_track">Track</label>
                    <select id="edit_track" name="track" class="form-control" required>
                        <option value="Academic">Academic</option><option value="TVL">TVL</option>
                    </select>
                </div>
                <div class="form-group"><label for="edit_strand">Strand</label>
                    <select id="edit_strand" name="strand" class="form-control" required>
                        <option value="STEM">STEM</option><option value="ABM">ABM</option><option value="HUMSS">HUMSS</option><option value="GAS">GAS</option><option value="ICT">ICT</option><option value="HE">HE</option><option value="IA">IA</option>
                    </select>
                </div>
                <div class="form-group"><label for="edit_teacher_name">Adviser / Teacher Name</label>
                    <input type="text" id="edit_teacher_name" name="teacher_name" class="form-control" required>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                    <button type="button" onclick="closeEditModal()" class="btn" style="background:#4b5563;color:white;padding:10px 16px;border-radius:6px;border:none;cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../static/sidebar.js?v=2"></script>
    <script>
        function openEditModal(btn) {
            document.getElementById('edit_section_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_section_name').value = btn.getAttribute('data-name');
            document.getElementById('edit_grade_level').value = btn.getAttribute('data-grade');
            document.getElementById('edit_track').value = btn.getAttribute('data-track');
            document.getElementById('edit_strand').value = btn.getAttribute('data-strand');
            document.getElementById('edit_teacher_name').value = btn.getAttribute('data-teacher');
            
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
</body>
</html>
