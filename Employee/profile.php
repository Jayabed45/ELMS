<?php
require '../Includes/auth.php';
require '../Includes/db.php';

// Ensure session is started and user is authenticated
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../login.php");
    exit();
}

$profile_message = '';

// Fetch user profile data
$user_id = $_SESSION['user_id'];
$profile_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($profile_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
$profile_data = $profile_result->fetch_assoc();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $address = isset($_POST['address']) ? $_POST['address'] : '';
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $profile_message = "Name and email are required.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Update basic profile information
            $update_profile_query = "UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
            $stmt = $conn->prepare($update_profile_query);
            $stmt->bind_param("ssssi", $name, $email, $phone, $address, $user_id);
            $stmt->execute();
            
            // Update session values
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            
            // Handle password change if requested
            if (!empty($current_password) && !empty($new_password)) {
                // Verify current password
                $password_query = "SELECT password FROM users WHERE id = ?";
                $stmt = $conn->prepare($password_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                if (password_verify($current_password, $user['password'])) {
                    // Check if new password and confirmation match
                    if ($new_password === $confirm_password) {
                        // Hash new password and update
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_password_query = "UPDATE users SET password = ? WHERE id = ?";
                        $stmt = $conn->prepare($update_password_query);
                        $stmt->bind_param("si", $hashed_password, $user_id);
                        $stmt->execute();
                        
                        $profile_message = "Profile and password updated successfully.";
                    } else {
                        throw new Exception("New password and confirmation do not match.");
                    }
                } else {
                    throw new Exception("Current password is incorrect.");
                }
            } else {
                $profile_message = "Profile updated successfully.";
            }
            
            // Commit the transaction
            $conn->commit();
        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $profile_message = "Error: " . $e->getMessage();
        }
    }
}

// Initialize the viewed notifications session variable if it doesn't exist
if (!isset($_SESSION['viewed_notifications'])) {
    $_SESSION['viewed_notifications'] = false;
}

// Get count of leave requests with status changed (approved or declined) for the badge
$notification_count_query = "SELECT COUNT(*) as count FROM leave_requests 
                         WHERE user_id = ? AND (status = 'approved' OR status = 'declined')";
$stmt = $conn->prepare($notification_count_query);
$notification_count = 0;
if ($stmt) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $notification_count = $count_result->fetch_assoc()['count'];
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
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-blue-600 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo / Title -->
            <div class="flex items-center">
                <span class="text-white text-xl font-bold">Company Portal</span>
            </div>

            <!-- Right Side: Welcome, Notification, Logout -->
            <div class="flex items-center space-x-4">
                <!-- Welcome Message -->
                <span class="text-white">Welcome, <?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User' ?></span>

                <!-- Notification Icon -->
                <div class="relative">
                    <a href="employee.php" class="relative text-white hover:text-yellow-300 focus:outline-none">
                        <!-- Bell Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 00-4 0v.341C8.67 6.165 8 7.388 8 9v5.159c0 .538-.214 1.055-.595 1.436L6 17h5m4 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>

                        <!-- Notification Badge -->
                        <?php if ($notification_count > 0 && !$_SESSION['viewed_notifications']): ?>
                            <span class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">
                                <?= $notification_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
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
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Your Profile</h1>
                    <p class="text-gray-600">Update your personal information and password</p>
                </div>
                <a href="employee.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Dashboard
                </a>
            </div>
            
            <!-- Profile Message Display -->
            <?php if ($profile_message): ?>
                <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md">
                    <p><?= $profile_message ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Profile Management Section -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <h2 class="text-lg leading-6 font-medium text-gray-900">
                        Personal Information
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Update your personal details and contact information
                    </p>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" name="name" id="name" required 
                                    value="<?= htmlspecialchars($profile_data['name'] ?? '') ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                <input type="email" name="email" id="email" required 
                                    value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="tel" name="phone" id="phone"
                                    value="<?= htmlspecialchars($profile_data['phone'] ?? '') ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                                <input type="text" id="role" value="<?= ucfirst(htmlspecialchars($profile_data['role'] ?? '')) ?>" disabled
                                    class="mt-1 block w-full border border-gray-200 bg-gray-50 rounded-md shadow-sm py-2 px-3 text-gray-500 sm:text-sm">
                            </div>
                        </div>
                        
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea name="address" id="address" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?= htmlspecialchars($profile_data['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4">
                            <h3 class="text-md font-medium text-gray-900 mb-3">Change Password</h3>
                            <p class="mb-3 text-sm text-gray-500">Leave these fields blank if you don't want to change your password</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                                    <input type="password" name="current_password" id="current_password"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                                    <input type="password" name="new_password" id="new_password"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <button type="submit" 
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-300">
                                Update Profile
                            </button>
                            
                            <button type="reset"
                                class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-300">
                                Reset Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Information Section -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <h2 class="text-lg leading-6 font-medium text-gray-900">
                        Account Information
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Details about your company account
                    </p>
                </div>
                <div class="bg-white shadow overflow-hidden">
                    <dl>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Employee ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($profile_data['id'] ?? 'N/A') ?></dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Full name</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($profile_data['name'] ?? 'N/A') ?></dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Email address</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($profile_data['email'] ?? 'N/A') ?></dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Account created</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?= isset($profile_data['created_at']) ? date('F j, Y', strtotime($profile_data['created_at'])) : 'N/A' ?>
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Last login</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?= isset($profile_data['last_login']) ? date('F j, Y g:i a', strtotime($profile_data['last_login'])) : 'N/A' ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?= date('Y') ?> Company Portal. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>