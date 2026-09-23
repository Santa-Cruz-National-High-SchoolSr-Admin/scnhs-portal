<?php
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

$msg = "";
$msgType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $msg = "Please fill in all fields.";
        $msgType = "error";
    } else {
        // Check if username exists
        $check = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = "Superadmin username already exists!";
            $msgType = "error";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO accounts (username, password, role) VALUES (?, ?, 'superadmin')");
            $stmt->bind_param("ss", $username, $hashed);
            
            if ($stmt->execute()) {
                $msg = "✅ Superadmin account created successfully! You can now log in.";
                $msgType = "success";
            } else {
                $msg = "Error: " . $conn->error;
                $msgType = "error";
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Superadmin</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { margin-top: 0; color: #1f2937; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: #374151; }
        input[type="text"], input[type="password"] { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background-color: #2563eb; color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; }
        button:hover { background-color: #1d4ed8; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .warning { background-color: #fffbeb; color: #92400e; border: 1px solid #fde68a; padding: 1rem; border-radius: 4px; margin-top: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Create Superadmin</h2>
        
        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msgType; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="create">Create Account</button>
        </form>
        
        <div class="warning">
            <strong>Security Warning:</strong> Once you have created your superadmin account, please delete this file (<code>admin/create_superadmins.php</code>) from your server to prevent unauthorized access.
        </div>
    </div>
</body>
</html>
