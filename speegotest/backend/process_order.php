<?php
session_start();
include 'db_connection.php'; // Assuming you have a db connection file

header('Content-Type: application/json');

$data = json_decode(file_get_contents('POST'), true);
$customerId = $data['customerId'];
$selectedItems = $data['selectedItems'];
$total = $data['total'];

if (!$customerId || empty($selectedItems)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Insert new order
$stmt = $conn->prepare("INSERT INTO orders (CustomerID, OrderDate, Status, Total) VALUES (?, NOW(), 'Pending', ?)");
$stmt->bind_param("id", $customerId, $total);
if ($stmt->execute()) {
    $orderId = $conn->insert_id;

    // Insert order items
    foreach ($selectedItems as $productId) {
        // Fetch product details (assuming cart has quantity)
        $cartStmt = $conn->prepare("SELECT Quantity FROM shopping_cart WHERE CustomerID = ? AND ProductID = ?");
        $cartStmt->bind_param("ii", $customerId, $productId);
        $cartStmt->execute();
        $cartResult = $cartStmt->get_result();
        if ($row = $cartResult->fetch_assoc()) {
            $quantity = $row['Quantity'];
            $itemStmt = $conn->prepare("INSERT INTO order_items (OrderID, ProductID, Quantity) VALUES (?, ?, ?)");
            $itemStmt->bind_param("iii", $orderId, $productId, $quantity);
            $itemStmt->execute();
            $itemStmt->close();

            // Remove from cart
            $deleteStmt = $conn->prepare("DELETE FROM shopping_cart WHERE CustomerID = ? AND ProductID = ?");
            $deleteStmt->bind_param("ii", $customerId, $productId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
        $cartStmt->close();
    }

    echo json_encode(['success' => true, 'orderId' => $orderId]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to create order']);
}

$stmt->close();
$conn->close();
?>