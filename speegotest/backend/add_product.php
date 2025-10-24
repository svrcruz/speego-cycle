<?php
// Database connection
$servername = "localhost";
$dbuser = "root";     
$dbpass = "admin123";         
$dbname = "speegotest";

// Connect to the database
$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data safely
$product_name = $_POST['product_name'];
$category = $_POST['category'];
$price = $_POST['price'];
$stock = $_POST['stock'];
$reorder_level = $_POST['reorder_level'];

// Insert product
$sql1 = "INSERT INTO product (Product_Name, Category, Price, Stock, Reorder_Level)
         VALUES ('$product_name', '$category', '$price', '$stock', '$reorder_level')";

if ($conn->query($sql1) === TRUE) {
    $product_id = $conn->insert_id;

    // Determine availability
    if ($stock == 0) {
        $availability = "No Stock";
    } elseif ($stock <= $reorder_level) {
        $availability = "Low Stock";
    } else {
        $availability = "In Stock";
    }

    // Insert into inventory table (sync values)
    $sql2 = "INSERT INTO inventory (ProductID, Stock_Level, Low_Stock, Availability)
             VALUES ('$product_id', '$stock', '$reorder_level', '$availability')";

    if ($conn->query($sql2) === TRUE) {
        echo "<script>alert('Product added successfully!'); window.location.href='inventoryadmin.html';</script>";
    } else {
        echo "<script>alert('Error adding to inventory: " . $conn->error . "'); window.location.href='inventoryadmin.html';</script>";
    }
} else {
    echo "<script>alert('Error adding product: " . $conn->error . "'); window.location.href='inventoryadmin.html';</script>";
}

$conn->close();
?>
