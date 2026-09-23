<?php
session_start();

// Ensure only superadmins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';

// Generate CSRF token
$csrf_token = generate_csrf_token();

$msg = "";
$msgType = "";

// Handle Database Backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    // CSRF Validation
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Security error: Invalid request token. Please refresh and try again.";
        $msgType = "error";
    } else {
        // Generate backup
        $conn = get_db_connection();
        $db_name = DB_NAME; 
        
        // Set headers for download
        $filename = "backup_" . $db_name . "_" . date("Y-m-d_H-i-s") . ".sql";
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output SQL dump
        echo "-- Database Backup for $db_name\n";
        echo "-- Generated on: " . date("Y-m-d H:i:s") . "\n\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        $tables = array();
        $result = $conn->query("SHOW TABLES");
        while($row = $result->fetch_row()){
            $tables[] = $row[0];
        }
        
        foreach($tables as $table){
            $result = $conn->query("SELECT * FROM `$table`");
            $num_fields = $result->field_count;
            
            $row2 = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
            echo "\n\n" . $row2[1] . ";\n\n";
            
            for ($i = 0; $i < $num_fields; $i++) {
                while($row = $result->fetch_row()) {
                    echo "INSERT INTO `$table` VALUES(";
                    for($j=0; $j<$num_fields; $j++) {
                        if ($row[$j] === null) {
                            echo "NULL";
                        } else {
                            $escaped = addslashes($row[$j]);
                            $escaped = str_replace("\n","\\n", $escaped);
                            echo '"' . $escaped . '"';
                        }
                        if ($j<($num_fields-1)) { echo ','; }
                    }
                    echo ");\n";
                }
            }
            echo "\n\n\n";
        }
        echo "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Log the backup action
        log_audit($conn, $_SESSION['username'], 'superadmin', "Downloaded database backup ($filename)");
        
        exit(); // Terminate to prevent HTML rendering in SQL file
    }
}

$conn = get_db_connection();
$php_version = phpversion();
$mysql_version = $conn->server_info;
$server_software = $_SERVER['SERVER_SOFTWARE'];
$max_execution_time = ini_get('max_execution_time');
$memory_limit = ini_get('memory_limit');
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');

// Get approximate DB size
$db_name = "if0_42429154_scnhs_db";
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
    <title>System & Backup | SCNHS</title>
    <link rel="icon" href="../image/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
    <style>
        .system-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .info-card { background: var(--white); border: 1px solid var(--border-light); padding: 20px; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
        .info-card h4 { margin: 0 0 15px 0; color: var(--text-dark); font-size: 1rem; border-bottom: 2px solid var(--border-light); padding-bottom: 8px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed var(--border-light); }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-size: 0.88rem; }
        .info-value { font-weight: 600; color: var(--text-dark); font-size: 0.88rem; font-family: monospace; }
        .backup-card { text-align: center; padding: 30px 20px; background: var(--blue-pale); border: 1px solid rgba(20,86,217,0.15); border-radius: var(--radius-lg); }
        .backup-icon { font-size: 3rem; color: var(--blue); margin-bottom: 15px; }
    </style>
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
                <h1>System & Backup Tools</h1>
                <p>Monitor server environment and manage database backups.</p>
            </div>
            <div class="user-profile">
                <i class="fas fa-shield-alt"></i>
                <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </header>

        <div class="content-grid" style="grid-template-columns: 2fr 1fr;">
            <!-- System Information -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-microchip"></i>
                    <h2>Environment Information</h2>
                </div>
                <div class="panel-body">
                    <div class="system-info-grid">
                        <div class="info-card">
                            <h4>Software Versions</h4>
                            <div class="info-row">
                                <span class="info-label">PHP Version</span>
                                <span class="info-value"><?php echo htmlspecialchars($php_version); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">MySQL Version</span>
                                <span class="info-value"><?php echo htmlspecialchars($mysql_version); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Server</span>
                                <span class="info-value"><?php echo htmlspecialchars($server_software); ?></span>
                            </div>
                        </div>

                        <div class="info-card">
                            <h4>PHP Configuration</h4>
                            <div class="info-row">
                                <span class="info-label">Max Execution Time</span>
                                <span class="info-value"><?php echo htmlspecialchars($max_execution_time); ?>s</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Memory Limit</span>
                                <span class="info-value"><?php echo htmlspecialchars($memory_limit); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Upload Max Size</span>
                                <span class="info-value"><?php echo htmlspecialchars($upload_max_filesize); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Post Max Size</span>
                                <span class="info-value"><?php echo htmlspecialchars($post_max_size); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Backup -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-database"></i>
                    <h2>Database Backup</h2>
                </div>
                <div class="panel-body">
                    <div class="backup-card">
                        <i class="fas fa-file-export backup-icon"></i>
                        <h3 style="margin: 0 0 10px 0; color: #1e293b;">Export Database</h3>
                        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 20px;">
                            Download a full `.sql` backup of the database structure and data. 
                            <br><br><strong>Current Size:</strong> <?php echo $db_size_mb; ?> MB
                        </p>
                        <form method="POST" action="" onsubmit="return confirm('Generate and download a full database backup? This may take a moment for large databases.');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="backup">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-download"></i> Download .sql Backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </main>

<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
