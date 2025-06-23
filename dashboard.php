<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$role = getUserRole();
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Check for attendance success message
$attendanceSuccess = isset($_GET['attendance_success']) && $_GET['attendance_success'] == '1';

// Example statistics (replace with your actual queries)
// $totalEmployees = 1; // Example
// $totalTasks = 0;
// $dueToday = 0;
// $overdue = 0;
// $present = 0;
// $late = 0;
// $absent = 0;
// $halfDay = 0;
// $pending = 0;
// $inProgress = 0;
// $completed = 0;
// $recentTasks = [];

// Get statistics based on user role
if ($role === 'employee') {
    // Get employee's attendance statistics for today
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'half-day' THEN 1 ELSE 0 END) as half_days
        FROM attendance 
        WHERE employee_id = ? AND date = CURDATE()
    ");
    $stmt->execute([$userId]);
    $attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get employee's task statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as in_progress_tasks,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM tasks 
        WHERE assigned_to = ?
    ");
    $stmt->execute([$userId]);
    $taskStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent tasks for this employee
    $stmt = $conn->prepare("
        SELECT t.*, 
            CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
        FROM tasks t
        JOIN users u ON t.assigned_to = u.id
        WHERE t.assigned_to = ?
        ORDER BY t.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get today's attendance record for employee
    $stmt = $conn->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? AND date = CURDATE()
    ");
    $stmt->execute([$userId]);
    $todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

} else {
    // Get overall attendance statistics for today
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN status = 'half-day' THEN 1 ELSE 0 END) as half_day_count
        FROM attendance 
        WHERE date = CURDATE()
    ");
    $stmt->execute();
    $attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get overall task statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as in_progress_tasks,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM tasks
    ");
    $stmt->execute();
    $taskStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent tasks
    $stmt = $conn->prepare("
        SELECT t.*, 
            CONCAT(u2.first_name, ' ', u2.last_name) as assigned_to_name
        FROM tasks t
        JOIN users u2 ON t.assigned_to = u2.id
        ORDER BY t.due_date ASC
        LIMIT 5
    ");
    $stmt->execute();
    $recentTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    .theme-primary {
        background: linear-gradient(90deg, var(--accent-primary) 0%, var(--accent-secondary) 100%) !important;
    }
    .theme-primary-text {
        color: var(--accent-primary) !important;
    }
    .theme-primary-bg {
        background-color: var(--accent-primary) !important;
        color: #fff !important;
    }
    .theme-primary-border {
        border-color: var(--accent-primary) !important;
    }

    /* Dark mode specific overrides for dashboard */
    .dark .bg-gray-50 {
        background-color: var(--bg-tertiary) !important;
    }

    .dark .bg-green-50 {
        background-color: rgba(34, 197, 94, 0.1) !important;
    }

    .dark .bg-yellow-50 {
        background-color: rgba(234, 179, 8, 0.1) !important;
    }

    .dark .bg-red-50 {
        background-color: rgba(239, 68, 68, 0.1) !important;
    }

    .dark .bg-orange-50 {
        background-color: rgba(249, 115, 22, 0.1) !important;
    }

    .dark .bg-blue-50 {
        background-color: rgba(59, 130, 246, 0.1) !important;
    }

    .dark .bg-green-100 {
        background-color: rgba(34, 197, 94, 0.2) !important;
    }

    .dark .bg-yellow-100 {
        background-color: rgba(234, 179, 8, 0.2) !important;
    }

    .dark .bg-red-100 {
        background-color: rgba(239, 68, 68, 0.2) !important;
    }

    .dark .bg-blue-100 {
        background-color: rgba(59, 130, 246, 0.2) !important;
    }

    .dark .text-green-600 {
        color: #22c55e !important;
    }

    .dark .text-yellow-600 {
        color: #eab308 !important;
    }

    .dark .text-red-600 {
        color: #ef4444 !important;
    }

    .dark .text-blue-600 {
        color: #3b82f6 !important;
    }

    .dark .text-orange-600 {
        color: #f97316 !important;
    }

    /* Progress bars in dark mode */
    .dark .bg-gray-200 {
        background-color: var(--bg-tertiary) !important;
    }

    /* Card shadows in dark mode */
    .dark .shadow {
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3), 0 1px 2px 0 rgba(0, 0, 0, 0.2) !important;
    }

    .dark .shadow-lg {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2) !important;
    }
