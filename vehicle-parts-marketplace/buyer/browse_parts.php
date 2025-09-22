<?php
session_start();
include '../includes/config.php';

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch user role and status
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role, role_status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// ✅ Check if user has buyer role (even if multi-role)
$roles = [];
if (!empty($user['role'])) {
    $roles = explode(',', $user['role']);
}
$has_buyer_role = in_array('buyer', $roles) && $user['role_status'] === 'approved';

// Fetch wishlist and cart items only if user has buyer role
$wishlist_items = [];
$cart_items = [];

if ($has_buyer_role) {
    try {
        // Get wishlist items
        $wishlist_stmt = $pdo->prepare("SELECT part_id FROM wishlists WHERE user_id = ?");
        $wishlist_stmt->execute([$user_id]);
        $wishlist_items = $wishlist_stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get cart items
        $cart_stmt = $pdo->prepare("SELECT product_id FROM cart_items WHERE buyer_id = ?");
        $cart_stmt->execute([$user_id]);
        $cart_items = $cart_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Failed to fetch wishlist/cart: " . $e->getMessage());
    }
}

// Get filters
$search = trim($_GET['search'] ?? ''); // Trim whitespace
$category_id = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$parts = [];
$total_parts = 0;

try {
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM parts p WHERE p.status = 'active'";
    $params = [];
    $types = [];

    if ($search !== '') {
        $count_sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $types[] = \PDO::PARAM_STR;
        $types[] = \PDO::PARAM_STR;
    }
    if ($category_id !== '' && is_numeric($category_id)) {
        $count_sql .= " AND p.category_id = ?";
        $params[] = $category_id;
        $types[] = \PDO::PARAM_INT;
    }
    if ($min_price !== '' && is_numeric($min_price)) {
        $count_sql .= " AND p.price >= ?";
        $params[] = $min_price;
        $types[] = \PDO::PARAM_STR;
    }
    if ($max_price !== '' && is_numeric($max_price)) {
        $count_sql .= " AND p.price <= ?";
        $params[] = $max_price;
        $types[] = \PDO::PARAM_STR;
    }

    $stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, $types[$key]);
    }
    $stmt->execute();
    $total_parts = $stmt->fetchColumn();

    // Get parts with category name
    $sql = "SELECT p.id, p.name, c.name as category_name, p.price, p.stock_quantity as stock, 
                   p.description, p.image_url, p.created_at, u.name as seller_name
            FROM parts p 
            LEFT JOIN users u ON p.seller_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'";

    $params = [];
    $types = [];

    if ($search !== '') {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $types[] = \PDO::PARAM_STR;
        $types[] = \PDO::PARAM_STR;
    }
    if ($category_id !== '' && is_numeric($category_id)) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_id;
        $types[] = \PDO::PARAM_INT;
    }
    if ($min_price !== '' && is_numeric($min_price)) {
        $sql .= " AND p.price >= ?";
        $params[] = $min_price;
        $types[] = \PDO::PARAM_STR;
    }
    if ($max_price !== '' && is_numeric($max_price)) {
        $sql .= " AND p.price <= ?";
        $params[] = $max_price;
        $types[] = \PDO::PARAM_STR;
    }

    // Add sorting
    switch ($sort) {
        case 'price_low':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY p.price DESC";
            break;
        case 'name':
            $sql .= " ORDER BY p.name ASC";
            break;
        default:
            $sql .= " ORDER BY p.created_at DESC";
            break;
    }

    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $types[] = \PDO::PARAM_INT;
    $params[] = $offset;
    $types[] = \PDO::PARAM_INT;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, $types[$key]);
    }
    $stmt->execute();
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all categories for filter
    $categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if ($categories === null) $categories = [];
} catch (Exception $e) {
    error_log("Failed to fetch parts: " . $e->getMessage());
    $parts = [];
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Browse Parts - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --secondary: #64748b;
      --accent: #f97316;
      --light: #f8fafc;
      --dark: #0f172a;
    }

    body {
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      background-color: #f8fafc;
    }

    .gradient-bg {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    }

    .part-card {
      transition: all 0.3s ease;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      position: relative;
    }

    .part-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .category-pill {
      transition: all 0.2s ease;
    }

    .category-pill:hover {
      transform: scale(1.05);
    }

    .price-tag {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-weight: 600;
    }

    .filter-section {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .loading-indicator {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: white;
      padding: 1rem 2rem;
      border-radius: 8px;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      align-items: center;
    }

    .wishlist-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      background: white;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 10;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .wishlist-btn:hover {
      transform: scale(1.1);
    }

    .wishlist-btn.active {
      color: #ef4444;
    }

    .wishlist-btn:not(.active) {
      color: #9ca3af;
    }

    .wishlist-btn:not(.active):hover {
      color: #ef4444;
    }

    .part-image-container {
      position: relative;
      cursor: pointer;
    }

    .part-image-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.03);
      opacity: 0;
      transition: opacity 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .part-image-container:hover .part-image-overlay {
      opacity: 1;
    }

    .view-details-btn {
      background: rgba(255, 255, 255, 0.9);
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 500;
      transform: translateY(10px);
      transition: all 0.3s ease;
      opacity: 0;
    }

    .part-image-container:hover .view-details-btn {
      transform: translateY(0);
      opacity: 1;
    }

    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .heartbeat {
      animation: heartbeat 1.5s ease-in-out infinite both;
    }

    @keyframes heartbeat {
      from { transform: scale(1); }
      10% { transform: scale(0.91); }
      17% { transform: scale(0.98); }
      33% { transform: scale(0.87); }
      45% { transform: scale(1); }
    }

    .added-to-cart {
      background: linear-gradient(135deg, #10b981, #059669);
      transition: all 0.3s ease;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include '../includes/header.php'; ?>

  <!-- Page Header -->
  <div class="gradient-bg text-white py-16">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Browse Vehicle Parts</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Discover high-quality auto parts from trusted sellers across India.</p>
    </div>
  </div>

  <!-- Loading Indicator -->
  <div id="filterLoader" class="loading-indicator">
    <i class="fas fa-spinner fa-spin text-primary mr-2"></i> Filtering products...
  </div>

  <!-- Wishlist Notification -->
  <div id="wishlistNotification" class="fixed top-4 right-4 hidden p-4 bg-green-500 text-white rounded-lg shadow-lg max-w-xs z-50 transition-opacity duration-300">
    <div class="flex items-center">
      <i class="fas fa-check-circle mr-3"></i>
      <span id="wishlistMessage">Item added to wishlist</span>
      <button class="ml-4" onclick="hideWishlistNotification()">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>

  <!-- Role Error Modal -->
  <div id="roleErrorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 max-w-sm mx-4 text-center shadow-lg">
      <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
      <h3 class="text-lg font-bold text-gray-800 mb-2">Permission Denied</h3>
      <p class="text-gray-600 mb-4">You need a buyer role to use this function.</p>
      <button onclick="hideRoleError()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        OK
      </button>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Sidebar Filters -->
      <div class="w-full lg:w-1/4">
        <div class="filter-section p-6 sticky top-24">
          <h2 class="text-xl font-bold mb-6 text-gray-800 border-b pb-2">Filters</h2>

          <!-- Search -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" id="searchInput" placeholder="Search parts..." 
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                   value="<?= htmlspecialchars($search) ?>">
          </div>

          <!-- Price Range -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Price Range (₹)</label>
            <div class="flex gap-2">
              <input type="number" id="minPrice" placeholder="Min" 
                     class="w-1/2 px-3 py-2 border border-gray-300 rounded-lg" 
                     value="<?= htmlspecialchars($min_price) ?>" min="0" step="1">
              <input type="number" id="maxPrice" placeholder="Max" 
                     class="w-1/2 px-3 py-2 border border-gray-300 rounded-lg"
                     value="<?= htmlspecialchars($max_price) ?>" min="0" step="1">
            </div>
          </div>

          <!-- Category Filter -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
            <select id="categorySelect" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars(ucfirst($cat['name'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Sort -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
            <select id="sortSelect" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
              <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
              <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
              <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
              <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name: A to Z</option>
            </select>
          </div>

          <!-- Apply Filters Button -->
          <button id="applyFilters" class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium">
            Apply Filters
          </button>

          <!-- Reset Filters -->
          <button id="resetFilters" class="w-full mt-3 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition font-medium">
            Reset Filters
          </button>
        </div>
      </div>

      <!-- Main Content Area -->
      <div class="w-full lg:w-3/4">
        <!-- Results Info and View Toggle -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
          <div class="text-gray-600 mb-4 md:mb-0">
            <?php if ($total_parts === 0): ?>
              <p>No parts found.</p>
            <?php else: ?>
              <p class="font-medium">Showing <span class="text-blue-600"><?= min($offset + 1, $total_parts) ?>–<?= min($offset + count($parts), $total_parts) ?></span> of <span class="text-blue-600"><?= $total_parts ?></span> parts</p>
            <?php endif; ?>
          </div>

          <!-- View Toggle -->
          <div class="flex items-center space-x-2 bg-white p-2 rounded-lg shadow-sm">
            <button id="gridView" class="p-2 rounded-md bg-blue-100 text-blue-600">
              <i class="fas fa-th"></i>
            </button>
            <button id="listView" class="p-2 rounded-md text-gray-500 hover:bg-gray-100">
              <i class="fas fa-list"></i>
            </button>
          </div>
        </div>

        <!-- Category Tabs -->
        <div class="flex flex-wrap gap-2 mb-8">
          <a href="?" class="category-pill px-4 py-2 rounded-full bg-blue-600 text-white font-medium">
            All Categories
          </a>
          <?php foreach ($categories as $cat): ?>
            <a href="?category=<?= $cat['id'] ?>" 
               class="category-pill px-4 py-2 rounded-full <?= $category_id == $cat['id'] ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300' ?> font-medium">
              <?= htmlspecialchars(ucfirst($cat['name'])) ?>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Parts Grid -->
        <div id="partsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php if (empty($parts)): ?>
            <div class="col-span-full text-center py-12">
              <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
              <h3 class="text-xl font-medium text-gray-500">No parts available</h3>
              <p class="text-gray-400 mt-2">Try adjusting your filters or check back later for new parts.</p>
              <?php if ($search || $category_id || $min_price || $max_price): ?>
                <a href="?" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                  Clear All Filters
                </a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <?php foreach ($parts as $part): ?>
              <div class="part-card bg-white rounded-xl overflow-hidden">
                <!-- Wishlist Button -->
                <div class="wishlist-btn 
                  <?= $has_buyer_role ? (in_array($part['id'], $wishlist_items) ? 'active' : '') : 'cursor-not-allowed opacity-50' ?>" 
                  data-part-id="<?= $part['id'] ?>" 
                  onclick="handleWishlistAction(this, <?= $part['id'] ?>, event)">
                  <i class="fas fa-heart 
                    <?= in_array($part['id'], $wishlist_items) ? 'heartbeat' : '' ?>
                    <?= !$has_buyer_role ? 'text-gray-400' : '' ?>">
                  </i>
                </div>

                <!-- Clickable Image -->
                <div class="part-image-container relative h-48 bg-gray-100 overflow-hidden" 
                     onclick="window.location.href='view_part.php?id=<?= $part['id'] ?>'">
                  <?php if ($part['image_url']): ?>
                    <img src="<?= htmlspecialchars($part['image_url']) ?>" alt="<?= htmlspecialchars($part['name']) ?>"
                         class="w-full h-full object-cover transition-transform duration-300 hover:scale-105">
                  <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center bg-gray-200">
                      <i class="fas fa-cog text-gray-400 text-4xl"></i>
                    </div>
                  <?php endif; ?>

                  <!-- Overlay -->
                  <div class="part-image-overlay">
                    <span class="view-details-btn">View Details <i class="fas fa-arrow-right ml-1"></i></span>
                  </div>

                  <div class="absolute top-3 left-3">
                    <span class="price-tag">₹<?= number_format($part['price'], 0) ?></span>
                  </div>
                  <?php if ($part['stock'] <= 5 && $part['stock'] > 0): ?>
                    <div class="absolute top-3 right-12 bg-amber-500 text-white text-xs px-2 py-1 rounded-full">
                      Low Stock
                    </div>
                  <?php elseif ($part['stock'] == 0): ?>
                    <div class="absolute top-3 right-12 bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                      Out of Stock
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Product Info -->
                <div class="p-5">
                  <div class="flex justify-between items-start mb-2">
                    <h3 class="font-semibold text-gray-800 text-lg cursor-pointer hover:text-blue-600"
                        onclick="window.location.href='view_part.php?id=<?= $part['id'] ?>'">
                      <?= htmlspecialchars($part['name']) ?>
                    </h3>
                    <span class="text-xs text-gray-500"><?= date('M j, Y', strtotime($part['created_at'])) ?></span>
                  </div>
                  <span class="inline-block capitalize text-xs font-medium text-blue-600 bg-blue-100 px-2 py-1 rounded-full mb-3">
                    <?= htmlspecialchars($part['category_name'] ?? 'Unknown') ?>
                  </span>
                  <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($part['description']) ?></p>

                  <div class="flex items-center justify-between mb-4">
                    <div class="text-sm text-gray-500">
                      <i class="fas fa-store mr-1"></i> <?= htmlspecialchars($part['seller_name'] ?? 'Unknown') ?>
                    </div>
                    <div class="text-sm <?= $part['stock'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                      <i class="fas fa-boxes mr-1"></i> <?= $part['stock'] ?> in stock
                    </div>
                  </div>

                  <div class="flex space-x-2">
                    <form method="POST" action="cart/add_to_cart.php" class="flex-1">
                      <input type="hidden" name="part_id" value="<?= $part['id'] ?>">
                      <input type="hidden" name="quantity" value="1">
                      <?php if ($has_buyer_role): ?>
                        <?php if (in_array($part['id'], $cart_items)): ?>
                          <!-- Already in Cart - Disabled Button -->
                          <button type="button" 
                                  class="w-full px-4 py-2 bg-green-600 text-white rounded-lg flex items-center justify-center cursor-default added-to-cart">
                            <i class="fas fa-check-circle mr-2"></i>
                            Added to Cart
                          </button>
                        <?php else: ?>
                          <!-- Not in Cart - Active Button -->
                          <button type="submit" 
                                  class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                                  <?= $part['stock'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart mr-2"></i>
                            <?= $part['stock'] > 0 ? 'Add to Cart' : 'Out of Stock' ?>
                          </button>
                        <?php endif; ?>
                      <?php else: ?>
                        <button type="button" 
                                class="w-full px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center justify-center"
                                onclick="showRoleError()">
                          <i class="fas fa-ban mr-2"></i> Buyer Only
                        </button>
                      <?php endif; ?>
                    </form>
                    <a href="view_part.php?id=<?= $part['id'] ?>" 
                       class="flex items-center justify-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition">
                      <i class="fas fa-eye"></i>
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_parts > $limit): ?>
          <div class="flex justify-center mt-12">
            <nav class="inline-flex rounded-md shadow-sm">
              <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(['search' => $search, 'category' => $category_id, 'min_price' => $min_price, 'max_price' => $max_price, 'sort' => $sort, 'page' => $page - 1]) ?>"
                   class="px-4 py-2 border border-gray-300 bg-white text-blue-600 hover:bg-blue-50 rounded-l-md">
                  <i class="fas fa-chevron-left mr-1"></i> Previous
                </a>
              <?php endif; ?>

              <?php 
              $total_pages = ceil($total_parts / $limit);
              $start_page = max(1, $page - 2);
              $end_page = min($total_pages, $start_page + 4);

              if ($end_page - $start_page < 4 && $start_page > 1) {
                $start_page = max(1, $end_page - 4);
              }

              for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?<?= http_build_query(['search' => $search, 'category' => $category_id, 'min_price' => $min_price, 'max_price' => $max_price, 'sort' => $sort, 'page' => $i]) ?>"
                   class="px-4 py-2 border border-gray-300 bg-white text-blue-600 hover:bg-blue-50 <?= $page === $i ? 'font-bold bg-blue-50' : '' ?>">
                  <?= $i ?>
                </a>
              <?php endfor; ?>

              <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(['search' => $search, 'category' => $category_id, 'min_price' => $min_price, 'max_price' => $max_price, 'sort' => $sort, 'page' => $page + 1]) ?>"
                   class="px-4 py-2 border border-gray-300 bg-white text-blue-600 hover:bg-blue-50 rounded-r-md">
                  Next <i class="fas fa-chevron-right ml-1"></i>
                </a>
              <?php endif; ?>
            </nav>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const filterLoader = document.getElementById('filterLoader');
      const searchInput = document.getElementById('searchInput');
      const categorySelect = document.getElementById('categorySelect');
      const sortSelect = document.getElementById('sortSelect');
      const minPrice = document.getElementById('minPrice');
      const maxPrice = document.getElementById('maxPrice');
      const applyFilters = document.getElementById('applyFilters');
      const resetFilters = document.getElementById('resetFilters');
      const gridView = document.getElementById('gridView');
      const listView = document.getElementById('listView');
      const partsContainer = document.getElementById('partsContainer');

      // Function to apply filters with loading indicator
      function applyFiltersWithLoading() {
        filterLoader.style.display = 'flex';
        
        // Build URL parameters
        const params = new URLSearchParams();
        if (searchInput.value.trim() !== '') params.set('search', searchInput.value.trim());
        if (categorySelect.value) params.set('category', categorySelect.value);
        if (sortSelect.value) params.set('sort', sortSelect.value);
        if (minPrice.value) params.set('min_price', minPrice.value);
        if (maxPrice.value) params.set('max_price', maxPrice.value);
        
        // Redirect after a short delay to show loading indicator
        setTimeout(() => {
          window.location.href = 'browse_parts.php?' + params.toString();
        }, 300);
      }

      // Event listeners
      applyFilters.addEventListener('click', function() {
        applyFiltersWithLoading();
      });

      resetFilters.addEventListener('click', function() {
        searchInput.value = '';
        categorySelect.value = '';
        sortSelect.value = 'newest';
        minPrice.value = '';
        maxPrice.value = '';
        applyFiltersWithLoading();
      });

      // Handle Enter key press on search input
      searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          applyFiltersWithLoading();
        }
      });

      // Also handle Enter key press on other inputs for better UX
      categorySelect.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          applyFiltersWithLoading();
        }
      });

      sortSelect.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          applyFiltersWithLoading();
        }
      });

      minPrice.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          applyFiltersWithLoading();
        }
      });

      maxPrice.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          applyFiltersWithLoading();
        }
      });

      // View toggle
      gridView.addEventListener('click', function() {
        partsContainer.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6';
        gridView.className = 'p-2 rounded-md bg-blue-100 text-blue-600';
        listView.className = 'p-2 rounded-md text-gray-500 hover:bg-gray-100';
      });

      listView.addEventListener('click', function() {
        partsContainer.className = 'grid grid-cols-1 gap-6';
        listView.className = 'p-2 rounded-md bg-blue-100 text-blue-600';
        gridView.className = 'p-2 rounded-md text-gray-500 hover:bg-gray-100';
      });
    });

    function showRoleError() {
      document.getElementById('roleErrorModal').classList.remove('hidden');
    }

    function hideRoleError() {
      document.getElementById('roleErrorModal').classList.add('hidden');
    }

    function handleWishlistAction(button, partId, event) {
      event.stopPropagation();
      <?php if (!$has_buyer_role): ?>
        showRoleError();
        return;
      <?php else: ?>
        const isActive = button.classList.contains('active');
        const heartIcon = button.querySelector('i');
        heartIcon.className = 'fas fa-spinner fa-spin';
        const formData = new FormData();
        formData.append('part_id', partId);
        formData.append('action', isActive ? 'remove' : 'add');

        fetch('wishlist/toggle_wishlist.php', { method: 'POST', body: formData })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              if (isActive) {
                button.classList.remove('active');
                heartIcon.className = 'fas fa-heart';
                showWishlistNotification(data.message);
              } else {
                button.classList.add('active');
                heartIcon.className = 'fas fa-heart heartbeat';
                showWishlistNotification(data.message);
              }
            } else {
              showWishlistNotification('Error: ' + data.message);
              heartIcon.className = isActive ? 'fas fa-heart heartbeat' : 'fas fa-heart';
            }
          })
          .catch(error => {
            console.error('Wishlist Error:', error);
            showWishlistNotification('Network error. Please try again.');
            heartIcon.className = isActive ? 'fas fa-heart heartbeat' : 'fas fa-heart';
          });
      <?php endif; ?>
    }

    function showWishlistNotification(message) {
      const n = document.getElementById('wishlistNotification');
      const m = document.getElementById('wishlistMessage');
      m.textContent = message;
      n.classList.remove('hidden');
      setTimeout(() => n.classList.add('hidden'), 3000);
    }

    function hideWishlistNotification() {
      document.getElementById('wishlistNotification').classList.add('hidden');
    }

    // Make entire card clickable (except buttons and wishlist)
    document.querySelectorAll('.part-card').forEach(card => {
      card.addEventListener('click', function(e) {
        // Check if click is on button or wishlist button
        if (e.target.tagName === 'BUTTON' || 
            e.target.closest('button') || 
            e.target.closest('.wishlist-btn')) return;
        
        // Get part ID from wishlist button
        const wishlistBtn = this.querySelector('.wishlist-btn');
        if (wishlistBtn) {
          window.location.href = 'view_part.php?id=' + wishlistBtn.dataset.partId;
        }
      });
    });
  </script>
</body>
</html>