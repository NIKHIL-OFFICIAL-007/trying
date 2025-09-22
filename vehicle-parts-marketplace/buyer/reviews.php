<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
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

// Fetch reviews by this buyer
$reviews = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at, 
               p.name as part_name, p.image_url, p.id as part_id
        FROM reviews r
        JOIN parts p ON r.part_id = p.id  -- ✅ Fixed: 'product_id' → 'part_id'
        WHERE r.buyer_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch reviews: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Reviews - Buyer Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">My Reviews</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">See your feedback on purchased parts.</p>
    </div>
  </div>

  <!-- Main Content -->
  
  <div class="container mx-auto px-6 py-8">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Your Reviews</h2>
        <p class="text-gray-600 mt-1">View your feedback on purchased parts.</p>
      </div>
      
      <div class="p-6">
        <?php if (empty($reviews)): ?>
          <div class="text-center py-12">
            <i class="fas fa-star-half-alt text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-500">No reviews yet</h3>
            <p class="text-gray-400 mt-2">Leave a review after purchasing and using parts.</p>
          </div>
        <?php else: ?>
          <div class="space-y-6">
            <?php foreach ($reviews as $review): ?>
              <div class="border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center">
                    <?php if ($review['image_url']): ?>
                      <img src="<?= htmlspecialchars($review['image_url']) ?>" alt="<?= htmlspecialchars($review['part_name']) ?>" class="w-10 h-10 rounded object-cover">
                    <?php else: ?>
                      <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center">
                        <i class="fas fa-cog text-gray-400"></i>
                      </div>
                    <?php endif; ?>
                    <div class="ml-3">
                      <div class="font-medium text-gray-800"><?= htmlspecialchars($review['part_name']) ?></div>
                      <div class="text-sm text-gray-500"><?= date('M j, Y', strtotime($review['created_at'])) ?></div>
                    </div>
                  </div>
                  <div class="flex items-center">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                    <?php endfor; ?>
                  </div>
                </div>
                
                <p class="text-gray-700 mb-4"><?= htmlspecialchars($review['comment']) ?></p>
                
                <div class="flex justify-end">
                  <a href="view_part.php?id=<?= $review['part_id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-eye mr-1"></i> View Part
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>