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

$roles = explode(',', $_SESSION['role']);
if (!in_array('seller', $roles) || $_SESSION['role_status'] !== 'approved') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name']);

// Fetch orders for this seller
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.total_amount, o.status, o.created_at as order_date, u.name as buyer_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN parts p ON oi.part_id = p.id
        JOIN users u ON o.buyer_id = u.id
        WHERE p.seller_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Orders - Seller Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/seller_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Orders</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">View and manage your customer orders.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Your Orders</h2>
        <p class="text-gray-600 mt-1">Manage your customer orders here.</p>
      </div>
      
      <div class="p-6">
        <?php if (empty($orders)): ?>
          <div class="text-center py-12">
            <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-500">No orders yet</h3>
            <p class="text-gray-400 mt-2">Start selling parts to see orders here.</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
                <tr>
                  <th class="px-6 py-3 text-left">Order ID</th>
                  <th class="px-6 py-3 text-left">Buyer</th>
                  <th class="px-6 py-3 text-left">Total</th>
                  <th class="px-6 py-3 text-left">Status</th>
                  <th class="px-6 py-3 text-left">Date</th>
                  <th class="px-6 py-3 text-left">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <?php foreach ($orders as $order): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium"><?= htmlspecialchars($order['id']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($order['buyer_name']) ?></td>
                    <td class="px-6 py-4">$<?= number_format($order['total_amount'], 2) ?></td>
                    <td class="px-6 py-4">
                      <span class="capitalize px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                        <?= htmlspecialchars($order['status']) ?>
                      </span>
                    </td>
                    <td class="px-6 py-4"><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                    <td class="px-6 py-4 space-x-2">
                      <a href="view_order.php?id=<?= $order['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-eye mr-1"></i> View
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'includes/seller_footer.php'; ?>
</body>
</html>