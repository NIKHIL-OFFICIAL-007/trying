<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has approved seller role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Check if user has 'seller' role (even if multi-role) AND is approved
$roles = explode(',', $_SESSION['role']);
if (!in_array('seller', $roles) || $_SESSION['role_status'] !== 'approved') {
    header("Location: ../login.php");
    exit();
}

$user_name = htmlspecialchars($_SESSION['name']);

// Fetch stats
try {
    // Total parts listed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parts WHERE seller_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_parts = (int)$stmt->fetchColumn();

    // Pending orders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN parts p ON oi.part_id = p.id
        WHERE p.seller_id = ? AND o.status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_orders = (int)$stmt->fetchColumn();

    // Total revenue (delivered orders)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(oi.quantity * oi.price), 0) FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN parts p ON oi.part_id = p.id
        WHERE p.seller_id = ? AND o.status = 'delivered'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $total_revenue = (float)$stmt->fetchColumn();

    // Pending reviews
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reviews r
        JOIN parts p ON r.product_id = p.id
        WHERE p.seller_id = ? AND r.is_read = FALSE
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_reviews = (int)$stmt->fetchColumn();

} catch (Exception $e) {
    error_log("Seller dashboard stats query failed: " . $e->getMessage());
    $total_parts = $pending_orders = $pending_reviews = 0;
    $total_revenue = 0.0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Seller Dashboard - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/seller_header.php'; ?>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Parts -->
    <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition transform hover:-translate-y-1">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-xl mr-4">
          <i class="fas fa-boxes"></i>
        </div>
        <div>
          <div class="text-sm font-medium text-gray-500">Total Parts Listed</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($total_parts) ?></div>
        </div>
      </div>
    </div>

    <!-- Pending Orders -->
    <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition transform hover:-translate-y-1">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center text-yellow-600 text-xl mr-4">
          <i class="fas fa-clock"></i>
        </div>
        <div>
          <div class="text-sm font-medium text-gray-500">Pending Orders</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($pending_orders) ?></div>
        </div>
      </div>
    </div>

    <!-- Revenue -->
    <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition transform hover:-translate-y-1">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-600 text-xl mr-4">
          <i class="fas fa-dollar-sign"></i>
        </div>
        <div>
          <div class="text-sm font-medium text-gray-500">Total Revenue</div>
          <div class="text-2xl font-bold text-gray-800">$<?= number_format($total_revenue, 2) ?></div>
        </div>
      </div>
    </div>

    <!-- Reviews -->
    <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition transform hover:-translate-y-1">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center text-teal-600 text-xl mr-4">
          <i class="fas fa-star"></i>
        </div>
        <div>
          <div class="text-sm font-medium text-gray-500">Pending Reviews</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($pending_reviews) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="bg-white rounded-xl shadow-md p-6 mb-8">
    <h2 class="text-xl font-bold text-gray-800 mb-6">Quick Actions</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <a href="add_part.php" class="flex flex-col items-center p-4 bg-gray-50 hover:bg-blue-600 hover:text-white rounded-lg transition transform hover:-translate-y-1">
        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-blue-600 mb-3">
          <i class="fas fa-plus text-xl"></i>
        </div>
        <span class="font-medium">Add Part</span>
      </a>

      <a href="manage_parts.php" class="flex flex-col items-center p-4 bg-gray-50 hover:bg-green-600 hover:text-white rounded-lg transition transform hover:-translate-y-1">
        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-green-600 mb-3">
          <i class="fas fa-cogs text-xl"></i>
        </div>
        <span class="font-medium">Manage Parts</span>
      </a>

      <a href="orders.php" class="flex flex-col items-center p-4 bg-gray-50 hover:bg-purple-600 hover:text-white rounded-lg transition transform hover:-translate-y-1">
        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-purple-600 mb-3">
          <i class="fas fa-boxes text-xl"></i>
        </div>
        <span class="font-medium">Orders</span>
      </a>

      <a href="reviews.php" class="flex flex-col items-center p-4 bg-gray-50 hover:bg-yellow-600 hover:text-white rounded-lg transition transform hover:-translate-y-1">
        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-yellow-600 mb-3">
          <i class="fas fa-comments text-xl"></i>
        </div>
        <span class="font-medium">Reviews</span>
      </a>
    </div>
  </div>

  <?php include 'includes/seller_footer.php'; ?>
</body>
</html>