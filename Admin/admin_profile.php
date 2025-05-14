<?php
require '../Includes/auth.php';
require '../Includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get current user data
$stmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required fields.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Update basic info
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $email, $user_id);
            $stmt->execute();
            
            // Check if password update is requested
            if (!empty($current_password) && !empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match.");
                }
                
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                
                if (!password_verify($current_password, $user_data['password'])) {
                    throw new Exception("Current password is incorrect.");
                }
                
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                $stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            $success_message = "Profile updated successfully.";
            
            // Update session data
            $_SESSION['name'] = $name;
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Get counts for the sidebar (for admins and head office)
$total_employees = 0;
$total_heads = 0;

if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'head_office') {
    $total_employees = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch_assoc()['count'];
    $total_heads = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'head_office'")->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile - Company Portal</title>
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
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>Manage Users</span>
                </a>
                <a href="reports.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Reports</span>
                </a>
            <?php elseif ($_SESSION['role'] === 'head_office'): ?>
                <a href="head_dashboard.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="head_reports.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Reports</span>
                </a>
            <?php elseif ($_SESSION['role'] === 'employee'): ?>
                <a href="employee_dashboard.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>
            <?php endif; ?>
            
            <a href="profile.php" class="flex items-center px-4 py-2 bg-indigo-800 rounded-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Admin Profile</span>
            </a>
            
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'head_office'): ?>
                <!-- User Statistics Section -->
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
            <?php endif; ?>
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
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">My Profile</h1>
            <p class="text-gray-600 mb-6">Manage your account settings and password</p>
            
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?= htmlspecialchars($success_message) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Profile Information
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Update your personal details and password
                    </p>
                </div>
                
                <form method="POST" action="" class="px-4 py-5 sm:p-6">
                    <div class="space-y-6">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" 
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" 
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <!-- Role (Display only) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Role</label>
                            <div class="mt-1 block w-full py-2 px-3 bg-gray-50 border border-gray-300 rounded-md shadow-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 
                                        ($user['role'] === 'head_office' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>">
                                    <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Password Change Section -->
                        <div class="pt-6 border-t border-gray-200">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Change Password</h4>
                            
                            <!-- Current Password -->
                            <div class="mb-4">
                                <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                                <input type="password" name="current_password" id="current_password" 
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <p class="mt-1 text-xs text-gray-500">Leave blank if you don't want to change your password</p>
                            </div>
                            
                            <!-- New Password -->
                            <div class="mb-4">
                                <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                                <input type="password" name="new_password" id="new_password" 
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <!-- Confirm Password -->
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" 
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>