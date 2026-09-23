<?php
session_start();

// Ensure only superadmins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit();
}

require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

// Generate CSRF token
$csrf_token = generate_csrf_token();

$msg = "";
$msgType = "";

// Whitelist of valid roles
$valid_roles = ['admin', 'registrar', 'superadmin'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // CSRF Validation on ALL POST requests
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Security error: Invalid request token. Please refresh and try again.";
        $msgType = "error";
    } else {
    
        // ── Add User ──
        if (isset($_POST['add_user'])) {
            $username = trim($_POST['username']);
            $role = $_POST['role_type'] ?? '';
            $raw_password = $_POST['password'] ?? '';
            
            // Validate role
            if (!in_array($role, $valid_roles)) {
                $msg = "Invalid role specified.";
                $msgType = "error";
            } elseif (strlen($username) < 3) {
                $msg = "Username must be at least 3 characters.";
                $msgType = "error";
            } elseif (strlen($raw_password) < 6) {
                $msg = "Password must be at least 6 characters.";
                $msgType = "error";
            } else {
                $password = password_hash($raw_password, PASSWORD_DEFAULT);
                
                // Check if username exists across all roles in unified table
                $check = $conn->prepare("SELECT id FROM `accounts` WHERE username = ?");
                $check->bind_param("s", $username);
                $check->execute();
                $check->store_result();
                
                if ($check->num_rows > 0) {
                    $msg = "Username already exists.";
                    $msgType = "error";
                } else {
                    // Always insert into the unified accounts table and explicitly set the role
                    $sql = "INSERT INTO `accounts` (username, password, role) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $username, $password, $role);
                    
                    if ($stmt->execute()) {
                        $msg = ucfirst($role) . " account created successfully.";
                        $msgType = "success";
                        log_audit($conn, $_SESSION['username'], 'superadmin', "Created $role account: $username");
                    } else {
                        $msg = "Error creating account. Please try again.";
                        $msgType = "error";
                    }
                }
            }
        }
        
        // ── Delete User ──
        if (isset($_POST['delete_user'])) {
            $user_id = intval($_POST['user_id']);
            $role = $_POST['role_type'] ?? '';
            $username = trim($_POST['username'] ?? '');
            
            if (!in_array($role, $valid_roles)) {
                $msg = "Invalid role specified.";
                $msgType = "error";
            } elseif ($role === 'superadmin' && $username === $_SESSION['username']) {
                $msg = "Error: You cannot delete your own account.";
                $msgType = "error";
            } else {
                $del = $conn->prepare("DELETE FROM `accounts` WHERE id = ? AND role = ?");
                $del->bind_param("is", $user_id, $role);
                if ($del->execute() && $del->affected_rows > 0) {
                    $msg = ucfirst($role) . " account deleted.";
                    $msgType = "success";
                    log_audit($conn, $_SESSION['username'], 'superadmin', "Deleted $role account: $username");
                } else {
                    $msg = "Error deleting account or account not found.";
                    $msgType = "error";
                }
            }
        }
        
        // ── Reset Password ──
        if (isset($_POST['reset_password'])) {
            $user_id = intval($_POST['user_id']);
            $role = $_POST['role_type'] ?? '';
            $username = trim($_POST['username'] ?? '');
            $raw_password = $_POST['new_password'] ?? '';
            
            if (!in_array($role, $valid_roles)) {
                $msg = "Invalid role specified.";
                $msgType = "error";
            } elseif (strlen($raw_password) < 6) {
                $msg = "New password must be at least 6 characters.";
                $msgType = "error";
            } else {
                $new_password = password_hash($raw_password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE `accounts` SET password = ? WHERE id = ? AND role = ?");
                $upd->bind_param("sis", $new_password, $user_id, $role);
                if ($upd->execute() && $upd->affected_rows > 0) {
                    $msg = "Password reset successfully for $username.";
                    $msgType = "success";
                    log_audit($conn, $_SESSION['username'], 'superadmin', "Reset password for $role: $username");
                } else {
                    $msg = "Error resetting password or account not found.";
                    $msgType = "error";
                }
            }
        }
    
    } // end CSRF check
}

