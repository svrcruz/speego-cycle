<?php
session_start();

$servername = "localhost";
$dbuser = "root";
$dbpass = "admin123";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['CustomerID'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$customerID = $_SESSION['CustomerID'];

$sql = "SELECT 
            sr.serviceRequestID,
            sr.serviceType,
            sr.problemDescription,
            sr.appointmentDate,
            sr.status,
            sr.dateCompleted,
            p.Product_Name AS ebikeModel
        FROM service_request sr
        LEFT JOIN product p ON sr.productID = p.productID
        WHERE sr.customerID = ? AND sr.status = 'Completed'
        ORDER BY sr.dateCompleted DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerID);
$stmt->execute();
$result = $stmt->get_result();

$services = [];
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

echo json_encode($services);

$stmt->close();
$conn->close();
?>
