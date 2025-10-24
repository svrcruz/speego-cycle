<?php
// Database connection
$servername = "localhost";
$dbuser = "root";     
$dbpass = "Password1$";         
$dbname = "speegotest";

// Connect to the database
$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Get form data
$fname = $_POST['Customer_FName'];
$lname = $_POST['Customer_LName'];
$email = $_POST['Customer_Email'];
$pass = password_hash($_POST['Customer_Password'], PASSWORD_DEFAULT);
$phone = $_POST['Customer_Phone'];
$address = $_POST['Customer_Address'];

// Check if email already exists
$check = $conn->prepare("SELECT * FROM customer WHERE Customer_Email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
  echo "<script>alert('Email already exists!'); window.location='createnewuser.html';</script>";
  exit;
}

$check->close();

// Insert new customer
$sql = "INSERT INTO customer (Customer_FName, Customer_LName, Customer_Email, Customer_Password, Customer_Phone, Customer_Address)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $fname, $lname, $email, $pass, $phone, $address);

if ($stmt->execute()) {
  echo "<script>alert('Account created successfully! You can now log in.'); window.location='loginuser.html';</script>";
} else {
  echo "<script>alert('Error creating account.'); window.location='createnewuser.html';</script>";
}

$stmt->close();
$conn->close();
?>
