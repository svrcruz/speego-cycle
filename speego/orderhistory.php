<?php
session_start();
include 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['CustomerID'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$customerId = $_SESSION['CustomerID'];

$query = "
    SELECT o.OrderID, o.OrderDate, o.Status, o.TotalAmount,
           GROUP_CONCAT(p.ProductName, ' (Qty: ', oi.Quantity, ')') AS Items
    FROM orders o
    JOIN order_items oi ON o.OrderID = oi.OrderID
    JOIN product p ON oi.ProductID = p.ProductID
    WHERE o.CustomerID = ?
    GROUP BY o.OrderID
    ORDER BY o.OrderDate DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

echo json_encode(['orders' => $orders]);

$stmt->close();
$conn->close();
?>
