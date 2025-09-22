<?php
session_start();
include 'includes/config.php';

// Check if user is logged in and has admin role
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

// Fetch ALL seller applications
$applications = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            sa.id,
            sa.user_id,
            sa.name,
            sa.phone,
            sa.business_address,
            sa.website,
            sa.business_license,
            sa.role_reason,
            sa.additional_info,
            sa.status,
            sa.created_at,
            u.email
        FROM seller_applications sa
        JOIN users u ON sa.user_id = u.id
        ORDER BY sa.created_at DESC
    ");
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch seller applications: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Seller Applications - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
    .status-badge {
      padding: 0.35rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .action-btn {
      padding: 0.375rem 0.75rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      font-weight: 500;
      transition: all 0.2s ease-in-out;
    }
    .action-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Main Content Container -->
  <div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Page Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-800 mb-2">Seller Applications</h1>
          <p class="text-gray-600">Review and manage seller applications</p>
        </div>
        <div class="flex space-x-3">
          <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Total Applications:</span>
            <span class="ml-1 font-bold text-gray-800"><?= count($applications) ?></span>
          </div>
          <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Pending:</span>
            <span class="ml-1 font-bold text-orange-600">
              <?= count(array_filter($applications, fn($app) => $app['status'] === 'pending')) ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_GET['message']) && $_GET['message'] === 'application_approved'): ?>
      <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-800 rounded-lg text-sm flex items-center">
        <i class="fas fa-check-circle mr-3 text-green-500"></i>
        <div>
          <p class="font-medium">Seller application approved successfully.</p>
          <p class="text-sm">The user has been notified and can now access seller features.</p>
        </div>
      </div>
    <?php elseif (isset($_GET['message']) && $_GET['message'] === 'application_rejected'): ?>
      <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-800 rounded-lg text-sm flex items-center">
        <i class="fas fa-times-circle mr-3 text-red-500"></i>
        <div>
          <p class="font-medium">Seller application rejected.</p>
          <p class="text-sm">The user has been notified of the rejection.</p>
        </div>
      </div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'action_failed'): ?>
      <div class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 rounded-lg text-sm flex items-center">
        <i class="fas fa-exclamation-triangle mr-3 text-yellow-500"></i>
        <div>
          <p class="font-medium">Failed to process request.</p>
          <p class="text-sm">Please try again or contact support if the issue persists.</p>
        </div>
      </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="mb-6 border-b border-gray-200">
      <nav class="-mb-px flex space-x-8">
        <a href="#" class="border-b-2 border-blue-500 py-4 px-1 text-sm font-medium text-blue-600 whitespace-nowrap">All Applications</a>
        <a href="#" class="border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap">Pending</a>
        <a href="#" class="border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap">Approved</a>
        <a href="#" class="border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap">Rejected</a>
      </nav>
    </div>

    <!-- Applications Cards -->
    <?php if (empty($applications)): ?>
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
          <i class="fas fa-user-plus text-3xl text-gray-400"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-800 mb-2">No Applications Found</h3>
        <p class="text-gray-600">There are no seller applications to display at this time.</p>
      </div>
    <?php else: ?>
      <div class="grid gap-6">
        <?php foreach ($applications as $app): ?>
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200">
            <div class="p-6">
              <!-- Header Row -->
              <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                <div class="flex items-center">
                  <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <span class="text-blue-600 font-bold text-lg"><?= strtoupper(substr($app['name'], 0, 1)) ?></span>
                  </div>
                  <div>
                    <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($app['name']) ?></h3>
                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($app['email']) ?></p>
                  </div>
                </div>
                
                <div class="flex items-center space-x-3 mt-3 md:mt-0">
                  <span class="text-sm text-gray-500">Applied: <?= date('M j, Y', strtotime($app['created_at'])) ?></span>
                  
                  <?php if ($app['status'] === 'approved'): ?>
                    <span class="status-badge bg-green-100 text-green-800">Approved</span>
                  <?php elseif ($app['status'] === 'rejected'): ?>
                    <span class="status-badge bg-red-100 text-red-800">Rejected</span>
                  <?php else: ?>
                    <span class="status-badge bg-orange-100 text-orange-800">Pending</span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Application Details -->
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <div class="bg-gray-50 p-3 rounded-lg">
                  <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Full Name</p>
                  <p class="text-sm text-gray-800 mt-1"><?= htmlspecialchars($app['name']) ?></p>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-lg">
                  <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Phone</p>
                  <p class="text-sm text-gray-800 mt-1"><?= htmlspecialchars($app['phone']) ?></p>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-lg">
                  <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status</p>
                  <p class="text-sm text-gray-800 mt-1 capitalize"><?= htmlspecialchars($app['status']) ?></p>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-lg md:col-span-2 lg:col-span-1">
                  <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Website</p>
                  <p class="text-sm text-blue-600 mt-1">
                    <?php if ($app['website']): ?>
                      <a href="<?= htmlspecialchars($app['website']) ?>" target="_blank" class="hover:underline">
                        <?= htmlspecialchars($app['website']) ?>
                      </a>
                    <?php else: ?>
                      Not provided
                    <?php endif; ?>
                  </p>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-lg md:col-span-2">
                  <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Business Address</p>
                  <p class="text-sm text-gray-800 mt-1"><?= nl2br(htmlspecialchars($app['business_address'])) ?></p>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-lg">
                  <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">License</p>
                  <p class="text-sm text-gray-800 mt-1">
                    <?php if ($app['business_license']): ?>
                      <a href="../uploads/<?= htmlspecialchars($app['business_license']) ?>" target="_blank" class="text-green-600 hover:underline flex items-center">
                        <i class="fas fa-file mr-1"></i> View Document
                      </a>
                    <?php else: ?>
                      Not uploaded
                    <?php endif; ?>
                  </p>
                </div>
              </div>

              <!-- Reason -->
              <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg mb-6">
                <p class="text-xs font-medium text-blue-800 uppercase tracking-wide mb-2">Application Reason</p>
                <p class="text-sm text-blue-900"><?= htmlspecialchars($app['role_reason']) ?></p>
              </div>

              <!-- Additional Information -->
              <?php if (!empty($app['additional_info'])): ?>
                <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded-r-lg mb-6">
                  <p class="text-xs font-medium text-purple-800 uppercase tracking-wide mb-2">Additional Information</p>
                  <p class="text-sm text-purple-900"><?= htmlspecialchars($app['additional_info']) ?></p>
                </div>
              <?php endif; ?>

              <!-- Action Buttons -->
              <?php if ($app['status'] === 'pending'): ?>
                <div class="flex flex-col sm:flex-row sm:justify-end space-y-3 sm:space-y-0 sm:space-x-3">
                  <form method="POST" action="approve_seller.php" class="inline" onsubmit="return confirm('Are you sure you want to approve this application? This action cannot be undone.')">
                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="action-btn bg-green-600 hover:bg-green-700 text-white">
                      <i class="fas fa-check mr-1"></i> Approve Application
                    </button>
                  </form>
                  
                  <form method="POST" action="approve_seller.php" class="inline" onsubmit="return confirm('Are you sure you want to reject this application? This action cannot be undone.')">
                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="action-btn bg-red-600 hover:bg-red-700 text-white">
                      <i class="fas fa-times mr-1"></i> Reject Application
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <div class="text-right">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                    <i class="fas fa-lock mr-2"></i>
                    <?= ucfirst($app['status']) ?> - No further action required
                  </span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php include 'includes/admin_footer.php'; ?>


</body>
</html>