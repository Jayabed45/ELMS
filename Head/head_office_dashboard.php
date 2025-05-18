<?php
require '../Includes/auth.php';
require '../Includes/db.php';

// Redirect if not head office
if ($_SESSION['role'] !== 'head_office') {
    header("Location: ../login.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $user_id = $_SESSION['user_id'];
    $errors = [];
    
    // Validate inputs
    if (empty($name)) {
        $errors[] = "Name cannot be empty";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    // Check if email already exists for another user
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email already in use by another account";
        }
    }
    
    // If no errors and there's a password change
    if (empty($errors)) {
        if (!empty($password)) {
            // Update name, email, and password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $email, $hashed_password, $user_id);
        } else {
            // Update only name and email
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $email, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $success_message = "Profile updated successfully!";
        } else {
            $errors[] = "Failed to update profile: " . $conn->error;
        }
    }
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

// Fetch current user's data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

// Fetch total non-admin users
$totalUsersResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role != 'admin'");
$totalUsers = $totalUsersResult ? $totalUsersResult->fetch_assoc()['total'] : 0;

// Fetch pending leave requests
$leaveQuery = "
    SELECT l.id, u.name, l.start_date, l.end_date, l.reason 
    FROM leave_requests l 
    JOIN users u ON l.user_id = u.id 
    WHERE l.status = 'pending'
";
$pendingLeaves = $conn->query($leaveQuery);
$pendingCount = $pendingLeaves ? $pendingLeaves->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Head Office Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex">
    <!-- Side Navigation -->
    <aside class="bg-indigo-600 text-white w-64 min-h-screen flex flex-col">
        <div class="p-4 border-b border-indigo-800">
            <h2 class="text-xl font-bold">Company Portal</h2>
        </div>
        <nav class="flex-1 px-2 py-4 space-y-2">
            <a href="head_office_dashboard.php" class="flex items-center px-4 py-2 bg-indigo-800 rounded-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Head Dashboard</span>
            </a>
            <a href="#profile" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300" onclick="openProfileTab(event)">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Edit Profile</span>
            </a>
            <!-- Other menu items (commented out) -->
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
            <!-- Dashboard Content -->
            <div id="dashboard-content" class="tab-content">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Head Office Dashboard</h1>
                <p class="text-gray-600 mb-6">Manage leave requests and view employee statistics</p>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
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
                                            <?= $totalUsers ?>
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
                                                        <button class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md text-sm transition-colors duration-300" name="action" value="approve" onclick="return confirm('Approve this request?')">Approve</button>
                                                        <button class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-sm transition-colors duration-300" name="action" value="decline" onclick="return confirm('Decline this request?')">Decline</button>
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

            <!-- Edit Profile Content -->
            <div id="profile-content" class="tab-content hidden">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Edit Profile</h1>
                <p class="text-gray-600 mb-6">Update your personal information</p>

                <!-- Profile Form -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h2 class="text-lg leading-6 font-medium text-gray-900">
                            Personal Information
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Update your account details and preferences
                        </p>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <?php if (isset($errors) && !empty($errors)): ?>
                            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                <ul class="list-disc pl-5">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($success_message)): ?>
                            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                                <?= htmlspecialchars($success_message) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" id="name" value="<?= htmlspecialchars($user_data['name'] ?? $_SESSION['name']) ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                                <input type="email" name="email" id="email" value="<?= htmlspecialchars($user_data['email'] ?? $_SESSION['email']) ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label>
                                <input type="password" name="password" id="password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="password_confirm" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                <input type="password" name="password_confirm" id="password_confirm" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <button type="submit" name="update_profile" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript for tab switching -->
    <script>
        function openProfileTab(event) {
            event.preventDefault();
            
            // Hide all tab content
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show the profile content
            document.getElementById('profile-content').classList.remove('hidden');
            
            // Update active menu item
            const menuItems = document.querySelectorAll('nav a');
            menuItems.forEach(item => {
                item.classList.remove('bg-indigo-800');
                item.classList.add('text-indigo-100', 'hover:bg-indigo-700');
            });
            
            // Set clicked item as active
            event.currentTarget.classList.remove('text-indigo-100', 'hover:bg-indigo-700');
            event.currentTarget.classList.add('bg-indigo-800');
        }
        
        // Get the dashboard menu item and add click handler
        document.querySelector('nav a[href="head_office_dashboard.php"]').addEventListener('click', function(event) {
            if (window.location.pathname.endsWith('head_office_dashboard.php')) {
                event.preventDefault();
                
                // Hide all tab content
                const tabContents = document.querySelectorAll('.tab-content');
                tabContents.forEach(content => {
                    content.classList.add('hidden');
                });
                
                // Show the dashboard content
                document.getElementById('dashboard-content').classList.remove('hidden');
                
                // Update active menu item
                const menuItems = document.querySelectorAll('nav a');
                menuItems.forEach(item => {
                    item.classList.remove('bg-indigo-800');
                    item.classList.add('text-indigo-100', 'hover:bg-indigo-700');
                });
                
                // Set dashboard as active
                this.classList.remove('text-indigo-100', 'hover:bg-indigo-700');
                this.classList.add('bg-indigo-800');
            }
        });
    </script>
</body>
</html>