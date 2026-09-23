<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

// Ensure only superadmins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msgType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['create_teacher'])) {
        $employee_id = trim($_POST['employee_id']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $department = trim($_POST['department']);
        $password_plain = $_POST['password'];
        
        $hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);

        // Check if employee_id exists
        $check = $conn->prepare("SELECT id FROM teachers WHERE employee_id = ?");
        $check->bind_param("s", $employee_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = "A teacher with this Employee ID already exists!";
            $msgType = "error";
        } else {
            $sql = "INSERT INTO teachers (employee_id, password, first_name, last_name, department) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $employee_id, $hashed_password, $first_name, $last_name, $department);
            if ($stmt->execute()) {
                $msg = "New teacher created successfully!";
                $msgType = "success";
            } else {
                $msg = "Error creating teacher: " . $conn->error;
                $msgType = "error";
            }
            $stmt->close();
        }
        $check->close();
    } elseif (isset($_POST['delete_teacher'])) {
        $teacher_id = intval($_POST['teacher_id']);
        $del = $conn->prepare("DELETE FROM teachers WHERE id = ?");
        $del->bind_param("i", $teacher_id);
        if ($del->execute()) {
            $msg = "Teacher deleted successfully!";
            $msgType = "success";
        } else {
            $msg = "Error deleting teacher.";
            $msgType = "error";
        }
        $del->close();
    }
}

// Fetch teachers list
$teachers_result = $conn->query("SELECT id, employee_id, first_name, last_name, department, created_at FROM teachers ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers | Superadmin</title>
    <link rel="icon" href="../image/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--bg-dark:#0f1115;--bg-panel:rgba(26,29,36,.7);--bg-panel-hover:rgba(35,40,48,.8);--text-main:#f3f4f6;--text-muted:#9ca3af;--primary:#3b82f6;--primary-hover:#2563eb;--accent:#8b5cf6;--danger:#ef4444;--success:#10b981;--border:rgba(255,255,255,.08);--glass:blur(12px)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-dark);background-image:radial-gradient(circle at 15% 50%,rgba(59,130,246,.12),transparent 25%),radial-gradient(circle at 85% 30%,rgba(139,92,246,.12),transparent 25%);background-attachment:fixed;color:var(--text-main);display:flex;min-height:100vh}
        
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;flex-wrap:wrap;gap:20px}
        .header h1{font-size:1.8rem;font-weight:600}.header p{color:var(--text-muted);margin-top:4px}
        .table-responsive{overflow-x:auto}
        table{width:100%;border-collapse:collapse;text-align:left}
        th{padding:16px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;white-space:nowrap}
        td{padding:16px;border-bottom:1px solid rgba(255,255,255,.03);color:#e5e7eb;vertical-align:middle;white-space:nowrap}
        tr:hover td{background:rgba(255,255,255,.02)}
        .badge{font-family:monospace;background:rgba(59,130,246,.1);color:var(--primary);padding:4px 8px;border-radius:4px;font-weight:bold}
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
                <div>
                    <h1>Manage Teachers</h1>
                    <p>Create and remove teacher portal accounts.</p>
                </div>
            </div>
            <div class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </header>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msgType; ?>">
                <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Add Teacher Form -->
            <div class="panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-user-plus"></i><h2>Create Teacher</h2></div></div>
                <form action="manage_teachers.php" method="POST">
                    <div class="form-group">
                        <label for="employee_id">Employee ID / Username</label>
                        <input type="text" id="employee_id" name="employee_id" class="form-control" placeholder="e.g. TCH-001" required>
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" placeholder="e.g. John" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" placeholder="e.g. Doe" required>
                    </div>
                    <div class="form-group">
                        <label for="department">Department (Optional)</label>
                        <input type="text" id="department" name="department" class="form-control" placeholder="e.g. Science">
                    </div>
                    <div class="form-group" style="position: relative;">
                        <label for="password">Initial Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter secure password" required>
                        <i class="fas fa-eye password-toggle" id="togglePassword" style="position: absolute; right: 14px; top: 38px; cursor: pointer; color: var(--text-muted);"></i>
                    </div>
                    <button type="submit" name="create_teacher" class="btn btn-primary"><i class="fas fa-user-check"></i> Register Teacher</button>
                </form>
            </div>

            <!-- Teachers List -->
            <div class="panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-users"></i><h2>Registered Teachers</h2></div></div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Date Added</th>
                                <th style="text-align:right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($teachers_result->num_rows > 0): ?>
                                <?php while ($row = $teachers_result->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="badge"><?php echo htmlspecialchars($row['employee_id']); ?></span></td>
                                    <td style="font-weight:500"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department'] ?: 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td style="text-align:right">
                                        <form action="manage_teachers.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this teacher account?');" style="display:inline;">
                                            <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="delete_teacher" class="btn btn-danger" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-folder-open" style="font-size:2rem;margin-bottom:12px;opacity:.5;display:block"></i>No teachers registered yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        if (togglePassword && password) {
            togglePassword.addEventListener('click', function () {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
            });
        }
    </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
