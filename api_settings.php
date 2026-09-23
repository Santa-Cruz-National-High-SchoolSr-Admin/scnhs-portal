<?php
/**
 * API to fetch global settings for the frontend
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$conn = get_db_connection();

$response = [
    'success' => false,
    'settings' => []
];

// Fetch all settings
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $response['settings'][$row['setting_key']] = $row['setting_value'];
    }
    $response['success'] = true;
} else {
    $response['error'] = 'Failed to fetch settings.';
}

$conn->close();
echo json_encode($response);
?>
