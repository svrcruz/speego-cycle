<?php
$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$sql = "
SELECT 
    sr.serviceRequestID,
    CONCAT(c.Customer_FName, ' ', c.Customer_LName) AS customerName,
    p.Product_Name AS ebikeModel,
    sr.appointmentDate,
    sd.technicianName,
    sr.serviceType,
    sr.status
FROM service_request sr
LEFT JOIN customer c ON sr.customerID = c.customerID
LEFT JOIN product p ON sr.productID = p.productID
LEFT JOIN service_diagnosis sd ON sr.serviceRequestID = sd.serviceRequestID
ORDER BY sr.appointmentDate DESC
";

$result = $conn->query($sql);
$requests = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'serviceRequestID' => $row['serviceRequestID'], // ✅ THIS WAS MISSING
            'customerName' => $row['customerName'],
            'ebikeModel' => $row['ebikeModel'],
            'appointmentDate' => date('M d, Y', strtotime($row['appointmentDate'])),
            'technicianName' => $row['technicianName'] ?: '—',
            'serviceType' => $row['serviceType'],
            'status' => $row['status']
        ];
    }
}

echo json_encode($requests);
$conn->close();
