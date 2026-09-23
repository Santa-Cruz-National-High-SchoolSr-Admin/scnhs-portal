<?php
$dir = 'c:/xampp/htdocs/Final/admin/';
$files = glob($dir . '*.php');

foreach($files as $file) {
    if(in_array(basename($file), ['login_process.php', '../login.php', 'superadmin_roles.php', 'superadmin_dashboard.php', 'superadmin_system.php', 'dashboard.php', 'settings.php'])) continue;

    $content = file_get_contents($file);
    $original = $content;

    // Fix auth checks at top of files
    $content = str_replace(
        "if (!isset(\$_SESSION['admin']) && !isset(\$_SESSION['superadmin']))", 
        "if (!isset(\$_SESSION['role']) || !in_array(\$_SESSION['role'], ['admin', 'superadmin']))", 
        $content
    );

    // Fix username display
    $content = str_replace(
        "isset(\$_SESSION['superadmin']) ? \$_SESSION['superadmin'] : \$_SESSION['admin']",
        "\$_SESSION['username']",
        $content
    );

    // Fix sidebar logic
    $content = str_replace(
        "<?php if (isset(\$_SESSION['superadmin'])): ?>",
        "<?php if (isset(\$_SESSION['role']) && \$_SESSION['role'] === 'superadmin'): ?>",
        $content
    );
    
    $content = str_replace(
        "<?php echo isset(\$_SESSION['superadmin']) ? 'Superadmin' : 'Administrator'; ?>",
        "<?php echo (isset(\$_SESSION['role']) && \$_SESSION['role'] === 'superadmin') ? 'Superadmin' : 'Administrator'; ?>",
        $content
    );

    // Fix admin name logic in gallery/achievements/announcements
    $content = str_replace(
        "\$admin_name = isset(\$_SESSION['admin']) ? \$_SESSION['admin'] : (isset(\$_SESSION['superadmin']) ? \$_SESSION['superadmin'] : 'Admin');",
        "\$admin_name = \$_SESSION['username'] ?? 'Admin';",
        $content
    );

    if($original !== $content) {
        file_put_contents($file, $content);
        echo "Updated " . basename($file) . "\n";
    }
}
echo "Done.";
?>
