<?php
$servername = "localhost";
$dbuser = "root";
$dbpass = "admin123";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $reorder_level = $_POST['reorder_level'];

    if ($stock == 0) {
        $availability = "No Stock";
    } elseif ($stock <= $reorder_level) {
        $availability = "Low Stock";
    } else {
        $availability = "In Stock";
    }

    $sql1 = "UPDATE product 
             SET Product_Name = '$product_name',
                 Category = '$category',
                 Price = '$price',
                 Stock = '$stock',
                 Reorder_Level = '$reorder_level'
             WHERE ProductID = '$product_id'";

    $sql2 = "UPDATE inventory 
             SET Stock_Level = '$stock',
                 Low_Stock = '$reorder_level',
                 Availability = '$availability'
             WHERE ProductID = '$product_id'";

    if ($conn->query($sql1) === TRUE && $conn->query($sql2) === TRUE) {
        echo "<script>alert('Product updated successfully!'); window.location.href='inventoryadmin.html';</script>";
    } else {
        echo "<script>alert('Error updating product: " . $conn->error . "'); window.location.href='inventoryadmin.html';</script>";
    }
}

$conn->close();
?>
