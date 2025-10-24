<?php
// Database connection
$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";

// Connect to the database
$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

// Get all products joined with inventory (if you have separate tables)
$query = "
SELECT 
    p.ProductID,
    p.Product_Name,
    p.Category,
    p.Price,
    p.Stock,
    p.Reorder_Level
FROM product p
";

$result = $conn->query($query);
$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Determine availability based on stock and reorder level
        if ($row['Stock'] == 0) {
            $availability = "No Stock";
        } elseif ($row['Stock'] <= $row['Reorder_Level']) {
            $availability = "Low Stock";
        } else {
            $availability = "In Stock";
        }

        $products[] = [
            "ProductID" => $row["ProductID"],
            "Product_Name" => $row["Product_Name"],
            "Category" => $row["Category"],
            "Price" => $row["Price"],
            "Stock" => $row["Stock"],
            "Reorder_Level" => $row["Reorder_Level"],
            "Availability" => $availability
        ];
    }
}

echo json_encode($products);
$conn->close();
