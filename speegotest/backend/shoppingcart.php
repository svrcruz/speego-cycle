<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "admin123", "speegotest");
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// For testing, assume customer 1
if (!isset($_SESSION['CustomerID'])) {
    $_SESSION['CustomerID'] = 1;
}
$customer_id = $_SESSION['CustomerID'];

//Get all items in this user's cart
$sql = "
    SELECT 
        c.CartID,
        p.ProductID,
        p.Product_Name AS ProductName,
        p.Price,
        c.Quantity
    FROM cart AS c
    INNER JOIN product AS p ON c.ProductID = p.ProductID
    WHERE c.CustomerID = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

$cart = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    $subtotal = $row['Price'] * $row['Quantity'];
    $cart[] = [
        'CartID' => $row['CartID'],
        'ProductID' => $row['ProductID'],
        'ProductName' => $row['ProductName'],
        'Price' => (float)$row['Price'],
        'Quantity' => (int)$row['Quantity'],
        'Subtotal' => $subtotal
    ];
    $total += $subtotal;
}

echo json_encode([
    'cart' => $cart,
    'total' => $total,
    'count' => count($cart)
], JSON_PRETTY_PRINT);

$conn->close();
?>
