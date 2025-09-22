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

// Fetch ALL parts sold by this seller (with category and image)
$parts = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name as part_name, c.name as category_name, p.image_url
        FROM parts p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.seller_id = ? AND p.status = 'active'
        ORDER BY p.name
    ");
    $stmt->execute([$user_id]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch parts: " . $e->getMessage());
}

// Initialize reviews_by_part with ALL parts (even those with 0 reviews)
$reviews_by_part = [];
foreach ($parts as $part) {
    $reviews_by_part[$part['id']] = [
        'part' => $part['part_name'],
        'category' => $part['category_name'] ?? 'Uncategorized',
        'image_url' => $part['image_url'] ?? null,
        'reviews' => [],
        'avg_rating' => 0,
        'review_count' => 0
    ];
}

// Fetch reviews for seller's parts
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at, u.name as buyer_name, p.name as part_name, p.id as part_id
        FROM reviews r
        JOIN parts p ON r.part_id = p.id
        JOIN users u ON r.buyer_id = u.id
        WHERE p.seller_id = ? AND r.status = 'active'
        ORDER BY p.name, r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $all_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group reviews by part_id
    foreach ($all_reviews as $review) {
        $part_id = $review['part_id'];
        if (isset($reviews_by_part[$part_id])) {
            $reviews_by_part[$part_id]['reviews'][] = $review;
        }
    }

    // Calculate average rating for each part
    foreach ($reviews_by_part as &$part_data) {
        $ratings = array_column($part_data['reviews'], 'rating');
        $part_data['avg_rating'] = count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : 0;
        $part_data['review_count'] = count($ratings);
    }
} catch (Exception $e) {
    error_log("Failed to fetch reviews: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reviews - Seller Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/seller_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Product Reviews</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">See customer feedback for each of your parts.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Customer Reviews by Product</h2>
        <p class="text-gray-600 mt-1">Review feedback and ratings for each part you sell — even those with no reviews yet.</p>
      </div>
      
      <div class="p-6">
        <?php if (empty($parts)): ?>
          <div class="text-center py-12">
            <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-500">No products listed</h3>
            <p class="text-gray-400 mt-2">You haven't listed any parts for sale yet.</p>
          </div>
        <?php else: ?>
          <div class="space-y-8">
            <?php foreach ($reviews_by_part as $part_id => $part_data): ?>
              <div class="border border-gray-200 rounded-lg overflow-hidden">
                <!-- Product Header with Image -->
                <div class="flex flex-col md:flex-row">
                  <!-- Product Image -->
                  <div class="md:w-1/4 bg-gray-100 flex items-center justify-center p-4 md:p-6">
                    <?php if ($part_data['image_url']): ?>
                      <img src="<?= htmlspecialchars($part_data['image_url']) ?>" 
                           alt="<?= htmlspecialchars($part_data['part']) ?>"
                           class="max-h-40 object-contain rounded">
                    <?php else: ?>
                      <div class="w-24 h-24 flex items-center justify-center bg-gray-200 rounded">
                        <i class="fas fa-image text-gray-400 text-3xl"></i>
                      </div>
                    <?php endif; ?>
                  </div>
                  
                  <!-- Product Info & Rating -->
                  <div class="md:w-3/4 bg-gray-50 px-6 py-4 flex flex-col justify-center">
                    <div class="flex items-start justify-between">
                      <div>
                        <h3 class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($part_data['part']) ?></h3>
                        <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($part_data['category']) ?></p>
                      </div>
                      
                      <div class="text-right">
                        <div class="flex items-center">
                          <div class="flex">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                              <i class="fas fa-star <?= $i <= $part_data['avg_rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                            <?php endfor; ?>
                          </div>
                          <span class="ml-2 text-sm font-medium text-gray-700">
                            <?= number_format($part_data['avg_rating'], 1) ?> (<?= $part_data['review_count'] ?>)
                          </span>
                        </div>
                        <?php if ($part_data['review_count'] === 0): ?>
                          <p class="text-xs text-gray-500 mt-1">No reviews yet</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Reviews Section (Visible by Default) -->
                <div class="p-6 border-t border-gray-200">
                  <?php if (empty($part_data['reviews'])): ?>
                    <p class="text-gray-500 italic text-center py-4">No reviews for this part yet.</p>
                  <?php else: ?>
                    <div class="space-y-4">
                      <?php foreach ($part_data['reviews'] as $review): ?>
                        <div class="border-l-4 border-blue-500 pl-4 py-3 bg-gray-50 rounded">
                          <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                              <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-xs font-medium text-gray-700 mr-2">
                                <?= substr($review['buyer_name'], 0, 1) ?>
                              </div>
                              <span class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($review['buyer_name']) ?></span>
                            </div>
                            <div class="flex">
                              <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                              <?php endfor; ?>
                            </div>
                          </div>
                          <p class="text-gray-700 text-sm mb-1"><?= htmlspecialchars($review['comment']) ?></p>
                          <div class="text-xs text-gray-500">
                            <?= date('M j, Y', strtotime($review['created_at'])) ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'includes/seller_footer.php'; ?>
</body>
</html>