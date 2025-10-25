<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "Password1$";
$dbname = "speegotest";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the posted data
    $serviceRequestID = isset($_POST['serviceRequestID']) ? intval($_POST['serviceRequestID']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    // Validation
    if ($serviceRequestID <= 0 || $status === '') {
        echo json_encode(['success' => false, 'error' => 'Missing serviceRequestID or status']);
        exit;
    }

    // Update service_request table
    $stmt = $conn->prepare("UPDATE service_request SET status = ? WHERE serviceRequestID = ?");
    $stmt->bind_param("si", $status, $serviceRequestID);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to execute update']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

$conn->close();
