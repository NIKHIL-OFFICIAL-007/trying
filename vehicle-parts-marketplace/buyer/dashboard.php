<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // ✅ Fixed path: assuming /buyer/dashboard.php
    exit();
}

// ✅ Check if user has 'buyer' role (even if they have others)
if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    // ❌ User does NOT have buyer role
    header("Location: ../login.php");
    exit();
}

// ✅ All good — continue with dashboard
$user_name = htmlspecialchars($_SESSION['name']);
$user_id = $_SESSION['user_id'];

// Fetch stats
try {
    // Total orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ?");
    $stmt->execute([$user_id]);
    $total_orders = (int)$stmt->fetchColumn();

    // Pending orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_orders = (int)$stmt->fetchColumn();

    // Delivered orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status = 'delivered'");
    $stmt->execute([$user_id]);
    $delivered_orders = (int)$stmt->fetchColumn();

    // Items in wishlist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_count = (int)$stmt->fetchColumn();

    // Cart items and total
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as item_count, SUM(ci.quantity * p.price) as total 
        FROM cart_items ci 
        JOIN parts p ON ci.product_id = p.id 
        WHERE ci.buyer_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_data = $stmt->fetch();
    $cart_item_count = (int)$cart_data['item_count'];
    $cart_total = $cart_data['total'] ? number_format($cart_data['total'], 2) : '0.00';

    // Recent cart items (last 3 added)
    $stmt = $pdo->prepare("
        SELECT ci.*, p.name, p.price, p.image_url 
        FROM cart_items ci 
        JOIN parts p ON ci.product_id = p.id 
        WHERE ci.buyer_id = ? 
        ORDER BY ci.added_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $recent_cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Unread tickets count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status != 'closed' AND (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = tickets.id AND is_read = FALSE AND sender_role = 'support') > 0");
    $stmt->execute([$user_id]);
    $unread_tickets = (int)$stmt->fetchColumn();

} catch (Exception $e) {
    error_log("Buyer dashboard stats query failed: " . $e->getMessage());
    $total_orders = $pending_orders = $delivered_orders = $wishlist_count = $cart_item_count = 0;
    $cart_total = '0.00';
    $recent_cart_items = [];
    $unread_tickets = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Buyer Dashboard - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .cart-item-image {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 8px;
    }
    .stats-card:hover {
      transform: translateY(-5px);
      transition: transform 0.3s ease;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
    <!-- Total Orders -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-xl mr-4">
          <i class="fas fa-boxes"></i>
        </div>
        <div>
          <div class="text-sm font-medium text-gray-500">Total Orders</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($total_orders) ?></div>
        </div>
      </div>
    </div>

    <!-- Pending Orders -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
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

    <!-- Delivered Orders -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-600 text-xl mr-4">
          <i class="fas fa-check-circle"></i>
        </div>
        <div>
          <div class="text-sm font-medium text-gray-500">Delivered Orders</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($delivered_orders) ?></div>
        </div>
      </div>
    </div>

    <!-- Wishlist -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center text-red-600 text-xl mr-4">
          <i class="fas fa-heart"></i>
        </div>
        <div>
          <div class="text-sm font-medium text-gray-500">Wishlist Items</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($wishlist_count) ?></div>
        </div>
      </div>
    </div>

    <!-- Cart Summary -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600 text-xl mr-4">
          <i class="fas fa-shopping-cart"></i>
        </div>
        <div>
          <div class="text-sm font-medium text-gray-500">Cart Items</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($cart_item_count) ?></div>
          <div class="text-sm font-medium text-purple-600">Total: $<?= $cart_total ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="bg-white rounded-xl shadow-md p-6 mb-8">
    <h2 class="text-xl font-bold text-gray-800 mb-6">Quick Actions</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <a href="orders.php" class="flex flex-col items-center p-4 bg-gray-50 hover:bg-blue-600 hover:text-white rounded-lg transition transform hover:-translate-y-1">
        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-blue-600 mb-3">
          <i class="fas fa-boxes text-xl"></i>
        </div>
        <span class="font-medium">My Orders</span>
      </a>

      <a href="wishlist.php" class="flex flex-col items-center p-4 bg-gray-50 hover:bg-red-600 hover:text-white rounded-lg transition transform hover:-translate-y-1">
        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-red-600 mb-3">
          <i class="fas fa-heart text-xl"></i>
        </div>
        <span class="font-medium">Wishlist</span>
      </a>

      <a href="cart.php" class="flex flex-col items-center p-4 bg-gray-50 hover:bg-purple-600 hover:text-white rounded-lg transition transform hover:-translate-y-1">
        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-purple-600 mb-3">
          <i class="fas fa-shopping-cart text-xl"></i>
        </div>
        <span class="font-medium">My Cart</span>
      </a>

      <a href="reviews.php" class="flex flex-col items-center p-4 bg-gray-50 hover:bg-yellow-600 hover:text-white rounded-lg transition transform hover:-translate-y-1">
        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-yellow-600 mb-3">
          <i class="fas fa-star text-xl"></i>
        </div>
        <span class="font-medium">My Reviews</span>
      </a>

      <a href="ticket_form.php" class="flex flex-col items-center p-4 bg-gray-50 hover:bg-green-600 hover:text-white rounded-lg transition transform hover:-translate-y-1">
        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-green-600 mb-3">
          <i class="fas fa-ticket-alt text-xl"></i>
        </div>
        <span class="font-medium">Support</span>
      </a>
    </div>
  </div>

  <!-- Two-column layout for Cart and Recent Activity -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Cart Summary -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">My Cart</h2>
        <a href="cart.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
          View Full Cart <i class="fas fa-arrow-right ml-1"></i>
        </a>
      </div>
      
      <?php if ($cart_item_count > 0): ?>
        <div class="space-y-4">
          <?php foreach ($recent_cart_items as $item): ?>
            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
              <?php if ($item['image_url']): ?>
                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-image mr-4">
              <?php else: ?>
                <div class="cart-item-image bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                  <i class="fas fa-cog text-gray-400"></i>
                </div>
              <?php endif; ?>
              
              <div class="flex-1">
                <p class="font-medium text-gray-800 truncate"><?= htmlspecialchars($item['name']) ?></p>
                <p class="text-sm text-gray-500">Qty: <?= $item['quantity'] ?></p>
              </div>
              
              <div class="text-right">
                <p class="font-medium text-purple-600">$<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                <p class="text-xs text-gray-500">$<?= number_format($item['price'], 2) ?> each</p>
              </div>
            </div>
          <?php endforeach; ?>
          
          <?php if ($cart_item_count > 3): ?>
            <div class="text-center pt-2">
              <p class="text-sm text-gray-500">+<?= $cart_item_count - 3 ?> more items in cart</p>
            </div>
          <?php endif; ?>
          
          <div class="border-t pt-4 mt-4">
            <div class="flex justify-between items-center mb-2">
              <span class="font-medium text-gray-700">Subtotal:</span>
              <span class="font-bold text-lg text-purple-600">$<?= $cart_total ?></span>
            </div>
            <a href="cart.php" class="block w-full mt-4 text-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition">
              Proceed to Checkout
            </a>
          </div>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-shopping-cart text-gray-400 text-2xl"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">Your cart is empty</h3>
          <p class="text-gray-500 mb-4">Start shopping to add items to your cart</p>
          <a href="../buyer/browse_parts.php" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Browse Parts
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <h2 class="text-xl font-bold text-gray-800 mb-6">Recent Activity</h2>
      <div class="space-y-4">
        <div class="flex items-start p-4 bg-gray-50 rounded-lg">
          <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-boxes text-blue-600"></i>
          </div>
          <div>
            <p class="font-medium text-gray-800">New order placed: <span class="text-blue-600">#ORD-1001</span></p>
            <p class="text-sm text-gray-500">2 hours ago</p>
          </div>
        </div>
        
        <div class="flex items-start p-4 bg-gray-50 rounded-lg">
          <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-heart text-red-600"></i>
          </div>
          <div>
            <p class="font-medium text-gray-800">Added to wishlist: <span class="text-red-600">Brake Pads</span></p>
            <p class="text-sm text-gray-500">4 hours ago</p>
          </div>
        </div>
        
        <div class="flex items-start p-4 bg-gray-50 rounded-lg">
          <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-star text-yellow-600"></i>
          </div>
          <div>
            <p class="font-medium text-gray-800">Left a review: <span class="text-yellow-600">★★★★★</span></p>
            <p class="text-sm text-gray-500">Yesterday</p>
          </div>
        </div>
        
        <?php if ($cart_item_count > 0): ?>
        <div class="flex items-start p-4 bg-gray-50 rounded-lg">
          <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-shopping-cart text-purple-600"></i>
          </div>
          <div>
            <p class="font-medium text-gray-800">Added to cart: <span class="text-purple-600"><?= $cart_item_count ?> item(s)</span></p>
            <p class="text-sm text-gray-500">Recently</p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>