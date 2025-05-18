<?php
require '../Includes/auth.php';
require '../Includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Delete user logic
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: Admin/admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_id'], $_POST['action'])) {
    $leave_id = intval($_POST['leave_id']);
    $action = $_POST['action'] === 'approve' ? 'approved' : 'declined';
    $admin_id = $_SESSION['user_id']; // Get the current admin user ID

    // Update the status of the leave request AND store the approver ID
    $stmt = $conn->prepare("UPDATE leave_requests SET status = ?, approver_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $action, $admin_id, $leave_id);
    
    if ($stmt->execute()) {
        $success_message = "Leave request successfully " . $action . ".";
    } else {
        $error_message = "Failed to update leave request: " . $conn->error;
    }
}

// Fetch all non-admin users
$result = $conn->query("SELECT id, name, email, role FROM users WHERE role != 'admin'");

// Get counts for the dashboard
$total_employees = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch_assoc()['count'];
$total_heads = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'head_office'")->fetch_assoc()['count'];

// Get pending leave requests
$leaveQuery = "
    SELECT l.id, u.name, l.start_date, l.end_date, l.reason 
    FROM leave_requests l 
    JOIN users u ON l.user_id = u.id 
    WHERE l.status = 'pending'
";
$pendingLeaves = $conn->query($leaveQuery);
$pendingCount = $pendingLeaves ? $pendingLeaves->num_rows : 0;

// Get data for the chart
$role_data = [];
$role_query = $conn->query("SELECT role, COUNT(*) as count FROM users WHERE role != 'admin' GROUP BY role");
while ($row = $role_query->fetch_assoc()) {
    $role_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Manage Users</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen flex">
    <!-- Side Navigation -->
    <aside class="bg-indigo-600 text-white w-64 min-h-screen flex flex-col">
        <div class="p-4 border-b border-indigo-800">
            <h2 class="text-xl font-bold">Company Portal</h2>
        </div>
        <nav class="flex-1 px-2 py-4 space-y-2">
            <a href="#dashboard" class="flex items-center px-4 py-2 bg-indigo-800 rounded-md" id="dashboard-tab">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="#users" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300" id="users-tab">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Manage Users</span>
            </a>
            <a href="#leave" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300" id="leave-tab">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Leave Requests</span>
                <?php if ($pendingCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="reports.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>Reports</span>
            </a>
            <a href="admin_profile.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Admin Profile</span>
            </a>
            <div class="pt-4 pb-2 border-t border-indigo-800">
                <h3 class="px-4 text-xs font-semibold text-indigo-200 uppercase tracking-wider">
                    User Statistics
                </h3>
            </div>
            <div class="px-4 py-2">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-indigo-100">Total Employees:</span>
                    <span class="text-sm font-bold bg-indigo-800 px-2 py-1 rounded"><?= $total_employees ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-indigo-100">Head Office:</span>
                    <span class="text-sm font-bold bg-indigo-800 px-2 py-1 rounded"><?= $total_heads ?></span>
                </div>
            </div>
        </nav>
        <div class="p-4 border-t border-indigo-800">
            <div class="flex items-center">
                <span class="text-sm font-medium"><?= htmlspecialchars($_SESSION['name']) ?></span>
                <a href="../logout.php" class="ml-auto text-indigo-100 hover:text-white transition-colors duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="max-w-7xl mx-auto">
            <!-- Status Messages -->
            <?php if (isset($success_message)): ?>
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard Content -->
            <div id="dashboard-content" class="tab-content">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Admin Dashboard</h1>
                <p class="text-gray-600 mb-6">Overview of the system</p>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Total Employees
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900">
                                            <?= $total_employees ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Head Office Staff
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900">
                                            <?= $total_heads ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Pending Leave Requests
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900">
                                            <?= $pendingCount ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Users Management Content -->
            <div id="users-content" class="tab-content hidden">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Users</h1>
                <p class="text-gray-600 mb-6">View and manage user accounts</p>
                
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6 flex justify-between items-center">
                        <div>
                            <h2 class="text-lg leading-6 font-medium text-gray-900">
                                Users
                            </h2>
                            <p class="mt-1 text-sm text-gray-500">
                                Manage non-admin user accounts
                            </p>
                        </div>
                        <a href="add_user.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Add New User
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($row['name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($row['email']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= $row['role'] === 'head_office' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                                    <?= htmlspecialchars($row['role']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="edit_user.php?id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</a>
                                                <a href="admin_dashboard.php?delete=<?= $row['id'] ?>" 
                                                onclick="return confirm('Are you sure you want to delete this user?');" 
                                                class="text-red-600 hover:text-red-900">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No users found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Leave Requests Content -->
            <div id="leave-content" class="tab-content hidden">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Leave Requests</h1>
                <p class="text-gray-600 mb-6">Manage employee leave requests</p>
                
                <!-- Leave Requests Table -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h2 class="text-lg leading-6 font-medium text-gray-900">
                            Pending Leave Requests
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Review and manage employee leave requests
                        </p>
                    </div>
                    <?php if (!$pendingLeaves): ?>
                        <div class="px-4 py-5 sm:px-6">
                            <p class="text-red-600">Error fetching pending leave requests: <?= $conn->error ?></p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($pendingCount > 0): ?>
                                        <?php while ($leave = $pendingLeaves->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($leave['name']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($leave['start_date']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($leave['end_date']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?= htmlspecialchars($leave['reason']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <form method="POST" class="inline-flex space-x-2">
                                                        <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md text-sm transition-colors duration-300" name="action" value="approve" onclick="return confirm('Approve this request?')">Approve</button>
                                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-sm transition-colors duration-300" name="action" value="decline" onclick="return confirm('Decline this request?')">Decline</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                                No pending leave requests.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript for tab navigation -->
    <script src="assets/js/admin_dashboard.js"></script>
</body>
</html>