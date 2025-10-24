<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

$conn = new mysqli("localhost", "root", "Password1$", "speegotest");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['CustomerID'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$customerId = $_SESSION['CustomerID'];

// Get checkout data
$selectedItems = json_decode($_POST['selectedItems'] ?? '[]', true);
$firstName = $_POST['firstName'] ?? '';
$lastName = $_POST['lastName'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$streetAddress = $_POST['streetAddress'] ?? '';
$city = $_POST['city'] ?? '';
$zipCode = $_POST['zipCode'] ?? '';
$paymentMethod = $_POST['paymentMethod'] ?? 'Pay on Delivery';

// Validate
if (empty($selectedItems) || empty($firstName) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$shippingAddress = "$streetAddress, $city, $zipCode";
$totalAmount = 0;
$orderItems = [];

// --- Calculate total and prepare items ---
foreach ($selectedItems as $productId) {
    $cartStmt = $conn->prepare("SELECT Quantity FROM cart WHERE CustomerID = ? AND ProductID = ?");
    $cartStmt->bind_param("ii", $customerId, $productId);
    $cartStmt->execute();
    $cartResult = $cartStmt->get_result();
    if ($cartRow = $cartResult->fetch_assoc()) {
        $quantity = $cartRow['Quantity'];
        $priceStmt = $conn->prepare("SELECT Price FROM product WHERE ProductID = ?");
        $priceStmt->bind_param("i", $productId);
        $priceStmt->execute();
        $priceResult = $priceStmt->get_result();
        if ($priceRow = $priceResult->fetch_assoc()) {
            $unitPrice = $priceRow['Price'];
            $subtotal = $unitPrice * $quantity;
            $totalAmount += $subtotal;
            $orderItems[] = [
                'productId' => $productId,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'subtotal' => $subtotal
            ];
        }
        $priceStmt->close();
    }
    $cartStmt->close();
}

if (empty($orderItems)) {
    echo json_encode(['success' => false, 'message' => 'No valid items found']);
    exit;
}

// --- Insert order ---
$orderStmt = $conn->prepare("
    INSERT INTO orders (CustomerID, OrderDate, TotalAmount, PaymentMethod, ShippingAddress, Status)
    VALUES (?, NOW(), ?, ?, ?, 'Pending')
");
$orderStmt->bind_param("idss", $customerId, $totalAmount, $paymentMethod, $shippingAddress);

if (!$orderStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create order']);
    exit;
}

$orderId = $conn->insert_id;

// --- Insert order items ---
$itemStmt = $conn->prepare("
    INSERT INTO order_items (OrderID, ProductID, Quantity, UnitPrice, Subtotal)
    VALUES (?, ?, ?, ?, ?)
");

foreach ($orderItems as $item) {
    $itemStmt->bind_param("iiidd", $orderId, $item['productId'], $item['quantity'], $item['unitPrice'], $item['subtotal']);
    $itemStmt->execute();
}

// --- Remove purchased items from cart ---
$deleteStmt = $conn->prepare("DELETE FROM cart WHERE CustomerID = ? AND ProductID = ?");
foreach ($selectedItems as $productId) {
    $deleteStmt->bind_param("ii", $customerId, $productId);
    $deleteStmt->execute();
}

echo json_encode(['success' => true, 'orderId' => $orderId]);

$orderStmt->close();
$itemStmt->close();
$deleteStmt->close();
$conn->close();
