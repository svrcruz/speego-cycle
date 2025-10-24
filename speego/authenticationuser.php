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

// When user clicks "Forgot password?" and passes ?email=...
if (isset($_GET['email'])) {
    $email = $_GET['email'];
    $otp = rand(1000, 9999);

    $_SESSION['otp'] = $otp;
    $_SESSION['email'] = $email;

    // Show OTP directly in browser
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <script>
            alert('Your test OTP code is: $otp');
            window.location.href='authenticationuser.html?email=$email';
        </script>
    </head>
    <body></body>
    </html>";
    exit();
}

// When user clicks “Resend Code”
if (isset($_POST['resend'])) {
    $email = $_POST['resend_email'];
    $otp = rand(1000, 9999);

    $_SESSION['otp'] = $otp;
    $_SESSION['email'] = $email;

    echo "<!DOCTYPE html>
    <html><head>
        <meta charset='UTF-8'>
        <script>
            alert('Your new test OTP code is: $otp');
            window.location.href='authenticationuser.html?email=$email';
        </script>
    </head></html>";
    exit();
}

//  When user submits OTP verification
if (isset($_POST['verify'])) {
    $enteredOTP = implode('', $_POST['otp']);
    if ($enteredOTP == $_SESSION['otp']) {
        echo "<script>alert('Verification successful!'); window.location='newpassworduser.html';</script>";
    } else {
        echo "<script>alert('Incorrect OTP. Please try again.'); window.history.back();</script>";
    }
    exit();
}
