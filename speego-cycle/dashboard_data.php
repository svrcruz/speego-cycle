<?php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}


// Initialize values
$totalSales = $totalProducts = $pendingRequests = $pendingOrders = 0;

//  Total Sales from order_items
$totalOrderItems = 0;
$query = "SELECT COALESCE(SUM(Subtotal), 0) AS total_order_items FROM order_items";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $totalOrderItems = (float)($row['total_order_items'] ?? 0);
}

//  Total Sales from service_cost
$totalServiceCost = 0;
$query = "SELECT COALESCE(SUM(TotalCost), 0) AS total_service_cost FROM service_cost";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $totalServiceCost = (float)($row['total_service_cost'] ?? 0);
}

//  Grand Total Sales
$totalSales = $totalOrderItems + $totalServiceCost;

//  Total Products
$query = "SELECT COUNT(*) AS total_products FROM product";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $totalProducts = $row['total_products'] ?? 0;
}

//  Total Service Requests
$query = "SELECT COUNT(*) AS total_requests FROM service_request";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $pendingRequests = $row['total_requests'] ?? 0;
}

//  Pending Orders
$query = "SELECT COUNT(*) AS pending_orders FROM orders WHERE status = 'pending'";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $pendingOrders = $row['pending_orders'] ?? 0;
}

//  Output JSON
echo json_encode([
    'totalSales' => number_format($totalSales, 2, '.', ''),
    'totalProducts' => (int)$totalProducts,
    'pendingRequests' => (int)$pendingRequests,
    'pendingOrders' => (int)$pendingOrders
]);

$conn->close();
