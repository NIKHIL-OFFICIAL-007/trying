<?php
session_start();
include '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: ../logout.php");
    exit();
}

// Fetch seller applications for this user
$stmt = $pdo->prepare("SELECT * FROM seller_applications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get message from URL parameters
$message = '';
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'seller_application_submitted':
            $message = 'Your seller application has been submitted successfully. Our team will review it within 3-5 business days.';
            break;
        case 'already_approved':
            $message = 'You are already an approved seller.';
            break;
        case 'pending_application_exists':
            $message = 'You already have a pending seller application.';
            break;
        default:
            $message = '';
    }
}

// Get error from URL parameters
$error = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'missing_required_fields':
            $error = 'Please fill in all required fields.';
            break;
        case 'invalid_file_type':
            $error = 'Invalid file type. Please upload JPG, PNG, or PDF.';
            break;
        case 'file_too_large':
            $error = 'File is too large. Maximum size is 5MB.';
            break;
        case 'upload_failed':
            $error = 'File upload failed. Please try again.';
            break;
        case 'application_failed':
            $error = 'Application submission failed. Please try again.';
            break;
        default:
            $error = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Requests - AutoParts Hub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f9fafb;
      color: #1f2937;
    }
  </style>
</head>
<body class="min-h-screen bg-gray-50">
  <?php include '../includes/header.php'; ?>

  <div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Page Header -->
    <div class="text-center mb-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-2">My Seller Applications</h1>
      <p class="text-lg text-gray-600">Track the status of your seller applications</p>
    </div>

    <!-- Success Message -->
    <?php if ($message): ?>
      <div class="mb-8 p-4 bg-green-100 border-l-4 border-green-500 text-green-800 rounded-lg">
        <div class="flex">
          <div class="flex-shrink-0">
            <i class="fas fa-check-circle text-green-500 mt-0.5"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium"><?= htmlspecialchars($message) ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if ($error): ?>
      <div class="mb-8 p-4 bg-red-100 border-l-4 border-red-500 text-red-800 rounded-lg">
        <div class="flex">
          <div class="flex-shrink-0">
            <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium"><?= htmlspecialchars($error) ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- No Applications Message -->
    <?php if (empty($applications)): ?>
      <div class="bg-white rounded-xl shadow-lg p-8 text-center">
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-file-alt text-2xl text-blue-600"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-800 mb-2">No Applications Found</h3>
        <p class="text-gray-600 mb-6">You haven't submitted any seller applications yet.</p>
        <a href="apply_seller.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition">
          Apply as Seller
        </a>
      </div>
    <?php else: ?>
      <!-- Applications List -->
      <div class="space-y-6">
        <?php foreach ($applications as $app): ?>
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-gray-800 mb-1">Application #<?= $app['id'] ?></h3>
                <p class="text-gray-600">Submitted on <?= date('M j, Y', strtotime($app['created_at'])) ?></p>
              </div>
              <div class="mt-3 md:mt-0">
                <?php if ($app['status'] === 'pending'): ?>
                  <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">Pending Review</span>
                <?php elseif ($app['status'] === 'approved'): ?>
                  <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Approved</span>
                <?php else: ?>
                  <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">Rejected</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <div>
                <p class="text-sm font-medium text-gray-500">Full Name</p>
                <p class="text-gray-800"><?= htmlspecialchars($app['name']) ?></p>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-500">Phone</p>
                <p class="text-gray-800"><?= htmlspecialchars($app['phone']) ?></p>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-500">Business Address</p>
                <p class="text-gray-800"><?= nl2br(htmlspecialchars($app['business_address'])) ?></p>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-500">Website</p>
                <p class="text-gray-800">
                  <?php if ($app['website']): ?>
                    <a href="<?= htmlspecialchars($app['website']) ?>" target="_blank" class="text-blue-600 hover:underline">
                      <?= htmlspecialchars($app['website']) ?>
                    </a>
                  <?php else: ?>
                    Not provided
                  <?php endif; ?>
                </p>
              </div>
            </div>

            <div class="mb-6">
              <p class="text-sm font-medium text-gray-500 mb-2">Reason for Applying</p>
              <p class="text-gray-800 bg-gray-50 p-3 rounded-lg"><?= htmlspecialchars($app['role_reason']) ?></p>
            </div>

            <?php if (!empty($app['additional_info'])): ?>
              <div class="mb-6">
                <p class="text-sm font-medium text-gray-500 mb-2">Additional Information</p>
                <p class="text-gray-800 bg-gray-50 p-3 rounded-lg"><?= htmlspecialchars($app['additional_info']) ?></p>
              </div>
            <?php endif; ?>

            <?php if (!empty($app['business_license'])): ?>
              <div class="mb-6">
                <p class="text-sm font-medium text-gray-500 mb-2">Business License</p>
                <a href="../uploads/<?= htmlspecialchars($app['business_license']) ?>" target="_blank" class="inline-flex items-center text-blue-600 hover:underline">
                  <i class="fas fa-file-alt mr-2"></i>
                  View Document
                </a>
              </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row sm:justify-end space-y-3 sm:space-y-0 sm:space-x-3">
              <?php if ($app['status'] === 'pending'): ?>
                <div class="text-sm text-gray-600">
                  <i class="fas fa-clock mr-2"></i>
                  Your application is being reviewed. You'll receive an email notification once a decision has been made.
                </div>
              <?php elseif ($app['status'] === 'approved'): ?>
                <div class="text-sm text-green-600">
                  <i class="fas fa-check-circle mr-2"></i>
                  Congratulations! Your application has been approved.
                </div>
              <?php else: ?>
                <div class="text-sm text-red-600">
                  <i class="fas fa-times-circle mr-2"></i>
                  Your application has been rejected.
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Apply Again Button (if no pending applications) -->
      <?php 
      $has_pending = false;
      foreach ($applications as $app) {
          if ($app['status'] === 'pending') {
              $has_pending = true;
              break;
          }
      }
      ?>
      
      <?php if (!$has_pending): ?>
        <div class="mt-8 text-center">
          <a href="apply_seller.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition inline-flex items-center">
            <i class="fas fa-plus mr-2"></i>
            Submit New Application
          </a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php include '../includes/footer.php'; ?>
</body>
</html>