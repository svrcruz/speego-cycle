<?php
session_start();

$servername = "localhost";
$dbuser = "root";
$dbpass = "admin123";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}


if (!isset($_SESSION['CustomerID'])) {
    $_SESSION['CustomerID'] = 1; 
}

$customerID = $_SESSION['CustomerID'];

$sql = "SELECT 
            serviceRequestID AS serviceNo,
            serviceType,
            appointmentDate,
            dateCompleted
        FROM service_request
        WHERE customerID = ? AND status = 'Completed'
        ORDER BY dateCompleted DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerID);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = [
        'serviceNo' => $row['serviceNo'],
        'serviceType' => $row['serviceType'],
        'datePlaced' => date('F d, Y', strtotime($row['appointmentDate'])),
        'dateCompleted' => $row['dateCompleted'] ? date('F d, Y', strtotime($row['dateCompleted'])) : ''
    ];
}

echo json_encode($history);

$stmt->close();
$conn->close();
?>
