<?php
session_start();
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: loginadmin.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Inventory Admin</title>
  <link rel="stylesheet" href="inventoryadmin.css">
</head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars($_SESSION['admin_user']); ?>!</h1>
  <p>This is your protected admin inventory page.</p>
  <a href="logout.php">Logout</a>
</body>
</html>
