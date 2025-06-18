<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$userId = $_SESSION['user_id'];
$conn = getDBConnection();
$message = '';
$error = '';

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_avatar') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $fileName = basename($file['name']);
        $fileType = $file['type'];
        $fileSize = $file['size'];
        $fileTmpPath = $file['tmp_name'];
        
        error_log("Avatar upload attempt - File: $fileName, Type: $fileType, Size: $fileSize");
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($fileType, $allowedTypes)) {
            $error = 'Please upload a valid image file (JPEG, PNG, or GIF).';
            error_log("Invalid file type: $fileType");
        } else if ($fileSize > 5 * 1024 * 1024) {
            $error = 'File size too large. Maximum size is 5MB.';
            error_log("File too large: $fileSize bytes");
        } else {
            $uploadDir = 'uploads/avatars';
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    $error = 'Failed to create upload directory.';
                    error_log("Failed to create directory: $uploadDir");
                }
            }
            
            if (!isset($error)) {
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . '/' . $uniqueFileName;
                
                if (move_uploaded_file($fileTmpPath, $uploadPath)) {
                    try {
                        $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $oldAvatar = $stmt->fetchColumn();
                        
                        if ($oldAvatar && $oldAvatar !== 'default-avatar.png' && file_exists($oldAvatar)) {
                            unlink($oldAvatar);
                        }
                        
                        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                        $stmt->execute([$uploadPath, $userId]);
                        $message = 'Avatar updated successfully!';
                        error_log("Avatar updated successfully: $uploadPath");
                    } catch (Exception $e) {
                        $error = 'Error updating avatar in database.';
                        error_log("Database error: " . $e->getMessage());
                        unlink($uploadPath);
                    }
                } else {
                    $error = 'Error uploading file. Please try again.';
                    error_log("Failed to move uploaded file to: $uploadPath");
                }
            }
        }
    } else {
        $error = 'Please select a file to upload.';
        if (isset($_FILES['avatar'])) {
            error_log("Upload error code: " . $_FILES['avatar']['error']);
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate input
    if (empty($firstName) || empty($lastName)) {
        $error = 'First name and last name are required.';
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Update name
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $userId]);
            
            // If password update is requested
            if (!empty($currentPassword)) {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $currentHash = $stmt->fetchColumn();
                
                if (!password_verify($currentPassword, $currentHash)) {
                    throw new Exception('Current password is incorrect.');
                }
                
                // Validate new password
                if (empty($newPassword)) {
                    throw new Exception('New password is required when changing password.');
                }
                
                if ($newPassword !== $confirmPassword) {
                    throw new Exception('New passwords do not match.');
                }
                
                if (strlen($newPassword) < 8) {
                    throw new Exception('Password must be at least 8 characters long.');
                }
                
                // Update password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$newHash, $userId]);
            }
            
            $conn->commit();
            $message = 'Profile updated successfully!';
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Get user information
$stmt = $conn->prepare("
    SELECT u.*, r.role_name,
           (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed') as completed_tasks,
           (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as total_tasks
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get employee performance metrics
$stmt = $conn->prepare("
    SELECT 
        u.id as employee_id,
        u.first_name,
        u.last_name,
        COUNT(t.id) as total_tasks_assigned,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as total_tasks_completed,
        SUM(CASE 
            WHEN t.status = 'completed' AND t.updated_at <= t.due_date THEN 1 
            ELSE 0 
        END) as tasks_completed_on_time,
        SUM(CASE 
            WHEN t.status = 'completed' AND t.updated_at > t.due_date THEN 1 
            ELSE 0 
        END) as tasks_completed_late,
        (
            SELECT COUNT(*) 
            FROM attendance a 
            WHERE a.employee_id = u.id 
            AND a.status = 'present'
        ) as present_days,
        (
            SELECT COUNT(*) 
            FROM attendance a 
            WHERE a.employee_id = u.id
        ) as total_working_days,
        COALESCE(AVG(tr.overall_rating), 0) as average_task_rating,
        COALESCE(AVG(tr.completion_time_rating), 0) as average_completion_time_rating,
        COALESCE(AVG(tr.attendance_rating), 0) as average_attendance_rating
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
    LEFT JOIN task_ratings tr ON t.id = tr.task_id
    WHERE u.id = ?
    GROUP BY u.id, u.first_name, u.last_name
");
$stmt->execute([$userId]);
$performanceMetrics = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate completion rate
$completionRate = $performanceMetrics['total_tasks_assigned'] > 0 
    ? ($performanceMetrics['total_tasks_completed'] / $performanceMetrics['total_tasks_assigned']) * 100 
    : 0;

// Calculate on-time completion rate
$onTimeRate = $performanceMetrics['total_tasks_completed'] > 0 
    ? ($performanceMetrics['tasks_completed_on_time'] / $performanceMetrics['total_tasks_completed']) * 100 
    : 0;

// Calculate attendance rate
$attendanceRate = $performanceMetrics['total_working_days'] > 0 
    ? ($performanceMetrics['present_days'] / $performanceMetrics['total_working_days']) * 100 
    : 0;

// Get recent ratings with details
$stmt = $conn->prepare("
    SELECT tr.*, t.title as task_title, 
           CONCAT('TT-', DATE_FORMAT(t.created_at, '%d-%m-%Y'), '-', t.id) as task_number,
           CONCAT(m.first_name, ' ', m.last_name) as manager_name,
           CASE 
               WHEN tr.completed_on_time THEN 'On Time'
               ELSE 'Late'
           END as completion_status
    FROM task_ratings tr
    JOIN tasks t ON tr.task_id = t.id
    JOIN users m ON tr.manager_id = m.id
    WHERE tr.employee_id = ?
    ORDER BY tr.created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$recentRatings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top rated employees (for comparison)
$stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.avatar, u.average_rating, u.total_ratings
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE r.role_name = 'employee'
    ORDER BY u.average_rating DESC, u.total_ratings DESC
    LIMIT 5
");
$stmt->execute();
$topEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees list with ratings for managers and admins
if ($user['role_name'] === 'manager' || $user['role_name'] === 'admin') {
    $stmt = $conn->prepare("
        SELECT u.id, u.first_name, u.last_name, u.employee_number, u.average_rating, u.total_ratings,
               (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed') as completed_tasks,
               (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as total_tasks
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.role_name = 'employee'
        ORDER BY u.average_rating DESC, u.total_ratings DESC
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-2xl font-bold">User Profile</h2>
        <a href="dashboard.php" class="text-indigo-600 hover:text-indigo-900">Back to Dashboard</a>
    </div>
    
    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Profile Information -->
        <div class="md:col-span-1">
            <div class="bg-gray-50 rounded-lg p-6">
                <div class="text-center mb-6">
                    <div class="w-32 h-32 rounded-full bg-indigo-600 mx-auto mb-4 flex items-center justify-center">
                        <span class="text-4xl font-bold text-white">
                            <?php 
                            echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); 
                            ?>
                        </span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </h3>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars(ucfirst($user['role_name'])); ?></p>
                </div>
                
                <!-- Edit Profile Form -->
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="first_name" id="first_name" 
                               value="<?php echo htmlspecialchars($user['first_name']); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="last_name" id="last_name" 
                               value="<?php echo htmlspecialchars($user['last_name']); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    
                    <div class="pt-4 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Change Password</h4>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                                <input type="password" name="current_password" id="current_password"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                                <input type="password" name="new_password" id="new_password"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-4">
                        <button type="submit" 
                                class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Update Profile
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Employee Number</p>
                        <p class="mt-1"><?php echo htmlspecialchars($user['employee_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Email</p>
                        <p class="mt-1"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance Metrics -->
        <div class="md:col-span-2">
            <?php if ($user['role_name'] === 'employee'): ?>
            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Performance Metrics</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-lg p-4 shadow">
                        <p class="text-sm font-medium text-gray-500">Overall Rating</p>
                        <div class="mt-2 flex items-center">
                            <span class="text-2xl font-bold"><?php echo number_format($performanceMetrics['average_task_rating'], 1); ?></span>
                            <span class="ml-2 text-yellow-400">★</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                            Based on <?php echo $performanceMetrics['total_tasks_completed']; ?> completed tasks
                        </p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-4 shadow">
                        <p class="text-sm font-medium text-gray-500">Task Completion</p>
                        <p class="mt-2 text-2xl font-bold"><?php echo round($completionRate); ?>%</p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo $performanceMetrics['total_tasks_completed']; ?> of <?php echo $performanceMetrics['total_tasks_assigned']; ?> tasks
                        </p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-4 shadow">
                        <p class="text-sm font-medium text-gray-500">On-Time Completion</p>
                        <p class="mt-2 text-2xl font-bold"><?php echo round($onTimeRate); ?>%</p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo $performanceMetrics['tasks_completed_on_time']; ?> on time, 
                            <?php echo $performanceMetrics['tasks_completed_late']; ?> late
                        </p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-4 shadow">
                        <p class="text-sm font-medium text-gray-500">Attendance Rate</p>
                        <p class="mt-2 text-2xl font-bold"><?php echo round($attendanceRate); ?>%</p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo $performanceMetrics['present_days']; ?> present days of 
                            <?php echo $performanceMetrics['total_working_days']; ?> working days
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Ratings -->
            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Recent Ratings</h3>
                <?php if (!empty($recentRatings)): ?>
                    <div class="space-y-4">
                        <?php foreach ($recentRatings as $rating): ?>
                            <div class="bg-white rounded-lg p-4 shadow">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-900">Task #<?php echo htmlspecialchars($rating['task_number']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($rating['task_title']); ?></p>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-yellow-400 mr-1">★</span>
                                        <span class="font-medium"><?php echo $rating['rating']; ?>/5</span>
                                    </div>
                                </div>
                                <div class="mt-2 grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">Overall Rating</p>
                                        <p class="font-medium"><?php echo number_format($rating['overall_rating'], 1); ?>/5</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Completion</p>
                                        <p class="font-medium <?php echo $rating['completion_status'] === 'On Time' ? 'text-green-600' : 'text-yellow-600'; ?>">
                                            <?php echo $rating['completion_status']; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Attendance</p>
                                        <p class="font-medium"><?php echo number_format($rating['attendance_rating'], 1); ?>/5</p>
                                    </div>
                                </div>
                                <?php if ($rating['comment']): ?>
                                    <p class="mt-2 text-sm text-gray-600"><?php echo htmlspecialchars($rating['comment']); ?></p>
                                <?php endif; ?>
                                <p class="mt-2 text-xs text-gray-500">
                                    Rated by <?php echo htmlspecialchars($rating['manager_name']); ?> on 
                                    <?php echo date('M d, Y', strtotime($rating['created_at'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">No ratings yet.</p>
                <?php endif; ?>
            </div>
            
            <?php if ($user['role_name'] === 'employee'): ?>
            <!-- Top Rated Employees -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Top Rated Employees</h3>
                <div class="space-y-4">
                    <?php foreach ($topEmployees as $index => $employee): ?>
                        <div class="bg-white rounded-lg p-4 shadow <?php echo $employee['id'] === $userId ? 'ring-2 ring-indigo-500' : ''; ?>">
                            <div class="flex items-center">
                                <span class="text-lg font-bold text-gray-500 mr-4">#<?php echo $index + 1; ?></span>
                                <?php if (!empty($employee['avatar']) && file_exists($employee['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($employee['avatar']); ?>" 
                                         alt="Employee Avatar" 
                                         class="w-10 h-10 rounded-full mr-4 object-cover">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-indigo-600 mr-4 flex items-center justify-center">
                                        <span class="text-white font-medium">
                                            <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <p class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                        <?php if ($employee['id'] === $userId): ?>
                                            <span class="text-sm text-indigo-600">(You)</span>
                                        <?php endif; ?>
                                    </p>
                                    <div class="flex items-center mt-1">
                                        <span class="text-yellow-400 mr-1">★</span>
                                        <span class="text-sm text-gray-600">
                                            <?php echo number_format($employee['average_rating'], 1); ?> 
                                            (<?php echo $employee['total_ratings']; ?> ratings)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($user['role_name'] === 'manager' || $user['role_name'] === 'admin'): ?>
            <!-- Employees List -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Employee Performance Overview</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tasks</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($employees as $employee): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-indigo-600 rounded-full flex items-center justify-center">
                                                <span class="text-white font-medium">
                                                    <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($employee['employee_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-yellow-400 mr-1">★</span>
                                            <span class="text-sm text-gray-900">
                                                <?php echo number_format($employee['average_rating'], 1); ?>
                                            </span>
                                            <span class="text-xs text-gray-500 ml-1">
                                                (<?php echo $employee['total_ratings']; ?> ratings)
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $employee['completed_tasks']; ?>/<?php echo $employee['total_tasks']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        $completionRate = $employee['total_tasks'] > 0 
                                            ? round(($employee['completed_tasks'] / $employee['total_tasks']) * 100) 
                                            : 0;
                                        echo $completionRate . '%';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 