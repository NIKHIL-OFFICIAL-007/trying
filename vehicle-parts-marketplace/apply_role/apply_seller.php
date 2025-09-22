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

// Check if already approved seller
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_roles = $stmt->fetch();

if ($user_roles) {
    $roles = array_filter(array_map('trim', explode(',', $user_roles['role'])));
    $has_seller = in_array('seller', $roles);
    
    if ($has_seller) {
        header("Location: my_requests.php?message=already_approved");
        exit();
    }
}

// Check if already has a PENDING seller request
$stmt = $pdo->prepare("SELECT id, status FROM seller_applications WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$existing_application = $stmt->fetch();

if ($existing_application) {
    header("Location: my_requests.php?message=pending_application_exists");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== SELLER APPLICATION SUBMITTED ===");
    
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $business_address = trim($_POST['business_address']);
    $website = trim($_POST['website']);
    $reason = trim($_POST['reason']);
    $additional_info = $_POST['additional_info'] ?? '';

    // Validate required fields
    if (empty($name) || empty($phone) || empty($business_address) || empty($reason)) {
        error_log("Validation failed: Missing required fields");
        header("Location: apply_seller.php?error=missing_required_fields");
        exit();
    }

    // Handle file upload
    $business_license = null;
    if (isset($_FILES['business_license']) && $_FILES['business_license']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $file_type = mime_content_type($_FILES['business_license']['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            error_log("Invalid file type: " . $file_type);
            header("Location: apply_seller.php?error=invalid_file_type");
            exit();
        }
        
        // Check file size (5MB max)
        if ($_FILES['business_license']['size'] > 5 * 1024 * 1024) {
            error_log("File too large: " . $_FILES['business_license']['size'] . " bytes");
            header("Location: apply_seller.php?error=file_too_large");
            exit();
        }
        
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = uniqid('license_') . '_' . basename($_FILES['business_license']['name']);
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['business_license']['tmp_name'], $target_path)) {
            $business_license = $filename;
            error_log("File uploaded successfully: " . $filename);
        } else {
            error_log("File upload failed for: " . $_FILES['business_license']['name']);
            header("Location: apply_seller.php?error=upload_failed");
            exit();
        }
    }

    try {
        // Insert into seller_applications table
        $stmt = $pdo->prepare("
            INSERT INTO seller_applications 
            (user_id, name, phone, business_address, website, business_license, role_reason, additional_info, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $result = $stmt->execute([
            $user_id,
            $name,
            $phone,
            $business_address,
            $website,
            $business_license,
            $reason,
            $additional_info
        ]);
        
        if ($result) {
            error_log("Seller application created successfully");
            
            // Add notification
            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your seller application has been submitted.', 'info')")
                ->execute([$user_id]);

            header("Location: my_requests.php?message=seller_application_submitted");
            exit();
        } else {
            error_log("Database insert failed");
            throw new Exception("Database insert failed");
        }
    } catch (Exception $e) {
        error_log("Seller application failed: " . $e->getMessage());
        header("Location: apply_seller.php?error=application_failed");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Apply as Seller - AutoParts Hub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f9fafb;
      color: #1f2937;
    }
    
    .form-section {
      padding: 32px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      margin-bottom: 24px;
      background-color: white;
    }
    
    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #1f2937;
    }
    
    .form-input {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 16px;
      transition: border-color 0.2s ease;
    }
    
    .form-input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    
    .form-textarea {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 16px;
      min-height: 120px;
      resize: vertical;
      transition: border-color 0.2s ease;
    }
    
    .form-textarea:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    
    .file-upload {
      border: 2px dashed #d1d5db;
      border-radius: 8px;
      padding: 24px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .file-upload:hover {
      border-color: #3b82f6;
      background-color: #f3f4f6;
    }
    
    .file-upload-icon {
      font-size: 32px;
      color: #6b7280;
      margin-bottom: 12px;
    }
    
    .submit-btn {
      background-color: #3b82f6;
      color: white;
      border: none;
      padding: 16px 32px;
      font-size: 16px;
      font-weight: 600;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
      width: 100%;
      margin-top: 16px;
    }
    
    .submit-btn:hover {
      background-color: #2563eb;
    }
    
    .error-message {
      color: #ef4444;
      font-size: 14px;
      margin-top: 8px;
      display: none;
    }
  </style>
</head>
<body class="min-h-screen bg-gray-50">
  <?php include '../includes/header.php'; ?>

  <div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Page Header -->
    <div class="text-center mb-10">
      <h1 class="text-4xl font-bold text-gray-900 mb-4">Become a Verified Seller</h1>
      <p class="text-xl text-gray-600 max-w-2xl mx-auto">
        Join our marketplace of trusted sellers and start reaching thousands of customers looking for quality auto parts
      </p>
    </div>

    <!-- Form Container -->
    <div class="bg-white rounded-xl shadow-lg p-8">
      <form method="POST" class="space-y-8" enctype="multipart/form-data">
        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Personal Information</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" required class="form-input" placeholder="John Doe" value="<?= htmlspecialchars($user['name'] ?? '')?>">
              <div class="error-message" id="name-error">Please enter your full name</div>
            </div>
            
            <div>
              <label class="form-label">Phone Number *</label>
              <input type="tel" name="phone" required class="form-input" placeholder="+1 (555) 123-4567" value="">
              <div class="error-message" id="phone-error">Please enter a valid phone number</div>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Business Information</h2>
          
          <div>
            <label class="form-label">Business Address *</label>
            <textarea name="business_address" required class="form-textarea" placeholder="123 Main Street, City, State, ZIP Code"></textarea>
            <div class="error-message" id="address-error">Please enter your business address</div>
          </div>
          
          <div class="mt-6">
            <label class="form-label">Website or LinkedIn Profile</label>
            <input type="url" name="website" class="form-input" placeholder="https://yourbusiness.com">
          </div>
        </div>

        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Documentation</h2>
          
          <div>
            <label class="form-label">Business License (PDF/JPG/PNG)</label>
            <div class="file-upload" onclick="document.getElementById('business_license').click()">
              <i class="fas fa-file-upload file-upload-icon"></i>
              <p class="text-sm text-gray-600 mt-2">Click to upload or drag and drop</p>
              <p class="text-xs text-gray-500 mt-1">PNG, JPG, PDF up to 5MB</p>
            </div>
            <input type="file" name="business_license" accept=".pdf,.jpg,.jpeg,.png" class="hidden" id="business_license">
          </div>
        </div>

        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Application Details</h2>
          
          <div>
            <label class="form-label">Reason for Applying *</label>
            <textarea name="reason" required class="form-textarea" placeholder="Please explain why you want to sell auto parts on our platform..."></textarea>
            <div class="error-message" id="reason-error">Please explain why you want to become a seller</div>
          </div>
          
          <div class="mt-6">
            <label class="form-label">Additional Information</label>
            <textarea name="additional_info" class="form-textarea" placeholder="Share any relevant experience, qualifications, or business details..."></textarea>
          </div>
        </div>

        <!-- Processing Information -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <i class="fas fa-info-circle text-yellow-500 mt-0.5"></i>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800">Processing Information</h3>
              <div class="mt-2 text-sm text-yellow-700">
                <strong>Review Time:</strong> Applications are typically reviewed within 3-5 business days. You'll receive an email notification once a decision has been made.
              </div>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="pt-6">
          <button type="submit" class="submit-btn">
            <i class="fas fa-paper-plane mr-2"></i>
            Submit Application
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Form validation
      const form = document.querySelector('form');
      const inputs = form.querySelectorAll('input, textarea');
      
      form.addEventListener('submit', function(e) {
        let hasError = false;
        
        // Reset error states
        inputs.forEach(input => {
          input.style.borderColor = '#d1d5db';
          input.classList.remove('border-red-500');
          const errorElement = input.nextElementSibling;
          if (errorElement && errorElement.classList.contains('error-message')) {
            errorElement.style.display = 'none';
          }
        });
        
        // Validate required fields
        const requiredFields = [
          { element: document.querySelector('input[name="name"]'), errorId: 'name-error', message: 'Please enter your full name' },
          { element: document.querySelector('input[name="phone"]'), errorId: 'phone-error', message: 'Please enter a valid phone number' },
          { element: document.querySelector('textarea[name="business_address"]'), errorId: 'address-error', message: 'Please enter your business address' },
          { element: document.querySelector('textarea[name="reason"]'), errorId: 'reason-error', message: 'Please explain why you want to become a seller' }
        ];
        
        requiredFields.forEach(field => {
          if (!field.element.value.trim()) {
            field.element.style.borderColor = '#ef4444';
            field.element.classList.add('border-red-500');
            const errorElement = document.getElementById(field.errorId);
            if (errorElement) {
              errorElement.textContent = field.message;
              errorElement.style.display = 'block';
            }
            hasError = true;
          }
        });
        
        if (hasError) {
          e.preventDefault();
          return;
        }
      });
      
      // File upload preview
      const fileInput = document.getElementById('business_license');
      if (fileInput) {
        fileInput.addEventListener('change', function() {
          const fileName = this.files[0]?.name || '';
          if (fileName) {
            const fileUploadDiv = document.querySelector('.file-upload');
            fileUploadDiv.style.backgroundColor = '#f3f4f6';
            fileUploadDiv.style.border = '2px dashed #3b82f6';
            
            // Update the text inside the upload area
            const textSpan = fileUploadDiv.querySelector('p:nth-child(2)');
            if (textSpan) {
              textSpan.textContent = fileName;
            }
          }
        });
      }
    });
  </script>
</body>
</html>