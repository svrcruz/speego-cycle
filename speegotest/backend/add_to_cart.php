<?php
session_start();

$conn = new mysqli("localhost", "root", "admin123", "speegotest");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

//Ensure user is logged in
if (!isset($_SESSION['CustomerID'])) {
    die("User not logged in.");
}

$customer_id = $_SESSION['CustomerID'];

//Ensure we have the required POST data
if (isset($_POST['product_id'], $_POST['quantity'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    //Check if the product exists in product table (safety check)
    $check_product = $conn->prepare("SELECT ProductID FROM product WHERE ProductID = ?");
    $check_product->bind_param("i", $product_id);
    $check_product->execute();
    $product_result = $check_product->get_result();

    if ($product_result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Product not found."]);
        exit;
    }

    //Check if already in the user's cart
    $check_cart = $conn->prepare("SELECT Quantity FROM cart WHERE CustomerID = ? AND ProductID = ?");
    $check_cart->bind_param("ii", $customer_id, $product_id);
    $check_cart->execute();
    $cart_result = $check_cart->get_result();

    if ($cart_result->num_rows > 0) {
        // ✅ Update quantity
        $update = $conn->prepare("UPDATE cart SET Quantity = Quantity + ? WHERE CustomerID = ? AND ProductID = ?");
        $update->bind_param("iii", $quantity, $customer_id, $product_id);
        $update->execute();
    } else {
        //Insert new entry
        $insert = $conn->prepare("INSERT INTO cart (CustomerID, ProductID, Quantity) VALUES (?, ?, ?)");
        $insert->bind_param("iii", $customer_id, $product_id, $quantity);
        $insert->execute();
    }

    echo json_encode(["success" => true, "message" => "Product added to cart."]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
