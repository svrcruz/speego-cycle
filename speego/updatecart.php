Can't create, edit, or upload … If your storage is full for 2+ years, your files may be deleted from Drive and Photos. Get 30 GB of storage for ₱49 ₱10/month for 3 months.
<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "Password1$", "speegotest");
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['CustomerID'])) {
    echo json_encode(['error' => 'User not logged in.']);
    exit;
}

$customer_id = $_SESSION['CustomerID'];

// Get product ID and new quantity from JS
$data = json_decode(file_get_contents("php://input"), true);
$product_id = (int)$data['product_id'];
$new_quantity = (int)$data['quantity'];

//Update quantity in DB
$update = $conn->prepare("UPDATE cart SET Quantity = ? WHERE CustomerID = ? AND ProductID = ?");
$update->bind_param("iii", $new_quantity, $customer_id, $product_id);

if ($update->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cart updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$conn->close();
?>