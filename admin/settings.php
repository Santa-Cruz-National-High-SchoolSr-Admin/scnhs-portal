<?php
session_start();

// Redirect to login page if the admin is NOT logged in
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../login.php");
    exit();
}

// Auto logout after 15 minutes of inactivity
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 900)) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
} else {
    $_SESSION['login_time'] = time(); // Reset session time
}

// Connect to the database
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

// Ensure the settings table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `settings` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` VARCHAR(255) DEFAULT NULL,
        `description`   VARCHAR(255) DEFAULT NULL,
        `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Insert default values if missing
$conn->query("
    INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
    ('enable_final_exam', '0', 'Toggle final exam visibility/access'),
    ('enable_show_grades', '0', 'Toggle visibility of grades to students'),
    ('enable_sslg_voting', '0', 'Toggle SSLG election voting for students'),
    ('hero_banner_text', 'School Year 2025-2026 Enrollment Now Open', 'Main banner text on the homepage');
");

$success_message = '';
$error_message = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $toUpdate = [
        'enable_final_exam'  => isset($_POST['enable_final_exam'])  ? '1' : '0',
        'enable_show_grades' => isset($_POST['enable_show_grades']) ? '1' : '0',
        'enable_sslg_voting' => isset($_POST['enable_sslg_voting']) ? '1' : '0',
        'hero_banner_text'   => $_POST['hero_banner_text'] ?? 'School Year 2025-2026 Enrollment Now Open',
    ];
    $allOk = true;
    foreach ($toUpdate as $key => $val) {
        $s = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $s->bind_param("ss", $val, $key);
        if (!$s->execute()) $allOk = false;
        $s->close();
    }
    if ($allOk) {
        $success_message = "Settings updated successfully.";
        if (function_exists('log_audit')) log_audit($conn, $_SESSION['username'], $_SESSION['role'], 'Updated global settings.');
    } else {
        $error_message = "One or more settings failed to update.";
    }
}

// Fetch Current Settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value, description FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/logo.png" type="image/x-icon">
    <title>Global Settings | SCNHS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
    <style>
        .settings-form {
            max-width: 600px;
        }
        .setting-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .setting-info h3 {
            font-size: 1rem;
            margin-bottom: 4px;
            color: #fff;
        }
        .setting-info p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 0;
        }
        /* Toggle Switch CSS */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #4b5563;
            transition: .4s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: var(--primary);
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
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
            <div style="display: flex; align-items: center; gap: 16px;">
                <button type="button" class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
                <div>
                    <h1>Global Settings</h1>
                    <p>Manage application-wide configurations and features.</p>
                </div>
            </div>
                <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </header>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-sliders-h"></i>
                    <h2>System Toggles</h2>
                </div>
            </div>
            
            <div style="padding: 24px;">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="settings.php" class="settings-form">
                    <!-- Final Exam Toggle -->
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Enable Final Exam</h3>
                            <p><?php echo htmlspecialchars($settings['enable_final_exam']['description'] ?? 'Toggle final exam visibility/access'); ?></p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="enable_final_exam" value="1" <?php echo (isset($settings['enable_final_exam']) && $settings['enable_final_exam']['setting_value'] === '1') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <!-- Show Grades Toggle -->
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Show Grades to Students</h3>
                            <p><?php echo htmlspecialchars($settings['enable_show_grades']['description'] ?? 'Toggle visibility of grades to students'); ?></p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="enable_show_grades" value="1" <?php echo (isset($settings['enable_show_grades']) && $settings['enable_show_grades']['setting_value'] === '1') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <!-- SSLG Voting Toggle -->
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Enable SSLG Elections Voting</h3>
                            <p><?php echo htmlspecialchars($settings['enable_sslg_voting']['description'] ?? 'Toggle SSLG election voting for students'); ?></p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="enable_sslg_voting" value="1" <?php echo (isset($settings['enable_sslg_voting']) && $settings['enable_sslg_voting']['setting_value'] === '1') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <!-- Hero Banner Text -->
                    <div class="setting-item" style="flex-direction: column; align-items: flex-start; gap: 10px;">
                        <div class="setting-info" style="width: 100%;">
                            <h3>Homepage Banner Text</h3>
                            <p><?php echo htmlspecialchars($settings['hero_banner_text']['description'] ?? 'Main banner text on the homepage'); ?></p>
                        </div>
                        <input type="text" name="hero_banner_text" value="<?php echo htmlspecialchars($settings['hero_banner_text']['setting_value'] ?? 'School Year 2025-2026 Enrollment Now Open'); ?>" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: #fff; font-family: inherit;">
                    </div>

                    <div style="margin-top: 24px;">
                        <button type="submit" name="update_settings" class="btn btn-primary" style="padding: 10px 20px; font-size: 1rem;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
