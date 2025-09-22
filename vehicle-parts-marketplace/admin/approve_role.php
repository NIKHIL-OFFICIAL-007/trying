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

// Validate POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: role_requests.php");
    exit();
}

$user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
$action = filter_var($_POST['action'] ?? null, FILTER_SANITIZE_STRING);

if (!$user_id || !in_array($action, ['approve', 'reject'])) {
    header("Location: role_requests.php?error=action_failed");
    exit();
}

// Debug: Log the action
error_log("Processing role $action for user ID: $user_id");

try {
    $pdo->beginTransaction();

    // Fetch current user data
    $stmt = $pdo->prepare("SELECT role_request, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found.");
    }

    if (!$user['role_request']) {
        throw new Exception("No role request found for this user.");
    }

    if ($action === 'approve') {
        $requested_role = $user['role_request'];

        if (!in_array($requested_role, ['seller', 'support', 'admin'])) {
            throw new Exception("Invalid requested role: " . htmlspecialchars($requested_role));
        }

        // Merge new role into existing roles
        $current_roles = array_filter(array_map('trim', explode(',', $user['role'])));
        $new_roles = array_unique(array_merge($current_roles, [$requested_role]));
        $new_role_string = implode(',', $new_roles);

        // Update user: set new role + approved status + clear request
        $stmt = $pdo->prepare("UPDATE users SET role = ?, role_status = 'approved', role_request = NULL, role_reason = NULL WHERE id = ?");
        $stmt->execute([$new_role_string, $user_id]);

        // Debug: Log the update
        error_log("Approved role request for user $user_id. New roles: $new_role_string");

        // Notify user
        $message = "🎉 Your request to become a " . ucfirst($requested_role) . " has been approved!";
        $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'role_approved')")
            ->execute([$user_id, $message]);

    } else { // reject
        // Clear request and mark as rejected
        $stmt = $pdo->prepare("UPDATE users SET role_status = 'rejected', role_request = NULL, role_reason = NULL WHERE id = ?");
        $stmt->execute([$user_id]);

        // Debug: Log the update
        error_log("Rejected role request for user $user_id");

        // Notify user
        $message = "❌ Your role request to become a " . ucfirst($user['role_request'] ?? 'unknown') . " has been rejected.";
        $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'role_rejected')")
            ->execute([$user_id, $message]);
    }

    // Log admin action
    $details = "$action: " . ($user['role_request'] ?? 'unknown');
    $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_user_id, details) VALUES (?, 'role_action', ?, ?)")
        ->execute([$_SESSION['user_id'], $user_id, $details]);

    $pdo->commit();

    // Redirect back to role requests page with success message
    $message = $action === 'approve' ? 'role_approved' : 'role_rejected';
    header("Location: role_requests.php?message=$message");
    exit();

} catch (Exception $e) {
    $pdo->rollback();
    error_log("Role approval/rejection failed: " . $e->getMessage());
    header("Location: role_requests.php?error=action_failed");
    exit();
}
?>