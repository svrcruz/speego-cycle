<?php
session_start();
header("Content-Type: application/json");

// Check if user is logged in
if (!isset($_SESSION['CustomerID'])) {
    echo json_encode(["error" => "Please log in first."]);
    exit();
}

// Database connection
$servername = "localhost";
$dbuser = "root";
$dbpass = "admin123";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed."]);
    exit();
}

$customerID = $_SESSION['CustomerID'];

// Fetch user data and include CustomerID
$sql = "SELECT Customer_FName, Customer_LName, Customer_Email, Customer_Phone FROM customer WHERE CustomerID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user['CustomerID'] = $customerID;  // Add CustomerID to response
    echo json_encode($user);
} else {
    echo json_encode(["error" => "User not found."]);
}

$stmt->close();
$conn->close();
?>