<?php
require 'config.php';
$conn = get_db_connection();
foreach(['students', 'teachers', 'accounts'] as $tbl) {
    echo "$tbl:\n";
    $res = $conn->query("DESCRIBE $tbl");
    while($r = $res->fetch_assoc()) echo "  " . $r['Field'] . "\n";
}
