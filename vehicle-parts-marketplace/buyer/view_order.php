<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// Fetch order and items
$order = null;
$items = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.total_amount, o.status, o.created_at as order_date,
               o.shipping_name, o.shipping_email, o.shipping_phone,
               o.shipping_address, o.shipping_city, o.shipping_state,
               o.shipping_zip_code, o.shipping_country
        FROM orders o
        WHERE o.id = ? AND o.buyer_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "Order not found.";
        header("Location: orders.php");
        exit();
    }

    $item_stmt = $pdo->prepare("
        SELECT oi.id, oi.quantity, oi.price, p.id as part_id, p.name, p.image_url, c.name as category_name
        FROM order_items oi
        JOIN parts p ON oi.part_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE oi.order_id = ?
    ");
    $item_stmt->execute([$order_id]);
    $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch order: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load order details.";
    header("Location: orders.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>View Order - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .order-item-image {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Order Details</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Review your order and delivery information.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Order Summary -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
          <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-800">Order #<?= htmlspecialchars($order['id']) ?></h2>
            <p class="text-gray-600 mt-1">Placed on <?= date('M j, Y', strtotime($order['order_date'])) ?></p>
          </div>
          
          <div class="p-6">
            <!-- Order Items -->
            <div class="space-y-4">
              <?php foreach ($items as $item): ?>
                <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                  <!-- Image -->
                  <div class="flex-shrink-0 mr-4">
                    <?php if ($item['image_url']): ?>
                      <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="order-item-image">
                    <?php else: ?>
                      <div class="order-item-image bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cog text-gray-400"></i>
                      </div>
                    <?php endif; ?>
                  </div>
                  
                  <!-- Info -->
                  <div class="flex-1 mr-4">
                    <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($item['name']) ?></h3>
                    <span class="capitalize text-sm text-blue-600 mb-2"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></span>
                    <p class="text-sm text-gray-600">Qty: <?= $item['quantity'] ?></p>
                  </div>
                  
                  <!-- Price -->
                  <div class="text-right">
                    <p class="text-lg font-bold text-gray-800">$<?= number_format($item['price'], 2) ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Order Total -->
            <div class="border-t pt-6 mt-6">
              <div class="flex justify-between text-lg font-bold">
                <span>Total Amount</span>
                <span>$<?= number_format($order['total_amount'], 2) ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Shipping Info -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md overflow-hidden sticky top-24">
          <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-800">Shipping Details</h2>
          </div>
          
          <div class="p-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <p class="text-gray-800"><?= htmlspecialchars($order['shipping_name']) ?></p>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <p class="text-gray-800"><?= htmlspecialchars($order['shipping_email']) ?></p>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700">Phone</label>
                <p class="text-gray-800"><?= htmlspecialchars($order['shipping_phone']) ?></p>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700">Address</label>
                <p class="text-gray-800"><?= htmlspecialchars($order['shipping_address']) ?></p>
                <p class="text-gray-800"><?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> <?= htmlspecialchars($order['shipping_zip_code']) ?></p>
                <p class="text-gray-800"><?= htmlspecialchars($order['shipping_country']) ?></p>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <span class="capitalize px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                  <?= htmlspecialchars($order['status']) ?>
                </span>
              </div>
            </div>

            <!-- Cancel Button -->
            <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
              <div class="mt-4 pt-4 border-t">
                <form method="POST" action="cancel_order.php" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                  <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                  <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition text-sm font-medium">
                    <i class="fas fa-times-circle mr-1"></i> Cancel Order
                  </button>
                </form>
              </div>
            <?php elseif ($order['status'] === 'cancelled'): ?>
              <div class="mt-4 pt-4 border-t">
                <p class="text-red-600 font-medium">This order has been cancelled.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>