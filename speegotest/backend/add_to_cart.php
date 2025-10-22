<?php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "admin123";
$dbname = "speegotest";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['CustomerID'])) {
    $_SESSION['CustomerID'] = 1; // demo only
}

$customerID = (int) $_SESSION['CustomerID'];
$productID = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($productID <= 0) {
    echo json_encode(['error' => 'Invalid product']);
    exit;
}

// Check if already in cart
$check = $conn->prepare("SELECT Quantity FROM cart WHERE CustomerID = ? AND ProductID = ?");
$check->bind_param("ii", $customerID, $productID);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // Update quantity
    $row = $result->fetch_assoc();
    $newQty = $row['Quantity'] + $quantity;
    $update = $conn->prepare("UPDATE cart SET Quantity = ? WHERE CustomerID = ? AND ProductID = ?");
    $update->bind_param("iii", $newQty, $customerID, $productID);
    $update->execute();
    $update->close();
} else {
    // Add new item
    $insert = $conn->prepare("INSERT INTO cart (CustomerID, ProductID, Quantity) VALUES (?, ?, ?)");
    $insert->bind_param("iii", $customerID, $productID, $quantity);
    $insert->execute();
    $insert->close();
}

echo json_encode(['success' => true, 'message' => 'Added to cart']);
$conn->close();
?>
