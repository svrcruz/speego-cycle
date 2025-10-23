<?php
session_start();
include 'db_connection.php'; // Your database connection file

// Check if admin is logged in (add your admin session check here, e.g., if (!isset($_SESSION['admin_id'])) { echo json_encode(['error' => 'Unauthorized']); exit; })

$query = "
    SELECT o.OrderID, o.OrderDate, o.Status, o.Total, c.FirstName, c.LastName,
           GROUP_CONCAT(p.ProductName, ' (Qty: ', oi.Quantity, ')') AS Items
    FROM orders o
    JOIN customers c ON o.CustomerID = c.CustomerID
    JOIN order_items oi ON o.OrderID = oi.OrderID
    JOIN products p ON oi.ProductID = p.ProductID
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