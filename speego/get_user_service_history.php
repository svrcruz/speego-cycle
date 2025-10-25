<?php
session_start();

$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['CustomerID'])) {
    echo json_encode(['error' => 'User not logged in.']);
    exit;
}

$customerID = $_SESSION['CustomerID'];

// Fetch service requests for this customer
$sql = "SELECT 
            serviceRequestID AS serviceNo,
            serviceType,
            appointmentDate,
            status
        FROM service_request
        WHERE customerID = ?
        ORDER BY appointmentDate DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerID);
$stmt->execute();
$result = $stmt->get_result();

$serviceRequests = [];

while ($row = $result->fetch_assoc()) {
    $serviceRequests[] = [
        'serviceNo' => $row['serviceNo'],
        'serviceType' => $row['serviceType'],
        'datePlaced' => date('F d, Y', strtotime($row['appointmentDate'])),
        'status' => $row['status'],
        'date' => $row['appointmentDate']
    ];
}

echo json_encode($serviceRequests);

$stmt->close();
$conn->close();
