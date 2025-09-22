<?php
session_start();
include 'includes/config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = $_POST['application_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($application_id > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            // Get user_id from application
            $stmt = $pdo->prepare("SELECT user_id FROM seller_applications WHERE id = ?");
            $stmt->execute([$application_id]);
            $application = $stmt->fetch();
            
            if ($application) {
                $user_id = $application['user_id'];
                
                if ($action === 'approve') {
                    // Update application status
                    $stmt = $pdo->prepare("UPDATE seller_applications SET status = 'approved' WHERE id = ?");
                    $stmt->execute([$application_id]);
                    
                    // Update user role
                    $stmt = $pdo->prepare("UPDATE users SET role = CONCAT(role, ',seller') WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Add notification
                    $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Congratulations! Your seller application has been approved.', 'success')")
                        ->execute([$user_id]);
                    
                    header("Location: role_requests.php?message=application_approved");
                    exit();
                } else {
                    // Update application status
                    $stmt = $pdo->prepare("UPDATE seller_applications SET status = 'rejected' WHERE id = ?");
                    $stmt->execute([$application_id]);
                    
                    // Add notification
                    $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'We regret to inform you that your seller application has been rejected.', 'error')")
                        ->execute([$user_id]);
                    
                    header("Location: role_requests.php?message=application_rejected");
                    exit();
                }
            }
        } catch (Exception $e) {
            error_log("Failed to process seller application: " . $e->getMessage());
        }
    }
}

header("Location: role_requests.php?error=action_failed");
exit();
?>