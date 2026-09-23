<?php
// Connect to the database
$conn = new mysqli("localhost", "root", "", "enrollment_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set headers to download file as Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=sections.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Open output stream
$output = fopen("php://output", "w");

// Column headers
fputcsv($output, ["Section ID", "Section Name", "Teacher Name"], ",");

// Fetch sections from the database
$result = $conn->query("SELECT * FROM sections");

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [$row['section_id'], $row['section_name'], $row['teacher_name']], ",");
}

fclose($output);
exit();
?>
