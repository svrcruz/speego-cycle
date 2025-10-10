<?php
session_start();

$servername = "localhost";
$dbuser = "root";     
$dbpass = "admin123";         
$dbname = "speegotest";

// Connect to the database
$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: loginadmin.html');
    exit;
}

$user = trim($_POST['username'] ?? '');
$pass = trim($_POST['password'] ?? '');

//  table  admin
$sql = "SELECT * FROM admin WHERE username = ? AND password = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $user, $pass);
$stmt->execute();
$result = $stmt->get_result();

// If match, login success
if ($result->num_rows > 0) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = $user;
    header('Location: inventoryadmin.html');
    exit;
} else {
    echo "<script>
            alert('Invalid username or password');
            window.location.href = 'loginadmin.html';
          </script>";
    exit;
}
?>
