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

// Fetch wishlist items with part details (FIXED: Join with categories for category_name)
$wishlist = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.stock_quantity as stock, 
               p.image_url, p.description, c.name as category_name
        FROM wishlists w
        JOIN parts p ON w.part_id = p.id
        JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = ? AND p.status = 'active'
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch wishlist: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Wishlist - Buyer Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Wishlist</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Save your favorite parts for later purchase.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php if (empty($wishlist)): ?>
        <div class="col-span-full text-center py-12">
          <i class="fas fa-heart-broken text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-medium text-gray-500">Your wishlist is empty</h3>
          <p class="text-gray-400 mt-2">Add items to your wishlist while browsing parts.</p>
          <a href="../buyer/browse_parts.php" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Browse Parts
          </a>
        </div>
      <?php else: ?>
        <?php foreach ($wishlist as $item): ?>
          <div class="bg-white rounded-xl overflow-hidden shadow-md hover:shadow-lg transition transform hover:-translate-y-1">
            <!-- Image -->
            <div class="relative h-48 bg-gray-100">
              <?php if ($item['image_url']): ?>
                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>"
                     class="w-full h-full object-cover transition-transform duration-300 hover:scale-105">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center bg-gray-200">
                  <i class="fas fa-cog text-gray-400 text-4xl"></i>
                </div>
              <?php endif; ?>
              
              <!-- Price Tag -->
              <div class="absolute top-4 left-4 bg-blue-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                $<?= number_format($item['price'], 2) ?>
              </div>
              
              <!-- Remove Button (Heart Emoji) -->
              <div class="absolute top-4 right-4">
                <form method="POST" action="wishlist/remove_from_wishlist.php">
                  <input type="hidden" name="part_id" value="<?= $item['id'] ?>">
                  <button type="submit" class="bg-red-500 text-white p-2 rounded-full hover:bg-red-600 transition">
                    <i class="fas fa-heart"></i>
                  </button>
                </form>
              </div>
            </div>
            
            <!-- Info -->
            <div class="p-5">
              <h3 class="font-semibold text-gray-800 mb-1"><?= htmlspecialchars($item['name']) ?></h3>
              <span class="capitalize text-sm text-blue-600 mb-2"><?= htmlspecialchars($item['category_name']) ?></span>
              <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($item['description'] ?? '') ?></p>
              
              <div class="flex items-center justify-between mb-4">
                <div class="text-sm <?= $item['stock'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                  <i class="fas fa-boxes mr-1"></i> <?= $item['stock'] ?> in stock
                </div>
              </div>
              
              <div class="flex space-x-2">
                <form method="POST" action="../cart/add_to_cart.php" class="flex-1">
                  <input type="hidden" name="part_id" value="<?= $item['id'] ?>">
                  <input type="hidden" name="quantity" value="1">
                  <button type="submit" 
                          class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center justify-center disabled:opacity-50"
                          <?= $item['stock'] <= 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-shopping-cart mr-2"></i>
                    <?= $item['stock'] > 0 ? 'Add to Cart' : 'Out of Stock' ?>
                  </button>
                </form>
                <a href="view_part.php?id=<?= $item['id'] ?>" 
                   class="flex items-center justify-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition">
                  <i class="fas fa-eye"></i>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>