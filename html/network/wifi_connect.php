<?php
/**
 * Wi-Fi Connect Form Page
 * Displays a form to enter a password (if needed) before connecting to a Wi-Fi network
 */
session_start();

// Redirect to index.php if not logged in
if (empty($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

// Ensure SSID and security are provided
if (!isset($_GET['ssid']) || !isset($_GET['security'])) {
    header("Location: index.php");
    exit;
}

$ssid = $_GET['ssid'];
$security = $_GET['security'];
$needsPassword = stripos($security, 'WPA') !== false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Connect to Wi-Fi</title>
  <link href="/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      padding: 20px;
      background-color: #f8f9fa;
    }
    .card-connect {
      border: none;
      border-radius: 0.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      max-width: 400px;
      margin: 0 auto;
    }
    .card-connect .card-header {
      background-color: #ffffff;
      border-bottom: 1px solid #dee2e6;
      font-weight: 500;
      font-size: 1.1rem;
      color: #343a40;
    }
    .btn-back {
      font-size: 0.85rem;
      padding: 0.25rem 0.75rem;
      background-color: #6c757d;
      border-color: #6c757d;
    }
    .btn-back:hover {
      background-color: #5c636a;
      border-color: #5c636a;
    }
  </style>
</head>
<body>
  <div class="card card-connect mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Connect to <?= htmlspecialchars($ssid) ?></span>
      <a href="index.php" class="btn btn-back">Back</a>
    </div>
    <div class="card-body">
      <form method="post" action="index.php">
        <input type="hidden" name="connect_ssid" value="<?= htmlspecialchars($ssid) ?>">
        <?php if ($needsPassword): ?>
          <div class="mb-3">
            <label for="wifi_password" class="form-label">Wi-Fi Password</label>
            <input type="password" class="form-control" id="wifi_password" name="wifi_password" placeholder="Enter password" required>
          </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary w-100">Connect</button>
      </form>
    </div>
  </div>
</body>
</html>
<?php
?>
