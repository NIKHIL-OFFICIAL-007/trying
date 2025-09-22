<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'includes/config.php';

// Fetch user data with all fields from the database
$stmt = $pdo->prepare("SELECT id, name, email, role, role_status, role_request, role_reason, additional_info, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $additional_info = trim($_POST['additional_info'] ?? '');

    // Validate inputs
    if (empty($name) || !$email) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Name and email are required.</div>";
    } elseif ($new_password && strlen($new_password) < 6) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> New password must be at least 6 characters.</div>";
    } elseif ($new_password && $new_password !== $confirm_password) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> New passwords do not match.</div>";
    } else {
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Email is already in use by another account.</div>";
        } else {
            // Verify current password if changing password
            if ($new_password) {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $hash = $stmt->fetchColumn();

                if (!password_verify($current_password, $hash)) {
                    $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Current password is incorrect.</div>";
                }
            }

            // If no errors, update user
            if (!$message) {
                try {
                    if ($new_password) {
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, additional_info = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $hashed, $additional_info, $_SESSION['user_id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, additional_info = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $additional_info, $_SESSION['user_id']]);
                    }

                    // Update session
                    $_SESSION['name'] = htmlspecialchars($name);
                    $_SESSION['email'] = $email;

                    $message = "<div class='alert alert-success'><i class='fas fa-check-circle mr-2'></i> Profile updated successfully!</div>";
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT id, name, email, role, role_status, role_request, role_reason, additional_info, created_at FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    
                } catch (Exception $e) {
                    error_log("Profile update failed: " . $e->getMessage());
                    $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Update failed. Try again.</div>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Profile | AutoParts Hub</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root {
      --primary: #4361ee;
      --primary-dark: #3a56d4;
      --secondary: #6c757d;
      --light: #f8f9fa;
      --dark: #212529;
      --success: #28a745;
      --danger: #dc3545;
      --warning: #ffc107;
    }
    
    body { 
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
    }
    
    .card {
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
    }
    
    .input-group {
      position: relative;
      margin-bottom: 1.5rem;
    }
    
    .input-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: #374151;
      transition: color 0.2s ease;
    }
    
    .input-group input:not([disabled]), .input-group textarea:not([disabled]) {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 2px solid #e5e7eb;
      border-radius: 10px;
      transition: all 0.3s ease;
      font-size: 0.95rem;
    }
    
    .input-group input:not([disabled]):focus, .input-group textarea:not([disabled]):focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
    }
    
    .input-group input[disabled], .input-group textarea[disabled] {
      width: 100%;
      padding: 0.75rem 1rem;
      background-color: #f9fafb;
      border: 2px solid #e5e7eb;
      border-radius: 10px;
      color: #6b7280;
      cursor: not-allowed;
    }
    
    .btn-primary {
      background: linear-gradient(to right, var(--primary), var(--primary-dark));
      color: white;
      padding: 0.75rem 1.5rem;
      border-radius: 10px;
      font-weight: 500;
      transition: all 0.3s ease;
      box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2);
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 7px 14px rgba(67, 97, 238, 0.3);
    }
    
    .btn-secondary {
      background: #f1f5f9;
      color: #64748b;
      padding: 0.75rem 1.5rem;
      border-radius: 10px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-secondary:hover {
      background: #e2e8f0;
      transform: translateY(-2px);
    }
    
    .alert {
      padding: 1rem 1.25rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
    }
    
    .alert-success {
      background-color: #f0fdf4;
      color: #166534;
      border-left: 4px solid #22c55e;
    }
    
    .alert-error {
      background-color: #fef2f2;
      color: #dc2626;
      border-left: 4px solid #ef4444;
    }
    
    .header-gradient {
      background: linear-gradient(135deg, var(--primary) 0%, #5e72e4 100%);
    }
    
    .password-toggle {
      position: absolute;
      right: 12px;
      top: 38px;
      color: #9ca3af;
      cursor: pointer;
      transition: color 0.2s ease;
    }
    
    .password-toggle:hover {
      color: var(--primary);
    }
    
    .section-title {
      position: relative;
      padding-left: 1.25rem;
      margin-bottom: 1.5rem;
    }
    
    .section-title:before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      height: 20px;
      width: 4px;
      background: linear-gradient(to bottom, var(--primary), var(--primary-dark));
      border-radius: 4px;
    }
    
    .back-link {
      display: inline-flex;
      align-items: center;
      color: var(--primary);
      font-weight: 500;
      transition: color 0.2s ease;
    }
    
    .back-link:hover {
      color: var(--primary-dark);
    }
    
    .status-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .status-approved {
      background-color: #def7ec;
      color: #03543f;
    }
    
    .status-pending {
      background-color: #fdf6b2;
      color: #723b13;
    }
    
    .error-text {
      color: #dc2626;
      font-size: 0.875rem;
      margin-top: 0.25rem;
      display: none;
    }
    
    .input-error {
      border-color: #dc2626 !important;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15) !important;
    }
  </style>
</head>
<body class="min-h-screen py-10 px-4">
  <div class="container mx-auto max-w-3xl">

    <div class="card bg-white overflow-hidden">
      <!-- Header -->
      <div class="header-gradient text-white py-8 px-8 text-center">
        <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-white/20 flex items-center justify-center">
          <i class="fas fa-user-edit text-3xl"></i>
        </div>
        <h1 class="text-2xl font-bold mb-2">Edit Your Profile</h1>
        <p class="opacity-90">Update your personal information and preferences</p>
      </div>

      <!-- Alert -->
      <?php if ($message): ?>
        <div class="mx-6 mt-6"><?= $message ?></div>
      <?php endif; ?>

      <!-- Profile Form -->
      <form method="POST" class="p-8 space-y-6" id="profileForm">
        <!-- Personal Information Section -->
        <div>
          <h3 class="section-title text-lg font-semibold text-gray-800">Personal Information</h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Name -->
            <div class="input-group">
              <label for="name"><i class="fas fa-user text-blue-500 mr-1"></i> Full Name</label>
              <input type="text" name="name" id="name" required
                     value="<?= htmlspecialchars($user['name']) ?>">
            </div>

            <!-- Email -->
            <div class="input-group">
              <label for="email"><i class="fas fa-envelope text-blue-500 mr-1"></i> Email Address</label>
              <input type="email" name="email" id="email" required
                     value="<?= htmlspecialchars($user['email']) ?>">
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Role (Read-only) -->
            <div class="input-group">
              <label><i class="fas fa-user-tag text-blue-500 mr-1"></i> Account Role</label>
              <input type="text" value="<?= ucfirst($user['role'] ?? 'buyer') ?>" disabled>
            </div>

            <!-- Role Status -->
            <div class="input-group">
              <label><i class="fas fa-badge-check text-blue-500 mr-1"></i> Role Status</label>
              <div class="flex items-center h-12">
                <span class="status-badge <?= $user['role_status'] === 'approved' ? 'status-approved' : 'status-pending' ?>">
                  <?= ucfirst($user['role_status'] ?? 'approved') ?>
                </span>
              </div>
            </div>
          </div>
          
          <!-- Additional Info -->
          <div class="input-group">
            <label for="additional_info"><i class="fas fa-info-circle text-blue-500 mr-1"></i> Additional Information</label>
            <textarea name="additional_info" id="additional_info" rows="3" placeholder="Any additional information about yourself"><?= htmlspecialchars($user['additional_info'] ?? '') ?></textarea>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Member Since (Read-only) -->
            <div class="input-group">
              <label><i class="fas fa-calendar-alt text-blue-500 mr-1"></i> Member Since</label>
              <input type="text" value="<?= date('M d, Y', strtotime($user['created_at'])) ?>" disabled>
            </div>
            
            <!-- Role Request (if any) -->
            <?php if (!empty($user['role_request'])): ?>
            <div class="input-group">
              <label><i class="fas fa-hourglass-half text-blue-500 mr-1"></i> Pending Request</label>
              <input type="text" value="Requesting: <?= ucfirst($user['role_request']) ?>" disabled>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Change Password Section -->
        <div>
          <h3 class="section-title text-lg font-semibold text-gray-800">Change Password</h3>
          <p class="text-sm text-gray-500 mb-4 -mt-2">Leave these fields blank if you don't want to change your password</p>
          
          <!-- Current Password -->
          <div class="input-group">
            <label for="current_password"><i class="fas fa-lock text-blue-500 mr-1"></i> Current Password</label>
            <input type="password" name="current_password" id="current_password"
                   placeholder="Enter your current password">
            <span class="password-toggle" onclick="togglePassword('current_password')">
              <i class="fas fa-eye"></i>
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- New Password -->
            <div class="input-group">
              <label for="new_password"><i class="fas fa-key text-blue-500 mr-1"></i> New Password</label>
              <input type="password" name="new_password" id="new_password"
                     placeholder="At least 6 characters"
                     minlength="6"
                     oninput="validateNewPassword()">
              <span class="password-toggle" onclick="togglePassword('new_password')">
                <i class="fas fa-eye"></i>
              </span>
              <div id="newPasswordError" class="error-text"></div>
            </div>

            <!-- Confirm Password -->
            <div class="input-group">
              <label for="confirm_password"><i class="fas fa-key text-blue-500 mr-1"></i> Confirm New Password</label>
              <input type="password" name="confirm_password" id="confirm_password"
                     placeholder="Re-enter new password"
                     oninput="validateConfirmPassword()">
              <span class="password-toggle" onclick="togglePassword('confirm_password')">
                <i class="fas fa-eye"></i>
              </span>
              <div id="confirmPasswordError" class="error-text"></div>
            </div>
          </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 pt-4">
          <button type="submit" class="btn-primary flex-1 flex items-center justify-center">
            <i class="fas fa-save mr-2"></i> Save Changes
          </button>
          <a href="index.php" class="btn-secondary flex-1 flex items-center justify-center">
            <i class="fas fa-arrow-left mr-2"></i> Cancel
          </a>
        </div>
      </form>
    </div>

    <!-- Back Link -->
    <div class="text-center mt-8">
      <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left mr-2"></i> Back to Home
      </a>
    </div>
  </div>

  <script>
    function togglePassword(inputId) {
      const input = document.getElementById(inputId);
      const icon = input.nextElementSibling.querySelector('i');
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }
    
    // Add subtle animations to form elements
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('input, textarea');
      inputs.forEach(input => {
        input.addEventListener('focus', () => {
          input.parentElement.querySelector('label').style.color = '#4361ee';
        });
        
        input.addEventListener('blur', () => {
          input.parentElement.querySelector('label').style.color = '#374151';
        });
      });
    });
    
    // Password validation functions
    function validateNewPassword() {
      const newPassword = document.getElementById('new_password').value;
      const errorDiv = document.getElementById('newPasswordError');
      
      if (newPassword.length > 0 && newPassword.length < 6) {
        errorDiv.textContent = "Password must be at least 6 characters long.";
        errorDiv.style.display = 'block';
        document.getElementById('new_password').classList.add('input-error');
      } else {
        errorDiv.style.display = 'none';
        document.getElementById('new_password').classList.remove('input-error');
      }
    }
    
    function validateConfirmPassword() {
      const newPassword = document.getElementById('new_password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const errorDiv = document.getElementById('confirmPasswordError');
      
      if (confirmPassword.length > 0 && newPassword !== confirmPassword) {
        errorDiv.textContent = "Passwords do not match.";
        errorDiv.style.display = 'block';
        document.getElementById('confirm_password').classList.add('input-error');
      } else {
        errorDiv.style.display = 'none';
        document.getElementById('confirm_password').classList.remove('input-error');
      }
    }
    
    // Form submission validation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
      const newPassword = document.getElementById('new_password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      let hasError = false;
      
      // Validate new password length
      if (newPassword.length > 0 && newPassword.length < 6) {
        const errorDiv = document.getElementById('newPasswordError');
        errorDiv.textContent = "Password must be at least 6 characters long.";
        errorDiv.style.display = 'block';
        document.getElementById('new_password').classList.add('input-error');
        hasError = true;
      }
      
      // Validate password match
      if (newPassword.length > 0 && confirmPassword.length > 0 && newPassword !== confirmPassword) {
        const errorDiv = document.getElementById('confirmPasswordError');
        errorDiv.textContent = "Passwords do not match.";
        errorDiv.style.display = 'block';
        document.getElementById('confirm_password').classList.add('input-error');
        hasError = true;
      }
      
      // Prevent form submission if there are errors
      if (hasError) {
        e.preventDefault();
      }
    });
  </script>
</body>
</html>