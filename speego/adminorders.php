<?php
session_start();
include 'db_connection.php';

header('Content-Type: application/json');

// Optional: check admin login
// if (!isset($_SESSION['admin_logged_in'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

$query = "
    SELECT o.OrderID, o.OrderDate, o.Status, o.TotalAmount,
           CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName,
           GROUP_CONCAT(p.ProductName, ' (Qty: ', oi.Quantity, ')') AS Items
    FROM orders o
    JOIN customer c ON o.CustomerID = c.CustomerID
    JOIN order_items oi ON o.OrderID = oi.OrderID
    JOIN product p ON oi.ProductID = p.ProductID
    GROUP BY o.OrderID
    ORDER BY o.OrderDate DESC
";

$result = mysqli_query($conn, $query);
$orders = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
}

echo json_encode(['orders' => $orders]);
?>
