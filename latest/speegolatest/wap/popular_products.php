<?php
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

// Path to product images
$imageDir = __DIR__ . "/images/";
$imageURLPrefix = "images/";

// Fetch top 4 popular products based on quantity in order_items
$sql = "
    SELECT p.ProductID, p.Product_Name AS ProductName, SUM(oi.Quantity) AS TotalSold
    FROM order_items oi
    JOIN product p ON oi.ProductID = p.ProductID
    GROUP BY p.ProductID
    ORDER BY TotalSold DESC
    LIMIT 4
";

$result = $conn->query($sql);
$popular = [];

while ($row = $result->fetch_assoc()) {
    // find image
    $productName = $row['ProductName'];
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
    $row['ImageURL'] = $imageURLPrefix . $imageFile;
    $popular[] = $row;
}

echo json_encode($popular, JSON_PRETTY_PRINT);

$conn->close();
?>