</style>
<div class="space-y-6">
    <!-- Success Message for Attendance -->
    <?php if ($attendanceSuccess): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span class="block sm:inline">Attendance marked successfully! Welcome to your dashboard.</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Welcome Banner -->
    <div class="rounded-lg p-6 mb-4 theme-primary">
        <h2 class="text-2xl md:text-3xl font-bold text-white">Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h2>
        <p class="text-white text-lg mt-1">Here's what's happening today.</p>
    </div>

    <?php if ($role === 'employee' && $todayAttendance): ?>
        <!-- Employee Today's Attendance Status -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 theme-primary-text">Today's Attendance</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-center">
                    <div class="bg-green-100 text-green-600 rounded-full p-3 mr-4">
                        <i class="fas fa-sign-in-alt fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-gray-500 text-sm">Clock In</div>
                        <div class="text-lg font-semibold"><?php echo date('h:i A', strtotime($todayAttendance['clock_in'])); ?></div>
                    </div>
                </div>
                <?php if ($todayAttendance['clock_out']): ?>
                    <div class="flex items-center">
                        <div class="bg-red-100 text-red-600 rounded-full p-3 mr-4">
                            <i class="fas fa-sign-out-alt fa-lg"></i>
                        </div>
                        <div>
                            <div class="text-gray-500 text-sm">Clock Out</div>
                            <div class="text-lg font-semibold"><?php echo date('h:i A', strtotime($todayAttendance['clock_out'])); ?></div>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="bg-blue-100 text-blue-600 rounded-full p-3 mr-4">
                            <i class="fas fa-clock fa-lg"></i>
                        </div>
                        <div>
                            <div class="text-gray-500 text-sm">Total Hours</div>
                            <div class="text-lg font-semibold">
                                <?php 
                                    $clockIn = new DateTime($todayAttendance['clock_in']);
                                    $clockOut = new DateTime($todayAttendance['clock_out']);
                                    $interval = $clockIn->diff($clockOut);
                                    echo $interval->format('%Hh %im');
                                ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center">
                        <div class="bg-yellow-100 text-yellow-600 rounded-full p-3 mr-4">
                            <i class="fas fa-clock fa-lg"></i>
                        </div>
                        <div>
                            <div class="text-gray-500 text-sm">Status</div>
                            <div class="text-lg font-semibold">Working</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-4">
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php
                    switch($todayAttendance['status']) {
                        case 'present': echo 'bg-green-100 text-green-800'; break;
                        case 'late': echo 'bg-yellow-100 text-yellow-800'; break;
                        case 'absent': echo 'bg-red-100 text-red-800'; break;
                        case 'half-day': echo 'bg-blue-100 text-blue-800'; break;
                    }
                ?>">
                    <?php echo ucfirst($todayAttendance['status']); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="flex items-center bg-white rounded-lg shadow p-4">
            <div class="theme-primary-bg rounded-full p-3">
                <i class="fas fa-list-check fa-lg"></i>
            </div>
            <div class="ml-4">
                <div class="text-gray-500 text-sm">Total Tasks</div>
                <div class="text-2xl font-bold theme-primary-text"><?php echo isset($taskStats['total_tasks']) ? $taskStats['total_tasks'] : 0; ?></div>
            </div>
        </div>
        <div class="flex items-center bg-white rounded-lg shadow p-4">
            <div class="theme-primary-bg rounded-full p-3">
                <i class="fas fa-clock fa-lg"></i>
            </div>
            <div class="ml-4">
                <div class="text-gray-500 text-sm">Pending</div>
                <div class="text-2xl font-bold theme-primary-text"><?php echo isset($taskStats['pending_tasks']) ? $taskStats['pending_tasks'] : 0; ?></div>
            </div>
        </div>
        <div class="flex items-center bg-white rounded-lg shadow p-4">
            <div class="theme-primary-bg rounded-full p-3">
                <i class="fas fa-check-circle fa-lg"></i>
            </div>
            <div class="ml-4">
                <div class="text-gray-500 text-sm">Completed</div>
                <div class="text-2xl font-bold theme-primary-text"><?php echo isset($taskStats['completed_tasks']) ? $taskStats['completed_tasks'] : 0; ?></div>
            </div>
        </div>
    </div>

    <!-- Task Progress & Recent Tasks -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-lg shadow p-4 md:col-span-2">
            <div class="font-semibold theme-primary-text mb-2">Task Progress</div>
            <div class="mb-2 flex justify-between text-sm text-gray-500">
                <span>Pending</span><span><?php echo isset($taskStats['pending_tasks']) ? $taskStats['pending_tasks'] : 0; ?></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mb-4">
                <div class="theme-primary-bg h-2.5 rounded-full" style="width: <?php echo ($taskStats['total_tasks'] > 0) ? ($taskStats['pending_tasks'] / $taskStats['total_tasks'] * 100) : 0; ?>%"></div>
            </div>
            <div class="mb-2 flex justify-between text-sm text-gray-500">
                <span>In Progress</span><span><?php echo isset($taskStats['in_progress_tasks']) ? $taskStats['in_progress_tasks'] : 0; ?></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mb-4">
                <div class="theme-primary-bg h-2.5 rounded-full" style="width: <?php echo ($taskStats['total_tasks'] > 0) ? ($taskStats['in_progress_tasks'] / $taskStats['total_tasks'] * 100) : 0; ?>%"></div>
            </div>
            <div class="mb-2 flex justify-between text-sm text-gray-500">
                <span>Completed</span><span><?php echo isset($taskStats['completed_tasks']) ? $taskStats['completed_tasks'] : 0; ?></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="theme-primary-bg h-2.5 rounded-full" style="width: <?php echo ($taskStats['total_tasks'] > 0) ? ($taskStats['completed_tasks'] / $taskStats['total_tasks'] * 100) : 0; ?>%"></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center mb-2">
                <div class="font-semibold theme-primary-text">Recent Tasks</div>
                <a href="my_tasks.php" class="theme-primary-text text-sm hover:underline">View All</a>
            </div>
            <div class="text-gray-500 text-sm">
                <?php if (empty($recentTasks)): ?>
                    No recent tasks.
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($recentTasks as $task): ?>
                            <li class="flex justify-between items-center border-b border-gray-100 pb-2">
                                <div>
                                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($task['title']); ?></span>
                                    <span class="ml-2 text-xs text-gray-500">Due: <?php echo htmlspecialchars($task['due_date']); ?></span>
                                </div>
                                <span class="text-xs px-2 py-1 rounded <?php
                                    switch($task['status']) {
                                        case 'completed': echo 'bg-green-100 text-green-800'; break;
                                        case 'in-progress': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                ?>"><?php echo ucfirst($task['status']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Attendance Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="rounded-lg p-4 bg-green-50 flex items-center">
            <div class="bg-green-100 text-green-600 rounded-full p-3 mr-4">
                <i class="fas fa-user-check fa-lg"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Present</div>
                <div class="text-2xl font-bold"><?php echo isset($attendanceStats['present_count']) ? $attendanceStats['present_count'] : 0; ?></div>
            </div>
        </div>
        <div class="rounded-lg p-4 bg-yellow-50 flex items-center">
            <div class="bg-yellow-100 text-yellow-600 rounded-full p-3 mr-4">
                <i class="fas fa-user-clock fa-lg"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Late</div>
                <div class="text-2xl font-bold"><?php echo isset($attendanceStats['late_count']) ? $attendanceStats['late_count'] : 0; ?></div>
            </div>
        </div>
        <div class="rounded-lg p-4 bg-red-50 flex items-center">
            <div class="bg-red-100 text-red-600 rounded-full p-3 mr-4">
                <i class="fas fa-user-times fa-lg"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Absent</div>
                <div class="text-2xl font-bold"><?php echo isset($attendanceStats['absent_count']) ? $attendanceStats['absent_count'] : 0; ?></div>
            </div>
        </div>
        <div class="rounded-lg p-4 bg-orange-50 flex items-center">
            <div class="bg-orange-100 text-orange-600 rounded-full p-3 mr-4">
                <i class="fas fa-user-friends fa-lg"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Half Day</div>
                <div class="text-2xl font-bold"><?php echo isset($attendanceStats['half_day_count']) ? $attendanceStats['half_day_count'] : 0; ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 