// Fetch lists
$admins = $conn->query("SELECT id, username, created_at FROM accounts WHERE role='admin' ORDER BY created_at DESC");
$registrars = $conn->query("SELECT id, username, created_at FROM accounts WHERE role='registrar' ORDER BY created_at DESC");
$superadmins = $conn->query("SELECT id, username, created_at FROM accounts WHERE role='superadmin' ORDER BY created_at DESC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles & Permissions | SCNHS</title>
    <link rel="icon" href="../image/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../static/dashboard_shared.css?v=3">
    <style>
        .role-badge { display: inline-block; padding: 4px 10px; border-radius: 100px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .role-superadmin { background: #ede9fe; color: #7c3aed; }
        .role-admin { background: var(--blue-light); color: var(--blue); }
        .role-registrar { background: #dcfce7; color: var(--green); }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }
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
                <h1>Roles & Permissions</h1>
                <p>Manage system access for Superadmins, Admins, and Registrars.</p>
            </div>
            <div class="user-profile">
                <i class="fas fa-shield-alt"></i>
                <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </header>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo htmlspecialchars($msgType); ?>">
                <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid" style="grid-template-columns: 1fr 2fr;">
            
            <!-- Add User Form -->
            <div class="panel" style="height: fit-content;">
                <div class="panel-header">
                    <i class="fas fa-user-plus"></i>
                    <h2>Create Account</h2>
                </div>
                <div class="panel-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role_type" class="form-control" required>
                                <option value="admin">Admin (Student/Data Mgmt)</option>
                                <option value="registrar">Registrar (Grades/Records)</option>
                                <option value="superadmin">Superadmin (Full Access)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Username <small style="color:#94a3b8;">(min 3 chars)</small></label>
                            <input type="text" name="username" class="form-control" required autocomplete="off" minlength="3">
                        </div>
                        <div class="form-group">
                            <label>Initial Password <small style="color:#94a3b8;">(min 6 chars)</small></label>
                            <input type="password" name="password" class="form-control" required autocomplete="new-password" minlength="6">
                        </div>
                        <button type="submit" name="add_user" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-plus"></i> Create Account
                        </button>
                    </form>
                </div>
            </div>

            <!-- Users List -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-users-cog"></i>
                    <h2>System Administrators</h2>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $all_users = [];
                            if ($superadmins) while($r = $superadmins->fetch_assoc()) { $r['role'] = 'superadmin'; $all_users[] = $r; }
                            if ($admins) while($r = $admins->fetch_assoc()) { $r['role'] = 'admin'; $all_users[] = $r; }
                            if ($registrars) while($r = $registrars->fetch_assoc()) { $r['role'] = 'registrar'; $all_users[] = $r; }
                            ?>

                            <?php if (empty($all_users)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:30px; color:#94a3b8;">No accounts found.</td></tr>
                            <?php endif; ?>

                            <?php foreach($all_users as $user): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <div class="admin-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($user['username']); ?></span>
                                        <?php if($user['role'] === 'superadmin' && $user['username'] === $_SESSION['username']): ?>
                                            <span class="badge" style="margin-left: 8px; font-size: 0.65rem;">You</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo htmlspecialchars($user['role']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <!-- Password Reset -->
                                        <form method="POST" style="display:inline;" onsubmit="let p = prompt('Enter new password for <?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?> (min 6 characters):'); if(p && p.length >= 6) { this.new_password.value = p; return true; } else if(p !== null) { alert('Password must be at least 6 characters.'); } return false;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo intval($user['id']); ?>">
                                            <input type="hidden" name="role_type" value="<?php echo htmlspecialchars($user['role']); ?>">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                            <input type="hidden" name="new_password" value="">
                                            <button type="submit" name="reset_password" class="btn btn-secondary btn-sm" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>

                                        <!-- Delete -->
                                        <?php $is_self = ($user['role'] === 'superadmin' && $user['username'] === $_SESSION['username']); ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete <?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo intval($user['id']); ?>">
                                            <input type="hidden" name="role_type" value="<?php echo htmlspecialchars($user['role']); ?>">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm" title="<?php echo $is_self ? 'Cannot delete your own account' : 'Delete User'; ?>" <?php if($is_self) echo 'disabled style="opacity:0.4; cursor:not-allowed;"'; ?>>
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
