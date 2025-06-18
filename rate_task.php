<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$role = getUserRole();
if ($role !== 'manager' && $role !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();
$message = '';
$error = '';

// Handle task rating
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rate_task') {
    $taskId = $_POST['task_id'] ?? '';
    $rating = $_POST['rating'] ?? '';
    $comment = $_POST['comment'] ?? '';
    
    if (!empty($taskId) && !empty($rating) && $rating >= 1 && $rating <= 5) {
        try {
            // Get task details
            $stmt = $conn->prepare("
                SELECT t.*, u.id as employee_id 
                FROM tasks t 
                JOIN users u ON t.assigned_to = u.id 
                WHERE t.id = ? AND t.assigned_by = ? AND t.status = 'completed'
            ");
            $stmt->execute([$taskId, $userId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($task) {
                // Check if task is already rated
                $checkStmt = $conn->prepare("SELECT id FROM task_ratings WHERE task_id = ?");
                $checkStmt->execute([$taskId]);
                if ($checkStmt->fetch()) {
                    $error = 'This task has already been rated.';
                } else {
                    // Calculate completion time rating (0-5)
                    $completionTimeRating = 0;
                    if ($task['status'] === 'completed') {
                        if ($task['updated_at'] <= $task['due_date']) {
                            $completionTimeRating = 5; // Completed on time
                        } else {
                            // Calculate days late
                            $dueDate = new DateTime($task['due_date']);
                            $completionDate = new DateTime($task['updated_at']);
                            $daysLate = $completionDate->diff($dueDate)->days;
                            
                            // Reduce rating based on days late (max 3 points reduction)
                            $completionTimeRating = max(2, 5 - min(3, $daysLate));
                        }
                    }
                    
                    // Calculate attendance rating (0-5)
                    $attendanceRating = 0;
                    $stmt = $conn->prepare("
                        SELECT 
                            COUNT(*) as total_days,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
                        FROM attendance 
                        WHERE employee_id = ? 
                        AND date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
                    ");
                    $stmt->execute([$task['employee_id']]);
                    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($attendance['total_days'] > 0) {
                        $attendanceRate = ($attendance['present_days'] / $attendance['total_days']) * 100;
                        $attendanceRating = min(5, max(0, $attendanceRate / 20)); // Convert percentage to 0-5 scale
                    }
                    
                    // Calculate overall rating (weighted average)
                    $overallRating = ($rating * 0.5) + ($completionTimeRating * 0.3) + ($attendanceRating * 0.2);
                    
                    // Insert rating into database
                    $stmt = $conn->prepare("
                        INSERT INTO task_ratings (
                            task_id, 
                            employee_id, 
                            manager_id, 
                            rating, 
                            comment, 
                            completed_on_time,
                            completion_time_rating,
                            attendance_rating,
                            overall_rating,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $completedOnTime = $task['updated_at'] <= $task['due_date'];
                    $stmt->execute([
                        $taskId,
                        $task['employee_id'],
                        $_SESSION['user_id'],
                        $rating,
                        $comment,
                        $completedOnTime,
                        $completionTimeRating,
                        $attendanceRating,
                        $overallRating
                    ]);
                    
                    // Update employee's average rating
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET average_rating = (
                            SELECT COALESCE(AVG(overall_rating), 0)
                            FROM task_ratings
                            WHERE employee_id = ?
                        )
                        WHERE id = ?
                    ");
                    $stmt->execute([$task['employee_id'], $task['employee_id']]);
                    
                    // Send notification to employee
                    $notificationMessage = sprintf(
                        "Your task '%s' has been rated %.1f/5. Overall performance rating: %.1f/5",
                        $task['title'],
                        $rating,
                        $overallRating
                    );
                    
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, message, type, reference_id, created_at)
                        VALUES (?, ?, 'task_rating', ?, NOW())
                    ");
                    $stmt->execute([$task['employee_id'], $notificationMessage, $taskId]);
                    
                    // Redirect back to tasks page with success message
                    $_SESSION['success'] = "Task rated successfully!";
                    header("Location: tasks.php");
                    exit();
                }
            } else {
                $error = 'Invalid task or task not completed.';
            }
        } catch (Exception $e) {
            error_log("Error rating task: " . $e->getMessage());
            $error = 'Task rated successfully!';
        }
    } else {
        $error = 'Please provide a valid rating (1-5).';
    }
}

// Get completed tasks that need rating
$stmt = $conn->prepare("
    SELECT t.*, 
           CONCAT(u.first_name, ' ', u.last_name) as employee_name,
           u.avatar as employee_avatar,
           CASE 
               WHEN t.updated_at <= t.due_date THEN 'On Time'
               ELSE 'Late'
           END as completion_status,
           DATEDIFF(t.updated_at, t.due_date) as days_difference
    FROM tasks t
    JOIN users u ON t.assigned_to = u.id
    WHERE t.assigned_by = ? 
    AND t.status = 'completed'
    AND NOT EXISTS (
        SELECT 1 
        FROM task_ratings tr 
        WHERE tr.task_id = t.id
    )
    ORDER BY t.due_date DESC
");
$stmt->execute([$userId]);
$tasksToRate = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-start mb-6">
        <h2 class="text-2xl font-bold">Rate Completed Tasks</h2>
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
    
    <?php if (empty($tasksToRate)): ?>
        <div class="text-center py-8">
            <p class="text-gray-500">No tasks need rating at the moment.</p>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($tasksToRate as $task): ?>
                <div class="bg-gray-50 rounded-lg p-6">
                    <div class="flex items-start space-x-4">
                        <?php if (!empty($task['employee_avatar']) && file_exists($task['employee_avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($task['employee_avatar']); ?>" 
                                 alt="Employee Avatar" 
                                 class="w-12 h-12 rounded-full object-cover">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-indigo-600 flex items-center justify-center">
                                <span class="text-white font-medium">
                                    <?php echo strtoupper(substr($task['employee_name'], 0, 1)); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        Task #<?php echo htmlspecialchars($task['task_number']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        Completed by <?php echo htmlspecialchars($task['employee_name']); ?>
                                    </p>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $task['completion_status'] === 'On Time' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $task['completion_status']; ?>
                                    </span>
                                    <?php if ($task['completion_status'] === 'Late'): ?>
                                        <span class="text-xs text-gray-500 mt-1">
                                            <?php echo abs($task['days_difference']); ?> days late
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($task['title']); ?></p>
                                <p class="mt-1 text-sm text-gray-500"><?php echo htmlspecialchars($task['description']); ?></p>
                            </div>
                            
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="action" value="rate_task">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Rating</label>
                                        <div class="mt-1 flex items-center space-x-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="rating" value="<?php echo $i; ?>" required
                                                        class="sr-only">
                                                    <span class="text-2xl text-gray-300 hover:text-yellow-400">★</span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="comment" class="block text-sm font-medium text-gray-700">Comment (Optional)</label>
                                        <textarea name="comment" id="comment" rows="3"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Add your feedback about the task completion..."></textarea>
                                    </div>
                                    
                                    <div class="flex justify-end">
                                        <button type="submit"
                                            class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                                            Submit Rating
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Add star rating interaction
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const stars = this.parentElement.parentElement.querySelectorAll('span');
        const rating = parseInt(this.value);
        
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.remove('text-gray-300');
                star.classList.add('text-yellow-400');
            } else {
                star.classList.remove('text-yellow-400');
                star.classList.add('text-gray-300');
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 