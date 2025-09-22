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

// Fetch all parts from database with category names
$parts = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, c.name as category_name, p.price, p.stock_quantity as stock, p.image_url, p.status, p.description, p.category_id, p.seller_id
        FROM parts p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch parts: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Parts - Admin Panel</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Page Header -->
  <div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-800">Manage Parts</h1>
    <p class="text-gray-600 mt-1">Add, edit, hide, or delete vehicle parts from the marketplace.</p>
  </div>

  <!-- Parts Grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($parts)): ?>
      <div class="col-span-full text-center py-12">
        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-medium text-gray-500">No parts found</h3>
        <p class="text-gray-400 mt-2">We're working on adding more parts soon.</p>
      </div>
    <?php else: ?>
      <?php foreach ($parts as $part): ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition transform hover:-translate-y-1">
          <!-- Status Badge -->
          <div class="absolute top-2 right-2 z-10">
            <?php if ($part['status'] === 'active'): ?>
              <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-medium">Active</span>
            <?php else: ?>
              <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full font-medium">Hidden</span>
            <?php endif; ?>
          </div>
          
          <!-- Image -->
          <div class="relative h-48 bg-gray-100">
            <?php if ($part['image_url']): ?>
              <img src="<?= htmlspecialchars($part['image_url']) ?>" alt="<?= htmlspecialchars($part['name']) ?>"
                   class="w-full h-full object-cover">
            <?php else: ?>
              <div class="w-full h-full flex items-center justify-center">
                <i class="fas fa-car text-gray-400 text-4xl"></i>
              </div>
            <?php endif; ?>
          </div>

          <!-- Content -->
          <div class="p-5">
            <h3 class="font-semibold text-gray-800 mb-1"><?= htmlspecialchars($part['name']) ?></h3>
            <span class="capitalize text-sm text-blue-600 mb-2"><?= htmlspecialchars($part['category_name'] ?? 'Unknown') ?></span>
            <div class="flex items-center justify-between mt-2">
              <span class="text-2xl font-bold text-gray-800">$<?= number_format($part['price'], 2) ?></span>
              <span class="text-sm text-gray-500">Stock: <?= $part['stock'] ?></span>
            </div>

            <!-- Actions -->
            <div class="flex space-x-2 mt-4">
              <!-- View Details Button -->
              <a href="view_part_details.php?id=<?= $part['id'] ?>" 
                 class="text-blue-600 hover:text-blue-800 text-sm flex items-center bg-blue-50 px-3 py-1 rounded"
                 title="View part details, reviews, and ratings">
                <i class="fas fa-eye mr-1"></i> View Details
              </a>
              
              <!-- Hide/Show Button -->
              <?php if ($part['status'] === 'active'): ?>
                <a href="hide_part.php?id=<?= $part['id'] ?>" 
                   class="text-yellow-600 hover:text-yellow-800 text-sm flex items-center bg-yellow-50 px-3 py-1 rounded"
                   onclick="return confirm('Are you sure you want to hide this part from users?')">
                  <i class="fas fa-eye-slash mr-1"></i> Hide
                </a>
              <?php else: ?>
                <a href="show_part.php?id=<?= $part['id'] ?>" 
                   class="text-green-600 hover:text-green-800 text-sm flex items-center bg-green-50 px-3 py-1 rounded"
                   onclick="return confirm('Are you sure you want to make this part visible to users?')">
                  <i class="fas fa-eye mr-1"></i> Show
                </a>
              <?php endif; ?>
              
              <!-- Delete Button -->
              <a href="delete_part.php?id=<?= $part['id'] ?>" 
                 class="text-red-600 hover:text-red-800 text-sm flex items-center bg-red-50 px-3 py-1 rounded"
                 onclick="return confirm('Are you sure you want to permanently delete this part? This action cannot be undone.')">
                <i class="fas fa-trash mr-1"></i> Delete
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php include 'includes/admin_footer.php'; ?>
</body>
</html>