<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "Password1$", "speegotest");
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed."]));
}

// Check login
if (!isset($_SESSION['CustomerID'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$customerId = $_SESSION['CustomerID'];

// Get cart items for the logged-in user
$query = "
    SELECT c.ProductID, p.Product_Name AS ProductName, p.Price, c.Quantity
    FROM cart c
    JOIN product p ON c.ProductID = p.ProductID
    WHERE c.CustomerID = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$cartItems = [];

// Path for images
$imageDir = __DIR__ . "/images/";      // server path
$imageURLPrefix = "images/";           // relative to HTML

while ($row = $result->fetch_assoc()) {
    $productId = $row["ProductID"];
    $productName = $row["ProductName"];
    $quantity = (int)$row["Quantity"];
    $price = (float)$row["Price"];
    $imageFile = "placeholder.png"; // default

    // Try to find an image that matches the product name
    foreach (glob($imageDir . "*") as $filePath) {
        $fileName = basename($filePath);
        // remove extension and non-alphanumeric characters for comparison
        $fileBase = preg_replace("/[^a-zA-Z0-9]/", "", pathinfo($fileName, PATHINFO_FILENAME));
        $productBase = preg_replace("/[^a-zA-Z0-9]/", "", $productName);

        if (strcasecmp($fileBase, $productBase) === 0) {
            $imageFile = $fileName;
            break;
        }
    }

    $imageURL = $imageURLPrefix . $imageFile;

    $cartItems[] = [
        "ProductID" => $productId,
        "ProductName" => htmlspecialchars($productName),
        "Price" => $price,
        "Quantity" => $quantity,
        "ImageURL" => $imageURL
    ];
}

$stmt->close();
$conn->close();

header("Content-Type: application/json");
echo json_encode(["cart" => $cartItems], JSON_PRETTY_PRINT);
