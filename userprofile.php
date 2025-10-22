<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['CustomerID'])) {
  header("Location: loginuser.html");
  exit();
}

// Database connection
$servername = "localhost";
$dbuser = "root";
$dbpass = "admin123";
$dbname = "speegotest";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Fetch user data
$customerID = $_SESSION['CustomerID'];
$sql = "SELECT Customer_FName, Customer_LName, Customer_Email, Customer_Phone FROM customer WHERE CustomerID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SpeegoCycle - Manage My Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <link rel="stylesheet" href="userprofile.css">
</head>

<body>
  <a href="#" class="back-link">
    <span><i class="ph ph-caret-left"></i></span>
    <span>Manage My Profile</span>
  </a>

  <div class="container">
    <div class="content-wrapper">
      <div class="profile-card">
        <div class="profile-header">
          <div class="profile-icon"><i class="ph ph-user"></i></div>
          <div class="profile-name">
            <?php echo htmlspecialchars($user['Customer_FName'] . ' ' . $user['Customer_LName']); ?>
          </div>
          <div class="profile-username">
            @<?php echo strtolower(str_replace(' ', '', $user['Customer_FName'])); ?>
          </div>
        </div>

        <div class="info-section">
          <div class="info-label">Full Name</div>
          <div class="info-value">
            <?php echo htmlspecialchars($user['Customer_FName'] . ' ' . $user['Customer_LName']); ?>
          </div>
        </div>

        <div class="info-section">
          <div class="info-label">Email Address</div>
          <div class="info-value">
            <a href="mailto:<?php echo htmlspecialchars($user['Customer_Email']); ?>">
              <?php echo htmlspecialchars($user['Customer_Email']); ?>
            </a>
          </div>
        </div>

        <div class="info-section">
          <div class="info-label">Phone Number</div>
          <div class="info-value">
            <?php echo htmlspecialchars($user['Customer_Phone']); ?>
          </div>
        </div>

        <div class="sms-toggle">
          <span>SMS Alerts Activation</span>
          <span class="toggle-indicator"></span>
        </div>
      </div>

      <div class="right-column">
        <div class="payment-card">
          <h2>Payment Methods</h2>

          <div class="payment-method">
            <div class="payment-info">
              <div class="payment-status">Active Account</div>
              <div class="payment-type">RCBC Debit</div>
              <div class="payment-number">574 867 392 864</div>
            </div>
            <button class="deactivate-btn-small">Deactivate</button>
          </div>

          <div class="payment-method">
            <div class="payment-info">
              <div class="payment-status">Active Account</div>
              <div class="payment-type">GCash</div>
              <div class="payment-number">
                <?php echo htmlspecialchars($user['Customer_Phone']); ?>
              </div>
            </div>
            <button class="deactivate-btn-small">Deactivate</button>
          </div>
        </div>

        <div class="actions-card">
          <button class="action-button">Deactivate Account</button>
          <button class="action-button">Delete Account</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.querySelector('.back-link').addEventListener('click', function(e) {
      e.preventDefault();
      window.history.back();
    });
  </script>
</body>
</html>
