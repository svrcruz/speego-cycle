<?php
header('Content-Type: application/json');
$servername = "localhost";
$username = "root";
$password = "Password1$";
$dbname = "speegotest";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

if (!isset($_GET['orderId'])) {
    echo json_encode(["error" => "No order ID provided"]);
    exit;
}

$orderId = intval($_GET['orderId']);

// Get the order info
$sql = "SELECT * FROM orders WHERE OrderID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$orderResult = $stmt->get_result();

if ($orderResult->num_rows === 0) {
    echo json_encode(["error" => "Order not found for ID $orderId"]);
    exit;
}

$order = $orderResult->fetch_assoc();

// Get items and join with product table
$sql_items = "
  SELECT p.Product_Name AS ProductName, oi.Quantity, p.Price
  FROM order_items oi
  JOIN product p ON oi.ProductID = p.ProductID
  WHERE oi.OrderID = ?
";

$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $orderId);
$stmt_items->execute();
$itemsResult = $stmt_items->get_result();

$items = [];
$imageDir = __DIR__ . "/images/";
$imageURLPrefix = "images/";

while ($row = $itemsResult->fetch_assoc()) {
    // Find the image file matching the product name
    $productName = $row["ProductName"];
    $imageFile = "placeholder.png"; // fallback

    foreach (glob($imageDir . "*") as $filePath) {
        $fileName = basename($filePath);
        $fileBase = preg_replace("/[^a-zA-Z0-9]/", "", pathinfo($fileName, PATHINFO_FILENAME));
        $productBase = preg_replace("/[^a-zA-Z0-9]/", "", $productName);

        if (strcasecmp($fileBase, $productBase) === 0) {
            $imageFile = $fileName;
            break;
        }
    }

    $row['ImageURL'] = $imageURLPrefix . $imageFile;
    $items[] = $row;
}

$order['items'] = $items;

echo json_encode($order, JSON_PRETTY_PRINT);
?>
