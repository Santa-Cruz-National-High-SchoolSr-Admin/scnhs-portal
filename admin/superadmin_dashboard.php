<?php
session_start();

// Ensure only superadmins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

// Fetch stats
$total_students = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()['count'];
$total_teachers = $conn->query("SELECT COUNT(*) AS count FROM teachers")->fetch_assoc()['count'];
$total_sections = $conn->query("SELECT COUNT(*) AS count FROM sections")->fetch_assoc()['count'];
$total_admins = $conn->query("SELECT COUNT(*) AS count FROM accounts WHERE role = 'admin'")->fetch_assoc()['count'];
$total_registrars = $conn->query("SELECT COUNT(*) AS count FROM accounts WHERE role = 'registrar'")->fetch_assoc()['count'];
$pending_enrollments = $conn->query("SELECT COUNT(*) AS count FROM students WHERE status = 'pending'")->fetch_assoc()['count'];

// Fetch recent 5 audit logs
$recent_logs = $conn->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 5");

// System Health Info
$php_version = phpversion();
$mysql_version = $conn->server_info;

// Get approximate DB size
$db_name = "if0_42429154_scnhs_db"; // As per config.php
$size_query = $conn->query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb FROM information_schema.tables WHERE table_schema = '$db_name'");
$db_size_mb = 0;
if ($size_query && $size_query->num_rows > 0) {
    $size_row = $size_query->fetch_assoc();
    $db_size_mb = round($size_row['size_mb'], 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Dashboard | SCNHS</title>
    <link rel="icon" href="../image/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <?php include 'includes/sidebar.php'; ?>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header">
            <div>
                <h1>Superadmin Overview</h1>
                <p>Welcome to the central control panel for the SCNHS portal.</p>
            </div>
            <div class="user-profile">
                <i class="fas fa-shield-alt"></i>
                <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </header>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_students; ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_teachers; ?></h3>
                    <p>Total Teachers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="fas fa-layer-group"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_sections; ?></h3>
                    <p>Sections</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-red"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $pending_enrollments; ?></h3>
                    <p>Pending Enrollments</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-user-shield"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_admins; ?></h3>
                    <p>Active Admins</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-user-edit"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_registrars; ?></h3>
                    <p>Active Registrars</p>
                </div>
            </div>
        </div>

        <div class="content-grid" style="grid-template-columns: 2fr 1fr;">
            <!-- Recent Activity -->
            <div class="panel">
                <div class="panel-header" style="display:flex; justify-content:space-between;">
                    <div>
                        <i class="fas fa-history"></i>
                        <h2 style="display:inline-block; margin-left:8px;">Recent Audit Logs</h2>
                    </div>
                    <a href="audit_logs.php" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.85rem;">View All</a>
                </div>
                <div class="panel-body" style="padding: 0;">
                    <?php if ($recent_logs && $recent_logs->num_rows > 0): ?>
                        <?php while($log = $recent_logs->fetch_assoc()): ?>
                            <div class="log-item">
                                <div class="log-details">
                                    <span class="log-action"><?php echo htmlspecialchars($log['action']); ?></span>
                                    <span class="log-meta">
                                        <i class="fas fa-user" style="margin-right:4px;"></i> <?php echo htmlspecialchars($log['user_id']); ?> 
                                        (<?php echo htmlspecialchars($log['role']); ?>) - 
                                        <?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?>
                                    </span>
                                </div>
                                <span class="badge" style="background: #f1f5f9; color: #64748b; font-family: monospace; font-size:0.75rem;">
                                    <?php echo htmlspecialchars($log['ip_address']); ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: #94a3b8;">
                            No recent activity recorded.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Health -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-server"></i>
                    <h2 style="display:inline-block; margin-left:8px;">System Health</h2>
                </div>
                <div class="panel-body">
                    <div style="display: grid; gap: 15px;">
                        <div class="health-card">
                            <h4>PHP Version</h4>
                            <p><?php echo htmlspecialchars($php_version); ?></p>
                        </div>
                        <div class="health-card">
                            <h4>MySQL Version</h4>
                            <p><?php echo htmlspecialchars(explode('-', $mysql_version)[0] ?? $mysql_version); ?></p>
                        </div>
                        <div class="health-card">
                            <h4>Database Size</h4>
                            <p><?php echo $db_size_mb; ?> MB</p>
                        </div>
                    </div>
                    <div style="margin-top: 20px; text-align: center;">
                        <a href="superadmin_system.php" class="btn btn-secondary" style="width: 100%;">
                            <i class="fas fa-cogs"></i> Advanced System Tools
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </main>

<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
