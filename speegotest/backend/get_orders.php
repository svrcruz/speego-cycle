<?php
$servername = "localhost";
$dbuser = "root";     
$dbpass = "admin123";         
$dbname = "speegotest";

// Connect to the database
$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch all orders (✅ now includes ShippingAddress and Status)
$order_query = "
    SELECT o.OrderID, o.CustomerID, o.OrderDate, o.TotalAmount, o.ShippingAddress, o.Status
    FROM orders o
    ORDER BY o.OrderDate DESC
";
$order_result = $conn->query($order_query);

$orders = [];

if ($order_result && $order_result->num_rows > 0) {
    while ($order = $order_result->fetch_assoc()) {
        $orderID = $order['OrderID'];

        // Get the order items with correct column names
        $item_query = "
            SELECT p.Product_Name AS ProductName, oi.Quantity, p.Price
            FROM order_items oi
            JOIN product p ON oi.ProductID = p.ProductID
            WHERE oi.OrderID =  ?
        ";
        $item_stmt = $conn->prepare($item_query);
        $item_stmt->bind_param("i", $orderID);
        $item_stmt->execute();
        $item_result = $item_stmt->get_result();

        $items = [];
        while ($item = $item_result->fetch_assoc()) {
            $items[] = $item;
        }

        $order['items'] = $items;
        $orders[] = $order;
    }
}

$conn->close();

// Return as JSON for frontend
header('Content-Type: application/json');
echo json_encode($orders);
?>
