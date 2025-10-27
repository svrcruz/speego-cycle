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

// Get filter from request
$type = $_GET['type'] ?? 'weekly'; // 'weekly', 'monthly', 'yearly'

// Prepare date filter
$dateFilter = '';
if ($type === 'weekly') {
    $dateFilter = ">= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($type === 'monthly') {
    $dateFilter = ">= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
} elseif ($type === 'yearly') {
    $dateFilter = ">= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
}

// ------------------- SALES -------------------
// Orders total
$queryOrders = "SELECT COALESCE(SUM(TotalAmount),0) as total_orders
                FROM orders
                WHERE OrderDate $dateFilter";
$resOrders = $conn->query($queryOrders);
$rowOrders = $resOrders->fetch_assoc();
$totalOrdersAmount = (float)($rowOrders['total_orders'] ?? 0);

// Service total
$queryService = "SELECT COALESCE(SUM(TotalCost),0) as total_service
                 FROM service_cost
                 WHERE CreatedAt $dateFilter";
$resService = $conn->query($queryService);
$rowService = $resService->fetch_assoc();
$totalServiceAmount = (float)($rowService['total_service'] ?? 0);

// Grand total sales
$totalSales = $totalOrdersAmount + $totalServiceAmount;

// Count of orders
$queryOrdersCount = "SELECT COUNT(*) as total_orders_count
                     FROM orders
                     WHERE OrderDate $dateFilter";
$resOrdersCount = $conn->query($queryOrdersCount);
$rowOrdersCount = $resOrdersCount->fetch_assoc();
$totalOrdersCount = (int)($rowOrdersCount['total_orders_count'] ?? 0);

// Count of products sold (using orders TotalAmount as proxy)
$queryProducts = "
    SELECT COALESCE(SUM(oi.Quantity),0) AS products_sold
    FROM orders o
    JOIN order_items oi ON o.OrderID = oi.OrderID
    WHERE o.OrderDate $dateFilter
";
$resProducts = $conn->query($queryProducts);
$rowProducts = $resProducts->fetch_assoc();
$productsSold = (int)($rowProducts['products_sold'] ?? 0);


// New customers (distinct CustomerID from orders)
$queryCustomers = "
    SELECT COUNT(DISTINCT CustomerID) as total_customers
    FROM orders
    WHERE OrderDate $dateFilter
";
$resCustomers = $conn->query($queryCustomers);
$rowCustomers = $resCustomers->fetch_assoc();
$newCustomers = (int)($rowCustomers['total_customers'] ?? 0);

// ------------------- SERVICE -------------------
// Total service requests
$queryServiceReq = "SELECT COUNT(*) as total_requests
                    FROM service_request
                    WHERE CreatedAt $dateFilter";
$resServiceReq = $conn->query($queryServiceReq);
$rowServiceReq = $resServiceReq->fetch_assoc();
$totalServiceRequests = (int)($rowServiceReq['total_requests'] ?? 0);

// Active Warranties
$activeWarranties = 0;
$query = "SELECT COUNT(*) AS activeWarranties 
          FROM warranty 
          WHERE Warranty_Status = 'Active'";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $activeWarranties = (int)($row['activeWarranties'] ?? 0);
}


// ------------------- COMPLAINTS / FEEDBACK -------------------
// If you have a feedback table, add query here
$totalFeedback = 0;
$totalComplaints = 0;
$averageRating = 0;

// ------------------- OUTPUT -------------------
echo json_encode([
    'totalSales' => $totalSales,
    'totalOrders' => $totalOrdersCount,
    'productsSold' => $productsSold,
    'newCustomers' => $newCustomers,
    'totalServiceRequests' => $totalServiceRequests,
    'totalFeedback' => $totalFeedback,
    'totalComplaints' => $totalComplaints,
    'averageRating' => $averageRating,
    'activeWarranties' => $activeWarranties
]);


$conn->close();
