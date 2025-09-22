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

// Fetch all users with their role
$users = [];
try {
    $stmt = $pdo->query("
        SELECT id, name, email, role, role_status, created_at
        FROM users
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch users: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Users - Admin Panel</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Page Header -->
  <div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-800">Manage Users</h1>
    <p class="text-gray-600 mt-1">View and manage all registered users.</p>
  </div>

  <!-- Success/Error Messages -->
  <?php if (isset($_GET['message']) && $_GET['message'] === 'user_updated'): ?>
    <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-lg text-sm">
      <i class="fas fa-check-circle mr-2"></i> User updated successfully.
    </div>
  <?php elseif (isset($_GET['message']) && $_GET['message'] === 'user_deleted'): ?>
    <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-lg text-sm">
      <i class="fas fa-check-circle mr-2"></i> User deleted successfully.
    </div>
  <?php endif; ?>

  <!-- Users Table -->
  <div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
          <tr>
            <th class="px-6 py-3 text-left">Name</th>
            <th class="px-6 py-3 text-left">Email</th>
            <th class="px-6 py-3 text-left">Role</th>
            <th class="px-6 py-3 text-left">Status</th>
            <th class="px-6 py-3 text-left">Joined</th>
            <th class="px-6 py-3 text-left">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="6" class="px-6 py-8 text-center text-gray-500">No users found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $user): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium"><?= htmlspecialchars($user['name']) ?></td>
                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($user['email']) ?></td>
                <td class="px-6 py-4">
                  <span class="capitalize px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">
                    <?= htmlspecialchars($user['role']) ?>
                  </span>
                </td>
                <td class="px-6 py-4">
                  <?php if ($user['role_status'] === 'approved'): ?>
                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Approved</span>
                  <?php elseif ($user['role_status'] === 'pending'): ?>
                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Pending</span>
                  <?php else: ?>
                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Rejected</span>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                <td class="px-6 py-4 space-x-2">
                  <!-- Edit Button -->
                  <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <a href="edit_user.php?id=<?= $user['id'] ?>" 
                       class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm transition">
                      <i class="fas fa-edit mr-1"></i>
                      <span>Edit</span>
                    </a>

                    <!-- Delete Button -->
                    <a href="delete_user.php?id=<?= $user['id'] ?>" 
                       class="inline-flex items-center text-red-600 hover:text-red-800 text-sm transition">
                      <i class="fas fa-trash mr-1"></i>
                      <span>Delete</span>
                    </a>
                  <?php else: ?>
                    <span class="text-gray-500 text-sm">Self</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php include 'includes/admin_footer.php'; ?>
</body>
</html>