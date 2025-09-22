<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch tickets created by this user AS A BUYER
$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.subject, t.status, t.priority, t.created_at,
               (SELECT COUNT(*) FROM ticket_replies tr 
                WHERE tr.ticket_id = t.id 
                  AND tr.sender_role = 'support' 
                  AND tr.is_read = FALSE) as unread_replies
        FROM tickets t
        WHERE t.user_id = ? AND t.sender_role = 'buyer'
        ORDER BY 
            CASE WHEN t.status = 'open' THEN 1 WHEN t.status = 'in_progress' THEN 2 ELSE 3 END,
            t.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch tickets: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Tickets - AutoParts Hub</title>

  <!-- ✅ Corrected Tailwind & Font Awesome (no extra spaces) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">My Support Tickets</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Track your support requests and responses.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Your Tickets</h2>
        <p class="text-gray-600 mt-1">View and manage your support history.</p>
      </div>
      
      <div class="p-6">
        <?php if (empty($tickets)): ?>
          <div class="text-center py-12">
            <i class="fas fa-ticket-alt text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-500">No tickets yet</h3>
            <p class="text-gray-400 mt-2">Open a new ticket to get help.</p>
            <a href="ticket_form.php" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
              Open Ticket
            </a>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
                <tr>
                  <th class="px-6 py-3 text-left">ID</th>
                  <th class="px-6 py-3 text-left">Subject</th>
                  <th class="px-6 py-3 text-left">Status</th>
                  <th class="px-6 py-3 text-left">Priority</th>
                  <th class="px-6 py-3 text-left">Date</th>
                  <th class="px-6 py-3 text-left">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <?php foreach ($tickets as $ticket): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium">#<?= htmlspecialchars($ticket['id']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($ticket['subject']) ?></td>
                    <td class="px-6 py-4">
                      <?php if ($ticket['status'] === 'open'): ?>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Open</span>
                      <?php elseif ($ticket['status'] === 'in_progress'): ?>
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">In Progress</span>
                      <?php elseif ($ticket['status'] === 'resolved'): ?>
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Resolved</span>
                      <?php else: ?>
                        <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">Closed</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                      <?php if ($ticket['priority'] === 'urgent'): ?>
                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold">Urgent</span>
                      <?php elseif ($ticket['priority'] === 'high'): ?>
                        <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-bold">High</span>
                      <?php elseif ($ticket['priority'] === 'medium'): ?>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold">Medium</span>
                      <?php else: ?>
                        <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-bold">Low</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4"><?= date('M j, Y', strtotime($ticket['created_at'])) ?></td>
                    <td class="px-6 py-4">
                      <a href="view_ticket.php?id=<?= (int)$ticket['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                        <i class="fas fa-eye mr-1"></i> View
                        <?php if ($ticket['unread_replies'] > 0): ?>
                          <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-1"><?= (int)$ticket['unread_replies'] ?></span>
                        <?php endif; ?>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>