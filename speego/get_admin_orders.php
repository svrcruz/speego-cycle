<?php
session_start();

$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

header('Content-Type: application/json');

$query = "
    SELECT 
        o.OrderID,
        c.Customer_FName AS FirstName,
        c.Customer_LName AS LastName,
        GROUP_CONCAT(p.Product_Name SEPARATOR ', ') AS Items,
        SUM(oi.Quantity) AS TotalQuantity,
        o.TotalAmount
    FROM orders o
    JOIN customer c ON o.CustomerID = c.CustomerID
    JOIN order_items oi ON o.OrderID = oi.OrderID
    JOIN product p ON oi.ProductID = p.ProductID
    GROUP BY o.OrderID
    ORDER BY o.OrderDate DESC
";

$result = $conn->query($query);
$orders = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'OrderID' => $row['OrderID'],
            'Customer' => $row['FirstName'] . ' ' . $row['LastName'],
            'Items' => $row['Items'],
            'Quantity' => (int)$row['TotalQuantity'],
            'TotalAmount' => $row['TotalAmount']
        ];
    }
}

echo json_encode(['orders' => $orders]);
$conn->close();
