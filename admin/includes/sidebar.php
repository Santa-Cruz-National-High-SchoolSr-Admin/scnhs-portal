<?php
if (!isset($_SESSION['role'])) {
    return;
}
$role = $_SESSION['role'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!function_exists('is_active')) {
    function is_active($page, $current) {
        return $page === $current ? 'active' : '';
    }
}
$logout_url = ($role === 'admin') ? 'logout.php' : 'superadmin_logout.php';
?>
<div class="sidebar-header">
    <img src="../image/SCNHS.png" alt="SCNHS Logo">
    <h2>SCNHS Portal</h2>
    <p><?php echo $role === 'superadmin' ? 'Superadmin Panel' : 'Admin Panel'; ?></p>
</div>

<div class="nav-links">
    <?php if ($role === 'superadmin'): ?>
        <div class="sidebar-group">Superadmin Controls</div>
        <a href="superadmin_dashboard.php" class="nav-item <?php echo is_active('superadmin_dashboard.php', $current_page); ?>"><i class="fas fa-chart-line"></i> Overview</a>
        <a href="superadmin_roles.php" class="nav-item <?php echo is_active('superadmin_roles.php', $current_page); ?>"><i class="fas fa-user-shield"></i> Roles & Permissions</a>
    <?php endif; ?>

    <div class="sidebar-group">Academic Management</div>
    <a href="dashboard.php" class="nav-item <?php echo is_active('dashboard.php', $current_page); ?>"><i class="fas fa-user-graduate"></i> Students & Enrollment</a>
    <a href="section.php" class="nav-item <?php echo is_active('section.php', $current_page); ?>"><i class="fas fa-layer-group"></i> Sections & Subjects</a>
    <a href="manage_teachers.php" class="nav-item <?php echo is_active('manage_teachers.php', $current_page); ?>"><i class="fas fa-chalkboard-teacher"></i> Teachers</a>
    <a href="attendance_report.php" class="nav-item <?php echo is_active('attendance_report.php', $current_page); ?>"><i class="fas fa-file-invoice"></i> Reports</a>
    <a href="barcode_generator.php" class="nav-item <?php echo is_active('barcode_generator.php', $current_page); ?>"><i class="fas fa-barcode"></i> Barcode Generator</a>

    <div class="sidebar-group">Content Management</div>
    <a href="manage_announcements.php" class="nav-item <?php echo is_active('manage_announcements.php', $current_page); ?>"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="manage_gallery.php" class="nav-item <?php echo is_active('manage_gallery.php', $current_page); ?>"><i class="fas fa-images"></i> Gallery</a>
    <a href="manage_calendar.php" class="nav-item <?php echo is_active('manage_calendar.php', $current_page); ?>"><i class="fas fa-calendar-alt"></i> Calendar</a>
    <a href="manage_achievements.php" class="nav-item <?php echo is_active('manage_achievements.php', $current_page); ?>"><i class="fas fa-trophy"></i> Achievements</a>
    <a href="manage_sslg.php" class="nav-item <?php echo is_active('manage_sslg.php', $current_page); ?>"><i class="fas fa-vote-yea"></i> SSLG Elections</a>

    <div class="sidebar-group">Records & Requests</div>
    <a href="manage_clearances.php" class="nav-item <?php echo is_active('manage_clearances.php', $current_page); ?>"><i class="fas fa-check-double"></i> Clearances</a>
    <a href="manage_document_requests.php" class="nav-item <?php echo is_active('manage_document_requests.php', $current_page); ?>"><i class="fas fa-file-alt"></i> Document Requests</a>
    <a href="manage_health.php" class="nav-item <?php echo is_active('manage_health.php', $current_page); ?>"><i class="fas fa-heartbeat"></i> Health Records</a>
    <a href="manage_immersion.php" class="nav-item <?php echo is_active('manage_immersion.php', $current_page); ?>"><i class="fas fa-briefcase"></i> Work Immersion</a>

    <div class="sidebar-group">System Tools</div>
    <?php if ($role === 'superadmin'): ?>
        <a href="audit_logs.php" class="nav-item <?php echo is_active('audit_logs.php', $current_page); ?>"><i class="fas fa-clipboard-list"></i> Audit Logs</a>
        <a href="superadmin_system.php" class="nav-item <?php echo is_active('superadmin_system.php', $current_page); ?>"><i class="fas fa-server"></i> System & Backup</a>
    <?php endif; ?>
    <a href="settings.php" class="nav-item <?php echo is_active('settings.php', $current_page); ?>"><i class="fas fa-cog"></i> Settings</a>

    <div class="sidebar-group">Links</div>
    <a href="../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> View Website</a>
</div>

<div class="sidebar-footer">
    <a href="<?php echo $logout_url; ?>" class="nav-item nav-logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
</div>
