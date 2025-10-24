<?php
session_start();
$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";
$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (isset($_POST['save'])) {
    $email = $_SESSION['email'];
    $newpass = $_POST['newpass'];
    $confirmpass = $_POST['confirmpass'];

    if ($newpass !== $confirmpass) {
        echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
        exit();
    }

    $hashed = password_hash($newpass, PASSWORD_DEFAULT);
    $sql = "UPDATE customer SET Customer_Password = ? WHERE Customer_Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed, $email);
    if ($stmt->execute()) {
        echo "<script>alert('Password successfully updated!'); window.location='loginuser.html';</script>";
    } else {
        echo "<script>alert('Error updating password.'); window.history.back();</script>";
    }
    $stmt->close();
}
$conn->close();
