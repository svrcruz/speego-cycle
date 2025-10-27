<?php
session_start();

$servername = "localhost";
$dbuser = "root";
$dbpass = "admin123";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['orderId']) || !isset($data['status'])) {
    echo json_encode(['error' => 'Missing orderId or status']);
    exit;
}

$orderId = intval($data['orderId']);
$status = $conn->real_escape_string($data['status']);

$sql = "UPDATE orders SET Status = '$status' WHERE OrderID = $orderId";
if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => $conn->error]);
}
?>
