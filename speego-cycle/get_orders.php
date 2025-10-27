<?php
session_start();

$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed."]));
}

//  Check login session
if (!isset($_SESSION['CustomerID'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$customerId = $_SESSION['CustomerID'];

// Paths for images
$imageDir = __DIR__ . "/images/";
$imageURLPrefix = "images/";

// Fetch orders for this customer only
$order_query = "
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.ShippingAddress, o.Status
    FROM orders o
    WHERE o.CustomerID = ?
    ORDER BY o.OrderDate DESC
";

$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("i", $customerId);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

$orders = [];

while ($order = $order_result->fetch_assoc()) {
    $orderID = $order['OrderID'];

    // Fetch only this user's order items
    $item_query = "
        SELECT p.ProductID, p.Product_Name AS ProductName, oi.Quantity, p.Price
        FROM order_items oi
        JOIN product p ON oi.ProductID = p.ProductID
        WHERE oi.OrderID = ?
    ";
    $item_stmt = $conn->prepare($item_query);
    $item_stmt->bind_param("i", $orderID);
    $item_stmt->execute();
    $item_result = $item_stmt->get_result();

    $items = [];
    while ($item = $item_result->fetch_assoc()) {
        // Find image for product
        $productName = $item["ProductName"];
        $imageFile = "placeholder.png"; // default

        foreach (glob($imageDir . "*") as $filePath) {
            $fileName = basename($filePath);
            $fileBase = preg_replace("/[^a-zA-Z0-9]/", "", pathinfo($fileName, PATHINFO_FILENAME));
            $productBase = preg_replace("/[^a-zA-Z0-9]/", "", $productName);

            if (strcasecmp($fileBase, $productBase) === 0) {
                $imageFile = $fileName;
                break;
            }
        }

        $item['ImageURL'] = $imageURLPrefix . $imageFile;
        $items[] = $item;
    }

    $order['items'] = $items;
    $orders[] = $order;

    $item_stmt->close();
}

$order_stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($orders, JSON_PRETTY_PRINT);
?>
