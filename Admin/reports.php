<?php
require '../Includes/auth.php';
require '../Includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get counts for the dashboard
$total_employees = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch_assoc()['count'];
$total_heads = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'head_office'")->fetch_assoc()['count'];

// Get data for the charts
$role_data = [];
$role_query = $conn->query("SELECT role, COUNT(*) as count FROM users WHERE role != 'admin' GROUP BY role");
while ($row = $role_query->fetch_assoc()) {
    $role_data[] = $row;
}

// Convert data to JSON for chart.js
$chart_labels = [];
$chart_data = [];
$chart_colors = [];

foreach ($role_data as $data) {
    $chart_labels[] = ucfirst($data['role']);
    $chart_data[] = $data['count'];
    
    // Add different colors for each role
    if ($data['role'] === 'employee') {
        $chart_colors[] = 'rgba(59, 130, 246, 0.7)'; // Blue
    } else if ($data['role'] === 'head_office') {
        $chart_colors[] = 'rgba(16, 185, 129, 0.7)'; // Green
    } else {
        $chart_colors[] = 'rgba(107, 114, 128, 0.7)'; // Gray
    }
}

// Convert arrays to JSON for use in JavaScript
$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);
$chart_colors_json = json_encode($chart_colors);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Reports</title>
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
            <a href="admin_dashboard.php" class="flex items-center px-4 py-2 text-indigo-100 hover:bg-indigo-700 rounded-md transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Manage Users</span>
            </a>
            <a href="reports.php" class="flex items-center px-4 py-2 bg-indigo-800 rounded-md">
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Reports</h1>
            <p class="text-gray-600 mb-6">Overview of employee statistics</p>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Total Employees Card -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Employees</p>
                            <p class="text-3xl font-bold text-gray-900"><?= $total_employees ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Total Head Office Card -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Head Office Staff</p>
                            <p class="text-3xl font-bold text-gray-900"><?= $total_heads ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Pie Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Employee Breakdown</h2>
                    <div class="h-64">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>

                <!-- Bar Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Employee Distribution</h2>
                    <div class="h-64">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Chart data
        const labels = <?= $chart_labels_json ?>;
        const data = <?= $chart_data_json ?>;
        const colors = <?= $chart_colors_json ?>;
        
        // Setup pie chart
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        const pieChart = new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderColor: colors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Setup bar chart
        const barCtx = document.getElementById('barChart').getContext('2d');
        const barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Number of Users',
                    data: data,
                    backgroundColor: colors,
                    borderColor: colors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>