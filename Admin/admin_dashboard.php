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

// Fetch all non-admin users
$result = $conn->query("SELECT id, name, email, role FROM users WHERE role != 'admin'");

// Get counts for the dashboard
$total_employees = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch_assoc()['count'];
$total_heads = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'head_office'")->fetch_assoc()['count'];

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
            <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="admin_dashboard.php" class="flex items-center px-4 py-2 bg-indigo-800 rounded-md">
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
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Admin Dashboard</h1>
            <p class="text-gray-600 mb-6">Manage Users</p>
            
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
    </main>
</body>
</html>