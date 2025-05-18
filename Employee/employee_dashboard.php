<?php
require '../Includes/auth.php';
require '../Includes/db.php';

// Ensure session is started and user is authenticated
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../login.php");
    exit();
}

$message = '';

// Check if there's a notification (for leave request approval/decline)
$notification = '';
if (isset($_SESSION['leave_notification'])) {
    $notification = $_SESSION['leave_notification'];
    unset($_SESSION['leave_notification']); // Clear the notification after it's displayed
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if POST variables are set before using them
    $user_id = $_SESSION['user_id'];
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
    $reason = isset($_POST['reason']) ? $_POST['reason'] : '';

    // Basic validation
    if (empty($start_date) || empty($end_date) || empty($reason)) {
        $message = "All fields are required.";
    } else {
        // Insert the leave request with 'pending' status
        $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, 'pending')");

        if ($stmt === false) {
            // Output the error if prepare fails
            die("Error preparing the SQL statement: " . $conn->error);
        }

        $stmt->bind_param("isss", $user_id, $start_date, $end_date, $reason);

        if ($stmt->execute()) {
            $message = "Leave request submitted successfully.";
        } else {
            $message = "Error submitting request: " . $conn->error;
        }
    }
}

// Check if there are any leave requests and their status
// Updated query to join with users table to get approver information
$leave_request_query = "SELECT lr.*, 
                        u.name as approver_name, 
                        u.role as approver_role
                        FROM leave_requests lr
                        LEFT JOIN users u ON lr.approver_id = u.id
                        WHERE lr.user_id = ? 
                        ORDER BY lr.request_date DESC";
$stmt = $conn->prepare($leave_request_query);

