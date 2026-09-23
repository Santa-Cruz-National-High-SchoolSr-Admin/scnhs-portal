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
    if (isset($_POST['add_Gallery'])) {
        $title = trim($_POST['title']);
        $category = trim($_POST['category']) ?: 'General';
        
        // Handle file upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = uniqid() . '.' . $ext;
                $upload_path = '../image/gallery/' . $new_filename;
                $db_path = 'image/gallery/' . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $sql = "INSERT INTO gallery (title, category, image_path) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $title, $category, $db_path);

                    if ($stmt->execute()) {
                        $_SESSION['msg'] = "Gallery image uploaded successfully!";
                        $_SESSION['msgType'] = "success";
                    } else {
                        $_SESSION['msg'] = "Error: " . $stmt->error;
                        $_SESSION['msgType'] = "error";
                    }
                    $stmt->close();
                } else {
                    $_SESSION['msg'] = "Failed to move uploaded file.";
                    $_SESSION['msgType'] = "error";
                }
            } else {
                $_SESSION['msg'] = "Invalid file type. Only JPG, PNG, GIF, and WEBP allowed.";
                $_SESSION['msgType'] = "error";
            }
        } else {
            $_SESSION['msg'] = "Please select an image file to upload.";
            $_SESSION['msgType'] = "error";
        }
    } elseif (isset($_POST['delete_Gallery'])) {
        $id = intval($_POST['Gallery_id']);
        
        // Fetch image path to delete file
        $get = $conn->prepare("SELECT image_path FROM gallery WHERE id = ?");
        $get->bind_param("i", $id);
        $get->execute();
        $res = $get->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $file_to_delete = '../' . $row['image_path'];
            if (file_exists($file_to_delete)) {
                unlink($file_to_delete);
            }
        }
        $get->close();

        $del = $conn->prepare("DELETE FROM gallery WHERE id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) {
            $_SESSION['msg'] = "Gallery image deleted successfully!";
            $_SESSION['msgType'] = "success";
        } else {
            $_SESSION['msg'] = "Error deleting image.";
            $_SESSION['msgType'] = "error";
        }
        $del->close();
    }
    
    // Redirect to self (PRG pattern)
    header("Location: manage_gallery.php");
    exit();
}

// Fetch Gallery
$Gallery = $conn->query("SELECT * FROM gallery ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" description="width=device-width, initial-scale=1.0">
    <title>Manage School Gallery | SCNHS</title>
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
                    <h1>School Gallery</h1>
                    <p>Manage images displayed in the Life at SCNHS gallery.</p>
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
                <div class="panel-header"><div class="panel-title"><i class="fas fa-upload"></i><h2>Upload Image</h2></div></div>
                <form action="manage_gallery.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-group">
                        <label for="title">Image Caption / Title</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="e.g. STEM Laboratory" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" class="form-control" placeholder="e.g. STEM, TVL, General">
                    </div>
                    <div class="form-group">
                        <label for="image">Select Image (JPG, PNG, GIF)</label>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*" required style="background: rgba(0,0,0,0.4);">
                    </div>
                    <button type="submit" name="add_Gallery" class="btn btn-primary"><i class="fas fa-upload"></i> Upload to Gallery</button>
                </form>
            </div>

            <!-- Published Achievements List -->
            <div class="panel">
                <div class="panel-header"><div class="panel-title"><i class="fas fa-images"></i><h2>Gallery Images</h2></div></div>
                <div class="news-list" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
                    <?php if ($Gallery->num_rows > 0): ?>
                        <?php while ($row = $Gallery->fetch_assoc()): ?>
                            <div class="news-item" style="padding:10px;">
                                <div style="height:150px; overflow:hidden; border-radius:8px; margin-bottom:10px; background:#000;">
                                    <img src="../<?php echo htmlspecialchars($row['image_path']); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                                <div class="news-header">
                                    <div class="news-title" style="font-size:1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($row['title']); ?></div>
                                </div>
                                <div class="news-meta" style="margin-bottom:12px;">
                                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($row['category']); ?></span>
                                </div>
                                <div class="news-actions" style="padding-top:8px;">
                                    <form action="manage_gallery.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this image?');" style="margin:0; width:100%;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="Gallery_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_Gallery" class="btn btn-danger" style="width:100%; padding: 6px 12px; font-size: 0.8rem;"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align:center;padding:40px;color:var(--text-muted)">
                            <i class="fas fa-image" style="font-size:2.5rem;margin-bottom:16px;opacity:0.5;display:block"></i>
                            No images uploaded yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="../static/sidebar.js?v=2"></script>
</body>
</html>

