<?php
// Alternative session checker with more debugging
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Log session data for debugging
error_log("=== SESSION DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));
error_log("==================");

// Check if user is logged in
if (!isset($_SESSION['CustomerID'])) {
    echo json_encode([
        "error" => "Please log in first.",
        "logged_in" => false,
        "session_id" => session_id(),
        "debug_info" => "No CustomerID in session"
    ]);
    exit();
}

// Database connection
$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";

try {
    $conn = new mysqli($servername, $dbuser, $dbpass, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $customerID = $_SESSION['CustomerID'];

    // Fetch user data with CustomerID included
    $sql = "SELECT CustomerID, Customer_FName, Customer_LName, Customer_Email, Customer_Phone 
            FROM customer 
            WHERE CustomerID = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $customerID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Return user data with logged_in flag
        echo json_encode([
            "CustomerID" => (int)$user['CustomerID'],
            "Customer_FName" => $user['Customer_FName'],
            "Customer_LName" => $user['Customer_LName'],
            "Customer_Email" => $user['Customer_Email'],
            "Customer_Phone" => $user['Customer_Phone'],
            "logged_in" => true,
            "session_id" => session_id()
        ]);
    } else {
        echo json_encode([
            "error" => "User not found in database.",
            "logged_in" => false,
            "customer_id_from_session" => $customerID
        ]);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode([
        "error" => $e->getMessage(),
        "logged_in" => false
    ]);
}
