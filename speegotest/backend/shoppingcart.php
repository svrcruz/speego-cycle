<?php
session_start();
header('Content-Type: application/json');

// --- Database connection ---
$servername = "localhost";
$username = "root";
$password = "admin123";
$dbname = "speegotest";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// --- Temporary session user (demo only) ---
if (!isset($_SESSION['CustomerID'])) {
    $_SESSION['CustomerID'] = 1; // Replace with actual login session later
}
$customerID = (int) $_SESSION['CustomerID'];

// --- Fetch cart items safely ---
$sql = "
    SELECT 
        c.CartID, 
        p.ProductID, 
        p.ProductName, 
        p.Price, 
        c.Quantity, 
        p.ImageURL
    FROM cart AS c
    INNER JOIN product AS p ON c.ProductID = p.ProductID
    WHERE c.CustomerID = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customerID);
$stmt->execute();
$result = $stmt->get_result();

$cart = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    $subtotal = (float)$row['Price'] * (int)$row['Quantity'];
    $cart[] = [
        'CartID' => $row['CartID'],
        'ProductID' => $row['ProductID'],
        'ProductName' => $row['ProductName'],
        'Price' => (float)$row['Price'],
        'Quantity' => (int)$row['Quantity'],
        'Subtotal' => $subtotal,
        'ImageURL' => $row['ImageURL'] ?? 'default.jpg'
    ];
    $total += $subtotal;
}

echo json_encode([
    'cart' => $cart,
    'total' => $total,
    'count' => count($cart)
], JSON_PRETTY_PRINT);

$stmt->close();
$conn->close();
?>
