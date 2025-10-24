<?php
$servername = "localhost";
$dbuser = "root";
$dbpass = "Password1$";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (isset($_POST['get_otp'])) {
    $email = $_POST['email'];

    // Check if email exists in database
    $sql = "SELECT * FROM customer WHERE Customer_Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Email found — redirect to your OTP flow
        header("Location: authenticationuser.php?email=" . urlencode($email));
        exit();
    } else {
        // Email not found — show alert and return to forgot password page
        echo "<script>
            alert('Email not found. Please check or register first.');
            window.location.href='forgotpassword.html';
        </script>";
        exit();
    }
}
?>

