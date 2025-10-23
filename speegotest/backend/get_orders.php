<?php
session_start();
include 'db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in (assuming session has CustomerID)
if (!isset($_SESSION['CustomerID'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$customerId = $_SESSION['CustomerID'];
$orderId = $_GET['orderId'] ?? null;

if ($orderId) {
    // Fetch single order for tracking
    $stmt = $conn->prepare("SELECT * FROM orders WHERE OrderID = ? AND CustomerID = ?");
    $stmt->bind_param("ii", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        // Fetch items
        $itemStmt = $conn->prepare("SELECT p.ProductName, oi.Quantity FROM order_items oi JOIN products p ON oi.ProductID = p.ProductID WHERE oi.OrderID = ?");
        $itemStmt->bind_param("i", $orderId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        $items = [];
        while ($item = $itemResult->fetch_assoc()) {
            $items[] = $item['ProductName'] . ' (Qty: ' . $item['Quantity'] . ')';
        }
        $row['Items'] = implode(', ', $items);
        echo json_encode(['order' => $row]);
        $itemStmt->close();
    } else {
        echo json_encode(['error' => 'Order not found']);
    }
} else {
    // Fetch all orders for history
    $stmt = $conn->prepare("SELECT * FROM orders WHERE CustomerID = ? ORDER BY OrderDate DESC");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    echo json_encode(['orders' => $orders]);
}

$stmt->close();
$conn->close();
?>