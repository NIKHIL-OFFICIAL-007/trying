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
$user_name = htmlspecialchars($_SESSION['name']);

// Fetch cart items with part details
$cart_items = [];
$cart_total = 0;
$cart_count = 0;

try {
    $stmt = $pdo->prepare("
        SELECT ci.id as cart_item_id, ci.quantity, 
               p.id as part_id, p.name, p.price, p.stock_quantity as stock, 
               p.image_url, c.name as category_name
        FROM cart_items ci
        JOIN parts p ON ci.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE ci.buyer_id = ? AND p.status = 'active'
        ORDER BY ci.added_at DESC
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cart_items as $item) {
        $cart_total += $item['price'] * $item['quantity'];
        $cart_count += $item['quantity'];
    }

    if (empty($cart_items)) {
        $_SESSION['error'] = "Your cart is empty.";
        header("Location: cart.php");
        exit();
    }

} catch (Exception $e) {
    error_log("Checkout fetch cart failed: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load cart items.";
    header("Location: cart.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        $country = trim($_POST['country'] ?? '');

        $errors = [];
        if (empty($full_name)) $errors[] = "Full name is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (empty($phone)) $errors[] = "Phone number is required.";
        if (empty($address)) $errors[] = "Address is required.";
        if (empty($city)) $errors[] = "City is required.";
        if (empty($state)) $errors[] = "State is required.";
        if (empty($zip_code)) $errors[] = "ZIP code is required.";
        if (empty($country)) $errors[] = "Country is required.";

        if (!empty($errors)) {
            $_SESSION['error'] = implode(" ", $errors);
            header("Location: checkout.php");
            exit();
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert order
        $stmt = $pdo->prepare("
            INSERT INTO orders (buyer_id, total_amount, status, shipping_name, 
                               shipping_email, shipping_phone, shipping_address, 
                               shipping_city, shipping_state, shipping_zip_code, 
                               shipping_country)
            VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, $cart_total + 9.99 + ($cart_total * 0.08),
            $full_name, $email, $phone, $address,
            $city, $state, $zip_code, $country
        ]);
        $order_id = $pdo->lastInsertId();

        // Insert order items and reduce stock
        foreach ($cart_items as $item) {
            $stock_stmt = $pdo->prepare("SELECT stock_quantity FROM parts WHERE id = ?");
            $stock_stmt->execute([$item['part_id']]);
            $stock = $stock_stmt->fetchColumn();

            if ($stock < $item['quantity']) {
                throw new Exception("Not enough stock for {$item['name']}");
            }

            $item_stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, part_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            $item_stmt->execute([
                $order_id,
                $item['part_id'],
                $item['quantity'],
                $item['price']
            ]);

            $update_stock = $pdo->prepare("UPDATE parts SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $update_stock->execute([$item['quantity'], $item['part_id']]);
        }

        // Clear cart
        $clear_cart = $pdo->prepare("DELETE FROM cart_items WHERE buyer_id = ?");
        $clear_cart->execute([$user_id]);

        $pdo->commit();

        $_SESSION['success'] = "Order placed successfully!";
        header("Location: order_success.php?order_id=" . $order_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Checkout failed: " . $e->getMessage());
        $_SESSION['error'] = "Failed to process order. Please try again.";
        header("Location: checkout.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Checkout - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .cart-item-image {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
    }
    .quantity-btn {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #e5e7eb;
      background: white;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .quantity-btn:hover {
      background: #f3f4f6;
    }
    .quantity-input {
      width: 50px;
      height: 32px;
      text-align: center;
      border: 1px solid #e5e7eb;
      border-left: none;
      border-right: none;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Checkout</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Complete your purchase and get your parts delivered.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['error'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
        <div class="flex items-center">
          <i class="fas fa-exclamation-circle mr-2"></i>
          <span><?= htmlspecialchars($_SESSION['error']) ?></span>
        </div>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Order Summary -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
          <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-800">Order Summary</h2>
          </div>
          
          <div class="p-6">
            <?php if (empty($cart_items)): ?>
              <div class="text-center py-12">
                <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-500">Your cart is empty</h3>
                <a href="../buyer/browse_parts.php" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                  Browse Parts
                </a>
              </div>
            <?php else: ?>
              <div class="space-y-6">
                <?php foreach ($cart_items as $item): ?>
                  <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                    <!-- Image -->
                    <div class="flex-shrink-0 mr-4">
                      <?php if ($item['image_url']): ?>
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-image">
                      <?php else: ?>
                        <div class="cart-item-image bg-gray-200 rounded-lg flex items-center justify-center">
                          <i class="fas fa-cog text-gray-400"></i>
                        </div>
                      <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div class="flex-1 mr-4">
                      <h3 class="font-semibold text-gray-800 mb-1"><?= htmlspecialchars($item['name']) ?></h3>
                      <span class="capitalize text-sm text-blue-600 mb-2"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></span>
                      <p class="text-lg font-bold text-blue-600 mt-2">$<?= number_format($item['price'], 2) ?></p>
                      <div class="text-sm <?= $item['stock'] > 0 ? 'text-green-600' : 'text-red-600' ?> mt-1">
                        <i class="fas fa-boxes mr-1"></i> <?= $item['stock'] ?> in stock
                      </div>
                    </div>
                    
                    <!-- Quantity -->
                    <div class="text-right mr-4">
                      <p class="text-lg font-bold text-gray-800">x<?= $item['quantity'] ?></p>
                    </div>
                    
                    <!-- Total -->
                    <div class="text-right">
                      <p class="text-lg font-bold text-gray-800">$<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Shipping Form -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md overflow-hidden sticky top-24">
          <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-800">Shipping Details</h2>
          </div>
          
          <form method="POST" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
              <input type="text" name="full_name" required
                     class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                     placeholder="">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input type="email" name="email" required
                     class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                     placeholder="">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
              <input type="tel" name="phone" required
                     class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                     placeholder="">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
              <input type="text" name="address" required
                     class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                     placeholder="">
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                <input type="text" name="city" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                <input type="text" name="state" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="">
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                <input type="text" name="zip_code" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                <select name="country" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                  <option value="" selected>Select</option>
                  <option value="IND">INDIA</option>
                  <option value="US">USA</option>
                  <option value="CA">Canada</option>
                  <option value="UK">UK</option>
                </select>
              </div>
            </div>

            <div class="pt-4">
              <button type="submit" class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium">
                Place Order
              </button>
              <a href="cart.php" class="block w-full text-center mt-3 px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                Back to Cart
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>