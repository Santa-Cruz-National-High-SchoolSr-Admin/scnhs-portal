<?php
$conn = new mysqli("localhost", "root", "", "enrollment_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_superadmin'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO accounts (username, password, role) VALUES (?, ?, 'superadmin')";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("SQL Preparation Error: " . $conn->error);
    }

    $stmt->bind_param("ss", $username, $password);

    if ($stmt->execute()) {
        echo "<p style='color: green;'>New Superadmin Created Successfully!</p>";
    } else {
        echo "<p style='color: red;'>Error creating superadmin.</p>";
    }

    $stmt->close();
}

$conn->close();
?>
