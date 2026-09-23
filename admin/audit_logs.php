<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth_guard.php';

$conn = get_db_connection();
enforce_auth('superadmin'); // Only superadmins can view audit logs

// Fetch logs
$logs = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 500");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Logs | Superadmin</title>
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
        td{padding:16px;border-bottom:1px solid rgba(255,255,255,.03);color:#e5e7eb;vertical-align:middle;font-size:0.9rem;}
        tr:hover td{background:rgba(255,255,255,.02)}
        .role-badge{font-family:monospace;background:rgba(59,130,246,.1);color:var(--primary);padding:4px 8px;border-radius:4px;font-weight:bold;text-transform:uppercase;font-size:0.8rem;}
        .ip-addr{font-family:monospace;color:var(--text-muted);}
        
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
                    <h1>System Audit Logs</h1>
                    <p>Review recent administrative actions and security events.</p>
                </div>
            </div>
        </header>

        <div class="panel">
            <div class="panel-header"><div class="panel-title"><i class="fas fa-list-alt"></i><h2>Action Trail (Last 500)</h2></div></div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User ID</th>
                            <th>Role</th>
                            <th>Action Performed</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while ($row = $logs->fetch_assoc()): ?>
                            <tr>
                                <td style="white-space:nowrap;color:var(--text-muted);"><?php echo date('M d, Y H:i:s', strtotime($row['created_at'])); ?></td>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($row['user_id']); ?></td>
                                <td><span class="role-badge"><?php echo htmlspecialchars($row['role']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['action']); ?></td>
                                <td><span class="ip-addr"><?php echo htmlspecialchars($row['ip_address']); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-check-circle" style="font-size:2rem;margin-bottom:12px;opacity:.5;display:block"></i>No logs recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="../static/sidebar.js?v=2"></script>
</body>
</html>
