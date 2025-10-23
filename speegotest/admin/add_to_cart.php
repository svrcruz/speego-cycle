<?php
session_start();
$conn = new mysqli("localhost", "root", "admin123", "speegotest");

if (isset($_POST['product_id'], $_POST['customer_id'], $_POST['quantity'])) {
    $product_id = $_POST['product_id'];
    $customer_id = $_POST['customer_id'];
    $quantity = $_POST['quantity'];

    // Check if item already exists in cart
    $check = $conn->prepare("SELECT * FROM cart WHERE CustomerID = ? AND ProductID = ?");
    $check->bind_param("ii", $customer_id, $product_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // If product already in cart, update quantity
        $update = $conn->prepare("UPDATE cart SET Quantity = Quantity + ? WHERE CustomerID = ? AND ProductID = ?");
        $update->bind_param("iii", $quantity, $customer_id, $product_id);
        $update->execute();
    } else {
        // Insert new item into cart
        $insert = $conn->prepare("INSERT INTO cart (CustomerID, ProductID, Quantity) VALUES (?, ?, ?)");
        $insert->bind_param("iii", $customer_id, $product_id, $quantity);
        $insert->execute();
    }

    header("Location: cart.php");
    exit();
} else {
    echo "Invalid request.";
}
?>
