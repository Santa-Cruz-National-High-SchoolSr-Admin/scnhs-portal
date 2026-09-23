<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

// Check if user is logged in
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msgType = "";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msgType = $_SESSION['msgType'];
    unset($_SESSION['msg'], $_SESSION['msgType']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_event'])) {
        $title = trim($_POST['event_title']);
        $date = trim($_POST['event_date']);
        $description = trim($_POST['description']);

        $sql = "INSERT INTO calendar_events (event_title, event_date, description) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $title, $date, $description);

        if ($stmt->execute()) {
            $_SESSION['msg'] = "Calendar event added successfully!";
            $_SESSION['msgType'] = "success";
            $stmt->close();
            header("Location: manage_calendar.php");
            exit();
        } else {
            $msg = "Error: " . $stmt->error;
            $msgType = "error";
        }
        $stmt->close();
    } elseif (isset($_POST['delete_event'])) {
        $id = intval($_POST['event_id']);
        $del = $conn->prepare("DELETE FROM calendar_events WHERE id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) {
            $_SESSION['msg'] = "Event deleted successfully!";
            $_SESSION['msgType'] = "success";
            $del->close();
            header("Location: manage_calendar.php");
            exit();
        } else {
            $msg = "Error deleting event.";
            $msgType = "error";
        }
        $del->close();
    }
}

// Fetch upcoming events
$events = $conn->query("SELECT * FROM calendar_events ORDER BY event_date ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage School Calendar | SCNHS</title>
    <link rel="icon" href="../image/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--bg-dark:#0b1120;--bg-panel:rgba(17,24,39,.75);--bg-panel-hover:rgba(31,41,55,.85);--text-main:#f9fafb;--text-muted:#9ca3af;--primary:#3b82f6;--primary-hover:#2563eb;--accent:#10b981;--danger:#ef4444;--border:rgba(255,255,255,.08);--glass:blur(16px)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-dark);background-image:radial-gradient(circle at 85% 15%,rgba(59,130,246,.08),transparent 25%),radial-gradient(circle at 15% 85%,rgba(16,185,129,.05),transparent 25%);background-attachment:fixed;color:var(--text-main);display:flex;min-height:100vh}
        
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:20px}
        .header h1{font-size:1.8rem;font-weight:600}.header p{color:var(--text-muted);margin-top:4px}
        textarea
        
        /* Table Styles */
        .table-responsive{overflow-x:auto}
        table{width:100%;border-collapse:collapse;text-align:left}
        th{padding:16px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;white-space:nowrap}
        td{padding:16px;border-bottom:1px solid rgba(255,255,255,.03);color:#e5e7eb;vertical-align:middle;white-space:nowrap}
        tr:hover td{background:rgba(255,255,255,.02)}
        .date-badge{font-family:monospace;background:rgba(59,130,246,.1);color:var(--primary);padding:6px 10px;border-radius:6px;font-weight:bold;font-size:0.9rem}
        .desc-text{font-size:0.9rem;color:var(--text-muted);margin-top:4px;max-width:300px;white-space:normal;line-height:1.4}
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
                    <h1>School Calendar</h1>
                    <p>Manage upcoming events and activities.</p>
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
            <!-- Add Event Form -->
            <div class="panel" style="height: fit-content;">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-calendar-plus"></i><h2>Add New Event</h2></div></div>
                <form action="manage_calendar.php" method="POST">
                    <div class="form-group">
                        <label for="event_title">Event Title</label>
                        <input type="text" id="event_title" name="event_title" class="form-control" placeholder="e.g. Intramurals 2026" required>
                    </div>
                    <div class="form-group">
                        <label for="event_date">Event Date</label>
                        <input type="date" id="event_date" name="event_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Short Description (Optional)</label>
                        <textarea id="description" name="description" class="form-control" placeholder="Briefly describe the event..."></textarea>
                    </div>
                    <button type="submit" name="add_event" class="btn btn-primary"><i class="fas fa-save"></i> Save Event</button>
                </form>
            </div>

            <!-- Events List -->
            <div class="panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i><h2>Scheduled Events</h2></div></div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Event Details</th>
                                <th style="text-align:right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($events->num_rows > 0): ?>
                                <?php while ($row = $events->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span class="date-badge">
                                                <i class="far fa-calendar"></i> 
                                                <?php echo date('M d, Y', strtotime($row['event_date'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;font-size:1.05rem;color:#fff;"><?php echo htmlspecialchars($row['event_title']); ?></div>
                                            <?php if (!empty($row['description'])): ?>
                                                <div class="desc-text"><?php echo htmlspecialchars($row['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right">
                                            <form action="manage_calendar.php" method="POST" onsubmit="return confirm('Are you sure you want to remove this event?');" style="margin:0;">
                                                <input type="hidden" name="event_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="delete_event" class="btn btn-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;padding:40px;color:var(--text-muted)">
                                        <i class="fas fa-calendar-times" style="font-size:2.5rem;margin-bottom:16px;opacity:0.5;display:block"></i>
                                        No upcoming events scheduled.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="../static/sidebar.js?v=2"></script>
</body>
</html>
