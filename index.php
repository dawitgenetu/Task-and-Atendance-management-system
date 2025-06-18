<?php
require_once 'config/database.php';
require_once 'includes/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amhara Media Corporation - Work & Attendance Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .hero-section {
            background: linear-gradient(135deg, #ff4b4b 0%, #ff0000 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <img src="assets/logo.jpg" alt="AMC Logo" class="h-10 w-10 object-contain">
                    <span class="ml-2 text-xl font-semibold text-gray-900">AMECO</span>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" class="text-gray-700 hover:text-red-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="logout.php" class="bg-red-600 text-white hover:bg-red-700 px-4 py-2 rounded-md text-sm font-medium">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-red-600 px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-white mb-4">
                    Amhara Media Corporation
                </h1>
                <p class="text-xl text-white/90 mb-8">
                    Work & Attendance Management System
                </p>
                <?php if (!isLoggedIn()): ?>
                    <a href="login.php" class="inline-block bg-white text-red-600 hover:bg-gray-100 px-8 py-3 rounded-lg font-medium text-lg shadow-lg transition-colors duration-200">
                        Get Started
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="glass-effect rounded-xl p-6">
                <div class="text-red-600 mb-4">
                    <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Time Tracking</h3>
                <p class="text-gray-600">Efficiently track employee attendance and working hours with our advanced time tracking system.</p>
            </div>

            <!-- Feature 2 -->
            <div class="glass-effect rounded-xl p-6">
                <div class="text-red-600 mb-4">
                    <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Leave Management</h3>
                <p class="text-gray-600">Streamline leave requests and approvals with our comprehensive leave management system.</p>
            </div>

            <!-- Feature 3 -->
            <div class="glass-effect rounded-xl p-6">
                <div class="text-red-600 mb-4">
                    <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Reports & Analytics</h3>
                <p class="text-gray-600">Generate detailed reports and analytics to make informed decisions about workforce management.</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="text-gray-400">&copy; <?php echo date('Y'); ?> Amhara Media Corporation. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html> 