if ($stmt === false) {
    die("Error preparing the SQL statement for leave requests: " . $conn->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$leave_result = $stmt->get_result();

// Mark notifications as viewed if requested
if (!isset($_SESSION['viewed_notifications'])) {
    $_SESSION['viewed_notifications'] = false;
}
if (isset($_GET['mark_viewed']) && $_GET['mark_viewed'] == 1) {
    $_SESSION['viewed_notifications'] = true;
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// Auto-mark notifications as viewed if the user has seen the dropdown
if (!$_SESSION['viewed_notifications']) {
    $status_query = "SELECT status FROM leave_requests WHERE user_id = ? AND (status = 'approved' OR status = 'declined')";
    $stmt = $conn->prepare($status_query);
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $status_result = $stmt->get_result();
        while ($row = $status_result->fetch_assoc()) {
            if ($row['status'] === 'approved' || $row['status'] === 'declined') {
                $_SESSION['leave_notification'] = "Your leave request has been " . $row['status'] . ".";
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
<nav class="bg-blue-600 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo / Title -->
            <div class="flex items-center">
                <span class="text-white text-xl font-bold">Company Portal</span>
            </div>

            <!-- Navigation Links -->
            <div class="hidden md:flex space-x-8 items-center">
                <a href="employee_dashboard.php" class="bg-blue-700 text-white px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                <a href="employee_profile.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">My Profile</a>
            </div>

            <!-- Right Side: Welcome, Notification, Logout -->
            <div class="flex items-center space-x-4">
                <!-- Welcome Message -->
                <span class="text-white">Welcome, <?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User' ?></span>

                <!-- Notification Icon -->
                <div class="relative">
                    <button id="notificationBtn" class="relative text-white hover:text-yellow-300 focus:outline-none">
                        <!-- Bell Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 00-4 0v.341C8.67 6.165 8 7.388 8 9v5.159c0 .538-.214 1.055-.595 1.436L6 17h5m4 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>

                        <!-- Notification Badge -->
                        <?php 
                        // Get count of leave requests with status changed (approved or declined)
                        $notification_count_query = "SELECT COUNT(*) as count FROM leave_requests 
                                                    WHERE user_id = ? AND (status = 'approved' OR status = 'declined')";
                        $stmt = $conn->prepare($notification_count_query);
                        if ($stmt) {
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            $stmt->execute();
                            $count_result = $stmt->get_result();
                            $notification_count = $count_result->fetch_assoc()['count'];
                            
                            // Only show badge if there are notifications AND they haven't been viewed
                            if ($notification_count > 0 && !$_SESSION['viewed_notifications']): 
                            ?>
                                <span id="notificationBadge" class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">
                                    <?= $notification_count ?>
                                </span>
                            <?php 
                            endif;
                        }
                        ?>
                    </button>
                    
                    <!-- Notification Dropdown Panel -->
                    <div id="notificationPanel" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg py-1 z-50">
                        <div class="px-4 py-2 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-700">Notifications</h3>
                        </div>
                        
                        <?php
                        // Fetch leave requests with their status and approver info
                        $leave_requests_query = "SELECT lr.id, lr.start_date, lr.end_date, lr.status, lr.request_date,
                                              u.name as approver_name, u.role as approver_role
                                              FROM leave_requests lr
                                              LEFT JOIN users u ON lr.approver_id = u.id
                                              WHERE lr.user_id = ? ORDER BY lr.request_date DESC LIMIT 5";
                        $stmt = $conn->prepare($leave_requests_query);
                        if ($stmt) {
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            $stmt->execute();
                            $leave_requests = $stmt->get_result();
                            
                            if ($leave_requests->num_rows > 0): 
                                while ($request = $leave_requests->fetch_assoc()):
                                    $start = date('M d, Y', strtotime($request['start_date']));
                                    $end = date('M d, Y', strtotime($request['end_date']));
                                    $status_class = $request['status'] === 'approved' ? 'text-green-600' : 
                                                ($request['status'] === 'declined' ? 'text-red-600' : 'text-yellow-600');
                                    
                                    // Get approver information if available
                                    $approver_info = '';
                                    if ($request['status'] === 'approved' || $request['status'] === 'declined') {
                                        if (!empty($request['approver_name'])) {
                                            $role_display = $request['approver_role'] === 'admin' ? 'Admin' : 'Head Office';
                                            $approver_info = " by {$request['approver_name']} ({$role_display})";
                                        }
                                    }
                        ?>
                            <div class="px-4 py-3 border-b border-gray-200 hover:bg-gray-50">
                                <p class="text-sm text-gray-700">
                                    Your leave request for <span class="font-semibold"><?= $start ?></span> to <span class="font-semibold"><?= $end ?></span> has been 
                                    <span class="font-bold <?= $status_class ?>"><?= $request['status'] . $approver_info ?></span>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?= date('F j, Y g:i a', strtotime($request['request_date'])) ?>
                                </p>
                            </div>
                        <?php 
                                endwhile;
                            else: 
                        ?>
                            <div class="px-4 py-3">
                                <p class="text-sm text-gray-500">No notifications to display</p>
                            </div>
                        <?php 
                            endif;
                        } else {
                        ?>
                            <div class="px-4 py-3">
                                <p class="text-sm text-gray-500">Error loading notifications</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- Logout Button -->
                <a href="../logout.php"
                   class="bg-blue-800 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-300">
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="py-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Employee Dashboard</h1>
            <p class="text-gray-600 mb-6">Manage your leave requests and view notifications</p>
            
            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md">
                    <p><?= $message ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Leave Status Notification -->
            <?php if ($notification): ?>
                <div class="mb-6 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded-md">
                    <p class="font-medium"><?= $notification ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Recent Leave Requests Section -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <h2 class="text-lg leading-6 font-medium text-gray-900">
                        Your Leave Requests
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        View the status of your recent leave requests
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested On</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            // Reset the result pointer to the beginning
                            if ($leave_result && $leave_result->num_rows > 0) {
                                mysqli_data_seek($leave_result, 0);
                                
                                while ($row = $leave_result->fetch_assoc()):
                                    $status_color = '';
                                    if ($row['status'] === 'approved') {
                                        $status_color = 'bg-green-100 text-green-800';
                                    } elseif ($row['status'] === 'declined') {
                                        $status_color = 'bg-red-100 text-red-800';
                                    } else {
                                        $status_color = 'bg-yellow-100 text-yellow-800';
                                    }
                                    
                                    // Format approver information
                                    $approver_display = '-';
                                    if (($row['status'] === 'approved' || $row['status'] === 'declined') && !empty($row['approver_name'])) {
                                        $role_display = $row['approver_role'] === 'admin' ? 'Admin' : 'Head Office';
                                        $approver_display = $row['approver_name'] . ' (' . $role_display . ')';
                                    }
                            ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($row['reason']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $approver_display ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y', strtotime($row['request_date'])) ?></td>
                                </tr>
                            <?php
                                endwhile;
                            } else {
                            ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-sm text-center text-gray-500">No leave requests found</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Request Leave Form -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <h2 class="text-lg leading-6 font-medium text-gray-900">
                        Request Leave
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Fill out the form below to submit a new leave request
                    </p>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                                <input type="date" name="start_date" id="start_date" required 
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                                <input type="date" name="end_date" id="end_date" required 
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700">Reason</label>
                            <textarea name="reason" id="reason" rows="4" required 
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                        </div>
                        <div>
                            <button type="submit" 
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-300">
                                Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Notification Toggle -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationPanel = document.getElementById('notificationPanel');
        const notificationBadge = document.getElementById('notificationBadge');
        
        // Toggle notification panel
        notificationBtn.addEventListener('click', function() {
            notificationPanel.classList.toggle('hidden');
            
            // If we're showing the panel, hide the badge
            if (!notificationPanel.classList.contains('hidden') && notificationBadge) {
                notificationBadge.classList.add('hidden');
                
                // Send AJAX request to mark as viewed
                fetch('employee_dashboard.php?mark_viewed=1', {
                    method: 'GET',
                });
            }
        });
        
        // Close the panel when clicking elsewhere
        document.addEventListener('click', function(event) {
            if (!notificationBtn.contains(event.target) && !notificationPanel.contains(event.target)) {
                notificationPanel.classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html>