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
    if (isset($_POST['add_announcement'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $published_date = date('Y-m-d'); // Use today's date

        $sql = "INSERT INTO announcements (title, content, author, published_date) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $title, $content, $admin_name, $published_date);

        if ($stmt->execute()) {
            $_SESSION['msg'] = "Announcement published successfully!";
            $_SESSION['msgType'] = "success";
        } else {
            $_SESSION['msg'] = "Error: " . $stmt->error;
            $_SESSION['msgType'] = "error";
        }
        $stmt->close();
    } elseif (isset($_POST['delete_announcement'])) {
        $id = intval($_POST['announcement_id']);
        $del = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) {
            $_SESSION['msg'] = "Announcement deleted successfully!";
            $_SESSION['msgType'] = "success";
        } else {
            $_SESSION['msg'] = "Error deleting announcement.";
            $_SESSION['msgType'] = "error";
        }
        $del->close();
    }
    
    // Redirect to self (PRG pattern)
    header("Location: manage_announcements.php");
    exit();
}

// Fetch announcements
$announcements = $conn->query("SELECT * FROM announcements ORDER BY published_date DESC, created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage News & Announcements | SCNHS</title>
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
                    <h1>News & Announcements</h1>
                    <p>Publish important updates for the school.</p>
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
            <!-- Add Announcement Form -->
            <div class="panel" style="height: fit-content;">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-plus-circle"></i><h2>Publish Update</h2></div></div>
                <form action="manage_announcements.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-group">
                        <label for="title">Announcement Title</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Enrollment Starts Today!" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Announcement Content</label>
                        <textarea id="content" name="content" class="form-control" placeholder="Type your announcement here..." required></textarea>
                    </div>
                    <button type="submit" name="add_announcement" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish Now</button>
                </form>
            </div>

            <!-- Published Announcements List -->
            <div class="panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i><h2>Recent Announcements</h2></div></div>
                <div class="news-list">
                    <?php if ($announcements->num_rows > 0): ?>
                        <?php while ($row = $announcements->fetch_assoc()): ?>
                            <div class="news-item">
                                <div class="news-header">
                                    <div class="news-title"><?php echo htmlspecialchars($row['title']); ?></div>
                                </div>
                                <div class="news-meta" style="margin-bottom:12px;">
                                    <span><i class="fas fa-calendar-day"></i> <?php echo date('F j, Y', strtotime($row['published_date'])); ?></span>
                                    <span><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($row['author']); ?></span>
                                </div>
                                <div class="news-content">
                                    <?php echo nl2br(htmlspecialchars($row['content'])); ?>
                                </div>
                                <div class="news-actions">
                                    <form action="manage_announcements.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this announcement?');" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="announcement_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_announcement" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:40px;color:var(--text-muted)">
                            <i class="fas fa-inbox" style="font-size:2.5rem;margin-bottom:16px;opacity:0.5;display:block"></i>
                            No announcements have been published yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="../static/sidebar.js?v=2"></script>
</body>
</html>
