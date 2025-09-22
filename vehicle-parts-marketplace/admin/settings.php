<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has admin role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
    header("Location: ../login.php");
    exit();
}

$message = '';
$error = '';

// Fetch current settings
$current_settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
    $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no settings exist, create default
    if (!$current_settings) {
        $stmt = $pdo->prepare("INSERT INTO settings (site_name, contact_email, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->execute(['AutoParts Hub', 'support@autopartshub.com', '+1 (555) 123-4567', '123 Auto Lane, Tech City, TC 10101']);
        
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
        $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Failed to fetch settings: " . $e->getMessage());
    $current_settings = [
        'site_name' => 'AutoParts Hub',
        'contact_email' => 'support@autopartshub.com',
        'phone' => '+1 (555) 123-4567',
        'address' => '123 Auto Lane, Tech City, TC 10101'
    ];
}

// Handle form submission
if ($_POST) {
    $site_name = trim($_POST['site_name'] ?? '');
    $contact_email = filter_var($_POST['contact_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$site_name || !$contact_email || !$phone || !$address) {
        $error = "All fields are required.";
    } else {
        try {
            // Update existing settings or insert new ones
            if ($current_settings) {
                $stmt = $pdo->prepare("UPDATE settings SET site_name = ?, contact_email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$site_name, $contact_email, $phone, $address, $current_settings['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (site_name, contact_email, phone, address) VALUES (?, ?, ?, ?)");
                $stmt->execute([$site_name, $contact_email, $phone, $address]);
            }
            
            $message = "Settings saved successfully.";
            // Refresh current settings
            $stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
            $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to save settings: " . $e->getMessage());
            $error = "Failed to save settings. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Settings - Admin Panel</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Page Header -->
  <div class="py-6 bg-white shadow-sm border-b">
    <div class="container mx-auto px-6">
      <h1 class="text-2xl font-bold text-gray-800">Settings</h1>
      <p class="text-gray-600 mt-1">Configure site-wide settings and preferences.</p>
    </div>
  </div>

  <div class="container mx-auto px-6 py-8">
    <!-- Success/Error Messages -->
    <?php if ($message): ?>
      <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-lg text-sm flex items-center">
        <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="mb-6 p-4 bg-red-100 text-red-800 rounded-lg text-sm flex items-center">
        <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Site Settings Form -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
      <h2 class="text-xl font-semibold text-gray-800 mb-6">Site Settings</h2>
      <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Site Name</label>
            <input type="text" name="site_name" value="<?= htmlspecialchars($current_settings['site_name'] ?? 'AutoParts Hub') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
            <input type="email" name="contact_email" value="<?= htmlspecialchars($current_settings['contact_email'] ?? 'support@autopartshub.com') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($current_settings['phone'] ?? '+1 (555) 123-4567') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
            <input type="text" name="address" value="<?= htmlspecialchars($current_settings['address'] ?? '123 Auto Lane, Tech City, TC 10101') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
          </div>
        </div>
        <div class="mt-6">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition">
            Save Settings
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include 'includes/admin_footer.php'; ?>
</body>
</html>