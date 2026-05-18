<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'database');

// Create connection
$connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($connection->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $connection->connect_error]));
}

// Set charset to utf8mb4
$connection->set_charset("utf8mb4");

// Helper function to close connections
function closeConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

// Function to log system actions
function logAction($conn, $logType, $action, $details = '', $userType = 'System', $referenceId = null, $status = 'Success') {
    $query = "INSERT INTO system_logs (log_type, action, details, user_type, reference_id, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ssssss", $logType, $action, $details, $userType, $referenceId, $status);
        $stmt->execute();
        $stmt->close();
    }
}

// Function to generate unique order number
function generateOrderNumber($conn) {
    $timestamp = time();
    $random = rand(1000, 9999);
    $orderNumber = 'ORD-' . date('YmdHis', $timestamp) . '-' . $random;
    return $orderNumber;
}

// CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>
