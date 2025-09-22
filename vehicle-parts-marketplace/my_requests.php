<?php
session_start();
include '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name']);
$user_role = $_SESSION['role'] ?? '';

// Fetch role applications
$applications = [];
try {
    $stmt = $pdo->prepare("
        SELECT requested_role, reason, status, created_at, additional_info 
        FROM role_applications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading applications.";
}

// Handle success message
$message = '';
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'support_application_submitted') {
        $message = "Your application for the Support role has been submitted successfully. We'll review it shortly.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - AutoParts Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; }
        .status-pending { @apply bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-medium; }
        .status-approved { @apply bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium; }
        .status-rejected { @apply bg-red-100 text-red-800 px-3 py-1 rounded-full text-xs font-medium; }
        .card-hover:hover { @apply shadow-lg transform transition duration-300 scale-[1.01]; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-10 max-w-4xl">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6 text-center">
                <h1 class="text-2xl md:text-3xl font-bold">My Role Applications</h1>
                <p class="text-blue-100">Welcome back, <?php echo $user_name; ?>! Here are your submitted requests.</p>
            </div>

            <!-- Success Message -->
            <?php if ($message): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mx-6 mt-6 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-3 text-lg"></i>
                        <p class="text-green-800"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mx-6 mt-6 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-600 mr-3 text-lg"></i>
                        <p class="text-red-800"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Applications List -->
            <div class="p-6">
                <?php if (empty($applications)): ?>
                    <div class="text-center py-10">
                        <i class="fas fa-inbox text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-500 text-lg">You haven't applied for any roles yet.</p>
                        <a href="apply_support.php" class="mt-4 inline-block px-6 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition">
                            Apply Now
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($applications as $app): 
                            $info = json_decode($app['additional_info'], true) ?: [];
                        ?>
                            <div class="border border-gray-200 rounded-lg p-5 card-hover">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="font-semibold text-lg text-gray-800 capitalize">
                                        <i class="fas fa-user-shield text-teal-600 mr-2"></i>
                                        <?php echo htmlspecialchars($app['requested_role']); ?> Role
                                    </h3>
                                    <span class="
                                        <?php echo $app['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                               ($app['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>
                                        px-3 py-1 rounded-full text-xs font-medium
                                    ">
                                        <?php echo ucfirst(htmlspecialchars($app['status'])); ?>
                                    </span>
                                </div>

                                <p class="text-gray-600 text-sm mb-4">
                                    <strong>Applied on:</strong> <?php echo (new DateTime($app['created_at']))->format('F j, Y \a\t g:i A'); ?>
                                </p>

                                <div class="bg-gray-50 p-4 rounded-lg mb-4 text-sm">
                                    <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($app['reason'])); ?></p>
                                    <?php if (!empty($info)): ?>
                                        <div class="mt-3 pt-3 border-t border-gray-200 text-gray-700 space-y-1">
                                            <?php if (!empty($info['experience'])): ?>
                                                <p><strong>Experience:</strong> <?php echo htmlspecialchars($info['experience']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($info['skills'])): ?>
                                                <p><strong>Skills:</strong> <?php echo htmlspecialchars($info['skills']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($info['availability'])): ?>
                                                <p><strong>Availability:</strong> <?php echo htmlspecialchars($info['availability']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($info['languages'])): ?>
                                                <p><strong>Languages:</strong> <?php echo htmlspecialchars($info['languages']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($app['status'] === 'pending'): ?>
                                    <div class="text-sm text-yellow-700 bg-yellow-50 p-3 rounded border border-yellow-200">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Your application is under review. You will be notified once a decision is made.
                                    </div>
                                <?php elseif ($app['status'] === 'approved'): ?>
                                    <div class="bg-green-100 border-l-4 border-green-500 p-4 rounded">
                                        <div class="flex items-center">
                                            <i class="fas fa-check-circle text-green-600 mr-3 text-lg"></i>
                                            <p class="text-green-800 font-medium">
                                                Congratulations! You've been approved as a Support member.<br>
                                                <a href="/vehicle-parts-marketplace/support/dashboard.php" class="underline font-semibold hover:text-green-700">Go to Dashboard</a>
                                            </p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-sm text-red-700 bg-red-50 p-3 rounded border border-red-200">
                                        <i class="fas fa-times-circle mr-2"></i>
                                        Your application was not approved. You can <a href="apply_support.php" class="underline">apply again</a>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Back Button -->
            <div class="p-6 bg-gray-50 border-t flex justify-center">
                <a href="../index.php" class="inline-flex items-center px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Home
                </a>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>