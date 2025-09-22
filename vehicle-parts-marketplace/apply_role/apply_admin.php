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
$stmt = $pdo->prepare("SELECT role, role_status, role_request FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: ../logout.php");
    exit();
}

// ✅ Split roles safely
$roles = array_filter(array_map('trim', explode(',', $user['role'])));
$has_admin = in_array('admin', $roles);

// Check if already approved admin
if ($has_admin && $user['role_status'] === 'approved') {
    header("Location: my_requests.php?message=already_approved");
    exit();
}

// Check if already has a PENDING admin request
if ($user['role_request'] === 'admin' && $user['role_status'] === 'pending') {
    header("Location: my_requests.php?message=pending_application_exists");
    exit();
}

// Handle form submission
if ($_POST) {
    $reason = trim($_POST['reason']);
    $additional_info = $_POST['additional_info'] ?? '';

    try {
        // ✅ Only update role_request and status — do NOT add admin to role yet
        $stmt = $pdo->prepare("
            UPDATE users 
            SET role_request = 'admin', 
                role_status = 'pending', 
                role_reason = ?, 
                additional_info = ?
            WHERE id = ?
        ");
        $stmt->execute([$reason, $additional_info, $user_id]);

        // Add notification
        $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your admin application has been submitted.', 'info')")
            ->execute([$user_id]);

        header("Location: my_requests.php?message=admin_application_submitted");
        exit();
    } catch (Exception $e) {
        error_log("Admin application failed: " . $e->getMessage());
        header("Location: my_requests.php?error=application_failed");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Apply as Admin - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root {
      --primary: #4361ee;
      --primary-dark: #3a56d4;
      --primary-light: #e6eeff;
      --success: #10b981;
      --warning: #f59e0b;
      --error: #ef4444;
    }
    
    body {
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .form-container {
      background: white;
      border-radius: 1rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      overflow: hidden;
    }
    
    .form-header {
      background: linear-gradient(120deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      padding: 2rem;
    }
    
    .form-step {
      display: none;
    }
    
    .form-step.active {
      display: block;
      animation: fadeIn 0.5s ease;
    }
    
    .step-indicator {
      display: flex;
      justify-content: center;
      margin-bottom: 2rem;
      padding: 0 2rem;
    }
    
    .step {
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      padding: 0 1.5rem;
    }
    
    .step:not(:last-child):after {
      content: '';
      position: absolute;
      top: 20px;
      right: -50%;
      width: 100%;
      height: 2px;
      background: #e2e8f0;
      z-index: 1;
    }
    
    .step-number {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      color: #64748b;
      margin-bottom: 0.5rem;
      position: relative;
      z-index: 2;
    }
    
    .step.active .step-number {
      background: var(--primary);
      color: white;
    }
    
    .step.completed .step-number {
      background: var(--success);
      color: white;
    }
    
    .step.completed:not(:last-child):after {
      background: var(--success);
    }
    
    .step-label {
      font-size: 0.875rem;
      color: #64748b;
      font-weight: 500;
    }
    
    .step.active .step-label {
      color: var(--primary);
      font-weight: 600;
    }
    
    .form-body {
      padding: 2rem;
    }
    
    .form-group {
      margin-bottom: 1.5rem;
    }
    
    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: #374151;
    }
    
    .form-input, .form-textarea {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      font-size: 1rem;
      transition: all 0.2s;
    }
    
    .form-input:focus, .form-textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
    }
    
    .form-textarea {
      min-height: 120px;
      resize: vertical;
    }
    
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.75rem 1.5rem;
      border-radius: 0.5rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      border: none;
    }
    
    .btn-primary {
      background: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background: var(--primary-dark);
    }
    
    .btn-outline {
      background: transparent;
      border: 1px solid #d1d5db;
      color: #4b5563;
    }
    
    .btn-outline:hover {
      background: #f9fafb;
    }
    
    .btn-icon {
      margin-right: 0.5rem;
    }
    
    .form-actions {
      display: flex;
      justify-content: space-between;
      margin-top: 2rem;
    }
    
    .character-count {
      text-align: right;
      font-size: 0.875rem;
      color: #6b7280;
      margin-top: 0.25rem;
    }
    
    .character-count.warning {
      color: var(--warning);
    }
    
    .character-count.error {
      color: var(--error);
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .feature-list {
      list-style: none;
      padding: 0;
      margin: 1.5rem 0;
    }
    
    .feature-list li {
      display: flex;
      align-items: center;
      margin-bottom: 0.75rem;
      color: #4b5563;
    }
    
    .feature-list i {
      color: var(--success);
      margin-right: 0.75rem;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">
  <?php include '../includes/header.php'; ?>

  <div class="container mx-auto px-6 py-12 max-w-4xl">
    <div class="form-container">
      <div class="form-header text-center">
        <h1 class="text-2xl font-bold mb-2">Apply as Administrator</h1>
        <p class="opacity-90">Help manage the platform and ensure smooth operations for all users.</p>
      </div>
      
      <div class="step-indicator pt-6">
        <div class="step active">
          <div class="step-number">1</div>
          <div class="step-label">Application</div>
        </div>
        <div class="step">
          <div class="step-number">2</div>
          <div class="step-label">Review</div>
        </div>
        <div class="step">
          <div class="step-number">3</div>
          <div class="step-label">Submit</div>
        </div>
      </div>
      
      <form method="POST" class="form-body">
        <!-- Step 1 -->
        <div class="form-step active" id="step1">
          <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-3">Tell us about yourself</h2>
            <p class="text-gray-600">Why do you want to be an admin?</p>
          </div>
          
          <div class="form-group">
            <label class="form-label">Reason for Applying <span class="text-red-500">*</span></label>
            <textarea 
              name="reason" 
              required 
              class="form-textarea" 
              rows="4" 
              placeholder="Why do you want to become an admin? What experience do you have?"
              oninput="updateCharacterCount(this, 'reason-count', 500)"
            ></textarea>
            <div class="character-count" id="reason-count">0/500 characters</div>
          </div>

          <div class="form-group">
            <label class="form-label">Additional Information</label>
            <textarea 
              name="additional_info" 
              class="form-textarea" 
              rows="4" 
              placeholder="Any leadership or technical experience?"
              oninput="updateCharacterCount(this, 'info-count', 1000)"
            ></textarea>
            <div class="character-count" id="info-count">0/1000 characters</div>
          </div>
          
          <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 mt-6">
            <h3 class="font-semibold text-blue-800 mb-2 flex items-center">
              <i class="fas fa-info-circle mr-2"></i> What we look for in admins
            </h3>
            <ul class="feature-list">
              <li><i class="fas fa-check-circle"></i> Leadership and communication skills</li>
              <li><i class="fas fa-check-circle"></i> Platform knowledge and fairness</li>
              <li><i class="fas fa-check-circle"></i> Commitment to community trust</li>
              <li><i class="fas fa-check-circle"></i> Technical understanding of the platform</li>
            </ul>
          </div>
          
          <div class="form-actions">
            <div></div>
            <button type="button" class="btn btn-primary" onclick="showStep(2)">
              <i class="btn-icon fas fa-arrow-right"></i> Continue
            </button>
          </div>
        </div>
        
        <!-- Step 2: Review -->
        <div class="form-step" id="step2">
          <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-3">Review Your Application</h2>
            <p class="text-gray-600">Please review before submitting.</p>
          </div>
          
          <div class="bg-gray-50 p-5 rounded-lg mb-6">
            <div class="mb-4">
              <h3 class="text-sm font-medium text-gray-500 mb-1">Reason for Applying</h3>
              <p class="text-gray-800" id="review-reason"></p>
            </div>
            
            <div>
              <h3 class="text-sm font-medium text-gray-500 mb-1">Additional Information</h3>
              <p class="text-gray-800" id="review-info"></p>
              <p class="text-gray-400 italic" id="review-info-empty" style="display: none;">No additional information provided</p>
            </div>
          </div>
          
          <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200 mb-6">
            <h3 class="font-semibold text-yellow-800 mb-2 flex items-center">
              <i class="fas fa-exclamation-triangle mr-2"></i> Important
            </h3>
            <p class="text-yellow-700 text-sm">
              Admin applications undergo a thorough review process and may take 5-7 business days.
            </p>
          </div>
          
          <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="showStep(1)">
              <i class="btn-icon fas fa-arrow-left"></i> Back
            </button>
            <button type="button" class="btn btn-primary" onclick="showStep(3)">
              <i class="btn-icon fas fa-check"></i> Submit
            </button>
          </div>
        </div>
        
        <!-- Step 3: Confirmation -->
        <div class="form-step" id="step3">
          <div class="text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-6">
              <i class="fas fa-check text-2xl"></i>
            </div>
            
            <h2 class="text-xl font-semibold text-gray-800 mb-3">Ready to Submit</h2>
            <p class="text-gray-600 max-w-md mx-auto mb-6">
              By submitting, you agree to uphold the platform's integrity and maintain user trust. Our team will contact you soon.
            </p>
            
            <div class="form-actions justify-center">
              <button type="button" class="btn btn-outline mr-4" onclick="showStep(2)">
                <i class="btn-icon fas fa-arrow-left"></i> Back
              </button>
              <button type="submit" class="btn btn-primary">
                <i class="btn-icon fas fa-paper-plane"></i> Submit Application
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
    function showStep(stepNumber) {
      document.querySelectorAll('.form-step').forEach(step => {
        step.classList.remove('active');
      });
      document.getElementById('step' + stepNumber).classList.add('active');

      document.querySelectorAll('.step').forEach((step, index) => {
        step.classList.remove('active', 'completed');
        if (index + 1 < stepNumber) step.classList.add('completed');
        else if (index + 1 === stepNumber) step.classList.add('active');
      });

      if (stepNumber === 2) {
        const reason = document.querySelector('textarea[name="reason"]').value;
        const additionalInfo = document.querySelector('textarea[name="additional_info"]').value;
        
        document.getElementById('review-reason').textContent = reason || 'Not provided';
        
        if (additionalInfo) {
          document.getElementById('review-info').textContent = additionalInfo;
          document.getElementById('review-info').style.display = 'block';
          document.getElementById('review-info-empty').style.display = 'none';
        } else {
          document.getElementById('review-info').style.display = 'none';
          document.getElementById('review-info-empty').style.display = 'block';
        }
      }
    }

    function updateCharacterCount(textarea, countElementId, maxLength) {
      const count = textarea.value.length;
      const countElement = document.getElementById(countElementId);
      countElement.textContent = `${count}/${maxLength} characters`;
      
      if (count > maxLength * 0.9) {
        countElement.classList.add('error');
        countElement.classList.remove('warning');
      } else if (count > maxLength * 0.75) {
        countElement.classList.add('warning');
        countElement.classList.remove('error');
      } else {
        countElement.classList.remove('warning', 'error');
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      const reasonTextarea = document.querySelector('textarea[name="reason"]');
      const infoTextarea = document.querySelector('textarea[name="additional_info"]');
      
      if (reasonTextarea) updateCharacterCount(reasonTextarea, 'reason-count', 500);
      if (infoTextarea) updateCharacterCount(infoTextarea, 'info-count', 1000);
    });
  </script>
</body>
</html>