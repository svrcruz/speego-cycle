<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['CustomerID'])) {
    echo json_encode([
        'logged_in' => true,
        'customer_id' => $_SESSION['CustomerID'],
        'customer_name' => $_SESSION['Customer_FName']
    ]);
} else {
    echo json_encode([
        'logged_in' => false
    ]);
}
