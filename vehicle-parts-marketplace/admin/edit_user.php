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

$user_id = $_GET['id'] ?? null;

// Prevent editing self
if ($user_id == $_SESSION['user_id']) {
    header("Location: manage_users.php?error=edit_self");
    exit();
}

// Fetch user data
$user = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, email, role, role_status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: manage_users.php?error=user_not_found");
        exit();
    }
} catch (Exception $e) {
    error_log("Failed to fetch user: " . $e->getMessage());
    header("Location: manage_users.php?error=fetch_failed");
    exit();
}

// Handle form submission
if ($_POST) {
    $name = trim($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if (empty($name) || !$email) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // Update user info
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $user_id]);

            // Add notification
            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, '⚙️ Your account has been updated by admin.', 'admin_update')")
                ->execute([$user_id]);

            header("Location: manage_users.php?message=user_updated");
            exit();
        } catch (Exception $e) {
            $error = "Update failed. Please try again.";
            error_log("Update failed: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit User - Admin Panel</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Page Header -->
  <div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-800">Edit User</h1>
    <p class="text-gray-600 mt-1">Update user details.</p>
  </div>

  <!-- Edit Form -->
  <div class="bg-white rounded-xl shadow-md p-6 max-w-2xl mx-auto">
    <form method="POST" class="space-y-4">
      <?php if (isset($error)): ?>
        <div class="p-3 bg-red-100 text-red-800 rounded-lg text-sm">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" 
               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Current Role</label>
        <div class="px-4 py-2 bg-gray-100 rounded-lg font-medium capitalize">
            <?= htmlspecialchars($user['role']) ?>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
        <div class="px-4 py-2 bg-blue-100 rounded-lg font-medium capitalize">
            <?= htmlspecialchars($user['role_status']) ?>
        </div>
      </div>

      <div class="flex justify-end space-x-4 pt-4">
        <a href="manage_users.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition">
          Cancel
        </a>
        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
          Save Changes
        </button>
      </div>
    </form>
  </div>

  <?php include 'includes/admin_footer.php'; ?>
</body>
</html>