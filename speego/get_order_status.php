<?php
session_start();

$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";

header('Content-Type: application/json');

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$orderId = $_GET['orderId'] ?? '';
if (!$orderId) {
    echo json_encode(['error' => 'No orderId provided']);
    exit;
}

$sql = "
    SELECT 
        OrderID,
        OrderDate,
        PaymentMethod,
        ShippingAddress,
        TotalAmount,
        Status,
        DATE_ADD(OrderDate, INTERVAL 5 DAY) AS EstimatedDelivery
    FROM orders
    WHERE OrderID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Order not found']);
    exit;
}

echo json_encode($result->fetch_assoc());
