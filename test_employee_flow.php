<?php
/**
 * Test Employee Flow
 * 
 * This file demonstrates the complete employee flow:
 * 1. Employee logs in
 * 2. Employee is redirected to mark attendance (if not marked today)
 * 3. Employee marks attendance with video
 * 4. Employee is redirected to dashboard with success message
 * 5. Employee can access their tasks
 * 
 * To test this flow:
 * 1. Login as an employee
 * 2. You will be redirected to mark_attendance.php if attendance not marked
 * 3. Mark attendance with video recording
 * 4. You will be redirected to dashboard.php with success message
 * 5. You can now access dashboard, my_tasks.php, and other employee pages
 */

require_once 'config/database.php';
require_once 'includes/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo "<h2>Please login first</h2>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
    exit();
}

$role = getUserRole();
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get user details
$stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check today's attendance
$stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()");
$stmt->execute([$userId]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Get task count
$stmt = $conn->prepare("SELECT COUNT(*) as task_count FROM tasks WHERE assigned_to = ?");
$stmt->execute([$userId]);
$taskCount = $stmt->fetch(PDO::FETCH_ASSOC)['task_count'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Flow Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Employee Flow Test</h1>
        
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Current User Status</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                    <p><strong>Role:</strong> <?php echo ucfirst($role); ?></p>
                    <p><strong>User ID:</strong> <?php echo $userId; ?></p>
                </div>
                <div>
                    <p><strong>Today's Attendance:</strong> 
                        <?php if ($attendance): ?>
                            <span class="text-green-600 font-semibold">Marked</span>
                        <?php else: ?>
                            <span class="text-red-600 font-semibold">Not Marked</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Assigned Tasks:</strong> <?php echo $taskCount; ?></p>
                </div>
            </div>
        </div>

        <?php if ($role === 'employee'): ?>
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Employee Flow Steps</h2>
                <div class="space-y-4">
                    <div class="flex items-center p-4 border rounded-lg <?php echo $attendance ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200'; ?>">
                        <div class="flex-shrink-0 mr-4">
                            <?php if ($attendance): ?>
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            <?php else: ?>
                                <i class="fas fa-clock text-yellow-500 text-xl"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-semibold">Step 1: Mark Attendance</h3>
                            <p class="text-sm text-gray-600">
                                <?php if ($attendance): ?>
                                    ✅ Attendance marked for today at <?php echo date('h:i A', strtotime($attendance['clock_in'])); ?>
                                <?php else: ?>
                                    ⏳ Need to mark attendance before accessing dashboard
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center p-4 border rounded-lg <?php echo $attendance ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'; ?>">
                        <div class="flex-shrink-0 mr-4">
                            <?php if ($attendance): ?>
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            <?php else: ?>
                                <i class="fas fa-lock text-gray-400 text-xl"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-semibold">Step 2: Access Dashboard</h3>
                            <p class="text-sm text-gray-600">
                                <?php if ($attendance): ?>
                                    ✅ Can access dashboard and view task statistics
                                <?php else: ?>
                                    🔒 Dashboard access restricted until attendance is marked
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center p-4 border rounded-lg <?php echo $attendance ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'; ?>">
                        <div class="flex-shrink-0 mr-4">
                            <?php if ($attendance): ?>
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            <?php else: ?>
                                <i class="fas fa-lock text-gray-400 text-xl"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-semibold">Step 3: Manage Tasks</h3>
                            <p class="text-sm text-gray-600">
                                <?php if ($attendance): ?>
                                    ✅ Can view and update assigned tasks
                                <?php else: ?>
                                    🔒 Task management restricted until attendance is marked
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php if (!$attendance): ?>
                        <a href="mark_attendance.php" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors text-center">
                            <i class="fas fa-calendar-check mr-2"></i>
                            Mark Attendance
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors text-center">
                            <i class="fas fa-tachometer-alt mr-2"></i>
                            Go to Dashboard
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($attendance): ?>
                        <a href="my_tasks.php" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors text-center">
                            <i class="fas fa-tasks mr-2"></i>
                            View My Tasks
                        </a>
                    <?php endif; ?>
                    
                    <a href="logout.php" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors text-center">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Logout
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                <h2 class="text-xl font-semibold text-yellow-800 mb-2">Not an Employee</h2>
                <p class="text-yellow-700">This test is designed for employees. You are logged in as a <?php echo ucfirst($role); ?>.</p>
                <div class="mt-4">
                    <a href="dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                        Go to Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 