<?php
require '../Includes/auth.php';
require '../Includes/db.php';

// Ensure session is started and user is authenticated
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
} else {
    $error = "Error fetching user data: " . $conn->error;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate inputs
    if (empty($name) || empty($email)) {
        $error = "Name and email are required fields.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Check if email already exists for another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Email address is already in use by another account.");
            }
            
            // If password change is requested
            if (!empty($current_password) && !empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    throw new Exception("New password and confirmation do not match.");
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
                
                // Update user data with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $email, $hashed_password, $user_id);
            } else {
                // Update user data without changing password
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $email, $user_id);
            }
            
            if ($stmt->execute()) {
                // Update session data
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                
                $message = "Profile updated successfully!";
                $conn->commit();
                
                // Reload user data
                $stmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                throw new Exception("Error updating profile: " . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile - Employee Portal</title>
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
                    <a href="employee_dashboard.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                    <a href="employee_profile.php" class="bg-blue-700 text-white px-3 py-2 rounded-md text-sm font-medium">My Profile</a>
                </div>

                <!-- Right Side: Welcome, Logout -->
                <div class="flex items-center space-x-4">
                    <!-- Welcome Message -->
                    <span class="text-white">Welcome, <?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User' ?></span>

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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Your Profile</h1>
            <p class="text-gray-600 mb-6">Update your personal information and change your password</p>
            
            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md">
                    <p><?= $message ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md">
                    <p><?= $error ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Profile Information Form -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <h2 class="text-lg leading-6 font-medium text-gray-900">
                        Profile Information
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Update your personal details and password
                    </p>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <form method="POST" class="space-y-6">
                        <!-- Basic Information Section -->
                        <div class="border-b border-gray-200 pb-6">
                            <h3 class="text-md font-medium text-gray-900 mb-4">Basic Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                    <input type="text" name="name" id="name" value="<?= isset($user['name']) ? htmlspecialchars($user['name']) : '' ?>" required 
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                    <input type="email" name="email" id="email" value="<?= isset($user['email']) ? htmlspecialchars($user['email']) : '' ?>" required 
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                                <input type="text" id="role" value="<?= isset($user['role']) ? ucfirst(htmlspecialchars($user['role'])) : '' ?>" readonly 
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-50 text-gray-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500">Your role cannot be changed. Contact administrator for role changes.</p>
                            </div>
                        </div>
                        
                        <!-- Password Change Section -->
                        <div>
                            <h3 class="text-md font-medium text-gray-900 mb-4">Change Password</h3>
                            <p class="text-sm text-gray-500 mb-4">Leave these fields empty if you don't want to change your password</p>
                            
                            <div class="space-y-4">
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
                        
                        <!-- Submit Button -->
                        <div class="pt-4">
                            <button type="submit" 
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-300">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>