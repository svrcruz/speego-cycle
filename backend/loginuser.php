<?php
// Database connection
$servername = "localhost";
$dbuser = "root";     
$dbpass = "admin123";         
$dbname = "speegotest";

// Connect to the database
$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

$email = $_POST['Customer_Email'];
$pass = $_POST['Customer_Password'];

$sql = "SELECT * FROM customer WHERE Customer_Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $row = $result->fetch_assoc();

  if (password_verify($pass, $row['Customer_Password'])) {
    $_SESSION['CustomerID'] = $row['CustomerID'];
    $_SESSION['Customer_FName'] = $row['Customer_FName'];

    echo "<script>alert('Welcome, {$row['Customer_FName']}!'); window.location='index.html';</script>";
  } else {
    echo "<script>alert('Incorrect password.'); window.location='loginuser.html';</script>";
  }
} else {
  echo "<script>alert('No account found with that email.'); window.location='createnewuser.html';</script>";
}

$stmt->close();
$conn->close();
?>
