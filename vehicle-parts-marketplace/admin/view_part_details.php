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

// Get part ID from URL
$part_id = $_GET['id'] ?? 0;

if ($part_id <= 0) {
    header("Location: manage_parts.php");
    exit();
}

// Fetch part details with proper joins
$part = [];
$reviews = [];

try {
    // Get part information with seller name
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, u.name as seller_name
        FROM parts p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.seller_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$part_id]);
    $part = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$part) {
        throw new Exception("Part not found");
    }

    // Get reviews for this part
    $review_stmt = $pdo->prepare("
        SELECT r.*, u.name as reviewer_name, u.role as reviewer_role
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.part_id = ?
        ORDER BY r.created_at DESC
    ");
    $review_stmt->execute([$part_id]);
    $reviews = $review_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate average rating
    $total_ratings = count($reviews);
    $sum_ratings = array_sum(array_column($reviews, 'rating'));
    $average_rating = $total_ratings > 0 ? round($sum_ratings / $total_ratings, 1) : 0;
    
} catch (Exception $e) {
    error_log("Error fetching part details: " . $e->getMessage());
    header("Location: manage_parts.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Part Details - Admin Panel</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Page Header -->
  <div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-800">Part Details</h1>
    <p class="text-gray-600 mt-1">View detailed information about this vehicle part including reviews and ratings.</p>
  </div>

  <!-- Part Information -->
  <div class="bg-white rounded-xl shadow-md p-6 mb-8">
    <div class="flex flex-col md:flex-row gap-6">
      <!-- Image -->
      <div class="md:w-1/3">
        <div class="relative h-64 bg-gray-100 rounded-lg overflow-hidden">
          <?php if ($part['image_url']): ?>
            <img src="<?= htmlspecialchars($part['image_url']) ?>" alt="<?= htmlspecialchars($part['name']) ?>"
                 class="w-full h-full object-cover">
          <?php else: ?>
            <div class="w-full h-full flex items-center justify-center">
              <i class="fas fa-car text-gray-400 text-4xl"></i>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Part Info -->
      <div class="md:w-2/3">
        <h2 class="text-2xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($part['name']) ?></h2>
        <div class="flex items-center mb-2">
          <span class="capitalize text-sm text-blue-600"><?= htmlspecialchars($part['category_name'] ?? 'Unknown') ?></span>
          <?php if ($part['status'] === 'active'): ?>
            <span class="ml-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-medium">Active</span>
          <?php else: ?>
            <span class="ml-2 bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full font-medium">Hidden</span>
          <?php endif; ?>
        </div>
        
        <div class="flex items-center mb-4">
          <span class="text-3xl font-bold text-gray-800">$<?= number_format($part['price'], 2) ?></span>
          <span class="ml-4 text-sm text-gray-500">Stock: <?= $part['stock_quantity'] ?></span>
        </div>
        
        <div class="mb-4">
          <strong class="text-gray-700">Seller:</strong>
          <span class="text-blue-600"><?= htmlspecialchars($part['seller_name'] ?? 'Unknown') ?></span>
        </div>
        
        <div class="mb-4">
          <strong class="text-gray-700">Description:</strong>
          <p class="text-gray-600 mt-1"><?= nl2br(htmlspecialchars($part['description'] ?? '')) ?></p>
        </div>
        
        <div class="flex space-x-2">
          <a href="edit_part.php?id=<?= $part['id'] ?>" 
             class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center">
            <i class="fas fa-edit mr-2"></i> Edit Part
          </a>
          <a href="manage_parts.php" 
             class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Reviews Section -->
  <div class="bg-white rounded-xl shadow-md p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Reviews & Ratings</h2>
    
    <!-- Average Rating -->
    <div class="mb-6">
      <div class="flex items-center">
        <div class="flex items-center">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="<?= $i <= $average_rating ? 'fas' : 'far' ?> fa-star text-yellow-400 text-lg"></i>
          <?php endfor; ?>
        </div>
        <span class="ml-2 text-lg font-semibold text-gray-700"><?= $average_rating ?>/5</span>
        <span class="ml-2 text-sm text-gray-500">(<?= $total_ratings ?> review<?= $total_ratings !== 1 ? 's' : '' ?>)</span>
      </div>
    </div>

    <!-- Individual Reviews -->
    <?php if (empty($reviews)): ?>
      <div class="text-center py-8 text-gray-500">
        <i class="fas fa-comment-alt text-4xl mb-2"></i>
        <p>No reviews yet for this part.</p>
      </div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($reviews as $review): ?>
          <div class="border-b pb-4 last:border-b-0">
            <div class="flex items-start justify-between">
              <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-semibold">
                  <?= substr($review['reviewer_name'], 0, 1) ?>
                </div>
                <div class="ml-3">
                  <div class="font-semibold text-gray-800"><?= htmlspecialchars($review['reviewer_name']) ?></div>
                  <div class="text-sm text-gray-500"><?= htmlspecialchars($review['reviewer_role']) ?></div>
                </div>
              </div>
              <div class="flex items-center">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star text-yellow-400 text-sm"></i>
                <?php endfor; ?>
                <span class="ml-2 text-sm text-gray-500"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
              </div>
            </div>
            <p class="mt-2 text-gray-700"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php include 'includes/admin_footer.php'; ?>
</body>
</html>