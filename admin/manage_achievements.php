<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

require_once dirname(__DIR__) . '/auth_guard.php';
enforce_auth('admin');

$msg = "";
$msgType = "";
$admin_name = $_SESSION['username'] ?? 'Admin';

// Handle session messages
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msgType = $_SESSION['msgType'];
    unset($_SESSION['msg'], $_SESSION['msgType']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_Achievement'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $year = trim($_POST['year']); 
        $icon = trim($_POST['icon']) ?: '&#127942;'; // default trophy

        $sql = "INSERT INTO achievements (title, description, year, icon) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $title, $description, $year, $icon);

        if ($stmt->execute()) {
            $_SESSION['msg'] = "Achievement published successfully!";
            $_SESSION['msgType'] = "success";
        } else {
            $_SESSION['msg'] = "Error: " . $stmt->error;
            $_SESSION['msgType'] = "error";
        }
        $stmt->close();
    } elseif (isset($_POST['delete_Achievement'])) {
        $id = intval($_POST['Achievement_id']);
        $del = $conn->prepare("DELETE FROM achievements WHERE id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) {
            $_SESSION['msg'] = "Achievement deleted successfully!";
            $_SESSION['msgType'] = "success";
        } else {
            $_SESSION['msg'] = "Error deleting Achievement.";
            $_SESSION['msgType'] = "error";
        }
        $del->close();
    }
    
    // Redirect to self (PRG pattern)
    header("Location: manage_achievements.php");
    exit();
}

// Fetch Achievements
$Achievements = $conn->query("SELECT * FROM achievements ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" description="width=device-width, initial-scale=1.0">
    <title>Manage School Achievements | SCNHS</title>
    <link rel="icon" href="../image/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--bg-dark:#0b1120;--bg-panel:rgba(17,24,39,.75);--bg-panel-hover:rgba(31,41,55,.85);--text-main:#f9fafb;--text-muted:#9ca3af;--primary:#3b82f6;--primary-hover:#2563eb;--accent:#10b981;--danger:#ef4444;--border:rgba(255,255,255,.08);--glass:blur(16px)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-dark);background-image:radial-gradient(circle at 85% 15%,rgba(59,130,246,.08),transparent 25%),radial-gradient(circle at 15% 85%,rgba(16,185,129,.05),transparent 25%);background-attachment:fixed;color:var(--text-main);display:flex;min-height:100vh}
        
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:20px}
        .header h1{font-size:1.8rem;font-weight:600}.header p{color:var(--text-muted);margin-top:4px}
        textarea{width:100%;min-height:100px;padding:12px;background:rgba(0,0,0,.2);border:1px solid var(--border);border-radius:8px;color:#fff;font-family:inherit;font-size:.9rem;resize:vertical}
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
                    <h1>School Achievements</h1>
                    <p>Manage school achievements and awards for the school.</p>
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
            <!-- Add Achievement Form -->
            <div class="panel" style="height: fit-content;">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-plus-circle"></i><h2>Add Achievement</h2></div></div>
                <form action="manage_achievements.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-group">
                        <label for="title">Achievement Title</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Regional Science Fair Champion" required>
                    </div>
                    <div class="form-group">
                        <label for="year">Year</label>
                        <input type="text" id="year" name="year" class="form-control" placeholder="e.g. 2024" required>
                    </div>
                    <div class="form-group">
                        <label for="icon">Icon (HTML entity or Emoji)</label>
                        <input type="text" id="icon" name="icon" class="form-control" placeholder="e.g. &#127942; or ðŸ†">
                    </div>
                    <div class="form-group">
                        <label for="description">Achievement Description</label>
                        <textarea id="description" name="description" class="form-control" placeholder="Type achievement details here..." required></textarea>
                    </div>
                    <button type="submit" name="add_Achievement" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Add Achievement</button>
                </form>
            </div>

            <!-- Published Achievements List -->
            <div class="panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i><h2>Recent Achievements</h2></div></div>
                <div class="news-list">
                    <?php if ($Achievements->num_rows > 0): ?>
                        <?php while ($row = $Achievements->fetch_assoc()): ?>
                            <div class="news-item">
                                <div class="news-header">
                                    <div class="news-title"><?php echo htmlspecialchars($row['title']); ?></div>
                                </div>
                                <div class="news-meta" style="margin-bottom:12px;">
                                    <span><i class="fas fa-calendar-day"></i> <?php echo htmlspecialchars($row['year']); ?></span>
                                    <span>Icon: <?php echo htmlspecialchars($row['icon']); ?></span>
                                </div>
                                <div class="news-description">
                                    <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                                </div>
                                <div class="news-actions">
                                    <form action="manage_achievements.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this Achievement?');" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="Achievement_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_Achievement" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:40px;color:var(--text-muted)">
                            <i class="fas fa-inbox" style="font-size:2.5rem;margin-bottom:16px;opacity:0.5;display:block"></i>
                            No Achievements have been published yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="../static/sidebar.js?v=2"></script>
</body>
</html>

